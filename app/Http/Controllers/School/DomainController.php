<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\School;
use App\Services\School\SchoolDomainService;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class DomainController extends Controller
{
    use SharesSchoolShell;

    public function __construct(protected SchoolDomainService $domains) {}

    public function edit(Request $request, TenantContext $tenant): Response
    {
        $school = $this->currentSchool($tenant);
        $this->domains->assignPlatformDomain($school);

        // Live polling from the UI: re-check pending DNS at most once every 20s per school.
        if ($request->boolean('check') && Cache::add('schoolos:domain-check:'.$school->id, 1, 20)) {
            $this->checkPending($school);
        }

        $school->load('domains');
        $urls = $this->domains->urlsFor($school);
        $cnameTarget = $this->domains->cnameTarget($school);

        $sorted = $school->domains
            ->sortBy([['is_primary', 'desc'], ['is_custom', 'desc'], ['id', 'asc']])
            ->values();

        $customCount = $school->domains->where('is_custom', true)->count();

        return Inertia::render('School/Domain/Edit', array_merge($this->shellProps(), [
            'urls' => $urls,
            'cnameTarget' => $cnameTarget,
            'baseDomain' => $this->domains->baseDomain(),
            'domains' => $sorted->map(fn (Domain $d) => $this->serializeDomain($d, $urls))->all(),
            'storeUrl' => route('school.domains.store'),
            'customAllowed' => $this->domains->customDomainsAllowed($school),
            'customLimit' => (int) config('schoolos.custom_domain_limit', 3),
            'customCount' => $customCount,
            'pendingTtlDays' => (int) config('schoolos.custom_domain_pending_ttl_days', 14),
            'isLocalInstall' => in_array($this->domains->baseDomain(), ['localhost', '127.0.0.1'], true),
        ]));
    }

    public function store(Request $request, TenantContext $tenant): RedirectResponse
    {
        $school = $this->currentSchool($tenant);

        $validated = $request->validate([
            'host' => ['required', 'string', 'max:191'],
            'make_primary' => ['sometimes', 'boolean'],
        ]);

        $makePrimary = (bool) ($validated['make_primary'] ?? true);

        try {
            $domain = $this->domains->connectCustomDomain($school, $validated['host'], $makePrimary);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['host' => $e->getMessage()])->withInput();
        }

        // Local/testing auto-verifies; production waits for DNS.
        try {
            $this->domains->verifyAndActivate($domain);
            $message = $makePrimary
                ? 'Custom domain connected and set as your main school URL.'
                : 'Custom domain connected and verified.';
        } catch (InvalidArgumentException) {
            $message = 'Domain added. Publish the DNS records below — we will detect them automatically and switch your URL over.';
        }

        return back()->with('success', $message);
    }

    public function verify(Domain $domain, TenantContext $tenant): RedirectResponse
    {
        $this->authorizeDomain($domain, $tenant);

        try {
            $activated = $this->domains->verifyAndActivate($domain);

            return back()->with('success', $activated->is_primary
                ? 'Domain verified. It is now your main school URL.'
                : 'Domain verified.');
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function makePrimary(Domain $domain, TenantContext $tenant): RedirectResponse
    {
        $this->authorizeDomain($domain, $tenant);

        try {
            $this->domains->makePrimary($domain);

            return back()->with('success', 'Main school URL updated.');
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy(Domain $domain, TenantContext $tenant): RedirectResponse
    {
        $this->authorizeDomain($domain, $tenant);

        try {
            $this->domains->removeCustomDomain($domain);

            return back()->with('success', 'Custom domain removed. Your platform URL is active again.');
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    protected function checkPending(School $school): void
    {
        $school->domains()
            ->where('is_custom', true)
            ->whereNull('verified_at')
            ->get()
            ->each(function (Domain $domain) {
                try {
                    $this->domains->verifyAndActivate($domain);
                } catch (InvalidArgumentException) {
                    // Still pending; status persisted on the row.
                }
            });
    }

    protected function serializeDomain(Domain $domain, array $urls): array
    {
        return [
            'id' => $domain->id,
            'host' => $domain->host,
            'is_primary' => $domain->is_primary,
            'is_custom' => $domain->is_custom,
            'ssl_active' => $domain->ssl_active,
            'verified' => $domain->isVerified(),
            'verified_at' => $domain->verified_at?->toIso8601String(),
            'last_checked_at' => $domain->last_checked_at?->toIso8601String(),
            'created_at' => $domain->created_at?->toIso8601String(),
            'error' => $domain->verification_error,
            'method' => $domain->verification_method,
            'primary_on_verify' => $domain->primary_on_verify,
            'url' => $domain->is_custom
                ? $this->domains->absoluteUrl($domain->host, '/', $domain->ssl_active ? true : null)
                : $urls['platform_url'],
            'verify_url' => $domain->is_custom ? route('school.domains.verify', $domain) : null,
            'make_primary_url' => route('school.domains.primary', $domain),
            'destroy_url' => $domain->is_custom ? route('school.domains.destroy', $domain) : null,
            'records' => $domain->is_custom && ! $domain->isVerified()
                ? $this->domains->dnsRecords($domain)
                : [],
        ];
    }

    protected function authorizeDomain(Domain $domain, TenantContext $tenant): void
    {
        $school = $this->currentSchool($tenant);
        abort_unless((int) $domain->school_id === (int) $school->id, 404);
    }

    protected function currentSchool(TenantContext $tenant): School
    {
        $school = $tenant->school() ?? auth()->user()?->school;
        abort_unless($school, 403);
        abort_unless(auth()->user()?->can('domains.manage'), 403);

        return $school;
    }
}
