<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\FeeAdjustment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\StudentFee;
use App\Notifications\SchoolAnnouncement;
use App\Services\Finance\AdjustmentService;
use App\Services\Finance\InvoiceService;
use App\Services\Ops\OpsSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/** Phase 9A: fee structures, invoices, adjustments, payments, receipts, reconciliation and reports. */
class Phase9FinanceTest extends Phase9TestCase
{
    private function inv(int $id): Invoice
    {
        return $this->inSchool(fn () => Invoice::query()->findOrFail($id));
    }

    private function pay($user, $invoice, array $data)
    {
        return $this->actAs($user)->post(route('fees.payments.store'), ['invoice_id' => $invoice->id] + $data);
    }

    // ------------------------------------------------------------------ setup and permissions

    public function test_only_fee_managers_can_configure_the_schedule(): void
    {
        $cat = $this->category();
        $body = ['fee_category_id' => $cat->id, 'academic_year_id' => $this->year->id, 'amount' => 500, 'is_optional' => false];

        foreach ([$this->teacher, $this->user('parent'), $this->user('accountant')] as $u) {
            $this->actAs($u)->post(route('fees.setup.structures.store'), $body)->assertForbidden();
        }

        $this->actAs($this->finance)->post(route('fees.setup.structures.store'), $body)->assertRedirect()->assertSessionHas('success', 'ok.fin.structureSaved');
        $this->assertDatabaseHas('fee_structures', ['school_id' => $this->school->id, 'amount' => 500]);
        $this->assertTrue(ActivityLog::query()->withoutGlobalScopes()->where('event', 'fin.structure.created')->exists());
    }

    public function test_structure_rejects_a_term_from_another_year_and_other_schools_ids(): void
    {
        $other = $this->otherSchool();
        $foreignCat = $this->inSchool(fn () => \App\Models\FeeCategory::query()->create(['name' => 'Foreign']), $other);
        $year2 = \App\Models\AcademicYear::query()->create(['name' => '2027/2028', 'starts_on' => '2027-09-01', 'ends_on' => '2028-07-31', 'is_current' => false]);

        $this->actAs($this->finance)->post(route('fees.setup.structures.store'), [
            'fee_category_id' => $foreignCat->id, 'academic_year_id' => $this->year->id, 'amount' => 100, 'is_optional' => false,
        ])->assertSessionHasErrors('fee_category_id');

        $this->actAs($this->finance)->post(route('fees.setup.structures.store'), [
            'fee_category_id' => $this->category()->id, 'academic_year_id' => $year2->id, 'term_id' => $this->term1->id, 'amount' => 100, 'is_optional' => false,
        ])->assertSessionHas('error', 'err.fin.termYear');

        $this->assertSame(0, \DB::table('fee_structures')->count());
    }

    public function test_finance_rules_are_saved_and_a_bad_prefix_is_refused(): void
    {
        $put = fn (array $over) => $this->actAs($this->finance)->put(route('fees.setup.settings'), $over + [
            'invoice_prefix' => 'FEE', 'due_days' => 14, 'allow_installments' => true, 'installments_max' => 3, 'parent_submissions' => true,
            'self_approval' => false, 'reminder_days' => 5, 'reminder_max' => 2, 'receipt_footer' => 'Thank you',
        ]);

        $put(['invoice_prefix' => 'BAD!'])->assertSessionHas('error', 'err.fin.prefix');
        $put([])->assertSessionHas('success', 'ok.fin.settingsSaved');

        $this->school->refresh();
        $cfg = $this->inSchool(fn () => app(OpsSettings::class)->group('finance'));
        $this->assertSame('FEE', $cfg['invoice_prefix']);
        $this->assertTrue($cfg['allow_installments']);
        $this->assertSame(14, $cfg['due_days']);
    }

    // ------------------------------------------------------------------ generation

    public function test_invoice_is_built_from_the_schedule_for_the_learners_class_only(): void
    {
        $s = $this->student();
        $this->structure(800);                                                      // everyone, term 1
        $this->structure(200, ['school_class_id' => $this->class->id, 'category' => $this->category('Lab')]);
        $this->structure(900, ['school_class_id' => $this->otherClass->id, 'category' => $this->category('Other class')]);
        $this->structure(50, ['term_id' => $this->term2->id, 'category' => $this->category('Term 2 only')]);
        $this->structure(75, ['is_optional' => true, 'category' => $this->category('Trip')]);

        $this->actAs($this->finance)->post(route('fees.invoices.generate'), [
            'academic_year_id' => $this->year->id, 'term_id' => $this->term1->id, 'student_id' => $s->id,
        ])->assertRedirect();

        $inv = $this->inSchool(fn () => Invoice::query()->with('items')->where('student_id', $s->id)->firstOrFail());
        $this->assertSame(1000, (int) $inv->total_amount);
        $this->assertSame('issued', $inv->status);
        $this->assertMatchesRegularExpression('/^INV-\d{4}-\d{5}$/', $inv->invoice_number);
        $this->assertCount(2, $inv->items);
        $this->assertSame('structure', $inv->source);
        $this->assertNotNull($inv->due_on);
    }

    public function test_chosen_optional_lines_are_billed(): void
    {
        $s = $this->student();
        $this->structure(800);
        $trip = $this->structure(75, ['is_optional' => true, 'category' => $this->category('Trip')]);

        $this->actAs($this->finance)->post(route('fees.invoices.generate'), [
            'academic_year_id' => $this->year->id, 'term_id' => $this->term1->id, 'student_id' => $s->id, 'optional_ids' => [$trip->id],
        ])->assertRedirect();

        $this->assertSame(875, (int) $this->inSchool(fn () => Invoice::query()->where('student_id', $s->id)->value('total_amount')));
    }

    public function test_student_specific_charge_is_billed_once_and_released_when_the_invoice_is_cancelled(): void
    {
        $s = $this->student();
        $this->structure(800);
        $fee = StudentFee::query()->create(['student_id' => $s->id, 'description' => 'Lost textbook', 'amount' => 120, 'status' => 'pending']);

        $invoice = $this->inSchool(fn () => app(InvoiceService::class)->generate($s, $this->year, $this->term1, $this->admin));
        $this->assertSame(920, (int) $invoice->total_amount);
        $this->assertSame('invoiced', $fee->fresh()->status);

        $this->actAs($this->head)->post(route('fees.invoices.cancel', $invoice), ['reason' => 'Raised by mistake'])->assertSessionHas('success', 'ok.fin.invoiceCancelled');

        $this->assertSame('cancelled', $this->inv($invoice->id)->status);
        $this->assertSame('pending', $fee->fresh()->status);
        $this->assertNull($fee->fresh()->invoice_id);
    }

    public function test_a_term_is_not_invoiced_twice_and_nothing_to_bill_is_reported(): void
    {
        $s = $this->student();
        $empty = $this->student();
        $this->structure(800, ['school_class_id' => $this->class->id]);
        $svc = app(InvoiceService::class);

        $this->inSchool(fn () => $svc->generate($s, $this->year, $this->term1, $this->admin));
        $this->actAs($this->finance)->post(route('fees.invoices.generate'), ['academic_year_id' => $this->year->id, 'term_id' => $this->term1->id, 'student_id' => $s->id])
            ->assertSessionHas('error', 'err.fin.invoiceExists');

        $this->actAs($this->finance)->post(route('fees.invoices.generate'), ['academic_year_id' => $this->year->id, 'term_id' => $this->term2->id, 'student_id' => $empty->id])
            ->assertSessionHas('error', 'err.fin.nothingToBill');
    }

    public function test_class_generation_creates_skips_and_leaves_withdrawn_learners_alone(): void
    {
        $a = $this->student();
        $b = $this->student();
        $gone = $this->student();
        $gone->update(['status' => 'withdrawn']);
        $this->structure(500, ['school_class_id' => $this->class->id]);
        $this->inSchool(fn () => app(InvoiceService::class)->generate($b, $this->year, $this->term1, $this->admin));

        $this->actAs($this->finance)->post(route('fees.invoices.generate'), [
            'academic_year_id' => $this->year->id, 'term_id' => $this->term1->id, 'school_class_id' => $this->class->id,
        ])->assertSessionHas('count', 1)->assertSessionHas('failedCount', 1);

        $this->assertSame(1, \DB::table('invoices')->where('student_id', $a->id)->count());
        $this->assertSame(0, \DB::table('invoices')->where('student_id', $gone->id)->count());
    }

    public function test_generation_needs_exactly_one_target(): void
    {
        $this->actAs($this->finance)->post(route('fees.invoices.generate'), ['academic_year_id' => $this->year->id])->assertSessionHas('error', 'err.fin.target');
    }

    public function test_manual_invoice_validates_lines_and_audits(): void
    {
        $s = $this->student();

        $this->actAs($this->finance)->post(route('fees.invoices.store'), ['student_id' => $s->id, 'lines' => [['description' => '', 'amount' => 0]]])
            ->assertSessionHasErrors();

        $this->actAs($this->finance)->post(route('fees.invoices.store'), ['student_id' => $s->id, 'lines' => [['description' => 'Uniform', 'amount' => 300], ['description' => 'Badge', 'amount' => 20]]])
            ->assertRedirect();

        $this->assertSame(320, (int) \DB::table('invoices')->where('student_id', $s->id)->value('total_amount'));
        $this->assertTrue(ActivityLog::query()->withoutGlobalScopes()->where('event', 'fin.invoice.created')->exists());
    }

    // ------------------------------------------------------------------ adjustments

    private function adjust(array $over = [])
    {
        return $this->actAs($this->finance)->post(route('fees.adjustments.store'), $over + [
            'student_id' => $this->student()->id, 'kind' => 'discount', 'basis' => 'percent', 'value' => 10, 'reason' => 'Sibling discount',
        ]);
    }

    public function test_adjustments_need_a_reason_and_valid_values(): void
    {
        $this->adjust(['reason' => ''])->assertSessionHasErrors('reason');
        $this->adjust(['value' => 150])->assertSessionHas('error', 'err.fin.adjustmentValue');
        $this->adjust(['kind' => 'gift'])->assertSessionHasErrors('kind');
        $this->adjust()->assertSessionHas('success', 'ok.fin.adjustmentRequested');
    }

    public function test_the_requester_cannot_approve_their_own_adjustment_unless_the_school_allows_it(): void
    {
        $a = FeeAdjustment::query()->create(['student_id' => $this->student()->id, 'kind' => 'waiver', 'basis' => 'percent', 'value' => 100, 'reason' => 'Hardship', 'status' => 'pending', 'requested_by' => $this->head->id]);

        $this->actAs($this->head)->post(route('fees.adjustments.approve', $a))->assertSessionHas('error', 'err.fin.selfApprove');
        $this->assertSame('pending', $a->fresh()->status);

        $this->actAs($this->finance)->post(route('fees.adjustments.approve', $a))->assertForbidden();   // finance holds no fees.approve

        $this->actAs($this->admin)->post(route('fees.adjustments.approve', $a), ['note' => 'Verified'])->assertSessionHas('success', 'ok.fin.adjustmentApproved');
        $this->assertSame('approved', $a->fresh()->status);

        $b = FeeAdjustment::query()->create(['student_id' => $this->student()->id, 'kind' => 'discount', 'basis' => 'fixed', 'value' => 10, 'reason' => 'x', 'status' => 'pending', 'requested_by' => $this->head->id]);
        $this->setSchoolSettings(['ops' => ['finance' => ['self_approval' => true]]]);
        $this->actAs($this->head)->post(route('fees.adjustments.approve', $b))->assertSessionHas('success', 'ok.fin.adjustmentApproved');
    }

    public function test_only_approved_adjustments_reduce_the_next_invoice(): void
    {
        $s = $this->student();
        $this->structure(1000);
        $pending = FeeAdjustment::query()->create(['student_id' => $s->id, 'kind' => 'discount', 'basis' => 'percent', 'value' => 50, 'reason' => 'x', 'status' => 'pending', 'requested_by' => $this->head->id]);
        FeeAdjustment::query()->create(['student_id' => $s->id, 'kind' => 'scholarship', 'basis' => 'percent', 'value' => 25, 'reason' => 'Merit', 'status' => 'approved', 'requested_by' => $this->head->id]);
        FeeAdjustment::query()->create(['student_id' => $s->id, 'kind' => 'discount', 'basis' => 'fixed', 'value' => 100, 'reason' => 'Staff child', 'status' => 'approved', 'requested_by' => $this->head->id]);

        $inv = $this->inSchool(fn () => app(InvoiceService::class)->generate($s, $this->year, $this->term1, $this->admin));

        $this->assertSame(1000, (int) $inv->subtotal);
        $this->assertSame(350, (int) $inv->adjustments_total);       // 250 + 100
        $this->assertSame(650, (int) $inv->total_amount);
        $this->assertSame(['fee', 'scholarship', 'discount'], $inv->items()->orderBy('id')->pluck('kind')->all());
        $this->assertSame('pending', $pending->fresh()->status);
    }

    public function test_a_full_waiver_makes_a_zero_invoice_and_reductions_never_exceed_the_charge(): void
    {
        $s = $this->student();
        $this->structure(400);
        FeeAdjustment::query()->create(['student_id' => $s->id, 'kind' => 'waiver', 'basis' => 'percent', 'value' => 100, 'reason' => 'Hardship', 'status' => 'approved', 'requested_by' => $this->head->id]);
        FeeAdjustment::query()->create(['student_id' => $s->id, 'kind' => 'discount', 'basis' => 'fixed', 'value' => 999, 'reason' => 'Too much', 'status' => 'approved', 'requested_by' => $this->head->id]);

        $inv = $this->inSchool(fn () => app(InvoiceService::class)->generate($s, $this->year, $this->term1, $this->admin));

        $this->assertSame(0, (int) $inv->total_amount);
        $this->assertSame(400, (int) $inv->adjustments_total);
        $this->assertSame('paid', $inv->status);
    }

    public function test_category_scoped_adjustment_only_touches_that_category(): void
    {
        $s = $this->student();
        $tuition = $this->category();
        $bus = $this->category('Bus');
        $this->structure(1000, ['category' => $tuition]);
        $this->structure(200, ['category' => $bus]);
        FeeAdjustment::query()->create(['student_id' => $s->id, 'kind' => 'discount', 'basis' => 'percent', 'value' => 50, 'fee_category_id' => $bus->id, 'reason' => 'x', 'status' => 'approved', 'requested_by' => $this->head->id]);

        $inv = $this->inSchool(fn () => app(InvoiceService::class)->generate($s, $this->year, $this->term1, $this->admin));

        $this->assertSame(100, (int) $inv->adjustments_total);
        $this->assertSame(1100, (int) $inv->total_amount);
    }

    // ------------------------------------------------------------------ recording payments

    public function test_cash_payment_updates_the_invoice_and_issues_a_receipt(): void
    {
        $s = $this->student();
        $inv = $this->invoiceFor($s, 1000);

        $this->pay($this->finance, $inv, ['amount' => 400, 'method' => 'cash'])->assertSessionHas('success', 'ok.fin.paymentRecorded');
        $fresh = $this->inv($inv->id);
        $this->assertSame('partial', $fresh->status);
        $this->assertSame(400, (int) $fresh->amount_paid);

        $this->pay($this->finance, $inv, ['amount' => 600, 'method' => 'cash'])->assertSessionHas('success');
        $fresh = $this->inv($inv->id);
        $this->assertSame('paid', $fresh->status);

        $receipts = \DB::table('payments')->where('invoice_id', $inv->id)->orderBy('id')->pluck('receipt_number')->all();
        $this->assertMatchesRegularExpression('/^RCT-\d{4}-\d{5}$/', $receipts[0]);
        $this->assertNotSame($receipts[0], $receipts[1]);
        $this->assertSame(2, ActivityLog::query()->withoutGlobalScopes()->where('event', 'fin.payment.recorded')->count());
    }

    public function test_payments_cannot_overpay_be_zero_or_hit_settled_or_cancelled_invoices(): void
    {
        $s = $this->student();
        $inv = $this->invoiceFor($s, 500);

        $this->pay($this->finance, $inv, ['amount' => 501, 'method' => 'cash'])->assertSessionHas('error', 'err.fin.overpay');
        $this->pay($this->finance, $inv, ['amount' => 0, 'method' => 'cash'])->assertSessionHasErrors('amount');
        $this->pay($this->finance, $inv, ['amount' => 500, 'method' => 'cash'])->assertSessionHas('success');
        $this->pay($this->finance, $inv, ['amount' => 1, 'method' => 'cash'])->assertSessionHas('error', 'err.fin.invoiceSettled');

        $dead = $this->invoiceFor($s, 100, ['status' => 'cancelled']);
        $this->pay($this->finance, $dead, ['amount' => 10, 'method' => 'cash'])->assertSessionHas('error', 'err.fin.invoiceCancelled');
        $this->assertSame(500, (int) \DB::table('payments')->sum('amount'));
    }

    public function test_referenced_methods_need_a_unique_reference(): void
    {
        $inv = $this->invoiceFor($this->student(), 900);

        $this->pay($this->finance, $inv, ['amount' => 100, 'method' => 'wave'])->assertSessionHas('error', 'err.fin.referenceRequired');
        $this->pay($this->finance, $inv, ['amount' => 100, 'method' => 'wave', 'reference' => 'W-1'])->assertSessionHas('success');
        $this->pay($this->finance, $inv, ['amount' => 100, 'method' => 'wave', 'reference' => 'W-1'])->assertSessionHas('error', 'err.fin.referenceUsed');
        $this->pay($this->finance, $inv, ['amount' => 100, 'method' => 'bank_transfer', 'reference' => 'W-1'])->assertSessionHas('success');   // another channel
        $this->pay($this->finance, $inv, ['amount' => 100, 'method' => 'bitcoin', 'reference' => 'x'])->assertSessionHasErrors('method');
    }

    public function test_a_payment_against_another_schools_invoice_is_refused(): void
    {
        $other = $this->otherSchool();
        $foreignStudent = $this->student(null, false, 'Zed', $other);
        $foreign = $this->inSchool(fn () => Invoice::query()->create([
            'student_id' => $foreignStudent->id, 'invoice_number' => 'INV-B-1', 'status' => 'issued', 'total_amount' => 500, 'amount_paid' => 0, 'subtotal' => 500, 'issued_at' => now(),
        ]), $other);

        $this->actAs($this->finance)->post(route('fees.payments.store'), ['invoice_id' => $foreign->id, 'amount' => 100, 'method' => 'cash'])->assertSessionHasErrors('invoice_id');
        $this->assertSame(0, \DB::table('payments')->count());
    }

    // ------------------------------------------------------------------ parent submissions

    public function test_a_parent_reports_a_payment_which_only_counts_after_staff_confirm_it(): void
    {
        Notification::fake();
        $s = $this->student();
        $g = $this->guardian([$s]);
        $this->payMethod('wave');
        $inv = $this->invoiceFor($s, 1000);

        $this->actAs($g->user)->post(route('fees.submissions.store', $inv), ['amount' => 300, 'method' => 'wave', 'reference' => 'WAVE-77', 'payer_name' => 'Mariama'])
            ->assertSessionHas('success', 'ok.fin.paymentSubmitted');

        $fresh = $this->inv($inv->id);
        $this->assertSame(0, (int) $fresh->amount_paid);                       // pending money is not money
        $this->assertSame('issued', $fresh->status);
        $payment = Payment::query()->withoutGlobalScopes()->where('invoice_id', $inv->id)->firstOrFail();
        $this->assertSame('pending', $payment->status);
        $this->assertStringStartsWith('PEND-', $payment->receipt_number);

        $this->actAs($g->user)->post(route('fees.payments.confirm', $payment))->assertForbidden();

        $this->actAs($this->finance)->post(route('fees.payments.confirm', $payment))->assertSessionHas('success', 'ok.fin.paymentConfirmed');
        $this->assertSame(300, (int) $this->inv($inv->id)->amount_paid);
        $this->assertStringStartsWith('RCT-', $payment->fresh()->receipt_number);
        Notification::assertSentTo($g->user, SchoolAnnouncement::class, fn ($n) => $n->title === 'Payment received');

        $this->actAs($this->finance)->post(route('fees.payments.confirm', $payment))->assertSessionHas('error', 'err.fin.notPending');
    }

    public function test_a_rejected_submission_never_counts_and_tells_the_parent(): void
    {
        Notification::fake();
        $s = $this->student();
        $g = $this->guardian([$s]);
        $this->payMethod('bank_transfer', 'School bank');
        $inv = $this->invoiceFor($s, 500);

        $this->actAs($g->user)->post(route('fees.submissions.store', $inv), ['amount' => 500, 'method' => 'bank_transfer', 'reference' => 'TRX-1'])->assertSessionHas('success');
        $payment = Payment::query()->withoutGlobalScopes()->firstOrFail();

        $this->actAs($this->finance)->post(route('fees.payments.reject', $payment), [])->assertSessionHasErrors('note');
        $this->actAs($this->finance)->post(route('fees.payments.reject', $payment), ['note' => 'No such transfer'])->assertSessionHas('success', 'ok.fin.paymentRejected');

        $this->assertSame('rejected', $payment->fresh()->status);
        $this->assertSame(0, (int) $this->inv($inv->id)->amount_paid);
        Notification::assertSentTo($g->user, SchoolAnnouncement::class, fn ($n) => $n->title === 'Payment not accepted');

        // the reference of a rejected claim can be used again
        $this->actAs($g->user)->post(route('fees.submissions.store', $inv), ['amount' => 500, 'method' => 'bank_transfer', 'reference' => 'TRX-1'])->assertSessionHas('success');
    }

    public function test_family_submission_rules(): void
    {
        $s = $this->student();
        $stranger = $this->student();
        $g = $this->guardian([$s]);
        $mine = $this->invoiceFor($s, 600);
        $notMine = $this->invoiceFor($stranger, 600);
        $post = fn ($inv, $over = []) => $this->actAs($g->user)->post(route('fees.submissions.store', $inv), $over + ['amount' => 100, 'method' => 'wave', 'reference' => 'R'.random_int(1, 99999)]);

        $post($notMine)->assertForbidden();
        $post($mine)->assertSessionHas('error', 'err.fin.methodOff');          // school has not configured Wave
        $this->payMethod('wave');
        $post($mine, ['method' => 'cash'])->assertSessionHasErrors('method');
        $post($mine, ['amount' => 601])->assertSessionHas('error', 'err.fin.overpay');
        $post($mine, ['amount' => 400])->assertSessionHas('success');
        $post($mine, ['amount' => 300])->assertSessionHas('error', 'err.fin.overpay');   // 400 pending + 300 > 600
        $post($mine, ['amount' => 200])->assertSessionHas('success');

        $this->setSchoolSettings(['ops' => ['finance' => ['parent_submissions' => false]]]);
        $g->user->unsetRelation('school');
        $post($mine, ['amount' => 1])->assertSessionHas('error', 'err.fin.submissionsOff');

        $this->actAs($this->teacher)->post(route('fees.submissions.store', $mine), ['amount' => 1, 'method' => 'wave', 'reference' => 'T'])->assertForbidden();
    }

    public function test_proof_upload_is_private_to_the_submitter_and_verifiers(): void
    {
        Storage::fake('local');
        $s = $this->student();
        $g = $this->guardian([$s]);
        $other = $this->guardian([$this->student()]);
        $this->payMethod('wave');
        $inv = $this->invoiceFor($s, 500);

        $this->actAs($g->user)->post(route('fees.submissions.store', $inv), [
            'amount' => 100, 'method' => 'wave', 'reference' => 'P1', 'evidence' => UploadedFile::fake()->image('slip.jpg'),
        ])->assertSessionHas('success');
        $payment = Payment::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertNotNull($payment->evidence_path);

        $this->actAs($g->user)->get(route('fees.payments.evidence', $payment))->assertOk();
        $this->actAs($this->finance)->get(route('fees.payments.evidence', $payment))->assertOk();
        $this->actAs($other->user)->get(route('fees.payments.evidence', $payment))->assertNotFound();
        $this->actAs($this->teacher)->get(route('fees.payments.evidence', $payment))->assertForbidden();

        $this->actAs($g->user)->post(route('fees.submissions.store', $inv), [
            'amount' => 50, 'method' => 'wave', 'reference' => 'P2', 'evidence' => UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream'),
        ])->assertSessionHasErrors('evidence');
    }

    public function test_unauthorised_people_cannot_record_or_verify(): void
    {
        $s = $this->student();
        $inv = $this->invoiceFor($s, 200);
        $g = $this->guardian([$s]);

        foreach ([$this->teacher, $g->user, $this->user('student')] as $u) {
            $this->pay($u, $inv, ['amount' => 50, 'method' => 'cash'])->assertForbidden();
        }
        $this->actAs($this->user('librarian'))->get(route('fees.payments.index'))->assertForbidden();
    }

    // ------------------------------------------------------------------ voiding, cancelling, instalments

    public function test_voiding_needs_approval_rights_a_reason_and_recalculates(): void
    {
        $inv = $this->invoiceFor($this->student(), 500);
        $this->pay($this->finance, $inv, ['amount' => 500, 'method' => 'cash']);
        $payment = Payment::query()->withoutGlobalScopes()->firstOrFail();

        $this->actAs($this->finance)->post(route('fees.payments.void', $payment), ['reason' => 'Wrong invoice'])->assertForbidden();
        $this->actAs($this->head)->post(route('fees.payments.void', $payment), [])->assertSessionHasErrors('reason');
        $this->actAs($this->head)->post(route('fees.payments.void', $payment), ['reason' => 'Wrong invoice'])->assertSessionHas('success', 'ok.fin.paymentVoided');

        $fresh = $this->inv($inv->id);
        $this->assertSame('issued', $fresh->status);
        $this->assertSame(0, (int) $fresh->amount_paid);
        $this->assertSame('voided', $payment->fresh()->status);
        $this->actAs($this->head)->post(route('fees.payments.void', $payment), ['reason' => 'again'])->assertSessionHas('error', 'err.fin.notConfirmed');
    }

    public function test_a_reconciled_payment_cannot_be_voided_and_a_paid_invoice_cannot_be_cancelled(): void
    {
        $inv = $this->invoiceFor($this->student(), 300);
        $this->pay($this->finance, $inv, ['amount' => 300, 'method' => 'cash']);
        $payment = Payment::query()->withoutGlobalScopes()->firstOrFail();

        $this->actAs($this->head)->post(route('fees.invoices.cancel', $inv), ['reason' => 'oops'])->assertSessionHas('error', 'err.fin.hasPayments');

        $payment->update(['is_reconciled' => true]);
        $this->actAs($this->head)->post(route('fees.payments.void', $payment), ['reason' => 'late'])->assertSessionHas('error', 'err.fin.reconciled');
    }

    public function test_cancelling_is_blocked_while_a_claim_is_pending(): void
    {
        $s = $this->student();
        $g = $this->guardian([$s]);
        $this->payMethod('wave');
        $inv = $this->invoiceFor($s, 300);
        $this->actAs($g->user)->post(route('fees.submissions.store', $inv), ['amount' => 100, 'method' => 'wave', 'reference' => 'Z1']);

        $this->actAs($this->head)->post(route('fees.invoices.cancel', $inv), ['reason' => 'x'])->assertSessionHas('error', 'err.fin.hasPending');
        $this->actAs($this->finance)->post(route('fees.invoices.cancel', $inv), ['reason' => 'x'])->assertForbidden();
    }

    public function test_instalment_plans_follow_the_school_setting_and_split_exactly(): void
    {
        $inv = $this->invoiceFor($this->student(), 1000);
        $post = fn ($over = []) => $this->actAs($this->finance)->post(route('fees.invoices.installments', $inv), $over + ['count' => 3, 'first_due_on' => now()->addDays(5)->toDateString()]);

        $post()->assertSessionHas('error', 'err.fin.installmentsOff');

        $this->setSchoolSettings(['ops' => ['finance' => ['allow_installments' => true, 'installments_max' => 4]]]);
        $post(['count' => 5])->assertSessionHas('error', 'err.fin.installmentCount');
        $post(['count' => 13])->assertSessionHasErrors('count');
        $post(['first_due_on' => now()->subDay()->toDateString()])->assertSessionHas('error', 'err.fin.installmentPast');
        $post()->assertSessionHas('success', 'ok.fin.installmentsSet');

        $plan = \DB::table('invoice_installments')->where('invoice_id', $inv->id)->orderBy('number')->pluck('amount')->all();
        $this->assertSame([333, 333, 334], $plan);

        // payments fill the earliest instalment first
        $this->pay($this->finance, $inv, ['amount' => 400, 'method' => 'cash']);
        $paid = \DB::table('invoice_installments')->where('invoice_id', $inv->id)->orderBy('number')->pluck('amount_paid')->all();
        $this->assertSame([333, 67, 0], $paid);

        $post()->assertSessionHas('error', 'err.fin.installmentLocked');          // once money has arrived the plan is frozen
    }

    // ------------------------------------------------------------------ reconciliation

    public function test_reconciliation_covers_confirmed_payments_only_and_records_the_difference(): void
    {
        Notification::fake();
        $s = $this->student();
        $g = $this->guardian([$s]);
        $this->payMethod('wave');
        $inv = $this->invoiceFor($s, 2000);
        $this->pay($this->finance, $inv, ['amount' => 700, 'method' => 'cash']);
        $this->pay($this->finance, $inv, ['amount' => 300, 'method' => 'wave', 'reference' => 'W9']);
        $this->actAs($g->user)->post(route('fees.submissions.store', $inv), ['amount' => 100, 'method' => 'wave', 'reference' => 'PEND1']);

        $ids = Payment::query()->withoutGlobalScopes()->pluck('id')->all();
        $body = ['period_starts_on' => now()->subDay()->toDateString(), 'period_ends_on' => now()->toDateString(), 'payment_ids' => $ids, 'statement_amount' => 950];

        $this->actAs($this->finance)->post(route('fees.reconciliation.store'), $body)->assertSessionHas('success', 'ok.fin.reconciled');

        $run = \DB::table('payment_reconciliations')->first();
        $this->assertSame(1000, (int) $run->collected_amount);        // the pending 100 was left out
        $this->assertSame(950, (int) $run->expected_amount);
        $this->assertSame(50, (int) $run->variance_amount);
        $this->assertSame('variance', $run->status);
        $this->assertSame(2, \DB::table('payments')->where('is_reconciled', true)->count());
        $this->assertSame(0, \DB::table('payments')->where('status', 'pending')->where('is_reconciled', true)->count());

        $this->actAs($this->finance)->post(route('fees.reconciliation.store'), $body)->assertSessionHas('error', 'err.fin.nothingToReconcile');
    }

    public function test_reconciliation_rejects_payments_outside_the_period_and_other_schools_payments(): void
    {
        $inv = $this->invoiceFor($this->student(), 500);
        $this->pay($this->finance, $inv, ['amount' => 100, 'method' => 'cash']);
        $id = Payment::query()->withoutGlobalScopes()->value('id');

        $this->actAs($this->finance)->post(route('fees.reconciliation.store'), [
            'period_starts_on' => now()->subDays(10)->toDateString(), 'period_ends_on' => now()->subDays(5)->toDateString(), 'payment_ids' => [$id],
        ])->assertSessionHas('error', 'err.fin.outsidePeriod');

        $other = $this->otherSchool();
        $os = $this->student(null, false, 'Zed', $other);
        $oi = $this->inSchool(fn () => Invoice::query()->create(['student_id' => $os->id, 'invoice_number' => 'B1', 'status' => 'issued', 'total_amount' => 100, 'subtotal' => 100, 'amount_paid' => 0, 'issued_at' => now()]), $other);
        $op = $this->inSchool(fn () => Payment::query()->create(['invoice_id' => $oi->id, 'student_id' => $os->id, 'receipt_number' => 'RB1', 'amount' => 100, 'method' => 'cash', 'paid_at' => now(), 'status' => 'confirmed']), $other);

        $this->actAs($this->finance)->post(route('fees.reconciliation.store'), [
            'period_starts_on' => now()->subDay()->toDateString(), 'period_ends_on' => now()->toDateString(), 'payment_ids' => [$op->id],
        ])->assertSessionHasErrors('payment_ids.0');
        $this->assertFalse((bool) \DB::table('payments')->where('id', $op->id)->value('is_reconciled'));
    }

    // ------------------------------------------------------------------ receipts, isolation, family view

    public function test_receipts_are_pdfs_for_the_family_and_staff_only(): void
    {
        $s = $this->student();
        $mine = $this->guardian([$s]);
        $other = $this->guardian([$this->student()]);
        $inv = $this->invoiceFor($s, 500);
        $this->pay($this->finance, $inv, ['amount' => 500, 'method' => 'cash']);
        $payment = Payment::query()->withoutGlobalScopes()->firstOrFail();

        $res = $this->actAs($mine->user)->get(route('fees.payments.receipt', $payment))->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
        $this->actAs($this->finance)->get(route('fees.payments.receipt', $payment))->assertOk();
        $this->actAs($other->user)->get(route('fees.payments.receipt', $payment))->assertForbidden();
    }

    public function test_one_school_never_sees_anothers_invoices_or_payments(): void
    {
        $other = $this->otherSchool();
        $os = $this->student(null, false, 'Zed', $other);
        $oi = $this->inSchool(fn () => Invoice::query()->create(['student_id' => $os->id, 'invoice_number' => 'INV-B-SECRET', 'status' => 'issued', 'total_amount' => 500, 'subtotal' => 500, 'amount_paid' => 0, 'issued_at' => now()]), $other);
        $op = $this->inSchool(fn () => Payment::query()->create(['invoice_id' => $oi->id, 'student_id' => $os->id, 'receipt_number' => 'RB-SECRET', 'amount' => 100, 'method' => 'cash', 'paid_at' => now(), 'status' => 'confirmed']), $other);

        $this->actAs($this->admin)->get(route('fees.invoices.show', $oi))->assertNotFound();
        $this->actAs($this->admin)->get(route('fees.payments.receipt', $op))->assertNotFound();
        $this->actAs($this->admin)->post(route('fees.payments.void', $op), ['reason' => 'x'])->assertNotFound();
        $this->actAs($this->admin)->post(route('fees.invoices.cancel', $oi), ['reason' => 'x'])->assertNotFound();

        foreach (['fees.index', 'fees.invoices.index', 'fees.payments.index', 'fees.reports.index'] as $name) {
            $this->assertStringNotContainsString('INV-B-SECRET', $this->actAs($this->admin)->get(route($name, $name === 'fees.payments.index' ? ['status' => 'confirmed'] : []))->getContent());
        }
    }

    public function test_families_see_only_their_own_children_and_can_open_only_their_invoices(): void
    {
        $mine = $this->student();
        $theirs = $this->student();
        $g = $this->guardian([$mine]);
        $own = $this->invoiceFor($mine, 700, ['invoice_number' => 'INV-MINE']);
        $foreign = $this->invoiceFor($theirs, 800, ['invoice_number' => 'INV-THEIRS']);

        $page = $this->actAs($g->user)->get(route('fees.index'))->assertOk();
        $page->assertInertia(fn ($p) => $p->component('Fees/Index')->where('mode', 'family')->has('invoices', 1)->where('invoices.0.invoice_number', 'INV-MINE'));
        $this->assertStringNotContainsString('INV-THEIRS', $page->getContent());

        $this->actAs($g->user)->get(route('fees.invoices.show', $own))->assertOk();
        $this->actAs($g->user)->get(route('fees.invoices.show', $foreign))->assertForbidden();
        $this->actAs($g->user)->get(route('fees.invoices.index'))->assertForbidden();
        $this->actAs($g->user)->get(route('fees.reports.index'))->assertForbidden();
        $this->actAs($g->user)->get(route('fees.setup.index'))->assertForbidden();
        $this->actAs($g->user)->get(route('fees.statements.show', $theirs))->assertForbidden();
        $this->actAs($g->user)->get(route('fees.statements.show', $mine))->assertOk();
    }

    public function test_a_learner_sees_only_their_own_fees(): void
    {
        $login = $this->student(null, true);
        $other = $this->student();
        $this->invoiceFor($login, 100, ['invoice_number' => 'INV-OWN']);
        $this->invoiceFor($other, 100, ['invoice_number' => 'INV-OTHER']);

        $page = $this->actAs($login->user)->get(route('fees.index'))->assertOk();
        $this->assertStringContainsString('INV-OWN', $page->getContent());
        $this->assertStringNotContainsString('INV-OTHER', $page->getContent());
    }

    // ------------------------------------------------------------------ reports

    public function test_reports_summarise_billing_collection_and_ageing(): void
    {
        $s1 = $this->student();
        $s2 = $this->student($this->otherClass);
        $a = $this->invoiceFor($s1, 1000, ['due_on' => now()->subDays(45)->toDateString()]);
        $b = $this->invoiceFor($s2, 500);
        $this->pay($this->finance, $a, ['amount' => 400, 'method' => 'cash']);
        $this->pay($this->finance, $b, ['amount' => 500, 'method' => 'wave', 'reference' => 'RW1']);

        $res = $this->actAs($this->finance)->get(route('fees.reports.index'))->assertOk();
        $res->assertInertia(fn ($p) => $p->component('Fees/Reports')
            ->where('summary.billed', 1500)->where('summary.collected', 900)->where('summary.outstanding', 600)->where('summary.overdue', 600)
            ->where('outstanding.buckets.d31_60', 600)->where('outstanding.buckets.current', 0)
            ->has('byMethod', 2));
    }

    public function test_csv_exports_neutralise_formulas_and_need_the_export_permission(): void
    {
        $s = $this->student();
        $s->update(['first_name' => '=HYPERLINK("http://evil")']);
        $inv = $this->invoiceFor($s, 500);
        $this->pay($this->finance, $inv, ['amount' => 100, 'method' => 'cash']);

        $csv = $this->actAs($this->finance)->get(route('fees.reports.export', ['type' => 'outstanding']))->assertOk()->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);

        $payments = $this->actAs($this->finance)->get(route('fees.reports.export', ['type' => 'payments']))->assertOk()->streamedContent();
        $this->assertStringContainsString('RCT-', $payments);

        $this->actAs($this->user('headteacher'))->get(route('fees.reports.export', ['type' => 'outstanding']))->assertOk();   // headteacher can export
        $this->actAs($this->teacher)->get(route('fees.reports.export', ['type' => 'outstanding']))->assertForbidden();
        $this->actAs($this->finance)->get(route('fees.reports.export', ['type' => 'secrets']))->assertNotFound();
    }

    // ------------------------------------------------------------------ reminders and legacy compatibility

    public function test_overdue_reminders_are_throttled_and_capped(): void
    {
        Notification::fake();
        $s = $this->student();
        $g = $this->guardian([$s]);
        $inv = $this->invoiceFor($s, 500, ['due_on' => now()->subDays(3)->toDateString()]);
        $this->invoiceFor($this->student(), 500);   // not overdue: ignored

        $this->artisan('schoolos:fees:remind')->assertSuccessful();
        Notification::assertSentToTimes($g->user, SchoolAnnouncement::class, 1);
        $this->artisan('schoolos:fees:remind')->assertSuccessful();     // too soon
        Notification::assertSentToTimes($g->user, SchoolAnnouncement::class, 1);

        $this->inSchool(fn () => Invoice::query()->whereKey($inv->id)->update(['reminded_at' => now()->subDays(8)]));
        $this->artisan('schoolos:fees:remind')->assertSuccessful();
        Notification::assertSentToTimes($g->user, SchoolAnnouncement::class, 2);

        $this->inSchool(fn () => Invoice::query()->whereKey($inv->id)->update(['reminded_at' => now()->subDays(30), 'reminder_count' => 3]));
        $this->artisan('schoolos:fees:remind')->assertSuccessful();     // reached the cap
        Notification::assertSentToTimes($g->user, SchoolAnnouncement::class, 2);
    }

    public function test_pre_phase9_rows_keep_working(): void
    {
        // a payment row written by the old code has no status column value of its own: the database default confirms it
        $s = $this->student();
        $inv = $this->invoiceFor($s, 100, ['status' => 'paid', 'amount_paid' => 100]);
        \DB::table('payments')->insert(['school_id' => $this->school->id, 'invoice_id' => $inv->id, 'student_id' => $s->id, 'receipt_number' => 'RCPT-OLD', 'amount' => 100, 'method' => 'cash', 'paid_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame('confirmed', \DB::table('payments')->where('receipt_number', 'RCPT-OLD')->value('status'));
        $this->actAs($this->finance)->get(route('fees.reports.index'))->assertInertia(fn ($p) => $p->where('summary.collected', 100));
    }

    public function test_the_service_audits_sensitive_money_events_without_secrets(): void
    {
        $inv = $this->invoiceFor($this->student(), 100);
        $this->pay($this->finance, $inv, ['amount' => 100, 'method' => 'cash']);
        $payment = Payment::query()->withoutGlobalScopes()->firstOrFail();
        $this->actAs($this->head)->post(route('fees.payments.void', $payment), ['reason' => 'Test void']);

        $events = ActivityLog::query()->withoutGlobalScopes()->pluck('event')->all();
        $this->assertContains('fin.payment.recorded', $events);
        $this->assertContains('fin.payment.voided', $events);
        $log = ActivityLog::query()->withoutGlobalScopes()->where('event', 'fin.payment.voided')->first();
        $this->assertSame($this->head->id, $log->user_id);
        $this->assertSame($this->school->id, $log->school_id);
    }

    public function test_adjustment_service_validates_directly(): void
    {
        $svc = app(AdjustmentService::class);
        $this->actingAs($this->finance);
        $this->expectExceptionMessage('err.fin.adjustment');
        $svc->request($this->finance, $this->student()->id, 'bribe', 'percent', 5, null, null, null, 'x');
    }
}
