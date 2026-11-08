<?php

namespace Tests\Feature;

use App\Models\Level;
use App\Models\NewsPost;
use App\Models\School;
use App\Models\User;
use App\Notifications\SchoolRegisteredWelcome;
use Database\Seeders\RolesAndPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use App\Support\TenantContext;
use Tests\TestCase;

class RegisterSchoolTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_registration_wizard_creates_school_and_admin(): void
    {
        Notification::fake();
        app(TenantContext::class)->enterPlatformMode(); // inspect provisioned rows as an operator

        $this->seed(RolesAndPlansSeeder::class);

        $response = $this->post(route('register-school.store'), [
            'school_name' => 'New Horizon Academy',
            'school_email' => 'info@newhorizon.test',
            'school_phone' => '+220 4400000',
            'address' => '12 Test Road',
            'district' => 'Banjul Central',
            'terms' => '1',
            'category' => 'Primary',
            'country' => 'The Gambia',
            'region' => 'Banjul',
            'subdomain' => 'newhorizon',
            'academic_year_name' => '2025/2026',
            'term_name' => 'Term 1',
            'admin_name' => 'Admin User',
            'admin_email' => 'admin@newhorizon.test',
            'admin_phone' => '+220 1111111',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        // The admin must verify their email address before opening the dashboard.
        $response->assertRedirect(route('verification.notice'));
        $this->assertDatabaseHas('schools', [
            'slug' => 'newhorizon',
            'status' => 'active',
            'category' => 'primary',
            'website_published' => true,
        ]);
        $this->assertDatabaseHas('domains', ['host' => 'newhorizon.localhost']);
        $admin = User::query()->where('email', 'admin@newhorizon.test')->first();
        $this->assertTrue($admin?->hasRole('school_admin'));
        $this->assertNull($admin->email_verified_at, 'Registered admins must verify their email.');
        $this->assertAuthenticated();

        $school = School::query()->where('slug', 'newhorizon')->firstOrFail();
        $this->assertSame('Building strong foundations for lifelong learning', $school->tagline);
        $this->assertSame('primary', $school->settings['landing']['template'] ?? null);
        $this->assertSame(6, Level::query()->where('school_id', $school->id)->count());
        $this->assertDatabaseHas('levels', ['school_id' => $school->id, 'code' => 'g1', 'name' => 'Grade 1']);
        $this->assertTrue(
            NewsPost::query()->where('school_id', $school->id)->whereNotNull('published_at')->exists()
        );

        Notification::assertSentTo($admin, SchoolRegisteredWelcome::class, function (SchoolRegisteredWelcome $notification) use ($school) {
            return $notification->publicSiteUrl === route('school.site', ['school_slug' => $school->slug]);
        });

        Cache::forget('schoolos:public_site:'.$school->id);

        $this->get(route('school.site', ['school_slug' => $school->slug]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('School/PublicSite')
                ->where('school.slug', 'newhorizon')
                ->where('school.category', 'primary')
                ->where('landing.template', 'primary')
                ->has('levels', 6)
                ->has('news', 6)
                ->has('landing.features')
                ->has('landing.level_cards')
                ->has('landing.hero_slides', 3));
    }

    public function test_register_school_page_exposes_base_domain_and_annual_prices(): void
    {
        $this->seed(RolesAndPlansSeeder::class);

        $response = $this->get(route('register-school.create', ['type' => 'Primary']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Onboarding/RegisterSchool')
            ->has('prices.Primary.label')
            ->where('preselectedCategory', 'Primary')
            ->where('baseDomain', config('schoolos.base_domain'))
            ->has('categories', 7)
            ->where('trialDays', (int) config('schoolos.trial_days', 30)));
    }

    public function test_authenticated_users_are_redirected_away_from_register_school(): void
    {
        $this->seed(RolesAndPlansSeeder::class);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('register-school.create'));

        $response->assertRedirect();
    }

    public function test_duplicate_subdomain_is_rejected(): void
    {
        $this->seed(RolesAndPlansSeeder::class);

        School::query()->create([
            'name' => 'Existing',
            'slug' => 'taken',
            'status' => 'active',
        ]);

        $response = $this->from(route('register-school.create'))->post(route('register-school.store'), [
            'school_name' => 'Another',
            'school_email' => 'info@another.test',
            'school_phone' => '+220 4400001',
            'address' => '1 Other Road',
            'district' => 'Banjul Central',
            'admin_phone' => '+220 2222222',
            'terms' => '1',
            'category' => 'Primary',
            'subdomain' => 'taken',
            'academic_year_name' => '2025/2026',
            'term_name' => 'Term 1',
            'admin_name' => 'Admin',
            'admin_email' => 'unique@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('register-school.create'));
        $response->assertSessionHasErrors('subdomain');
    }
}
