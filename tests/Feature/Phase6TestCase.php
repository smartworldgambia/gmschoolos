<?php

namespace Tests\Feature;

use App\Models\ExternalExamSubject;
use App\Models\Level;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\RolesAndPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Shared fixtures for the Phase 6 suites (external exams, Senior Secondary, Records Office). */
abstract class Phase6TestCase extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPlansSeeder::class);

        $this->school = School::query()->create(['name' => 'Alpha School', 'slug' => 'alpha', 'status' => 'active', 'website_published' => true, 'address' => '1 Main Rd, Banjul', 'phone' => '+2207000000', 'email' => 'office@alpha.test']);
        app(TenantContext::class)->set($this->school);
        $this->admin = $this->user('school_admin');
    }

    protected function user(string $role, ?School $school = null): User
    {
        $user = User::factory()->create(['school_id' => ($school ?? $this->school)->id, 'email_verified_at' => now()]);
        $user->assignRole($role);

        return $user;
    }

    protected function classAt(int $grade, ?School $school = null): SchoolClass
    {
        $school ??= $this->school;
        $level = Level::query()->withoutGlobalScopes()->where('school_id', $school->id)->where('grade_number', $grade)->first()
            ?? Level::query()->withoutGlobalScopes()->create(['school_id' => $school->id, 'name' => 'Grade '.$grade, 'code' => 'G'.$grade, 'sort_order' => $grade]);

        return SchoolClass::query()->withoutGlobalScopes()->firstOrCreate(
            ['school_id' => $school->id, 'level_id' => $level->id, 'name' => 'Grade '.$grade.'A'],
            ['capacity' => 60]
        );
    }

    protected function studentAt(int $grade, array $attrs = []): Student
    {
        static $n = 0;
        $n++;

        return Student::query()->create(array_merge([
            'school_id' => $this->school->id, 'student_number' => 'STU-2026-'.str_pad((string) (100 + $n), 5, '0', STR_PAD_LEFT).'-'.$n,
            'first_name' => 'Awa'.$n, 'last_name' => 'Jallow'.$n, 'gender' => 'female', 'date_of_birth' => '2008-01-01',
            'school_class_id' => $this->classAt($grade)->id, 'status' => 'active',
        ], $attrs));
    }

    protected function subject(string $type, string $code, string $name): ExternalExamSubject
    {
        return ExternalExamSubject::query()->create(['exam_type' => $type, 'code' => $code, 'name' => $name, 'is_active' => true]);
    }

    /** Switch the acting tenant to another school (tests that act as another school's user must clear first). */
    protected function otherSchool(string $slug = 'beta'): School
    {
        return School::query()->create(['name' => 'Beta School', 'slug' => $slug, 'status' => 'active', 'website_published' => true]);
    }

    protected function actAs(User $user): static
    {
        app(TenantContext::class)->clear();

        return $this->actingAs($user);
    }
}
