<?php

namespace App\Services\Billing;

use App\Models\BillingPayment;
use App\Models\BillingWebhookEvent;
use App\Services\Billing\Gateways\GatewayException;
use App\Support\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Stripe / Wave webhook intake.
 *
 *  1. authenticate the RAW body (provider signature / shared secret) - invalid => 400, nothing recorded;
 *  2. idempotency: one billing_webhook_events row per (provider, event id); a repeat is acknowledged, not re-applied;
 *  3. the webhook only TRIGGERS processing - the provider is then asked server-side for the real state, and that
 *     answer (amount, currency, reference, status) is what settles the payment;
 *  4. transient failures answer 5xx so the provider retries; unknown payments are acknowledged and logged.
 */
class WebhookProcessor
{
    public function __construct(protected TenantContext $tenant, protected PaymentService $payments) {}

    /**
     * @param  array<string, string|array<int, string>>  $headers
     * @return array{0: int, 1: string} [http status, short message]
     */
    public function handle(string $provider, string $rawBody, array $headers): array
    {
        $gateway = $this->payments->gateway($provider);
        if (! $gateway || ! $gateway->isConfigured() || ! config("schoolos.billing.payment_methods.{$provider}.enabled", false)) {
            return [404, 'not enabled'];
        }

        if (! $gateway->verifyWebhook($rawBody, $headers)) {
            Log::warning('billing.webhook.rejected', ['provider' => $provider, 'reason' => 'authentication failed']);

            return [400, 'invalid signature'];
        }

        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            return [400, 'invalid payload'];
        }

        $event = $gateway->parseWebhook($payload);
        if (! $event['event_id']) {
            return [400, 'missing event id'];
        }

        return $this->tenant->runAsPlatform(function () use ($provider, $rawBody, $gateway, $event) {
            $row = $this->claim($provider, $event, $rawBody);
            if ($row === null) {
                return [200, 'duplicate'];
            }

            try {
                $payment = $this->locate($provider, $event);

                if (! $payment || $event['intent'] === 'ignore') {
                    $row->forceFill([
                        'status' => BillingWebhookEvent::IGNORED,
                        'error' => $payment ? null : 'No payment matches this event.',
                        'processed_at' => now(),
                    ])->save();
                    if (! $payment && $event['intent'] !== 'ignore') {
                        Log::warning('billing.webhook.unknown_payment', ['provider' => $provider, 'event' => $event['event_id']]);
                    }

                    return [200, 'ignored'];
                }

                $row->forceFill(['billing_payment_id' => $payment->id, 'school_id' => $payment->school_id])->save();

                match ($event['intent']) {
                    'completed', 'expired' => $this->payments->refresh($payment, 'webhook'),
                    'failed' => $this->failed($payment, $provider),
                    'refunded' => $event['full_refund'] ? $this->payments->markRefunded($payment, 'webhook') : null,
                    default => null,
                };

                $row->forceFill(['status' => BillingWebhookEvent::PROCESSED, 'error' => null, 'processed_at' => now()])->save();

                return [200, 'ok'];
            } catch (GatewayException|ConnectionException $e) {
                $row->forceFill(['status' => BillingWebhookEvent::FAILED, 'error' => mb_substr($e->getMessage(), 0, 500)])->save();

                return [503, 'provider unavailable, retry']; // 5xx => provider retries
            } catch (Throwable $e) {
                $row->forceFill(['status' => BillingWebhookEvent::FAILED, 'error' => mb_substr($e->getMessage(), 0, 500)])->save();
                report($e);

                return [500, 'processing error, retry'];
            }
        });
    }

    /** @return BillingWebhookEvent|null null when this event was already handled */
    protected function claim(string $provider, array $event, string $rawBody): ?BillingWebhookEvent
    {
        try {
            return BillingWebhookEvent::query()->create([
                'provider' => $provider,
                'event_id' => $event['event_id'],
                'type' => $event['type'],
                'status' => BillingWebhookEvent::RECEIVED,
                'payload_hash' => hash('sha256', $rawBody),
                'received_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            $row = BillingWebhookEvent::query()->where('provider', $provider)->where('event_id', $event['event_id'])->first();

            if ($row && in_array($row->status, [BillingWebhookEvent::PROCESSED, BillingWebhookEvent::IGNORED], true)) {
                return null;
            }

            $row?->forceFill(['attempts' => $row->attempts + 1])->save();

            return $row; // previous attempt failed: process again
        }
    }

    protected function locate(string $provider, array $event): ?BillingPayment
    {
        if ($event['intent'] === 'refunded') {
            return $event['payment_intent']
                ? BillingPayment::query()->where('method', $provider)->where('transaction_reference', $event['payment_intent'])->first()
                : null;
        }

        return $event['provider_reference']
            ? BillingPayment::query()->where('provider', $provider)->where('provider_reference', $event['provider_reference'])->first()
            : null;
    }

    /**
     * A failure notice. The provider is asked for the real state first: if it was actually paid, that wins.
     * Wave sends several failures per session (the payer may retry); only Stripe's async failure is final.
     */
    protected function failed(BillingPayment $payment, string $provider): void
    {
        $result = $this->payments->refresh($payment, 'webhook');

        if ($provider === 'stripe' && $result->isOpen()) {
            $this->payments->markFailed($result, 'The payment failed at the provider.', 'webhook');
        }
    }
}
