<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CourseEnrolment;
use App\Models\Examination;
use App\Models\Grade;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/** Part 16: the teacher, student and parent portals show only what the person is entitled to. */
class Phase8PortalsTest extends Phase8TestCase
{
    private User $teacher2;

    private Student $childA;

    private Student $childB;

    private User $learnerA;

    private User $parentA;

    private User $parentB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher2 = $this->user('teacher');
        $this->childA = $this->student($this->class, login: true, first: 'Ada');
        $this->childB = $this->student($this->otherClass, login: true, first: 'Bea');
        $this->learnerA = $this->childA->user;
        $this->parentA = $this->guardian([$this->childA])->user;
        $this->parentB = $this->guardian([$this->childB])->user;
    }

    private function course(string $title, string $status = 'published', ?User $teacher = null): Course
    {
        return Course::query()->create(['school_id' => $this->school->id, 'title' => $title, 'code' => strtoupper(substr($title, 0, 3)).rand(10, 99), 'status' => $status, 'teacher_id' => $teacher?->id]);
    }

    private function enrol(Course $c, Student $s, string $status = 'active'): void
    {
        CourseEnrolment::query()->create(['school_id' => $this->school->id, 'course_id' => $c->id, 'student_id' => $s->id, 'status' => $status, 'source' => 'manual', 'enrolled_at' => now()]);
    }

    private function grade(Student $s, int $total, ?bool $published, bool $withExam = true): Grade
    {
        $exam = $withExam ? Examination::query()->create(['school_id' => $this->school->id, 'name' => 'Exam '.$total, 'status' => 'approved']) : null;

        return Grade::query()->create([
            'school_id' => $this->school->id, 'examination_id' => $exam?->id, 'student_id' => $s->id, 'subject_id' => \App\Models\Subject::query()->create(['school_id' => $this->school->id, 'name' => 'Subj'.$total, 'code' => 'S'.$total])->id,
            'ca_score' => 10, 'exam_score' => $total - 10, 'total' => $total, 'letter_grade' => 'B', 'is_published' => (bool) $published,
        ]);
    }

    private function navKeys(TestResponse $r): array
    {
        return collect($r->viewData('page')['props']['nav'])->pluck('key')->all();
    }

    // ------------------------------------------------------------------ student

    public function test_a_learner_sees_only_enrolled_published_courses_and_released_results(): void
    {
        $mine = $this->course('Maths');
        $draft = $this->course('Hidden', 'draft');
        $notMine = $this->course('Physics');
        $this->enrol($mine, $this->childA);
        $this->enrol($draft, $this->childA);
        $this->enrol($notMine, $this->childB);

        $this->grade($this->childA, 71, true);                    // released exam result
        $this->grade($this->childA, 90, false);                   // exam result not released yet
        $this->grade($this->childA, 55, null, withExam: false);   // continuous assessment, shown as entered
        $this->grade($this->childB, 99, true);                    // someone else's

        $this->actAs($this->learnerA)->get(route('student.dashboard'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Student/Dashboard')
                ->where('stats.classes', 1)
                ->where('student.full_name', $this->childA->full_name)
                ->has('results', 2)
                ->where('results', fn ($r) => collect($r)->pluck('total')->map(fn ($t) => (int) $t)->sort()->values()->all() === [55, 71]));
    }

    public function test_draft_or_future_assignments_and_other_peoples_courses_never_reach_the_learner(): void
    {
        $mine = $this->course('Maths');
        $other = $this->course('Physics');
        $this->enrol($mine, $this->childA);
        $this->enrol($other, $this->childB);

        $mk = fn (Course $c, string $title, string $status, $due = null) => Assignment::query()->create(['school_id' => $this->school->id, 'course_id' => $c->id, 'title' => $title, 'status' => $status, 'due_at' => $due ?? now()->addDay()]);
        $mk($mine, 'Live homework', 'published');
        $mk($mine, 'Draft homework', 'draft');
        $mk($other, 'Not my course', 'published');
        $done = $mk($mine, 'Already handed in', 'published');
        AssignmentSubmission::query()->create(['school_id' => $this->school->id, 'assignment_id' => $done->id, 'student_id' => $this->childA->id, 'submitted_at' => now(), 'status' => 'submitted']);

        $this->actAs($this->learnerA)->get(route('student.dashboard'))
            ->assertInertia(fn (Assert $p) => $p->where('stats.pending_assignments', 1)->has('assignments', 1)->where('assignments.0.title', 'Live homework'));
    }

    public function test_a_learner_account_without_a_learner_record_sees_an_empty_portal_not_someone_elses(): void
    {
        $orphan = $this->user('student');

        $this->actAs($orphan)->get(route('student.dashboard'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('student', null)->has('results', 0)->has('assignments', 0)->where('stats.classes', 0));
    }

    // ------------------------------------------------------------------ teacher

    public function test_a_teacher_sees_only_the_assigned_class_and_subject(): void
    {
        $this->student($this->class, first: 'Cy');
        $this->student($this->otherClass, first: 'Di');

        $this->actAs($this->teacher)->get(route('teacher.dashboard'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Teacher/Dashboard')
                ->where('stats.classes', 1)->where('stats.students', 2)       // Ada (childA) + Cy; never the other class
                ->has('classes', 1)->where('classes.0.name', 'Grade 10A')
                ->has('subjects', 1)->where('subjects.0.subject', 'Mathematics'));
    }

    public function test_a_teacher_without_any_allocation_gets_empty_lists_not_the_whole_school(): void
    {
        $this->actAs($this->teacher2)->get(route('teacher.dashboard'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('stats.classes', 0)->where('stats.students', 0)->where('stats.courses', 0)
                ->has('classes', 0)->has('subjects', 0)->has('pendingItems', 0));
    }

    public function test_the_teachers_marking_queue_counts_only_their_own_courses(): void
    {
        $mine = $this->course('Maths', teacher: $this->teacher);
        $theirs = $this->course('Physics', teacher: $this->teacher2);

        foreach ([[$mine, $this->childA], [$theirs, $this->childB]] as [$course, $student]) {
            $a = Assignment::query()->create(['school_id' => $this->school->id, 'course_id' => $course->id, 'title' => 'HW '.$course->title, 'status' => 'published', 'due_at' => now()->addDay()]);
            AssignmentSubmission::query()->create(['school_id' => $this->school->id, 'assignment_id' => $a->id, 'student_id' => $student->id, 'submitted_at' => now(), 'status' => 'submitted']);
        }

        $this->actAs($this->teacher)->get(route('teacher.dashboard'))
            ->assertInertia(fn (Assert $p) => $p->where('stats.courses', 1)->where('stats.pending_marking', 1)->has('pendingItems', 1)->where('pendingItems.0.title', 'HW Maths'));
    }

    // ------------------------------------------------------------------ parent

    public function test_a_parent_sees_only_the_children_linked_to_them(): void
    {
        $this->grade($this->childA, 71, true);
        $this->grade($this->childA, 88, false);                  // not released
        $this->grade($this->childB, 99, true);
        Invoice::query()->create(['school_id' => $this->school->id, 'student_id' => $this->childA->id, 'invoice_number' => 'INV-A', 'status' => 'issued', 'total_amount' => 500, 'amount_paid' => 100, 'issued_at' => now()]);
        Invoice::query()->create(['school_id' => $this->school->id, 'student_id' => $this->childB->id, 'invoice_number' => 'INV-B', 'status' => 'issued', 'total_amount' => 900, 'amount_paid' => 0, 'issued_at' => now()]);

        $this->actAs($this->parentA)->get(route('parent.dashboard'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Parent/Dashboard')
                ->has('children', 1)->where('children.0.id', $this->childA->id)
                ->has('children.0.grades', 1)->where('children.0.grades.0.total', '71.00')
                ->has('children.0.invoices', 1)->where('children.0.invoices.0.invoice_number', 'INV-A')
                ->where('children.0.invoices.0.balance', 400));

        $this->actAs($this->parentB)->get(route('parent.dashboard'))
            ->assertInertia(fn (Assert $p) => $p->has('children', 1)->where('children.0.id', $this->childB->id)->where('children.0.invoices.0.invoice_number', 'INV-B'));
    }

    public function test_a_parent_account_without_a_guardian_record_is_refused(): void
    {
        $this->actAs($this->user('parent'))->get(route('parent.dashboard'))->assertForbidden();
    }

    public function test_a_parent_cannot_open_a_child_that_is_not_theirs(): void
    {
        $this->actAs($this->parentA)->get(route('students.show', $this->childA))->assertOk();
        $this->actAs($this->parentA)->get(route('students.show', $this->childB))->assertForbidden();
    }

    // ------------------------------------------------------------------ the home pages carry the new community features

    public function test_every_portal_home_carries_messages_announcements_and_badges(): void
    {
        $this->actAs($this->teacher)->post(route('messages.store'), ['recipient_id' => $this->parentA->id, 'body' => 'Hello', 'student_id' => $this->childA->id]);
        $this->actAs($this->admin)->post(route('announcements.store'), ['title' => 'Open day', 'body' => 'Saturday', 'audience' => 'school', 'action' => 'publish']);

        $parent = $this->actAs($this->parentA)->get(route('parent.dashboard'));
        $parent->assertInertia(fn (Assert $p) => $p->where('unreadMessages', 1)->has('announcements', 1)->where('announcements.0.title', 'Open day')
            ->where('badges.messages', 1)->where('badges.announcements', 1));
        $this->assertEqualsCanonicalizing(['dashboard', 'children', 'timetable', 'fees', 'messages', 'announcements', 'events', 'notifications'], $this->navKeys($parent));

        $teacher = $this->actAs($this->teacher)->get(route('teacher.dashboard'));
        $this->assertContains('messages', $this->navKeys($teacher));
        $this->assertContains('announcements', $this->navKeys($teacher));
        $teacher->assertInertia(fn (Assert $p) => $p->has('announcements', 1));

        $learner = $this->actAs($this->learnerA)->get(route('student.dashboard'));
        $this->assertContains('messages', $this->navKeys($learner));
        $learner->assertInertia(fn (Assert $p) => $p->has('announcements', 1)->where('stats.unread_messages', 0));
    }

    // ------------------------------------------------------------------ administration stays administrative

    public function test_only_the_right_roles_reach_accounts_communications_and_moderation(): void
    {
        foreach ([$this->teacher, $this->parentA, $this->learnerA] as $who) {
            $this->actAs($who)->get(route('accounts.index'))->assertForbidden();
            $this->actAs($who)->get(route('communications.index'))->assertForbidden();
            $this->actAs($who)->get(route('messages.moderation'))->assertForbidden();
        }

        $this->actAs($this->admin)->get(route('accounts.index'))->assertOk();
        $this->actAs($this->admin)->get(route('communications.index'))->assertOk();
        $this->actAs($this->admin)->get(route('messages.moderation'))->assertOk();
        $this->assertContains('accounts', $this->navKeys($this->actAs($this->admin)->get(route('admin.dashboard'))));
    }

    public function test_the_head_teacher_runs_communications_but_a_deputy_cannot_change_sms_settings(): void
    {
        $head = $this->user('headteacher');
        $deputy = $this->user('deputy_headteacher');

        $this->actAs($head)->get(route('communications.index'))->assertOk();
        $this->actAs($deputy)->get(route('communications.index'))->assertForbidden();
        $this->actAs($deputy)->get(route('messages.moderation'))->assertOk();
    }
}
