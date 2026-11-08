<?php

namespace App\Services\Billing;

use App\Models\BillingPayment;
use App\Models\BillingWebhookEvent;
use App\Support\Currency;
use App\Support\Money;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Reconciliation = make our ledger agree with the providers and the bank, and surface what cannot be agreed.
 *
 *  run():    server-side re-check of gateway sessions whose webhook never arrived, and expiry of stale open payments
 *            (idempotent; safe to schedule).
 *  report(): totals per method and a "needs attention" list for humans.
 */
class PaymentReconciler
{
    public function __construct(protected TenantContext $tenant, protected PaymentService $payments) {}

    /** @return array{checked: int, settled: int, expired: int, errors: int} */
    public function run(bool $dryRun = false): array
    {
        $summary = ['checked' => 0, 'settled' => 0, 'expired' => 0, 'errors' => 0];

        $this->tenant->runAsPlatform(function () use ($dryRun, &$summary) {
            $cutoff = now()->subMinutes(max(1, (int) config('schoolos.billing.reconcile_after_minutes', 10)));

            // 1. Gateway sessions with no confirmed outcome: ask the provider.
            BillingPayment::query()
                ->whereIn('status', BillingPayment::OPEN)
                ->whereNotNull('provider_reference')
                ->where('created_at', '<=', $cutoff)
                ->lazyById()
                ->each(function (BillingPayment $payment) use ($dryRun, &$summary) {
                    $summary['checked']++;
                    if ($dryRun) {
                        return;
                    }
                    try {
                        $after = $this->payments->refresh($payment, 'reconciliation');
                        if ($after->status === BillingPayment::VERIFIED) {
                            $summary['settled']++;
                        } elseif ($after->status === BillingPayment::EXPIRED) {
                            $summary['expired']++;
                        }
                    } catch (Throwable $e) {
                        $summary['errors']++;
                        report($e);
                    }
                });

            // 2. Manual payments nobody completed: expire (never a payment already under verification).
            BillingPayment::query()
                ->where('status', BillingPayment::PENDING)
                ->whereNull('provider_reference')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->lazyById()
                ->each(function (BillingPayment $payment) use ($dryRun, &$summary) {
                    $summary['checked']++;
                    if ($dryRun) {
                        return;
                    }
                    if ($payment->isGateway()) {
                        return; // gateway payments without a session are handled when the school retries
                    }
                    $payment->forceFill(['status' => BillingPayment::EXPIRED])->save();
                    $this->payments->audit($payment, 'payment_expired', null, ['reason' => 'ttl', 'source' => 'reconciliation']);
                    $summary['expired']++;
                });
        });

        return $summary;
    }

    /**
     * @return array{totals: array<int, array<string, mixed>>, attention: array<int, array<string, mixed>>, webhooks: array<string, int>}
     */
    public function report(): array
    {
        return $this->tenant->runAsPlatform(function () {
            $totals = BillingPayment::query()
                ->select('method', 'status', 'currency', DB::raw('COUNT(*) as n'), DB::raw('SUM(total_amount) as total'))
                ->groupBy('method', 'status', 'currency')
                ->orderBy('method')
                ->get()
                ->map(fn ($r) => [
                    'method' => $r->method,
                    'status' => $r->status,
                    'count' => (int) $r->n,
                    'currency' => $r->currency,
                    'total' => (int) $r->total,
                    'total_label' => Money::formatCurrency($r->total, $r->currency),
                ])->all();

            $attention = [];
            $add = function (BillingPayment $p, string $reason) use (&$attention) {
                $attention[] = [
                    'id' => $p->id,
                    'reason' => $reason,
                    'school' => $p->school?->name,
                    'reference' => $p->reference,
                    'method' => $p->method,
                    'status' => $p->status,
                    'total_label' => Money::formatCurrency($p->total_amount, $p->currency),
                    'charged_label' => $p->charged_amount !== null ? Currency::labelMinor((int) $p->charged_amount, (string) $p->charged_currency) : null,
                    'since' => ($p->evidence_uploaded_at ?? $p->updated_at)?->toDateTimeString(),
                    'url' => route('platform.payments.show', $p->id),
                ];
            };

            $sla = now()->subDays(max(1, (int) config('schoolos.billing.verification_sla_days', 3)));

            BillingPayment::query()->with('school:id,name')
                ->where('status', BillingPayment::PENDING_VERIFICATION)->where('evidence_uploaded_at', '<=', $sla)
                ->each(fn ($p) => $add($p, 'verification_overdue'));

            BillingPayment::query()->with('school:id,name')
                ->where('status', BillingPayment::FAILED)->where('meta', 'like', '%"mismatch":true%')
                ->each(fn ($p) => $add($p, 'amount_mismatch'));

            BillingPayment::query()->with('school:id,name')
                ->where('meta', 'like', '%settlement_error%')->whereNotIn('status', [BillingPayment::VERIFIED])
                ->each(fn ($p) => $add($p, 'settlement_error'));

            BillingPayment::query()->with('school:id,name')
                ->where('status', BillingPayment::REFUNDED)
                ->each(fn ($p) => $add($p, 'refunded_review_access'));

            BillingPayment::query()->with('school:id,name')
                ->whereIn('status', BillingPayment::OPEN)->whereNotNull('provider_reference')
                ->where('created_at', '<=', now()->subHours(24))
                ->each(fn ($p) => $add($p, 'gateway_unresolved'));

            return [
                'totals' => $totals,
                'attention' => $attention,
                'webhooks' => [
                    'processed' => BillingWebhookEvent::query()->where('status', BillingWebhookEvent::PROCESSED)->count(),
                    'ignored' => BillingWebhookEvent::query()->where('status', BillingWebhookEvent::IGNORED)->count(),
                    'failed' => BillingWebhookEvent::query()->where('status', BillingWebhookEvent::FAILED)->count(),
                ],
            ];
        });
    }
}
