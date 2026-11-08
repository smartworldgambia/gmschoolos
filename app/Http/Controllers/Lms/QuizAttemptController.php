<?php

namespace App\Http\Controllers\Lms;

use App\Exceptions\AcademicRuleException;
use App\Http\Controllers\Concerns\UsesLms;
use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\QuizQuestion;
use App\Services\Lms\LmsAccess;
use App\Services\Lms\QuizService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Learner side of quizzes. The server decides everything that matters: the questions drawn, their order, the deadline,
 * what is graded and what may be shown. Correct answers are never sent while an attempt is running.
 */
class QuizAttemptController extends Controller
{
    use UsesLms;

    public function __construct(protected QuizService $service) {}

    public function show(Quiz $quiz): Response
    {
        $course = $quiz->course;
        $role = $this->role($course);
        $header = $this->shellProps() + $this->courseHeader($course->loadMissing('subject'), $role);
        $summary = [
            'id' => $quiz->id, 'title' => $quiz->title, 'description' => $quiz->description, 'status' => $quiz->status,
            'opens_at' => $quiz->opens_at?->toDateTimeString(), 'closes_at' => $quiz->closes_at?->toDateTimeString(),
            'time_limit_minutes' => $quiz->time_limit_minutes, 'max_attempts' => $quiz->max_attempts, 'max_score' => $quiz->max_score,
            'grade_method' => $quiz->grade_method, 'release_mode' => $quiz->release_mode, 'release_at' => $quiz->release_at?->toDateTimeString(),
            'shuffle_questions' => (bool) $quiz->shuffle_questions,
        ];

        if (LmsAccess::authors($role)) {
            return Inertia::render('Lms/QuizShow', $header + [
                'mode' => 'preview', 'quiz' => $summary + ['edit_url' => route('lms.quizzes.edit', $quiz), 'attempts_url' => route('lms.quizzes.attempts', $quiz)],
                'questions' => $quiz->questions()->get()->map(fn (QuizQuestion $q) => [
                    'id' => $q->id, 'type' => $q->type, 'prompt' => $q->prompt, 'points' => $q->points, 'options' => $q->options ?? [],
                    'correct_answer' => $q->correct_answer, 'accepted_answers' => $q->accepted_answers ?? [],
                ])->values(),
                'pulls' => $quiz->pulls()->with('bank:id,title')->get()->map(fn ($p) => ['bank' => $p->bank?->title, 'count' => $p->count])->values(),
            ]);
        }

        abort_unless($this->lms()->quizVisible($quiz), 404);
        $student = $this->me();
        $running = QuizAttempt::query()->where('quiz_id', $quiz->id)->where('student_id', $student->id)->where('status', 'in_progress')->latest('id')->first();
        if ($running && $this->service->expireIfNeeded($running)) {
            $running = null;
        }

        if ($running) {
            return Inertia::render('Lms/QuizShow', $header + [
                'mode' => 'taking', 'quiz' => $summary,
                'attempt' => [
                    'id' => $running->id, 'attempt_no' => $running->attempt_no, 'due_at' => $running->due_at?->toIso8601String(),
                    'seconds_left' => $running->due_at ? max(0, now()->diffInSeconds($running->due_at, false)) : null,
                    'save_url' => route('lms.attempts.save', $running), 'submit_url' => route('lms.attempts.submit', $running),
                ],
                'questions' => $this->learnerQuestions($running),
                'answers' => QuizAttemptAnswer::query()->where('quiz_attempt_id', $running->id)->pluck('answer', 'quiz_question_id'),
            ]);
        }

        $attempts = QuizAttempt::query()->where('quiz_id', $quiz->id)->where('student_id', $student->id)->orderBy('attempt_no')->get();
        $result = $this->service->result($quiz, $student);

        return Inertia::render('Lms/QuizShow', $header + [
            'mode' => 'intro', 'quiz' => $summary,
            'attempts' => $attempts->map(function (QuizAttempt $a) use ($quiz) {
                $visible = $this->service->marksVisible($quiz, $a);

                return [
                    'id' => $a->id, 'attempt_no' => $a->attempt_no, 'status' => $a->status, 'submitted_at' => $a->submitted_at?->toDateTimeString(),
                    'timed_out' => (bool) $a->timed_out, 'awaiting_marking' => $a->status === 'submitted' && $a->grading_status === 'needs_grading',
                    'withheld' => $a->status === 'submitted' && $a->grading_status === 'graded' && ! $visible,
                    'score' => $visible ? (float) $a->score : null, 'max_score' => $visible ? (float) $a->max_score : null,
                    'review_url' => $a->status === 'submitted' ? route('lms.attempts.show', $a) : null,
                ];
            })->values(),
            'result' => $quiz->resultsReleased() ? $result : null,
            'block' => $this->service->startBlock($quiz, $student),
            'startUrl' => route('lms.quizzes.start', $quiz),
        ]);
    }

    /** Questions for an attempt, in the attempt's own order, with options in the attempt's own order, and no answers. */
    protected function learnerQuestions(QuizAttempt $attempt): array
    {
        $questions = QuizQuestion::query()->whereIn('id', $attempt->question_order ?? [])->get()->keyBy('id');
        $orders = $attempt->option_orders ?? [];

        return collect($attempt->question_order ?? [])->map(function ($qid) use ($questions, $orders) {
            $q = $questions->get($qid);
            if (! $q) {
                return null;
            }
            $options = $q->options ?? [];
            if (isset($orders[$q->id])) {
                $options = collect($orders[$q->id])->map(fn ($i) => $q->options[$i] ?? null)->filter(fn ($o) => $o !== null)->values()->all();
            }

            return ['id' => $q->id, 'type' => $q->type, 'prompt' => $q->prompt, 'points' => $q->points, 'options' => $options];
        })->filter()->values()->all();
    }

    public function start(Quiz $quiz): RedirectResponse
    {
        $course = $quiz->course;
        $this->role($course, [LmsAccess::LEARNER]);
        $student = $this->me();
        abort_unless($student && $this->lms()->quizVisible($quiz), 404);

        return $this->attempt(function () use ($course, $quiz, $student) {
            $this->service->start($course, $quiz, $student);

            return redirect()->route('lms.quizzes.show', $quiz);
        });
    }

    protected function ownAttempt(QuizAttempt $attempt): void
    {
        $this->role($attempt->quiz->course, [LmsAccess::LEARNER]);
        $student = $this->me();
        abort_unless($student && (int) $attempt->student_id === (int) $student->id, 404);
    }

    /** Autosave while answering. Returns JSON so the page never reloads. */
    public function save(Request $request, QuizAttempt $attempt): JsonResponse
    {
        $this->ownAttempt($attempt);
        $v = $request->validate(['answers' => ['required', 'array', 'max:200'], 'answers.*' => ['nullable', 'string', 'max:10000']]);

        try {
            $this->service->saveAnswers($attempt, $v['answers']);
        } catch (AcademicRuleException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 409);
        }

        return response()->json(['ok' => true, 'saved_at' => now()->toIso8601String()]);
    }

    public function submit(Request $request, QuizAttempt $attempt): RedirectResponse
    {
        $this->ownAttempt($attempt);
        $v = $request->validate(['answers' => ['nullable', 'array', 'max:200'], 'answers.*' => ['nullable', 'string', 'max:10000']]);

        return $this->attempt(function () use ($attempt, $v) {
            $done = $this->service->submit($attempt->quiz->course, $attempt, $v['answers'] ?? []);

            return redirect()->route('lms.quizzes.show', $done->quiz_id)->with('success', $done->timed_out ? 'ok.lms.quizTimedOut' : 'ok.lms.quizSubmitted');
        });
    }

    /** The learner's own review of a finished attempt. Marks and correct answers appear only when the release rule allows. */
    public function review(QuizAttempt $attempt): Response
    {
        $quiz = $attempt->quiz;
        $course = $quiz->course;
        $this->ownAttempt($attempt);
        abort_unless($attempt->status === 'submitted', 404);
        $role = LmsAccess::LEARNER;

        $marks = $this->service->marksVisible($quiz, $attempt);
        $answersShown = $this->service->answersVisible($quiz, $attempt);
        $questions = QuizQuestion::query()->whereIn('id', $attempt->question_order ?? [])->get()->keyBy('id');
        $given = QuizAttemptAnswer::query()->where('quiz_attempt_id', $attempt->id)->get()->keyBy('quiz_question_id');

        return Inertia::render('Lms/QuizReview', $this->shellProps() + $this->courseHeader($course->loadMissing('subject'), $role) + [
            'quiz' => ['id' => $quiz->id, 'title' => $quiz->title, 'url' => route('lms.quizzes.show', $quiz)],
            'attempt' => [
                'attempt_no' => $attempt->attempt_no, 'submitted_at' => $attempt->submitted_at?->toDateTimeString(), 'timed_out' => (bool) $attempt->timed_out,
                'awaiting_marking' => $attempt->grading_status === 'needs_grading',
                'score' => $marks ? (float) $attempt->score : null, 'max_score' => $marks ? (float) $attempt->max_score : null,
            ],
            'marksVisible' => $marks, 'answersVisible' => $answersShown,
            'items' => collect($attempt->question_order ?? [])->map(function ($qid) use ($questions, $given, $marks, $answersShown) {
                $q = $questions->get($qid);
                if (! $q) {
                    return null;
                }
                $a = $given->get($q->id);

                return [
                    'id' => $q->id, 'type' => $q->type, 'prompt' => $q->prompt, 'points' => $q->points, 'answer' => $a?->answer,
                    'is_correct' => $marks ? $a?->is_correct : null,
                    'points_awarded' => $marks && $a?->points_awarded !== null ? (float) $a->points_awarded : null,
                    'feedback' => $marks ? $a?->feedback : null,
                    'correct_answer' => $answersShown && $q->type !== 'essay' ? $q->correct_answer : null,
                    'explanation' => $answersShown ? $q->explanation : null,
                ];
            })->filter()->values(),
        ]);
    }
}
