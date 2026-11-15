<?php

namespace Tests\Feature\Auth;

use App\Models\School;
use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use App\Support\TwoFactorOtp;
use Database\Seeders\RolesAndPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TwoFactorOtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPlansSeeder::class);
        config(['schoolos.security.require_two_factor_privileged' => true]);
    }

    public function test_a_new_school_admin_can_enrol_with_an_email_code_instead_of_an_authenticator(): void
    {
        Notification::fake();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('two-factor.settings'));

        $this->actingAs($admin)
            ->get(route('two-factor.settings'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Profile/TwoFactor')
                ->where('required', true)
                ->where('enabled', false));

        $this->actingAs($admin)
            ->post(route('two-factor.start'), ['method' => 'email'])
            ->assertRedirect(route('two-factor.settings'));

        Notification::assertSentTo($admin, TwoFactorCodeNotification::class);
        $code = TwoFactorOtp::codeForTests($admin->fresh(), TwoFactorOtp::PURPOSE_SETUP);
        $this->assertNotEmpty($code);

        $this->actingAs($admin)
            ->withSession(['auth.two_factor_setup_method' => 'email'])
            ->post(route('two-factor.confirm'), ['code' => $code])
            ->assertRedirect(route('dashboard', absolute: false));

        $admin->refresh();
        $this->assertTrue($admin->hasTwoFactorEnabled());
        $this->assertSame('email', $admin->twoFactorMethod());
        $this->assertTrue(session('auth.two_factor_passed'));
    }

    public function test_a_school_admin_can_enrol_with_a_phone_code(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('two-factor.start'), ['method' => 'sms', 'phone' => '7123456'])
            ->assertRedirect(route('two-factor.settings'));

        $code = TwoFactorOtp::codeForTests($admin->fresh(), TwoFactorOtp::PURPOSE_SETUP);
        $this->assertNotEmpty($code);

        $this->actingAs($admin)
            ->withSession([
                'auth.two_factor_setup_method' => 'sms',
                'auth.two_factor_setup_phone' => '+2207123456',
            ])
            ->post(route('two-factor.confirm'), ['code' => $code])
            ->assertRedirect(route('dashboard', absolute: false));

        $admin->refresh();
        $this->assertTrue($admin->hasTwoFactorEnabled());
        $this->assertSame('sms', $admin->twoFactorMethod());
        $this->assertSame('+2207123456', $admin->phone);
    }

    public function test_email_two_factor_is_challenged_on_the_next_login(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $admin->forceFill([
            'two_factor_method' => 'email',
            'two_factor_confirmed_at' => now(),
            'password' => 'password',
        ])->save();

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('two-factor.challenge'));

        $this->get(route('two-factor.challenge'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/TwoFactorChallenge')
                ->where('method', 'email'));

        Notification::assertSentTo($admin, TwoFactorCodeNotification::class);
        $code = TwoFactorOtp::codeForTests($admin->fresh(), TwoFactorOtp::PURPOSE_LOGIN);

        $this->post(route('two-factor.challenge.store'), ['code' => $code])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertTrue(session('auth.two_factor_passed'));
    }

    public function test_an_invalid_email_code_is_rejected_and_an_invalid_phone_cannot_start_sms(): void
    {
        Notification::fake();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('two-factor.start'), ['method' => 'email'])
            ->assertRedirect(route('two-factor.settings'));

        $this->actingAs($admin)
            ->withSession(['auth.two_factor_setup_method' => 'email'])
            ->from(route('two-factor.settings'))
            ->post(route('two-factor.confirm'), ['code' => '000000'])
            ->assertRedirect(route('two-factor.settings'))
            ->assertSessionHasErrors('code');

        $this->assertFalse($admin->fresh()->hasTwoFactorEnabled());

        $this->actingAs($admin)
            ->from(route('two-factor.settings'))
            ->post(route('two-factor.start'), ['method' => 'sms', 'phone' => 'abc'])
            ->assertRedirect(route('two-factor.settings'))
            ->assertSessionHasErrors('phone');
    }

    private function admin(): User
    {
        $school = School::query()->create([
            'name' => 'OTP Academy',
            'slug' => 'otp-academy',
            'status' => 'active',
            'billing_email' => 'bill@otp.test',
        ]);

        $user = User::factory()->create([
            'school_id' => $school->id,
            'email' => 'head@otp.test',
            'email_verified_at' => now(),
            'password' => 'password',
        ]);
        $user->assignRole('school_admin');

        return $user;
    }
}
