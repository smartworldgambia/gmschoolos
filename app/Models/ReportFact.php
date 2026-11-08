<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportFact extends Model
{
    protected $fillable = [
        'school_id', 'fact_key', 'grain', 'captured_on', 'dimensions',
        'value_numeric', 'value_text', 'refreshed_at',
    ];

    protected function casts(): array
    {
        return [
            'captured_on' => 'date',
            'dimensions' => 'array',
            'refreshed_at' => 'datetime',
            'value_numeric' => 'float',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
