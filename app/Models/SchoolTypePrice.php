<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One annual price per school type. Append-only history: a price change inserts a new row with an
 * effective date; earlier rows are kept, so already-paid periods never change retroactively.
 */
class SchoolTypePrice extends Model
{
    protected $fillable = [
        'school_type', 'amount', 'currency', 'tax_rate', 'effective_from', 'status', 'description', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'tax_rate' => 'decimal:2',
            'effective_from' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function taxAmount(): int
    {
        return $this->tax_rate !== null ? (int) round($this->amount * ((float) $this->tax_rate) / 100) : 0;
    }

    public function totalAmount(): int
    {
        return $this->amount + $this->taxAmount();
    }
}