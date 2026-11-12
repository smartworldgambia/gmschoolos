<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use BelongsToSchool;

    public const STATUSES = ['draft', 'published', 'archived'];

    protected $fillable = [
        'school_id', 'subject_id', 'school_class_id', 'category_id', 'teacher_id', 'created_by', 'title', 'code', 'description', 'objectives', 'status',
    ];

    protected function casts(): array
    {
        return ['objectives' => 'array'];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CourseCategory::class, 'category_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function coTeachers(): HasMany
    {
        return $this->hasMany(CourseTeacher::class);
    }

    public function audiences(): HasMany
    {
        return $this->hasMany(CourseAudience::class);
    }

    public function enrolments(): HasMany
    {
        return $this->hasMany(CourseEnrolment::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(CourseSection::class)->orderBy('sort_order')->orderBy('id');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('sort_order')->orderBy('id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }
}
