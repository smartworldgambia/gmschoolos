<?php

namespace Tests\Feature;

use App\Models\ErrorReport;
use App\Models\School;
use App\Models\User;
use App\Services\Ops\ErrorReporter;
use App\Support\Totp;
use Database\Seeders\RolesAndPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ProductionHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPlansSeeder::class);
    }

    public function test_security_headers_are_present(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_error_reporter_persists_fingerprint(): void
    {
        Config::set('schoolos.errors.enabled', true);
        Config::set('schoolos.errors.raise_platform_alerts', false);
        Config::set('schoolos.errors.webhook_url', null);

        app(ErrorReporter::class)->report(new \RuntimeException('hardening probe'));

        $this->assertDatabaseHas('error_reports', [
            'exception_class' => \RuntimeException::class,
        ]);

        $this->assertSame(1, ErrorReport::query()->count());

        app(ErrorReporter::class)->report(new \RuntimeException('hardening probe'));

        $this->assertSame(1, ErrorReport::query()->count());
        $this->assertSame(2, ErrorReport::query()->first()->occurrences);
    }

    public function test_login_redirects_to_two_factor_challenge_when_enabled(): void
    {
        $secret = Totp::generateSecret();
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'two_factor_secret' => encrypt($secret),
            'two_factor_recovery_codes' => encrypt(json_encode(['AAAA-BBBB'])),
            'two_factor_confirmed_at' => now(),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('two-factor.challenge'));

        $this->assertAuthenticated();
        $this->assertFalse(session('auth.two_factor_passed', false));

        $this->get(route('dashboard'))
            ->assertRedirect(route('two-factor.challenge'));
    }

    public function test_valid_totp_clears_two_factor_gate(): void
    {
        $secret = Totp::generateSecret();
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'two_factor_secret' => encrypt($secret),
            'two_factor_recovery_codes' => encrypt(json_encode(['RECO-VERY1'])),
            'two_factor_confirmed_at' => now(),
        ]);

        $this->actingAs($user);
        $this->withSession(['auth.two_factor_passed' => false]);

        $code = Totp::at($secret, (int) floor(time() / 30));

        $this->post(route('two-factor.challenge.store'), ['code' => $code])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertTrue(session('auth.two_factor_passed'));
    }

    public function test_user_can_enable_two_factor_via_settings(): void
    {
        $school = School::query()->create([
            'name' => 'MFA School',
            'slug' => 'mfa-school',
            'status' => 'active',
            'billing_email' => 'bill@mfa.test',
        ]);

        $user = User::factory()->create([
            'school_id' => $school->id,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('school_admin');

        $this->actingAs($user)
            ->post(route('two-factor.start'))
            ->assertRedirect(route('two-factor.settings'));

        $secret = session('auth.two_factor_setup_secret');
        $this->assertNotEmpty($secret);

        $code = Totp::at($secret, (int) floor(time() / 30));

        $this->actingAs($user)
            ->withSession(['auth.two_factor_setup_secret' => $secret])
            ->post(route('two-factor.confirm'), ['code' => $code])
            ->assertRedirect(route('two-factor.settings'));

        $user->refresh();
        $this->assertTrue($user->hasTwoFactorEnabled());
    }

    public function test_platform_ops_page_loads(): void
    {
        $admin = User::factory()->create([
            'school_id' => null,
            'email' => 'ops-harden@schoolos.test',
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('platform_admin');

        $this->actingAs($admin)
            ->get(route('platform.ops.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Platform/Ops/Index')
                ->has('errorReporting.enabled')
                ->has('security.headers_enabled')
            );
    }
}
