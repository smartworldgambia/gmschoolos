<?php

namespace App\Services\Reporting;

use App\Exceptions\AcademicRuleException;
use App\Models\AcademicReport;
use App\Models\AcademicReportEvent;
use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Services\Academics\TeacherScope;
use App\Services\Records\SchoolSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Report-card workflow: Draft → Submitted → Under review → Approved → Published.
 * Published payloads are immutable snapshots. Corrections create a new version and supersede the previous one.
 */
class AcademicReportService
{
    public const PREFIX = ['termly' => 'RC', 'annual' => 'AR', 'final' => 'FR'];

    public function __construct(
        protected ReportCompiler $compiler,
        protected AcademicReportSettings $settings,
        protected SchoolSequence $sequence,
        protected TeacherScope $scope,
    ) {}

    public function generate(User $user, Student $student, string $type, AcademicYear $year, ?Term $term = null, array $extras = []): AcademicReport
    {
        $this->assertType($type);
        $this->can($user, 'prepare', $student);
        if ($type === 'termly') {
            if (! $term || (int) $term->academic_year_id !== (int) $year->id) {
                throw AcademicRuleException::because('err.rpt.termRequired');
            }
        } else {
            $term = null;
        }

        $payload = $this->compile($student, $type, $year, $term, $extras);
        $current = $this->current($student, $type, $year, $term);

        if ($current && $current->isLocked()) {
            throw AcademicRuleException::because('err.rpt.publishedLocked');
        }
        if ($current && ! in_array($current->status, ['draft'], true)) {
            throw AcademicRuleException::because('err.rpt.status.'.$current->status);
        }

        return DB::transaction(function () use ($user, $student, $type, $year, $term, $payload, $extras, $current) {
            if ($current) {
                $current->update($this->row($student, $type, $year, $term, $payload, $extras, $user) + [
                    'prepared_by' => $current->prepared_by ?? $user->id,
                ]);
                $this->event($current, 'recompiled', null, $user);

                return $current->fresh();
            }

            $n = $this->sequence->next((int) $student->school_id, 'rpt_'.$type);
            $report = AcademicReport::query()->create($this->row($student, $type, $year, $term, $payload, $extras, $user) + [
                'school_id' => $student->school_id,
                'status' => 'draft',
                'reference' => sprintf('%s-%s-%06d', self::PREFIX[$type], now()->format('Y'), $n),
                'verification_token' => Str::lower(Str::random(32)),
                'version' => 1,
                'prepared_by' => $user->id,
            ]);
            $this->event($report, 'created', null, $user);

            return $report;
        });
    }

    public function updateDraft(User $user, AcademicReport $report, array $extras): AcademicReport
    {
        $this->can($user, 'prepare', $report->student);
        $this->requireStatus($report, ['draft']);
        $report->loadMissing('student', 'academicYear', 'term');
        $payload = $this->compile($report->student, $report->type, $report->academicYear, $report->term, $extras);
        $report->update($this->comments($extras) + [
            'payload' => $payload,
            'incomplete' => (bool) ($payload['incomplete'] ?? false),
            'overall_average' => $payload['summary']['average'] ?? null,
            'overall_grade' => $payload['summary']['grade'] ?? null,
            'allow_incomplete' => (bool) ($extras['allow_incomplete'] ?? $report->allow_incomplete),
        ]);
        $this->event($report, 'updated', null, $user);

        return $report->fresh();
    }

    public function submit(User $user, AcademicReport $report): AcademicReport
    {
        $this->can($user, 'prepare', $report->student);
        $this->requireStatus($report, ['draft']);
        $report->loadMissing('student', 'academicYear', 'term');
        $payload = $this->compile($report->student, $report->type, $report->academicYear, $report->term, $this->extrasFrom($report));
        $report->forceFill([
            'payload' => $payload,
            'incomplete' => (bool) ($payload['incomplete'] ?? false),
            'overall_average' => $payload['summary']['average'] ?? null,
            'overall_grade' => $payload['summary']['grade'] ?? null,
            'status' => 'submitted',
            'submitted_by' => $user->id,
            'submitted_at' => now(),
        ])->save();
        $this->event($report, 'submitted', null, $user);

        return $report->fresh();
    }

    public function startReview(User $user, AcademicReport $report): AcademicReport
    {
        $this->can($user, 'approve', $report->student);
        $this->requireStatus($report, ['submitted']);
        $report->forceFill(['status' => 'in_review', 'reviewed_by' => $user->id, 'reviewed_at' => now()])->save();
        $this->event($report, 'in_review', null, $user);

        return $report->fresh();
    }

    public function approve(User $user, AcademicReport $report): AcademicReport
    {
        $this->can($user, 'approve', $report->student);
        $this->requireStatus($report, ['submitted', 'in_review']);
        $report->forceFill([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
            'reviewed_by' => $report->reviewed_by ?? $user->id,
            'reviewed_at' => $report->reviewed_at ?? now(),
        ])->save();
        $this->event($report, 'approved', null, $user);

        return $report->fresh();
    }

    public function returnToDraft(User $user, AcademicReport $report, string $reason): AcademicReport
    {
        $this->can($user, 'approve', $report->student);
        $this->requireStatus($report, ['submitted', 'in_review', 'approved']);
        if (mb_strlen(trim($reason)) < 5) {
            throw AcademicRuleException::because('err.rpt.reasonRequired');
        }
        $report->forceFill([
            'status' => 'draft',
            'submitted_by' => null, 'submitted_at' => null,
            'reviewed_by' => null, 'reviewed_at' => null,
            'approved_by' => null, 'approved_at' => null,
        ])->save();
        $this->event($report, 'returned', $reason, $user);

        return $report->fresh();
    }

    public function publish(User $user, AcademicReport $report): AcademicReport
    {
        $this->can($user, 'publish', $report->student);
        $this->requireStatus($report, ['approved']);
        if ($report->incomplete && ! $report->allow_incomplete) {
            throw AcademicRuleException::because('err.rpt.incomplete');
        }
        $report->forceFill([
            'status' => 'published',
            'published_by' => $user->id,
            'published_at' => now(),
            'issue_date' => $report->issue_date ?? now()->toDateString(),
        ])->save();
        $this->event($report, 'published', null, $user);

        return $report->fresh();
    }

    public function revise(User $user, AcademicReport $report, string $reason): AcademicReport
    {
        $this->can($user, 'publish', $report->student);
        $this->requireStatus($report, ['published']);
        if (mb_strlen(trim($reason)) < 5) {
            throw AcademicRuleException::because('err.rpt.reasonRequired');
        }

        $report->loadMissing('student', 'academicYear', 'term');
        $extras = $this->extrasFrom($report) + ['revision_reason' => $reason];
        $payload = $this->compile($report->student, $report->type, $report->academicYear, $report->term, $extras);

        return DB::transaction(function () use ($user, $report, $reason, $payload, $extras) {
            $report->forceFill(['status' => 'superseded'])->save();
            $this->event($report, 'superseded', $reason, $user);

            $n = $this->sequence->next((int) $report->school_id, 'rpt_'.$report->type);
            $next = AcademicReport::query()->create($this->row(
                $report->student, $report->type, $report->academicYear, $report->term, $payload, $extras, $user
            ) + [
                'school_id' => $report->school_id,
                'status' => 'draft',
                'reference' => sprintf('%s-%s-%06d', self::PREFIX[$report->type], now()->format('Y'), $n),
                'verification_token' => Str::lower(Str::random(32)),
                'version' => $report->version + 1,
                'supersedes_id' => $report->id,
                'prepared_by' => $user->id,
                'revision_reason' => $reason,
            ]);
            $this->event($next, 'revised', $reason, $user);

            return $next;
        });
    }

    public function verificationUrl(AcademicReport $report): string
    {
        return route('records.verify', ['token' => $report->verification_token]);
    }

    public function canView(User $user, AcademicReport $report): bool
    {
        if ($user->isFamilyPortal()) {
            return $report->isPublished() && $user->can('view', $report->student);
        }
        if (! $user->can('exams.view') && ! $user->can('exams.mark') && ! $user->can('exams.manage')) {
            return false;
        }

        return $this->canAccessStudent($user, $report->student);
    }

    /** @param  'prepare'|'approve'|'publish'  $level */
    public function can(User $user, string $level, ?Student $student): void
    {
        $ok = match ($level) {
            'publish' => $user->can('results.publish'),
            'approve' => $user->can('exams.manage') || $user->can('results.publish'),
            default => $user->can('exams.manage') || $user->can('exams.mark'),
        };
        if (! $ok) {
            throw AcademicRuleException::because('err.rpt.permission');
        }
        if ($level === 'prepare' && $student?->school_class_id && ! $user->can('exams.manage')
            && ! $this->scope->isClassTeacher($user, (int) $student->school_class_id)
            && ! $this->scope->canAccessClass($user, (int) $student->school_class_id, 'exams.manage')) {
            throw AcademicRuleException::because('err.rpt.notYourClass');
        }
        if ($student && ! $this->canAccessStudent($user, $student)) {
            throw AcademicRuleException::because('err.rpt.permission');
        }
    }

    public function canAccessStudent(User $user, Student $student): bool
    {
        if (! $user->school_id || (int) $user->school_id !== (int) $student->school_id) {
            return false;
        }
        if ($user->isFamilyPortal()) {
            return $user->can('view', $student);
        }
        if ($user->can('exams.manage') || $user->can('results.publish')) {
            return true;
        }
        if (! $student->school_class_id) {
            return false;
        }

        return $this->scope->canAccessClass($user, (int) $student->school_class_id, 'exams.manage');
    }

    public function flags(User $user, AcademicReport $report): array
    {
        $family = $user->isFamilyPortal();

        return [
            'view' => $this->canView($user, $report),
            'edit' => ! $family && $report->status === 'draft',
            'submit' => ! $family && $report->status === 'draft',
            'review' => ! $family && $report->status === 'submitted' && ($user->can('exams.manage') || $user->can('results.publish')),
            'approve' => ! $family && in_array($report->status, ['submitted', 'in_review'], true) && ($user->can('exams.manage') || $user->can('results.publish')),
            'return' => ! $family && in_array($report->status, ['submitted', 'in_review', 'approved'], true) && ($user->can('exams.manage') || $user->can('results.publish')),
            'publish' => ! $family && $report->status === 'approved' && $user->can('results.publish'),
            'revise' => ! $family && $report->status === 'published' && $user->can('results.publish'),
            'pdf' => $this->canView($user, $report),
            'family' => $family,
        ];
    }

    protected function compile(Student $student, string $type, AcademicYear $year, ?Term $term, array $extras): array
    {
        return match ($type) {
            'annual' => $this->compiler->compileAnnual($student, $year, $extras),
            'final' => $this->compiler->compileFinal($student, $year, $extras),
            default => $this->compiler->compileTermly($student, $term, $extras),
        };
    }

    protected function current(Student $student, string $type, AcademicYear $year, ?Term $term): ?AcademicReport
    {
        return AcademicReport::query()->where('student_id', $student->id)->where('type', $type)
            ->where('academic_year_id', $year->id)
            ->when($type === 'termly', fn ($q) => $q->where('term_id', $term?->id), fn ($q) => $q->whereNull('term_id'))
            ->whereNotIn('status', ['superseded'])
            ->first();
    }

    protected function row(Student $student, string $type, AcademicYear $year, ?Term $term, array $payload, array $extras, User $user): array
    {
        return $this->comments($extras) + [
            'type' => $type,
            'student_id' => $student->id,
            'school_class_id' => $student->school_class_id,
            'academic_year_id' => $year->id,
            'term_id' => $term?->id,
            'template_id' => $payload['template']['id'] ?? null,
            'payload' => $payload,
            'overall_average' => $payload['summary']['average'] ?? null,
            'overall_grade' => $payload['summary']['grade'] ?? null,
            'incomplete' => (bool) ($payload['incomplete'] ?? false),
            'allow_incomplete' => (bool) ($extras['allow_incomplete'] ?? false),
        ];
    }

    protected function comments(array $extras): array
    {
        return [
            'class_teacher_comment' => $extras['class_teacher_comment'] ?? null,
            'headteacher_comment' => $extras['headteacher_comment'] ?? null,
            'behaviour' => $extras['behaviour'] ?? null,
            'participation' => $extras['participation'] ?? null,
            'promotion_status' => $extras['promotion_status'] ?? null,
            'promotion_decision' => $extras['promotion_decision'] ?? null,
        ];
    }

    protected function extrasFrom(AcademicReport $report): array
    {
        return [
            'class_teacher_comment' => $report->class_teacher_comment,
            'headteacher_comment' => $report->headteacher_comment,
            'behaviour' => $report->behaviour,
            'participation' => $report->participation,
            'promotion_status' => $report->promotion_status,
            'promotion_decision' => $report->promotion_decision,
            'allow_incomplete' => $report->allow_incomplete,
        ];
    }

    protected function assertType(string $type): void
    {
        if (! in_array($type, AcademicReport::TYPES, true)) {
            throw AcademicRuleException::because('err.rpt.typeInvalid');
        }
    }

    protected function requireStatus(AcademicReport $report, array $allowed): void
    {
        if (! in_array($report->status, $allowed, true)) {
            throw AcademicRuleException::because('err.rpt.status.'.$report->status);
        }
    }

    protected function event(AcademicReport $report, string $event, ?string $comment, ?User $user): void
    {
        AcademicReportEvent::query()->create([
            'school_id' => $report->school_id,
            'academic_report_id' => $report->id,
            'event' => $event,
            'comment' => $comment ? mb_substr($comment, 0, 500) : null,
            'user_id' => $user?->id,
        ]);
    }
}
