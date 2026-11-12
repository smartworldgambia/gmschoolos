<?php

namespace App\Services\Academics;

use App\Models\ClassSubject;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use App\Services\Lms\CourseService;

/**
 * Nursery/primary classrooms: the class teacher teaches every subject in that room except specialists (IRK, CRK, PE).
 * Upper basic and senior stay departmental: each subject has its own teacher.
 */
class HomeroomService
{
    public const HOMEROOM = 'homeroom';

    public const SPECIALIST = 'specialist';

    public static function defaultModel(?int $grade, string $name = '', string $code = ''): string
    {
        $blob = strtolower(trim($name.' '.$code));
        if ($blob !== '' && preg_match('/nurs|kg\b|kinder|creche|crèche|pre-?k|reception|baby/i', $blob)) {
            return self::HOMEROOM;
        }
        if ($blob !== '' && preg_match('/senior|sss\b|junior|jss\b|upper\s*basic|basic\s*secondary/i', $blob)) {
            return self::SPECIALIST;
        }
        if ($grade !== null && $grade <= 6) {
            return self::HOMEROOM;
        }

        return self::SPECIALIST;
    }

    public function modelFor(Level $level): string
    {
        $stored = (string) $level->teaching_model;
        if (in_array($stored, [self::HOMEROOM, self::SPECIALIST], true)) {
            return $stored;
        }

        return self::defaultModel($level->grade_number, (string) $level->name, (string) $level->code);
    }

    public function isHomeroomClass(SchoolClass $class): bool
    {
        $class->loadMissing('level');

        return $class->level ? $this->modelFor($class->level) === self::HOMEROOM : false;
    }

    /**
     * Fill unassigned non-specialist subjects with this classroom's class teacher. Never overwrites a set teacher.
     *
     * @return int rows updated
     */
    public function applyClassTeacher(SchoolClass $class): int
    {
        $class->loadMissing('level');
        if (! $class->class_teacher_id || ! $this->isHomeroomClass($class)) {
            return 0;
        }

        $rows = ClassSubject::query()->withoutGlobalScopes()
            ->with('subject')->where('school_id', $class->school_id)->where('school_class_id', $class->id)->get();
        $updated = 0;
        $subjectIds = [];
        foreach ($rows as $row) {
            if ($row->teacher_id || $row->subject?->is_specialist) {
                continue;
            }
            $row->update(['teacher_id' => $class->class_teacher_id]);
            $updated++;
            $subjectIds[] = (int) $row->subject_id;
        }

        if ($subjectIds !== []) {
            $lms = app(CourseService::class);
            foreach (Subject::query()->withoutGlobalScopes()->whereIn('id', array_unique($subjectIds))->get() as $subject) {
                $lms->syncSubjectCourse($subject);
            }
        }

        return $updated;
    }

    /** Class teacher of a homeroom classroom may teach every non-specialist subject that classroom takes. */
    public function classTeacherTeaches(User $user, int $classId, int $subjectId): bool
    {
        $class = SchoolClass::query()->with('level')->whereKey($classId)->where('class_teacher_id', $user->id)->first();
        if (! $class || ! $this->isHomeroomClass($class)) {
            return false;
        }
        $subject = Subject::query()->find($subjectId);
        if (! $subject || $subject->is_specialist) {
            return false;
        }

        return ClassSubject::query()->where('school_class_id', $classId)->where('subject_id', $subjectId)->exists();
    }
}
