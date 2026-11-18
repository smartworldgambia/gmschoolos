<?php

namespace Tests\Feature;

use App\Models\ClassEducator;
use App\Models\Level;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\RolesAndPlansSeeder;
use Database\Seeders\SmartworldNurserySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmartworldNurserySeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPlansSeeder::class);
        app(TenantContext::class)->enterPlatformMode();
    }

    public function test_seeds_smartworld_nursery_without_demo_wording(): void
    {
        $this->seed(SmartworldNurserySeeder::class);
        app(TenantContext::class)->enterPlatformMode();

        $school = School::query()->where('slug', 'smartworld')->first();
        $this->assertNotNull($school);
        $this->assertSame('Smartworld Nursery', $school->name);
        $this->assertSame('nursery', $school->category);
        $this->assertSame(['nursery'], $school->enabled_categories);
        $this->assertStringNotContainsStringIgnoringCase('demo', (string) $school->name);
        $this->assertStringNotContainsStringIgnoringCase('demo', (string) $school->about);
        $this->assertStringNotContainsStringIgnoringCase('demo', (string) ($school->settings['landing']['hero_body'] ?? ''));

        $this->assertSame(5, Level::query()->where('school_id', $school->id)->count());
        $this->assertSame(
            ['baby', 'toddler', 'n1', 'n2', 'reception'],
            Level::query()->where('school_id', $school->id)->orderBy('sort_order')->pluck('code')->all()
        );
        $this->assertSame(
            7,
            Subject::query()->where('school_id', $school->id)->where('domain_kind', 'learning_domain')->count()
        );
        $this->assertSame(5, SchoolClass::query()->where('school_id', $school->id)->count());
        $this->assertSame(14, Student::query()->where('school_id', $school->id)->where('status', 'active')->count());
        $this->assertFalse(
            Level::query()->where('school_id', $school->id)->whereIn('code', ['G1', 'g1', 'NUR', 'SSS'])->exists()
        );
        $this->assertTrue(
            ClassEducator::query()->where('school_id', $school->id)->where('role', ClassEducator::LEAD)->exists()
        );
        $admin = User::query()->where('email', 'like', 'admin@smartworld.%')->first();
        $this->assertNotNull($admin);

        app(TenantContext::class)->clear();

        $this->actingAs($admin)
            ->get(route('academics.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('academicBand', 'nursery'));
    }
}
