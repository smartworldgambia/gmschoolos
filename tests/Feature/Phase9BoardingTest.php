<?php

namespace Tests\Feature;

use App\Models\BoardingAllocation;
use App\Models\BoardingBed;
use App\Models\BoardingHostel;
use App\Models\BoardingRoom;
use App\Models\StudentFee;

/** Phase 9E: hostels, beds, gender, one allocation, term charges, welfare. */
class Phase9BoardingTest extends Phase9TestCase
{
    /** @return array{0:BoardingHostel,1:BoardingRoom} */
    private function hostelRoom(string $gender = 'female', int $beds = 2, int $fee = 900): array
    {
        $this->actAs($this->admin)->post(route('boarding.hostels.store'), [
            'name' => 'North Wing', 'gender' => $gender, 'fee_per_term' => $fee,
        ])->assertSessionHas('success', 'ok.bd.hostelSaved');
        $hostel = BoardingHostel::query()->withoutGlobalScopes()->firstOrFail();
        $this->actAs($this->admin)->post(route('boarding.rooms.store'), [
            'boarding_hostel_id' => $hostel->id, 'name' => 'N1', 'capacity' => $beds,
        ])->assertSessionHas('success', 'ok.bd.roomSaved');
        $room = BoardingRoom::query()->withoutGlobalScopes()->firstOrFail();

        return [$hostel, $room];
    }

    public function test_only_boarding_staff_can_manage_hostels(): void
    {
        $this->actAs($this->teacher)->get(route('boarding.index'))->assertForbidden();
        $this->actAs($this->admin)->get(route('boarding.index'))->assertOk()->assertInertia(fn ($p) => $p->component('Boarding/Index'));
    }

    public function test_rooms_create_beds_and_gender_must_match(): void
    {
        [, $room] = $this->hostelRoom('male', 2);
        $this->assertSame(2, BoardingBed::query()->withoutGlobalScopes()->where('boarding_room_id', $room->id)->count());
        $this->assertSame(2, (int) $room->fresh()->capacity);

        $girl = $this->student();
        $this->actAs($this->admin)->post(route('boarding.allocations.store'), [
            'boarding_room_id' => $room->id, 'student_id' => $girl->id, 'starts_on' => now()->toDateString(),
        ])->assertSessionHas('error', 'err.bd.gender');

        $this->inSchool(fn () => $girl->update(['gender' => 'male']));
        $this->actAs($this->admin)->post(route('boarding.allocations.store'), [
            'boarding_room_id' => $room->id, 'student_id' => $girl->id, 'starts_on' => now()->toDateString(),
        ])->assertSessionHas('success', 'ok.bd.allocated');
    }

    public function test_one_active_bed_per_learner_and_a_full_room_is_refused(): void
    {
        [, $room] = $this->hostelRoom('female', 1);
        $a = $this->student();
        $b = $this->student();

        $this->actAs($this->admin)->post(route('boarding.allocations.store'), [
            'boarding_room_id' => $room->id, 'student_id' => $a->id, 'starts_on' => now()->toDateString(),
        ])->assertSessionHas('success', 'ok.bd.allocated');
        $this->actAs($this->admin)->post(route('boarding.allocations.store'), [
            'boarding_room_id' => $room->id, 'student_id' => $b->id, 'starts_on' => now()->toDateString(),
        ])->assertSessionHas('error', 'err.bd.full');
        $this->actAs($this->admin)->post(route('boarding.allocations.store'), [
            'boarding_room_id' => $room->id, 'student_id' => $a->id, 'starts_on' => now()->toDateString(),
        ])->assertSessionHas('error', 'err.bd.alreadyAllocated');

        $alloc = BoardingAllocation::query()->withoutGlobalScopes()->firstOrFail();
        $this->actAs($this->admin)->post(route('boarding.allocations.checkout', $alloc->id))->assertSessionHas('success', 'ok.bd.checkedOut');
        $this->actAs($this->admin)->post(route('boarding.allocations.store'), [
            'boarding_room_id' => $room->id, 'student_id' => $b->id, 'starts_on' => now()->toDateString(),
        ])->assertSessionHas('success');
        $this->assertSame(1, (int) $room->fresh()->occupied);
    }

    public function test_term_charges_are_idempotent_and_welfare_is_only_for_boarders(): void
    {
        [$hostel, $room] = $this->hostelRoom();
        $boarder = $this->student();
        $day = $this->student();
        $this->actAs($this->admin)->post(route('boarding.allocations.store'), [
            'boarding_room_id' => $room->id, 'student_id' => $boarder->id, 'starts_on' => now()->toDateString(),
        ]);

        $this->actAs($this->admin)->post(route('boarding.bill'), ['term_id' => $this->term1->id])->assertSessionHas('success', 'ok.bd.billed');
        $this->actAs($this->admin)->post(route('boarding.bill'), ['term_id' => $this->term1->id]);
        $fees = StudentFee::query()->withoutGlobalScopes()->where('source', 'boarding:'.$hostel->id.':'.$this->term1->id)->get();
        $this->assertCount(1, $fees);
        $this->assertSame(900, (int) $fees->first()->amount);

        $this->actAs($this->admin)->post(route('boarding.welfare.store'), [
            'student_id' => $day->id, 'kind' => 'health', 'note' => 'Not a boarder',
        ])->assertStatus(422);
        $this->actAs($this->admin)->post(route('boarding.welfare.store'), [
            'student_id' => $boarder->id, 'kind' => 'health', 'note' => 'Clinic visit',
        ])->assertSessionHas('success', 'ok.bd.welfareSaved');

        $this->actAs($this->admin)->post(route('boarding.attendance.save'), [
            'attended_on' => now()->toDateString(),
            'session' => 'evening',
            'marks' => [['student_id' => $boarder->id, 'status' => 'present']],
        ])->assertSessionHas('success', 'ok.bd.attendanceSaved');
    }

    public function test_foreign_hostels_are_invisible(): void
    {
        $other = $this->otherSchool();
        $foreign = $this->inSchool(fn () => BoardingHostel::query()->create(['name' => 'Away', 'gender' => 'mixed', 'status' => 'active']), $other);
        $this->actAs($this->admin)->post(route('boarding.rooms.store'), [
            'boarding_hostel_id' => $foreign->id, 'name' => 'X', 'capacity' => 1,
        ])->assertSessionHasErrors('boarding_hostel_id');
    }
}
