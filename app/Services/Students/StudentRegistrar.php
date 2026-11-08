<?php

namespace App\Services\Students;

use App\Exceptions\AcademicRuleException;
use App\Models\AcademicYear;
use App\Models\ActivityLog;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentMovement;
use App\Services\Platform\QuotaService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The single place where a Student row is created (manual registration, admission enrolment, CSV import).
 * It owns: the permanent student number, quota, class capacity, duplicate detection and the first movement record.
 */
class StudentRegistrar
{
    public const FIELDS = [
        'first_name', 'last_name', 'gender', 'date_of_birth', 'place_of_birth', 'nationality', 'religion',
        'school_class_id', 'admitted_on', 'previous_school', 'address',
        'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relationship',
        'medical_notes', 'welfare_notes',
    ];

    public function __construct(
        protected QuotaService $quotas,
        protected StudentNumberGenerator $numbers,
    ) {}

    /**
     * @param  array<string,mixed>  $data  keys from self::FIELDS (+ optional student_number for imports)
     * @param  array<string,mixed>|null  $guardian  name|first_name,last_name, relationship, phone, email
     *
     * @throws AcademicRuleException
     */
    public function register(School $school, array $data, ?array $guardian = null, bool $allowDuplicate = false, string $event = 'enrolled'): Student
    {
        $this->guardQuota($school);

        $classId = ! empty($data['school_class_id']) ? (int) $data['school_class_id'] : null;
        if ($classId) {
            $this->guardCapacity($school, $classId);
        }

        if (! $allowDuplicate && $this->findDuplicate($school->id, (string) $data['first_name'], (string) $data['last_name'], $data['date_of_birth'] ?? null)) {
            throw AcademicRuleException::because('err.student.duplicate');
        }

        return DB::transaction(function () use ($school, $data, $guardian, $classId, $event) {
            // A pre-existing number (CSV import of a school's own numbering) is honoured once and must be unique;
            // otherwise a permanent number is generated. It is never changed afterwards.
            $number = filled($data['student_number'] ?? null) ? trim((string) $data['student_number']) : $this->numbers->next($school->id);
            if (Student::withoutGlobalScopes()->where('school_id', $school->id)->where('student_number', $number)->exists()) {
                throw AcademicRuleException::because('err.student.numberTaken');
            }

            $student = Student::query()->create([
                ...array_intersect_key($data, array_flip(self::FIELDS)),
                'school_id' => $school->id,
                'school_class_id' => $classId,
                'student_number' => $number,
                'status' => 'active',
                'admitted_on' => $data['admitted_on'] ?? now()->toDateString(),
            ]);

            if ($guardian) {
                $this->attachGuardian($school, $student, $guardian);
            }

            $this->movement($student, $event, null, $classId, null);

            ActivityLog::query()->create([
                'school_id' => $school->id,
                'user_id' => auth()->id(),
                'event' => 'student_registered',
                'description' => "Registered student {$student->student_number}",
            ]);

            return $student;
        });
    }

    public function findDuplicate(int $schoolId, string $first, string $last, mixed $dob): ?Student
    {
        if (blank($dob)) {
            return null; // name alone is not evidence of a duplicate
        }

        return Student::query()->where('school_id', $schoolId)
            ->whereRaw('lower(first_name) = ?', [mb_strtolower(trim($first))])
            ->whereRaw('lower(last_name) = ?', [mb_strtolower(trim($last))])
            ->whereDate('date_of_birth', $dob)
            ->first();
    }

    public function attachGuardian(School $school, Student $student, array $g): ?Guardian
    {
        $first = $g['first_name'] ?? null;
        $last = $g['last_name'] ?? null;
        if (! $first && ! empty($g['name'])) {
            $parts = preg_split('/\s+/', trim((string) $g['name']), 2) ?: [];
            $first = $parts[0] ?? null;
            $last = $parts[1] ?? null;
        }
        if (! $first) {
            return null;
        }

        // Siblings share one guardian record (and so one parent account) when the phone or e-mail matches.
        $existing = null;
        if (filled($g['phone'] ?? null)) {
            $existing = Guardian::query()->where('school_id', $school->id)->where('phone', $g['phone'])->first();
        }
        if (! $existing && filled($g['email'] ?? null)) {
            $existing = Guardian::query()->where('school_id', $school->id)->where('email', $g['email'])->first();
        }

        $guardian = $existing ?? Guardian::query()->create([
            'school_id' => $school->id,
            'first_name' => $first,
            'last_name' => $last ?: '-',
            'relationship' => $g['relationship'] ?? null,
            'phone' => $g['phone'] ?? null,
            'email' => $g['email'] ?? null,
            'occupation' => $g['occupation'] ?? null,
        ]);

        $isFirst = ! $student->guardians()->exists();
        $student->guardians()->syncWithoutDetaching([
            $guardian->id => ['school_id' => $school->id, 'is_primary' => $isFirst],
        ]);

        return $guardian;
    }

    public function guardQuota(School $school): void
    {
        try {
            $this->quotas->assertCanAddStudent($school);
        } catch (RuntimeException $e) {
            if ($e instanceof AcademicRuleException) {
                throw $e;
            }
            throw AcademicRuleException::because('err.quota.students');
        }
    }

    public function guardCapacity(School $school, int $classId): void
    {
        $class = SchoolClass::query()->where('school_id', $school->id)->find($classId);
        if (! $class) {
            throw AcademicRuleException::because('err.class.notFound');
        }
        if ($class->capacity !== null) {
            $count = Student::query()->where('school_class_id', $class->id)->where('status', 'active')->count();
            if ($count >= $class->capacity) {
                throw AcademicRuleException::because('err.class.full');
            }
        }
    }

    public function movement(Student $student, string $event, ?int $from, ?int $to, ?string $reason, ?string $effectiveOn = null): StudentMovement
    {
        return StudentMovement::query()->create([
            'school_id' => $student->school_id,
            'student_id' => $student->id,
            'academic_year_id' => AcademicYear::query()->where('is_current', true)->value('id'),
            'event' => $event,
            'from_class_id' => $from,
            'to_class_id' => $to,
            'reason' => $reason,
            'effective_on' => $effectiveOn ?? now()->toDateString(),
            'performed_by' => auth()->id(),
        ]);
    }
}
