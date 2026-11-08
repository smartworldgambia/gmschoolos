<?php

namespace Database\Seeders;

use App\Services\Scale\CacheTuner;
use App\Models\AcademicYear;
use App\Models\ActivityLog;
use App\Models\AdmissionApplication;
use App\Models\Assignment;
use App\Models\AssignmentRubric;
use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Domain;
use App\Models\Examination;
use App\Models\FeeItem;
use App\Models\Grade;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Level;
use App\Models\NewsPost;
use App\Models\Payment;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\Room;
use App\Models\BoardingHostel;
use App\Models\BoardingRoom;
use App\Models\BoardingAllocation;
use App\Models\LibraryBook;
use App\Models\LibraryLoan;
use App\Models\LtiTool;
use App\Models\PlatformAlert;
use App\Models\SafeguardingCase;
use App\Models\School;
use App\Models\SchoolClass;
use App\Services\Billing\SubscriptionLifecycle;
use App\Models\ScormPackage;
use App\Models\Staff;
use App\Models\StaffLeaveRequest;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TimetableSlot;
use App\Models\TransportAssignment;
use App\Models\TransportRoute;
use App\Models\TransportVehicle;
use App\Models\User;
use App\Services\Platform\QuotaService;
use App\Services\Platform\UsageRecorder;
use App\Services\Reporting\ReportStoreRefresher;
use App\Support\TenantContext;
use Database\Seeders\Concerns\DemoMailDomain;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSchoolSeeder extends Seeder
{
    use DemoMailDomain;

    public function run(): void
    {
        $baseDomain = $this->demoMailHost();

        $school = School::query()->updateOrCreate(
            ['slug' => 'brightfuture'],
            [
                'name' => 'Bright Future School',
                'category' => 'combined',
                'country' => 'GM',
                'region' => 'Kanifing',
                'address' => 'Kairaba Avenue, Serrekunda',
                'phone' => '+220 3000000',
                'email' => 'info@brightfuture.gm',
                'tagline' => 'Knowledge • Character • A Brighter Tomorrow',
                'about' => 'Bright Future School serves nursery through senior secondary learners across The Gambia — nurturing knowledge, character, and a brighter tomorrow for every student.',
                'status' => 'active',
                'website_published' => true,
                'primary_color' => '#1A2B56',
                'accent_color' => '#1683F8',
            ]
        );

        $school->update([
            'settings' => array_merge($school->settings ?? [], [
                'landing' => [
                    'template' => 'combined',
                    'category_label' => 'All-through School',
                    'hero_eyebrow' => 'School community',
                    'hero_headline' => "Nurturing Today's Learners for a Brighter Tomorrow",
                    'hero_body' => 'From Nursery to Senior Secondary, Bright Future School provides a caring, ambitious learning community where every student can thrive.',
                    'hero_image' => '/images/marketing/hero-students.jpg',
                    'hero_slides' => [
                        [
                            'image' => '/images/marketing/hero-students.jpg',
                            'alt' => 'Students smiling in school uniforms',
                        ],
                        [
                            'image' => '/images/marketing/hero-classroom.jpg',
                            'alt' => 'Teacher supporting learners in class',
                        ],
                        [
                            'image' => '/images/marketing/hero-primary.jpg',
                            'alt' => 'Primary learners on campus',
                        ],
                    ],
                    'cta_primary' => 'Apply for Admission',
                    'cta_secondary' => 'Learn More',
                    'levels_heading' => 'Our Academic Levels',
                    'levels_intro' => 'From first steps in Nursery to Senior Secondary pathways, Bright Future School supports learners with care, structure, and ambition.',
                    'about_headline' => 'A community built for every stage of learning',
                    'about_image' => '/images/marketing/hero-classroom.jpg',
                    'admissions_title' => 'Begin your journey with us',
                    'admissions_body' => 'Families choose Bright Future School for strong academics, character formation, and a welcoming campus. Start an admissions enquiry today.',
                    'admissions_steps' => [
                        [
                            'title' => 'Enquire',
                            'body' => 'Share your child’s details and the level you are applying for.',
                        ],
                        [
                            'title' => 'Visit & assess',
                            'body' => 'Meet our team, tour the campus, and complete any placement checks.',
                        ],
                        [
                            'title' => 'Enrol',
                            'body' => 'Confirm your place, complete registration, and prepare for term start.',
                        ],
                    ],
                    'contact_blurb' => 'Visit us in Kanifing or send a message — the Bright Future School team is ready to help with programmes, fees, and school visits.',
                    'features' => [
                        [
                            'key' => 'teachers',
                            'icon' => 'teachers',
                            'title' => 'Qualified Teachers',
                            'subtitle' => 'Experienced educators dedicated to every learner',
                        ],
                        [
                            'key' => 'facilities',
                            'icon' => 'facilities',
                            'title' => 'Modern Facilities',
                            'subtitle' => 'Classrooms and resources built for learning',
                        ],
                        [
                            'key' => 'activities',
                            'icon' => 'activities',
                            'title' => 'Co-curricular Activities',
                            'subtitle' => 'Sports, arts, and clubs that build character',
                        ],
                        [
                            'key' => 'safety',
                            'icon' => 'safety',
                            'title' => 'Safe Environment',
                            'subtitle' => 'A caring campus where students thrive',
                        ],
                    ],
                    'level_cards' => [
                        [
                            'name' => 'Nursery',
                            'code' => 'nursery',
                            'description' => 'A warm start with play-based learning and early foundations.',
                            'image' => '/images/marketing/hero-primary.jpg',
                        ],
                        [
                            'name' => 'Primary',
                            'code' => 'primary',
                            'description' => 'Strong literacy, numeracy, and character in supportive classes.',
                            'image' => '/images/marketing/hero-classroom.jpg',
                        ],
                        [
                            'name' => 'Basic Secondary',
                            'code' => 'basic_secondary',
                            'description' => 'Junior secondary academics with discipline and discovery.',
                            'image' => '/images/marketing/hero-students.jpg',
                        ],
                        [
                            'name' => 'Senior Secondary',
                            'code' => 'senior_secondary',
                            'description' => 'Exam pathways and leadership for life beyond school.',
                            'image' => '/images/marketing/hero-classroom.jpg',
                        ],
                    ],
                ],
            ]),
        ]);

        app(CacheTuner::class)->forgetPublicSite($school->id);

        Domain::query()->updateOrCreate(
            ['host' => 'brightfuture.'.$baseDomain],
            ['school_id' => $school->id, 'is_primary' => true, 'is_custom' => false, 'ssl_active' => false]
        );

        // Demo tenant: a long trial (not a fabricated payment) so the demo never expires under developers' feet.
        app(SubscriptionLifecycle::class)->startTrial($school, (int) env('SCHOOLOS_DEMO_TRIAL_DAYS', 365), 'combined');

        $school->update(['billing_email' => 'billing@brightfuture.gm']);
        app(QuotaService::class)->forSchool($school->fresh('subscription'));

        app(TenantContext::class)->set($school);

        $schoolHost = $this->demoSchoolMailHost($school->slug);
        $this->relocateLoginEmail('admin@brightfuture.test', 'admin@'.$schoolHost);
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@'.$schoolHost],
            [
                'school_id' => $school->id,
                'name' => 'Mr. Lamin Jallow',
                'phone' => '+220 3000001',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $admin->syncRoles(['school_admin']);

        $this->relocateLoginEmail('teacher@brightfuture.test', 'teacher@'.$schoolHost);
        $teacherUser = User::query()->updateOrCreate(
            ['email' => 'teacher@'.$schoolHost],
            [
                'school_id' => $school->id,
                'name' => 'Ms. Fatou Jallow',
                'phone' => '+220 3000002',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $teacherUser->syncRoles(['teacher']);

        Staff::query()->updateOrCreate(
            ['school_id' => $school->id, 'email' => 'teacher@'.$schoolHost],
            [
                'user_id' => $teacherUser->id,
                'staff_number' => 'TCH-001',
                'first_name' => 'Fatou',
                'last_name' => 'Jallow',
                'phone' => '+220 3000002',
                'role_title' => 'Mathematics Teacher',
                'status' => 'active',
            ]
        );

        $year = AcademicYear::query()->updateOrCreate(
            ['school_id' => $school->id, 'name' => '2024/2025'],
            [
                'starts_on' => '2024-09-01',
                'ends_on' => '2025-07-31',
                'is_current' => true,
            ]
        );

        $term = Term::query()->updateOrCreate(
            ['school_id' => $school->id, 'academic_year_id' => $year->id, 'name' => 'Term 2'],
            [
                'starts_on' => '2025-01-06',
                'ends_on' => '2025-04-11',
                'is_current' => true,
            ]
        );

        $levels = collect([
            ['name' => 'Nursery', 'code' => 'NUR', 'sort_order' => 1],
            ['name' => 'Lower Basic', 'code' => 'LB', 'sort_order' => 2],
            ['name' => 'Upper Basic', 'code' => 'UB', 'sort_order' => 3],
            ['name' => 'Junior Secondary', 'code' => 'JSS', 'sort_order' => 4],
            ['name' => 'Senior Secondary', 'code' => 'SSS', 'sort_order' => 5],
        ])->map(fn ($level) => Level::query()->updateOrCreate(
            ['school_id' => $school->id, 'code' => $level['code']],
            $level
        ));

        $sss = $levels->firstWhere('code', 'SSS');

        $class10A = SchoolClass::query()->updateOrCreate(
            ['school_id' => $school->id, 'name' => 'Grade 10A'],
            [
                'level_id' => $sss?->id,
                'stream' => 'A',
                'class_teacher_id' => $teacherUser->id,
                'capacity' => 40,
            ]
        );

        $class10B = SchoolClass::query()->updateOrCreate(
            ['school_id' => $school->id, 'name' => 'Grade 10B'],
            [
                'level_id' => $sss?->id,
                'stream' => 'B',
                'class_teacher_id' => $teacherUser->id,
                'capacity' => 40,
            ]
        );

        $subjects = collect([
            ['name' => 'Mathematics', 'code' => 'MATH'],
            ['name' => 'English Language', 'code' => 'ENG'],
            ['name' => 'Biology', 'code' => 'BIO'],
            ['name' => 'Social Studies', 'code' => 'SST'],
        ])->map(fn ($subject) => Subject::query()->updateOrCreate(
            ['school_id' => $school->id, 'code' => $subject['code']],
            $subject
        ));

        $math = $subjects->firstWhere('code', 'MATH');
        $eng = $subjects->firstWhere('code', 'ENG');

        $this->relocateLoginEmail('student@brightfuture.test', 'student@'.$schoolHost);
        $studentUser = User::query()->updateOrCreate(
            ['email' => 'student@'.$schoolHost],
            [
                'school_id' => $school->id,
                'name' => 'Amadou Jallow',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $studentUser->syncRoles(['student']);

        $this->relocateLoginEmail('parent@brightfuture.test', 'parent@'.$schoolHost);
        $parentUser = User::query()->updateOrCreate(
            ['email' => 'parent@'.$schoolHost],
            [
                'school_id' => $school->id,
                'name' => 'Parent Jallow',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $parentUser->syncRoles(['parent']);

        $students = [];
        $names = [
            ['Amadou', 'Jallow', 'male'],
            ['Fatoumata', 'Ceesay', 'female'],
            ['Omar', 'Sanyang', 'male'],
            ['Awa', 'Touray', 'female'],
            ['Lamin', 'Bah', 'male'],
            ['Mariama', 'Jammeh', 'female'],
            ['Ebrima', 'Sowe', 'male'],
            ['Isatou', 'Njie', 'female'],
        ];

        foreach ($names as $i => [$first, $last, $gender]) {
            $class = $i % 2 === 0 ? $class10A : $class10B;
            $number = 'BFS-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);
            $student = Student::query()->updateOrCreate(
                ['school_id' => $school->id, 'student_number' => $number],
                [
                    'user_id' => $i === 0 ? $studentUser->id : (in_array($i, [2, 4], true) ? $this->demoClassmateUser($school, $first, $last, $studentUser->email)->id : null),
                    'school_class_id' => $class->id,
                    'first_name' => $first,
                    'last_name' => $last,
                    'gender' => $gender,
                    'date_of_birth' => now()->subYears(15)->subDays($i * 40)->toDateString(),
                    'place_of_birth' => 'Banjul',
                    'nationality' => 'Gambian',
                    'religion' => 'Islam',
                    'status' => 'active',
                    'admitted_on' => '2024-09-02',
                ]
            );
            $students[] = $student;

            $guardian = Guardian::query()->updateOrCreate(
                ['school_id' => $school->id, 'email' => Str::lower($first).'.guardian@example.test'],
                [
                    'user_id' => $i === 0 ? $parentUser->id : null,
                    'first_name' => 'Parent',
                    'last_name' => $last,
                    'relationship' => $i % 2 === 0 ? 'Father' : 'Mother',
                    'phone' => '+220 7'.str_pad((string) (100000 + $i), 6, '0', STR_PAD_LEFT),
                    'occupation' => 'Trader',
                ]
            );

            $student->guardians()->syncWithoutDetaching([
                $guardian->id => ['school_id' => $school->id, 'is_primary' => true],
            ]);
        }

        AdmissionApplication::query()->updateOrCreate(
            ['school_id' => $school->id, 'application_number' => 'ADM-DEMO-0001'],
            [
                'status' => 'submitted',
                'first_name' => 'Kebba',
                'last_name' => 'Camara',
                'gender' => 'male',
                'date_of_birth' => '2011-03-18',
                'preferred_class_id' => $class10A->id,
                'guardian_name' => 'Satang Camara',
                'guardian_relationship' => 'Mother',
                'guardian_phone' => '+220 7000999',
                'nationality' => 'Gambian',
            ]
        );

        foreach ($students as $i => $student) {
            foreach (range(0, 4) as $day) {
                AttendanceRecord::query()->updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'attended_on' => now()->subDays($day)->toDateString(),
                        'session' => 'full',
                    ],
                    [
                        'school_id' => $school->id,
                        'school_class_id' => $student->school_class_id,
                        'status' => $day === 1 && $i === 2 ? 'late' : ($day === 3 && $i === 4 ? 'absent' : 'present'),
                        'recorded_by' => $teacherUser->id,
                    ]
                );
            }

            foreach ($subjects as $subject) {
                $ca = 28 + ($i % 8);
                $exam = 40 + (($i * 3) % 15);
                $total = $ca + $exam;
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
                        'letter_grade' => $total >= 80 ? 'A' : ($total >= 70 ? 'B+' : ($total >= 60 ? 'B' : 'C')),
                        'remark' => $total >= 70 ? 'Good progress' : 'Needs support',
                    ]
                );
            }
        }

        $feeTuition = FeeItem::query()->updateOrCreate(
            ['school_id' => $school->id, 'name' => 'Tuition'],
            ['amount' => 4500, 'applicable_to' => 'all', 'type' => 'compulsory', 'is_active' => true]
        );
        $feeExam = FeeItem::query()->updateOrCreate(
            ['school_id' => $school->id, 'name' => 'Examination'],
            ['amount' => 750, 'applicable_to' => 'all', 'type' => 'compulsory', 'is_active' => true]
        );

        foreach ($students as $i => $student) {
            $total = $feeTuition->amount + $feeExam->amount;
            $paid = $i < 5 ? $total : ($i < 7 ? (int) ($total * 0.5) : 0);
            $status = $paid >= $total ? 'paid' : ($paid > 0 ? 'partial' : 'overdue');

            $invoice = Invoice::query()->updateOrCreate(
                ['school_id' => $school->id, 'invoice_number' => 'INV-2025-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT)],
                [
                    'student_id' => $student->id,
                    'status' => $status,
                    'total_amount' => $total,
                    'amount_paid' => $paid,
                    'due_on' => now()->subDays(10)->toDateString(),
                    'issued_at' => now()->subDays(40),
                ]
            );

            InvoiceItem::query()->updateOrCreate(
                ['invoice_id' => $invoice->id, 'fee_item_id' => $feeTuition->id],
                ['school_id' => $school->id, 'description' => 'Tuition Term 2', 'amount' => $feeTuition->amount]
            );
            InvoiceItem::query()->updateOrCreate(
                ['invoice_id' => $invoice->id, 'fee_item_id' => $feeExam->id],
                ['school_id' => $school->id, 'description' => 'Examination Fee', 'amount' => $feeExam->amount]
            );

            if ($paid > 0) {
                Payment::query()->updateOrCreate(
                    ['school_id' => $school->id, 'receipt_number' => 'RCPT-'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT)],
                    [
                        'invoice_id' => $invoice->id,
                        'student_id' => $student->id,
                        'amount' => $paid,
                        'method' => $i % 2 === 0 ? 'wave' : 'cash',
                        'paid_at' => now()->subDays(5 + $i),
                        'recorded_by' => $admin->id,
                        'receipt_sent' => true,
                    ]
                );
            }
        }

        $course = Course::query()->updateOrCreate(
            ['school_id' => $school->id, 'code' => 'MATH-10'],
            [
                'subject_id' => $math?->id,
                'teacher_id' => $teacherUser->id,
                'title' => 'Mathematics - Grade 10',
                'description' => 'Algebra, geometry, and problem solving for Grade 10.',
                'status' => 'published',
            ]
        );

        $section = CourseSection::query()->updateOrCreate(
            ['school_id' => $school->id, 'course_id' => $course->id, 'title' => 'Week 1: Algebra Foundations'],
            ['sort_order' => 1]
        );

        Lesson::query()->updateOrCreate(
            ['school_id' => $school->id, 'course_id' => $course->id, 'title' => 'Introduction to Linear Equations'],
            [
                'course_section_id' => $section->id,
                'content_type' => 'video',
                'content' => 'Watch the worked examples and take notes.',
                'sort_order' => 1,
                'duration_minutes' => 18,
            ]
        );
        Lesson::query()->updateOrCreate(
            ['school_id' => $school->id, 'course_id' => $course->id, 'title' => 'Practice Worksheet PDF'],
            [
                'course_section_id' => $section->id,
                'content_type' => 'pdf',
                'content' => 'Download and complete the practice set.',
                'sort_order' => 2,
                'duration_minutes' => 25,
            ]
        );

        $assignment = Assignment::query()->updateOrCreate(
            ['school_id' => $school->id, 'course_id' => $course->id, 'title' => 'Algebra Worksheet'],
            [
                'instructions' => 'Complete questions 1–12 and upload your solutions.',
                'due_at' => now()->addDays(3),
                'max_score' => 100,
            ]
        );

        AssignmentRubric::query()->updateOrCreate(
            ['school_id' => $school->id, 'assignment_id' => $assignment->id],
            [
                'title' => 'Algebra worksheet rubric',
                'criteria' => [
                    ['label' => 'Accuracy', 'max_points' => 40],
                    ['label' => 'Working shown', 'max_points' => 30],
                    ['label' => 'Presentation', 'max_points' => 30],
                ],
            ]
        );

        $quiz = Quiz::query()->updateOrCreate(
            ['school_id' => $school->id, 'course_id' => $course->id, 'title' => 'Linear Equations Check'],
            [
                'opens_at' => now()->subDay(),
                'closes_at' => now()->addDays(7),
                'max_score' => 4,
            ]
        );

        QuizQuestion::query()->updateOrCreate(
            ['school_id' => $school->id, 'quiz_id' => $quiz->id, 'prompt' => 'Solve for x: 2x + 4 = 10'],
            [
                'type' => 'multiple_choice',
                'options' => ['x = 2', 'x = 3', 'x = 4', 'x = 5'],
                'correct_answer' => 'x = 3',
                'points' => 2,
                'sort_order' => 1,
            ]
        );
        QuizQuestion::query()->updateOrCreate(
            ['school_id' => $school->id, 'quiz_id' => $quiz->id, 'prompt' => 'Is 5 a solution to x - 2 = 3?'],
            [
                'type' => 'true_false',
                'options' => ['True', 'False'],
                'correct_answer' => 'True',
                'points' => 2,
                'sort_order' => 2,
            ]
        );

        $firstLesson = Lesson::query()
            ->where('course_id', $course->id)
            ->orderBy('sort_order')
            ->first();

        if ($firstLesson && isset($students[0])) {
            LessonCompletion::query()->updateOrCreate(
                [
                    'lesson_id' => $firstLesson->id,
                    'student_id' => $students[0]->id,
                ],
                [
                    'school_id' => $school->id,
                    'course_id' => $course->id,
                    'completed_at' => now()->subDay(),
                ]
            );
        }

        Course::query()->updateOrCreate(
            ['school_id' => $school->id, 'code' => 'ENG-10'],
            [
                'subject_id' => $eng?->id,
                'teacher_id' => $teacherUser->id,
                'title' => 'English Language - Grade 10',
                'description' => 'Reading comprehension and composition.',
                'status' => 'published',
            ]
        );

        $room12 = Room::query()->updateOrCreate(
            ['school_id' => $school->id, 'name' => 'Room 12'],
            ['code' => 'R12', 'capacity' => 40, 'building' => 'Main Block', 'is_active' => true]
        );
        $lab1 = Room::query()->updateOrCreate(
            ['school_id' => $school->id, 'name' => 'Science Lab 1'],
            ['code' => 'LAB1', 'capacity' => 30, 'building' => 'Science Block', 'is_active' => true]
        );

        foreach ([1, 2, 3, 4, 5] as $dow) {
            TimetableSlot::query()->updateOrCreate(
                [
                    'school_id' => $school->id,
                    'school_class_id' => $class10A->id,
                    'day_of_week' => $dow,
                    'starts_at' => '08:00:00',
                ],
                [
                    'subject_id' => $dow % 2 === 0 ? $eng?->id : $math?->id,
                    'teacher_id' => $teacherUser->id,
                    'ends_at' => '08:45:00',
                    'room' => $dow % 2 === 0 ? $room12->name : $lab1->name,
                    'room_id' => $dow % 2 === 0 ? $room12->id : $lab1->id,
                ]
            );
        }

        $examination = Examination::query()->updateOrCreate(
            ['school_id' => $school->id, 'name' => 'Term 2 Mid-Term Examinations'],
            [
                'term_id' => $term->id,
                'academic_year_id' => $year->id,
                'status' => 'published',
                'starts_on' => '2025-02-10',
                'ends_on' => '2025-02-21',
                'published_at' => now()->subDays(2),
                'published_by' => $admin->id,
            ]
        );

        Grade::query()
            ->where('school_id', $school->id)
            ->where('term_id', $term->id)
            ->update([
                'examination_id' => $examination->id,
                'is_published' => true,
            ]);

        $newsPosts = [
            [
                'slug' => 'welcome-to-our-new-school-website',
                'title' => 'Welcome to our new school website',
                'excerpt' => 'Families can explore programmes and apply online.',
                'body' => 'Bright Future School now has a dedicated public landing page.',
                'image_url' => '/images/marketing/hero-students.jpg',
                'days_ago' => 1,
            ],
            [
                'slug' => 'open-day-for-prospective-families',
                'title' => 'Open day for prospective families',
                'excerpt' => 'Tour classrooms, meet teachers, and learn about our programmes.',
                'body' => 'Join us for a guided campus open day at Bright Future School.',
                'image_url' => '/images/marketing/hero-classroom.jpg',
                'days_ago' => 4,
            ],
            [
                'slug' => 'literacy-week-celebrates-young-readers',
                'title' => 'Literacy week celebrates young readers',
                'excerpt' => 'Pupils shared stories, poetry, and reading challenges across grades.',
                'body' => 'Bright Future School celebrated literacy week with classroom showcases.',
                'image_url' => '/images/marketing/hero-primary.jpg',
                'days_ago' => 8,
            ],
            [
                'slug' => 'sports-day-builds-teamwork-and-grit',
                'title' => 'Sports day builds teamwork and grit',
                'excerpt' => 'House competitions highlighted fitness, friendship, and fair play.',
                'body' => 'Our annual sports day brought learners together in healthy competition.',
                'image_url' => '/images/marketing/hero-students.jpg',
                'days_ago' => 12,
            ],
            [
                'slug' => 'stem-club-showcases-student-projects',
                'title' => 'STEM club showcases student projects',
                'excerpt' => 'Robotics, science demos, and coding challenges filled the STEM hall.',
                'body' => 'Students presented inventive STEM projects to parents and peers.',
                'image_url' => '/images/marketing/hero-classroom.jpg',
                'days_ago' => 18,
            ],
            [
                'slug' => 'parent-forum-on-wellbeing-and-learning',
                'title' => 'Parent forum on wellbeing and learning',
                'excerpt' => 'Counselors and teachers shared practical tips for supporting learners at home.',
                'body' => 'Bright Future School hosted a parent forum on wellbeing and learning.',
                'image_url' => '/images/marketing/hero-primary.jpg',
                'days_ago' => 24,
            ],
        ];

        foreach ($newsPosts as $post) {
            NewsPost::query()->updateOrCreate(
                ['school_id' => $school->id, 'slug' => $post['slug']],
                [
                    'title' => $post['title'],
                    'excerpt' => $post['excerpt'],
                    'body' => $post['body'],
                    'image_url' => $post['image_url'],
                    'published_at' => now()->subDays($post['days_ago']),
                ]
            );
        }

        ActivityLog::query()->create([
            'school_id' => $school->id,
            'user_id' => $admin->id,
            'event' => 'seed',
            'description' => 'Demo school data refreshed',
            'properties' => ['students' => count($students)],
        ]);

        ActivityLog::query()->create([
            'school_id' => $school->id,
            'user_id' => $teacherUser->id,
            'event' => 'attendance',
            'description' => 'Attendance recorded for Grade 10A',
        ]);

        ActivityLog::query()->create([
            'school_id' => $school->id,
            'user_id' => $admin->id,
            'event' => 'payment',
            'description' => 'Fee payment recorded for Amadou Jallow',
        ]);

        $staffMember = Staff::query()->where('school_id', $school->id)->first();
        if ($staffMember) {
            StaffLeaveRequest::query()->updateOrCreate(
                [
                    'school_id' => $school->id,
                    'staff_id' => $staffMember->id,
                    'starts_on' => now()->addWeek()->toDateString(),
                ],
                [
                    'leave_type' => 'annual',
                    'ends_on' => now()->addWeek()->addDays(2)->toDateString(),
                    'days' => 3,
                    'status' => 'pending',
                    'reason' => 'Family visit',
                ]
            );
        }

        $book = LibraryBook::query()->updateOrCreate(
            ['school_id' => $school->id, 'isbn' => '978-0-demo-001'],
            [
                'title' => 'Mathematics for Senior Secondary',
                'author' => 'WAEC Prep',
                'category' => 'Mathematics',
                'copies_total' => 5,
                'copies_available' => 4,
                'status' => 'available',
            ]
        );

        if (isset($students[0])) {
            LibraryLoan::query()->updateOrCreate(
                [
                    'school_id' => $school->id,
                    'library_book_id' => $book->id,
                    'student_id' => $students[0]->id,
                    'status' => 'out',
                ],
                [
                    'loaned_on' => now()->subDays(3)->toDateString(),
                    'due_on' => now()->addDays(11)->toDateString(),
                ]
            );
        }

        $bus = TransportVehicle::query()->updateOrCreate(
            ['school_id' => $school->id, 'plate_number' => 'BJL-1001'],
            [
                'name' => 'Bus 1',
                'capacity' => 40,
                'driver_name' => 'Ebrima Ceesay',
                'driver_phone' => '+220 3000099',
                'status' => 'active',
            ]
        );

        $route = TransportRoute::query()->updateOrCreate(
            ['school_id' => $school->id, 'name' => 'Kanifing Loop'],
            [
                'transport_vehicle_id' => $bus->id,
                'area' => 'Kanifing',
                'fee_monthly' => 250,
                'status' => 'active',
            ]
        );

        if (isset($students[0])) {
            TransportAssignment::query()->updateOrCreate(
                ['transport_route_id' => $route->id, 'student_id' => $students[0]->id],
                ['school_id' => $school->id, 'stop_name' => 'Kairaba Avenue', 'status' => 'active']
            );
        }

        $hostel = BoardingHostel::query()->updateOrCreate(
            ['school_id' => $school->id, 'name' => 'Sunrise House'],
            ['gender' => 'mixed', 'warden_name' => 'Mrs. Njie', 'status' => 'active']
        );

        $dorm = BoardingRoom::query()->updateOrCreate(
            ['school_id' => $school->id, 'boarding_hostel_id' => $hostel->id, 'name' => 'A1'],
            ['capacity' => 4, 'occupied' => isset($students[1]) ? 1 : 0, 'status' => 'available']
        );

        if (isset($students[1])) {
            BoardingAllocation::query()->updateOrCreate(
                [
                    'boarding_room_id' => $dorm->id,
                    'student_id' => $students[1]->id,
                    'status' => 'active',
                ],
                [
                    'school_id' => $school->id,
                    'starts_on' => now()->subMonths(2)->toDateString(),
                ]
            );
        }

        SafeguardingCase::query()->updateOrCreate(
            ['school_id' => $school->id, 'reference' => 'SG-DEMO-0001'],
            [
                'category' => 'welfare',
                'severity' => 'low',
                'status' => 'open',
                'student_id' => $students[0]->id ?? null,
                'summary' => 'Demo welfare check-in seed record.',
                'reported_by' => $admin->id,
            ]
        );

        LtiTool::query()->updateOrCreate(
            ['school_id' => $school->id, 'name' => 'Demo LTI Tool'],
            [
                'launch_url' => 'https://example.com/lti/launch',
                'client_id' => 'demo-client',
                'is_active' => true,
            ]
        );

        ScormPackage::query()->updateOrCreate(
            ['school_id' => $school->id, 'title' => 'Intro Science SCORM'],
            [
                'version' => '1.2',
                'entry_point' => 'https://example.com/scorm/index.html',
                'status' => 'ready',
            ]
        );

        app(ReportStoreRefresher::class)->refreshSchool($school->fresh());

        $this->relocateLoginEmail('platform@schoolos.test', $this->demoPlatformEmail('platform'));
        User::query()->updateOrCreate(
            ['email' => $this->demoPlatformEmail('platform')],
            [
                'school_id' => null,
                'name' => 'Platform Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        )->syncRoles(['platform_admin']);

        app(UsageRecorder::class)->snapshotSchool($school->fresh());

        PlatformAlert::query()->updateOrCreate(
            [
                'source' => 'quota',
                'title' => 'Demo: review usage headroom',
                'school_id' => $school->id,
            ],
            [
                'severity' => 'info',
                'message' => 'Seeded informational alert for platform monitoring UI.',
                'context' => ['seeded' => true],
                'resolved_at' => null,
            ]
        );

        app(TenantContext::class)->clear();
    }

    private function demoClassmateUser(School $school, string $first, string $last, string $studentEmail): User
    {
        $domain = strstr($studentEmail, '@') ?: '@'.$this->demoSchoolMailHost('brightfuture');
        $user = User::query()->updateOrCreate(
            ['email' => strtolower($first).$domain],
            [
                'school_id' => $school->id,
                'name' => $first.' '.$last,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $user->syncRoles(['student']);

        return $user;
    }
}
