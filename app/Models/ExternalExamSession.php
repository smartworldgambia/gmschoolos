<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExternalExamSession extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'type', 'name', 'exam_year', 'registration_opens_on', 'registration_closes_on', 'status'];

    protected function casts(): array
    {
        return [
            'registration_opens_on' => 'date',
            'registration_closes_on' => 'date',
        ];
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(ExternalCandidate::class, 'session_id');
    }
}
