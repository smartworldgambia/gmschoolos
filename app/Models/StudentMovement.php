<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentMovement extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'student_id', 'academic_year_id', 'event', 'from_class_id', 'to_class_id', 'reason', 'effective_on', 'performed_by'];

    protected function casts(): array
    {
        return [
            'effective_on' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function fromClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'from_class_id');
    }

    public function toClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'to_class_id');
    }
}
