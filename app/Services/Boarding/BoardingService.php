<?php

namespace App\Services\Boarding;

use App\Exceptions\AcademicRuleException;
use App\Models\AcademicYear;
use App\Models\BoardingAllocation;
use App\Models\BoardingBed;
use App\Models\BoardingHostel;
use App\Models\BoardingRoom;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Services\Ops\Ops;
use App\Services\Ops\TermCharge;
use Illuminate\Support\Facades\DB;

class BoardingService
{
    /**
     * Place a learner in a bed. Gender must match the hostel (unless mixed). One active allocation per learner.
     * `$bedId` null picks the first free bed in the room (keeps the original allocate-to-room form working).
     */
    public function allocate(BoardingRoom $room, Student $student, string $startsOn, ?int $bedId, User $actor): BoardingAllocation
    {
        if ($student->status !== 'active') {
            throw AcademicRuleException::because('err.bd.studentInactive');
        }

        return DB::transaction(function () use ($room, $student, $startsOn, $bedId, $actor) {
            $room = BoardingRoom::query()->lockForUpdate()->findOrFail($room->id);
            $hostel = BoardingHostel::query()->findOrFail($room->boarding_hostel_id);
            $this->assertGender($hostel, $student);

            if (BoardingAllocation::query()->where('student_id', $student->id)->where('status', 'active')->exists()) {
                throw AcademicRuleException::because('err.bd.alreadyAllocated');
            }

            $bed = $this->pickBed($room, $bedId);
            $alloc = BoardingAllocation::query()->create([
                'boarding_room_id' => $room->id, 'boarding_bed_id' => $bed->id, 'student_id' => $student->id,
                'starts_on' => $startsOn, 'status' => 'active',
            ]);
            $this->refreshOccupied($room);
            Ops::audit('bd.allocated', 'Boarder allocated to '.$hostel->name.' / '.$room->name, ['allocation_id' => $alloc->id, 'student_id' => $student->id, 'bed_id' => $bed->id, 'by' => $actor->id]);

            return $alloc;
        });
    }

    public function checkout(BoardingAllocation $a, ?string $on, ?string $reason): BoardingAllocation
    {
        return DB::transaction(function () use ($a, $on, $reason) {
            $a = BoardingAllocation::query()->lockForUpdate()->findOrFail($a->id);
            if ($a->status !== 'active') {
                throw AcademicRuleException::because('err.bd.notActive');
            }
            $a->update(['status' => 'ended', 'ends_on' => $on ?: today()->toDateString(), 'end_reason' => $reason ? mb_substr(trim($reason), 0, 200) : null]);
            $this->refreshOccupied($a->room);
            Ops::audit('bd.checked_out', 'Boarder checked out', ['allocation_id' => $a->id, 'student_id' => $a->student_id]);

            return $a;
        });
    }

    public function addBeds(BoardingRoom $room, int $count): void
    {
        $existing = BoardingBed::query()->where('boarding_room_id', $room->id)->count();
        for ($i = 1; $i <= $count; $i++) {
            BoardingBed::query()->create(['boarding_room_id' => $room->id, 'label' => (string) ($existing + $i), 'in_service' => true]);
        }
        $room->update(['capacity' => $existing + $count]);
    }

    public function billTerm(Term $term, User $actor): int
    {
        $year = AcademicYear::query()->find($term->academic_year_id);
        $n = 0;
        BoardingAllocation::query()->with(['room.hostel', 'student'])->where('status', 'active')->orderBy('id')->each(function (BoardingAllocation $a) use ($term, $year, $actor, &$n) {
            $hostel = $a->room?->hostel;
            $fee = (int) ($hostel?->fee_per_term ?? 0);
            if ($fee < 1 || ! $a->student || $a->student->status !== 'active') {
                return;
            }
            $source = 'boarding:'.$hostel->id.':'.$term->id;
            TermCharge::ensure($a->student, $source, 'Boarding — '.$hostel->name.' ('.$term->name.')', $fee, 'Boarding', $year?->id, $term->id, $actor);
            $n++;
        });
        Ops::audit('bd.billed', 'Boarding term charges recorded', ['term_id' => $term->id, 'charges' => $n]);

        return $n;
    }

    private function assertGender(BoardingHostel $hostel, Student $student): void
    {
        $g = $hostel->gender;
        if (! $g || $g === 'mixed') {
            return;
        }
        if ($student->gender && $student->gender !== $g) {
            throw AcademicRuleException::because('err.bd.gender');
        }
    }

    private function pickBed(BoardingRoom $room, ?int $bedId): BoardingBed
    {
        $taken = BoardingAllocation::query()->where('status', 'active')->whereNotNull('boarding_bed_id')->pluck('boarding_bed_id');
        $q = BoardingBed::query()->where('boarding_room_id', $room->id)->where('in_service', true)->whereNotIn('id', $taken)->lockForUpdate();
        if ($bedId) {
            $bed = (clone $q)->where('id', $bedId)->first();
            if (! $bed) {
                throw AcademicRuleException::because('err.bd.bedTaken');
            }

            return $bed;
        }
        $bed = $q->orderBy('id')->first();
        if (! $bed) {
            throw AcademicRuleException::because('err.bd.full');
        }

        return $bed;
    }

    private function refreshOccupied(BoardingRoom $room): void
    {
        $n = BoardingAllocation::query()->where('boarding_room_id', $room->id)->where('status', 'active')->count();
        $cap = max(1, (int) $room->capacity);
        $room->update(['occupied' => $n, 'status' => $n >= $cap ? 'full' : 'available']);
    }
}
