<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase 11: /metrics is not public in production; CI files exist; 2M users stay uncertified. */
class Phase11OpsTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_and_ready_stay_public(): void
    {
        $this->get(route('health'))->assertOk()->assertJsonPath('status', 'ok');
        $this->get(route('ready'))->assertOk();
    }

    public function test_metrics_are_open_in_testing_when_no_token_is_set(): void
    {
        config(['schoolos.metrics.token' => '', 'schoolos.metrics.allow_ips' => []]);

        $this->get(route('metrics'))
            ->assertOk()
            ->assertJsonStructure(['status', 'queues', 'autoscaling']);
    }

    public function test_a_configured_token_is_required_and_compared_safely(): void
    {
        config(['schoolos.metrics.token' => 'probe-secret', 'schoolos.metrics.allow_ips' => []]);

        $this->get(route('metrics'))->assertUnauthorized();
        $this->withHeaders(['Authorization' => 'Bearer wrong'])->get(route('metrics'))->assertUnauthorized();
        $this->withHeaders(['Authorization' => 'Bearer probe-secret'])->get(route('metrics'))->assertOk();
        $this->withHeaders(['X-Metrics-Token' => 'probe-secret'])->get(route('metrics'))->assertOk();
    }

    public function test_query_string_tokens_are_rejected(): void
    {
        config(['schoolos.metrics.token' => 'probe-secret', 'schoolos.metrics.allow_ips' => []]);

        $this->get(route('metrics', ['token' => 'probe-secret']))->assertUnauthorized();
    }

    public function test_ip_allow_list_is_enforced(): void
    {
        config(['schoolos.metrics.token' => '', 'schoolos.metrics.allow_ips' => ['10.0.0.8']]);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->get(route('metrics'))->assertForbidden();
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.8'])->get(route('metrics'))->assertOk();
    }

    public function test_production_refuses_metrics_when_unconfigured(): void
    {
        $this->app['env'] = 'production';
        config(['schoolos.metrics.token' => '', 'schoolos.metrics.allow_ips' => []]);

        $this->get(route('metrics'))->assertForbidden();
        $this->get(route('health'))->assertOk();
    }

    public function test_two_million_users_remain_uncertified(): void
    {
        $this->assertFalse(config('schoolos.capacity.two_million_certified'));
    }

    public function test_ci_workflow_runs_phpunit_i18n_and_the_frontend_build(): void
    {
        $path = base_path('.github/workflows/ci.yml');
        $this->assertFileExists($path);
        $yaml = file_get_contents($path);
        $this->assertStringContainsString('php artisan test', $yaml);
        $this->assertStringContainsString('check-phase10-i18n.mjs', $yaml);
        $this->assertStringContainsString('npm run build', $yaml);
        $this->assertStringNotContainsString('STRIPE_SECRET', $yaml);
        $this->assertStringNotContainsString('TWILIO_AUTH_TOKEN', $yaml);
    }
}
