<?php

namespace App\Services\Events;

use App\Exceptions\AcademicRuleException;
use App\Models\EventRegistration;
use App\Models\EventVenue;
use App\Models\SchoolEvent;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use App\Services\Ops\Ops;
use App\Services\Ops\OpsSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EventService
{
    public function __construct(private readonly OpsSettings $settings) {}

    public const CATEGORIES = ['academic', 'sports', 'cultural', 'meeting', 'trip', 'other'];

    public const AUDIENCES = ['all', 'staff', 'students', 'parents', 'class'];

    public function save(User $actor, ?SchoolEvent $existing, array $data): SchoolEvent
    {
        $from = Carbon::parse($data['starts_at']);
        $to = Carbon::parse($data['ends_at']);
        if ($to->lte($from)) {
            throw AcademicRuleException::because('err.ev.dates');
        }
        if (($data['audience'] ?? '') === 'class' && empty($data['school_class_id'])) {
            throw AcademicRuleException::because('err.ev.classRequired');
        }
        if (($data['audience'] ?? '') !== 'class') {
            $data['school_class_id'] = null;
        }
        $this->assertVenueFree($data['event_venue_id'] ?? null, $from, $to, $existing?->id);

        if ($existing) {
            if ($existing->status === 'cancelled') {
                throw AcademicRuleException::because('err.ev.cancelled');
            }
            $existing->update($data);
            Ops::audit('ev.updated', 'Event updated: '.$existing->title, ['event_id' => $existing->id]);

            return $existing;
        }

        $event = SchoolEvent::query()->create($data + ['status' => 'draft', 'created_by' => $actor->id]);
        Ops::audit('ev.created', 'Event created: '.$event->title, ['event_id' => $event->id]);

        return $event;
    }

    public function publish(SchoolEvent $event, User $actor): SchoolEvent
    {
        return DB::transaction(function () use ($event, $actor) {
            $event = SchoolEvent::query()->lockForUpdate()->findOrFail($event->id);
            if ($event->status !== 'draft') {
                throw AcademicRuleException::because('err.ev.notDraft');
            }
            $this->assertVenueFree($event->event_venue_id, $event->starts_at, $event->ends_at, $event->id);
            $event->update(['status' => 'published']);
            $event->loadMissing('venue');
            Ops::audit('ev.published', 'Event published: '.$event->title, ['event_id' => $event->id]);
            if ($this->settings->get('events', 'announce_on_publish', false)) {
                Ops::notify(Ops::usersWith('events.view'), 'Event: '.$event->title, $event->starts_at->format('d M Y H:i').($event->venue?->name ? ' · '.$event->venue->name : ''), route('events.show', $event), 'info', 'events');
                $event->update(['announced_at' => now()]);
            }

            return $event;
        });
    }

    public function cancel(SchoolEvent $event, string $reason): SchoolEvent
    {
        $reason = mb_substr(trim($reason), 0, 300);
        if ($reason === '') {
            throw AcademicRuleException::because('err.ev.cancelReason');
        }

        return DB::transaction(function () use ($event, $reason) {
            $event = SchoolEvent::query()->lockForUpdate()->findOrFail($event->id);
            if (in_array($event->status, ['cancelled', 'completed'], true)) {
                throw AcademicRuleException::because('err.ev.final');
            }
            $event->update(['status' => 'cancelled', 'cancel_reason' => $reason]);
            EventRegistration::query()->where('school_event_id', $event->id)->where('status', 'registered')->update(['status' => 'cancelled']);
            Ops::audit('ev.cancelled', 'Event cancelled: '.$event->title, ['event_id' => $event->id]);

            return $event;
        });
    }

    public function complete(SchoolEvent $event): SchoolEvent
    {
        return DB::transaction(function () use ($event) {
            $event = SchoolEvent::query()->lockForUpdate()->findOrFail($event->id);
            if ($event->status !== 'published') {
                throw AcademicRuleException::because('err.ev.notPublished');
            }
            $event->update(['status' => 'completed']);
            Ops::audit('ev.completed', 'Event completed: '.$event->title, ['event_id' => $event->id]);

            return $event;
        });
    }

    public function register(SchoolEvent $event, ?int $studentId, ?int $staffId, User $actor): EventRegistration
    {
        if (! $studentId && ! $staffId) {
            throw AcademicRuleException::because('err.ev.who');
        }
        if ($event->status !== 'published') {
            throw AcademicRuleException::because('err.ev.notPublished');
        }
        if ($event->registration_required && $event->registration_closes_at && $event->registration_closes_at->isPast()) {
            throw AcademicRuleException::because('err.ev.closed');
        }

        return DB::transaction(function () use ($event, $studentId, $staffId, $actor) {
            $event = SchoolEvent::query()->lockForUpdate()->findOrFail($event->id);
            $existing = null;
            if ($studentId) {
                Student::query()->findOrFail($studentId);
                $existing = EventRegistration::query()->where('school_event_id', $event->id)->where('student_id', $studentId)->lockForUpdate()->first();
                if ($existing && $existing->status === 'registered') {
                    throw AcademicRuleException::because('err.ev.alreadyIn');
                }
            }
            if ($staffId) {
                Staff::query()->findOrFail($staffId);
                $existing = $existing ?: EventRegistration::query()->where('school_event_id', $event->id)->where('staff_id', $staffId)->lockForUpdate()->first();
                if ($existing && $existing->status === 'registered') {
                    throw AcademicRuleException::because('err.ev.alreadyIn');
                }
            }
            $event->loadMissing('venue');
            $cap = $event->capacity ?: $event->venue?->capacity;
            if ($cap) {
                $taken = EventRegistration::query()->where('school_event_id', $event->id)->where('status', 'registered')->lockForUpdate()->count();
                if ($taken >= (int) $cap) {
                    throw AcademicRuleException::because('err.ev.full');
                }
            }
            if ($existing) {
                $existing->update([
                    'student_id' => $studentId, 'staff_id' => $staffId, 'registered_by' => $actor->id,
                    'status' => 'registered', 'attendance' => null, 'checked_at' => null,
                ]);
                $reg = $existing;
            } else {
                $reg = EventRegistration::query()->create([
                    'school_event_id' => $event->id, 'student_id' => $studentId, 'staff_id' => $staffId, 'registered_by' => $actor->id, 'status' => 'registered',
                ]);
            }
            Ops::audit('ev.registered', 'Registration for '.$event->title, ['event_id' => $event->id, 'registration_id' => $reg->id, 'student_id' => $studentId, 'staff_id' => $staffId]);

            return $reg;
        });
    }

    public function drop(EventRegistration $reg): EventRegistration
    {
        if ($reg->status !== 'registered') {
            throw AcademicRuleException::because('err.ev.notRegistered');
        }
        $reg->update(['status' => 'cancelled']);
        Ops::audit('ev.unregistered', 'Registration cancelled', ['registration_id' => $reg->id, 'event_id' => $reg->school_event_id]);

        return $reg;
    }

    public function markAttendance(EventRegistration $reg, string $status): EventRegistration
    {
        if (! in_array($status, ['present', 'absent'], true)) {
            throw AcademicRuleException::because('err.ev.attendance');
        }
        if ($reg->status !== 'registered') {
            throw AcademicRuleException::because('err.ev.notRegistered');
        }
        $reg->update(['attendance' => $status, 'checked_at' => now()]);
        Ops::audit('ev.attendance', 'Attendance marked', ['registration_id' => $reg->id, 'status' => $status]);

        return $reg;
    }

    private function assertVenueFree(?int $venueId, Carbon $from, Carbon $to, ?int $except = null): void
    {
        if (! $venueId) {
            return;
        }
        EventVenue::query()->findOrFail($venueId);
        $clash = SchoolEvent::query()->where('event_venue_id', $venueId)->whereNotIn('status', ['cancelled'])
            ->when($except, fn ($q) => $q->where('id', '!=', $except))
            ->where('starts_at', '<', $to)->where('ends_at', '>', $from)->exists();
        if ($clash) {
            throw AcademicRuleException::because('err.ev.venueBusy');
        }
    }
}
