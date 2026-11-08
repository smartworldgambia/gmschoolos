<?php

namespace App\Http\Controllers\Fees;

use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Finance\FinanceAccess;
use App\Services\Finance\PaymentService;
use App\Services\Finance\ReceiptPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    use HandlesAcademicRules, SharesSchoolShell;

    /** Payment register for staff: the confirmation queue first, then history. */
    public function index(Request $request): Response
    {
        $status = (string) $request->query('status', 'pending');
        $method = (string) $request->query('method', '');
        $q = trim((string) $request->query('q', ''));

        $payments = Payment::query()->with(['student:id,first_name,last_name,student_number', 'invoice:id,invoice_number', 'submitter:id,name', 'recorder:id,name'])
            ->when(in_array($status, ['pending', 'confirmed', 'rejected', 'voided'], true), fn ($b) => $b->where('status', $status))
            ->when(in_array($method, PaymentService::STAFF_METHODS, true), fn ($b) => $b->where('method', $method))
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('receipt_number', 'like', "%$q%")->orWhere('external_reference', 'like', "%$q%")
                ->orWhereHas('student', fn ($s) => $s->where('first_name', 'like', "%$q%")->orWhere('last_name', 'like', "%$q%"))))
            ->latest('id')->paginate(25)->withQueryString();

        $user = $request->user();

        return Inertia::render('Fees/Payments', array_merge($this->shellProps(), [
            'payments' => $payments->through(fn (Payment $p) => [
                'id' => $p->id, 'receipt_number' => $p->receipt_number, 'student' => $p->student?->full_name, 'invoice' => $p->invoice?->invoice_number,
                'invoice_id' => $p->invoice_id, 'amount' => (int) $p->amount, 'method' => $p->method, 'reference' => $p->external_reference,
                'status' => $p->status, 'paid_at' => $p->paid_at?->toDateString(), 'payer_name' => $p->payer_name, 'notes' => $p->notes,
                'by' => $p->submitter?->name ?? $p->recorder?->name, 'submitted' => $p->submitted_by !== null, 'has_evidence' => $p->evidence_path !== null,
                'decision_note' => $p->decision_note, 'void_reason' => $p->void_reason, 'is_reconciled' => (bool) $p->is_reconciled,
            ]),
            'filters' => ['status' => $status, 'method' => $method, 'q' => $q],
            'pendingCount' => Payment::query()->where('status', 'pending')->count(),
            'can' => ['verify' => $user->can('payments.verify'), 'approve' => $user->can('fees.approve')],
        ]));
    }

    /** Staff records a payment at the desk (route name kept from the original foundation). */
    public function store(Request $request, PaymentService $service): RedirectResponse
    {
        abort_unless($request->user()?->can('payments.record'), 403);

        $v = $request->validate([
            'invoice_id' => ['required', $this->inSchool('invoices')],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000'],
            'method' => ['required', 'in:'.implode(',', PaymentService::STAFF_METHODS)],
            'paid_on' => ['nullable', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:100'],
            'payer_name' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->attempt(fn () => $service->record(
            Invoice::query()->findOrFail($v['invoice_id']), (int) $v['amount'], $v['method'], $v['paid_on'] ?? null,
            $v['reference'] ?? null, $v['payer_name'] ?? null, $v['notes'] ?? null, $request->user(),
        ), 'ok.fin.paymentRecorded');
    }

    /** A parent or learner reports a payment made through one of the school's own methods. */
    public function submit(Request $request, Invoice $invoice, PaymentService $service): RedirectResponse
    {
        $user = $request->user();
        abort_unless(FinanceAccess::canSee($user, (int) $invoice->student_id) && ! FinanceAccess::isStaff($user), 403);

        $v = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:100000000'],
            'method' => ['required', 'in:wave,bank_transfer,other'],
            'reference' => ['required', 'string', 'max:100'],
            'payer_name' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:500'],
            'evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ]);

        return $this->attempt(fn () => $service->submit(
            $invoice, (int) $v['amount'], $v['method'], $v['reference'], $v['payer_name'] ?? null, $v['notes'] ?? null, $request->file('evidence'), $user,
        ), 'ok.fin.paymentSubmitted');
    }

    public function confirm(Request $request, Payment $payment, PaymentService $service): RedirectResponse
    {
        return $this->attempt(fn () => $service->confirm($payment, $request->user()), 'ok.fin.paymentConfirmed');
    }

    public function reject(Request $request, Payment $payment, PaymentService $service): RedirectResponse
    {
        $v = $request->validate(['note' => ['required', 'string', 'max:500']]);

        return $this->attempt(fn () => $service->reject($payment, $v['note'], $request->user()), 'ok.fin.paymentRejected');
    }

    public function void(Request $request, Payment $payment, PaymentService $service): RedirectResponse
    {
        $v = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->attempt(fn () => $service->void($payment, $v['reason'], $request->user()), 'ok.fin.paymentVoided');
    }

    public function receipt(Request $request, Payment $payment, ReceiptPdf $pdf)
    {
        abort_unless(FinanceAccess::canSee($request->user(), (int) $payment->student_id), 403);
        abort_unless($payment->status === 'confirmed' || FinanceAccess::isStaff($request->user()), 404);

        return response($pdf->render($payment), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="receipt-'.$payment->receipt_number.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Proof of payment: the person who submitted it and staff who verify payments. */
    public function evidence(Request $request, Payment $payment)
    {
        $user = $request->user();
        abort_unless($payment->evidence_path && ($user->can('payments.verify') || (int) $payment->submitted_by === (int) $user->id), 404);
        abort_unless(Storage::disk('local')->exists($payment->evidence_path), 404);

        return Storage::disk('local')->download($payment->evidence_path, 'payment-proof-'.$payment->id.'.'.pathinfo($payment->evidence_path, PATHINFO_EXTENSION), ['Cache-Control' => 'private, no-store']);
    }
}
