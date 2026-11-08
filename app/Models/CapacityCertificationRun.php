<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CapacityCertificationRun extends Model
{
    protected $fillable = [
        'gate', 'profile', 'status', 'environment', 'vus', 'duration_seconds',
        'p95_ms', 'error_rate_pct', 'rps', 'thresholds', 'results', 'notes',
        'recorded_by', 'ran_at',
    ];

    protected function casts(): array
    {
        return [
            'thresholds' => 'array',
            'results' => 'array',
            'ran_at' => 'datetime',
            'p95_ms' => 'float',
            'error_rate_pct' => 'float',
            'rps' => 'float',
        ];
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
