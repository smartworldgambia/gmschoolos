<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransportAttendance extends Model
{
    use BelongsToSchool;

    protected $table = 'transport_attendance';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'travel_date' => 'date',
        ];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(TransportRoute::class, 'transport_route_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
