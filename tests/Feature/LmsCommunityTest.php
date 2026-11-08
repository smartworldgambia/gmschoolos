<?php

namespace Tests\Feature;

use App\Models\CourseAnnouncement;
use App\Models\CourseEnrolment;
use App\Models\ForumPost;
use App\Models\ForumThread;
use App\Models\Grade;
use App\Models\QuizAttempt;
use App\Notifications\SchoolAnnouncement;
use Illuminate\Support\Facades\Notification;

/** Gradebook, analytics, announcements, forum and tenant isolation of the new LMS controllers. */
class LmsCommunityTest extends Phase7TestCase
{
    /** @return array{0:\App\Models\User,1:\App\Models\Student,2:\App\Models\Course} */
    protected function enrolled(array $courseOver = [], string $first = 'Awa'): array
    {
        [$user, $student] = $this->learner(null, $first);
        $course = $this->course($courseOver);
        $this->enrol($course, $student);

        return [$user, $student, $course];
    }

    // ------------------------------------------------------------------ gradebook

    public function test_the_gradebook_combines_assignments_and_quizzes_and_learners_only_see_themselves(): void
    {
        [$u1, $s1, $course] = $this->enrolled();
        [$u2, $s2] = $this->learner();
        $this->enrol($course, $s2);
        $assignment = $this->assignment($course, ['max_score' => 20]);
        $sub = $this->assignments->submit($course, $assignment, $s1, $u1, 'My answer', []);
        $this->assignments->grade($this->teacher, $sub, 15, [], 'Good');

        $quiz = $this->quizzes->save($this->admin, $course, null, ['title' => 'Quick', 'status' => 'draft', 'max_attempts' => 1, 'release_mode' => 'immediate']);
        $q = $this->quizzes->addQuestion($quiz, ['type' => 'true_false', 'prompt' => 'Yes?', 'points' => 5, 'correct_answer' => 'true']);
        $this->quizzes->save($this->admin, $course, $quiz, ['title' => 'Quick', 'status' => 'published', 'max_attempts' => 1, 'release_mode' => 'immediate']);
        $this->actAs($u1)->post(route('lms.quizzes.start', $quiz));
        $attempt = QuizAttempt::query()->firstOrFail();
        $this->actAs($u1)->post(route('lms.attempts.submit', $attempt), ['answers' => [$q->id => 'true']]);

        $this->actAs($this->teacher)->get(route('lms.courses.gradebook', $course))->assertOk()->assertInertia(fn ($p) => $p
            ->has('rows', 2)->has('columns', 2)->where('rows.0.percent', 80)->where('rows.0.cells.a'.$assignment->id.'.score', 15)->where('rows.0.cells.q'.$quiz->id.'.percent', 100)
            ->where('rows.1.percent', null)->where('rows.1.cells.a'.$assignment->id.'.state', 'missing')->where('pending.submissions', 0)->whereNot('exportUrl', null));

        $this->actAs($u1)->get(route('lms.courses.gradebook', $course))->assertInertia(fn ($p) => $p->has('rows', 1)->where('rows.0.student.id', $s1->id)->where('exportUrl', null)->where('pending', null));
        $this->actAs($u2)->get(route('lms.courses.gradebook', $course))->assertInertia(fn ($p) => $p->has('rows', 1)->where('rows.0.student.id', $s2->id));
    }

    public function test_learners_do_not_see_withheld_quiz_marks_in_the_gradebook(): void
    {
        [$user, , $course] = $this->enrolled();
        $quiz = $this->quizzes->save($this->admin, $course, null, ['title' => 'Held', 'status' => 'draft', 'max_attempts' => 1, 'release_mode' => 'manual']);
        $q = $this->quizzes->addQuestion($quiz, ['type' => 'true_false', 'prompt' => 'Yes?', 'points' => 5, 'correct_answer' => 'true']);
        $this->quizzes->save($this->admin, $course, $quiz, ['title' => 'Held', 'status' => 'published', 'max_attempts' => 1, 'release_mode' => 'manual']);
        $this->actAs($user)->post(route('lms.quizzes.start', $quiz));
        $this->actAs($user)->post(route('lms.attempts.submit', QuizAttempt::query()->withoutGlobalScopes()->firstOrFail()), ['answers' => [$q->id => 'true']]);

        $this->actAs($user)->get(route('lms.courses.gradebook', $course))->assertInertia(fn ($p) => $p
            ->where('rows.0.cells.q'.$quiz->id.'.state', 'withheld')->where('rows.0.cells.q'.$quiz->id.'.score', null)->where('rows.0.percent', null));
        $this->actAs($this->teacher)->get(route('lms.courses.gradebook', $course))->assertInertia(fn ($p) => $p->where('rows.0.percent', 100));
    }

    public function test_only_published_examination_results_appear_next_to_the_course(): void
    {
        $subject = $this->subject();
        [, $student, $course] = $this->enrolled(['subject_id' => $subject->id]);
        Grade::query()->create(['school_id' => $this->school->id, 'student_id' => $student->id, 'subject_id' => $subject->id, 'total' => 71, 'letter_grade' => 'B', 'is_published' => false]);
        $this->actAs($this->teacher)->get(route('lms.courses.gradebook', $course))->assertInertia(fn ($p) => $p->has('rows.0.exams', 0));

        Grade::query()->where('student_id', $student->id)->update(['is_published' => true]);
        $this->actAs($this->teacher)->get(route('lms.courses.gradebook', $course))->assertInertia(fn ($p) => $p->has('rows.0.exams', 1)->where('rows.0.exams.0.letter', 'B'));
    }

    public function test_the_csv_export_is_for_teachers_and_neutralises_spreadsheet_formulas(): void
    {
        [$user, $student, $course] = $this->enrolled([], '=cmd');
        $student->update(['first_name' => '=HYPERLINK("x")']);

        $this->actAs($user)->get(route('lms.courses.gradebook.export', $course))->assertForbidden();
        $response = $this->actAs($this->teacher)->get(route('lms.courses.gradebook.export', $course))->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_teacher_feedback_is_saved_cleared_and_limited_to_enrolled_learners(): void
    {
        [$user, $student, $course] = $this->enrolled();
        [, $outsider] = $this->learner();
        $url = fn ($s) => route('lms.courses.gradebook.feedback', [$course, $s]);

        $this->actAs($this->teacher)->post($url($student), ['body' => 'Keep going'])->assertSessionHas('success', 'ok.lms.feedbackSaved');
        $this->actAs($user)->get(route('lms.courses.gradebook', $course))->assertInertia(fn ($p) => $p->where('rows.0.feedback', 'Keep going'));
        $this->actAs($user)->post($url($student), ['body' => 'self praise'])->assertForbidden();
        $this->actAs($this->teacher)->post($url($outsider), ['body' => 'x'])->assertNotFound();

        $this->actAs($this->teacher)->post($url($student), ['body' => ''])->assertSessionHas('success');
        $this->actAs($user)->get(route('lms.courses.gradebook', $course))->assertInertia(fn ($p) => $p->where('rows.0.feedback', null));
    }

    // ------------------------------------------------------------------ analytics

    public function test_the_progress_report_flags_inactive_learners_and_counts_completion(): void
    {
        [$active, $s1, $course] = $this->enrolled();
        [, $s2] = $this->learner();
        $this->enrol($course, $s2);
        $lesson = $this->lesson($course);
        $this->actAs($active)->post(route('lms.lessons.complete', $lesson))->assertRedirect();
        CourseEnrolment::query()->where('student_id', $s2->id)->update(['last_activity_at' => now()->subDays(40), 'enrolled_at' => now()->subDays(60)]);

        $this->actAs($this->teacher)->get(route('lms.courses.progress', $course))->assertOk()->assertInertia(fn ($p) => $p
            ->where('summary.enrolled', 2)->where('summary.inactive', 1)->where('summary.avg_lesson_percent', 50)
            ->where('rows.0.student.id', $s1->id)->where('rows.0.lesson_percent', 100)->where('rows.0.inactive', false)
            ->where('rows.1.lesson_percent', 0)->where('rows.1.inactive', true));

        $this->actAs($active)->get(route('lms.courses.progress', $course))->assertForbidden();
    }

    // ------------------------------------------------------------------ announcements

    public function test_announcements_notify_enrolled_learners_once_and_scheduled_ones_wait(): void
    {
        Notification::fake();
        [$user, , $course] = $this->enrolled();
        [$stranger] = $this->learner();

        $this->actAs($this->teacher)->post(route('lms.announcements.store', $course), ['title' => 'Test on Friday', 'body' => 'Chapters 1-3'])->assertSessionHas('success', 'ok.lms.announced');
        Notification::assertSentTo($user, SchoolAnnouncement::class);
        Notification::assertNotSentTo($stranger, SchoolAnnouncement::class);
        Notification::assertCount(1);

        $this->actAs($this->teacher)->post(route('lms.announcements.store', $course), ['title' => 'Later', 'body' => 'Soon', 'publish_at' => now()->addDay()->toDateTimeString()])->assertSessionHas('success');
        Notification::assertCount(1);
        $this->actAs($user)->get(route('lms.courses.announcements', $course))->assertInertia(fn ($p) => $p->has('announcements', 1)->where('announcements.0.title', 'Test on Friday')->where('announcements.0.delete_url', null));
        $this->actAs($this->teacher)->get(route('lms.courses.announcements', $course))->assertInertia(fn ($p) => $p->has('announcements', 2));

        $this->travel(2)->days();
        $this->artisan('schoolos:lms:announce')->assertExitCode(0);
        $this->artisan('schoolos:lms:announce')->assertExitCode(0);
        Notification::assertCount(2);
        $this->actAs($user)->get(route('lms.courses.announcements', $course))->assertInertia(fn ($p) => $p->has('announcements', 2));
    }

    public function test_learners_cannot_post_or_delete_announcements(): void
    {
        [$user, , $course] = $this->enrolled();
        $a = CourseAnnouncement::query()->create(['school_id' => $this->school->id, 'course_id' => $course->id, 'user_id' => $this->teacher->id, 'title' => 'T', 'body' => 'B']);

        $this->actAs($user)->post(route('lms.announcements.store', $course), ['title' => 'x', 'body' => 'y'])->assertForbidden();
        $this->actAs($user)->delete(route('lms.announcements.destroy', $a))->assertForbidden();
        $this->actAs($this->teacher)->delete(route('lms.announcements.destroy', $a))->assertSessionHas('success', 'ok.lms.announcementRemoved');
    }

    // ------------------------------------------------------------------ forum

    public function test_learners_ask_questions_and_the_asker_or_teacher_marks_the_answer(): void
    {
        [$asker, , $course] = $this->enrolled();
        [$peer, $peerStudent] = $this->learner();
        $this->enrol($course, $peerStudent);

        $this->actAs($asker)->post(route('lms.threads.store', $course), ['type' => 'question', 'title' => 'How do I factorise?', 'body' => 'x^2-1'])->assertRedirect();
        $thread = ForumThread::query()->firstOrFail();
        $this->actAs($peer)->post(route('lms.threads.reply', $thread), ['body' => '(x-1)(x+1)'])->assertSessionHas('success', 'ok.lms.replied');
        $post = ForumPost::query()->firstOrFail();

        $this->actAs($peer)->post(route('lms.threads.answer', [$thread, $post]))->assertForbidden();
        $this->actAs($asker)->post(route('lms.threads.answer', [$thread, $post]))->assertSessionHas('success', 'ok.lms.answerMarked');
        $this->assertSame($post->id, (int) $thread->fresh()->answer_post_id);
        $this->actAs($this->teacher)->post(route('lms.threads.answer', [$thread, $post]))->assertSessionHas('success');
        $this->assertNull($thread->fresh()->answer_post_id, 'marking again un-marks');

        $this->actAs($asker)->get(route('lms.courses.forum', $course))->assertInertia(fn ($p) => $p->has('threads', 1)->where('threads.0.replies', 1));
    }

    public function test_locked_threads_refuse_replies_and_hidden_posts_are_masked_for_learners(): void
    {
        [$user, , $course] = $this->enrolled();
        $this->actAs($user)->post(route('lms.threads.store', $course), ['type' => 'discussion', 'title' => 'Topic', 'body' => 'b']);
        $thread = ForumThread::query()->firstOrFail();
        $this->actAs($user)->post(route('lms.threads.reply', $thread), ['body' => 'rude words']);
        $post = ForumPost::query()->firstOrFail();

        $this->actAs($user)->post(route('lms.posts.hide', $post))->assertForbidden();
        $this->actAs($user)->post(route('lms.threads.lock', $thread))->assertForbidden();
        $this->actAs($user)->post(route('lms.threads.pin', $thread))->assertForbidden();

        $this->actAs($this->teacher)->post(route('lms.posts.hide', $post))->assertSessionHas('success', 'ok.lms.postModerated');
        $this->actAs($user)->get(route('lms.threads.show', $thread))->assertInertia(fn ($p) => $p->where('posts.0.hidden', true)->where('posts.0.body', null));
        $this->actAs($this->teacher)->get(route('lms.threads.show', $thread))->assertInertia(fn ($p) => $p->where('posts.0.body', 'rude words'));

        $this->actAs($this->teacher)->post(route('lms.threads.lock', $thread))->assertSessionHas('success');
        $this->actAs($user)->post(route('lms.threads.reply', $thread), ['body' => 'one more'])->assertSessionHas('error', 'err.lms.threadLocked');
        $this->assertSame(1, ForumPost::query()->count());
    }

    public function test_the_forum_is_closed_to_people_who_are_not_in_the_course(): void
    {
        [, , $course] = $this->enrolled();
        [$outsider] = $this->learner();
        $otherTeacher = $this->user('teacher');
        $this->actAs($this->teacher)->post(route('lms.threads.store', $course), ['type' => 'discussion', 'title' => 'T', 'body' => 'b']);
        $thread = ForumThread::query()->firstOrFail();

        foreach ([$outsider, $otherTeacher] as $who) {
            $this->actAs($who)->get(route('lms.courses.forum', $course))->assertForbidden();
            $this->actAs($who)->get(route('lms.threads.show', $thread))->assertForbidden();
            $this->actAs($who)->post(route('lms.threads.reply', $thread), ['body' => 'hi'])->assertForbidden();
            $this->actAs($who)->post(route('lms.threads.store', $course), ['type' => 'discussion', 'title' => 'x', 'body' => 'y'])->assertForbidden();
            $this->actAs($who)->get(route('lms.courses.announcements', $course))->assertForbidden();
            $this->actAs($who)->get(route('lms.courses.gradebook', $course))->assertForbidden();
        }
        $this->assertSame(0, ForumPost::query()->count());
    }

    // ------------------------------------------------------------------ tenant isolation

    public function test_other_schools_cannot_reach_any_of_these_records(): void
    {
        [, , $course] = $this->enrolled();
        $quiz = $this->quizzes->save($this->admin, $course, null, ['title' => 'Q', 'status' => 'draft']);
        $question = $this->quizzes->addQuestion($quiz, ['type' => 'essay', 'prompt' => 'x', 'points' => 1]);
        $this->actAs($this->teacher)->post(route('lms.threads.store', $course), ['type' => 'discussion', 'title' => 'T', 'body' => 'b']);
        $thread = ForumThread::query()->firstOrFail();
        $announcement = CourseAnnouncement::query()->create(['school_id' => $this->school->id, 'course_id' => $course->id, 'user_id' => $this->teacher->id, 'title' => 'T', 'body' => 'B']);

        $beta = $this->otherSchool();
        $stranger = $this->user('school_admin', $beta);
        $routes = [
            ['get', route('lms.courses.gradebook', $course)], ['get', route('lms.courses.progress', $course)], ['get', route('lms.courses.gradebook.export', $course)],
            ['get', route('lms.courses.forum', $course)], ['get', route('lms.threads.show', $thread)], ['post', route('lms.threads.lock', $thread)],
            ['get', route('lms.quizzes.edit', $quiz)], ['get', route('lms.quizzes.attempts', $quiz)], ['put', route('lms.questions.update', $question)],
            ['delete', route('lms.announcements.destroy', $announcement)], ['get', route('lms.courses.announcements', $course)],
        ];
        foreach ($routes as [$method, $url]) {
            $this->actAs($stranger)->{$method}($url)->assertNotFound();
        }
        $this->assertSame(1, CourseAnnouncement::query()->withoutGlobalScopes()->count());
    }
}
