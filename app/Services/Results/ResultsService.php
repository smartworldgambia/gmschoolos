<?php

namespace App\Services\Results;

use App\Exceptions\AcademicRuleException;
use App\Models\ActivityLog;
use App\Models\AssessmentCategory;
use App\Models\Examination;
use App\Models\Grade;
use App\Models\GradeChange;
use App\Models\GradeScore;
use App\Models\GradingBand;
use App\Models\GradingScale;
use App\Models\Student;
use App\Models\User;
use App\Notifications\ExamPublishedNotification;
use App\Services\Academics\TeacherScope;
use App\Models\ClassSubject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Grading configuration, weighted mark entry, the approval workflow and the result-change history.
 *
 * Workflow:  draft (marks open)  ->  submitted  ->  approved  ->  published
 *   - mark entry is open only in `draft`; `submitted`/`approved` are locked until an exam manager returns it,
 *   - `published` results can be corrected only by someone holding `results.publish`, with a reason, and every
 *     correction is written to `grade_changes` (append-only) and stamps `grades.revised_at`.
 *
 * The scale and categories are PER SCHOOL. A school that has configured nothing receives the editable starting
 * points from config('schoolos.results') - they are not an official grading scheme.
 */
class ResultsService
{
    public function __construct(protected TeacherScope $scope) {}

    // ------------------------------------------------------------------ configuration

    public function ensureDefaults(): void
    {
        if (! GradingScale::query()->exists()) {
            $scale = GradingScale::query()->create(['name' => 'Default scale', 'is_default' => true]);
            foreach ((array) config('schoolos.results.default_scale', []) as $band) {
                GradingBand::query()->create([
                    'grading_scale_id' => $scale->id, 'school_id' => $scale->school_id,
                    'letter' => $band['letter'], 'min_score' => $band['min'], 'remark' => $band['remark'] ?? null,
                ]);
            }
        }

        if (! AssessmentCategory::query()->exists()) {
            foreach ((array) config('schoolos.results.default_categories', []) as $i => $c) {
                AssessmentCategory::query()->create($c + ['is_active' => true, 'sort_order' => $i]);
            }
        }
    }

    public function scale(): GradingScale
    {
        $this->ensureDefaults();

        return GradingScale::query()->where('is_default', true)->with('bands')->firstOrFail();
    }

    /** @return Collection<int,AssessmentCategory> */
    public function categories(bool $onlyActive = true): Collection
    {
        $this->ensureDefaults();

        return AssessmentCategory::query()->when($onlyActive, fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')->orderBy('id')->get();
    }

    /** @return array{letter:string,remark:?string} */
    public function letterFor(?float $total): array
    {
        if ($total === null) {
            return ['letter' => null, 'remark' => null];
        }
        $band = $this->scale()->bands->first(fn (GradingBand $b) => (float) $b->min_score <= $total)
            ?? $this->scale()->bands->last();

        return ['letter' => $band?->letter, 'remark' => $band?->remark];
    }

    /**
     * @param  array<int,array{id?:int,letter:string,min_score:float|int|string,remark?:?string}>  $bands
     */
    public function saveScale(array $bands): void
    {
        $bands = collect($bands)->map(fn ($b) => [
            'letter' => trim((string) $b['letter']), 'min_score' => (float) $b['min_score'], 'remark' => $b['remark'] ?? null,
        ])->sortByDesc('min_score')->values();

        if ($bands->isEmpty() || $bands->pluck('letter')->duplicates()->isNotEmpty() || $bands->pluck('min_score')->duplicates()->isNotEmpty()) {
            throw AcademicRuleException::because('err.grading.bands');
        }
        if ((float) $bands->last()['min_score'] !== 0.0) {
            throw AcademicRuleException::because('err.grading.mustCoverZero');
        }
        if ($bands->contains(fn ($b) => $b['min_score'] < 0 || $b['min_score'] > 100 || $b['letter'] === '')) {
            throw AcademicRuleException::because('err.grading.bands');
        }

        $scale = $this->scale();
        DB::transaction(function () use ($scale, $bands) {
            GradingBand::query()->where('grading_scale_id', $scale->id)->delete();
            foreach ($bands as $b) {
                GradingBand::query()->create($b + ['grading_scale_id' => $scale->id, 'school_id' => $scale->school_id]);
            }
            $this->audit('grading_scale_saved', $bands->count().' band(s)');
        });
    }

    /**
     * Categories must have unique codes and the ACTIVE ones must weigh exactly 100. Existing marks are never
     * rewritten: results already stored keep the totals they were published with.
     *
     * @param  array<int,array<string,mixed>>  $rows
     */
    public function saveCategories(array $rows): void
    {
        $rows = collect($rows)->map(fn ($r) => [
            'id' => $r['id'] ?? null, 'name' => trim((string) $r['name']), 'code' => strtoupper(trim((string) $r['code'])),
            'type' => $r['type'], 'weight' => (float) $r['weight'], 'max_score' => (float) $r['max_score'],
            'is_active' => (bool) ($r['is_active'] ?? true),
        ]);

        if ($rows->pluck('code')->duplicates()->isNotEmpty() || $rows->contains(fn ($r) => ! in_array($r['type'], ['ca', 'exam'], true) || $r['max_score'] <= 0 || $r['weight'] < 0)) {
            throw AcademicRuleException::because('err.assessment.invalid');
        }
        if (abs($rows->where('is_active', true)->sum('weight') - 100.0) > 0.001) {
            throw AcademicRuleException::because('err.assessment.weights');
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows->values() as $i => $r) {
                $attrs = collect($r)->except('id')->all() + ['sort_order' => $i];
                if ($r['id']) {
                    AssessmentCategory::query()->whereKey($r['id'])->update($attrs);
                } else {
                    AssessmentCategory::query()->create($attrs);
                }
            }
            $this->audit('assessment_categories_saved', $rows->count().' category(ies)');
        });
    }

    // ------------------------------------------------------------------ mark entry

    /**
     * @param  array<int,float|int|string|null>  $scores  assessment_category_id => mark (out of that category's max)
     *
     * @throws AcademicRuleException
     */
    public function saveMarks(User $user, Examination $exam, Student $student, int $subjectId, array $scores, ?string $remark = null, ?string $reason = null): Grade
    {
        $correction = false;
        if ($exam->status === 'published') {
            if (! $user->can('results.publish')) {
                throw AcademicRuleException::because('err.exam.published');
            }
            if (blank($reason) || mb_strlen(trim($reason)) < 5) {
                throw AcademicRuleException::because('err.exam.reasonRequired');
            }
            $correction = true;
        } elseif ($exam->status !== 'draft') {
            throw AcademicRuleException::because('err.exam.locked');
        }

        if (! $user->can('exams.mark') && ! $user->can('exams.manage')) {
            throw AcademicRuleException::because('err.exam.forbidden');
        }
        if ($student->status !== 'active') {
            throw AcademicRuleException::because('err.exam.studentInactive');
        }
        if (! $student->school_class_id) {
            throw AcademicRuleException::because('err.exam.noClass');
        }

        $this->assertSubjectAllowed($user, $student, $subjectId);

        $categories = $this->categories()->keyBy('id');
        $clean = [];
        foreach ($scores as $categoryId => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $category = $categories[(int) $categoryId] ?? null;
            if (! $category || ! is_numeric($value)) {
                throw AcademicRuleException::because('err.exam.scoreInvalid');
            }
            $value = round((float) $value, 2);
            if ($value < 0 || $value > (float) $category->max_score) {
                throw AcademicRuleException::because('err.exam.scoreRange');
            }
            $clean[(int) $categoryId] = $value;
        }
        if ($clean === []) {
            throw AcademicRuleException::because('err.exam.scoreRequired');
        }

        return DB::transaction(function () use ($user, $exam, $student, $subjectId, $clean, $remark, $reason, $correction, $categories) {
            $grade = Grade::query()->where('examination_id', $exam->id)
                ->where('student_id', $student->id)->where('subject_id', $subjectId)->first();
            $old = $grade ? $this->snapshot($grade) : null;

            if (! $grade) {
                $grade = Grade::query()->create([
                    'school_id' => $exam->school_id, 'examination_id' => $exam->id, 'student_id' => $student->id,
                    'subject_id' => $subjectId, 'term_id' => $exam->term_id, 'is_published' => $correction,
                    'level_id' => $student->schoolClass?->level_id,
                ]);
            }

            foreach ($clean as $categoryId => $value) {
                GradeScore::query()->updateOrCreate(
                    ['grade_id' => $grade->id, 'assessment_category_id' => $categoryId],
                    ['school_id' => $exam->school_id, 'score' => $value]
                );
            }

            $all = GradeScore::query()->where('grade_id', $grade->id)->pluck('score', 'assessment_category_id');
            $calc = $this->compute($all->all(), $categories);
            $letter = $this->letterFor($calc['total']);

            $grade->update([
                'ca_score' => $calc['ca'], 'exam_score' => $calc['exam'], 'total' => $calc['total'],
                'letter_grade' => $letter['letter'], 'remark' => $remark ?? $grade->remark,
                'entered_by' => $user->id, 'revised_at' => $correction ? now() : $grade->revised_at,
            ]);

            $new = $this->snapshot($grade->fresh());
            if ($old !== $new) {
                GradeChange::query()->create([
                    'school_id' => $exam->school_id, 'grade_id' => $grade->id, 'examination_id' => $exam->id,
                    'student_id' => $student->id, 'subject_id' => $subjectId,
                    'event' => $correction ? 'corrected_after_publication' : ($old ? 'updated' : 'entered'),
                    'old_values' => $old, 'new_values' => $new, 'reason' => $correction ? trim((string) $reason) : null,
                    'changed_by' => $user->id,
                ]);
            }

            return $grade->fresh();
        });
    }

    /** Subject must be in the student's class curriculum (when one exists) and the user must be allowed to mark it. */
    protected function assertSubjectAllowed(User $user, Student $student, int $subjectId): void
    {
        $hasCurriculum = ClassSubject::query()->where('school_class_id', $student->school_class_id)->exists();
        $inCurriculum = ClassSubject::query()->where('school_class_id', $student->school_class_id)->where('subject_id', $subjectId)->exists();

        if ($hasCurriculum && ! $inCurriculum) {
            throw AcademicRuleException::because('err.exam.subjectNotInClass');
        }
        if (! $this->scope->canTeach($user, (int) $student->school_class_id, $subjectId, 'exams.manage')) {
            throw AcademicRuleException::because('err.exam.notYourSubject');
        }
    }

    /**
     * @param  array<int,float|string>  $scores
     * @return array{ca:float,exam:float,total:float}
     */
    public function compute(array $scores, Collection $categories): array
    {
        $ca = 0.0;
        $exam = 0.0;
        foreach ($scores as $categoryId => $score) {
            $c = $categories[(int) $categoryId] ?? null;
            if (! $c || (float) $c->max_score <= 0) {
                continue;
            }
            $weighted = (float) $score / (float) $c->max_score * (float) $c->weight;
            $c->type === 'ca' ? $ca += $weighted : $exam += $weighted;
        }

        return ['ca' => round($ca, 2), 'exam' => round($exam, 2), 'total' => round($ca + $exam, 2)];
    }

    protected function snapshot(Grade $grade): array
    {
        return [
            'ca_score' => $grade->ca_score !== null ? (float) $grade->ca_score : null,
            'exam_score' => $grade->exam_score !== null ? (float) $grade->exam_score : null,
            'total' => $grade->total !== null ? (float) $grade->total : null,
            'letter_grade' => $grade->letter_grade,
            'remark' => $grade->remark,
            'scores' => GradeScore::query()->where('grade_id', $grade->id)->orderBy('assessment_category_id')
                ->pluck('score', 'assessment_category_id')->map(fn ($v) => (float) $v)->all(),
        ];
    }

    // ------------------------------------------------------------------ workflow

    public function submit(User $user, Examination $exam): Examination
    {
        $this->requireStatus($exam, 'draft');
        if (! $user->can('exams.mark') && ! $user->can('exams.manage')) {
            throw AcademicRuleException::because('err.exam.forbidden');
        }

        $grades = Grade::query()->where('examination_id', $exam->id)->withCount('scores')->get();
        if ($grades->isEmpty()) {
            throw AcademicRuleException::because('err.exam.noMarks');
        }
        $needed = $this->categories()->count();
        $incomplete = $grades->contains(fn (Grade $g) => $g->scores_count < $needed && ! ($g->scores_count === 0 && $g->total !== null));
        if ($incomplete) {
            throw AcademicRuleException::because('err.exam.incomplete');
        }

        $exam->update(['status' => 'submitted', 'submitted_at' => now(), 'submitted_by' => $user->id, 'returned_reason' => null]);
        $this->audit('exam_submitted', $exam->name, $exam->school_id);

        return $exam->fresh();
    }

    public function approve(User $user, Examination $exam): Examination
    {
        $this->requireStatus($exam, 'submitted');
        if (! $user->can('exams.manage')) {
            throw AcademicRuleException::because('err.exam.forbidden');
        }

        $exam->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $user->id]);
        $this->audit('exam_approved', $exam->name, $exam->school_id);

        return $exam->fresh();
    }

    public function returnToDraft(User $user, Examination $exam, string $reason): Examination
    {
        if (! in_array($exam->status, ['submitted', 'approved'], true)) {
            throw AcademicRuleException::because('err.exam.badState');
        }
        if (! $user->can('exams.manage')) {
            throw AcademicRuleException::because('err.exam.forbidden');
        }
        if (mb_strlen(trim($reason)) < 5) {
            throw AcademicRuleException::because('err.exam.reasonRequired');
        }

        $exam->update([
            'status' => 'draft', 'returned_reason' => trim($reason),
            'submitted_at' => null, 'submitted_by' => null, 'approved_at' => null, 'approved_by' => null,
        ]);
        $this->audit('exam_returned', $exam->name, $exam->school_id);

        return $exam->fresh();
    }

    public function publish(User $user, Examination $exam): Examination
    {
        $this->requireStatus($exam, 'approved');
        if (! $user->can('results.publish')) {
            throw AcademicRuleException::because('err.exam.forbidden');
        }

        DB::transaction(function () use ($exam, $user) {
            $exam->update(['status' => 'published', 'published_at' => now(), 'published_by' => $user->id]);
            Grade::query()->where('examination_id', $exam->id)->update(['is_published' => true]);
            $this->audit('exam_published', $exam->name, $exam->school_id);
        });

        $studentIds = Grade::query()->where('examination_id', $exam->id)->pluck('student_id')->unique();
        $students = Student::query()->whereIn('id', $studentIds)->with('guardians')->get();
        $userIds = $students->pluck('user_id')->merge($students->flatMap(fn (Student $s) => $s->guardians->pluck('user_id')))->filter()->unique();
        $recipients = User::query()->where('school_id', $exam->school_id)->whereIn('id', $userIds)->get();
        Notification::send($recipients, new ExamPublishedNotification($exam));

        return $exam->fresh();
    }

    protected function requireStatus(Examination $exam, string $status): void
    {
        if ($exam->status !== $status) {
            throw AcademicRuleException::because('err.exam.badState');
        }
    }

    protected function audit(string $event, string $description, ?int $schoolId = null): void
    {
        ActivityLog::query()->create([
            'school_id' => $schoolId ?? app(\App\Support\TenantContext::class)->id() ?? auth()->user()?->school_id,
            'user_id' => auth()->id(), 'event' => $event, 'description' => $description,
        ]);
    }

    // ------------------------------------------------------------------ report cards / history

    /**
     * Result rows for a learner. Portal users (student / parent) only ever receive PUBLISHED results.
     *
     * @return array{rows:Collection,average:?float,letter:?string}
     */
    public function reportFor(Student $student, bool $publishedOnly, ?int $examinationId = null): array
    {
        $this->ensureDefaults();
        $categories = $this->categories(false)->keyBy('id');

        $rows = Grade::query()->with(['subject', 'examination.term', 'scores'])
            ->where('student_id', $student->id)
            ->when($examinationId, fn ($q) => $q->where('examination_id', $examinationId))
            ->when($publishedOnly, fn ($q) => $q->where(fn ($w) => $w->where('is_published', true)->orWhereNull('examination_id')))
            ->get()
            ->map(fn (Grade $g) => [
                'subject' => $g->subject?->name,
                'examination' => $g->examination?->name,
                'examination_id' => $g->examination_id,
                'term' => $g->examination?->term?->name,
                'ca_score' => $g->ca_score, 'exam_score' => $g->exam_score, 'total' => $g->total,
                'letter_grade' => $g->letter_grade, 'remark' => $g->remark,
                'revised' => $g->revised_at !== null,
                'breakdown' => $g->scores->map(fn (GradeScore $s) => [
                    'category' => $categories[$s->assessment_category_id]->name ?? null,
                    'score' => $s->score, 'max' => $categories[$s->assessment_category_id]->max_score ?? null,
                ])->values(),
            ]);

        $avg = $rows->avg('total');

        return [
            'rows' => $rows,
            'average' => $avg !== null ? round((float) $avg, 1) : null,
            'letter' => $avg !== null ? $this->letterFor((float) $avg)['letter'] : null,
        ];
    }

    /** Per-examination averages across terms (published only for portal users). */
    public function history(Student $student, bool $publishedOnly): Collection
    {
        return Grade::query()->with('examination.term')->where('student_id', $student->id)
            ->whereNotNull('examination_id')
            ->when($publishedOnly, fn ($q) => $q->where('is_published', true))
            ->get()->groupBy('examination_id')
            ->map(function (Collection $set) {
                $exam = $set->first()->examination;
                $avg = (float) $set->avg('total');

                return [
                    'examination' => $exam?->name, 'term' => $exam?->term?->name, 'subjects' => $set->count(),
                    'average' => round($avg, 1), 'letter' => $this->letterFor($avg)['letter'],
                    'date' => $exam?->published_at?->toDateString() ?? $exam?->starts_on?->toDateString(),
                ];
            })->sortBy('date')->values();
    }
}
