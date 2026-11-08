<?php

namespace App\Services\Scale;

use App\Models\CapacityCertificationRun;
use App\Models\WorkloadModel;

class CapacityCertifier
{
    public function gateThresholds(string $gate): array
    {
        return config("schoolos.capacity.gates.{$gate}", [
            'max_p95_ms' => 1500,
            'max_error_rate_pct' => 5,
            'min_rps' => 0,
        ]);
    }

    public function evaluate(array $payload): array
    {
        $gate = $payload['gate'];
        $thresholds = $this->gateThresholds($gate);
        $p95 = (float) ($payload['p95_ms'] ?? 0);
        $errors = (float) ($payload['error_rate_pct'] ?? 100);
        $rps = (float) ($payload['rps'] ?? 0);

        $checks = [
            'p95' => $p95 > 0 && $p95 <= (float) $thresholds['max_p95_ms'],
            'errors' => $errors <= (float) $thresholds['max_error_rate_pct'],
            'rps' => $rps >= (float) ($thresholds['min_rps'] ?? 0),
        ];

        $passed = ! in_array(false, $checks, true);

        return [
            'status' => $passed ? 'passed' : 'failed',
            'checks' => $checks,
            'thresholds' => $thresholds,
        ];
    }

    public function record(array $payload, ?int $userId = null): CapacityCertificationRun
    {
        $eval = $this->evaluate($payload);

        $run = CapacityCertificationRun::query()->create([
            'gate' => $payload['gate'],
            'profile' => $payload['profile'],
            'status' => $eval['status'],
            'environment' => $payload['environment'] ?? 'staging',
            'vus' => $payload['vus'] ?? null,
            'duration_seconds' => $payload['duration_seconds'] ?? null,
            'p95_ms' => $payload['p95_ms'] ?? null,
            'error_rate_pct' => $payload['error_rate_pct'] ?? null,
            'rps' => $payload['rps'] ?? null,
            'thresholds' => $eval['thresholds'],
            'results' => ['checks' => $eval['checks'], 'raw' => $payload['results'] ?? null],
            'notes' => $payload['notes'] ?? null,
            'recorded_by' => $userId,
            'ran_at' => now(),
        ]);

        // Only mark workload model certified for Gate 2+ sustained evidence on that profile.
        if ($run->status === 'passed' && in_array($run->gate, ['gate2', 'gate3'], true)) {
            WorkloadModel::query()
                ->where('profile', $run->profile)
                ->update(['is_certified' => false]); // still false for extreme claims

            // Soft flag in notes — extreme (2M) remains uncertified by policy.
            $run->update([
                'notes' => trim(($run->notes ?? '').' Gate evidence recorded; extreme-scale (2M) remains uncertified.'),
            ]);
        }

        return $run->fresh();
    }

    public function summary(): array
    {
        $latest = CapacityCertificationRun::query()
            ->latest('ran_at')
            ->limit(20)
            ->get();

        $byGate = [];
        foreach (['gate1', 'gate2', 'gate3', 'gate4'] as $gate) {
            $run = CapacityCertificationRun::query()
                ->where('gate', $gate)
                ->latest('ran_at')
                ->first();
            $byGate[$gate] = $run ? [
                'status' => $run->status,
                'profile' => $run->profile,
                'p95_ms' => $run->p95_ms,
                'error_rate_pct' => $run->error_rate_pct,
                'ran_at' => $run->ran_at?->toDateTimeString(),
            ] : ['status' => 'not_run'];
        }

        return [
            'gates' => $byGate,
            'latest' => $latest->map(fn (CapacityCertificationRun $r) => [
                'id' => $r->id,
                'gate' => $r->gate,
                'profile' => $r->profile,
                'status' => $r->status,
                'environment' => $r->environment,
                'p95_ms' => $r->p95_ms,
                'error_rate_pct' => $r->error_rate_pct,
                'rps' => $r->rps,
                'ran_at' => $r->ran_at?->diffForHumans(),
            ])->all(),
            'two_million_certified' => false,
        ];
    }
}
