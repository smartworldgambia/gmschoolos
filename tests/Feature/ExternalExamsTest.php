<?php

namespace Tests\Feature;

use App\Exceptions\AcademicRuleException;
use App\Models\CertificateRecord;
use App\Models\ExternalCandidate;
use App\Models\ExternalExamSession;
use App\Models\ExternalResult;
use App\Models\ExternalResultChange;
use App\Models\ResultCollection;
use App\Models\User;
use App\Notifications\CollectionReminder;
use App\Services\ExternalExams\CollectionService;
use App\Services\ExternalExams\ExternalExamService;
use App\Services\ExternalExams\ExternalResultImporter;
use App\Services\Records\RecordsSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/** Phase 6 - GABECE / WASSCE registration, results, collection register and certificate tracking. */
class ExternalExamsTest extends Phase6TestCase
{
    protected ExternalExamService $svc;

    protected ExternalExamSession $wassce;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(ExternalExamService::class);
        $this->wassce = $this->svc->createSession(['type' => 'wassce', 'name' => 'WASSCE 2026', 'exam_year' => 2026]);
    }

    protected function candidate(array $subjectIds = [], ?int $grade = 12): ExternalCandidate
    {
        return $this->svc->register($this->admin, $this->wassce, $this->studentAt($grade), $subjectIds);
    }

    protected function refuses(string $key, callable $fn): void
    {
        try {
            $fn();
            $this->fail("Expected refusal {$key}");
        } catch (AcademicRuleException $e) {
            $this->assertSame($key, $e->getMessage());
        }
    }

    // ------------------------------------------------------------------ registration & identifiers

    public function test_registration_never_fabricates_an_official_index_number(): void
    {
        $c = $this->candidate();

        $this->assertNull($c->official_index_number, 'no official index is ever generated');
        $this->assertMatchesRegularExpression('/^IC-WASSCE-2026-\d{5}$/', $c->internal_reference);
        $this->assertSame('registered', $c->status);
        $this->assertSame($c->student->student_number, $c->details['student_number']);

        $second = $this->candidate();
        $this->assertNotSame($c->internal_reference, $second->internal_reference, 'internal references are unique');
        $this->assertNull($second->official_index_number);
    }

    public function test_official_index_is_stored_exactly_as_supplied(): void
    {
        $c = $this->candidate();
        $supplied = 'WAEC/2026/0012-a'; // mixed case, punctuation: must survive byte for byte

        $this->svc->recordIndexNumber($this->admin, $c, $supplied);

        $this->assertSame($supplied, DB::table('external_candidates')->where('id', $c->id)->value('official_index_number'));
    }

    public function test_index_number_rejects_blank_padded_internal_and_duplicate_values(): void
    {
        $a = $this->candidate();
        $b = $this->candidate();

        $this->refuses('err.ext.indexBlank', fn () => $this->svc->recordIndexNumber($this->admin, $a, '   '));
        $this->refuses('err.ext.indexFormat', fn () => $this->svc->recordIndexNumber($this->admin, $a, ' 12345 '));
        $this->refuses('err.ext.indexIsInternal', fn () => $this->svc->recordIndexNumber($this->admin, $a, $a->internal_reference));
        $this->refuses('err.ext.indexIsInternal', fn () => $this->svc->recordIndexNumber($this->admin, $a, 'ic-wassce-2026-00099'));

        $this->svc->recordIndexNumber($this->admin, $a, 'ABC/123');
        $this->refuses('err.ext.indexDuplicate', fn () => $this->svc->recordIndexNumber($this->admin, $b, 'abc-123'));
        $this->assertNull($b->refresh()->official_index_number);
    }

    public function test_changing_an_index_number_needs_verify_permission_and_a_reason(): void
    {
        $c = $this->candidate();
        $officer = $this->user('records_officer');
        $this->svc->recordIndexNumber($officer, $c, 'ABC/123');

        $this->refuses('err.ext.indexChangeDenied', fn () => $this->svc->recordIndexNumber($officer, $c, 'ABC/124', 'typo'));
        $this->refuses('err.ext.reasonRequired', fn () => $this->svc->recordIndexNumber($this->admin, $c, 'ABC/124'));

        $this->svc->recordIndexNumber($this->admin, $c, 'ABC/124', 'Corrected from the WAEC schedule');
        $this->assertSame('ABC/124', $c->refresh()->official_index_number);
    }

    public function test_registration_enforces_session_window_grade_and_duplicates(): void
    {
        $this->refuses('err.ext.wrongGrade.wassce', fn () => $this->svc->register($this->admin, $this->wassce, $this->studentAt(7)));

        $first = $this->studentAt(12);
        $this->svc->register($this->admin, $this->wassce, $first);
        $this->refuses('err.ext.alreadyRegistered', fn () => $this->svc->register($this->admin, $this->wassce, $first));

        $this->svc->setSessionStatus($this->wassce, 'closed');
        $this->refuses('err.ext.sessionClosed', fn () => $this->svc->register($this->admin, $this->wassce, $this->studentAt(12)));

        $gabece = $this->svc->createSession(['type' => 'gabece', 'name' => 'GABECE 2026', 'exam_year' => 2026, 'registration_closes_on' => now()->subDay()->toDateString()]);
        $this->refuses('err.ext.windowClosed', fn () => $this->svc->register($this->admin, $gabece, $this->studentAt(9)));
    }

    public function test_gabece_is_for_grade_nine_and_unmapped_levels_are_not_guessed(): void
    {
        $gabece = $this->svc->createSession(['type' => 'gabece', 'name' => 'GABECE 2026', 'exam_year' => 2026]);
        $this->refuses('err.ext.wrongGrade.gabece', fn () => $this->svc->register($this->admin, $gabece, $this->studentAt(12)));
        $this->assertSame(9, $this->svc->register($this->admin, $gabece, $this->studentAt(9))->student->schoolClass->level->grade_number);

        // A level whose grade number is unknown is allowed through (an administrator maps it in Records settings).
        $level = \App\Models\Level::query()->create(['name' => 'Senior Form', 'code' => 'SF', 'sort_order' => 50]);
        $this->assertNull($level->grade_number);
        $class = \App\Models\SchoolClass::query()->create(['level_id' => $level->id, 'name' => 'SF-A', 'capacity' => 30]);
        $this->assertNotNull($this->svc->register($this->admin, $this->wassce, $this->studentAt(12, ['school_class_id' => $class->id])));
    }

    public function test_subjects_must_belong_to_the_exam_type_and_be_active(): void
    {
        $eng = $this->subject('wassce', 'ENG', 'English');
        $gab = $this->subject('gabece', 'ENG', 'English (GABECE)');

        $this->refuses('err.ext.subjectInvalid', fn () => $this->candidate([$gab->id]));
        $c = $this->candidate([$eng->id]);
        $this->assertSame([$eng->id], $c->subjectLinks()->pluck('subject_id')->all());
    }

    // ------------------------------------------------------------------ results: entry, verification, publication, correction

    public function test_result_entry_needs_a_registered_subject_and_an_allowed_grade(): void
    {
        $eng = $this->subject('wassce', 'ENG', 'English');
        $math = $this->subject('wassce', 'MATH', 'Mathematics');
        $c = $this->candidate([$eng->id]);

        $this->refuses('err.ext.subjectNotRegistered', fn () => $this->svc->enterResult($this->admin, $c, $math->id, 'B2'));
        $this->refuses('err.ext.gradeInvalid', fn () => $this->svc->enterResult($this->admin, $c, $eng->id, 'Z9'));

        $r = $this->svc->enterResult($this->admin, $c, $eng->id, 'B2');
        $this->assertSame('entered', $r->status);
    }

    public function test_results_cannot_be_published_before_verification_then_publish_after(): void
    {
        $eng = $this->subject('wassce', 'ENG', 'English');
        $c = $this->candidate([$eng->id]);
        $this->svc->enterResult($this->admin, $c, $eng->id, 'B2');

        $this->refuses('err.ext.unverified', fn () => $this->svc->publish($this->admin, $this->wassce));
        $this->assertSame('entered', ExternalResult::query()->first()->status);

        $this->svc->verify($this->admin, $c);
        $out = $this->svc->publish($this->admin, $this->wassce);

        $this->assertSame(1, $out['published']);
        $row = ExternalResult::query()->first();
        $this->assertSame('published', $row->status);
        $this->assertNotNull($row->published_at);
    }

    public function test_verification_and_publication_need_the_verify_permission(): void
    {
        $eng = $this->subject('wassce', 'ENG', 'English');
        $c = $this->candidate([$eng->id]);
        $examOfficer = $this->user('exam_officer');
        $this->svc->enterResult($examOfficer, $c, $eng->id, 'C5');

        $this->refuses('err.ext.permission', fn () => $this->svc->verify($examOfficer, $c));
        $this->actingAs($examOfficer)->post(route('external-exams.candidates.verify', $c))->assertForbidden();
        $this->actingAs($examOfficer)->post(route('external-exams.sessions.publish', $this->wassce))->assertForbidden();
        $this->assertSame('entered', ExternalResult::query()->first()->status);
    }

    public function test_correction_needs_a_reason_keeps_history_and_requires_republication(): void
    {
        $eng = $this->subject('wassce', 'ENG', 'English');
        $c = $this->candidate([$eng->id]);
        $res = $this->svc->enterResult($this->admin, $c, $eng->id, 'C5');
        $this->svc->verify($this->admin, $c);
        $this->svc->publish($this->admin, $this->wassce);

        $this->refuses('err.ext.locked', fn () => $this->svc->enterResult($this->admin, $c, $eng->id, 'A1'));
        $this->refuses('err.ext.reasonRequired', fn () => $this->svc->correct($this->admin, $res->refresh(), 'B3', ''));
        $this->refuses('err.ext.noChange', fn () => $this->svc->correct($this->admin, $res->refresh(), 'C5', 'same'));

        $this->svc->correct($this->admin, $res->refresh(), 'B3', 'Re-marked per WAEC amendment');

        $res->refresh();
        $this->assertSame('B3', $res->grade);
        $this->assertSame('verified', $res->status, 'a corrected result drops back to verified and must be re-published');
        $this->assertNull($res->published_at);

        $events = ExternalResultChange::query()->where('candidate_id', $c->id)->orderBy('id')->pluck('event')->all();
        $this->assertSame(['entered', 'verified', 'published', 'corrected'], $events);
        $corrected = ExternalResultChange::query()->where('event', 'corrected')->first();
        $this->assertSame(['C5', 'B3', 'Re-marked per WAEC amendment'], [$corrected->old_grade, $corrected->new_grade, $corrected->reason]);
    }

    public function test_result_history_is_append_only(): void
    {
        $eng = $this->subject('wassce', 'ENG', 'English');
        $c = $this->candidate([$eng->id]);
        $this->svc->enterResult($this->admin, $c, $eng->id, 'C5');
        $row = ExternalResultChange::query()->firstOrFail();

        $this->expectException(\LogicException::class);
        $row->update(['new_grade' => 'A1']);
    }

    // ------------------------------------------------------------------ import

    protected function csv(array $rows, array $header = ['official_index_number', 'internal_reference', 'student_number', 'subject_code', 'grade']): UploadedFile
    {
        $lines = [implode(',', $header)];
        foreach ($rows as $r) {
            $lines[] = implode(',', array_map(fn ($v) => '"'.$v.'"', $r));
        }

        return UploadedFile::fake()->createWithContent('results.csv', implode("\n", $lines));
    }

    public function test_import_matches_candidates_reports_every_problem_and_never_verifies(): void
    {
        Storage::fake('local');
        $eng = $this->subject('wassce', 'ENG', 'English');
        $math = $this->subject('wassce', 'MATH', 'Mathematics');
        $a = $this->candidate([$eng->id, $math->id]);
        $b = $this->candidate([$eng->id]);
        $c = $this->candidate([$eng->id]);
        $this->svc->recordIndexNumber($this->admin, $a, 'IDX/0001');

        $file = $this->csv([
            ['idx/0001', '', '', 'ENG', 'B2'],                                   // 2: matched by official index (normalised compare)
            ['', $b->internal_reference, '', 'ENG', 'C4'],                        // 3: matched by internal reference
            ['', '', $c->student->student_number, 'ENG', 'A1'],                   // 4: matched by student number
            ['NOPE/9', '', '', 'ENG', 'B2'],                                      // 5: unmatched
            ['', $b->internal_reference, '', 'PHYS', 'B2'],                       // 6: unknown subject
            ['', $b->internal_reference, '', 'ENG', 'Q1'],                        // 7: invalid grade
            ['IDX/0001', '', '', 'MATH', 'B2'],                                   // 8: ok (second subject for A)
            ['', $b->internal_reference, '', 'MATH', 'B2'],                       // 9: subject not registered for B
        ]);

        $batch = app(ExternalResultImporter::class)->import($this->admin, $this->wassce, $file);

        $this->assertSame('completed_with_errors', $batch->status);
        $this->assertSame([8, 4, 4], [$batch->total_rows, $batch->success_rows, $batch->failed_rows]);
        $errors = collect($batch->errors)->pluck('error', 'line')->all();
        $this->assertSame([5 => 'err.ext.importUnmatched', 6 => 'err.ext.importSubject', 7 => 'err.ext.gradeInvalid', 9 => 'err.ext.subjectNotRegistered'], $errors);

        $this->assertSame(4, ExternalResult::query()->count());
        $this->assertSame(['imported'], ExternalResult::query()->pluck('status')->unique()->values()->all(), 'imported rows are never auto-verified');
        $this->assertSame('import', ExternalResult::query()->first()->source);
    }

    public function test_import_never_overwrites_locked_results_and_rejects_bad_headers(): void
    {
        Storage::fake('local');
        $eng = $this->subject('wassce', 'ENG', 'English');
        $a = $this->candidate([$eng->id]);
        $this->svc->enterResult($this->admin, $a, $eng->id, 'C5');
        $this->svc->verify($this->admin, $a);

        $batch = app(ExternalResultImporter::class)->import($this->admin, $this->wassce, $this->csv([['', $a->internal_reference, '', 'ENG', 'A1']]));
        $this->assertSame('err.ext.locked', $batch->errors[0]['error']);
        $this->assertSame('C5', ExternalResult::query()->first()->grade);

        $this->refuses('err.ext.importHeaders', fn () => app(ExternalResultImporter::class)->import($this->admin, $this->wassce, $this->csv([['x']], ['foo'])));
    }

    public function test_import_is_scoped_to_the_session_and_the_school(): void
    {
        Storage::fake('local');
        $eng = $this->subject('wassce', 'ENG', 'English');
        $a = $this->candidate([$eng->id]);
        $gabece = $this->svc->createSession(['type' => 'gabece', 'name' => 'GABECE 2026', 'exam_year' => 2026]);

        // `a` is a WASSCE candidate: a GABECE import must not match it.
        $batch = app(ExternalResultImporter::class)->import($this->admin, $gabece, $this->csv([['', $a->internal_reference, '', 'ENG', 'B2']]));
        $this->assertSame('err.ext.importUnmatched', $batch->errors[0]['error']);
        $this->assertSame(0, ExternalResult::query()->count());
    }

    // ------------------------------------------------------------------ collection register & certificates

    public function test_collection_register_flow_and_validation(): void
    {
        $c = $this->candidate();
        $col = app(CollectionService::class);

        $this->refuses('err.col.notReceived', fn () => $col->collect($this->admin, $c, ['collected_on' => now()->toDateString(), 'collector_name' => 'X', 'acknowledgement' => 'signed']));

        $col->markReceived($this->admin, $c, now()->subDays(3)->toDateString());
        $this->refuses('err.col.collectorRequired', fn () => $col->collect($this->admin, $c, ['collected_on' => now()->toDateString(), 'collector_name' => '', 'acknowledgement' => '']));
        $this->refuses('err.col.beforeReceived', fn () => $col->collect($this->admin, $c, ['collected_on' => now()->subDays(10)->toDateString(), 'collector_name' => 'Binta', 'acknowledgement' => 'signed']));
        $this->refuses('err.col.dateFuture', fn () => $col->markReceived($this->admin, $c, now()->addDays(2)->toDateString()));

        $row = $col->collect($this->admin, $c, [
            'collected_on' => now()->toDateString(), 'collector_name' => 'Binta Jallow', 'collector_relationship' => 'mother',
            'collector_id_type' => 'national_id', 'collector_id_number' => 'A1234567', 'acknowledgement' => 'Signed in register p.4',
        ]);
        $this->assertSame('collected', $row->status);
        $this->assertSame($this->admin->id, $row->staff_user_id);
        $this->refuses('err.col.alreadyCollected', fn () => $col->markReceived($this->admin, $c, now()->toDateString()));

        // identity number is encrypted at rest, and only a masked tail reaches the browser
        $raw = DB::table('result_collections')->where('id', $row->id)->value('collector_id_number');
        $this->assertStringNotContainsString('A1234567', $raw);
        $this->actingAs($this->admin)->get(route('external-exams.collections.index', ['session' => $this->wassce->id]))->assertOk()
            ->assertDontSee('A1234567');
    }

    public function test_certificate_statuses_follow_the_expected_order(): void
    {
        $c = $this->candidate();
        $col = app(CollectionService::class);

        $this->refuses('err.cert.skipStep', fn () => $col->updateCertificate($this->admin, $c, 'available'));
        $this->assertSame('awaiting', $col->updateCertificate($this->admin, $c, 'awaiting')->status);
        $this->assertSame('received', $col->updateCertificate($this->admin, $c, 'received')->status);
        $this->refuses('err.cert.skipStep', fn () => $col->updateCertificate($this->admin, $c, 'collected'));
        $this->assertSame('available', $col->updateCertificate($this->admin, $c, 'available')->status);
        $this->assertSame('outstanding', $col->updateCertificate($this->admin, $c, 'outstanding')->status);
        $this->refuses('err.col.collectorRequired', fn () => $col->updateCertificate($this->admin, $c, 'collected', []));

        $done = $col->updateCertificate($this->admin, $c, 'collected', ['collector_name' => 'Awa', 'acknowledgement' => 'signed']);
        $this->assertSame('collected', $done->status);
        $this->refuses('err.cert.collectedLocked', fn () => $col->updateCertificate($this->admin, $c, 'available'));
        $this->refuses('err.cert.statusInvalid', fn () => $col->updateCertificate($this->admin, $c, 'lost'));
    }

    public function test_reminders_go_to_staff_respect_interval_and_cap_and_carry_no_student_data(): void
    {
        Notification::fake();
        $staff = $this->user('records_officer');
        $c = $this->candidate();
        $col = app(CollectionService::class);
        $col->markReceived($this->admin, $c, now()->subDays(20)->toDateString());
        $cert = $col->updateCertificate($this->admin, $c, 'received');
        $cert = $col->updateCertificate($this->admin, $c, 'available', ['available_on' => now()->subDays(30)->toDateString()]);

        $this->assertSame(1, $col->sendReminders($this->school));
        Notification::assertSentTo($staff, CollectionReminder::class, function (CollectionReminder $n) {
            return $n->results === 1 && $n->certificates === 1 && ! str_contains(json_encode($n->toArray(new \stdClass)), 'Awa');
        });
        $this->assertSame(1, ResultCollection::query()->first()->reminders_sent);
        $this->assertSame(1, $cert->refresh()->reminders_sent);

        // within the interval: nothing more is sent
        $this->assertSame(0, $col->sendReminders($this->school));

        // interval elapsed, cap of 2 reached after one more
        app(RecordsSettings::class)->save(['reminders' => ['enabled' => true, 'interval_days' => 1, 'max' => 2]], $this->school);
        ResultCollection::query()->update(['last_reminded_at' => now()->subDays(3)]);
        CertificateRecord::query()->update(['last_reminded_at' => now()->subDays(3)]);
        $this->assertSame(1, $col->sendReminders($this->school->refresh()));
        ResultCollection::query()->update(['last_reminded_at' => now()->subDays(3)]);
        CertificateRecord::query()->update(['last_reminded_at' => now()->subDays(3)]);
        $this->assertSame(0, $col->sendReminders($this->school->refresh()), 'max reminders reached');
    }

    public function test_reminders_can_be_disabled_per_school_and_skip_collected_items(): void
    {
        Notification::fake();
        $this->user('records_officer');
        $c = $this->candidate();
        $col = app(CollectionService::class);
        $col->markReceived($this->admin, $c, now()->subDays(40)->toDateString());

        app(RecordsSettings::class)->save(['reminders' => ['enabled' => false, 'interval_days' => 7, 'max' => 3]], $this->school);
        $this->assertSame(0, $col->sendReminders($this->school->refresh()));

        app(RecordsSettings::class)->save(['reminders' => ['enabled' => true, 'interval_days' => 7, 'max' => 3]], $this->school);
        $col->collect($this->admin, $c, ['collected_on' => now()->toDateString(), 'collector_name' => 'X', 'acknowledgement' => 'signed']);
        $this->assertSame(0, $col->sendReminders($this->school->refresh()), 'collected results are not chased');
    }

    public function test_scheduled_command_is_registered(): void
    {
        $this->artisan('schoolos:records:remind')->assertSuccessful();
        $scheduled = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->contains(fn ($e) => str_contains($e->command, 'schoolos:records:remind'));
        $this->assertTrue($scheduled);
    }

    // ------------------------------------------------------------------ HTTP, permissions and tenant isolation

    /** Regression: with no tenant bound beforehand (a real request), route-model binding must still resolve own-school rows. */
    public function test_model_bound_routes_resolve_when_no_tenant_is_pre_bound(): void
    {
        $eng = $this->subject('wassce', 'ENG', 'English');
        $c = $this->candidate([$eng->id]);
        $urls = [route('external-exams.sessions.show', $this->wassce), route('external-exams.candidates.show', $c)];

        foreach ($urls as $url) {
            $this->actAs($this->admin)->get($url)->assertOk();
        }
    }

    public function test_pages_render_and_unprivileged_roles_are_refused(): void
    {
        $eng = $this->subject('wassce', 'ENG', 'English');
        $c = $this->candidate([$eng->id]);

        foreach ([route('external-exams.index'), route('external-exams.sessions.show', $this->wassce), route('external-exams.candidates.show', $c),
            route('external-exams.subjects.index'), route('external-exams.collections.index'), route('external-exams.collections.print')] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }

        $teacher = $this->user('teacher');
        $this->actingAs($teacher)->get(route('external-exams.index'))->assertForbidden();
        $this->actingAs($teacher)->post(route('external-exams.sessions.store'), ['type' => 'gabece', 'name' => 'x', 'exam_year' => 2027])->assertForbidden();
    }

    public function test_http_registration_and_index_recording(): void
    {
        $eng = $this->subject('wassce', 'ENG', 'English');
        $student = $this->studentAt(12);

        $this->actingAs($this->admin)->post(route('external-exams.sessions.register', $this->wassce), ['student_ids' => [$student->id], 'subject_ids' => [$eng->id]])
            ->assertSessionHas('success', 'ok.ext.registered');
        $c = ExternalCandidate::query()->firstOrFail();

        $this->actingAs($this->admin)->post(route('external-exams.candidates.index-number', $c), ['official_index_number' => ' padded '])
            ->assertSessionHas('error', 'err.ext.indexFormat');
        $this->actingAs($this->admin)->post(route('external-exams.candidates.index-number', $c), ['official_index_number' => 'G/2026/77'])
            ->assertSessionHas('success', 'ok.ext.indexSaved');
        $this->assertSame('G/2026/77', $c->refresh()->official_index_number);
    }

    public function test_another_schools_users_cannot_read_or_change_a_candidate(): void
    {
        $eng = $this->subject('wassce', 'ENG', 'English');
        $c = $this->candidate([$eng->id]);
        $res = $this->svc->enterResult($this->admin, $c, $eng->id, 'B2');

        $beta = $this->otherSchool();
        app(\App\Support\TenantContext::class)->clear();
        $outsider = $this->user('school_admin', $beta);

        $this->actAs($outsider)->get(route('external-exams.candidates.show', $c))->assertNotFound();
        $this->actAs($outsider)->get(route('external-exams.sessions.show', $this->wassce))->assertNotFound();
        $this->actAs($outsider)->post(route('external-exams.candidates.verify', $c))->assertNotFound();
        $this->actAs($outsider)->post(route('external-exams.results.correct', $res), ['grade' => 'A1', 'reason' => 'x'])->assertNotFound();
        $this->actAs($outsider)->post(route('external-exams.sessions.register', $this->wassce), ['student_ids' => [1]])->assertNotFound();

        app(\App\Support\TenantContext::class)->set($this->school);
        $this->assertSame('B2', $res->refresh()->grade);
    }

    public function test_candidate_documents_are_private_and_validated(): void
    {
        Storage::fake('local');
        $c = $this->candidate();

        $this->actingAs($this->admin)->post(route('external-exams.documents.store', $c), ['type' => 'photo', 'file' => UploadedFile::fake()->create('evil.php', 10, 'application/x-php')])->assertSessionHasErrors('file');
        $this->actingAs($this->admin)->post(route('external-exams.documents.store', $c), ['type' => 'photo', 'file' => UploadedFile::fake()->image('p.png')])->assertSessionHas('success');

        $doc = $c->documents()->firstOrFail();
        $this->actingAs($this->admin)->get(route('external-exams.documents.show', [$c, $doc]))->assertOk();

        $beta = $this->otherSchool();
        app(\App\Support\TenantContext::class)->clear();
        $this->actAs($this->user('school_admin', $beta))->get(route('external-exams.documents.show', [$c, $doc]))->assertNotFound();
    }

    public function test_subject_catalogue_and_grade_overrides(): void
    {
        $this->actingAs($this->admin)->post(route('external-exams.subjects.store'), ['exam_type' => 'wassce', 'code' => 'phy', 'name' => 'Physics'])->assertSessionHas('success');
        $this->assertSame('PHY', \App\Models\ExternalExamSubject::query()->first()->code);
        $this->actingAs($this->admin)->post(route('external-exams.subjects.store'), ['exam_type' => 'wassce', 'code' => 'PHY', 'name' => 'Dup'])->assertSessionHas('error', 'err.ext.subjectExists');

        $this->actingAs($this->admin)->post(route('external-exams.subjects.grades'), ['exam_type' => 'gabece', 'grades' => 'A, B, C, D, E'])->assertSessionHas('success');
        $this->assertSame(['A', 'B', 'C', 'D', 'E'], $this->svc->gradeList('gabece'));
        $this->assertContains('A1', $this->svc->gradeList('wassce'), 'the WASSCE list is untouched');
        $this->actingAs($this->admin)->post(route('external-exams.subjects.grades'), ['exam_type' => 'gabece', 'grades' => 'bad grade!!'])->assertSessionHas('error', 'err.ext.gradesInvalid');
    }

    public function test_exam_type_session_uniqueness_and_user_model_unaffected(): void
    {
        $this->refuses('err.ext.sessionExists', fn () => $this->svc->createSession(['type' => 'wassce', 'name' => 'Again', 'exam_year' => 2026]));
        $this->assertInstanceOf(User::class, $this->admin);
    }
}
