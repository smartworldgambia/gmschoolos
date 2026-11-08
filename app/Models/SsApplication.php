<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SsApplication extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'round_id', 'reference', 'status', 'source', 'first_name', 'last_name', 'gender', 'date_of_birth', 'nationality', 'previous_school', 'gabece_index_number', 'gabece_year', 'guardian_name', 'guardian_phone', 'guardian_email', 'address', 'programme_choice', 'notes', 'consent_at', 'submitted_ip', 'aggregate', 'eligible', 'assessment', 'rank_position', 'recommendation', 'computed_at', 'results_verified_at', 'results_verified_by', 'is_override', 'decision_reason', 'decided_by', 'decided_at'];

    protected $hidden = ['submitted_ip'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'consent_at' => 'datetime',
            'aggregate' => 'decimal:2',
            'eligible' => 'boolean',
            'assessment' => 'array',
            'computed_at' => 'datetime',
            'results_verified_at' => 'datetime',
            'is_override' => 'boolean',
            'decided_at' => 'datetime',
        ];
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(SsRound::class, 'round_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(SsApplicationResult::class, 'application_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SsApplicationDocument::class, 'application_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SsApplicationEvent::class, 'application_id');
    }
}
