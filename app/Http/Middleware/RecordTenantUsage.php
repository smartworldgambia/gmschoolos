<?php

namespace App\Http\Middleware;

use App\Services\Platform\UsageRecorder;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordTenantUsage
{
    public function __construct(
        protected TenantContext $tenant,
        protected UsageRecorder $usage,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $schoolId = $this->tenant->id() ?? $request->user()?->school_id;
        $this->usage->bumpRequest($schoolId);

        return $response;
    }
}
