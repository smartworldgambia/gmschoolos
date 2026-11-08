<?php

namespace App\Http\Controllers\Communications;

use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\SchoolClass;
use App\Services\Academics\TeacherScope;
use App\Services\Communications\AnnouncementService;
use App\Services\Communications\CommunicationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * School and class announcements. Everyone with a portal reads their own feed (only announcements they were addressed
 * by); authors (announcements.manage / announcements.class) also see and manage what they wrote. Reach and read counts
 * are the author's; nobody sees who else read what.
 */
class AnnouncementController extends Controller
{
    use HandlesAcademicRules, SharesSchoolShell;

    public function __construct(private readonly AnnouncementService $announcements, private readonly TeacherScope $scope) {}

    public function index(Request $request): Response
    {
        $me = $request->user();

        $feed = $this->announcements->feed($me)->map(fn (Announcement $a) => [
            'id' => $a->id, 'title' => $a->title, 'body' => $a->body, 'author' => $a->author?->name,
            'at' => $a->published_at?->toIso8601String(), 'unread' => $a->my_read_at === null,
        ])->values();

        // opening the feed is what "read" means; the unread flag above was taken before this
        $unread = $feed->where('unread', true)->pluck('id');
        if ($unread->isNotEmpty()) {
            \App\Models\AnnouncementRecipient::query()->where('user_id', $me->id)->whereIn('announcement_id', $unread)->whereNull('read_at')->update(['read_at' => now()]);
        }

        $mine = [];
        if ($this->announcements->canAuthor($me)) {
            $mine = Announcement::query()->with('author:id,name')
                ->when(! $me->can('announcements.manage'), fn ($q) => $q->where('author_id', $me->id))
                ->latest('id')->limit(50)->get()->map(fn (Announcement $a) => [
                    'id' => $a->id, 'title' => $a->title, 'audience' => $a->audience, 'status' => $a->status, 'author' => $a->author?->name,
                    'publish_at' => $a->publish_at?->toIso8601String(), 'published_at' => $a->published_at?->toIso8601String(),
                    'channels' => (array) $a->channels, 'stats' => $a->status === 'published' ? $this->announcements->stats($a) : null,
                    'editable' => in_array($a->status, ['draft', 'scheduled'], true),
                ])->values();
        }

        return Inertia::render('Announcements/Index', array_merge($this->shellProps(), [
            'feed' => $feed,
            'mine' => $mine,
            'canAuthor' => $this->announcements->canAuthor($me),
        ]));
    }

    public function create(Request $request): Response
    {
        abort_unless($this->announcements->canAuthor($request->user()), 403);

        return $this->form($request, null);
    }

    public function edit(Request $request, Announcement $announcement): Response
    {
        abort_unless($this->announcements->canAuthor($request->user()), 403);
        abort_unless($request->user()->can('announcements.manage') || (int) $announcement->author_id === (int) $request->user()->id, 404);
        abort_unless(in_array($announcement->status, ['draft', 'scheduled'], true), 404);

        return $this->form($request, $announcement);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, null);
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        return $this->save($request, $announcement);
    }

    public function cancel(Request $request, Announcement $announcement): RedirectResponse
    {
        return $this->attempt(fn () => $this->announcements->cancel($request->user(), $announcement), 'ok.ann.cancelled');
    }

    private function save(Request $request, ?Announcement $existing): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'body' => ['required', 'string', 'max:5000'],
            'audience' => ['required', 'in:'.implode(',', AnnouncementService::AUDIENCES)],
            'class_ids' => ['nullable', 'array', 'max:60'],
            'class_ids.*' => ['integer', $this->inSchool('school_classes')],
            'include_students' => ['nullable', 'boolean'],
            'include_guardians' => ['nullable', 'boolean'],
            'channels' => ['nullable', 'array'],
            'channels.*' => ['in:email,sms'],
            'publish_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'action' => ['required', 'in:draft,publish,schedule'],
        ]);

        return $this->attempt(function () use ($request, $existing, $data) {
            $a = $this->announcements->save($request->user(), $existing, $data, $data['action']);

            return redirect()->route('announcements.index')->with('success', match ($a->status) {
                'published' => 'ok.ann.published', 'scheduled' => 'ok.ann.scheduled', default => 'ok.saved',
            });
        });
    }

    private function form(Request $request, ?Announcement $a): Response
    {
        $me = $request->user();
        $classes = SchoolClass::query()->orderBy('name');
        if (! $me->can('announcements.manage')) {
            $classes->whereIn('id', $this->scope->classIds($me));
        }

        return Inertia::render('Announcements/Form', array_merge($this->shellProps(), [
            'announcement' => $a ? [
                'id' => $a->id, 'title' => $a->title, 'body' => $a->body, 'audience' => $a->audience, 'class_ids' => (array) $a->class_ids,
                'include_students' => $a->include_students, 'include_guardians' => $a->include_guardians, 'channels' => (array) $a->channels,
                'publish_at' => $a->publish_at?->format('Y-m-d\TH:i'), 'expires_at' => $a->expires_at?->format('Y-m-d\TH:i'), 'status' => $a->status,
            ] : null,
            'audiences' => $me->can('announcements.manage') ? AnnouncementService::AUDIENCES : ['classes'],
            'classes' => $classes->get(['id', 'name'])->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values(),
            'smsReady' => CommunicationSettings::smsEnabled($me->school),
        ]));
    }
}
