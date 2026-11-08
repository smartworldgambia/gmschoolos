<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizQuestion extends Model
{
    use BelongsToSchool;

    public const TYPES = ['multiple_choice', 'true_false', 'short_answer', 'essay'];

    protected $fillable = [
        'school_id', 'quiz_id', 'prompt', 'type', 'options', 'correct_answer', 'points', 'sort_order',
        'accepted_answers', 'case_sensitive', 'explanation', 'quiz_attempt_id', 'bank_question_id',
    ];

    protected function casts(): array
    {
        return ['options' => 'array', 'accepted_answers' => 'array', 'case_sensitive' => 'boolean'];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }
}
