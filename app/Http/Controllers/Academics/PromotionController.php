<?php

namespace App\Http\Controllers\Academics;

use App\Exceptions\AcademicRuleException;
use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\Students\StudentMovementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** End-of-year progression: promote, repeat or graduate selected learners. Every move is recorded, nothing is deleted. */
class PromotionController extends Controller
{
    use HandlesAcademicRules;
    use SharesSchoolShell;

    public function index(Request $request): Response
    {
        $classes = SchoolClass::query()->with('level')->withCount(['students' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('level_id')->orderBy('name')->get();
        $from = $classes->firstWhere('id', $request->integer('class_id'));

        return Inertia::render('Academics/Promotion', array_merge($this->shellProps(), [
            'classes' => $classes->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name, 'level' => $c->level?->name, 'capacity' => $c->capacity, 'students_count' => $c->students_count,
            ]),
            'selected' => $from?->id,
            'students' => $from
                ? Student::query()->where('school_class_id', $from->id)->where('status', 'active')->orderBy('first_name')->get()
                    ->map(fn ($s) => ['id' => $s->id, 'student_number' => $s->student_number, 'name' => $s->full_name])
                : [],
        ]));
    }

    public function store(Request $request, StudentMovementService $movements): RedirectResponse
    {
        $v = $request->validate([
            'action' => ['required', 'in:promote,repeat,graduate'],
            'from_class_id' => ['required', $this->inSchool('school_classes')],
            'to_class_id' => ['nullable', $this->inSchool('school_classes')],
            'student_ids' => ['required', 'array', 'min:1', 'max:500'],
            'student_ids.*' => ['integer', $this->inSchool('students')],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->attempt(function () use ($v, $movements) {
            // only learners who really are active in the chosen source class are moved
            $ids = Student::query()->where('school_class_id', $v['from_class_id'])->where('status', 'active')
                ->whereIn('id', $v['student_ids'])->pluck('id')->all();
            if ($ids === []) {
                throw AcademicRuleException::because('err.movement.noneEligible');
            }

            if ($v['action'] === 'graduate') {
                foreach (Student::query()->whereIn('id', $ids)->get() as $student) {
                    $movements->graduate($student);
                }

                return;
            }

            $target = SchoolClass::query()->find($v['to_class_id'] ?? ($v['action'] === 'repeat' ? $v['from_class_id'] : 0));
            if (! $target) {
                throw AcademicRuleException::because('err.class.notFound');
            }
            if ($v['action'] === 'repeat') {
                $movements->repeat($ids, $target, $v['reason'] ?? null);
            } else {
                if ((int) $target->id === (int) $v['from_class_id']) {
                    throw AcademicRuleException::because('err.movement.sameClass');
                }
                $movements->promote($ids, $target, $v['reason'] ?? null);
            }
        }, 'ok.movementSaved');
    }
}
