<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

/**
 * Permission-based (see config/rbac.php). Students/parents are restricted to their own records
 * through the portal.student / portal.parent permissions; staff are governed by students.*.
 */
class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('students.view');
    }

    public function view(User $user, Student $student): bool
    {
        if (! $user->can('students.view')) {
            return false;
        }

        // Tenant boundary: a school user can only ever see their own school's students.
        if (! $user->school_id || (int) $user->school_id !== (int) $student->school_id) {
            return false;
        }

        if ($user->isLearnerPortal()) {
            return (int) $user->studentProfile?->id === (int) $student->id;
        }

        if ($user->isGuardianPortal()) {
            $guardian = $user->guardianProfile;

            return $guardian
                ? $guardian->students()->where('students.id', $student->id)->exists()
                : false;
        }

        // Teachers (without academics.manage) see only learners in classes they teach or are class teacher of.
        if ($user->can('portal.teacher') && ! $user->can('academics.manage')) {
            return $student->school_class_id !== null
                && app(\App\Services\Academics\TeacherScope::class)->classIds($user)->contains((int) $student->school_class_id);
        }

        return true;
    }

    public function create(User $user): bool
    {
        return (bool) $user->school_id && $user->can('students.create');
    }

    public function update(User $user, Student $student): bool
    {
        return $user->can('students.update')
            && (int) $user->school_id === (int) $student->school_id;
    }
}