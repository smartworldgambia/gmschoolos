<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoardingRoom extends Model
{
    use BelongsToSchool;

    protected $guarded = ['id'];

    public function hostel(): BelongsTo
    {
        return $this->belongsTo(BoardingHostel::class, 'boarding_hostel_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(BoardingAllocation::class);
    }

    public function beds(): HasMany
    {
        return $this->hasMany(BoardingBed::class);
    }
}
