<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Level extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'name', 'code', 'grade_number', 'sort_order', 'teaching_model',
        'age_min_months', 'age_max_months', 'description', 'is_active', 'teaching_arrangement',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'age_min_months' => 'integer',
            'age_max_months' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Level $l) {
            if ($l->grade_number === null) {
                $l->grade_number = static::guessGradeNumber((string) $l->name, (string) $l->code);
            }

            $arrangement = (string) ($l->teaching_arrangement ?? '');
            if ($arrangement !== '' && in_array($arrangement, ['homeroom', 'activity', 'learning_centre', 'specialist'], true)) {
                $l->teaching_model = $arrangement === 'specialist' ? 'specialist' : 'homeroom';
            }

            if (! in_array((string) $l->teaching_model, ['homeroom', 'specialist'], true)) {
                $l->teaching_model = \App\Services\Academics\HomeroomService::defaultModel(
                    $l->grade_number, (string) $l->name, (string) $l->code
                );
            }

            if ($l->teaching_arrangement === null || $l->teaching_arrangement === '') {
                $l->teaching_arrangement = $l->teaching_model === 'specialist' ? 'specialist' : 'homeroom';
            }
        });
    }

    /** Best-effort: "Grade 7" / "G7" / "Class 10" -> 7 / 7 / 10. Administrators can correct it in Records settings. */
    public static function guessGradeNumber(string $name, string $code = ''): ?int
    {
        if (preg_match('/\b(?:grade|gr|g|class|year)\s*0?(\d{1,2})\b/i', trim($name.' '.$code), $m)) {
            $n = (int) $m[1];
            return $n >= 1 && $n <= 13 ? $n : null;
        }

        return null;
    }

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }
}
