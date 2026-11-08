<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoardingHostel extends Model
{
    use BelongsToSchool;

    protected $guarded = ['id'];

    public function rooms(): HasMany
    {
        return $this->hasMany(BoardingRoom::class);
    }

    public function houseMaster(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'house_master_staff_id');
    }
}
