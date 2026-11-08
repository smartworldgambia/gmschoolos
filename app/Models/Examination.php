<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Examination extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'term_id', 'academic_year_id', 'name', 'status',
        'starts_on', 'ends_on', 'published_at', 'published_by',
        'type', 'submitted_at', 'submitted_by', 'approved_at', 'approved_by', 'returned_reason',
    ];

    /** Result workflow: draft (marks open) -> submitted -> approved -> published. */
    public const STATUSES = ['draft', 'submitted', 'approved', 'published'];

    public const TYPES = ['continuous_assessment', 'mid_term', 'end_of_term', 'other'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'published_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
