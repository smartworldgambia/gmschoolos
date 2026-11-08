<?php

namespace Tests\Feature;

use App\Models\NewsPost;
use App\Models\School;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\RolesAndPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolWebsiteAdminTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPlansSeeder::class);

        $this->school = School::query()->create([
            'name' => 'Website School',
            'slug' => 'website-school',
            'status' => 'active',
            'website_published' => true,
            'tagline' => 'Old tagline',
            'about' => 'Old about',
            'settings' => [
                'landing' => [
                    'template' => 'combined',
                    'hero_headline' => 'Old hero',
                ],
            ],
        ]);
        app(TenantContext::class)->set($this->school);

        $this->admin = User::factory()->create([
            'school_id' => $this->school->id,
            'email_verified_at' => now(),
        ]);
        $this->admin->assignRole('school_admin');
    }

    public function test_school_admin_can_view_website_editor(): void
    {
        $this->actingAs($this->admin)
            ->get(route('website.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('School/Website/Edit')
                ->where('website.slug', 'website-school')
                ->has('news'));
    }

    public function test_school_admin_can_update_landing_content_and_clear_cache_payload(): void
    {
        $this->actingAs($this->admin)
            ->put(route('website.update'), [
                'tagline' => 'Knowledge • Character',
                'about' => 'Updated about copy for families.',
                'phone' => '+220 3000000',
                'email' => 'info@website-school.test',
                'address' => 'Kairaba Avenue',
                'region' => 'Kanifing',
                'primary_color' => '#1A2B56',
                'accent_color' => '#1683F8',
                'website_published' => true,
                'hero_headline' => 'Welcome families',
                'hero_body' => 'A modern school community.',
                'hero_image' => '/images/marketing/hero-students.jpg',
                'hero_slides' => [
                    ['image' => '/images/marketing/hero-students.jpg', 'alt' => 'Students'],
                    ['image' => '/images/marketing/hero-classroom.jpg', 'alt' => 'Classroom'],
                ],
                'cta_primary' => 'Apply for Admission',
                'cta_secondary' => 'Learn More',
                'about_headline' => 'Our story',
                'about_image' => '/images/marketing/hero-classroom.jpg',
                'levels_heading' => 'Our Academic Levels',
                'levels_intro' => 'Programmes for every stage.',
                'level_cards' => [
                    [
                        'name' => 'Nursery',
                        'code' => 'nursery',
                        'description' => 'Early years care.',
                        'image' => '/images/marketing/hero-primary.jpg',
                    ],
                ],
                'features' => [
                    [
                        'key' => 'teachers',
                        'icon' => 'teachers',
                        'title' => 'Qualified Teachers',
                        'subtitle' => 'Dedicated educators',
                    ],
                ],
                'admissions_title' => 'Admissions open',
                'admissions_body' => 'Start your application today.',
                'admissions_steps' => [
                    ['title' => 'Enquire', 'body' => 'Tell us about your child.'],
                ],
                'contact_blurb' => 'Visit or message us anytime.',
            ])
            ->assertRedirect();

        $this->school->refresh();
        $this->assertSame('Knowledge • Character', $this->school->tagline);
        $this->assertSame('Updated about copy for families.', $this->school->about);
        $this->assertSame('Welcome families', $this->school->settings['landing']['hero_headline']);
        $this->assertSame('Our story', $this->school->settings['landing']['about_headline']);
        $this->assertSame('Nursery', $this->school->settings['landing']['level_cards'][0]['name']);
        $this->assertSame('Enquire', $this->school->settings['landing']['admissions_steps'][0]['title']);
        $this->assertCount(2, $this->school->settings['landing']['hero_slides']);
        $this->assertSame('/images/marketing/hero-students.jpg', $this->school->settings['landing']['hero_slides'][0]['image']);
    }

    public function test_school_admin_can_create_news_post(): void
    {
        $this->actingAs($this->admin)
            ->post(route('website.news.store'), [
                'title' => 'Open day announced',
                'excerpt' => 'Join us this Saturday.',
                'body' => 'Families are welcome to tour campus.',
                'published' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('news_posts', [
            'school_id' => $this->school->id,
            'title' => 'Open day announced',
            'slug' => 'open-day-announced',
        ]);
        $this->assertNotNull(
            NewsPost::query()->where('school_id', $this->school->id)->where('title', 'Open day announced')->value('published_at')
        );
    }
}
