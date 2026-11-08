<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicReportEvent extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'academic_report_id', 'event', 'user_id', 'comment', 'properties'];

    protected function casts(): array
    {
        return ['properties' => 'array'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(AcademicReport::class, 'academic_report_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
