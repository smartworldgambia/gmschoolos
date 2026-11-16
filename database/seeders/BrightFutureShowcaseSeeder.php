<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\AnnouncementRecipient;
use App\Models\FeeAdjustment;
use App\Models\GlAccount;
use App\Models\GlJournal;
use App\Models\NewsPost;
use App\Models\School;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\ChartOfAccounts;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Deepens Bright Future School with realistic operational data for demos —
 * especially AIS journals — without any "demo" wording in user-visible fields.
 */
class BrightFutureShowcaseSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::query()->where('slug', 'brightfuture')->first();
        if (! $school) {
            $this->command?->warn('BrightFutureShowcaseSeeder: Bright Future School not found — run DemoSchoolSeeder first.');

            return;
        }

        app(TenantContext::class)->set($school);

        $finance = User::query()
            ->where('school_id', $school->id)
            ->where(function ($q) {
                $q->where('email', 'like', 'finance@%')
                    ->orWhere('email', 'like', 'accountant@%')
                    ->orWhere('email', 'like', 'admin@%');
            })
            ->orderByRaw("CASE WHEN email LIKE 'finance@%' THEN 0 WHEN email LIKE 'accountant@%' THEN 1 ELSE 2 END")
            ->first();

        $admin = User::query()
            ->where('school_id', $school->id)
            ->where('email', 'like', 'admin@%')
            ->first() ?? $finance;

        if (! $finance || ! $admin) {
            $this->command?->warn('BrightFutureShowcaseSeeder: missing finance/admin users.');
            app(TenantContext::class)->clear();

            return;
        }

        $this->seedAccounting($school, $finance);
        $this->seedExtraNews($school);
        $this->seedExtraAnnouncements($school, $admin);
        $this->backfillFeeLedgerHints($school);

        app(TenantContext::class)->clear();
        $this->command?->info('Bright Future School showcase data ready (AIS, news, announcements).');
    }

    private function seedAccounting(School $school, User $finance): void
    {
        $ais = app(AccountingService::class);
        $coa = app(ChartOfAccounts::class);
        $ais->ensureReady();

        if ($ais->setupNeeded()) {
            $maps = $coa->maps();
            $ais->saveMaps($maps);
        }

        $accounts = GlAccount::query()
            ->where('school_id', $school->id)
            ->get()
            ->keyBy('code');

        if ($accounts->isEmpty()) {
            $this->command?->warn('BrightFutureShowcaseSeeder: chart of accounts missing.');

            return;
        }

        $id = fn (string $code) => (int) $accounts[$code]->id;

        if (! GlJournal::query()->where('school_id', $school->id)->where('source', 'opening')->where('status', 'posted')->exists()) {
            try {
                $ais->postOpening($finance, '2026-09-01', [
                    ['account_id' => $id('1000'), 'debit' => 85000],
                    ['account_id' => $id('1010'), 'debit' => 425000],
                    ['account_id' => $id('1100'), 'debit' => 186000],
                    ['account_id' => $id('2000'), 'credit' => 42000],
                    ['account_id' => $id('3000'), 'credit' => 654000],
                ]);
            } catch (\Throwable $e) {
                $this->command?->warn('Opening balances skipped: '.$e->getMessage());
            }
        }

        $manuals = [
            [
                'date' => '2026-09-12',
                'memo' => 'Stationery restock — Term 1 (Kanifing Science Supplies)',
                'lines' => [
                    ['account_id' => $id('5300'), 'debit' => 12500],
                    ['account_id' => $id('1010'), 'credit' => 12500],
                ],
            ],
            [
                'date' => '2026-09-28',
                'memo' => 'NAWEC electricity — September',
                'lines' => [
                    ['account_id' => $id('5200'), 'debit' => 18400],
                    ['account_id' => $id('1010'), 'credit' => 18400],
                ],
            ],
            [
                'date' => '2026-10-03',
                'memo' => 'Tuition collections deposited — Week 1 October',
                'lines' => [
                    ['account_id' => $id('1010'), 'debit' => 96500],
                    ['account_id' => $id('1100'), 'credit' => 96500],
                ],
            ],
            [
                'date' => '2026-10-08',
                'memo' => 'PTA fundraising — sports day raffle',
                'lines' => [
                    ['account_id' => $id('1000'), 'debit' => 7800],
                    ['account_id' => $id('4100'), 'credit' => 7800],
                ],
            ],
            [
                'date' => '2026-10-15',
                'memo' => 'Staff salaries — October (Trust Bank transfer)',
                'lines' => [
                    ['account_id' => $id('5000'), 'debit' => 214500],
                    ['account_id' => $id('1010'), 'credit' => 214500],
                ],
            ],
            [
                'date' => '2026-10-22',
                'memo' => 'GAMTEL internet & phone — October',
                'lines' => [
                    ['account_id' => $id('5200'), 'debit' => 4500],
                    ['account_id' => $id('1010'), 'credit' => 4500],
                ],
            ],
            [
                'date' => '2026-11-02',
                'memo' => 'Laboratory chemicals for SSS Biology',
                'lines' => [
                    ['account_id' => $id('5300'), 'debit' => 9200],
                    ['account_id' => $id('2000'), 'credit' => 9200],
                ],
            ],
            [
                'date' => '2026-11-05',
                'memo' => 'Fee income recognised — mid-term invoices',
                'lines' => [
                    ['account_id' => $id('1100'), 'debit' => 142500],
                    ['account_id' => $id('4000'), 'credit' => 142500],
                ],
            ],
            [
                'date' => '2026-11-10',
                'memo' => 'Wave mobile money fee collections — November',
                'lines' => [
                    ['account_id' => $id('1010'), 'debit' => 67800],
                    ['account_id' => $id('1100'), 'credit' => 67800],
                ],
            ],
            [
                'date' => '2026-11-12',
                'memo' => 'Scholarship discount — Lamin Ceesay (Term 1)',
                'lines' => [
                    ['account_id' => $id('5400'), 'debit' => 1500],
                    ['account_id' => $id('1100'), 'credit' => 1500],
                ],
            ],
            [
                'date' => '2026-11-14',
                'memo' => 'Supplier settlement — Kanifing Science Supplies',
                'lines' => [
                    ['account_id' => $id('2000'), 'debit' => 9200],
                    ['account_id' => $id('1010'), 'credit' => 9200],
                ],
            ],
            [
                'date' => '2026-11-15',
                'memo' => 'Petty cash replenishment — front office',
                'lines' => [
                    ['account_id' => $id('1000'), 'debit' => 5000],
                    ['account_id' => $id('1010'), 'credit' => 5000],
                ],
            ],
        ];

        foreach ($manuals as $row) {
            $exists = GlJournal::query()
                ->where('school_id', $school->id)
                ->where('source', 'manual')
                ->where('memo', $row['memo'])
                ->whereIn('status', ['posted', 'reversed'])
                ->exists();

            if ($exists) {
                continue;
            }

            try {
                $ais->postManual($finance, $row['date'], $row['memo'], $row['lines']);
            } catch (\Throwable $e) {
                $this->command?->warn('Journal skipped ('.$row['memo'].'): '.$e->getMessage());
            }
        }
    }

    private function seedExtraNews(School $school): void
    {
        $posts = [
            [
                'slug' => 'inter-house-sports-2026',
                'title' => 'Inter-house sports day brings the campus alive',
                'excerpt' => 'Learners from Nursery to SSS competed in athletics, football and cultural displays on the main field.',
                'body' => "Bright Future School held its annual inter-house sports day with strong turnout from parents and alumni.\n\nSunrise House took the athletics cup, while Harbour House won the football finals. The PTA provided refreshments and awarded medals at the closing ceremony.",
                'days' => 12,
            ],
            [
                'slug' => 'gabece-registration-open',
                'title' => 'GABECE 2026 registration now open',
                'excerpt' => 'Grade 9 families should complete forms at the exams office before 28 February 2026.',
                'body' => "Registration for the 2026 GABECE is open. Candidates need a birth certificate photocopy, two passport photos and the registration fee.\n\nVisit the Examinations Office on weekdays between 8:00 and 14:00. Late applications may not be accepted by WAEC.",
                'days' => 8,
            ],
            [
                'slug' => 'science-fair-winners',
                'title' => 'SSS science fair winners announced',
                'excerpt' => 'Projects on solar water heating and malaria awareness earned top places.',
                'body' => "Congratulations to our Senior Secondary science students. The panel of teachers and visiting mentors praised the practical focus of this year’s exhibits.\n\nWinning teams will present at the Greater Banjul schools STEM meet next month.",
                'days' => 5,
            ],
            [
                'slug' => 'library-week',
                'title' => 'Library week: read, borrow, share',
                'excerpt' => 'Extra borrowing allowances and reading circles run all week in the main library.',
                'body' => "Library Week invites every class to visit. Learners may borrow two additional titles this week, and prefects will host lunchtime reading circles for Grades 4–9.",
                'days' => 3,
            ],
        ];

        foreach ($posts as $post) {
            NewsPost::query()->updateOrCreate(
                ['school_id' => $school->id, 'slug' => $post['slug']],
                [
                    'title' => $post['title'],
                    'excerpt' => $post['excerpt'],
                    'body' => $post['body'],
                    'published_at' => now()->subDays($post['days']),
                ]
            );
        }
    }

    private function seedExtraAnnouncements(School $school, User $admin): void
    {
        $items = [
            [
                'title' => 'Parents’ evening — Grades 7 to 9',
                'body' => 'Join class teachers on Thursday from 16:00 to 18:30 in the multipurpose hall. Report cards will be available for collection.',
                'audience' => 'school',
                'days' => 2,
            ],
            [
                'title' => 'Staff briefing: attendance registers',
                'body' => 'Please submit morning registers by 08:45. Corrections after 10:00 need headteacher approval.',
                'audience' => 'staff',
                'days' => 1,
            ],
            [
                'title' => 'Uniform reminder — Term 2',
                'body' => 'Full school uniform is required from Monday. PE kits only on timetable PE days.',
                'audience' => 'school',
                'days' => 0,
            ],
        ];

        foreach ($items as $item) {
            $announcement = Announcement::query()->updateOrCreate(
                ['school_id' => $school->id, 'title' => $item['title']],
                [
                    'author_id' => $admin->id,
                    'body' => $item['body'],
                    'audience' => $item['audience'],
                    'channels' => ['in_app'],
                    'status' => 'published',
                    'publish_at' => now()->subDays($item['days']),
                    'published_at' => now()->subDays($item['days']),
                    'recipients_count' => 1,
                ]
            );

            AnnouncementRecipient::query()->updateOrCreate(
                ['announcement_id' => $announcement->id, 'user_id' => $admin->id],
                ['school_id' => $school->id, 'read_at' => null]
            );
        }
    }

    private function backfillFeeLedgerHints(School $school): void
    {
        FeeAdjustment::query()
            ->where('school_id', $school->id)
            ->where(function ($q) {
                $q->where('reason', 'like', '%Demo%')
                    ->orWhere('reason', 'like', '%demo%');
            })
            ->update(['reason' => 'Approved fee concession — financial hardship']);

        unset($school);
    }
}
