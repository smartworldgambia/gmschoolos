<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExternalCandidateDocument extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'candidate_id', 'type', 'original_name', 'path', 'mime', 'size', 'uploaded_by'];

    protected function casts(): array
    {
        return [
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(ExternalCandidate::class, 'candidate_id');
    }
}
