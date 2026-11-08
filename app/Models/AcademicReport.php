<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicReport extends Model
{
    use BelongsToSchool;

    public const TYPES = ['termly', 'annual', 'final'];

    public const STATUSES = ['draft', 'submitted', 'in_review', 'approved', 'published', 'superseded'];

    protected $fillable = [
        'school_id', 'type', 'status', 'reference', 'verification_token', 'version', 'supersedes_id',
        'student_id', 'school_class_id', 'academic_year_id', 'term_id', 'template_id', 'payload',
        'overall_average', 'overall_grade', 'incomplete', 'class_teacher_comment', 'headteacher_comment',
        'behaviour', 'participation', 'promotion_status', 'promotion_decision', 'allow_incomplete',
        'revision_reason', 'prepared_by', 'submitted_by', 'submitted_at', 'reviewed_by', 'reviewed_at',
        'approved_by', 'approved_at', 'published_by', 'published_at', 'issue_date',
    ];

    protected $hidden = ['verification_token'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'overall_average' => 'decimal:2',
            'incomplete' => 'boolean',
            'allow_incomplete' => 'boolean',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
            'issue_date' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(AcademicReportTemplate::class, 'template_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AcademicReportEvent::class);
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isLocked(): bool
    {
        return in_array($this->status, ['published', 'superseded'], true);
    }
}
