<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\AdmissionApplication;
use App\Models\AttendanceRecord;
use App\Models\ClassEducator;
use App\Models\ClassSubject;
use App\Models\Domain;
use App\Models\FeeItem;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Level;
use App\Models\Payment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolSubscription;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\Billing\SubscriptionLifecycle;
use App\Services\Demo\DemoLoginProvisioner;
use App\Services\Platform\QuotaService;
use App\Services\Scale\CacheTuner;
use App\Services\School\SchoolLandingProvisioner;
use App\Support\DemoLoginCatalog;
use App\Support\SchoolCategoryCatalog;
use App\Support\TenantContext;
use Database\Seeders\Concerns\DemoMailDomain;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds Smartworld Nursery as a clickable ECD tenant.
 * User-facing copy stays school-realistic — no "demo" wording in names, news, or descriptions.
 */
class SmartworldNurserySeeder extends Seeder
{
    use DemoMailDomain;

    public function run(): void
    {
        $baseDomain = $this->demoMailHost();

        $tenant = app(TenantContext::class);
        $tenant->enterPlatformMode();

        $school = School::query()->updateOrCreate(
            ['slug' => 'smartworld'],
            [
                'name' => 'Smartworld Nursery',
                'category' => 'nursery',
                'enabled_categories' => ['nursery'],
                'country' => 'GM',
                'region' => 'Kanifing',
                'address' => 'Opposite Netpage, Kairaba Avenue, Serrekunda',
                'phone' => '+220 4462100',
                'email' => 'info@smartworld.gm',
                'status' => 'active',
                'website_published' => true,
                'primary_color' => '#1A2B56',
                'accent_color' => '#1683F8',
            ]
        );

        $tenant->set($school);

        // Drop leftover Grade/Form structure from earlier combined rich packs so Academics shows ECD only.
        $this->purgeOutOfPackStructure($school);

        app(SchoolLandingProvisioner::class)->provision($school->fresh());

        $school->refresh();
        $school->update([
            'tagline' => 'A caring start for every little learner',
            'about' => 'Smartworld Nursery is an early childhood centre in Kanifing focused on play-based learning, language and numeracy foundations, and a safe, nurturing environment for children from Baby Class through Reception.',
            'billing_email' => 'billing@smartworld.gm',
            'website_published' => true,
            'status' => 'active',
            'suspended_at' => null,
            'settings' => array_merge($school->settings ?? [], [
                'landing' => array_merge($school->settings['landing'] ?? [], [
                    'hero_headline' => 'Nurturing little learners with care and curiosity',
                    'hero_body' => 'Smartworld Nursery welcomes children into a warm early-years community focused on play, confidence, and first foundations.',
                    'levels_intro' => 'Age-appropriate groups that help children grow in confidence at Smartworld Nursery.',
                    'admissions_body' => 'Families choose Smartworld Nursery for gentle care, early foundations, and a welcoming campus. Start an admissions enquiry today.',
                    'contact_blurb' => 'Visit us on Kairaba Avenue or send a message — the Smartworld Nursery team is ready to help with programmes, fees, and school visits.',
                ]),
            ]),
        ]);

        $host = 'smartworld.'.$baseDomain;
        Domain::query()->updateOrCreate(
            ['host' => $host],
            ['school_id' => $school->id, 'is_primary' => true, 'is_custom' => false, 'ssl_active' => false]
        );

        $this->ensureNurseryTrial($school);
        app(QuotaService::class)->forSchool($school->fresh('subscription'));

        $users = app(DemoLoginProvisioner::class)->ensureSchool($school);
        $this->seedStaff($school, $users);
        [$year, $term] = $this->seedCalendar($school);
        $classes = $this->seedClassrooms($school, $users);
        $this->allocateDomains($school, $classes);
        $children = $this->seedChildren($school, $classes, $users);
        $this->seedAttendance($school, $children, $users['teacher']);
        $this->seedFees($school, $children, $term, $users['finance_officer']);
        $this->seedAdmissions($school, $classes);

        app(CacheTuner::class)->forgetPublicSite($school->id);
        app(CacheTuner::class)->forgetAdminDashboard($school->id);

        $levels = Level::query()->where('school_id', $school->id)->count();
        $domains = Subject::query()->where('school_id', $school->id)->where('domain_kind', 'learning_domain')->count();
        $rooms = SchoolClass::query()->where('school_id', $school->id)->count();
        $kids = Student::query()->where('school_id', $school->id)->where('status', 'active')->count();

        app(TenantContext::class)->clear();

        $this->command?->info('Smartworld Nursery ready.');
        $this->command?->info("  Host:      https://{$host}");
        $this->command?->info('  Site path: /sites/smartworld');
        $this->command?->info('  Login:     admin@'.$host.' / '.DemoLoginCatalog::PASSWORD);
        $this->command?->info("  Counts:    levels={$levels} domains={$domains} classes={$rooms} children={$kids}");
    }

    private function ensureNurseryTrial(School $school): void
    {
        $days = (int) env('SCHOOLOS_DEMO_TRIAL_DAYS', 365);
        $existing = SchoolSubscription::query()->where('school_id', $school->id)->first();
        if (! $existing) {
            app(SubscriptionLifecycle::class)->startTrial($school, $days, 'nursery');

            return;
        }

        $start = now();
        // school_subscriptions has suspended_at / status — there is no cancelled_at column.
        $existing->forceFill([
            'school_type' => 'nursery',
            'status' => SchoolSubscription::TRIAL,
            'payment_status' => 'not_required_during_trial',
            'starts_at' => $existing->starts_at ?? $start,
            'ends_at' => $start->copy()->addDays($days),
            'trial_starts_at' => $existing->trial_starts_at ?? $start,
            'trial_ends_at' => $start->copy()->addDays($days),
            'suspended_at' => null,
            'expired_at' => null,
        ])->save();
    }

    /** Remove Grade/Form leftovers so the exclusive nursery pack is the only academic structure. */
    private function purgeOutOfPackStructure(School $school): void
    {
        $allowed = SchoolCategoryCatalog::allowedLevelCodes($school) ?? [];
        if ($allowed === []) {
            return;
        }

        $allowedLookup = array_fill_keys($allowed, true);
        $outLevels = Level::query()
            ->where('school_id', $school->id)
            ->get()
            ->filter(fn (Level $level) => ! isset($allowedLookup[strtolower((string) $level->code)]));

        if ($outLevels->isEmpty()) {
            return;
        }

        $levelIds = $outLevels->pluck('id')->all();
        $classIds = SchoolClass::query()
            ->where('school_id', $school->id)
            ->whereIn('level_id', $levelIds)
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($school, $levelIds, $classIds) {
            if ($classIds !== []) {
                $studentIds = Student::query()
                    ->where('school_id', $school->id)
                    ->whereIn('school_class_id', $classIds)
                    ->pluck('id')
                    ->all();

                if ($studentIds !== []) {
                    $invoiceIds = Invoice::query()
                        ->where('school_id', $school->id)
                        ->whereIn('student_id', $studentIds)
                        ->pluck('id')
                        ->all();
                    if ($invoiceIds !== []) {
                        Payment::query()->where('school_id', $school->id)->whereIn('invoice_id', $invoiceIds)->delete();
                        InvoiceItem::query()->where('school_id', $school->id)->whereIn('invoice_id', $invoiceIds)->delete();
                        Invoice::query()->where('school_id', $school->id)->whereIn('id', $invoiceIds)->delete();
                    }
                    AttendanceRecord::query()->where('school_id', $school->id)->whereIn('student_id', $studentIds)->delete();
                    DB::table('student_guardians')->where('school_id', $school->id)->whereIn('student_id', $studentIds)->delete();
                    Student::query()->where('school_id', $school->id)->whereIn('id', $studentIds)->delete();
                }

                ClassSubject::query()->where('school_id', $school->id)->whereIn('school_class_id', $classIds)->delete();
                ClassEducator::query()->where('school_id', $school->id)->whereIn('school_class_id', $classIds)->delete();
                AdmissionApplication::query()->where('school_id', $school->id)->whereIn('preferred_class_id', $classIds)->update(['preferred_class_id' => null]);
                SchoolClass::query()->where('school_id', $school->id)->whereIn('id', $classIds)->delete();
            }

            Level::query()->where('school_id', $school->id)->whereIn('id', $levelIds)->delete();
        });
    }

    /** @param  array<string, User>  $users */
    private function seedStaff(School $school, array $users): void
    {
        $rows = [
            ['user' => 'school_admin', 'number' => 'SWN-ADM', 'first' => 'Awa', 'last' => 'Jallow', 'title' => 'Nursery Administrator'],
            ['user' => 'headteacher', 'number' => 'SWN-HEAD', 'first' => 'Haddy', 'last' => 'Njie', 'title' => 'Head of Nursery'],
            ['user' => 'deputy_headteacher', 'number' => 'SWN-DEP', 'first' => 'Ousman', 'last' => 'Ceesay', 'title' => 'Deputy Head of Nursery'],
            ['user' => 'academic_coordinator', 'number' => 'SWN-ACD', 'first' => 'Isatou', 'last' => 'Sowe', 'title' => 'Early Years Coordinator'],
            ['user' => 'teacher', 'number' => 'SWN-EDU-01', 'first' => 'Fatou', 'last' => 'Jallow', 'title' => 'Lead Educator'],
            ['user' => 'teacher_b', 'number' => 'SWN-EDU-02', 'first' => 'Musa', 'last' => 'Touray', 'title' => 'Lead Educator'],
            ['user' => 'teacher_c', 'number' => 'SWN-EDU-03', 'first' => 'Awa', 'last' => 'Camara', 'title' => 'Assistant Educator'],
            ['user' => 'admissions_officer', 'number' => 'SWN-ADM-OFF', 'first' => 'Satang', 'last' => 'Colley', 'title' => 'Admissions Officer'],
            ['user' => 'finance_officer', 'number' => 'SWN-FIN', 'first' => 'Binta', 'last' => 'Sanyang', 'title' => 'Finance Officer'],
            ['user' => 'records_officer', 'number' => 'SWN-REC', 'first' => 'Mariama', 'last' => 'Bojang', 'title' => 'Records Officer'],
        ];

        foreach ($rows as $i => $row) {
            $user = $users[$row['user']] ?? null;
            if (! $user) {
                continue;
            }

            Staff::query()->updateOrCreate(
                ['school_id' => $school->id, 'staff_number' => $row['number']],
                [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'first_name' => $row['first'],
                    'last_name' => $row['last'],
                    'phone' => $user->phone,
                    'role_title' => $row['title'],
                    'status' => 'active',
                    'hired_on' => now()->subYears(2)->subMonths($i)->toDateString(),
                    'employment_type' => 'permanent',
                ]
            );
        }
    }

    /** @return array{0: AcademicYear, 1: Term} */
    private function seedCalendar(School $school): array
    {
        $year = AcademicYear::query()->updateOrCreate(
            ['school_id' => $school->id, 'name' => '2025/2026'],
            [
                'starts_on' => '2025-09-01',
                'ends_on' => '2026-07-31',
                'is_current' => true,
            ]
        );

        AcademicYear::query()
            ->where('school_id', $school->id)
            ->where('id', '!=', $year->id)
            ->update(['is_current' => false]);

        Term::query()->updateOrCreate(
            ['school_id' => $school->id, 'academic_year_id' => $year->id, 'name' => 'Term 1'],
            ['starts_on' => '2025-09-01', 'ends_on' => '2025-12-12', 'is_current' => false]
        );

        $term = Term::query()->updateOrCreate(
            ['school_id' => $school->id, 'academic_year_id' => $year->id, 'name' => 'Term 2'],
            ['starts_on' => '2026-01-06', 'ends_on' => '2026-04-10', 'is_current' => true]
        );

        Term::query()
            ->where('school_id', $school->id)
            ->where('academic_year_id', $year->id)
            ->where('id', '!=', $term->id)
            ->update(['is_current' => false]);

        Term::query()->updateOrCreate(
            ['school_id' => $school->id, 'academic_year_id' => $year->id, 'name' => 'Term 3'],
            ['starts_on' => '2026-04-20', 'ends_on' => '2026-07-17', 'is_current' => false]
        );

        return [$year, $term];
    }

    /**
     * @param  array<string, User>  $users
     * @return array<string, SchoolClass>
     */
    private function seedClassrooms(School $school, array $users): array
    {
        $leads = [
            'baby' => $users['teacher'],
            'toddler' => $users['teacher_b'],
            'n1' => $users['teacher'],
            'n2' => $users['teacher_b'],
            'reception' => $users['teacher'],
        ];
        $assistant = $users['teacher_c'];

        $classes = [];
        $levels = Level::query()->where('school_id', $school->id)->orderBy('sort_order')->get();

        foreach ($levels as $level) {
            $code = strtolower((string) $level->code);
            $name = match ($code) {
                'baby' => 'Baby Class A',
                'toddler' => 'Toddler A',
                'n1' => 'Nursery 1A',
                'n2' => 'Nursery 2A',
                'reception' => 'Reception A',
                default => $level->name.' A',
            };

            $lead = $leads[$code] ?? $users['teacher'];
            $class = SchoolClass::query()->updateOrCreate(
                ['school_id' => $school->id, 'name' => $name],
                [
                    'level_id' => $level->id,
                    'stream' => 'A',
                    'class_teacher_id' => $lead->id,
                    'capacity' => $code === 'baby' ? 12 : ($code === 'toddler' ? 16 : 20),
                    'teaching_arrangement' => $level->teaching_arrangement,
                    'learning_centres' => $level->teaching_arrangement === 'learning_centre'
                        ? [
                            ['key' => 'literacy', 'name' => 'Literacy corner', 'active' => true],
                            ['key' => 'numeracy', 'name' => 'Numeracy table', 'active' => true],
                            ['key' => 'creative', 'name' => 'Creative arts', 'active' => true],
                            ['key' => 'discovery', 'name' => 'Discovery area', 'active' => true],
                        ]
                        : null,
                ]
            );

            ClassEducator::query()->where('school_class_id', $class->id)->delete();
            ClassEducator::query()->create([
                'school_id' => $school->id,
                'school_class_id' => $class->id,
                'user_id' => $lead->id,
                'role' => ClassEducator::LEAD,
            ]);
            ClassEducator::query()->create([
                'school_id' => $school->id,
                'school_class_id' => $class->id,
                'user_id' => $assistant->id,
                'role' => ClassEducator::ASSISTANT,
            ]);

            $classes[$code] = $class;
        }

        return $classes;
    }

    /** @param  array<string, SchoolClass>  $classes */
    private function allocateDomains(School $school, array $classes): void
    {
        $domains = Subject::query()
            ->where('school_id', $school->id)
            ->where('domain_kind', 'learning_domain')
            ->get();

        foreach ($classes as $class) {
            $leadId = $class->class_teacher_id;
            foreach ($domains as $domain) {
                ClassSubject::query()->updateOrCreate(
                    [
                        'school_id' => $school->id,
                        'school_class_id' => $class->id,
                        'subject_id' => $domain->id,
                    ],
                    [
                        'teacher_id' => $leadId,
                        'periods_per_week' => 3,
                    ]
                );
            }
        }
    }

    /**
     * @param  array<string, SchoolClass>  $classes
     * @param  array<string, User>  $users
     * @return list<Student>
     */
    private function seedChildren(School $school, array $classes, array $users): array
    {
        $roster = [
            ['baby', 'Aminata', 'Jallow', 'female', 30],
            ['baby', 'Omar', 'Ceesay', 'male', 28],
            ['toddler', 'Fatou', 'Bah', 'female', 40],
            ['toddler', 'Lamin', 'Sowe', 'male', 42],
            ['toddler', 'Isatou', 'Touray', 'female', 38],
            ['n1', 'Modou', 'Camara', 'male', 52],
            ['n1', 'Mariama', 'Njie', 'female', 54],
            ['n1', 'Ebrima', 'Sanyang', 'male', 50],
            ['n2', 'Awa', 'Bojang', 'female', 64],
            ['n2', 'Musa', 'Colley', 'male', 66],
            ['n2', 'Haddy', 'Manneh', 'female', 62],
            ['reception', 'Kebba', 'Jallow', 'male', 68],
            ['reception', 'Satang', 'Darboe', 'female', 70],
            ['reception', 'Yankuba', 'Sillah', 'male', 69],
        ];

        $studentUser = $users['student'];
        $parentUser = $users['parent'];
        $children = [];

        foreach ($roster as $i => [$levelCode, $first, $last, $gender, $ageMonths]) {
            $class = $classes[$levelCode] ?? null;
            if (! $class) {
                continue;
            }

            $number = 'SWN-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);
            $linkUser = $i === 0 ? $studentUser->id : null;

            $child = Student::query()->updateOrCreate(
                ['school_id' => $school->id, 'student_number' => $number],
                [
                    'user_id' => $linkUser,
                    'school_class_id' => $class->id,
                    'first_name' => $first,
                    'last_name' => $last,
                    'gender' => $gender,
                    'date_of_birth' => now()->subMonths($ageMonths)->subDays($i * 3)->toDateString(),
                    'place_of_birth' => 'Banjul',
                    'nationality' => 'Gambian',
                    'religion' => 'Islam',
                    'status' => 'active',
                    'admitted_on' => '2025-09-02',
                ]
            );
            $children[] = $child;

            if ($i === 0) {
                $studentUser->forceFill(['name' => $first.' '.$last])->save();
            }

            $guardian = Guardian::query()->updateOrCreate(
                ['school_id' => $school->id, 'email' => Str::lower($first).'.'.$last.'.family@smartworld.gm'],
                [
                    'user_id' => $i === 0 ? $parentUser->id : null,
                    'first_name' => $i % 2 === 0 ? 'Parent' : 'Guardian',
                    'last_name' => $last,
                    'relationship' => $i % 2 === 0 ? 'Mother' : 'Father',
                    'phone' => '+220 7'.str_pad((string) (310000 + $i), 6, '0', STR_PAD_LEFT),
                    'occupation' => $i % 2 === 0 ? 'Trader' : 'Civil servant',
                ]
            );

            if ($i === 0) {
                $parentUser->forceFill(['name' => $guardian->first_name.' '.$guardian->last_name])->save();
            }

            $child->guardians()->syncWithoutDetaching([
                $guardian->id => ['school_id' => $school->id, 'is_primary' => true],
            ]);
        }

        return $children;
    }

    /** @param  list<Student>  $children */
    private function seedAttendance(School $school, array $children, User $educator): void
    {
        foreach ($children as $i => $child) {
            foreach (range(0, 4) as $day) {
                AttendanceRecord::query()->updateOrCreate(
                    [
                        'student_id' => $child->id,
                        'attended_on' => now()->subDays($day)->toDateString(),
                        'session' => 'full',
                    ],
                    [
                        'school_id' => $school->id,
                        'school_class_id' => $child->school_class_id,
                        'status' => $day === 2 && $i === 3 ? 'absent' : ($day === 1 && $i === 5 ? 'late' : 'present'),
                        'recorded_by' => $educator->id,
                    ]
                );
            }
        }
    }

    /** @param  list<Student>  $children */
    private function seedFees(School $school, array $children, Term $term, User $finance): void
    {
        $tuition = FeeItem::query()->updateOrCreate(
            ['school_id' => $school->id, 'name' => 'Termly tuition'],
            ['amount' => 3500, 'applicable_to' => 'all', 'type' => 'compulsory', 'is_active' => true]
        );
        $meals = FeeItem::query()->updateOrCreate(
            ['school_id' => $school->id, 'name' => 'Meals'],
            ['amount' => 1200, 'applicable_to' => 'all', 'type' => 'compulsory', 'is_active' => true]
        );

        foreach ($children as $i => $child) {
            $total = (int) $tuition->amount + (int) $meals->amount;
            $paid = $i < 8 ? $total : ($i < 11 ? (int) ($total * 0.5) : 0);
            $status = $paid >= $total ? 'paid' : ($paid > 0 ? 'partial' : 'issued');

            $invoice = Invoice::query()->updateOrCreate(
                [
                    'school_id' => $school->id,
                    'invoice_number' => 'INV-SWN-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                ],
                [
                    'student_id' => $child->id,
                    'status' => $status,
                    'subtotal' => $total,
                    'adjustments_total' => 0,
                    'total_amount' => $total,
                    'amount_paid' => $paid,
                    'due_on' => now()->parse((string) $term->starts_on)->addDays(21)->toDateString(),
                    'issued_at' => now()->parse((string) $term->starts_on)->addDays(2),
                ]
            );

            foreach ([$tuition, $meals] as $item) {
                InvoiceItem::query()->updateOrCreate(
                    [
                        'invoice_id' => $invoice->id,
                        'fee_item_id' => $item->id,
                    ],
                    [
                        'school_id' => $school->id,
                        'description' => $item->name,
                        'amount' => $item->amount,
                    ]
                );
            }

            if ($paid > 0) {
                Payment::query()->updateOrCreate(
                    [
                        'school_id' => $school->id,
                        'receipt_number' => 'RCPT-SWN-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                    ],
                    [
                        'invoice_id' => $invoice->id,
                        'student_id' => $child->id,
                        'amount' => $paid,
                        'method' => $i % 2 === 0 ? 'cash' : 'wave',
                        'status' => 'confirmed',
                        'paid_at' => now()->parse((string) $term->starts_on)->addDays(5 + $i),
                        'recorded_by' => $finance->id,
                        'receipt_sent' => true,
                    ]
                );
            }
        }
    }

    /** @param  array<string, SchoolClass>  $classes */
    private function seedAdmissions(School $school, array $classes): void
    {
        $preferred = $classes['toddler'] ?? reset($classes);
        if (! $preferred instanceof SchoolClass) {
            return;
        }

        AdmissionApplication::query()->updateOrCreate(
            ['school_id' => $school->id, 'application_number' => 'ADM-SWN-2026-0001'],
            [
                'status' => 'submitted',
                'first_name' => 'Ndey',
                'last_name' => 'Kinteh',
                'gender' => 'female',
                'date_of_birth' => now()->subMonths(36)->toDateString(),
                'preferred_class_id' => $preferred->id,
                'guardian_name' => 'Ya Kinteh',
                'guardian_relationship' => 'Mother',
                'guardian_phone' => '+220 7001888',
                'nationality' => 'Gambian',
                'notes' => 'Looking for a place in Toddler from next term.',
            ]
        );
    }
}
