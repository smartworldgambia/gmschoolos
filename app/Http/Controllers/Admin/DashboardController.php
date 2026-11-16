<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AttendanceRecord;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Services\Scale\CacheTuner;
use App\Services\Billing\SubscriptionLifecycle;
use App\Services\School\SchoolDomainService;
use App\Support\Money;
use App\Support\TenantContext;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use SharesSchoolShell;

    public function __invoke(CacheTuner $cache, SchoolDomainService $domains): Response
    {
        $tenant = app(TenantContext::class);
        $school = $tenant->school() ?? auth()->user()?->school;
        $schoolId = $school?->id ?? auth()->user()?->school_id;

        if ($school) {
            $domains->assignPlatformDomain($school);
        }

        $cached = $cache->adminDashboardStats((int) $schoolId, function () {
            $students = Student::query()->where('status', 'active')->count();
            $teachers = Staff::query()->where('status', 'active')->count();
            $classes = SchoolClass::query()->count();

            $billed = (int) Invoice::query()->sum('total_amount');
            $collected = (int) Payment::query()->sum('amount');
            $rate = $billed > 0 ? round(($collected / $billed) * 100, 1) : 0;

            $attendanceTotal = AttendanceRecord::query()->count();
            $present = AttendanceRecord::query()->where('status', 'present')->count();
            $attendanceRate = $attendanceTotal > 0 ? round(($present / $attendanceTotal) * 100, 1) : 0;

            return [
                'stats' => [
                    'students' => $students,
                    'teachers' => $teachers,
                    'classes' => $classes,
                    'fees_collected' => Money::format($collected),
                    'fees_collected_raw' => $collected,
                    'collection_rate' => $rate,
                    'attendance_rate' => $attendanceRate,
                    'outstanding' => Money::format(max(0, $billed - $collected)),
                ],
            ];
        });

        $recentActivity = ActivityLog::query()
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (ActivityLog $log) => [
                'description' => $log->description,
                'status' => $log->event,
                'created_at' => $log->created_at?->diffForHumans(),
            ]);

        $user = auth()->user();
        $canManageSchoolUrl = (bool) $user?->canAny(['domains.manage', 'website.manage', 'school.settings.manage']);

        $quickActions = array_values(array_filter([
            ['key' => 'students', 'href' => route('students.index'), 'tone' => 'teal', 'any' => ['students.view']],
            ['key' => 'attendance', 'href' => route('attendance.index'), 'tone' => 'blue', 'any' => ['attendance.record', 'attendance.approve', 'attendance.staff']],
            ['key' => 'fees', 'href' => route('fees.index'), 'tone' => 'navy', 'any' => ['fees.view']],
            ['key' => 'lms', 'href' => route('lms.courses.index'), 'tone' => 'purple', 'any' => ['lms.view']],
        ], fn (array $action) => $user?->canAny($action['any'])));

        return Inertia::render('Admin/Dashboard', array_merge($this->shellProps(), [
            'billing' => $school ? app(SubscriptionLifecycle::class)->state($school) : null,
            'billingUrl' => $user?->can('subscriptions.view') ? route('billing.index') : null,
            'stats' => $cached['stats'],
            'recentActivity' => $recentActivity,
            'quickActions' => array_map(fn (array $action) => [
                'key' => $action['key'],
                'href' => $action['href'],
                'tone' => $action['tone'],
            ], $quickActions),
            'schoolUrls' => $canManageSchoolUrl && $school ? $domains->urlsFor($school->fresh('domains')) : null,
            'domainSettingsUrl' => $user?->can('domains.manage') ? route('school.domains.edit') : null,
            'can' => [
                'students' => (bool) $user?->can('students.view'),
                'staff' => (bool) $user?->canAny(['hr.view', 'students.view']),
                'classes' => (bool) $user?->canAny(['academics.view', 'students.view']),
                'fees' => (bool) $user?->can('fees.view'),
            ],
        ]));
    }
}
