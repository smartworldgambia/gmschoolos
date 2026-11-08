<?php

namespace Tests\Feature;

use App\Exceptions\AcademicRuleException;
use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\Level;
use App\Models\RecordsDocument;
use App\Models\RecordsDocumentEvent;
use App\Models\RecordsTemplate;
use App\Models\Student;
use App\Models\StudentTermRecord;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\Records\DocumentPdf;
use App\Services\Records\DocumentService;
use App\Services\Records\TranscriptCompiler;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Phase 6 - transcripts (9 / 8 terms), testimonials, cover letters, issuance register, PDF and public verification. */
class RecordsOfficeTest extends Phase6TestCase
{
    protected DocumentService $docs;

    protected Subject $maths;

    protected Subject $english;

    protected User $head;

    protected function setUp(): void
    {
        parent::setUp();
        $this->docs = app(DocumentService::class);
        $this->maths = Subject::query()->create(['name' => 'Mathematics', 'code' => 'MATH']);
        $this->english = Subject::query()->create(['name' => 'English', 'code' => 'ENG']);
        $this->head = $this->user('headteacher');
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

    /** Create the three terms of the academic year used for a grade (once) and return the requested one. */
    protected function term(int $grade, int $seq): Term
    {
        $start = 2018 + $grade; // grade 7 => 2025 ... arbitrary but ordered
        $year = AcademicYear::query()->firstOrCreate(['name' => "{$start}/".($start + 1)], ['starts_on' => "$start-01-01", 'ends_on' => ($start + 1).'-12-31', 'is_current' => false]);
        foreach ([1 => '01', 2 => '05', 3 => '09'] as $n => $month) {
            Term::query()->firstOrCreate(['academic_year_id' => $year->id, 'name' => "Term $n"], ['starts_on' => "$start-$month-01", 'ends_on' => "$start-$month-28", 'is_current' => false]);
        }

        return Term::query()->where('academic_year_id', $year->id)->where('name', "Term $seq")->firstOrFail();
    }

    protected function publish(Student $student, int $grade, int $seq, float $total = 70, string $letter = 'B+', bool $published = true, ?Subject $subject = null): Grade
    {
        $level = Level::query()->where('grade_number', $grade)->first() ?? $this->classAt($grade)->level;

        return Grade::query()->create([
            'student_id' => $student->id, 'subject_id' => ($subject ?? $this->maths)->id, 'term_id' => $this->term($grade, $seq)->id,
            'level_id' => $level->id, 'total' => $total, 'letter_grade' => $letter, 'is_published' => $published,
        ]);
    }

    /** All required terms of a transcript with one published mark each. */
    protected function fullHistory(Student $student, string $type, int $mark = 70): void
    {
        foreach (TranscriptCompiler::requiredTerms($type) as [$g, $t]) {
            $this->publish($student, $g, $t, $mark + $t);
        }
    }

    // ------------------------------------------------------------------ structure

    public function test_term_sequence_and_level_grade_number_are_derived(): void
    {
        $this->assertSame([1, 2, 3], [$this->term(9, 1)->sequence, $this->term(9, 2)->sequence, $this->term(9, 3)->sequence]);

        $this->assertSame(7, Level::guessGradeNumber('Grade 7'));
        $this->assertSame(10, Level::guessGradeNumber('Class 10', 'C10'));
        $this->assertSame(9, Level::guessGradeNumber('G9'));
        $this->assertNull(Level::guessGradeNumber('Nursery 1'));
        $this->assertNull(Level::guessGradeNumber('Senior Form'));
        $this->assertSame(12, Level::query()->create(['name' => 'Grade 12', 'code' => 'G12', 'sort_order' => 12])->grade_number);
    }

    public function test_grade_rows_record_the_level_the_learner_was_in(): void
    {
        $student = $this->studentAt(8);
        $levelId = $student->schoolClass->level_id;
        $exam = \App\Models\Examination::query()->create(['term_id' => $this->term(8, 1)->id, 'name' => 'End of term', 'status' => 'draft', 'type' => 'end_of_term']);
        $cat = app(\App\Services\Results\ResultsService::class)->categories()->first();

        app(\App\Services\Results\ResultsService::class)->saveMarks($this->admin, $exam, $student, $this->maths->id, [$cat->id => (float) $cat->max_score / 2]);

        $this->assertSame($levelId, Grade::query()->firstOrFail()->level_id);
    }

    // ------------------------------------------------------------------ transcript term rules

    public function test_grade_9_transcript_has_exactly_nine_terms(): void
    {
        $student = $this->studentAt(9);
        $payload = app(TranscriptCompiler::class)->compile($student, 'transcript_g9');

        $this->assertCount(9, $payload['terms']);
        $this->assertSame(['G7-T1', 'G7-T2', 'G7-T3', 'G8-T1', 'G8-T2', 'G8-T3', 'G9-T1', 'G9-T2', 'G9-T3'], array_column($payload['terms'], 'label'));
        $this->assertSame(9, $payload['summary']['terms_required']);
    }

    public function test_grade_12_transcript_has_exactly_eight_terms_and_no_term_3_of_grade_12(): void
    {
        $student = $this->studentAt(12);
        $payload = app(TranscriptCompiler::class)->compile($student, 'transcript_g12');

        $this->assertCount(8, $payload['terms']);
        $this->assertSame(['G10-T1', 'G10-T2', 'G10-T3', 'G11-T1', 'G11-T2', 'G11-T3', 'G12-T1', 'G12-T2'], array_column($payload['terms'], 'label'));
        $this->assertNotContains('G12-T3', array_column($payload['terms'], 'label'));

        // even when Grade 12 Term 3 marks exist, they are not pulled in automatically
        $this->fullHistory($student, 'transcript_g12');
        $this->publish($student, 12, 3, 99, 'A');
        $again = app(TranscriptCompiler::class)->compile($student, 'transcript_g12');
        $this->assertCount(8, $again['terms']);
        $this->assertSame([], $again['missing']);
    }

    public function test_only_published_marks_are_used_and_the_latest_exam_wins(): void
    {
        $student = $this->studentAt(9);
        $this->publish($student, 7, 1, 40, 'D', published: false);
        $this->publish($student, 7, 2, 55, 'C');
        $this->publish($student, 7, 2, 65, 'B'); // later published row for the same subject/term wins

        $payload = app(TranscriptCompiler::class)->compile($student, 'transcript_g9');
        $byLabel = collect($payload['terms'])->keyBy('label');

        $this->assertSame('missing', $byLabel['G7-T1']['status'], 'a draft/unpublished mark never reaches a transcript');
        $this->assertSame(65.0, $byLabel['G7-T2']['entries'][0]['mark']);
        $this->assertSame('platform', $byLabel['G7-T2']['source']);
        $this->assertContains('G7-T1', $payload['missing']);
    }

    public function test_manual_history_fills_terms_the_platform_does_not_have_and_never_overrides_it(): void
    {
        $student = $this->studentAt(9);
        $this->publish($student, 8, 1, 71, 'B+');
        StudentTermRecord::query()->create(['student_id' => $student->id, 'grade_number' => 7, 'term_sequence' => 1, 'academic_year_label' => '2023/2024', 'source' => 'manual',
            'entries' => [['subject' => 'Science', 'mark' => 60, 'grade' => 'B'], ['subject' => 'Art', 'mark' => 82, 'grade' => 'A']]]);
        StudentTermRecord::query()->create(['student_id' => $student->id, 'grade_number' => 8, 'term_sequence' => 1, 'academic_year_label' => 'ignored', 'source' => 'manual',
            'entries' => [['subject' => 'Ignored', 'mark' => 1, 'grade' => 'F']]]);

        $terms = collect(app(TranscriptCompiler::class)->compile($student, 'transcript_g9')['terms'])->keyBy('label');

        $this->assertSame('manual', $terms['G7-T1']['source']);
        $this->assertSame('2023/2024', $terms['G7-T1']['academic_year']);
        $this->assertSame(71.0, $terms['G7-T1']['average']);
        $this->assertSame('platform', $terms['G8-T1']['source'], 'platform grades take precedence over manual rows');
        $this->assertSame('Mathematics', $terms['G8-T1']['entries'][0]['subject']);
    }

    public function test_grade_description_comes_from_the_schools_grading_scale(): void
    {
        $student = $this->studentAt(9);
        app(\App\Services\Results\ResultsService::class)->ensureDefaults();
        $band = \App\Models\GradingBand::query()->orderByDesc('min_score')->first();
        $this->publish($student, 7, 1, 90, $band->letter);

        $entry = app(TranscriptCompiler::class)->compile($student, 'transcript_g9')['terms'][0]['entries'][0];
        $this->assertSame($band->remark, $entry['description']);
    }

    // ------------------------------------------------------------------ missing terms and exceptions

    public function test_missing_terms_block_submission_until_an_authorised_exception(): void
    {
        $officer = $this->user('exam_officer');
        $student = $this->studentAt(12);
        foreach (TranscriptCompiler::requiredTerms('transcript_g12') as [$g, $t]) {
            if (! ($g === 11 && $t === 2)) {
                $this->publish($student, $g, $t);
            }
        }
        $doc = $this->docs->createTranscript($officer, $student, 'transcript_g12');
        $this->assertSame(['G11-T2'], $doc->payload['missing']);

        $this->refuses('err.rec.missingTerms', fn () => $this->docs->submit($officer, $doc));
        $this->refuses('err.rec.permission', fn () => $this->docs->grantException($officer, $doc, false, true, 'Records lost in a flood'));
        $this->refuses('err.rec.reasonRequired', fn () => $this->docs->grantException($this->head, $doc, false, true, ''));
        $this->refuses('err.rec.exceptionEmpty', fn () => $this->docs->grantException($this->head, $doc, false, false, 'nothing'));

        $this->docs->grantException($this->head, $doc, false, true, 'Records lost in a flood; confirmed by the head');
        $doc->refresh();
        $this->assertTrue($doc->exception['allow_incomplete']);
        $this->assertSame($this->head->id, $doc->exception['approved_by']);

        $this->docs->submit($officer, $doc);
        $this->assertSame('submitted', $doc->refresh()->status);
        $this->assertCount(8, $doc->payload['terms'], 'an incomplete-term exception does not add or remove terms');
        $this->assertSame('missing', collect($doc->payload['terms'])->firstWhere('label', 'G11-T2')['status']);
        $this->assertTrue(RecordsDocumentEvent::query()->where('document_id', $doc->id)->where('event', 'exception')->exists());
    }

    public function test_grade_12_term_3_can_be_added_only_through_an_approved_exception(): void
    {
        $student = $this->studentAt(12);
        $this->fullHistory($student, 'transcript_g12');
        $this->publish($student, 12, 3, 88, 'A');
        $doc = $this->docs->createTranscript($this->head, $student, 'transcript_g12');
        $this->assertCount(8, $doc->payload['terms']);

        $this->refuses('err.rec.exceptionNa', fn () => $this->docs->grantException($this->head, $this->docs->createTranscript($this->head, $this->studentAt(9), 'transcript_g9'), true, false, 'x'));

        $this->docs->grantException($this->head, $doc, true, false, 'Student sat all three terms; approved by the head');
        $doc->refresh();

        $this->assertCount(9, $doc->payload['terms']);
        $this->assertSame('G12-T3', $doc->payload['terms'][8]['label']);
        $this->assertTrue($doc->exception['include_grade12_term3']);
        $this->assertSame('Student sat all three terms; approved by the head', $doc->exception['reason']);
    }

    // ------------------------------------------------------------------ workflow, register, immutability

    protected function readyTranscript(): RecordsDocument
    {
        $student = $this->studentAt(9);
        $this->fullHistory($student, 'transcript_g9');

        return $this->docs->createTranscript($this->head, $student, 'transcript_g9');
    }

    public function test_document_workflow_and_issuance_register(): void
    {
        $officer = $this->user('records_officer');
        $student = $this->studentAt(9);
        $this->fullHistory($student, 'transcript_g9');
        $doc = $this->docs->createTranscript($officer, $student, 'transcript_g9');

        $this->assertSame('draft', $doc->status);
        $this->assertMatchesRegularExpression('/^TR9-\d{4}-\d{6}$/', $doc->reference);
        $this->assertSame(32, strlen($doc->verification_token));

        $this->refuses('err.rec.status.draft', fn () => $this->docs->approve($this->head, $doc));
        $this->docs->submit($officer, $doc);
        $this->refuses('err.rec.permission', fn () => $this->docs->approve($officer, $doc->refresh()));
        $this->docs->approve($this->head, $doc->refresh());
        $this->refuses('err.rec.status.approved', fn () => $this->docs->submit($officer, $doc->refresh()));
        $this->docs->issue($this->head, $doc->refresh(), '2026-03-02');
        $doc->refresh();
        $this->assertSame(['issued', '2026-03-02', $this->head->id], [$doc->status, $doc->issue_date->toDateString(), $doc->issued_by]);

        $this->refuses('err.rec.reasonRequired', fn () => $this->docs->reprint($officer, $doc, ''));
        $this->docs->reprint($officer, $doc, 'Original lost by the family');
        $this->docs->reprint($officer, $doc->refresh(), 'Second copy for embassy');
        $this->assertSame(['reprinted', 2], [$doc->refresh()->status, $doc->reprint_count]);

        $this->refuses('err.rec.reasonRequired', fn () => $this->docs->revoke($this->head, $doc, ''));
        $this->refuses('err.rec.permission', fn () => $this->docs->revoke($officer, $doc, 'no'));
        $this->docs->revoke($this->head, $doc, 'Issued in error');
        $this->assertSame('revoked', $doc->refresh()->status);
        $this->refuses('err.rec.status.revoked', fn () => $this->docs->reprint($officer, $doc, 'again'));

        $this->assertSame(['created', 'submitted', 'approved', 'issued', 'reprinted', 'reprinted', 'revoked'],
            RecordsDocumentEvent::query()->where('document_id', $doc->id)->orderBy('id')->pluck('event')->all());
    }

    public function test_returned_documents_go_back_to_draft_with_a_reason(): void
    {
        $officer = $this->user('records_officer');
        $doc = $this->readyTranscript();
        $this->docs->submit($officer, $doc);

        $this->refuses('err.rec.reasonRequired', fn () => $this->docs->returnToDraft($this->head, $doc, ''));
        $this->docs->returnToDraft($this->head, $doc, 'Subject list incomplete');
        $this->assertSame('draft', $doc->refresh()->status);
        $this->assertSame('Subject list incomplete', RecordsDocumentEvent::query()->where('event', 'returned')->value('note'));
    }

    public function test_the_snapshot_is_frozen_at_submission_and_issued_documents_never_change(): void
    {
        $student = $this->studentAt(9);
        $this->fullHistory($student, 'transcript_g9', 60);
        $doc = $this->docs->createTranscript($this->head, $student, 'transcript_g9');
        $this->docs->submit($this->head, $doc);
        $frozen = $doc->refresh()->payload;

        // later changes to the learner's records, even a correction of the grade, do not touch the document
        Grade::query()->where('student_id', $student->id)->update(['total' => 5, 'letter_grade' => 'F']);
        $student->update(['first_name' => 'Renamed']);
        $this->refuses('err.rec.status.submitted', fn () => $this->docs->recompile($this->head, $doc));

        $this->docs->approve($this->head, $doc->refresh());
        $this->docs->issue($this->head, $doc->refresh());
        $this->assertSame($frozen, $doc->refresh()->payload);
        $this->assertNotSame('Renamed Jallow', $doc->payload['student']['full_name']);
    }

    public function test_draft_transcripts_can_be_recompiled_before_submission(): void
    {
        $student = $this->studentAt(9);
        $doc = $this->docs->createTranscript($this->head, $student, 'transcript_g9');
        $this->assertCount(9, $doc->payload['missing']);

        $this->fullHistory($student, 'transcript_g9');
        $this->docs->recompile($this->head, $doc);
        $this->assertSame([], $doc->refresh()->payload['missing']);
    }

    // ------------------------------------------------------------------ letters and templates

    public function test_testimonials_and_cover_letters_use_templates_with_plain_text_placeholders(): void
    {
        $student = $this->studentAt(12, ['first_name' => '<b>Awa</b>', 'last_name' => 'Jallow']);
        $tpl = $this->docs->saveTemplate($this->head, ['type' => 'testimonial', 'name' => 'Standard', 'body' => 'This confirms {{ student_name }} ({{student_number}}) attended {{ school_name }}. {{ unknown_field }}', 'is_default' => true]);

        $doc = $this->docs->createLetter($this->head, $student, 'testimonial', $tpl, 'Conduct: excellent.');

        $this->assertStringContainsString("<b>Awa</b> Jallow ({$student->student_number}) attended Alpha School.", $doc->body);
        $this->assertStringContainsString('{{ unknown_field }}', $doc->body, 'unknown placeholders are left visible, never silently dropped');
        $this->assertStringEndsWith('Conduct: excellent.', $doc->body);
        $this->assertSame($tpl->id, $doc->template_id);
        $this->assertStringStartsWith('TES-', $doc->reference);

        $cover = $this->docs->createLetter($this->head, $student, 'cover_letter', null, null);
        $this->assertStringContainsString('Alpha School confirms the enclosed records', $cover->body);
        $this->assertStringStartsWith('CVL-', $cover->reference);

        $this->refuses('err.rec.templateInvalid', fn () => $this->docs->createLetter($this->head, $student, 'cover_letter', $tpl, null));
    }

    public function test_letters_need_a_body_and_are_editable_only_while_draft(): void
    {
        $student = $this->studentAt(12);
        $doc = $this->docs->createLetter($this->head, $student, 'testimonial', null, null);
        $this->docs->updateDraft($this->head, $doc, 'Hand-edited testimonial text.');
        $this->assertSame('Hand-edited testimonial text.', $doc->refresh()->body);

        $doc->update(['body' => '   ']);
        $this->refuses('err.rec.bodyRequired', fn () => $this->docs->submit($this->head, $doc->refresh()));
        $doc->update(['body' => 'ok']);
        $this->docs->submit($this->head, $doc->refresh());
        $this->refuses('err.rec.status.submitted', fn () => $this->docs->updateDraft($this->head, $doc->refresh(), 'changed'));
    }

    public function test_teachers_cannot_draft_or_view_documents(): void
    {
        $teacher = $this->user('teacher');
        $student = $this->studentAt(9);
        $this->refuses('err.rec.permission', fn () => $this->docs->createTranscript($teacher, $student, 'transcript_g9'));

        $doc = $this->docs->createTranscript($this->head, $student, 'transcript_g9');
        $this->actingAs($teacher)->get(route('records.index'))->assertForbidden();
        $this->actingAs($teacher)->get(route('records.show', $doc))->assertForbidden();
        $this->actingAs($teacher)->get(route('records.pdf', $doc))->assertForbidden();
    }

    // ------------------------------------------------------------------ PDF

    public function test_pdf_is_generated_for_every_document_type(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $student = $this->studentAt(9);
        $this->fullHistory($student, 'transcript_g9');
        $transcript = $this->docs->createTranscript($this->head, $student, 'transcript_g9');
        $letter = $this->docs->createLetter($this->head, $student, 'testimonial', null, null);

        $pdf = app(DocumentPdf::class);
        foreach ([$transcript, $letter] as $doc) {
            $bytes = $pdf->render($doc);
            $this->assertStringStartsWith('%PDF-', $bytes);
            $this->assertGreaterThan(1500, strlen($bytes));
        }

        $response = $this->actingAs($this->head)->get(route('records.pdf', $transcript))->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_pdf_embeds_a_qr_image_and_the_non_waec_disclaimer(): void
    {
        $this->assertStringContainsString('not an official WAEC result or certificate', DocumentPdf::DISCLAIMER);
        $this->assertStringStartsWith('data:image/png;base64,', app(DocumentPdf::class)->qr('https://example.test/verify/abc'));

        $view = file_get_contents(resource_path('views/records/document.blade.php'));
        $this->assertStringContainsString('$disclaimer', $view);
        $this->assertStringContainsString('<img src="{{ $qr }}"', $view);
        $this->assertStringContainsString('$verifyUrl', $view);
    }

    /** Regression: route-model binding must resolve own-school documents when no tenant is pre-bound (a real request). */
    public function test_own_documents_resolve_when_no_tenant_is_pre_bound(): void
    {
        $doc = $this->docs->createTranscript($this->head, $this->studentAt(9), 'transcript_g9');

        $this->actAs($this->head)->get(route('records.show', $doc))->assertOk();
        $this->actAs($this->head)->get(route('records.pdf', $doc))->assertOk();
    }

    public function test_pdf_of_another_schools_document_is_not_reachable(): void
    {
        $doc = $this->docs->createTranscript($this->head, $this->studentAt(9), 'transcript_g9');
        $beta = $this->otherSchool();
        app(\App\Support\TenantContext::class)->clear();
        $outsider = $this->user('school_admin', $beta);

        $this->actAs($outsider)->get(route('records.pdf', $doc))->assertNotFound();
        $this->actAs($outsider)->get(route('records.show', $doc))->assertNotFound();
        $this->actAs($outsider)->post(route('records.revoke', $doc), ['reason' => 'x'])->assertNotFound();
    }

    // ------------------------------------------------------------------ public verification

    protected function issued(): RecordsDocument
    {
        $doc = $this->readyTranscript();
        $this->docs->submit($this->head, $doc);
        $this->docs->approve($this->head, $doc->refresh());

        return $this->docs->issue($this->head, $doc->refresh(), '2026-03-02');
    }

    public function test_verification_page_confirms_authenticity_with_minimal_disclosure(): void
    {
        $doc = $this->issued();
        $student = $doc->student;
        app(\App\Support\TenantContext::class)->clear(); // a verifier is anonymous and has no school context

        $response = $this->get(route('records.verify', ['token' => $doc->verification_token]))->assertOk();
        $response->assertInertia(fn ($p) => $p->component('Verify/Show')
            ->where('result.state', 'valid')
            ->where('result.reference', $doc->reference)
            ->where('result.school', 'Alpha School')
            ->where('result.issue_date', '2026-03-02')
            ->where('result.type', 'transcript_g9')
        );

        $props = $response->viewData('page')['props']['result'];
        $this->assertSame(['state', 'type', 'reference', 'school', 'issue_date', 'initials', 'revoked_on'], array_keys($props));
        $body = $response->getContent();
        foreach ([$student->first_name, $student->last_name, $student->student_number, 'Mathematics', (string) $student->date_of_birth?->format('Y-m-d'), 'office@alpha.test', '+2207000000'] as $secret) {
            $this->assertStringNotContainsString($secret, $body, "verification page leaked: {$secret}");
        }
        $this->assertMatchesRegularExpression('/^[A-Z]\. [A-Z]\.$/', $props['initials']);
    }

    public function test_revoked_documents_show_as_invalid_and_unknown_or_draft_tokens_look_identical(): void
    {
        $doc = $this->issued();
        $this->docs->revoke($this->head, $doc->refresh(), 'Issued in error');
        $draft = $this->docs->createTranscript($this->head, $this->studentAt(9), 'transcript_g9');
        app(\App\Support\TenantContext::class)->clear();

        $this->get(route('records.verify', ['token' => $doc->verification_token]))->assertInertia(fn ($p) => $p->where('result.state', 'revoked')->where('result.revoked_on', now()->toDateString()));

        $unknown = $this->get(route('records.verify', ['token' => str_repeat('a', 32)]))->assertOk();
        $unknown->assertInertia(fn ($p) => $p->where('result', ['state' => 'not_found']));
        $this->get(route('records.verify', ['token' => $draft->verification_token]))->assertInertia(fn ($p) => $p->where('result', ['state' => 'not_found']));
        $this->get('/verify/not-a-valid-token')->assertNotFound();
    }

    public function test_issued_documents_are_served_with_the_verification_url_only_after_issue(): void
    {
        $doc = $this->readyTranscript();
        $this->actingAs($this->head)->get(route('records.show', $doc))->assertInertia(fn ($p) => $p->where('document.verification_url', null)->where('document.disclaimer', DocumentPdf::DISCLAIMER));

        $this->docs->submit($this->head, $doc);
        $this->docs->approve($this->head, $doc->refresh());
        $this->docs->issue($this->head, $doc->refresh());
        $this->actingAs($this->head)->get(route('records.show', $doc))->assertInertia(fn ($p) => $p->where('document.verification_url', route('records.verify', $doc->verification_token)));
    }

    // ------------------------------------------------------------------ HTTP

    public function test_staff_screens_and_actions_over_http(): void
    {
        $student = $this->studentAt(9);
        $this->fullHistory($student, 'transcript_g9');

        foreach ([route('records.index'), route('records.create'), route('records.create', ['q' => 'Awa']), route('records.templates.index'), route('records.settings.edit'), route('records.history.show', $student)] as $url) {
            $this->actingAs($this->head)->get($url)->assertOk();
        }

        $this->actingAs($this->head)->post(route('records.store'), ['type' => 'transcript_g9', 'student_id' => $student->id])->assertRedirect();
        $doc = RecordsDocument::query()->firstOrFail();

        $this->actingAs($this->head)->post(route('records.submit', $doc))->assertSessionHas('success', 'ok.rec.submitted');
        $this->actingAs($this->head)->post(route('records.approve', $doc))->assertSessionHas('success', 'ok.rec.approved');
        $this->actingAs($this->head)->post(route('records.issue', $doc), ['issue_date' => now()->addDays(3)->toDateString()])->assertSessionHas('error', 'err.rec.dateFuture');
        $this->actingAs($this->head)->post(route('records.issue', $doc))->assertSessionHas('success', 'ok.rec.issued');
        $this->actingAs($this->head)->post(route('records.reprint', $doc), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($this->head)->post(route('records.reprint', $doc), ['reason' => 'Lost'])->assertSessionHas('success', 'ok.rec.reprinted');
        $this->actingAs($this->head)->post(route('records.revoke', $doc), ['reason' => 'Error'])->assertSessionHas('success', 'ok.rec.revoked');
    }

    public function test_manual_history_screen_saves_a_term_and_it_flows_into_the_next_transcript(): void
    {
        $student = $this->studentAt(9);

        $this->actingAs($this->head)->post(route('records.history.store', $student), [
            'grade_number' => 7, 'term_sequence' => 2, 'academic_year_label' => '2023/2024',
            'entries' => [['subject' => 'Science', 'mark' => 64, 'grade' => 'B'], ['subject' => 'Art', 'mark' => '', 'grade' => 'A']],
        ])->assertSessionHas('success');
        $this->actingAs($this->head)->post(route('records.history.store', $student), ['grade_number' => 13, 'term_sequence' => 1, 'academic_year_label' => 'x', 'entries' => [['subject' => 'S']]])->assertSessionHasErrors('grade_number');

        $term = collect(app(TranscriptCompiler::class)->compile($student, 'transcript_g9')['terms'])->firstWhere('label', 'G7-T2');
        $this->assertSame('recorded', $term['status']);
        $this->assertSame(64.0, $term['average']);
        $this->assertSame(2, $term['subjects_count']);
    }

    public function test_settings_signatories_stamp_levels_and_reminders(): void
    {
        Storage::fake('local');
        $level = Level::query()->create(['name' => 'Senior Form', 'code' => 'SF', 'sort_order' => 60]);

        $this->actingAs($this->head)->put(route('records.settings.update'), [
            'signatories' => [['name' => 'A. Head', 'title' => 'Headteacher'], ['name' => '', 'title' => 'ignored']],
            'reminders' => ['enabled' => true, 'interval_days' => 10, 'max' => 4],
        ])->assertSessionHas('success');
        $this->actingAs($this->head)->put(route('records.settings.levels'), ['levels' => [['id' => $level->id, 'grade_number' => 12]]])->assertSessionHas('success');
        $this->actingAs($this->head)->post(route('records.settings.stamp'), ['stamp' => UploadedFile::fake()->image('stamp.png', 200, 200)])->assertSessionHas('success');
        $this->actingAs($this->head)->post(route('records.settings.stamp'), ['stamp' => UploadedFile::fake()->create('evil.php', 5, 'application/x-php')])->assertSessionHasErrors('stamp');

        $settings = app(\App\Services\Records\RecordsSettings::class)->all($this->school->refresh());
        $this->assertSame([['name' => 'A. Head', 'title' => 'Headteacher']], $settings['signatories']);
        $this->assertSame([true, 10, 4], [$settings['reminders']['enabled'], $settings['reminders']['interval_days'], $settings['reminders']['max']]);
        $this->assertSame(12, $level->refresh()->grade_number);
        Storage::disk('local')->assertExists($settings['stamp_path']);

        // exam officers draft documents but may not change the school's official signatories / stamp
        $this->actingAs($this->user('exam_officer'))->get(route('records.settings.edit'))->assertForbidden();
    }

    public function test_templates_over_http_and_default_swap(): void
    {
        $this->actingAs($this->head)->post(route('records.templates.store'), ['type' => 'cover_letter', 'name' => 'A', 'body' => 'Hello {{ student_name }}', 'is_default' => true])->assertSessionHas('success');
        $this->actingAs($this->head)->post(route('records.templates.store'), ['type' => 'cover_letter', 'name' => 'B', 'body' => 'Hi', 'is_default' => true])->assertSessionHas('success');

        $this->assertSame(['B'], RecordsTemplate::query()->where('is_default', true)->pluck('name')->all());
        $this->actingAs($this->head)->post(route('records.templates.store'), ['type' => 'cover_letter', 'name' => 'C', 'body' => ''])->assertSessionHasErrors('body');
    }
}
