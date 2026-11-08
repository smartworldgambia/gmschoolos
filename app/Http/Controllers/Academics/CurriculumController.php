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
use App\Models\User;
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
        $classes = SchoolClass::query()->with('level')->orderBy('level_id')->orderBy('name')->get();
        $classId = $request->integer('class_id') ?: $classes->first()?->id;
        $class = $classes->firstWhere('id', $classId);

        $rows = $class
            ? ClassSubject::query()->with(['subject', 'teacher'])->where('school_class_id', $class->id)->get()
                ->sortBy(fn ($r) => $r->subject?->name)->values()
                ->map(fn (ClassSubject $r) => [
                    'subject_id' => $r->subject_id, 'subject' => $r->subject?->name, 'code' => $r->subject?->code,
                    'teacher_id' => $r->teacher_id, 'teacher' => $r->teacher?->name, 'periods_per_week' => $r->periods_per_week,
                ])
            : collect();

        return Inertia::render('Academics/Allocation', array_merge($this->shellProps(), [
            'classes' => $classes->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'level' => $c->level?->name, 'class_teacher' => $c->classTeacher?->name]),
            'selected' => $class?->id,
            'curriculum' => $rows,
            'subjects' => Subject::query()->orderBy('name')->get(['id', 'name', 'code']),
            'teachers' => $this->teachers(),
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
            $teacherIds = $this->teachers()->pluck('id');
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
                    ClassSubject::query()->updateOrCreate(
                        ['school_class_id' => $record->id, 'subject_id' => $r['subject_id']],
                        ['school_id' => $record->school_id, 'teacher_id' => $r['teacher_id'] ?? null, 'periods_per_week' => $r['periods_per_week'] ?? null]
                    );
                }
                ActivityLog::query()->create([
                    'school_id' => $record->school_id, 'user_id' => auth()->id(),
                    'event' => 'curriculum_saved', 'description' => "Class {$record->id}: {$rows->count()} subject(s)",
                ]);
            });
        });
    }

    /** Copy the subject list (not the teachers) of one class to other classes. */
    public function copy(Request $request, int $class): RedirectResponse
    {
        $source = SchoolClass::query()->findOrFail($class);
        $v = $request->validate([
            'class_ids' => ['required', 'array', 'min:1', 'max:40'],
            'class_ids.*' => ['integer', $this->inSchool('school_classes')],
        ]);

        return $this->attempt(function () use ($source, $v) {
            $subjects = ClassSubject::query()->where('school_class_id', $source->id)->get();
            DB::transaction(function () use ($subjects, $v, $source) {
                foreach (array_diff($v['class_ids'], [$source->id]) as $targetId) {
                    foreach ($subjects as $row) {
                        ClassSubject::query()->firstOrCreate(
                            ['school_class_id' => $targetId, 'subject_id' => $row->subject_id],
                            ['school_id' => $source->school_id, 'periods_per_week' => $row->periods_per_week]
                        );
                    }
                }
            });
        });
    }

    protected function teachers()
    {
        return User::query()->where('school_id', $this->schoolId())->permission('portal.teacher')
            ->orderBy('name')->get(['id', 'name'])->unique('id')->values();
    }
}
