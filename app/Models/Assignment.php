<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Assignment extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'course_id', 'title', 'instructions', 'due_at', 'max_score', 'status', 'publish_at', 'late_policy',
        'closes_at', 'max_attempts', 'accepts_text', 'accepts_files', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime', 'publish_at' => 'datetime', 'closes_at' => 'datetime',
            'accepts_text' => 'boolean', 'accepts_files' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function rubric(): HasOne
    {
        return $this->hasOne(AssignmentRubric::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function isLive(): bool
    {
        return $this->status === 'published' && ($this->publish_at === null || $this->publish_at->lte(now()));
    }
}
