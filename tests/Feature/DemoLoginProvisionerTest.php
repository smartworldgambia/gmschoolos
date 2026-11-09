<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use App\Services\Demo\DemoLoginProvisioner;
use App\Support\DemoLoginCatalog;
use App\Support\TenantContext;
use Database\Seeders\RolesAndPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoLoginProvisionerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPlansSeeder::class);
        Config::set('app.url', 'https://gambiaschoolos.com');
        Config::set('schoolos.base_domain', 'gambiaschoolos.com');
        app(TenantContext::class)->clear();
    }

    public function test_mail_host_ignores_stale_edu_gm_base_domain(): void
    {
        Config::set('app.url', 'https://gambiaschoolos.com');
        Config::set('schoolos.base_domain', 'gmschoolos.edu.gm');

        $this->assertSame('gambiaschoolos.com', DemoLoginCatalog::mailHost());
        $this->assertSame('platform@gambiaschoolos.com', DemoLoginCatalog::platformEmail('platform'));
        $this->assertSame('admin@brightfuture.gambiaschoolos.com', DemoLoginCatalog::schoolDirectory('brightfuture')['school_admin']['email']);
    }

    public function test_sync_relocates_legacy_platform_email_and_resets_password(): void
    {
        $legacy = User::factory()->create([
            'email' => 'platform@schoolos.test',
            'school_id' => null,
            'password' => 'old-secret',
            'email_verified_at' => now(),
        ]);
        $legacy->syncRoles(['platform_admin']);

        app(DemoLoginProvisioner::class)->sync();

        $this->assertDatabaseMissing('users', ['email' => 'platform@schoolos.test']);
        $user = User::query()->where('email', 'platform@gambiaschoolos.com')->first();
        $this->assertNotNull($user);
        $this->assertSame($legacy->id, $user->id);
        $this->assertTrue(Hash::check('password', $user->password));

        $this->post('/login', [
            'email' => 'platform@gambiaschoolos.com',
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_accepts_canonical_email_when_the_row_still_has_a_legacy_address(): void
    {
        $legacy = User::factory()->create([
            'email' => 'admin@brightfuture.gmschoolos.edu.gm',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $legacy->syncRoles(['school_admin']);

        $this->post('/login', [
            'email' => 'admin@brightfuture.gambiaschoolos.com',
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($legacy);
    }

    public function test_sync_creates_missing_demo_accounts_for_an_existing_school(): void
    {
        $school = School::query()->create([
            'name' => 'Bright Future School',
            'slug' => 'brightfuture',
            'status' => 'active',
        ]);

        app(DemoLoginProvisioner::class)->sync();

        $admin = User::query()->where('email', 'admin@brightfuture.gambiaschoolos.com')->first();
        $this->assertNotNull($admin);
        $this->assertSame($school->id, $admin->school_id);
        $this->assertTrue($admin->hasRole('school_admin'));
        $this->assertTrue(Hash::check('password', $admin->password));

        $this->post('/login', [
            'email' => 'teacher@brightfuture.gambiaschoolos.com',
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_sync_does_not_overwrite_a_live_registered_admin_password(): void
    {
        $school = School::query()->create([
            'name' => 'Smartworld',
            'slug' => 'smartworld',
            'status' => 'active',
        ]);

        $owner = User::factory()->create([
            'email' => 'smartworldgambia@gmail.com',
            'school_id' => $school->id,
            'password' => 'OwnerSecret1',
            'email_verified_at' => now(),
        ]);
        $owner->syncRoles(['school_admin']);

        app(DemoLoginProvisioner::class)->sync();

        $this->assertTrue(Hash::check('OwnerSecret1', $owner->fresh()->password));
        $this->assertSame('smartworldgambia@gmail.com', $owner->fresh()->email);

        $demoAdmin = User::query()->where('email', 'admin@smartworld.gambiaschoolos.com')->first();
        $this->assertNotNull($demoAdmin);
        $this->assertTrue(Hash::check('password', $demoAdmin->password));
    }

    public function test_privileged_demo_accounts_are_not_forced_through_two_factor_enrolment(): void
    {
        Config::set('schoolos.security.require_two_factor_privileged', true);

        $user = User::factory()->create([
            'email' => 'platform@gambiaschoolos.com',
            'school_id' => null,
            'password' => 'password',
            'email_verified_at' => now(),
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ]);
        $user->syncRoles(['platform_admin']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('platform.dashboard'));

        $this->actingAs($user)
            ->get(route('platform.dashboard'))
            ->assertOk();
    }
}
