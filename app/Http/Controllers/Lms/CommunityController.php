<?php

namespace App\Http\Controllers\Lms;

use App\Http\Controllers\Concerns\UsesLms;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseAnnouncement;
use App\Models\ForumPost;
use App\Models\ForumThread;
use App\Services\Lms\CommunityService;
use App\Services\Lms\LmsAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Course announcements, discussion forum and student questions. */
class CommunityController extends Controller
{
    use UsesLms;

    public function __construct(protected CommunityService $service) {}

    // ------------------------------------------------------------------ announcements

    public function announcements(Course $course): Response
    {
        $role = $this->role($course);
        $author = LmsAccess::authors($role);

        $rows = CourseAnnouncement::query()->with('author:id,name')->where('course_id', $course->id)
            ->when(! $author, fn ($q) => $q->where(fn ($w) => $w->whereNull('publish_at')->orWhere('publish_at', '<=', now())))
            ->orderByDesc('is_pinned')->orderByDesc('id')->limit(200)->get();

        return Inertia::render('Lms/Announcements', $this->shellProps() + $this->courseHeader($course->loadMissing('subject'), $role) + [
            'announcements' => $rows->map(fn (CourseAnnouncement $a) => [
                'id' => $a->id, 'title' => $a->title, 'body' => $a->body, 'is_pinned' => (bool) $a->is_pinned, 'author' => $a->author?->name,
                'publish_at' => $a->publish_at?->toDateTimeString(), 'scheduled' => $a->publish_at !== null && $a->publish_at->gt(now()),
                'created_at' => $a->created_at->toDateTimeString(), 'delete_url' => $author ? route('lms.announcements.destroy', $a) : null,
            ])->values(),
        ]);
    }

    public function storeAnnouncement(Request $request, Course $course): RedirectResponse
    {
        $this->author($course);
        $v = $request->validate([
            'title' => ['required', 'string', 'max:190'], 'body' => ['required', 'string', 'max:10000'],
            'publish_at' => ['nullable', 'date'], 'is_pinned' => ['boolean'],
        ]);

        return $this->attempt(function () use ($request, $course, $v) {
            $this->service->announce($request->user(), $course, $v);

            return back()->with('success', 'ok.lms.announced');
        });
    }

    public function destroyAnnouncement(CourseAnnouncement $announcement): RedirectResponse
    {
        $this->author($announcement->course);
        $announcement->delete();

        return back()->with('success', 'ok.lms.announcementRemoved');
    }

    // ------------------------------------------------------------------ forum

    public function forum(Course $course): Response
    {
        $role = $this->role($course);
        $threads = ForumThread::query()->with('author:id,name')->withCount(['posts as replies' => fn ($q) => $q->whereNull('hidden_at')])
            ->where('course_id', $course->id)->orderByDesc('is_pinned')->orderByDesc('last_post_at')->limit(200)->get();

        return Inertia::render('Lms/Forum', $this->shellProps() + $this->courseHeader($course->loadMissing('subject'), $role) + [
            'threads' => $threads->map(fn (ForumThread $t) => [
                'id' => $t->id, 'title' => $t->title, 'type' => $t->type, 'author' => $t->author?->name, 'replies' => $t->replies,
                'is_pinned' => (bool) $t->is_pinned, 'is_locked' => (bool) $t->is_locked, 'answered' => $t->answer_post_id !== null,
                'last_post_at' => $t->last_post_at?->toDateTimeString(), 'url' => route('lms.threads.show', $t),
            ])->values(),
        ]);
    }

    public function storeThread(Request $request, Course $course): RedirectResponse
    {
        $this->role($course);
        $v = $request->validate(['type' => ['required', 'in:discussion,question'], 'title' => ['required', 'string', 'max:190'], 'body' => ['required', 'string', 'max:10000']]);
        $thread = $this->service->startThread($request->user(), $course, $v);

        return redirect()->route('lms.threads.show', $thread)->with('success', 'ok.lms.threadStarted');
    }

    public function thread(ForumThread $thread): Response
    {
        $course = $thread->course;
        $role = $this->role($course);
        $author = LmsAccess::authors($role);
        $userId = auth()->id();

        $posts = ForumPost::query()->with('author:id,name')->where('forum_thread_id', $thread->id)->orderBy('id')->get();

        return Inertia::render('Lms/Thread', $this->shellProps() + $this->courseHeader($course->loadMissing('subject'), $role) + [
            'thread' => [
                'id' => $thread->id, 'title' => $thread->title, 'body' => $thread->body, 'type' => $thread->type, 'author' => $thread->author?->name,
                'is_locked' => (bool) $thread->is_locked, 'is_pinned' => (bool) $thread->is_pinned, 'answer_post_id' => $thread->answer_post_id,
                'created_at' => $thread->created_at->toDateTimeString(), 'can_mark' => $thread->type === 'question' && ($author || (int) $thread->user_id === (int) $userId),
                'forum_url' => route('lms.courses.forum', $course),
            ],
            'posts' => $posts->map(fn (ForumPost $p) => [
                'id' => $p->id, 'author' => $p->author?->name, 'created_at' => $p->created_at->toDateTimeString(),
                'hidden' => $p->hidden_at !== null,
                'body' => $p->hidden_at !== null && ! $author ? null : $p->body,
                'mine' => (int) $p->user_id === (int) $userId,
            ])->values(),
        ]);
    }

    public function reply(Request $request, ForumThread $thread): RedirectResponse
    {
        $this->role($thread->course);
        $v = $request->validate(['body' => ['required', 'string', 'max:10000']]);

        return $this->attempt(function () use ($request, $thread, $v) {
            $this->service->reply($request->user(), $thread, $v['body']);

            return back()->with('success', 'ok.lms.replied');
        });
    }

    public function markAnswer(ForumThread $thread, ForumPost $post): RedirectResponse
    {
        $role = $this->role($thread->course);
        abort_unless(LmsAccess::authors($role) || (int) $thread->user_id === (int) auth()->id(), 403);

        return $this->attempt(function () use ($thread, $post) {
            $this->service->markAnswer($thread, $post);

            return back()->with('success', 'ok.lms.answerMarked');
        });
    }

    public function hidePost(Request $request, ForumPost $post): RedirectResponse
    {
        $this->author($post->thread->course);
        $this->service->hidePost($request->user(), $post);

        return back()->with('success', 'ok.lms.postModerated');
    }

    public function lockThread(ForumThread $thread): RedirectResponse
    {
        $this->author($thread->course);
        $thread->forceFill(['is_locked' => ! $thread->is_locked])->save();

        return back()->with('success', 'ok.lms.threadUpdated');
    }

    public function pinThread(ForumThread $thread): RedirectResponse
    {
        $this->author($thread->course);
        $thread->forceFill(['is_pinned' => ! $thread->is_pinned])->save();

        return back()->with('success', 'ok.lms.threadUpdated');
    }
}
