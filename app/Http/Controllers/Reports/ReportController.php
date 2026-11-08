<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Concerns\AuthorizesSchoolAdmin;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Jobs\RefreshReportStoreJob;
use App\Models\ReportFact;
use App\Services\Reporting\ReportStoreRefresher;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    use AuthorizesSchoolAdmin, SharesSchoolShell;

    public function index(): Response
    {
        abort_unless(auth()->user()?->can('reports.view'), 403);

        $schoolId = app(TenantContext::class)->id() ?? auth()->user()->school_id;

        $facts = ReportFact::query()
            ->where('school_id', $schoolId)
            ->orderByDesc('captured_on')
            ->orderBy('fact_key')
            ->limit(60)
            ->get()
            ->map(fn (ReportFact $f) => [
                'fact_key' => $f->fact_key,
                'grain' => $f->grain,
                'captured_on' => $f->captured_on?->toDateString(),
                'value_numeric' => $f->value_numeric,
                'dimensions' => $f->dimensions,
                'refreshed_at' => $f->refreshed_at?->toDateTimeString(),
            ]);

        $latestByKey = ReportFact::query()
            ->where('school_id', $schoolId)
            ->whereDate('captured_on', now()->toDateString())
            ->get()
            ->keyBy('fact_key');

        return Inertia::render('Reports/Index', array_merge($this->shellProps(), [
            'facts' => $facts,
            'today' => [
                'students_active' => $latestByKey->get('students.active')?->value_numeric,
                'fees_collected' => $latestByKey->get('fees.collected_to_date')?->value_numeric,
                'attendance_rate' => $latestByKey->get('attendance.rate')?->value_numeric,
            ],
            'refreshUrl' => route('reports.refresh'),
        ]));
    }

    public function refresh(ReportStoreRefresher $refresher): RedirectResponse
    {
        abort_unless(auth()->user()?->can('reports.view'), 403);

        $school = app(TenantContext::class)->school() ?? auth()->user()?->school;
        if ($school) {
            $refresher->refreshSchool($school);
        } else {
            RefreshReportStoreJob::dispatch();
        }

        return back()->with('success', 'Reporting store refreshed.');
    }
}
