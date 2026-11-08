<?php

namespace App\Http\Controllers\Messaging;

use App\Exceptions\AcademicRuleException;
use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\MessageReport;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\Academics\TeacherScope;
use App\Services\Messaging\MessagingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Internal messaging. Every rule about who may talk to whom lives in MessagingService; this controller only validates
 * input, shapes what each viewer is allowed to see and never trusts an id from the browser without re-checking it.
 */
class MessageController extends Controller
{
    use HandlesAcademicRules, SharesSchoolShell;

    public function __construct(private readonly MessagingService $messages, private readonly TeacherScope $scope) {}

    public function index(Request $request): Response
    {
        $me = $request->user();
        $rows = $this->messages->inbox($me);

        $lastIds = Message::query()->whereIn('conversation_id', $rows->pluck('conversation_id'))
            ->selectRaw('max(id) as id')->groupBy('conversation_id')->pluck('id');
        $last = Message::query()->whereIn('id', $lastIds)->get()->keyBy('conversation_id');

        $conversations = $rows->map(function ($p) use ($me, $last) {
            $c = $p->conversation;
            $m = $last->get($c->id);

            return [
                'id' => $c->id,
                'type' => $c->type,
                'title' => $this->title($c, $me),
                'with' => $this->counterparts($c, $me),
                'class' => $c->schoolClass?->name,
                'student' => $c->student?->full_name,
                'snippet' => $m ? ($m->hidden_at ? null : mb_substr($m->body, 0, 140)) : null,
                'snippet_hidden' => (bool) $m?->hidden_at,
                'mine' => $m && (int) $m->user_id === (int) $me->id,
                'at' => $c->last_message_at?->toIso8601String(),
                'unread' => $this->messages->isUnread($p),
                'locked' => $c->isLocked(),
                'muted' => (bool) $p->muted,
            ];
        })->values();

        return Inertia::render('Messages/Index', array_merge($this->shellProps(), [
            'conversations' => $conversations,
            'canStart' => $this->canCompose($me),
            'canModerate' => $me->can('messages.moderate'),
            'openReports' => $me->can('messages.moderate') ? MessageReport::query()->where('status', 'open')->count() : 0,
        ]));
    }

    public function create(Request $request): Response
    {
        $me = $request->user();
        abort_unless($this->messages->usable($me), 403);

        $kind = $this->messages->kind($me);
        $classes = [];
        if ($kind === 'staff') {
            $query = SchoolClass::query()->orderBy('name');
            if (! $this->scope->unrestricted($me)) {
                $query->whereIn('id', $this->scope->classIds($me));
            }
            $classes = $query->get(['id', 'name'])->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->all();
        }

        return Inertia::render('Messages/Create', array_merge($this->shellProps(), [
            'kind' => $kind,
            'contacts' => $this->messages->contacts($me),
            'children' => $kind === 'guardian'
                ? \App\Models\Student::query()->whereIn('id', $this->messages->childIds($me))->orderBy('first_name')->get()->map(fn ($s) => ['id' => $s->id, 'name' => $s->full_name])->values()
                : [],
            'classes' => $classes,
            'limits' => [
                'max_files' => (int) config('schoolos.messaging.max_attachments'),
                'max_kb' => (int) config('schoolos.messaging.attachment_max_kb'),
                'body_max' => (int) config('schoolos.messaging.body_max'),
                'mimes' => (string) config('schoolos.messaging.allowed_mimes'),
            ],
            'prefill' => [
                'recipient_id' => $request->integer('to') ?: null,
                'student_id' => $request->integer('student') ?: null,
            ],
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'recipient_id' => ['required', 'integer'],
            'student_id' => ['nullable', 'integer', $this->inSchool('students')],
            'subject' => ['nullable', 'string', 'max:190'],
            'body' => ['required', 'string', 'max:'.config('schoolos.messaging.body_max')],
            'attachments' => ['nullable', 'array', 'max:'.config('schoolos.messaging.max_attachments')],
            'attachments.*' => $this->fileRule(),
        ]);

        $me = $request->user();

        return $this->attempt(function () use ($me, $data, $request) {
            $to = User::query()->where('school_id', $me->school_id)->find($data['recipient_id']);
            if (! $to) {
                throw AcademicRuleException::because('err.msg.notAllowed');
            }

            $c = $this->messages->startDirect($me, $to, $data['student_id'] ?? null, $data['subject'] ?? null, $data['body'], $request->file('attachments', []));

            return redirect()->route('messages.show', $c->id)->with('success', 'ok.msg.sent');
        });
    }

    public function storeGroup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'class_id' => ['required', 'integer', $this->inSchool('school_classes')],
            'subject' => ['required', 'string', 'max:190'],
            'audience' => ['required', 'in:guardians,students,both'],
            'replies_allowed' => ['required', 'boolean'],
            'body' => ['required', 'string', 'max:'.config('schoolos.messaging.body_max')],
            'attachments' => ['nullable', 'array', 'max:'.config('schoolos.messaging.max_attachments')],
            'attachments.*' => $this->fileRule(),
        ]);

        $me = $request->user();

        return $this->attempt(function () use ($me, $data, $request) {
            $c = $this->messages->startGroup($me, SchoolClass::query()->findOrFail($data['class_id']), $data['subject'], $data['audience'],
                (bool) $data['replies_allowed'], $data['body'], $request->file('attachments', []));

            return redirect()->route('messages.show', $c->id)->with('success', 'ok.msg.sent');
        });
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        $me = $request->user();
        abort_unless($this->messages->canView($me, $conversation), 404);

        $participant = $this->messages->participant($conversation, $me);
        $moderatorView = $participant === null;

        if ($moderatorView) {
            $this->messages->auditModeratorView($me, $conversation);
        } else {
            $this->messages->markRead($me, $conversation);
        }

        $reportedByMe = MessageReport::query()->where('conversation_id', $conversation->id)->where('reporter_id', $me->id)->pluck('message_id')->all();
        $openReports = MessageReport::query()->where('conversation_id', $conversation->id)->where('status', 'open')->pluck('message_id')->all();

        $messages = $conversation->messages()->with(['author:id,name', 'attachments'])->orderBy('id')->get()->map(function (Message $m) use ($me, $moderatorView, $reportedByMe, $openReports) {
            $hidden = $m->hidden_at !== null;
            $visible = ! $hidden || $moderatorView;

            return [
                'id' => $m->id,
                'mine' => (int) $m->user_id === (int) $me->id,
                'author' => $m->author?->name,
                'author_kind' => $m->author ? $this->messages->kind($m->author) : null,
                'body' => $visible ? $m->body : null,
                'hidden' => $hidden,
                'hidden_reason' => $moderatorView ? $m->hidden_reason : null,
                'at' => $m->created_at?->toIso8601String(),
                'attachments' => $visible ? $m->attachments->map(fn (MessageAttachment $a) => ['id' => $a->id, 'name' => $a->original_name, 'size' => (int) $a->size])->values() : [],
                'can_report' => ! $moderatorView && (int) $m->user_id !== (int) $me->id && ! $hidden && ! in_array($m->id, $reportedByMe, true),
                'reported' => in_array($m->id, $reportedByMe, true),
                'report_open' => $moderatorView && in_array($m->id, $openReports, true),
            ];
        })->values();

        return Inertia::render('Messages/Show', array_merge($this->shellProps(), [
            'conversation' => [
                'id' => $conversation->id,
                'type' => $conversation->type,
                'title' => $this->title($conversation, $me),
                'class' => $conversation->schoolClass?->name,
                'student' => $conversation->student?->full_name,
                'locked' => $conversation->isLocked(),
                'replies_allowed' => (bool) $conversation->replies_allowed,
                'with' => $this->counterparts($conversation, $me),
                'muted' => (bool) $participant?->muted,
            ],
            'messages' => $messages,
            'canReply' => ! $moderatorView && $this->messages->canPost($me, $conversation),
            'canLock' => $this->canLock($me, $conversation, $participant?->role),
            'moderatorView' => $moderatorView,
            'canModerate' => $me->can('messages.moderate'),
            'canStart' => ! $moderatorView && $this->canCompose($me),
            'limits' => [
                'max_files' => (int) config('schoolos.messaging.max_attachments'),
                'max_kb' => (int) config('schoolos.messaging.attachment_max_kb'),
                'body_max' => (int) config('schoolos.messaging.body_max'),
                'mimes' => (string) config('schoolos.messaging.allowed_mimes'),
            ],
        ]));
    }

    public function reply(Request $request, Conversation $conversation): RedirectResponse
    {
        // Outsiders learn nothing, not even that the conversation exists.
        abort_unless($this->messages->participant($conversation, $request->user()), 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.config('schoolos.messaging.body_max')],
            'attachments' => ['nullable', 'array', 'max:'.config('schoolos.messaging.max_attachments')],
            'attachments.*' => $this->fileRule(),
        ]);

        return $this->attempt(function () use ($request, $conversation, $data) {
            $this->messages->reply($request->user(), $conversation, $data['body'], $request->file('attachments', []));

            return redirect()->route('messages.show', $conversation->id);
        });
    }

    public function mute(Request $request, Conversation $conversation): RedirectResponse
    {
        $data = $request->validate(['muted' => ['required', 'boolean']]);
        abort_unless($this->messages->participant($conversation, $request->user()), 404);

        $this->messages->setMuted($request->user(), $conversation, (bool) $data['muted']);

        return back()->with('success', 'ok.saved');
    }

    public function lock(Request $request, Conversation $conversation): RedirectResponse
    {
        return $this->attempt(fn () => $this->messages->setLocked($request->user(), $conversation, true), 'ok.msg.locked');
    }

    public function unlock(Request $request, Conversation $conversation): RedirectResponse
    {
        return $this->attempt(fn () => $this->messages->setLocked($request->user(), $conversation, false), 'ok.msg.unlocked');
    }

    public function report(Request $request, Message $message): RedirectResponse
    {
        abort_unless($message->conversation && $this->messages->participant($message->conversation, $request->user()), 404);

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->attempt(fn () => $this->messages->report($request->user(), $message, $data['reason']), 'ok.msg.reported');
    }

    public function attachment(Request $request, MessageAttachment $attachment): StreamedResponse
    {
        $me = $request->user();
        $message = $attachment->message;
        $conversation = $message?->conversation;

        abort_unless($conversation && $this->messages->canView($me, $conversation), 404);
        // A hidden message's files are for moderators only.
        abort_if($message->hidden_at !== null && ! $me->can('messages.moderate'), 404);

        $disk = Storage::disk(config('schoolos.messaging.disk', 'local'));
        abort_unless($disk->exists($attachment->path), 404);

        return $disk->download($attachment->path, $attachment->original_name, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    // ------------------------------------------------------------------ moderation

    public function moderation(Request $request): Response
    {
        $reports = $this->messages->openReports()->map(fn (MessageReport $r) => [
            'id' => $r->id,
            'reason' => $r->reason,
            'reporter' => $r->reporter?->name,
            'author' => $r->message?->author?->name,
            'excerpt' => $r->message ? mb_substr($r->message->body, 0, 200) : null,
            'conversation_id' => $r->conversation_id,
            'message_id' => $r->message_id,
            'at' => $r->created_at?->toIso8601String(),
        ])->values();

        $handled = MessageReport::query()->with(['message.author:id,name', 'reporter:id,name'])->where('status', '!=', 'open')->latest('handled_at')->limit(25)->get()
            ->map(fn (MessageReport $r) => ['id' => $r->id, 'status' => $r->status, 'reason' => $r->reason, 'author' => $r->message?->author?->name, 'at' => $r->handled_at?->toIso8601String()]);

        return Inertia::render('Messages/Moderation', array_merge($this->shellProps(), ['reports' => $reports, 'handled' => $handled]));
    }

    public function hide(Request $request, Message $message): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:250']]);

        return $this->attempt(fn () => $this->messages->hide($request->user(), $message, $data['reason']), 'ok.msg.hidden');
    }

    public function dismiss(Request $request, MessageReport $report): RedirectResponse
    {
        return $this->attempt(fn () => $this->messages->dismiss($request->user(), $report), 'ok.msg.dismissed');
    }

    // ------------------------------------------------------------------ helpers

    private function fileRule(): array
    {
        return ['file', 'max:'.config('schoolos.messaging.attachment_max_kb'), 'mimes:'.config('schoolos.messaging.allowed_mimes')];
    }

    private function canCompose(User $me): bool
    {
        if (! $this->messages->usable($me)) {
            return false;
        }

        $c = $this->messages->contacts($me);

        return $c['colleagues'] !== [] || $c['families'] !== [] || $c['staff'] !== [] || ($c['classmates'] ?? []) !== [];
    }

    private function canLock(User $me, Conversation $c, ?string $role): bool
    {
        if ($role === 'owner' && $this->messages->kind($me) === 'staff') {
            return true;
        }

        return $me->can('messages.moderate') && MessageReport::query()->where('conversation_id', $c->id)->exists();
    }

    private function title(Conversation $c, User $me): string
    {
        if ($c->subject) {
            return $c->subject;
        }

        return $this->counterparts($c, $me)['names'] ?: '';
    }

    /**
     * Who is on the other side, as this viewer is allowed to know it. Families never see other families' names in a class
     * group: they see the staff and a head count.
     *
     * @return array{names:string,count:int}
     */
    private function counterparts(Conversation $c, User $me): array
    {
        $others = $c->participants->where('user_id', '!=', $me->id)->filter(fn ($p) => $p->left_at === null);
        $viewerStaff = $this->messages->kind($me) === 'staff';

        $users = $others->map(fn ($p) => $p->user)->filter();
        if ($c->type === 'class_group' && ! $viewerStaff) {
            $users = $users->filter(fn (User $u) => $this->messages->kind($u) === 'staff');
        }

        return ['names' => $users->take(3)->pluck('name')->implode(', '), 'count' => $others->count()];
    }
}
