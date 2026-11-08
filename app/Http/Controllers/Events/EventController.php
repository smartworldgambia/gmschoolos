<?php

namespace App\Http\Controllers\Events;

use App\Exceptions\AcademicRuleException;
use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\EventRegistration;
use App\Models\EventVenue;
use App\Models\SchoolClass;
use App\Models\SchoolEvent;
use App\Models\Staff;
use App\Models\Student;
use App\Services\Events\EventService;
use App\Services\Ops\Ops;
use App\Services\Ops\OpsSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    use HandlesAcademicRules, SharesSchoolShell;

    public function index(Request $request): Response
    {
        $from = (string) $request->query('from', now()->startOfMonth()->toDateString());
        $to = (string) $request->query('to', now()->addMonth()->toDateString());
        $canManage = $request->user()->can('events.manage');

        $events = SchoolEvent::query()->with('venue:id,name')
            ->when(! $canManage, fn ($q) => $q->where('status', 'published'))
            ->where('starts_at', '<=', $to.' 23:59:59')->where('ends_at', '>=', $from.' 00:00:00')
            ->orderBy('starts_at')->limit(80)->get()
            ->map(fn (SchoolEvent $e) => $this->card($e));

        return Inertia::render('Events/Index', array_merge($this->shellProps(), [
            'events' => $events, 'from' => $from, 'to' => $to,
            'venues' => EventVenue::query()->orderBy('name')->get(['id', 'name', 'capacity', 'location']),
            'classes' => SchoolClass::query()->orderBy('name')->get(['id', 'name']),
            'categories' => EventService::CATEGORIES,
            'audiences' => EventService::AUDIENCES,
            'canManage' => $canManage,
        ]));
    }

    public function store(Request $request, EventService $service): RedirectResponse
    {
        $v = $this->eventRules($request);

        return $this->attempt(function () use ($v, $request, $service) {
            $e = $service->save($request->user(), null, $v);

            return redirect()->route('events.show', $e)->with('success', 'ok.ev.saved');
        });
    }

    public function show(Request $request, SchoolEvent $event): Response
    {
        abort_unless($request->user()->can('events.manage') || $event->status === 'published', 404);
        $event->load(['venue', 'schoolClass:id,name', 'registrations.student:id,first_name,last_name', 'registrations.staff:id,first_name,last_name']);
        $canManage = $request->user()->can('events.manage');

        return Inertia::render('Events/Show', array_merge($this->shellProps(), [
            'event' => $this->card($event) + [
                'description' => $event->description, 'audience' => $event->audience, 'school_class_id' => $event->school_class_id, 'class' => $event->schoolClass?->name,
                'capacity' => $event->capacity, 'registration_required' => (bool) $event->registration_required, 'registration_closes_at' => $event->registration_closes_at?->toDateTimeString(),
                'cancel_reason' => $event->cancel_reason, 'event_venue_id' => $event->event_venue_id, 'venue_location' => $event->venue?->location,
                'registrations' => $canManage ? $event->registrations->map(fn (EventRegistration $r) => [
                    'id' => $r->id, 'who' => $r->student?->full_name ?? $r->staff?->full_name, 'kind' => $r->student_id ? 'student' : 'staff',
                    'status' => $r->status, 'attendance' => $r->attendance,
                ]) : [],
                'taken' => $event->registrations->where('status', 'registered')->count(),
            ],
            'venues' => EventVenue::query()->orderBy('name')->get(['id', 'name', 'capacity']),
            'classes' => SchoolClass::query()->orderBy('name')->get(['id', 'name']),
            'students' => $canManage ? Student::query()->where('status', 'active')->orderBy('last_name')->limit(400)->get(['id', 'first_name', 'last_name'])->map(fn ($s) => ['id' => $s->id, 'name' => $s->full_name]) : [],
            'staff' => $canManage ? Staff::query()->where('status', 'active')->orderBy('last_name')->get(['id', 'first_name', 'last_name'])->map(fn ($s) => ['id' => $s->id, 'name' => $s->full_name]) : [],
            'categories' => EventService::CATEGORIES,
            'audiences' => EventService::AUDIENCES,
            'canManage' => $canManage,
        ]));
    }

    public function update(Request $request, SchoolEvent $event, EventService $service): RedirectResponse
    {
        $v = $this->eventRules($request);

        return $this->attempt(fn () => $service->save($request->user(), $event, $v), 'ok.ev.saved');
    }

    public function publish(Request $request, SchoolEvent $event, EventService $service): RedirectResponse
    {
        return $this->attempt(fn () => $service->publish($event, $request->user()), 'ok.ev.published');
    }

    public function cancel(Request $request, SchoolEvent $event, EventService $service): RedirectResponse
    {
        $v = $request->validate(['reason' => ['required', 'string', 'max:300']]);

        return $this->attempt(fn () => $service->cancel($event, $v['reason']), 'ok.ev.cancelled');
    }

    public function complete(SchoolEvent $event, EventService $service): RedirectResponse
    {
        return $this->attempt(fn () => $service->complete($event), 'ok.ev.completed');
    }

    public function register(Request $request, SchoolEvent $event, EventService $service): RedirectResponse
    {
        $v = $request->validate([
            'student_id' => ['nullable', $this->inSchool('students')],
            'staff_id' => ['nullable', $this->inSchool('staff')],
        ]);

        return $this->attempt(fn () => $service->register($event, isset($v['student_id']) ? (int) $v['student_id'] : null, isset($v['staff_id']) ? (int) $v['staff_id'] : null, $request->user()), 'ok.ev.registered');
    }

    public function drop(EventRegistration $registration, EventService $service): RedirectResponse
    {
        return $this->attempt(fn () => $service->drop($registration), 'ok.ev.unregistered');
    }

    public function attendance(Request $request, EventRegistration $registration, EventService $service): RedirectResponse
    {
        $v = $request->validate(['attendance' => ['required', 'in:present,absent']]);

        return $this->attempt(fn () => $service->markAttendance($registration, $v['attendance']), 'ok.ev.attendance');
    }

    public function venues(): Response
    {
        return Inertia::render('Events/Venues', array_merge($this->shellProps(), [
            'venues' => EventVenue::query()->orderBy('name')->get(['id', 'name', 'capacity', 'location']),
        ]));
    }

    public function storeVenue(Request $request): RedirectResponse
    {
        $v = $request->validate(['name' => ['required', 'string', 'max:150'], 'capacity' => ['nullable', 'integer', 'min:1', 'max:10000'], 'location' => ['nullable', 'string', 'max:250']]);

        return $this->attempt(function () use ($v) {
            if (EventVenue::query()->where('name', $v['name'])->exists()) {
                throw AcademicRuleException::because('err.ev.venueExists');
            }
            EventVenue::query()->create($v);
            Ops::audit('ev.venue.created', 'Venue added: '.$v['name'], []);
        }, 'ok.ev.venueSaved');
    }

    public function reports(): Response
    {
        $published = SchoolEvent::query()->where('status', 'published')->count();
        $upcoming = SchoolEvent::query()->where('status', 'published')->where('starts_at', '>=', now())->count();

        return Inertia::render('Events/Reports', array_merge($this->shellProps(), [
            'stats' => ['published' => $published, 'upcoming' => $upcoming, 'completed' => SchoolEvent::query()->where('status', 'completed')->count()],
            'recent' => SchoolEvent::query()->withCount(['registrations as taken' => fn ($q) => $q->where('status', 'registered')])->latest('starts_at')->limit(20)->get()
                ->map(fn (SchoolEvent $e) => ['id' => $e->id, 'title' => $e->title, 'status' => $e->status, 'starts_at' => $e->starts_at?->toDateTimeString(), 'taken' => (int) $e->taken, 'capacity' => $e->capacity]),
        ]));
    }

    public function setup(OpsSettings $settings): Response
    {
        return Inertia::render('Events/Setup', array_merge($this->shellProps(), [
            'settings' => $settings->group('events'),
        ]));
    }

    public function saveSetup(Request $request, OpsSettings $settings): RedirectResponse
    {
        $v = $request->validate(['announce_on_publish' => ['required', 'boolean']]);

        return $this->attempt(function () use ($v, $settings) {
            $settings->save('events', ['announce_on_publish' => $v['announce_on_publish']]);
            Ops::audit('ev.settings.saved', 'Event rules saved', []);
        }, 'ok.ev.settingsSaved');
    }

    private function eventRules(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['required', Rule::in(EventService::CATEGORIES)],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date'],
            'event_venue_id' => ['nullable', $this->inSchool('event_venues')],
            'audience' => ['required', Rule::in(EventService::AUDIENCES)],
            'school_class_id' => ['nullable', $this->inSchool('school_classes')],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'registration_required' => ['required', 'boolean'],
            'registration_closes_at' => ['nullable', 'date'],
        ]);
    }

    private function card(SchoolEvent $e): array
    {
        return [
            'id' => $e->id, 'title' => $e->title, 'category' => $e->category, 'status' => $e->status,
            'starts_at' => $e->starts_at?->toDateTimeString(), 'ends_at' => $e->ends_at?->toDateTimeString(),
            'venue' => $e->venue?->name,
        ];
    }
}
