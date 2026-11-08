<?php

namespace App\Http\Controllers\Boarding;

use App\Exceptions\AcademicRuleException;
use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\BoardingAllocation;
use App\Models\BoardingAttendance;
use App\Models\BoardingBed;
use App\Models\BoardingHostel;
use App\Models\BoardingRoom;
use App\Models\BoardingWelfareRecord;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Term;
use App\Services\Boarding\BoardingService;
use App\Services\Ops\Ops;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BoardingController extends Controller
{
    use HandlesAcademicRules, SharesSchoolShell;

    public function index(): Response
    {
        $hostels = BoardingHostel::query()->with(['rooms.beds', 'houseMaster:id,first_name,last_name'])->latest()->get();

        return Inertia::render('Boarding/Index', array_merge($this->shellProps(), [
            'hostels' => $hostels->map(fn (BoardingHostel $h) => [
                'id' => $h->id, 'name' => $h->name, 'gender' => $h->gender, 'warden_name' => $h->warden_name, 'house_master' => $h->houseMaster?->full_name,
                'fee_per_term' => (int) ($h->fee_per_term ?? 0), 'status' => $h->status, 'rooms' => $h->rooms->count(),
                'beds' => $h->rooms->sum(fn ($r) => $r->beds->count()),
            ]),
            'rooms' => BoardingRoom::query()->with(['hostel:id,name,gender', 'beds'])->latest()->get()->map(fn (BoardingRoom $r) => [
                'id' => $r->id, 'name' => $r->name, 'hostel' => $r->hostel?->name, 'hostel_id' => $r->boarding_hostel_id, 'capacity' => (int) $r->capacity,
                'occupied' => (int) $r->occupied, 'status' => $r->status,
                'beds' => $r->beds->map(fn (BoardingBed $b) => ['id' => $b->id, 'label' => $b->label, 'in_service' => (bool) $b->in_service]),
            ]),
            'allocations' => BoardingAllocation::query()->with(['room:id,name', 'bed:id,label', 'student:id,first_name,last_name,gender'])->where('status', 'active')->latest()->limit(80)->get()
                ->map(fn (BoardingAllocation $a) => [
                    'id' => $a->id, 'room' => $a->room?->name, 'bed' => $a->bed?->label, 'student' => $a->student?->full_name, 'starts_on' => $a->starts_on?->toDateString(),
                ]),
            'students' => Student::query()->where('status', 'active')->orderBy('last_name')->limit(400)->get(['id', 'first_name', 'last_name', 'gender'])->map(fn ($s) => ['id' => $s->id, 'name' => $s->full_name, 'gender' => $s->gender]),
            'staff' => Staff::query()->where('status', 'active')->orderBy('last_name')->get(['id', 'first_name', 'last_name'])->map(fn ($s) => ['id' => $s->id, 'name' => $s->full_name]),
            'terms' => Term::query()->orderByDesc('starts_on')->limit(8)->get(['id', 'name']),
            'stats' => [
                'hostels' => BoardingHostel::query()->count(),
                'rooms' => BoardingRoom::query()->count(),
                'boarders' => BoardingAllocation::query()->where('status', 'active')->count(),
            ],
        ]));
    }

    public function storeHostel(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'gender' => ['nullable', 'in:male,female,mixed'],
            'warden_name' => ['nullable', 'string', 'max:100'],
            'house_master_staff_id' => ['nullable', $this->inSchool('staff')],
            'fee_per_term' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'description' => ['nullable', 'string', 'max:300'],
        ]);

        return $this->attempt(function () use ($v) {
            if (BoardingHostel::query()->where('name', $v['name'])->exists()) {
                throw AcademicRuleException::because('err.bd.hostelExists');
            }
            BoardingHostel::query()->create($v + ['status' => 'active']);
            Ops::audit('bd.hostel.created', 'Hostel added: '.$v['name'], []);
        }, 'ok.bd.hostelSaved');
    }

    public function storeRoom(Request $request, BoardingService $service): RedirectResponse
    {
        $v = $request->validate([
            'boarding_hostel_id' => ['required', $this->inSchool('boarding_hostels')],
            'name' => ['required', 'string', 'max:50'],
            'capacity' => ['required', 'integer', 'min:1', 'max:40'],
        ]);

        return $this->attempt(function () use ($v, $service) {
            $room = BoardingRoom::query()->create($v + ['occupied' => 0, 'status' => 'available']);
            $service->addBeds($room, (int) $v['capacity']);
            Ops::audit('bd.room.created', 'Room added: '.$v['name'], ['room_id' => $room->id, 'beds' => (int) $v['capacity']]);
        }, 'ok.bd.roomSaved');
    }

    public function storeAllocation(Request $request, BoardingService $service): RedirectResponse
    {
        $v = $request->validate([
            'boarding_room_id' => ['required', $this->inSchool('boarding_rooms')],
            'student_id' => ['required', $this->inSchool('students')],
            'starts_on' => ['required', 'date'],
            'boarding_bed_id' => ['nullable', $this->inSchool('boarding_beds')],
        ]);

        return $this->attempt(fn () => $service->allocate(
            BoardingRoom::query()->findOrFail($v['boarding_room_id']),
            Student::query()->findOrFail($v['student_id']),
            $v['starts_on'],
            isset($v['boarding_bed_id']) ? (int) $v['boarding_bed_id'] : null,
            $request->user(),
        ), 'ok.bd.allocated');
    }

    public function checkout(Request $request, BoardingAllocation $allocation, BoardingService $service): RedirectResponse
    {
        $v = $request->validate(['ends_on' => ['nullable', 'date'], 'end_reason' => ['nullable', 'string', 'max:200']]);

        return $this->attempt(fn () => $service->checkout($allocation, $v['ends_on'] ?? null, $v['end_reason'] ?? null), 'ok.bd.checkedOut');
    }

    public function attendance(Request $request): Response
    {
        $date = (string) $request->query('date', today()->toDateString());
        $session = (string) $request->query('session', 'evening');
        if (! in_array($session, ['morning', 'evening'], true)) {
            $session = 'evening';
        }
        $boarders = BoardingAllocation::query()->with('student:id,first_name,last_name')->where('status', 'active')->get();
        $marks = BoardingAttendance::query()->where('attended_on', $date)->where('session', $session)->get()->keyBy('student_id');

        return Inertia::render('Boarding/Attendance', array_merge($this->shellProps(), [
            'date' => $date, 'session' => $session,
            'rows' => $boarders->map(fn (BoardingAllocation $a) => [
                'student_id' => $a->student_id, 'student' => $a->student?->full_name, 'status' => optional($marks->get($a->student_id))->status, 'remarks' => optional($marks->get($a->student_id))->remarks,
            ]),
        ]));
    }

    public function saveAttendance(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'attended_on' => ['required', 'date', 'before_or_equal:today'],
            'session' => ['required', 'in:morning,evening'],
            'marks' => ['required', 'array', 'max:400'],
            'marks.*.student_id' => ['required', $this->inSchool('students')],
            'marks.*.status' => ['required', 'in:present,absent,leave,sick'],
            'marks.*.remarks' => ['nullable', 'string', 'max:200'],
        ]);

        return $this->attempt(function () use ($v, $request) {
            foreach ($v['marks'] as $m) {
                BoardingAttendance::query()->updateOrCreate(
                    ['student_id' => $m['student_id'], 'attended_on' => $v['attended_on'], 'session' => $v['session']],
                    ['status' => $m['status'], 'remarks' => $m['remarks'] ?? null, 'recorded_by' => $request->user()->id],
                );
            }
            Ops::audit('bd.attendance', 'Boarding attendance saved', ['date' => $v['attended_on'], 'session' => $v['session'], 'rows' => count($v['marks'])]);
        }, 'ok.bd.attendanceSaved');
    }

    public function welfare(Request $request): Response
    {
        $studentId = $request->integer('student') ?: null;
        $rows = BoardingWelfareRecord::query()->with(['student:id,first_name,last_name', 'recorder:id,name'])
            ->when($studentId, fn ($q) => $q->where('student_id', $studentId))
            ->latest('id')->limit(40)->get();

        return Inertia::render('Boarding/Welfare', array_merge($this->shellProps(), [
            'records' => $rows->map(fn (BoardingWelfareRecord $r) => [
                'id' => $r->id, 'student' => $r->student?->full_name, 'kind' => $r->kind, 'note' => $r->note, 'by' => $r->recorder?->name, 'at' => $r->created_at?->toDateString(),
            ]),
            'studentId' => $studentId,
            'students' => Student::query()->whereIn('id', BoardingAllocation::query()->where('status', 'active')->pluck('student_id'))->orderBy('last_name')->get(['id', 'first_name', 'last_name'])->map(fn ($s) => ['id' => $s->id, 'name' => $s->full_name]),
        ]));
    }

    public function storeWelfare(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'student_id' => ['required', $this->inSchool('students')],
            'kind' => ['required', 'in:health,pastoral,discipline,visitor,other'],
            'note' => ['required', 'string', 'max:4000'],
        ]);

        return $this->attempt(function () use ($v, $request) {
            abort_unless(BoardingAllocation::query()->where('student_id', $v['student_id'])->where('status', 'active')->exists(), 422);
            $r = BoardingWelfareRecord::query()->create($v + ['recorded_by' => $request->user()->id]);
            Ops::audit('bd.welfare', 'Boarding welfare note recorded', ['record_id' => $r->id, 'student_id' => $v['student_id'], 'kind' => $v['kind']]);
        }, 'ok.bd.welfareSaved');
    }

    public function bill(Request $request, BoardingService $service): RedirectResponse
    {
        $v = $request->validate(['term_id' => ['required', $this->inSchool('terms')]]);

        return $this->attempt(function () use ($v, $request, $service) {
            $n = $service->billTerm(Term::query()->findOrFail($v['term_id']), $request->user());

            return back()->with('success', 'ok.bd.billed')->with('count', $n);
        });
    }
}
