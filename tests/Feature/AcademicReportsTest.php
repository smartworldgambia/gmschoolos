<?php

namespace Tests\Feature;

use App\Exceptions\AcademicRuleException;
use App\Models\AcademicReport;
use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\Grade;
use App\Models\Guardian;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\Records\TranscriptCompiler;
use App\Services\Reporting\AcademicReportPdf;
use App\Services\Reporting\AcademicReportService;
use App\Services\Reporting\ReportCompiler;
use App\Support\TenantContext;

class AcademicReportsTest extends Phase6TestCase
{
    protected AcademicReportService $reports;

    protected ReportCompiler $compiler;

    protected Subject $maths;

    protected Subject $english;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reports = app(AcademicReportService::class);
        $this->compiler = app(ReportCompiler::class);
        $this->maths = Subject::query()->create(['name' => 'Mathematics', 'code' => 'MATH']);
        $this->english = Subject::query()->create(['name' => 'English', 'code' => 'ENG']);
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

    protected function year(): AcademicYear
    {
        return AcademicYear::query()->firstOrCreate(
            ['name' => '2025/2026'],
            ['starts_on' => '2025-09-01', 'ends_on' => '2026-07-31', 'is_current' => true]
        );
    }

    protected function term(int $seq): Term
    {
        $year = $this->year();
        $months = [1 => '09', 2 => '01', 3 => '04'];
        $startYear = $seq === 1 ? 2025 : 2026;

        return Term::query()->firstOrCreate(
            ['academic_year_id' => $year->id, 'name' => "Term $seq"],
            ['starts_on' => "$startYear-{$months[$seq]}-01", 'ends_on' => "$startYear-{$months[$seq]}-28", 'is_current' => $seq === 1]
        );
    }

    protected function publish(Student $student, int $seq, float $total, Subject $subject, bool $published = true): Grade
    {
        $term = $this->term($seq);

        return Grade::query()->create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'level_id' => $student->schoolClass?->level_id,
            'total' => $total,
            'ca_score' => round($total * 0.4, 2),
            'exam_score' => round($total * 0.6, 2),
            'letter_grade' => $total >= 80 ? 'A' : ($total >= 60 ? 'B' : 'C'),
            'is_published' => $published,
        ]);
    }

    protected function allocate(Student $student): void
    {
        foreach ([$this->maths, $this->english] as $subject) {
            ClassSubject::query()->firstOrCreate(
                ['school_class_id' => $student->school_class_id, 'subject_id' => $subject->id],
                ['periods_per_week' => 5]
            );
        }
    }

    public function test_dashboard_and_nav_are_available_to_school_staff(): void
    {
        $this->year();
        $this->actingAs($this->admin)->get(route('reporting.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Reporting/Dashboard')->has('kpis')->has('tabs'));
    }

    public function test_tenant_isolation_hides_another_schools_reports(): void
    {
        $student = $this->studentAt(5);
        $this->allocate($student);
        $this->publish($student, 1, 70, $this->maths);
        $this->publish($student, 1, 75, $this->english);
        $report = $this->reports->generate($this->admin, $student, 'termly', $this->year(), $this->term(1));

        $other = $this->otherSchool();
        $outsider = $this->user('school_admin', $other);
        app(TenantContext::class)->clear();
        $this->actingAs($outsider)->get(route('reporting.show', $report))->assertNotFound();
        $this->actingAs($outsider)->get(route('reporting.index'))->assertOk()
            ->assertInertia(fn ($p) => $p->where('recent', []));
    }

    public function test_termly_report_uses_published_grades_and_flags_missing_subjects(): void
    {
        $student = $this->studentAt(5);
        $this->allocate($student);
        $this->publish($student, 1, 88, $this->english);
        $this->publish($student, 1, 40, $this->maths, published: false);

        $payload = $this->compiler->compileTermly($student, $this->term(1));
        $this->assertTrue($payload['incomplete']);
        $this->assertContains('Mathematics', $payload['missing_subjects']);
        $this->assertSame(88.0, collect($payload['subjects'])->firstWhere('name', 'English')['total']);
        $this->assertSame('missing', collect($payload['subjects'])->firstWhere('name', 'Mathematics')['status']);
        $this->assertSame('termly', $payload['kind']);
    }

    public function test_annual_report_never_treats_missing_terms_as_zero(): void
    {
        $student = $this->studentAt(5);
        $this->allocate($student);
        $this->publish($student, 1, 75, $this->english);
        $this->publish($student, 2, 78, $this->english);
        $this->term(3);

        $payload = $this->compiler->compileAnnual($student, $this->year());
        $english = collect($payload['subjects'])->firstWhere('name', 'English');
        $this->assertTrue($english['incomplete']);
        $this->assertEquals(76.5, $english['annual_average']);
        $this->assertSame('equal_available', $payload['weighting']);
        $this->assertSame('missing', collect($english['terms'])->firstWhere('sequence', 3)['status']);
        $this->assertTrue($payload['incomplete']);
    }

    public function test_annual_report_uses_school_term_weights_when_every_term_is_present(): void
    {
        $student = $this->studentAt(5);
        $this->allocate($student);
        $this->publish($student, 1, 75, $this->english);
        $this->publish($student, 2, 78, $this->english);
        $this->publish($student, 3, 82, $this->english);
        app(\App\Services\Reporting\AcademicReportSettings::class)->save(['term_weights' => ['1' => 30, '2' => 30, '3' => 40]]);

        $payload = $this->compiler->compileAnnual($student, $this->year());
        $english = collect($payload['subjects'])->firstWhere('name', 'English');
        $this->assertFalse($english['incomplete']);
        $this->assertEquals(78.7, $english['annual_average']);
        $this->assertSame('weighted', $payload['weighting']);
    }

    protected function historyTerm(int $grade, int $seq): Term
    {
        $start = 2018 + $grade;
        $year = AcademicYear::query()->firstOrCreate(
            ['name' => "{$start}/".($start + 1)],
            ['starts_on' => "$start-01-01", 'ends_on' => ($start + 1).'-12-31', 'is_current' => false]
        );
        foreach ([1 => '01', 2 => '05', 3 => '09'] as $n => $month) {
            Term::query()->firstOrCreate(
                ['academic_year_id' => $year->id, 'name' => "Term $n"],
                ['starts_on' => "$start-$month-01", 'ends_on' => "$start-$month-28", 'is_current' => false]
            );
        }

        return Term::query()->where('academic_year_id', $year->id)->where('name', "Term $seq")->firstOrFail();
    }

    protected function publishAtGrade(Student $student, int $grade, int $seq, float $total = 70): Grade
    {
        $level = Level::query()->where('grade_number', $grade)->first() ?? $this->classAt($grade)->level;

        return Grade::query()->create([
            'student_id' => $student->id,
            'subject_id' => $this->maths->id,
            'term_id' => $this->historyTerm($grade, $seq)->id,
            'level_id' => $level->id,
            'total' => $total,
            'letter_grade' => 'B',
            'is_published' => true,
        ]);
    }

    public function test_grade_12_final_report_includes_eight_terms_and_not_term_three(): void
    {
        $student = $this->studentAt(12);
        foreach (TranscriptCompiler::requiredTerms('transcript_g12') as [$g, $t]) {
            $this->publishAtGrade($student, $g, $t, 70);
        }
        $this->publishAtGrade($student, 12, 3, 99);

        $payload = $this->compiler->compileFinal($student, $this->year());
        $this->assertSame('transcript_g12', $payload['history']['kind']);
        $this->assertCount(8, $payload['history']['terms']);
        $this->assertSame(['G10-T1', 'G10-T2', 'G10-T3', 'G11-T1', 'G11-T2', 'G11-T3', 'G12-T1', 'G12-T2'], array_column($payload['history']['terms'], 'label'));
        $this->assertNotContains('G12-T3', array_column($payload['history']['terms'], 'label'));
    }

    public function test_grade_9_final_report_includes_nine_terms(): void
    {
        $student = $this->studentAt(9);
        $payload = $this->compiler->compileFinal($student, $this->year());
        $this->assertSame('transcript_g9', $payload['history']['kind']);
        $this->assertCount(9, $payload['history']['terms']);
        $this->assertSame(9, $payload['history']['summary']['terms_required']);
        $this->assertTrue($payload['incomplete']);
    }

    public function test_approval_workflow_and_published_immutability(): void
    {
        $student = $this->studentAt(5);
        $this->allocate($student);
        $this->publish($student, 1, 80, $this->maths);
        $this->publish($student, 1, 70, $this->english);
        $report = $this->reports->generate($this->admin, $student, 'termly', $this->year(), $this->term(1));
        $this->assertSame('draft', $report->status);
        $this->assertFalse($report->incomplete);

        $this->reports->submit($this->admin, $report);
        $this->reports->startReview($this->admin, $report->fresh());
        $this->reports->approve($this->admin, $report->fresh());
        $published = $this->reports->publish($this->admin, $report->fresh());
        $this->assertSame('published', $published->status);
        $this->assertNotNull($published->issue_date);
        $locked = $published->payload;

        $this->refuses('err.rpt.publishedLocked', fn () => $this->reports->generate($this->admin, $student, 'termly', $this->year(), $this->term(1)));
        $this->refuses('err.rpt.status.published', fn () => $this->reports->updateDraft($this->admin, $published, ['class_teacher_comment' => 'nope']));

        $revision = $this->reports->revise($this->admin, $published, 'Headteacher asked for a comment correction');
        $this->assertSame('draft', $revision->status);
        $this->assertSame($published->id, $revision->supersedes_id);
        $this->assertSame('superseded', $published->fresh()->status);
        $this->assertSame($locked, $published->fresh()->payload);
        $this->assertSame(2, $revision->version);
    }

    public function test_incomplete_reports_cannot_be_published_without_an_exception(): void
    {
        $student = $this->studentAt(5);
        $this->allocate($student);
        $this->publish($student, 1, 70, $this->english);
        $report = $this->reports->generate($this->admin, $student, 'termly', $this->year(), $this->term(1));
        $this->assertTrue($report->incomplete);
        $this->reports->submit($this->admin, $report);
        $this->reports->approve($this->admin, $report->fresh());
        $this->refuses('err.rpt.incomplete', fn () => $this->reports->publish($this->admin, $report->fresh()));

        $this->reports->returnToDraft($this->admin, $report->fresh(), 'Allow incomplete this once');
        $this->reports->updateDraft($this->admin, $report->fresh(), ['allow_incomplete' => true]);
        $this->reports->submit($this->admin, $report->fresh());
        $this->reports->approve($this->admin, $report->fresh());
        $this->reports->publish($this->admin, $report->fresh());
        $this->assertSame('published', $report->fresh()->status);
    }

    public function test_http_generation_pdf_and_public_verification(): void
    {
        $student = $this->studentAt(5);
        $this->allocate($student);
        $this->publish($student, 1, 80, $this->maths);
        $this->publish($student, 1, 72, $this->english);

        $this->actingAs($this->admin)->post(route('reporting.store'), [
            'type' => 'termly',
            'academic_year_id' => $this->year()->id,
            'term_id' => $this->term(1)->id,
            'student_ids' => [$student->id],
        ])->assertRedirect();

        $report = AcademicReport::query()->firstOrFail();
        $this->actingAs($this->admin)->get(route('reporting.show', $report))->assertOk()
            ->assertInertia(fn ($p) => $p->component('Reporting/Show')->where('report.reference', $report->reference)->where('can.submit', true)->where('can.publish', false));

        $this->actingAs($this->admin)->post(route('reporting.submit', $report))->assertRedirect();
        $this->actingAs($this->admin)->post(route('reporting.approve', $report))->assertRedirect();
        $this->actingAs($this->admin)->post(route('reporting.publish', $report))->assertRedirect();

        $pdf = $this->actingAs($this->admin)->get(route('reporting.pdf', $report));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $token = $report->fresh()->verification_token;
        $this->get(route('records.verify', $token))->assertOk()
            ->assertInertia(fn ($p) => $p->component('Verify/Show')->where('result.state', 'valid')->where('result.reference', $report->reference)->missing('result.payload'));
    }

    public function test_students_and_parents_only_see_published_reports(): void
    {
        $student = $this->studentAt(5);
        $this->allocate($student);
        $this->publish($student, 1, 80, $this->maths);
        $this->publish($student, 1, 70, $this->english);
        $report = $this->reports->generate($this->admin, $student, 'termly', $this->year(), $this->term(1));

        $parent = $this->user('parent');
        Guardian::query()->create(['school_id' => $this->school->id, 'user_id' => $parent->id, 'first_name' => 'P', 'last_name' => 'Q'])
            ->students()->attach($student->id, ['school_id' => $this->school->id]);
        $learner = $this->user('student');
        $student->update(['user_id' => $learner->id]);

        $this->actingAs($parent)->get(route('reporting.show', $report))->assertForbidden();
        $this->actingAs($learner)->get(route('reporting.show', $report))->assertForbidden();
        $this->actingAs($learner)->get(route('reporting.generate'))->assertForbidden();
        $this->actingAs($parent)->get(route('reporting.index'))->assertOk()
            ->assertInertia(fn ($p) => $p->component('Reporting/Family')
                ->where('learners.0.reports', [])
                ->has('learners.0.results.subjects', 2));

        $this->reports->submit($this->admin, $report);
        $this->reports->approve($this->admin, $report->fresh());
        $this->reports->publish($this->admin, $report->fresh());

        $this->actingAs($parent)->get(route('reporting.show', $report))->assertOk();
        $this->actingAs($learner)->get(route('reporting.show', $report))->assertOk();
        $this->actingAs($parent)->get(route('reporting.pdf', $report))->assertOk();
        $this->actingAs($learner)->get(route('reporting.index'))->assertOk()
            ->assertInertia(fn ($p) => $p->component('Reporting/Family')
                ->has('learners.0.reports', 1)
                ->where('learners.0.reports.0.reference', $report->fresh()->reference));
    }

    public function test_student_portal_shows_published_exam_grades_without_a_generated_report(): void
    {
        $student = $this->studentAt(5);
        $this->allocate($student);
        $this->publish($student, 1, 80, $this->maths);
        $this->publish($student, 1, 70, $this->english, false);
        $learner = $this->user('student');
        $student->update(['user_id' => $learner->id]);

        $this->actingAs($learner)->get(route('reporting.index'))->assertOk()
            ->assertInertia(fn ($p) => $p->component('Reporting/Family')
                ->where('isLearner', true)
                ->has('learners.0.results.subjects', 1)
                ->where('learners.0.results.subjects.0.total', 80)
                ->where('learners.0.reports', []));
    }

    public function test_class_teacher_cannot_prepare_another_class(): void
    {
        $mine = $this->studentAt(5);
        $otherClass = SchoolClass::query()->create([
            'level_id' => $mine->schoolClass->level_id, 'name' => 'Grade 5B', 'capacity' => 40,
        ]);
        $other = $this->studentAt(5, ['school_class_id' => $otherClass->id, 'first_name' => 'Other']);
        $this->allocate($other);
        $this->publish($other, 1, 70, $this->english);

        $teacher = $this->user('class_teacher');
        $mine->schoolClass->update(['class_teacher_id' => $teacher->id]);

        $this->refuses('err.rpt.notYourClass', fn () => $this->reports->generate($teacher, $other, 'termly', $this->year(), $this->term(1)));
    }

    public function test_pdf_renderer_produces_bytes_for_a_draft(): void
    {
        $student = $this->studentAt(5);
        $this->allocate($student);
        $this->publish($student, 1, 80, $this->maths);
        $this->publish($student, 1, 70, $this->english);
        $report = $this->reports->generate($this->admin, $student, 'termly', $this->year(), $this->term(1));
        $bytes = app(AcademicReportPdf::class)->render($report);
        $this->assertStringStartsWith('%PDF', $bytes);
    }
}
