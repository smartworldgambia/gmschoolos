<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\RolesAndPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_from_another_school_cannot_be_viewed(): void
    {
        $this->seed(RolesAndPlansSeeder::class);
        Role::findOrCreate('school_admin');

        $schoolA = School::query()->create([
            'name' => 'School A',
            'slug' => 'school-a',
            'status' => 'active',
        ]);
        $schoolB = School::query()->create([
            'name' => 'School B',
            'slug' => 'school-b',
            'status' => 'active',
        ]);

        app(TenantContext::class)->set($schoolB);
        $foreignStudent = Student::query()->create([
            'school_id' => $schoolB->id,
            'student_number' => 'B-0001',
            'first_name' => 'Other',
            'last_name' => 'Student',
            'status' => 'active',
        ]);
        app(TenantContext::class)->clear();

        $admin = User::factory()->create([
            'school_id' => $schoolA->id,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('school_admin');

        $this->actingAs($admin)
            ->get(route('students.show', $foreignStudent->id))
            ->assertNotFound();
    }

    public function test_admin_dashboard_uses_scoped_student_counts(): void
    {
        $this->seed(RolesAndPlansSeeder::class);

        $schoolA = School::query()->create([
            'name' => 'School A',
            'slug' => 'school-a',
            'status' => 'active',
        ]);
        $schoolB = School::query()->create([
            'name' => 'School B',
            'slug' => 'school-b',
            'status' => 'active',
        ]);

        app(TenantContext::class)->set($schoolA);
        Student::query()->create([
            'school_id' => $schoolA->id,
            'student_number' => 'A-1',
            'first_name' => 'A',
            'last_name' => 'One',
            'status' => 'active',
        ]);
        app(TenantContext::class)->set($schoolB);
        Student::query()->create([
            'school_id' => $schoolB->id,
            'student_number' => 'B-1',
            'first_name' => 'B',
            'last_name' => 'One',
            'status' => 'active',
        ]);
        Student::query()->create([
            'school_id' => $schoolB->id,
            'student_number' => 'B-2',
            'first_name' => 'B',
            'last_name' => 'Two',
            'status' => 'active',
        ]);
        app(TenantContext::class)->clear();

        $admin = User::factory()->create([
            'school_id' => $schoolA->id,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('school_admin');

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->where('stats.students', 1)
        );
    }
}
