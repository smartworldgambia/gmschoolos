<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Concerns\SharesPlatformShell;
use App\Http\Controllers\Controller;
use App\Models\BillingEvent;
use App\Models\BillingPayment;
use App\Models\BillingWebhookEvent;
use App\Services\Billing\Gateways\GatewayException;
use App\Services\Billing\PaymentReconciler;
use App\Services\Billing\PaymentService;
use App\Support\Currency;
use App\Support\Money;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Platform finance: the payment queue, cash recording, bank approval, gateway status, reconciliation, receipts.
 * Viewing needs platform.finance.view; confirming / rejecting / reconciling needs platform.payments.verify.
 * (Platform routes already run in platform mode - see EnsurePlatformAdmin.)
 */
class PaymentController extends Controller
{
    use SharesPlatformShell;

    public function __construct(protected PaymentService $payments, protected TenantContext $tenant) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['q', 'status', 'method']);

        $rows = $this->filtered($filters)
            // Anything waiting on a human comes first.
            ->orderByRaw("CASE status WHEN 'pending_verification' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END")
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (BillingPayment $p) => $this->row($p));

        return Inertia::render('Platform/Payments/Index', array_merge($this->platformShellProps(), [
            'payments' => $rows,
            'filters' => ['q' => $filters['q'] ?? '', 'status' => $filters['status'] ?? '', 'method' => $filters['method'] ?? ''],
            'statuses' => BillingPayment::STATUSES,
            'methods' => \App\Services\Billing\SubscriptionLifecycle::METHODS,
            'stats' => [
                'awaiting_verification' => BillingPayment::query()->where('status', BillingPayment::PENDING_VERIFICATION)->count(),
                'open_cash' => BillingPayment::query()->where('status', BillingPayment::PENDING)->where('method', 'cash')->count(),
                'open_gateway' => BillingPayment::query()->where('status', BillingPayment::PENDING)->whereNotNull('provider')->count(),
                'failed' => BillingPayment::query()->where('status', BillingPayment::FAILED)->count(),
                'refunded' => BillingPayment::query()->where('status', BillingPayment::REFUNDED)->count(),
            ],
            'exportUrl' => route('platform.payments.export', array_filter($filters)),
            'canVerify' => (bool) $request->user()->can('platform.payments.verify'),
            'canConfigure' => (bool) $request->user()->can('platform.payments.configure'),
        ]));
    }

    public function show(Request $request, int $paymentId): Response
    {
        $payment = $this->find($paymentId);
        $payment->loadMissing('school:id,name,slug,email', 'verifier:id,name', 'rejecter:id,name', 'initiator:id,name');

        $events = BillingEvent::query()
            ->where('billing_payment_id', $payment->id)
            ->with('school:id')
            ->orderBy('id')
            ->get()
            ->map(function (BillingEvent $e) {
                return [
                    'type' => $e->type,
                    'at' => $e->created_at?->toDateTimeString(),
                    'actor' => $e->actor_id ? \App\Models\User::query()->whereKey($e->actor_id)->value('name') : null,
                    'meta' => $e->meta,
                ];
            });

        return Inertia::render('Platform/Payments/Show', array_merge($this->platformShellProps(), [
            'payment' => $this->detail($payment),
            'events' => $events,
            'webhookEvents' => BillingWebhookEvent::query()->where('billing_payment_id', $payment->id)->latest('id')->limit(20)->get()
                ->map(fn ($w) => ['provider' => $w->provider, 'type' => $w->type, 'status' => $w->status, 'attempts' => $w->attempts, 'at' => $w->received_at?->toDateTimeString()]),
            'canVerify' => (bool) $request->user()->can('platform.payments.verify'),
        ]));
    }

    public function confirmCash(Request $request, int $paymentId): RedirectResponse
    {
        $payment = $this->find($paymentId);

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'receipt_number' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->payments->confirmCash($payment, $request->user(), $data);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with('success', 'Cash payment confirmed. The subscription is active and a receipt was issued.');
    }

    public function approveBank(Request $request, int $paymentId): RedirectResponse
    {
        $payment = $this->find($paymentId);

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'transaction_reference' => ['required', 'string', 'max:120'],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'confirm_funds' => ['accepted'],
        ]);

        try {
            $this->payments->approveBank($payment, $request->user(), $data);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with('success', 'Bank transfer approved. The subscription is active and a receipt was issued.');
    }

    public function reject(Request $request, int $paymentId): RedirectResponse
    {
        $payment = $this->find($paymentId);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        try {
            $this->payments->reject($payment, $request->user(), $data['reason']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with('success', 'Payment rejected. The school was notified.');
    }

    /** Ask the provider (server-to-server) for the real state of one gateway payment. */
    public function recheck(Request $request, int $paymentId): RedirectResponse
    {
        $payment = $this->find($paymentId);

        try {
            $after = $this->payments->refresh($payment, 'platform_recheck');
            $this->payments->audit($after, 'provider_rechecked', $request->user(), ['status' => $after->status]);
        } catch (GatewayException|ConnectionException $e) {
            return back()->withErrors(['payment' => 'The provider could not be reached: '.$e->getMessage()]);
        }

        return back()->with('success', 'Provider state re-checked.');
    }

    public function evidence(Request $request, int $paymentId)
    {
        $payment = $this->find($paymentId);
        $disk = (string) config('schoolos.billing.evidence.disk', 'local');

        abort_unless($payment->hasEvidence() && Storage::disk($disk)->exists($payment->evidence_path), 404);

        $this->tenant->runForSchool(\App\Models\School::query()->findOrFail($payment->school_id), fn () => $this->payments->audit(
            BillingPayment::query()->findOrFail($payment->id), 'evidence_viewed', $request->user(),
        ));

        $extension = pathinfo($payment->evidence_path, PATHINFO_EXTENSION);

        // Always a download with nosniff: school-uploaded files are never rendered inline by the platform origin.
        return Storage::disk($disk)->download($payment->evidence_path, 'evidence-'.$payment->reference.'.'.$extension, [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }

    public function receipt(int $paymentId): Response
    {
        $payment = $this->find($paymentId);
        abort_unless(in_array($payment->status, [BillingPayment::VERIFIED, BillingPayment::REFUNDED], true), 404);

        return Inertia::render('Billing/Receipt', [
            'receipt' => $this->payments->receipt($payment),
            'backUrl' => route('platform.payments.show', $payment->id),
        ]);
    }

    public function reconciliation(Request $request, PaymentReconciler $reconciler): Response
    {
        return Inertia::render('Platform/Payments/Reconciliation', array_merge($this->platformShellProps(), [
            'report' => $reconciler->report(),
            'canVerify' => (bool) $request->user()->can('platform.payments.verify'),
        ]));
    }

    public function reconcile(Request $request, PaymentReconciler $reconciler): RedirectResponse
    {
        $result = $reconciler->run();

        return back()->with('success', sprintf(
            'Reconciliation finished: %d checked, %d settled, %d expired, %d errors.',
            $result['checked'], $result['settled'], $result['expired'], $result['errors'],
        ));
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $request->only(['q', 'status', 'method']);

        // The stream callback runs after the request pipeline: platform mode must be re-entered explicitly.
        return response()->streamDownload(function () use ($filters) {
            app(TenantContext::class)->runAsPlatform(function () use ($filters) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['reference', 'school', 'method', 'status', 'currency', 'total', 'charged', 'receipt_number', 'transaction_reference', 'paid_at', 'verified_by', 'created_at']);

                $this->filtered($filters)->with('school:id,name', 'verifier:id,name')->latest('id')->each(function (BillingPayment $p) use ($out) {
                    fputcsv($out, [
                        $p->reference, $this->csvSafe($p->school?->name), $p->method, $p->status, $p->currency, $p->total_amount,
                        $p->charged_amount !== null ? Currency::labelMinor((int) $p->charged_amount, (string) $p->charged_currency) : '',
                        $p->receipt_number, $this->csvSafe($p->transaction_reference), $p->paid_at?->toDateString(),
                        $this->csvSafe($p->verifier?->name), $p->created_at?->toDateTimeString(),
                    ]);
                });

                fclose($out);
            });
        }, 'payments-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
    }

    // ------------------------------------------------------------------ helpers

    protected function find(int $id): BillingPayment
    {
        return $this->tenant->runAsPlatform(fn () => BillingPayment::query()->findOrFail($id));
    }

    protected function filtered(array $filters): Builder
    {
        return BillingPayment::query()
            ->with('school:id,name')
            ->when($filters['status'] ?? null, fn (Builder $q, string $s) => $q->where('status', $s))
            ->when($filters['method'] ?? null, fn (Builder $q, string $m) => $q->where('method', $m))
            ->when($filters['q'] ?? null, fn (Builder $q, string $term) => $q->where(fn (Builder $w) => $w
                ->where('reference', 'like', "%{$term}%")
                ->orWhere('receipt_number', 'like', "%{$term}%")
                ->orWhere('transaction_reference', 'like', "%{$term}%")
                ->orWhereHas('school', fn (Builder $s) => $s->where('name', 'like', "%{$term}%"))));
    }

    /** @return array<string, mixed> */
    protected function row(BillingPayment $p): array
    {
        return [
            'id' => $p->id,
            'url' => route('platform.payments.show', $p->id),
            'school' => $p->school?->name,
            'reference' => $p->reference,
            'receipt_number' => $p->receipt_number,
            'method' => $p->method,
            'status' => $p->status,
            'total_label' => Money::formatCurrency($p->total_amount, $p->currency),
            'charged_label' => $p->charged_amount !== null ? Currency::labelMinor((int) $p->charged_amount, (string) $p->charged_currency) : null,
            'has_evidence' => $p->hasEvidence(),
            'created_at' => $p->created_at?->toDateString(),
            'paid_at' => $p->paid_at?->toDateString(),
        ];
    }

    /** @return array<string, mixed> */
    protected function detail(BillingPayment $p): array
    {
        return $this->row($p) + [
            'school_url' => route('platform.schools.show', $p->school_id),
            'school_type' => $p->school_type,
            'amount_label' => Money::formatCurrency($p->amount, $p->currency),
            'tax_label' => $p->tax_amount ? Money::formatCurrency($p->tax_amount, $p->currency) : null,
            'total' => (int) $p->total_amount,
            'currency' => $p->currency,
            'fx_rate' => $p->fx_rate,
            'provider' => $p->provider,
            'provider_reference' => $p->provider_reference,
            'transaction_reference' => $p->transaction_reference,
            'provider_confirmed_at' => $p->provider_confirmed_at?->toDateTimeString(),
            'expires_at' => $p->expires_at?->toDateTimeString(),
            'evidence' => $p->hasEvidence() ? [
                'name' => $p->evidence_original_name,
                'mime' => $p->evidence_mime,
                'size' => $p->evidence_size,
                'uploaded_at' => $p->evidence_uploaded_at?->toDateTimeString(),
                'url' => route('platform.payments.evidence', $p->id),
            ] : null,
            'claimed' => $p->meta['claimed'] ?? null,
            'mismatch' => ! empty($p->meta['mismatch']),
            'settlement_error' => $p->meta['settlement_error'] ?? null,
            'provider_observed' => $p->meta['provider_observed'] ?? null,
            'failure_reason' => $p->failure_reason,
            'rejection_reason' => $p->rejection_reason,
            'rejected_by' => $p->rejecter?->name,
            'verified_by' => $p->verifier?->name,
            'verified_at' => $p->verified_at?->toDateTimeString(),
            'initiated_by' => $p->initiator?->name,
            'period_start' => $p->period_start?->toDateString(),
            'period_end' => $p->period_end?->toDateString(),
            'receipt_url' => in_array($p->status, [BillingPayment::VERIFIED, BillingPayment::REFUNDED], true)
                ? route('platform.payments.receipt', $p->id)
                : null,
            'is_gateway' => $p->isGateway(),
            'is_open' => $p->isOpen(),
            'notes' => $p->notes,
        ];
    }

    protected function csvSafe(?string $value): ?string
    {
        return $value !== null && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
