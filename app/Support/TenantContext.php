<?php

namespace App\Support;

use App\Models\School;
use Closure;
use RuntimeException;

/**
 * Holds the school the current request/job is acting for.
 *
 * Tenant-owned models are FAIL-CLOSED: with no school bound and platform mode off,
 * {@see \App\Models\Scopes\SchoolScope} returns no rows. Cross-tenant work must be requested
 * explicitly through {@see self::runAsPlatform()} or {@see self::runForSchool()}.
 */
class TenantContext
{
    protected ?School $school = null;

    /** Depth counter so nested runAsPlatform() calls unwind correctly. */
    protected int $platformDepth = 0;

    public function set(?School $school): void
    {
        $this->school = $school;
    }

    public function clear(): void
    {
        $this->school = null;
        $this->platformDepth = 0;
    }

    public function school(): ?School
    {
        return $this->school;
    }

    public function id(): ?int
    {
        return $this->school?->id;
    }

    public function check(): School
    {
        if (! $this->school) {
            throw new RuntimeException('No tenant context is set for this request.');
        }

        return $this->school;
    }

    public function has(): bool
    {
        return $this->school !== null;
    }

    /**
     * Explicit platform-level (cross-tenant) mode. Only platform-authorised code paths
     * (platform console, scheduled commands, provisioning) may use this.
     */
    public function isPlatformMode(): bool
    {
        return $this->platformDepth > 0;
    }

    /**
     * Permanently enter platform mode for the rest of this process. Reserved for trusted operator
     * processes (artisan commands). Never call this from an HTTP request.
     */
    public function enterPlatformMode(): void
    {
        $this->platformDepth++;
    }

    /**
     * Run a callback with tenant scoping lifted for the duration of the callback only.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function runAsPlatform(Closure $callback): mixed
    {
        $this->platformDepth++;

        try {
            return $callback();
        } finally {
            $this->platformDepth--;
        }
    }

    /**
     * Run a callback bound to one school (jobs, commands) and restore the previous context after.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function runForSchool(School $school, Closure $callback): mixed
    {
        $previousSchool = $this->school;
        $previousDepth = $this->platformDepth;

        $this->school = $school;
        $this->platformDepth = 0;

        try {
            return $callback();
        } finally {
            $this->school = $previousSchool;
            $this->platformDepth = $previousDepth;
        }
    }

    public function cacheKey(string $key): string
    {
        $id = $this->id() ?? 'central';

        return "school:{$id}:{$key}";
    }
}
