<?php

namespace App\Http\Controllers\Transport;

use App\Exceptions\AcademicRuleException;
use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Term;
use App\Models\TransportAssignment;
use App\Models\TransportAttendance;
use App\Models\TransportDriver;
use App\Models\TransportRoute;
use App\Models\TransportStop;
use App\Models\TransportVehicle;
use App\Models\VehicleMaintenance;
use App\Services\Ops\Ops;
use App\Services\Transport\TransportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TransportController extends Controller
{
    use HandlesAcademicRules, SharesSchoolShell;

    public function index(): Response
    {
        return Inertia::render('Transport/Index', array_merge($this->shellProps(), [
            'vehicles' => TransportVehicle::query()->with('driver:id,name')->latest()->get()->map(fn (TransportVehicle $v) => [
                'id' => $v->id, 'name' => $v->name, 'plate_number' => $v->plate_number, 'capacity' => (int) $v->capacity, 'status' => $v->status,
                'driver' => $v->driver?->name ?? $v->driver_name, 'insurance_expires_on' => $v->insurance_expires_on?->toDateString(), 'inspection_due_on' => $v->inspection_due_on?->toDateString(),
            ]),
            'drivers' => TransportDriver::query()->orderBy('name')->get(['id', 'name', 'phone', 'licence_number', 'licence_expires_on', 'status']),
            'routes' => TransportRoute::query()->with(['vehicle:id,name,capacity', 'stops'])->withCount(['assignments as riders' => fn ($q) => $q->where('status', 'active')])->latest()->get()
                ->map(fn (TransportRoute $r) => [
                    'id' => $r->id, 'name' => $r->name, 'area' => $r->area, 'vehicle' => $r->vehicle?->name, 'capacity' => (int) ($r->vehicle?->capacity ?? 0),
                    'riders' => (int) $r->riders, 'fee_per_term' => (int) ($r->fee_per_term ?? 0), 'status' => $r->status,
                    'stops' => $r->stops->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'sequence' => (int) $s->sequence]),
                ]),
            'assignments' => TransportAssignment::query()->with(['route:id,name', 'student:id,first_name,last_name', 'stop:id,name'])->where('status', 'active')->latest()->limit(80)->get()
                ->map(fn (TransportAssignment $a) => [
                    'id' => $a->id, 'route' => $a->route?->name, 'student' => $a->student?->full_name, 'stop' => $a->stop?->name ?? $a->stop_name, 'starts_on' => $a->starts_on?->toDateString(),
                ]),
            'maintenance' => VehicleMaintenance::query()->with('vehicle:id,name')->latest('performed_on')->limit(20)->get()->map(fn (VehicleMaintenance $m) => [
                'id' => $m->id, 'vehicle' => $m->vehicle?->name, 'kind' => $m->kind, 'performed_on' => $m->performed_on?->toDateString(), 'cost' => (int) $m->cost, 'next_due_on' => $m->next_due_on?->toDateString(),
            ]),
            'students' => Student::query()->where('status', 'active')->orderBy('last_name')->limit(400)->get(['id', 'first_name', 'last_name'])->map(fn ($s) => ['id' => $s->id, 'name' => $s->full_name]),
            'staff' => Staff::query()->where('status', 'active')->orderBy('last_name')->get(['id', 'first_name', 'last_name'])->map(fn ($s) => ['id' => $s->id, 'name' => $s->full_name]),
            'terms' => Term::query()->with('academicYear:id,name')->orderByDesc('starts_on')->limit(8)->get(['id', 'name', 'academic_year_id']),
            'stats' => [
                'vehicles' => TransportVehicle::query()->count(),
                'routes' => TransportRoute::query()->count(),
                'riders' => TransportAssignment::query()->where('status', 'active')->count(),
            ],
        ]));
    }

    public function storeVehicle(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'plate_number' => ['nullable', 'string', 'max:40'],
            'capacity' => ['required', 'integer', 'min:1', 'max:200'],
            'driver_name' => ['nullable', 'string', 'max:100'],
            'driver_phone' => ['nullable', 'string', 'max:40'],
            'transport_driver_id' => ['nullable', $this->inSchool('transport_drivers')],
            'make_model' => ['nullable', 'string', 'max:120'],
            'insurance_expires_on' => ['nullable', 'date'],
            'inspection_due_on' => ['nullable', 'date'],
        ]);

        return $this->attempt(function () use ($v) {
            TransportVehicle::query()->create($v + ['status' => 'active']);
            Ops::audit('tr.vehicle.created', 'Vehicle added: '.$v['name'], []);
        }, 'ok.tr.vehicleSaved');
    }

    public function storeDriver(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'licence_number' => ['nullable', 'string', 'max:60'],
            'licence_expires_on' => ['nullable', 'date'],
            'staff_id' => ['nullable', $this->inSchool('staff')],
        ]);

        return $this->attempt(function () use ($v) {
            TransportDriver::query()->create($v + ['status' => 'active']);
            Ops::audit('tr.driver.created', 'Driver added: '.$v['name'], []);
        }, 'ok.tr.driverSaved');
    }

    public function storeRoute(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'area' => ['nullable', 'string', 'max:100'],
            'transport_vehicle_id' => ['nullable', $this->inSchool('transport_vehicles')],
            'fee_monthly' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'fee_per_term' => ['nullable', 'integer', 'min:0', 'max:10000000'],
        ]);

        return $this->attempt(function () use ($v) {
            TransportRoute::query()->create($v + ['status' => 'active']);
            Ops::audit('tr.route.created', 'Route created: '.$v['name'], []);
        }, 'ok.tr.routeSaved');
    }

    public function storeStop(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'transport_route_id' => ['required', $this->inSchool('transport_routes')],
            'name' => ['required', 'string', 'max:150'],
            'sequence' => ['nullable', 'integer', 'min:1', 'max:200'],
            'pickup_time' => ['nullable', 'date_format:H:i'],
            'drop_time' => ['nullable', 'date_format:H:i'],
        ]);

        return $this->attempt(function () use ($v) {
            $seq = $v['sequence'] ?? ((int) TransportStop::query()->where('transport_route_id', $v['transport_route_id'])->max('sequence') + 1);
            TransportStop::query()->create($v + ['sequence' => $seq]);
            Ops::audit('tr.stop.created', 'Stop added: '.$v['name'], ['route_id' => $v['transport_route_id']]);
        }, 'ok.tr.stopSaved');
    }

    public function storeAssignment(Request $request, TransportService $service): RedirectResponse
    {
        $v = $request->validate([
            'transport_route_id' => ['required', $this->inSchool('transport_routes')],
            'student_id' => ['required', $this->inSchool('students')],
            'stop_name' => ['nullable', 'string', 'max:100'],
            'transport_stop_id' => ['nullable', $this->inSchool('transport_stops')],
            'starts_on' => ['nullable', 'date'],
        ]);

        return $this->attempt(fn () => $service->assign(
            TransportRoute::query()->findOrFail($v['transport_route_id']),
            Student::query()->findOrFail($v['student_id']),
            isset($v['transport_stop_id']) ? (int) $v['transport_stop_id'] : null,
            $v['stop_name'] ?? null,
            $v['starts_on'] ?? null,
            $request->user(),
        ), 'ok.tr.assigned');
    }

    public function endAssignment(Request $request, TransportAssignment $assignment, TransportService $service): RedirectResponse
    {
        $v = $request->validate(['ends_on' => ['nullable', 'date']]);

        return $this->attempt(fn () => $service->end($assignment, $v['ends_on'] ?? null), 'ok.tr.ended');
    }

    public function storeMaintenance(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'transport_vehicle_id' => ['required', $this->inSchool('transport_vehicles')],
            'kind' => ['required', 'in:service,repair,inspection,insurance,other'],
            'performed_on' => ['required', 'date'],
            'cost' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'vendor' => ['nullable', 'string', 'max:150'],
            'next_due_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:300'],
        ]);

        return $this->attempt(function () use ($v, $request) {
            VehicleMaintenance::query()->create($v + ['cost' => (int) ($v['cost'] ?? 0), 'recorded_by' => $request->user()->id]);
            Ops::audit('tr.maintenance', 'Vehicle maintenance recorded', ['vehicle_id' => $v['transport_vehicle_id'], 'kind' => $v['kind']]);
        }, 'ok.tr.maintSaved');
    }

    public function attendance(Request $request): Response
    {
        $date = (string) $request->query('date', today()->toDateString());
        $routeId = $request->integer('route') ?: TransportRoute::query()->value('id');
        $riders = $routeId
            ? TransportAssignment::query()->with('student:id,first_name,last_name')->where('transport_route_id', $routeId)->where('status', 'active')->get()
            : collect();
        $marks = $routeId
            ? TransportAttendance::query()->where('transport_route_id', $routeId)->where('travel_date', $date)->get()->groupBy(fn ($r) => $r->student_id.'|'.$r->direction)
            : collect();

        return Inertia::render('Transport/Attendance', array_merge($this->shellProps(), [
            'date' => $date,
            'routeId' => $routeId,
            'routes' => TransportRoute::query()->orderBy('name')->get(['id', 'name']),
            'rows' => $riders->map(fn (TransportAssignment $a) => [
                'student_id' => $a->student_id, 'student' => $a->student?->full_name,
                'pickup' => optional($marks->get($a->student_id.'|pickup')?->first())->status,
                'drop' => optional($marks->get($a->student_id.'|drop')?->first())->status,
            ]),
        ]));
    }

    public function saveAttendance(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'transport_route_id' => ['required', $this->inSchool('transport_routes')],
            'travel_date' => ['required', 'date', 'before_or_equal:today'],
            'marks' => ['required', 'array', 'max:400'],
            'marks.*.student_id' => ['required', $this->inSchool('students')],
            'marks.*.direction' => ['required', 'in:pickup,drop'],
            'marks.*.status' => ['required', 'in:boarded,absent,not_travelling'],
        ]);

        return $this->attempt(function () use ($v, $request) {
            foreach ($v['marks'] as $m) {
                TransportAttendance::query()->updateOrCreate(
                    ['transport_route_id' => $v['transport_route_id'], 'student_id' => $m['student_id'], 'travel_date' => $v['travel_date'], 'direction' => $m['direction']],
                    ['status' => $m['status'], 'recorded_by' => $request->user()->id],
                );
            }
            Ops::audit('tr.attendance', 'Transport attendance saved', ['route_id' => $v['transport_route_id'], 'date' => $v['travel_date'], 'rows' => count($v['marks'])]);
        }, 'ok.tr.attendanceSaved');
    }

    public function bill(Request $request, TransportService $service): RedirectResponse
    {
        $v = $request->validate(['term_id' => ['required', $this->inSchool('terms')]]);

        return $this->attempt(function () use ($v, $request, $service) {
            $n = $service->billTerm(Term::query()->findOrFail($v['term_id']), $request->user());

            return back()->with('success', 'ok.tr.billed')->with('count', $n);
        });
    }
}
