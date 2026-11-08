<?php

namespace App\Services\Finance;

use App\Exceptions\AcademicRuleException;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SchoolPaymentMethod;
use App\Models\User;
use App\Notifications\SchoolAnnouncement;
use App\Services\Ops\Ops;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * School-fee payments. Money counts only when CONFIRMED.
 *
 *   staff record   (payments.record)  -> confirmed immediately (cash at the desk, a Wave / bank slip the officer has checked)
 *   family submit  (a parent / learner) -> PENDING until someone with payments.verify confirms or rejects it
 *   void           (fees.approve)     -> a confirmed payment cancelled with a reason; its receipt stays on file, marked void
 *
 * No money moves through this application: a person confirms every payment. Wave / card gateway automation for school
 * fees is not implemented; the platform's own SaaS billing gateways are a separate system.
 */
class PaymentService
{
    public const STAFF_METHODS = ['cash', 'wave', 'bank_transfer', 'card', 'other'];

    public const REFERENCED = ['wave', 'bank_transfer', 'card'];

    public function __construct(private readonly InvoiceService $invoices) {}

    // ------------------------------------------------------------------ recording

    public function record(Invoice $invoice, int $amount, string $method, ?string $paidOn, ?string $reference, ?string $payer, ?string $notes, User $actor): Payment
    {
        return DB::transaction(function () use ($invoice, $amount, $method, $paidOn, $reference, $payer, $notes, $actor) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $this->assertPayable($invoice);
            $this->assertMethod($method, $reference);
            $this->assertAmount($invoice, $amount);

            $payment = Payment::query()->create([
                'invoice_id' => $invoice->id, 'student_id' => $invoice->student_id, 'amount' => $amount, 'method' => $method,
                'external_reference' => $this->cleanReference($reference), 'payer_name' => $this->clip($payer, 150), 'notes' => $this->clip($notes, 500),
                'paid_at' => $paidOn ?: now(), 'recorded_by' => $actor->id, 'status' => 'confirmed',
                'decided_by' => $actor->id, 'decided_at' => now(),
                'receipt_number' => Ops::nextNumber('payments', 'RCT', 'receipt_number'),
            ]);
            $this->invoices->recalculate($invoice);

            Ops::audit('fin.payment.recorded', 'Payment '.$payment->receipt_number.' recorded', ['payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'amount' => $amount, 'method' => $method]);

            return $payment;
        });
    }

    /** A parent or learner reports a payment made outside the application; it waits for confirmation. */
    public function submit(Invoice $invoice, int $amount, string $method, string $reference, ?string $payer, ?string $notes, ?UploadedFile $evidence, User $actor): Payment
    {
        if (! app(\App\Services\Ops\OpsSettings::class)->get('finance', 'parent_submissions', true)) {
            throw AcademicRuleException::because('err.fin.submissionsOff');
        }
        if ($method === 'cash') {
            throw AcademicRuleException::because('err.fin.cashAtDesk');
        }

        $active = SchoolPaymentMethod::query()->where('is_active', true)->where('method', $method)->exists();
        if (! $active) {
            throw AcademicRuleException::because('err.fin.methodOff');
        }

        $path = null;
        if ($evidence) {
            $path = $evidence->storeAs('fee-evidence/'.$invoice->school_id, Str::uuid().'.'.$evidence->extension(), 'local');
        }

        try {
            return DB::transaction(function () use ($invoice, $amount, $method, $reference, $payer, $notes, $path, $actor) {
                $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
                $this->assertPayable($invoice);
                $this->assertMethod($method, $reference);
                $pending = (int) $invoice->payments()->where('status', 'pending')->sum('amount');
                $this->assertAmount($invoice, $amount, $pending);

                $payment = Payment::query()->create([
                    'invoice_id' => $invoice->id, 'student_id' => $invoice->student_id, 'amount' => $amount, 'method' => $method,
                    'external_reference' => $this->cleanReference($reference), 'payer_name' => $this->clip($payer, 150), 'notes' => $this->clip($notes, 500),
                    'paid_at' => now(), 'submitted_by' => $actor->id, 'status' => 'pending', 'evidence_path' => $path,
                    'receipt_number' => 'PEND-'.Str::upper(Str::random(10)),
                ]);

                Ops::audit('fin.payment.submitted', 'Payment submitted for '.$invoice->invoice_number, ['payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'amount' => $amount, 'method' => $method]);

                return $payment;
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $e;
        }
    }

    // ------------------------------------------------------------------ decisions

    public function confirm(Payment $payment, User $actor): Payment
    {
        return DB::transaction(function () use ($payment, $actor) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->status !== 'pending') {
                throw AcademicRuleException::because('err.fin.notPending');
            }
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($payment->invoice_id);
            $this->assertPayable($invoice);
            $this->assertAmount($invoice, (int) $payment->amount);

            $payment->forceFill([
                'status' => 'confirmed', 'decided_by' => $actor->id, 'decided_at' => now(), 'recorded_by' => $actor->id,
                'receipt_number' => Ops::nextNumber('payments', 'RCT', 'receipt_number'),
            ])->save();
            $this->invoices->recalculate($invoice);

            Ops::audit('fin.payment.confirmed', 'Payment '.$payment->receipt_number.' confirmed', ['payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'amount' => $payment->amount]);
            $this->tell($payment, 'Payment received', 'Your payment of '.Money::format((int) $payment->amount).' for invoice '.$invoice->invoice_number.' was confirmed. Receipt '.$payment->receipt_number.'.', 'success');

            return $payment;
        });
    }

    public function reject(Payment $payment, string $note, User $actor): Payment
    {
        $payment = Payment::query()->findOrFail($payment->id);
        if ($payment->status !== 'pending') {
            throw AcademicRuleException::because('err.fin.notPending');
        }
        $payment->forceFill(['status' => 'rejected', 'decided_by' => $actor->id, 'decided_at' => now(), 'decision_note' => $this->clip($note, 500)])->save();

        Ops::audit('fin.payment.rejected', 'Payment submission rejected', ['payment_id' => $payment->id, 'invoice_id' => $payment->invoice_id, 'amount' => $payment->amount]);
        $this->tell($payment, 'Payment not accepted', 'Your payment report of '.Money::format((int) $payment->amount).' was not accepted: '.$this->clip($note, 200), 'warning');

        return $payment;
    }

    public function void(Payment $payment, string $reason, User $actor): Payment
    {
        return DB::transaction(function () use ($payment, $reason, $actor) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->status !== 'confirmed') {
                throw AcademicRuleException::because('err.fin.notConfirmed');
            }
            if ($payment->is_reconciled) {
                throw AcademicRuleException::because('err.fin.reconciled');
            }

            $payment->forceFill(['status' => 'voided', 'voided_by' => $actor->id, 'voided_at' => now(), 'void_reason' => $this->clip($reason, 500)])->save();
            $this->invoices->recalculate(Invoice::query()->findOrFail($payment->invoice_id));

            Ops::audit('fin.payment.voided', 'Payment '.$payment->receipt_number.' voided', ['payment_id' => $payment->id, 'invoice_id' => $payment->invoice_id, 'amount' => $payment->amount, 'reason' => $this->clip($reason, 200)]);

            return $payment;
        });
    }

    // ------------------------------------------------------------------ rules

    private function assertPayable(Invoice $invoice): void
    {
        if (! in_array($invoice->status, ['issued', 'partial'], true)) {
            throw AcademicRuleException::because($invoice->status === 'cancelled' ? 'err.fin.invoiceCancelled' : 'err.fin.invoiceSettled');
        }
    }

    private function assertAmount(Invoice $invoice, int $amount, int $alsoPending = 0): void
    {
        if ($amount < 1) {
            throw AcademicRuleException::because('err.fin.amount');
        }
        if ($amount > $invoice->balance() - $alsoPending) {
            throw AcademicRuleException::because('err.fin.overpay');
        }
    }

    private function assertMethod(string $method, ?string $reference): void
    {
        if (! in_array($method, self::STAFF_METHODS, true)) {
            throw AcademicRuleException::because('err.fin.method');
        }
        if (in_array($method, self::REFERENCED, true)) {
            $ref = $this->cleanReference($reference);
            if ($ref === null) {
                throw AcademicRuleException::because('err.fin.referenceRequired');
            }
            $used = Payment::query()->where('method', $method)->where('external_reference', $ref)->whereIn('status', ['pending', 'confirmed'])->exists();
            if ($used) {
                throw AcademicRuleException::because('err.fin.referenceUsed');
            }
        }
    }

    private function cleanReference(?string $reference): ?string
    {
        $ref = trim((string) $reference);

        return $ref === '' ? null : mb_substr($ref, 0, 100);
    }

    private function clip(?string $text, int $max): ?string
    {
        $text = trim((string) $text);

        return $text === '' ? null : mb_substr($text, 0, $max);
    }

    private function tell(Payment $payment, string $title, string $body, string $level): void
    {
        if (! $payment->submitted_by) {
            return;
        }
        $user = User::query()->where('id', $payment->submitted_by)->where('school_id', $payment->school_id)->first();
        $user?->notify(new SchoolAnnouncement($title, $body, route('fees.index'), $level, 'fees', ['in_app', 'email']));
    }
}
