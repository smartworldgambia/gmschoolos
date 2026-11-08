<?php

namespace Tests\Feature;

use App\Models\ClassSubject;
use App\Models\Guardian;
use App\Models\Level;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\RolesAndPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Shared fixtures for the Phase 8 portals / communication suites. */
abstract class Phase8TestCase extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $admin;

    protected User $teacher;

    protected SchoolClass $class;

    protected SchoolClass $otherClass;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPlansSeeder::class);
        $this->school = School::query()->create(['name' => 'Alpha School', 'slug' => 'alpha', 'status' => 'active', 'website_published' => true]);
        app(TenantContext::class)->set($this->school);
        $this->admin = $this->user('school_admin');
        $this->teacher = $this->user('teacher');
        $this->class = $this->classNamed('Grade 10A');
        $this->otherClass = $this->classNamed('Grade 11B');
        $this->allocate($this->teacher, $this->subject('Mathematics'), $this->class);
    }

    protected function user(string $role, ?School $school = null, array $over = []): User
    {
        $user = User::factory()->create($over + ['school_id' => ($school ?? $this->school)->id, 'email_verified_at' => now()]);
        $user->assignRole($role);

        return $user;
    }

    protected function classNamed(string $name, ?School $school = null): SchoolClass
    {
        $school ??= $this->school;
        $level = Level::query()->withoutGlobalScopes()->firstOrCreate(['school_id' => $school->id, 'name' => 'Level '.$name], ['code' => 'L'.substr(md5($name), 0, 4), 'sort_order' => 1]);

        return SchoolClass::query()->withoutGlobalScopes()->create(['school_id' => $school->id, 'level_id' => $level->id, 'name' => $name, 'capacity' => 60]);
    }

    protected function subject(string $name): Subject
    {
        return Subject::query()->create(['school_id' => $this->school->id, 'name' => $name, 'code' => strtoupper(substr($name, 0, 3))]);
    }

    protected function allocate(User $teacher, Subject $subject, SchoolClass $class): void
    {
        ClassSubject::query()->create(['school_id' => $this->school->id, 'school_class_id' => $class->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'periods_per_week' => 3]);
    }

    protected function otherSchool(string $slug = 'beta'): School
    {
        return School::query()->create(['name' => 'Beta School', 'slug' => $slug, 'status' => 'active', 'website_published' => true]);
    }

    /** A learner record, optionally with a login. */
    protected function student(?SchoolClass $class = null, bool $login = false, string $first = 'Awa', ?School $school = null): Student
    {
        static $n = 0;
        $n++;
        $school ??= $this->school;
        $userId = $login ? $this->user('student', $school)->id : null;

        return Student::query()->withoutGlobalScopes()->create([
            'school_id' => $school->id, 'user_id' => $userId, 'student_number' => 'STU-8-'.$n, 'first_name' => $first.$n, 'last_name' => 'Jallow',
            'gender' => 'female', 'date_of_birth' => '2010-01-01', 'school_class_id' => ($class ?? $this->class)->id, 'status' => 'active',
        ]);
    }

    /** A guardian record linked to the given children; with a login when $login is true. */
    protected function guardian(array $children, bool $login = true, ?string $phone = null): Guardian
    {
        static $n = 0;
        $n++;
        $school = $this->school;
        $user = $login ? $this->user('parent', $school, ['phone' => $phone]) : null;

        $guardian = Guardian::query()->withoutGlobalScopes()->create([
            'school_id' => $school->id, 'user_id' => $user?->id, 'first_name' => 'Mariama'.$n, 'last_name' => 'Jallow',
            'relationship' => 'mother', 'phone' => $phone, 'email' => 'guardian'.$n.'@alpha.test',
        ]);

        foreach ($children as $child) {
            $guardian->students()->attach($child->id, ['is_primary' => true, 'school_id' => $school->id]);
        }

        return $guardian;
    }

    /**
     * Change the school's settings the way an admin would. Users created earlier in the test hold a cached
     * `school` relation, which a real request never does, so drop it.
     */
    protected function setSchoolSettings(array $settings): void
    {
        $this->school->forceFill(['settings' => array_replace_recursive((array) $this->school->settings, $settings)])->save();

        foreach ((new \ReflectionObject($this))->getProperties() as $property) {
            $property->setAccessible(true);
            if ($property->isInitialized($this) && $property->getValue($this) instanceof User) {
                $property->getValue($this)->unsetRelation('school');
            }
        }
    }

    /** Act as another user; the real middleware must bind the tenant itself. */
    protected function actAs(User $user): static
    {
        app(TenantContext::class)->clear();

        return $this->actingAs($user);
    }

    /** Run something as the school (the tenant is cleared by actAs). */
    protected function inSchool(callable $fn, ?School $school = null): mixed
    {
        return app(TenantContext::class)->runForSchool($school ?? $this->school, $fn);
    }
}
