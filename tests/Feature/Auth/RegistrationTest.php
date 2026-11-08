<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Open self-registration is disabled by design (Phase 1 security remediation).
 * Accounts are created only through school provisioning, invitations or admin-created users.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_generic_registration_screen_redirects_to_school_signup(): void
    {
        $this->get('/register')->assertRedirect(route('register-school.create'));
    }

    public function test_public_registration_cannot_create_users(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('register-school.create'));

        $this->assertGuest();
        $this->assertSame(0, User::query()->where('email', 'test@example.com')->count());
    }
}
