<?php

namespace App\Http\Middleware;

use App\Models\Domain;
use App\Models\School;
use App\Services\School\SchoolDomainService;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    /** Public, cookie-free pages that may be canonicalised to the school's primary custom domain. */
    protected const CANONICAL_PATHS = ['/', 'privacy', 'terms'];

    public function __construct(
        protected TenantContext $tenant,
        protected SchoolDomainService $domains,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->tenant->clear();

        $host = $this->domains->requestHost($request);

        if ($this->domains->isCentralHost($host)) {
            // Optional path-based school site for local/dev without wildcard DNS.
            if ($request->route('school_slug')) {
                $school = School::query()
                    ->where('slug', $request->route('school_slug'))
                    ->where('status', 'active')
                    ->first();
                $this->tenant->set($school);
            }

            return $next($request);
        }

        $school = $this->domains->schoolForHost($host);
        if ($school) {
            $this->tenant->set($school);

            $domain = Domain::query()->where('host', $host)->first();
            if ($domain && ($redirect = $this->redirectToPrimary($request, $domain, $host))) {
                return $redirect;
            }
        }

        return $next($request);
    }

    /**
     * Once a custom domain is verified, live on TLS and chosen as the main URL,
     * public pages on any other host of the school (e.g. the platform subdomain) forward to it.
     * Sign-in and /admin are intentionally left alone so admins are never locked out.
     */
    protected function redirectToPrimary(Request $request, Domain $current, string $host): ?Response
    {
        if (! config('schoolos.redirect_to_primary_domain', true)
            || $current->is_primary
            || ! $request->isMethod('GET')
            || $request->header('X-Inertia')
            || $request->ajax()
            || ! in_array(trim($request->path(), '/') === '' ? '/' : trim($request->path(), '/'), self::CANONICAL_PATHS, true)) {
            return null;
        }

        $primary = Domain::query()
            ->where('school_id', $current->school_id)
            ->where('is_primary', true)
            ->first();

        if (! $primary || ! $primary->is_custom || ! $primary->verified_at || ! $primary->ssl_active
            || strtolower($primary->host) === $host) {
            return null;
        }

        return redirect()->away('https://'.$primary->host.$request->getRequestUri(), 302);
    }
}
