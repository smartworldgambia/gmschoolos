<?php

namespace App\Services\School;

use App\Models\School;
use App\Models\SchoolSubscription;
use App\Services\Billing\SubscriptionLifecycle;
use App\Support\TenantContext;
use Illuminate\Support\Carbon;

/**
 * Decides whether a school may use protected tenant services right now.
 *
 * Blocking NEVER deletes data - it only gates access. Platform staff are not subject to this gate.
 * Reasons: suspended | trial_expired | subscription_expired
 *
 * The decision uses the CALENDAR (ends_at + the explicitly configured grace period, default 0 days), so
 * access stops on time even if the hourly scheduler has not yet recorded the expiry.
 *
 * A school with no subscription record at all is allowed (legacy/demo tenants); every school provisioned
 * by registration or the platform console gets a trial subscription automatically.
 */
class SchoolAccessGate
{
    public const SUSPENDED = 'suspended';

    public const TRIAL_EXPIRED = 'trial_expired';

    public const SUBSCRIPTION_EXPIRED = 'subscription_expired';

    public function __construct(
        protected TenantContext $tenant,
        protected SubscriptionLifecycle $lifecycle,
    ) {}

    /** @return string|null  null when access is allowed, otherwise the blocking reason */
    public function blockReason(School $school, ?Carbon $at = null): ?string
    {
        $at ??= now();

        if ($school->status !== 'active' || $school->suspended_at !== null) {
            return self::SUSPENDED;
        }

        // SchoolSubscription is tenant-scoped; read it explicitly for this school.
        $subscription = $this->tenant->runForSchool($school, fn () => SchoolSubscription::query()
            ->where('school_id', $school->id)
            ->first());

        if (! $subscription) {
            return null;
        }

        $status = $this->lifecycle->effectiveStatus($subscription, $at);

        // "expired" inside an explicitly configured grace period still has access.
        if ($status === SchoolSubscription::EXPIRED) {
            return $at->lessThan($this->lifecycle->graceEnd($subscription)) ? null : $this->expiredReason($subscription);
        }

        return match ($status) {
            SchoolSubscription::SUSPENDED, SchoolSubscription::CANCELLED => $this->expiredReason($subscription),
            default => null, // trial / active
        };
    }

    public function allows(School $school): bool
    {
        return $this->blockReason($school) === null;
    }

    protected function expiredReason(SchoolSubscription $subscription): string
    {
        return $subscription->activated_at ? self::SUBSCRIPTION_EXPIRED : self::TRIAL_EXPIRED;
    }
}