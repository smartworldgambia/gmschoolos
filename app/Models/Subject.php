<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'name', 'code', 'department_id', 'is_specialist'];

    protected function casts(): array
    {
        return ['is_specialist' => 'boolean'];
    }

    /** IRK, CRK and PE (and close name variants) travel between classrooms; the class teacher does not cover them. */
    public static function guessSpecialist(string $name, string $code = ''): bool
    {
        $blob = strtolower(trim($name.' '.$code));
        if ($blob === '') {
            return false;
        }

        return (bool) preg_match('/\b(irk|crk|pe|p\.?e\.?)\b|physical\s*education|islamic(\s*religious)?|christian(\s*religious)?|religious\s*knowledge/i', $blob);
    }

    public function classSubjects(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ClassSubject::class);
    }

    public function department(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function courses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function course(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Course::class);
    }
}
