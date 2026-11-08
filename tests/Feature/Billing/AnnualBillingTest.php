<?php

namespace Tests\Feature\Billing;

use App\Models\BillingEvent;
use App\Models\BillingNotificationLog;
use App\Models\BillingPayment;
use App\Models\School;
use App\Models\SchoolSubscription;
use App\Models\SchoolTypePrice;
use App\Models\Student;
use App\Models\User;
use App\Services\Billing\PricingService;
use App\Services\Billing\SubscriptionLifecycle;
use App\Support\TenantContext;
use Database\Seeders\RolesAndPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Tests\TestCase;

/** Phase 2 acceptance: the complete annual subscription lifecycle. */
class AnnualBillingTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPlansSeeder::class);

        // Deterministic test price (the seeded ones are placeholders).
        SchoolTypePrice::query()->where('school_type', 'primary')->delete();
        app(PricingService::class)->set('primary', 20000, 'GMD', '2000-01-01', 0.0, 'test price');

        $this->school = School::query()->create([
            'name' => 'Billing School', 'slug' => 'billing-school', 'status' => 'active', 'category' => 'Primary',
        ]);
        $this->admin = $this->userFor($this->school, 'school_admin');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function userFor(?School $school, ?string $role): User
    {
        $user = User::factory()->create(['school_id' => $school?->id, 'email_verified_at' => now()]);
        if ($role) {
            $user->assignRole($role);
        }

        return $user;
    }

    protected function as(User $user): static
    {
        app(TenantContext::class)->clear();

        return $this->actingAs($user);
    }

    protected function lifecycle(): SubscriptionLifecycle
    {
        return app(SubscriptionLifecycle::class);
    }

    protected function sub(?School $school = null): SchoolSubscription
    {
        return app(TenantContext::class)->runAsPlatform(fn () => SchoolSubscription::query()
            ->where('school_id', ($school ?? $this->school)->id)->firstOrFail());
    }

    protected function pay(array $extra = [], ?School $school = null): BillingPayment
    {
        return $this->lifecycle()->recordVerifiedPayment($school ?? $this->school, array_merge(['method' => 'cash'], $extra));
    }

    // ------------------------------------------------------------------ pricing

    public function test_each_school_type_has_one_current_price_with_append_only_history(): void
    {
        $pricing = app(PricingService::class);
        $this->assertSame(20000, $pricing->current('primary')->amount);

        $pricing->set('primary', 25000, 'GMD', now()->toDateString(), null, 'raise');
        $this->assertSame(25000, $pricing->current('primary')->amount);

        $history = collect($pricing->history('primary'));
        $this->assertGreaterThanOrEqual(2, $history->count());
        $this->assertSame(1, $history->where('status', 'current')->count());
        $this->assertTrue($history->where('amount', 20000)->every(fn ($r) => $r['status'] === 'superseded'));
    }

    public function test_future_dated_prices_are_scheduled_not_current(): void
    {
        $pricing = app(PricingService::class);
        $pricing->set('primary', 30000, 'GMD', now()->addMonth()->toDateString());

        $this->assertSame(20000, $pricing->current('primary')->amount);
        $this->assertTrue(collect($pricing->history('primary'))->contains(fn ($r) => $r['amount'] === 30000 && $r['status'] === 'scheduled'));
    }

    public function test_invalid_prices_are_rejected(): void
    {
        $pricing = app(PricingService::class);
        foreach ([['nonsense', 100, 'GMD'], ['primary', 0, 'GMD'], ['primary', 100, 'gambia']] as [$type, $amount, $currency]) {
            try {
                $pricing->set($type, $amount, $currency);
                $this->fail("Accepted invalid price {$type}/{$amount}/{$currency}");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_every_school_type_has_a_seeded_placeholder_price_that_is_editable(): void
    {
        $this->assertGreaterThanOrEqual(7, SchoolTypePrice::query()->distinct('school_type')->count('school_type'));
    }

    // ------------------------------------------------------------------ trial

    public function test_a_30_day_trial_starts_automatically_and_needs_no_payment(): void
    {
        $subscription = $this->lifecycle()->startTrial($this->school);

        $this->assertSame('trial', $subscription->status);
        $this->assertSame('not_required_during_trial', $subscription->payment_status);
        $this->assertSame('annual', $subscription->billing_cycle);
        $this->assertSame(30, (int) now()->startOfDay()->diffInDays($subscription->ends_at->copy()->startOfDay()));
        $this->assertSame('primary', $subscription->school_type);
        $this->assertSame(0, BillingPayment::withoutGlobalScopes()->count());

        $state = $this->lifecycle()->state($this->school);
        $this->assertSame('trial', $state['status']);
        $this->assertSame(30, $state['days_remaining']);
    }

    public function test_a_school_can_only_ever_have_one_subscription(): void
    {
        $first = $this->lifecycle()->startTrial($this->school);
        $second = $this->lifecycle()->startTrial($this->school);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, SchoolSubscription::withoutGlobalScopes()->where('school_id', $this->school->id)->count());
    }

    public function test_registration_starts_the_trial_with_the_type_price_and_no_plan_choice(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $this->post(route('register-school.store'), [
            'school_name' => 'Fresh Start', 'category' => 'Primary', 'region' => 'Banjul', 'subdomain' => 'freshstart',
            'school_email' => 'info@freshstart.test', 'school_phone' => '+220 4400000', 'address' => '12 Test Road', 'district' => 'Banjul Central', 'admin_phone' => '+220 1111111', 'terms' => '1',
            'academic_year_name' => '2025/2026', 'term_name' => 'Term 1',
            'admin_name' => 'Head', 'admin_email' => 'head@freshstart.test',
            'password' => 'password', 'password_confirmation' => 'password',
        ])->assertRedirect();

        $school = School::query()->where('slug', 'freshstart')->firstOrFail();
        $sub = $this->sub($school);
        $this->assertSame('trial', $sub->status);
        $this->assertSame('primary', $sub->school_type);
        $this->assertSame(20000, $sub->annual_amount);
    }

    // ------------------------------------------------------------------ annual only

    public function test_monthly_billing_cannot_be_stored(): void
    {
        $sub = $this->lifecycle()->startTrial($this->school);
        $sub->forceFill(['billing_cycle' => 'monthly'])->save();

        $this->assertSame('annual', $sub->fresh()->billing_cycle);
        $this->assertSame('annual', app(TenantContext::class)->runAsPlatform(fn () => SchoolSubscription::query()->find($sub->id))->billing_cycle);
    }

    public function test_unknown_subscription_statuses_are_rejected(): void
    {
        $sub = $this->lifecycle()->startTrial($this->school);
        $this->expectException(InvalidArgumentException::class);
        $sub->forceFill(['status' => 'past_due'])->save();
    }

    public function test_there_is_no_monthly_route_or_pricing_surface(): void
    {
        foreach (app('router')->getRoutes() as $route) {
            $this->assertStringNotContainsString('monthly', strtolower($route->uri().' '.$route->getName()));
        }
    }

    // ------------------------------------------------------------------ payments

    public function test_only_a_full_verified_payment_activates_a_trial_for_twelve_months(): void
    {
        Carbon::setTestNow('2026-03-10 09:00:00');
        $this->lifecycle()->startTrial($this->school);

        $payment = $this->pay(['transaction_reference' => 'TX-1']);
        $sub = $this->sub();

        $this->assertSame('verified', $payment->status);
        $this->assertSame('active', $sub->status);
        $this->assertSame('paid', $sub->payment_status);
        $this->assertSame('2027-03-10', $sub->ends_at->toDateString());
        $this->assertNotEmpty($payment->receipt_number);
        $this->assertSame(20000, $payment->total_amount);
    }

    public function test_partial_excess_and_duplicate_payments_do_not_activate(): void
    {
        $this->lifecycle()->startTrial($this->school);

        foreach ([10000, 25000] as $amount) {
            try {
                $this->pay(['amount' => $amount]);
                $this->fail('A non-full payment was accepted.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame('trial', $this->sub()->status);
        $this->assertSame(0, BillingPayment::withoutGlobalScopes()->count());

        $this->pay(['transaction_reference' => 'DUP-1']);
        $this->expectException(InvalidArgumentException::class);
        $this->pay(['transaction_reference' => 'DUP-1']);
    }

    public function test_a_period_started_on_leap_day_is_twelve_calendar_months(): void
    {
        Carbon::setTestNow('2028-02-29 10:00:00');
        $this->lifecycle()->startTrial($this->school);
        $this->pay();

        $this->assertSame('2029-02-28', $this->sub()->ends_at->toDateString());
    }

    public function test_early_renewal_extends_from_the_current_period_end(): void
    {
        Carbon::setTestNow('2026-01-15 08:00:00');
        $this->lifecycle()->startTrial($this->school);
        $this->pay();
        $this->assertSame('2027-01-15', $this->sub()->ends_at->toDateString());

        Carbon::setTestNow('2026-12-20 08:00:00');
        $this->pay();
        $this->assertSame('2028-01-15', $this->sub()->ends_at->toDateString());
        $this->assertSame(2, BillingPayment::withoutGlobalScopes()->count());
        $this->assertTrue(BillingEvent::withoutGlobalScopes()->where('type', 'renewed')->exists());
    }

    public function test_a_price_change_never_alters_already_paid_periods(): void
    {
        $this->lifecycle()->startTrial($this->school);
        $payment = $this->pay();

        app(PricingService::class)->set('primary', 99000, 'GMD');

        $this->assertSame(20000, $payment->fresh()->total_amount);
        $this->assertSame('active', $this->sub()->status);
    }

    public function test_payment_without_a_configured_price_is_refused(): void
    {
        $school = School::query()->create(['name' => 'No Price', 'slug' => 'no-price', 'status' => 'active', 'category' => 'Other']);
        SchoolTypePrice::query()->where('school_type', 'other')->delete();
        $this->lifecycle()->startTrial($school);

        $this->expectException(InvalidArgumentException::class);
        $this->pay([], $school);
    }

    // ------------------------------------------------------------------ expiry, suspension, reactivation

    public function test_trial_expiry_then_suspension_preserves_all_school_data_and_reactivates_on_payment(): void
    {
        Carbon::setTestNow('2026-05-01 06:00:00');
        $this->lifecycle()->startTrial($this->school);
        app(TenantContext::class)->runForSchool($this->school, fn () => Student::query()->create([
            'school_id' => $this->school->id, 'student_number' => 'B-1', 'first_name' => 'Keep', 'last_name' => 'Me', 'status' => 'active',
        ]));

        Carbon::setTestNow('2026-06-01 06:00:00'); // day 31
        $first = $this->lifecycle()->processDue();
        $this->assertSame(1, $first['expired']);
        $this->assertSame(1, $first['suspended']); // grace = 0 by default
        $this->assertSame('suspended', $this->sub()->status);

        $this->as($this->admin)->get(route('admin.dashboard'))->assertForbidden();
        $this->as($this->admin)->get(route('billing.index'))->assertOk(); // can still see what is owed

        $this->assertSame(1, app(TenantContext::class)->runAsPlatform(fn () => Student::query()->count()));

        $this->pay(['transaction_reference' => 'RE-1']);
        $this->assertSame('active', $this->sub()->status);
        $this->assertTrue(BillingEvent::withoutGlobalScopes()->where('type', 'reactivated')->exists());
        $this->as($this->admin)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_paid_subscription_expiry_blocks_without_deleting_anything(): void
    {
        Carbon::setTestNow('2026-01-01 08:00:00');
        $this->lifecycle()->startTrial($this->school);
        $this->pay();

        Carbon::setTestNow('2027-01-02 08:00:00');
        $this->lifecycle()->processDue();

        $this->assertSame('suspended', $this->sub()->status);
        $this->assertSame(1, BillingPayment::withoutGlobalScopes()->count());
        $this->assertNotNull(School::query()->find($this->school->id));
    }

    public function test_grace_days_delay_suspension_but_not_expiry(): void
    {
        config(['schoolos.access.grace_days' => 3]);
        Carbon::setTestNow('2026-05-01 06:00:00');
        $this->lifecycle()->startTrial($this->school);

        Carbon::setTestNow('2026-06-01 06:00:00');
        $result = $this->lifecycle()->processDue();
        $this->assertSame(1, $result['expired']);
        $this->assertSame(0, $result['suspended']);
        $this->assertSame('expired', $this->sub()->status);

        Carbon::setTestNow('2026-06-05 06:00:00');
        $this->assertSame(1, $this->lifecycle()->processDue()['suspended']);
    }

    public function test_the_billing_process_is_idempotent_and_dry_run_changes_nothing(): void
    {
        Carbon::setTestNow('2026-05-01 06:00:00');
        $this->lifecycle()->startTrial($this->school);
        Carbon::setTestNow('2026-06-02 06:00:00');

        $dry = $this->lifecycle()->processDue(dryRun: true);
        $this->assertSame(1, $dry['expired']);
        $this->assertSame('trial', $this->sub()->status);

        $this->lifecycle()->processDue();
        $again = $this->lifecycle()->processDue();
        $this->assertSame(['expired' => 0, 'suspended' => 0, 'reminders' => 0], $again);
    }

    public function test_the_billing_command_runs(): void
    {
        $this->lifecycle()->startTrial($this->school);
        $this->artisan('schoolos:billing:process --dry-run')->assertSuccessful();
    }

    // ------------------------------------------------------------------ reminders

    public function test_reminders_are_sent_once_per_offset_and_delivery_is_tracked(): void
    {
        Carbon::setTestNow('2026-05-01 06:00:00');
        $this->lifecycle()->startTrial($this->school);

        Carbon::setTestNow('2026-05-25 06:00:00'); // 6 days left -> the 7-day reminder
        $this->assertSame(1, $this->lifecycle()->processDue()['reminders']);
        $this->assertSame(0, $this->lifecycle()->processDue()['reminders']); // no duplicate

        $log = BillingNotificationLog::withoutGlobalScopes()->where('kind', 'trial_7')->firstOrFail();
        $this->assertSame('sent', $log->status); // set from NotificationSent, not from queueing
        $this->assertNotNull($log->sent_at);

        Carbon::setTestNow('2026-05-29 06:00:00'); // 2 days left -> next offset (3), once
        $this->assertSame(1, $this->lifecycle()->processDue()['reminders']);
        $this->assertSame(2, BillingNotificationLog::withoutGlobalScopes()->count());
    }

    public function test_a_failed_delivery_is_recorded_as_failed(): void
    {
        Carbon::setTestNow('2026-05-01 06:00:00');
        $this->lifecycle()->startTrial($this->school);
        Carbon::setTestNow('2026-05-25 06:00:00');
        $this->lifecycle()->processDue();

        $log = BillingNotificationLog::withoutGlobalScopes()->firstOrFail();
        $notification = new \App\Notifications\SubscriptionLifecycleNotification('trial_7', [], $log->id);
        Event::dispatch(new NotificationFailed($this->admin, $notification, 'mail', ['message' => 'smtp down']));

        $this->assertSame('failed', $log->fresh()->status);
    }

    public function test_reminders_go_to_verified_billing_administrators_only(): void
    {
        $unverified = User::factory()->create(['school_id' => $this->school->id, 'email_verified_at' => null]);
        $unverified->assignRole('school_admin');

        \Illuminate\Support\Facades\Notification::fake();
        Carbon::setTestNow('2026-05-01 06:00:00');
        $this->lifecycle()->startTrial($this->school);
        Carbon::setTestNow('2026-05-25 06:00:00');
        $this->lifecycle()->processDue();

        \Illuminate\Support\Facades\Notification::assertSentTo($this->admin, \App\Notifications\SubscriptionLifecycleNotification::class);
        \Illuminate\Support\Facades\Notification::assertNotSentTo($unverified, \App\Notifications\SubscriptionLifecycleNotification::class);
    }

    // ------------------------------------------------------------------ school billing dashboard

    public function test_school_billing_page_shows_countdown_amount_and_history(): void
    {
        $this->lifecycle()->startTrial($this->school);

        $this->as($this->admin)->get(route('billing.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Billing/Index')
                ->where('billing.status', 'trial')
                ->where('billing.payment_status', 'not_required_during_trial')
                ->where('billing.billing_cycle', 'annual')
                ->where('billing.days_remaining', 30)
                ->where('billing.annual_total', 20000)
                ->has('payments', 0)
                ->has('methods'));

        $this->pay();
        $this->as($this->admin)->get(route('billing.index'))
            ->assertInertia(fn ($page) => $page->where('billing.status', 'active')->has('payments', 1));
    }

    public function test_teachers_cannot_open_the_school_billing_page_but_finance_can(): void
    {
        $this->lifecycle()->startTrial($this->school);

        $this->as($this->userFor($this->school, 'teacher'))->get(route('billing.index'))->assertForbidden();
        $this->as($this->userFor($this->school, 'finance_officer'))->get(route('billing.index'))->assertOk();
    }

    public function test_a_school_cannot_see_another_schools_payments(): void
    {
        $other = School::query()->create(['name' => 'Other', 'slug' => 'other', 'status' => 'active', 'category' => 'Primary']);
        $this->lifecycle()->startTrial($this->school);
        $this->lifecycle()->startTrial($other);
        $this->pay([], $other);

        $this->as($this->admin)->get(route('billing.index'))
            ->assertInertia(fn ($page) => $page->has('payments', 0));
    }

    // ------------------------------------------------------------------ platform dashboards / permissions

    public function test_platform_billing_dashboard_lists_statuses_and_filters(): void
    {
        $this->lifecycle()->startTrial($this->school);
        $platform = $this->userFor(null, 'platform_admin');

        $this->as($platform)->get(route('platform.billing.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Platform/Billing/Index')
                ->where('stats.trial', 1)
                ->has('schools.data', 1)
                ->has('upcomingTrials'));

        $this->as($platform)->get(route('platform.billing.index', ['status' => 'active']))
            ->assertInertia(fn ($page) => $page->has('schools.data', 0));
    }

    public function test_platform_finance_can_record_payments_but_support_cannot(): void
    {
        $this->lifecycle()->startTrial($this->school);

        $this->as($this->userFor(null, 'platform_support'))
            ->post(route('platform.schools.payments.store', $this->school), ['method' => 'cash'])
            ->assertForbidden();
        $this->assertSame('trial', $this->sub()->status);

        $this->as($this->userFor(null, 'platform_finance'))
            ->post(route('platform.schools.payments.store', $this->school), ['method' => 'cash'])
            ->assertRedirect();
        $this->assertSame('active', $this->sub()->status);
    }

    public function test_platform_payment_endpoint_rejects_partial_amounts_and_unsupported_methods(): void
    {
        $this->lifecycle()->startTrial($this->school);
        $finance = $this->userFor(null, 'platform_finance');

        $this->as($finance)->post(route('platform.schools.payments.store', $this->school), ['method' => 'cash', 'amount' => 5000])
            ->assertSessionHasErrors('payment');
        $this->as($finance)->post(route('platform.schools.payments.store', $this->school), ['method' => 'stripe'])
            ->assertSessionHasErrors('method');
        $this->assertSame('trial', $this->sub()->status);
    }

    public function test_school_admins_cannot_reach_platform_billing_or_pricing(): void
    {
        $this->as($this->admin)->get(route('platform.billing.index'))->assertForbidden();
        $this->as($this->admin)->post(route('platform.pricing.store'), ['school_type' => 'primary', 'amount' => 1, 'currency' => 'GMD'])->assertForbidden();
    }

    public function test_only_pricing_managers_can_change_prices(): void
    {
        $this->as($this->userFor(null, 'platform_finance'))
            ->post(route('platform.pricing.store'), ['school_type' => 'primary', 'amount' => 1, 'currency' => 'GMD'])
            ->assertForbidden();

        $this->as($this->userFor(null, 'platform_admin'))
            ->post(route('platform.pricing.store'), ['school_type' => 'primary', 'amount' => 21000, 'currency' => 'GMD'])
            ->assertRedirect();
        $this->assertSame(21000, app(PricingService::class)->current('primary')->amount);
    }

    public function test_changing_school_type_is_audited_and_changes_the_fee(): void
    {
        app(PricingService::class)->set('nursery', 12000, 'GMD', now()->subDay()->toDateString());
        $this->lifecycle()->startTrial($this->school);

        $this->as($this->userFor(null, 'platform_admin'))
            ->patch(route('platform.schools.type.update', $this->school), ['school_type' => 'nursery'])
            ->assertRedirect();

        $this->assertSame('nursery', $this->sub()->school_type);
        $this->assertSame(12000, $this->lifecycle()->quote($this->sub())['total']);
        $this->assertTrue(BillingEvent::withoutGlobalScopes()->where('type', 'school_type_changed')->exists());
    }

    public function test_billing_export_is_csv_and_neutralises_formula_injection(): void
    {
        $evil = School::query()->create(['name' => '=HYPERLINK("http://x")', 'slug' => 'evil', 'status' => 'active', 'category' => 'Primary']);
        $this->lifecycle()->startTrial($evil);

        $response = $this->as($this->userFor(null, 'platform_admin'))->get(route('platform.billing.export'));
        $response->assertOk();
        $body = $response->streamedContent();

        $this->assertStringContainsString('school,school_type,status', $body);
        $this->assertStringNotContainsString("\n=HYPERLINK", $body);
        $this->assertStringContainsString("\"'=HYPERLINK", $body);
    }

    public function test_landing_prices_come_from_the_platform_price_table(): void
    {
        $this->get(route('home'))
            ->assertInertia(fn ($page) => $page->has('prices')->where('trialDays', 30));
    }
}
