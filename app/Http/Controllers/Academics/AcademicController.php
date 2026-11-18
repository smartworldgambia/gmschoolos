<?php

namespace App\Http\Controllers\Academics;

use App\Exceptions\AcademicRuleException;
use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\ClassEducator;
use App\Models\Department;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Services\Academics\HomeroomService;
use App\Services\Lms\CourseService;
use App\Services\Onboarding\SetupWizard;
use App\Support\SchoolTypes;
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
        $school = $user->school;
        $academicBand = SchoolTypes::normalise((string) ($school?->category ?? '')) === 'nursery' ? 'nursery' : 'standard';

        $classes = SchoolClass::query()
            ->with(['level', 'classTeacher', 'educators.user:id,name'])
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
                'teaching_arrangement' => $class->teaching_arrangement ?: $class->level?->teaching_arrangement,
                'learning_centres' => $class->learning_centres ?? [],
                'educators' => $class->educators->map(fn (ClassEducator $e) => [
                    'id' => $e->id,
                    'user_id' => $e->user_id,
                    'name' => $e->user?->name,
                    'role' => $e->role,
                ])->values()->all(),
            ]);

        $subjects = Subject::query()->with(['department', 'classSubjects.teacher:id,name'])
            ->withCount('classSubjects')->orderBy('name')->get()
            ->map(fn (Subject $s) => [
                'id' => $s->id, 'name' => $s->name, 'code' => $s->code,
                'department_id' => $s->department_id, 'department' => $s->department?->name,
                'classes_count' => $s->class_subjects_count, 'is_specialist' => (bool) $s->is_specialist,
                'domain_kind' => $s->domain_kind,
                'teachers' => $s->classSubjects->pluck('teacher.name')->filter()->unique()->values()->all(),
            ]);

        return Inertia::render('Academics/Index', array_merge($this->shellProps(), [
            'academicBand' => $academicBand,
            'classes' => $classes,
            'subjects' => $subjects,
            'levels' => Level::query()->orderBy('sort_order')->get()
                ->map(fn (Level $l) => [
                    'id' => $l->id,
                    'name' => $l->name,
                    'code' => $l->code,
                    'grade_number' => $l->grade_number,
                    'age_min_months' => $l->age_min_months,
                    'age_max_months' => $l->age_max_months,
                    'description' => $l->description,
                    'is_active' => (bool) $l->is_active,
                    'teaching_arrangement' => $l->teaching_arrangement
                        ?: ($l->teaching_model === 'specialist' ? 'specialist' : 'homeroom'),
                    'teaching_model' => app(HomeroomService::class)->modelFor($l),
                ]),
            'departments' => Department::query()->with('head')->orderBy('name')->get()->map(fn ($d) => [
                'id' => $d->id, 'name' => $d->name, 'code' => $d->code, 'head_user_id' => $d->head_user_id, 'head' => $d->head?->name,
            ]),
            'teachers' => $this->teachingStaff(array_merge(
                $classes->pluck('class_teacher_id')->all(),
                $classes->flatMap(fn ($c) => collect($c['educators'])->pluck('user_id'))->all(),
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
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:20'],
            'teaching_model' => ['nullable', 'in:homeroom,specialist'],
            'teaching_arrangement' => ['nullable', 'in:homeroom,activity,learning_centre,specialist'],
            'age_min_months' => ['nullable', 'integer', 'min:0', 'max:216'],
            'age_max_months' => ['nullable', 'integer', 'min:0', 'max:216'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $level = $wizard->addLevel(auth()->user()->school, $v['name'], $v['code'] ?? null);
        $arrangement = $v['teaching_arrangement']
            ?? ((! empty($v['teaching_model']) && $v['teaching_model'] === 'specialist') ? 'specialist' : null);
        $level->update(array_filter([
            'teaching_arrangement' => $arrangement,
            'teaching_model' => $arrangement === 'specialist' ? 'specialist' : ($v['teaching_model'] ?? null),
            'age_min_months' => $v['age_min_months'] ?? null,
            'age_max_months' => $v['age_max_months'] ?? null,
            'description' => $v['description'] ?? null,
            'is_active' => array_key_exists('is_active', $v) ? (bool) $v['is_active'] : true,
        ], static fn ($x) => $x !== null));

        return back()->with('success', 'ok.saved');
    }

    public function updateLevel(Request $request, int $level): RedirectResponse
    {
        $record = Level::query()->findOrFail($level);
        $v = $request->validate([
            'teaching_model' => ['nullable', 'in:homeroom,specialist'],
            'teaching_arrangement' => ['nullable', 'in:homeroom,activity,learning_centre,specialist'],
            'age_min_months' => ['nullable', 'integer', 'min:0', 'max:216'],
            'age_max_months' => ['nullable', 'integer', 'min:0', 'max:216'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'name' => ['nullable', 'string', 'max:100'],
        ]);

        $arrangement = $v['teaching_arrangement']
            ?? ((! empty($v['teaching_model']) && $v['teaching_model'] === 'specialist') ? 'specialist' : ($v['teaching_model'] ?? $record->teaching_arrangement));

        $record->update([
            'name' => array_key_exists('name', $v) && $v['name'] ? trim($v['name']) : $record->name,
            'teaching_arrangement' => $arrangement,
            'teaching_model' => $arrangement === 'specialist' ? 'specialist' : 'homeroom',
            'age_min_months' => array_key_exists('age_min_months', $v) ? $v['age_min_months'] : $record->age_min_months,
            'age_max_months' => array_key_exists('age_max_months', $v) ? $v['age_max_months'] : $record->age_max_months,
            'description' => array_key_exists('description', $v) ? $v['description'] : $record->description,
            'is_active' => array_key_exists('is_active', $v) ? (bool) $v['is_active'] : $record->is_active,
        ]);

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
            'teaching_arrangement' => ['nullable', 'in:homeroom,activity,learning_centre,specialist'],
            'learning_centres' => ['nullable', 'array'],
            'learning_centres.*.key' => ['nullable', 'string', 'max:40'],
            'learning_centres.*.name' => ['required_with:learning_centres', 'string', 'max:120'],
            'learning_centres.*.active' => ['nullable', 'boolean'],
            'assistant_ids' => ['nullable', 'array'],
            'assistant_ids.*' => [$this->inSchool('users')],
            'specialist_ids' => ['nullable', 'array'],
            'specialist_ids.*' => [$this->inSchool('users')],
        ]);
        if (! empty($v['class_teacher_id']) && ! $this->teachingStaff([(int) $v['class_teacher_id']])->contains('id', (int) $v['class_teacher_id'])) {
            return back()->with('error', 'err.class.teacherInvalid')->withInput();
        }
        $class = $wizard->addClass(auth()->user()->school, (int) $v['level_id'], $v['name'], $v['stream'] ?? null, $v['capacity'] ?? null);
        $class->update([
            'teaching_arrangement' => $v['teaching_arrangement'] ?? null,
            'learning_centres' => $v['learning_centres'] ?? null,
        ]);
        $this->syncClassEducators(
            $class->fresh(),
            $v['class_teacher_id'] ?? null,
            $v['assistant_ids'] ?? [],
            $v['specialist_ids'] ?? []
        );

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
            'teaching_arrangement' => ['nullable', 'in:homeroom,activity,learning_centre,specialist'],
            'learning_centres' => ['nullable', 'array'],
            'learning_centres.*.key' => ['nullable', 'string', 'max:40'],
            'learning_centres.*.name' => ['required_with:learning_centres', 'string', 'max:120'],
            'learning_centres.*.active' => ['nullable', 'boolean'],
            'assistant_ids' => ['nullable', 'array'],
            'assistant_ids.*' => [$this->inSchool('users')],
            'specialist_ids' => ['nullable', 'array'],
            'specialist_ids.*' => [$this->inSchool('users')],
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
                'name' => trim($v['name']),
                'stream' => $v['stream'] ?? null,
                'capacity' => $v['capacity'] ?? null,
                'teaching_arrangement' => $v['teaching_arrangement'] ?? null,
                'learning_centres' => $v['learning_centres'] ?? $record->learning_centres,
            ]);
            $this->syncClassEducators(
                $record->fresh(),
                $v['class_teacher_id'] ?? null,
                $v['assistant_ids'] ?? [],
                $v['specialist_ids'] ?? []
            );
        });
    }

    /**
     * @param  list<int|string>  $assistantIds
     * @param  list<int|string>  $specialistIds
     */
    protected function syncClassEducators(SchoolClass $class, int|string|null $leadId, array $assistantIds, array $specialistIds): void
    {
        $leadId = $leadId ? (int) $leadId : null;
        $class->update(['class_teacher_id' => $leadId]);

        ClassEducator::query()->where('school_class_id', $class->id)->delete();

        if ($leadId) {
            ClassEducator::query()->create([
                'school_id' => $class->school_id,
                'school_class_id' => $class->id,
                'user_id' => $leadId,
                'role' => ClassEducator::LEAD,
            ]);
            app(HomeroomService::class)->applyClassTeacher($class->fresh(['level']));
        }

        foreach (array_unique(array_map('intval', $assistantIds)) as $uid) {
            if ($uid && $uid !== $leadId) {
                ClassEducator::query()->create([
                    'school_id' => $class->school_id,
                    'school_class_id' => $class->id,
                    'user_id' => $uid,
                    'role' => ClassEducator::ASSISTANT,
                ]);
            }
        }

        foreach (array_unique(array_map('intval', $specialistIds)) as $uid) {
            if ($uid && $uid !== $leadId) {
                ClassEducator::query()->create([
                    'school_id' => $class->school_id,
                    'school_class_id' => $class->id,
                    'user_id' => $uid,
                    'role' => ClassEducator::SPECIALIST,
                ]);
            }
        }
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
