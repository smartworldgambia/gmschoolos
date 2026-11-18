<?php

namespace Tests\Feature;

use App\Models\ClassEducator;
use App\Models\ClassSubject;
use App\Models\Level;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use App\Services\Academics\HomeroomService;
use App\Services\Onboarding\SetupWizard;
use App\Services\School\SchoolLandingProvisioner;
use App\Support\SchoolCategoryCatalog;
use App\Support\TenantContext;
use Database\Seeders\RolesAndPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EcdAcademicStructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPlansSeeder::class);
        app(TenantContext::class)->enterPlatformMode();
    }

    protected function nurserySchool(): School
    {
        $school = School::query()->create([
            'name' => 'ECD Nursery',
            'slug' => 'ecd-nursery',
            'category' => 'nursery',
            'enabled_categories' => ['nursery'],
            'status' => 'active',
            'website_published' => false,
        ]);

        return app(SchoolLandingProvisioner::class)->provision($school);
    }

    protected function primarySchool(): School
    {
        $school = School::query()->create([
            'name' => 'Primary Only',
            'slug' => 'primary-only',
            'category' => 'primary',
            'enabled_categories' => ['primary'],
            'status' => 'active',
            'website_published' => false,
        ]);

        return app(SchoolLandingProvisioner::class)->provision($school);
    }

    protected function adminFor(School $school): User
    {
        $user = User::factory()->create([
            'school_id' => $school->id,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('school_admin');

        return $user;
    }

    protected function teacherFor(School $school, string $role = 'teacher'): User
    {
        $user = User::factory()->create([
            'school_id' => $school->id,
            'email_verified_at' => now(),
        ]);
        $user->assignRole($role);

        return $user;
    }

    public function test_nursery_provision_seeds_five_ecd_levels_and_seven_learning_domains(): void
    {
        $school = $this->nurserySchool();

        $this->assertSame(5, Level::query()->where('school_id', $school->id)->count());
        $this->assertSame(
            ['baby', 'toddler', 'n1', 'n2', 'reception'],
            SchoolCategoryCatalog::allowedLevelCodes($school)
        );
        $this->assertDatabaseHas('levels', [
            'school_id' => $school->id,
            'code' => 'n1',
            'name' => 'Nursery 1',
            'age_min_months' => 48,
            'age_max_months' => 60,
        ]);
        $this->assertDatabaseHas('levels', [
            'school_id' => $school->id,
            'code' => 'reception',
            'teaching_arrangement' => 'learning_centre',
        ]);

        $domains = Subject::query()
            ->where('school_id', $school->id)
            ->where('domain_kind', 'learning_domain')
            ->orderBy('code')
            ->get();

        $this->assertCount(7, $domains);
        $this->assertSame(
            ['cog', 'creat', 'lang', 'num', 'phys', 'sel', 'world'],
            $domains->pluck('code')->all()
        );
    }

    public function test_non_nursery_academics_uses_standard_band(): void
    {
        $school = $this->primarySchool();
        $admin = $this->adminFor($school);
        app(TenantContext::class)->clear();

        $this->actingAs($admin)
            ->get(route('academics.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('academicBand', 'standard')
                ->where('levels', fn ($levels) => collect($levels)->isNotEmpty()
                    && collect($levels)->every(fn ($l) => ($l['age_min_months'] ?? null) === null)));
    }

    public function test_nursery_academics_uses_ecd_band_and_age_fields(): void
    {
        $school = $this->nurserySchool();
        $admin = $this->adminFor($school);
        app(TenantContext::class)->clear();

        $this->actingAs($admin)
            ->get(route('academics.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('academicBand', 'nursery')
                ->where('levels', fn ($levels) => collect($levels)->firstWhere('code', 'baby')['age_min_months'] === 24
                    && collect($levels)->firstWhere('code', 'baby')['age_max_months'] === 36
                    && collect($levels)->firstWhere('code', 'n1')['teaching_arrangement'] === 'activity')
                ->where('subjects', fn ($subjects) => collect($subjects)
                    ->where('domain_kind', 'learning_domain')
                    ->count() === 7));
    }

    public function test_out_of_pack_level_still_rejected_for_nursery(): void
    {
        $school = $this->nurserySchool();

        $this->expectException(ValidationException::class);
        app(SetupWizard::class)->addLevel($school, 'Grade 1', 'g1');
    }

    public function test_nursery_pack_allows_n1_and_n2_codes(): void
    {
        $school = $this->nurserySchool();
        Level::query()->where('school_id', $school->id)->whereIn('code', ['n1', 'n2'])->delete();

        $wizard = app(SetupWizard::class);
        $n1 = $wizard->addLevel($school, 'Nursery 1', 'n1');
        $n2 = $wizard->addLevel($school, 'Nursery 2', 'n2');

        $this->assertSame('n1', $n1->code);
        $this->assertSame('n2', $n2->code);
    }

    public function test_lead_educator_syncs_class_teacher_id_and_class_educators(): void
    {
        $school = $this->nurserySchool();
        $admin = $this->adminFor($school);
        $lead = $this->teacherFor($school, 'class_teacher');
        $assistant = $this->teacherFor($school, 'teacher');
        $level = Level::query()->where('school_id', $school->id)->where('code', 'baby')->firstOrFail();
        $class = SchoolClass::query()->create([
            'school_id' => $school->id,
            'level_id' => $level->id,
            'name' => 'Baby A',
            'capacity' => 20,
        ]);

        app(TenantContext::class)->clear();

        $this->actingAs($admin)->put(route('academics.classes.update', $class), [
            'name' => 'Baby A',
            'class_teacher_id' => $lead->id,
            'assistant_ids' => [$assistant->id],
            'specialist_ids' => [],
            'teaching_arrangement' => 'homeroom',
        ])->assertSessionHas('success');

        $class->refresh();
        $this->assertSame($lead->id, (int) $class->class_teacher_id);
        $this->assertDatabaseHas('class_educators', [
            'school_class_id' => $class->id,
            'user_id' => $lead->id,
            'role' => ClassEducator::LEAD,
        ]);
        $this->assertDatabaseHas('class_educators', [
            'school_class_id' => $class->id,
            'user_id' => $assistant->id,
            'role' => ClassEducator::ASSISTANT,
        ]);
    }

    public function test_homeroom_allocation_still_works_for_nursery_class(): void
    {
        $school = $this->nurserySchool();
        $admin = $this->adminFor($school);
        $lead = $this->teacherFor($school, 'class_teacher');
        $level = Level::query()->where('school_id', $school->id)->where('code', 'toddler')->firstOrFail();
        $class = SchoolClass::query()->create([
            'school_id' => $school->id,
            'level_id' => $level->id,
            'name' => 'Toddler A',
            'capacity' => 18,
        ]);
        $lang = Subject::query()->where('school_id', $school->id)->where('code', 'lang')->firstOrFail();
        ClassSubject::query()->create([
            'school_id' => $school->id,
            'school_class_id' => $class->id,
            'subject_id' => $lang->id,
            'teacher_id' => null,
        ]);

        app(TenantContext::class)->clear();

        $this->actingAs($admin)->put(route('academics.classes.update', $class), [
            'name' => 'Toddler A',
            'class_teacher_id' => $lead->id,
        ])->assertSessionHas('success');

        $this->assertSame(
            $lead->id,
            (int) ClassSubject::query()->where('subject_id', $lang->id)->where('school_class_id', $class->id)->value('teacher_id')
        );
        $this->assertSame(
            HomeroomService::HOMEROOM,
            app(HomeroomService::class)->modelFor($level->fresh())
        );
    }

    public function test_level_arrangement_maps_to_teaching_model(): void
    {
        $school = $this->nurserySchool();
        $admin = $this->adminFor($school);
        $level = Level::query()->where('school_id', $school->id)->where('code', 'n1')->firstOrFail();

        app(TenantContext::class)->clear();

        $this->actingAs($admin)->put(route('academics.levels.update', $level), [
            'name' => $level->name,
            'code' => $level->code,
            'teaching_arrangement' => 'specialist',
        ])->assertSessionHas('success');

        $level->refresh();
        $this->assertSame('specialist', $level->teaching_arrangement);
        $this->assertSame(HomeroomService::SPECIALIST, $level->teaching_model);

        $this->actingAs($admin)->put(route('academics.levels.update', $level), [
            'name' => $level->name,
            'code' => $level->code,
            'teaching_arrangement' => 'learning_centre',
        ])->assertSessionHas('success');

        $level->refresh();
        $this->assertSame('learning_centre', $level->teaching_arrangement);
        $this->assertSame(HomeroomService::HOMEROOM, $level->teaching_model);
    }
}
