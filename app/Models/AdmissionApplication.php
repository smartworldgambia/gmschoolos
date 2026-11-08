<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionApplication extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'application_number', 'status', 'first_name', 'last_name', 'gender',
        'date_of_birth', 'place_of_birth', 'nationality', 'religion', 'preferred_class_id',
        'guardian_name', 'guardian_relationship', 'guardian_phone', 'guardian_email', 'notes',
        'reviewed_by', 'reviewed_at', 'student_id',
        'source', 'waitlist_position', 'decision_reason', 'decided_at', 'previous_school', 'address',
        'emergency_contact_name', 'emergency_contact_phone', 'submitted_ip',
    ];

    public function documents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StudentDocument::class, 'admission_application_id');
    }

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'reviewed_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function preferredClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'preferred_class_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
