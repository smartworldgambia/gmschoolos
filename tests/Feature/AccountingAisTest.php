<?php

namespace Tests\Feature;

use App\Models\GlAccount;
use App\Models\GlJournal;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\ChartOfAccounts;
use App\Support\TenantContext;
use Database\Seeders\RolesAndPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingAisTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPlansSeeder::class);
        $this->school = School::query()->create([
            'name' => 'Alpha School',
            'slug' => 'alpha',
            'status' => 'active',
            'website_published' => true,
        ]);
        app(TenantContext::class)->set($this->school);
        $this->admin = $this->user('school_admin');
    }

    protected function user(string $role, ?School $school = null): User
    {
        $user = User::factory()->create([
            'school_id' => ($school ?? $this->school)->id,
            'email_verified_at' => now(),
        ]);
        $user->assignRole($role);

        return $user;
    }

    protected function platform(string $role = 'platform_admin'): User
    {
        $user = User::factory()->create([
            'school_id' => null,
            'email_verified_at' => now(),
        ]);
        $user->assignRole($role);

        return $user;
    }

    public function test_only_finance_roles_open_ais(): void
    {
        foreach (['teacher', 'parent', 'hr_officer'] as $role) {
            $this->actingAs($this->user($role))->get(route('accounting.index'))->assertForbidden();
        }

        foreach (['accountant', 'finance_officer', 'school_admin'] as $role) {
            $this->actingAs($this->user($role))->get(route('accounting.index'))->assertOk();
        }
    }

    public function test_unbalanced_journal_is_rejected_and_balanced_posts_into_trial_balance(): void
    {
        app(ChartOfAccounts::class)->ensureFor($this->school);
        $cash = GlAccount::query()->where('code', '1000')->firstOrFail();
        $income = GlAccount::query()->where('code', '4000')->firstOrFail();

        $this->actingAs($this->admin)->post(route('accounting.journals.store'), [
            'date' => now()->toDateString(),
            'memo' => 'Bad',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 100, 'credit' => 0],
                ['account_id' => $income->id, 'debit' => 0, 'credit' => 50],
            ],
        ])->assertSessionHas('error', 'err.ais.unbalanced');

        $this->actingAs($this->admin)->post(route('accounting.journals.store'), [
            'date' => now()->toDateString(),
            'memo' => 'Good',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 100, 'credit' => 0],
                ['account_id' => $income->id, 'debit' => 0, 'credit' => 100],
            ],
        ])->assertSessionHas('success', 'ok.ais.journalPosted');

        $rows = app(AccountingService::class)->trialBalance(now()->toDateString());
        $debit = array_sum(array_column($rows, 'debit'));
        $credit = array_sum(array_column($rows, 'credit'));
        $this->assertSame($debit, $credit);
        $this->assertSame(100, $debit);
    }

    public function test_two_schools_journals_stay_isolated(): void
    {
        $other = School::query()->create(['name' => 'Beta', 'slug' => 'beta', 'status' => 'active']);
        app(TenantContext::class)->set($this->school);
        app(ChartOfAccounts::class)->ensureFor($this->school);
        $cash = GlAccount::query()->where('code', '1000')->firstOrFail();
        $income = GlAccount::query()->where('code', '4000')->firstOrFail();

        $this->actingAs($this->admin)->post(route('accounting.journals.store'), [
            'date' => now()->toDateString(),
            'memo' => 'Alpha only',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 50, 'credit' => 0],
                ['account_id' => $income->id, 'debit' => 0, 'credit' => 50],
            ],
        ])->assertSessionHasNoErrors();

        $betaAdmin = $this->user('school_admin', $other);
        app(TenantContext::class)->set($other);
        $this->actingAs($betaAdmin)->get(route('accounting.index'))->assertOk();
        $this->assertSame(0, GlJournal::query()->count());

        $alphaJournal = app(TenantContext::class)->runForSchool($this->school, fn () => GlJournal::query()->firstOrFail());
        $this->actingAs($betaAdmin)->post(route('accounting.journals.reverse', $alphaJournal))->assertNotFound();
    }

    public function test_confirmed_fee_payment_posts_once_and_void_reverses(): void
    {
        app(ChartOfAccounts::class)->ensureFor($this->school);
        $student = Student::query()->create([
            'school_id' => $this->school->id,
            'student_number' => 'STU-AIS-1',
            'first_name' => 'Awa',
            'last_name' => 'Jallow',
            'gender' => 'female',
            'date_of_birth' => '2010-01-01',
            'status' => 'active',
        ]);
        $invoice = Invoice::query()->create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'invoice_number' => 'INV-AIS-1',
            'status' => 'issued',
            'subtotal' => 1000,
            'adjustments_total' => 0,
            'total_amount' => 1000,
            'amount_paid' => 0,
            'issued_at' => now(),
            'due_on' => now()->addDays(30)->toDateString(),
        ]);

        $payment = app(\App\Services\Finance\PaymentService::class)->record(
            $invoice,
            1000,
            'cash',
            now()->toDateString(),
            null,
            'Parent',
            null,
            $this->admin
        );

        $this->assertSame(1, GlJournal::query()->where('source', 'fee_payment')->where('source_ref_id', $payment->id)->count());

        app(\App\Services\Accounting\AccountingService::class)->postFeePayment($payment->fresh(), $this->admin);
        $this->assertSame(1, GlJournal::query()->where('source', 'fee_payment')->where('source_ref_id', $payment->id)->count());

        app(\App\Services\Finance\PaymentService::class)->void($payment->fresh(), 'mistake', $this->admin);

        $original = GlJournal::query()->where('source', 'fee_payment')->where('source_ref_id', $payment->id)->firstOrFail();
        $this->assertSame('reversed', $original->status);
        $this->assertSame(1, GlJournal::query()->where('source', 'reversal')->where('reverses_journal_id', $original->id)->count());

        $rows = app(AccountingService::class)->trialBalance(now()->toDateString());
        $this->assertSame(array_sum(array_column($rows, 'debit')), array_sum(array_column($rows, 'credit')));
    }

    public function test_backfill_posts_seed_style_fee_rows_into_ais(): void
    {
        app(ChartOfAccounts::class)->ensureFor($this->school);
        $student = Student::query()->create([
            'school_id' => $this->school->id,
            'student_number' => 'STU-AIS-BF',
            'first_name' => 'Lamin',
            'last_name' => 'Ceesay',
            'gender' => 'male',
            'date_of_birth' => '2011-05-01',
            'status' => 'active',
        ]);

        // Mimic DemoRichDataSeeder: insert invoice + confirmed payment without PaymentService.
        $invoice = Invoice::query()->create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'invoice_number' => 'INV-SEED-1',
            'status' => 'paid',
            'subtotal' => 5250,
            'adjustments_total' => 0,
            'total_amount' => 5250,
            'amount_paid' => 5250,
            'issued_at' => now()->subDays(10),
            'due_on' => now()->addDays(20)->toDateString(),
        ]);
        $payment = Payment::query()->create([
            'school_id' => $this->school->id,
            'invoice_id' => $invoice->id,
            'student_id' => $student->id,
            'amount' => 5250,
            'method' => 'wave',
            'status' => 'confirmed',
            'paid_at' => now()->subDays(3),
            'receipt_number' => 'RCPT-SEED-1',
            'recorded_by' => $this->admin->id,
        ]);

        $this->assertSame(0, GlJournal::query()->where('source', 'fee_invoice')->count());
        $this->assertSame(0, GlJournal::query()->where('source', 'fee_payment')->count());

        $ais = app(AccountingService::class);
        $first = $ais->backfillFeeLedgers($this->admin);
        $this->assertFalse($first['skipped']);
        $this->assertSame(1, $first['invoices']);
        $this->assertSame(1, $first['payments']);
        $this->assertSame(1, GlJournal::query()->where('source', 'fee_invoice')->where('source_ref_id', $invoice->id)->count());
        $this->assertSame(1, GlJournal::query()->where('source', 'fee_payment')->where('source_ref_id', $payment->id)->count());

        $second = $ais->backfillFeeLedgers($this->admin);
        $this->assertSame(0, $second['invoices']);
        $this->assertSame(0, $second['payments']);

        $stats = $ais->overview(now()->subMonth()->toDateString(), now()->toDateString());
        $this->assertSame(5250, $stats['income']);
        $this->assertGreaterThan(0, $stats['cash_bank']);
    }

    public function test_accountant_nav_has_ais_and_fees_not_domain(): void
    {
        $response = $this->actingAs($this->user('accountant'))->get(route('admin.dashboard'))->assertOk();
        $nav = collect($response->viewData('page')['props']['nav'])->pluck('key')->all();
        $this->assertContains('ais', $nav);
        $this->assertContains('fees', $nav);
        $this->assertNotContains('domain', $nav);

        $teacher = $this->actingAs($this->user('teacher'))->get(route('teacher.dashboard'))->assertOk();
        $tNav = collect($teacher->viewData('page')['props']['nav'])->pluck('key')->all();
        $this->assertNotContains('ais', $tNav);
        $this->assertNotContains('domain', $tNav);
    }

    public function test_platform_admin_cannot_open_tenant_ais_but_can_manage_platform_users(): void
    {
        $platform = $this->platform();
        $this->actingAs($platform)->get(route('accounting.index'))->assertForbidden();
        $this->actingAs($platform)->get(route('platform.users.index'))->assertOk();

        $this->actingAs($this->admin)->get(route('platform.users.index'))->assertForbidden();

        $this->actingAs($platform)->post(route('platform.users.store'), [
            'name' => 'Ops Person',
            'email' => 'ops@gambiaschoolos.test',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'role' => 'platform_support',
        ])->assertRedirect(route('platform.users.index'));

        $created = User::query()->where('email', 'ops@gambiaschoolos.test')->firstOrFail();
        $this->assertNull($created->school_id);
        $this->assertTrue($created->hasRole('platform_support'));
    }

    public function test_unmapped_maps_do_not_break_fee_payment(): void
    {
        // Wipe maps after COA seed so auto-post skips.
        app(ChartOfAccounts::class)->ensureFor($this->school);
        \App\Models\GlAccountMap::query()->delete();

        $student = Student::query()->create([
            'school_id' => $this->school->id,
            'student_number' => 'STU-AIS-2',
            'first_name' => 'Binta',
            'last_name' => 'Ceesay',
            'gender' => 'female',
            'date_of_birth' => '2011-01-01',
            'status' => 'active',
        ]);
        $invoice = Invoice::query()->create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'invoice_number' => 'INV-AIS-2',
            'status' => 'issued',
            'subtotal' => 500,
            'adjustments_total' => 0,
            'total_amount' => 500,
            'amount_paid' => 0,
            'issued_at' => now(),
            'due_on' => now()->addDays(30)->toDateString(),
        ]);

        $payment = app(\App\Services\Finance\PaymentService::class)->record(
            $invoice,
            500,
            'cash',
            now()->toDateString(),
            null,
            null,
            null,
            $this->admin
        );

        $this->assertSame('confirmed', $payment->status);
        $this->assertSame(0, GlJournal::query()->where('source', 'fee_payment')->count());

        $this->actingAs($this->admin)->get(route('accounting.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('stats.setup_needed', true));
    }
}
