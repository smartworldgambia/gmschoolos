<?php

namespace Tests\Unit;

use App\Models\Level;
use App\Models\NewsPost;
use App\Models\School;
use App\Services\School\SchoolLandingProvisioner;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolLandingProvisionerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // These tests inspect provisioned rows as platform operators (tenancy fails closed otherwise).
        app(TenantContext::class)->enterPlatformMode();
    }

    public function test_nursery_template_seeds_levels_news_and_branding(): void
    {
        $school = School::query()->create([
            'name' => 'Little Stars Nursery',
            'slug' => 'littlestars',
            'category' => 'nursery',
            'status' => 'active',
            'website_published' => false,
        ]);

        $provisioned = app(SchoolLandingProvisioner::class)->provision($school);

        $this->assertTrue($provisioned->website_published);
        $this->assertSame('nursery', $provisioned->settings['landing']['template']);
        $this->assertSame('#1A2B56', $provisioned->primary_color);
        $this->assertSame('#1683F8', $provisioned->accent_color);
        $this->assertNotEmpty($provisioned->settings['landing']['features'] ?? []);
        $this->assertCount(3, $provisioned->settings['landing']['level_cards'] ?? []);
        $this->assertCount(3, $provisioned->settings['landing']['hero_slides'] ?? []);
        $this->assertStringContainsString('Little Stars Nursery', (string) $provisioned->about);
        $this->assertSame(3, Level::query()->where('school_id', $school->id)->count());
        $this->assertDatabaseHas('levels', [
            'school_id' => $school->id,
            'code' => 'baby',
            'name' => 'Baby Class',
        ]);
        $this->assertSame(
            6,
            NewsPost::query()->where('school_id', $school->id)->whereNotNull('published_at')->count()
        );
        $this->assertNotNull(
            NewsPost::query()->where('school_id', $school->id)->whereNotNull('image_url')->value('image_url')
        );
    }

    public function test_unknown_category_falls_back_to_combined(): void
    {
        $school = School::query()->create([
            'name' => 'Unity School',
            'slug' => 'unity',
            'category' => 'unknown_type',
            'status' => 'active',
        ]);

        $provisioned = app(SchoolLandingProvisioner::class)->provision($school);

        $this->assertSame('combined', $provisioned->settings['landing']['template']);
        $this->assertSame(4, Level::query()->where('school_id', $school->id)->count());
        $this->assertCount(3, $provisioned->settings['landing']['hero_slides'] ?? []);
        $this->assertSame(
            6,
            NewsPost::query()->where('school_id', $school->id)->whereNotNull('published_at')->count()
        );
    }
}
