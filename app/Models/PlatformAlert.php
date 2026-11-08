<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformAlert extends Model
{
    protected $fillable = [
        'severity', 'source', 'title', 'message', 'school_id', 'context',
        'acknowledged_at', 'acknowledged_by', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function scopeOpen($query)
    {
        return $query->whereNull('resolved_at');
    }
}
