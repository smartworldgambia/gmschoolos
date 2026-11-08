<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Course;
use App\Services\Lms\LmsAccess;
use App\Support\SchoolTypes;
use App\Support\TenantContext;
use Carbon\CarbonInterface;

/** Shared helpers for the LMS controllers. */
trait UsesLms
{
    use HandlesAcademicRules;
    use SharesSchoolShell;

    protected function lms(): LmsAccess
    {
        return app(LmsAccess::class);
    }

    /** Resolve the caller's role in the course or abort 403. */
    protected function role(Course $course, array $roles = [LmsAccess::MANAGER, LmsAccess::TEACHER, LmsAccess::LEARNER]): string
    {
        return $this->lms()->authorize(auth()->user(), $course, $roles);
    }

    /** Authoring roles only (manager / course teacher). */
    protected function author(Course $course): string
    {
        return $this->role($course, [LmsAccess::MANAGER, LmsAccess::TEACHER]);
    }

    /** The logged-in learner's student record (null for staff). */
    protected function me(): ?\App\Models\Student
    {
        return $this->lms()->student(auth()->user());
    }

    /** Inertia props every LMS page shares (shell + learner band / role). */
    protected function lmsPage(array $extra = []): array
    {
        return $this->shellProps() + $this->lmsContext() + $extra;
    }

    /**
     * School type, division/class and LMS role already stored on School / Level / User.
     * Combined schools resolve a learner band from the student's class level — never a parallel config.
     */
    protected function lmsContext(): array
    {
        $user = auth()->user();
        $school = app(TenantContext::class)->school() ?? $user?->school;
        $category = SchoolTypes::normalise($school?->category);
        $student = $user ? $this->me() : null;
        $student?->loadMissing('schoolClass.level');
        $class = $student?->schoolClass;
        $level = $class?->level;

        $role = 'none';
        if ($user?->can('lms.manage') || $user?->can('lms.teach')) {
            $role = 'author';
        } elseif ($student) {
            $role = 'learner';
        } elseif ($user?->isGuardianPortal()) {
            $role = 'guardian';
        }

        return [
            'lmsContext' => [
                'role' => $role,
                'band' => $this->learningBand($category, $level?->grade_number, $level?->name, $level?->code),
                'school_type' => $category,
                'class_name' => $class?->name,
                'level_name' => $level?->name,
                'grade_number' => $level?->grade_number,
            ],
        ];
    }

    /**
     * Nursery / primary / basic secondary / senior secondary for UI density and labels.
     * Dedicated school types win; combined/international/other use the learner's level.
     */
    protected function learningBand(?string $category, ?int $grade, ?string $levelName, ?string $levelCode = null): string
    {
        $category = SchoolTypes::normalise($category);
        if (in_array($category, ['nursery', 'primary', 'basic_secondary', 'senior_secondary'], true)) {
            return $category;
        }

        $blob = strtolower(trim(($levelName ?? '').' '.($levelCode ?? '')));
        if ($blob !== '' && preg_match('/nurs|kg\b|kinder|creche|crèche|pre-?k|reception|baby/i', $blob)) {
            return 'nursery';
        }
        if ($blob !== '' && preg_match('/senior|sss\b/i', $blob)) {
            return 'senior_secondary';
        }
        if ($blob !== '' && preg_match('/junior|jss\b|upper\s*basic|basic\s*secondary/i', $blob)) {
            return 'basic_secondary';
        }
        if ($blob !== '' && preg_match('/lower\s*basic|primary/i', $blob)) {
            return 'primary';
        }
        if ($grade !== null) {
            if ($grade <= 6) {
                return 'primary';
            }
            if ($grade <= 9) {
                return 'basic_secondary';
            }

            return 'senior_secondary';
        }

        return $category === 'combined' ? 'combined' : 'primary';
    }

    /** Learner/staff work chip: upcoming, due, overdue, submitted, graded, returned, missing, to_mark. */
    protected function assignmentWorkState(?string $mine, ?CarbonInterface $dueAt, bool $author, int $toMark = 0): string
    {
        if ($author) {
            return $toMark > 0 ? 'to_mark' : ($dueAt && $dueAt->isPast() ? 'overdue' : ($this->dueSoon($dueAt) ? 'due' : 'upcoming'));
        }
        if (in_array($mine, ['graded', 'returned', 'submitted'], true)) {
            return $mine;
        }
        if ($dueAt?->isPast()) {
            return 'overdue';
        }
        if ($this->dueSoon($dueAt)) {
            return 'due';
        }

        return $mine === 'missing' ? 'missing' : 'upcoming';
    }

    protected function quizWorkState(?string $mine, ?CarbonInterface $closesAt, bool $author, int $toMark = 0): string
    {
        if ($author) {
            return $toMark > 0 ? 'to_mark' : ($closesAt && $closesAt->isPast() ? 'overdue' : ($this->dueSoon($closesAt) ? 'due' : 'upcoming'));
        }
        if (in_array($mine, ['graded', 'submitted'], true)) {
            return $mine;
        }
        if ($closesAt?->isPast()) {
            return 'overdue';
        }
        if ($this->dueSoon($closesAt)) {
            return 'due';
        }

        return 'upcoming';
    }

    protected function dueSoon(?CarbonInterface $when): bool
    {
        return $when !== null && $when->isFuture() && $when->lte(now()->addHours(48));
    }

    /** Header data every course page shares. */
    protected function courseHeader(Course $course, string $role): array
    {
        return $this->lmsContext() + [
            'course' => [
                'id' => $course->id, 'title' => $course->title, 'code' => $course->code, 'status' => $course->status,
                'subject' => $course->subject?->name,
            ],
            'role' => $role,
            'author' => LmsAccess::authors($role),
        ];
    }

    /** Accept a datetime-local style value and return it unchanged (validated as a date elsewhere). */
    protected function dt(?string $v): ?string
    {
        return $v !== null && trim($v) !== '' ? $v : null;
    }
}
