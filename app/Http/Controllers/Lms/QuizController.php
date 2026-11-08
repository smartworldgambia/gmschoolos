<?php

namespace App\Http\Controllers\Lms;

use App\Exceptions\AcademicRuleException;
use App\Http\Controllers\Concerns\UsesLms;
use App\Http\Controllers\Controller;
use App\Models\BankQuestion;
use App\Models\Course;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\QuizBankPull;
use App\Models\QuizQuestion;
use App\Models\QuestionBank;
use App\Services\Lms\QuizService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Teacher side of quizzes: settings, questions, random bank draws, result release and manual marking. */
class QuizController extends Controller
{
    use UsesLms;

    public function __construct(protected QuizService $service) {}

    public function create(Course $course): Response
    {
        $role = $this->author($course);

        return $this->form($course, null, $role);
    }

    public function edit(Quiz $quiz): Response
    {
        $role = $this->author($quiz->course);

        return $this->form($quiz->course, $quiz, $role);
    }

    protected function form(Course $course, ?Quiz $quiz, string $role): Response
    {
        $user = auth()->user();
        $banks = QuestionBank::query()->withCount('questions')
            ->where(fn ($w) => $w->where('created_by', $user->id)->orWhere('is_shared', true))
            ->orderBy('title')->get();
        if ($user->can('lms.manage')) {
            $banks = QuestionBank::query()->withCount('questions')->orderBy('title')->get();
        }
        $pulls = $quiz ? $quiz->pulls()->with('bank:id,title')->get() : collect();

        return Inertia::render('Lms/QuizForm', $this->shellProps() + $this->courseHeader($course->loadMissing('subject'), $role) + [
            'quiz' => $quiz ? [
                'id' => $quiz->id, 'title' => $quiz->title, 'description' => $quiz->description, 'status' => $quiz->status,
                'opens_at' => $quiz->opens_at?->format('Y-m-d\TH:i'), 'closes_at' => $quiz->closes_at?->format('Y-m-d\TH:i'),
                'time_limit_minutes' => $quiz->time_limit_minutes, 'max_attempts' => $quiz->max_attempts,
                'shuffle_questions' => (bool) $quiz->shuffle_questions, 'shuffle_options' => (bool) $quiz->shuffle_options,
                'release_mode' => $quiz->release_mode, 'release_at' => $quiz->release_at?->format('Y-m-d\TH:i'),
                'show_correct_answers' => (bool) $quiz->show_correct_answers, 'grade_method' => $quiz->grade_method,
                'max_score' => $quiz->max_score, 'released' => $quiz->results_released_at !== null,
                'has_attempts' => $quiz->attempts()->exists(),
                'attempts_url' => route('lms.quizzes.attempts', $quiz), 'preview_url' => route('lms.quizzes.show', $quiz),
            ] : null,
            'questions' => $quiz ? $quiz->questions()->get()->map(fn (QuizQuestion $q) => $this->questionRow($q))->values() : [],
            'pulls' => $pulls->map(fn (QuizBankPull $p) => ['id' => $p->id, 'bank' => $p->bank?->title, 'count' => $p->count])->values(),
            'banks' => $banks->map(fn ($b) => ['id' => $b->id, 'title' => $b->title, 'count' => $b->questions_count])->values(),
            'releaseModes' => Quiz::RELEASE_MODES, 'gradeMethods' => Quiz::GRADE_METHODS, 'questionTypes' => QuizQuestion::TYPES,
        ]);
    }

    /** @return array<string,mixed> */
    protected function questionRow(QuizQuestion $q): array
    {
        return [
            'id' => $q->id, 'type' => $q->type, 'prompt' => $q->prompt, 'points' => $q->points, 'options' => $q->options ?? [],
            'correct_answer' => $q->correct_answer, 'accepted_answers' => $q->accepted_answers ?? [], 'case_sensitive' => (bool) $q->case_sensitive,
            'explanation' => $q->explanation,
        ];
    }

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:190'], 'description' => ['nullable', 'string', 'max:5000'],
            'opens_at' => ['nullable', 'date'], 'closes_at' => ['nullable', 'date'], 'time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'max_attempts' => ['required', 'integer', 'min:1', 'max:10'], 'shuffle_questions' => ['boolean'], 'shuffle_options' => ['boolean'],
            'release_mode' => ['required', 'in:'.implode(',', Quiz::RELEASE_MODES)], 'release_at' => ['nullable', 'date'],
            'show_correct_answers' => ['boolean'], 'grade_method' => ['required', 'in:'.implode(',', Quiz::GRADE_METHODS)],
            'status' => ['required', 'in:draft,published'],
        ];
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        $this->author($course);
        $v = $request->validate($this->rules());

        return $this->attempt(function () use ($request, $course, $v) {
            $quiz = $this->service->save($request->user(), $course, null, $v);

            return redirect()->route('lms.quizzes.edit', $quiz)->with('success', 'ok.lms.quizSaved');
        });
    }

    public function update(Request $request, Quiz $quiz): RedirectResponse
    {
        $this->author($quiz->course);
        $v = $request->validate($this->rules());

        return $this->attempt(function () use ($request, $quiz, $v) {
            $this->service->save($request->user(), $quiz->course, $quiz, $v);

            return back()->with('success', 'ok.lms.quizSaved');
        });
    }

    public function destroy(Quiz $quiz): RedirectResponse
    {
        $course = $quiz->course;
        $this->author($course);
        if ($quiz->attempts()->exists()) {
            return back()->with('error', 'err.lms.quizHasAttempts');
        }
        $quiz->questions()->delete();
        $quiz->pulls()->delete();
        $quiz->delete();

        return redirect()->route('lms.courses.show', $course)->with('success', 'ok.lms.quizRemoved');
    }

    // ------------------------------------------------------------------ questions and bank draws

    protected function questionRules(): array
    {
        return [
            'type' => ['required', 'in:'.implode(',', QuizQuestion::TYPES)], 'prompt' => ['required', 'string', 'max:3000'],
            'points' => ['required', 'integer', 'min:1', 'max:100'], 'options' => ['nullable', 'array', 'max:8'], 'options.*' => ['nullable', 'string', 'max:300'],
            'correct_answer' => ['nullable', 'string', 'max:500'], 'accepted_answers' => ['nullable', 'array', 'max:20'], 'accepted_answers.*' => ['nullable', 'string', 'max:300'],
            'case_sensitive' => ['boolean'], 'explanation' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function storeQuestion(Request $request, Quiz $quiz): RedirectResponse
    {
        $this->author($quiz->course);
        $v = $request->validate($this->questionRules());

        return $this->attempt(function () use ($quiz, $v) {
            $this->service->addQuestion($quiz, $v);

            return back()->with('success', 'ok.lms.questionSaved');
        });
    }

    public function updateQuestion(Request $request, QuizQuestion $question): RedirectResponse
    {
        abort_if($question->quiz_attempt_id !== null, 404); // attempt snapshots are never edited
        $this->author($question->quiz->course);
        $v = $request->validate($this->questionRules());

        return $this->attempt(function () use ($question, $v) {
            $this->service->updateQuestion($question, $v);

            return back()->with('success', 'ok.lms.questionSaved');
        });
    }

    public function destroyQuestion(QuizQuestion $question): RedirectResponse
    {
        abort_if($question->quiz_attempt_id !== null, 404);
        $this->author($question->quiz->course);

        return $this->attempt(function () use ($question) {
            $this->service->deleteQuestion($question);

            return back()->with('success', 'ok.lms.questionRemoved');
        });
    }

    public function storePull(Request $request, Quiz $quiz): RedirectResponse
    {
        $this->author($quiz->course);
        $v = $request->validate(['question_bank_id' => ['required', 'integer', $this->inSchool('question_banks')], 'count' => ['required', 'integer', 'min:1', 'max:200']]);
        $bank = QuestionBank::query()->findOrFail($v['question_bank_id']);
        $user = $request->user();
        abort_unless($user->can('lms.manage') || $bank->is_shared || (int) $bank->created_by === (int) $user->id, 403);

        return $this->attempt(function () use ($user, $quiz, $bank, $v) {
            $this->service->addPull($user, $quiz, $bank, (int) $v['count']);

            return back()->with('success', 'ok.lms.pullSaved');
        });
    }

    public function destroyPull(QuizBankPull $pull): RedirectResponse
    {
        $this->author($pull->quiz->course);

        return $this->attempt(function () use ($pull) {
            $this->service->removePull($pull);

            return back()->with('success', 'ok.lms.pullRemoved');
        });
    }

    public function release(Quiz $quiz): RedirectResponse
    {
        $this->author($quiz->course);

        return $this->attempt(function () use ($quiz) {
            $this->service->release($quiz);

            return back()->with('success', 'ok.lms.resultsReleased');
        });
    }

    // ------------------------------------------------------------------ attempts and manual marking

    public function attempts(Quiz $quiz): Response
    {
        $course = $quiz->course;
        $role = $this->author($course);
        $attempts = QuizAttempt::query()->with('student:id,first_name,last_name,student_number,school_class_id')->where('quiz_id', $quiz->id)
            ->where('status', '!=', 'in_progress')->orderByRaw("case when grading_status = 'needs_grading' then 0 else 1 end")->orderByDesc('submitted_at')->limit(500)->get();

        return Inertia::render('Lms/QuizAttempts', $this->shellProps() + $this->courseHeader($course->loadMissing('subject'), $role) + [
            'quiz' => ['id' => $quiz->id, 'title' => $quiz->title, 'max_score' => $quiz->max_score, 'release_mode' => $quiz->release_mode,
                'released' => $quiz->resultsReleased(), 'edit_url' => route('lms.quizzes.edit', $quiz),
                'can_release' => $quiz->release_mode === 'manual' && $quiz->results_released_at === null],
            'attempts' => $attempts->map(fn (QuizAttempt $a) => [
                'id' => $a->id, 'name' => $a->student?->full_name, 'number' => $a->student?->student_number, 'attempt_no' => $a->attempt_no,
                'submitted_at' => $a->submitted_at?->toDateTimeString(), 'timed_out' => (bool) $a->timed_out, 'grading_status' => $a->grading_status,
                'score' => $a->score !== null ? (float) $a->score : null, 'max_score' => (float) $a->max_score, 'url' => route('lms.attempts.grade', $a),
            ])->values(),
        ]);
    }

    public function showAttempt(QuizAttempt $attempt): Response
    {
        $quiz = $attempt->quiz;
        $course = $quiz->course;
        $role = $this->author($course);
        abort_unless($attempt->status === 'submitted', 404);

        $questions = QuizQuestion::query()->whereIn('id', $attempt->question_order ?? [])->get()->keyBy('id');
        $answers = QuizAttemptAnswer::query()->where('quiz_attempt_id', $attempt->id)->get()->keyBy('quiz_question_id');

        return Inertia::render('Lms/QuizGrade', $this->shellProps() + $this->courseHeader($course->loadMissing('subject'), $role) + [
            'quiz' => ['id' => $quiz->id, 'title' => $quiz->title, 'url' => route('lms.quizzes.attempts', $quiz)],
            'student' => ['name' => $attempt->student?->full_name, 'number' => $attempt->student?->student_number],
            'attempt' => [
                'id' => $attempt->id, 'attempt_no' => $attempt->attempt_no, 'score' => $attempt->score !== null ? (float) $attempt->score : null,
                'max_score' => (float) $attempt->max_score, 'grading_status' => $attempt->grading_status, 'timed_out' => (bool) $attempt->timed_out,
                'submitted_at' => $attempt->submitted_at?->toDateTimeString(),
            ],
            'items' => collect($attempt->question_order ?? [])->map(function ($qid) use ($questions, $answers) {
                $q = $questions->get($qid);
                if (! $q) {
                    return null;
                }
                $a = $answers->get($q->id);

                return [
                    'id' => $q->id, 'type' => $q->type, 'prompt' => $q->prompt, 'points' => $q->points, 'correct_answer' => $q->type === 'essay' ? null : $q->correct_answer,
                    'accepted_answers' => $q->accepted_answers ?? [], 'answer' => $a?->answer, 'is_correct' => $a?->is_correct,
                    'points_awarded' => $a?->points_awarded !== null ? (float) $a->points_awarded : null, 'needs_grading' => (bool) $a?->needs_grading,
                    'feedback' => $a?->feedback,
                ];
            })->filter()->values(),
        ]);
    }

    public function gradeAttempt(Request $request, QuizAttempt $attempt): RedirectResponse
    {
        $this->author($attempt->quiz->course);
        $v = $request->validate(['points' => ['required', 'array'], 'points.*' => ['nullable', 'numeric', 'min:0', 'max:100'], 'feedback' => ['nullable', 'array'], 'feedback.*' => ['nullable', 'string', 'max:2000']]);

        return $this->attempt(function () use ($request, $attempt, $v) {
            $this->service->grade($request->user(), $attempt, $v['points'], $v['feedback'] ?? []);

            return back()->with('success', 'ok.lms.graded');
        });
    }
}
