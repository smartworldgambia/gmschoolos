<?php

namespace App\Services\School;

use App\Models\Domain;
use App\Models\School;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Owns the whole URL lifecycle of a school:
 *  1. a dedicated platform subdomain assigned at registration ({slug}.{base_domain});
 *  2. optional custom domains, proven by DNS (routing record + ownership proof);
 *  3. promotion of a verified custom domain to the school's primary public URL.
 */
class SchoolDomainService
{
    public const TXT_LABEL = '_schoolos-verify';

    public const TXT_PREFIX = 'schoolos-verify=';

    /** Second-level labels that form a two-part public suffix when followed by a 2-letter ccTLD. */
    protected const MULTIPART_SLDS = ['co', 'com', 'edu', 'gov', 'org', 'net', 'ac', 'sch', 'or', 'ne', 'go', 'mil'];

    public function __construct(protected DnsResolver $dns) {}

    /* ------------------------------------------------------------------ */
    /* Platform subdomain                                                   */
    /* ------------------------------------------------------------------ */

    public function baseDomain(): string
    {
        return strtolower((string) config('schoolos.base_domain', 'localhost'));
    }

    public function platformHost(School $school): string
    {
        return strtolower($school->slug.'.'.$this->baseDomain());
    }

    public function cnameTarget(School $school): string
    {
        $configured = config('schoolos.custom_domain_cname_target');
        if (is_string($configured) && $configured !== '') {
            return strtolower($configured);
        }

        return $this->platformHost($school);
    }

    /**
     * Normalise user input into a DNS-safe subdomain label.
     */
    public function normalizeSlug(string $raw): string
    {
        return strtolower(trim($raw));
    }

    /**
     * Availability of a registration subdomain.
     *
     * @return array{status: string, slug: string, host: string, available: bool}
     */
    public function slugStatus(string $raw): array
    {
        $slug = $this->normalizeSlug($raw);
        $host = $slug.'.'.$this->baseDomain();

        $status = match (true) {
            $slug === '' => 'too_short',
            ! preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $slug) || str_contains($slug, '--') => 'invalid',
            strlen($slug) < 3 => 'too_short',
            strlen($slug) > 63 => 'too_long',
            in_array($slug, array_map('strtolower', (array) config('schoolos.reserved_subdomains', [])), true) => 'reserved',
            School::query()->where('slug', $slug)->exists() || Domain::query()->where('host', $host)->exists() => 'taken',
            default => 'available',
        };

        return [
            'status' => $status,
            'slug' => $slug,
            'host' => $host,
            'available' => $status === 'available',
        ];
    }

    public function slugErrorMessage(string $status): string
    {
        return match ($status) {
            'too_short' => 'Use at least 3 characters.',
            'too_long' => 'Use 63 characters or fewer.',
            'invalid' => 'Use lowercase letters, numbers and single hyphens (not at the start or end).',
            'reserved' => 'That subdomain is reserved by the platform. Please choose another.',
            'taken' => 'This subdomain is already taken.',
            default => '',
        };
    }

    /**
     * Ensure the school has a dedicated platform subdomain row (primary if none yet).
     */
    public function assignPlatformDomain(School $school): Domain
    {
        $host = $this->platformHost($school);

        $canonical = Domain::query()->where('host', $host)->first();
        if ($canonical && (int) $canonical->school_id !== (int) $school->id) {
            throw new InvalidArgumentException('Platform host is already assigned to another school.');
        }

        $canonical ??= Domain::query()->create([
            'school_id' => $school->id,
            'host' => $host,
            'is_primary' => false,
            'is_custom' => false,
            'ssl_active' => false,
            'verification_token' => null,
            'verified_at' => now(),
        ]);

        // The canonical platform URL is primary unless a (verified) custom domain has taken over.
        // A stale platform-style alias (e.g. left over from an older base domain) never keeps primary.
        $primary = $school->domains()->where('is_primary', true)->first();
        if (! $primary || (! $primary->is_custom && (int) $primary->id !== (int) $canonical->id)) {
            $canonical = $this->makePrimary($canonical);
        }

        return $canonical->fresh();
    }

    /* ------------------------------------------------------------------ */
    /* Custom domains                                                       */
    /* ------------------------------------------------------------------ */

    public function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('#^[a-z][a-z0-9+.-]*://#', '', $host) ?? $host;
        $host = explode('/', $host)[0];
        $host = explode('?', $host)[0];
        $host = explode('#', $host)[0];
        $host = explode(':', $host)[0];

        return rtrim($host, '.');
    }

    public function assertValidCustomHost(string $host): void
    {
        $host = $this->normalizeHost($host);

        if ($host === '' || strlen($host) > 253
            || ! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $host)) {
            throw new InvalidArgumentException('Enter a valid domain name (e.g. www.yourschool.gm).');
        }

        $base = $this->baseDomain();
        if ($host === $base || str_ends_with($host, '.'.$base)) {
            throw new InvalidArgumentException('Use your own domain — platform subdomains are assigned at registration.');
        }

        foreach (array_map('strtolower', (array) config('schoolos.central_domains', [])) as $central) {
            if ($host === $central || str_ends_with($host, '.'.$central)) {
                throw new InvalidArgumentException('That hostname is reserved by the platform.');
            }
        }
    }

    public function customDomainsAllowed(School $school): bool
    {
        // Subscriptions have no tiers, so custom domains are not a plan feature: every school may connect them
        // (bounded by custom_domain_limit and DNS ownership proof).
        return true;
    }

    /**
     * Register a custom domain claim. It stays pending until DNS proves ownership.
     *
     * @param  bool  $trusted  Platform operators may bypass plan/limit checks and skip DNS proof.
     */
    public function connectCustomDomain(School $school, string $rawHost, bool $makePrimary = true, bool $trusted = false): Domain
    {
        $host = $this->normalizeHost($rawHost);
        $this->assertValidCustomHost($host);

        if (! $trusted) {
            if (! $this->customDomainsAllowed($school)) {
                throw new InvalidArgumentException('Custom domains are not available for this school.');
            }

            $limit = (int) config('schoolos.custom_domain_limit', 3);
            if ($limit > 0 && $school->domains()->where('is_custom', true)->count() >= $limit) {
                throw new InvalidArgumentException("You can connect up to {$limit} custom domains. Remove one first.");
            }
        }

        $this->assignPlatformDomain($school);

        try {
            return DB::transaction(function () use ($school, $host, $makePrimary, $trusted) {
                $existing = Domain::query()->where('host', $host)->lockForUpdate()->first();

                if ($existing) {
                    if ((int) $existing->school_id === (int) $school->id) {
                        throw new InvalidArgumentException('This domain is already added to your school.');
                    }

                    if ($existing->verified_at !== null || ! $existing->is_custom) {
                        throw new InvalidArgumentException('This domain is already connected to another school. Contact support if you own it.');
                    }

                    // A competing claim that never proved DNS ownership must not squat the name.
                    $existing->delete();
                }

                return Domain::query()->create([
                    'school_id' => $school->id,
                    'host' => $host,
                    'is_primary' => false,
                    'is_custom' => true,
                    'primary_on_verify' => $makePrimary,
                    'ssl_active' => false,
                    'verification_token' => Str::lower(Str::random(32)),
                    'verified_at' => $trusted ? now() : null,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw new InvalidArgumentException('This domain is already connected to a school.');
        }
    }

    /**
     * DNS records the school must publish for a custom domain.
     *
     * @return list<array{purpose: string, type: string, name: string, host: string, value: string, required: bool}>
     */
    public function dnsRecords(Domain $domain): array
    {
        $school = $domain->school;
        $host = $domain->host;
        $target = $this->cnameTarget($school);
        $apex = $this->isApex($host);
        $ip = (string) config('schoolos.custom_domain_ip_target', '');
        $relative = $this->relativeName($host);

        $routing = match (true) {
            $apex && $ip !== '' => ['type' => 'A', 'value' => $ip],
            $apex => ['type' => 'ALIAS', 'value' => $target],
            default => ['type' => 'CNAME', 'value' => $target],
        };

        $txtName = $relative === '@' ? self::TXT_LABEL : self::TXT_LABEL.'.'.$relative;

        return [
            [
                'purpose' => 'routing',
                'type' => $routing['type'],
                'name' => $relative,
                'host' => $host,
                'value' => $routing['value'],
                'required' => true,
            ],
            [
                'purpose' => 'ownership',
                'type' => 'TXT',
                'name' => $txtName,
                'host' => self::TXT_LABEL.'.'.$host,
                'value' => self::TXT_PREFIX.$domain->verification_token,
                'required' => $this->txtRequired($school, $host),
            ],
        ];
    }

    /**
     * Ownership is implied when the CNAME points at the school's own platform host;
     * any shared target (or apex A/ALIAS) needs the TXT proof instead.
     */
    public function txtRequired(School $school, string $host): bool
    {
        if ($this->isApex($host)) {
            return true;
        }

        return $this->cnameTarget($school) !== $this->platformHost($school);
    }

    /**
     * Inspect DNS without persisting anything.
     *
     * @return array{ok: bool, routing: bool, ownership: bool, method: string|null, error: string|null}
     */
    public function inspect(Domain $domain): array
    {
        $school = $domain->school;
        $host = $domain->host;
        $target = strtolower(rtrim($this->cnameTarget($school), '.'));
        $ownHost = $this->platformHost($school);

        $routing = false;
        $ownershipByCname = false;
        $method = null;

        foreach ($this->dns->cname($host) as $point) {
            if ($point === $ownHost) {
                $routing = $ownershipByCname = true;
                $method = 'cname';
            } elseif ($point === $target || str_ends_with($point, '.'.$target)) {
                $routing = true;
                $method = 'cname';
            }
        }

        if (! $routing) {
            $hostIps = $this->dns->a($host);
            $ip = (string) config('schoolos.custom_domain_ip_target', '');
            $targetIps = $ip !== '' ? [$ip] : $this->dns->a($target);

            if ($hostIps !== [] && array_intersect($hostIps, $targetIps) !== []) {
                $routing = true;
                $method = 'a';
            }
        }

        $ownershipByTxt = false;
        if ($domain->verification_token) {
            $expected = self::TXT_PREFIX.$domain->verification_token;
            foreach ($this->dns->txt(self::TXT_LABEL.'.'.$host) as $value) {
                if (hash_equals($expected, trim($value, " \t\n\r\0\x0B\""))) {
                    $ownershipByTxt = true;
                    $method = $method ?? 'txt';
                    break;
                }
            }
        }

        $ownership = $ownershipByCname || $ownershipByTxt;

        $error = match (true) {
            $routing && $ownership => null,
            ! $routing && ! $ownership => 'dns_not_found',
            ! $routing => 'routing_missing',
            default => 'ownership_missing',
        };

        return [
            'ok' => $routing && $ownership,
            'routing' => $routing,
            'ownership' => $ownership,
            'method' => $method,
            'error' => $error,
        ];
    }

    /**
     * Run a verification pass and persist the outcome.
     */
    public function verify(Domain $domain): bool
    {
        if (! $domain->is_custom) {
            if (! $domain->verified_at) {
                $domain->update(['verified_at' => now()]);
            }

            return true;
        }

        if ($domain->verified_at) {
            return true;
        }

        if ($this->shouldAutoVerify()) {
            $this->markVerified($domain, 'auto');

            return true;
        }

        $result = $this->inspect($domain);

        if ($result['ok']) {
            $this->markVerified($domain, $result['method']);

            return true;
        }

        $domain->update([
            'last_checked_at' => now(),
            'verification_error' => $result['error'],
        ]);

        return false;
    }

    protected function markVerified(Domain $domain, ?string $method): void
    {
        $domain->update([
            'verified_at' => now(),
            'last_checked_at' => now(),
            'verification_error' => null,
            'verification_method' => $method,
            'ssl_active' => ! $this->isLocalBase(),
        ]);
    }

    public function makePrimary(Domain $domain): Domain
    {
        if ($domain->is_custom && ! $domain->verified_at) {
            throw new InvalidArgumentException('Verify the domain before making it your main school URL.');
        }

        return DB::transaction(function () use ($domain) {
            Domain::query()
                ->where('school_id', $domain->school_id)
                ->update(['is_primary' => false, 'primary_on_verify' => false]);

            $domain->update(['is_primary' => true]);

            return $domain->fresh();
        });
    }

    /**
     * Verify DNS then (optionally) replace the platform URL as the school's primary host.
     * When $makePrimary is null the stored "primary on verify" intent is used.
     */
    public function verifyAndActivate(Domain $domain, ?bool $makePrimary = null): Domain
    {
        $makePrimary ??= (bool) $domain->primary_on_verify;

        if (! $this->verify($domain)) {
            $domain->refresh();
            throw new InvalidArgumentException($this->failureMessage($domain));
        }

        $domain = $domain->fresh();

        return $makePrimary ? $this->makePrimary($domain) : $domain;
    }

    public function failureMessage(Domain $domain): string
    {
        $records = collect($this->dnsRecords($domain));
        $routing = $records->firstWhere('purpose', 'routing');
        $txt = $records->firstWhere('purpose', 'ownership');

        return match ($domain->verification_error) {
            'ownership_missing' => "Routing looks good, but the ownership TXT record {$txt['host']} was not found yet.",
            'routing_missing' => "We could not find the {$routing['type']} record for {$domain->host} pointing to {$routing['value']} yet.",
            default => "DNS not detected yet. Add the records shown below at your registrar, then check again (changes can take up to a few hours).",
        };
    }

    public function removeCustomDomain(Domain $domain): void
    {
        if (! $domain->is_custom) {
            throw new InvalidArgumentException('The platform subdomain cannot be removed.');
        }

        DB::transaction(function () use ($domain) {
            $school = $domain->school;
            $wasPrimary = $domain->is_primary;
            $domain->delete();

            if ($wasPrimary) {
                $fallback = $school->domains()
                    ->where('is_custom', false)
                    ->first() ?? $this->assignPlatformDomain($school);

                $this->makePrimary($fallback);
            }
        });
    }

    public function canBecomePrimary(Domain $domain): bool
    {
        return ! $domain->is_custom || $domain->verified_at !== null;
    }

    /**
     * Background sweep: verify pending claims, activate those that pass, release stale ones.
     *
     * @return array{checked: int, verified: int, expired: int}
     */
    public function verifyPending(): array
    {
        $stats = ['checked' => 0, 'verified' => 0, 'expired' => 0];

        $ttl = (int) config('schoolos.custom_domain_pending_ttl_days', 14);
        if ($ttl > 0) {
            $stats['expired'] = Domain::query()
                ->where('is_custom', true)
                ->whereNull('verified_at')
                ->where('created_at', '<', now()->subDays($ttl))
                ->delete();
        }

        Domain::query()
            ->with('school')
            ->where('is_custom', true)
            ->whereNull('verified_at')
            ->orderBy('id')
            ->each(function (Domain $domain) use (&$stats) {
                if (! $domain->school) {
                    return;
                }

                $stats['checked']++;

                try {
                    $this->verifyAndActivate($domain);
                    $stats['verified']++;
                } catch (InvalidArgumentException) {
                    // Still pending — status is recorded on the row.
                }
            });

        return $stats;
    }

    /**
     * Resolve the active school that should own this HTTP Host.
     *
     * Matches a domains row first. If the row still uses an old base domain (or is
     * missing), {slug}.{base_domain} and {slug}.{live APP_URL / central host} still
     * bind the school so platform subdomains never fall through to the SaaS landing.
     */
    public function schoolForHost(string $host): ?School
    {
        $host = $this->normalizeHost($host);
        if ($host === '' || $this->isCentralHost($host)) {
            return null;
        }

        $domain = Domain::query()->where('host', $host)->first();
        if ($domain) {
            if ($domain->is_custom && $domain->verified_at === null) {
                return null;
            }

            $school = $domain->school;

            return $school?->isActive() ? $school : null;
        }

        $slug = $this->platformSlugFromHost($host) ?? $this->fallbackSlugFromHost($host);
        if ($slug === null) {
            return null;
        }

        $school = School::query()->where('slug', $slug)->first();

        return $school?->isActive() ? $school : null;
    }

    /**
     * Host that should identify the tenant for this request.
     *
     * `$request->getHost()` is not enough behind Laravel Cloud: trusted proxies
     * prefer X-Forwarded-Host, which is often the apex (`gambiaschoolos.com`)
     * even when the browser opened `{slug}.gambiaschoolos.com`. Using that apex
     * short-circuits IdentifyTenant as "central" and renders the SaaS landing.
     *
     * Prefer any candidate that maps to a school; otherwise the first non-central
     * host; otherwise getHost().
     */
    public function requestHost(Request $request): string
    {
        $candidates = $this->requestHostCandidates($request);

        foreach ($candidates as $host) {
            if ($this->schoolForHost($host) !== null) {
                return $host;
            }
        }

        foreach ($candidates as $host) {
            if (! $this->isCentralHost($host)) {
                return $host;
            }
        }

        return $candidates[0] ?? $this->normalizeHost($request->getHost());
    }

    /**
     * Every hostname this request might be for, Host first then forwarded values.
     *
     * @return list<string>
     */
    public function requestHostCandidates(Request $request): array
    {
        $out = [];

        foreach ([
            $request->headers->get('Host'),
            $request->server->get('HTTP_HOST'),
            $request->headers->get('X-Original-Host'),
            $request->headers->get('X-Forwarded-Host'),
            $request->headers->get('Forwarded'),
            $request->getHost(),
        ] as $raw) {
            if (! is_string($raw) || $raw === '') {
                continue;
            }

            foreach ($this->hostsFromHeaderValue($raw) as $host) {
                if (! in_array($host, $out, true)) {
                    $out[] = $host;
                }
            }
        }

        return $out;
    }

    /** @return list<string> */
    protected function hostsFromHeaderValue(string $raw): array
    {
        $out = [];

        foreach (explode(',', $raw) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            if (stripos($part, 'host=') !== false && preg_match('/host="?([^";,]+)"?/i', $part, $match)) {
                $host = $this->normalizeHost($match[1]);
            } else {
                $host = $this->normalizeHost($part);
            }

            if ($host !== '' && ! str_contains($host, '=') && ! in_array($host, $out, true)) {
                $out[] = $host;
            }
        }

        return $out;
    }

    /**
     * Whether a TLS terminator may issue a certificate for this host (on-demand TLS "ask").
     */
    public function isHostServable(string $host): bool
    {
        return $this->schoolForHost($host) !== null;
    }

    /**
     * Single-label slug if this host is {slug}.{platform root}.
     */
    public function platformSlugFromHost(string $host): ?string
    {
        $host = $this->normalizeHost($host);

        foreach ($this->platformRoots() as $root) {
            $suffix = '.'.$root;
            if ($host === $root || ! str_ends_with($host, $suffix)) {
                continue;
            }

            $slug = substr($host, 0, -strlen($suffix));
            if ($slug !== '' && ! str_contains($slug, '.') && preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $slug)) {
                return $slug;
            }
        }

        return null;
    }

    /**
     * {slug}.{sld}.{tld} when env still lists an old base domain (e.g. domain
     * rows and SCHOOLOS_BASE_DOMAIN on gmschoolos.edu.gm while the browser is
     * on brightfuture.gambiaschoolos.com). Reserved labels and deeper hosts
     * (custom domains) are left to the domains table.
     */
    protected function fallbackSlugFromHost(string $host): ?string
    {
        $host = $this->normalizeHost($host);
        if (! preg_match('/^([a-z0-9](?:[a-z0-9-]*[a-z0-9])?)\.[^.]+\.[a-z]{2,}$/', $host, $match)) {
            return null;
        }

        $slug = $match[1];
        if (in_array($slug, (array) config('schoolos.reserved_subdomains', []), true)) {
            return null;
        }

        return $slug;
    }

    /** @return list<string> */
    protected function platformRoots(): array
    {
        $roots = [$this->baseDomain()];
        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        if ($appHost !== '') {
            $roots[] = $appHost;
        }

        foreach ((array) config('schoolos.central_domains', []) as $central) {
            $central = strtolower(trim((string) $central));
            if ($central !== '') {
                $roots[] = $central;
            }
        }

        return array_values(array_unique(array_filter($roots)));
    }

    public function isCentralHost(string $host): bool
    {
        $host = $this->normalizeHost($host);
        if ($host === '') {
            return false;
        }

        $roots = $this->platformRoots();
        if (in_array($host, $roots, true)) {
            return true;
        }

        return str_starts_with($host, 'www.') && in_array(substr($host, 4), $roots, true);
    }

    /* ------------------------------------------------------------------ */
    /* URLs                                                                 */
    /* ------------------------------------------------------------------ */

    /**
     * Absolute URLs for admin / public surfaces.
     *
     * @return array{
     *   primary_host: string|null,
     *   platform_host: string,
     *   primary_url: string,
     *   platform_url: string,
     *   path_url: string,
     *   admin_url: string,
     *   uses_path_preview: bool,
     *   primary_is_custom: bool,
     *   primary_ssl_active: bool
     * }
     */
    public function urlsFor(School $school): array
    {
        $school->loadMissing('domains');
        $platformHost = $this->platformHost($school);
        $primary = $school->domains->firstWhere('is_primary', true)
            ?? $school->domains->firstWhere('host', $platformHost);

        $primaryHost = $primary?->host ?? $platformHost;
        $pathUrl = route('school.site', ['school_slug' => $school->slug]);
        $usesPathPreview = $this->preferPathPreview();

        // Canonical platform URL is always the dedicated subdomain (path fallback only on localhost base).
        $platformUrl = $this->isLocalBase()
            ? $pathUrl
            : $this->absoluteUrl($platformHost, '/');

        $primaryIsCustom = (bool) ($primary?->is_custom);
        $primaryUrl = $primaryIsCustom
            ? $this->absoluteUrl($primaryHost, '/', $primary->ssl_active ? true : null)
            : ($usesPathPreview ? $pathUrl : $platformUrl);

        // Admin stays on a host that is guaranteed to serve TLS until the custom certificate is live.
        $adminHost = ($primaryIsCustom && $primary->ssl_active) ? $primaryHost : $platformHost;

        return [
            'primary_host' => $primaryHost,
            'platform_host' => $platformHost,
            'primary_url' => $primaryUrl,
            'platform_url' => $platformUrl,
            'path_url' => $pathUrl,
            'admin_url' => $usesPathPreview
                ? route('admin.dashboard')
                : $this->absoluteUrl($adminHost, '/admin'),
            'uses_path_preview' => $usesPathPreview,
            'primary_is_custom' => $primaryIsCustom,
            'primary_ssl_active' => (bool) ($primary?->ssl_active),
        ];
    }

    public function absoluteUrl(string $host, string $path = '/', ?bool $secure = null): string
    {
        $scheme = $secure === true
            ? 'https'
            : (request()?->getScheme() ?: (parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https'));
        $port = request()?->getPort();
        $portSuffix = $secure !== true && $port && ! in_array((int) $port, [80, 443], true) ? ':'.$port : '';
        $normalizedPath = str_starts_with($path, '/') ? $path : '/'.$path;

        return $scheme.'://'.$host.$portSuffix.$normalizedPath;
    }

    public function preferPathPreview(): bool
    {
        return $this->isLocalBase() || ! config('schoolos.redirect_to_tenant_after_register');
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                              */
    /* ------------------------------------------------------------------ */

    public function isApex(string $host): bool
    {
        $labels = explode('.', $host);

        return count($labels) <= $this->registrableLabelCount($labels);
    }

    /**
     * Record name relative to the registrable domain ("@" for the apex, "www" for www.school.gm).
     */
    public function relativeName(string $host): string
    {
        $labels = explode('.', $host);
        $keep = count($labels) - $this->registrableLabelCount($labels);

        return $keep <= 0 ? '@' : implode('.', array_slice($labels, 0, $keep));
    }

    /** @param  list<string>  $labels */
    protected function registrableLabelCount(array $labels): int
    {
        $n = count($labels);

        if ($n >= 3) {
            $tld = $labels[$n - 1];
            $sld = $labels[$n - 2];
            if (strlen($tld) === 2 && in_array($sld, self::MULTIPART_SLDS, true)) {
                return 3;
            }
        }

        return 2;
    }

    protected function shouldAutoVerify(): bool
    {
        $configured = config('schoolos.custom_domain_auto_verify');
        if ($configured !== null) {
            return (bool) $configured;
        }

        return app()->environment(['local', 'testing']);
    }

    protected function isLocalBase(): bool
    {
        return in_array($this->baseDomain(), ['localhost', '127.0.0.1'], true);
    }
}
