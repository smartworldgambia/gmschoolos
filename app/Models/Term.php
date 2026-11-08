<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Term extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'academic_year_id', 'name', 'sequence', 'starts_on', 'ends_on', 'is_current'];

    /** Terms are numbered 1..3 by start date within their academic year (used by transcripts). */
    protected static function booted(): void
    {
        static::saved(fn (Term $t) => static::resequence((int) $t->academic_year_id));
        static::deleted(fn (Term $t) => static::resequence((int) $t->academic_year_id));
    }

    public static function resequence(int $yearId): void
    {
        if ($yearId <= 0) {
            return;
        }
        $n = 0;
        foreach (\Illuminate\Support\Facades\DB::table('terms')->where('academic_year_id', $yearId)->orderBy('starts_on')->orderBy('id')->get(['id', 'sequence']) as $row) {
            $n++;
            $want = $n <= 3 ? $n : null;
            if ($row->sequence !== $want) {
                \Illuminate\Support\Facades\DB::table('terms')->where('id', $row->id)->update(['sequence' => $want]);
            }
        }
    }

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_current' => 'boolean',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
