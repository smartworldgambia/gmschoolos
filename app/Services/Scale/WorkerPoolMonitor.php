<?php

namespace App\Services\Scale;

use Illuminate\Support\Facades\DB;
use Throwable;

class WorkerPoolMonitor
{
    /**
     * @return array<int, array{name:string, queue:string, pending:int|null, status:string}>
     */
    public function pools(): array
    {
        $queues = config('schoolos.queues', []);
        $driver = config('queue.default');
        $pools = [];

        foreach ($queues as $name => $queue) {
            $pending = null;
            $status = 'ok';

            try {
                if ($driver === 'database') {
                    $pending = (int) DB::table(config('queue.connections.database.table', 'jobs'))
                        ->where('queue', $queue)
                        ->count();
                } elseif ($driver === 'sync') {
                    $pending = 0;
                }
            } catch (Throwable $e) {
                $status = 'error';
            }

            $pools[] = [
                'name' => $name,
                'queue' => $queue,
                'pending' => $pending,
                'status' => $status,
                'sheddable' => in_array($name, ['reports', 'notifications', 'exports'], true),
            ];
        }

        return $pools;
    }

    public function totalPending(): int
    {
        return (int) collect($this->pools())->sum(fn ($p) => (int) ($p['pending'] ?? 0));
    }

    public function autoscalingHints(): array
    {
        $cfg = config('schoolos.autoscaling', []);
        $depth = $this->totalPending();
        $scaleOutAt = (int) ($cfg['scale_out_queue_depth'] ?? 100);

        return [
            'app_min' => $cfg['app_min'] ?? 1,
            'app_max' => $cfg['app_max'] ?? 4,
            'worker_min' => $cfg['worker_min'] ?? 1,
            'worker_max' => $cfg['worker_max'] ?? 6,
            'target_cpu_pct' => $cfg['target_cpu_pct'] ?? 65,
            'scale_out_queue_depth' => $scaleOutAt,
            'current_queue_depth' => $depth,
            'recommend_scale_workers' => $depth >= $scaleOutAt,
            'db_connection_budget' => $cfg['db_connection_budget'] ?? 80,
            'tags' => config('schoolos.cost.tags', []),
        ];
    }
}
