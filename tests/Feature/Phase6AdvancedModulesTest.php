<?php

namespace Tests\Feature;

use App\Models\LibraryBook;
use App\Models\ReportFact;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\RolesAndPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase6AdvancedModulesTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $admin;

    protected Staff $staff;

    protected Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPlansSeeder::class);

        $this->school = School::query()->create([
            'name' => 'Advanced School',
            'slug' => 'advanced-school',
            'status' => 'active',
        ]);
        app(TenantContext::class)->set($this->school);

        $this->admin = User::factory()->create([
            'school_id' => $this->school->id,
            'email_verified_at' => now(),
        ]);
        $this->admin->assignRole('school_admin');

        $this->staff = Staff::query()->create([
            'school_id' => $this->school->id,
            'staff_number' => 'STF-1',
            'first_name' => 'Fatou',
            'last_name' => 'Sowe',
            'status' => 'active',
        ]);

        $this->student = Student::query()->create([
            'school_id' => $this->school->id,
            'student_number' => 'STU-ADV-1',
            'first_name' => 'Lamin',
            'last_name' => 'Bah',
            'status' => 'active',
        ]);
    }

    public function test_hr_leave_and_payroll_flow(): void
    {
        $this->actingAs($this->admin)
            ->post(route('hr.leave.store'), [
                'staff_id' => $this->staff->id,
                'leave_type' => 'sick',
                'starts_on' => now()->toDateString(),
                'ends_on' => now()->addDay()->toDateString(),
                'days' => 2,
                'reason' => 'Fever',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('staff_leave_requests', [
            'staff_id' => $this->staff->id,
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin)
            ->post(route('hr.payroll.store'), [
                'period_label' => '2025-11',
                'period_start' => '2025-11-01',
                'period_end' => '2025-11-30',
                'basic_salary' => 4000,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('payroll_runs', [
            'period_label' => '2025-11',
            'status' => 'draft',
        ]);
    }

    public function test_library_loan_and_return(): void
    {
        $book = LibraryBook::query()->create([
            'school_id' => $this->school->id,
            'title' => 'English Reader',
            'copies_total' => 2,
            'copies_available' => 2,
            'status' => 'available',
        ]);

        $this->actingAs($this->admin)
            ->post(route('library.loans.store'), [
                'library_book_id' => $book->id,
                'student_id' => $this->student->id,
                'due_on' => now()->addDays(7)->toDateString(),
            ])
            ->assertRedirect();

        $this->assertSame(1, $book->fresh()->copies_available);

        $loanId = $book->loans()->first()->id;

        $this->actingAs($this->admin)
            ->post(route('library.loans.return', $loanId))
            ->assertRedirect();

        $this->assertSame(2, $book->fresh()->copies_available);
    }

    public function test_transport_boarding_and_safeguarding(): void
    {
        $this->actingAs($this->admin)
            ->post(route('transport.vehicles.store'), [
                'name' => 'Van 1',
                'capacity' => 12,
            ])
            ->assertRedirect();

        $vehicleId = $this->school->fresh()->id;
        $this->assertDatabaseHas('transport_vehicles', ['name' => 'Van 1', 'school_id' => $this->school->id]);

        $this->actingAs($this->admin)
            ->post(route('boarding.hostels.store'), [
                'name' => 'North Wing',
                'gender' => 'male',
            ])
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->post(route('safeguarding.store'), [
                'category' => 'bullying',
                'severity' => 'high',
                'student_id' => $this->student->id,
                'summary' => 'Reported peer conflict requiring follow-up.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('safeguarding_cases', [
            'school_id' => $this->school->id,
            'category' => 'bullying',
            'status' => 'open',
        ]);

        unset($vehicleId);
    }

    public function test_report_store_refresh_and_lti_registration(): void
    {
        $this->actingAs($this->admin)
            ->post(route('reports.refresh'))
            ->assertRedirect();

        $this->assertTrue(
            ReportFact::query()
                ->where('school_id', $this->school->id)
                ->where('fact_key', 'students.active')
                ->exists()
        );

        $this->actingAs($this->admin)
            ->post(route('lms.external.tools.store'), [
                'name' => 'Quiz Tool',
                'launch_url' => 'https://tools.example/launch',
                'client_id' => 'abc',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('lti_tools', [
            'school_id' => $this->school->id,
            'name' => 'Quiz Tool',
        ]);

        $this->actingAs($this->admin)
            ->get(route('lms.external.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Lms/ExternalContent')->has('tools'));
    }

    public function test_teacher_cannot_access_safeguarding(): void
    {
        $teacher = User::factory()->create([
            'school_id' => $this->school->id,
            'email_verified_at' => now(),
        ]);
        $teacher->assignRole('teacher');

        $this->actingAs($teacher)
            ->get(route('safeguarding.index'))
            ->assertForbidden();
    }
}
