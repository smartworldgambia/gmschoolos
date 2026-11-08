<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Concerns\SharesPlatformShell;
use App\Http\Controllers\Controller;
use App\Services\Scale\CacheTuner;
use App\Services\Scale\CostAttributor;
use App\Services\Scale\QueryProfiler;
use App\Services\Scale\WorkerPoolMonitor;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ScaleController extends Controller
{
    use SharesPlatformShell;

    public function index(
        WorkerPoolMonitor $workers,
        CostAttributor $cost,
        QueryProfiler $profiler,
    ): Response {
        return Inertia::render('Platform/Scale/Index', array_merge($this->platformShellProps(), [
            'pools' => $workers->pools(),
            'autoscaling' => $workers->autoscalingHints(),
            'cost' => $cost->platformSummary(),
            'slowQueries' => $profiler->recent(),
            'queryProfileEnabled' => $profiler->enabled(),
            'cacheWarmUrl' => route('platform.scale.cache-warm'),
        ]));
    }

    public function warmCache(CacheTuner $cache): RedirectResponse
    {
        $result = $cache->warm();

        return back()->with('success', "Cache warmed ({$result['public_sites']} public sites).");
    }
}
