<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'student_id', 'school_class_id', 'attended_on', 'session', 'status', 'remarks', 'recorded_by',
        'timetable_slot_id', 'reason', 'arrived_at', 'left_at', 'guardian_notified_at',
    ];

    /** Status vocabulary (stable English codes; only the display labels are translated). */
    public const STATUSES = ['present', 'absent', 'late', 'excused', 'left_early'];

    public function schoolClass(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    protected function casts(): array
    {
        return [
            'attended_on' => 'date',
            'guardian_notified_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
