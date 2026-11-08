<?php

namespace App\Services\Billing\Gateways;

use App\Models\BillingPayment;
use App\Services\Billing\Gateways\Concerns\VerifiesSignedWebhooks;
use App\Support\Currency;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Stripe Checkout (hosted page). Card data never touches this application - the payer enters it on Stripe.
 * Webhooks are authenticated with the endpoint signing secret (Stripe-Signature, HMAC-SHA256 over "t.body").
 * Activation never depends on the browser returning from Stripe: the session is re-fetched server-side.
 */
class StripeGateway implements PaymentGateway
{
    use VerifiesSignedWebhooks;

    public function key(): string
    {
        return 'stripe';
    }

    public function isConfigured(): bool
    {
        return $this->cfg('secret_key') !== null && $this->cfg('secret_key') !== ''
            && $this->cfg('webhook_secret') !== null && $this->cfg('webhook_secret') !== '';
    }

    public function currency(): string
    {
        return Currency::normalise((string) $this->cfg('currency', 'USD'));
    }

    public function fxRate(): ?float
    {
        $rate = $this->cfg('fx_rate');

        return $rate !== null && (float) $rate > 0 ? (float) $rate : null;
    }

    public function mode(): string
    {
        $key = (string) $this->cfg('secret_key', '');

        return match (true) {
            str_starts_with($key, 'sk_test_'), str_starts_with($key, 'rk_test_') => 'test',
            str_starts_with($key, 'sk_live_'), str_starts_with($key, 'rk_live_') => 'live',
            default => 'unknown',
        };
    }

    public function createCheckout(BillingPayment $payment, array $context): array
    {
        $minutes = max(30, min(1440, (int) $this->cfg('session_minutes', 60)));
        $expiresAt = Carbon::now()->addMinutes($minutes);

        $response = $this->client()
            ->withHeaders(['Idempotency-Key' => 'schoolos-checkout-'.$payment->reference])
            ->asForm()
            ->post($this->url('/v1/checkout/sessions'), [
                'mode' => 'payment',
                'success_url' => $context['success_url'],
                'cancel_url' => $context['cancel_url'],
                'client_reference_id' => $payment->reference,
                'expires_at' => $expiresAt->getTimestamp(),
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower((string) $payment->charged_currency),
                        'unit_amount' => (int) $payment->charged_amount,
                        'product_data' => ['name' => $context['description']],
                    ],
                ]],
                'metadata' => ['payment_reference' => $payment->reference, 'school_id' => (string) $context['school_id']],
                'payment_intent_data' => ['metadata' => ['payment_reference' => $payment->reference]],
            ]);

        $body = $this->decode($response, 'create checkout');

        if (empty($body['id']) || empty($body['url'])) {
            throw new GatewayException('Stripe did not return a checkout session.');
        }

        return [
            'id' => (string) $body['id'],
            'url' => (string) $body['url'],
            'expires_at' => isset($body['expires_at']) ? Carbon::createFromTimestamp((int) $body['expires_at']) : $expiresAt,
        ];
    }

    public function fetch(string $providerReference): array
    {
        $body = $this->decode(
            $this->client()->get($this->url('/v1/checkout/sessions/'.rawurlencode($providerReference))),
            'retrieve checkout'
        );

        $paid = ($body['payment_status'] ?? null) === 'paid';
        $status = $body['status'] ?? null;

        return [
            'state' => $paid ? 'paid' : ($status === 'expired' ? 'expired' : 'open'),
            'amount' => (int) ($body['amount_total'] ?? 0),
            'currency' => Currency::normalise((string) ($body['currency'] ?? '')),
            'reference' => $body['client_reference_id'] ?? null,
            'transaction_id' => is_string($body['payment_intent'] ?? null) ? $body['payment_intent'] : null,
            'error' => null,
            'raw_status' => trim(($status ?? '?').'/'.($body['payment_status'] ?? '?')),
        ];
    }

    public function expire(string $providerReference): void
    {
        try {
            $this->client()->asForm()->post($this->url('/v1/checkout/sessions/'.rawurlencode($providerReference).'/expire'));
        } catch (\Throwable) {
            // Best effort - a late payment on the session is still handled by the verified webhook path.
        }
    }

    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        return $this->signatureMatches(
            $rawBody,
            $this->header($headers, 'stripe-signature'),
            (string) $this->cfg('webhook_secret', ''),
            (int) $this->cfg('webhook_tolerance', 300),
            '.',
        );
    }

    public function parseWebhook(array $payload): array
    {
        $type = $payload['type'] ?? null;
        $object = $payload['data']['object'] ?? [];

        $intent = match ($type) {
            'checkout.session.completed', 'checkout.session.async_payment_succeeded' => 'completed',
            'checkout.session.async_payment_failed' => 'failed',
            'checkout.session.expired' => 'expired',
            'charge.refunded' => 'refunded',
            default => 'ignore',
        };

        $isCharge = $type === 'charge.refunded';

        return [
            'event_id' => isset($payload['id']) ? (string) $payload['id'] : null,
            'type' => is_string($type) ? $type : null,
            'intent' => $intent,
            'provider_reference' => $isCharge ? null : (isset($object['id']) ? (string) $object['id'] : null),
            'payment_intent' => is_string($object['payment_intent'] ?? null) ? $object['payment_intent'] : null,
            'full_refund' => $isCharge
                && (int) ($object['amount'] ?? 0) > 0
                && (int) ($object['amount_refunded'] ?? 0) >= (int) ($object['amount'] ?? 0),
        ];
    }

    // ------------------------------------------------------------------ internals

    protected function client(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new GatewayException('Stripe is not configured.');
        }

        return Http::withBasicAuth((string) $this->cfg('secret_key'), '')
            ->acceptJson()
            ->timeout(20)
            ->connectTimeout(8);
    }

    protected function url(string $path): string
    {
        return rtrim((string) $this->cfg('api_base', 'https://api.stripe.com'), '/').$path;
    }

    /** @return array<string, mixed> */
    protected function decode(Response $response, string $what): array
    {
        if ($response->failed()) {
            $message = $response->json('error.message');
            throw new GatewayException(sprintf('Stripe could not %s (HTTP %d%s).', $what, $response->status(), $message ? ': '.mb_substr((string) $message, 0, 160) : ''));
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    protected function header(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) === $name) {
                return is_array($value) ? ($value[0] ?? null) : $value;
            }
        }

        return null;
    }

    protected function cfg(string $key, mixed $default = null): mixed
    {
        return config('schoolos.billing.stripe.'.$key, $default);
    }
}
