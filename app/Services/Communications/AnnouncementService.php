<?php

namespace App\Services\Communications;

use App\Exceptions\AcademicRuleException;
use App\Models\Announcement;
use App\Models\AnnouncementRecipient;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Notifications\SchoolAnnouncement;
use App\Services\Academics\TeacherScope;
use App\Support\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * School and class announcements.
 *
 *  announcements.manage  school-wide audiences (everyone, staff, teachers, learners, guardians) and any class
 *  announcements.class   a teacher: only their own classes (TeacherScope), only for learners and/or guardians of those classes
 *
 * In-app delivery is always on; e-mail and SMS are chosen per announcement and still obey each person's preferences.
 * The recipient list is frozen when the announcement is published, so the feed and the read receipts stay stable.
 */
class AnnouncementService
{
    public const AUDIENCES = ['school', 'staff', 'teachers', 'students', 'guardians', 'classes'];

    /** "Teachers" means the people who teach, not every administrator who happens to hold the teacher permission. */
    public const TEACHING_ROLES = ['teacher', 'class_teacher', 'head_of_department'];

    public function __construct(private readonly TeacherScope $scope, private readonly TenantContext $tenant) {}

    public function canAuthor(User $user): bool
    {
        return ! $user->isBlocked() && ($user->can('announcements.manage') || $user->can('announcements.class'));
    }

    /**
     * @param  array<string,mixed>  $data  title, body, audience, class_ids, include_students, include_guardians, channels, publish_at, expires_at
     * @param  string  $mode  draft | publish | schedule
     */
    public function save(User $actor, ?Announcement $existing, array $data, string $mode): Announcement
    {
        if (! $this->canAuthor($actor)) {
            throw AcademicRuleException::because('err.ann.notAllowed');
        }

        if ($existing) {
            $this->assertEditable($actor, $existing);
        }

        $audience = (string) ($data['audience'] ?? '');
        if (! in_array($audience, self::AUDIENCES, true)) {
            throw AcademicRuleException::because('err.ann.audience');
        }

        $classIds = collect($data['class_ids'] ?? [])->map(fn ($i) => (int) $i)->unique()->values();
        if ($audience !== 'classes') {
            $classIds = collect();
        }

        $this->assertAudienceAllowed($actor, $audience, $classIds);

        $includeStudents = (bool) ($data['include_students'] ?? true);
        $includeGuardians = (bool) ($data['include_guardians'] ?? true);
        if ($audience === 'classes' && ! $includeStudents && ! $includeGuardians) {
            throw AcademicRuleException::because('err.ann.noRecipients');
        }

        $channels = collect($data['channels'] ?? [])->intersect(['email', 'sms'])->values()->all();

        $publishAt = ! empty($data['publish_at']) ? Carbon::parse($data['publish_at']) : null;
        if ($mode === 'schedule' && (! $publishAt || $publishAt->isPast())) {
            throw AcademicRuleException::because('err.ann.scheduleFuture');
        }

        $expiresAt = ! empty($data['expires_at']) ? Carbon::parse($data['expires_at']) : null;
        if ($expiresAt && $expiresAt->lte($publishAt ?? now())) {
            throw AcademicRuleException::because('err.ann.expiry');
        }

        $attrs = [
            'title' => mb_substr(trim((string) $data['title']), 0, 190),
            'body' => trim((string) $data['body']),
            'audience' => $audience,
            'class_ids' => $classIds->all() ?: null,
            'include_students' => $includeStudents,
            'include_guardians' => $includeGuardians,
            'channels' => $channels,
            'publish_at' => $mode === 'schedule' ? $publishAt : null,
            'expires_at' => $expiresAt,
            'status' => $mode === 'schedule' ? 'scheduled' : 'draft',
        ];

        if ($attrs['title'] === '' || $attrs['body'] === '') {
            throw AcademicRuleException::because('err.ann.content');
        }

        $announcement = $existing
            ? tap($existing)->update($attrs)
            : Announcement::query()->create($attrs + ['school_id' => $actor->school_id, 'author_id' => $actor->id]);

        if ($mode === 'publish') {
            $this->publish($announcement);
        }

        return $announcement->refresh();
    }

    public function cancel(User $actor, Announcement $a): void
    {
        $this->assertEditable($actor, $a, allowPublished: true);

        if ($a->status === 'cancelled') {
            return;
        }

        $a->forceFill(['status' => 'cancelled'])->save();
    }

    private function assertEditable(User $actor, Announcement $a, bool $allowPublished = false): void
    {
        if ((int) $a->school_id !== (int) $actor->school_id || ! $this->canAuthor($actor)) {
            throw AcademicRuleException::because('err.ann.notAllowed');
        }

        if (! $actor->can('announcements.manage') && (int) $a->author_id !== (int) $actor->id) {
            throw AcademicRuleException::because('err.ann.notAllowed');
        }

        if (! $allowPublished && ! in_array($a->status, ['draft', 'scheduled'], true)) {
            throw AcademicRuleException::because('err.ann.locked');
        }
    }

    private function assertAudienceAllowed(User $actor, string $audience, Collection $classIds): void
    {
        if ($actor->can('announcements.manage')) {
            if ($audience === 'classes') {
                $this->assertClassesExist($actor, $classIds);
            }

            return;
        }

        // a teacher: classes only, and only their own
        if ($audience !== 'classes' || $classIds->isEmpty()) {
            throw AcademicRuleException::because('err.ann.classOnly');
        }

        $this->assertClassesExist($actor, $classIds);

        foreach ($classIds as $id) {
            if (! $this->scope->canAccessClass($actor, $id)) {
                throw AcademicRuleException::because('err.ann.classNotYours');
            }
        }
    }

    private function assertClassesExist(User $actor, Collection $classIds): void
    {
        if ($classIds->isEmpty()) {
            throw AcademicRuleException::because('err.ann.classRequired');
        }

        $found = DB::table('school_classes')->where('school_id', $actor->school_id)->whereIn('id', $classIds)->count();
        if ($found !== $classIds->count()) {
            throw AcademicRuleException::because('err.ann.classNotYours');
        }
    }

    // ------------------------------------------------------------------ publishing

    /** Idempotent: only a draft or a scheduled announcement can be published, and only once. */
    public function publish(Announcement $a): bool
    {
        $claimed = Announcement::query()->whereKey($a->id)->whereIn('status', ['draft', 'scheduled'])
            ->update(['status' => 'published', 'published_at' => now()]);

        if (! $claimed) {
            return false;
        }

        $a->refresh();
        $school = $a->school;
        $users = $this->audience($a);

        DB::transaction(function () use ($a, $users) {
            foreach ($users->pluck('id')->chunk(500) as $chunk) {
                AnnouncementRecipient::query()->insertOrIgnore($chunk->map(fn ($id) => [
                    'school_id' => $a->school_id, 'announcement_id' => $a->id, 'user_id' => $id, 'created_at' => now(), 'updated_at' => now(),
                ])->all());
            }
            $a->forceFill(['recipients_count' => $users->count()])->save();
        });

        $offered = array_merge(['in_app'], (array) $a->channels);
        $note = new SchoolAnnouncement($a->title, $a->body, route('announcements.index', absolute: false), 'info', 'announcement', $offered);

        foreach ($users->chunk(200) as $chunk) {
            Notification::send($chunk, $note);
        }

        $this->tenant->set($school);

        return true;
    }

    /** Scheduler entry point: publish everything whose time has come, each in its own school. */
    public function publishDue(): int
    {
        $due = $this->tenant->runAsPlatform(fn () => Announcement::query()->withoutGlobalScopes()
            ->where('status', 'scheduled')->where('publish_at', '<=', now())->orderBy('id')->limit(200)->get());

        $count = 0;
        foreach ($due as $a) {
            $school = School::query()->find($a->school_id);
            if ($school && $this->tenant->runForSchool($school, fn () => $this->publish(Announcement::query()->find($a->id)))) {
                $count++;
            }
        }

        return $count;
    }

    /** @return Collection<int,User> everyone the announcement is for (suspended accounts excluded) */
    public function audience(Announcement $a): Collection
    {
        $active = fn () => User::query()->where('school_id', $a->school_id)->whereNull('suspended_at')->whereNull('access_revoked_at');

        // School administrators hold every portal permission (the `*school` shorthand), so learners and guardians are
        // "portal.student / portal.parent and not staff" - the same rule User::isLearnerPortal() / isGuardianPortal() apply.
        $staffIds = fn () => $active()->permission(['portal.admin', 'portal.teacher'])->select('users.id');

        $users = match ($a->audience) {
            'school' => $active()->permission(['portal.admin', 'portal.teacher', 'portal.student', 'portal.parent'])->get(),
            'staff' => $active()->permission(['portal.admin', 'portal.teacher'])->get(),
            'teachers' => $active()->role(self::TEACHING_ROLES)->get(),
            'students' => $active()->permission('portal.student')->whereNotIn('users.id', $staffIds())->get(),
            'guardians' => $active()->permission('portal.parent')->whereNotIn('users.id', $staffIds())->get(),
            'classes' => $this->classFamilies($a, $active),
            default => collect(),
        };

        return $users->unique('id')->values();
    }

    private function classFamilies(Announcement $a, \Closure $active): Collection
    {
        $students = Student::query()->with('guardians')->whereIn('school_class_id', (array) $a->class_ids)->where('status', 'active')->get();

        $ids = collect();
        if ($a->include_students) {
            $ids = $ids->merge($students->pluck('user_id'));
        }
        if ($a->include_guardians) {
            $ids = $ids->merge($students->flatMap(fn (Student $s) => $s->guardians->pluck('user_id')));
        }

        $ids = $ids->filter()->unique()->values();

        return $ids->isEmpty() ? collect() : $active()->whereIn('id', $ids)->get();
    }

    // ------------------------------------------------------------------ reading

    /** @return Collection<int,Announcement> */
    public function feed(User $user, int $limit = 50): Collection
    {
        return Announcement::query()->with('author:id,name')
            ->join('announcement_recipients', 'announcement_recipients.announcement_id', '=', 'announcements.id')
            ->where('announcement_recipients.user_id', $user->id)
            ->where('announcements.status', 'published')
            ->where(fn ($q) => $q->whereNull('announcements.expires_at')->orWhere('announcements.expires_at', '>', now()))
            ->orderByDesc('announcements.published_at')
            ->select('announcements.*', 'announcement_recipients.read_at as my_read_at')
            ->limit($limit)->get();
    }

    public function unreadCount(User $user): int
    {
        return AnnouncementRecipient::query()->where('user_id', $user->id)->whereNull('read_at')
            ->whereHas('announcement', fn ($q) => $q->where('status', 'published')
                ->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', now())))->count();
    }

    public function markRead(User $user, Announcement $a): void
    {
        AnnouncementRecipient::query()->where('announcement_id', $a->id)->where('user_id', $user->id)->whereNull('read_at')->update(['read_at' => now()]);
    }

    /** What the author sees: reach and read count. */
    public function stats(Announcement $a): array
    {
        return [
            'recipients' => (int) $a->recipients_count,
            'read' => $a->recipients()->whereNotNull('read_at')->count(),
        ];
    }
}
