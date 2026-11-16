<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlAccountMap extends Model
{
    use BelongsToSchool;

    protected $guarded = ['id'];

    public const KEYS = [
        'cash', 'bank', 'fees_receivable', 'fee_income', 'discounts',
        'payroll_expense', 'salaries_payable', 'ap', 'inventory',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'account_id');
    }
}
