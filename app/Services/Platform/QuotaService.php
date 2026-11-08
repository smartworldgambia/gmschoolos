<?php

namespace App\Services\Platform;

use App\Models\PlatformAlert;
use App\Models\School;
use App\Models\SchoolQuota;
use App\Models\Student;
use RuntimeException;

class QuotaService
{
    public function forSchool(School $school): SchoolQuota
    {
        return SchoolQuota::query()->firstOrCreate(
            ['school_id' => $school->id],
            $this->defaultsFromPlan($school)
        );
    }

    public function defaultsFromPlan(School $school): array
    {
        // Subscriptions are annual-only with no tiers, so quotas are not derived from a plan: every school gets the same
        // platform defaults (unlimited students/staff unless an operator sets a limit) and operators may override them.
        return [
            'max_students' => config('schoolos.quota_defaults.max_students'),
            'max_staff' => config('schoolos.quota_defaults.max_staff'),
            'max_storage_mb' => 2048,
            'max_exports_per_day' => 20,
            'max_api_requests_per_hour' => 5000,
            'is_premium_isolated' => false,
        ];
    }

    public function assertCanAddStudent(School $school): void
    {
        $quota = $this->forSchool($school);
        if ($quota->max_students === null) {
            return;
        }

        $count = Student::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->where('status', 'active')
            ->count();

        if ($count >= $quota->max_students) {
            PlatformAlert::query()->create([
                'severity' => 'warning',
                'source' => 'quota',
                'title' => 'Student quota reached',
                'message' => "{$school->name} reached max_students={$quota->max_students}.",
                'school_id' => $school->id,
                'context' => ['metric' => 'max_students', 'current' => $count],
            ]);

            throw new RuntimeException('Student quota reached for this school. Ask the platform team to raise the quota.');
        }
    }

    public function usageSummary(School $school): array
    {
        $quota = $this->forSchool($school);
        $students = Student::withoutGlobalScopes()->where('school_id', $school->id)->where('status', 'active')->count();

        return [
            'students' => [
                'used' => $students,
                'max' => $quota->max_students,
                'pct' => $quota->max_students ? round(($students / max($quota->max_students, 1)) * 100, 1) : null,
            ],
            'is_premium_isolated' => $quota->is_premium_isolated,
            'max_exports_per_day' => $quota->max_exports_per_day,
            'max_api_requests_per_hour' => $quota->max_api_requests_per_hour,
        ];
    }
}
