<?php

namespace Database\Seeders;

use App\Models\AcademicEvent;
use App\Models\AcademicYear;
use App\Models\AdmissionApplication;
use App\Models\Announcement;
use App\Models\AnnouncementRecipient;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetMaintenance;
use App\Models\AssessmentCategory;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\BankQuestion;
use App\Models\BoardingAllocation;
use App\Models\BoardingAttendance;
use App\Models\BoardingBed;
use App\Models\BoardingHostel;
use App\Models\BoardingRoom;
use App\Models\BoardingWelfareRecord;
use App\Models\CertificateRecord;
use App\Models\ClassSubject;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Course;
use App\Models\CourseAnnouncement;
use App\Models\CourseAudience;
use App\Models\CourseCategory;
use App\Models\CourseEnrolment;
use App\Models\CourseTeacher;
use App\Models\Department;
use App\Models\EventRegistration;
use App\Models\EventVenue;
use App\Models\ExternalCandidate;
use App\Models\ExternalCandidateSubject;
use App\Models\ExternalExamSession;
use App\Models\ExternalExamSubject;
use App\Models\ExternalResult;
use App\Models\FeeAdjustment;
use App\Models\FeeCategory;
use App\Models\FeeItem;
use App\Models\FeeStructure;
use App\Models\ForumPost;
use App\Models\ForumThread;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Grade;
use App\Models\GradingBand;
use App\Models\GradingScale;
use App\Models\Guardian;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\InvoiceItem;
use App\Models\JobPosition;
use App\Models\Level;
use App\Models\LibraryBook;
use App\Models\LibraryCopy;
use App\Models\LibraryFine;
use App\Models\LibraryLoan;
use App\Models\Message;
use App\Models\NewsPost;
use App\Models\Payment;
use App\Models\PayrollComponent;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\PerformanceRecord;
use App\Models\Period;
use App\Models\PlatformBankAccount;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\QuestionBank;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\QuizQuestion;
use App\Models\RecordsDocument;
use App\Models\RecordsDocumentEvent;
use App\Models\RecordsTemplate;
use App\Models\ResultCollection;
use App\Models\Room;
use App\Models\SafeguardingCase;
use App\Models\SafeguardingNote;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolEvent;
use App\Models\SchoolInquiry;
use App\Models\SchoolPaymentMethod;
use App\Models\SmsMessage;
use App\Models\SmsTemplate;
use App\Models\SsApplication;
use App\Models\SsApplicationEvent;
use App\Models\SsApplicationResult;
use App\Models\SsRound;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StaffContract;
use App\Models\StaffInvitation;
use App\Models\StaffLeaveRequest;
use App\Models\StockMovement;
use App\Models\Student;
use App\Models\StudentFee;
use App\Models\StudentMovement;
use App\Models\StudentTermRecord;
use App\Models\Subject;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\TeacherUnavailability;
use App\Models\Term;
use App\Models\TimetableSlot;
use App\Models\TransportAssignment;
use App\Models\TransportAttendance;
use App\Models\TransportDriver;
use App\Models\TransportRoute;
use App\Models\TransportStop;
use App\Models\TransportVehicle;
use App\Models\User;
use App\Models\VehicleMaintenance;
use App\Services\Platform\UsageRecorder;
use App\Services\Reporting\ReportStoreRefresher;
use App\Services\Scale\CacheTuner;
use App\Services\Demo\DemoLoginProvisioner;
use App\Support\TenantContext;
use Database\Seeders\Concerns\DemoMailDomain;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Extra realistic data so every school module can be clicked through.
 *
 * Demo passwords are always `password`. Existing non-demo accounts (for example a
 * live-registered school admin) are never overwritten.
 */
class DemoRichDataSeeder extends Seeder
{
    use DemoMailDomain;

    public function run(): void
    {
        $this->seedPlatformStaff();
        $this->seedPlatformBankAccount();

        $schools = School::query()->where('status', 'active')->orderBy('id')->get();
        if ($schools->isEmpty()) {
            $this->command?->warn('DemoRichDataSeeder: no active schools to enrich.');

            return;
        }

        foreach ($schools as $school) {
            app(TenantContext::class)->set($school);
            $this->seedSchool($school);
            try {
                app(ReportStoreRefresher::class)->refreshSchool($school->fresh());
                app(UsageRecorder::class)->snapshotSchool($school->fresh());
            } catch (\Throwable $e) {
                $this->command?->warn('Reporting snapshot skipped for '.$school->slug.': '.$e->getMessage());
            }
            app(CacheTuner::class)->forgetAdminDashboard($school->id);
            app(CacheTuner::class)->forgetPublicSite($school->id);
            app(TenantContext::class)->clear();
        }

        $host = $this->demoMailHost();
        $this->command?->info('Demo logins (password for every demo account):');
        $this->command?->info("  Platform:  platform@{$host}  finance@{$host}  support@{$host}  ops@{$host}  audit@{$host}");
        $this->command?->info("  School:    admin@{slug}.{$host}  head@{slug}.{$host}  teacher@{slug}.{$host}  finance@{slug}.{$host}  hr@{slug}.{$host}  student@{slug}.{$host}  parent@{slug}.{$host}");
    }

    private function seedPlatformStaff(): void
    {
        app(DemoLoginProvisioner::class)->ensurePlatform();
    }

    private function seedPlatformBankAccount(): void
    {
        $platform = User::query()->where('email', $this->demoPlatformEmail('platform'))->first();

        PlatformBankAccount::query()->updateOrCreate(
            ['account_number' => '001-123456-01'],
            [
                'bank_name' => 'Trust Bank Gambia',
                'account_name' => 'Gambia SchoolOS',
                'branch' => 'Kairaba Avenue',
                'swift_code' => 'TBLGGMGM',
                'currency' => 'GMD',
                'instructions' => 'Use this account only for the school’s annual platform subscription. Quote the school slug as the payment reference. This is demo seed data — not a live production account.',
                'is_active' => true,
                'created_by' => $platform?->id,
            ]
        );
    }

    private function seedSchool(School $school): void
    {
        $domain = $this->demoSchoolMailHost($school->slug);
        $prefix = strtoupper(substr(preg_replace('/[^a-z0-9]/i', '', $school->slug) ?: 'SCH', 0, 3));

        $users = $this->seedSpecialistUsers($school, $domain);
        $admin = $users['school_admin'];
        $teacher = $users['teacher'];
        $head = $users['headteacher'];
        $hr = $users['hr_officer'];
        $finance = $users['finance_officer'];
        $store = $users['storekeeper'];
        $librarian = $users['librarian'];
        $examOfficer = $users['exam_officer'];
        $records = $users['records_officer'];
        $admissions = $users['admissions_officer'];
        $studentUser = $users['student'];
        $parentUser = $users['parent'];

        $staff = $this->seedHr($school, $users);
        [$year, $term] = $this->seedCalendar($school, $admin);
        $levels = $this->seedLevels($school);
        $classes = $this->seedClasses($school, $levels, $users);
        $subjects = $this->seedSubjects($school, $staff);
        $this->seedCurriculum($school, $classes, $subjects, $users);
        $this->seedRoomsAndTimetable($school, $classes, $subjects, $users);
        $students = $this->seedStudents($school, $classes, $prefix, $studentUser, $parentUser, $admin, $year);
        $this->seedAttendanceAndGrades($school, $students, $subjects, $term, $teacher, $year);
        $this->seedAdmissions($school, $classes, $admissions);
        $this->seedFinance($school, $students, $year, $term, $classes, $finance, $head, $admin);
        $this->seedLms($school, $subjects, $classes, $students, $users, $teacher);
        $this->seedLibrary($school, $students, $librarian, $staff['teacher']);
        $this->seedInventoryAndProcurement($school, $users, $staff, $store, $head, $finance);
        $this->seedTransport($school, $students, $staff, $users['transport_officer']);
        $this->seedBoarding($school, $students, $staff, $head);
        $this->seedEvents($school, $classes, $students, $staff, $head);
        $this->seedComms($school, $users, $students, $classes);
        $this->seedExternalExams($school, $students, $examOfficer, $records);
        $this->seedSeniorAdmissions($school, $admissions, $head);
        $this->seedRecords($school, $students, $records, $head);
        $this->seedSafeguarding($school, $students, $head, $teacher);
        $this->seedWebsiteExtras($school);
        $this->seedInvitations($school, $admin);
    }

    /** @return array<string, User> */
    private function seedSpecialistUsers(School $school, string $domain): array
    {
        $existingAdmin = User::query()
            ->where('school_id', $school->id)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['school_admin', 'school_owner']))
            ->orderBy('id')
            ->first();

        $users = app(DemoLoginProvisioner::class)->ensureSchool($school);

        // Keep a live-registered owner as the acting admin for seeded FKs; never overwrite that password.
        if ($existingAdmin && $existingAdmin->email !== 'admin@'.$domain) {
            $users['school_admin'] = $existingAdmin;
        }

        return $users;
    }

    /** @param  array<string, User>  $users */
    private function seedHr(School $school, array $users): array
    {
        $departments = collect([
            ['code' => 'ACAD', 'name' => 'Academics', 'head' => 'academic_coordinator'],
            ['code' => 'SCI', 'name' => 'Sciences', 'head' => 'head_of_department'],
            ['code' => 'FIN', 'name' => 'Finance', 'head' => 'finance_officer'],
            ['code' => 'OPS', 'name' => 'Operations', 'head' => 'storekeeper'],
            ['code' => 'WEL', 'name' => 'Welfare', 'head' => 'headteacher'],
        ])->mapWithKeys(function (array $row) use ($school, $users) {
            $dept = Department::query()->updateOrCreate(
                ['school_id' => $school->id, 'name' => $row['name']],
                ['code' => $row['code'], 'head_user_id' => $users[$row['head']]->id]
            );

            return [$row['code'] => $dept];
        });

        $positions = [];
        foreach ([
            ['title' => 'Headteacher', 'dept' => 'ACAD'],
            ['title' => 'Deputy Headteacher', 'dept' => 'ACAD'],
            ['title' => 'Mathematics Teacher', 'dept' => 'SCI'],
            ['title' => 'English Teacher', 'dept' => 'ACAD'],
            ['title' => 'Science Teacher', 'dept' => 'SCI'],
            ['title' => 'Finance Officer', 'dept' => 'FIN'],
            ['title' => 'Accountant', 'dept' => 'FIN'],
            ['title' => 'HR Officer', 'dept' => 'OPS'],
            ['title' => 'Storekeeper', 'dept' => 'OPS'],
            ['title' => 'Librarian', 'dept' => 'ACAD'],
            ['title' => 'Transport Officer', 'dept' => 'OPS'],
            ['title' => 'Examinations Officer', 'dept' => 'ACAD'],
            ['title' => 'Admissions Officer', 'dept' => 'ACAD'],
            ['title' => 'Records Officer', 'dept' => 'ACAD'],
        ] as $row) {
            $positions[$row['title']] = JobPosition::query()->updateOrCreate(
                ['school_id' => $school->id, 'title' => $row['title']],
                ['department_id' => $departments[$row['dept']]->id, 'is_active' => true]
            );
        }

        $staffMap = [];
        $staffRows = [
            ['key' => 'head', 'user' => 'headteacher', 'number' => 'STF-HEAD', 'first' => 'Haddy', 'last' => 'Njie', 'title' => 'Headteacher', 'dept' => 'ACAD', 'salary' => 18000],
            ['key' => 'deputy', 'user' => 'deputy_headteacher', 'number' => 'STF-DEP', 'first' => 'Ousman', 'last' => 'Ceesay', 'title' => 'Deputy Headteacher', 'dept' => 'ACAD', 'salary' => 15000],
            ['key' => 'teacher', 'user' => 'teacher', 'number' => 'TCH-001', 'first' => 'Fatou', 'last' => 'Jallow', 'title' => 'Mathematics Teacher', 'dept' => 'SCI', 'salary' => 8500],
            ['key' => 'teacher_b', 'user' => 'teacher_b', 'number' => 'TCH-002', 'first' => 'Musa', 'last' => 'Touray', 'title' => 'English Teacher', 'dept' => 'ACAD', 'salary' => 8200],
            ['key' => 'teacher_c', 'user' => 'teacher_c', 'number' => 'TCH-003', 'first' => 'Awa', 'last' => 'Camara', 'title' => 'Science Teacher', 'dept' => 'SCI', 'salary' => 8200],
            ['key' => 'finance', 'user' => 'finance_officer', 'number' => 'STF-FIN', 'first' => 'Binta', 'last' => 'Sanyang', 'title' => 'Finance Officer', 'dept' => 'FIN', 'salary' => 9000],
            ['key' => 'accountant', 'user' => 'accountant', 'number' => 'STF-ACC', 'first' => 'Lamin', 'last' => 'Darboe', 'title' => 'Accountant', 'dept' => 'FIN', 'salary' => 8800],
            ['key' => 'hr', 'user' => 'hr_officer', 'number' => 'STF-HR', 'first' => 'Ebrima', 'last' => 'Manneh', 'title' => 'HR Officer', 'dept' => 'OPS', 'salary' => 8000],
            ['key' => 'store', 'user' => 'storekeeper', 'number' => 'STF-STO', 'first' => 'Omar', 'last' => 'Kinteh', 'title' => 'Storekeeper', 'dept' => 'OPS', 'salary' => 6500],
            ['key' => 'library', 'user' => 'librarian', 'number' => 'STF-LIB', 'first' => 'Ya', 'last' => 'Sohna', 'title' => 'Librarian', 'dept' => 'ACAD', 'salary' => 7000],
            ['key' => 'transport', 'user' => 'transport_officer', 'number' => 'STF-TRN', 'first' => 'Alhagie', 'last' => 'Sillah', 'title' => 'Transport Officer', 'dept' => 'OPS', 'salary' => 7200],
            ['key' => 'exam', 'user' => 'exam_officer', 'number' => 'STF-EXM', 'first' => 'Kebba', 'last' => 'Jallow', 'title' => 'Examinations Officer', 'dept' => 'ACAD', 'salary' => 7800],
            ['key' => 'admissions', 'user' => 'admissions_officer', 'number' => 'STF-ADM', 'first' => 'Satang', 'last' => 'Colley', 'title' => 'Admissions Officer', 'dept' => 'ACAD', 'salary' => 7500],
            ['key' => 'records', 'user' => 'records_officer', 'number' => 'STF-REC', 'first' => 'Mariama', 'last' => 'Bojang', 'title' => 'Records Officer', 'dept' => 'ACAD', 'salary' => 7500],
        ];

        foreach ($staffRows as $i => $row) {
            $user = $users[$row['user']];
            $member = Staff::query()->updateOrCreate(
                ['school_id' => $school->id, 'staff_number' => $row['number']],
                [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'first_name' => $row['first'],
                    'last_name' => $row['last'],
                    'phone' => $user->phone,
                    'role_title' => $row['title'],
                    'status' => 'active',
                    'department_id' => $departments[$row['dept']]->id,
                    'job_position_id' => $positions[$row['title']]->id,
                    'hired_on' => now()->subYears(3)->subMonths($i)->toDateString(),
                    'employment_type' => 'permanent',
                    'emergency_contact_name' => 'Family '.$row['last'],
                    'emergency_contact_phone' => '+220 7'.str_pad((string) (200000 + $i), 6, '0', STR_PAD_LEFT),
                    'bank_name' => 'Trust Bank',
                    'bank_account' => 'TB-'.str_pad((string) (10000 + $i), 6, '0', STR_PAD_LEFT),
                ]
            );
            $staffMap[$row['key']] = $member;

            StaffContract::query()->updateOrCreate(
                ['school_id' => $school->id, 'staff_id' => $member->id, 'starts_on' => '2026-01-01'],
                [
                    'job_position_id' => $positions[$row['title']]->id,
                    'contract_type' => 'permanent',
                    'basic_salary' => $row['salary'],
                    'status' => 'active',
                    'created_by' => $users['hr_officer']->id,
                    'notes' => 'Demo contract',
                ]
            );

            if ($i % 3 === 0) {
                PerformanceRecord::query()->updateOrCreate(
                    ['school_id' => $school->id, 'staff_id' => $member->id, 'period_label' => '2025/2026'],
                    [
                        'rating' => 4 + ($i % 2),
                        'strengths' => 'Reliable with learners and colleagues.',
                        'improvements' => 'Document schemes of work earlier in the term.',
                        'goals' => 'Lead one departmental CPD session.',
                        'reviewer_id' => $users['headteacher']->id,
                    ]
                );
            }

            $today = now()->toDateString();
            $attendance = StaffAttendance::query()
                ->where('staff_id', $member->id)
                ->whereDate('attended_on', $today)
                ->first();
            $attendancePayload = [
                'school_id' => $school->id,
                'status' => $i === 4 ? 'late' : 'present',
                'recorded_by' => $users['hr_officer']->id,
            ];
            if ($attendance) {
                $attendance->update($attendancePayload);
            } else {
                StaffAttendance::query()->create($attendancePayload + [
                    'staff_id' => $member->id,
                    'attended_on' => $today,
                ]);
            }
        }

        StaffLeaveRequest::query()->updateOrCreate(
            ['school_id' => $school->id, 'staff_id' => $staffMap['teacher']->id, 'starts_on' => now()->addWeek()->toDateString()],
            [
                'leave_type' => 'annual',
                'ends_on' => now()->addWeek()->addDays(2)->toDateString(),
                'days' => 3,
                'status' => 'pending',
                'reason' => 'Family visit',
                'applied_by' => $users['teacher']->id,
            ]
        );
        StaffLeaveRequest::query()->updateOrCreate(
            ['school_id' => $school->id, 'staff_id' => $staffMap['teacher_b']->id, 'starts_on' => now()->subDays(20)->toDateString()],
            [
                'leave_type' => 'sick',
                'ends_on' => now()->subDays(18)->toDateString(),
                'days' => 3,
                'status' => 'approved',
                'reason' => 'Malaria treatment',
                'applied_by' => $users['teacher_b']->id,
                'reviewed_by' => $users['headteacher']->id,
                'reviewed_at' => now()->subDays(21),
                'decision_note' => 'Approved with cover arranged.',
            ]
        );
        StaffLeaveRequest::query()->updateOrCreate(
            ['school_id' => $school->id, 'staff_id' => $staffMap['store']->id, 'starts_on' => now()->subDays(40)->toDateString()],
            [
                'leave_type' => 'unpaid',
                'ends_on' => now()->subDays(38)->toDateString(),
                'days' => 3,
                'status' => 'rejected',
                'reason' => 'Personal errand during stock count',
                'applied_by' => $users['storekeeper']->id,
                'reviewed_by' => $users['headteacher']->id,
                'reviewed_at' => now()->subDays(41),
                'decision_note' => 'Stock count week — please resubmit later.',
            ]
        );

        foreach ([
            ['code' => 'HSE', 'name' => 'Housing allowance', 'kind' => 'allowance', 'calc' => 'fixed', 'value' => 1500, 'sort' => 1],
            ['code' => 'TRN', 'name' => 'Transport allowance', 'kind' => 'allowance', 'calc' => 'fixed', 'value' => 500, 'sort' => 2],
            ['code' => 'PEN', 'name' => 'Pension contribution', 'kind' => 'deduction', 'calc' => 'percent_of_basic', 'value' => 5, 'sort' => 3],
        ] as $comp) {
            PayrollComponent::query()->updateOrCreate(
                ['school_id' => $school->id, 'code' => $comp['code']],
                [
                    'name' => $comp['name'],
                    'kind' => $comp['kind'],
                    'calc' => $comp['calc'],
                    'value' => $comp['value'],
                    'applies_to' => 'all',
                    'is_active' => true,
                    'sort_order' => $comp['sort'],
                ]
            );
        }

        $this->seedPayrollRun($school, $staffMap, $users, '2026-09', '2026-09-01', '2026-09-30', 'paid');
        $this->seedPayrollRun($school, $staffMap, $users, '2026-10', '2026-10-01', '2026-10-31', 'posted');
        $this->seedPayrollRun($school, $staffMap, $users, '2026-11', '2026-11-01', '2026-11-30', 'draft');

        return $staffMap;
    }

    /** @param  array<string, Staff>  $staffMap */
    private function seedPayrollRun(School $school, array $staffMap, array $users, string $label, string $start, string $end, string $status): void
    {
        $run = PayrollRun::query()->updateOrCreate(
            ['school_id' => $school->id, 'period_label' => $label],
            [
                'period_start' => $start,
                'period_end' => $end,
                'status' => $status,
                'created_by' => $users['hr_officer']->id,
                'posted_by' => in_array($status, ['posted', 'paid'], true) ? $users['headteacher']->id : null,
                'posted_at' => in_array($status, ['posted', 'paid'], true) ? now()->subDays(10) : null,
                'paid_by' => $status === 'paid' ? $users['accountant']->id : null,
                'paid_at' => $status === 'paid' ? now()->subDays(8) : null,
                'payment_reference' => $status === 'paid' ? 'PAY-'.$label : null,
                'notes' => 'Demo payroll '.$label,
            ]
        );

        $grossTotal = 0;
        $netTotal = 0;
        foreach ($staffMap as $member) {
            $contract = StaffContract::query()->where('staff_id', $member->id)->orderByDesc('starts_on')->first();
            $basic = (int) ($contract?->basic_salary ?? 7000);
            $allow = 2000;
            $deduct = (int) round($basic * 0.05);
            $gross = $basic + $allow;
            $net = $gross - $deduct;
            PayrollItem::query()->updateOrCreate(
                ['payroll_run_id' => $run->id, 'staff_id' => $member->id],
                [
                    'school_id' => $school->id,
                    'basic_salary' => $basic,
                    'allowances' => $allow,
                    'deductions' => $deduct,
                    'gross_pay' => $gross,
                    'net_pay' => $net,
                    'lines' => [
                        ['kind' => 'basic', 'name' => 'Basic salary', 'code' => 'BASIC', 'amount' => $basic],
                        ['kind' => 'allowance', 'name' => 'Housing allowance', 'code' => 'HSE', 'amount' => 1500],
                        ['kind' => 'allowance', 'name' => 'Transport allowance', 'code' => 'TRN', 'amount' => 500],
                        ['kind' => 'deduction', 'name' => 'Pension contribution', 'code' => 'PEN', 'amount' => $deduct],
                    ],
                    'meta' => ['source' => 'seed'],
                ]
            );
            $grossTotal += $gross;
            $netTotal += $net;
        }

        $run->update(['gross_total' => $grossTotal, 'net_total' => $netTotal]);
    }

    /** @return array{0: AcademicYear, 1: Term} */
    private function seedCalendar(School $school, User $admin): array
    {
        AcademicYear::query()
            ->where('school_id', $school->id)
            ->where('name', '!=', '2026/2027')
            ->update(['is_current' => false]);
        Term::query()->where('school_id', $school->id)->update(['is_current' => false]);

        $year = AcademicYear::query()->updateOrCreate(
            ['school_id' => $school->id, 'name' => '2026/2027'],
            ['starts_on' => '2026-09-01', 'ends_on' => '2027-07-31', 'is_current' => true]
        );

        $terms = [
            ['name' => 'Term 1', 'sequence' => 1, 'starts_on' => '2026-09-01', 'ends_on' => '2026-12-12', 'is_current' => true],
            ['name' => 'Term 2', 'sequence' => 2, 'starts_on' => '2027-01-05', 'ends_on' => '2027-04-03', 'is_current' => false],
            ['name' => 'Term 3', 'sequence' => 3, 'starts_on' => '2027-04-20', 'ends_on' => '2027-07-15', 'is_current' => false],
        ];
        $term1 = null;
        foreach ($terms as $row) {
            $term = Term::query()->updateOrCreate(
                ['school_id' => $school->id, 'academic_year_id' => $year->id, 'name' => $row['name']],
                $row
            );
            if ($row['sequence'] === 1) {
                $term1 = $term;
            }
        }

        foreach ([
            ['type' => 'holiday', 'title' => 'Independence Day', 'starts_on' => '2027-02-18'],
            ['type' => 'exam_period', 'title' => 'Term 1 examinations', 'starts_on' => '2026-11-24', 'ends_on' => '2026-12-05'],
            ['type' => 'admission_period', 'title' => 'Senior Secondary intake window', 'starts_on' => '2026-06-01', 'ends_on' => '2026-08-15'],
            ['type' => 'event', 'title' => 'Sports day', 'starts_on' => '2026-11-14'],
        ] as $event) {
            AcademicEvent::query()->updateOrCreate(
                ['school_id' => $school->id, 'title' => $event['title']],
                [
                    'academic_year_id' => $year->id,
                    'term_id' => $term1?->id,
                    'type' => $event['type'],
                    'starts_on' => $event['starts_on'],
                    'ends_on' => $event['ends_on'] ?? $event['starts_on'],
                ]
            );
        }

        $scale = GradingScale::query()->updateOrCreate(
            ['school_id' => $school->id, 'name' => 'School 100-point scale'],
            ['is_default' => true]
        );
        foreach ([['A', 80, 'Excellent'], ['B+', 70, 'Very good'], ['B', 60, 'Good'], ['C', 50, 'Credit'], ['D', 40, 'Pass'], ['F', 0, 'Fail']] as $i => [$letter, $min, $remark]) {
            GradingBand::query()->updateOrCreate(
                ['school_id' => $school->id, 'grading_scale_id' => $scale->id, 'letter' => $letter],
                ['min_score' => $min, 'remark' => $remark]
            );
        }

        AssessmentCategory::query()->updateOrCreate(
            ['school_id' => $school->id, 'code' => 'CA'],
            ['name' => 'Continuous assessment', 'type' => 'ca', 'weight' => 30, 'max_score' => 30, 'is_active' => true, 'sort_order' => 1]
        );
        AssessmentCategory::query()->updateOrCreate(
            ['school_id' => $school->id, 'code' => 'EXAM'],
            ['name' => 'End of term exam', 'type' => 'exam', 'weight' => 70, 'max_score' => 70, 'is_active' => true, 'sort_order' => 2]
        );

        return [$year, $term1];
    }

    /** @return array<string, Level> */
    private function seedLevels(School $school): array
    {
        $rows = [
            ['code' => 'NUR', 'name' => 'Nursery', 'sort_order' => 1, 'grade_number' => null],
            ['code' => 'LB', 'name' => 'Lower Basic', 'sort_order' => 2, 'grade_number' => null],
            ['code' => 'UB', 'name' => 'Upper Basic', 'sort_order' => 3, 'grade_number' => null],
            ['code' => 'JSS', 'name' => 'Junior Secondary', 'sort_order' => 4, 'grade_number' => null],
            ['code' => 'SSS', 'name' => 'Senior Secondary', 'sort_order' => 5, 'grade_number' => 10],
            ['code' => 'G1', 'name' => 'Grade 1', 'sort_order' => 11, 'grade_number' => 1],
            ['code' => 'G6', 'name' => 'Grade 6', 'sort_order' => 16, 'grade_number' => 6],
            ['code' => 'G9', 'name' => 'Grade 9', 'sort_order' => 19, 'grade_number' => 9],
            ['code' => 'G12', 'name' => 'Grade 12', 'sort_order' => 22, 'grade_number' => 12],
        ];

        $levels = [];
        foreach ($rows as $row) {
            $levels[$row['code']] = Level::query()->updateOrCreate(
                ['school_id' => $school->id, 'code' => $row['code']],
                $row
            );
        }

        return $levels;
    }

    /**
     * @param  array<string, Level>  $levels
     * @param  array<string, User>  $users
     * @return array<string, SchoolClass>
     */
    private function seedClasses(School $school, array $levels, array $users): array
    {
        $rows = [
            ['name' => 'Nursery A', 'level' => 'NUR', 'stream' => 'A', 'teacher' => 'teacher_c', 'capacity' => 25],
            ['name' => 'Grade 1A', 'level' => 'G1', 'stream' => 'A', 'teacher' => 'teacher_c', 'capacity' => 35],
            ['name' => 'Grade 6A', 'level' => 'G6', 'stream' => 'A', 'teacher' => 'teacher_b', 'capacity' => 40],
            ['name' => 'Grade 9A', 'level' => 'G9', 'stream' => 'A', 'teacher' => 'teacher_b', 'capacity' => 40],
            ['name' => 'Grade 10A', 'level' => 'SSS', 'stream' => 'A', 'teacher' => 'teacher', 'capacity' => 40],
            ['name' => 'Grade 10B', 'level' => 'SSS', 'stream' => 'B', 'teacher' => 'teacher', 'capacity' => 40],
            ['name' => 'Grade 12A', 'level' => 'G12', 'stream' => 'A', 'teacher' => 'head_of_department', 'capacity' => 40],
        ];

        $classes = [];
        foreach ($rows as $row) {
            $classes[$row['name']] = SchoolClass::query()->updateOrCreate(
                ['school_id' => $school->id, 'name' => $row['name']],
                [
                    'level_id' => $levels[$row['level']]->id,
                    'stream' => $row['stream'],
                    'class_teacher_id' => $users[$row['teacher']]->id,
                    'capacity' => $row['capacity'],
                ]
            );
        }

        return $classes;
    }

    /** @return array<string, Subject> */
    private function seedSubjects(School $school, array $staff): array
    {
        $sciDept = Department::query()->where('school_id', $school->id)->where('code', 'SCI')->first();
        $acadDept = Department::query()->where('school_id', $school->id)->where('code', 'ACAD')->first();

        $rows = [
            ['code' => 'MATH', 'name' => 'Mathematics', 'dept' => $sciDept],
            ['code' => 'ENG', 'name' => 'English Language', 'dept' => $acadDept],
            ['code' => 'BIO', 'name' => 'Biology', 'dept' => $sciDept],
            ['code' => 'PHY', 'name' => 'Physics', 'dept' => $sciDept],
            ['code' => 'CHE', 'name' => 'Chemistry', 'dept' => $sciDept],
            ['code' => 'SST', 'name' => 'Social Studies', 'dept' => $acadDept],
            ['code' => 'FRE', 'name' => 'French', 'dept' => $acadDept],
            ['code' => 'ICT', 'name' => 'ICT', 'dept' => $sciDept],
            ['code' => 'IRS', 'name' => 'Islamic Studies', 'dept' => $acadDept],
            ['code' => 'PE', 'name' => 'Physical Education', 'dept' => $acadDept],
        ];

        $subjects = [];
        foreach ($rows as $row) {
            $subjects[$row['code']] = Subject::query()->updateOrCreate(
                ['school_id' => $school->id, 'code' => $row['code']],
                ['name' => $row['name'], 'department_id' => $row['dept']?->id]
            );
        }

        return $subjects;
    }

    /**
     * @param  array<string, SchoolClass>  $classes
     * @param  array<string, Subject>  $subjects
     * @param  array<string, User>  $users
     */
    private function seedCurriculum(School $school, array $classes, array $subjects, array $users): void
    {
        $map = [
            'Nursery A' => [['ENG', 'teacher_c', 5], ['PE', 'teacher_c', 3]],
            'Grade 1A' => [['ENG', 'teacher_c', 6], ['MATH', 'teacher', 6], ['SST', 'teacher_b', 4]],
            'Grade 6A' => [['ENG', 'teacher_b', 5], ['MATH', 'teacher', 5], ['SST', 'teacher_b', 4], ['ICT', 'head_of_department', 2]],
            'Grade 9A' => [['ENG', 'teacher_b', 5], ['MATH', 'teacher', 5], ['BIO', 'teacher_c', 4], ['SST', 'teacher_b', 4], ['FRE', 'teacher_b', 3]],
            'Grade 10A' => [['MATH', 'teacher', 5], ['ENG', 'teacher_b', 5], ['BIO', 'teacher_c', 4], ['SST', 'academic_coordinator', 3]],
            'Grade 10B' => [['MATH', 'teacher', 5], ['ENG', 'teacher_b', 5], ['BIO', 'teacher_c', 4], ['SST', 'academic_coordinator', 3]],
            'Grade 12A' => [['MATH', 'teacher', 6], ['ENG', 'teacher_b', 5], ['PHY', 'head_of_department', 4], ['CHE', 'teacher_c', 4], ['BIO', 'teacher_c', 4]],
        ];

        foreach ($map as $className => $links) {
            $class = $classes[$className];
            foreach ($links as [$code, $teacherKey, $periods]) {
                ClassSubject::query()->updateOrCreate(
                    ['school_id' => $school->id, 'school_class_id' => $class->id, 'subject_id' => $subjects[$code]->id],
                    ['teacher_id' => $users[$teacherKey]->id, 'periods_per_week' => $periods]
                );
            }
        }
    }

    /**
     * @param  array<string, SchoolClass>  $classes
     * @param  array<string, Subject>  $subjects
     * @param  array<string, User>  $users
     */
    private function seedRoomsAndTimetable(School $school, array $classes, array $subjects, array $users): void
    {
        $rooms = [];
        foreach ([
            ['name' => 'Room 12', 'code' => 'R12', 'capacity' => 40, 'building' => 'Main Block'],
            ['name' => 'Science Lab 1', 'code' => 'LAB1', 'capacity' => 30, 'building' => 'Science Block'],
            ['name' => 'Hall', 'code' => 'HALL', 'capacity' => 200, 'building' => 'Main Block'],
            ['name' => 'ICT Lab', 'code' => 'ICT1', 'capacity' => 32, 'building' => 'Science Block'],
        ] as $row) {
            $rooms[$row['code']] = Room::query()->updateOrCreate(
                ['school_id' => $school->id, 'name' => $row['name']],
                ['code' => $row['code'], 'capacity' => $row['capacity'], 'building' => $row['building'], 'is_active' => true]
            );
        }

        $periodDefs = [
            ['Assembly', '07:45:00', '08:00:00', true, 0],
            ['Period 1', '08:00:00', '08:45:00', false, 1],
            ['Period 2', '08:45:00', '09:30:00', false, 2],
            ['Break', '09:30:00', '09:50:00', true, 3],
            ['Period 3', '09:50:00', '10:35:00', false, 4],
            ['Period 4', '10:35:00', '11:20:00', false, 5],
        ];
        $periods = [];
        foreach ($periodDefs as [$name, $start, $end, $break, $sort]) {
            $periods[] = Period::query()->updateOrCreate(
                ['school_id' => $school->id, 'name' => $name],
                ['starts_at' => $start, 'ends_at' => $end, 'is_break' => $break, 'sort_order' => $sort]
            );
        }

        $p1 = collect($periods)->firstWhere('name', 'Period 1');
        $p2 = collect($periods)->firstWhere('name', 'Period 2');

        foreach (['Grade 10A', 'Grade 9A', 'Grade 12A'] as $className) {
            $class = $classes[$className];
            foreach ([1, 2, 3, 4, 5] as $dow) {
                $math = $dow % 2 === 1;
                TimetableSlot::query()->updateOrCreate(
                    [
                        'school_id' => $school->id,
                        'school_class_id' => $class->id,
                        'day_of_week' => $dow,
                        'starts_at' => '08:00:00',
                    ],
                    [
                        'subject_id' => $math ? $subjects['MATH']->id : $subjects['ENG']->id,
                        'teacher_id' => $math ? $users['teacher']->id : $users['teacher_b']->id,
                        'ends_at' => '08:45:00',
                        'room' => $math ? $rooms['LAB1']->name : $rooms['R12']->name,
                        'room_id' => $math ? $rooms['LAB1']->id : $rooms['R12']->id,
                        'period_id' => $p1?->id,
                        'is_published' => true,
                        'published_at' => now()->subDays(20),
                    ]
                );
                TimetableSlot::query()->updateOrCreate(
                    [
                        'school_id' => $school->id,
                        'school_class_id' => $class->id,
                        'day_of_week' => $dow,
                        'starts_at' => '08:45:00',
                    ],
                    [
                        'subject_id' => $subjects['BIO']->id,
                        'teacher_id' => $users['teacher_c']->id,
                        'ends_at' => '09:30:00',
                        'room' => $rooms['LAB1']->name,
                        'room_id' => $rooms['LAB1']->id,
                        'period_id' => $p2?->id,
                        'is_published' => true,
                        'published_at' => now()->subDays(20),
                    ]
                );
            }
        }

        TeacherUnavailability::query()->updateOrCreate(
            ['school_id' => $school->id, 'teacher_id' => $users['teacher']->id, 'day_of_week' => 5, 'starts_at' => '13:00:00'],
            ['ends_at' => '16:00:00', 'reason' => 'Department meeting']
        );
    }

    /**
     * @param  array<string, SchoolClass>  $classes
     * @return list<Student>
     */
    private function seedStudents(School $school, array $classes, string $prefix, User $studentUser, User $parentUser, User $admin, AcademicYear $year): array
    {
        $rosters = [
            'Nursery A' => [['Mariama', 'Jobe', 'female'], ['Omar', 'Faal', 'male'], ['Awa', 'Gassama', 'female'], ['Lamin', 'Mendy', 'male']],
            'Grade 1A' => [['Fatou', 'Njie', 'female'], ['Ebrima', 'Ceesay', 'male'], ['Isatou', 'Bojang', 'female'], ['Modou', 'Sowe', 'male'], ['Haddy', 'Touray', 'female'], ['Alieu', 'Jallow', 'male']],
            'Grade 6A' => [['Binta', 'Camara', 'female'], ['Yusupha', 'Sanyang', 'male'], ['Kaddy', 'Manneh', 'female'], ['Pa', 'Sillah', 'male'], ['Ndey', 'Colley', 'female'], ['Abdoulie', 'Bah', 'male']],
            'Grade 9A' => [['Amie', 'Jallow', 'female'], ['Ousman', 'Darboe', 'male'], ['Satang', 'Ceesay', 'female'], ['Kebba', 'Touray', 'male'], ['Ya', 'Sowe', 'female'], ['Musa', 'Kinteh', 'male'], ['Jainaba', 'Njie', 'female'], ['Yankuba', 'Bojang', 'male']],
            'Grade 10A' => [['Amadou', 'Jallow', 'male'], ['Omar', 'Sanyang', 'male'], ['Lamin', 'Bah', 'male'], ['Ebrima', 'Sowe', 'male'], ['Adama', 'Jatta', 'male'], ['Fanta', 'Sanneh', 'female']],
            'Grade 10B' => [['Fatoumata', 'Ceesay', 'female'], ['Awa', 'Touray', 'female'], ['Mariama', 'Jammeh', 'female'], ['Isatou', 'Njie', 'female'], ['Penda', 'Jallow', 'female'], ['Sainabou', 'Ceesay', 'female']],
            'Grade 12A' => [['Mustapha', 'Jallow', 'male'], ['Hawa', 'Sanyang', 'female'], ['Bakary', 'Ceesay', 'male'], ['Fatou', 'Camara', 'female'], ['Momodou', 'Touray', 'male'], ['Aisha', 'Njie', 'female'], ['Sait', 'Manneh', 'male'], ['Ramou', 'Bah', 'female']],
        ];

        $ageByClass = [
            'Nursery A' => 5, 'Grade 1A' => 7, 'Grade 6A' => 12, 'Grade 9A' => 15,
            'Grade 10A' => 16, 'Grade 10B' => 16, 'Grade 12A' => 18,
        ];

        $all = [];
        $n = 100;
        foreach ($rosters as $className => $people) {
            $class = $classes[$className];
            foreach ($people as $i => [$first, $last, $gender]) {
                $n++;
                $number = $prefix.'-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
                $isPrimaryStudent = $className === 'Grade 10A' && $first === 'Amadou' && $last === 'Jallow';
                $classmateLogin = $className === 'Grade 10A' && in_array($first.' '.$last, ['Omar Sanyang', 'Lamin Bah'], true);

                $existing = $isPrimaryStudent
                    ? Student::query()->where('school_id', $school->id)->where('user_id', $studentUser->id)->first()
                    : Student::query()->where('school_id', $school->id)->where('first_name', $first)->where('last_name', $last)->where('school_class_id', $class->id)->first();

                $portalUserId = $isPrimaryStudent ? $studentUser->id : null;
                if ($classmateLogin) {
                    $domain = strstr($studentUser->email, '@') ?: '@'.$this->demoSchoolMailHost('brightfuture');
                    $classmate = User::query()->updateOrCreate(
                        ['email' => strtolower($first).$domain],
                        [
                            'school_id' => $school->id,
                            'name' => $first.' '.$last,
                            'password' => 'password',
                            'email_verified_at' => now(),
                        ]
                    );
                    $classmate->syncRoles(['student']);
                    $portalUserId = $classmate->id;
                }

                $student = Student::query()->updateOrCreate(
                    ['school_id' => $school->id, 'student_number' => $existing?->student_number ?? $number],
                    [
                        'user_id' => $portalUserId,
                        'school_class_id' => $class->id,
                        'first_name' => $first,
                        'last_name' => $last,
                        'gender' => $gender,
                        'date_of_birth' => now()->subYears($ageByClass[$className])->subDays($i * 17)->toDateString(),
                        'place_of_birth' => 'Banjul',
                        'nationality' => 'Gambian',
                        'religion' => 'Islam',
                        'status' => 'active',
                        'admitted_on' => '2024-09-02',
                        'address' => 'Kanifing',
                        'previous_school' => $ageByClass[$className] > 7 ? 'Neighbourhood Lower Basic' : null,
                        'emergency_contact_name' => 'Parent '.$last,
                        'emergency_contact_phone' => '+220 7'.str_pad((string) (300000 + $n), 6, '0', STR_PAD_LEFT),
                        'emergency_contact_relationship' => $i % 2 === 0 ? 'Father' : 'Mother',
                    ]
                );
                $all[] = $student;

                $guardian = Guardian::query()->updateOrCreate(
                    ['school_id' => $school->id, 'email' => Str::lower($first).'.'.Str::lower($last).'.guardian@example.test'],
                    [
                        'user_id' => $isPrimaryStudent ? $parentUser->id : null,
                        'first_name' => $i % 2 === 0 ? 'Parent' : 'Aja',
                        'last_name' => $last,
                        'relationship' => $i % 2 === 0 ? 'Father' : 'Mother',
                        'phone' => '+220 7'.str_pad((string) (400000 + $n), 6, '0', STR_PAD_LEFT),
                        'occupation' => $i % 3 === 0 ? 'Teacher' : 'Trader',
                    ]
                );
                $student->guardians()->syncWithoutDetaching([
                    $guardian->id => ['school_id' => $school->id, 'is_primary' => true],
                ]);

                StudentMovement::query()->updateOrCreate(
                    ['school_id' => $school->id, 'student_id' => $student->id, 'event' => 'enrolled'],
                    [
                        'academic_year_id' => $year->id,
                        'to_class_id' => $class->id,
                        'reason' => 'New admission',
                        'effective_on' => $student->admitted_on?->toDateString() ?? '2024-09-02',
                        'performed_by' => $admin->id,
                    ]
                );
            }
        }

        return $all;
    }

    /**
     * @param  list<Student>  $students
     * @param  array<string, Subject>  $subjects
     */
    private function seedAttendanceAndGrades(School $school, array $students, array $subjects, Term $term, User $teacher, AcademicYear $year): void
    {
        foreach ($students as $i => $student) {
            foreach (range(0, 9) as $day) {
                $status = 'present';
                if ($day === 2 && $i % 7 === 0) {
                    $status = 'late';
                } elseif ($day === 5 && $i % 9 === 0) {
                    $status = 'absent';
                }
                $attendedOn = now()->subDays($day)->toDateString();
                $record = AttendanceRecord::query()
                    ->where('student_id', $student->id)
                    ->whereDate('attended_on', $attendedOn)
                    ->where('session', 'full')
                    ->first();
                $payload = [
                    'school_id' => $school->id,
                    'school_class_id' => $student->school_class_id,
                    'status' => $status,
                    'recorded_by' => $teacher->id,
                    'reason' => $status === 'absent' ? 'Fever' : null,
                    'arrived_at' => $status === 'late' ? '08:20:00' : null,
                ];
                if ($record) {
                    $record->update($payload);
                } else {
                    $record = AttendanceRecord::query()->create($payload + [
                        'student_id' => $student->id,
                        'attended_on' => $attendedOn,
                        'session' => 'full',
                    ]);
                }

                if ($status === 'absent' && $day === 5 && $i % 9 === 0) {
                    AttendanceCorrection::query()->updateOrCreate(
                        ['attendance_record_id' => $record->id],
                        [
                            'school_id' => $school->id,
                            'requested_by' => $teacher->id,
                            'from_status' => 'absent',
                            'to_status' => 'present',
                            'reason' => 'Marked in error — learner was at a clinic visit with a note.',
                            'status' => 'pending',
                        ]
                    );
                }
            }

            foreach ($subjects as $subject) {
                $ca = 18 + ($i % 12);
                $exam = 38 + (($i * 2) % 25);
                $total = min(100, $ca + $exam);
                Grade::query()->updateOrCreate(
                    [
                        'school_id' => $school->id,
                        'student_id' => $student->id,
                        'subject_id' => $subject->id,
                        'term_id' => $term->id,
                    ],
                    [
                        'ca_score' => $ca,
                        'exam_score' => $exam,
                        'total' => $total,
                        'letter_grade' => $total >= 80 ? 'A' : ($total >= 70 ? 'B+' : ($total >= 60 ? 'B' : ($total >= 50 ? 'C' : 'D'))),
                        'remark' => $total >= 70 ? 'Good progress' : 'Needs support',
                        'is_published' => true,
                        'level_id' => $student->schoolClass?->level_id,
                    ]
                );
            }
        }
    }

    /** @param  array<string, SchoolClass>  $classes */
    private function seedAdmissions(School $school, array $classes, User $officer): void
    {
        $rows = [
            ['ADM-DEMO-0001', 'submitted', 'Kebba', 'Camara', 'male', $classes['Grade 10A']->id],
            ['ADM-DEMO-0002', 'accepted', 'Fanta', 'Jallow', 'female', $classes['Grade 1A']->id],
            ['ADM-DEMO-0003', 'waitlisted', 'Pa', 'Sowe', 'male', $classes['Grade 6A']->id],
            ['ADM-DEMO-0004', 'rejected', 'Nyima', 'Touray', 'female', $classes['Grade 9A']->id],
            ['ADM-DEMO-0005', 'submitted', 'Alhagie', 'Bah', 'male', $classes['Nursery A']->id],
        ];

        foreach ($rows as $i => [$number, $status, $first, $last, $gender, $classId]) {
            AdmissionApplication::query()->updateOrCreate(
                ['school_id' => $school->id, 'application_number' => $number],
                [
                    'status' => $status,
                    'first_name' => $first,
                    'last_name' => $last,
                    'gender' => $gender,
                    'date_of_birth' => now()->subYears(10 + $i)->toDateString(),
                    'preferred_class_id' => $classId,
                    'guardian_name' => 'Guardian '.$last,
                    'guardian_relationship' => 'Mother',
                    'guardian_phone' => '+220 7001'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                    'nationality' => 'Gambian',
                    'source' => $i % 2 === 0 ? 'online' : 'walk_in',
                    'previous_school' => 'Community Lower Basic',
                    'address' => 'Serrekunda',
                    'decision_reason' => in_array($status, ['accepted', 'rejected'], true) ? 'Reviewed by admissions' : null,
                    'decided_at' => in_array($status, ['accepted', 'rejected', 'waitlisted'], true) ? now()->subDays(3) : null,
                ]
            );
        }
    }

    /**
     * @param  list<Student>  $students
     * @param  array<string, SchoolClass>  $classes
     */
    private function seedFinance(School $school, array $students, AcademicYear $year, Term $term, array $classes, User $finance, User $head, User $admin): void
    {
        foreach ([
            ['Cash office', 'cash', 'Pay at the accounts window during school hours.', null, null, null],
            ['School Wave', 'wave', 'Send to the school Wave number and keep the confirmation SMS.', 'Bright Future Fees', '3000000', null],
            ['Trust Bank fees account', 'bank_transfer', 'Use the student number as the payment reference.', 'School Fees Account', '002-445566-09', 'Trust Bank'],
        ] as $i => [$label, $method, $instructions, $accountName, $accountNumber, $bank]) {
            SchoolPaymentMethod::query()->updateOrCreate(
                ['school_id' => $school->id, 'label' => $label],
                [
                    'method' => $method,
                    'instructions' => $instructions,
                    'account_name' => $accountName,
                    'account_number' => $accountNumber,
                    'bank_name' => $bank,
                    'is_active' => true,
                    'sort_order' => $i + 1,
                ]
            );
        }

        $cats = [];
        foreach ([
            ['TUITION', 'Tuition'],
            ['EXAM', 'Examination'],
            ['TRANSPORT', 'Transport'],
            ['BOARDING', 'Boarding'],
            ['PTA', 'PTA levy'],
        ] as [$code, $name]) {
            $cats[$code] = FeeCategory::query()->updateOrCreate(
                ['school_id' => $school->id, 'name' => $name],
                ['code' => $code, 'is_active' => true]
            );
        }

        foreach ($cats as $code => $cat) {
            $amount = ['TUITION' => 4500, 'EXAM' => 750, 'TRANSPORT' => 250, 'BOARDING' => 3500, 'PTA' => 200][$code];
            FeeStructure::query()->updateOrCreate(
                ['school_id' => $school->id, 'fee_category_id' => $cat->id, 'academic_year_id' => $year->id, 'term_id' => $term->id, 'name' => $cat->name.' Term 1'],
                ['amount' => $amount, 'is_optional' => in_array($code, ['TRANSPORT', 'BOARDING'], true), 'is_active' => true]
            );
            FeeItem::query()->updateOrCreate(
                ['school_id' => $school->id, 'name' => $cat->name],
                ['amount' => $amount, 'applicable_to' => 'all', 'type' => in_array($code, ['TRANSPORT', 'BOARDING'], true) ? 'optional' : 'compulsory', 'is_active' => true]
            );
        }

        $tuition = FeeItem::query()->where('school_id', $school->id)->where('name', 'Tuition')->first();
        $exam = FeeItem::query()->where('school_id', $school->id)->where('name', 'Examination')->first();

        foreach ($students as $i => $student) {
            $total = (int) $tuition->amount + (int) $exam->amount;
            $paid = $i % 5 === 0 ? 0 : ($i % 4 === 0 ? (int) ($total * 0.5) : $total);
            $status = $paid >= $total ? 'paid' : ($paid > 0 ? 'partial' : 'overdue');
            $number = 'INV-2026-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);

            $invoice = Invoice::query()->updateOrCreate(
                ['school_id' => $school->id, 'invoice_number' => $number],
                [
                    'student_id' => $student->id,
                    'academic_year_id' => $year->id,
                    'term_id' => $term->id,
                    'status' => $status,
                    'source' => 'structure',
                    'subtotal' => $total,
                    'adjustments_total' => 0,
                    'total_amount' => $total,
                    'amount_paid' => $paid,
                    'due_on' => now()->addDays(14)->toDateString(),
                    'issued_at' => now()->subDays(20),
                    'created_by' => $finance->id,
                ]
            );

            InvoiceItem::query()->updateOrCreate(
                ['invoice_id' => $invoice->id, 'fee_item_id' => $tuition->id],
                ['school_id' => $school->id, 'description' => 'Tuition Term 1', 'amount' => $tuition->amount, 'kind' => 'fee', 'fee_category_id' => $cats['TUITION']->id]
            );
            InvoiceItem::query()->updateOrCreate(
                ['invoice_id' => $invoice->id, 'fee_item_id' => $exam->id],
                ['school_id' => $school->id, 'description' => 'Examination Fee', 'amount' => $exam->amount, 'kind' => 'fee', 'fee_category_id' => $cats['EXAM']->id]
            );

            if ($i === 1) {
                foreach ([1, 2, 3] as $n) {
                    InvoiceInstallment::query()->updateOrCreate(
                        ['invoice_id' => $invoice->id, 'number' => $n],
                        [
                            'school_id' => $school->id,
                            'due_on' => now()->addDays($n * 21)->toDateString(),
                            'amount' => (int) floor($total / 3),
                            'amount_paid' => $n === 1 ? (int) floor($total / 3) : 0,
                        ]
                    );
                }
            }

            if ($paid > 0) {
                Payment::query()->updateOrCreate(
                    ['school_id' => $school->id, 'receipt_number' => 'RCPT-2026-'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT)],
                    [
                        'invoice_id' => $invoice->id,
                        'student_id' => $student->id,
                        'amount' => $paid,
                        'method' => $i % 3 === 0 ? 'wave' : ($i % 3 === 1 ? 'cash' : 'bank_transfer'),
                        'status' => 'confirmed',
                        'paid_at' => now()->subDays(4 + ($i % 6)),
                        'recorded_by' => $finance->id,
                        'receipt_sent' => true,
                        'payer_name' => $student->full_name.' guardian',
                    ]
                );
            }

            if ($i === 2) {
                FeeAdjustment::query()->updateOrCreate(
                    ['school_id' => $school->id, 'student_id' => $student->id, 'kind' => 'scholarship'],
                    [
                        'basis' => 'percent',
                        'value' => 25,
                        'fee_category_id' => $cats['TUITION']->id,
                        'academic_year_id' => $year->id,
                        'term_id' => $term->id,
                        'reason' => 'Headteacher scholarship for strong Grade 9 results.',
                        'status' => 'approved',
                        'requested_by' => $finance->id,
                        'decided_by' => $head->id,
                        'decided_at' => now()->subDays(2),
                    ]
                );
            }
            if ($i === 3) {
                FeeAdjustment::query()->updateOrCreate(
                    ['school_id' => $school->id, 'student_id' => $student->id, 'kind' => 'waiver'],
                    [
                        'basis' => 'fixed',
                        'value' => 500,
                        'reason' => 'PTA levy waiver pending review.',
                        'status' => 'pending',
                        'requested_by' => $finance->id,
                    ]
                );
            }

            StudentFee::query()->updateOrCreate(
                ['student_id' => $student->id, 'source' => 'demo-trip:sports-day'],
                [
                    'school_id' => $school->id,
                    'fee_category_id' => $cats['PTA']->id,
                    'academic_year_id' => $year->id,
                    'term_id' => $term->id,
                    'description' => 'Sports day contribution',
                    'amount' => 150,
                    'status' => $i < 8 ? 'invoiced' : 'pending',
                    'created_by' => $finance->id,
                ]
            );
        }
    }

    /**
     * @param  array<string, Subject>  $subjects
     * @param  array<string, SchoolClass>  $classes
     * @param  list<Student>  $students
     * @param  array<string, User>  $users
     */
    private function seedLms(School $school, array $subjects, array $classes, array $students, array $users, User $teacher): void
    {
        $stem = CourseCategory::query()->updateOrCreate(
            ['school_id' => $school->id, 'name' => 'STEM'],
            []
        );
        $languages = CourseCategory::query()->updateOrCreate(
            ['school_id' => $school->id, 'name' => 'Languages'],
            []
        );

        $math = Course::query()->updateOrCreate(
            ['school_id' => $school->id, 'code' => 'MATH-10'],
            [
                'subject_id' => $subjects['MATH']->id,
                'category_id' => $stem->id,
                'teacher_id' => $teacher->id,
                'created_by' => $teacher->id,
                'title' => 'Mathematics - Grade 10',
                'description' => 'Algebra, geometry, and problem solving for Grade 10.',
                'objectives' => ['Solve linear equations', 'Apply Pythagoras', 'Interpret graphs'],
                'status' => 'published',
            ]
        );
        $eng = Course::query()->updateOrCreate(
            ['school_id' => $school->id, 'code' => 'ENG-10'],
            [
                'subject_id' => $subjects['ENG']->id,
                'category_id' => $languages->id,
                'teacher_id' => $users['teacher_b']->id,
                'created_by' => $users['teacher_b']->id,
                'title' => 'English Language - Grade 10',
                'description' => 'Reading comprehension and composition.',
                'status' => 'published',
            ]
        );
        $bio = Course::query()->updateOrCreate(
            ['school_id' => $school->id, 'code' => 'BIO-12'],
            [
                'subject_id' => $subjects['BIO']->id,
                'category_id' => $stem->id,
                'teacher_id' => $users['teacher_c']->id,
                'created_by' => $users['teacher_c']->id,
                'title' => 'Biology - Grade 12',
                'description' => 'Exam-focused biology for Grade 12.',
                'status' => 'published',
            ]
        );

        foreach ([$math, $eng, $bio] as $course) {
            CourseTeacher::query()->updateOrCreate(
                ['course_id' => $course->id, 'user_id' => $course->teacher_id],
                ['school_id' => $school->id]
            );
        }
        CourseAudience::query()->updateOrCreate(
            ['course_id' => $math->id, 'school_class_id' => $classes['Grade 10A']->id],
            ['school_id' => $school->id]
        );
        CourseAudience::query()->updateOrCreate(
            ['course_id' => $math->id, 'school_class_id' => $classes['Grade 10B']->id],
            ['school_id' => $school->id]
        );
        CourseAudience::query()->updateOrCreate(
            ['course_id' => $bio->id, 'school_class_id' => $classes['Grade 12A']->id],
            ['school_id' => $school->id]
        );

        $grade10 = array_values(array_filter($students, fn (Student $s) => in_array($s->schoolClass?->name, ['Grade 10A', 'Grade 10B'], true)));
        foreach (array_slice($grade10, 0, 12) as $student) {
            CourseEnrolment::query()->updateOrCreate(
                ['course_id' => $math->id, 'student_id' => $student->id],
                ['school_id' => $school->id, 'status' => 'active', 'source' => 'class', 'enrolled_at' => now()->subDays(30)]
            );
        }

        $assignment = Assignment::query()->updateOrCreate(
            ['school_id' => $school->id, 'course_id' => $math->id, 'title' => 'Algebra Worksheet'],
            [
                'instructions' => 'Complete questions 1–12 and upload your solutions.',
                'due_at' => now()->addDays(3),
                'max_score' => 100,
            ]
        );

        foreach (array_slice($grade10, 0, 4) as $i => $student) {
            AssignmentSubmission::query()->updateOrCreate(
                ['assignment_id' => $assignment->id, 'student_id' => $student->id, 'attempt_no' => 1],
                [
                    'school_id' => $school->id,
                    'body' => 'Worked solutions for questions 1–12.',
                    'submitted_at' => now()->subDays(2 - $i),
                    'is_late' => false,
                    'status' => $i < 2 ? 'graded' : 'submitted',
                    'score' => $i < 2 ? 72 + $i * 8 : null,
                    'rubric_scores' => $i < 2 ? ['Accuracy' => 30, 'Working shown' => 24, 'Presentation' => 20] : null,
                    'feedback' => $i < 2 ? 'Clear working. Watch signs on question 8.' : null,
                    'graded_by' => $i < 2 ? $teacher->id : null,
                    'graded_at' => $i < 2 ? now()->subDay() : null,
                ]
            );
        }

        $quiz = Quiz::query()->updateOrCreate(
            ['school_id' => $school->id, 'course_id' => $math->id, 'title' => 'Linear Equations Check'],
            ['opens_at' => now()->subDay(), 'closes_at' => now()->addDays(7), 'max_score' => 4]
        );
        $q1 = QuizQuestion::query()->where('quiz_id', $quiz->id)->orderBy('sort_order')->first();
        if ($q1 && isset($grade10[0])) {
            $attempt = QuizAttempt::query()->updateOrCreate(
                ['quiz_id' => $quiz->id, 'student_id' => $grade10[0]->id, 'attempt_no' => 1],
                [
                    'school_id' => $school->id,
                    'status' => 'submitted',
                    'score' => 4,
                    'max_score' => 4,
                    'started_at' => now()->subHours(3),
                    'submitted_at' => now()->subHours(2),
                    'grading_status' => 'graded',
                    'graded_at' => now()->subHours(2),
                ]
            );
            QuizAttemptAnswer::query()->updateOrCreate(
                ['quiz_attempt_id' => $attempt->id, 'quiz_question_id' => $q1->id],
                [
                    'school_id' => $school->id,
                    'answer' => 'x = 3',
                    'is_correct' => true,
                    'points_awarded' => 2,
                ]
            );
        }

        $bank = QuestionBank::query()->updateOrCreate(
            ['school_id' => $school->id, 'title' => 'Grade 10 algebra bank'],
            ['subject_id' => $subjects['MATH']->id, 'is_shared' => true, 'created_by' => $teacher->id]
        );
        BankQuestion::query()->updateOrCreate(
            ['question_bank_id' => $bank->id, 'prompt' => 'What is the slope of y = 2x + 1?'],
            [
                'school_id' => $school->id,
                'type' => 'multiple_choice',
                'options' => ['1', '2', '3', '0'],
                'correct_answer' => '2',
                'points' => 1,
            ]
        );

        CourseAnnouncement::query()->updateOrCreate(
            ['school_id' => $school->id, 'course_id' => $math->id, 'title' => 'Worksheet due Friday'],
            [
                'user_id' => $teacher->id,
                'body' => 'Upload the algebra worksheet before Friday assembly.',
                'publish_at' => now()->subDay(),
                'is_pinned' => true,
            ]
        );

        $thread = ForumThread::query()->updateOrCreate(
            ['school_id' => $school->id, 'course_id' => $math->id, 'title' => 'How did you rearrange 2x + 4 = 10?'],
            [
                'user_id' => $grade10[0]->user_id ?? $teacher->id,
                'type' => 'question',
                'body' => 'I subtracted 4 first. Is that right?',
                'is_pinned' => false,
                'last_post_at' => now()->subHours(5),
            ]
        );
        ForumPost::query()->updateOrCreate(
            ['forum_thread_id' => $thread->id, 'body' => 'Yes — subtract 4, then divide by 2.'],
            ['school_id' => $school->id, 'user_id' => $teacher->id]
        );
    }

    /** @param  list<Student>  $students */
    private function seedLibrary(School $school, array $students, User $librarian, Staff $teacherStaff): void
    {
        $books = [
            ['isbn' => '978-0-demo-001', 'title' => 'Mathematics for Senior Secondary', 'author' => 'WAEC Prep', 'category' => 'Mathematics', 'copies' => 5],
            ['isbn' => '978-0-demo-002', 'title' => 'English Composition Made Simple', 'author' => 'A. Njie', 'category' => 'English', 'copies' => 4],
            ['isbn' => '978-0-demo-003', 'title' => 'Biology for GABECE', 'author' => 'Science Series', 'category' => 'Science', 'copies' => 6],
            ['isbn' => '978-0-demo-004', 'title' => 'The Gambia: Land and People', 'author' => 'History Desk', 'category' => 'Social Studies', 'copies' => 3],
            ['isbn' => '978-0-demo-005', 'title' => 'Stories from the River', 'author' => 'Lenrie Peters', 'category' => 'Literature', 'copies' => 2],
        ];

        foreach ($books as $b) {
            $book = LibraryBook::query()->updateOrCreate(
                ['school_id' => $school->id, 'isbn' => $b['isbn']],
                [
                    'title' => $b['title'],
                    'author' => $b['author'],
                    'category' => $b['category'],
                    'copies_total' => $b['copies'],
                    'copies_available' => max(1, $b['copies'] - 1),
                    'status' => 'available',
                    'publisher' => 'Banjul Education Press',
                    'publication_year' => 2022,
                    'language' => 'English',
                    'shelf_location' => 'A-'.$b['copies'],
                ]
            );
            for ($i = 1; $i <= $b['copies']; $i++) {
                LibraryCopy::query()->updateOrCreate(
                    ['school_id' => $school->id, 'accession_number' => 'BF-'.$book->id.'-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT)],
                    [
                        'library_book_id' => $book->id,
                        'status' => $i === 1 ? 'on_loan' : 'available',
                        'acquired_on' => '2025-01-15',
                    ]
                );
            }
        }

        $firstBook = LibraryBook::query()->where('school_id', $school->id)->where('isbn', '978-0-demo-001')->first();
        $copy = LibraryCopy::query()->where('library_book_id', $firstBook?->id)->orderBy('id')->first();
        if ($firstBook && $copy && isset($students[0])) {
            $loan = LibraryLoan::query()->updateOrCreate(
                ['school_id' => $school->id, 'library_book_id' => $firstBook->id, 'student_id' => $students[0]->id, 'status' => 'out'],
                [
                    'library_copy_id' => $copy->id,
                    'loaned_on' => now()->subDays(18)->toDateString(),
                    'due_on' => now()->subDays(4)->toDateString(),
                    'issued_by' => $librarian->id,
                ]
            );
            LibraryFine::query()->updateOrCreate(
                ['library_loan_id' => $loan->id],
                [
                    'school_id' => $school->id,
                    'student_id' => $students[0]->id,
                    'amount' => 50,
                    'reason' => 'overdue',
                    'status' => 'unpaid',
                    'note' => 'Four days overdue.',
                ]
            );
        }

        $second = LibraryBook::query()->where('school_id', $school->id)->where('isbn', '978-0-demo-002')->first();
        $copy2 = LibraryCopy::query()->where('library_book_id', $second?->id)->orderBy('id')->first();
        if ($second && $copy2 && isset($students[1])) {
            LibraryLoan::query()->updateOrCreate(
                ['school_id' => $school->id, 'library_book_id' => $second->id, 'student_id' => $students[1]->id, 'status' => 'returned'],
                [
                    'library_copy_id' => $copy2->id,
                    'loaned_on' => now()->subDays(20)->toDateString(),
                    'due_on' => now()->subDays(6)->toDateString(),
                    'returned_on' => now()->subDays(7)->toDateString(),
                    'issued_by' => $librarian->id,
                    'received_by' => $librarian->id,
                ]
            );
        }
    }

    /** @param  array<string, User>  $users */
    private function seedInventoryAndProcurement(School $school, array $users, array $staff, User $store, User $head, User $finance): void
    {
        $suppliers = [];
        foreach ([
            ['Gambia Stationery Ltd', 'Fatou Sowe', '+220 4390001', 'sales@gambstat.test'],
            ['Kanifing Science Supplies', 'Omar Jallow', '+220 4390002', 'lab@kss.test'],
            ['Coastal Print', 'Awa Njie', '+220 4390003', 'print@coastal.test'],
        ] as [$name, $person, $phone, $email]) {
            $suppliers[$name] = Supplier::query()->updateOrCreate(
                ['school_id' => $school->id, 'name' => $name],
                ['contact_person' => $person, 'phone' => $phone, 'email' => $email, 'address' => 'Kanifing', 'is_active' => true]
            );
        }

        $storeRoom = InventoryLocation::query()->updateOrCreate(
            ['school_id' => $school->id, 'name' => 'Main store'],
            ['description' => 'Locked store beside the accounts office']
        );
        $labStore = InventoryLocation::query()->updateOrCreate(
            ['school_id' => $school->id, 'name' => 'Science prep room'],
            ['description' => 'Lab consumables']
        );

        $cats = [];
        foreach (['Stationery', 'Science', 'ICT', 'Furniture'] as $name) {
            $cats[$name] = InventoryCategory::query()->updateOrCreate(
                ['school_id' => $school->id, 'name' => $name],
                []
            );
        }

        $items = [
            ['PEN-1', 'Ballpoint pens (box)', 'Stationery', 20, 8],
            ['EXB-1', 'Exercise books (pack of 10)', 'Stationery', 40, 15],
            ['CHL-1', 'Chalk (box)', 'Stationery', 10, 4],
            ['BUN-1', 'Bunsen burners', 'Science', 5, 12],
            ['LAP-1', 'Laptop bags', 'ICT', 3, 2],
        ];
        $itemModels = [];
        foreach ($items as [$sku, $name, $cat, $reorder, $qty]) {
            $item = InventoryItem::query()->updateOrCreate(
                ['school_id' => $school->id, 'sku' => $sku],
                [
                    'inventory_category_id' => $cats[$cat]->id,
                    'name' => $name,
                    'unit' => 'pcs',
                    'reorder_level' => $reorder,
                    'is_active' => true,
                    'low_stock_alerted_at' => $qty <= $reorder ? now()->subDay() : null,
                ]
            );
            $itemModels[$sku] = $item;
            InventoryStock::query()->updateOrCreate(
                ['inventory_item_id' => $item->id, 'inventory_location_id' => $storeRoom->id],
                ['school_id' => $school->id, 'quantity' => $qty]
            );
            StockMovement::query()->updateOrCreate(
                ['school_id' => $school->id, 'inventory_item_id' => $item->id, 'reference' => 'SEED-OPEN-'.$sku],
                [
                    'inventory_location_id' => $storeRoom->id,
                    'type' => 'receipt',
                    'quantity' => $qty,
                    'balance_after' => $qty,
                    'reason' => 'Opening balance',
                    'user_id' => $store->id,
                ]
            );
        }

        $projector = Asset::query()->updateOrCreate(
            ['school_id' => $school->id, 'tag' => 'AST-PRJ-001'],
            [
                'name' => 'Classroom projector',
                'inventory_category_id' => $cats['ICT']->id,
                'inventory_location_id' => $storeRoom->id,
                'supplier_id' => $suppliers['Kanifing Science Supplies']->id,
                'serial_number' => 'PRJ-DEMO-001',
                'acquired_on' => '2025-03-01',
                'cost' => 18500,
                'condition' => 'good',
                'status' => 'in_use',
                'assigned_staff_id' => $staff['teacher']->id,
            ]
        );
        AssetAssignment::query()->updateOrCreate(
            ['asset_id' => $projector->id, 'staff_id' => $staff['teacher']->id, 'assigned_on' => '2026-09-01'],
            ['school_id' => $school->id, 'assigned_by' => $store->id, 'notes' => 'Issued for Grade 10A']
        );
        Asset::query()->updateOrCreate(
            ['school_id' => $school->id, 'tag' => 'AST-LAP-002'],
            [
                'name' => 'Admin laptop',
                'inventory_category_id' => $cats['ICT']->id,
                'serial_number' => 'LAP-DEMO-002',
                'acquired_on' => '2024-11-12',
                'cost' => 42000,
                'condition' => 'good',
                'status' => 'in_store',
            ]
        );
        $printer = Asset::query()->updateOrCreate(
            ['school_id' => $school->id, 'tag' => 'AST-PRT-003'],
            [
                'name' => 'Office printer',
                'inventory_category_id' => $cats['ICT']->id,
                'serial_number' => 'PRT-DEMO-003',
                'cost' => 15000,
                'condition' => 'fair',
                'status' => 'under_maintenance',
            ]
        );
        AssetMaintenance::query()->updateOrCreate(
            ['asset_id' => $printer->id, 'kind' => 'repair', 'scheduled_on' => now()->toDateString()],
            [
                'school_id' => $school->id,
                'description' => 'Paper jam mechanism',
                'status' => 'scheduled',
                'cost' => 800,
                'performed_by' => 'Coastal Print',
            ]
        );

        $pr = PurchaseRequest::query()->updateOrCreate(
            ['school_id' => $school->id, 'number' => 'PR-2026-001'],
            [
                'title' => 'Term 1 stationery restock',
                'justification' => 'Exercise books and pens running low.',
                'needed_by' => now()->addDays(10)->toDateString(),
                'status' => 'approved',
                'estimated_total' => 8500,
                'requested_by' => $store->id,
                'decided_by' => $head->id,
                'decided_at' => now()->subDays(2),
            ]
        );
        $prItem = PurchaseRequestItem::query()->updateOrCreate(
            ['purchase_request_id' => $pr->id, 'description' => 'Exercise books (pack of 10)'],
            [
                'school_id' => $school->id,
                'inventory_item_id' => $itemModels['EXB-1']->id,
                'quantity' => 40,
                'estimated_unit_cost' => 180,
            ]
        );
        PurchaseRequest::query()->updateOrCreate(
            ['school_id' => $school->id, 'number' => 'PR-2026-002'],
            [
                'title' => 'Lab glassware',
                'justification' => 'Replace broken test tubes.',
                'needed_by' => now()->addDays(21)->toDateString(),
                'status' => 'submitted',
                'estimated_total' => 2200,
                'requested_by' => $users['teacher_c']->id,
            ]
        );

        $po = PurchaseOrder::query()->updateOrCreate(
            ['school_id' => $school->id, 'number' => 'PO-2026-001'],
            [
                'supplier_id' => $suppliers['Gambia Stationery Ltd']->id,
                'purchase_request_id' => $pr->id,
                'status' => 'received',
                'ordered_on' => now()->subDays(8)->toDateString(),
                'expected_on' => now()->subDays(2)->toDateString(),
                'total_amount' => 7200,
                'created_by' => $finance->id,
            ]
        );
        $poItem = PurchaseOrderItem::query()->updateOrCreate(
            ['purchase_order_id' => $po->id, 'description' => 'Exercise books (pack of 10)'],
            [
                'school_id' => $school->id,
                'inventory_item_id' => $itemModels['EXB-1']->id,
                'quantity' => 40,
                'unit_cost' => 180,
                'received_quantity' => 40,
            ]
        );
        $gr = GoodsReceipt::query()->updateOrCreate(
            ['school_id' => $school->id, 'number' => 'GR-2026-001'],
            [
                'purchase_order_id' => $po->id,
                'inventory_location_id' => $storeRoom->id,
                'received_on' => now()->subDays(2)->toDateString(),
                'received_by' => $store->id,
                'note' => 'All cartons intact.',
            ]
        );
        GoodsReceiptItem::query()->updateOrCreate(
            ['goods_receipt_id' => $gr->id, 'purchase_order_item_id' => $poItem->id],
            ['school_id' => $school->id, 'quantity' => 40]
        );
        SupplierInvoice::query()->updateOrCreate(
            ['school_id' => $school->id, 'supplier_id' => $suppliers['Gambia Stationery Ltd']->id, 'invoice_number' => 'GSL-8821'],
            [
                'purchase_order_id' => $po->id,
                'invoice_date' => now()->subDays(2)->toDateString(),
                'due_on' => now()->addDays(14)->toDateString(),
                'amount' => 7200,
                'status' => 'approved',
                'recorded_by' => $store->id,
                'approved_by' => $finance->id,
            ]
        );
    }

    /** @param  list<Student>  $students */
    private function seedTransport(School $school, array $students, array $staff, User $officer): void
    {
        $driver = TransportDriver::query()->updateOrCreate(
            ['school_id' => $school->id, 'licence_number' => 'GM-LIC-4401'],
            [
                'staff_id' => $staff['transport']->id,
                'name' => 'Ebrima Ceesay',
                'phone' => '+220 3000099',
                'licence_expires_on' => now()->addMonths(8)->toDateString(),
                'status' => 'active',
            ]
        );
        $bus = TransportVehicle::query()->updateOrCreate(
            ['school_id' => $school->id, 'plate_number' => 'BJL-1001'],
            [
                'name' => 'Bus 1',
                'capacity' => 40,
                'driver_name' => 'Ebrima Ceesay',
                'driver_phone' => '+220 3000099',
                'status' => 'active',
                'transport_driver_id' => $driver->id,
                'make_model' => 'Toyota Coaster',
                'insurance_expires_on' => now()->addMonths(5)->toDateString(),
                'inspection_due_on' => now()->addMonths(2)->toDateString(),
            ]
        );
        $bus2 = TransportVehicle::query()->updateOrCreate(
            ['school_id' => $school->id, 'plate_number' => 'BJL-1002'],
            [
                'name' => 'Bus 2',
                'capacity' => 25,
                'status' => 'active',
                'make_model' => 'Hyundai County',
            ]
        );
        $route = TransportRoute::query()->updateOrCreate(
            ['school_id' => $school->id, 'name' => 'Kanifing Loop'],
            [
                'transport_vehicle_id' => $bus->id,
                'area' => 'Kanifing',
                'fee_monthly' => 250,
                'fee_per_term' => 750,
                'status' => 'active',
            ]
        );
        TransportRoute::query()->updateOrCreate(
            ['school_id' => $school->id, 'name' => 'Brikama Express'],
            [
                'transport_vehicle_id' => $bus2->id,
                'area' => 'Brikama',
                'fee_monthly' => 400,
                'fee_per_term' => 1200,
                'status' => 'active',
            ]
        );

        $stops = [];
        foreach ([['Kairaba Avenue', 1, '06:40:00'], ['Pipeline', 2, '06:50:00'], ['School gate', 3, '07:15:00']] as [$name, $seq, $time]) {
            $stops[] = TransportStop::query()->updateOrCreate(
                ['transport_route_id' => $route->id, 'name' => $name],
                ['school_id' => $school->id, 'sequence' => $seq, 'pickup_time' => $time, 'drop_time' => '14:40:00']
            );
        }

        foreach (array_slice($students, 0, 6) as $i => $student) {
            TransportAssignment::query()->updateOrCreate(
                ['transport_route_id' => $route->id, 'student_id' => $student->id],
                [
                    'school_id' => $school->id,
                    'stop_name' => $stops[$i % 3]->name,
                    'transport_stop_id' => $stops[$i % 3]->id,
                    'status' => 'active',
                    'starts_on' => '2026-09-01',
                ]
            );
            $travelDate = now()->toDateString();
            $ta = TransportAttendance::query()
                ->where('transport_route_id', $route->id)
                ->where('student_id', $student->id)
                ->whereDate('travel_date', $travelDate)
                ->where('direction', 'pickup')
                ->first();
            $taPayload = [
                'school_id' => $school->id,
                'status' => $i === 4 ? 'absent' : 'boarded',
                'recorded_by' => $officer->id,
            ];
            if ($ta) {
                $ta->update($taPayload);
            } else {
                TransportAttendance::query()->create($taPayload + [
                    'transport_route_id' => $route->id,
                    'student_id' => $student->id,
                    'travel_date' => $travelDate,
                    'direction' => 'pickup',
                ]);
            }
        }

        VehicleMaintenance::query()->updateOrCreate(
            ['transport_vehicle_id' => $bus->id, 'kind' => 'service', 'performed_on' => now()->subDays(12)->toDateString()],
            [
                'school_id' => $school->id,
                'cost' => 4500,
                'vendor' => 'Kotu Motors',
                'next_due_on' => now()->addMonths(3)->toDateString(),
                'notes' => 'Oil and filters',
                'recorded_by' => $officer->id,
            ]
        );
    }

    /** @param  list<Student>  $students */
    private function seedBoarding(School $school, array $students, array $staff, User $head): void
    {
        $hostel = BoardingHostel::query()->updateOrCreate(
            ['school_id' => $school->id, 'name' => 'Sunrise House'],
            [
                'gender' => 'mixed',
                'warden_name' => 'Mrs. Njie',
                'status' => 'active',
                'house_master_staff_id' => $staff['deputy']->id,
                'fee_per_term' => 3500,
                'description' => 'Main boarding house beside the sports field.',
            ]
        );
        $room = BoardingRoom::query()->updateOrCreate(
            ['school_id' => $school->id, 'boarding_hostel_id' => $hostel->id, 'name' => 'A1'],
            ['capacity' => 4, 'occupied' => 2, 'status' => 'available']
        );
        $beds = [];
        foreach (['A1-1', 'A1-2', 'A1-3', 'A1-4'] as $label) {
            $beds[] = BoardingBed::query()->updateOrCreate(
                ['boarding_room_id' => $room->id, 'label' => $label],
                ['school_id' => $school->id, 'in_service' => true]
            );
        }
        foreach (array_slice($students, 1, 2) as $i => $student) {
            BoardingAllocation::query()->updateOrCreate(
                ['boarding_room_id' => $room->id, 'student_id' => $student->id, 'status' => 'active'],
                [
                    'school_id' => $school->id,
                    'boarding_bed_id' => $beds[$i]->id,
                    'starts_on' => now()->subMonths(2)->toDateString(),
                ]
            );
            $boardingOn = now()->toDateString();
            $ba = BoardingAttendance::query()
                ->where('student_id', $student->id)
                ->whereDate('attended_on', $boardingOn)
                ->where('session', 'evening')
                ->first();
            if ($ba) {
                $ba->update(['status' => 'present', 'recorded_by' => $head->id]);
            } else {
                BoardingAttendance::query()->create([
                    'school_id' => $school->id,
                    'student_id' => $student->id,
                    'attended_on' => $boardingOn,
                    'session' => 'evening',
                    'status' => 'present',
                    'recorded_by' => $head->id,
                ]);
            }
        }
        if (isset($students[1])) {
            BoardingWelfareRecord::query()->updateOrCreate(
                ['school_id' => $school->id, 'student_id' => $students[1]->id, 'kind' => 'health'],
                ['note' => 'Mild fever overnight; paracetamol given and parent informed.', 'recorded_by' => $head->id]
            );
        }
    }

    /**
     * @param  array<string, SchoolClass>  $classes
     * @param  list<Student>  $students
     */
    private function seedEvents(School $school, array $classes, array $students, array $staff, User $head): void
    {
        $hall = EventVenue::query()->updateOrCreate(
            ['school_id' => $school->id, 'name' => 'School hall'],
            ['capacity' => 200, 'location' => 'Main Block']
        );
        $field = EventVenue::query()->updateOrCreate(
            ['school_id' => $school->id, 'name' => 'Sports field'],
            ['capacity' => 400, 'location' => 'West campus']
        );

        $sports = SchoolEvent::query()->updateOrCreate(
            ['school_id' => $school->id, 'title' => 'Annual sports day'],
            [
                'event_venue_id' => $field->id,
                'description' => 'House competitions, 100m, and a parents’ race.',
                'category' => 'sports',
                'starts_at' => now()->addDays(8)->setTime(8, 0),
                'ends_at' => now()->addDays(8)->setTime(15, 0),
                'audience' => 'all',
                'capacity' => 400,
                'registration_required' => true,
                'registration_closes_at' => now()->addDays(6),
                'status' => 'published',
                'announced_at' => now()->subDays(3),
                'created_by' => $head->id,
            ]
        );
        foreach (array_slice($students, 0, 8) as $student) {
            EventRegistration::query()->updateOrCreate(
                ['school_event_id' => $sports->id, 'student_id' => $student->id],
                ['school_id' => $school->id, 'registered_by' => $head->id, 'status' => 'registered']
            );
        }

        SchoolEvent::query()->updateOrCreate(
            ['school_id' => $school->id, 'title' => 'PTA meeting — Term 1'],
            [
                'event_venue_id' => $hall->id,
                'description' => 'Progress reports and fee reminders.',
                'category' => 'meeting',
                'starts_at' => now()->addDays(12)->setTime(16, 0),
                'ends_at' => now()->addDays(12)->setTime(18, 0),
                'audience' => 'parents',
                'status' => 'published',
                'created_by' => $head->id,
            ]
        );
        SchoolEvent::query()->updateOrCreate(
            ['school_id' => $school->id, 'title' => 'Grade 10A literacy evening'],
            [
                'event_venue_id' => $hall->id,
                'category' => 'academic',
                'starts_at' => now()->addDays(18)->setTime(17, 0),
                'ends_at' => now()->addDays(18)->setTime(19, 0),
                'audience' => 'class',
                'school_class_id' => $classes['Grade 10A']->id,
                'status' => 'draft',
                'created_by' => $head->id,
            ]
        );
        SchoolEvent::query()->updateOrCreate(
            ['school_id' => $school->id, 'title' => 'Independence assembly'],
            [
                'event_venue_id' => $hall->id,
                'category' => 'cultural',
                'starts_at' => now()->subDays(30)->setTime(8, 0),
                'ends_at' => now()->subDays(30)->setTime(9, 30),
                'audience' => 'all',
                'status' => 'completed',
                'created_by' => $head->id,
            ]
        );
    }

    /**
     * @param  array<string, User>  $users
     * @param  list<Student>  $students
     * @param  array<string, SchoolClass>  $classes
     */
    private function seedComms(School $school, array $users, array $students, array $classes): void
    {
        $schoolWide = Announcement::query()->updateOrCreate(
            ['school_id' => $school->id, 'title' => 'Term 1 examinations timetable'],
            [
                'author_id' => $users['headteacher']->id,
                'body' => 'Examinations begin 24 November. Learners should arrive by 07:30. See the notice board for the full timetable.',
                'audience' => 'school',
                'channels' => ['in_app', 'email'],
                'status' => 'published',
                'publish_at' => now()->subDays(2),
                'published_at' => now()->subDays(2),
                'recipients_count' => 4,
            ]
        );
        foreach (['school_admin', 'teacher', 'parent', 'student'] as $key) {
            AnnouncementRecipient::query()->updateOrCreate(
                ['announcement_id' => $schoolWide->id, 'user_id' => $users[$key]->id],
                ['school_id' => $school->id, 'read_at' => $key === 'teacher' ? now()->subDay() : null]
            );
        }

        Announcement::query()->updateOrCreate(
            ['school_id' => $school->id, 'title' => 'Grade 10A homework club'],
            [
                'author_id' => $users['teacher']->id,
                'body' => 'Homework club runs on Tuesdays in Room 12 after last period.',
                'audience' => 'classes',
                'class_ids' => [$classes['Grade 10A']->id],
                'include_students' => true,
                'include_guardians' => true,
                'channels' => ['in_app'],
                'status' => 'published',
                'publish_at' => now()->subDays(1),
                'published_at' => now()->subDays(1),
            ]
        );
        Announcement::query()->updateOrCreate(
            ['school_id' => $school->id, 'title' => 'Staff briefing draft'],
            [
                'author_id' => $users['headteacher']->id,
                'body' => 'Draft notes for Monday briefing — not yet published.',
                'audience' => 'staff',
                'channels' => ['in_app'],
                'status' => 'draft',
            ]
        );

        if ($users['parent']->id && $users['teacher']->id && isset($students[0])) {
            $convo = Conversation::query()->updateOrCreate(
                ['school_id' => $school->id, 'subject' => 'Amadou — late arrival this week'],
                [
                    'type' => 'direct',
                    'student_id' => $students[0]->id,
                    'created_by' => $users['teacher']->id,
                    'replies_allowed' => true,
                    'last_message_at' => now()->subHours(2),
                ]
            );
            foreach ([$users['teacher'], $users['parent']] as $i => $participant) {
                ConversationParticipant::query()->updateOrCreate(
                    ['conversation_id' => $convo->id, 'user_id' => $participant->id],
                    ['school_id' => $school->id, 'role' => $i === 0 ? 'owner' : 'member', 'last_read_at' => now()->subHour()]
                );
            }
            Message::query()->updateOrCreate(
                ['conversation_id' => $convo->id, 'body' => 'Amadou arrived after assembly on Tuesday. Could you help us with a regular morning routine?'],
                ['school_id' => $school->id, 'user_id' => $users['teacher']->id]
            );
            Message::query()->updateOrCreate(
                ['conversation_id' => $convo->id, 'body' => 'Thank you for the message. We will leave home earlier from tomorrow.'],
                ['school_id' => $school->id, 'user_id' => $users['parent']->id]
            );
        }

        $group = Conversation::query()->updateOrCreate(
            ['school_id' => $school->id, 'subject' => 'Grade 10A class group'],
            [
                'type' => 'class_group',
                'school_class_id' => $classes['Grade 10A']->id,
                'created_by' => $users['teacher']->id,
                'replies_allowed' => true,
                'last_message_at' => now()->subHours(6),
            ]
        );
        ConversationParticipant::query()->updateOrCreate(
            ['conversation_id' => $group->id, 'user_id' => $users['teacher']->id],
            ['school_id' => $school->id, 'role' => 'owner']
        );
        Message::query()->updateOrCreate(
            ['conversation_id' => $group->id, 'body' => 'Please bring graph paper for Friday’s lesson.'],
            ['school_id' => $school->id, 'user_id' => $users['teacher']->id]
        );

        SmsTemplate::query()->updateOrCreate(
            ['school_id' => $school->id, 'key' => 'attendance_alert', 'locale' => 'en'],
            [
                'body' => '{name} was marked {status} today at '.$school->name.'. Please contact the school if this is unexpected.',
                'active' => true,
            ]
        );
        SmsMessage::query()->updateOrCreate(
            ['school_id' => $school->id, 'provider_ref' => 'SEED-SMS-001'],
            [
                'user_id' => $users['parent']->id,
                'to_phone' => '+2207000118',
                'template_key' => 'attendance_alert',
                'category' => 'attendance',
                'body' => 'Amadou Jallow was marked absent today. This is a logged demo SMS — not sent by a live provider.',
                'segments' => 1,
                'status' => 'logged',
                'provider' => 'demo',
            ]
        );
    }

    /** @param  list<Student>  $students */
    private function seedExternalExams(School $school, array $students, User $examOfficer, User $records): void
    {
        $gabece = ExternalExamSession::query()->updateOrCreate(
            ['school_id' => $school->id, 'type' => 'gabece', 'exam_year' => 2026],
            [
                'name' => 'GABECE 2026',
                'registration_opens_on' => '2025-11-01',
                'registration_closes_on' => '2026-02-28',
                'status' => 'open',
            ]
        );
        $wassce = ExternalExamSession::query()->updateOrCreate(
            ['school_id' => $school->id, 'type' => 'wassce', 'exam_year' => 2026],
            [
                'name' => 'WASSCE 2026',
                'registration_opens_on' => '2025-10-01',
                'registration_closes_on' => '2026-01-31',
                'status' => 'open',
            ]
        );

        $gabeceSubjects = [];
        foreach ([['ENG', 'English Language'], ['MATH', 'Mathematics'], ['SCI', 'Science'], ['SOC', 'Social Studies'], ['FRE', 'French']] as [$code, $name]) {
            $gabeceSubjects[$code] = ExternalExamSubject::query()->updateOrCreate(
                ['school_id' => $school->id, 'exam_type' => 'gabece', 'code' => $code],
                ['name' => $name, 'is_active' => true]
            );
        }
        $wassceSubjects = [];
        foreach ([['ENG', 'English Language'], ['MATH', 'Mathematics'], ['BIO', 'Biology'], ['PHY', 'Physics'], ['CHE', 'Chemistry'], ['GOV', 'Government']] as [$code, $name]) {
            $wassceSubjects[$code] = ExternalExamSubject::query()->updateOrCreate(
                ['school_id' => $school->id, 'exam_type' => 'wassce', 'code' => $code],
                ['name' => $name, 'is_active' => true]
            );
        }

        $g9 = array_values(array_filter($students, fn (Student $s) => $s->schoolClass?->name === 'Grade 9A'));
        $g12 = array_values(array_filter($students, fn (Student $s) => $s->schoolClass?->name === 'Grade 12A'));

        foreach (array_slice($g9, 0, 6) as $i => $student) {
            $candidate = ExternalCandidate::query()->updateOrCreate(
                ['session_id' => $gabece->id, 'student_id' => $student->id],
                [
                    'school_id' => $school->id,
                    'internal_reference' => sprintf('IC-GABECE-2026-%05d', 90001 + $i),
                    'official_index_number' => $i < 3 ? 'DEMO/GABECE/2026/'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT) : null,
                    'index_recorded_at' => $i < 3 ? now()->subDays(5) : null,
                    'index_recorded_by' => $i < 3 ? $examOfficer->id : null,
                    'status' => 'registered',
                    'registered_on' => now()->subDays(20)->toDateString(),
                    'registered_by' => $examOfficer->id,
                    'details' => [
                        'full_name' => $student->full_name,
                        'student_number' => $student->student_number,
                        'class' => 'Grade 9A',
                    ],
                ]
            );
            foreach (array_slice($gabeceSubjects, 0, 4) as $subject) {
                ExternalCandidateSubject::query()->updateOrCreate(
                    ['candidate_id' => $candidate->id, 'subject_id' => $subject->id],
                    ['school_id' => $school->id]
                );
            }
            if ($i < 2) {
                $eng = $gabeceSubjects['ENG'];
                ExternalResult::query()->updateOrCreate(
                    ['candidate_id' => $candidate->id, 'subject_id' => $eng->id],
                    [
                        'school_id' => $school->id,
                        'grade' => $i === 0 ? 'B3' : 'C4',
                        'status' => $i === 0 ? 'published' : 'verified',
                        'source' => 'manual',
                        'verified_by' => $records->id,
                        'verified_at' => now()->subDays(2),
                        'published_by' => $i === 0 ? $records->id : null,
                        'published_at' => $i === 0 ? now()->subDay() : null,
                    ]
                );
            }
            ResultCollection::query()->updateOrCreate(
                ['candidate_id' => $candidate->id],
                [
                    'school_id' => $school->id,
                    'received_on' => now()->subDays(4)->toDateString(),
                    'status' => $i === 0 ? 'collected' : 'outstanding',
                    'collected_on' => $i === 0 ? now()->subDay()->toDateString() : null,
                    'collector_name' => $i === 0 ? 'Parent '.$student->last_name : null,
                    'collector_relationship' => $i === 0 ? 'Father' : null,
                    'staff_user_id' => $records->id,
                ]
            );
        }

        foreach (array_slice($g12, 0, 6) as $i => $student) {
            $candidate = ExternalCandidate::query()->updateOrCreate(
                ['session_id' => $wassce->id, 'student_id' => $student->id],
                [
                    'school_id' => $school->id,
                    'internal_reference' => sprintf('IC-WASSCE-2026-%05d', 90001 + $i),
                    'official_index_number' => $i === 0 ? 'DEMO/WASSCE/2026/001' : null,
                    'index_recorded_at' => $i === 0 ? now()->subDays(3) : null,
                    'index_recorded_by' => $i === 0 ? $examOfficer->id : null,
                    'status' => 'registered',
                    'registered_on' => now()->subDays(15)->toDateString(),
                    'registered_by' => $examOfficer->id,
                    'details' => [
                        'full_name' => $student->full_name,
                        'student_number' => $student->student_number,
                        'class' => 'Grade 12A',
                    ],
                ]
            );
            foreach (array_slice($wassceSubjects, 0, 5) as $subject) {
                ExternalCandidateSubject::query()->updateOrCreate(
                    ['candidate_id' => $candidate->id, 'subject_id' => $subject->id],
                    ['school_id' => $school->id]
                );
            }
            CertificateRecord::query()->updateOrCreate(
                ['candidate_id' => $candidate->id],
                [
                    'school_id' => $school->id,
                    'status' => $i === 0 ? 'available' : 'awaiting',
                    'received_on' => $i === 0 ? now()->subDays(6)->toDateString() : null,
                    'available_on' => $i === 0 ? now()->subDays(2)->toDateString() : null,
                    'staff_user_id' => $records->id,
                    'notes' => 'School-held certificate tracking only — not an official WAEC certificate record.',
                ]
            );
        }
    }

    private function seedSeniorAdmissions(School $school, User $officer, User $head): void
    {
        $rules = [
            'direction' => 'lower_better',
            'subjects' => [
                ['code' => 'ENG', 'name' => 'English'],
                ['code' => 'MATH', 'name' => 'Mathematics'],
                ['code' => 'SCI', 'name' => 'Science'],
                ['code' => 'SOC', 'name' => 'Social Studies'],
            ],
            'grade_points' => ['A1' => 1, 'B2' => 2, 'B3' => 3, 'C4' => 4, 'C5' => 5, 'C6' => 6, 'D7' => 7, 'E8' => 8, 'F9' => 9],
            'required_subjects' => ['ENG', 'MATH'],
            'pass_limit' => 6,
            'elective_count' => 2,
            'weights' => [],
            'combinations' => [],
            'min_subjects' => 4,
            'aggregate_threshold' => 14,
        ];

        $round = SsRound::query()->updateOrCreate(
            ['school_id' => $school->id, 'slug' => '2026-intake'],
            [
                'name' => '2026 Senior Secondary Intake',
                'instructions' => 'Apply with GABECE results. Offers are school decisions, not ministry placements.',
                'opens_on' => now()->subDays(20)->toDateString(),
                'closes_on' => now()->addDays(25)->toDateString(),
                'capacity' => 40,
                'waitlist_capacity' => 10,
                'status' => 'open',
                'rules' => $rules,
            ]
        );

        $apps = [
            ['SSA-2026-0001', 'shortlisted', 'Jainaba', 'Sowe', 'female', ['ENG' => 'B2', 'MATH' => 'B3', 'SCI' => 'C4', 'SOC' => 'A1'], 10],
            ['SSA-2026-0002', 'waitlisted', 'Omar', 'Colley', 'male', ['ENG' => 'C5', 'MATH' => 'C6', 'SCI' => 'C4', 'SOC' => 'B3'], 18],
            ['SSA-2026-0003', 'submitted', 'Awa', 'Manneh', 'female', ['ENG' => 'B3', 'MATH' => 'C4', 'SCI' => 'B3', 'SOC' => 'C5'], 15],
            ['SSA-2026-0004', 'approved', 'Lamin', 'Jatta', 'male', ['ENG' => 'A1', 'MATH' => 'B2', 'SCI' => 'B3', 'SOC' => 'B2'], 8],
            ['SSA-2026-0005', 'rejected', 'Nyima', 'Bah', 'female', ['ENG' => 'D7', 'MATH' => 'E8', 'SCI' => 'C6', 'SOC' => 'C5'], 26],
        ];

        foreach ($apps as $i => [$ref, $status, $first, $last, $gender, $grades, $agg]) {
            $app = SsApplication::query()->updateOrCreate(
                ['school_id' => $school->id, 'reference' => $ref],
                [
                    'round_id' => $round->id,
                    'status' => $status,
                    'source' => $i % 2 === 0 ? 'online' : 'desk',
                    'first_name' => $first,
                    'last_name' => $last,
                    'gender' => $gender,
                    'date_of_birth' => '2010-04-1'.($i + 1),
                    'nationality' => 'Gambian',
                    'previous_school' => 'Upper Basic School '.$last,
                    'gabece_year' => 2026,
                    'guardian_name' => 'Guardian '.$last,
                    'guardian_phone' => '+220 71100'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                    'programme_choice' => $i % 2 === 0 ? 'Science' : 'Arts',
                    'consent_at' => now()->subDays(10 - $i),
                    'aggregate' => $agg,
                    'eligible' => $status !== 'rejected',
                    'recommendation' => $status === 'shortlisted' || $status === 'approved' ? 'shortlist' : ($status === 'waitlisted' ? 'waitlist' : ($status === 'rejected' ? 'reject' : 'review')),
                    'rank_position' => $i + 1,
                    'computed_at' => now()->subDays(3),
                    'decided_by' => in_array($status, ['approved', 'rejected', 'shortlisted', 'waitlisted'], true) ? $head->id : null,
                    'decided_at' => in_array($status, ['approved', 'rejected', 'shortlisted', 'waitlisted'], true) ? now()->subDays(1) : null,
                ]
            );
            foreach ($grades as $code => $grade) {
                SsApplicationResult::query()->updateOrCreate(
                    ['application_id' => $app->id, 'subject_code' => $code],
                    [
                        'school_id' => $school->id,
                        'subject_name' => ['ENG' => 'English', 'MATH' => 'Mathematics', 'SCI' => 'Science', 'SOC' => 'Social Studies'][$code],
                        'grade' => $grade,
                    ]
                );
            }
            SsApplicationEvent::query()->firstOrCreate(
                ['application_id' => $app->id, 'event' => 'submitted'],
                [
                    'school_id' => $school->id,
                    'user_id' => $officer->id,
                    'to_status' => 'submitted',
                    'note' => 'Application received',
                    'created_at' => now()->subDays(10 - $i),
                ]
            );
        }
    }

    /** @param  list<Student>  $students */
    private function seedRecords(School $school, array $students, User $records, User $head): void
    {
        foreach (['testimonial', 'cover_letter', 'admission_letter'] as $type) {
            RecordsTemplate::query()->updateOrCreate(
                ['school_id' => $school->id, 'type' => $type, 'name' => 'Default '.$type],
                [
                    'body' => match ($type) {
                        'testimonial' => "TESTIMONIAL\n\nThis is to certify that {{ student_name }} (Student ID {{ student_number }}) was a student of {{ school_name }}.\n\nThis is a school-issued testimonial. It is not an official WAEC result or certificate.\n\nDate: {{ date }}",
                        'cover_letter' => "To whom it may concern,\n\n{{ school_name }} confirms the enclosed records for {{ student_name }} (Student ID {{ student_number }}).\n\nDate: {{ date }}",
                        default => "Dear Parent / Guardian,\n\n{{ school_name }} is pleased to offer {{ student_name }} (ref. {{ student_number }}) a place for {{ round_name }}.\n\nDate: {{ date }}",
                    },
                    'is_default' => true,
                    'is_active' => true,
                    'created_by' => $records->id,
                ]
            );
        }

        $g12 = array_values(array_filter($students, fn (Student $s) => $s->schoolClass?->name === 'Grade 12A'));
        foreach (array_slice($g12, 0, 3) as $i => $student) {
            StudentTermRecord::query()->updateOrCreate(
                ['student_id' => $student->id, 'grade_number' => 11, 'term_sequence' => 3],
                [
                    'school_id' => $school->id,
                    'academic_year_label' => '2025/2026',
                    'entries' => [
                        ['subject' => 'Mathematics', 'mark' => 72 - $i, 'grade' => 'B'],
                        ['subject' => 'English Language', 'mark' => 68, 'grade' => 'B'],
                        ['subject' => 'Biology', 'mark' => 74, 'grade' => 'A'],
                    ],
                    'source' => 'manual',
                    'entered_by' => $records->id,
                ]
            );
        }

        if (isset($students[0])) {
            $template = RecordsTemplate::query()->where('school_id', $school->id)->where('type', 'testimonial')->first();
            $doc = RecordsDocument::query()->updateOrCreate(
                ['school_id' => $school->id, 'reference' => 'TES-DEMO-0001'],
                [
                    'type' => 'testimonial',
                    'verification_token' => 'demo-verify-'.substr(sha1($school->id.'-tes-1'), 0, 16),
                    'student_id' => $students[0]->id,
                    'template_id' => $template?->id,
                    'status' => 'issued',
                    'title' => 'Testimonial - '.$students[0]->full_name,
                    'body' => 'School-issued testimonial for '.$students[0]->full_name.'. Not an official WAEC document.',
                    'prepared_by' => $records->id,
                    'submitted_at' => now()->subDays(5),
                    'approved_by' => $head->id,
                    'approved_at' => now()->subDays(4),
                    'issued_by' => $records->id,
                    'issued_at' => now()->subDays(3),
                    'issue_date' => now()->subDays(3)->toDateString(),
                ]
            );
            RecordsDocumentEvent::query()->firstOrCreate(
                ['document_id' => $doc->id, 'event' => 'issued'],
                ['school_id' => $school->id, 'user_id' => $records->id, 'note' => 'Issued to guardian', 'created_at' => now()->subDays(3)]
            );

            RecordsDocument::query()->updateOrCreate(
                ['school_id' => $school->id, 'reference' => 'TES-DEMO-0002'],
                [
                    'type' => 'cover_letter',
                    'verification_token' => 'demo-verify-'.substr(sha1($school->id.'-cvl-2'), 0, 16),
                    'student_id' => $students[0]->id,
                    'status' => 'draft',
                    'title' => 'Cover letter - '.$students[0]->full_name,
                    'body' => 'Draft cover letter.',
                    'prepared_by' => $records->id,
                ]
            );
        }
    }

    /** @param  list<Student>  $students */
    private function seedSafeguarding(School $school, array $students, User $head, User $teacher): void
    {
        $open = SafeguardingCase::query()->updateOrCreate(
            ['school_id' => $school->id, 'reference' => 'SG-DEMO-0001'],
            [
                'category' => 'welfare',
                'severity' => 'low',
                'status' => 'open',
                'student_id' => $students[0]->id ?? null,
                'summary' => 'Demo welfare check-in seed record.',
                'reported_by' => $head->id,
                'assigned_to' => $head->id,
                'occurred_on' => now()->subDays(6)->toDateString(),
            ]
        );
        SafeguardingNote::query()->updateOrCreate(
            ['safeguarding_case_id' => $open->id, 'body' => 'Class teacher reported quieter than usual this week. Follow-up scheduled.'],
            ['school_id' => $school->id, 'author_id' => $teacher->id, 'is_confidential' => true]
        );

        SafeguardingCase::query()->updateOrCreate(
            ['school_id' => $school->id, 'reference' => 'SG-DEMO-0002'],
            [
                'category' => 'bullying',
                'severity' => 'medium',
                'status' => 'investigating',
                'student_id' => $students[2]->id ?? null,
                'summary' => 'Peer conflict reported at break. Being followed up with both families.',
                'reported_by' => $teacher->id,
                'assigned_to' => $head->id,
                'occurred_on' => now()->subDays(2)->toDateString(),
            ]
        );
    }

    private function seedWebsiteExtras(School $school): void
    {
        NewsPost::query()->updateOrCreate(
            ['school_id' => $school->id, 'slug' => 'term-1-parents-evening'],
            [
                'title' => 'Term 1 parents’ evening',
                'excerpt' => 'Book a slot to meet class teachers this month.',
                'body' => 'Parents’ evening runs in the school hall. Bring the learner’s exercise books.',
                'image_url' => '/images/marketing/hero-classroom.jpg',
                'published_at' => now()->subDays(2),
            ]
        );

        SchoolInquiry::query()->updateOrCreate(
            ['school_id' => $school->id, 'email' => 'visitor@example.test'],
            [
                'name' => 'Fatou Visitor',
                'phone' => '+220 7000888',
                'subject' => 'Grade 1 place for 2027',
                'message' => 'Do you still have places in Grade 1 for next year?',
                'status' => 'new',
            ]
        );
        SchoolInquiry::query()->updateOrCreate(
            ['school_id' => $school->id, 'email' => 'parent.enquiry@example.test'],
            [
                'name' => 'Omar Enquiry',
                'phone' => '+220 7000889',
                'subject' => 'Boarding fees',
                'message' => 'Please send the boarding fee schedule.',
                'status' => 'closed',
            ]
        );
    }

    private function seedInvitations(School $school, User $admin): void
    {
        $inviteEmail = 'new.teacher@'.$this->demoSchoolMailHost($school->slug);
        StaffInvitation::query()
            ->where('school_id', $school->id)
            ->where('email', 'new.teacher@'.$school->slug.'.test')
            ->update(['email' => $inviteEmail]);

        StaffInvitation::query()->updateOrCreate(
            ['school_id' => $school->id, 'email' => $inviteEmail],
            [
                'kind' => 'staff',
                'name' => 'Pending Teacher',
                'role' => 'teacher',
                'invited_by' => $admin->id,
                'token_hash' => hash('sha256', 'demo-invite-'.$school->id),
                'expires_at' => now()->addDays(14),
            ]
        );
    }
}
