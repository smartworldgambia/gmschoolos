<?php

namespace App\Console\Commands;

use App\Services\Scale\WorkloadModeller;
use Illuminate\Console\Command;

class ModelWorkloadCommand extends Command
{
    protected $signature = 'schoolos:model-workload {--persist : Store estimates in workload_models}';

    protected $description = 'Estimate capacity needs for traffic profiles A/B/C (modelling only)';

    public function handle(WorkloadModeller $modeller): int
    {
        $rows = [];
        foreach (array_keys($modeller->profiles()) as $key) {
            $e = $modeller->estimate($key);
            $rows[] = [
                $e['profile'],
                $e['label'],
                $e['assumed_active_users'],
                $e['estimated_rps'],
                $e['estimated_db_connections'],
                $e['recommended_app_replicas'],
                $e['recommended_workers'],
                'no',
            ];
        }

        $this->table(
            ['Profile', 'Label', 'Users', 'RPS', 'DB conns', 'App', 'Workers', '2M certified'],
            $rows
        );
        $this->warn('These are models, not load-test certifications. 2M concurrent users remain uncertified.');

        if ($this->option('persist')) {
            $n = $modeller->persistAll();
            $this->info("Persisted {$n} workload model row(s).");
        }

        return self::SUCCESS;
    }
}
