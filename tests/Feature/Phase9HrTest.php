<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\PayrollComponent;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\Staff;
use App\Models\StaffContract;
use App\Models\StaffDocument;
use App\Models\StaffLeaveRequest;
use App\Models\User;
use App\Notifications\SchoolAnnouncement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/** Phase 9B: staff records, contracts, documents, performance, leave, payroll and payslips. */
class Phase9HrTest extends Phase9TestCase
{
    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hr = $this->user('hr_officer');
    }

    private function contract(Staff $staff, int $salary = 1000, string $from = '2026-01-01', array $over = []): StaffContract
    {
        return $this->inSchool(fn () => StaffContract::query()->create($over + [
            'staff_id' => $staff->id, 'contract_type' => 'permanent', 'starts_on' => $from, 'basic_salary' => $salary, 'status' => 'active',
        ]));
    }

    private function payComp(array $over = []): PayrollComponent
    {
        static $n = 0;
        $n++;

        return $this->inSchool(fn () => PayrollComponent::query()->create($over + [
            'name' => 'Comp '.$n, 'code' => 'C'.$n, 'kind' => 'allowance', 'calc' => 'fixed', 'value' => 100, 'applies_to' => 'all', 'is_active' => true, 'sort_order' => $n,
        ]));
    }

    private function leaveFor(Staff $staff, string $from, string $to, string $status = 'pending', ?User $by = null, array $over = []): StaffLeaveRequest
    {
        return $this->inSchool(fn () => StaffLeaveRequest::query()->create($over + [
            'staff_id' => $staff->id, 'leave_type' => 'annual', 'starts_on' => $from, 'ends_on' => $to, 'days' => 1, 'status' => $status, 'applied_by' => ($by ?? $this->hr)->id,
        ]));
    }

    private function draftRun(string $label = '2026-10', string $start = '2026-10-01', string $end = '2026-10-31', ?User $by = null)
    {
        return $this->actAs($by ?? $this->hr)->post(route('hr.payroll.store'), ['period_label' => $label, 'period_start' => $start, 'period_end' => $end]);
    }

    private function runModel(string $label = '2026-10'): PayrollRun
    {
        return $this->inSchool(fn () => PayrollRun::query()->where('period_label', $label)->firstOrFail());
    }

    // ------------------------------------------------------------------ permissions and isolation

    public function test_hr_areas_are_closed_to_people_without_hr_permissions(): void
    {
        $parent = $this->user('parent');
        foreach ([$this->teacher, $parent] as $u) {
            foreach (['hr.index', 'hr.staff.index', 'hr.leave.index', 'hr.payroll.index', 'hr.setup.index'] as $r) {
                $this->actAs($u)->get(route($r))->assertForbidden();
            }
        }
        $this->actAs($this->teacher)->post(route('hr.staff.store'), ['first_name' => 'A', 'last_name' => 'B'])->assertForbidden();
        $this->actAs($this->teacher)->post(route('hr.payroll.store'), ['period_label' => 'x', 'period_start' => '2026-01-01', 'period_end' => '2026-01-31'])->assertForbidden();

        $this->actAs($this->hr)->get(route('hr.index'))->assertOk()->assertInertia(fn ($p) => $p->component('Hr/Index'));
        $this->actAs($this->hr)->get(route('hr.staff.index'))->assertOk()->assertInertia(fn ($p) => $p->component('Hr/Staff'));
    }

    public function test_staff_are_scoped_to_their_school_and_foreign_ids_are_refused(): void
    {
        $other = $this->otherSchool();
        $foreign = $this->inSchool(fn () => Staff::query()->create(['staff_number' => 'STF-F', 'first_name' => 'Far', 'last_name' => 'Away', 'status' => 'active']), $other);
        $foreignUser = $this->user('teacher', $other);
        $foreignDept = $this->inSchool(fn () => \App\Models\Department::query()->create(['name' => 'Foreign dept']), $other);

        $this->actAs($this->hr)->get(route('hr.staff.show', $foreign->id))->assertNotFound();
        $this->actAs($this->hr)->post(route('hr.leave.store'), ['staff_id' => $foreign->id, 'leave_type' => 'annual', 'starts_on' => '2026-12-01', 'ends_on' => '2026-12-02'])->assertSessionHasErrors('staff_id');
        $this->actAs($this->hr)->post(route('hr.staff.store'), ['first_name' => 'A', 'last_name' => 'B', 'department_id' => $foreignDept->id])->assertSessionHasErrors('department_id');
        $this->actAs($this->hr)->post(route('hr.staff.store'), ['first_name' => 'A', 'last_name' => 'B', 'user_id' => $foreignUser->id])->assertSessionHasErrors('user_id');
        $this->assertSame(0, \DB::table('staff')->where('first_name', 'A')->count());
    }

    // ------------------------------------------------------------------ staff records

    public function test_hr_creates_a_staff_record_with_an_automatic_number_and_audit(): void
    {
        $login = $this->user('teacher');
        $this->actAs($this->hr)->post(route('hr.staff.store'), ['first_name' => 'Binta', 'last_name' => 'Ceesay', 'user_id' => $login->id, 'employment_type' => 'permanent'])
            ->assertRedirect()->assertSessionHas('success', 'ok.hr.staffSaved');

        $staff = $this->inSchool(fn () => Staff::query()->where('first_name', 'Binta')->firstOrFail());
        $this->assertMatchesRegularExpression('/^STF-\d{4}-\d{5}$/', $staff->staff_number);
        $this->assertSame('active', $staff->status);
        $this->assertTrue(ActivityLog::query()->withoutGlobalScopes()->where('event', 'hr.staff.created')->exists());

        // a login can belong to one staff record only
        $this->actAs($this->hr)->post(route('hr.staff.store'), ['first_name' => 'Dup', 'last_name' => 'Login', 'user_id' => $login->id])->assertSessionHas('error', 'err.hr.loginLinked');
        $this->assertSame(0, \DB::table('staff')->where('first_name', 'Dup')->count());
    }

    public function test_exiting_staff_ends_their_active_contract(): void
    {
        $staff = $this->staffRecord();
        $c = $this->contract($staff);

        $this->actAs($this->hr)->put(route('hr.staff.update', $staff->id), ['first_name' => $staff->first_name, 'last_name' => 'Sowe', 'status' => 'exited'])->assertRedirect();

        $this->assertSame('exited', $staff->fresh()->status);
        $this->assertNotNull($staff->fresh()->exited_on);
        $this->assertSame('ended', $c->fresh()->status);
    }

    public function test_bank_details_are_encrypted_masked_and_kept_when_the_field_is_left_empty(): void
    {
        $staff = $this->staffRecord();
        $this->actAs($this->hr)->put(route('hr.staff.update', $staff->id), ['first_name' => $staff->first_name, 'last_name' => 'Sowe', 'status' => 'active', 'bank_name' => 'GT Bank', 'bank_account' => '1234567890'])->assertRedirect();

        $this->assertNotSame('1234567890', \DB::table('staff')->where('id', $staff->id)->value('bank_account'));
        $this->assertSame('1234567890', $staff->fresh()->bank_account);

        $res = $this->actAs($this->hr)->get(route('hr.staff.show', $staff->id));
        $res->assertOk()->assertInertia(fn ($p) => $p->where('staff.bank_account_masked', '••••••7890'));
        $this->assertStringNotContainsString('1234567890', $res->getContent());

        // saving again with empty bank fields keeps what is stored
        $this->actAs($this->hr)->put(route('hr.staff.update', $staff->id), ['first_name' => $staff->first_name, 'last_name' => 'Sowe', 'status' => 'active', 'bank_account' => ''])->assertRedirect();
        $this->assertSame('1234567890', $staff->fresh()->bank_account);

        // a viewer who only has hr.view never receives the bank details
        $viewer = $this->user('teacher');
        $viewer->givePermissionTo('hr.view');
        $this->actAs($viewer)->get(route('hr.staff.show', $staff->id))->assertOk()->assertInertia(fn ($p) => $p->where('staff.bank_account_masked', null)->where('staff.bank_name', null));
    }

    // ------------------------------------------------------------------ contracts

    public function test_contracts_need_payroll_rights_and_only_one_is_active(): void
    {
        $staff = $this->staffRecord();
        $body = ['contract_type' => 'permanent', 'starts_on' => '2026-01-01', 'basic_salary' => 3000];

        $this->actAs($this->head)->post(route('hr.contracts.store', $staff->id), $body)->assertForbidden();
        $this->actAs($this->hr)->post(route('hr.contracts.store', $staff->id), $body)->assertSessionHas('success', 'ok.hr.contractSaved');
        $this->actAs($this->hr)->post(route('hr.contracts.store', $staff->id), ['starts_on' => '2026-09-01', 'basic_salary' => 3500, 'contract_type' => 'permanent'])->assertSessionHas('success');

        $rows = StaffContract::query()->where('staff_id', $staff->id)->orderBy('starts_on')->get();
        $this->assertSame(['ended', 'active'], $rows->pluck('status')->all());
        $this->assertSame('2026-08-31', $rows[0]->ends_on->toDateString());
        $this->assertSame(1, $rows->where('status', 'active')->count());

        // not before the current contract started, and fixed term needs an end date
        $this->actAs($this->hr)->post(route('hr.contracts.store', $staff->id), ['starts_on' => '2026-02-01', 'basic_salary' => 1, 'contract_type' => 'permanent'])->assertSessionHas('error', 'err.hr.contractOrder');
        $this->actAs($this->hr)->post(route('hr.contracts.store', $staff->id), ['starts_on' => '2026-10-01', 'basic_salary' => 1, 'contract_type' => 'fixed_term'])->assertSessionHas('error', 'err.hr.contractEnd');
    }

    public function test_salary_is_hidden_from_hr_viewers_without_payroll_rights(): void
    {
        $staff = $this->staffRecord();
        $this->contract($staff, 4321);
        $viewer = $this->user('teacher');
        $viewer->givePermissionTo('hr.view');

        $this->actAs($viewer)->get(route('hr.staff.show', $staff->id))->assertOk()->assertInertia(fn ($p) => $p->where('contracts.0.basic_salary', null));
        $this->actAs($this->hr)->get(route('hr.staff.show', $staff->id))->assertOk()->assertInertia(fn ($p) => $p->where('contracts.0.basic_salary', 4321));
    }

    // ------------------------------------------------------------------ documents

    public function test_documents_are_private_logged_and_limited_to_hr_managers(): void
    {
        Storage::fake('local');
        $staff = $this->staffRecord();

        $file = UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf');
        $this->actAs($this->head)->post(route('hr.documents.store', $staff->id), ['type' => 'contract', 'title' => 'Signed', 'file' => $file])->assertForbidden();

        $file = UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf');
        $this->actAs($this->hr)->post(route('hr.documents.store', $staff->id), ['type' => 'contract', 'title' => 'Signed', 'file' => $file])->assertSessionHas('success', 'ok.hr.documentSaved');

        $doc = StaffDocument::query()->firstOrFail();
        Storage::disk('local')->assertExists($doc->path);
        $this->assertStringStartsWith('staff-docs/'.$this->school->id.'/', $doc->path);

        $this->actAs($this->teacher)->get(route('hr.documents.download', $doc->id))->assertForbidden();
        $this->actAs($this->head)->get(route('hr.documents.download', $doc->id))->assertForbidden();
        $this->actAs($this->hr)->get(route('hr.documents.download', $doc->id))->assertOk();
        $this->assertTrue(ActivityLog::query()->withoutGlobalScopes()->where('event', 'hr.document.downloaded')->exists());

        // other file types are refused
        $bad = UploadedFile::fake()->create('run.php', 5, 'text/x-php');
        $this->actAs($this->hr)->post(route('hr.documents.store', $staff->id), ['type' => 'other', 'title' => 'Bad', 'file' => $bad])->assertSessionHasErrors('file');

        // another school cannot reach it
        $other = $this->otherSchool();
        $stranger = $this->user('hr_officer', $other);
        $this->actAs($stranger)->get(route('hr.documents.download', $doc->id))->assertNotFound();

        $this->actAs($this->hr)->delete(route('hr.documents.destroy', $doc->id))->assertSessionHas('success', 'ok.hr.documentRemoved');
        Storage::disk('local')->assertMissing($doc->path);
    }

    // ------------------------------------------------------------------ performance

    public function test_performance_reviews_need_their_own_permission(): void
    {
        $staff = $this->staffRecord();
        $body = ['period_label' => '2026 T1', 'rating' => 4, 'strengths' => 'Punctual'];

        $this->actAs($this->teacher)->post(route('hr.performance.store', $staff->id), $body)->assertForbidden();
        $this->actAs($this->hr)->post(route('hr.performance.store', $staff->id), $body)->assertSessionHas('success', 'ok.hr.performanceSaved');
        $this->actAs($this->hr)->post(route('hr.performance.store', $staff->id), ['rating' => 9, 'period_label' => 'x'])->assertSessionHasErrors('rating');

        $viewer = $this->user('teacher');
        $viewer->givePermissionTo('hr.view');
        $this->actAs($viewer)->get(route('hr.staff.show', $staff->id))->assertOk()->assertInertia(fn ($p) => $p->where('performance', null));
        $this->actAs($this->hr)->get(route('hr.staff.show', $staff->id))->assertOk()->assertInertia(fn ($p) => $p->has('performance', 1));
    }

    // ------------------------------------------------------------------ leave

    public function test_leave_rejects_overlaps_bad_dates_and_inactive_staff(): void
    {
        $staff = $this->staffRecord();
        $post = fn (array $o = []) => $this->actAs($this->hr)->post(route('hr.leave.store'), $o + ['staff_id' => $staff->id, 'leave_type' => 'annual', 'starts_on' => '2026-12-01', 'ends_on' => '2026-12-05']);

        $post()->assertSessionHas('success', 'ok.hr.leaveRecorded');
        $this->assertSame(5, (int) \DB::table('staff_leave_requests')->value('days'));
        $post(['starts_on' => '2026-12-04', 'ends_on' => '2026-12-08'])->assertSessionHas('error', 'err.hr.overlap');
        $post(['starts_on' => '2026-12-20', 'ends_on' => '2026-12-18'])->assertSessionHasErrors('ends_on');
        $post(['starts_on' => '2026-12-20', 'ends_on' => '2026-12-21', 'days' => 3])->assertSessionHas('error', 'err.hr.leaveDays');
        $post(['leave_type' => 'bogus', 'starts_on' => '2027-01-10', 'ends_on' => '2027-01-11'])->assertSessionHasErrors('leave_type');

        $staff->update(['status' => 'inactive']);
        $post(['starts_on' => '2027-02-01', 'ends_on' => '2027-02-02'])->assertSessionHas('error', 'err.hr.staffInactive');
        $this->assertSame(1, \DB::table('staff_leave_requests')->count());
    }

    public function test_yearly_leave_limits_apply_only_when_the_school_sets_them(): void
    {
        $staff = $this->staffRecord();
        $post = fn (string $from, string $to) => $this->actAs($this->hr)->post(route('hr.leave.store'), ['staff_id' => $staff->id, 'leave_type' => 'annual', 'starts_on' => $from, 'ends_on' => $to]);

        // no limit configured: a long absence is accepted
        $post('2026-03-01', '2026-03-30')->assertSessionHas('success');

        $this->setSchoolSettings(['ops' => ['hr' => ['leave_annual_days' => 35]]]);
        $this->school->refresh();
        $post('2026-06-01', '2026-06-05')->assertSessionHas('success');             // 30 + 5 = 35, exactly at the limit
        $post('2026-08-01', '2026-08-01')->assertSessionHas('error', 'err.hr.entitlement'); // 36 > 35
        $post('2027-01-05', '2027-01-06')->assertSessionHas('success');             // a new year starts a new count
    }

    public function test_nobody_decides_their_own_leave_and_rejection_needs_a_reason(): void
    {
        Notification::fake();
        $staff = $this->staffRecord('Self', $this->hr);
        $leave = $this->leaveFor($staff, '2026-12-01', '2026-12-02', 'pending', $this->hr);

        $this->actAs($this->hr)->post(route('hr.leave.review', $leave->id), ['status' => 'approved'])->assertSessionHas('error', 'err.hr.selfReview');
        $this->assertSame('pending', $leave->fresh()->status);

        $this->actAs($this->admin)->post(route('hr.leave.review', $leave->id), ['status' => 'rejected'])->assertSessionHas('error', 'err.hr.noteRequired');
        $this->actAs($this->admin)->post(route('hr.leave.review', $leave->id), ['status' => 'rejected', 'note' => 'Exams week'])->assertSessionHas('success', 'ok.hr.leaveRejected');

        $fresh = $leave->fresh();
        $this->assertSame('rejected', $fresh->status);
        $this->assertSame($this->admin->id, (int) $fresh->reviewed_by);
        Notification::assertSentTo($this->hr, SchoolAnnouncement::class, fn ($n) => $n->title === 'Leave not approved');

        // a decision is final
        $this->actAs($this->admin)->post(route('hr.leave.review', $leave->id), ['status' => 'approved'])->assertSessionHas('error', 'err.hr.notPending');

        // unless the school allows it
        $this->setSchoolSettings(['ops' => ['hr' => ['self_approval' => true]]]);
        $this->school->refresh();
        $again = $this->leaveFor($staff, '2027-01-04', '2027-01-05', 'pending', $this->hr);
        $this->actAs($this->hr)->post(route('hr.leave.review', $again->id), ['status' => 'approved'])->assertSessionHas('success', 'ok.hr.leaveApproved');
    }

    public function test_leave_can_be_cancelled_until_it_starts(): void
    {
        $staff = $this->staffRecord();
        $future = $this->leaveFor($staff, now()->addDays(10)->toDateString(), now()->addDays(11)->toDateString(), 'approved');
        $started = $this->leaveFor($staff, now()->subDay()->toDateString(), now()->addDay()->toDateString(), 'approved');
        $rejected = $this->leaveFor($staff, now()->addDays(30)->toDateString(), now()->addDays(31)->toDateString(), 'rejected');

        $this->actAs($this->hr)->post(route('hr.leave.cancel', $future->id))->assertSessionHas('success', 'ok.hr.leaveCancelled');
        $this->actAs($this->hr)->post(route('hr.leave.cancel', $started->id))->assertSessionHas('error', 'err.hr.cannotCancel');
        $this->actAs($this->hr)->post(route('hr.leave.cancel', $rejected->id))->assertSessionHas('error', 'err.hr.cannotCancel');
        $this->assertSame('cancelled', $future->fresh()->status);
    }

    public function test_staff_apply_for_their_own_leave_and_cannot_touch_anyone_elses(): void
    {
        Notification::fake();
        $mine = $this->staffRecord('Mine', $this->teacher);
        $theirs = $this->staffRecord('Theirs', $this->user('teacher'));
        $theirLeave = $this->leaveFor($theirs, now()->addDays(20)->toDateString(), now()->addDays(21)->toDateString());

        $this->actAs($this->teacher)->get(route('my.leave.index'))->assertOk()->assertInertia(fn ($p) => $p->component('Hr/MyLeave')->where('linked', true));
        $this->actAs($this->teacher)->post(route('my.leave.store'), ['leave_type' => 'sick', 'starts_on' => now()->addDays(3)->toDateString(), 'ends_on' => now()->addDays(4)->toDateString(), 'reason' => 'Dentist'])
            ->assertSessionHas('success', 'ok.hr.leaveApplied');
        $leave = StaffLeaveRequest::query()->where('staff_id', $mine->id)->firstOrFail();
        $this->assertSame('pending', $leave->status);
        Notification::assertSentTo($this->hr, SchoolAnnouncement::class, fn ($n) => $n->title === 'Leave request');

        // the applicant cannot approve it, cancel someone else's, or backdate
        $this->actAs($this->teacher)->post(route('hr.leave.review', $leave->id), ['status' => 'approved'])->assertForbidden();
        $this->actAs($this->teacher)->post(route('my.leave.cancel', $theirLeave->id))->assertForbidden();
        $this->actAs($this->teacher)->post(route('my.leave.store'), ['leave_type' => 'annual', 'starts_on' => now()->subDays(2)->toDateString(), 'ends_on' => now()->toDateString()])->assertSessionHasErrors('starts_on');
        $this->actAs($this->teacher)->post(route('my.leave.cancel', $leave->id))->assertSessionHas('success', 'ok.hr.leaveCancelled');

        // a login with no staff record gets a clear message instead of a crash
        $loose = $this->user('teacher');
        $this->actAs($loose)->get(route('my.leave.index'))->assertOk()->assertInertia(fn ($p) => $p->where('linked', false));
        $this->actAs($loose)->post(route('my.leave.store'), ['leave_type' => 'annual', 'starts_on' => now()->addDay()->toDateString(), 'ends_on' => now()->addDays(2)->toDateString()])->assertSessionHas('error', 'err.hr.notLinked');
    }

    public function test_another_schools_leave_cannot_be_reviewed(): void
    {
        $other = $this->otherSchool();
        $foreignStaff = $this->inSchool(fn () => Staff::query()->create(['staff_number' => 'STF-F2', 'first_name' => 'Far', 'last_name' => 'Away', 'status' => 'active']), $other);
        $foreign = $this->inSchool(fn () => StaffLeaveRequest::query()->create(['staff_id' => $foreignStaff->id, 'leave_type' => 'annual', 'starts_on' => '2026-12-01', 'ends_on' => '2026-12-02', 'days' => 2, 'status' => 'pending']), $other);

        $this->actAs($this->admin)->post(route('hr.leave.review', $foreign->id), ['status' => 'approved'])->assertNotFound();
        $this->assertSame('pending', StaffLeaveRequest::query()->withoutGlobalScopes()->find($foreign->id)->status);
    }

    // ------------------------------------------------------------------ payroll

    public function test_payroll_builds_lines_from_contracts_and_components_with_half_up_percentages(): void
    {
        $a = $this->staffRecord('Aa');
        $b = $this->staffRecord('Bb');
        $this->contract($a, 1005);
        $this->contract($b, 2000);
        $this->payComp(['name' => 'Transport', 'code' => 'TRN', 'value' => 150]);
        $this->payComp(['name' => 'Pension', 'code' => 'PEN', 'kind' => 'deduction', 'calc' => 'percent_of_basic', 'value' => 10]);
        $housing = $this->payComp(['name' => 'Housing', 'code' => 'HSE', 'applies_to' => 'assigned', 'value' => 300]);

        // Housing applies to Bb only, with a personal value
        $this->actAs($this->hr)->put(route('hr.components.assign', $housing->id), ['assignments' => [['staff_id' => $b->id, 'value_override' => 400]]])->assertSessionHas('success', 'ok.hr.componentAssigned');

        $this->draftRun()->assertRedirect()->assertSessionHas('success', 'ok.hr.payrollCreated');
        $run = $this->runModel();
        $ia = PayrollItem::query()->where('payroll_run_id', $run->id)->where('staff_id', $a->id)->firstOrFail();
        $ib = PayrollItem::query()->where('payroll_run_id', $run->id)->where('staff_id', $b->id)->firstOrFail();

        // 10% of 1005 = 100.5 -> 101 (half up)
        $this->assertSame([1005, 150, 101, 1155, 1054], [(int) $ia->basic_salary, (int) $ia->allowances, (int) $ia->deductions, (int) $ia->gross_pay, (int) $ia->net_pay]);
        $this->assertSame([2000, 550, 200, 2550, 2350], [(int) $ib->basic_salary, (int) $ib->allowances, (int) $ib->deductions, (int) $ib->gross_pay, (int) $ib->net_pay]);
        $this->assertSame(3705, (int) $run->gross_total);
        $this->assertSame(3404, (int) $run->net_total);
        $this->assertSame('draft', $run->status);
        $this->assertContains('HSE', collect($ib->lines)->pluck('code')->all());
        $this->assertNotContains('HSE', collect($ia->lines)->pluck('code')->all());
    }

    public function test_the_legacy_default_basic_is_used_only_for_staff_without_a_contract(): void
    {
        $this->staffRecord('NoContract');
        $withContract = $this->staffRecord('Has');
        $this->contract($withContract, 800);

        $this->actAs($this->hr)->post(route('hr.payroll.store'), ['period_label' => '2026-10', 'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'basic_salary' => 500])->assertRedirect();
        $items = PayrollItem::query()->orderBy('basic_salary')->pluck('basic_salary')->map(fn ($v) => (int) $v)->all();
        $this->assertSame([500, 800], $items);
    }

    public function test_payroll_refuses_duplicates_overlaps_empty_runs_and_negative_pay(): void
    {
        $this->draftRun()->assertSessionHas('error', 'err.hr.nothingToPay');
        $this->assertSame(0, \DB::table('payroll_runs')->count());

        $s = $this->staffRecord();
        $this->contract($s, 1000);
        $this->draftRun()->assertSessionHas('success');
        $this->draftRun('2026-10', '2026-11-01', '2026-11-30')->assertSessionHas('error', 'err.hr.runExists');
        $this->draftRun('2026-10b', '2026-10-15', '2026-11-15')->assertSessionHas('error', 'err.hr.periodOverlap');
        $this->draftRun('2026-12', '2026-12-31', '2026-12-01')->assertSessionHasErrors('period_end');

        // a deduction bigger than the pay it comes from stops the whole run (nothing half-created)
        $this->payComp(['name' => 'Loan', 'code' => 'LN', 'kind' => 'deduction', 'value' => 5000]);
        $this->draftRun('2026-11', '2026-11-01', '2026-11-30')->assertSessionHas('error', 'err.hr.negativeNet');
        $this->assertSame(1, \DB::table('payroll_runs')->count());
        $this->assertSame(1, \DB::table('payroll_items')->count());
    }

    public function test_component_rules_are_validated_and_tenant_scoped(): void
    {
        $post = fn (array $o) => $this->actAs($this->hr)->post(route('hr.components.store'), $o + ['name' => 'X', 'code' => 'X1', 'kind' => 'allowance', 'calc' => 'fixed', 'value' => 10, 'applies_to' => 'all']);

        $post([])->assertSessionHas('success', 'ok.hr.componentSaved');
        $post(['name' => 'Dup'])->assertSessionHasErrors('code');
        $post(['code' => 'P1', 'calc' => 'percent_of_basic', 'value' => 150])->assertSessionHas('error', 'err.hr.componentPercent');
        $post(['code' => 'bad code!'])->assertSessionHasErrors('code');
        $this->actAs($this->teacher)->post(route('hr.components.store'), ['name' => 'X', 'code' => 'Z', 'kind' => 'allowance', 'calc' => 'fixed', 'value' => 1, 'applies_to' => 'all'])->assertForbidden();

        // the same code is fine in another school
        $other = $this->otherSchool();
        $this->actAs($this->user('hr_officer', $other))->post(route('hr.components.store'), ['name' => 'X', 'code' => 'X1', 'kind' => 'allowance', 'calc' => 'fixed', 'value' => 10, 'applies_to' => 'all'])->assertSessionHas('success');

        // staff lists only make sense for "assigned" components, and only for this school's staff
        $all = $this->payComp(['code' => 'ALL']);
        $this->actAs($this->hr)->put(route('hr.components.assign', $all->id), ['assignments' => []])->assertSessionHas('error', 'err.hr.componentAll');
        $assigned = $this->payComp(['code' => 'ASG', 'applies_to' => 'assigned']);
        $foreign = $this->inSchool(fn () => Staff::query()->create(['staff_number' => 'STF-F3', 'first_name' => 'Far', 'last_name' => 'Away', 'status' => 'active']), $other);
        $this->actAs($this->hr)->put(route('hr.components.assign', $assigned->id), ['assignments' => [['staff_id' => $foreign->id]]])->assertSessionHasErrors('assignments.0.staff_id');
    }

    public function test_payroll_needs_a_second_approver_and_a_payment_reference(): void
    {
        $s = $this->staffRecord();
        $this->contract($s, 1000);

        $this->draftRun('2026-10', '2026-10-01', '2026-10-31', $this->hr)->assertSessionHas('success');
        $run = $this->runModel();

        // the HR officer has no approval right; the head teacher approves
        $this->actAs($this->hr)->post(route('hr.payroll.post', $run->id))->assertForbidden();
        $this->actAs($this->teacher)->post(route('hr.payroll.post', $run->id))->assertForbidden();
        $this->actAs($this->head)->post(route('hr.payroll.pay', $run->id), ['payment_reference' => 'X'])->assertForbidden();

        // paying before approval is refused
        $this->actAs($this->hr)->post(route('hr.payroll.pay', $run->id), ['payment_reference' => 'TRF-1'])->assertSessionHas('error', 'err.hr.notPosted');

        $this->actAs($this->head)->post(route('hr.payroll.post', $run->id))->assertSessionHas('success', 'ok.hr.payrollPosted');
        $this->assertSame('posted', $run->fresh()->status);
        $this->assertSame($this->head->id, (int) $run->fresh()->posted_by);

        // an approved run is final
        $this->actAs($this->head)->post(route('hr.payroll.post', $run->id))->assertSessionHas('error', 'err.hr.notDraft');
        $this->actAs($this->hr)->delete(route('hr.payroll.destroy', $run->id))->assertSessionHas('error', 'err.hr.notDraft');

        $this->actAs($this->hr)->post(route('hr.payroll.pay', $run->id), ['payment_reference' => ''])->assertSessionHasErrors('payment_reference');
        $this->actAs($this->hr)->post(route('hr.payroll.pay', $run->id), ['payment_reference' => 'TRF-1', 'paid_on' => now()->toDateString()])->assertSessionHas('success', 'ok.hr.payrollPaid');
        $this->assertSame('paid', $run->fresh()->status);
        $this->assertSame('TRF-1', $run->fresh()->payment_reference);

        foreach (['hr.payroll.created', 'hr.payroll.posted', 'hr.payroll.paid'] as $event) {
            $this->assertTrue(ActivityLog::query()->withoutGlobalScopes()->where('event', $event)->exists(), $event);
        }
    }

    public function test_the_person_who_created_a_run_cannot_approve_it_unless_the_school_allows_it(): void
    {
        $s = $this->staffRecord();
        $this->contract($s, 1000);

        $this->draftRun('2026-10', '2026-10-01', '2026-10-31', $this->admin)->assertSessionHas('success');
        $run = $this->runModel();
        $this->actAs($this->admin)->post(route('hr.payroll.post', $run->id))->assertSessionHas('error', 'err.hr.selfApprovePayroll');
        $this->assertSame('draft', $run->fresh()->status);

        $this->setSchoolSettings(['ops' => ['payroll' => ['self_approval' => true]]]);
        $this->school->refresh();
        $this->actAs($this->admin)->post(route('hr.payroll.post', $run->id))->assertSessionHas('success', 'ok.hr.payrollPosted');
    }

    public function test_a_draft_can_be_discarded_and_an_approved_run_keeps_its_amounts(): void
    {
        $s = $this->staffRecord();
        $this->contract($s, 1000);
        $this->draftRun()->assertSessionHas('success');
        $run = $this->runModel();

        $this->actAs($this->hr)->delete(route('hr.payroll.destroy', $run->id))->assertRedirect(route('hr.payroll.index'))->assertSessionHas('success', 'ok.hr.payrollDiscarded');
        $this->assertSame(0, \DB::table('payroll_runs')->count());
        $this->assertSame(0, \DB::table('payroll_items')->count());

        // approved amounts do not move when pay rules change later
        $this->draftRun()->assertSessionHas('success');
        $run = $this->runModel();
        $this->actAs($this->head)->post(route('hr.payroll.post', $run->id))->assertSessionHas('success');
        $this->payComp(['name' => 'Bonus', 'code' => 'BON', 'value' => 999]);
        $this->inSchool(fn () => StaffContract::query()->where('staff_id', $s->id)->update(['basic_salary' => 9999]));
        $this->assertSame(1000, (int) PayrollItem::query()->where('payroll_run_id', $run->id)->value('net_pay'));
    }

    public function test_payroll_figures_are_for_payroll_roles_only_and_export_is_audited(): void
    {
        $s = $this->staffRecord();
        $this->contract($s, 1000);
        $this->draftRun()->assertSessionHas('success');
        $run = $this->runModel();

        $this->actAs($this->teacher)->get(route('hr.payroll.show', $run->id))->assertForbidden();
        $viewer = $this->user('teacher');
        $viewer->givePermissionTo('hr.view');
        $this->actAs($viewer)->get(route('hr.payroll.show', $run->id))->assertForbidden();
        $this->actAs($viewer)->get(route('hr.payroll.export', $run->id))->assertForbidden();

        $this->actAs($this->head)->get(route('hr.payroll.show', $run->id))->assertOk()->assertInertia(fn ($p) => $p->component('Hr/PayrollShow')->has('items', 1));
        $csv = $this->actAs($this->hr)->get(route('hr.payroll.export', $run->id));
        $csv->assertOk();
        $this->assertStringContainsString('Staff no.', $csv->streamedContent());
        $this->assertTrue(ActivityLog::query()->withoutGlobalScopes()->where('event', 'hr.payroll.exported')->exists());

        // another school's run does not exist for us
        $other = $this->otherSchool();
        $this->actAs($this->user('hr_officer', $other))->get(route('hr.payroll.show', $run->id))->assertNotFound();
    }

    // ------------------------------------------------------------------ payslips

    public function test_payslips_are_visible_to_their_owner_only_after_approval_and_never_to_colleagues(): void
    {
        $mine = $this->staffRecord('Mine', $this->teacher, ['bank_account' => '9876543210']);
        $colleagueUser = $this->user('teacher');
        $colleague = $this->staffRecord('Col', $colleagueUser);
        $this->contract($mine, 1000);
        $this->contract($colleague, 2000);
        $this->draftRun()->assertSessionHas('success');
        $run = $this->runModel();
        $myItem = PayrollItem::query()->where('staff_id', $mine->id)->firstOrFail();
        $theirItem = PayrollItem::query()->where('staff_id', $colleague->id)->firstOrFail();

        // draft: the owner sees nothing yet; payroll roles can preview it (marked as a draft)
        $this->actAs($this->teacher)->get(route('hr.payslips.show', $myItem->id))->assertNotFound();
        $this->actAs($this->teacher)->get(route('my.payslips.index'))->assertOk()->assertInertia(fn ($p) => $p->component('Hr/MyPayslips')->has('payslips', 0));
        $this->actAs($this->hr)->get(route('hr.payslips.show', $myItem->id))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->actAs($this->head)->post(route('hr.payroll.post', $run->id))->assertSessionHas('success');

        $res = $this->actAs($this->teacher)->get(route('hr.payslips.show', $myItem->id));
        $res->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $res->getContent());
        $this->assertStringContainsString('no-store', (string) $res->headers->get('Cache-Control'));
        $this->actAs($this->teacher)->get(route('hr.payslips.show', $theirItem->id))->assertForbidden();
        $this->actAs($this->teacher)->get(route('my.payslips.index'))->assertOk()->assertInertia(fn ($p) => $p->has('payslips', 1)->where('payslips.0.net', 1000));

        // parents and other schools never see one
        $this->actAs($this->user('parent'))->get(route('hr.payslips.show', $myItem->id))->assertForbidden();
        $other = $this->otherSchool();
        $this->actAs($this->user('hr_officer', $other))->get(route('hr.payslips.show', $myItem->id))->assertNotFound();
    }

    public function test_the_payslip_never_carries_the_full_bank_account(): void
    {
        $s = $this->staffRecord('Bank', $this->teacher, ['bank_account' => '9876543210']);
        $this->contract($s, 1000);
        $this->draftRun()->assertSessionHas('success');
        $item = PayrollItem::query()->firstOrFail();

        $html = view('hr.payslip', [
            'item' => $item, 'run' => $item->run, 'staff' => $s, 'school' => $this->school, 'maskedAccount' => '••••••3210', 'watermark' => null,
        ])->render();
        $this->assertStringContainsString('3210', $html);
        $this->assertStringNotContainsString('9876543210', $html);

        $pdf = app(\App\Services\Hr\PayslipPdf::class)->render($item);
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    // ------------------------------------------------------------------ setup

    public function test_positions_and_rules_are_managed_by_hr_only(): void
    {
        $this->actAs($this->teacher)->post(route('hr.setup.positions.store'), ['title' => 'Bursar'])->assertForbidden();
        $this->actAs($this->head)->post(route('hr.setup.positions.store'), ['title' => 'Bursar'])->assertForbidden();

        $this->actAs($this->hr)->post(route('hr.setup.positions.store'), ['title' => 'Bursar'])->assertSessionHas('success', 'ok.hr.positionSaved');
        $this->actAs($this->hr)->post(route('hr.setup.positions.store'), ['title' => 'Bursar'])->assertSessionHas('error', 'err.hr.positionExists');

        $position = \App\Models\JobPosition::query()->firstOrFail();
        $this->actAs($this->hr)->put(route('hr.setup.positions.update', $position->id), ['title' => 'Bursar', 'is_active' => false])->assertSessionHas('success');
        $this->assertFalse((bool) $position->fresh()->is_active);

        $this->actAs($this->hr)->put(route('hr.setup.settings'), ['leave_annual_days' => 21, 'leave_sick_days' => null, 'leave_self_approval' => false, 'payroll_self_approval' => false])
            ->assertSessionHas('success', 'ok.hr.settingsSaved');
        $this->school->refresh();
        $this->assertSame(21, (int) data_get($this->school->settings, 'ops.hr.leave_annual_days'));
        $this->actAs($this->hr)->put(route('hr.setup.settings'), ['leave_annual_days' => 999, 'leave_self_approval' => false, 'payroll_self_approval' => false])->assertSessionHasErrors('leave_annual_days');
    }

    public function test_someone_without_payroll_rights_cannot_change_payroll_approval_rules(): void
    {
        $manager = $this->user('teacher');
        $manager->givePermissionTo(['hr.view', 'hr.manage']);

        $this->actAs($manager)->put(route('hr.setup.settings'), ['leave_annual_days' => null, 'leave_sick_days' => null, 'leave_self_approval' => false, 'payroll_self_approval' => true])->assertSessionHas('success');
        $this->school->refresh();
        $this->assertFalse((bool) data_get($this->school->settings, 'ops.payroll.self_approval', false));
    }

    // ------------------------------------------------------------------ navigation and compatibility

    public function test_hr_tabs_follow_permissions_and_the_teacher_menu_offers_my_hr(): void
    {
        $this->actAs($this->hr)->get(route('hr.index'))->assertInertia(fn ($p) => $p->where('hrTabs', ['overview', 'staff', 'leave', 'payroll', 'setup', 'myLeave', 'myPayslips']));
        $this->actAs($this->head)->get(route('hr.index'))->assertInertia(fn ($p) => $p->where('hrTabs', ['overview', 'staff', 'leave', 'payroll', 'myLeave', 'myPayslips']));

        $this->actAs($this->teacher)->get(route('my.leave.index'))->assertOk()->assertInertia(fn ($p) => $p->where('hrTabs', ['myLeave', 'myPayslips'])->where('nav', fn ($nav) => collect($nav)->contains('key', 'myHr')));
        $this->actAs($this->hr)->get(route('hr.index'))->assertInertia(fn ($p) => $p->where('nav', fn ($nav) => ! collect($nav)->contains('key', 'myHr') && collect($nav)->contains('key', 'hr')));
    }

    public function test_original_route_names_still_exist(): void
    {
        foreach (['hr.index', 'hr.leave.store', 'hr.leave.review', 'hr.payroll.store', 'hr.payroll.post'] as $name) {
            $this->assertTrue(\Route::has($name), $name);
        }
    }
}
