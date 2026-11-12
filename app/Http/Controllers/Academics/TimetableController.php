<?php

namespace App\Http\Controllers\Academics;

use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Period;
use App\Models\Room;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeacherUnavailability;
use App\Models\TimetableSlot;
use App\Exceptions\AcademicRuleException;
use App\Services\Academics\TimetableValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Timetable authoring and views.
 *  - managers (timetable.manage) author drafts, define periods/breaks and teacher availability, validate and publish,
 *  - teachers see their own published lessons, students their class, parents their children's classes.
 * New slots are DRAFTS; learners and teachers only ever see published ones. Publication is refused while any conflict exists.
 */
class TimetableController extends Controller
{
    use HandlesAcademicRules;
    use SharesSchoolShell;

    public function index(Request $request): Response
    {
        $user = auth()->user();
        abort_unless($user?->canAny(['timetable.manage', 'timetable.view']), 403);
        $manager = $user->can('timetable.manage');

        $query = TimetableSlot::query()->with(['subject', 'teacher', 'schoolClass', 'roomLocation']);
        $classOptions = collect();
        $classId = $request->integer('class_id') ?: null;

        if ($manager) {
            $classOptions = SchoolClass::query()->orderBy('name')->get(['id', 'name']);
            $query->when($classId, fn ($q) => $q->where('school_class_id', $classId))
                ->when($request->integer('teacher_id'), fn ($q, $t) => $q->where('teacher_id', $t));
        } else {
            $query->where('is_published', true);
            if ($user->isLearnerPortal()) {
                $classId = $user->studentProfile?->school_class_id;
                $query->where('school_class_id', $classId ?: 0);
            } elseif ($user->isGuardianPortal()) {
                $children = $user->guardianProfile?->students()->with('schoolClass')->get() ?? collect();
                $classOptions = $children->pluck('schoolClass')->filter()->unique('id')->values()->map(fn ($c) => ['id' => $c->id, 'name' => $c->name]);
                $classId = $classOptions->pluck('id')->contains($classId) ? $classId : $classOptions->first()['id'] ?? null;
                $query->where('school_class_id', $classId ?: 0);
            } else { // teacher: own lessons
                $query->where('teacher_id', $user->id);
            }
        }

        $slots = $query->orderBy('day_of_week')->orderBy('starts_at')->get()->map(fn (TimetableSlot $slot) => [
            'id' => $slot->id,
            'day_of_week' => $slot->day_of_week,
            'starts_at' => substr((string) $slot->starts_at, 0, 5),
            'ends_at' => substr((string) $slot->ends_at, 0, 5),
            'subject' => $slot->subject?->name,
            'teacher' => $slot->teacher?->name,
            'class' => $slot->schoolClass?->name,
            'room' => $slot->roomLocation?->name ?? $slot->room,
            'school_class_id' => $slot->school_class_id,
            'is_published' => (bool) $slot->is_published,
            'period_id' => $slot->period_id,
        ]);

        $props = [
            'slots' => $slots,
            'classes' => $classOptions,
            'periods' => Period::query()->orderBy('sort_order')->orderBy('starts_at')->get(['id', 'name', 'starts_at', 'ends_at', 'is_break'])
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'starts_at' => substr((string) $p->starts_at, 0, 5), 'ends_at' => substr((string) $p->ends_at, 0, 5), 'is_break' => $p->is_break]),
            'filters' => ['class_id' => $classId, 'teacher_id' => $request->integer('teacher_id') ?: null],
            'can' => ['manage' => $manager],
            'issues' => $manager ? session('timetable_issues', []) : [],
            'draftCount' => $manager ? TimetableSlot::query()->where('is_published', false)->count() : 0,
        ];

        if ($manager) {
            $props += [
                'subjects' => Subject::query()->orderBy('name')->get(['id', 'name']),
                'teachers' => $this->teachers(),
                'rooms' => Room::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'capacity']),
                'unavailability' => TeacherUnavailability::query()->with('teacher')->orderBy('teacher_id')->orderBy('day_of_week')->get()->map(fn ($u) => [
                    'id' => $u->id, 'teacher' => $u->teacher?->name, 'teacher_id' => $u->teacher_id, 'day_of_week' => $u->day_of_week,
                    'starts_at' => substr((string) $u->starts_at, 0, 5), 'ends_at' => substr((string) $u->ends_at, 0, 5), 'reason' => $u->reason,
                ]),
            ];
        }

        return Inertia::render('Academics/Timetable', array_merge($this->shellProps(), $props));
    }

    public function store(Request $request, TimetableValidator $validator): RedirectResponse
    {
        abort_unless(auth()->user()?->can('timetable.manage'), 403);

        $v = $request->validate([
            'school_class_id' => ['required', $this->inSchool('school_classes')],
            'subject_id' => ['required', $this->inSchool('subjects')],
            'teacher_id' => ['nullable', $this->inSchool('users')],
            'room_id' => ['nullable', $this->inSchool('rooms')],
            'period_id' => ['nullable', $this->inSchool('periods')],
            'day_of_week' => ['required', 'integer', 'between:1,7'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i', 'after:starts_at'],
        ]);

        return $this->attempt(function () use ($v, $validator) {
            if (! empty($v['period_id'])) {
                $period = Period::query()->findOrFail($v['period_id']);
                if ($period->is_break) {
                    throw AcademicRuleException::because('err.timetable.break');
                }
                $v['starts_at'] = substr((string) $period->starts_at, 0, 5);
                $v['ends_at'] = substr((string) $period->ends_at, 0, 5);
            }
            if (empty($v['starts_at']) || empty($v['ends_at'])) {
                throw AcademicRuleException::because('err.timetable.timesRequired');
            }

            $candidate = [...$v, 'starts_at' => $v['starts_at'].':00', 'ends_at' => $v['ends_at'].':00'];
            $conflicts = $validator->check($candidate);
            if ($conflicts !== []) {
                throw AcademicRuleException::because($conflicts[0]['code']);
            }

            $roomName = ! empty($v['room_id']) ? Room::query()->find($v['room_id'])?->name : null;
            TimetableSlot::query()->create([
                'school_class_id' => $v['school_class_id'], 'subject_id' => $v['subject_id'],
                'teacher_id' => $v['teacher_id'] ?? null, 'room_id' => $v['room_id'] ?? null, 'room' => $roomName,
                'period_id' => $v['period_id'] ?? null, 'day_of_week' => $v['day_of_week'],
                'starts_at' => $candidate['starts_at'], 'ends_at' => $candidate['ends_at'],
                'is_published' => false,
            ]);
        }, 'ok.slotAdded');
    }

    public function destroy(int $slot): RedirectResponse
    {
        abort_unless(auth()->user()?->can('timetable.manage'), 403);
        TimetableSlot::query()->findOrFail($slot)->delete();

        return back()->with('success', 'ok.removed');
    }

    /** Check the whole timetable without publishing. */
    public function check(TimetableValidator $validator): RedirectResponse
    {
        abort_unless(auth()->user()?->can('timetable.manage'), 403);
        $issues = $validator->validateAll();

        return back()->with('timetable_issues', $issues->all())
            ->with($issues->isEmpty() ? 'success' : 'error', $issues->isEmpty() ? 'ok.timetableValid' : 'err.timetable.conflicts');
    }

    /** Publication is all-or-nothing and is refused while any conflict exists. */
    public function publish(TimetableValidator $validator): RedirectResponse
    {
        abort_unless(auth()->user()?->can('timetable.manage'), 403);

        $issues = $validator->validateAll();
        if ($issues->isNotEmpty()) {
            return back()->with('timetable_issues', $issues->all())->with('error', 'err.timetable.conflicts');
        }

        $count = TimetableSlot::query()->where('is_published', false)->update(['is_published' => true, 'published_at' => now()]);
        ActivityLog::query()->create([
            'school_id' => $this->schoolId(), 'user_id' => auth()->id(),
            'event' => 'timetable_published', 'description' => "{$count} slot(s) published",
        ]);

        return back()->with('success', 'ok.timetablePublished');
    }

    // ------------------------------------------------------------------ rooms, periods, availability

    public function storeRoom(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->can('timetable.manage'), 403);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:50'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
            'building' => ['nullable', 'string', 'max:100'],
        ]);

        if (Room::query()->whereRaw('lower(name) = ?', [mb_strtolower(trim($validated['name']))])->exists()) {
            return back()->with('error', 'err.name.taken');
        }

        Room::query()->create([...$validated, 'school_id' => $this->schoolId(), 'is_active' => true]);

        return back()->with('success', 'ok.saved');
    }

    public function storePeriod(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->can('timetable.manage'), 403);
        $v = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'is_break' => ['nullable', 'boolean'],
        ]);

        return $this->attempt(function () use ($v) {
            if (Period::query()->whereRaw('lower(name) = ?', [mb_strtolower(trim($v['name']))])->exists()) {
                throw AcademicRuleException::because('err.name.taken');
            }
            if (Period::query()->where('starts_at', '<', $v['ends_at'].':00')->where('ends_at', '>', $v['starts_at'].':00')->exists()) {
                throw AcademicRuleException::because('err.period.overlap');
            }
            Period::query()->create([
                'school_id' => $this->schoolId(), 'name' => trim($v['name']),
                'starts_at' => $v['starts_at'].':00', 'ends_at' => $v['ends_at'].':00',
                'is_break' => (bool) ($v['is_break'] ?? false), 'sort_order' => (int) Period::query()->max('sort_order') + 1,
            ]);
        });
    }

    public function destroyPeriod(int $period): RedirectResponse
    {
        abort_unless(auth()->user()?->can('timetable.manage'), 403);
        Period::query()->findOrFail($period)->delete(); // slots keep their own times (period_id is set to null)

        return back()->with('success', 'ok.removed');
    }

    public function storeUnavailability(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->can('timetable.manage'), 403);
        $v = $request->validate([
            'teacher_id' => ['required', $this->inSchool('users')],
            'day_of_week' => ['required', 'integer', 'between:1,7'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        TeacherUnavailability::query()->create([
            ...$v, 'school_id' => $this->schoolId(),
            'starts_at' => $v['starts_at'].':00', 'ends_at' => $v['ends_at'].':00',
        ]);

        return back()->with('success', 'ok.saved');
    }

    public function destroyUnavailability(int $unavailability): RedirectResponse
    {
        abort_unless(auth()->user()?->can('timetable.manage'), 403);
        TeacherUnavailability::query()->findOrFail($unavailability)->delete();

        return back()->with('success', 'ok.removed');
    }

    protected function teachers()
    {
        return $this->teachingStaff();
    }
}
