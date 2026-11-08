<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradingScale extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'name', 'is_default'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function bands(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(GradingBand::class)->orderByDesc('min_score');
    }
}
