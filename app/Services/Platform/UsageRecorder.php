<?php

namespace App\Services\Platform;

use App\Models\Course;
use App\Models\ExportBatch;
use App\Models\ImportBatch;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\UsageSnapshot;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class UsageRecorder
{
    public function bumpRequest(?int $schoolId): void
    {
        if (! $schoolId) {
            return;
        }

        $key = 'usage:requests:'.$schoolId.':'.now()->toDateString();
        Cache::increment($key);
        Cache::put($key, (int) Cache::get($key, 0), now()->endOfDay());
    }

    public function snapshotSchool(School $school, ?string $date = null): UsageSnapshot
    {
        $date ??= now()->toDateString();
        $requestCount = (int) Cache::get('usage:requests:'.$school->id.':'.$date, 0);

        return UsageSnapshot::query()->updateOrCreate(
            ['school_id' => $school->id, 'captured_on' => $date],
            [
                'students_count' => Student::withoutGlobalScopes()->where('school_id', $school->id)->count(),
                'staff_count' => Staff::withoutGlobalScopes()->where('school_id', $school->id)->count(),
                'users_count' => User::query()->where('school_id', $school->id)->count(),
                'courses_count' => Course::withoutGlobalScopes()->where('school_id', $school->id)->count(),
                'storage_mb' => 0,
                'request_count' => $requestCount,
                'export_count' => ExportBatch::withoutGlobalScopes()
                    ->where('school_id', $school->id)
                    ->whereDate('created_at', $date)
                    ->count(),
                'import_count' => ImportBatch::withoutGlobalScopes()
                    ->where('school_id', $school->id)
                    ->whereDate('created_at', $date)
                    ->count(),
            ]
        );
    }

    public function snapshotAll(?string $date = null): int
    {
        $count = 0;
        School::query()->orderBy('id')->chunkById(100, function ($schools) use (&$count, $date) {
            foreach ($schools as $school) {
                $this->snapshotSchool($school, $date);
                $count++;
            }
        });

        return $count;
    }
}
