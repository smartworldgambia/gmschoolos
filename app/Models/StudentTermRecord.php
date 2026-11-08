<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StudentTermRecord extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'student_id', 'grade_number', 'term_sequence', 'academic_year_label', 'entries', 'source', 'entered_by'];

    protected function casts(): array
    {
        return [
            'entries' => 'array',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
