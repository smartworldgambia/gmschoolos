<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Annual SaaS subscription payment made by a school (not a parent's school-fee payment).
 *
 * Status machine (only VERIFIED ever activates a subscription):
 *   pending ──(bank evidence)──> pending_verification ──> verified | rejected
 *   pending ──(gateway confirmed server-side)──> verified
 *   pending ──> cancelled | expired | failed        verified ──> refunded
 */
class BillingPayment extends Model
{
    use BelongsToSchool;

    public const PENDING = 'pending';

    public const PENDING_VERIFICATION = 'pending_verification';

    public const VERIFIED = 'verified';

    public const REJECTED = 'rejected';

    public const FAILED = 'failed';

    public const CANCELLED = 'cancelled';

    public const REFUNDED = 'refunded';

    public const EXPIRED = 'expired';

    public const STATUSES = [
        self::PENDING, self::PENDING_VERIFICATION, self::VERIFIED, self::REJECTED,
        self::FAILED, self::CANCELLED, self::REFUNDED, self::EXPIRED,
    ];

    /** A payment that is still waiting to be settled. */
    public const OPEN = [self::PENDING, self::PENDING_VERIFICATION];

    public const GATEWAYS = ['stripe', 'wave'];

    protected $fillable = [
        'school_id', 'school_subscription_id', 'reference', 'method', 'provider', 'provider_reference', 'checkout_url',
        'amount', 'tax_amount', 'total_amount', 'currency', 'charged_amount', 'charged_currency', 'fx_rate',
        'status', 'school_type', 'transaction_reference', 'receipt_number', 'paid_at',
        'period_start', 'period_end', 'recorded_by', 'initiated_by', 'verified_by', 'verified_at', 'notes', 'meta',
        'evidence_path', 'evidence_original_name', 'evidence_mime', 'evidence_size', 'evidence_uploaded_at',
        'rejection_reason', 'rejected_at', 'rejected_by', 'cancelled_at', 'failed_at', 'failure_reason',
        'refunded_at', 'expires_at', 'provider_confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'tax_amount' => 'integer',
            'total_amount' => 'integer',
            'charged_amount' => 'integer',
            'fx_rate' => 'float',
            'paid_at' => 'datetime',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'verified_at' => 'datetime',
            'evidence_uploaded_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'failed_at' => 'datetime',
            'refunded_at' => 'datetime',
            'expires_at' => 'datetime',
            'provider_confirmed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    public function isGateway(): bool
    {
        return in_array($this->method, self::GATEWAYS, true);
    }

    public function hasEvidence(): bool
    {
        return $this->evidence_path !== null && $this->evidence_path !== '';
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}
