<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Platform-level record (NOT tenant scoped): a registration that looked like an institution we already know.
 * Reviewing it is how repeated free-trial claims are controlled.
 */
class SchoolRegistrationReview extends Model
{
    public const PENDING = 'pending';

    public const CLEARED = 'cleared';

    public const CONFIRMED_DUPLICATE = 'confirmed_duplicate';

    protected $fillable = ['school_id', 'reasons', 'matched_school_ids', 'status', 'reviewed_by', 'reviewed_at', 'notes'];

    protected function casts(): array
    {
        return [
            'reasons' => 'array',
            'matched_school_ids' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}