<?php

namespace App\Services\Platform;

use App\Models\PlatformAlert;
use App\Services\Scale\WorkerPoolMonitor;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthMonitor
{
    public function snapshot(): array
    {
        $db = $this->probeDatabase();
        $queue = $this->probeQueue();
        $pools = app(WorkerPoolMonitor::class)->pools();
        $openAlerts = PlatformAlert::query()->open()->count();
        $criticalOpen = PlatformAlert::query()->open()->where('severity', 'critical')->count();

        $status = 'ok';
        if ($db['status'] !== 'ok' || $criticalOpen > 0) {
            $status = 'degraded';
        }
        if ($db['status'] === 'error') {
            $status = 'unavailable';
        }

        return [
            'status' => $status,
            'database' => $db,
            'queue' => $queue,
            'pools' => $pools,
            'alerts' => [
                'open' => $openAlerts,
                'critical_open' => $criticalOpen,
            ],
            'time' => now()->toIso8601String(),
        ];
    }

    public function raiseIfDegraded(): ?PlatformAlert
    {
        $snap = $this->snapshot();

        if ($snap['database']['status'] === 'error') {
            return PlatformAlert::query()->create([
                'severity' => 'critical',
                'source' => 'health',
                'title' => 'Database unreachable',
                'message' => $snap['database']['detail'] ?? 'Database probe failed',
                'context' => $snap,
            ]);
        }

        return null;
    }

    protected function probeDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return ['status' => 'ok', 'detail' => DB::connection()->getDatabaseName()];
        } catch (Throwable $e) {
            return ['status' => 'error', 'detail' => $e->getMessage()];
        }
    }

    protected function probeQueue(): array
    {
        try {
            $driver = config('queue.default');
            $size = null;
            if ($driver === 'database') {
                $size = DB::table(config('queue.connections.database.table', 'jobs'))->count();
            }

            return [
                'status' => 'ok',
                'driver' => $driver,
                'pending_jobs' => $size,
            ];
        } catch (Throwable $e) {
            return ['status' => 'error', 'driver' => config('queue.default'), 'detail' => $e->getMessage()];
        }
    }
}
