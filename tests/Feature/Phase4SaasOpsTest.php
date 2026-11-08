<?php

namespace Tests\Feature;

use App\Models\AdmissionApplication;
use App\Models\BackupValidationRun;
use App\Models\PlatformAlert;
use App\Models\School;
use App\Models\SchoolQuota;
use App\Models\SchoolSubscription;
use App\Models\Student;
use App\Models\UsageSnapshot;
use App\Models\User;
use App\Services\Admissions\AdmissionWorkflow;
use App\Services\Platform\QuotaService;
use App\Support\TenantContext;
use Database\Seeders\RolesAndPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase4SaasOpsTest extends TestCase
{
    use RefreshDatabase;

    protected User $platformAdmin;

    protected School $school;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPlansSeeder::class);

        $this->platformAdmin = User::factory()->create([
            'school_id' => null,
            'email' => 'ops@schoolos.test',
            'email_verified_at' => now(),
        ]);
        $this->platformAdmin->assignRole('platform_admin');

        $this->school = School::query()->create([
            'name' => 'Ops School',
            'slug' => 'ops-school',
            'status' => 'active',
            'billing_email' => 'bill@ops.test',
        ]);

        SchoolSubscription::query()->create([
            'school_id' => $this->school->id,
            'school_type' => 'primary',
            'status' => 'active',
            'payment_status' => 'paid',
            'starts_at' => now(),
            'ends_at' => now()->addYear(),
            'activated_at' => now(),
        ]);

        app(QuotaService::class)->forSchool($this->school->fresh('subscription'));
    }

    public function test_platform_admin_can_open_console(): void
    {
        $publicUrl = app(\App\Services\School\SchoolDomainService::class)->urlsFor($this->school)['primary_url'];

        $this->actingAs($this->platformAdmin)
            ->get(route('platform.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Platform/Dashboard')
                ->has('stats.schools')
                ->has('health.status')
                ->where('nav', fn ($nav) => collect($nav)->contains('key', 'landing'))
                ->where('recentSchools.0.public_url', $publicUrl)
            );

        $this->actingAs($this->platformAdmin)
            ->get(route('platform.schools.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Platform/Schools/Index')
                ->where('nav', fn ($nav) => collect($nav)->contains('key', 'landing'))
                ->where('schools.data.0.public_url', $publicUrl)
            );

        $this->actingAs($this->platformAdmin)
            ->get(route('platform.schools.show', $this->school))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Platform/Schools/Show')
                ->where('school.public_url', $publicUrl)
            );
    }

    public function test_school_admin_cannot_access_platform_console(): void
    {
        $admin = User::factory()->create([
            'school_id' => $this->school->id,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('school_admin');

        $this->actingAs($admin)
            ->get(route('platform.dashboard'))
            ->assertForbidden();
    }

    public function test_dashboard_redirects_platform_admin_to_platform_console(): void
    {
        $this->actingAs($this->platformAdmin)
            ->get(route('dashboard'))
            ->assertRedirect(route('platform.dashboard'));
    }

    public function test_platform_admin_can_update_quota_and_suspend_school(): void
    {
        $this->actingAs($this->platformAdmin)
            ->patch(route('platform.schools.quota', $this->school), [
                'max_students' => 2,
                'max_staff' => 5,
                'max_storage_mb' => 512,
                'max_exports_per_day' => 3,
                'max_api_requests_per_hour' => 100,
                'is_premium_isolated' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('school_quotas', [
            'school_id' => $this->school->id,
            'max_students' => 2,
        ]);

        $this->actingAs($this->platformAdmin)
            ->post(route('platform.schools.suspend', $this->school))
            ->assertRedirect();

        $this->assertNotNull($this->school->fresh()->suspended_at);
    }

    public function test_quota_blocks_enrolment_when_max_students_reached(): void
    {
        SchoolQuota::query()->where('school_id', $this->school->id)->update(['max_students' => 1]);

        Student::query()->create([
            'school_id' => $this->school->id,
            'student_number' => 'STU-EXIST',
            'first_name' => 'Existing',
            'last_name' => 'Student',
            'status' => 'active',
        ]);

        $application = AdmissionApplication::query()->create([
            'school_id' => $this->school->id,
            'application_number' => 'ADM-TEST-1',
            'first_name' => 'New',
            'last_name' => 'Applicant',
            'status' => 'accepted',
        ]);

        app(TenantContext::class)->set($this->school);

        $this->expectException(\App\Exceptions\AcademicRuleException::class);
        $this->expectExceptionMessage('err.quota.students');

        app(AdmissionWorkflow::class)->enrol($application);
    }

    public function test_usage_snapshot_command_and_backup_validation(): void
    {
        $this->artisan('schoolos:snapshot-usage')->assertSuccessful();

        $this->assertTrue(
            UsageSnapshot::query()
                ->where('school_id', $this->school->id)
                ->whereDate('captured_on', now()->toDateString())
                ->exists()
        );

        $this->artisan('schoolos:validate-backup')->assertSuccessful();

        $this->assertDatabaseHas('backup_validation_runs', [
            'status' => 'passed',
            'trigger' => 'manual',
        ]);

        $this->actingAs($this->platformAdmin)
            ->post(route('platform.backups.run'))
            ->assertRedirect(route('platform.backups.index'));

        $this->assertGreaterThanOrEqual(2, BackupValidationRun::query()->count());
    }

    public function test_monitoring_alert_acknowledge_and_resolve(): void
    {
        $alert = PlatformAlert::query()->create([
            'severity' => 'warning',
            'source' => 'manual',
            'title' => 'Test alert',
            'message' => 'Something to ack',
        ]);

        $this->actingAs($this->platformAdmin)
            ->post(route('platform.alerts.acknowledge', $alert))
            ->assertRedirect();

        $this->assertNotNull($alert->fresh()->acknowledged_at);

        $this->actingAs($this->platformAdmin)
            ->post(route('platform.alerts.resolve', $alert))
            ->assertRedirect();

        $this->assertNotNull($alert->fresh()->resolved_at);
    }

    public function test_platform_admin_can_add_domain_and_set_annual_price(): void
    {
        $this->actingAs($this->platformAdmin)
            ->post(route('platform.schools.domains.store', $this->school), [
                'host' => 'custom.ops.test',
                'is_primary' => false,
                'is_custom' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('domains', [
            'school_id' => $this->school->id,
            'host' => 'custom.ops.test',
        ]);

        $this->actingAs($this->platformAdmin)
            ->post(route('platform.pricing.store'), [
                'school_type' => 'nursery',
                'amount' => 15000,
                'currency' => 'GMD',
                'description' => 'Test price',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('school_type_prices', ['school_type' => 'nursery', 'amount' => 15000]);
    }
}
