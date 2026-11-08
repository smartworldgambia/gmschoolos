<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoardingBed extends Model
{
    use BelongsToSchool;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'in_service' => 'boolean',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(BoardingRoom::class, 'boarding_room_id');
    }
}
