<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherUnavailability extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'teacher_id', 'day_of_week', 'starts_at', 'ends_at', 'reason'];

    protected $table = 'teacher_unavailabilities';

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
