<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class AcademicReportTemplate extends Model
{
    use BelongsToSchool;

    public const BANDS = ['nursery', 'primary', 'basic_secondary', 'senior_secondary', 'default'];

    protected $fillable = ['school_id', 'key', 'name', 'program_band', 'config', 'is_default'];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_default' => 'boolean',
        ];
    }
}
