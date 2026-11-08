<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

/** Append-only audit trail of billing state changes. */
class BillingEvent extends Model
{
    use BelongsToSchool;

    public const UPDATED_AT = null;

    protected $fillable = ['school_id', 'school_subscription_id', 'billing_payment_id', 'type', 'actor_id', 'meta'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }
}
