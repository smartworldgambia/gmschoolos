<?php

namespace App\Services\Lms;

use App\Exceptions\AcademicRuleException;
use App\Models\Assignment;
use App\Models\AssignmentRubric;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/** Assignments, rubrics, submissions (late and resubmission rules) and marking. */
class AssignmentService
{
    public function __construct(protected CourseService $courses, protected LmsAccess $access) {}

    /**
     * @param  array<string,mixed>  $data  validated assignment fields + optional rubric_title and criteria[{label,max_points}]
     */
    public function save(User $actor, Course $course, ?Assignment $assignment, array $data): Assignment
    {
        $max = (int) ($data['max_score'] ?? 100);
        if ($max < 1) {
            throw AcademicRuleException::because('err.lms.maxScore');
        }
        if (empty($data['accepts_text']) && empty($data['accepts_files'])) {
            throw AcademicRuleException::because('err.lms.noSubmissionType');
        }
        $due = $data['due_at'] ?? null;
        $closes = $data['closes_at'] ?? null;
        if ($due && $closes && strtotime((string) $closes) < strtotime((string) $due)) {
            throw AcademicRuleException::because('err.lms.closesBeforeDue');
        }

        $criteria = collect($data['criteria'] ?? [])
            ->map(fn ($c) => ['label' => mb_substr(trim((string) ($c['label'] ?? '')), 0, 150), 'max_points' => (int) ($c['max_points'] ?? 0)])
            ->filter(fn ($c) => $c['label'] !== '' || $c['max_points'] > 0)->values();
        if ($criteria->isNotEmpty()) {
            if ($criteria->contains(fn ($c) => $c['label'] === '' || $c['max_points'] < 1)) {
                throw AcademicRuleException::because('err.lms.rubricInvalid');
            }
            if ($criteria->sum('max_points') !== $max) {
                throw AcademicRuleException::because('err.lms.rubricTotal');
            }
        }

        return DB::transaction(function () use ($actor, $course, $assignment, $data, $max, $criteria) {
            $attrs = [
                'title' => $data['title'], 'instructions' => $data['instructions'] ?? null, 'due_at' => $data['due_at'] ?? null,
                'closes_at' => $data['closes_at'] ?? null, 'max_score' => $max, 'status' => $data['status'] ?? 'draft',
                'publish_at' => $data['publish_at'] ?? null, 'late_policy' => $data['late_policy'] ?? 'allow',
                'max_attempts' => max(1, min(10, (int) ($data['max_attempts'] ?? 1))),
                'accepts_text' => (bool) ($data['accepts_text'] ?? true), 'accepts_files' => (bool) ($data['accepts_files'] ?? true),
            ];
            if ($assignment) {
                $assignment->update($attrs);
            } else {
                $assignment = Assignment::query()->create($attrs + ['school_id' => $course->school_id, 'course_id' => $course->id, 'created_by' => $actor->id]);
            }

            if ($criteria->isEmpty()) {
                AssignmentRubric::query()->where('assignment_id', $assignment->id)->delete();
            } else {
                AssignmentRubric::query()->updateOrCreate(
                    ['assignment_id' => $assignment->id],
                    ['school_id' => $course->school_id, 'title' => mb_substr((string) ($data['rubric_title'] ?? 'Rubric'), 0, 150) ?: 'Rubric', 'criteria' => $criteria->all()]
                );
            }

            return $assignment->fresh('rubric');
        });
    }

    /** The learner's attempts, newest last. */
    public function attempts(Assignment $assignment, Student $student)
    {
        return AssignmentSubmission::query()->where('assignment_id', $assignment->id)->where('student_id', $student->id)->orderBy('attempt_no')->get();
    }

    /** May this learner submit (or resubmit) now? Returns null when yes, else an `err.lms.*` code. */
    public function submitBlock(Assignment $assignment, Student $student): ?string
    {
        if (! $assignment->isLive()) {
            return 'err.lms.assignmentNotOpen';
        }
        if ($assignment->closes_at && $assignment->closes_at->lt(now())) {
            return 'err.lms.assignmentClosed';
        }
        if ($assignment->late_policy === 'block' && $assignment->due_at && $assignment->due_at->lt(now())) {
            return 'err.lms.lateBlocked';
        }
        $attempts = $this->attempts($assignment, $student);
        if ($attempts->count() >= $assignment->max_attempts) {
            return 'err.lms.noMoreAttempts';
        }
        if ($attempts->isNotEmpty() && $attempts->last()->status === 'graded') {
            return 'err.lms.alreadyGraded'; // the teacher must return it before another attempt
        }

        return null;
    }

    /** @param array<int,UploadedFile> $files */
    public function submit(Course $course, Assignment $assignment, Student $student, User $user, ?string $body, array $files): AssignmentSubmission
    {
        if ($block = $this->submitBlock($assignment, $student)) {
            throw AcademicRuleException::because($block);
        }
        $body = $body !== null ? trim($body) : null;
        $hasText = $assignment->accepts_text && $body !== null && $body !== '';
        $hasFiles = $assignment->accepts_files && count($files) > 0;
        if (($body && ! $assignment->accepts_text) || (count($files) && ! $assignment->accepts_files)) {
            throw AcademicRuleException::because('err.lms.submissionTypeRefused');
        }
        if (! $hasText && ! $hasFiles) {
            throw AcademicRuleException::because('err.lms.emptySubmission');
        }

        return DB::transaction(function () use ($course, $assignment, $student, $user, $body, $files) {
            $no = (int) AssignmentSubmission::query()->where('assignment_id', $assignment->id)->where('student_id', $student->id)->max('attempt_no') + 1;
            $submission = AssignmentSubmission::query()->create([
                'school_id' => $course->school_id, 'assignment_id' => $assignment->id, 'student_id' => $student->id, 'attempt_no' => $no,
                'body' => $body ?: null, 'submitted_at' => now(), 'is_late' => (bool) ($assignment->due_at && $assignment->due_at->lt(now())), 'status' => 'submitted',
            ]);
            foreach ($files as $file) {
                $this->courses->storeUpload($file, $course, 'submission', $submission->id, $user);
            }
            $this->access->touch($course, $student);

            return $submission;
        });
    }

    /**
     * Mark a submission. With a rubric the score is the SUM of the criterion points (never typed separately), so the two can
     * never disagree. Without one a plain score between 0 and the assignment maximum is required.
     *
     * @param  array<int|string,mixed>  $rubricScores  criterion index => points
     */
    public function grade(User $grader, AssignmentSubmission $submission, mixed $score, array $rubricScores, ?string $feedback): AssignmentSubmission
    {
        $assignment = $submission->assignment()->with('rubric')->first();
        $criteria = $assignment->rubric?->criteria ?? [];

        if ($criteria) {
            $scores = [];
            $total = 0.0;
            foreach ($criteria as $i => $c) {
                $p = $rubricScores[$i] ?? null;
                if (! is_numeric($p) || (float) $p < 0 || (float) $p > (float) $c['max_points']) {
                    throw AcademicRuleException::because('err.lms.rubricScore');
                }
                $scores[$i] = round((float) $p, 2);
                $total += (float) $p;
            }
            $final = round($total, 2);
            $submission->rubric_scores = $scores;
        } else {
            if (! is_numeric($score) || (float) $score < 0 || (float) $score > (float) $assignment->max_score) {
                throw AcademicRuleException::because('err.lms.scoreRange');
            }
            $final = round((float) $score, 2);
        }

        $submission->forceFill([
            'score' => $final, 'feedback' => $feedback !== null ? trim($feedback) : null, 'status' => 'graded',
            'graded_by' => $grader->id, 'graded_at' => now(),
        ])->save();

        return $submission;
    }

    public function returnForRevision(User $grader, AssignmentSubmission $submission, ?string $feedback): void
    {
        $assignment = $submission->assignment;
        $attempts = AssignmentSubmission::query()->where('assignment_id', $assignment->id)->where('student_id', $submission->student_id)->count();
        if ($attempts >= $assignment->max_attempts) {
            throw AcademicRuleException::because('err.lms.noMoreAttempts');
        }
        $submission->forceFill([
            'status' => 'returned', 'feedback' => $feedback !== null ? trim($feedback) : null, 'score' => null, 'rubric_scores' => null,
            'graded_by' => $grader->id, 'graded_at' => now(),
        ])->save();
    }
}
