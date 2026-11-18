<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolClass extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'level_id', 'name', 'stream', 'class_teacher_id', 'capacity',
        'teaching_arrangement', 'learning_centres',
    ];

    protected function casts(): array
    {
        return [
            'learning_centres' => 'array',
        ];
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function classTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'class_teacher_id');
    }

    public function educators(): HasMany
    {
        return $this->hasMany(ClassEducator::class, 'school_class_id');
    }

    public function classSubjects(): HasMany
    {
        return $this->hasMany(ClassSubject::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}
