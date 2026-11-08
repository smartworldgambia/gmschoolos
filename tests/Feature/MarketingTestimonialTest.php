<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Marketing\MarketingContent;
use Database\Seeders\MarketingSeeder;
use Database\Seeders\RolesAndPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingTestimonialTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketing_seeder_publishes_testimonial_on_landing(): void
    {
        $this->seed(RolesAndPlansSeeder::class);
        $this->seed(MarketingSeeder::class);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Marketing/Landing')
                ->where('marketing.testimonial.name', 'Awa Ceesay')
                ->where('marketing.testimonial.school', 'Bright Future School')
                ->where('marketing.testimonial.published', true)
            );
    }

    public function test_platform_admin_can_update_testimonial(): void
    {
        $this->seed(RolesAndPlansSeeder::class);

        $admin = User::factory()->create([
            'email' => 'platform-marketing@schoolos.test',
            'school_id' => null,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('platform_admin');

        $this->actingAs($admin)
            ->put(route('platform.marketing.update'), [
                'quote' => 'Approved quote for the landing page.',
                'name' => 'Fatou Jallow',
                'role' => 'Principal',
                'school' => 'Kanifing Academy',
                'photo_url' => '',
                'rating' => 5,
                'published' => true,
            ])
            ->assertRedirect();

        $this->assertSame(
            'Fatou Jallow',
            app(MarketingContent::class)->testimonial()['name'] ?? null
        );

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('marketing.testimonial.quote', 'Approved quote for the landing page.')
                ->where('marketing.testimonial.name', 'Fatou Jallow')
            );
    }

    public function test_unpublished_testimonial_is_hidden_on_landing(): void
    {
        $this->seed(RolesAndPlansSeeder::class);
        app(MarketingContent::class)->saveTestimonial([
            'quote' => 'Hidden quote',
            'name' => 'Hidden',
            'role' => 'Principal',
            'school' => 'Hidden School',
            'rating' => 5,
            'published' => false,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('marketing.testimonial', null)
            );
    }
}
