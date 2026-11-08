<?php

namespace App\Services\Students;

use App\Exceptions\AcademicRuleException;
use App\Models\ActivityLog;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Class promotion, repetition, transfer between classes, withdrawal, transfer out, graduation and reinstatement.
 * Nothing is ever deleted: every move is a StudentMovement row and withdrawn students keep all their records.
 */
class StudentMovementService
{
    public function __construct(protected StudentRegistrar $registrar) {}

    /**
     * Move the given active students into $to (promotion). Capacity is checked for the whole batch first so a
     * partial promotion never happens.
     *
     * @param  array<int,int>  $studentIds
     */
    public function promote(array $studentIds, SchoolClass $to, ?string $reason = null): int
    {
        return $this->moveMany($studentIds, $to, 'promoted', $reason);
    }

    /** @param  array<int,int>  $studentIds */
    public function repeat(array $studentIds, SchoolClass $same, ?string $reason = null): int
    {
        return $this->moveMany($studentIds, $same, 'repeated', $reason, enforceCapacity: false);
    }

    public function changeClass(Student $student, SchoolClass $to, ?string $reason = null): void
    {
        $this->moveMany([$student->id], $to, 'class_changed', $reason);
    }

    /** @param  array<int,int>  $studentIds */
    protected function moveMany(array $studentIds, SchoolClass $to, string $event, ?string $reason, bool $enforceCapacity = true): int
    {
        $students = Student::query()->whereIn('id', $studentIds)->where('status', 'active')->get();
        if ($students->isEmpty()) {
            throw AcademicRuleException::because('err.movement.noneEligible');
        }

        if ($enforceCapacity && $to->capacity !== null) {
            $incoming = $students->where('school_class_id', '!=', $to->id)->count();
            $current = Student::query()->where('school_class_id', $to->id)->where('status', 'active')->count();
            if ($current + $incoming > $to->capacity) {
                throw AcademicRuleException::because('err.class.full');
            }
        }

        return DB::transaction(function () use ($students, $to, $event, $reason) {
            foreach ($students as $student) {
                $from = $student->school_class_id;
                $student->update(['school_class_id' => $to->id]);
                $this->registrar->movement($student, $event, $from, $to->id, $reason);
            }
            $this->audit("student_{$event}", $students->count()." student(s) -> class {$to->id}");

            return $students->count();
        });
    }

    public function withdraw(Student $student, string $reason, ?string $on = null): void
    {
        $this->leave($student, 'withdrawn', $reason, $on);
    }

    public function transferOut(Student $student, string $reason, ?string $on = null): void
    {
        $this->leave($student, 'transferred_out', $reason, $on);
    }

    /** Final-year learners complete their studies; the class link is kept as the last class attended. */
    public function graduate(Student $student, ?string $on = null): void
    {
        $this->leave($student, 'graduated', null, $on);
    }

    protected function leave(Student $student, string $event, ?string $reason, ?string $on): void
    {
        if ($student->status !== 'active') {
            throw AcademicRuleException::because('err.movement.notActive');
        }

        DB::transaction(function () use ($student, $event, $reason, $on) {
            $student->update([
                'status' => $event,
                'withdrawn_on' => $on ?? now()->toDateString(),
                'withdrawal_reason' => $reason,
            ]);
            $this->registrar->movement($student, $event, $student->school_class_id, null, $reason, $on);
            $this->audit("student_{$event}", $student->student_number);
        });
    }

    public function reinstate(Student $student, ?SchoolClass $into = null, ?string $reason = null): void
    {
        if (! in_array($student->status, ['withdrawn', 'transferred_out'], true)) {
            throw AcademicRuleException::because('err.movement.notReinstatable');
        }

        $this->registrar->guardQuota($student->school);
        $classId = $into?->id ?? $student->school_class_id;
        if ($classId) {
            $this->registrar->guardCapacity($student->school, $classId);
        }

        DB::transaction(function () use ($student, $classId, $reason) {
            $student->update(['status' => 'active', 'school_class_id' => $classId, 'withdrawn_on' => null, 'withdrawal_reason' => null]);
            $this->registrar->movement($student, 'reinstated', null, $classId, $reason);
            $this->audit('student_reinstated', $student->student_number);
        });
    }

    protected function audit(string $event, string $description): void
    {
        ActivityLog::query()->create([
            'school_id' => app(\App\Support\TenantContext::class)->id() ?? auth()->user()?->school_id,
            'user_id' => auth()->id(),
            'event' => $event,
            'description' => $description,
        ]);
    }
}
