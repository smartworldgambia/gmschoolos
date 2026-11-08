<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffAttendance extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'staff_id', 'attended_on', 'status', 'remarks', 'recorded_by'];

    protected function casts(): array
    {
        return [
            'attended_on' => 'date',
        ];
    }

    protected $table = 'staff_attendance';

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
