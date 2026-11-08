<?php

namespace App\Services\Messaging;

use App\Exceptions\AcademicRuleException;
use App\Models\ActivityLog;
use App\Models\ClassSubject;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Guardian;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\MessageReport;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use App\Notifications\SchoolAnnouncement;
use App\Services\Academics\TeacherScope;
use App\Services\Communications\CommunicationSettings;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Secure school messaging. Who may talk to whom is decided here and nowhere else:
 *
 *  staff     -> staff                 always (both need messages.use)
 *  staff     -> guardian / learner    leadership (academics.manage) and office staff (messages.office) reach any guardian;
 *                                      a teacher reaches the guardians and learners of the classes they teach
 *  guardian  -> staff                 only the teachers of their own children's classes and office contacts (messages.office);
 *                                      never other guardians, never learners
 *  learner   -> staff                 any usable staff at the school, when the school allows student messaging
 *  learner   -> learner               classmates only (same class, both have a portal login)
 *  learner   never                    guardians, other classes, or anyone outside the school
 *  starting  a conversation           learners also need `students_can_start` (on by default); they may still reply
 *                                      in a thread they already belong to
 *  nobody    -> anybody outside the school, a suspended account, or an account whose role lacks messages.use
 *
 * Conversations are visible to their participants. A moderator (messages.moderate) can open a conversation only when a
 * message in it has been reported, and that access is written to the audit log. Class groups hide the other families'
 * names from non-staff participants.
 */
class MessagingService
{
    public function __construct(private readonly TeacherScope $scope, private readonly TenantContext $tenant) {}

    // ------------------------------------------------------------------ who is who

    public function kind(User $user): string
    {
        return match (true) {
            $user->isStaffPortal() => 'staff',
            $user->isGuardianPortal() => 'guardian',
            $user->isLearnerPortal() => 'student',
            default => 'other',
        };
    }

    public function usable(User $user): bool
    {
        return ! $user->isBlocked() && $user->school_id !== null && $user->can('messages.use');
    }

    /** @return array<int,int> class ids of the children linked to a guardian account */
    public function childClassIds(User $guardianUser): array
    {
        return Student::query()
            ->whereIn('id', $this->childIds($guardianUser))
            ->whereNotNull('school_class_id')->pluck('school_class_id')->map(fn ($i) => (int) $i)->unique()->values()->all();
    }

    /** @return array<int,int> */
    public function childIds(User $guardianUser): array
    {
        $guardian = Guardian::query()->where('user_id', $guardianUser->id)->first();

        return $guardian ? $guardian->students()->pluck('students.id')->map(fn ($i) => (int) $i)->all() : [];
    }

    public function canMessage(User $from, User $to): bool
    {
        if ($from->is($to) || (int) $from->school_id !== (int) $to->school_id || ! $this->usable($from) || ! $this->usable($to)) {
            return false;
        }

        $school = $from->school;
        $f = $this->kind($from);
        $t = $this->kind($to);

        if ($f === 'staff') {
            return match ($t) {
                'staff' => true,
                'guardian' => CommunicationSettings::parentMessaging($school) && $this->staffMayReachGuardian($from, $to),
                'student' => CommunicationSettings::studentMessaging($school) && $this->staffMayReachLearner($from, $to),
                default => false,
            };
        }

        if ($f === 'guardian') {
            return $t === 'staff'
                && CommunicationSettings::parentMessaging($school)
                && $this->staffReachableByClasses($to, $this->childClassIds($from), true);
        }

        if ($f === 'student') {
            if (! CommunicationSettings::studentMessaging($school)) {
                return false;
            }

            return match ($t) {
                'staff' => true,
                'student' => $this->areClassmates($from, $to),
                default => false,
            };
        }

        return false;
    }

    /** May this person open a new conversation with that person (stricter than answering an existing thread). */
    public function canStart(User $from, User $to): bool
    {
        if (! $this->canMessage($from, $to)) {
            return false;
        }

        if ($this->kind($from) === 'student' && ! (bool) config('schoolos.messaging.students_can_start', true)) {
            return false;
        }

        return true;
    }

    public function studentsCanStart(): bool
    {
        return (bool) config('schoolos.messaging.students_can_start', true);
    }

    private function areClassmates(User $a, User $b): bool
    {
        $classA = $a->studentProfile?->school_class_id;
        $classB = $b->studentProfile?->school_class_id;

        return $classA && $classB && (int) $classA === (int) $classB;
    }

    private function staffMayReachGuardian(User $staff, User $guardianUser): bool
    {
        if ($this->scope->unrestricted($staff) || $staff->can('messages.office')) {
            return true;
        }

        return array_intersect($this->childClassIds($guardianUser), $this->scope->classIds($staff)->all()) !== [];
    }

    private function staffMayReachLearner(User $staff, User $learnerUser): bool
    {
        $classId = $learnerUser->studentProfile?->school_class_id;

        return $classId !== null && ($this->scope->unrestricted($staff) || $this->scope->classIds($staff)->contains((int) $classId));
    }

    /** May a family member reach this staff member? Teachers of the family's classes, and office contacts for guardians. */
    private function staffReachableByClasses(User $staff, array $classIds, bool $allowOffice): bool
    {
        if ($allowOffice && $staff->can('messages.office')) {
            return true;
        }

        return array_intersect($classIds, $this->scope->classIds($staff)->all()) !== [];
    }

    // ------------------------------------------------------------------ who can I write to (New message screen)

    /**
     * @return array{colleagues:array<int,array<string,mixed>>,families:array<int,array<string,mixed>>,staff:array<int,array<string,mixed>>,classmates:array<int,array<string,mixed>>}
     */
    public function contacts(User $me): array
    {
        $empty = ['colleagues' => [], 'families' => [], 'staff' => [], 'classmates' => []];
        if (! $this->usable($me)) {
            return $empty;
        }

        $kind = $this->kind($me);

        if ($kind === 'staff') {
            return [
                'colleagues' => $this->staffUsers()->reject(fn (User $u) => $u->is($me))->map(fn (User $u) => $this->card($u))->values()->all(),
                'families' => $this->familiesFor($me),
                'staff' => [],
                'classmates' => [],
            ];
        }

        return [
            'colleagues' => [],
            'families' => [],
            'staff' => $this->staffFor($me),
            'classmates' => $kind === 'student' ? $this->classmatesFor($me) : [],
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function staffFor(User $family): array
    {
        $kind = $this->kind($family);

        if ($kind === 'student') {
            return $this->staffUsers()
                ->reject(fn (User $u) => $u->is($family))
                ->filter(fn (User $u) => $this->canStart($family, $u))
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->map(fn (User $u) => $this->card($u))
                ->values()->all();
        }

        $classIds = $this->childClassIds($family);

        $teacherIds = collect(ClassSubject::query()->whereIn('school_class_id', $classIds)->whereNotNull('teacher_id')->pluck('teacher_id'))
            ->merge(SchoolClass::query()->whereIn('id', $classIds)->whereNotNull('class_teacher_id')->pluck('class_teacher_id'))
            ->merge(TimetableSlot::query()->whereIn('school_class_id', $classIds)->whereNotNull('teacher_id')->pluck('teacher_id'))
            ->unique()->all();

        $office = User::permission('messages.office')->where('school_id', $family->school_id)->pluck('id')->all();

        return User::query()->where('school_id', $family->school_id)->whereIn('id', array_unique([...$teacherIds, ...$office]))
            ->orderBy('name')
            ->get()->filter(fn (User $u) => $this->canStart($family, $u))
            ->map(fn (User $u) => $this->card($u))->values()->all();
    }

    /** @return array<int,array<string,mixed>> */
    private function classmatesFor(User $learner): array
    {
        $classId = $learner->studentProfile?->school_class_id;
        if (! $classId || ! $this->studentsCanStart() || ! CommunicationSettings::studentMessaging($learner->school)) {
            return [];
        }

        return Student::query()
            ->with(['user', 'schoolClass:id,name'])
            ->where('school_class_id', $classId)
            ->where('status', 'active')
            ->whereNotNull('user_id')
            ->where('user_id', '!=', $learner->id)
            ->orderBy('first_name')
            ->limit(200)
            ->get()
            ->map(function (Student $s) use ($learner) {
                $u = $s->user;
                if (! $u || ! $this->canStart($learner, $u)) {
                    return null;
                }

                return $this->card($u, ['class' => $s->schoolClass?->name]);
            })
            ->filter()
            ->unique('id')
            ->values()->all();
    }

    /** @return array<int,array<string,mixed>> learners in the staff member's scope with the family accounts the staff member may write to */
    private function familiesFor(User $staff): array
    {
        $query = Student::query()->with(['schoolClass:id,name', 'guardians.user', 'user']);
        // leadership and office staff may write to any family; everyone else only to the classes they teach
        if (! ($this->scope->unrestricted($staff) || $staff->can('messages.office'))) {
            $query->whereIn('school_class_id', $this->scope->classIds($staff));
        }
        $students = $query->where('status', 'active')->orderBy('first_name')->limit(600)->get();

        // The learner list above is already limited to what this staff member may reach, so the per-person check only needs
        // the account-level rules (same school, active, may use messaging, school switch on).
        $school = $staff->school;
        $guardiansOn = CommunicationSettings::parentMessaging($school);
        $learnersOn = CommunicationSettings::studentMessaging($school);
        $ok = fn (?User $u) => $u !== null && ! $u->is($staff) && (int) $u->school_id === (int) $staff->school_id && $this->usable($u);

        return $students->map(function (Student $s) use ($guardiansOn, $learnersOn, $ok) {
            $guardians = $guardiansOn ? $s->guardians->map(fn (Guardian $g) => $g->user)->filter($ok)
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->values()->all() : [];
            $learner = $learnersOn && $ok($s->user) ? ['id' => $s->user->id, 'name' => $s->user->name] : null;

            return ['student_id' => $s->id, 'name' => $s->full_name, 'class' => $s->schoolClass?->name, 'guardians' => $guardians, 'learner' => $learner];
        })->filter(fn (array $r) => $r['guardians'] !== [] || $r['learner'] !== null)->values()->all();
    }

    private function staffUsers(): Collection
    {
        return User::query()->where('school_id', $this->tenant->id())->whereNull('suspended_at')->whereNull('access_revoked_at')
            ->get()->filter(fn (User $u) => $this->kind($u) === 'staff' && $u->can('messages.use'))->values();
    }

    /** @param  array<string,mixed>  $extra
     *  @return array<string,mixed> */
    private function card(User $u, array $extra = []): array
    {
        return ['id' => $u->id, 'name' => $u->name, 'role' => $u->getRoleNames()->first(), 'kind' => $this->kind($u)] + $extra;
    }

    // ------------------------------------------------------------------ starting and answering

    public function startDirect(User $from, User $to, ?int $studentId, ?string $subject, string $body, array $files = []): Conversation
    {
        if (! $this->canStart($from, $to)) {
            throw AcademicRuleException::because('err.msg.notAllowed');
        }

        $studentId = $this->aboutStudent($from, $to, $studentId);

        $conversation = $this->existingDirect($from, $to, $studentId) ?? DB::transaction(function () use ($from, $to, $studentId, $subject) {
            $c = Conversation::query()->create([
                'school_id' => $from->school_id, 'type' => 'direct', 'subject' => $subject ? mb_substr(trim($subject), 0, 190) : null,
                'student_id' => $studentId, 'created_by' => $from->id, 'replies_allowed' => true,
            ]);
            $this->addParticipant($c, $from, 'owner');
            $this->addParticipant($c, $to, 'member');

            return $c;
        });

        $this->post($from, $conversation, $body, $files);

        return $conversation;
    }

    /** Teacher / leader writes to a class: learners, guardians or both. */
    public function startGroup(User $from, SchoolClass $class, string $subject, string $audience, bool $repliesAllowed, string $body, array $files = []): Conversation
    {
        if ($this->kind($from) !== 'staff' || ! $this->usable($from)
            || ! $this->scope->canAccessClass($from, $class->id) || (int) $class->school_id !== (int) $from->school_id) {
            throw AcademicRuleException::because('err.msg.notAllowed');
        }

        if (! in_array($audience, ['guardians', 'students', 'both'], true)) {
            throw AcademicRuleException::because('err.msg.audience');
        }

        $students = Student::query()->with(['guardians.user', 'user'])->where('school_class_id', $class->id)->where('status', 'active')->get();
        $members = collect();
        if (in_array($audience, ['students', 'both'], true) && CommunicationSettings::studentMessaging($from->school)) {
            $members = $members->merge($students->map(fn (Student $s) => $s->user)->filter());
        }
        if (in_array($audience, ['guardians', 'both'], true) && CommunicationSettings::parentMessaging($from->school)) {
            $members = $members->merge($students->flatMap(fn (Student $s) => $s->guardians->map(fn (Guardian $g) => $g->user))->filter());
        }
        $members = $members->filter(fn (User $u) => $this->usable($u) && (int) $u->school_id === (int) $from->school_id)->unique('id')->values();

        if ($members->isEmpty()) {
            throw AcademicRuleException::because('err.msg.noMembers');
        }

        if ($members->count() > (int) config('schoolos.messaging.group_max_members', 400)) {
            throw AcademicRuleException::because('err.msg.groupTooBig');
        }

        $conversation = DB::transaction(function () use ($from, $class, $subject, $repliesAllowed, $members) {
            $c = Conversation::query()->create([
                'school_id' => $from->school_id, 'type' => 'class_group', 'subject' => mb_substr(trim($subject), 0, 190),
                'school_class_id' => $class->id, 'created_by' => $from->id, 'replies_allowed' => $repliesAllowed,
            ]);
            $this->addParticipant($c, $from, 'owner');
            foreach ($members as $m) {
                $this->addParticipant($c, $m, 'member');
            }

            return $c;
        });

        $this->post($from, $conversation, $body, $files);

        return $conversation;
    }

    public function canPost(User $user, Conversation $c): bool
    {
        if (! $this->usable($user) || (int) $c->school_id !== (int) $user->school_id || $c->isLocked()) {
            return false;
        }

        $mine = $this->participant($c, $user);
        if (! $mine) {
            return false;
        }

        if ($c->type === 'class_group') {
            return $this->kind($user) === 'staff' || $c->replies_allowed;
        }

        // direct: keep the thread open while either side is still allowed to message the other
        $others = $c->participants()->whereNull('left_at')->where('user_id', '!=', $user->id)->with('user')->get();

        return $others->isNotEmpty() && $others->every(function (ConversationParticipant $p) use ($user) {
            if (! $p->user || ! $this->usable($p->user)) {
                return false;
            }

            return $this->canMessage($user, $p->user) || $this->canMessage($p->user, $user);
        });
    }

    public function reply(User $user, Conversation $c, string $body, array $files = []): Message
    {
        if (! $this->canPost($user, $c)) {
            throw AcademicRuleException::because($c->isLocked() ? 'err.msg.locked' : 'err.msg.notAllowed');
        }

        return $this->post($user, $c, $body, $files);
    }

    private function post(User $author, Conversation $c, string $body, array $files): Message
    {
        $body = trim($body);
        if ($body === '' || mb_strlen($body) > (int) config('schoolos.messaging.body_max', 4000)) {
            throw AcademicRuleException::because('err.msg.body');
        }

        if (count($files) > (int) config('schoolos.messaging.max_attachments', 3)) {
            throw AcademicRuleException::because('err.msg.tooManyFiles');
        }

        $school = $author->school;

        $message = DB::transaction(function () use ($author, $c, $body, $files) {
            $m = Message::query()->create(['school_id' => $c->school_id, 'conversation_id' => $c->id, 'user_id' => $author->id, 'body' => $body]);
            foreach ($files as $file) {
                $this->storeAttachment($m, $file);
            }
            $c->forceFill(['last_message_at' => now()])->save();
            ConversationParticipant::query()->where('conversation_id', $c->id)->where('user_id', $author->id)->update(['last_read_at' => now(), 'left_at' => null]);

            return $m;
        });

        $this->notifyParticipants($author, $c, $message);
        $this->tenant->set($school);   // a synchronous queue wipes the tenant after each job; keep the request usable

        return $message;
    }

    private function notifyParticipants(User $author, Conversation $c, Message $message): void
    {
        $recipients = User::query()->whereIn('id', ConversationParticipant::query()
            ->where('conversation_id', $c->id)->where('user_id', '!=', $author->id)->whereNull('left_at')->where('muted', false)->pluck('user_id'))
            ->get()->filter(fn (User $u) => ! $u->isBlocked())
            // one unread alert per conversation is enough: do not flood a family with every reply
            ->reject(fn (User $u) => $u->unreadNotifications()->where('type', NewMessageNotification::class)->limit(50)->get()
                ->contains(fn ($n) => (int) ($n->data['conversation_id'] ?? 0) === (int) $c->id));

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new NewMessageNotification($c, $author->name, mb_substr($message->body, 0, 120)));
        }
    }

    private function storeAttachment(Message $m, UploadedFile $file): MessageAttachment
    {
        $name = preg_replace('/[\x00-\x1F\x7F\\\\\/]+/', '_', $file->getClientOriginalName()) ?: 'attachment';
        $path = Storage::disk(config('schoolos.messaging.disk', 'local'))
            ->putFile('messages/'.$m->school_id.'/'.$m->conversation_id, $file);

        return MessageAttachment::query()->create([
            'school_id' => $m->school_id, 'message_id' => $m->id, 'path' => $path,
            'original_name' => mb_substr($name, 0, 190), 'mime' => $file->getMimeType(), 'size' => $file->getSize() ?: 0,
        ]);
    }

    // ------------------------------------------------------------------ reading

    public function participant(Conversation $c, User $user): ?ConversationParticipant
    {
        return ConversationParticipant::query()->where('conversation_id', $c->id)->where('user_id', $user->id)->whereNull('left_at')->first();
    }

    /** Participant, or a moderator looking at a conversation that contains a reported message. */
    public function canView(User $user, Conversation $c): bool
    {
        if ((int) $c->school_id !== (int) $user->school_id || $user->isBlocked()) {
            return false;
        }

        if ($this->participant($c, $user)) {
            return true;
        }

        return $user->can('messages.moderate') && MessageReport::query()->where('conversation_id', $c->id)->exists();
    }

    public function auditModeratorView(User $user, Conversation $c): void
    {
        if (! $this->participant($c, $user)) {
            ActivityLog::query()->create([
                'school_id' => $user->school_id, 'user_id' => $user->id, 'event' => 'messages.moderator_view',
                'description' => 'conversation #'.$c->id, 'properties' => ['conversation_id' => $c->id],
            ]);
        }
    }

    public function markRead(User $user, Conversation $c): void
    {
        ConversationParticipant::query()->where('conversation_id', $c->id)->where('user_id', $user->id)->update(['last_read_at' => now()]);

        foreach ($user->unreadNotifications()->where('type', NewMessageNotification::class)->limit(100)->get() as $n) {
            if ((int) ($n->data['conversation_id'] ?? 0) === (int) $c->id) {
                $n->markAsRead();
            }
        }
    }

    public function setMuted(User $user, Conversation $c, bool $muted): void
    {
        ConversationParticipant::query()->where('conversation_id', $c->id)->where('user_id', $user->id)->update(['muted' => $muted]);
    }

    public function unreadCount(User $user): int
    {
        return $this->unreadQuery($user)->count();
    }

    /** @return EloquentCollection<int,ConversationParticipant> */
    public function inbox(User $user, int $limit = 100): EloquentCollection
    {
        return ConversationParticipant::query()->with(['conversation.student', 'conversation.schoolClass', 'conversation.participants.user'])
            ->where('user_id', $user->id)->whereNull('left_at')
            ->join('conversations', 'conversations.id', '=', 'conversation_participants.conversation_id')
            ->orderByDesc('conversations.last_message_at')->select('conversation_participants.*')->limit($limit)->get();
    }

    public function isUnread(ConversationParticipant $p): bool
    {
        return Message::query()->where('conversation_id', $p->conversation_id)->where('user_id', '!=', $p->user_id)
            ->when($p->last_read_at, fn ($q) => $q->where('created_at', '>', $p->last_read_at))->exists();
    }

    private function unreadQuery(User $user)
    {
        return ConversationParticipant::query()->where('user_id', $user->id)->whereNull('left_at')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))->from('messages')
                    ->whereColumn('messages.conversation_id', 'conversation_participants.conversation_id')
                    ->whereColumn('messages.user_id', '!=', 'conversation_participants.user_id')
                    ->where(fn ($w) => $w->whereNull('conversation_participants.last_read_at')
                        ->orWhereColumn('messages.created_at', '>', 'conversation_participants.last_read_at'));
            });
    }

    // ------------------------------------------------------------------ reporting and moderation

    public function report(User $reporter, Message $message, string $reason): MessageReport
    {
        $conversation = $message->conversation;

        if (! $conversation || ! $this->participant($conversation, $reporter) || (int) $message->user_id === (int) $reporter->id) {
            throw AcademicRuleException::because('err.msg.notAllowed');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw AcademicRuleException::because('err.msg.reasonRequired');
        }

        $report = MessageReport::query()->firstOrCreate(
            ['message_id' => $message->id, 'reporter_id' => $reporter->id, 'status' => 'open'],
            ['school_id' => $message->school_id, 'conversation_id' => $message->conversation_id, 'reason' => mb_substr($reason, 0, 500)],
        );

        if ($report->wasRecentlyCreated) {
            $school = $reporter->school;
            $moderators = User::permission('messages.moderate')->where('school_id', $reporter->school_id)->whereNull('suspended_at')->get();
            if ($moderators->isNotEmpty()) {
                Notification::send($moderators, new SchoolAnnouncement(
                    'A message was reported', 'A message in the school messaging system needs review.', route('messages.moderation', absolute: false), 'warning', 'account',
                ));
            }
            $this->tenant->set($school);
        }

        return $report;
    }

    /** @return EloquentCollection<int,MessageReport> */
    public function openReports(): EloquentCollection
    {
        return MessageReport::query()->with(['message.author', 'conversation', 'reporter'])->where('status', 'open')->latest()->get();
    }

    /** Hide a reported message from the participants (the text stays for moderators and in the audit trail). */
    public function hide(User $moderator, Message $message, string $reason): void
    {
        $this->assertModerator($moderator, $message->conversation_id);

        $message->forceFill(['hidden_at' => now(), 'hidden_by' => $moderator->id, 'hidden_reason' => mb_substr(trim($reason), 0, 250)])->save();
        MessageReport::query()->where('message_id', $message->id)->where('status', 'open')
            ->update(['status' => 'actioned', 'handled_by' => $moderator->id, 'handled_at' => now(), 'resolution' => 'hidden']);
        $this->audit($moderator, 'messages.hidden', $message->conversation_id, ['message_id' => $message->id]);
    }

    public function dismiss(User $moderator, MessageReport $report): void
    {
        $this->assertModerator($moderator, $report->conversation_id);

        $report->forceFill(['status' => 'dismissed', 'handled_by' => $moderator->id, 'handled_at' => now(), 'resolution' => 'dismissed'])->save();
        $this->audit($moderator, 'messages.report_dismissed', $report->conversation_id, ['report_id' => $report->id]);
    }

    /** Owner (staff who started it) or a moderator of a reported conversation can lock it; nobody can post while it is locked. */
    public function setLocked(User $user, Conversation $c, bool $locked): void
    {
        $owner = $this->participant($c, $user)?->role === 'owner' && $this->kind($user) === 'staff';

        if (! $owner) {
            $this->assertModerator($user, $c->id);
        }

        $c->forceFill($locked ? ['locked_at' => now(), 'locked_by' => $user->id] : ['locked_at' => null, 'locked_by' => null])->save();
        $this->audit($user, $locked ? 'messages.locked' : 'messages.unlocked', $c->id);
    }

    private function assertModerator(User $user, int $conversationId): void
    {
        if (! $user->can('messages.moderate') || ! MessageReport::query()->where('conversation_id', $conversationId)->exists()) {
            throw AcademicRuleException::because('err.msg.notAllowed');
        }
    }

    // ------------------------------------------------------------------ internals

    private function existingDirect(User $a, User $b, ?int $studentId): ?Conversation
    {
        return Conversation::query()->where('type', 'direct')->whereNull('locked_at')
            ->when($studentId, fn ($q) => $q->where('student_id', $studentId), fn ($q) => $q->whereNull('student_id'))
            ->whereHas('participants', fn ($q) => $q->where('user_id', $a->id)->whereNull('left_at'))
            ->whereHas('participants', fn ($q) => $q->where('user_id', $b->id)->whereNull('left_at'))
            ->has('participants', '=', 2)->latest('id')->first();
    }

    /** The child a conversation is about must really be linked to the family account involved. */
    private function aboutStudent(User $from, User $to, ?int $studentId): ?int
    {
        if (! $studentId) {
            return null;
        }

        $guardianUser = $this->kind($from) === 'guardian' ? $from : ($this->kind($to) === 'guardian' ? $to : null);
        $learnerUser = $this->kind($from) === 'student' ? $from : ($this->kind($to) === 'student' ? $to : null);

        if ($guardianUser) {
            return in_array($studentId, $this->childIds($guardianUser), true) ? $studentId : null;
        }

        if ($learnerUser) {
            return $learnerUser->studentProfile?->id === $studentId ? $studentId : null;
        }

        return null;
    }

    private function addParticipant(Conversation $c, User $user, string $role): void
    {
        ConversationParticipant::query()->create([
            'school_id' => $c->school_id, 'conversation_id' => $c->id, 'user_id' => $user->id, 'role' => $role,
            'last_read_at' => $role === 'owner' ? now() : null,
        ]);
    }

    /** @param  array<string,mixed>  $props */
    private function audit(User $user, string $event, int $conversationId, array $props = []): void
    {
        ActivityLog::query()->create([
            'school_id' => $user->school_id, 'user_id' => $user->id, 'event' => $event,
            'description' => 'conversation #'.$conversationId, 'properties' => ['conversation_id' => $conversationId] + $props,
        ]);
    }
}
