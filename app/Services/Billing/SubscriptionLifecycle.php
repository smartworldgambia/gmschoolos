<?php

namespace App\Services\Billing;

use App\Models\BillingEvent;
use App\Models\BillingPayment;
use App\Models\School;
use App\Models\SchoolSubscription;
use App\Models\User;
use App\Support\Money;
use App\Support\SchoolTypes;
use App\Support\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The annual subscription lifecycle: trial -> active -> expired -> suspended -> (verified payment) active.
 *
 * Rules enforced here:
 *  - exactly one subscription per school, annual cycle only (no monthly periods ever);
 *  - a 30-day trial starts automatically at provisioning (days configurable, never payment-gated);
 *  - only a VERIFIED, FULL annual payment activates / renews / reactivates;
 *  - a paid period lasts 12 calendar months (leap-year safe) and renewals extend from the existing end;
 *  - lapse never deletes data - it only changes status (access is enforced by SchoolAccessGate);
 *  - every transition is written to billing_events.
 */
class SubscriptionLifecycle
{
    public const METHODS = ['cash', 'bank_transfer', 'wave', 'stripe'];

    public function __construct(
        protected TenantContext $tenant,
        protected PricingService $pricing,
        protected BillingNotifier $notifier,
    ) {}

    // ------------------------------------------------------------------ trial

    public function startTrial(School $school, ?int $days = null, ?string $schoolType = null, ?User $actor = null): SchoolSubscription
    {
        return $this->tenant->runForSchool($school, function () use ($school, $days, $schoolType, $actor) {
            $existing = SchoolSubscription::query()->where('school_id', $school->id)->first();
            if ($existing) {
                return $existing; // exactly one subscription per school
            }

            $days = max(1, $days ?? (int) config('schoolos.trial_days', 30));
            $type = SchoolTypes::normalise($schoolType ?? $school->category);
            $price = $this->pricing->current($type);
            $start = now();

            $subscription = SchoolSubscription::query()->create([
                'school_id' => $school->id,
                'school_type' => $type,
                'annual_amount' => $price?->amount,
                'currency' => $price?->currency ?? config('schoolos.billing.currency', 'GMD'),
                'tax_rate' => $price?->tax_rate,
                'status' => SchoolSubscription::TRIAL,
                'payment_status' => 'not_required_during_trial',
                'starts_at' => $start,
                'ends_at' => $start->copy()->addDays($days),
                'trial_starts_at' => $start,
                'trial_ends_at' => $start->copy()->addDays($days),
            ]);

            $this->event($subscription, 'trial_started', $actor, ['days' => $days, 'school_type' => $type]);

            return $subscription;
        });
    }

    // ------------------------------------------------------------------ price / quote

    /** What the school owes for the next annual period (live price for its school type). */
    public function quote(SchoolSubscription $subscription): array
    {
        $price = $this->pricing->current((string) $subscription->school_type);

        if (! $price) {
            return ['configured' => false, 'amount' => null, 'tax_amount' => 0, 'total' => null,
                'currency' => $subscription->currency, 'tax_rate' => null, 'school_type' => $subscription->school_type];
        }

        return [
            'configured' => true,
            'amount' => $price->amount,
            'tax_amount' => $price->taxAmount(),
            'total' => $price->totalAmount(),
            'currency' => $price->currency,
            'tax_rate' => $price->tax_rate,
            'school_type' => $subscription->school_type,
        ];
    }

    // ------------------------------------------------------------------ state for dashboards

    /** Status as the calendar sees it (a lapsed row is "expired" even if the scheduler has not run yet). */
    public function effectiveStatus(SchoolSubscription $subscription, ?Carbon $now = null): string
    {
        $now ??= now();

        if (in_array($subscription->status, [SchoolSubscription::SUSPENDED, SchoolSubscription::CANCELLED], true)) {
            return $subscription->status;
        }

        if ($subscription->ends_at && $now->greaterThanOrEqualTo($subscription->ends_at)) {
            return $this->graceEnd($subscription)->lessThanOrEqualTo($now)
                ? SchoolSubscription::SUSPENDED
                : SchoolSubscription::EXPIRED;
        }

        return $subscription->status === SchoolSubscription::EXPIRED
            ? SchoolSubscription::EXPIRED
            : $subscription->status;
    }

    public function graceEnd(SchoolSubscription $subscription): Carbon
    {
        return $subscription->ends_at->copy()->addDays(max(0, (int) config('schoolos.access.grace_days', 0)));
    }

    /** Calendar days left (0 = ends today, negative = already lapsed). */
    public function daysRemaining(SchoolSubscription $subscription, ?Carbon $now = null): ?int
    {
        if (! $subscription->ends_at) {
            return null;
        }

        return (int) ($now ?? now())->copy()->startOfDay()->diffInDays($subscription->ends_at->copy()->startOfDay(), false);
    }

    /** @return array<string, mixed>|null */
    public function state(School $school, ?Carbon $now = null): ?array
    {
        $subscription = $this->tenant->runForSchool($school, fn () => SchoolSubscription::query()
            ->where('school_id', $school->id)->first());

        if (! $subscription) {
            return null;
        }

        $quote = $this->quote($subscription);
        $status = $this->effectiveStatus($subscription, $now);
        $isPaidUp = $status === SchoolSubscription::ACTIVE;
        $outstanding = $isPaidUp ? null : $quote['total'];

        return [
            'status' => $status,
            'payment_status' => $status === SchoolSubscription::TRIAL
                ? 'not_required_during_trial'
                : ($isPaidUp ? $subscription->payment_status : ($subscription->payment_status === 'pending_verification' ? 'pending_verification' : 'unpaid')),
            'school_type' => $subscription->school_type,
            'currency' => $quote['currency'],
            'annual_amount' => $quote['amount'],
            'annual_total' => $quote['total'],
            'annual_amount_label' => $quote['configured'] ? Money::formatCurrency($quote['total'], $quote['currency']) : null,
            'tax_rate' => $quote['tax_rate'],
            'price_configured' => $quote['configured'],
            'trial_starts_at' => $subscription->trial_starts_at?->toDateString(),
            'trial_ends_at' => $subscription->trial_ends_at?->toDateString(),
            'period_start' => $subscription->current_period_start?->toDateString(),
            'period_end' => $subscription->current_period_end?->toDateString(),
            'renews_at' => $subscription->renews_at?->toDateString(),
            'ends_at' => $subscription->ends_at?->toDateString(),
            'days_remaining' => $this->daysRemaining($subscription, $now),
            'phase' => $subscription->activated_at ? 'subscription' : 'trial',
            'outstanding_amount' => $outstanding,
            'outstanding_label' => $outstanding !== null ? Money::formatCurrency($outstanding, $quote['currency']) : null,
            'billing_cycle' => SchoolSubscription::BILLING_CYCLE,
        ];
    }

    // ------------------------------------------------------------------ payments

    /**
     * Record an AUTHORISED, VERIFIED payment of the full annual amount and activate / renew / reactivate.
     * Never call this for unverified receipts or browser "success" redirects.
     *
     * Pass `payment_id` to SETTLE a payment the school already initiated (cash reference, bank transfer, gateway
     * session): the amounts shown/charged at initiation (the stored snapshot) are what must be matched. Without it
     * a payment recorded directly by an authorised officer is created and verified in one step.
     *
     * @param  array{method?: string, payment_id?: int|null, late?: bool, amount?: int|null, transaction_reference?: string|null, paid_at?: mixed, notes?: string|null, receipt_number?: string|null, meta?: array|null, provider_confirmed_at?: mixed}  $data
     */
    public function recordVerifiedPayment(School $school, array $data, ?User $verifier = null): BillingPayment
    {
        $method = (string) ($data['method'] ?? '');
        if (! in_array($method, self::METHODS, true)) {
            throw new InvalidArgumentException('Unsupported payment method.');
        }

        $payment = $this->tenant->runForSchool($school, function () use ($school, $data, $method, $verifier) {
            return DB::transaction(function () use ($school, $data, $method, $verifier) {
                $subscription = SchoolSubscription::query()->where('school_id', $school->id)->lockForUpdate()->first();
                if (! $subscription) {
                    throw new InvalidArgumentException('This school has no subscription record.');
                }

                $existing = null;
                if (! empty($data['payment_id'])) {
                    $existing = BillingPayment::query()->where('school_id', $school->id)->lockForUpdate()->find($data['payment_id']);
                    if (! $existing || $existing->method !== $method) {
                        throw new InvalidArgumentException('That payment does not exist for this school and method.');
                    }
                    $settleable = array_merge(BillingPayment::OPEN, ! empty($data['late'])
                        ? [BillingPayment::CANCELLED, BillingPayment::EXPIRED, BillingPayment::FAILED]
                        : []);
                    if (! in_array($existing->status, $settleable, true)) {
                        throw new InvalidArgumentException('This payment can no longer be verified (status: '.$existing->status.').');
                    }
                }

                if ($existing) {
                    // Price snapshot taken when the school initiated the payment.
                    $quote = [
                        'configured' => true,
                        'amount' => (int) $existing->amount,
                        'tax_amount' => (int) $existing->tax_amount,
                        'total' => (int) $existing->total_amount,
                        'currency' => $existing->currency,
                        'tax_rate' => $subscription->tax_rate,
                    ];
                } else {
                    $quote = $this->quote($subscription);
                    if (! $quote['configured']) {
                        throw new InvalidArgumentException('No annual price is configured for this school type.');
                    }
                }

                $amount = $data['amount'] ?? $quote['total'];
                if ((int) $amount !== (int) $quote['total']) {
                    throw new InvalidArgumentException(
                        'A subscription is only activated by the full annual amount ('
                        .Money::formatCurrency($quote['total'], $quote['currency'])
                        .'). Partial or excess payments do not activate it.'
                    );
                }

                $reference = $data['transaction_reference'] ?? null;
                if ($reference && BillingPayment::withoutGlobalScopes()
                    ->where('method', $method)->where('transaction_reference', $reference)
                    ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))->exists()) {
                    throw new InvalidArgumentException('That transaction reference has already been recorded.');
                }

                $now = now();
                $wasActive = $this->effectiveStatus($subscription, $now) === SchoolSubscription::ACTIVE
                    && $subscription->activated_at !== null;
                $wasLapsed = in_array($this->effectiveStatus($subscription, $now), [SchoolSubscription::EXPIRED, SchoolSubscription::SUSPENDED, SchoolSubscription::CANCELLED], true);

                // Renewals extend from the current end so paid time is never lost; otherwise start now.
                $start = $wasActive && $subscription->current_period_end && $subscription->current_period_end->greaterThan($now)
                    ? $subscription->current_period_end->copy()
                    : $now->copy();
                $end = $start->copy()->addYearNoOverflow();

                $fields = [
                    'status' => BillingPayment::VERIFIED,
                    'transaction_reference' => $reference ?? $existing?->transaction_reference,
                    'paid_at' => isset($data['paid_at']) ? Carbon::parse($data['paid_at']) : $now,
                    'period_start' => $start,
                    'period_end' => $end,
                    'verified_by' => $verifier?->id,
                    'verified_at' => $now,
                    'provider_confirmed_at' => isset($data['provider_confirmed_at']) ? Carbon::parse($data['provider_confirmed_at']) : null,
                    // a verified payment carries no failure / rejection residue
                    'failed_at' => null, 'failure_reason' => null, 'cancelled_at' => null,
                ];

                if ($existing) {
                    $payment = $existing;
                    $payment->forceFill($fields + [
                        'recorded_by' => $existing->recorded_by ?? $verifier?->id,
                        'notes' => $data['notes'] ?? $existing->notes,
                        'meta' => array_merge((array) $existing->meta, (array) ($data['meta'] ?? [])),
                    ])->save();
                } else {
                    $payment = BillingPayment::query()->create($fields + [
                        'school_id' => $school->id,
                        'school_subscription_id' => $subscription->id,
                        'reference' => 'SOS-'.$school->id.'-'.$now->format('Ymd').'-'.Str::upper(Str::random(6)),
                        'method' => $method,
                        'amount' => $quote['amount'],
                        'tax_amount' => $quote['tax_amount'],
                        'total_amount' => $quote['total'],
                        'currency' => $quote['currency'],
                        'school_type' => $subscription->school_type,
                        'recorded_by' => $verifier?->id,
                        'notes' => $data['notes'] ?? null,
                        'meta' => $data['meta'] ?? null,
                    ]);
                }

                $payment->forceFill([
                    'receipt_number' => $data['receipt_number'] ?? $payment->receipt_number ?? sprintf('RCT-%s-%06d', $now->format('Y'), $payment->id),
                ])->save();

                $subscription->forceFill([
                    'status' => SchoolSubscription::ACTIVE,
                    'payment_status' => 'paid',
                    'annual_amount' => $quote['amount'],
                    'currency' => $quote['currency'],
                    'tax_rate' => $quote['tax_rate'],
                    'starts_at' => $subscription->activated_at ? $subscription->starts_at : $start,
                    'ends_at' => $end,
                    'current_period_start' => $start,
                    'current_period_end' => $end,
                    'renews_at' => $end,
                    'activated_at' => $subscription->activated_at ?? $now,
                    'reactivated_at' => $wasLapsed ? $now : $subscription->reactivated_at,
                    'expired_at' => null,
                    'suspended_at' => null,
                ])->save();

                $this->event($subscription, $wasLapsed ? 'reactivated' : ($wasActive ? 'renewed' : 'activated'), $verifier, [
                    'payment_id' => $payment->id,
                    'reference' => $payment->reference,
                    'method' => $method,
                    'total' => $quote['total'],
                    'currency' => $quote['currency'],
                    'period_start' => $start->toDateString(),
                    'period_end' => $end->toDateString(),
                ] + (! empty($data['late']) ? ['late_confirmation' => true] : []), $payment->id);

                return $payment;
            });
        });

        // Confirmation is sent only after the payment is committed and verified.
        $this->tenant->runForSchool($school, function () use ($school) {
            $fresh = SchoolSubscription::query()->where('school_id', $school->id)->first();
            if ($fresh) {
                $this->notifier->send($fresh, 'activated', $fresh->current_period_end?->toDateString() ?? 'n/a');
            }
        });

        return $payment;
    }

    // ------------------------------------------------------------------ scheduler

    /**
     * Expire, suspend and remind. Idempotent: safe to run every hour.
     *
     * @return array{expired: int, suspended: int, reminders: int}
     */
    public function processDue(?Carbon $now = null, bool $dryRun = false): array
    {
        $now ??= now();
        $summary = ['expired' => 0, 'suspended' => 0, 'reminders' => 0];

        $this->tenant->runAsPlatform(function () use ($now, $dryRun, &$summary) {
            SchoolSubscription::query()
                ->whereIn('status', [SchoolSubscription::TRIAL, SchoolSubscription::ACTIVE, SchoolSubscription::EXPIRED])
                ->whereNotNull('ends_at')
                ->orderBy('id')
                ->each(function (SchoolSubscription $subscription) use ($now, $dryRun, &$summary) {
                    $kindPrefix = $subscription->activated_at ? 'paid' : 'trial';
                    $periodKey = $subscription->ends_at->toDateString();

                    // 1. expiry
                    if (in_array($subscription->status, [SchoolSubscription::TRIAL, SchoolSubscription::ACTIVE], true)
                        && $now->greaterThanOrEqualTo($subscription->ends_at)) {
                        $summary['expired']++;
                        if (! $dryRun) {
                            $subscription->forceFill([
                                'status' => SchoolSubscription::EXPIRED,
                                'payment_status' => 'unpaid',
                                'expired_at' => $now,
                            ])->save();
                            $this->event($subscription, 'expired', null, ['phase' => $kindPrefix, 'ended_on' => $periodKey]);
                            $this->notifier->send($subscription, "{$kindPrefix}_expired", $periodKey);
                        }
                    }

                    // 2. suspension (after the optional, explicitly configured grace period)
                    if ($subscription->status === SchoolSubscription::EXPIRED
                        && $now->greaterThanOrEqualTo($this->graceEnd($subscription))) {
                        $summary['suspended']++;
                        if (! $dryRun) {
                            $subscription->forceFill([
                                'status' => SchoolSubscription::SUSPENDED,
                                'suspended_at' => $now,
                            ])->save();
                            $this->event($subscription, 'suspended', null, ['reason' => 'unpaid_after_expiry']);
                            $this->notifier->send($subscription, 'suspended', $periodKey);
                        }
                    }

                    // 3. reminders (only the most urgent unsent offset - never a burst of catch-up emails)
                    if (in_array($subscription->status, [SchoolSubscription::TRIAL, SchoolSubscription::ACTIVE], true)) {
                        $daysLeft = $this->daysRemaining($subscription, $now);
                        $offsets = (array) config($kindPrefix === 'trial'
                            ? 'schoolos.billing.trial_reminder_days'
                            : 'schoolos.billing.paid_reminder_days', []);
                        $due = collect($offsets)->filter(fn ($d) => $daysLeft !== null && $daysLeft >= 0 && $daysLeft <= (int) $d)->min();

                        if ($due !== null) {
                            $summary['reminders'] += $dryRun ? 1 : ($this->notifier->send($subscription, "{$kindPrefix}_{$due}", $periodKey) ? 1 : 0);
                        }
                    }
                });
        });

        return $summary;
    }

    // ------------------------------------------------------------------ authorised classification change

    /** Changing school type changes the fee: only an authorised operator may do it (audited). */
    public function changeSchoolType(School $school, string $newType, User $actor): SchoolSubscription
    {
        if (! SchoolTypes::isValid($newType)) {
            throw new InvalidArgumentException('Unknown school type.');
        }

        return $this->tenant->runForSchool($school, function () use ($school, $newType, $actor) {
            $subscription = SchoolSubscription::query()->where('school_id', $school->id)->firstOrFail();
            $old = $subscription->school_type;

            $school->forceFill(['category' => $newType])->save();
            $subscription->forceFill(['school_type' => $newType])->save();
            $this->event($subscription, 'school_type_changed', $actor, ['from' => $old, 'to' => $newType]);

            return $subscription;
        });
    }

    public function event(SchoolSubscription $subscription, string $type, ?User $actor = null, array $meta = [], ?int $paymentId = null): void
    {
        BillingEvent::query()->create([
            'school_id' => $subscription->school_id,
            'school_subscription_id' => $subscription->id,
            'billing_payment_id' => $paymentId ?? ($meta['payment_id'] ?? null),
            'type' => $type,
            'actor_id' => $actor?->id,
            'meta' => $meta ?: null,
        ]);
    }
}