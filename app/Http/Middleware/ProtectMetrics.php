<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autoscaling probes may read /metrics. The public internet may not.
 *
 * Production is fail-closed: a token and/or IP allow-list must be set.
 * Local and testing stay open when nothing is configured so existing
 * developer scripts and Gate 1 smokes keep working.
 */
class ProtectMetrics
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('schoolos.metrics.token', '');
        $allowIps = config('schoolos.metrics.allow_ips', []);

        if ($token === '' && $allowIps === []) {
            abort_if(app()->environment('production'), 403);

            return $next($request);
        }

        if ($allowIps !== [] && ! in_array($request->ip(), $allowIps, true)) {
            abort(403);
        }

        if ($token !== '') {
            $provided = $this->providedToken($request);
            abort_if($provided === '' || ! hash_equals($token, $provided), 401);
        }

        return $next($request);
    }

    private function providedToken(Request $request): string
    {
        $header = (string) $request->header('Authorization', '');
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return (string) $request->header('X-Metrics-Token', '');
    }
}
