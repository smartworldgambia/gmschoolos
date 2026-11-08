<?php

namespace Tests\Feature;

use App\Jobs\ProcessStudentImportBatch;
use App\Models\AdmissionApplication;
use App\Models\Examination;
use App\Models\Grade;
use App\Models\Guardian;
use App\Models\ImportBatch;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\RolesAndPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase2MisTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPlansSeeder::class);

        $this->school = School::query()->create([
            'name' => 'Test School',
            'slug' => 'test-school',
            'status' => 'active',
        ]);

        app(TenantContext::class)->set($this->school);

        $this->admin = User::factory()->create([
            'school_id' => $this->school->id,
            'email_verified_at' => now(),
        ]);
        $this->admin->assignRole('school_admin');
    }

    public function test_admission_can_be_created_and_enrolled(): void
    {
        $class = SchoolClass::query()->create([
            'school_id' => $this->school->id,
            'name' => 'Grade 7A',
        ]);

        $this->actingAs($this->admin)->post(route('admissions.store'), [
            'first_name' => 'Kebba',
            'last_name' => 'Camara',
            'gender' => 'male',
            'date_of_birth' => '2012-03-04',
            'preferred_class_id' => $class->id,
            'guardian_name' => 'Satang Camara',
            'guardian_phone' => '+220 7000000',
        ])->assertRedirect();

        $application = AdmissionApplication::query()->first();
        $this->assertNotNull($application);

        $this->actingAs($this->admin)
            ->patch(route('admissions.status', $application), ['status' => 'accepted'])
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->patch(route('admissions.status', $application), ['status' => 'enrolled'])
            ->assertRedirect();

        $this->assertDatabaseHas('students', [
            'first_name' => 'Kebba',
            'last_name' => 'Camara',
            'school_id' => $this->school->id,
        ]);
        $this->assertSame('enrolled', $application->fresh()->status);
    }

    public function test_student_csv_import_is_queued(): void
    {
        Queue::fake();
        Storage::fake('local');

        $csv = UploadedFile::fake()->createWithContent(
            'students.csv',
            "student_number,first_name,last_name,gender,class,status\nIMP-1,Test,Learner,female,,active\n"
        );

        $this->actingAs($this->admin)
            ->post(route('students.import.store'), ['file' => $csv])
            ->assertRedirect();

        Queue::assertPushed(ProcessStudentImportBatch::class);
        $this->assertDatabaseHas('import_batches', [
            'school_id' => $this->school->id,
            'type' => 'students',
            'status' => 'pending',
        ]);
    }

    public function test_examination_publish_marks_grades_published(): void
    {
        $subject = Subject::query()->create([
            'school_id' => $this->school->id,
            'name' => 'Math',
            'code' => 'M1',
        ]);
        $student = Student::query()->create([
            'school_id' => $this->school->id,
            'student_number' => 'T-1',
            'first_name' => 'A',
            'last_name' => 'B',
            'status' => 'active',
        ]);
        $exam = Examination::query()->create([
            'school_id' => $this->school->id,
            'name' => 'Mid Term',
            'status' => 'approved',
        ]);
        Grade::query()->create([
            'school_id' => $this->school->id,
            'examination_id' => $exam->id,
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'ca_score' => 30,
            'exam_score' => 50,
            'total' => 80,
            'letter_grade' => 'A',
            'is_published' => false,
        ]);

        $this->actingAs($this->admin)
            ->post(route('examinations.publish', $exam))
            ->assertRedirect();

        $this->assertSame('published', $exam->fresh()->status);
        $this->assertTrue((bool) Grade::query()->first()->is_published);
    }

    public function test_parent_cannot_view_unrelated_student(): void
    {
        $parent = User::factory()->create([
            'school_id' => $this->school->id,
            'email_verified_at' => now(),
        ]);
        $parent->assignRole('parent');

        $guardian = Guardian::query()->create([
            'school_id' => $this->school->id,
            'user_id' => $parent->id,
            'first_name' => 'Parent',
            'last_name' => 'One',
        ]);

        $own = Student::query()->create([
            'school_id' => $this->school->id,
            'student_number' => 'OWN-1',
            'first_name' => 'Own',
            'last_name' => 'Child',
            'status' => 'active',
        ]);
        $other = Student::query()->create([
            'school_id' => $this->school->id,
            'student_number' => 'OTH-1',
            'first_name' => 'Other',
            'last_name' => 'Child',
            'status' => 'active',
        ]);
        $own->guardians()->attach($guardian->id, [
            'school_id' => $this->school->id,
            'is_primary' => true,
        ]);

        $this->actingAs($parent)->get(route('students.show', $own))->assertOk();
        $this->actingAs($parent)->get(route('students.show', $other))->assertForbidden();
    }

    public function test_fee_reconciliation_marks_payments(): void
    {
        $student = Student::query()->create([
            'school_id' => $this->school->id,
            'student_number' => 'PAY-1',
            'first_name' => 'Pay',
            'last_name' => 'Student',
            'status' => 'active',
        ]);
        $invoice = Invoice::query()->create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'invoice_number' => 'INV-R-1',
            'status' => 'paid',
            'total_amount' => 1000,
            'amount_paid' => 1000,
            'issued_at' => now(),
        ]);
        $payment = Payment::query()->create([
            'school_id' => $this->school->id,
            'invoice_id' => $invoice->id,
            'student_id' => $student->id,
            'receipt_number' => 'R-1',
            'amount' => 1000,
            'method' => 'cash',
            'paid_at' => now(),
            'is_reconciled' => false,
        ]);

        $this->actingAs($this->admin)->post(route('fees.reconciliation.store'), [
            'period_starts_on' => now()->subDays(7)->toDateString(),
            'period_ends_on' => now()->toDateString(),
            'payment_ids' => [$payment->id],
        ])->assertRedirect();

        $this->assertTrue((bool) $payment->fresh()->is_reconciled);
    }
}
