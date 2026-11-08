<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErrorReport extends Model
{
    protected $fillable = [
        'fingerprint', 'level', 'exception_class', 'message', 'url',
        'user_id', 'school_id', 'occurrences', 'context', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
