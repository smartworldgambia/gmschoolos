<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class FeeItem extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'name', 'amount', 'applicable_to', 'type', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
