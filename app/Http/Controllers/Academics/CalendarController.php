<?php

namespace App\Http\Controllers\Academics;

use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\AcademicEvent;
use App\Models\AcademicYear;
use App\Models\Term;
use App\Services\Academics\AcademicCalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Academic years, terms, and calendar events (holidays, examination periods, admission periods, results dates). */
class CalendarController extends Controller
{
    use HandlesAcademicRules;
    use SharesSchoolShell;

    public function index(): Response
    {
        return Inertia::render('Academics/Calendar', array_merge($this->shellProps(), [
            'years' => AcademicYear::query()->with(['terms' => fn ($q) => $q->orderBy('starts_on')])->orderByDesc('starts_on')->get()->map(fn ($y) => [
                'id' => $y->id, 'name' => $y->name, 'starts_on' => $y->starts_on?->toDateString(), 'ends_on' => $y->ends_on?->toDateString(),
                'is_current' => $y->is_current,
                'terms' => $y->terms->map(fn ($t) => [
                    'id' => $t->id, 'name' => $t->name, 'starts_on' => $t->starts_on?->toDateString(),
                    'ends_on' => $t->ends_on?->toDateString(), 'is_current' => $t->is_current,
                ]),
            ]),
            'events' => AcademicEvent::query()->orderBy('starts_on')->get()->map(fn ($e) => [
                'id' => $e->id, 'type' => $e->type, 'title' => $e->title, 'starts_on' => $e->starts_on?->toDateString(),
                'ends_on' => $e->ends_on?->toDateString(), 'notes' => $e->notes,
            ]),
            'eventTypes' => AcademicEvent::TYPES,
            'can' => ['manage' => auth()->user()->can('academics.manage')],
        ]));
    }

    public function storeYear(Request $request, AcademicCalendarService $calendar): RedirectResponse
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date'],
            'make_current' => ['nullable', 'boolean'],
            'terms' => ['required', 'array', 'min:1', 'max:8'],
            'terms.*.name' => ['required', 'string', 'max:50'],
            'terms.*.starts_on' => ['required', 'date'], 'terms.*.ends_on' => ['required', 'date'],
        ]);

        return $this->attempt(fn () => $calendar->createYear($v), 'ok.yearCreated');
    }

    public function setCurrentYear(int $year, AcademicCalendarService $calendar): RedirectResponse
    {
        $record = AcademicYear::query()->findOrFail($year);

        return $this->attempt(fn () => $calendar->setCurrentYear($record));
    }

    public function updateTerm(Request $request, int $term, AcademicCalendarService $calendar): RedirectResponse
    {
        $record = Term::query()->findOrFail($term);
        $v = $request->validate(['name' => ['required', 'string', 'max:50'], 'starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date']]);

        return $this->attempt(fn () => $calendar->updateTerm($record, $v));
    }

    public function setCurrentTerm(int $term, AcademicCalendarService $calendar): RedirectResponse
    {
        $record = Term::query()->findOrFail($term);

        return $this->attempt(fn () => $calendar->setCurrentTerm($record));
    }

    public function storeEvent(Request $request, AcademicCalendarService $calendar): RedirectResponse
    {
        $v = $request->validate([
            'type' => ['required', 'in:'.implode(',', AcademicEvent::TYPES)],
            'title' => ['required', 'string', 'max:150'],
            'starts_on' => ['required', 'date'], 'ends_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->attempt(fn () => $calendar->addEvent($v), 'ok.eventSaved');
    }

    public function destroyEvent(int $event): RedirectResponse
    {
        AcademicEvent::query()->findOrFail($event)->delete();

        return back()->with('success', 'ok.removed');
    }
}
