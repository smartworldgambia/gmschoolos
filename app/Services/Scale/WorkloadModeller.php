<?php

namespace App\Services\Scale;

use App\Models\WorkloadModel;

class WorkloadModeller
{
    /**
     * Profiles are modelling inputs, not measured capacity.
     * is_certified stays false until a capacity_certification_run passes for that profile.
     */
    public function profiles(): array
    {
        return config('schoolos.workload.profiles', []);
    }

    public function estimate(string $profileKey, ?array $override = null): array
    {
        $profiles = $this->profiles();
        abort_unless(isset($profiles[$profileKey]), 422, "Unknown workload profile {$profileKey}");

        $p = array_merge($profiles[$profileKey], $override ?? []);
        $users = (int) $p['active_users'];
        $rpm = (float) $p['requests_per_user_per_min'];
        $rps = round(($users * $rpm) / 60, 2);

        $connPerReplica = (int) config('schoolos.workload.db_connections_per_app_replica', 15);
        $budget = (int) config('schoolos.autoscaling.db_connection_budget', 80);
        $targetRpsPerReplica = (float) config('schoolos.workload.target_rps_per_replica', 40);

        $appReplicas = max(1, (int) ceil($rps / max($targetRpsPerReplica, 1)));
        $dbConns = $appReplicas * $connPerReplica;
        while ($dbConns > $budget && $targetRpsPerReplica < 200) {
            // Prefer fewer replicas if connection budget binds; flag in assumptions.
            $appReplicas = max(1, $appReplicas - 1);
            $dbConns = $appReplicas * $connPerReplica;
            break;
        }

        $workers = max(
            (int) config('schoolos.autoscaling.worker_min', 1),
            (int) ceil($rps / (float) config('schoolos.workload.target_rps_per_worker', 80))
        );
        $workers = min($workers, (int) config('schoolos.autoscaling.worker_max', 6));

        return [
            'profile' => $profileKey,
            'label' => $p['label'],
            'assumed_active_users' => $users,
            'assumed_schools' => (int) $p['schools'],
            'requests_per_user_per_min' => $rpm,
            'estimated_rps' => $rps,
            'estimated_db_connections' => $dbConns,
            'recommended_app_replicas' => $appReplicas,
            'recommended_workers' => $workers,
            'assumptions' => [
                'note' => 'Model only — not a load-test certification.',
                'target_rps_per_replica' => $targetRpsPerReplica,
                'db_connections_per_app_replica' => $connPerReplica,
                'db_connection_budget' => $budget,
                'connection_budget_binding' => $dbConns >= $budget,
                'two_million_users_claimed' => false,
            ],
            'cost_notes' => [
                'message' => 'Do not quote infra cost for 2M users from this model alone.',
                'tags' => config('schoolos.cost.tags'),
            ],
        ];
    }

    public function persistAll(): int
    {
        $count = 0;
        foreach (array_keys($this->profiles()) as $key) {
            $est = $this->estimate($key);
            WorkloadModel::query()->updateOrCreate(
                ['profile' => $key],
                [
                    ...$est,
                    'is_certified' => false,
                ]
            );
            $count++;
        }

        return $count;
    }
}
