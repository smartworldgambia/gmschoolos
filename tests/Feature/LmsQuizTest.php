<?php

namespace Tests\Feature;

use App\Models\BankQuestion;
use App\Models\QuestionBank;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\QuizQuestion;
use App\Services\Lms\AnswerMatcher;

/** Quizzes: authoring, attempt engine, grading, release rules and question banks. */
class LmsQuizTest extends Phase7TestCase
{
    /** A published quiz with one question of every type. @return array{0:Quiz,1:array<string,QuizQuestion>} */
    protected function quizWithQuestions(\App\Models\Course $course, array $over = []): array
    {
        $quiz = $this->quizzes->save($this->admin, $course, null, $over + ['title' => 'Unit test', 'status' => 'draft', 'max_attempts' => 1, 'release_mode' => 'immediate']);
        $q = [
            'mc' => $this->quizzes->addQuestion($quiz, ['type' => 'multiple_choice', 'prompt' => '2+2?', 'points' => 2, 'options' => ['3', '4', '5'], 'correct_answer' => '4']),
            'tf' => $this->quizzes->addQuestion($quiz, ['type' => 'true_false', 'prompt' => 'Banjul is the capital', 'points' => 1, 'correct_answer' => 'true']),
            'sa' => $this->quizzes->addQuestion($quiz, ['type' => 'short_answer', 'prompt' => 'Capital of France?', 'points' => 2, 'accepted_answers' => ['Paris']]),
            'es' => $this->quizzes->addQuestion($quiz, ['type' => 'essay', 'prompt' => 'Discuss', 'points' => 5]),
        ];
        $this->quizzes->save($this->admin, $course, $quiz, ['title' => $quiz->title, 'status' => 'published', 'max_attempts' => $quiz->max_attempts, 'release_mode' => $quiz->release_mode, 'release_at' => $quiz->release_at,
            'closes_at' => $quiz->closes_at, 'time_limit_minutes' => $quiz->time_limit_minutes, 'show_correct_answers' => $quiz->show_correct_answers, 'grade_method' => $quiz->grade_method]);

        return [$quiz->fresh(), $q];
    }

    /** @return array{0:\App\Models\User,1:\App\Models\Student,2:\App\Models\Course} */
    protected function learnerInCourse(): array
    {
        [$user, $student] = $this->learner();
        $course = $this->course();
        $this->enrol($course, $student);

        return [$user, $student, $course];
    }

    protected function startAttempt($user, Quiz $quiz): QuizAttempt
    {
        $this->actAs($user)->post(route('lms.quizzes.start', $quiz))->assertRedirect();

        return QuizAttempt::query()->where('quiz_id', $quiz->id)->latest('id')->firstOrFail();
    }

    // ------------------------------------------------------------------ authoring

    public function test_a_quiz_cannot_be_published_empty_and_questions_are_validated(): void
    {
        $course = $this->course();
        $quiz = $this->quizzes->save($this->admin, $course, null, ['title' => 'Q', 'status' => 'draft']);

        $this->actAs($this->teacher)->put(route('lms.quizzes.update', $quiz), ['title' => 'Q', 'status' => 'published', 'max_attempts' => 1, 'release_mode' => 'immediate', 'grade_method' => 'highest'])
            ->assertSessionHas('error', 'err.lms.quizEmpty');

        $post = fn (array $d) => $this->actAs($this->teacher)->post(route('lms.quizzes.questions.store', $quiz), $d);
        $post(['type' => 'multiple_choice', 'prompt' => 'x', 'points' => 1, 'options' => ['a'], 'correct_answer' => 'a'])->assertSessionHas('error', 'err.lms.optionsInvalid');
        $post(['type' => 'multiple_choice', 'prompt' => 'x', 'points' => 1, 'options' => ['a', 'b'], 'correct_answer' => 'c'])->assertSessionHas('error', 'err.lms.correctNotInOptions');
        $post(['type' => 'multiple_choice', 'prompt' => 'x', 'points' => 1, 'options' => ['a', 'a'], 'correct_answer' => 'a'])->assertSessionHas('error', 'err.lms.optionsInvalid');
        $post(['type' => 'true_false', 'prompt' => 'x', 'points' => 1, 'correct_answer' => 'maybe'])->assertSessionHas('error', 'err.lms.correctTrueFalse');
        $post(['type' => 'short_answer', 'prompt' => 'x', 'points' => 1])->assertSessionHas('error', 'err.lms.acceptedRequired');
        $post(['type' => 'essay', 'prompt' => 'Write', 'points' => 5])->assertSessionHas('success', 'ok.lms.questionSaved');
        $this->assertSame(5, (int) $quiz->fresh()->max_score);
    }

    public function test_release_and_window_settings_are_checked(): void
    {
        $course = $this->course();
        $base = ['title' => 'Q', 'status' => 'draft', 'max_attempts' => 1, 'grade_method' => 'highest'];

        $this->actAs($this->teacher)->post(route('lms.quizzes.store', $course), $base + ['release_mode' => 'scheduled'])->assertSessionHas('error', 'err.lms.releaseAtRequired');
        $this->actAs($this->teacher)->post(route('lms.quizzes.store', $course), $base + ['release_mode' => 'after_close'])->assertSessionHas('error', 'err.lms.closeRequired');
        $this->actAs($this->teacher)->post(route('lms.quizzes.store', $course), $base + ['release_mode' => 'immediate', 'opens_at' => '2030-01-02 10:00', 'closes_at' => '2030-01-01 10:00'])
            ->assertSessionHas('error', 'err.lms.closesBeforeOpen');
        $this->actAs($this->teacher)->post(route('lms.quizzes.store', $course), $base + ['release_mode' => 'nonsense'])->assertSessionHasErrors('release_mode');
    }

    public function test_questions_are_locked_once_anyone_has_attempted_the_quiz(): void
    {
        [$user, , $course] = $this->learnerInCourse();
        [$quiz, $q] = $this->quizWithQuestions($course);
        $this->startAttempt($user, $quiz);

        $this->actAs($this->teacher)->put(route('lms.questions.update', $q['mc']), ['type' => 'multiple_choice', 'prompt' => 'changed', 'points' => 2, 'options' => ['1', '2'], 'correct_answer' => '1'])
            ->assertSessionHas('error', 'err.lms.quizHasAttempts');
        $this->actAs($this->teacher)->delete(route('lms.questions.destroy', $q['mc']))->assertSessionHas('error', 'err.lms.quizHasAttempts');
        $this->actAs($this->teacher)->delete(route('lms.quizzes.destroy', $quiz))->assertSessionHas('error', 'err.lms.quizHasAttempts');
    }

    // ------------------------------------------------------------------ taking a quiz

    public function test_correct_answers_never_reach_the_browser_while_an_attempt_is_running(): void
    {
        [$user, , $course] = $this->learnerInCourse();
        [$quiz] = $this->quizWithQuestions($course, ['show_correct_answers' => true]);
        $this->startAttempt($user, $quiz);

        $response = $this->actAs($user)->get(route('lms.quizzes.show', $quiz))->assertOk();
        $props = json_encode($response->viewData('page')['props']);
        $this->assertStringContainsString('"mode":"taking"', $props);
        $this->assertStringNotContainsString('correct_answer', $props);
        $this->assertStringNotContainsString('accepted_answers', $props);
        $this->assertStringNotContainsString('Paris', $props);
        $this->assertStringContainsString('2+2?', $props);
    }

    public function test_auto_grading_scores_objective_questions_and_queues_essays_for_the_teacher(): void
    {
        [$user, $student, $course] = $this->learnerInCourse();
        [$quiz, $q] = $this->quizWithQuestions($course);
        $attempt = $this->startAttempt($user, $quiz);

        $this->actAs($user)->post(route('lms.attempts.submit', $attempt), ['answers' => [
            $q['mc']->id => '4', $q['tf']->id => 'true', $q['sa']->id => '  PARIS. ', $q['es']->id => 'A long essay',
        ]])->assertSessionHas('success', 'ok.lms.quizSubmitted');

        $attempt->refresh();
        $this->assertSame('submitted', $attempt->status);
        $this->assertSame(5.0, (float) $attempt->score);
        $this->assertSame('needs_grading', $attempt->grading_status);

        $this->actAs($this->teacher)->get(route('lms.quizzes.attempts', $quiz))->assertInertia(fn ($p) => $p->has('attempts', 1)->where('attempts.0.grading_status', 'needs_grading'));
        $this->actAs($this->teacher)->post(route('lms.attempts.grade.store', $attempt), ['points' => [$q['es']->id => 6]])->assertSessionHas('error', 'err.lms.questionScore');
        $this->actAs($this->teacher)->post(route('lms.attempts.grade.store', $attempt), ['points' => [$q['es']->id => 4], 'feedback' => [$q['es']->id => 'Well argued']])->assertSessionHas('success', 'ok.lms.graded');

        $attempt->refresh();
        $this->assertSame(9.0, (float) $attempt->score);
        $this->assertSame('graded', $attempt->grading_status);
        $this->assertSame($this->teacher->id, (int) QuizAttemptAnswer::query()->where('quiz_question_id', $q['es']->id)->value('graded_by'));
        $this->actAs($user)->get(route('lms.attempts.show', $attempt))->assertInertia(fn ($p) => $p->where('attempt.score', 9)->where('marksVisible', true));
    }

    public function test_a_teacher_can_override_an_automatic_mark(): void
    {
        [$user, , $course] = $this->learnerInCourse();
        [$quiz, $q] = $this->quizWithQuestions($course);
        $attempt = $this->startAttempt($user, $quiz);
        $this->actAs($user)->post(route('lms.attempts.submit', $attempt), ['answers' => [$q['sa']->id => 'Parris']]);
        $this->assertSame(0.0, (float) QuizAttemptAnswer::query()->where('quiz_question_id', $q['sa']->id)->value('points_awarded'));

        $this->actAs($this->teacher)->post(route('lms.attempts.grade.store', $attempt), ['points' => [$q['sa']->id => 2]])->assertSessionHas('success');
        $this->assertSame(2.0, (float) QuizAttemptAnswer::query()->where('quiz_question_id', $q['sa']->id)->value('points_awarded'));
    }

    public function test_learners_cannot_grade_or_see_staff_screens(): void
    {
        [$user, , $course] = $this->learnerInCourse();
        [$quiz, $q] = $this->quizWithQuestions($course);
        $attempt = $this->startAttempt($user, $quiz);
        $this->actAs($user)->post(route('lms.attempts.submit', $attempt), ['answers' => [$q['mc']->id => '4']]);

        $this->actAs($user)->post(route('lms.attempts.grade.store', $attempt), ['points' => [$q['mc']->id => 2]])->assertForbidden();
        $this->actAs($user)->get(route('lms.attempts.grade', $attempt))->assertForbidden();
        $this->actAs($user)->get(route('lms.quizzes.attempts', $quiz))->assertForbidden();
        $this->actAs($user)->get(route('lms.quizzes.edit', $quiz))->assertForbidden();
        $this->actAs($user)->post(route('lms.quizzes.questions.store', $quiz), ['type' => 'essay', 'prompt' => 'x', 'points' => 1])->assertForbidden();
    }

    public function test_one_running_attempt_per_learner_attempt_limits_and_visibility(): void
    {
        [$user, , $course] = $this->learnerInCourse();
        [$quiz, $q] = $this->quizWithQuestions($course, ['max_attempts' => 2]);
        $first = $this->startAttempt($user, $quiz);
        $again = $this->startAttempt($user, $quiz);
        $this->assertSame($first->id, $again->id, 'starting twice resumes the same attempt');
        $this->assertSame(1, QuizAttempt::query()->count());

        $this->actAs($user)->post(route('lms.attempts.submit', $first), ['answers' => [$q['mc']->id => '3']]);
        $second = $this->startAttempt($user, $quiz);
        $this->actAs($user)->post(route('lms.attempts.submit', $second), ['answers' => [$q['mc']->id => '4']]);
        $this->actAs($user)->post(route('lms.quizzes.start', $quiz))->assertSessionHas('error', 'err.lms.noMoreAttempts');

        $draft = $this->quizzes->save($this->admin, $course, null, ['title' => 'Hidden', 'status' => 'draft']);
        $this->actAs($user)->post(route('lms.quizzes.start', $draft))->assertNotFound();
    }

    public function test_attempts_belong_to_their_learner(): void
    {
        [$u1, , $course] = $this->learnerInCourse();
        [$u2, $s2] = $this->learner();
        $this->enrol($course, $s2);
        [$quiz, $q] = $this->quizWithQuestions($course);
        $attempt = $this->startAttempt($u1, $quiz);

        $this->actAs($u2)->post(route('lms.attempts.save', $attempt), ['answers' => [$q['mc']->id => '4']])->assertNotFound();
        $this->actAs($u2)->post(route('lms.attempts.submit', $attempt), ['answers' => []])->assertNotFound();
        $this->assertSame('in_progress', $attempt->fresh()->status);
    }

    public function test_autosave_stores_answers_ignores_foreign_questions_and_resumes(): void
    {
        [$user, , $course] = $this->learnerInCourse();
        [$quiz, $q] = $this->quizWithQuestions($course);
        [$other] = $this->quizWithQuestions($course, ['title' => 'Other quiz']);
        $foreign = $other->questions()->first();
        $attempt = $this->startAttempt($user, $quiz);

        $this->actAs($user)->postJson(route('lms.attempts.save', $attempt), ['answers' => [$q['mc']->id => '4', $foreign->id => '3']])->assertOk()->assertJson(['ok' => true]);
        $this->assertSame('4', QuizAttemptAnswer::query()->where('quiz_attempt_id', $attempt->id)->where('quiz_question_id', $q['mc']->id)->value('answer'));
        $this->assertSame(0, QuizAttemptAnswer::query()->where('quiz_question_id', $foreign->id)->count());

        $this->actAs($user)->get(route('lms.quizzes.show', $quiz))->assertInertia(fn ($p) => $p->where('mode', 'taking')->where("answers.{$q['mc']->id}", '4'));
    }

    public function test_time_limit_is_enforced_by_the_server(): void
    {
        [$user, , $course] = $this->learnerInCourse();
        [$quiz, $q] = $this->quizWithQuestions($course, ['time_limit_minutes' => 10]);
        $attempt = $this->startAttempt($user, $quiz);
        $this->actAs($user)->postJson(route('lms.attempts.save', $attempt), ['answers' => [$q['mc']->id => '4']])->assertOk();

        $this->travel(11)->minutes();
        $this->actAs($user)->postJson(route('lms.attempts.save', $attempt), ['answers' => [$q['tf']->id => 'true']])->assertStatus(409)->assertJson(['ok' => false, 'error' => 'err.lms.attemptExpired']);

        $attempt->refresh();
        $this->assertSame('submitted', $attempt->status);
        $this->assertTrue((bool) $attempt->timed_out);
        $this->assertSame(2.0, (float) $attempt->score, 'only what was saved before the deadline counts');
        $this->assertSame(0, QuizAttemptAnswer::query()->where('quiz_question_id', $q['tf']->id)->whereNotNull('answer')->count());
    }

    public function test_submitting_after_the_deadline_ignores_the_late_answers(): void
    {
        [$user, , $course] = $this->learnerInCourse();
        [$quiz, $q] = $this->quizWithQuestions($course, ['time_limit_minutes' => 5]);
        $attempt = $this->startAttempt($user, $quiz);

        $this->travel(6)->minutes();
        $this->actAs($user)->post(route('lms.attempts.submit', $attempt), ['answers' => [$q['mc']->id => '4']])->assertSessionHas('success', 'ok.lms.quizTimedOut');
        $this->assertSame(0.0, (float) $attempt->fresh()->score);
        $this->assertTrue((bool) $attempt->fresh()->timed_out);
    }

    public function test_the_quiz_closing_time_is_a_hard_stop_even_without_a_time_limit(): void
    {
        [$user, , $course] = $this->learnerInCourse();
        [$quiz] = $this->quizWithQuestions($course, ['closes_at' => now()->addHour()->toDateTimeString()]);
        $attempt = $this->startAttempt($user, $quiz);
        $this->assertNotNull($attempt->due_at);

        $this->travel(2)->hours();
        $this->actAs($user)->get(route('lms.quizzes.show', $quiz))->assertInertia(fn ($p) => $p->where('mode', 'intro')->where('block', 'err.lms.quizClosed'));
        $this->assertSame('submitted', $attempt->fresh()->status);
    }

    // ------------------------------------------------------------------ releasing results

    public function test_marks_and_answers_stay_hidden_until_the_release_rule_is_met(): void
    {
        [$user, , $course] = $this->learnerInCourse();
        [$quiz, $q] = $this->quizWithQuestions($course, ['release_mode' => 'manual']);
        $attempt = $this->startAttempt($user, $quiz);
        $this->actAs($user)->post(route('lms.attempts.submit', $attempt), ['answers' => [$q['mc']->id => '4', $q['sa']->id => 'Paris']]);
        $this->actAs($this->teacher)->post(route('lms.attempts.grade.store', $attempt), ['points' => [$q['es']->id => 3]]);

        $this->actAs($user)->get(route('lms.attempts.show', $attempt))->assertInertia(fn ($p) => $p->where('marksVisible', false)->where('answersVisible', false)->where('attempt.score', null)->where('items.0.correct_answer', null));
        $this->actAs($user)->get(route('lms.quizzes.show', $quiz))->assertInertia(fn ($p) => $p->where('attempts.0.withheld', true)->where('attempts.0.score', null)->where('result', null));

        $this->actAs($this->teacher)->post(route('lms.quizzes.release', $quiz))->assertSessionHas('success', 'ok.lms.resultsReleased');
        $response = $this->actAs($user)->get(route('lms.attempts.show', $attempt))->assertInertia(fn ($p) => $p->where('marksVisible', true)->where('answersVisible', true)->where('attempt.score', 7));
        $this->assertStringContainsString('Paris', json_encode($response->viewData('page')['props']));
    }

    public function test_after_close_and_scheduled_release_follow_the_clock(): void
    {
        [$user, , $course] = $this->learnerInCourse();
        [$quiz, $q] = $this->quizWithQuestions($course, ['release_mode' => 'after_close', 'closes_at' => now()->addDay()->toDateTimeString(), 'max_attempts' => 1]);
        $attempt = $this->startAttempt($user, $quiz);
        $this->actAs($user)->post(route('lms.attempts.submit', $attempt), ['answers' => [$q['mc']->id => '4']]);
        $this->actAs($this->teacher)->post(route('lms.attempts.grade.store', $attempt), ['points' => [$q['es']->id => 1]]);

        $this->actAs($user)->get(route('lms.attempts.show', $attempt))->assertInertia(fn ($p) => $p->where('marksVisible', false));
        $this->travel(2)->days();
        $this->actAs($user)->get(route('lms.attempts.show', $attempt))->assertInertia(fn ($p) => $p->where('marksVisible', true)->where('answersVisible', true));

        $this->travelBack();
        [$scheduled] = $this->quizWithQuestions($course, ['title' => 'Scheduled', 'release_mode' => 'scheduled', 'release_at' => now()->addHours(3)->toDateTimeString()]);
        $this->assertFalse($scheduled->resultsReleased());
        $this->travel(4)->hours();
        $this->assertTrue($scheduled->fresh()->resultsReleased());
    }

    public function test_correct_answers_stay_hidden_while_another_attempt_is_still_available(): void
    {
        [$user, , $course] = $this->learnerInCourse();
        [$quiz, $q] = $this->quizWithQuestions($course, ['max_attempts' => 2]);
        $attempt = $this->startAttempt($user, $quiz);
        $this->actAs($user)->post(route('lms.attempts.submit', $attempt), ['answers' => [$q['mc']->id => '3']]);
        $this->actAs($this->teacher)->post(route('lms.attempts.grade.store', $attempt), ['points' => [$q['es']->id => 0]]);

        $this->actAs($user)->get(route('lms.attempts.show', $attempt))->assertInertia(fn ($p) => $p->where('marksVisible', true)->where('answersVisible', false)->where('items.0.correct_answer', null));
    }

    public function test_the_grade_method_picks_the_highest_latest_or_average_attempt(): void
    {
        [$user, $student, $course] = $this->learnerInCourse();
        foreach (['highest' => 80.0, 'latest' => 40.0, 'average' => 60.0] as $method => $expected) {
            $quiz = $this->quizzes->save($this->admin, $course, null, ['title' => $method, 'status' => 'draft', 'max_attempts' => 2, 'grade_method' => $method]);
            $mc = $this->quizzes->addQuestion($quiz, ['type' => 'multiple_choice', 'prompt' => 'Q1', 'points' => 5, 'options' => ['a', 'b'], 'correct_answer' => 'a']);
            $this->quizzes->addQuestion($quiz, ['type' => 'multiple_choice', 'prompt' => 'Q2', 'points' => 5, 'options' => ['a', 'b'], 'correct_answer' => 'a']);
            $this->quizzes->save($this->admin, $course, $quiz, ['title' => $method, 'status' => 'published', 'max_attempts' => 2, 'grade_method' => $method]);
            $q2 = $quiz->questions()->get()[1];

            $a1 = $this->startAttempt($user, $quiz);
            $this->actAs($user)->post(route('lms.attempts.submit', $a1), ['answers' => [$mc->id => 'a', $q2->id => 'a', ]]);   // 10/10 -> but override below
            QuizAttempt::query()->whereKey($a1->id)->update(['score' => 8]);              // 80%
            $a2 = $this->startAttempt($user, $quiz);
            $this->actAs($user)->post(route('lms.attempts.submit', $a2), ['answers' => [$mc->id => 'b', $q2->id => 'b']]);
            QuizAttempt::query()->whereKey($a2->id)->update(['score' => 4]);              // 40%

            app(\App\Support\TenantContext::class)->set($this->school);
            $this->assertSame($expected, $this->quizzes->result($quiz->fresh(), $student)['percent'], $method);
        }
    }

    // ------------------------------------------------------------------ question banks

    protected function bankWith(int $n, bool $shared = false, $owner = null): QuestionBank
    {
        $bank = QuestionBank::query()->create(['school_id' => $this->school->id, 'title' => 'Algebra', 'is_shared' => $shared, 'created_by' => ($owner ?? $this->teacher)->id]);
        for ($i = 1; $i <= $n; $i++) {
            BankQuestion::query()->create($this->quizzes->cleanQuestion(['type' => 'true_false', 'prompt' => "Statement $i", 'points' => 1, 'correct_answer' => 'true']) + ['school_id' => $this->school->id, 'question_bank_id' => $bank->id]);
        }

        return $bank;
    }

    public function test_random_bank_draws_are_snapshotted_per_attempt(): void
    {
        [$user, , $course] = $this->learnerInCourse();
        $bank = $this->bankWith(6);
        $quiz = $this->quizzes->save($this->admin, $course, null, ['title' => 'Random', 'status' => 'draft', 'max_attempts' => 2]);
        $this->actAs($this->teacher)->post(route('lms.quizzes.pulls.store', $quiz), ['question_bank_id' => $bank->id, 'count' => 7])->assertSessionHas('error', 'err.lms.pullTooMany');
        $this->actAs($this->teacher)->post(route('lms.quizzes.pulls.store', $quiz), ['question_bank_id' => $bank->id, 'count' => 3])->assertSessionHas('success', 'ok.lms.pullSaved');
        $this->quizzes->save($this->admin, $course, $quiz->fresh(), ['title' => 'Random', 'status' => 'published', 'max_attempts' => 2]);

        $attempt = $this->startAttempt($user, $quiz);
        $this->assertCount(3, $attempt->question_order);
        $this->assertSame(3, QuizQuestion::query()->where('quiz_attempt_id', $attempt->id)->count());
        $this->assertSame(3, (int) $attempt->max_score);

        BankQuestion::query()->where('question_bank_id', $bank->id)->update(['prompt' => 'EDITED']);
        $this->actAs($user)->get(route('lms.quizzes.show', $quiz))->assertInertia(fn ($p) => $p->where('mode', 'taking')->has('questions', 3));
        $this->assertSame(0, QuizQuestion::query()->where('quiz_attempt_id', $attempt->id)->where('prompt', 'EDITED')->count(), 'later bank edits do not change an issued attempt');
        $this->assertNull($quiz->fresh()->questions()->first(), 'draws never leak into the authored question list');
    }

    public function test_banks_are_private_unless_shared_and_cannot_be_deleted_while_in_use(): void
    {
        $colleague = $this->user('teacher');
        $private = $this->bankWith(2);
        $shared = $this->bankWith(2, true);

        $this->actAs($colleague)->get(route('lms.banks.show', $private))->assertNotFound();
        $this->actAs($colleague)->get(route('lms.banks.show', $shared))->assertOk()->assertInertia(fn ($p) => $p->where('bank.can_edit', false));
        $this->actAs($colleague)->put(route('lms.banks.update', $shared), ['title' => 'Mine now'])->assertForbidden();
        $this->actAs($colleague)->post(route('lms.banks.questions.store', $shared), ['type' => 'essay', 'prompt' => 'x', 'points' => 1])->assertForbidden();
        $this->actAs($colleague)->get(route('lms.banks.index'))->assertInertia(fn ($p) => $p->has('banks', 1));
        $this->actAs($this->admin)->get(route('lms.banks.index'))->assertInertia(fn ($p) => $p->has('banks', 2));

        $course = $this->course();
        $quiz = $this->quizzes->save($this->admin, $course, null, ['title' => 'Q', 'status' => 'draft']);
        $this->actAs($colleague)->post(route('lms.quizzes.pulls.store', $quiz), ['question_bank_id' => $private->id, 'count' => 1])->assertForbidden();
        $this->quizzes->addPull($this->teacher, $quiz, $private, 1);
        $this->actAs($this->teacher)->delete(route('lms.banks.destroy', $private))->assertSessionHas('error', 'err.lms.bankInUse');
        $this->actAs($this->teacher)->delete(route('lms.banks.destroy', $shared))->assertSessionHas('success', 'ok.lms.bankRemoved');
    }

    public function test_learners_cannot_open_the_question_bank_area(): void
    {
        [$user] = $this->learner();
        $bank = $this->bankWith(1, true);

        $this->actAs($user)->get(route('lms.banks.index'))->assertForbidden();
        $this->actAs($user)->get(route('lms.banks.show', $bank))->assertForbidden();
    }

    // ------------------------------------------------------------------ answer matching

    public function test_short_answer_matching_is_forgiving_about_form_but_not_about_substance(): void
    {
        $m = new AnswerMatcher;
        $this->assertTrue($m->matches('  PARIS. ', ['Paris']));
        $this->assertTrue($m->matches("New   York", ['new york']));
        $this->assertTrue($m->matches('1,5', ['1.50']), 'a decimal comma is not a wrong answer');
        $this->assertTrue($m->matches('1,234,567', ['1234567']));
        $this->assertFalse($m->matches('1,000', ['1000']), '"1,000" is 1 in French and 1000 in English - never guessed');
        $this->assertFalse($m->matches('1,000', ['1']));
        $this->assertFalse($m->matches('Parris', ['Paris']), 'spelling mistakes need a teacher');
        $this->assertFalse($m->matches('', ['Paris']));
        $this->assertFalse($m->matches('paris', ['Paris'], true), 'case-sensitive questions stay strict');
        $this->assertTrue($m->matches('Paris', ['Lyon', 'Paris']));
    }
}
