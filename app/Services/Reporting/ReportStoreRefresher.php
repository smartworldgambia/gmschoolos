<?php

namespace App\Services\Reporting;

use App\Models\AttendanceRecord;
use App\Models\Payment;
use App\Models\ReportFact;
use App\Models\School;
use App\Models\Student;
class ReportStoreRefresher
{
    public function refreshSchool(School $school, ?string $date = null): int
    {
        $date ??= now()->toDateString();
        $schoolId = $school->id;
        $written = 0;

        $activeStudents = Student::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->count();

        $written += $this->upsert($schoolId, 'students.active', $date, $activeStudents);

        $collected = (int) Payment::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->whereDate('paid_at', '<=', $date)
            ->sum('amount');

        $written += $this->upsert($schoolId, 'fees.collected_to_date', $date, $collected);

        $attendanceTotal = AttendanceRecord::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->whereDate('attended_on', $date)
            ->count();
        $present = AttendanceRecord::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->whereDate('attended_on', $date)
            ->where('status', 'present')
            ->count();
        $rate = $attendanceTotal > 0 ? round(($present / $attendanceTotal) * 100, 2) : null;
        $written += $this->upsert($schoolId, 'attendance.rate', $date, $rate, [
            'present' => $present,
            'total' => $attendanceTotal,
        ]);

        return $written;
    }

    public function refreshAll(?string $date = null): int
    {
        $count = 0;
        School::query()->orderBy('id')->chunkById(50, function ($schools) use (&$count, $date) {
            foreach ($schools as $school) {
                $count += $this->refreshSchool($school, $date);
            }
        });

        return $count;
    }

    protected function upsert(
        int $schoolId,
        string $factKey,
        string $date,
        int|float|null $value,
        ?array $dimensions = null,
    ): int {
        ReportFact::query()->updateOrCreate(
            [
                'school_id' => $schoolId,
                'fact_key' => $factKey,
                'grain' => 'school_day',
                'captured_on' => $date,
            ],
            [
                'dimensions' => $dimensions,
                'value_numeric' => $value,
                'refreshed_at' => now(),
            ]
        );

        return 1;
    }
}
