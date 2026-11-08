<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    use BelongsToSchool;

    public const RELEASE_MODES = ['immediate', 'after_close', 'scheduled', 'manual'];

    public const GRADE_METHODS = ['highest', 'latest', 'average'];

    protected $fillable = [
        'school_id', 'course_id', 'title', 'description', 'opens_at', 'closes_at', 'max_score', 'status',
        'time_limit_minutes', 'max_attempts', 'shuffle_questions', 'shuffle_options', 'release_mode', 'release_at',
        'results_released_at', 'show_correct_answers', 'grade_method', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime', 'closes_at' => 'datetime', 'release_at' => 'datetime', 'results_released_at' => 'datetime',
            'shuffle_questions' => 'boolean', 'shuffle_options' => 'boolean', 'show_correct_answers' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** Authored questions only (random bank draws are per-attempt snapshots, see QuizQuestion::quiz_attempt_id). */
    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->whereNull('quiz_attempt_id')->orderBy('sort_order')->orderBy('id');
    }

    public function pulls(): HasMany
    {
        return $this->hasMany(QuizBankPull::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /** Learners may begin an attempt now (published and inside the window). */
    public function isOpen(): bool
    {
        return $this->isPublished()
            && ($this->opens_at === null || $this->opens_at->lte(now()))
            && ($this->closes_at === null || $this->closes_at->gte(now()));
    }

    /** Has the teacher's release rule been satisfied (marks and, if allowed, answers may be shown)? */
    public function resultsReleased(): bool
    {
        return match ($this->release_mode) {
            'immediate' => true,
            'after_close' => $this->closes_at !== null && $this->closes_at->lte(now()),
            'scheduled' => $this->release_at !== null && $this->release_at->lte(now()),
            default => $this->results_released_at !== null && $this->results_released_at->lte(now()),
        };
    }
}
