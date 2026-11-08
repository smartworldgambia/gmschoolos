<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolQuota extends Model
{
    protected $fillable = [
        'school_id', 'max_students', 'max_staff', 'max_storage_mb',
        'max_exports_per_day', 'max_api_requests_per_hour', 'is_premium_isolated', 'overrides',
    ];

    protected function casts(): array
    {
        return [
            'is_premium_isolated' => 'boolean',
            'overrides' => 'array',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
