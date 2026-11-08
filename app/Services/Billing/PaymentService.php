<?php

namespace App\Services\Billing;

use App\Models\BillingEvent;
use App\Models\BillingPayment;
use App\Models\PlatformBankAccount;
use App\Models\School;
use App\Models\SchoolSubscription;
use App\Models\User;
use App\Services\Billing\Gateways\GatewayException;
use App\Services\Billing\Gateways\PaymentGateway;
use App\Services\Billing\Gateways\StripeGateway;
use App\Services\Billing\Gateways\WaveGateway;
use App\Services\School\SchoolDomainService;
use App\Support\Currency;
use App\Support\Money;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Payment workflows for the annual subscription: cash, bank transfer, Wave and Stripe.
 *
 * Invariants (each is covered by tests):
 *  - Only {@see SubscriptionLifecycle::recordVerifiedPayment()} activates a subscription, and only for a full payment.
 *  - Cash / bank: activation needs an authorised officer's confirmation. An uploaded transfer receipt is evidence for
 *    that officer to check against the real bank statement - it is never proof of payment.
 *  - Wave / Stripe: activation needs the PROVIDER's server-side confirmation (webhook-triggered or reconciled fetch)
 *    with the exact amount, currency and our reference. A browser returning to a "success" page changes nothing.
 *  - Nothing is converted silently: the charge amount/currency (and any FX rate) are stored, shown and matched.
 *  - Every step writes a billing_events audit row.
 */
class PaymentService
{
    public function __construct(
        protected TenantContext $tenant,
        protected SubscriptionLifecycle $lifecycle,
        protected BillingNotifier $notifier,
    ) {}

    // ------------------------------------------------------------------ method availability

    public function gateway(string $method): ?PaymentGateway
    {
        return match ($method) {
            'stripe' => app(StripeGateway::class),
            'wave' => app(WaveGateway::class),
            default => null,
        };
    }

    /**
     * @return array{key: string, enabled: bool, configured: bool, available: bool, reason: ?string, charge_currency: ?string, fx_rate: ?float}
     */
    public function availability(string $method, string $priceCurrency): array
    {
        $enabled = (bool) config("schoolos.billing.payment_methods.{$method}.enabled", false);
        $configured = true;
        $reason = null;
        $chargeCurrency = null;
        $rate = null;

        if ($method === 'bank_transfer') {
            $configured = PlatformBankAccount::query()->where('is_active', true)->exists();
            $reason = $configured ? null : 'no_bank_account';
        } elseif ($gateway = $this->gateway($method)) {
            $chargeCurrency = $gateway->currency();
            $rate = $chargeCurrency === Currency::normalise($priceCurrency) ? null : $gateway->fxRate();

            if (! $gateway->isConfigured()) {
                $configured = false;
                $reason = 'not_configured';
            } elseif ($chargeCurrency !== Currency::normalise($priceCurrency) && $rate === null) {
                $configured = false;
                $reason = 'no_currency_strategy';
            }
        }

        return [
            'key' => $method,
            'enabled' => $enabled,
            'configured' => $configured,
            'available' => $enabled && $configured,
            'reason' => $enabled ? $reason : 'disabled',
            'charge_currency' => $chargeCurrency,
            'fx_rate' => $rate,
        ];
    }

    /**
     * What a gateway will charge for a total (minor units, currency, rate) - or null when it cannot be done transparently.
     *
     * @return array{amount: int, currency: string, rate: ?float, label: string}|null
     */
    public function chargeFor(string $method, int $total, string $priceCurrency): ?array
    {
        $gateway = $this->gateway($method);
        if (! $gateway) {
            return null;
        }

        $same = $gateway->currency() === Currency::normalise($priceCurrency);
        $minor = Currency::convertToMinor($total, $priceCurrency, $gateway->currency(), $gateway->fxRate());
        if ($minor === null) {
            return null;
        }

        return [
            'amount' => $minor,
            'currency' => $gateway->currency(),
            'rate' => $same ? null : $gateway->fxRate(),
            'label' => Currency::labelMinor($minor, $gateway->currency()),
        ];
    }

    // ------------------------------------------------------------------ school: initiate / cancel / evidence

    /**
     * Start (or return the already open) payment for the full annual amount.
     *
     * @param  int|null  $expectedCharge  gateway only: the charge (minor units) the payer was shown; must still match
     *
     * @throws InvalidArgumentException
     */
    public function initiate(School $school, User $actor, string $method, ?int $expectedCharge = null): BillingPayment
    {
        if (! in_array($method, SubscriptionLifecycle::METHODS, true)) {
            throw new InvalidArgumentException('Unsupported payment method.');
        }

        $payment = $this->tenant->runForSchool($school, function () use ($school, $actor, $method, $expectedCharge) {
            return DB::transaction(function () use ($school, $actor, $method, $expectedCharge) {
                $subscription = SchoolSubscription::query()->where('school_id', $school->id)->lockForUpdate()->first();
                if (! $subscription) {
                    throw new InvalidArgumentException('This school has no subscription record.');
                }
                if ($subscription->status === SchoolSubscription::CANCELLED) {
                    throw new InvalidArgumentException('This subscription was cancelled. Contact the platform to reactivate it.');
                }

                $quote = $this->lifecycle->quote($subscription);
                if (! $quote['configured']) {
                    throw new InvalidArgumentException('No annual price is configured for this school type yet.');
                }

                $availability = $this->availability($method, $quote['currency']);
                if (! $availability['available']) {
                    throw new InvalidArgumentException('This payment method is not available right now.');
                }

                $open = BillingPayment::query()->whereIn('status', BillingPayment::OPEN)->latest('id')->first();
                if ($open) {
                    if ($open->expires_at && $open->expires_at->isPast() && $open->status === BillingPayment::PENDING) {
                        $this->markTerminal($open, BillingPayment::EXPIRED, 'payment_expired', null, ['reason' => 'past_expiry']);
                    } elseif ($open->method === $method) {
                        return $open; // idempotent: a second click never creates a second payment
                    } else {
                        throw new InvalidArgumentException('You already have an open payment ('.$open->reference.'). Complete or cancel it before choosing another method.');
                    }
                }

                $charge = null;
                if ($gateway = $this->gateway($method)) {
                    $charge = $this->chargeFor($method, (int) $quote['total'], $quote['currency']);
                    if (! $charge) {
                        throw new InvalidArgumentException('No transparent currency strategy is configured for this payment method.');
                    }
                    if ($expectedCharge !== null && $expectedCharge !== $charge['amount']) {
                        throw new InvalidArgumentException('The amount to be charged has changed. Please review it and try again.');
                    }
                }

                $now = now();
                $payment = BillingPayment::query()->create([
                    'school_id' => $school->id,
                    'school_subscription_id' => $subscription->id,
                    'reference' => 'SOS-'.$school->id.'-'.$now->format('Ymd').'-'.Str::upper(Str::random(6)),
                    'method' => $method,
                    'provider' => $gateway?->key(),
                    'amount' => $quote['amount'],
                    'tax_amount' => $quote['tax_amount'],
                    'total_amount' => $quote['total'],
                    'currency' => $quote['currency'],
                    'charged_amount' => $charge['amount'] ?? null,
                    'charged_currency' => $charge['currency'] ?? null,
                    'fx_rate' => $charge['rate'] ?? null,
                    'status' => BillingPayment::PENDING,
                    'school_type' => $subscription->school_type,
                    'initiated_by' => $actor->id,
                    'expires_at' => $gateway ? null : $now->copy()->addDays(max(1, (int) config('schoolos.billing.open_payment_ttl_days', 30))),
                ]);

                $this->audit($payment, 'payment_initiated', $actor, [
                    'total' => $quote['total'],
                    'currency' => $quote['currency'],
                    'charged' => $charge ? $charge['label'] : null,
                    'fx_rate' => $charge['rate'] ?? null,
                ]);

                return $payment;
            });
        });

        // A freshly created gateway payment still needs its hosted session (HTTP call kept outside the DB transaction).
        if ($payment->isGateway() && ! $payment->provider_reference && $payment->status === BillingPayment::PENDING) {
            $payment = $this->openGatewaySession($payment, $actor);
        }

        return $payment;
    }

    protected function openGatewaySession(BillingPayment $payment, User $actor): BillingPayment
    {
        $school = School::query()->findOrFail($payment->school_id);
        $gateway = $this->gateway($payment->method);

        try {
            $session = $gateway->createCheckout($payment, [
                'success_url' => $this->returnUrl($school, $payment, 'success'),
                'cancel_url' => $this->returnUrl($school, $payment, 'cancelled'),
                'description' => 'Gambia SchoolOS annual subscription - '.$school->name,
                'school_id' => $school->id,
            ]);
        } catch (GatewayException|\Illuminate\Http\Client\ConnectionException $e) {
            $this->inSchool($payment, function () use ($payment, $actor, $e) {
                $fresh = BillingPayment::query()->findOrFail($payment->id);
                $this->markTerminal($fresh, BillingPayment::FAILED, 'payment_initiation_failed', $actor, ['error' => mb_substr($e->getMessage(), 0, 300)], mb_substr($e->getMessage(), 0, 300));
            });
            report($e);

            throw new InvalidArgumentException('We could not start the payment with the provider. Nothing was charged. Please try again or choose another method.');
        }

        return $this->inSchool($payment, function () use ($payment, $session, $actor) {
            $fresh = BillingPayment::query()->findOrFail($payment->id);
            $fresh->forceFill([
                'provider_reference' => $session['id'],
                'checkout_url' => $session['url'],
                'expires_at' => $session['expires_at'],
            ])->save();
            $this->audit($fresh, 'gateway_session_created', $actor, ['provider' => $fresh->provider, 'session' => $session['id']]);

            return $fresh;
        });
    }

    /** The school may cancel a payment that has not been submitted for verification. */
    public function cancel(BillingPayment $payment, User $actor): BillingPayment
    {
        $result = $this->inSchool($payment, function () use ($payment, $actor) {
            $fresh = BillingPayment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($fresh->status !== BillingPayment::PENDING) {
                throw new InvalidArgumentException($fresh->status === BillingPayment::PENDING_VERIFICATION
                    ? 'This payment is awaiting verification by the finance team and can no longer be cancelled.'
                    : 'Only a pending payment can be cancelled.');
            }
            $this->markTerminal($fresh, BillingPayment::CANCELLED, 'payment_cancelled', $actor);

            return $fresh;
        });

        if ($result->isGateway() && $result->provider_reference) {
            $this->gateway($result->method)?->expire($result->provider_reference);
        }

        return $result;
    }

    /**
     * Attach bank-transfer evidence and queue the payment for an officer to verify against the REAL bank statement.
     * Nothing about this step can activate the subscription.
     *
     * @param  array{claimed_reference?: ?string, transferred_on?: ?string, note?: ?string}  $claim
     */
    public function attachEvidence(BillingPayment $payment, UploadedFile $file, User $actor, array $claim = []): BillingPayment
    {
        $allowed = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
        $mime = (string) $file->getMimeType();
        if (! isset($allowed[$mime])) {
            throw new InvalidArgumentException('Upload a PDF, JPG or PNG file.');
        }
        if ($file->getSize() > (int) config('schoolos.billing.evidence.max_kb', 5120) * 1024) {
            throw new InvalidArgumentException('That file is too large.');
        }

        return $this->inSchool($payment, function () use ($payment, $file, $actor, $claim, $mime, $allowed) {
            $fresh = BillingPayment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($fresh->method !== 'bank_transfer') {
                throw new InvalidArgumentException('Evidence is only used for bank transfers.');
            }
            if (! in_array($fresh->status, BillingPayment::OPEN, true)) {
                throw new InvalidArgumentException('This payment is closed.');
            }

            $disk = (string) config('schoolos.billing.evidence.disk', 'local');
            $name = $fresh->id.'-'.Str::lower(Str::random(32)).'.'.$allowed[$mime]; // never the client-supplied name
            $path = Storage::disk($disk)->putFileAs('billing-evidence/'.$fresh->school_id, $file, $name);

            if ($fresh->evidence_path && $fresh->evidence_path !== $path) {
                Storage::disk($disk)->delete($fresh->evidence_path);
            }

            $fresh->forceFill([
                'evidence_path' => $path,
                'evidence_original_name' => mb_substr(basename((string) $file->getClientOriginalName()), 0, 180),
                'evidence_mime' => $mime,
                'evidence_size' => $file->getSize(),
                'evidence_uploaded_at' => now(),
                'status' => BillingPayment::PENDING_VERIFICATION,
                'meta' => array_merge((array) $fresh->meta, ['claimed' => array_filter([
                    'reference' => $claim['claimed_reference'] ?? null,
                    'transferred_on' => $claim['transferred_on'] ?? null,
                    'note' => $claim['note'] ?? null,
                ])]),
            ])->save();

            $this->flagSubscription($fresh, 'pending_verification');
            $this->audit($fresh, 'evidence_uploaded', $actor, [
                'size' => $file->getSize(), 'mime' => $mime, 'sha256' => hash_file('sha256', $file->getRealPath()),
                'note' => 'Evidence is not proof of payment; awaiting verification against the bank statement.',
            ]);

            return $fresh;
        });
    }

    // ------------------------------------------------------------------ platform finance: confirm / approve / reject

    /**
     * Cash received at an authorised office.
     *
     * @param  array{amount: int, paid_at?: mixed, receipt_number?: ?string, notes?: ?string}  $data
     */
    public function confirmCash(BillingPayment $payment, User $officer, array $data): BillingPayment
    {
        return $this->settleManual($payment, $officer, 'cash', [
            'amount' => (int) $data['amount'],
            'paid_at' => $data['paid_at'] ?? now(),
            'receipt_number' => $data['receipt_number'] ?? null,
            'notes' => $data['notes'] ?? null,
            'transaction_reference' => null,
        ]);
    }

    /**
     * Approve a bank transfer after the officer has found the transfer on the REAL bank statement.
     *
     * @param  array{amount: int, transaction_reference: string, paid_at?: mixed, notes?: ?string}  $data
     */
    public function approveBank(BillingPayment $payment, User $officer, array $data): BillingPayment
    {
        return $this->settleManual($payment, $officer, 'bank_transfer', [
            'amount' => (int) $data['amount'],
            'paid_at' => $data['paid_at'] ?? now(),
            'receipt_number' => null,
            'notes' => $data['notes'] ?? null,
            'transaction_reference' => $data['transaction_reference'],
        ]);
    }

    /** @param  array<string, mixed>  $data */
    protected function settleManual(BillingPayment $payment, User $officer, string $method, array $data): BillingPayment
    {
        $school = School::query()->findOrFail($payment->school_id);

        if ($payment->method !== $method) {
            throw new InvalidArgumentException('That action does not apply to this payment method.');
        }

        if ($data['receipt_number'] && BillingPayment::withoutGlobalScopes()
            ->where('receipt_number', $data['receipt_number'])->where('id', '!=', $payment->id)->exists()) {
            throw new InvalidArgumentException('That receipt number has already been used.');
        }

        try {
            $verified = $this->lifecycle->recordVerifiedPayment($school, [
                'method' => $method,
                'payment_id' => $payment->id,
                'amount' => $data['amount'],
                'transaction_reference' => $data['transaction_reference'],
                'paid_at' => $data['paid_at'],
                'receipt_number' => $data['receipt_number'],
                'notes' => $data['notes'],
                'meta' => ['confirmed_by_officer' => $officer->id, 'confirmation' => $method === 'cash' ? 'cash_received' : 'bank_statement_checked'],
            ], $officer);
        } catch (InvalidArgumentException $e) {
            $this->inSchool($payment, fn () => $this->audit(
                BillingPayment::query()->findOrFail($payment->id),
                'payment_confirmation_refused',
                $officer,
                ['reason' => $e->getMessage(), 'received_amount' => $data['amount']],
            ));

            throw $e;
        }

        $this->inSchool($verified, fn () => $this->audit($verified, 'payment_verified', $officer, [
            'method' => $method,
            'amount' => $data['amount'],
            'transaction_reference' => $data['transaction_reference'],
            'receipt_number' => $verified->receipt_number,
        ]));

        return $verified;
    }

    public function reject(BillingPayment $payment, User $officer, string $reason): BillingPayment
    {
        $result = $this->inSchool($payment, function () use ($payment, $officer, $reason) {
            $fresh = BillingPayment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($fresh->isGateway()) {
                throw new InvalidArgumentException('Gateway payments are settled by the provider, not rejected manually.');
            }
            if (! in_array($fresh->status, BillingPayment::OPEN, true)) {
                throw new InvalidArgumentException('Only an open payment can be rejected.');
            }

            $fresh->forceFill([
                'status' => BillingPayment::REJECTED,
                'rejection_reason' => $reason,
                'rejected_at' => now(),
                'rejected_by' => $officer->id,
            ])->save();

            $this->flagSubscription($fresh, null);
            $this->audit($fresh, 'payment_rejected', $officer, ['reason' => $reason]);

            return $fresh;
        });

        $this->inSchool($result, function () use ($result, $reason) {
            $subscription = SchoolSubscription::query()->where('school_id', $result->school_id)->first();
            if ($subscription) {
                $this->notifier->send($subscription, 'payment_rejected', $result->reference, [
                    'payment_reference' => $result->reference, 'reason' => $reason,
                ]);
            }
        });

        return $result;
    }

    // ------------------------------------------------------------------ provider truth

    /** Ask the provider (server-to-server) what happened and apply it. Never trusts the browser. */
    public function refresh(BillingPayment $payment, string $source = 'refresh'): BillingPayment
    {
        if (! $payment->isGateway() || ! $payment->provider_reference) {
            return $payment;
        }
        if (in_array($payment->status, [BillingPayment::VERIFIED, BillingPayment::REFUNDED, BillingPayment::REJECTED], true)) {
            return $payment;
        }

        $state = $this->gateway($payment->method)->fetch($payment->provider_reference);

        return $this->applyProviderState($payment, $state, $source);
    }

    /**
     * @param  array{state: string, amount: int, currency: string, reference: ?string, transaction_id: ?string, error: ?string, raw_status: ?string}  $state
     */
    public function applyProviderState(BillingPayment $payment, array $state, string $source): BillingPayment
    {
        $outcome = $this->inSchool($payment, function () use ($payment, $state, $source) {
            $fresh = BillingPayment::query()->lockForUpdate()->findOrFail($payment->id);

            if (in_array($fresh->status, [BillingPayment::VERIFIED, BillingPayment::REFUNDED], true)) {
                if ($state['state'] === 'paid') {
                    $this->audit($fresh, 'duplicate_confirmation_ignored', null, ['source' => $source]);
                }

                return ['payment' => $fresh, 'settle' => false];
            }

            if ($fresh->status === BillingPayment::FAILED && ! empty($fresh->meta['mismatch'])) {
                return ['payment' => $fresh, 'settle' => false]; // already escalated to a human; never re-audit or re-settle
            }

            if ($state['state'] === 'paid') {
                $problems = $this->mismatches($fresh, $state);
                if ($problems) {
                    // Money may have moved but it does not match what we asked for: never activate, escalate to a human.
                    $fresh->forceFill([
                        'status' => BillingPayment::FAILED,
                        'failed_at' => now(),
                        'failure_reason' => 'Provider payment does not match the request: '.implode('; ', $problems),
                        'meta' => array_merge((array) $fresh->meta, ['mismatch' => true, 'provider_observed' => [
                            'amount' => $state['amount'], 'currency' => $state['currency'], 'reference' => $state['reference'], 'status' => $state['raw_status'],
                        ]]),
                    ])->save();
                    $this->flagSubscription($fresh, null);
                    $this->audit($fresh, 'payment_mismatch', null, ['source' => $source, 'problems' => $problems]);

                    return ['payment' => $fresh, 'settle' => false];
                }


                return ['payment' => $fresh, 'settle' => true, 'late' => ! in_array($fresh->status, BillingPayment::OPEN, true)];
            }

            if ($state['state'] === 'expired' && in_array($fresh->status, BillingPayment::OPEN, true)) {
                $this->markTerminal($fresh, BillingPayment::EXPIRED, 'payment_expired', null, ['source' => $source, 'provider_status' => $state['raw_status']]);
            } elseif ($state['state'] === 'open' && $state['error'] && ($fresh->meta['last_provider_error'] ?? null) !== $state['error']) {
                $fresh->forceFill(['meta' => array_merge((array) $fresh->meta, ['last_provider_error' => $state['error']])])->save();
                $this->audit($fresh, 'provider_attempt_failed', null, ['source' => $source, 'error' => $state['error']]);
            }

            return ['payment' => $fresh, 'settle' => false];
        });

        if (! $outcome['settle']) {
            return $outcome['payment'];
        }

        $school = School::query()->findOrFail($payment->school_id);

        try {
            $verified = $this->lifecycle->recordVerifiedPayment($school, [
                'method' => $payment->method,
                'payment_id' => $payment->id,
                'late' => $outcome['late'],
                'amount' => (int) $payment->total_amount,
                'transaction_reference' => $state['transaction_id'],
                'paid_at' => now(),
                'provider_confirmed_at' => now(),
                'meta' => [
                    'provider' => $payment->provider,
                    'provider_status' => $state['raw_status'],
                    'confirmed_via' => $source,
                    'charged' => Currency::labelMinor($state['amount'], $state['currency']),
                ],
            ], null);
        } catch (InvalidArgumentException $e) {
            // The provider says paid, but our rules refuse to settle (e.g. duplicate transaction id): escalate, don't loop.
            return $this->inSchool($payment, function () use ($payment, $e, $source) {
                $fresh = BillingPayment::query()->findOrFail($payment->id);
                $fresh->forceFill(['meta' => array_merge((array) $fresh->meta, ['settlement_error' => $e->getMessage()])])->save();
                $this->audit($fresh, 'payment_settlement_error', null, ['source' => $source, 'error' => $e->getMessage()]);

                return $fresh;
            });
        }

        $this->inSchool($verified, fn () => $this->audit($verified, 'payment_verified', null, [
            'source' => $source,
            'provider' => $verified->provider,
            'transaction_reference' => $verified->transaction_reference,
            'receipt_number' => $verified->receipt_number,
            'late' => $outcome['late'],
        ]));

        return $verified;
    }

    /** A provider-reported terminal failure (e.g. Stripe async payment failure). */
    public function markFailed(BillingPayment $payment, string $reason, string $source): BillingPayment
    {
        return $this->inSchool($payment, function () use ($payment, $reason, $source) {
            $fresh = BillingPayment::query()->lockForUpdate()->findOrFail($payment->id);
            if (! in_array($fresh->status, BillingPayment::OPEN, true)) {
                return $fresh;
            }
            $this->markTerminal($fresh, BillingPayment::FAILED, 'payment_failed', null, ['source' => $source, 'reason' => $reason], $reason);

            return $fresh;
        });
    }

    /**
     * Provider reports a full refund. The payment is marked refunded and the audit trail flags it; whether access is
     * withdrawn is a business decision for an authorised operator - it is NOT changed automatically.
     */
    public function markRefunded(BillingPayment $payment, string $source): BillingPayment
    {
        return $this->inSchool($payment, function () use ($payment, $source) {
            $fresh = BillingPayment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($fresh->status !== BillingPayment::VERIFIED) {
                return $fresh;
            }
            $fresh->forceFill(['status' => BillingPayment::REFUNDED, 'refunded_at' => now()])->save();
            $this->audit($fresh, 'payment_refunded', null, [
                'source' => $source,
                'note' => 'Subscription access was not changed automatically; an authorised operator must decide.',
            ]);

            return $fresh;
        });
    }

    // ------------------------------------------------------------------ audit & helpers

    public function audit(BillingPayment $payment, string $type, ?User $actor = null, array $meta = []): void
    {
        BillingEvent::query()->create([
            'school_id' => $payment->school_id,
            'school_subscription_id' => $payment->school_subscription_id,
            'billing_payment_id' => $payment->id,
            'type' => $type,
            'actor_id' => $actor?->id,
            'meta' => $meta + ['reference' => $payment->reference, 'method' => $payment->method],
        ]);
    }

    /** @param  array<string, mixed>  $state */
    protected function mismatches(BillingPayment $payment, array $state): array
    {
        $problems = [];

        if ((int) $state['amount'] !== (int) $payment->charged_amount) {
            $problems[] = sprintf('amount %d != expected %d', $state['amount'], (int) $payment->charged_amount);
        }
        if (Currency::normalise($state['currency']) !== Currency::normalise((string) $payment->charged_currency)) {
            $problems[] = sprintf('currency %s != expected %s', $state['currency'], $payment->charged_currency);
        }
        if (($state['reference'] ?? null) !== $payment->reference) {
            $problems[] = 'reference does not match';
        }

        return $problems;
    }

    protected function markTerminal(BillingPayment $payment, string $status, string $event, ?User $actor, array $meta = [], ?string $failure = null): void
    {
        $now = now();
        $payment->forceFill(['status' => $status]
            + ($status === BillingPayment::CANCELLED ? ['cancelled_at' => $now] : [])
            + ($status === BillingPayment::FAILED ? ['failed_at' => $now, 'failure_reason' => $failure] : [])
        )->save();

        $this->flagSubscription($payment, null);
        $this->audit($payment, $event, $actor, $meta);
    }

    /** Keep the subscription's payment_status honest while a payment awaits verification. */
    protected function flagSubscription(BillingPayment $payment, ?string $flag): void
    {
        $subscription = SchoolSubscription::query()->where('school_id', $payment->school_id)->first();
        if (! $subscription) {
            return;
        }

        if ($flag === 'pending_verification') {
            if (in_array($this->lifecycle->effectiveStatus($subscription), [SchoolSubscription::EXPIRED, SchoolSubscription::SUSPENDED], true)) {
                $subscription->forceFill(['payment_status' => 'pending_verification'])->save();
            }

            return;
        }

        $stillWaiting = BillingPayment::query()->where('status', BillingPayment::PENDING_VERIFICATION)->where('id', '!=', $payment->id)->exists();
        if ($subscription->payment_status === 'pending_verification' && ! $stillWaiting) {
            $subscription->forceFill(['payment_status' => 'unpaid'])->save();
        }
    }

    /** @template T @param Closure(): T $callback @return T */
    protected function inSchool(BillingPayment $payment, Closure $callback): mixed
    {
        $school = School::query()->findOrFail($payment->school_id);

        return $this->tenant->runForSchool($school, fn () => $callback());
    }

    /** Absolute URL on the school's own host so the payer returns to an authenticated session. */
    public function returnUrl(School $school, BillingPayment $payment, string $result): string
    {
        $urls = app(SchoolDomainService::class)->urlsFor($school);
        $base = preg_replace('#/admin/?$#', '', (string) ($urls['admin_url'] ?? url('/')));

        return rtrim($base, '/').'/billing/payments/'.$payment->reference.'/return?result='.$result;
    }

    /** Data for the printable receipt (verified or later refunded payments only). */
    public function receipt(BillingPayment $payment): array
    {
        $payment->loadMissing('school:id,name,address,email', 'verifier:id,name');

        return [
            'receipt_number' => $payment->receipt_number,
            'reference' => $payment->reference,
            'status' => $payment->status,
            'school' => $payment->school?->name,
            'school_type' => $payment->school_type,
            'method' => $payment->method,
            'transaction_reference' => $payment->transaction_reference,
            'currency' => $payment->currency,
            'amount_label' => Money::formatCurrency($payment->amount, $payment->currency),
            'tax_label' => $payment->tax_amount ? Money::formatCurrency($payment->tax_amount, $payment->currency) : null,
            'total_label' => Money::formatCurrency($payment->total_amount, $payment->currency),
            'charged_label' => $payment->charged_amount !== null && $payment->charged_currency
                ? Currency::labelMinor((int) $payment->charged_amount, $payment->charged_currency)
                : null,
            'fx_rate' => $payment->fx_rate,
            'paid_at' => $payment->paid_at?->toDateString(),
            'period_start' => $payment->period_start?->toDateString(),
            'period_end' => $payment->period_end?->toDateString(),
            'verified_by' => $payment->verifier?->name,
            'verified_at' => $payment->verified_at?->toDateTimeString(),
            'issuer' => [
                'name' => 'Gambia SchoolOS',
                'address' => config('schoolos.marketing.address'),
                'email' => config('schoolos.marketing.contact_email'),
            ],
        ];
    }

    public function bankAccounts(): \Illuminate\Support\Collection
    {
        return PlatformBankAccount::query()->where('is_active', true)->orderBy('id')->get();
    }
}
