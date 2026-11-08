<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Period extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'name', 'starts_at', 'ends_at', 'is_break', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_break' => 'boolean',
        ];
    }
}
