<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradingBand extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'grading_scale_id', 'letter', 'min_score', 'remark'];

    protected function casts(): array
    {
        return [
            'min_score' => 'decimal:2',
        ];
    }
}
