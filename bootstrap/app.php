<?php

use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureSchoolContext;
use App\Http\Middleware\EnsureTwoFactorVerified;
use App\Http\Middleware\ProtectMetrics;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\RecordTenantUsage;
use App\Http\Middleware\SecurityHeaders;
use App\Services\Ops\ErrorReporter;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Controllers\Platform\TlsAskController;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Stateless (no session/cookies): called by the TLS edge, not by browsers.
            Route::middleware('throttle:120,1')
                ->get('/_internal/domains/allow', TlsAskController::class)
                ->name('internal.domains.allow');
            // Payment-provider webhooks: public + stateless (no session, no CSRF). Authenticity = provider signature
            // verified over the raw body; processing is idempotent (billing_webhook_events).
            Route::middleware('throttle:600,1')
                ->post('/webhooks/{provider}', \App\Http\Controllers\Billing\WebhookController::class)
                ->where('provider', 'stripe|wave')
                ->name('webhooks.billing');
            // SMS delivery reports (Phase 8): signature / path-secret checked inside the controller.
            Route::middleware('throttle:600,1')->group(function (): void {
                Route::post('/webhooks/sms/twilio', [\App\Http\Controllers\Sms\SmsWebhookController::class, 'twilio'])->name('webhooks.sms.twilio');
                Route::post('/webhooks/sms/africastalking/{secret}', [\App\Http\Controllers\Sms\SmsWebhookController::class, 'africastalking'])->name('webhooks.sms.at');
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust Cloud/edge for client IP, scheme and port. Do not trust X-Forwarded-Host:
        // Laravel Cloud often sets that to the apex, which made getHost() look central and
        // served the SaaS landing on {slug}.gambiaschoolos.com. Tenant ID uses Host plus
        // forwarded candidates in SchoolDomainService::requestHost() instead.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX
        );
        // Official examination index numbers are stored exactly as supplied: never silently trimmed.
        $middleware->trimStrings(except: ['official_index_number']);
        $middleware->web(append: [
            \App\Http\Middleware\EnsureAccountActive::class,
            IdentifyTenant::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            RecordTenantUsage::class,
            SecurityHeaders::class,
            EnsureTwoFactorVerified::class,
        ]);

        // Tenant must be bound BEFORE route-model binding, otherwise the fail-closed SchoolScope hides every
        // tenant row and bound models 404. Order: Authenticate (default list) -> tenant -> school context -> bindings.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: IdentifyTenant::class);
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: EnsureSchoolContext::class);
        // The 2FA gate must still answer (redirect to the challenge) before tenant checks can refuse the request.
        $middleware->prependToPriorityList(before: IdentifyTenant::class, prepend: EnsureTwoFactorVerified::class);

        $middleware->alias([
            'identify.tenant' => IdentifyTenant::class,
            'school.context' => EnsureSchoolContext::class,
            'platform.admin' => EnsurePlatformAdmin::class,
            'two-factor' => EnsureTwoFactorVerified::class,
            'metrics.token' => ProtectMetrics::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->reportable(function (\Throwable $e): void {
            try {
                app(ErrorReporter::class)->report($e);
            } catch (\Throwable) {
                // Never break Laravel's exception pipeline.
            }
        });
    })->create();
