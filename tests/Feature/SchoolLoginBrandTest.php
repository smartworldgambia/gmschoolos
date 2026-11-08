<?php

namespace Tests\Feature;

use App\Models\School;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SchoolLoginBrandTest extends TestCase
{
    use RefreshDatabase;

    protected function makeSchool(array $overrides = []): School
    {
        $school = School::query()->create(array_merge([
            'name' => 'Brand School',
            'slug' => 'brand-school',
            'status' => 'active',
            'website_published' => true,
            'primary_color' => '#1A2B56',
            'accent_color' => '#1683F8',
        ], $overrides));
        app(TenantContext::class)->clear();

        return $school;
    }

    public function test_platform_login_stays_plain_without_a_school(): void
    {
        $this->makeSchool();

        $this->get('/login')->assertInertia(
            fn (Assert $page) => $page->component('Auth/Login')->where('school', null)
        );
    }

    public function test_login_opened_from_a_school_site_carries_that_school_brand(): void
    {
        $school = $this->makeSchool();

        $this->get('/login?school='.$school->slug)->assertInertia(
            fn (Assert $page) => $page
                ->component('Auth/Login')
                ->where('school.name', 'Brand School')
                ->where('school.primary_color', '#1A2B56')
                ->where('school.home_url', route('school.site', ['school_slug' => $school->slug]))
        );
    }

    public function test_unknown_or_unpublished_school_falls_back_to_plain_login(): void
    {
        $this->makeSchool(['slug' => 'hidden', 'website_published' => false]);

        $this->get('/login?school=hidden')->assertInertia(fn (Assert $page) => $page->where('school', null));
        $this->get('/login?school=nope')->assertInertia(fn (Assert $page) => $page->where('school', null));
    }

    public function test_school_site_login_links_carry_the_school_slug(): void
    {
        $school = $this->makeSchool();

        $this->get('/sites/'.$school->slug)->assertInertia(
            fn (Assert $page) => $page
                ->where('loginUrl', route('login', ['school' => $school->slug]))
                ->where('admissionsUrl', route('school.apply', ['school_slug' => $school->slug]))
        );
    }
}
