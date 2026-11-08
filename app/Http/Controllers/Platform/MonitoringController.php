<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Concerns\SharesPlatformShell;
use App\Http\Controllers\Controller;
use App\Models\PlatformAlert;
use App\Models\UsageSnapshot;
use App\Services\Platform\HealthMonitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MonitoringController extends Controller
{
    use SharesPlatformShell;

    public function index(HealthMonitor $monitor): Response
    {
        $alerts = PlatformAlert::query()
            ->with('school:id,name,slug')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (PlatformAlert $a) => [
                'id' => $a->id,
                'severity' => $a->severity,
                'source' => $a->source,
                'title' => $a->title,
                'message' => $a->message,
                'school' => $a->school?->name,
                'acknowledged_at' => $a->acknowledged_at?->toDateTimeString(),
                'resolved_at' => $a->resolved_at?->toDateTimeString(),
                'created_at' => $a->created_at?->diffForHumans(),
                'is_open' => $a->resolved_at === null,
            ]);

        $usageTotals = UsageSnapshot::query()
            ->whereDate('captured_on', '>=', now()->subDays(7)->toDateString())
            ->selectRaw('captured_on, SUM(students_count) as students, SUM(request_count) as requests, SUM(export_count) as exports')
            ->groupBy('captured_on')
            ->orderBy('captured_on')
            ->get()
            ->map(fn ($row) => [
                'date' => (string) $row->captured_on,
                'students' => (int) $row->students,
                'requests' => (int) $row->requests,
                'exports' => (int) $row->exports,
            ]);

        return Inertia::render('Platform/Monitoring/Index', array_merge($this->platformShellProps(), [
            'health' => $monitor->snapshot(),
            'alerts' => $alerts,
            'usageTotals' => $usageTotals,
        ]));
    }

    public function acknowledge(PlatformAlert $alert): RedirectResponse
    {
        $alert->update([
            'acknowledged_at' => now(),
            'acknowledged_by' => auth()->id(),
        ]);

        return back()->with('success', 'Alert acknowledged.');
    }

    public function resolve(PlatformAlert $alert): RedirectResponse
    {
        $alert->update([
            'resolved_at' => now(),
            'acknowledged_at' => $alert->acknowledged_at ?? now(),
            'acknowledged_by' => $alert->acknowledged_by ?? auth()->id(),
        ]);

        return back()->with('success', 'Alert resolved.');
    }

    public function storeTestAlert(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'severity' => ['required', 'in:info,warning,critical'],
            'title' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:1000'],
        ]);

        PlatformAlert::query()->create([
            'severity' => $validated['severity'],
            'source' => 'manual',
            'title' => $validated['title'],
            'message' => $validated['message'],
        ]);

        return back()->with('success', 'Alert created.');
    }
}
