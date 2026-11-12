<?php

namespace App\Services\Academics;

use App\Models\ClassSubject;
use App\Models\Department;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * "Teachers access only their assigned classes and subjects unless additional permissions are granted."
 *
 * A user is UNRESTRICTED for an area when they hold that area's management permission (for example
 * `academics.manage` or `exams.manage`). Everyone else is limited to:
 *   - classes where they are the class teacher,
 *   - class + subject pairs they are allocated to (class_subjects.teacher_id),
 *   - classes/subjects they head as a head of department,
 *   - classes they appear in on the timetable.
 * All queries run through tenant-scoped models, so another school's allocations can never match.
 */
class TeacherScope
{
    public function unrestricted(User $user, string $permission = 'academics.manage'): bool
    {
        return $user->can($permission);
    }

    public function isClassTeacher(User $user, int $classId): bool
    {
        return SchoolClass::query()->whereKey($classId)->where('class_teacher_id', $user->id)->exists();
    }

    /** @return Collection<int,int> */
    public function classIds(User $user): Collection
    {
        $viaClassTeacher = SchoolClass::query()->where('class_teacher_id', $user->id)->pluck('id');
        $viaAllocation = ClassSubject::query()->where('teacher_id', $user->id)->pluck('school_class_id');
        $viaSlots = TimetableSlot::query()->where('teacher_id', $user->id)->pluck('school_class_id');
        $viaDepartment = ClassSubject::query()
            ->whereIn('subject_id', $this->headedSubjectIds($user))
            ->pluck('school_class_id');

        return $viaClassTeacher->merge($viaAllocation)->merge($viaSlots)->merge($viaDepartment)
            ->map(fn ($id) => (int) $id)->unique()->values();
    }

    public function canAccessClass(User $user, int $classId, string $permission = 'academics.manage'): bool
    {
        return $this->unrestricted($user, $permission) || $this->classIds($user)->contains($classId);
    }

    /** May this user mark / teach this subject in this class? */
    public function canTeach(User $user, int $classId, int $subjectId, string $permission = 'exams.manage'): bool
    {
        if ($this->unrestricted($user, $permission)) {
            return true;
        }

        if (ClassSubject::query()
            ->where('school_class_id', $classId)->where('subject_id', $subjectId)
            ->where('teacher_id', $user->id)->exists()) {
            return true;
        }

        if (app(HomeroomService::class)->classTeacherTeaches($user, $classId, $subjectId)) {
            return true;
        }

        return in_array($subjectId, $this->headedSubjectIds($user)->all(), true)
            && ClassSubject::query()->where('school_class_id', $classId)->where('subject_id', $subjectId)->exists();
    }

    /** @return Collection<int,int> subjects in departments this user heads */
    public function headedSubjectIds(User $user): Collection
    {
        $departmentIds = Department::query()->where('head_user_id', $user->id)->pluck('id');
        if ($departmentIds->isEmpty()) {
            return collect();
        }

        return Subject::query()->whereIn('department_id', $departmentIds)->pluck('id')->map(fn ($i) => (int) $i);
    }

    /** Restrict a Student query to the learners this user may see. Unrestricted users pass through unchanged. */
    public function limitStudents($query, User $user)
    {
        if ($this->unrestricted($user) || ! $user->can('portal.teacher')) {
            return $query;
        }

        return $query->whereIn('school_class_id', $this->classIds($user));
    }
}
