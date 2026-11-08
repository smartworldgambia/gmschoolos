<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageSnapshot extends Model
{
    protected $fillable = [
        'school_id', 'captured_on', 'students_count', 'staff_count', 'users_count',
        'courses_count', 'storage_mb', 'request_count', 'export_count', 'import_count', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'captured_on' => 'date',
            'meta' => 'array',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
