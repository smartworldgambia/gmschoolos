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
        $this->assertCount(5, $provisioned->settings['landing']['level_cards'] ?? []);
        $this->assertCount(3, $provisioned->settings['landing']['hero_slides'] ?? []);
        $this->assertStringContainsString('Little Stars Nursery', (string) $provisioned->about);
        $this->assertSame(5, Level::query()->where('school_id', $school->id)->count());
        $this->assertDatabaseHas('levels', [
            'school_id' => $school->id,
            'code' => 'baby',
            'name' => 'Baby Class',
            'age_min_months' => 24,
            'age_max_months' => 36,
        ]);
        $this->assertDatabaseHas('levels', [
            'school_id' => $school->id,
            'code' => 'n1',
            'name' => 'Nursery 1',
        ]);
        $this->assertSame(
            7,
            \App\Models\Subject::query()
                ->where('school_id', $school->id)
                ->where('domain_kind', 'learning_domain')
                ->count()
        );
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
        // Combined academic structure is the union of the four exclusive packs; landing cards stay stage summaries.
        $this->assertSame(17, Level::query()->where('school_id', $school->id)->count());
        $this->assertCount(4, $provisioned->settings['landing']['level_cards'] ?? []);
        $this->assertCount(3, $provisioned->settings['landing']['hero_slides'] ?? []);
        $this->assertDatabaseHas('levels', ['school_id' => $school->id, 'code' => 'baby']);
        $this->assertDatabaseHas('levels', ['school_id' => $school->id, 'code' => 'g1']);
        $this->assertDatabaseHas('levels', ['school_id' => $school->id, 'code' => 'f1']);
        $this->assertDatabaseHas('levels', ['school_id' => $school->id, 'code' => 'f4']);
        $this->assertSame(
            6,
            NewsPost::query()->where('school_id', $school->id)->whereNotNull('published_at')->count()
        );
    }
}
