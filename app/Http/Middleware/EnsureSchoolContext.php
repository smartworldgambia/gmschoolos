<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use App\Services\Billing\SubscriptionLifecycle;
use App\Services\School\SchoolAccessGate;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds and validates the tenant for every authenticated school route. FAIL-CLOSED.
 *
 *  - school users must have a school, and (on a school host) it must be THE host's school;
 *  - users without a school that are not platform staff get nothing;
 *  - platform staff do not browse tenant routes (they use /platform); only a few shared
 *    account routes are allowed;
 *  - suspended / expired schools are blocked (data is preserved).
 */
class EnsureSchoolContext
{
    /** Routes platform staff may use outside /platform (account self-service only). */
    protected const PLATFORM_ALLOWED = ['dashboard', 'profile.*', 'notifications.*'];

    public function __construct(
        protected TenantContext $tenant,
        protected SchoolAccessGate $gate,
        protected SubscriptionLifecycle $lifecycle,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Platform staff: no tenant, no tenant data.
        if ($user->isPlatformStaff()) {
            if ($request->routeIs(...self::PLATFORM_ALLOWED)) {
                return $next($request);
            }

            $this->deny($request, 'platform_user_on_tenant_route');
            abort(403, 'Platform accounts manage schools from the platform console.');
        }

        if (! $user->school_id) {
            $this->deny($request, 'user_without_school');
            abort(403, 'Your account is not linked to a school.');
        }

        $school = $user->school;
        if (! $school) {
            $this->deny($request, 'user_school_missing');
            abort(403, 'Your account is not linked to a valid school.');
        }

        // Host-bound tenant (school subdomain / custom domain) must match the user's school.
        if ($this->tenant->id() && (int) $this->tenant->id() !== (int) $school->id) {
            $this->deny($request, 'cross_school_host');
            abort(403, 'You do not have access to this school.');
        }

        $this->tenant->set($school);

        // Suspension / trial / subscription enforcement.
        if (! $request->routeIs(...config('schoolos.access.always_allowed_routes', []))) {
            $reason = $this->gate->blockReason($school);
            if ($reason !== null) {
                $this->deny($request, 'school_access_blocked:'.$reason);

                return $this->blockedResponse($request, $reason);
            }
        }

        return $next($request);
    }

    protected function blockedResponse(Request $request, string $reason): Response
    {
        if ($request->header('X-Inertia')) {
            return Inertia::location(route('dashboard'));
        }

        return Inertia::render('Errors/AccessBlocked', [
            'reason' => $reason,
            'school' => ['name' => $this->tenant->school()?->name],
            // Outstanding annual amount + a payment link (only for users who may manage billing).
            'billing' => $this->billingSummary($request),
            'support' => [
                'email' => config('schoolos.marketing.support_email'),
                'phone' => config('schoolos.marketing.phone'),
            ],
        ])->toResponse($request)->setStatusCode(403);
    }

    /** @return array<string, mixed>|null */
    protected function billingSummary(Request $request): ?array
    {
        $school = $this->tenant->school();
        $state = $school ? $this->lifecycle->state($school) : null;

        if (! $state) {
            return null;
        }

        return [
            'amount_due_label' => $state['outstanding_label'],
            'price_configured' => $state['price_configured'],
            'school_type' => $state['school_type'],
            'ended_on' => $state['ends_at'],
            'pay_url' => $request->user()?->can('subscriptions.view') ? route('billing.index') : null,
        ];
    }

protected function deny(Request $request, string $reason): void
    {
        $context = [
            'reason' => $reason,
            'user_id' => $request->user()?->id,
            'school_id' => $request->user()?->school_id,
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
        ];

        Log::warning('security.access_denied', $context);

        // Persist for the school audit trail when a school is known.
        if ($request->user()?->school_id) {
            try {
                $this->tenant->runForSchool($request->user()->school, fn () => ActivityLog::query()->create([
                    'school_id' => $request->user()->school_id,
                    'user_id' => $request->user()->id,
                    'event' => 'security.access_denied',
                    'description' => $reason,
                    'properties' => $context,
                ]));
            } catch (\Throwable) {
                // Auditing must never break the denial itself.
            }
        }
    }
}
