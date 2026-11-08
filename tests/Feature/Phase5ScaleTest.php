<?php

namespace Tests\Feature;

use App\Jobs\ProcessStudentExportBatch;
use App\Jobs\ProcessStudentImportBatch;
use App\Models\School;
use App\Models\UsageSnapshot;
use App\Models\User;
use App\Services\Scale\CacheTuner;
use App\Services\Scale\CostAttributor;
use App\Services\Scale\QueryProfiler;
use App\Services\Scale\WorkerPoolMonitor;
use Database\Seeders\RolesAndPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class Phase5ScaleTest extends TestCase
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
            'name' => 'Scale School',
            'slug' => 'scale-school',
            'status' => 'active',
            'website_published' => true,
        ]);
    }

    public function test_metrics_endpoint_exposes_queues_and_autoscaling(): void
    {
        $this->get(route('metrics'))
            ->assertOk()
            ->assertJsonStructure([
                'status',
                'queues',
                'autoscaling' => ['app_min', 'app_max', 'worker_max', 'current_queue_depth'],
                'cost',
            ]);
    }

    public function test_jobs_use_dedicated_queues(): void
    {
        $import = new ProcessStudentImportBatch(1, 1);
        $export = new ProcessStudentExportBatch(1, 1);

        $this->assertSame(config('schoolos.queues.imports'), $import->queue);
        $this->assertSame(config('schoolos.queues.exports'), $export->queue);
    }

    public function test_worker_pool_monitor_lists_configured_pools(): void
    {
        $pools = app(WorkerPoolMonitor::class)->pools();
        $names = collect($pools)->pluck('name')->all();

        $this->assertContains('imports', $names);
        $this->assertContains('exports', $names);
        $this->assertContains('critical', $names);
    }

    public function test_public_site_cache_and_warm_command(): void
    {
        Cache::flush();

        $payload = app(CacheTuner::class)->publicSitePayload($this->school);
        $this->assertSame('Scale School', $payload['school']['name']);
        $this->assertTrue(Cache::has('schoolos:public_site:'.$this->school->id));

        $this->artisan('schoolos:cache-warm')->assertSuccessful();
        $this->assertTrue(Cache::has('schoolos:landing_prices'));
    }

    public function test_cost_attribution_from_usage_snapshots(): void
    {
        UsageSnapshot::query()->create([
            'school_id' => $this->school->id,
            'captured_on' => now()->toDateString(),
            'students_count' => 100,
            'request_count' => 1000,
            'export_count' => 2,
            'import_count' => 1,
            'courses_count' => 4,
        ]);

        $summary = app(CostAttributor::class)->platformSummary();
        $this->assertGreaterThan(0, $summary['total_units']);
        $this->assertSame('Scale School', $summary['top'][0]['school']);

        $this->artisan('schoolos:cost-report')->assertSuccessful();
    }

    public function test_platform_scale_console_and_query_profiler_sample(): void
    {
        app(QueryProfiler::class)->record(250, 'select * from students where id = ?', [1]);

        $this->actingAs($this->platformAdmin)
            ->get(route('platform.scale.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Platform/Scale/Index')
                ->has('pools')
                ->has('cost')
                ->has('autoscaling')
            );

        $this->actingAs($this->platformAdmin)
            ->post(route('platform.scale.cache-warm'))
            ->assertRedirect();
    }

    public function test_query_profiler_respects_threshold(): void
    {
        config(['schoolos.query_profile.slow_ms' => 100]);
        $profiler = app(QueryProfiler::class);
        $profiler->clear();

        $profiler->record(50, 'select 1');
        $this->assertCount(0, $profiler->recent());

        $profiler->record(150, 'select slow');
        $this->assertCount(1, $profiler->recent());
    }
}
