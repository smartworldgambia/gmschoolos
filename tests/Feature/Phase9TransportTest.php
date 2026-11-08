<?php

namespace Tests\Feature;

use App\Models\StudentFee;
use App\Models\TransportAssignment;
use App\Models\TransportRoute;
use App\Models\TransportStop;
use App\Models\TransportVehicle;
use App\Models\User;

/** Phase 9E: vehicles, routes, one ride per learner, capacity, term charges. */
class Phase9TransportTest extends Phase9TestCase
{
    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->officer = $this->user('transport_officer');
    }

    /** @return array{0:TransportVehicle,1:TransportRoute} */
    private function routeWithSeats(int $capacity = 2, int $fee = 400): array
    {
        $this->actAs($this->officer)->post(route('transport.vehicles.store'), ['name' => 'Van 1', 'capacity' => $capacity])->assertSessionHas('success', 'ok.tr.vehicleSaved');
        $vehicle = TransportVehicle::query()->withoutGlobalScopes()->firstOrFail();
        $this->actAs($this->officer)->post(route('transport.routes.store'), [
            'name' => 'Serrekunda', 'transport_vehicle_id' => $vehicle->id, 'fee_per_term' => $fee,
        ])->assertSessionHas('success', 'ok.tr.routeSaved');

        return [$vehicle, TransportRoute::query()->withoutGlobalScopes()->firstOrFail()];
    }

    public function test_only_transport_staff_can_manage_routes(): void
    {
        $this->actAs($this->teacher)->get(route('transport.index'))->assertForbidden();
        $this->actAs($this->officer)->get(route('transport.index'))->assertOk()->assertInertia(fn ($p) => $p->component('Transport/Index'));
    }

    public function test_a_learner_has_one_active_ride_and_the_vehicle_cannot_be_overfilled(): void
    {
        [, $route] = $this->routeWithSeats(1);
        $a = $this->student();
        $b = $this->student();

        $this->actAs($this->officer)->post(route('transport.assignments.store'), [
            'transport_route_id' => $route->id, 'student_id' => $a->id, 'stop_name' => 'Westfield',
        ])->assertSessionHas('success', 'ok.tr.assigned');
        $this->actAs($this->officer)->post(route('transport.assignments.store'), [
            'transport_route_id' => $route->id, 'student_id' => $b->id,
        ])->assertSessionHas('error', 'err.tr.full');

        $this->actAs($this->officer)->post(route('transport.vehicles.store'), ['name' => 'Van 2', 'capacity' => 12]);
        $other = TransportVehicle::query()->withoutGlobalScopes()->where('name', 'Van 2')->firstOrFail();
        $this->actAs($this->officer)->post(route('transport.routes.store'), ['name' => 'Brikama', 'transport_vehicle_id' => $other->id]);
        $second = TransportRoute::query()->withoutGlobalScopes()->where('name', 'Brikama')->firstOrFail();
        $this->actAs($this->officer)->post(route('transport.assignments.store'), [
            'transport_route_id' => $second->id, 'student_id' => $a->id,
        ])->assertSessionHas('error', 'err.tr.alreadyAssigned');

        $assignment = TransportAssignment::query()->withoutGlobalScopes()->firstOrFail();
        $this->actAs($this->officer)->post(route('transport.assignments.end', $assignment->id))->assertSessionHas('success', 'ok.tr.ended');
        $this->actAs($this->officer)->post(route('transport.assignments.store'), [
            'transport_route_id' => $second->id, 'student_id' => $a->id,
        ])->assertSessionHas('success', 'ok.tr.assigned');
    }

    public function test_stops_must_belong_to_the_route_and_foreign_learners_are_refused(): void
    {
        [, $route] = $this->routeWithSeats();
        $this->actAs($this->officer)->post(route('transport.stops.store'), ['transport_route_id' => $route->id, 'name' => 'Latrikunda']);
        $this->actAs($this->officer)->post(route('transport.vehicles.store'), ['name' => 'Spare', 'capacity' => 8]);
        $spare = TransportVehicle::query()->withoutGlobalScopes()->where('name', 'Spare')->firstOrFail();
        $this->actAs($this->officer)->post(route('transport.routes.store'), ['name' => 'Other', 'transport_vehicle_id' => $spare->id]);
        $otherRoute = TransportRoute::query()->withoutGlobalScopes()->where('name', 'Other')->firstOrFail();
        $this->actAs($this->officer)->post(route('transport.stops.store'), ['transport_route_id' => $otherRoute->id, 'name' => 'Foreign stop']);
        $foreignStop = TransportStop::query()->withoutGlobalScopes()->where('name', 'Foreign stop')->firstOrFail();

        $this->actAs($this->officer)->post(route('transport.assignments.store'), [
            'transport_route_id' => $route->id, 'student_id' => $this->student()->id, 'transport_stop_id' => $foreignStop->id,
        ])->assertSessionHas('error', 'err.tr.stopForeign');

        $other = $this->otherSchool();
        $foreignStudent = $this->student(null, false, 'Far', $other);
        $this->actAs($this->officer)->post(route('transport.assignments.store'), [
            'transport_route_id' => $route->id, 'student_id' => $foreignStudent->id,
        ])->assertSessionHasErrors('student_id');
    }

    public function test_term_charges_are_idempotent_and_attendance_can_be_saved(): void
    {
        [, $route] = $this->routeWithSeats(8, 400);
        $student = $this->student();
        $this->actAs($this->officer)->post(route('transport.assignments.store'), [
            'transport_route_id' => $route->id, 'student_id' => $student->id,
        ]);

        $this->actAs($this->officer)->post(route('transport.bill'), ['term_id' => $this->term1->id])->assertSessionHas('success', 'ok.tr.billed');
        $this->actAs($this->officer)->post(route('transport.bill'), ['term_id' => $this->term1->id])->assertSessionHas('success');
        $fees = StudentFee::query()->withoutGlobalScopes()->where('student_id', $student->id)->where('source', 'transport:'.$route->id.':'.$this->term1->id)->get();
        $this->assertCount(1, $fees);
        $this->assertSame(400, (int) $fees->first()->amount);
        $this->assertSame('pending', $fees->first()->status);

        $this->actAs($this->officer)->post(route('transport.attendance.save'), [
            'transport_route_id' => $route->id,
            'travel_date' => now()->toDateString(),
            'marks' => [
                ['student_id' => $student->id, 'direction' => 'pickup', 'status' => 'boarded'],
                ['student_id' => $student->id, 'direction' => 'drop', 'status' => 'absent'],
            ],
        ])->assertSessionHas('success', 'ok.tr.attendanceSaved');
    }
}
