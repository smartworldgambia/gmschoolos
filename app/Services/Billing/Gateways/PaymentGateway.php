<?php

namespace App\Services\Billing\Gateways;

use App\Models\BillingPayment;
use Illuminate\Http\Client\ConnectionException;

/**
 * A hosted-checkout payment provider. The provider's server-side answer (fetch) is the ONLY source of truth
 * for "was this paid" - never a browser redirect, a screenshot or an unverified reference.
 *
 * Normalised session state returned by fetch():
 *   state:          paid | open | expired | failed
 *   amount:         int  minor units the provider recorded
 *   currency:       string upper-case ISO code the provider recorded
 *   reference:      ?string  our payment reference echoed back by the provider
 *   transaction_id: ?string  provider transaction / payment-intent id
 *   error:          ?string  last failure message (never raw card data)
 */
interface PaymentGateway
{
    public function key(): string;

    /** Keys + webhook secret present (a necessary, not sufficient, condition for "verified"). */
    public function isConfigured(): bool;

    /** Currency the provider charges in. */
    public function currency(): string;

    /** Charge-currency units per 1 unit of the price currency; null when no conversion is configured. */
    public function fxRate(): ?float;

    /** @return string 'test' | 'live' | 'unknown' (derived from the key prefix, never from the key itself) */
    public function mode(): string;

    /**
     * @param  array{success_url: string, cancel_url: string, description: string, school_id: int}  $context
     * @return array{id: string, url: string, expires_at: ?\Illuminate\Support\Carbon}
     *
     * @throws GatewayException
     */
    public function createCheckout(BillingPayment $payment, array $context): array;

    /**
     * @return array{state: string, amount: int, currency: string, reference: ?string, transaction_id: ?string, error: ?string, raw_status: ?string}
     *
     * @throws GatewayException
     * @throws ConnectionException
     */
    public function fetch(string $providerReference): array;

    /** Best effort: ask the provider to stop accepting payment on an open session. */
    public function expire(string $providerReference): void;

    /** Authenticate a webhook request from its RAW body + headers. */
    public function verifyWebhook(string $rawBody, array $headers): bool;

    /**
     * Interpret a (verified) webhook payload.
     *
     * @return array{event_id: ?string, type: ?string, intent: string, provider_reference: ?string, payment_intent: ?string, full_refund: bool}
     *   intent: completed | failed | expired | refunded | ignore
     */
    public function parseWebhook(array $payload): array;
}
