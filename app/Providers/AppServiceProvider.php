<?php

namespace App\Providers;

use App\Services\Integrations\IntegrationSettings;
use App\Services\Scale\QueryProfiler;
use App\Support\TenantContext;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Event;
use App\Models\BillingNotificationLog;
use App\Notifications\SubscriptionLifecycleNotification;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Vite;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->singleton(QueryProfiler::class);
        $this->app->singleton(IntegrationSettings::class);
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised()
            : Password::min(8));

        $this->configureTenancyForNonHttpProcesses();
        $this->trackBillingNotificationDelivery();

        // Phase 8: delivery history for in-app and e-mail notifications.
        Event::listen(NotificationSent::class, [\App\Listeners\RecordNotificationDelivery::class, 'onSent']);
        Event::listen(NotificationFailed::class, [\App\Listeners\RecordNotificationDelivery::class, 'onFailed']);
        Event::listen(JobFailed::class, [\App\Listeners\RecordNotificationDelivery::class, 'onJobFailed']);

        // Platform-console mail / SMS credentials: only ACTIVE (tested) values are overlaid on the server defaults, once per
        // request and again before every queued job so a long-running worker never keeps an old transport.
        $this->app->booted(fn () => $this->app->make(IntegrationSettings::class)->apply());
        Event::listen(JobProcessing::class, fn () => $this->app->make(IntegrationSettings::class)->apply());

        $profiler = $this->app->make(QueryProfiler::class);

        if ($profiler->enabled()) {
            Event::listen(QueryExecuted::class, function (QueryExecuted $query) use ($profiler) {
                $profiler->record($query->time, $query->sql, $query->bindings);
            });
        }
    }

    /**
     * Tenancy fails closed, so non-HTTP processes need an explicit stance:
     *  - artisan commands (migrate, seed, schoolos:*, scheduler) are TRUSTED operator processes and run
     *    in platform mode; unit tests keep strict fail-closed behaviour;
     *  - queue workers stay fail-closed: each job must bind its own school, and the context is wiped
     *    between jobs so one tenant can never leak into the next.
     */
    protected function configureTenancyForNonHttpProcesses(): void
    {
        $tenant = $this->app->make(TenantContext::class);

        $argv = implode(' ', $_SERVER['argv'] ?? []);
        $isQueueWorker = (bool) preg_match('/\bqueue:(work|listen)\b/', $argv);

        if ($this->app->runningInConsole() && ! $this->app->runningUnitTests() && ! $isQueueWorker) {
            $tenant->enterPlatformMode();
        }

        Event::listen(JobProcessing::class, fn () => $tenant->set(null));
        Event::listen(JobProcessed::class, fn () => $tenant->set(null));
        Event::listen(JobFailed::class, fn () => $tenant->set(null));
    }

    /**
     * Billing reminders are only marked "sent" when the mail channel reports success, and "failed" with the
     * error otherwise - queued is not delivered. (Mail driver `log` counts as sent: see known limitations.)
     */
    protected function trackBillingNotificationDelivery(): void
    {
        Event::listen(NotificationSent::class, function (NotificationSent $event) {
            if ($event->notification instanceof SubscriptionLifecycleNotification
                && $event->channel === 'mail' && $event->notification->logId) {
                BillingNotificationLog::withoutGlobalScopes()->whereKey($event->notification->logId)
                    ->update(['status' => 'sent', 'sent_at' => now(), 'error' => null]);
            }
        });

        Event::listen(NotificationFailed::class, function (NotificationFailed $event) {
            if ($event->notification instanceof SubscriptionLifecycleNotification && $event->notification->logId) {
                BillingNotificationLog::withoutGlobalScopes()->whereKey($event->notification->logId)
                    ->update(['status' => 'failed', 'error' => mb_substr(json_encode($event->data), 0, 500)]);
            }
        });
    }
}