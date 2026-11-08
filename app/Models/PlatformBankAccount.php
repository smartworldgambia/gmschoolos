<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Bank account the Platform Administrator publishes to schools paying by bank transfer. */
class PlatformBankAccount extends Model
{
    protected $fillable = [
        'bank_name', 'account_name', 'account_number', 'branch', 'swift_code', 'currency', 'instructions',
        'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
