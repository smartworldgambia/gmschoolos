<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExternalCandidate extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'session_id', 'student_id', 'internal_reference', 'official_index_number', 'index_recorded_at', 'index_recorded_by', 'status', 'registered_on', 'details', 'notes', 'registered_by'];

    protected function casts(): array
    {
        return [
            'registered_on' => 'date',
            'index_recorded_at' => 'datetime',
            'details' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ExternalExamSession::class, 'session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function subjectLinks(): HasMany
    {
        return $this->hasMany(ExternalCandidateSubject::class, 'candidate_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(ExternalResult::class, 'candidate_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ExternalCandidateDocument::class, 'candidate_id');
    }

    public function collection(): HasOne
    {
        return $this->hasOne(ResultCollection::class, 'candidate_id');
    }

    public function certificate(): HasOne
    {
        return $this->hasOne(CertificateRecord::class, 'candidate_id');
    }
}
