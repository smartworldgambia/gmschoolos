<?php

namespace Tests\Feature;

use App\Models\CapacityCertificationRun;
use App\Models\School;
use App\Models\User;
use App\Models\WorkloadModel;
use App\Services\Scale\CapacityCertifier;
use App\Services\Scale\TenantIsolationService;
use App\Services\Scale\WorkloadModeller;
use Database\Seeders\RolesAndPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase7ScaleExpansionTest extends TestCase
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
            'email_verified_at' => now(),
        ]);
        $this->platformAdmin->assignRole('platform_admin');

        $this->school = School::query()->create([
            'name' => 'Tier School',
            'slug' => 'tier-school',
            'status' => 'active',
            'isolation_tier' => 'shared',
        ]);
    }

    public function test_workload_modeller_persists_profiles_without_certifying_two_million(): void
    {
        $this->artisan('schoolos:model-workload', ['--persist' => true])->assertSuccessful();

        $this->assertSame(3, WorkloadModel::query()->count());
        $this->assertFalse(config('schoolos.capacity.two_million_certified'));
        $this->assertFalse(WorkloadModel::query()->where('is_certified', true)->exists());
    }

    public function test_capacity_certifier_evaluates_thresholds(): void
    {
        $passed = app(CapacityCertifier::class)->record([
            'gate' => 'gate2',
            'profile' => 'A',
            'p95_ms' => 500,
            'error_rate_pct' => 0.5,
            'rps' => 25,
            'environment' => 'staging',
        ]);

        $this->assertSame('passed', $passed->status);

        $failed = app(CapacityCertifier::class)->record([
            'gate' => 'gate2',
            'profile' => 'A',
            'p95_ms' => 5000,
            'error_rate_pct' => 20,
            'rps' => 1,
        ]);

        $this->assertSame('failed', $failed->status);
        $this->assertFalse(app(CapacityCertifier::class)->summary()['two_million_certified']);
    }

    public function test_isolation_tier_and_dedicated_plan(): void
    {
        $service = app(TenantIsolationService::class);
        $service->assignTier($this->school, 'premium_isolated');
        $this->assertSame('premium_isolated', $this->school->fresh()->isolation_tier);
        $this->assertNotNull($this->school->fresh()->partition_key);

        $conn = $service->planDedicatedDatabase($this->school, [
            'host' => '10.0.0.5',
            'database' => 'schoolos_tier',
        ]);

        $this->assertSame('planned', $conn->status);
        $this->assertSame('dedicated_db', $this->school->fresh()->isolation_tier);
        $this->assertSame(config('database.default'), $service->connectionFor($this->school->fresh()));
    }

    public function test_platform_capacity_console_and_record_endpoint(): void
    {
        $this->actingAs($this->platformAdmin)
            ->get(route('platform.capacity.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Platform/Capacity/Index')
                ->where('twoMillionCertified', false)
                ->has('models')
                ->has('certification')
                ->has('isolation')
            );

        $this->actingAs($this->platformAdmin)
            ->post(route('platform.capacity.record'), [
                'gate' => 'gate1',
                'profile' => 'A',
                'environment' => 'local',
                'p95_ms' => 200,
                'error_rate_pct' => 0,
                'rps' => 5,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('capacity_certification_runs', [
            'gate' => 'gate1',
            'profile' => 'A',
            'status' => 'passed',
        ]);

        $this->actingAs($this->platformAdmin)
            ->post(route('platform.capacity.isolation'), [
                'school_id' => $this->school->id,
                'isolation_tier' => 'premium_isolated',
            ])
            ->assertRedirect();

        $this->assertSame('premium_isolated', $this->school->fresh()->isolation_tier);
    }

    public function test_record_capacity_artisan_command(): void
    {
        $this->artisan('schoolos:record-capacity-run', [
            'gate' => 'gate3',
            'profile' => 'B',
            '--p95' => 900,
            '--errors' => 1,
            '--rps' => 55,
            '--vus' => 40,
            '--duration' => 90,
        ])->assertSuccessful();

        $this->assertTrue(
            CapacityCertificationRun::query()
                ->where('gate', 'gate3')
                ->where('profile', 'B')
                ->where('status', 'passed')
                ->exists()
        );
    }
}
