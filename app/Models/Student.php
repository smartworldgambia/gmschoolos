<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'user_id', 'school_class_id', 'student_number', 'first_name', 'last_name',
        'gender', 'date_of_birth', 'place_of_birth', 'nationality', 'religion', 'photo_path',
        'status', 'admitted_on',
        'previous_school', 'address', 'emergency_contact_name', 'emergency_contact_phone',
        'emergency_contact_relationship', 'medical_notes', 'welfare_notes', 'withdrawn_on', 'withdrawal_reason',
    ];

    /** Never serialised by accident (JSON/Inertia). Exposed only through explicit, permission-checked code. */
    protected $hidden = ['medical_notes', 'welfare_notes'];

    public function documents(): HasMany
    {
        return $this->hasMany(StudentDocument::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StudentMovement::class)->orderByDesc('effective_on')->orderByDesc('id');
    }

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'admitted_on' => 'date',
            'withdrawn_on' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'student_guardians')
            ->withPivot(['is_primary', 'school_id'])
            ->withTimestamps();
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function academicReports(): HasMany
    {
        return $this->hasMany(AcademicReport::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
