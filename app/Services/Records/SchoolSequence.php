<?php

namespace App\Services\Records;

use Illuminate\Support\Facades\DB;

/** Per-school atomic counters (same `school_sequences` table the student numbers use). Values are never reused. */
class SchoolSequence
{
    public function next(int $schoolId, string $name): int
    {
        return DB::transaction(function () use ($schoolId, $name) {
            DB::table('school_sequences')->insertOrIgnore([
                'school_id' => $schoolId, 'name' => $name, 'next_value' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $row = DB::table('school_sequences')->where('school_id', $schoolId)->where('name', $name)->lockForUpdate()->first();
            $value = (int) $row->next_value;
            DB::table('school_sequences')->where('id', $row->id)->update(['next_value' => $value + 1, 'updated_at' => now()]);

            return $value;
        });
    }
}
