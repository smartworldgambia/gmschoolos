<?php

namespace App\Http\Controllers\Academics;

use App\Exceptions\AcademicRuleException;
use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Services\Academics\HomeroomService;
use App\Services\Lms\CourseService;
use App\Services\Onboarding\SetupWizard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Academic structure: levels, classes/streams, subjects, departments (with heads of department).
 * Creation and removal reuse the Phase 4 SetupWizard services so the wizard and this screen enforce the same rules.
 */
class AcademicController extends Controller
{
    use HandlesAcademicRules;
    use SharesSchoolShell;

    public function index(): Response
    {
        $user = auth()->user();

        $classes = SchoolClass::query()
            ->with(['level', 'classTeacher'])
            ->withCount(['students' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('level_id')->orderBy('name')
            ->get()
            ->map(fn (SchoolClass $class) => [
                'id' => $class->id,
                'name' => $class->name,
                'stream' => $class->stream,
                'level' => $class->level?->name,
                'level_id' => $class->level_id,
                'teacher' => $class->classTeacher?->name,
                'class_teacher_id' => $class->class_teacher_id,
                'students_count' => $class->students_count,
                'capacity' => $class->capacity,
            ]);

        $subjects = Subject::query()->with(['department', 'classSubjects.teacher:id,name'])
            ->withCount('classSubjects')->orderBy('name')->get()
            ->map(fn (Subject $s) => [
                'id' => $s->id, 'name' => $s->name, 'code' => $s->code,
                'department_id' => $s->department_id, 'department' => $s->department?->name,
                'classes_count' => $s->class_subjects_count, 'is_specialist' => (bool) $s->is_specialist,
                'teachers' => $s->classSubjects->pluck('teacher.name')->filter()->unique()->values()->all(),
            ]);

        return Inertia::render('Academics/Index', array_merge($this->shellProps(), [
            'classes' => $classes,
            'subjects' => $subjects,
            'levels' => Level::query()->orderBy('sort_order')->get(['id', 'name', 'code', 'teaching_model', 'grade_number'])
                ->map(fn (Level $l) => [
                    'id' => $l->id, 'name' => $l->name, 'code' => $l->code, 'grade_number' => $l->grade_number,
                    'teaching_model' => app(HomeroomService::class)->modelFor($l),
                ]),
            'departments' => Department::query()->with('head')->orderBy('name')->get()->map(fn ($d) => [
                'id' => $d->id, 'name' => $d->name, 'code' => $d->code, 'head_user_id' => $d->head_user_id, 'head' => $d->head?->name,
            ]),
            'teachers' => $this->teachingStaff(array_merge(
                $classes->pluck('class_teacher_id')->all(),
                Department::query()->whereNotNull('head_user_id')->pluck('head_user_id')->all(),
            )),
            'can' => ['manage' => $user->can('academics.manage')],
            'links' => [
                'allocation' => route('academics.allocation'),
                'calendar' => route('academics.calendar'),
                'promotion' => $user->can('academics.manage') ? route('academics.promotion') : null,
            ],
        ]));
    }

    // ------------------------------------------------------------------ levels / classes

    public function storeLevel(Request $request, SetupWizard $wizard): RedirectResponse
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:100'], 'code' => ['nullable', 'string', 'max:20'],
            'teaching_model' => ['nullable', 'in:homeroom,specialist'],
        ]);
        $level = $wizard->addLevel(auth()->user()->school, $v['name'], $v['code'] ?? null);
        if (! empty($v['teaching_model'])) {
            $level->update(['teaching_model' => $v['teaching_model']]);
        }

        return back()->with('success', 'ok.saved');
    }

    public function updateLevel(Request $request, int $level): RedirectResponse
    {
        $record = Level::query()->findOrFail($level);
        $v = $request->validate(['teaching_model' => ['required', 'in:homeroom,specialist']]);
        $record->update(['teaching_model' => $v['teaching_model']]);

        return back()->with('success', 'ok.saved');
    }

    public function destroyLevel(int $level, SetupWizard $wizard): RedirectResponse
    {
        $wizard->removeLevel(auth()->user()->school, $level);

        return back()->with('success', 'ok.removed');
    }

    public function storeClass(Request $request, SetupWizard $wizard): RedirectResponse
    {
        $v = $request->validate([
            'level_id' => ['required', $this->inSchool('levels')],
            'name' => ['required', 'string', 'max:100'],
            'stream' => ['nullable', 'string', 'max:50'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
            'class_teacher_id' => ['nullable', $this->inSchool('users')],
        ]);
        if (! empty($v['class_teacher_id']) && ! $this->teachingStaff([(int) $v['class_teacher_id']])->contains('id', (int) $v['class_teacher_id'])) {
            return back()->with('error', 'err.class.teacherInvalid')->withInput();
        }
        $class = $wizard->addClass(auth()->user()->school, (int) $v['level_id'], $v['name'], $v['stream'] ?? null, $v['capacity'] ?? null);
        if (! empty($v['class_teacher_id'])) {
            $class->update(['class_teacher_id' => $v['class_teacher_id']]);
            app(HomeroomService::class)->applyClassTeacher($class->fresh(['level']));
        }

        return back()->with('success', 'ok.saved');
    }

    public function updateClass(Request $request, int $class): RedirectResponse
    {
        $record = SchoolClass::query()->findOrFail($class);
        $v = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'stream' => ['nullable', 'string', 'max:50'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
            'class_teacher_id' => ['nullable', $this->inSchool('users')],
        ]);

        return $this->attempt(function () use ($record, $v) {
            if (! empty($v['class_teacher_id']) && ! $this->teachingStaff([(int) $v['class_teacher_id']])->contains('id', (int) $v['class_teacher_id'])) {
                throw AcademicRuleException::because('err.class.teacherInvalid');
            }
            if ($v['capacity'] ?? null) {
                $active = $record->students()->where('status', 'active')->count();
                if ($v['capacity'] < $active) {
                    throw AcademicRuleException::because('err.class.capacityBelowEnrolment');
                }
            }
            $dup = SchoolClass::query()->where('id', '!=', $record->id)->where('level_id', $record->level_id)
                ->whereRaw('lower(name) = ?', [mb_strtolower(trim($v['name']))])
                ->when($v['stream'] ?? null, fn ($q, $s) => $q->whereRaw('lower(stream) = ?', [mb_strtolower($s)]), fn ($q) => $q->whereNull('stream'))
                ->exists();
            if ($dup) {
                throw AcademicRuleException::because('err.name.taken');
            }
            $record->update([
                'name' => trim($v['name']), 'stream' => $v['stream'] ?? null,
                'capacity' => $v['capacity'] ?? null, 'class_teacher_id' => $v['class_teacher_id'] ?? null,
            ]);
            app(HomeroomService::class)->applyClassTeacher($record->fresh(['level']));
        });
    }

    public function destroyClass(int $class, SetupWizard $wizard): RedirectResponse
    {
        $wizard->removeClass(auth()->user()->school, $class);

        return back()->with('success', 'ok.removed');
    }

    // ------------------------------------------------------------------ subjects / departments

    public function storeSubject(Request $request, SetupWizard $wizard): RedirectResponse
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:150'], 'code' => ['nullable', 'string', 'max:20'],
            'department_id' => ['nullable', $this->inSchool('departments')],
            'is_specialist' => ['nullable', 'boolean'],
        ]);
        $subject = $wizard->addSubject(auth()->user()->school, $v['name'], $v['code'] ?? null);
        $subject->update([
            'department_id' => $v['department_id'] ?? null,
            'is_specialist' => array_key_exists('is_specialist', $v)
                ? (bool) $v['is_specialist']
                : Subject::guessSpecialist($v['name'], (string) ($v['code'] ?? '')),
        ]);
        app(CourseService::class)->syncSubjectCourse($subject->fresh());

        return back()->with('success', 'ok.saved');
    }

    public function updateSubject(Request $request, int $subject): RedirectResponse
    {
        $record = Subject::query()->findOrFail($subject);
        $v = $request->validate([
            'name' => ['required', 'string', 'max:150'], 'code' => ['nullable', 'string', 'max:20'],
            'department_id' => ['nullable', $this->inSchool('departments')],
            'is_specialist' => ['nullable', 'boolean'],
        ]);

        return $this->attempt(function () use ($record, $v) {
            $code = ! empty($v['code']) ? strtoupper(trim($v['code'])) : null;
            $dup = Subject::query()->where('id', '!=', $record->id)->where(fn ($q) => $q
                ->whereRaw('lower(name) = ?', [mb_strtolower(trim($v['name']))])
                ->when($code, fn ($qq) => $qq->orWhere('code', $code)))->exists();
            if ($dup) {
                throw AcademicRuleException::because('err.name.taken');
            }
            $record->update([
                'name' => trim($v['name']), 'code' => $code, 'department_id' => $v['department_id'] ?? null,
                'is_specialist' => array_key_exists('is_specialist', $v) ? (bool) $v['is_specialist'] : $record->is_specialist,
            ]);
            app(CourseService::class)->syncSubjectCourse($record->fresh());
        });
    }

    public function destroySubject(int $subject, SetupWizard $wizard): RedirectResponse
    {
        $wizard->removeSubject(auth()->user()->school, $subject);

        return back()->with('success', 'ok.removed');
    }

    public function storeDepartment(Request $request, SetupWizard $wizard): RedirectResponse
    {
        $v = $request->validate(['name' => ['required', 'string', 'max:150'], 'code' => ['nullable', 'string', 'max:20']]);
        $wizard->addDepartment(auth()->user()->school, $v['name'], $v['code'] ?? null);

        return back()->with('success', 'ok.saved');
    }

    public function updateDepartment(Request $request, int $department): RedirectResponse
    {
        $record = Department::query()->findOrFail($department);
        $v = $request->validate([
            'name' => ['required', 'string', 'max:150'], 'code' => ['nullable', 'string', 'max:20'],
            'head_user_id' => ['nullable', $this->inSchool('users')],
        ]);

        return $this->attempt(function () use ($record, $v) {
            if (! empty($v['head_user_id']) && ! $this->teachingStaff([(int) $v['head_user_id']])->contains('id', (int) $v['head_user_id'])) {
                throw AcademicRuleException::because('err.department.headInvalid');
            }
            if (Department::query()->where('id', '!=', $record->id)->whereRaw('lower(name) = ?', [mb_strtolower(trim($v['name']))])->exists()) {
                throw AcademicRuleException::because('err.name.taken');
            }
            $record->update(['name' => trim($v['name']), 'code' => $v['code'] ?? null, 'head_user_id' => $v['head_user_id'] ?? null]);
        });
    }

    public function destroyDepartment(int $department, SetupWizard $wizard): RedirectResponse
    {
        $wizard->removeDepartment(auth()->user()->school, $department);

        return back()->with('success', 'ok.removed');
    }

}
