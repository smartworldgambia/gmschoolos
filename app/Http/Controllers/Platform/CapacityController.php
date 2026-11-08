<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Concerns\SharesPlatformShell;
use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\WorkloadModel;
use App\Services\Scale\CapacityCertifier;
use App\Services\Scale\TenantIsolationService;
use App\Services\Scale\WorkloadModeller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CapacityController extends Controller
{
    use SharesPlatformShell;

    public function index(
        WorkloadModeller $modeller,
        CapacityCertifier $certifier,
        TenantIsolationService $isolation,
    ): Response {
        if (WorkloadModel::query()->count() === 0) {
            $modeller->persistAll();
        }

        $models = WorkloadModel::query()->orderBy('profile')->get()->map(fn (WorkloadModel $m) => [
            'profile' => $m->profile,
            'label' => $m->label,
            'assumed_active_users' => $m->assumed_active_users,
            'assumed_schools' => $m->assumed_schools,
            'estimated_rps' => $m->estimated_rps,
            'estimated_db_connections' => $m->estimated_db_connections,
            'recommended_app_replicas' => $m->recommended_app_replicas,
            'recommended_workers' => $m->recommended_workers,
            'is_certified' => $m->is_certified,
            'assumptions' => $m->assumptions,
        ]);

        return Inertia::render('Platform/Capacity/Index', array_merge($this->platformShellProps(), [
            'models' => $models,
            'certification' => $certifier->summary(),
            'isolation' => $isolation->platformSummary(),
            'schools' => School::query()
                ->orderBy('name')
                ->limit(100)
                ->get(['id', 'name', 'slug', 'isolation_tier', 'db_connection', 'partition_key']),
            'twoMillionCertified' => false,
            'urls' => [
                'remodel' => route('platform.capacity.remodel'),
                'record' => route('platform.capacity.record'),
                'isolation' => route('platform.capacity.isolation'),
            ],
        ]));
    }

    public function remodel(WorkloadModeller $modeller): RedirectResponse
    {
        $modeller->persistAll();

        return back()->with('success', 'Workload models refreshed (still uncertified estimates).');
    }

    public function record(Request $request, CapacityCertifier $certifier): RedirectResponse
    {
        $validated = $request->validate([
            'gate' => ['required', 'in:gate1,gate2,gate3,gate4'],
            'profile' => ['required', 'in:A,B,C'],
            'environment' => ['required', 'string', 'max:40'],
            'p95_ms' => ['required', 'numeric', 'min:0'],
            'error_rate_pct' => ['required', 'numeric', 'min:0'],
            'rps' => ['required', 'numeric', 'min:0'],
            'vus' => ['nullable', 'integer', 'min:1'],
            'duration_seconds' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $run = $certifier->record($validated, auth()->id());

        return back()->with(
            $run->status === 'passed' ? 'success' : 'error',
            "Capacity run {$run->gate}/{$run->profile}: {$run->status}. 2M users remain uncertified."
        );
    }

    public function updateIsolation(Request $request, TenantIsolationService $isolation): RedirectResponse
    {
        $validated = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'isolation_tier' => ['required', 'in:shared,premium_isolated,dedicated_db'],
            'plan_dedicated' => ['sometimes', 'boolean'],
            'host' => ['nullable', 'string', 'max:191'],
            'database' => ['nullable', 'string', 'max:100'],
        ]);

        $school = School::query()->findOrFail($validated['school_id']);

        if ($validated['isolation_tier'] === 'dedicated_db' && ($validated['plan_dedicated'] ?? false)) {
            $isolation->planDedicatedDatabase($school, [
                'host' => $validated['host'] ?? 'db.example.internal',
                'database' => $validated['database'] ?? ('schoolos_'.$school->slug),
            ]);
        } else {
            $isolation->assignTier($school, $validated['isolation_tier']);
        }

        return back()->with('success', 'Isolation tier updated.');
    }
}
