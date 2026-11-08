<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\BillingPayment;
use App\Services\Billing\PaymentService;
use App\Services\Billing\SubscriptionLifecycle;
use App\Support\Money;
use App\Support\TenantContext;
use Inertia\Inertia;
use Inertia\Response;

/**
 * School-side annual subscription page: status, countdown, amount due, payment history, methods.
 * Reachable even when the school is blocked (see schoolos.access.always_allowed_routes) so an
 * administrator can always see what is owed and how to pay.
 */
class BillingController extends Controller
{
    use SharesSchoolShell;

    public function index(SubscriptionLifecycle $lifecycle, PaymentService $payments, TenantContext $tenant): Response
    {
        $school = $tenant->school() ?? auth()->user()->school;

        $rows = BillingPayment::query()
            ->latest('paid_at')
            ->latest('id')
            ->limit(50)
            ->get();

        $paymentRows = $rows->map(fn (BillingPayment $p) => [
            'id' => $p->id,
            'reference' => $p->reference,
            'receipt_number' => $p->receipt_number,
            'method' => $p->method,
            'status' => $p->status,
            'total_label' => Money::formatCurrency($p->total_amount, $p->currency),
            'paid_at' => $p->paid_at?->toDateString(),
            'period_start' => $p->period_start?->toDateString(),
            'period_end' => $p->period_end?->toDateString(),
            'url' => route('billing.payments.show', $p->reference),
            'receipt_url' => in_array($p->status, [BillingPayment::VERIFIED, BillingPayment::REFUNDED], true)
                ? route('billing.payments.receipt', $p->reference)
                : null,
        ]);

        $state = $lifecycle->state($school);
        $currency = $state['currency'] ?? config('schoolos.billing.currency', 'GMD');
        $total = (int) ($state['annual_total'] ?? 0);
        $open = $rows->first(fn (BillingPayment $p) => $p->isOpen());

        $methods = collect(array_keys(config('schoolos.billing.payment_methods', [])))
            ->map(function (string $key) use ($payments, $currency, $total) {
                $a = $payments->availability($key, $currency);
                $charge = $a['available'] && $a['charge_currency'] ? $payments->chargeFor($key, $total, $currency) : null;

                return [
                    'key' => $key,
                    'enabled' => $a['available'], // offered to this school (switched on AND configured)
                    'reason' => $a['reason'],
                    'charge' => $charge,
                ];
            })
            ->values();

        return Inertia::render('Billing/Index', array_merge($this->shellProps(), [
            'billing' => $state,
            'payments' => $paymentRows,
            'methods' => $methods,
            'openPayment' => $open ? [
                'reference' => $open->reference,
                'method' => $open->method,
                'status' => $open->status,
                'url' => route('billing.payments.show', $open->reference),
            ] : null,
            'canManage' => (bool) auth()->user()?->can('subscriptions.manage'),
            'support' => [
                'email' => config('schoolos.marketing.support_email'),
                'phone' => config('schoolos.marketing.phone'),
            ],
        ]));
    }
}