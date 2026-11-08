<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the platform console. Allows platform staff only (no school + a platform role) who hold
 * `platform.access`, and runs the request in EXPLICIT platform mode so cross-tenant reads are
 * deliberate and confined to this request. Individual routes add granular `permission:` checks.
 */
class EnsurePlatformAdmin
{
    public function __construct(protected TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isPlatformStaff() || ! $user->can('platform.access')) {
            abort(403, 'Platform staff access required.');
        }

        return $this->tenant->runAsPlatform(fn () => $next($request));
    }
}
