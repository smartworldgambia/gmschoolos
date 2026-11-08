<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Level extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'name', 'code', 'grade_number', 'sort_order'];

    protected static function booted(): void
    {
        static::saving(function (Level $l) {
            if ($l->grade_number === null) {
                $l->grade_number = static::guessGradeNumber((string) $l->name, (string) $l->code);
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
