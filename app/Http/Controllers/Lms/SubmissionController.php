<?php

namespace App\Http\Controllers\Lms;

use App\Http\Controllers\Concerns\UsesLms;
use App\Http\Controllers\Controller;
use App\Models\AssignmentSubmission;
use App\Models\LmsFile;
use App\Services\Lms\AssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Staff-side marking of assignment submissions. */
class SubmissionController extends Controller
{
    use UsesLms;

    public function __construct(protected AssignmentService $service) {}

    public function show(AssignmentSubmission $submission): Response
    {
        $assignment = $submission->assignment()->with('rubric')->first();
        $course = $assignment->course;
        $role = $this->author($course);

        $history = AssignmentSubmission::query()->where('assignment_id', $assignment->id)->where('student_id', $submission->student_id)->orderBy('attempt_no')->get();

        return Inertia::render('Lms/SubmissionGrade', $this->shellProps() + $this->courseHeader($course->loadMissing('subject'), $role) + [
            'assignment' => [
                'id' => $assignment->id, 'title' => $assignment->title, 'max_score' => $assignment->max_score, 'due_at' => $assignment->due_at?->toDateTimeString(),
                'criteria' => $assignment->rubric?->criteria ?? [], 'url' => route('lms.assignments.show', $assignment), 'max_attempts' => $assignment->max_attempts,
            ],
            'student' => ['name' => $submission->student?->full_name, 'number' => $submission->student?->student_number],
            'submission' => [
                'id' => $submission->id, 'attempt_no' => $submission->attempt_no, 'status' => $submission->status, 'is_late' => (bool) $submission->is_late,
                'body' => $submission->body, 'submitted_at' => $submission->submitted_at?->toDateTimeString(),
                'score' => $submission->score !== null ? (float) $submission->score : null, 'feedback' => $submission->feedback,
                'rubric_scores' => $submission->rubric_scores ?? [],
                'files' => LmsFile::query()->where('owner_type', 'submission')->where('owner_id', $submission->id)->get()
                    ->map(fn ($f) => ['id' => $f->id, 'name' => $f->original_name, 'size' => $f->size, 'file_url' => route('lms.files.show', $f)])->values(),
            ],
            'history' => $history->map(fn ($s) => ['id' => $s->id, 'attempt_no' => $s->attempt_no, 'status' => $s->status, 'current' => $s->id === $submission->id, 'url' => route('lms.submissions.show', $s)])->values(),
        ]);
    }

    public function grade(Request $request, AssignmentSubmission $submission): RedirectResponse
    {
        $this->author($submission->assignment->course);
        $v = $request->validate([
            'score' => ['nullable', 'numeric', 'min:0', 'max:1000'], 'rubric_scores' => ['nullable', 'array'], 'rubric_scores.*' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'feedback' => ['nullable', 'string', 'max:5000'],
        ]);
        abort_if($submission->status === 'returned', 409);

        return $this->attempt(function () use ($request, $submission, $v) {
            $this->service->grade($request->user(), $submission, $v['score'] ?? null, $v['rubric_scores'] ?? [], $v['feedback'] ?? null);

            return back()->with('success', 'ok.lms.graded');
        });
    }

    public function returnBack(Request $request, AssignmentSubmission $submission): RedirectResponse
    {
        $this->author($submission->assignment->course);
        $v = $request->validate(['feedback' => ['required', 'string', 'max:5000']]);

        return $this->attempt(function () use ($request, $submission, $v) {
            $this->service->returnForRevision($request->user(), $submission, $v['feedback']);

            return back()->with('success', 'ok.lms.returned');
        });
    }
}
