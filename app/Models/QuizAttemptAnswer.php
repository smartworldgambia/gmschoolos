<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAttemptAnswer extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'quiz_attempt_id', 'quiz_question_id', 'answer', 'is_correct', 'points_awarded',
        'needs_grading', 'feedback', 'graded_by', 'graded_at',
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean', 'needs_grading' => 'boolean', 'points_awarded' => 'decimal:2', 'graded_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'quiz_question_id');
    }
}
