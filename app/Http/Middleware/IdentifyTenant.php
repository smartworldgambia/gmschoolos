<?php

namespace App\Http\Middleware;

use App\Models\Domain;
use App\Models\School;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    /** Public, cookie-free pages that may be canonicalised to the school's primary custom domain. */
    protected const CANONICAL_PATHS = ['/', 'privacy', 'terms'];

    public function __construct(protected TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());
        $central = config('schoolos.central_domains', []);

        if (in_array($host, $central, true)) {
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

        $domain = Domain::query()->where('host', $host)->first();
        if ($domain && (! $domain->is_custom || $domain->verified_at !== null)) {
            $this->tenant->set($domain->school);

            if ($redirect = $this->redirectToPrimary($request, $domain)) {
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
    protected function redirectToPrimary(Request $request, Domain $current): ?Response
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
            || strtolower($primary->host) === strtolower($request->getHost())) {
            return null;
        }

        return redirect()->away('https://'.$primary->host.$request->getRequestUri(), 302);
    }
}
