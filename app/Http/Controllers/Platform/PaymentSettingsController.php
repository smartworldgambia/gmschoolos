<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Concerns\SharesPlatformShell;
use App\Http\Controllers\Controller;
use App\Models\BillingPayment;
use App\Models\BillingWebhookEvent;
use App\Models\PlatformBankAccount;
use App\Services\Billing\PaymentService;
use App\Support\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Payment configuration for the Platform Administrator: bank accounts shown to schools and a READ-ONLY view of
 * gateway readiness. Secrets live only in the environment - they are never stored here, returned, or displayed.
 */
class PaymentSettingsController extends Controller
{
    use SharesPlatformShell;

    public function __construct(protected PaymentService $payments) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Platform/Payments/Settings', array_merge($this->platformShellProps(), [
            'methods' => $this->methodStatus(),
            'bankAccounts' => PlatformBankAccount::query()->orderByDesc('is_active')->orderBy('id')->get()->map(fn ($a) => [
                'id' => $a->id,
                'bank_name' => $a->bank_name,
                'account_name' => $a->account_name,
                'account_number' => $a->account_number,
                'branch' => $a->branch,
                'swift_code' => $a->swift_code,
                'currency' => $a->currency,
                'instructions' => $a->instructions,
                'is_active' => $a->is_active,
            ]),
            'webhookUrls' => [
                'stripe' => url('/webhooks/stripe'),
                'wave' => url('/webhooks/wave'),
            ],
            'canConfigure' => (bool) $request->user()->can('platform.payments.configure'),
        ]));
    }

    public function storeBank(Request $request): RedirectResponse
    {
        PlatformBankAccount::query()->create($this->validated($request) + ['created_by' => $request->user()->id]);

        return back()->with('success', 'Bank account saved.');
    }

    public function updateBank(Request $request, PlatformBankAccount $account): RedirectResponse
    {
        $account->update($this->validated($request));

        return back()->with('success', 'Bank account updated.');
    }

    /** Accounts are never deleted (past payments reference them in the audit trail): deactivate instead. */
    public function toggleBank(PlatformBankAccount $account): RedirectResponse
    {
        $account->update(['is_active' => ! $account->is_active]);

        return back()->with('success', $account->is_active ? 'Bank account activated.' : 'Bank account deactivated.');
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request): array
    {
        return $request->validate([
            'bank_name' => ['required', 'string', 'max:120'],
            'account_name' => ['required', 'string', 'max:160'],
            'account_number' => ['required', 'string', 'max:60'],
            'branch' => ['nullable', 'string', 'max:120'],
            'swift_code' => ['nullable', 'string', 'max:20'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'instructions' => ['nullable', 'string', 'max:1500'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * Honest integration status. "verified" is deliberately never computed here: it requires a human to record that
     * a sandbox transaction, webhook delivery and reconciliation succeeded (see docs/payment-integration.md).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function methodStatus(): array
    {
        $priceCurrency = (string) config('schoolos.billing.currency', 'GMD');

        return collect(['cash', 'bank_transfer', 'wave', 'stripe'])->map(function (string $key) use ($priceCurrency) {
            $a = $this->payments->availability($key, $priceCurrency);
            $gateway = $this->payments->gateway($key);

            $observed = $gateway
                ? BillingPayment::withoutGlobalScopes()->where('method', $key)->where('status', 'verified')->whereNotNull('provider_confirmed_at')->count()
                : null;

            $status = match (true) {
                $gateway === null => $a['available'] ? 'implemented' : 'requires_configuration',
                ! $a['configured'] => 'requires_configuration',
                $observed > 0 => 'traffic_observed',
                default => 'configured_untested',
            };

            return [
                'key' => $key,
                'enabled' => $a['enabled'],
                'configured' => $a['configured'],
                'available' => $a['available'],
                'reason' => $a['reason'],
                'status' => $status,
                'mode' => $gateway?->mode(),
                'charge_currency' => $gateway?->currency(),
                'fx_rate' => $gateway ? ($gateway->currency() === Currency::normalise($priceCurrency) ? null : $gateway->fxRate()) : null,
                'verified_transactions' => $observed,
                'webhooks_processed' => $gateway ? BillingWebhookEvent::query()->where('provider', $key)->where('status', 'processed')->count() : null,
                'webhooks_failed' => $gateway ? BillingWebhookEvent::query()->where('provider', $key)->where('status', 'failed')->count() : null,
            ];
        })->values()->all();
    }
}
