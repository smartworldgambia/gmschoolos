<?php

namespace Tests\Feature;

use App\Models\Lesson;

/** External content links: same access rules as the lesson itself, plain web links only, and no SCORM/LTI claims. */
class LmsExternalLinkTest extends Phase7TestCase
{
    protected function linked(string $url, array $over = []): Lesson
    {
        $course = $this->course();
        $lesson = $this->lesson($course, $over);
        $lesson->forceFill(['external_launch_url' => $url])->save();

        return $lesson;
    }

    public function test_enrolled_learners_and_course_teachers_can_open_a_link_but_outsiders_cannot(): void
    {
        $lesson = $this->linked('https://tool.example/launch');
        [$in, $student] = $this->learner();
        $this->enrol($lesson->course, $student);
        [$out] = $this->learner();
        $otherTeacher = $this->user('teacher');

        $this->actAs($in)->get(route('lms.external.launch', $lesson))->assertOk()->assertInertia(fn ($p) => $p->where('launchUrl', 'https://tool.example/launch'));
        $this->actAs($this->teacher)->get(route('lms.external.launch', $lesson))->assertOk();
        $this->actAs($out)->get(route('lms.external.launch', $lesson))->assertForbidden();
        $this->actAs($otherTeacher)->get(route('lms.external.launch', $lesson))->assertForbidden();
    }

    public function test_a_draft_lesson_link_is_not_reachable_by_learners(): void
    {
        $lesson = $this->linked('https://tool.example/launch', ['status' => 'draft']);
        [$user, $student] = $this->learner();
        $this->enrol($lesson->course, $student);

        $this->actAs($user)->get(route('lms.external.launch', $lesson))->assertNotFound();
        $this->actAs($this->teacher)->get(route('lms.external.launch', $lesson))->assertOk();
    }

    public function test_only_http_links_are_stored_or_launched(): void
    {
        $this->actAs($this->admin)->post(route('lms.external.tools.store'), ['name' => 'Bad', 'launch_url' => 'javascript:alert(1)'])->assertSessionHasErrors('launch_url');
        $this->actAs($this->admin)->post(route('lms.external.packages.store'), ['title' => 'Bad', 'version' => '1.2', 'entry_point' => 'data:text/html,x'])->assertSessionHasErrors('entry_point');
        $this->actAs($this->admin)->post(route('lms.external.tools.store'), ['name' => 'Good', 'launch_url' => 'https://tool.example/x'])->assertSessionHas('success', 'ok.lms.extToolSaved');

        // a value that slipped into the database some other way is still never put into a link
        $lesson = $this->linked('javascript:alert(1)');
        $this->actAs($this->teacher)->get(route('lms.external.launch', $lesson))->assertRedirect()->assertSessionHas('error', 'err.lms.extNoUrl');
    }

    public function test_the_links_page_is_for_staff_and_the_nav_no_longer_advertises_scorm(): void
    {
        [$user] = $this->learner();
        $this->actAs($user)->get(route('lms.external.index'))->assertForbidden();
        $this->actAs($this->admin)->get(route('lms.external.index'))->assertOk();

        $nav = collect(\App\Support\SchoolNav::for($this->admin))->pluck('key')->all();
        $this->assertContains('externalContent', $nav);
        $this->assertNotContains('scorm', $nav);
    }
}
