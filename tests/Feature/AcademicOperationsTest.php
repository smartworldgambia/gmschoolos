<?php

namespace Tests\Feature;

use App\Jobs\ProcessStudentImportBatch;
use App\Models\AcademicYear;
use App\Models\AdmissionApplication;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\ClassSubject;
use App\Models\Department;
use App\Models\Examination;
use App\Models\Grade;
use App\Models\GradeChange;
use App\Models\Guardian;
use App\Models\ImportBatch;
use App\Models\Level;
use App\Models\Period;
use App\Models\Room;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolQuota;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Models\StudentMovement;
use App\Models\Subject;
use App\Models\TeacherUnavailability;
use App\Models\Term;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Notifications\AttendanceAlertNotification;
use App\Services\Results\ResultsService;
use App\Support\TenantContext;
use Database\Seeders\RolesAndPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 5 - academic and student management: records, admissions, structure, attendance, timetable, results.
 */
class AcademicOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $admin;

    protected SchoolClass $class;

    protected SchoolClass $otherClass;

    protected Subject $maths;

    protected Subject $english;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPlansSeeder::class);

        $this->school = School::query()->create(['name' => 'Alpha School', 'slug' => 'alpha', 'status' => 'active', 'website_published' => true]);
        app(TenantContext::class)->set($this->school);

        $this->admin = $this->user('school_admin');
        $level = Level::query()->create(['school_id' => $this->school->id, 'name' => 'Grade 7', 'code' => 'G7', 'sort_order' => 1]);
        $this->class = SchoolClass::query()->create(['school_id' => $this->school->id, 'level_id' => $level->id, 'name' => 'Grade 7A', 'capacity' => 40]);
        $this->otherClass = SchoolClass::query()->create(['school_id' => $this->school->id, 'level_id' => $level->id, 'name' => 'Grade 7B', 'capacity' => 40]);
        $this->maths = Subject::query()->create(['school_id' => $this->school->id, 'name' => 'Mathematics', 'code' => 'MATH']);
        $this->english = Subject::query()->create(['school_id' => $this->school->id, 'name' => 'English', 'code' => 'ENG']);
    }

    // ------------------------------------------------------------------ helpers

    protected function user(string $role, ?School $school = null): User
    {
        $user = User::factory()->create(['school_id' => ($school ?? $this->school)->id, 'email_verified_at' => now()]);
        $user->assignRole($role);

        return $user;
    }

    protected function student(array $attrs = [], ?SchoolClass $class = null): Student
    {
        static $n = 0;
        $n++;

        return Student::query()->create(array_merge([
            'school_id' => $this->school->id,
            'student_number' => 'T-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT).'-'.uniqid(),
            'first_name' => 'First'.$n, 'last_name' => 'Last'.$n, 'gender' => 'female',
            'date_of_birth' => '2012-01-0'.(($n % 9) + 1),
            'school_class_id' => ($class ?? $this->class)->id, 'status' => 'active',
        ], $attrs));
    }

    protected function studentPayload(array $over = []): array
    {
        return array_merge([
            'first_name' => 'Awa', 'last_name' => 'Jallow', 'gender' => 'female', 'date_of_birth' => '2013-05-06',
            'school_class_id' => $this->class->id,
            'guardian' => ['name' => 'Fatou Jallow', 'relationship' => 'mother', 'phone' => '+2207000001', 'email' => 'fatou@example.test'],
        ], $over);
    }

    protected function allocate(SchoolClass $class, Subject $subject, ?User $teacher = null): ClassSubject
    {
        return ClassSubject::query()->create([
            'school_id' => $this->school->id, 'school_class_id' => $class->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher?->id,
        ]);
    }

    protected function classTeacher(): User
    {
        $teacher = $this->user('class_teacher');
        $this->class->update(['class_teacher_id' => $teacher->id]);

        return $teacher;
    }

    protected function otherSchool(): School
    {
        return School::query()->create(['name' => 'Beta School', 'slug' => 'beta', 'status' => 'active']);
    }

    // ================================================================== student records

    public function test_student_registration_creates_a_permanent_unique_number_and_guardian(): void
    {
        $this->actingAs($this->admin)->post(route('students.store'), $this->studentPayload())->assertRedirect();
        $this->actingAs($this->admin)->post(route('students.store'), $this->studentPayload(['first_name' => 'Binta', 'date_of_birth' => '2012-02-02', 'guardian' => ['name' => 'Fatou Jallow', 'phone' => '+2207000001']]))->assertRedirect();

        $numbers = Student::query()->orderBy('id')->pluck('student_number');
        $this->assertCount(2, $numbers);
        $this->assertCount(2, $numbers->unique());
        $this->assertMatchesRegularExpression('/^STU-\d{4}-\d{5}$/', $numbers[0]);
        $this->assertSame(1, Guardian::query()->count(), 'siblings with the same guardian phone share one guardian record');

        // The number is permanent: an update can never change it.
        $student = Student::query()->first();
        $this->actingAs($this->admin)->put(route('students.update', $student), array_merge($this->studentPayload(), ['student_number' => 'HACKED-1', 'first_name' => 'Awa2']))->assertRedirect();
        $this->assertSame($numbers[0], $student->fresh()->student_number);
        $this->assertSame('Awa2', $student->fresh()->first_name);
    }

    public function test_duplicate_students_are_blocked_unless_confirmed(): void
    {
        $this->actingAs($this->admin)->post(route('students.store'), $this->studentPayload())->assertRedirect();
        $this->actingAs($this->admin)->post(route('students.store'), $this->studentPayload())->assertSessionHas('error', 'err.student.duplicate');
        $this->assertSame(1, Student::query()->count());

        $this->actingAs($this->admin)->post(route('students.store'), $this->studentPayload(['confirm_duplicate' => true]))->assertSessionHas('success');
        $this->assertSame(2, Student::query()->count());
    }

    public function test_student_quota_and_class_capacity_are_enforced(): void
    {
        $this->class->update(['capacity' => 1]);
        $this->actingAs($this->admin)->post(route('students.store'), $this->studentPayload())->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('students.store'), $this->studentPayload(['first_name' => 'Other', 'date_of_birth' => '2011-01-01']))->assertSessionHas('error', 'err.class.full');

        $this->class->update(['capacity' => 40]);
        SchoolQuota::query()->updateOrCreate(['school_id' => $this->school->id], ['max_students' => 1]);
        $this->actingAs($this->admin)->post(route('students.store'), $this->studentPayload(['first_name' => 'Third', 'date_of_birth' => '2011-02-02']))->assertSessionHas('error', 'err.quota.students');
    }

    public function test_student_cannot_be_registered_into_another_schools_class(): void
    {
        $beta = $this->otherSchool();
        $foreign = app(TenantContext::class)->runForSchool($beta, fn () => SchoolClass::query()->create(['school_id' => $beta->id, 'name' => 'Beta 1']));

        $this->actingAs($this->admin)->post(route('students.store'), $this->studentPayload(['school_class_id' => $foreign->id]))
            ->assertSessionHasErrors('school_class_id');
        $this->assertSame(0, Student::query()->count());
    }

    public function test_sensitive_fields_are_hidden_without_permission(): void
    {
        $student = $this->student(['medical_notes' => 'Asthma', 'welfare_notes' => 'Needs support']);
        $teacher = $this->classTeacher(); // class_teacher holds students.view_sensitive
        $hod = $this->user('teacher');
        $this->allocate($this->class, $this->maths, $hod);

        $this->actingAs($teacher)->get(route('students.show', $student))->assertOk()
            ->assertInertia(fn ($p) => $p->where('sensitive.medical_notes', 'Asthma'));

        $this->actingAs($hod)->get(route('students.show', $student))->assertOk()
            ->assertInertia(fn ($p) => $p->where('sensitive', null));
        $this->assertStringNotContainsString('Asthma', $this->actingAs($hod)->get(route('students.show', $student))->getContent());
    }

    public function test_documents_are_private_validated_and_audited(): void
    {
        Storage::fake('local');
        $student = $this->student();

        $this->actingAs($this->admin)->post(route('students.documents.store', $student), ['type' => 'birth_certificate', 'file' => UploadedFile::fake()->create('birth.pdf', 100, 'application/pdf')])
            ->assertSessionHas('success', 'ok.documentUploaded');
        $this->actingAs($this->admin)->post(route('students.documents.store', $student), ['type' => 'other', 'file' => UploadedFile::fake()->create('virus.php', 10, 'text/x-php')])
            ->assertSessionHasErrors('file');

        $doc = StudentDocument::query()->first();
        $this->assertNotNull($doc);
        $this->assertStringNotContainsString('birth', $doc->path, 'stored under a random name');
        Storage::disk('local')->assertExists($doc->path);

        $this->actingAs($this->admin)->get(route('students.documents.show', [$student, $doc]))->assertOk();
        $this->assertDatabaseHas('activity_logs', ['event' => 'student_document_viewed']);

        $parent = $this->user('parent');
        Guardian::query()->create(['school_id' => $this->school->id, 'user_id' => $parent->id, 'first_name' => 'P', 'last_name' => 'Q'])->students()->attach($student->id, ['school_id' => $this->school->id]);
        $this->actingAs($parent)->get(route('students.documents.show', [$student, $doc]))->assertForbidden();
        $this->actingAs($this->user('teacher'))->get(route('students.documents.show', [$student, $doc]))->assertForbidden();
    }

    public function test_teacher_sees_only_students_of_their_own_classes(): void
    {
        $mine = $this->student(['first_name' => 'Mine']);
        $notMine = $this->student(['first_name' => 'Theirs'], $this->otherClass);
        $teacher = $this->classTeacher();

        $this->actingAs($teacher)->get(route('students.index'))->assertOk()
            ->assertInertia(fn ($p) => $p->has('students.data', 1)->where('students.data.0.full_name', $mine->full_name));
        $this->actingAs($teacher)->get(route('students.show', $notMine))->assertForbidden();
        $this->actingAs($this->admin)->get(route('students.index'))->assertInertia(fn ($p) => $p->has('students.data', 2));
    }

    public function test_parent_sees_only_their_children_and_students_never_get_admin_rights(): void
    {
        $child = $this->student();
        $stranger = $this->student();
        $parent = $this->user('parent');
        Guardian::query()->create(['school_id' => $this->school->id, 'user_id' => $parent->id, 'first_name' => 'P', 'last_name' => 'Q'])->students()->attach($child->id, ['school_id' => $this->school->id]);

        $this->actingAs($parent)->get(route('students.show', $child))->assertOk();
        $this->actingAs($parent)->get(route('students.show', $stranger))->assertForbidden();

        $learner = $this->user('student');
        foreach (['students.create', 'students.import', 'academics.manage', 'exams.manage', 'attendance.record', 'results.publish', 'timetable.manage'] as $permission) {
            $this->assertFalse($learner->can($permission), "student must not hold {$permission}");
        }
        $this->actingAs($learner)->post(route('students.store'), $this->studentPayload())->assertForbidden();
    }

    // ================================================================== admissions

    protected function applicationPayload(array $over = []): array
    {
        return array_merge([
            'first_name' => 'Kebba', 'last_name' => 'Ceesay', 'gender' => 'male', 'date_of_birth' => '2014-04-04',
            'preferred_class_id' => $this->class->id, 'guardian_name' => 'Isatou Ceesay', 'guardian_phone' => '+2207111111',
            'guardian_email' => 'isatou@example.test',
        ], $over);
    }

    public function test_public_application_creates_only_a_reviewable_application(): void
    {
        Notification::fake();
        Storage::fake('local');
        $url = route('school.apply.store', ['school_slug' => 'alpha']);

        $this->get(route('school.apply', ['school_slug' => 'alpha']))->assertOk();

        $this->post($url, $this->applicationPayload(['consent' => 1, 'documents' => [['type' => 'birth_certificate', 'file' => UploadedFile::fake()->create('b.pdf', 50, 'application/pdf')]]]))
            ->assertSessionHas('applied');

        $app = AdmissionApplication::query()->firstOrFail();
        $this->assertSame('submitted', $app->status);
        $this->assertSame('online', $app->source);
        $this->assertSame(0, Student::query()->count());
        $this->assertSame(1, $app->documents()->count());
        $this->assertSame(0, User::query()->where('email', 'isatou@example.test')->count());
    }

    public function test_public_application_requires_consent_blocks_bots_and_repeat_submissions(): void
    {
        Notification::fake();
        $url = route('school.apply.store', ['school_slug' => 'alpha']);

        $this->post($url, $this->applicationPayload())->assertSessionHasErrors('consent');
        $this->assertSame(0, AdmissionApplication::query()->count());

        $this->post($url, $this->applicationPayload(['consent' => 1, 'company_website' => 'http://spam.test']));
        $this->assertSame(0, AdmissionApplication::query()->count(), 'honeypot');

        $this->post($url, $this->applicationPayload(['consent' => 1]));
        $this->post($url, $this->applicationPayload(['consent' => 1]));
        $this->assertSame(1, AdmissionApplication::query()->count(), 'same child + guardian within 30 days is stored once');
    }

    public function test_public_application_is_unavailable_for_unpublished_or_unknown_schools(): void
    {
        $this->school->update(['website_published' => false]);
        $this->get(route('school.apply', ['school_slug' => 'alpha']))->assertNotFound();
        $this->get(route('school.apply', ['school_slug' => 'nope']))->assertNotFound();
    }

    public function test_admission_workflow_review_waitlist_reject_and_enrol(): void
    {
        $this->actingAs($this->admin)->post(route('admissions.store'), $this->applicationPayload())->assertRedirect();
        $this->actingAs($this->admin)->post(route('admissions.store'), $this->applicationPayload(['first_name' => 'Lamin', 'guardian_phone' => '+2207222222']))->assertRedirect();
        [$a, $b] = AdmissionApplication::query()->orderBy('id')->get()->all();
        $this->assertSame('walk_in', $a->source);

        // enrolment is only possible from "accepted"
        $this->actingAs($this->admin)->patch(route('admissions.status', $a), ['status' => 'enrolled'])->assertSessionHas('error');
        $this->assertSame(0, Student::query()->count());

        // rejection needs a reason
        $this->actingAs($this->admin)->patch(route('admissions.status', $a), ['status' => 'rejected'])->assertSessionHas('error', 'err.admission.reasonRequired');
        $this->actingAs($this->admin)->patch(route('admissions.status', $b), ['status' => 'waitlisted'])->assertSessionHas('success');
        $this->assertSame(1, $b->fresh()->waitlist_position);

        $this->actingAs($this->admin)->patch(route('admissions.status', $a), ['status' => 'accepted'])->assertSessionHas('success');
        $this->actingAs($this->admin)->patch(route('admissions.status', $a), ['status' => 'enrolled', 'school_class_id' => $this->class->id])->assertSessionHas('success');

        $student = Student::query()->firstOrFail();
        $this->assertSame('Kebba', $student->first_name);
        $this->assertSame($this->class->id, $student->school_class_id);
        $this->assertSame($student->id, $a->fresh()->student_id);
        $this->assertSame(1, Guardian::query()->count());
        $this->assertTrue(StudentMovement::query()->where('student_id', $student->id)->where('event', 'enrolled')->exists());

        // enrolling again must not create a second learner
        $this->actingAs($this->admin)->patch(route('admissions.status', $a), ['status' => 'enrolled'])->assertSessionHas('error');
        $this->assertSame(1, Student::query()->count());
    }

    public function test_admissions_are_restricted_to_authorised_staff_and_the_school(): void
    {
        $app = AdmissionApplication::query()->create(['school_id' => $this->school->id, 'application_number' => 'ADM-1', 'first_name' => 'A', 'last_name' => 'B', 'status' => 'submitted']);
        $teacher = $this->user('teacher');
        $this->actingAs($teacher)->get(route('admissions.index'))->assertForbidden();
        $this->actingAs($teacher)->patch(route('admissions.status', $app), ['status' => 'accepted'])->assertForbidden();

        $betaAdmin = $this->user('school_admin', $this->otherSchool());
        app(TenantContext::class)->clear(); // a request carries no tenant of its own; the user's school binds it
        $this->actingAs($betaAdmin)->get(route('admissions.show', $app->id))->assertNotFound();
    }

    // ================================================================== CSV import

    public function test_csv_import_reports_invalid_and_duplicate_rows_and_never_overwrites(): void
    {
        Storage::fake('local');
        $existing = $this->student(['first_name' => 'Musa', 'last_name' => 'Barry', 'date_of_birth' => '2012-03-03']);

        $csv = implode("\n", [
            'student_number,first_name,last_name,gender,date_of_birth,class,guardian_name,guardian_phone',
            ',Good,Learner,female,2012-05-05,Grade 7A,Mum Learner,+2207333333',            // ok
            ',Musa,Barry,male,2012-03-03,Grade 7A,,',                                       // duplicate of an existing learner
            ',Good,Learner,female,2012-05-05,Grade 7A,,',                                   // duplicate inside the file
            ',,NoFirst,female,2012-05-05,Grade 7A,,',                                       // missing name
            ',Bad,Gender,robot,2012-05-05,Grade 7A,,',                                      // bad gender
            ',Bad,Date,male,05/05/2012,Grade 7A,,',                                         // non-ISO date
            ',No,Class,male,2012-05-05,Grade 99,,',                                         // unknown class
            $existing->student_number.',Taken,Number,male,2011-01-01,Grade 7A,,',          // number already used
        ]);
        $path = 'imports/test.csv';
        Storage::disk('local')->put($path, $csv);
        $batch = ImportBatch::query()->create(['school_id' => $this->school->id, 'type' => 'students', 'status' => 'pending', 'storage_path' => $path, 'original_filename' => 'test.csv', 'uploaded_by' => $this->admin->id]);

        ProcessStudentImportBatch::dispatchSync($batch->id, $this->school->id);
        app(TenantContext::class)->set($this->school); // queue events wipe the tenant after every job (by design)

        $batch = $batch->fresh();
        $this->assertSame(2, Student::query()->count(), 'one existing + exactly one valid new row: '.json_encode($batch->errors).$batch->status);
        $this->assertSame('Good', Student::query()->where('first_name', 'Good')->value('first_name'));
        $this->assertSame('Musa', $existing->fresh()->first_name, 'existing record not overwritten');
        $this->assertSame($existing->student_number, $existing->fresh()->student_number);
        $errors = collect($batch->errors ?? []);
        $this->assertGreaterThanOrEqual(6, $errors->count(), 'every rejected row is reported');
        $blob = json_encode($errors->all());
        foreach (['err.import.duplicateInFile', 'err.import.nameRequired', 'err.import.gender', 'err.import.dob', 'err.import.classUnknown', 'err.import.numberExists'] as $code) {
            $this->assertStringContainsString($code, $blob, "{$code} reported");
        }
    }

    public function test_csv_import_upload_requires_permission(): void
    {
        $this->actingAs($this->user('teacher'))->post(route('students.import.store'), ['file' => UploadedFile::fake()->create('a.csv', 1, 'text/csv')])->assertForbidden();
    }

    // ================================================================== movements

    public function test_promotion_repeat_withdrawal_and_graduation_keep_history(): void
    {
        $a = $this->student();
        $b = $this->student();
        $c = $this->student();

        $this->actingAs($this->admin)->post(route('academics.promotion.store'), ['action' => 'promote', 'from_class_id' => $this->class->id, 'to_class_id' => $this->otherClass->id, 'student_ids' => [$a->id]])->assertSessionHas('success');
        $this->assertSame($this->otherClass->id, $a->fresh()->school_class_id);
        $this->assertSame('promoted', StudentMovement::query()->where('student_id', $a->id)->latest('id')->value('event'));

        $this->actingAs($this->admin)->post(route('academics.promotion.store'), ['action' => 'repeat', 'from_class_id' => $this->class->id, 'student_ids' => [$b->id]])->assertSessionHas('success');
        $this->assertSame($this->class->id, $b->fresh()->school_class_id);

        $this->actingAs($this->admin)->post(route('students.move', $c), ['action' => 'withdraw'])->assertSessionHas('error', 'err.movement.reasonRequired');
        $this->actingAs($this->admin)->post(route('students.move', $c), ['action' => 'withdraw', 'reason' => 'Family moved'])->assertSessionHas('success');
        $this->assertSame('withdrawn', $c->fresh()->status);
        $this->assertNotNull($c->fresh()->withdrawn_on);
        $this->assertDatabaseHas('students', ['id' => $c->id]); // nothing is deleted
        $this->assertTrue(StudentMovement::query()->where('student_id', $c->id)->where('event', 'withdrawn')->exists());

        // a withdrawn learner is no longer eligible for promotion
        $this->actingAs($this->admin)->post(route('academics.promotion.store'), ['action' => 'promote', 'from_class_id' => $this->class->id, 'to_class_id' => $this->otherClass->id, 'student_ids' => [$c->id]])->assertSessionHas('error', 'err.movement.noneEligible');
    }

    public function test_promotion_cannot_use_another_schools_class_and_respects_capacity(): void
    {
        $a = $this->student();
        $beta = $this->otherSchool();
        $foreign = app(TenantContext::class)->runForSchool($beta, fn () => SchoolClass::query()->create(['school_id' => $beta->id, 'name' => 'Beta 1']));
        $this->actingAs($this->admin)->post(route('academics.promotion.store'), ['action' => 'promote', 'from_class_id' => $this->class->id, 'to_class_id' => $foreign->id, 'student_ids' => [$a->id]])->assertSessionHasErrors('to_class_id');

        $this->otherClass->update(['capacity' => 1]);
        $this->student([], $this->otherClass);
        $this->actingAs($this->admin)->post(route('academics.promotion.store'), ['action' => 'promote', 'from_class_id' => $this->class->id, 'to_class_id' => $this->otherClass->id, 'student_ids' => [$a->id]])->assertSessionHas('error', 'err.class.full');
        $this->assertSame($this->class->id, $a->fresh()->school_class_id);
    }

    // ================================================================== academic structure, calendar, curriculum

    public function test_calendar_validates_years_and_terms_without_hard_coded_dates(): void
    {
        $payload = [
            'name' => '2030/31', 'starts_on' => '2030-09-01', 'ends_on' => '2031-07-15', 'make_current' => true,
            'terms' => [
                ['name' => 'Term 1', 'starts_on' => '2030-09-01', 'ends_on' => '2030-12-15'],
                ['name' => 'Term 2', 'starts_on' => '2031-01-05', 'ends_on' => '2031-04-01'],
                ['name' => 'Term 3', 'starts_on' => '2031-04-20', 'ends_on' => '2031-07-15'],
            ],
        ];
        $this->actingAs($this->admin)->post(route('academics.years.store'), $payload)->assertSessionHas('success', 'ok.yearCreated');
        $year = AcademicYear::query()->where('name', '2030/31')->firstOrFail();
        $this->assertTrue($year->is_current);
        $this->assertSame(3, $year->terms()->count());

        // overlapping terms are refused and nothing is saved
        $bad = $payload;
        $bad['name'] = '2040/41';
        $bad['starts_on'] = '2040-09-01';
        $bad['ends_on'] = '2041-07-15';
        $bad['terms'] = [
            ['name' => 'T1', 'starts_on' => '2040-09-01', 'ends_on' => '2040-12-20'],
            ['name' => 'T2', 'starts_on' => '2040-12-01', 'ends_on' => '2041-03-01'],
        ];
        $this->actingAs($this->admin)->post(route('academics.years.store'), $bad)->assertSessionHas('error', 'err.term.overlap');
        $this->assertSame(0, AcademicYear::query()->where('name', '2040/41')->count());

        // term outside the year
        $bad['terms'] = [['name' => 'T1', 'starts_on' => '2040-08-01', 'ends_on' => '2040-12-20']];
        $this->actingAs($this->admin)->post(route('academics.years.store'), $bad)->assertSessionHas('error', 'err.term.outside');

        // only one current term
        $terms = $year->terms()->orderBy('starts_on')->get();
        $this->actingAs($this->admin)->post(route('academics.terms.current', $terms[1]))->assertSessionHas('success');
        $this->assertSame(1, Term::query()->where('is_current', true)->count());
        $this->assertTrue($terms[1]->fresh()->is_current);

        $this->actingAs($this->user('teacher'))->post(route('academics.years.store'), $payload)->assertForbidden();
    }

    public function test_curriculum_and_teacher_allocation_are_validated_per_school(): void
    {
        $teacher = $this->user('teacher');
        $beta = $this->otherSchool();
        $foreignTeacher = $this->user('teacher', $beta);

        $this->actingAs($this->admin)->put(route('academics.curriculum.save', $this->class), ['rows' => [
            ['subject_id' => $this->maths->id, 'teacher_id' => $teacher->id, 'periods_per_week' => 5],
            ['subject_id' => $this->english->id, 'teacher_id' => null],
        ]])->assertSessionHas('success');
        $this->assertSame(2, ClassSubject::query()->where('school_class_id', $this->class->id)->count());

        $this->actingAs($this->admin)->put(route('academics.curriculum.save', $this->class), ['rows' => [
            ['subject_id' => $this->maths->id, 'teacher_id' => $foreignTeacher->id],
        ]])->assertSessionHasErrors('rows.0.teacher_id');

        $this->actingAs($this->admin)->put(route('academics.curriculum.save', $this->class), ['rows' => [
            ['subject_id' => $this->maths->id], ['subject_id' => $this->maths->id],
        ]])->assertSessionHas('error', 'err.curriculum.duplicate');

        // a subject that already has results cannot be dropped from a class
        $exam = Examination::query()->create(['school_id' => $this->school->id, 'name' => 'E', 'status' => 'draft']);
        $student = $this->student();
        Grade::query()->create(['school_id' => $this->school->id, 'examination_id' => $exam->id, 'student_id' => $student->id, 'subject_id' => $this->english->id, 'total' => 50]);
        $this->actingAs($this->admin)->put(route('academics.curriculum.save', $this->class), ['rows' => [['subject_id' => $this->maths->id]]])->assertSessionHas('error', 'err.curriculum.inUse');

        $this->actingAs($teacher)->put(route('academics.curriculum.save', $this->class), ['rows' => []])->assertForbidden();
        $this->actingAs($teacher)->get(route('academics.allocation'))->assertOk();
    }

    public function test_department_head_and_structure_changes_are_permissioned(): void
    {
        $head = $this->user('head_of_department');
        $this->actingAs($this->admin)->post(route('academics.departments.store'), ['name' => 'Sciences'])->assertSessionHasNoErrors();
        $dept = Department::query()->where('name', 'Sciences')->firstOrFail();
        $this->actingAs($this->admin)->put(route('academics.departments.update', $dept), ['name' => 'Sciences', 'head_user_id' => $head->id])->assertSessionHas('success');
        $this->assertSame($head->id, $dept->fresh()->head_user_id);

        $foreign = $this->user('teacher', $this->otherSchool());
        $this->actingAs($this->admin)->put(route('academics.departments.update', $dept), ['name' => 'Sciences', 'head_user_id' => $foreign->id])->assertSessionHasErrors('head_user_id');
        $this->assertSame($head->id, $dept->fresh()->head_user_id);

        $this->actingAs($this->user('teacher'))->post(route('academics.subjects.store'), ['name' => 'Hack', 'code' => 'H'])->assertForbidden();
    }

    // ================================================================== attendance

    protected function register(User $user, array $entries, array $over = [])
    {
        return $this->actingAs($user)->post(route('attendance.store'), array_merge([
            'class_id' => $this->class->id, 'date' => now()->toDateString(), 'session' => 'full', 'entries' => $entries,
        ], $over));
    }

    public function test_class_teacher_takes_the_daily_register_once_per_student_date_and_session(): void
    {
        $teacher = $this->classTeacher();
        $s1 = $this->student();
        $s2 = $this->student();

        $this->register($teacher, [['student_id' => $s1->id, 'status' => 'present'], ['student_id' => $s2->id, 'status' => 'absent', 'reason' => 'Sick']])->assertSessionHas('success', 'ok.attendanceSaved');
        $this->register($teacher, [['student_id' => $s1->id, 'status' => 'late', 'arrived_at' => '08:30']])->assertSessionHas('success');
        $this->assertSame(2, AttendanceRecord::query()->count(), 're-saving updates, it never duplicates');
        $this->assertSame('late', AttendanceRecord::query()->where('student_id', $s1->id)->value('status'));

        // the same learner twice in one submission is refused as a whole
        $this->register($teacher, [['student_id' => $s2->id, 'status' => 'present'], ['student_id' => $s2->id, 'status' => 'absent']], ['session' => 'am'])->assertSessionHas('error', 'err.attendance.duplicateEntry');
        $this->assertSame(0, AttendanceRecord::query()->where('session', 'am')->count());

        // a different session is a different register
        $this->register($teacher, [['student_id' => $s1->id, 'status' => 'present']], ['session' => 'pm'])->assertSessionHas('success');
        $this->assertSame(3, AttendanceRecord::query()->count());
    }

    public function test_attendance_rules_future_dates_foreign_students_statuses_and_scope(): void
    {
        $teacher = $this->classTeacher();
        $mine = $this->student();
        $other = $this->student([], $this->otherClass);

        $this->register($teacher, [['student_id' => $mine->id, 'status' => 'present']], ['date' => now()->addDay()->toDateString()])->assertSessionHas('error', 'err.attendance.future');
        $this->register($teacher, [['student_id' => $other->id, 'status' => 'present']])->assertSessionHas('error', 'err.attendance.notInClass');
        $this->register($teacher, [['student_id' => $mine->id, 'status' => 'flying']])->assertSessionHas('error', 'err.attendance.status');

        // a teacher who is not this class's class teacher may not take its daily register
        $stranger = $this->user('teacher');
        $this->register($stranger, [['student_id' => $mine->id, 'status' => 'present']])->assertSessionHas('error', 'err.attendance.notYourClass');
        $this->assertSame(0, AttendanceRecord::query()->count());

        $this->actingAs($this->user('parent'))->post(route('attendance.store'), [])->assertForbidden();
    }

    public function test_subject_period_attendance_belongs_to_the_lesson_teacher(): void
    {
        $subjectTeacher = $this->user('teacher');
        $student = $this->student();
        $slot = TimetableSlot::query()->create([
            'school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'subject_id' => $this->maths->id, 'teacher_id' => $subjectTeacher->id,
            'day_of_week' => 1, 'starts_at' => '09:00:00', 'ends_at' => '09:40:00', 'is_published' => true,
        ]);

        $this->register($subjectTeacher, [['student_id' => $student->id, 'status' => 'present']], ['session' => 'p'.$slot->id, 'slot_id' => $slot->id])->assertSessionHas('success');
        $this->assertSame('p'.$slot->id, AttendanceRecord::query()->value('session'));
        $this->assertSame($slot->id, AttendanceRecord::query()->value('timetable_slot_id'));

        $this->register($this->user('teacher'), [['student_id' => $student->id, 'status' => 'absent']], ['session' => 'p'.$slot->id, 'slot_id' => $slot->id])->assertSessionHas('error', 'err.attendance.notYourClass');
    }

    public function test_locked_registers_need_an_approved_correction(): void
    {
        Notification::fake();
        $teacher = $this->classTeacher();
        $student = $this->student();
        $old = now()->subDays(5)->toDateString();
        $record = AttendanceRecord::query()->create([
            'school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'student_id' => $student->id,
            'attended_on' => $old, 'session' => 'full', 'status' => 'absent', 'recorded_by' => $teacher->id,
        ]);

        // direct edit after the window is refused, and so is back-filling a missing student
        $this->register($teacher, [['student_id' => $student->id, 'status' => 'present']], ['date' => $old])->assertSessionHas('error', 'err.attendance.locked');
        $this->assertSame('absent', $record->fresh()->status);

        // correction needs a reason; no duplicate pending request
        $this->actingAs($teacher)->post(route('attendance.corrections.request', $record), ['to_status' => 'excused', 'reason' => 'x'])->assertSessionHasErrors('reason');
        $this->actingAs($teacher)->post(route('attendance.corrections.request', $record), ['to_status' => 'excused', 'reason' => 'Doctor note received'])->assertSessionHas('success', 'ok.correctionRequested');
        $this->actingAs($teacher)->post(route('attendance.corrections.request', $record), ['to_status' => 'present', 'reason' => 'Another request'])->assertSessionHas('error', 'err.attendance.correctionPending');
        $this->assertSame('absent', $record->fresh()->status, 'a request alone changes nothing');

        $correction = AttendanceCorrection::query()->firstOrFail();
        // the requester cannot approve their own correction
        $this->actingAs($teacher)->post(route('attendance.corrections.decide', $correction), ['decision' => 'approve'])->assertForbidden();

        $this->actingAs($this->admin)->post(route('attendance.corrections.decide', $correction), ['decision' => 'reject'])->assertSessionHas('error', 'err.attendance.noteRequired');
        $this->actingAs($this->admin)->post(route('attendance.corrections.decide', $correction), ['decision' => 'approve', 'note' => 'ok'])->assertSessionHas('success');
        $this->assertSame('excused', $record->fresh()->status);
        $this->assertSame('approved', $correction->fresh()->status);
        $this->actingAs($this->admin)->post(route('attendance.corrections.decide', $correction), ['decision' => 'approve'])->assertSessionHas('error', 'err.attendance.alreadyDecided');
    }

    public function test_guardians_with_accounts_are_notified_once_of_an_absence(): void
    {
        Notification::fake();
        $teacher = $this->classTeacher();
        $student = $this->student();
        $parent = $this->user('parent');
        Guardian::query()->create(['school_id' => $this->school->id, 'user_id' => $parent->id, 'first_name' => 'P', 'last_name' => 'Q'])->students()->attach($student->id, ['school_id' => $this->school->id]);

        $this->register($teacher, [['student_id' => $student->id, 'status' => 'absent']])->assertSessionHas('success');
        Notification::assertSentTo($parent, AttendanceAlertNotification::class);
        Notification::assertSentToTimes($parent, AttendanceAlertNotification::class, 1);

        // saving the same status again does not alert again
        $this->register($teacher, [['student_id' => $student->id, 'status' => 'absent']]);
        Notification::assertSentToTimes($parent, AttendanceAlertNotification::class, 1);
        $this->assertNotNull(AttendanceRecord::query()->first()->guardian_notified_at);

        // a present mark alerts nobody
        $other = $this->student();
        $this->register($teacher, [['student_id' => $other->id, 'status' => 'present']]);
        Notification::assertSentToTimes($parent, AttendanceAlertNotification::class, 1);
    }

    public function test_staff_attendance_requires_its_own_permission_and_valid_staff(): void
    {
        $staff = Staff::query()->create(['school_id' => $this->school->id, 'staff_number' => 'S1', 'first_name' => 'Ebrima', 'last_name' => 'Sowe', 'status' => 'active']);
        $day = now()->toDateString();

        $this->actingAs($this->user('teacher'))->post(route('attendance.staff.store'), ['date' => $day, 'entries' => [['staff_id' => $staff->id, 'status' => 'present']]])->assertForbidden();

        $hr = $this->user('hr_officer');
        $this->actingAs($hr)->post(route('attendance.staff.store'), ['date' => $day, 'entries' => [['staff_id' => $staff->id, 'status' => 'late']]])->assertSessionHas('success');
        $this->actingAs($hr)->post(route('attendance.staff.store'), ['date' => $day, 'entries' => [['staff_id' => $staff->id, 'status' => 'present']]])->assertSessionHas('success');
        $this->assertSame(1, StaffAttendance::query()->count());
        $this->assertSame('present', StaffAttendance::query()->value('status'));

        $this->actingAs($hr)->post(route('attendance.staff.store'), ['date' => $day, 'entries' => [['staff_id' => 999999, 'status' => 'present']]])->assertSessionHas('error', 'err.attendance.staffEntry');
    }

    public function test_attendance_reports_summarise_and_respect_class_scope(): void
    {
        $teacher = $this->classTeacher();
        $a = $this->student();
        $b = $this->student();
        $c = $this->student([], $this->otherClass);
        foreach ([[$a, 'present'], [$b, 'absent'], [$c, 'present']] as [$s, $status]) {
            AttendanceRecord::query()->create([
                'school_id' => $this->school->id, 'school_class_id' => $s->school_class_id, 'student_id' => $s->id,
                'attended_on' => now()->toDateString(), 'session' => 'full', 'status' => $status, 'recorded_by' => $teacher->id,
            ]);
        }

        $this->actingAs($teacher)->get(route('attendance.report', ['kind' => 'day']))->assertOk()
            ->assertInertia(fn ($p) => $p->component('Attendance/Report')->where('selected', $this->class->id));
        $content = $this->actingAs($teacher)->get(route('attendance.report', ['kind' => 'week', 'class_id' => $this->otherClass->id]))->assertOk()->getContent();
        $this->assertStringNotContainsString($c->first_name, $content, 'a teacher cannot report on a class they do not teach');

        $this->actingAs($this->admin)->get(route('attendance.report', ['kind' => 'term']))->assertOk();
        $this->actingAs($this->admin)->get(route('attendance.report', ['kind' => 'month']))->assertOk();
        $this->actingAs($this->admin)->get(route('attendance.index'))->assertOk()->assertInertia(fn ($p) => $p->component('Attendance/Index')->has('overview'));
    }

    public function test_student_attendance_history_is_limited_to_the_student_or_their_guardian(): void
    {
        $student = $this->student();
        $stranger = $this->student();
        $parent = $this->user('parent');
        Guardian::query()->create(['school_id' => $this->school->id, 'user_id' => $parent->id, 'first_name' => 'P', 'last_name' => 'Q'])->students()->attach($student->id, ['school_id' => $this->school->id]);

        $this->actingAs($parent)->get(route('attendance.student', $student))->assertOk();
        $this->actingAs($parent)->get(route('attendance.student', $stranger))->assertForbidden();
    }

    // ================================================================== timetable

    protected function slot(array $over = []): array
    {
        return array_merge([
            'school_class_id' => $this->class->id, 'subject_id' => $this->maths->id, 'day_of_week' => 1,
            'starts_at' => '09:00', 'ends_at' => '09:40',
        ], $over);
    }

    public function test_timetable_detects_class_teacher_room_and_break_conflicts(): void
    {
        $t1 = $this->user('teacher');
        $t2 = $this->user('teacher');
        $room = Room::query()->create(['school_id' => $this->school->id, 'name' => 'R1', 'is_active' => true]);
        Period::query()->create(['school_id' => $this->school->id, 'name' => 'Break', 'starts_at' => '10:00:00', 'ends_at' => '10:20:00', 'is_break' => true, 'sort_order' => 1]);

        $this->actingAs($this->admin)->post(route('timetable.store'), $this->slot(['teacher_id' => $t1->id, 'room_id' => $room->id]))->assertSessionHas('success');
        $this->assertFalse((bool) TimetableSlot::query()->first()->is_published, 'new slots are drafts');

        $this->actingAs($this->admin)->post(route('timetable.store'), $this->slot(['subject_id' => $this->english->id, 'teacher_id' => $t2->id, 'starts_at' => '09:30', 'ends_at' => '09:50']))->assertSessionHas('error', 'err.timetable.classOverlap');
        $this->actingAs($this->admin)->post(route('timetable.store'), $this->slot(['school_class_id' => $this->otherClass->id, 'teacher_id' => $t1->id]))->assertSessionHas('error', 'err.timetable.teacherOverlap');
        $this->actingAs($this->admin)->post(route('timetable.store'), $this->slot(['school_class_id' => $this->otherClass->id, 'teacher_id' => $t2->id, 'room_id' => $room->id]))->assertSessionHas('error', 'err.timetable.roomOverlap');
        $this->actingAs($this->admin)->post(route('timetable.store'), $this->slot(['school_class_id' => $this->otherClass->id, 'starts_at' => '10:05', 'ends_at' => '10:15']))->assertSessionHas('error', 'err.timetable.break');
        $this->assertSame(1, TimetableSlot::query()->count());
    }

    public function test_timetable_respects_teacher_availability_room_status_and_curriculum(): void
    {
        $teacher = $this->user('teacher');
        $other = $this->user('teacher');
        $closed = Room::query()->create(['school_id' => $this->school->id, 'name' => 'Closed', 'is_active' => false]);
        TeacherUnavailability::query()->create(['school_id' => $this->school->id, 'teacher_id' => $teacher->id, 'day_of_week' => 2, 'starts_at' => '08:00:00', 'ends_at' => '12:00:00']);
        $this->allocate($this->class, $this->maths, $teacher);

        $this->actingAs($this->admin)->post(route('timetable.store'), $this->slot(['teacher_id' => $teacher->id, 'day_of_week' => 2]))->assertSessionHas('error', 'err.timetable.teacherUnavailable');
        $this->actingAs($this->admin)->post(route('timetable.store'), $this->slot(['teacher_id' => $teacher->id, 'room_id' => $closed->id]))->assertSessionHas('error', 'err.timetable.roomInactive');
        $this->actingAs($this->admin)->post(route('timetable.store'), $this->slot(['subject_id' => $this->english->id, 'teacher_id' => $other->id]))->assertSessionHas('error', 'err.timetable.subjectNotInCurriculum');
        $this->actingAs($this->admin)->post(route('timetable.store'), $this->slot(['teacher_id' => $other->id]))->assertSessionHas('error', 'err.timetable.teacherNotAllocated');
        $this->assertSame(0, TimetableSlot::query()->count());
    }

    public function test_publication_is_blocked_until_the_timetable_is_valid_and_views_follow_publication(): void
    {
        $teacher = $this->user('teacher');
        $learner = $this->user('student');
        $child = $this->student();
        $learnerStudent = $this->student(['user_id' => $learner->id]);
        $parent = $this->user('parent');
        Guardian::query()->create(['school_id' => $this->school->id, 'user_id' => $parent->id, 'first_name' => 'P', 'last_name' => 'Q'])->students()->attach($child->id, ['school_id' => $this->school->id]);

        $good = TimetableSlot::query()->create(['school_id' => $this->school->id] + $this->slot(['teacher_id' => $teacher->id]) + ['starts_at' => '09:00:00', 'ends_at' => '09:40:00', 'is_published' => false]);
        // two legacy rows that clash (same class, overlapping)
        TimetableSlot::query()->create(['school_id' => $this->school->id] + $this->slot(['day_of_week' => 3, 'subject_id' => $this->english->id]) + ['starts_at' => '09:00:00', 'ends_at' => '09:40:00', 'is_published' => false]);
        $clash = TimetableSlot::query()->create(['school_id' => $this->school->id] + $this->slot(['day_of_week' => 3]) + ['starts_at' => '09:20:00', 'ends_at' => '10:00:00', 'is_published' => false]);

        $this->actingAs($this->admin)->post(route('timetable.check'))->assertSessionHas('error', 'err.timetable.conflicts');
        $this->actingAs($this->admin)->post(route('timetable.publish'))->assertSessionHas('error', 'err.timetable.conflicts');
        $this->assertSame(0, TimetableSlot::query()->where('is_published', true)->count(), 'publication is all-or-nothing');

        // nothing is visible to teachers, students or parents while it is a draft
        $this->actingAs($teacher)->get(route('timetable.index'))->assertOk()->assertInertia(fn ($p) => $p->has('slots', 0));

        $clash->delete();
        $this->actingAs($this->admin)->post(route('timetable.publish'))->assertSessionHas('success', 'ok.timetablePublished');
        $this->assertSame(2, TimetableSlot::query()->where('is_published', true)->count());

        $this->actingAs($teacher)->get(route('timetable.index'))->assertInertia(fn ($p) => $p->has('slots', 1)->where('can.manage', false));
        $this->actingAs($learner)->get(route('timetable.index'))->assertOk()->assertInertia(fn ($p) => $p->has('slots', 2));
        $this->actingAs($parent)->get(route('timetable.index'))->assertOk()->assertInertia(fn ($p) => $p->has('slots', 2));

        // only managers can change it
        $this->actingAs($teacher)->post(route('timetable.store'), $this->slot())->assertForbidden();
        $this->actingAs($teacher)->post(route('timetable.publish'))->assertForbidden();
        $this->actingAs($teacher)->delete(route('timetable.destroy', $good))->assertForbidden();
    }

    public function test_period_definitions_cannot_overlap(): void
    {
        $this->actingAs($this->admin)->post(route('timetable.periods.store'), ['name' => 'P1', 'starts_at' => '08:00', 'ends_at' => '08:40'])->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('timetable.periods.store'), ['name' => 'P2', 'starts_at' => '08:30', 'ends_at' => '09:10'])->assertSessionHas('error', 'err.period.overlap');
        $this->actingAs($this->admin)->post(route('timetable.periods.store'), ['name' => 'p1', 'starts_at' => '09:00', 'ends_at' => '09:40'])->assertSessionHas('error', 'err.name.taken');
    }

    // ================================================================== examinations and results

    protected function markableExam(?User $teacher = null, string $status = 'draft'): array
    {
        $teacher ??= $this->user('teacher');
        $this->allocate($this->class, $this->maths, $teacher);
        $student = $this->student();
        $exam = Examination::query()->create(['school_id' => $this->school->id, 'name' => 'End of term', 'type' => 'end_of_term', 'status' => $status]);
        $categories = app(ResultsService::class)->categories();

        return [$teacher, $student, $exam, $categories];
    }

    protected function marks(User $user, Examination $exam, Student $student, array $scores, array $over = [])
    {
        return $this->actingAs($user)->post(route('examinations.marks.store', $exam), array_merge([
            'class_id' => $this->class->id, 'subject_id' => $this->maths->id,
            'rows' => [['student_id' => $student->id, 'scores' => $scores]],
        ], $over));
    }

    public function test_weighted_scores_use_the_schools_categories_and_grading_scale(): void
    {
        [$teacher, $student, $exam, $categories] = $this->markableExam();
        $ca = $categories->firstWhere('type', 'ca');
        $ex = $categories->firstWhere('type', 'exam');
        $this->assertEquals(100, $categories->sum('weight'));

        $this->marks($teacher, $exam, $student, [$ca->id => 30, $ex->id => 45])->assertSessionHas('success', 'ok.marksSaved');
        $grade = Grade::query()->firstOrFail();
        // 30/40*40 = 30 ; 45/60*60 = 45
        $this->assertEquals(75.0, (float) $grade->total);
        $this->assertSame('B+', $grade->letter_grade);

        // the school can change the scale and the result follows
        $this->actingAs($this->admin)->post(route('examinations.settings.scale'), ['bands' => [
            ['letter' => 'Dist', 'min_score' => 70, 'remark' => 'Top'], ['letter' => 'Pass', 'min_score' => 40], ['letter' => 'Fail', 'min_score' => 0],
        ]])->assertSessionHas('success');
        $this->marks($teacher, $exam, $student, [$ca->id => 31])->assertSessionHas('success');
        $this->assertSame('Dist', $grade->fresh()->letter_grade);
    }

    public function test_assessment_weights_must_total_one_hundred(): void
    {
        app(ResultsService::class)->categories();
        $this->actingAs($this->admin)->post(route('examinations.settings.categories'), ['rows' => [
            ['name' => 'CA', 'code' => 'CA', 'type' => 'ca', 'weight' => 30, 'max_score' => 30, 'is_active' => true],
            ['name' => 'Exam', 'code' => 'EXAM', 'type' => 'exam', 'weight' => 60, 'max_score' => 60, 'is_active' => true],
        ]])->assertSessionHas('error', 'err.assessment.weights');

        $this->actingAs($this->admin)->post(route('examinations.settings.categories'), ['rows' => [
            ['name' => 'Test', 'code' => 'TEST', 'type' => 'ca', 'weight' => 20, 'max_score' => 20, 'is_active' => true],
            ['name' => 'Project', 'code' => 'PROJ', 'type' => 'ca', 'weight' => 20, 'max_score' => 20, 'is_active' => true],
            ['name' => 'Exam', 'code' => 'EXAM2', 'type' => 'exam', 'weight' => 60, 'max_score' => 60, 'is_active' => true],
        ]])->assertSessionHas('success', 'ok.settingsSaved');

        $this->actingAs($this->user('teacher'))->get(route('examinations.settings'))->assertForbidden();
    }

    public function test_mark_entry_is_limited_to_allocated_subjects_and_valid_scores(): void
    {
        [$teacher, $student, $exam, $categories] = $this->markableExam();
        $ca = $categories->firstWhere('type', 'ca');

        $this->marks($teacher, $exam, $student, [$ca->id => 41])->assertSessionHas('error', 'err.exam.scoreRange');
        $this->marks($teacher, $exam, $student, [$ca->id => -1])->assertSessionHas('error', 'err.exam.scoreRange');
        $this->marks($teacher, $exam, $student, [$ca->id => 'abc'])->assertSessionHas('error', 'err.exam.scoreInvalid');
        $this->marks($teacher, $exam, $student, [$ca->id => 20], ['subject_id' => $this->english->id])->assertSessionHas('error');
        $this->assertSame(0, Grade::query()->count());

        // unallocated teacher cannot mark at all (fail-closed)
        $stranger = $this->user('teacher');
        $this->marks($stranger, $exam, $student, [$ca->id => 20])->assertSessionHas('error', 'err.exam.notYourSubject');
        $this->assertSame(0, Grade::query()->count());

        // a score from a category of another school cannot be smuggled in
        $this->marks($teacher, $exam, $student, [999999 => 10])->assertSessionHas('error', 'err.exam.scoreInvalid');

        // an inactive learner cannot be marked
        $student->update(['status' => 'withdrawn']);
        $this->marks($teacher, $exam, $student, [$ca->id => 20])->assertSessionHas('error');

        $this->actingAs($this->user('parent'))->post(route('examinations.marks.store', $exam), [])->assertForbidden();
    }

    public function test_result_workflow_draft_submit_approve_publish_with_locking(): void
    {
        Notification::fake();
        [$teacher, $student, $exam, $categories] = $this->markableExam();
        $ca = $categories->firstWhere('type', 'ca');
        $ex = $categories->firstWhere('type', 'exam');

        $this->actingAs($teacher)->post(route('examinations.submit', $exam))->assertSessionHas('error', 'err.exam.noMarks');
        $this->marks($teacher, $exam, $student, [$ca->id => 30]);
        $this->actingAs($teacher)->post(route('examinations.submit', $exam))->assertSessionHas('error', 'err.exam.incomplete');
        $this->marks($teacher, $exam, $student, [$ex->id => 50]);
        $this->actingAs($teacher)->post(route('examinations.submit', $exam))->assertSessionHas('success', 'ok.examSubmitted');
        $this->assertSame('submitted', $exam->fresh()->status);

        // mark entry is locked once submitted
        $this->marks($teacher, $exam, $student, [$ca->id => 10])->assertSessionHas('error', 'err.exam.locked');
        // a teacher can neither approve nor publish
        $this->actingAs($teacher)->post(route('examinations.approve', $exam))->assertForbidden();
        $this->actingAs($teacher)->post(route('examinations.publish', $exam))->assertForbidden();
        // publish skips no step
        $this->actingAs($this->admin)->post(route('examinations.publish', $exam))->assertSessionHas('error', 'err.exam.badState');

        // returned for correction needs a reason, then re-opens mark entry
        $this->actingAs($this->admin)->post(route('examinations.return', $exam), ['reason' => 'no'])->assertSessionHasErrors('reason');
        $this->actingAs($this->admin)->post(route('examinations.return', $exam), ['reason' => 'Recheck maths marks'])->assertSessionHas('success');
        $this->assertSame('draft', $exam->fresh()->status);
        $this->marks($teacher, $exam, $student, [$ca->id => 32])->assertSessionHas('success');

        $this->actingAs($teacher)->post(route('examinations.submit', $exam));
        $this->actingAs($this->admin)->post(route('examinations.approve', $exam))->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('examinations.publish', $exam))->assertSessionHas('success', 'ok.examPublished');
        $this->assertSame('published', $exam->fresh()->status);
        $this->assertTrue((bool) Grade::query()->first()->is_published);
    }

    public function test_published_results_are_locked_and_corrections_are_recorded(): void
    {
        [$teacher, $student, $exam, $categories] = $this->markableExam(null, 'published');
        $ca = $categories->firstWhere('type', 'ca');
        $ex = $categories->firstWhere('type', 'exam');
        // seed a published result through the service as the manager
        $exam->update(['status' => 'draft']);
        $this->marks($this->admin, $exam, $student, [$ca->id => 30, $ex->id => 30])->assertSessionHas('success');
        $exam->update(['status' => 'published']);
        Grade::query()->update(['is_published' => true]);
        $this->assertEquals(60.0, (float) Grade::query()->value('total'));

        // a teacher cannot touch a published result
        $this->marks($teacher, $exam, $student, [$ca->id => 40])->assertSessionHas('error', 'err.exam.published');
        $this->assertEquals(60.0, (float) Grade::query()->value('total'));

        // a publisher needs a reason...
        $this->marks($this->admin, $exam, $student, [$ca->id => 40])->assertSessionHas('error', 'err.exam.reasonRequired');
        // ...and then the change is applied and recorded
        $this->marks($this->admin, $exam, $student, [$ca->id => 40], ['reason' => 'Marking error found on script'])->assertSessionHas('success');
        $grade = Grade::query()->first();
        $this->assertEquals(70.0, (float) $grade->total);
        $this->assertNotNull($grade->revised_at);

        $change = GradeChange::query()->where('event', 'corrected_after_publication')->firstOrFail();
        $this->assertSame('Marking error found on script', $change->reason);
        $this->assertEquals(60.0, (float) $change->old_values['total']);
        $this->assertEquals(70.0, (float) $change->new_values['total']);
        $this->assertSame($this->admin->id, $change->changed_by);

        $this->actingAs($this->admin)->get(route('examinations.history', $exam))->assertOk()->assertInertia(fn ($p) => $p->component('Examinations/History')->has('changes'));
        $this->actingAs($teacher)->get(route('examinations.history', $exam))->assertForbidden();
    }

    public function test_students_and_parents_only_see_published_results(): void
    {
        [$teacher, $student, $exam, $categories] = $this->markableExam();
        $ca = $categories->firstWhere('type', 'ca');
        $this->marks($teacher, $exam, $student, [$ca->id => 30]);
        $parent = $this->user('parent');
        Guardian::query()->create(['school_id' => $this->school->id, 'user_id' => $parent->id, 'first_name' => 'P', 'last_name' => 'Q'])->students()->attach($student->id, ['school_id' => $this->school->id]);

        $this->actingAs($parent)->get(route('students.report-card', $student))->assertOk()->assertInertia(fn ($p) => $p->has('grades', 0));

        $exam->update(['status' => 'published']);
        Grade::query()->update(['is_published' => true]);
        $this->actingAs($parent)->get(route('students.report-card', $student))->assertInertia(fn ($p) => $p->has('grades', 1));
    }

    public function test_mark_sheet_renders_for_learners_who_have_no_marks_yet_and_lists_every_learner(): void
    {
        [$teacher, $student, $exam] = $this->markableExam();
        $second = $this->student();

        $this->actingAs($this->admin)->get(route('examinations.show', $exam))->assertOk()
            ->assertInertia(fn ($p) => $p->component('Examinations/Show')->has('sheet', 2)->where('sheet.0.total', null));
        $this->actingAs($teacher)->get(route('examinations.show', $exam))->assertOk()->assertInertia(fn ($p) => $p->has('sheet', 2));
        $this->assertNotNull($second->id);
    }

    public function test_examination_endpoints_are_tenant_scoped(): void
    {
        $beta = $this->otherSchool();
        $foreign = app(TenantContext::class)->runForSchool($beta, fn () => Examination::query()->create(['school_id' => $beta->id, 'name' => 'Beta exam', 'status' => 'draft']));

        $this->actingAs($this->admin)->get(route('examinations.show', $foreign->id))->assertNotFound();
        $this->actingAs($this->admin)->post(route('examinations.approve', $foreign->id))->assertNotFound();
        $this->actingAs($this->admin)->get(route('students.show', 999999))->assertNotFound();
    }
}
