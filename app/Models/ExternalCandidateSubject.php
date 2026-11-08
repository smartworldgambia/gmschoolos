<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExternalCandidateSubject extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'candidate_id', 'subject_id'];

    protected function casts(): array
    {
        return [
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(ExternalExamSubject::class, 'subject_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(ExternalCandidate::class, 'candidate_id');
    }
}
