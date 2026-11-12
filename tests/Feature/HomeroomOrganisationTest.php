<?php

namespace Tests\Feature;

use App\Models\ClassSubject;
use App\Models\Course;
use App\Models\Level;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use App\Services\Academics\HomeroomService;
use App\Services\Academics\TeacherScope;
use App\Support\TenantContext;
use Database\Seeders\RolesAndPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Homeroom classrooms, specialist subjects, and class-teacher teaching rights. */
class HomeroomOrganisationTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $admin;

    protected Level $grade1;

    protected SchoolClass $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPlansSeeder::class);
        $this->school = School::query()->create(['name' => 'Alpha School', 'slug' => 'alpha', 'status' => 'active', 'website_published' => true]);
        app(TenantContext::class)->set($this->school);
        $this->admin = $this->user('school_admin');
        $this->grade1 = Level::query()->create(['school_id' => $this->school->id, 'name' => 'Grade 1', 'code' => 'G1', 'sort_order' => 1]);
        $this->room = SchoolClass::query()->create(['school_id' => $this->school->id, 'level_id' => $this->grade1->id, 'name' => 'Grade 1A', 'capacity' => 40]);
    }

    protected function user(string $role, ?School $school = null): User
    {
        $user = User::factory()->create(['school_id' => ($school ?? $this->school)->id, 'email_verified_at' => now()]);
        $user->assignRole($role);

        return $user;
    }

    protected function subject(string $name, string $code, bool $specialist = false): Subject
    {
        return Subject::query()->create([
            'school_id' => $this->school->id, 'name' => $name, 'code' => $code, 'is_specialist' => $specialist,
        ]);
    }

    protected function allocate(Subject $subject, ?User $teacher = null): ClassSubject
    {
        return ClassSubject::query()->create([
            'school_id' => $this->school->id, 'school_class_id' => $this->room->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher?->id,
        ]);
    }

    public function test_nursery_and_primary_default_to_homeroom_and_senior_to_specialist(): void
    {
        $this->assertSame(HomeroomService::HOMEROOM, HomeroomService::defaultModel(1, 'Grade 1', 'G1'));
        $this->assertSame(HomeroomService::HOMEROOM, HomeroomService::defaultModel(null, 'Nursery', 'N'));
        $this->assertSame(HomeroomService::HOMEROOM, $this->grade1->fresh()->teaching_model);
        $this->assertSame(HomeroomService::SPECIALIST, HomeroomService::defaultModel(10, 'Grade 10', 'G10'));
        $this->assertSame(HomeroomService::SPECIALIST, HomeroomService::defaultModel(null, 'Senior Secondary', 'SSS'));
    }

    public function test_irk_crk_and_pe_are_guessed_as_specialists(): void
    {
        $this->assertTrue(Subject::guessSpecialist('IRK', 'IRK'));
        $this->assertTrue(Subject::guessSpecialist('Christian Religious Knowledge', 'CRK'));
        $this->assertTrue(Subject::guessSpecialist('Physical Education', 'PE'));
        $this->assertFalse(Subject::guessSpecialist('English', 'ENG'));

        $this->actingAs($this->admin)->post(route('academics.subjects.store'), ['name' => 'IRK', 'code' => 'IRK'])
            ->assertSessionHas('success');
        $this->assertTrue((bool) Subject::query()->where('name', 'IRK')->value('is_specialist'));
    }

    public function test_saving_a_class_teacher_fills_unassigned_non_specialist_subjects_only(): void
    {
        $english = $this->subject('English', 'ENG');
        $pe = $this->subject('Physical Education', 'PE', true);
        $peTeacher = $this->user('teacher');
        $this->allocate($english);
        $this->allocate($pe, $peTeacher);
        $classTeacher = $this->user('class_teacher');

        $this->actingAs($this->admin)->put(route('academics.classes.update', $this->room), [
            'name' => 'Grade 1A', 'class_teacher_id' => $classTeacher->id,
        ])->assertSessionHas('success');

        $this->assertSame($classTeacher->id, (int) ClassSubject::query()->where('subject_id', $english->id)->value('teacher_id'));
        $this->assertSame($peTeacher->id, (int) ClassSubject::query()->where('subject_id', $pe->id)->value('teacher_id'));
    }

    public function test_apply_homeroom_skips_specialists_and_does_not_overwrite_a_set_teacher(): void
    {
        $maths = $this->subject('Mathematics', 'MATH');
        $science = $this->subject('Science', 'SCI');
        $irk = $this->subject('IRK', 'IRK', true);
        $scienceTeacher = $this->user('teacher');
        $classTeacher = $this->user('class_teacher');
        $this->room->update(['class_teacher_id' => $classTeacher->id]);
        $this->allocate($maths);
        $this->allocate($science, $scienceTeacher);
        $this->allocate($irk);

        $this->actingAs($this->admin)->post(route('academics.curriculum.homeroom', $this->room))
            ->assertSessionHas('success');

        $this->assertSame($classTeacher->id, (int) ClassSubject::query()->where('subject_id', $maths->id)->value('teacher_id'));
        $this->assertSame($scienceTeacher->id, (int) ClassSubject::query()->where('subject_id', $science->id)->value('teacher_id'));
        $this->assertNull(ClassSubject::query()->where('subject_id', $irk->id)->value('teacher_id'));
    }

    public function test_class_teacher_can_teach_non_specialist_subjects_in_their_homeroom(): void
    {
        $english = $this->subject('English', 'ENG');
        $pe = $this->subject('PE', 'PE', true);
        $this->allocate($english);
        $this->allocate($pe);
        $classTeacher = $this->user('class_teacher');
        $this->room->update(['class_teacher_id' => $classTeacher->id]);

        $scope = app(TeacherScope::class);
        $this->assertTrue($scope->canTeach($classTeacher, $this->room->id, $english->id));
        $this->assertFalse($scope->canTeach($classTeacher, $this->room->id, $pe->id));
        $this->assertTrue(app(HomeroomService::class)->classTeacherTeaches($classTeacher, $this->room->id, $english->id));
        $this->assertFalse(app(HomeroomService::class)->classTeacherTeaches($classTeacher, $this->room->id, $pe->id));
    }

    public function test_copying_a_homeroom_class_does_not_copy_the_source_class_teacher_onto_non_specialists(): void
    {
        $english = $this->subject('English', 'ENG');
        $pe = $this->subject('PE', 'PE', true);
        $sourceTeacher = $this->user('class_teacher');
        $peTeacher = $this->user('teacher');
        $this->room->update(['class_teacher_id' => $sourceTeacher->id]);
        $this->allocate($english, $sourceTeacher);
        $this->allocate($pe, $peTeacher);

        $other = SchoolClass::query()->create(['school_id' => $this->school->id, 'level_id' => $this->grade1->id, 'name' => 'Grade 1B', 'capacity' => 40]);
        $otherTeacher = $this->user('class_teacher');
        $other->update(['class_teacher_id' => $otherTeacher->id]);

        $this->actingAs($this->admin)->post(route('academics.curriculum.copy', $other), ['from_class_id' => $this->room->id])
            ->assertSessionHas('success');

        $this->assertSame($otherTeacher->id, (int) ClassSubject::query()->where('school_class_id', $other->id)->where('subject_id', $english->id)->value('teacher_id'));
        $this->assertSame($peTeacher->id, (int) ClassSubject::query()->where('school_class_id', $other->id)->where('subject_id', $pe->id)->value('teacher_id'));
    }

    public function test_class_teacher_sees_homeroom_lms_courses_with_subject_classroom_and_teacher(): void
    {
        $english = $this->subject('English', 'ENG');
        $pe = $this->subject('Physical Education', 'PE', true);
        $peTeacher = $this->user('teacher');
        $classTeacher = $this->user('class_teacher');
        $this->room->update(['class_teacher_id' => $classTeacher->id]);
        $this->allocate($english);
        $this->allocate($pe, $peTeacher);

        $this->actingAs($this->admin)->post(route('academics.curriculum.homeroom', $this->room))->assertSessionHas('success');

        $this->actingAs($classTeacher)->get(route('lms.courses.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->where('mode', 'author')
                ->has('courses', 1)
                ->where('courses.0.subject', 'English')
                ->where('courses.0.classroom', 'Grade 1A')
                ->where('courses.0.teacher', $classTeacher->name)
                ->where('courses.0.classes', ['Grade 1A']));

        app(TenantContext::class)->set($this->school);
        $this->assertSame(1, Course::query()->where('subject_id', $english->id)->where('school_class_id', $this->room->id)->count());
        $this->assertSame($classTeacher->id, (int) Course::query()->where('subject_id', $english->id)->value('teacher_id'));
    }

    public function test_academics_index_exposes_teaching_model_and_specialist_flags(): void
    {
        $this->subject('English', 'ENG');
        $this->subject('IRK', 'IRK', true);

        $this->actingAs($this->admin)->get(route('academics.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->where('levels.0.teaching_model', HomeroomService::HOMEROOM)
                ->where('subjects', fn ($subjects) => collect($subjects)->firstWhere('name', 'IRK')['is_specialist'] === true
                    && collect($subjects)->firstWhere('name', 'English')['is_specialist'] === false));
    }
}
