<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExternalResult extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'candidate_id', 'subject_id', 'grade', 'status', 'source', 'import_batch_id', 'verified_by', 'verified_at', 'published_by', 'published_at'];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(ExternalCandidate::class, 'candidate_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(ExternalExamSubject::class, 'subject_id');
    }
}
