<?php

namespace App\Services\Lms;

use App\Exceptions\AcademicRuleException;
use App\Models\BankQuestion;
use App\Models\Course;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\QuizBankPull;
use App\Models\QuizQuestion;
use App\Models\QuestionBank;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Quiz authoring and the attempt engine: time limits, attempt limits, shuffling, random draws from question banks,
 * automatic grading (multiple choice, true/false, short answer), manual grading (essays and overrides) and result release.
 *
 * Correct answers never leave the server while an attempt is running, and are shown afterwards only when the teacher's
 * release rule is satisfied (and, for multi-attempt quizzes, the learner has no attempt left to benefit from them).
 */
class QuizService
{
    /** Seconds allowed after the due time for the last autosave / submit to arrive (network latency). */
    public const GRACE_SECONDS = 20;

    public function __construct(protected AnswerMatcher $matcher, protected LmsAccess $access) {}

    // ------------------------------------------------------------------ authoring

    public function save(User $actor, Course $course, ?Quiz $quiz, array $data): Quiz
    {
        $open = $data['opens_at'] ?? null;
        $close = $data['closes_at'] ?? null;
        if ($open && $close && strtotime((string) $close) <= strtotime((string) $open)) {
            throw AcademicRuleException::because('err.lms.closesBeforeOpen');
        }
        $mode = $data['release_mode'] ?? 'immediate';
        if ($mode === 'scheduled' && empty($data['release_at'])) {
            throw AcademicRuleException::because('err.lms.releaseAtRequired');
        }
        if ($mode === 'after_close' && empty($close)) {
            throw AcademicRuleException::because('err.lms.closeRequired');
        }
        $status = $data['status'] ?? 'draft';

        $attrs = [
            'title' => $data['title'], 'description' => $data['description'] ?? null, 'opens_at' => $open, 'closes_at' => $close,
            'time_limit_minutes' => ($data['time_limit_minutes'] ?? null) ?: null, 'max_attempts' => max(1, min(10, (int) ($data['max_attempts'] ?? 1))),
            'shuffle_questions' => (bool) ($data['shuffle_questions'] ?? false), 'shuffle_options' => (bool) ($data['shuffle_options'] ?? false),
            'release_mode' => $mode, 'release_at' => $mode === 'scheduled' ? $data['release_at'] : null,
            'show_correct_answers' => (bool) ($data['show_correct_answers'] ?? true), 'grade_method' => $data['grade_method'] ?? 'highest',
            'status' => $status,
        ];

        if ($quiz) {
            if ($status === 'published' && ! $this->hasContent($quiz)) {
                throw AcademicRuleException::because('err.lms.quizEmpty');
            }
            $quiz->update($attrs);
        } else {
            if ($status === 'published') {
                throw AcademicRuleException::because('err.lms.quizEmpty'); // nothing to publish yet
            }
            $quiz = Quiz::query()->create($attrs + ['school_id' => $course->school_id, 'course_id' => $course->id, 'created_by' => $actor->id, 'max_score' => 0]);
        }

        return $quiz;
    }

    public function hasContent(Quiz $quiz): bool
    {
        return $quiz->questions()->exists() || $quiz->pulls()->exists();
    }

    protected function assertEditable(Quiz $quiz): void
    {
        if ($quiz->attempts()->exists()) {
            throw AcademicRuleException::because('err.lms.quizHasAttempts');
        }
    }

    /** Normalise and validate the fields common to quiz and bank questions. @return array<string,mixed> */
    public function cleanQuestion(array $d): array
    {
        $type = $d['type'] ?? '';
        if (! in_array($type, QuizQuestion::TYPES, true)) {
            throw AcademicRuleException::because('err.lms.questionType');
        }
        $points = (int) ($d['points'] ?? 1);
        if ($points < 1 || $points > 100) {
            throw AcademicRuleException::because('err.lms.questionPoints');
        }
        $out = ['type' => $type, 'prompt' => trim((string) $d['prompt']), 'points' => $points, 'explanation' => ($d['explanation'] ?? null) ?: null,
            'options' => null, 'correct_answer' => null, 'accepted_answers' => null, 'case_sensitive' => false];

        switch ($type) {
            case 'multiple_choice':
                $options = collect($d['options'] ?? [])->map(fn ($o) => trim((string) $o))->filter(fn ($o) => $o !== '')->values();
                if ($options->count() < 2 || $options->count() > 8 || $options->unique()->count() !== $options->count()) {
                    throw AcademicRuleException::because('err.lms.optionsInvalid');
                }
                $correct = trim((string) ($d['correct_answer'] ?? ''));
                if (! $options->contains($correct)) {
                    throw AcademicRuleException::because('err.lms.correctNotInOptions');
                }
                $out['options'] = $options->all();
                $out['correct_answer'] = $correct;
                break;
            case 'true_false':
                $correct = strtolower(trim((string) ($d['correct_answer'] ?? '')));
                if (! in_array($correct, ['true', 'false'], true)) {
                    throw AcademicRuleException::because('err.lms.correctTrueFalse');
                }
                $out['options'] = ['true', 'false'];
                $out['correct_answer'] = $correct;
                break;
            case 'short_answer':
                $accepted = collect($d['accepted_answers'] ?? [])->map(fn ($a) => trim((string) $a))->filter(fn ($a) => $a !== '')->unique()->values();
                if ($accepted->isEmpty()) {
                    throw AcademicRuleException::because('err.lms.acceptedRequired');
                }
                $out['accepted_answers'] = $accepted->take(20)->all();
                $out['correct_answer'] = $accepted->first();
                $out['case_sensitive'] = (bool) ($d['case_sensitive'] ?? false);
                break;
            case 'essay':
                break;
        }

        return $out;
    }

    public function addQuestion(Quiz $quiz, array $data): QuizQuestion
    {
        $this->assertEditable($quiz);
        $q = QuizQuestion::query()->create($this->cleanQuestion($data) + [
            'school_id' => $quiz->school_id, 'quiz_id' => $quiz->id,
            'sort_order' => (int) $quiz->questions()->max('sort_order') + 1,
        ]);
        $this->refreshMax($quiz);

        return $q;
    }

    public function updateQuestion(QuizQuestion $question, array $data): QuizQuestion
    {
        $quiz = $question->quiz;
        $this->assertEditable($quiz);
        $question->update($this->cleanQuestion($data));
        $this->refreshMax($quiz);

        return $question;
    }

    public function deleteQuestion(QuizQuestion $question): void
    {
        $quiz = $question->quiz;
        $this->assertEditable($quiz);
        $question->delete();
        $this->refreshMax($quiz);
        if ($quiz->status === 'published' && ! $this->hasContent($quiz)) {
            $quiz->update(['status' => 'draft']);
        }
    }

    public function refreshMax(Quiz $quiz): void
    {
        $quiz->update(['max_score' => (int) $quiz->questions()->sum('points')]);
    }

    public function addPull(User $actor, Quiz $quiz, QuestionBank $bank, int $count): QuizBankPull
    {
        $this->assertEditable($quiz);
        $available = BankQuestion::query()->where('question_bank_id', $bank->id)->count();
        if ($count < 1 || $count > $available) {
            throw AcademicRuleException::because('err.lms.pullTooMany');
        }

        return QuizBankPull::query()->updateOrCreate(
            ['quiz_id' => $quiz->id, 'question_bank_id' => $bank->id],
            ['school_id' => $quiz->school_id, 'count' => $count]
        );
    }

    public function removePull(QuizBankPull $pull): void
    {
        $quiz = $pull->quiz;
        $this->assertEditable($quiz);
        $pull->delete();
        if ($quiz->status === 'published' && ! $this->hasContent($quiz)) {
            $quiz->update(['status' => 'draft']);
        }
    }

    public function release(Quiz $quiz): void
    {
        if ($quiz->release_mode !== 'manual') {
            throw AcademicRuleException::because('err.lms.releaseNotManual');
        }
        $quiz->update(['results_released_at' => now()]);
    }

    // ------------------------------------------------------------------ attempts

    /** @return string|null `err.lms.*` code when the learner may not start a new attempt now */
    public function startBlock(Quiz $quiz, Student $student): ?string
    {
        if (! $quiz->isPublished() || ($quiz->opens_at && $quiz->opens_at->gt(now()))) {
            return 'err.lms.quizNotOpen';
        }
        if ($quiz->closes_at && $quiz->closes_at->lt(now())) {
            return 'err.lms.quizClosed';
        }
        $used = QuizAttempt::query()->where('quiz_id', $quiz->id)->where('student_id', $student->id)->count();

        return $used >= $quiz->max_attempts ? 'err.lms.noMoreAttempts' : null;
    }

    public function start(Course $course, Quiz $quiz, Student $student): QuizAttempt
    {
        $running = QuizAttempt::query()->where('quiz_id', $quiz->id)->where('student_id', $student->id)->where('status', 'in_progress')->latest('id')->first();
        if ($running) {
            if (! $this->expired($running)) {
                return $running;   // resume, never a second parallel attempt
            }
            $this->finalize($running, true);
        }
        if ($block = $this->startBlock($quiz, $student)) {
            throw AcademicRuleException::because($block);
        }

        return DB::transaction(function () use ($course, $quiz, $student) {
            $questions = $this->drawQuestions($quiz);
            if ($questions->isEmpty()) {
                throw AcademicRuleException::because('err.lms.quizEmpty');
            }
            $attemptNo = (int) QuizAttempt::query()->where('quiz_id', $quiz->id)->where('student_id', $student->id)->max('attempt_no') + 1;
            $started = now();
            $due = $quiz->time_limit_minutes ? $started->copy()->addMinutes($quiz->time_limit_minutes) : null;
            if ($quiz->closes_at && ($due === null || $quiz->closes_at->lt($due))) {
                $due = $quiz->closes_at->copy();   // the quiz closing is a hard stop
            }

            $attempt = QuizAttempt::query()->create([
                'school_id' => $quiz->school_id, 'quiz_id' => $quiz->id, 'student_id' => $student->id, 'status' => 'in_progress',
                'attempt_no' => $attemptNo, 'started_at' => $started, 'due_at' => $due, 'max_score' => $questions->sum('points'),
            ]);

            // Snapshot bank draws as attempt-owned rows so later bank edits cannot change what was asked.
            $ids = [];
            foreach ($questions as $q) {
                if ($q instanceof BankQuestion) {
                    $q = QuizQuestion::query()->create([
                        'school_id' => $quiz->school_id, 'quiz_id' => $quiz->id, 'quiz_attempt_id' => $attempt->id, 'bank_question_id' => $q->id,
                        'prompt' => $q->prompt, 'type' => $q->type, 'options' => $q->options, 'correct_answer' => $q->correct_answer,
                        'accepted_answers' => $q->accepted_answers, 'case_sensitive' => $q->case_sensitive, 'points' => $q->points,
                        'explanation' => $q->explanation, 'sort_order' => 0,
                    ]);
                }
                $ids[] = $q->id;
            }
            if ($quiz->shuffle_questions) {
                shuffle($ids);
            }

            $optionOrders = [];
            if ($quiz->shuffle_options) {
                foreach (QuizQuestion::query()->whereIn('id', $ids)->where('type', 'multiple_choice')->get() as $q) {
                    $order = array_keys($q->options ?? []);
                    shuffle($order);
                    $optionOrders[$q->id] = $order;
                }
            }
            $attempt->update(['question_order' => $ids, 'option_orders' => $optionOrders ?: null]);
            $this->access->touch($course, $student);

            return $attempt;
        });
    }

    /** @return Collection<int,QuizQuestion|BankQuestion> */
    protected function drawQuestions(Quiz $quiz): Collection
    {
        $set = $quiz->questions()->get()->values();
        foreach ($quiz->pulls()->get() as $pull) {
            $drawn = BankQuestion::query()->where('question_bank_id', $pull->question_bank_id)->inRandomOrder()->limit($pull->count)->get();
            $set = $set->concat($drawn);
        }

        return $set->values();
    }

    public function expired(QuizAttempt $attempt): bool
    {
        return $attempt->status === 'in_progress' && $attempt->due_at !== null
            && $attempt->due_at->copy()->addSeconds(self::GRACE_SECONDS)->lt(now());
    }

    /** Closes any attempt whose time ran out, grading what was saved. Returns true when it did. */
    public function expireIfNeeded(QuizAttempt $attempt): bool
    {
        if ($this->expired($attempt)) {
            $this->finalize($attempt, true);

            return true;
        }

        return false;
    }

    /** @param array<int|string,mixed> $answers question id => answer */
    public function saveAnswers(QuizAttempt $attempt, array $answers): void
    {
        if ($attempt->status !== 'in_progress') {
            throw AcademicRuleException::because('err.lms.attemptClosed');
        }
        if ($this->expireIfNeeded($attempt)) {
            throw AcademicRuleException::because('err.lms.attemptExpired');
        }
        $questions = QuizQuestion::query()->whereIn('id', $attempt->question_order ?? [])->get()->keyBy('id');
        foreach ($answers as $qid => $value) {
            $q = $questions->get((int) $qid);
            if (! $q) {
                continue; // an id that is not part of THIS attempt is ignored, never stored
            }
            $text = $value === null ? null : (string) $value;
            if ($text !== null) {
                $text = mb_substr($text, 0, $q->type === 'essay' ? 10000 : 500);
            }
            QuizAttemptAnswer::query()->updateOrCreate(
                ['quiz_attempt_id' => $attempt->id, 'quiz_question_id' => $q->id],
                ['school_id' => $attempt->school_id, 'answer' => $text]
            );
        }
    }

    public function submit(Course $course, QuizAttempt $attempt, array $answers): QuizAttempt
    {
        if ($attempt->status !== 'in_progress') {
            throw AcademicRuleException::because('err.lms.attemptClosed');
        }
        $late = $this->expired($attempt);
        if (! $late) {
            $this->saveAnswers($attempt, $answers);
        }
        $this->finalize($attempt, $late);
        $student = $attempt->student;
        if ($student) {
            $this->access->touch($course, $student);
        }

        return $attempt->fresh();
    }

    /** Grade everything that can be graded automatically and close the attempt. */
    public function finalize(QuizAttempt $attempt, bool $timedOut = false): void
    {
        DB::transaction(function () use ($attempt, $timedOut) {
            $questions = QuizQuestion::query()->whereIn('id', $attempt->question_order ?? [])->get()->keyBy('id');
            $answers = QuizAttemptAnswer::query()->where('quiz_attempt_id', $attempt->id)->get()->keyBy('quiz_question_id');
            $needs = false;
            $total = 0.0;

            foreach ($questions as $q) {
                $row = $answers->get($q->id) ?? QuizAttemptAnswer::query()->create([
                    'school_id' => $attempt->school_id, 'quiz_attempt_id' => $attempt->id, 'quiz_question_id' => $q->id, 'answer' => null,
                ]);
                $given = $row->answer;
                if ($q->type === 'essay') {
                    $pending = $given !== null && trim($given) !== '';
                    $row->forceFill(['is_correct' => false, 'points_awarded' => 0, 'needs_grading' => $pending])->save();
                    $needs = $needs || $pending;
                    continue;
                }
                $ok = match ($q->type) {
                    'short_answer' => $this->matcher->matches($given, $q->accepted_answers ?: [(string) $q->correct_answer], (bool) $q->case_sensitive),
                    default => $given !== null && trim($given) === (string) $q->correct_answer,
                };
                $points = $ok ? (float) $q->points : 0.0;
                $row->forceFill(['is_correct' => $ok, 'points_awarded' => $points, 'needs_grading' => false])->save();
                $total += $points;
            }

            $attempt->forceFill([
                'status' => 'submitted', 'submitted_at' => now(), 'timed_out' => $timedOut, 'score' => $total,
                'grading_status' => $needs ? 'needs_grading' : 'graded', 'graded_at' => $needs ? null : now(),
            ])->save();
        });
    }

    /**
     * Teacher marking of an attempt: essays, and overrides of automatic marks. Every touched answer is recorded with the marker.
     *
     * @param  array<int|string,mixed>  $points    question id => points
     * @param  array<int|string,mixed>  $feedback  question id => text
     */
    public function grade(User $grader, QuizAttempt $attempt, array $points, array $feedback): QuizAttempt
    {
        if ($attempt->status !== 'submitted') {
            throw AcademicRuleException::because('err.lms.attemptNotSubmitted');
        }
        $questions = QuizQuestion::query()->whereIn('id', $attempt->question_order ?? [])->get()->keyBy('id');

        DB::transaction(function () use ($grader, $attempt, $points, $feedback, $questions) {
            foreach ($points as $qid => $p) {
                $q = $questions->get((int) $qid);
                if (! $q || $p === null || $p === '') {
                    continue;
                }
                if (! is_numeric($p) || (float) $p < 0 || (float) $p > (float) $q->points) {
                    throw AcademicRuleException::because('err.lms.questionScore');
                }
                QuizAttemptAnswer::query()->updateOrCreate(
                    ['quiz_attempt_id' => $attempt->id, 'quiz_question_id' => $q->id],
                    ['school_id' => $attempt->school_id, 'points_awarded' => round((float) $p, 2), 'is_correct' => (float) $p >= (float) $q->points,
                        'needs_grading' => false, 'feedback' => isset($feedback[$qid]) ? trim((string) $feedback[$qid]) : null,
                        'graded_by' => $grader->id, 'graded_at' => now()]
                );
            }
            $answers = QuizAttemptAnswer::query()->where('quiz_attempt_id', $attempt->id)->get();
            $pending = $answers->contains('needs_grading', true);
            $attempt->forceFill([
                'score' => round((float) $answers->sum('points_awarded'), 2),
                'grading_status' => $pending ? 'needs_grading' : 'graded', 'graded_at' => $pending ? null : now(),
            ])->save();
        });

        return $attempt->fresh();
    }

    // ------------------------------------------------------------------ results

    /** Attempts that count: submitted and completely graded. */
    protected function countable(Quiz $quiz, Student $student): Collection
    {
        return QuizAttempt::query()->where('quiz_id', $quiz->id)->where('student_id', $student->id)
            ->where('status', 'submitted')->where('grading_status', 'graded')->orderBy('attempt_no')->get();
    }

    /**
     * The learner's quiz result by the quiz's grade method, as a percentage. `pending` is true when an attempt is still
     * waiting for manual marking (so the figure may still change).
     *
     * @return array{percent:?float,score:?float,max:?float,attempts:int,pending:bool}
     */
    public function result(Quiz $quiz, Student $student): array
    {
        $all = QuizAttempt::query()->where('quiz_id', $quiz->id)->where('student_id', $student->id)->where('status', 'submitted')->get();
        $done = $this->countable($quiz, $student)->filter(fn ($a) => (float) $a->max_score > 0);
        $pending = $all->contains('grading_status', 'needs_grading');
        if ($done->isEmpty()) {
            return ['percent' => null, 'score' => null, 'max' => null, 'attempts' => $all->count(), 'pending' => $pending];
        }
        $pct = fn ($a) => (float) $a->score / (float) $a->max_score * 100;
        $pick = match ($quiz->grade_method) {
            'latest' => $done->last(),
            'average' => null,
            default => $done->sortByDesc(fn ($a) => $pct($a))->first(),
        };
        if ($pick === null) {
            return ['percent' => round($done->avg($pct), 2), 'score' => round((float) $done->avg('score'), 2), 'max' => round((float) $done->avg('max_score'), 2),
                'attempts' => $all->count(), 'pending' => $pending];
        }

        return ['percent' => round($pct($pick), 2), 'score' => (float) $pick->score, 'max' => (float) $pick->max_score, 'attempts' => $all->count(), 'pending' => $pending];
    }

    /** May the learner see marks for this attempt? Needs the release rule AND complete marking. */
    public function marksVisible(Quiz $quiz, QuizAttempt $attempt): bool
    {
        return $attempt->status === 'submitted' && $attempt->grading_status === 'graded' && $quiz->resultsReleased();
    }

    /**
     * May the learner see the correct answers? Only once marks are released, the teacher allows it, and (for quizzes with
     * several attempts) no attempt is left to benefit from them or the quiz has closed.
     */
    public function answersVisible(Quiz $quiz, QuizAttempt $attempt): bool
    {
        if (! $quiz->show_correct_answers || ! $this->marksVisible($quiz, $attempt)) {
            return false;
        }
        $used = QuizAttempt::query()->where('quiz_id', $quiz->id)->where('student_id', $attempt->student_id)->count();

        return $used >= $quiz->max_attempts || ($quiz->closes_at !== null && $quiz->closes_at->lt(now()));
    }
}
