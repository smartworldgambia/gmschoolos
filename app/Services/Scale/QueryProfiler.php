<?php

namespace App\Services\Scale;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class QueryProfiler
{
    public function enabled(): bool
    {
        return (bool) config('schoolos.query_profile.enabled');
    }

    public function record(float $ms, string $sql, array $bindings = []): void
    {
        $threshold = (float) config('schoolos.query_profile.slow_ms', 100);

        if ($ms < $threshold) {
            return;
        }

        $entry = [
            'ms' => round($ms, 2),
            'sql' => mb_substr($sql, 0, 500),
            'bindings_count' => count($bindings),
            'at' => now()->toIso8601String(),
        ];

        Log::channel(config('logging.default'))->warning('slow_query', $entry);

        $key = 'schoolos:slow_queries';
        $limit = (int) config('schoolos.query_profile.sample_limit', 50);
        $list = Cache::get($key, []);
        array_unshift($list, $entry);
        Cache::put($key, array_slice($list, 0, $limit), now()->addDay());
    }

    public function recent(): array
    {
        return Cache::get('schoolos:slow_queries', []);
    }

    public function clear(): void
    {
        Cache::forget('schoolos:slow_queries');
    }
}
