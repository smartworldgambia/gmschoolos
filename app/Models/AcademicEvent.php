<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicEvent extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'academic_year_id', 'term_id', 'type', 'title', 'starts_on', 'ends_on', 'notes'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public const TYPES = ['holiday', 'exam_period', 'admission_period', 'results_publication', 'event'];
}
