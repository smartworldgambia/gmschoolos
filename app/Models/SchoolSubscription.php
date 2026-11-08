<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

/**
 * The single annual subscription of a school. Annual-only: the billing cycle is fixed and any attempt
 * to store another cycle is rejected. Lifecycle: trial -> active -> expired -> suspended (-> active on
 * verified payment). Data is never deleted when a subscription lapses.
 */
class SchoolSubscription extends Model
{
    use BelongsToSchool;

    public const TRIAL = 'trial';

    public const ACTIVE = 'active';

    public const EXPIRED = 'expired';

    public const SUSPENDED = 'suspended';

    public const CANCELLED = 'cancelled';

    public const STATUSES = [self::TRIAL, self::ACTIVE, self::EXPIRED, self::SUSPENDED, self::CANCELLED];

    public const BILLING_CYCLE = 'annual';

    protected $fillable = [
        'school_id', 'school_type', 'annual_amount', 'currency', 'tax_rate', 'status', 'payment_status',
        'starts_at', 'ends_at', 'trial_starts_at', 'trial_ends_at', 'current_period_start', 'current_period_end',
        'renews_at', 'activated_at', 'expired_at', 'suspended_at', 'reactivated_at', 'last_invoiced_at',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $subscription) {
            // Monthly billing is impossible: the cycle is not writable, always annual.
            $subscription->billing_cycle = self::BILLING_CYCLE;

            if (! in_array($subscription->status, self::STATUSES, true)) {
                throw new InvalidArgumentException("Unknown subscription status [{$subscription->status}].");
            }
        });
    }

    protected function casts(): array
    {
        return [
            'annual_amount' => 'integer',
            'tax_rate' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'trial_starts_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'renews_at' => 'datetime',
            'activated_at' => 'datetime',
            'expired_at' => 'datetime',
            'suspended_at' => 'datetime',
            'reactivated_at' => 'datetime',
            'last_invoiced_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BillingPayment::class, 'school_subscription_id');
    }
}