<?php

namespace Tests\Feature;

use App\Models\EventRegistration;
use App\Models\EventVenue;
use App\Models\SchoolEvent;
use App\Notifications\SchoolAnnouncement;
use Illuminate\Support\Facades\Notification;

/** Phase 9F: calendar, venue clashes, publish, registration, attendance. */
class Phase9EventsTest extends Phase9TestCase
{
    private function eventPayload(array $over = []): array
    {
        $day = now()->addDays(3);

        return $over + [
            'title' => 'Sports day',
            'category' => 'sports',
            'starts_at' => $day->copy()->setTime(10, 0)->toDateTimeString(),
            'ends_at' => $day->copy()->setTime(12, 0)->toDateTimeString(),
            'audience' => 'all',
            'registration_required' => 0,
        ];
    }

    public function test_viewers_see_published_events_only_and_cannot_manage(): void
    {
        $this->actAs($this->admin)->post(route('events.store'), $this->eventPayload())->assertSessionHas('success', 'ok.ev.saved');
        $draft = SchoolEvent::query()->withoutGlobalScopes()->firstOrFail();

        $this->actAs($this->teacher)->get(route('events.index'))->assertOk()->assertInertia(fn ($p) => $p->component('Events/Index')->has('events', 0));
        $this->actAs($this->teacher)->get(route('events.show', $draft->id))->assertNotFound();
        $this->actAs($this->teacher)->post(route('events.store'), $this->eventPayload(['title' => 'No']))->assertForbidden();
        $this->actAs($this->teacher)->get(route('events.venues.index'))->assertForbidden();

        $this->actAs($this->admin)->post(route('events.publish', $draft->id))->assertSessionHas('success', 'ok.ev.published');
        $this->actAs($this->teacher)->get(route('events.show', $draft->id))->assertOk()->assertInertia(fn ($p) => $p->component('Events/Show')->where('canManage', false));
        $this->actAs($this->admin)->get(route('events.index'))->assertOk()->assertInertia(fn ($p) => $p->component('Events/Index')->where('canManage', true));
    }

    public function test_a_venue_cannot_host_two_overlapping_events(): void
    {
        $this->actAs($this->admin)->post(route('events.venues.store'), ['name' => 'Hall', 'capacity' => 20])->assertSessionHas('success', 'ok.ev.venueSaved');
        $venue = EventVenue::query()->withoutGlobalScopes()->firstOrFail();
        $this->actAs($this->admin)->post(route('events.venues.store'), ['name' => 'Hall'])->assertSessionHas('error', 'err.ev.venueExists');

        $this->actAs($this->admin)->post(route('events.store'), $this->eventPayload(['event_venue_id' => $venue->id, 'title' => 'First']));
        $this->actAs($this->admin)->post(route('events.store'), $this->eventPayload([
            'event_venue_id' => $venue->id, 'title' => 'Clash',
            'starts_at' => now()->addDays(3)->setTime(11, 0)->toDateTimeString(),
            'ends_at' => now()->addDays(3)->setTime(13, 0)->toDateTimeString(),
        ]))->assertSessionHas('error', 'err.ev.venueBusy');

        $this->actAs($this->admin)->post(route('events.store'), $this->eventPayload([
            'event_venue_id' => $venue->id, 'title' => 'Later',
            'starts_at' => now()->addDays(3)->setTime(12, 0)->toDateTimeString(),
            'ends_at' => now()->addDays(3)->setTime(14, 0)->toDateTimeString(),
        ]))->assertSessionHas('success', 'ok.ev.saved');
    }

    public function test_registration_respects_capacity_and_can_be_reused_after_drop(): void
    {
        $this->actAs($this->admin)->post(route('events.store'), $this->eventPayload(['capacity' => 1, 'title' => 'Limited']));
        $event = SchoolEvent::query()->withoutGlobalScopes()->firstOrFail();
        $this->actAs($this->admin)->post(route('events.publish', $event->id));
        $a = $this->student();
        $b = $this->student();

        $this->actAs($this->admin)->post(route('events.register', $event->id), ['student_id' => $a->id])->assertSessionHas('success', 'ok.ev.registered');
        $this->actAs($this->admin)->post(route('events.register', $event->id), ['student_id' => $a->id])->assertSessionHas('error', 'err.ev.alreadyIn');
        $this->actAs($this->admin)->post(route('events.register', $event->id), ['student_id' => $b->id])->assertSessionHas('error', 'err.ev.full');

        $reg = EventRegistration::query()->withoutGlobalScopes()->firstOrFail();
        $this->actAs($this->admin)->post(route('events.registrations.attendance', $reg->id), ['attendance' => 'present'])->assertSessionHas('success', 'ok.ev.attendance');
        $this->actAs($this->admin)->post(route('events.registrations.drop', $reg->id))->assertSessionHas('success', 'ok.ev.unregistered');
        $this->actAs($this->admin)->post(route('events.register', $event->id), ['student_id' => $a->id])->assertSessionHas('success', 'ok.ev.registered');
        $this->actAs($this->admin)->post(route('events.register', $event->id), ['student_id' => $b->id])->assertSessionHas('error', 'err.ev.full');
        $this->actAs($this->admin)->post(route('events.registrations.drop', $reg->id))->assertSessionHas('success');
        $this->actAs($this->admin)->post(route('events.register', $event->id), ['student_id' => $b->id])->assertSessionHas('success', 'ok.ev.registered');
    }

    public function test_publish_can_announce_and_cancel_needs_a_reason(): void
    {
        Notification::fake();
        $this->actAs($this->admin)->put(route('events.setup.save'), ['announce_on_publish' => true])->assertSessionHas('success', 'ok.ev.settingsSaved');
        $this->actAs($this->admin)->post(route('events.store'), $this->eventPayload(['title' => 'Assembly']));
        $event = SchoolEvent::query()->withoutGlobalScopes()->firstOrFail();
        $this->actAs($this->admin)->post(route('events.publish', $event->id))->assertSessionHas('success', 'ok.ev.published');
        Notification::assertSentTo($this->teacher, SchoolAnnouncement::class);

        $this->actAs($this->admin)->post(route('events.cancel', $event->id), [])->assertSessionHasErrors('reason');
        $this->actAs($this->admin)->post(route('events.cancel', $event->id), ['reason' => 'Rain'])->assertSessionHas('success', 'ok.ev.cancelled');
        $this->actAs($this->admin)->post(route('events.complete', $event->id))->assertSessionHas('error', 'err.ev.notPublished');
    }

    public function test_class_audience_requires_a_class_and_end_must_follow_start(): void
    {
        $this->actAs($this->admin)->post(route('events.store'), $this->eventPayload([
            'audience' => 'class',
        ]))->assertSessionHas('error', 'err.ev.classRequired');
        $this->actAs($this->admin)->post(route('events.store'), $this->eventPayload([
            'starts_at' => now()->addDay()->setTime(14, 0)->toDateTimeString(),
            'ends_at' => now()->addDay()->setTime(13, 0)->toDateTimeString(),
        ]))->assertSessionHas('error', 'err.ev.dates');
        $this->actAs($this->admin)->post(route('events.store'), $this->eventPayload([
            'audience' => 'class', 'school_class_id' => $this->class->id,
        ]))->assertSessionHas('success', 'ok.ev.saved');
    }
}
