<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizAttempt extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'quiz_id', 'student_id', 'status', 'score', 'max_score', 'started_at', 'submitted_at',
        'attempt_no', 'due_at', 'question_order', 'option_orders', 'grading_status', 'timed_out', 'graded_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime', 'submitted_at' => 'datetime', 'due_at' => 'datetime', 'graded_at' => 'datetime',
            'question_order' => 'array', 'option_orders' => 'array', 'timed_out' => 'boolean',
            'score' => 'decimal:2', 'max_score' => 'decimal:2',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAttemptAnswer::class);
    }
}
