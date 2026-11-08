<?php

namespace App\Services\Academics;

use App\Models\ClassSubject;
use App\Models\Period;
use App\Models\Room;
use App\Models\TeacherUnavailability;
use App\Models\TimetableSlot;
use Illuminate\Support\Collection;

/**
 * Conflict rules for timetable slots. A slot may not:
 *  - overlap a break period,
 *  - overlap another lesson of the same class,
 *  - overlap another lesson of the same teacher (even in another class),
 *  - overlap another lesson in the same room, or use an inactive room,
 *  - fall inside a window the teacher declared unavailable,
 *  - teach a subject that is not in the class curriculum (when that class has a curriculum defined),
 *  - use a teacher other than the one allocated to that class+subject (when one is allocated).
 * Results are stable `err.timetable.*` keys, translated by the front end.
 */
class TimetableValidator
{
    /**
     * @param  array{school_class_id:int,subject_id:?int,teacher_id:?int,room_id:?int,day_of_week:int,starts_at:string,ends_at:string}  $slot
     * @return array<int,array{code:string,with:?int}>
     */
    public function check(array $slot, ?int $ignoreId = null): array
    {
        $start = $this->time($slot['starts_at']);
        $end = $this->time($slot['ends_at']);
        $day = (int) $slot['day_of_week'];
        $found = [];

        $overlaps = fn ($q) => $q->where('day_of_week', $day)->where('starts_at', '<', $end)->where('ends_at', '>', $start)
            ->when($ignoreId, fn ($qq) => $qq->where('id', '!=', $ignoreId));

        if (Period::query()->where('is_break', true)->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists()) {
            $found[] = ['code' => 'err.timetable.break', 'with' => null];
        }

        $class = $overlaps(TimetableSlot::query()->where('school_class_id', $slot['school_class_id']))->first();
        if ($class) {
            $found[] = ['code' => 'err.timetable.classOverlap', 'with' => $class->id];
        }

        if (! empty($slot['teacher_id'])) {
            $teacher = $overlaps(TimetableSlot::query()->where('teacher_id', $slot['teacher_id']))->first();
            if ($teacher) {
                $found[] = ['code' => 'err.timetable.teacherOverlap', 'with' => $teacher->id];
            }

            if (TeacherUnavailability::query()->where('teacher_id', $slot['teacher_id'])
                ->where('day_of_week', $day)->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists()) {
                $found[] = ['code' => 'err.timetable.teacherUnavailable', 'with' => null];
            }
        }

        if (! empty($slot['room_id'])) {
            $room = $overlaps(TimetableSlot::query()->where('room_id', $slot['room_id']))->first();
            if ($room) {
                $found[] = ['code' => 'err.timetable.roomOverlap', 'with' => $room->id];
            }
            $roomModel = Room::query()->find($slot['room_id']);
            if ($roomModel && ! $roomModel->is_active) {
                $found[] = ['code' => 'err.timetable.roomInactive', 'with' => null];
            }
        }

        if (! empty($slot['subject_id'])) {
            $curriculum = ClassSubject::query()->where('school_class_id', $slot['school_class_id']);
            if ($curriculum->clone()->exists()) {
                $row = $curriculum->clone()->where('subject_id', $slot['subject_id'])->first();
                if (! $row) {
                    $found[] = ['code' => 'err.timetable.subjectNotInCurriculum', 'with' => null];
                } elseif ($row->teacher_id && ! empty($slot['teacher_id']) && (int) $row->teacher_id !== (int) $slot['teacher_id']) {
                    $found[] = ['code' => 'err.timetable.teacherNotAllocated', 'with' => null];
                }
            }
        }

        return $found;
    }

    /**
     * Whole-timetable check used before publication.
     *
     * @return Collection<int,array{code:string,slots:array<int,int>}>
     */
    public function validateAll(): Collection
    {
        $issues = collect();
        $slots = TimetableSlot::query()->orderBy('id')->get();
        $breaks = Period::query()->where('is_break', true)->get();
        $unavailable = TeacherUnavailability::query()->get()->groupBy('teacher_id');
        $inactiveRooms = Room::query()->where('is_active', false)->pluck('id')->all();
        $curriculum = ClassSubject::query()->get()->groupBy('school_class_id');

        $overlap = fn ($a, $b) => (string) $a->starts_at < (string) $b->ends_at && (string) $a->ends_at > (string) $b->starts_at;
        $add = function (string $code, array $ids) use ($issues) {
            sort($ids);
            $key = $code.':'.implode('-', $ids);
            if (! $issues->contains('key', $key)) {
                $issues->push(['key' => $key, 'code' => $code, 'slots' => array_values($ids)]);
            }
        };

        // pairwise (same day) conflicts
        foreach ($slots->groupBy('day_of_week') as $day) {
            $day = $day->values();
            for ($i = 0; $i < $day->count(); $i++) {
                for ($j = $i + 1; $j < $day->count(); $j++) {
                    [$a, $b] = [$day[$i], $day[$j]];
                    if (! $overlap($a, $b)) {
                        continue;
                    }
                    if ($a->school_class_id === $b->school_class_id) {
                        $add('err.timetable.classOverlap', [$a->id, $b->id]);
                    }
                    if ($a->teacher_id && $a->teacher_id === $b->teacher_id) {
                        $add('err.timetable.teacherOverlap', [$a->id, $b->id]);
                    }
                    if ($a->room_id && $a->room_id === $b->room_id) {
                        $add('err.timetable.roomOverlap', [$a->id, $b->id]);
                    }
                }
            }
        }

        // single-slot rules
        foreach ($slots as $slot) {
            if ($breaks->contains(fn ($p) => (string) $p->starts_at < (string) $slot->ends_at && (string) $p->ends_at > (string) $slot->starts_at)) {
                $add('err.timetable.break', [$slot->id]);
            }
            if ($slot->teacher_id && ($unavailable[$slot->teacher_id] ?? collect())->contains(
                fn ($u) => (int) $u->day_of_week === (int) $slot->day_of_week && (string) $u->starts_at < (string) $slot->ends_at && (string) $u->ends_at > (string) $slot->starts_at
            )) {
                $add('err.timetable.teacherUnavailable', [$slot->id]);
            }
            if ($slot->room_id && in_array($slot->room_id, $inactiveRooms, true)) {
                $add('err.timetable.roomInactive', [$slot->id]);
            }
            $classRows = $curriculum[$slot->school_class_id] ?? collect();
            if ($slot->subject_id && $classRows->isNotEmpty()) {
                $row = $classRows->firstWhere('subject_id', $slot->subject_id);
                if (! $row) {
                    $add('err.timetable.subjectNotInCurriculum', [$slot->id]);
                } elseif ($row->teacher_id && $slot->teacher_id && (int) $row->teacher_id !== (int) $slot->teacher_id) {
                    $add('err.timetable.teacherNotAllocated', [$slot->id]);
                }
            }
        }

        return $issues->map(fn ($i) => collect($i)->except('key')->all())->values();
    }

    protected function time(string $t): string
    {
        return strlen($t) === 5 ? $t.':00' : $t;
    }
}
