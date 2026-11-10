<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CourseEnrolment;
use App\Models\LessonCompletion;
use App\Models\LmsFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Courses, enrolment, lessons, materials, assignments and tenant isolation of the native LMS. */
class LmsCoursesTest extends Phase7TestCase
{
    // ------------------------------------------------------------------ courses and access

    public function test_manager_creates_a_course_and_the_class_audience_enrols_active_learners(): void
    {
        [, $a] = $this->learner();
        [, $b] = $this->learner();
        $other = $this->classNamed('Grade 11A');
        [, $c] = $this->learner($other);

        $this->actAs($this->admin)->post(route('lms.courses.store'), [
            'title' => 'Biology', 'status' => 'published', 'teacher_id' => $this->teacher->id, 'audience_class_ids' => [$this->class->id], 'objectives' => ['Cells', ''],
        ])->assertRedirect();

        $course = Course::query()->where('title', 'Biology')->firstOrFail();
        $this->assertSame($this->teacher->id, (int) $course->teacher_id);
        $this->assertSame(['Cells'], $course->objectives);
        $enrolled = CourseEnrolment::query()->where('course_id', $course->id)->where('status', 'active')->pluck('student_id')->all();
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $enrolled);
        $this->assertNotContains($c->id, $enrolled);
        $this->assertSame('class', CourseEnrolment::query()->where('student_id', $a->id)->value('source'));
    }

    public function test_an_allocated_subject_appears_as_the_teachers_lms_course(): void
    {
        [, $learner] = $this->learner();
        $maths = $this->subject('Mathematics');
        $this->allocate($this->teacher, $maths);

        $this->actAs($this->teacher)->get(route('lms.courses.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->where('mode', 'author')
                ->has('courses', 1)
                ->where('courses.0.title', 'Mathematics')
                ->where('courses.0.subject', 'Mathematics')
                ->where('courses.0.teacher', $this->teacher->name));

        app(\App\Support\TenantContext::class)->set($this->school);
        $course = Course::query()->where('subject_id', $maths->id)->firstOrFail();
        $this->assertSame($this->teacher->id, (int) $course->teacher_id);
        $this->assertSame('published', $course->status);
        $this->assertTrue(\App\Models\CourseEnrolment::query()->where('course_id', $course->id)->where('student_id', $learner->id)->where('status', 'active')->exists());

        $this->actAs($this->teacher)->get(route('teacher.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->where('stats.courses', 1));
    }

    public function test_a_teacher_may_only_create_courses_for_subjects_they_are_allocated_to(): void
    {
        $maths = $this->subject('Mathematics');
        $art = $this->subject('Art');
        $this->allocate($this->teacher, $maths);

        $this->actAs($this->teacher)->post(route('lms.courses.store'), ['title' => 'Art 1', 'status' => 'draft', 'subject_id' => $art->id])
            ->assertSessionHas('error', 'err.lms.notAllocated');
        $this->actAs($this->teacher)->post(route('lms.courses.store'), ['title' => 'No subject', 'status' => 'draft'])
            ->assertSessionHas('error', 'err.lms.notAllocated');

        $this->actAs($this->teacher)->post(route('lms.courses.store'), ['title' => 'Maths 1', 'status' => 'draft', 'subject_id' => $maths->id, 'teacher_id' => $this->admin->id])
            ->assertSessionHas('success', 'ok.lms.courseSaved');
        app(\App\Support\TenantContext::class)->set($this->school);
        $course = Course::query()->where('title', 'Maths 1')->firstOrFail();
        $this->assertSame($this->teacher->id, (int) $course->teacher_id, 'a teacher cannot hand the course to someone else or claim another teacher');
    }

    public function test_only_enrolled_learners_reach_a_published_course(): void
    {
        [$in, $s1] = $this->learner();
        [$out] = $this->learner();
        $course = $this->course();
        $this->enrol($course, $s1);

        $this->actAs($in)->get(route('lms.courses.show', $course))->assertOk()->assertInertia(fn ($p) => $p->component('Lms/CourseShow')->where('role', 'learner')->where('author', false));
        $this->actAs($out)->get(route('lms.courses.show', $course))->assertForbidden();

        $draft = $this->course(['title' => 'Draft course', 'status' => 'draft']);
        $this->enrol($draft, $s1);
        $this->actAs($in)->get(route('lms.courses.show', $draft))->assertForbidden();

        $this->courses->withdraw(CourseEnrolment::query()->where('course_id', $course->id)->first());
        $this->actAs($in)->get(route('lms.courses.show', $course))->assertForbidden();
    }

    public function test_learners_only_list_their_own_published_courses(): void
    {
        [$in, $s1] = $this->learner();
        $mine = $this->course(['title' => 'Mine']);
        $this->course(['title' => 'Not mine']);
        $this->enrol($mine, $s1);

        $this->actAs($in)->get(route('lms.courses.index'))->assertOk()->assertInertia(fn ($p) => $p->where('mode', 'learner')->has('courses', 1)->where('courses.0.title', 'Mine'));
    }

    public function test_a_teacher_cannot_edit_or_see_the_people_of_another_teachers_course(): void
    {
        $course = $this->course();
        $colleague = $this->user('teacher');

        $this->actAs($colleague)->get(route('lms.courses.edit', $course))->assertForbidden();
        $this->actAs($colleague)->get(route('lms.courses.people', $course))->assertForbidden();
        $this->actAs($this->teacher)->get(route('lms.courses.people', $course))->assertOk();
    }

    public function test_changing_the_audience_withdraws_class_enrolments_but_keeps_manual_ones(): void
    {
        [, $a] = $this->learner();
        [, $b] = $this->learner();
        $course = $this->course(['audience_class_ids' => [$this->class->id]]);
        $this->courses->withdraw(CourseEnrolment::query()->where('student_id', $b->id)->first());
        $this->courses->enrol($this->admin, $course, [$b->id]);   // manual again
        $this->courses->setAudience($course, []);

        $this->assertSame('withdrawn', CourseEnrolment::query()->where('student_id', $a->id)->value('status'));
        $this->assertSame('active', CourseEnrolment::query()->where('student_id', $b->id)->value('status'));
    }

    // ------------------------------------------------------------------ lessons

    public function test_scheduled_and_class_restricted_lessons_are_hidden_from_learners(): void
    {
        [$user, $student] = $this->learner();
        $course = $this->course();
        $this->enrol($course, $student);
        $future = $this->lesson($course, ['title' => 'Later', 'publish_at' => now()->addDay()->toDateTimeString()]);
        $draft = $this->lesson($course, ['title' => 'Draft', 'status' => 'draft']);
        $other = $this->classNamed('Grade 11A');
        $restricted = $this->lesson($course, ['title' => 'Other class only', 'audience_class_ids' => [$other->id]]);
        $open = $this->lesson($course, ['title' => 'Open']);

        foreach ([$future, $draft, $restricted] as $hidden) {
            $this->actAs($user)->get(route('lms.lessons.show', $hidden))->assertNotFound();
            $this->actAs($user)->post(route('lms.lessons.complete', $hidden))->assertNotFound();
        }
        $this->actAs($user)->get(route('lms.lessons.show', $open))->assertOk();
        $this->actAs($user)->get(route('lms.courses.show', $course))->assertInertia(fn ($p) => $p->has('looseLessons', 1)->where('looseLessons.0.title', 'Open'));

        $this->travel(2)->days();
        $this->actAs($user)->get(route('lms.lessons.show', $future))->assertOk();
        $this->actAs($this->teacher)->get(route('lms.lessons.show', $draft))->assertOk();   // authors preview drafts
    }

    public function test_completing_lessons_tracks_progress_and_is_idempotent(): void
    {
        [$user, $student] = $this->learner();
        $course = $this->course();
        $this->enrol($course, $student);
        $one = $this->lesson($course, ['title' => 'One']);
        $this->lesson($course, ['title' => 'Two']);

        $this->actAs($user)->post(route('lms.lessons.complete', $one))->assertSessionHas('success', 'ok.lms.lessonCompleted');
        $this->actAs($user)->post(route('lms.lessons.complete', $one));
        $this->assertSame(1, LessonCompletion::query()->where('student_id', $student->id)->count());
        $this->actAs($user)->get(route('lms.courses.show', $course))->assertInertia(fn ($p) => $p->where('progressPercent', 50));
    }

    public function test_staff_cannot_complete_lessons_as_learners(): void
    {
        $course = $this->course();
        $lesson = $this->lesson($course);

        $this->actAs($this->teacher)->post(route('lms.lessons.complete', $lesson))->assertForbidden();
        $this->actAs($this->admin)->post(route('lms.lessons.complete', $lesson))->assertForbidden();
    }

    public function test_teacher_authors_a_lesson_over_http(): void
    {
        $course = $this->course();

        $this->actAs($this->teacher)->post(route('lms.lessons.store', $course), ['title' => 'Fractions', 'content_type' => 'text', 'content' => 'Intro', 'status' => 'draft'])
            ->assertSessionHas('success', 'ok.lms.lessonSaved');
        $this->actAs($this->teacher)->post(route('lms.lessons.store', $course), ['title' => '', 'content_type' => 'text', 'status' => 'draft'])->assertSessionHasErrors('title');
        $this->actAs($this->teacher)->post(route('lms.lessons.store', $course), ['title' => 'X', 'content_type' => 'exe', 'status' => 'draft'])->assertSessionHasErrors('content_type');
        $this->actAs($this->teacher)->post(route('lms.lessons.store', $course), ['title' => 'Bad link', 'content_type' => 'link', 'status' => 'draft', 'course_section_id' => 99999])
            ->assertSessionHas('error', 'err.lms.sectionInvalid');
    }

    // ------------------------------------------------------------------ materials

    public function test_materials_live_on_the_private_disk_and_follow_lesson_visibility(): void
    {
        Storage::fake('local');
        [$user, $student] = $this->learner();
        [$outsider] = $this->learner();
        $course = $this->course();
        $this->enrol($course, $student);
        $lesson = $this->lesson($course);
        $draft = $this->lesson($course, ['title' => 'Draft', 'status' => 'draft']);

        foreach ([$lesson, $draft] as $l) {
            $this->actAs($this->teacher)->post(route('lms.lessons.materials.store', $l), ['type' => 'file', 'file' => UploadedFile::fake()->create('notes.pdf', 20, 'application/pdf')])
                ->assertSessionHas('success', 'ok.lms.materialAdded');
        }
        $file = LmsFile::query()->where('owner_id', $lesson->id)->firstOrFail();
        $draftFile = LmsFile::query()->where('owner_id', $draft->id)->firstOrFail();
        Storage::disk('local')->assertExists($file->path);
        $this->assertStringNotContainsString('notes', $file->path, 'stored under a random name');

        $this->actAs($user)->get(route('lms.files.show', $file))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actAs($user)->get(route('lms.files.show', $draftFile))->assertNotFound();
        $this->actAs($outsider)->get(route('lms.files.show', $file))->assertForbidden();
        $this->actAs($user)->delete(route('lms.files.destroy', $file))->assertForbidden();
    }

    public function test_material_uploads_reject_executables_and_unsafe_links(): void
    {
        Storage::fake('local');
        $course = $this->course();
        $lesson = $this->lesson($course);

        $this->actAs($this->teacher)->post(route('lms.lessons.materials.store', $lesson), ['type' => 'file', 'file' => UploadedFile::fake()->create('run.php', 5, 'application/x-php')])->assertSessionHasErrors('file');
        $this->actAs($this->teacher)->post(route('lms.lessons.materials.store', $lesson), ['type' => 'link', 'url' => 'javascript:alert(1)'])->assertSessionHasErrors('url');
        $this->actAs($this->teacher)->post(route('lms.lessons.materials.store', $lesson), ['type' => 'link', 'url' => 'https://example.org/video'])->assertSessionHas('success', 'ok.lms.materialAdded');
        $this->actAs($this->teacher)->post(route('lms.lessons.materials.store', $lesson), ['type' => 'file'])->assertSessionHas('error', 'err.lms.materialEmpty');
    }

    // ------------------------------------------------------------------ assignments

    public function test_learner_submits_a_text_assignment_and_the_teacher_grades_it_with_feedback(): void
    {
        [$user, $student] = $this->learner();
        $course = $this->course();
        $this->enrol($course, $student);
        $assignment = $this->assignment($course);

        $this->actAs($user)->post(route('lms.assignments.submit', $assignment), ['body' => 'My answer'])->assertSessionHas('success', 'ok.lms.submitted');
        $sub = AssignmentSubmission::query()->firstOrFail();
        $this->assertFalse((bool) $sub->is_late);
        $this->assertSame('submitted', $sub->status);

        $this->actAs($user)->post(route('lms.submissions.grade', $sub), ['score' => 9])->assertForbidden();
        $this->actAs($this->teacher)->post(route('lms.submissions.grade', $sub), ['score' => 11])->assertSessionHas('error', 'err.lms.scoreRange');
        $this->actAs($this->teacher)->post(route('lms.submissions.grade', $sub), ['score' => 8.5, 'feedback' => 'Good work'])->assertSessionHas('success', 'ok.lms.graded');

        $sub->refresh();
        $this->assertSame('graded', $sub->status);
        $this->assertSame(8.5, (float) $sub->score);
        $this->assertSame($this->teacher->id, (int) $sub->graded_by);

        $this->actAs($user)->get(route('lms.assignments.show', $assignment))->assertInertia(fn ($p) => $p->where('mine.0.score', 8.5)->where('mine.0.feedback', 'Good work'));
    }

    public function test_late_submissions_are_flagged_or_blocked_by_policy_and_attempts_are_limited(): void
    {
        [$user, $student] = $this->learner();
        $course = $this->course();
        $this->enrol($course, $student);
        $late = $this->assignment($course, ['title' => 'Late ok', 'due_at' => now()->subDay()->toDateTimeString(), 'late_policy' => 'allow']);
        $strict = $this->assignment($course, ['title' => 'Strict', 'due_at' => now()->subDay()->toDateTimeString(), 'late_policy' => 'block']);
        $closed = $this->assignment($course, ['title' => 'Closed', 'closes_at' => now()->subHour()->toDateTimeString()]);

        $this->actAs($user)->post(route('lms.assignments.submit', $late), ['body' => 'x'])->assertSessionHas('success');
        $this->assertTrue((bool) AssignmentSubmission::query()->where('assignment_id', $late->id)->value('is_late'));
        $this->actAs($user)->post(route('lms.assignments.submit', $late), ['body' => 'again'])->assertSessionHas('error', 'err.lms.noMoreAttempts');
        $this->actAs($user)->post(route('lms.assignments.submit', $strict), ['body' => 'x'])->assertSessionHas('error', 'err.lms.lateBlocked');
        $this->actAs($user)->post(route('lms.assignments.submit', $closed), ['body' => 'x'])->assertSessionHas('error', 'err.lms.assignmentClosed');
    }

    public function test_resubmission_needs_the_teacher_to_return_the_work_and_empty_work_is_refused(): void
    {
        [$user, $student] = $this->learner();
        $course = $this->course();
        $this->enrol($course, $student);
        $a = $this->assignment($course, ['max_attempts' => 2]);

        $this->actAs($user)->post(route('lms.assignments.submit', $a), ['body' => '   '])->assertSessionHas('error', 'err.lms.emptySubmission');
        $this->actAs($user)->post(route('lms.assignments.submit', $a), ['body' => 'first']);
        $first = AssignmentSubmission::query()->firstOrFail();
        $this->actAs($this->teacher)->post(route('lms.submissions.grade', $first), ['score' => 4]);
        $this->actAs($user)->post(route('lms.assignments.submit', $a), ['body' => 'second'])->assertSessionHas('error', 'err.lms.alreadyGraded');

        $this->actAs($this->teacher)->post(route('lms.submissions.return', $first), ['feedback' => 'Please expand'])->assertSessionHas('success', 'ok.lms.returned');
        $this->actAs($user)->post(route('lms.assignments.submit', $a), ['body' => 'second'])->assertSessionHas('success', 'ok.lms.submitted');
        $this->assertSame(2, AssignmentSubmission::query()->where('student_id', $student->id)->count());
        $this->assertSame('returned', $first->fresh()->status);
    }

    public function test_rubric_scores_must_total_the_maximum_and_sum_into_the_grade(): void
    {
        [$user, $student] = $this->learner();
        $course = $this->course();
        $this->enrol($course, $student);

        $this->actAs($this->teacher)->post(route('lms.assignments.store', $course), [
            'title' => 'Bad rubric', 'max_score' => 10, 'status' => 'published', 'late_policy' => 'allow', 'max_attempts' => 1, 'accepts_text' => true,
            'criteria' => [['label' => 'Ideas', 'max_points' => 4], ['label' => 'Style', 'max_points' => 4]],
        ])->assertSessionHas('error', 'err.lms.rubricTotal');
        $this->actAs($this->teacher)->post(route('lms.assignments.store', $course), [
            'title' => 'Good rubric', 'max_score' => 10, 'status' => 'published', 'late_policy' => 'allow', 'max_attempts' => 1, 'accepts_text' => true,
            'criteria' => [['label' => 'Ideas', 'max_points' => 6], ['label' => 'Style', 'max_points' => 4]],
        ])->assertRedirect();
        $a = Assignment::query()->where('title', 'Good rubric')->firstOrFail();

        $this->actAs($user)->post(route('lms.assignments.submit', $a), ['body' => 'work']);
        $sub = AssignmentSubmission::query()->firstOrFail();
        $this->actAs($this->teacher)->post(route('lms.submissions.grade', $sub), ['rubric_scores' => [7, 3]])->assertSessionHas('error', 'err.lms.rubricScore');
        $this->actAs($this->teacher)->post(route('lms.submissions.grade', $sub), ['score' => 10, 'rubric_scores' => [5, 3]])->assertSessionHas('success', 'ok.lms.graded');
        $this->assertSame(8.0, (float) $sub->fresh()->score, 'the typed score is ignored when a rubric exists');
    }

    public function test_unpublished_or_scheduled_assignments_are_not_visible_or_submittable(): void
    {
        [$user, $student] = $this->learner();
        $course = $this->course();
        $this->enrol($course, $student);
        $draft = $this->assignment($course, ['status' => 'draft']);
        $later = $this->assignment($course, ['publish_at' => now()->addDay()->toDateTimeString()]);

        foreach ([$draft, $later] as $a) {
            $this->actAs($user)->get(route('lms.assignments.show', $a))->assertNotFound();
            $this->actAs($user)->post(route('lms.assignments.submit', $a), ['body' => 'x'])->assertNotFound();
        }
    }

    public function test_assignment_attachments_and_submission_files_are_private_to_the_right_people(): void
    {
        Storage::fake('local');
        [$u1, $s1] = $this->learner();
        [$u2, $s2] = $this->learner();
        $course = $this->course();
        $this->enrol($course, $s1, $s2);
        $a = $this->assignment($course, ['accepts_files' => true]);

        $this->actAs($u1)->post(route('lms.assignments.submit', $a), ['files' => [UploadedFile::fake()->create('work.pdf', 30, 'application/pdf')]])->assertSessionHas('success');
        $file = LmsFile::query()->where('owner_type', 'submission')->firstOrFail();

        $this->actAs($u1)->get(route('lms.files.show', $file))->assertOk();
        $this->actAs($u2)->get(route('lms.files.show', $file))->assertNotFound();
        $this->actAs($this->teacher)->get(route('lms.files.show', $file))->assertOk();
        $this->actAs($u1)->post(route('lms.assignments.submit', $a), ['files' => [UploadedFile::fake()->create('x.exe', 5, 'application/x-msdownload')]])->assertSessionHasErrors('files.0');
    }

    public function test_learners_never_see_the_marking_roster_or_other_learners_work(): void
    {
        [$u1, $s1] = $this->learner();
        [$u2, $s2] = $this->learner();
        $course = $this->course();
        $this->enrol($course, $s1, $s2);
        $a = $this->assignment($course);
        $this->actAs($u1)->post(route('lms.assignments.submit', $a), ['body' => 'secret']);
        $sub = AssignmentSubmission::query()->firstOrFail();

        $this->actAs($u2)->get(route('lms.assignments.show', $a))->assertInertia(fn ($p) => $p->where('roster', [])->has('mine', 0));
        $this->actAs($u2)->get(route('lms.submissions.show', $sub))->assertForbidden();
        $this->actAs($this->teacher)->get(route('lms.assignments.show', $a))->assertInertia(fn ($p) => $p->has('roster', 2));
    }

    // ------------------------------------------------------------------ tenant isolation

    public function test_another_schools_users_cannot_reach_any_lms_object(): void
    {
        [, $s1] = $this->learner();
        $course = $this->course();
        $this->enrol($course, $s1);
        $lesson = $this->lesson($course);
        $assignment = $this->assignment($course);
        $quiz = $this->quizzes->save($this->admin, $course, null, ['title' => 'Q', 'status' => 'draft']);
        $this->quizzes->addQuestion($quiz, ['type' => 'true_false', 'prompt' => 'Sky is blue', 'points' => 1, 'correct_answer' => 'true']);

        $beta = $this->otherSchool();
        app(\App\Support\TenantContext::class)->clear();
        $outsider = $this->user('school_admin', $beta);
        $coach = $this->user('teacher', $beta);

        foreach ([$outsider, $coach] as $who) {
            foreach ([route('lms.courses.show', $course), route('lms.courses.edit', $course), route('lms.lessons.show', $lesson), route('lms.assignments.show', $assignment),
                route('lms.quizzes.show', $quiz), route('lms.quizzes.edit', $quiz), route('lms.courses.gradebook', $course), route('lms.courses.progress', $course)] as $url) {
                $this->actAs($who)->get($url)->assertNotFound();
            }
        }
        $this->actAs($outsider)->post(route('lms.courses.enrol', $course), ['student_ids' => [$s1->id]])->assertNotFound();
        $this->actAs($outsider)->get(route('lms.courses.index'))->assertInertia(fn ($p) => $p->has('courses', 0));
    }

    public function test_bound_lms_routes_resolve_when_no_tenant_is_pre_bound(): void
    {
        $course = $this->course();
        $lesson = $this->lesson($course);

        $this->actAs($this->admin)->get(route('lms.courses.show', $course))->assertOk();
        $this->actAs($this->admin)->get(route('lms.lessons.show', $lesson))->assertOk();
    }
}
