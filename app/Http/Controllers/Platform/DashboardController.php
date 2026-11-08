<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Concerns\SharesPlatformShell;
use App\Http\Controllers\Controller;
use App\Models\BackupValidationRun;
use App\Models\PlatformAlert;
use App\Models\School;
use App\Models\SchoolSubscription;
use App\Models\UsageSnapshot;
use App\Services\Platform\HealthMonitor;
use App\Services\School\SchoolDomainService;
use App\Support\Money;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use SharesPlatformShell;

    public function __invoke(HealthMonitor $monitor): Response
    {
        $schools = School::query()->count();
        $active = School::query()->where('status', 'active')->whereNull('suspended_at')->count();
        $suspended = School::query()->whereNotNull('suspended_at')->count();
        $openAlerts = PlatformAlert::query()->open()->count();
        $annualRevenue = (int) \App\Models\BillingPayment::query()
            ->where('status', 'verified')
            ->whereBetween('paid_at', [now()->startOfYear(), now()->endOfYear()])
            ->where('currency', config('schoolos.billing.currency', 'GMD'))
            ->sum('total_amount');
        $onTrial = SchoolSubscription::query()->where('status', 'trial')->where('ends_at', '>', now())->count();
        $paidActive = SchoolSubscription::query()->where('status', 'active')->where('ends_at', '>', now())->count();

        $recentSchools = School::query()
            ->with(['subscription', 'primaryDomain'])
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (School $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'slug' => $s->slug,
                'status' => $s->suspended_at ? 'suspended' : $s->status,
                'school_type' => $s->subscription?->school_type,
                'subscription_status' => $s->subscription ? app(\App\Services\Billing\SubscriptionLifecycle::class)->effectiveStatus($s->subscription) : null,
                'domain' => $s->primaryDomain?->host,
                'public_url' => app(SchoolDomainService::class)->urlsFor($s)['primary_url'],
                'created_at' => $s->created_at?->toDateString(),
            ]);

        $latestUsage = UsageSnapshot::query()
            ->whereDate('captured_on', now()->toDateString())
            ->sum('students_count');

        $lastBackup = BackupValidationRun::query()->latest()->first();

        return Inertia::render('Platform/Dashboard', array_merge($this->platformShellProps(), [
            'stats' => [
                'schools' => $schools,
                'active' => $active,
                'suspended' => $suspended,
                'open_alerts' => $openAlerts,
                'annual_revenue' => Money::formatCurrency($annualRevenue),
                'on_trial' => $onTrial,
                'paid_active' => $paidActive,
                'students_today' => (int) $latestUsage,
            ],
            'health' => $monitor->snapshot(),
            'recentSchools' => $recentSchools,
            'lastBackup' => $lastBackup ? [
                'id' => $lastBackup->id,
                'status' => $lastBackup->status,
                'trigger' => $lastBackup->trigger,
                'duration_ms' => $lastBackup->duration_ms,
                'created_at' => $lastBackup->created_at?->diffForHumans(),
            ] : null,
        ]));
    }
}
