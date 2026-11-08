<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

/** Reminder ledger: one row per (subscription, kind, expiry date) - prevents duplicates, records delivery. */
class BillingNotificationLog extends Model
{
    use BelongsToSchool;

    protected $table = 'billing_notifications';

    protected $fillable = [
        'school_id', 'school_subscription_id', 'kind', 'period_key', 'status', 'recipients', 'error', 'sent_at',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }
}