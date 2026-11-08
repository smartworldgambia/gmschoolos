<?php

namespace App\Http\Controllers;

use App\Services\Scale\CostAttributor;
use App\Services\Scale\QueryProfiler;
use App\Services\Scale\WorkerPoolMonitor;
use App\Services\Platform\HealthMonitor;
use Illuminate\Http\JsonResponse;

class MetricsController extends Controller
{
    /**
     * Lightweight JSON metrics for autoscaling probes and ops dashboards.
     * Not a Prometheus exposition format — keep it simple and host-agnostic.
     */
    public function __invoke(
        HealthMonitor $health,
        WorkerPoolMonitor $workers,
        CostAttributor $cost,
        QueryProfiler $profiler,
    ): JsonResponse {
        $snap = $health->snapshot();
        $hints = $workers->autoscalingHints();

        return response()->json([
            'status' => $snap['status'],
            'time' => now()->toIso8601String(),
            'health' => $snap,
            'queues' => $workers->pools(),
            'autoscaling' => $hints,
            'cost' => [
                'tags' => config('schoolos.cost.tags'),
                'daily_budget_units' => config('schoolos.cost.daily_budget_units'),
            ],
            'slow_queries_sampled' => count($profiler->recent()),
            'query_profile_enabled' => $profiler->enabled(),
        ]);
    }
}
