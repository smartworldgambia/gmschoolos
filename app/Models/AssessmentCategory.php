<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentCategory extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'name', 'code', 'type', 'weight', 'max_score', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'max_score' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
