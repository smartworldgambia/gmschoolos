<?php

namespace App\Services\Billing\Gateways;

use App\Models\BillingPayment;
use App\Services\Billing\Gateways\Concerns\VerifiesSignedWebhooks;
use App\Support\Currency;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Wave Checkout API (https://docs.wave.com/checkout): POST /v1/checkout/sessions, GET /v1/checkout/sessions/:id,
 * POST /v1/checkout/sessions/:id/expire. Authentication is a Bearer API key; if the key was created with request
 * signing, every request also carries Wave-Signature (t=,v1= over timestamp + body).
 *
 * Webhooks use the strategy chosen when the webhook was registered in the Wave Business Portal:
 *   signing (recommended) - Wave-Signature header, HMAC-SHA256 over timestamp + raw body
 *   shared                - "Authorization: Bearer <secret>"
 * The configured strategy is enforced - a request cannot downgrade to the weaker one.
 *
 * Wave documents XOF only for the Checkout API. Charging in any other currency (including GMD) requires that Wave
 * has enabled it for the merchant; this class never assumes so.
 */
class WaveGateway implements PaymentGateway
{
    use VerifiesSignedWebhooks;

    public function key(): string
    {
        return 'wave';
    }

    public function isConfigured(): bool
    {
        return filled($this->cfg('api_key')) && filled($this->cfg('webhook_secret'));
    }

    public function currency(): string
    {
        return Currency::normalise((string) $this->cfg('currency', 'XOF'));
    }

    public function fxRate(): ?float
    {
        $rate = $this->cfg('fx_rate');

        return $rate !== null && (float) $rate > 0 ? (float) $rate : null;
    }

    public function mode(): string
    {
        $key = (string) $this->cfg('api_key', '');

        return match (true) {
            str_contains($key, '_sn_prod_') || str_contains($key, '_prod_') => 'live',
            str_contains($key, '_sn_test_') || str_contains($key, '_test_') || str_contains($key, '_sandbox_') => 'test',
            default => 'unknown',
        };
    }

    public function createCheckout(BillingPayment $payment, array $context): array
    {
        $response = $this->send('POST', '/v1/checkout/sessions', [
            'amount' => Currency::minorToDecimalString((int) $payment->charged_amount, (string) $payment->charged_currency),
            'currency' => Currency::normalise((string) $payment->charged_currency),
            'client_reference' => $payment->reference,
            'success_url' => $context['success_url'],
            'error_url' => $context['cancel_url'],
        ]);

        $body = $this->decode($response, 'create a checkout');

        if (empty($body['id']) || empty($body['wave_launch_url'])) {
            throw new GatewayException('Wave did not return a checkout session.');
        }

        return [
            'id' => (string) $body['id'],
            'url' => (string) $body['wave_launch_url'],
            'expires_at' => ! empty($body['when_expires']) ? Carbon::parse($body['when_expires']) : Carbon::now()->addMinutes((int) $this->cfg('session_minutes', 30)),
        ];
    }

    public function fetch(string $providerReference): array
    {
        $body = $this->decode($this->send('GET', '/v1/checkout/sessions/'.rawurlencode($providerReference)), 'retrieve the checkout');

        $currency = Currency::normalise((string) ($body['currency'] ?? ''));
        $checkout = $body['checkout_status'] ?? null; // open | complete | expired
        $payment = $body['payment_status'] ?? null;   // processing | cancelled | succeeded

        // "cancelled" is a failed ATTEMPT; the customer may retry until the session expires, so it is not final.
        $state = match (true) {
            $payment === 'succeeded' => 'paid',
            $checkout === 'expired' => 'expired',
            default => 'open',
        };

        return [
            'state' => $state,
            'amount' => Currency::decimalStringToMinor((string) ($body['amount'] ?? 0), $currency ?: 'XOF'),
            'currency' => $currency,
            'reference' => $body['client_reference'] ?? null,
            'transaction_id' => $body['transaction_id'] ?? null,
            'error' => $body['last_payment_error']['code'] ?? null,
            'raw_status' => trim(($checkout ?? '?').'/'.($payment ?? '?')),
        ];
    }

    public function expire(string $providerReference): void
    {
        try {
            $this->send('POST', '/v1/checkout/sessions/'.rawurlencode($providerReference).'/expire');
        } catch (\Throwable) {
            // Best effort - a late payment is still reconciled through the verified fetch path.
        }
    }

    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        $secret = (string) $this->cfg('webhook_secret', '');
        if ($secret === '') {
            return false;
        }

        if ($this->cfg('webhook_strategy', 'signing') === 'shared') {
            $auth = $this->header($headers, 'authorization');

            return $auth !== null && hash_equals('Bearer '.$secret, $auth);
        }

        return $this->signatureMatches(
            $rawBody,
            $this->header($headers, 'wave-signature'),
            $secret,
            (int) $this->cfg('webhook_tolerance', 300),
            '', // Wave signs timestamp + body with no separator
        );
    }

    public function parseWebhook(array $payload): array
    {
        $type = $payload['type'] ?? null;
        $data = $payload['data'] ?? [];

        return [
            'event_id' => isset($payload['id']) ? (string) $payload['id'] : null,
            'type' => is_string($type) ? $type : null,
            'intent' => match ($type) {
                'checkout.session.completed' => 'completed',
                'checkout.session.payment_failed' => 'failed',
                default => 'ignore',
            },
            'provider_reference' => isset($data['id']) ? (string) $data['id'] : null,
            'payment_intent' => null,
            'full_refund' => false,
        ];
    }

    // ------------------------------------------------------------------ internals

    /** @param  array<string, mixed>|null  $json */
    protected function send(string $method, string $path, ?array $json = null): Response
    {
        if (! filled($this->cfg('api_key'))) {
            throw new GatewayException('Wave is not configured.');
        }

        $body = $json !== null ? json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
        $headers = ['Authorization' => 'Bearer '.$this->cfg('api_key')];

        if (filled($this->cfg('request_signing_secret'))) {
            $timestamp = time();
            $headers['Wave-Signature'] = 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.$body, (string) $this->cfg('request_signing_secret'));
        }

        $request = Http::withHeaders($headers)->acceptJson()->timeout(20)->connectTimeout(8);
        $url = rtrim((string) $this->cfg('api_base', 'https://api.wave.com'), '/').$path;

        return $json !== null
            ? $request->withBody($body, 'application/json')->send($method, $url)
            : $request->send($method, $url);
    }

    /** @return array<string, mixed> */
    protected function decode(Response $response, string $what): array
    {
        if ($response->failed()) {
            $code = $response->json('code') ?? $response->json('error_code') ?? $response->json('error.code');
            throw new GatewayException(sprintf('Wave could not %s (HTTP %d%s).', $what, $response->status(), $code ? ': '.mb_substr((string) $code, 0, 80) : ''));
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
        return config('schoolos.billing.wave.'.$key, $default);
    }
}
