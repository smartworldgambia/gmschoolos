<?php

namespace App\Services\Finance;

use App\Exceptions\AcademicRuleException;
use App\Models\AcademicYear;
use App\Models\FeeAdjustment;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\InvoiceItem;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentFee;
use App\Models\Term;
use App\Models\User;
use App\Notifications\SchoolAnnouncement;
use App\Services\Ops\Ops;
use App\Services\Ops\OpsSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * School fee invoices (what parents owe the SCHOOL; unrelated to the school's SaaS subscription).
 *
 *   total = sum of fee lines - approved discounts / scholarships / waivers (never below zero)
 *   paid  = confirmed payments only (pending, rejected and voided payments never count)
 */
class InvoiceService
{
    public function __construct(private readonly OpsSettings $settings) {}

    // ------------------------------------------------------------------ generation

    /**
     * Invoice one learner for a year (and optionally a term) from the fee schedule, the learner's own pending charges and
     * their approved adjustments. Returns null when there is nothing to bill.
     *
     * @param  array<int,int>  $optionalStructureIds  optional fee lines the family chose
     */
    public function generate(Student $student, AcademicYear $year, ?Term $term, ?User $actor = null, array $optionalStructureIds = []): ?Invoice
    {
        if ($student->status !== 'active') {
            throw AcademicRuleException::because('err.fin.studentInactive');
        }

        if ($term && (int) $term->academic_year_id !== (int) $year->id) {
            throw AcademicRuleException::because('err.fin.termYear');
        }

        $exists = Invoice::query()
            ->where('student_id', $student->id)->where('academic_year_id', $year->id)->where('source', 'structure')
            ->where('term_id', $term?->id)->where('status', '!=', 'cancelled')->exists();
        if ($exists) {
            throw AcademicRuleException::because('err.fin.invoiceExists');
        }

        $structures = FeeStructure::query()
            ->where('academic_year_id', $year->id)->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('term_id')->when($term, fn ($q2) => $q2->orWhere('term_id', $term->id)))
            ->when($term === null, fn ($q) => $q->whereNull('term_id'))
            ->where(fn ($q) => $q->whereNull('school_class_id')->when($student->school_class_id, fn ($q2) => $q2->orWhere('school_class_id', $student->school_class_id)))
            ->where(fn ($q) => $q->where('is_optional', false)->orWhereIn('id', $optionalStructureIds ?: [0]))
            ->with('category')
            ->get();

        $charges = StudentFee::query()
            ->where('student_id', $student->id)->where('status', 'pending')
            ->where(fn ($q) => $q->whereNull('academic_year_id')->orWhere('academic_year_id', $year->id))
            ->where(fn ($q) => $q->whereNull('term_id')->when($term, fn ($q2) => $q2->orWhere('term_id', $term->id)))
            ->get();

        $lines = [];
        foreach ($structures as $s) {
            $lines[] = ['kind' => 'fee', 'description' => $s->name ?: $s->category?->name ?: 'Fee', 'amount' => (int) $s->amount, 'fee_category_id' => $s->fee_category_id, 'fee_structure_id' => $s->id];
        }
        foreach ($charges as $c) {
            $lines[] = ['kind' => 'fee', 'description' => $c->description, 'amount' => (int) $c->amount, 'fee_category_id' => $c->fee_category_id, 'student_fee_id' => $c->id];
        }

        $subtotal = (int) array_sum(array_column($lines, 'amount'));
        if ($subtotal <= 0) {
            return null;
        }

        [$reductions, $reduced] = $this->reductions($student, $year, $term, $lines, $subtotal);

        $invoice = Ops::numbered(function () use ($student, $year, $term, $actor, $lines, $reductions, $subtotal, $reduced, $charges) {
            $invoice = Invoice::query()->create([
                'student_id' => $student->id,
                'invoice_number' => Ops::nextNumber('invoices', (string) $this->settings->get('finance', 'invoice_prefix', 'INV'), 'invoice_number'),
                'academic_year_id' => $year->id, 'term_id' => $term?->id, 'source' => 'structure',
                'status' => 'issued', 'subtotal' => $subtotal, 'adjustments_total' => $reduced, 'total_amount' => $subtotal - $reduced,
                'amount_paid' => 0, 'issued_at' => now(), 'due_on' => now()->addDays((int) $this->settings->get('finance', 'due_days', 30))->toDateString(),
                'created_by' => $actor?->id,
            ]);

            foreach (array_merge($lines, $reductions) as $line) {
                InvoiceItem::query()->create($line + ['invoice_id' => $invoice->id]);
            }

            if ($charges->isNotEmpty()) {
                StudentFee::query()->whereIn('id', $charges->pluck('id'))->update(['status' => 'invoiced', 'invoice_id' => $invoice->id]);
            }

            return $this->recalculate($invoice);
        });

        Ops::audit('fin.invoice.generated', 'Invoice '.$invoice->invoice_number.' generated', ['invoice_id' => $invoice->id, 'student_id' => $student->id, 'total' => $invoice->total_amount]);
        $this->notifyGuardians($invoice, 'issued');

        return $invoice;
    }

    /** @return array{created:int,skipped:int,empty:int} */
    public function generateForClass(SchoolClass $class, AcademicYear $year, ?Term $term, ?User $actor = null): array
    {
        $result = ['created' => 0, 'skipped' => 0, 'empty' => 0];

        Student::query()->where('school_class_id', $class->id)->where('status', 'active')->orderBy('id')->each(function (Student $s) use ($year, $term, $actor, &$result) {
            try {
                $this->generate($s, $year, $term, $actor) ? $result['created']++ : $result['empty']++;
            } catch (AcademicRuleException $e) {
                $result['skipped']++;
            }
        });

        return $result;
    }

    /**
     * One-off invoice with lines typed by the finance officer (for a charge that is not on the fee schedule).
     *
     * @param  array<int,array{description:string,amount:int,fee_category_id?:?int}>  $lines
     */
    public function manual(Student $student, array $lines, ?string $dueOn, ?string $notes, User $actor): Invoice
    {
        if ($student->status !== 'active') {
            throw AcademicRuleException::because('err.fin.studentInactive');
        }

        $lines = array_values(array_filter($lines, fn ($l) => (int) ($l['amount'] ?? 0) > 0 && trim((string) ($l['description'] ?? '')) !== ''));
        if ($lines === []) {
            throw AcademicRuleException::because('err.fin.noLines');
        }
        $total = (int) array_sum(array_map(fn ($l) => (int) $l['amount'], $lines));

        $invoice = Ops::numbered(function () use ($student, $lines, $total, $dueOn, $notes, $actor) {
            $invoice = Invoice::query()->create([
                'student_id' => $student->id,
                'invoice_number' => Ops::nextNumber('invoices', (string) $this->settings->get('finance', 'invoice_prefix', 'INV'), 'invoice_number'),
                'source' => 'manual', 'status' => 'issued', 'subtotal' => $total, 'adjustments_total' => 0, 'total_amount' => $total,
                'amount_paid' => 0, 'issued_at' => now(), 'notes' => $notes ? mb_substr($notes, 0, 500) : null,
                'due_on' => $dueOn ?: now()->addDays((int) $this->settings->get('finance', 'due_days', 30))->toDateString(),
                'created_by' => $actor->id,
            ]);
            foreach ($lines as $l) {
                InvoiceItem::query()->create([
                    'invoice_id' => $invoice->id, 'kind' => 'fee', 'description' => mb_substr(trim($l['description']), 0, 190),
                    'amount' => (int) $l['amount'], 'fee_category_id' => $l['fee_category_id'] ?? null,
                ]);
            }

            return $invoice;
        });

        Ops::audit('fin.invoice.created', 'Invoice '.$invoice->invoice_number.' created', ['invoice_id' => $invoice->id, 'student_id' => $student->id, 'total' => $total]);
        $this->notifyGuardians($invoice, 'issued');

        return $invoice;
    }

    // ------------------------------------------------------------------ adjustments applied at generation

    /** @return array{0:array<int,array<string,mixed>>,1:int} the reduction lines and their total */
    private function reductions(Student $student, AcademicYear $year, ?Term $term, array $lines, int $subtotal): array
    {
        $approved = FeeAdjustment::query()
            ->where('student_id', $student->id)->where('status', 'approved')
            ->where(fn ($q) => $q->whereNull('academic_year_id')->orWhere('academic_year_id', $year->id))
            ->where(fn ($q) => $q->whereNull('term_id')->when($term, fn ($q2) => $q2->orWhere('term_id', $term->id)))
            ->orderBy('id')->get();

        $out = [];
        $remaining = $subtotal;
        foreach ($approved as $a) {
            $base = $a->fee_category_id
                ? (int) array_sum(array_map(fn ($l) => (int) $l['fee_category_id'] === (int) $a->fee_category_id ? $l['amount'] : 0, $lines))
                : $subtotal;
            $amount = $a->basis === 'percent' ? Ops::percentOf($base, (float) $a->value) : min((int) $a->value, $base);
            $amount = min($amount, $remaining);
            if ($amount <= 0) {
                continue;
            }
            $remaining -= $amount;
            $out[] = ['kind' => $a->kind, 'description' => ucfirst($a->kind), 'amount' => $amount, 'fee_category_id' => $a->fee_category_id, 'fee_adjustment_id' => $a->id];
        }

        return [$out, $subtotal - $remaining];
    }

    // ------------------------------------------------------------------ balances

    /** Recompute paid amount, status and instalments from the confirmed payments. */
    public function recalculate(Invoice $invoice): Invoice
    {
        $invoice->refresh();
        $paid = (int) $invoice->payments()->where('status', 'confirmed')->sum('amount');

        if ($invoice->status !== 'cancelled') {
            $total = (int) $invoice->total_amount;
            $invoice->amount_paid = $paid;
            $invoice->status = ($total === 0 || $paid >= $total) ? 'paid' : ($paid > 0 ? 'partial' : 'issued');
            $invoice->save();
        }

        $left = $paid;
        foreach ($invoice->installments as $inst) {
            $take = min($left, (int) $inst->amount);
            $left -= $take;
            if ((int) $inst->amount_paid !== $take) {
                $inst->update(['amount_paid' => $take]);
            }
        }

        return $invoice;
    }

    // ------------------------------------------------------------------ instalments

    /** Split an unpaid invoice into equal instalments (the last one carries the rounding), only where the school allows it. */
    public function setInstallments(Invoice $invoice, int $count, string $firstDue, User $actor): Invoice
    {
        $cfg = $this->settings->group('finance');
        if (! $cfg['allow_installments']) {
            throw AcademicRuleException::because('err.fin.installmentsOff');
        }
        if ($count < 2 || $count > (int) $cfg['installments_max']) {
            throw AcademicRuleException::because('err.fin.installmentCount');
        }
        if (! in_array($invoice->status, ['issued', 'partial'], true) || (int) $invoice->amount_paid > 0) {
            throw AcademicRuleException::because('err.fin.installmentLocked');
        }
        $first = \Illuminate\Support\Carbon::parse($firstDue)->startOfDay();
        if ($first->lt(today())) {
            throw AcademicRuleException::because('err.fin.installmentPast');
        }

        DB::transaction(function () use ($invoice, $count, $first) {
            $invoice->installments()->delete();
            $each = intdiv((int) $invoice->total_amount, $count);
            for ($i = 1; $i <= $count; $i++) {
                InvoiceInstallment::query()->create([
                    'invoice_id' => $invoice->id, 'number' => $i, 'due_on' => $first->copy()->addMonthsNoOverflow($i - 1)->toDateString(),
                    'amount' => $i === $count ? (int) $invoice->total_amount - $each * ($count - 1) : $each,
                ]);
            }
            $invoice->update(['due_on' => $first->copy()->addMonthsNoOverflow($count - 1)->toDateString()]);
        });

        Ops::audit('fin.invoice.installments', 'Instalment plan set on '.$invoice->invoice_number, ['invoice_id' => $invoice->id, 'count' => $count]);

        return $invoice->refresh();
    }

    public function clearInstallments(Invoice $invoice): void
    {
        $invoice->installments()->delete();
        Ops::audit('fin.invoice.installments', 'Instalment plan removed from '.$invoice->invoice_number, ['invoice_id' => $invoice->id, 'count' => 0]);
    }

    // ------------------------------------------------------------------ cancelling

    public function cancel(Invoice $invoice, string $reason, User $actor): Invoice
    {
        if ($invoice->status === 'cancelled') {
            throw AcademicRuleException::because('err.fin.alreadyCancelled');
        }
        if ($invoice->payments()->where('status', 'confirmed')->exists()) {
            throw AcademicRuleException::because('err.fin.hasPayments');
        }
        if ($invoice->payments()->where('status', 'pending')->exists()) {
            throw AcademicRuleException::because('err.fin.hasPending');
        }

        DB::transaction(function () use ($invoice, $reason, $actor) {
            StudentFee::query()->where('invoice_id', $invoice->id)->update(['status' => 'pending', 'invoice_id' => null]);
            $invoice->update([
                'status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $actor->id, 'cancel_reason' => mb_substr($reason, 0, 500),
            ]);
        });

        Ops::audit('fin.invoice.cancelled', 'Invoice '.$invoice->invoice_number.' cancelled', ['invoice_id' => $invoice->id, 'reason' => mb_substr($reason, 0, 200)]);

        return $invoice->refresh();
    }

    // ------------------------------------------------------------------ notices

    public function notifyGuardians(Invoice $invoice, string $kind): void
    {
        $student = Student::query()->with('guardians')->find($invoice->student_id);
        $users = User::query()
            ->whereIn('id', $student?->guardians->pluck('user_id')->filter()->all() ?? [])
            ->where('school_id', $invoice->school_id)->get();
        $users = $users->merge(User::query()->where('id', $student?->user_id)->where('school_id', $invoice->school_id)->get());

        if ($users->isEmpty()) {
            return;
        }

        $body = $kind === 'reminder'
            ? 'Invoice '.$invoice->invoice_number.' for '.$student->full_name.' is overdue. Balance: '.\App\Support\Money::format($invoice->balance()).'.'
            : 'Invoice '.$invoice->invoice_number.' for '.$student->full_name.': '.\App\Support\Money::format((int) $invoice->total_amount).'.';

        Notification::send($users, new SchoolAnnouncement(
            $kind === 'reminder' ? 'Fee reminder' : 'New fee invoice', $body, route('fees.index'),
            $kind === 'reminder' ? 'warning' : 'info', 'fees', ['in_app', 'email'],
        ));
    }
}
