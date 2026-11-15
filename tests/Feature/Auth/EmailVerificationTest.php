<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\EmailVerification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/verify-email');

        $response->assertStatus(200);
    }

    public function test_email_can_be_verified(): void
    {
        $user = User::factory()->unverified()->create();

        Event::fake();

        $response = $this->actingAs($user)->get(EmailVerification::relativeUrl($user));

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
    }

    public function test_a_guest_can_verify_from_the_emailed_link_and_is_signed_in(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->get(EmailVerification::relativeUrl($user));

        $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
        $this->assertAuthenticatedAs($user->fresh());
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_the_emailed_link_works_even_when_another_account_is_signed_in(): void
    {
        $admin = User::factory()->unverified()->create(['email' => 'new-school@example.test']);
        $other = User::factory()->create(['email' => 'someone-else@example.test']);

        $response = $this->actingAs($other)->get(EmailVerification::relativeUrl($admin));

        $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
        $this->assertAuthenticatedAs($admin->fresh());
        $this->assertTrue($admin->fresh()->hasVerifiedEmail());
        $this->assertFalse($other->fresh()->is($this->app['auth']->user()));
    }

    public function test_an_absolute_signed_link_already_in_an_inbox_still_verifies(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $this->get($url)
            ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_the_notification_href_uses_the_application_url(): void
    {
        Notification::fake();
        config(['app.url' => 'https://gambiaschoolos.com']);

        $user = User::factory()->unverified()->create();
        $user->sendEmailVerificationNotification();

        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use ($user) {
            $href = $notification->toMail($user)->actionUrl;

            return str_starts_with((string) $href, 'https://gambiaschoolos.com/verify-email/'.$user->id.'/');
        });
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')],
        );

        $this->actingAs($user)->get($verificationUrl)->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
}
