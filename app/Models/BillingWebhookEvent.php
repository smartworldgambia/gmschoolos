<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Idempotency ledger for gateway webhooks: one row per (provider, event id). Platform-level, not tenant-scoped
 * (a webhook arrives before any tenant is known). The raw body is never stored - only its hash.
 */
class BillingWebhookEvent extends Model
{
    public const RECEIVED = 'received';

    public const PROCESSED = 'processed';

    public const IGNORED = 'ignored';

    public const FAILED = 'failed';

    protected $fillable = [
        'provider', 'event_id', 'type', 'billing_payment_id', 'school_id', 'status', 'attempts',
        'payload_hash', 'error', 'received_at', 'processed_at',
    ];

    protected function casts(): array
    {
        return ['received_at' => 'datetime', 'processed_at' => 'datetime'];
    }
}
