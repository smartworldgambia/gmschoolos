<?php

namespace App\Http\Controllers;

use App\Services\Platform\HealthMonitor;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'app' => config('app.name'),
            'time' => now()->toIso8601String(),
        ]);
    }

    public function ready(HealthMonitor $monitor): JsonResponse
    {
        $snap = $monitor->snapshot();

        if ($snap['status'] === 'unavailable') {
            return response()->json([
                'status' => 'unavailable',
                'database' => $snap['database']['status'] ?? 'error',
                'queue' => $snap['queue'] ?? null,
            ], 503);
        }

        return response()->json([
            'status' => 'ready',
            'database' => $snap['database']['status'] ?? 'ok',
            'queue' => $snap['queue'] ?? null,
            'alerts' => $snap['alerts'] ?? null,
            'time' => $snap['time'],
        ]);
    }
}
