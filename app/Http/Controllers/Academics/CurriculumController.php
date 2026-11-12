<?php

namespace App\Http\Controllers\Academics;

use App\Exceptions\AcademicRuleException;
use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ClassSubject;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TimetableSlot;
use App\Services\Academics\HomeroomService;
use App\Services\Lms\CourseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Curriculum (which subjects a class studies) and subject-teacher allocation. */
class CurriculumController extends Controller
{
    use HandlesAcademicRules;
    use SharesSchoolShell;

    public function index(Request $request): Response
    {
        $homeroom = app(HomeroomService::class);
        $classes = SchoolClass::query()->with(['level', 'classTeacher'])->orderBy('level_id')->orderBy('name')->get();
        $classId = $request->integer('class_id') ?: $classes->first()?->id;
        $class = $classes->firstWhere('id', $classId);

        $rows = $class
            ? ClassSubject::query()->with(['subject', 'teacher'])->where('school_class_id', $class->id)->get()
                ->sortBy(fn ($r) => $r->subject?->name)->values()
                ->map(fn (ClassSubject $r) => [
                    'subject_id' => $r->subject_id, 'subject' => $r->subject?->name, 'code' => $r->subject?->code,
                    'teacher_id' => $r->teacher_id, 'teacher' => $r->teacher?->name, 'periods_per_week' => $r->periods_per_week,
                    'is_specialist' => (bool) $r->subject?->is_specialist,
                ])
            : collect();

        return Inertia::render('Academics/Allocation', array_merge($this->shellProps(), [
            'classes' => $classes->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name, 'level' => $c->level?->name, 'class_teacher' => $c->classTeacher?->name,
                'class_teacher_id' => $c->class_teacher_id, 'homeroom' => $homeroom->isHomeroomClass($c),
            ]),
            'selected' => $class?->id,
            'curriculum' => $rows,
            'subjects' => Subject::query()->orderBy('name')->get(['id', 'name', 'code', 'is_specialist']),
            'teachers' => $this->teachingStaff($rows->pluck('teacher_id')->all()),
            'can' => ['manage' => auth()->user()->can('academics.manage')],
        ]));
    }

    public function save(Request $request, int $class): RedirectResponse
    {
        $record = SchoolClass::query()->findOrFail($class);
        $v = $request->validate([
            'rows' => ['present', 'array', 'max:60'],
            'rows.*.subject_id' => ['required', 'integer', $this->inSchool('subjects')],
            'rows.*.teacher_id' => ['nullable', 'integer', $this->inSchool('users')],
            'rows.*.periods_per_week' => ['nullable', 'integer', 'min:1', 'max:40'],
        ]);

        return $this->attempt(function () use ($record, $v) {
            $rows = collect($v['rows']);
            if ($rows->pluck('subject_id')->duplicates()->isNotEmpty()) {
                throw AcademicRuleException::because('err.curriculum.duplicate');
            }
            $teacherIds = $this->teachingStaff($rows->pluck('teacher_id')->all())->pluck('id');
            if ($rows->pluck('teacher_id')->filter()->contains(fn ($id) => ! $teacherIds->contains((int) $id))) {
                throw AcademicRuleException::because('err.class.teacherInvalid');
            }

            $keep = $rows->pluck('subject_id')->map(fn ($i) => (int) $i);
            $removed = ClassSubject::query()->where('school_class_id', $record->id)->whereNotIn('subject_id', $keep)->pluck('subject_id');
            foreach ($removed as $subjectId) {
                $inTimetable = TimetableSlot::query()->where('school_class_id', $record->id)->where('subject_id', $subjectId)->exists();
                $hasResults = Grade::query()->where('subject_id', $subjectId)
                    ->whereIn('student_id', Student::query()->where('school_class_id', $record->id)->select('id'))->exists();
                if ($inTimetable || $hasResults) {
                    throw AcademicRuleException::because('err.curriculum.inUse');
                }
            }

            DB::transaction(function () use ($record, $rows, $keep) {
                ClassSubject::query()->where('school_class_id', $record->id)->whereNotIn('subject_id', $keep)->delete();
                foreach ($rows as $r) {
                    $teacherId = ! empty($r['teacher_id']) ? (int) $r['teacher_id'] : null;
                    ClassSubject::query()->updateOrCreate(
                        ['school_class_id' => $record->id, 'subject_id' => $r['subject_id']],
                        ['school_id' => $record->school_id, 'teacher_id' => $teacherId, 'periods_per_week' => $r['periods_per_week'] ?? null]
                    );
                }
                ActivityLog::query()->create([
                    'school_id' => $record->school_id, 'user_id' => auth()->id(),
                    'event' => 'curriculum_saved', 'description' => "Class {$record->id}: {$rows->count()} subject(s)",
                ]);
            });

            app(HomeroomService::class)->applyClassTeacher($record->fresh(['level']));
            $lms = app(CourseService::class);
            foreach ($keep->merge($removed)->unique()->filter() as $subjectId) {
                $subject = Subject::query()->find($subjectId);
                if ($subject) {
                    $lms->syncSubjectCourse($subject);
                }
            }
        });
    }

    public function applyHomeroom(int $class): RedirectResponse
    {
        $record = SchoolClass::query()->with('level')->findOrFail($class);

        return $this->attempt(function () use ($record) {
            app(HomeroomService::class)->applyClassTeacher($record);
        });
    }

    /**
     * Copy curriculum. `from_class_id` copies that class INTO the current class.
     * `class_ids` copies the current class OUT to those classes.
     * Teachers are copied onto new rows only; an existing teacher on the target is left alone.
     */
    public function copy(Request $request, int $class): RedirectResponse
    {
        $current = SchoolClass::query()->findOrFail($class);
        $v = $request->validate([
            'from_class_id' => ['nullable', 'integer', $this->inSchool('school_classes')],
            'class_ids' => ['nullable', 'array', 'max:40'],
            'class_ids.*' => ['integer', $this->inSchool('school_classes')],
        ]);

        return $this->attempt(function () use ($current, $v) {
            if (! empty($v['from_class_id']) && (int) $v['from_class_id'] !== $current->id) {
                $this->copyCurriculum(SchoolClass::query()->findOrFail((int) $v['from_class_id']), [$current->id]);
            } elseif (! empty($v['class_ids'])) {
                $this->copyCurriculum($current, $v['class_ids']);
            } else {
                throw AcademicRuleException::because('err.curriculum.copyClass');
            }
        });
    }

    /** @param  list<int>  $targetIds */
    protected function copyCurriculum(SchoolClass $source, array $targetIds): void
    {
        $source->loadMissing('level');
        $subjects = ClassSubject::query()->with('subject')->where('school_class_id', $source->id)->get();
        $homeroom = app(HomeroomService::class);
        DB::transaction(function () use ($subjects, $targetIds, $source) {
            foreach (array_diff($targetIds, [$source->id]) as $targetId) {
                foreach ($subjects as $row) {
                    $teacherId = $row->teacher_id;
                    if ($source->class_teacher_id && (int) $row->teacher_id === (int) $source->class_teacher_id && ! $row->subject?->is_specialist) {
                        $teacherId = null;
                    }
                    $existing = ClassSubject::query()->where('school_class_id', $targetId)->where('subject_id', $row->subject_id)->first();
                    if ($existing) {
                        $existing->update([
                            'periods_per_week' => $existing->periods_per_week ?: $row->periods_per_week,
                            'teacher_id' => $existing->teacher_id ?: $teacherId,
                        ]);
                    } else {
                        ClassSubject::query()->create([
                            'school_id' => $source->school_id,
                            'school_class_id' => $targetId,
                            'subject_id' => $row->subject_id,
                            'teacher_id' => $teacherId,
                            'periods_per_week' => $row->periods_per_week,
                        ]);
                    }
                }
            }
        });
        foreach (array_diff($targetIds, [$source->id]) as $targetId) {
            $target = SchoolClass::query()->with('level')->find($targetId);
            if ($target) {
                $homeroom->applyClassTeacher($target);
            }
        }
        $lms = app(CourseService::class);
        foreach ($subjects->pluck('subject_id')->unique() as $subjectId) {
            $subject = Subject::query()->find($subjectId);
            if ($subject) {
                $lms->syncSubjectCourse($subject);
            }
        }
    }
}
