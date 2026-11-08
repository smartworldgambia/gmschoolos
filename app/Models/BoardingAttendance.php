<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoardingAttendance extends Model
{
    use BelongsToSchool;

    protected $table = 'boarding_attendance';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'attended_on' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
