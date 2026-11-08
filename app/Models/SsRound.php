<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SsRound extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'name', 'slug', 'instructions', 'opens_on', 'closes_on', 'capacity', 'waitlist_capacity', 'status', 'rules', 'ranked_at'];

    protected function casts(): array
    {
        return [
            'opens_on' => 'date',
            'closes_on' => 'date',
            'rules' => 'array',
            'ranked_at' => 'datetime',
        ];
    }

    public function applications(): HasMany
    {
        return $this->hasMany(SsApplication::class, 'round_id');
    }
}
