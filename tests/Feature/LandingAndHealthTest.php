<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use Database\Seeders\RolesAndPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingAndHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_saas_landing_renders_with_annual_prices(): void
    {
        $this->seed(RolesAndPlansSeeder::class);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Marketing/Landing')
                ->has('prices')
                ->where('statsMode', 'live')
                ->has('stats.schools')
                ->has('stats.students')
                ->has('stats.staff')
                ->has('stats.parents')
                ->has('stats.classes')
                ->has('illustrativeStats')
                ->where('trialDays', (int) config('schoolos.trial_days', 30))
                ->has('marketing.hero_image')
                ->has('marketing.hero_slides', 3)
                ->has('seo.title')
            );
    }

    public function test_landing_stats_use_live_production_counts(): void
    {
        $this->seed(RolesAndPlansSeeder::class);

        $school = School::query()->create([
            'name' => 'Live Stats School',
            'slug' => 'live-stats-school',
            'status' => 'active',
        ]);

        Student::query()->create([
            'school_id' => $school->id,
            'student_number' => 'STU-LIVE-1',
            'first_name' => 'Awa',
            'last_name' => 'Ceesay',
            'status' => 'active',
        ]);

        Staff::query()->create([
            'school_id' => $school->id,
            'staff_number' => 'STF-LIVE-1',
            'first_name' => 'Lamin',
            'last_name' => 'Sowe',
            'status' => 'active',
        ]);

        Guardian::query()->create([
            'school_id' => $school->id,
            'first_name' => 'Fatou',
            'last_name' => 'Jallow',
        ]);

        SchoolClass::query()->create([
            'school_id' => $school->id,
            'name' => 'Grade 7A',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Marketing/Landing')
                ->where('statsMode', 'live')
                ->where('stats.schools', 1)
                ->where('stats.students', 1)
                ->where('stats.staff', 1)
                ->where('stats.parents', 1)
                ->where('stats.classes', 1)
            );
    }

    public function test_health_endpoints(): void
    {
        $this->get(route('health'))->assertOk()->assertJson(['status' => 'ok']);
        $this->get(route('ready'))->assertOk()->assertJson(['status' => 'ready']);
    }

    public function test_privacy_and_terms_pages_render(): void
    {
        $this->get(route('privacy'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Marketing/LegalPage')
                ->where('document', 'privacy')
                ->where('marketing.contact_email', 'info@gmschoolos.edu.gm')
                ->where('marketing.phone', '+220 877031891')
            );

        $this->get(route('terms'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Marketing/LegalPage')
                ->where('document', 'terms')
            );
    }

    public function test_landing_exposes_contact_details(): void
    {
        $this->seed(RolesAndPlansSeeder::class);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Marketing/Landing')
                ->where('marketing.contact_email', 'info@gmschoolos.edu.gm')
                ->where('marketing.address', 'Smartworld, Opposite Netpage, Kairaba Avenue, The Gambia')
            );
    }
}
