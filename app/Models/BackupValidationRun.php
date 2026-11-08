<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupValidationRun extends Model
{
    protected $fillable = [
        'status', 'trigger', 'ran_by', 'duration_ms', 'checks', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'checks' => 'array',
        ];
    }

    public function runner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ran_by');
    }
}
