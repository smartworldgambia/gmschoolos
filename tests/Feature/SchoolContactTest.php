<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\SchoolInquiry;
use App\Models\User;
use App\Notifications\SchoolInquiryReceived;
use App\Support\TenantContext;
use Database\Seeders\RolesAndPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SchoolContactTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPlansSeeder::class);

        $this->school = School::query()->create([
            'name' => 'Contact School',
            'slug' => 'contact-school',
            'status' => 'active',
            'website_published' => true,
            'email' => 'office@contact-school.test',
            'address' => 'Kairaba Avenue',
            'region' => 'Kanifing',
            'country' => 'The Gambia',
            'primary_color' => '#1A2B56',
            'accent_color' => '#1683F8',
        ]);
        app(TenantContext::class)->set($this->school);

        $this->admin = User::factory()->create([
            'school_id' => $this->school->id,
            'email_verified_at' => now(),
        ]);
        $this->admin->assignRole('school_admin');
    }

    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Aminata Jallow',
            'email' => 'aminata@example.com',
            'phone' => '+220 7000000',
            'subject' => 'Admissions',
            'message' => 'Hello, could you share the admissions timeline?',
        ], $overrides);
    }

    public function test_public_site_exposes_contact_endpoint_and_map_settings(): void
    {
        $this->get(route('school.site', ['school_slug' => 'contact-school']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('School/PublicSite')
                ->where('contactUrl', route('school.contact', ['school_slug' => 'contact-school']))
                ->where('school.country', 'The Gambia')
                ->where('landing.map_enabled', true));
    }

    public function test_visitor_can_send_a_message_and_school_is_notified(): void
    {
        Notification::fake();

        $this->post('/sites/contact-school/contact', $this->payload())->assertRedirect();

        $this->assertDatabaseHas('school_inquiries', [
            'school_id' => $this->school->id,
            'email' => 'aminata@example.com',
            'status' => 'new',
        ]);

        Notification::assertSentTo($this->admin, SchoolInquiryReceived::class);
        Notification::assertSentOnDemand(
            SchoolInquiryReceived::class,
            fn ($notification, $channels, $notifiable) => in_array('office@contact-school.test', (array) $notifiable->routes['mail'], true)
        );
    }

    public function test_message_is_validated(): void
    {
        $this->post('/sites/contact-school/contact', $this->payload(['email' => 'nope', 'message' => 'short']))
            ->assertSessionHasErrors(['email', 'message']);

        $this->assertSame(0, SchoolInquiry::withoutGlobalScopes()->count());
    }

    public function test_honeypot_submissions_are_silently_dropped(): void
    {
        Notification::fake();

        $this->post('/sites/contact-school/contact', $this->payload(['company_website' => 'http://spam.test']))
            ->assertRedirect();

        $this->assertSame(0, SchoolInquiry::withoutGlobalScopes()->count());
        Notification::assertNothingSent();
    }

    public function test_unpublished_or_unknown_school_cannot_receive_messages(): void
    {
        $this->school->update(['website_published' => false]);

        $this->post('/sites/contact-school/contact', $this->payload())->assertNotFound();
        $this->post('/sites/missing-school/contact', $this->payload())->assertNotFound();
    }

    public function test_contact_form_is_rate_limited(): void
    {
        Notification::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/sites/contact-school/contact', $this->payload())->assertRedirect();
        }

        $this->post('/sites/contact-school/contact', $this->payload())->assertStatus(429);
    }

    public function test_admin_can_set_and_clear_map_pin_and_hours(): void
    {
        $base = [
            'tagline' => 'Tagline',
            'website_published' => true,
            'map_enabled' => true,
            'map_lat' => '13.454876',
            'map_lng' => '-16.579032',
            'contact_hours' => "Mon–Fri: 07:30–16:30\nSat: closed",
        ];

        $this->actingAs($this->admin)->put(route('website.update'), $base)->assertRedirect();

        $landing = $this->school->fresh()->settings['landing'];
        $this->assertSame(13.454876, $landing['map_lat']);
        $this->assertSame(-16.579032, $landing['map_lng']);
        $this->assertTrue($landing['map_enabled']);
        $this->assertStringContainsString('Sat: closed', $landing['contact_hours']);

        $this->actingAs($this->admin)
            ->put(route('website.update'), array_merge($base, ['map_lat' => null, 'map_lng' => null, 'map_enabled' => false, 'contact_hours' => null]))
            ->assertRedirect();

        $landing = $this->school->fresh()->settings['landing'];
        $this->assertNull($landing['map_lat']);
        $this->assertNull($landing['map_lng']);
        $this->assertFalse($landing['map_enabled']);
        $this->assertNull($landing['contact_hours']);
    }

    public function test_map_coordinates_must_be_valid_and_paired(): void
    {
        $this->actingAs($this->admin)
            ->put(route('website.update'), ['map_lat' => '123', 'map_lng' => '-16.5'])
            ->assertSessionHasErrors('map_lat');

        $this->actingAs($this->admin)
            ->put(route('website.update'), ['map_lat' => '13.4'])
            ->assertSessionHasErrors('map_lng');
    }
}
