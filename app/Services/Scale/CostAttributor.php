<?php

namespace App\Services\Scale;

use App\Models\PlatformAlert;
use App\Models\School;
use App\Models\UsageSnapshot;
use Illuminate\Support\Collection;

class CostAttributor
{
    public function weights(): array
    {
        return config('schoolos.cost.weights', []);
    }

    public function unitsForSnapshot(UsageSnapshot $snap): float
    {
        $w = $this->weights();

        return round(
            ($snap->students_count * ($w['students'] ?? 1))
            + ($snap->request_count * ($w['requests'] ?? 0.01))
            + ($snap->export_count * ($w['exports'] ?? 5))
            + ($snap->import_count * ($w['imports'] ?? 8))
            + ($snap->courses_count * ($w['courses'] ?? 0.5)),
            2
        );
    }

    /**
     * @return Collection<int, array{school_id:int, school:string, slug:string, units:float, students:int, requests:int, exports:int, imports:int}>
     */
    public function bySchool(?string $from = null, ?string $to = null): Collection
    {
        $from ??= now()->subDays(7)->toDateString();
        $to ??= now()->toDateString();

        $rows = UsageSnapshot::query()
            ->whereDate('captured_on', '>=', $from)
            ->whereDate('captured_on', '<=', $to)
            ->whereNotNull('school_id')
            ->get()
            ->groupBy('school_id');

        $schools = School::query()->whereIn('id', $rows->keys())->get()->keyBy('id');

        return $rows->map(function (Collection $snaps, $schoolId) use ($schools) {
            $units = $snaps->sum(fn (UsageSnapshot $s) => $this->unitsForSnapshot($s));
            $school = $schools->get($schoolId);

            return [
                'school_id' => (int) $schoolId,
                'school' => $school?->name ?? 'Unknown',
                'slug' => $school?->slug ?? '',
                'units' => round($units, 2),
                'students' => (int) $snaps->avg('students_count'),
                'requests' => (int) $snaps->sum('request_count'),
                'exports' => (int) $snaps->sum('export_count'),
                'imports' => (int) $snaps->sum('import_count'),
            ];
        })->sortByDesc('units')->values();
    }

    public function platformSummary(?string $from = null, ?string $to = null): array
    {
        $bySchool = $this->bySchool($from, $to);
        $total = round($bySchool->sum('units'), 2);
        $budget = (float) config('schoolos.cost.daily_budget_units', 100000);
        $days = max(1, now()->parse($from ?? now()->subDays(7))->diffInDays(now()->parse($to ?? now())) + 1);
        $periodBudget = $budget * $days;
        $pct = $periodBudget > 0 ? round(($total / $periodBudget) * 100, 1) : 0;

        return [
            'total_units' => $total,
            'period_budget_units' => $periodBudget,
            'budget_used_pct' => $pct,
            'schools_counted' => $bySchool->count(),
            'top' => $bySchool->take(10)->values()->all(),
            'tags' => config('schoolos.cost.tags', []),
            'weights' => $this->weights(),
            'from' => $from ?? now()->subDays(7)->toDateString(),
            'to' => $to ?? now()->toDateString(),
        ];
    }

    public function raiseBudgetAlertIfNeeded(?string $from = null, ?string $to = null): ?PlatformAlert
    {
        $summary = $this->platformSummary($from, $to);
        $alertPct = (float) config('schoolos.cost.alert_pct', 80);

        if ($summary['budget_used_pct'] < $alertPct) {
            return null;
        }

        $existing = PlatformAlert::query()
            ->open()
            ->where('source', 'billing')
            ->where('title', 'Cost attribution budget pressure')
            ->whereDate('created_at', now()->toDateString())
            ->exists();

        if ($existing) {
            return null;
        }

        return PlatformAlert::query()->create([
            'severity' => $summary['budget_used_pct'] >= 100 ? 'critical' : 'warning',
            'source' => 'billing',
            'title' => 'Cost attribution budget pressure',
            'message' => "Relative cost units at {$summary['budget_used_pct']}% of period budget ({$summary['total_units']} / {$summary['period_budget_units']}).",
            'context' => $summary,
        ]);
    }
}
