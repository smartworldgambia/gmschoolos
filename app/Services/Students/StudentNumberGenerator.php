<?php

namespace App\Services\Students;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Permanent, unique, never-reused student numbers: PREFIX-YEAR-00001.
 * The counter is per school and advances inside a transaction with a row lock; numbers already present
 * (for example imported or hand-entered ones) are skipped, so a generated number can never collide.
 * The number is written once and no code path updates `student_number` afterwards.
 */
class StudentNumberGenerator
{
    public function next(int $schoolId): string
    {
        $prefix = strtoupper((string) config('schoolos.students.number_prefix', 'STU'));
        $year = now()->format('Y');

        return DB::transaction(function () use ($schoolId, $prefix, $year) {
            DB::table('school_sequences')->insertOrIgnore([
                'school_id' => $schoolId, 'name' => 'student_number', 'next_value' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            do {
                $row = DB::table('school_sequences')
                    ->where('school_id', $schoolId)->where('name', 'student_number')
                    ->lockForUpdate()->first();
                $value = (int) $row->next_value;

                DB::table('school_sequences')->where('id', $row->id)
                    ->update(['next_value' => $value + 1, 'updated_at' => now()]);

                $number = sprintf('%s-%s-%05d', $prefix, $year, $value);
                $taken = Student::withoutGlobalScopes()
                    ->where('school_id', $schoolId)->where('student_number', $number)->exists();
            } while ($taken);

            return $number;
        });
    }
}
