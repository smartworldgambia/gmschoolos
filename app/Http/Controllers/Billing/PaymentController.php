<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\BillingEvent;
use App\Models\BillingPayment;
use App\Services\Billing\Gateways\GatewayException;
use App\Services\Billing\PaymentService;
use App\Support\Currency;
use App\Support\Money;
use App\Support\TenantContext;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * School-side payment workflows. Tenant-scoped end to end: a payment reference from another school is a 404.
 * Reachable while the school is blocked (route names start with "billing.") so a lapsed school can always pay.
 */
class PaymentController extends Controller
{
    use SharesSchoolShell;

    public function __construct(protected PaymentService $payments, protected TenantContext $tenant) {}

    /** Start a payment for the full annual amount with the chosen method. */
    public function store(Request $request): SymfonyResponse
    {
        $validated = $request->validate([
            'method' => ['required', 'in:cash,bank_transfer,wave,stripe'],
            'expected_charge' => ['nullable', 'integer', 'min:1'],
        ]);

        $school = $this->tenant->school() ?? $request->user()->school;

        try {
            $payment = $this->payments->initiate(
                $school,
                $request->user(),
                $validated['method'],
                isset($validated['expected_charge']) ? (int) $validated['expected_charge'] : null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        // Gateways: hand the payer straight to the hosted page. Nothing is activated by going there.
        if ($payment->isGateway() && $payment->checkout_url && $payment->status === BillingPayment::PENDING) {
            return Inertia::location($payment->checkout_url);
        }

        return redirect()->route('billing.payments.show', $payment->reference);
    }

    public function show(string $reference): Response
    {
        $payment = $this->find($reference);

        $events = BillingEvent::query()
            ->where('billing_payment_id', $payment->id)
            ->orderBy('id')
            ->get(['type', 'created_at'])
            ->map(fn ($e) => ['type' => $e->type, 'at' => $e->created_at?->toDateTimeString()]);

        $bank = $payment->method === 'bank_transfer'
            ? $this->payments->bankAccounts()->map(fn ($a) => [
                'id' => $a->id, 'bank_name' => $a->bank_name, 'account_name' => $a->account_name,
                'account_number' => $a->account_number, 'branch' => $a->branch, 'swift_code' => $a->swift_code,
                'currency' => $a->currency, 'instructions' => $a->instructions,
            ])->values()
            : [];

        return Inertia::render('Billing/Payment', array_merge($this->shellProps(), [
            'payment' => $this->present($payment),
            'events' => $events,
            'bankAccounts' => $bank,
            'cash' => [
                'office' => config('schoolos.billing.cash.office'),
                'instructions' => config('schoolos.billing.cash.instructions'),
            ],
            'evidence' => [
                'max_kb' => (int) config('schoolos.billing.evidence.max_kb', 5120),
                'accept' => '.pdf,.jpg,.jpeg,.png',
            ],
            'canManage' => (bool) auth()->user()?->can('subscriptions.manage'),
        ]));
    }

    public function evidence(Request $request, string $reference): RedirectResponse
    {
        $payment = $this->find($reference);

        $validator = Validator::make($request->all(), [
            'evidence' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.(int) config('schoolos.billing.evidence.max_kb', 5120)],
            'claimed_reference' => ['nullable', 'string', 'max:120'],
            'transferred_on' => ['nullable', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        try {
            $this->payments->attachEvidence($payment, $request->file('evidence'), $request->user(), $validator->validated());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['evidence' => $e->getMessage()]);
        }

        return back()->with('success', 'Evidence received. Finance will verify the transfer against the bank statement.');
    }

    public function cancel(Request $request, string $reference): RedirectResponse
    {
        $payment = $this->find($reference);

        try {
            $this->payments->cancel($payment, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return redirect()->route('billing.index')->with('success', 'Payment cancelled.');
    }

    /**
     * Where the provider sends the payer back. The query string is IGNORED for decisions: the provider is asked
     * server-side what happened, so a forged "?result=success" can never activate anything.
     */
    public function returned(Request $request, string $reference): RedirectResponse
    {
        return $this->check($reference, null);
    }

    /** "Check status" button: same server-side verification, no redirect parameter involved. */
    public function refresh(string $reference): RedirectResponse
    {
        return $this->check($reference, 'check');
    }

    public function receipt(string $reference): Response|RedirectResponse
    {
        $payment = $this->find($reference);

        if (! in_array($payment->status, [BillingPayment::VERIFIED, BillingPayment::REFUNDED], true)) {
            abort(404);
        }

        return Inertia::render('Billing/Receipt', [
            'receipt' => $this->payments->receipt($payment),
            'backUrl' => route('billing.index'),
        ]);
    }

    // ------------------------------------------------------------------ helpers

    protected function check(string $reference, ?string $mode): RedirectResponse
    {
        $payment = $this->find($reference);

        try {
            $after = $this->payments->refresh($payment, 'return');
        } catch (GatewayException|ConnectionException) {
            return redirect()->route('billing.payments.show', $payment->reference)
                ->withErrors(['payment' => 'We could not reach the payment provider. Your payment is not lost - check again in a minute.']);
        }

        $redirect = redirect()->route('billing.payments.show', $payment->reference);

        // Pending is shown on the page itself ("waiting for the provider"); only a confirmed payment gets a success notice.
        return $after->status === BillingPayment::VERIFIED
            ? $redirect->with('success', 'Payment confirmed. Your subscription is active.')
            : $redirect;
    }
    protected function find(string $reference): BillingPayment
    {
        // SchoolScope guarantees tenant isolation: another school's reference simply does not exist here.
        return BillingPayment::query()->where('reference', $reference)->firstOrFail();
    }

    /** @return array<string, mixed> */
    protected function present(BillingPayment $p): array
    {
        return [
            'reference' => $p->reference,
            'method' => $p->method,
            'status' => $p->status,
            'total_label' => Money::formatCurrency($p->total_amount, $p->currency),
            'currency' => $p->currency,
            'amount_label' => Money::formatCurrency($p->amount, $p->currency),
            'tax_label' => $p->tax_amount ? Money::formatCurrency($p->tax_amount, $p->currency) : null,
            'charged_label' => $p->charged_amount !== null && $p->charged_currency
                ? Currency::labelMinor((int) $p->charged_amount, $p->charged_currency)
                : null,
            'fx_rate' => $p->fx_rate,
            'charged_currency' => $p->charged_currency,
            'is_gateway' => $p->isGateway(),
            'checkout_url' => $p->status === BillingPayment::PENDING ? $p->checkout_url : null,
            'expires_at' => $p->expires_at?->toDateTimeString(),
            'has_evidence' => $p->hasEvidence(),
            'evidence_name' => $p->evidence_original_name,
            'evidence_uploaded_at' => $p->evidence_uploaded_at?->toDateTimeString(),
            'claimed' => $p->meta['claimed'] ?? null,
            'rejection_reason' => $p->rejection_reason,
            'failure_reason' => $p->status === BillingPayment::FAILED && ! empty($p->meta['mismatch'])
                ? 'mismatch'
                : null,
            'receipt_number' => $p->receipt_number,
            'receipt_url' => in_array($p->status, [BillingPayment::VERIFIED, BillingPayment::REFUNDED], true)
                ? route('billing.payments.receipt', $p->reference)
                : null,
            'paid_at' => $p->paid_at?->toDateString(),
            'period_start' => $p->period_start?->toDateString(),
            'period_end' => $p->period_end?->toDateString(),
            'created_at' => $p->created_at?->toDateTimeString(),
            'can_cancel' => $p->status === BillingPayment::PENDING,
            'can_upload' => $p->method === 'bank_transfer' && $p->isOpen(),
        ];
    }
}
