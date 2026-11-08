<?php

namespace App\Services\Lms;

use App\Exceptions\AcademicRuleException;
use App\Models\Course;
use App\Models\CourseAnnouncement;
use App\Models\CourseEnrolment;
use App\Models\CourseFeedback;
use App\Models\ForumPost;
use App\Models\ForumThread;
use App\Models\Student;
use App\Models\User;
use App\Notifications\SchoolAnnouncement;
use Illuminate\Support\Facades\Notification;

/** Course announcements, discussion forums / student questions, and per-learner teacher feedback. */
class CommunityService
{
    // ------------------------------------------------------------------ announcements

    public function announce(User $author, Course $course, array $data): CourseAnnouncement
    {
        $publishAt = $data['publish_at'] ?? null;
        $announcement = CourseAnnouncement::query()->create([
            'school_id' => $course->school_id, 'course_id' => $course->id, 'user_id' => $author->id,
            'title' => $data['title'], 'body' => $data['body'], 'publish_at' => $publishAt, 'is_pinned' => (bool) ($data['is_pinned'] ?? false),
        ]);
        if ($publishAt === null || strtotime((string) $publishAt) <= time()) {
            $this->notify($announcement);
        }

        return $announcement;
    }

    /** Send queued notifications to the enrolled learners (once). Scheduled announcements are picked up by `schoolos:lms:announce`. */
    public function notify(CourseAnnouncement $announcement): int
    {
        if ($announcement->notified_at !== null) {
            return 0;
        }
        $course = $announcement->course;
        $userIds = Student::query()->whereIn('id', CourseEnrolment::query()->where('course_id', $course->id)->where('status', 'active')->pluck('student_id'))
            ->whereNotNull('user_id')->pluck('user_id');
        $users = User::query()->whereIn('id', $userIds)->get();
        if ($users->isNotEmpty()) {
            Notification::send($users, new SchoolAnnouncement($announcement->title, mb_substr($announcement->body, 0, 400), route('lms.courses.announcements', $course, false), 'info', 'lms'));
        }
        $announcement->forceFill(['notified_at' => now()])->save();

        return $users->count();
    }

    public function notifyDue(): int
    {
        $sent = 0;
        foreach (CourseAnnouncement::query()->withoutGlobalScopes()->whereNull('notified_at')->whereNotNull('publish_at')->where('publish_at', '<=', now())->get() as $a) {
            $sent += app(\App\Support\TenantContext::class)->runForSchool($a->school, fn () => $this->notify($a->unsetRelation('course')));
        }

        return $sent;
    }

    // ------------------------------------------------------------------ forum

    public function startThread(User $user, Course $course, array $data): ForumThread
    {
        $thread = ForumThread::query()->create([
            'school_id' => $course->school_id, 'course_id' => $course->id, 'user_id' => $user->id,
            'type' => in_array($data['type'] ?? null, ['discussion', 'question'], true) ? $data['type'] : 'discussion',
            'title' => $data['title'], 'body' => $data['body'], 'last_post_at' => now(),
        ]);

        return $thread;
    }

    public function reply(User $user, ForumThread $thread, string $body): ForumPost
    {
        if ($thread->is_locked) {
            throw AcademicRuleException::because('err.lms.threadLocked');
        }
        $post = ForumPost::query()->create(['school_id' => $thread->school_id, 'forum_thread_id' => $thread->id, 'user_id' => $user->id, 'body' => $body]);
        $thread->forceFill(['last_post_at' => now()])->save();

        return $post;
    }

    public function markAnswer(ForumThread $thread, ForumPost $post): void
    {
        if ($thread->type !== 'question' || (int) $post->forum_thread_id !== (int) $thread->id || $post->hidden_at) {
            throw AcademicRuleException::because('err.lms.answerInvalid');
        }
        $thread->forceFill(['answer_post_id' => $thread->answer_post_id === $post->id ? null : $post->id])->save();
    }

    public function hidePost(User $by, ForumPost $post): void
    {
        $post->forceFill(['hidden_at' => $post->hidden_at ? null : now(), 'hidden_by' => $post->hidden_at ? null : $by->id])->save();
    }

    // ------------------------------------------------------------------ feedback

    public function saveFeedback(User $by, Course $course, Student $student, ?string $body): void
    {
        $body = $body !== null ? trim($body) : '';
        if ($body === '') {
            CourseFeedback::query()->where('course_id', $course->id)->where('student_id', $student->id)->delete();

            return;
        }
        CourseFeedback::query()->updateOrCreate(
            ['course_id' => $course->id, 'student_id' => $student->id],
            ['school_id' => $course->school_id, 'user_id' => $by->id, 'body' => mb_substr($body, 0, 3000)]
        );
    }
}
