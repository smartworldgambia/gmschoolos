<?php

namespace App\Services\Onboarding;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Level;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolSetupStep;
use App\Models\Staff;
use App\Models\StaffInvitation;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Twelve-step school setup. Progress is stored per school so an administrator can stop and resume.
 *
 * A step counts as complete only when the administrator confirmed it (or, for list steps, it holds real data that they
 * entered). Details collected at registration (profile, logo) still have to be confirmed in the wizard. Seeded scaffolding never counts as "done" on its own, so nothing is reported as finished
 * that nobody has looked at.
 */
class SetupWizard
{
    /** @var array<int, string> ordered step keys */
    public const STEPS = [
        'profile', 'branding', 'school_type', 'structure', 'academic_year', 'departments',
        'classes', 'subjects', 'staff', 'students', 'parents', 'website',
    ];

    /** Steps an administrator may consciously postpone / skip. */
    public const SKIPPABLE = ['branding', 'departments', 'staff', 'students', 'parents', 'website'];

    /** Steps that are complete as soon as real data exists (still markable explicitly). */
    private const DATA_DRIVEN = ['departments', 'classes', 'subjects', 'staff', 'students'];

    public function __construct(private readonly TenantContext $tenant) {}

    public function isStep(string $step): bool
    {
        return in_array($step, self::STEPS, true);
    }

    /**
     * @return array<string, array{status: string, count: ?int}>
     */
    public function statuses(School $school): array
    {
        return $this->tenant->runForSchool($school, function () use ($school) {
            $saved = SchoolSetupStep::query()->where('school_id', $school->id)->pluck('status', 'step');
            $counts = $this->counts($school);

            $out = [];
            foreach (self::STEPS as $step) {
                $status = $saved[$step] ?? null;

                if ($status === null) {
                    $status = $this->satisfiedByData($school, $step, $counts) ? SchoolSetupStep::DONE : 'todo';
                }

                $out[$step] = ['status' => $status, 'count' => $counts[$step] ?? null];
            }

            return $out;
        });
    }

    /** @return array{done: int, skipped: int, total: int, percent: int, next: ?string, complete: bool} */
    public function progress(School $school): array
    {
        $statuses = $this->statuses($school);
        $done = collect($statuses)->where('status', SchoolSetupStep::DONE)->count();
        $skipped = collect($statuses)->where('status', SchoolSetupStep::SKIPPED)->count();
        $total = count(self::STEPS);
        $next = collect($statuses)->filter(fn ($s) => $s['status'] === 'todo')->keys()->first();

        return [
            'done' => $done,
            'skipped' => $skipped,
            'total' => $total,
            'percent' => (int) round((($done + $skipped) / $total) * 100),
            'next' => $next,
            'complete' => $next === null,
        ];
    }

    public function markDone(School $school, string $step, ?User $by = null): void
    {
        $this->record($school, $step, SchoolSetupStep::DONE, $by);
    }

    public function skip(School $school, string $step, ?User $by = null): void
    {
        if (! in_array($step, self::SKIPPABLE, true)) {
            throw ValidationException::withMessages(['step' => 'This step is required and cannot be skipped.']);
        }

        $this->record($school, $step, SchoolSetupStep::SKIPPED, $by);
    }

    /** Re-open a finished or skipped step. */
    public function reopen(School $school, string $step): void
    {
        $this->assertStep($step);

        $this->tenant->runForSchool($school, function () use ($school, $step) {
            SchoolSetupStep::query()->where('school_id', $school->id)->where('step', $step)->delete();
            // A reopened step no longer counts as complete, even if the data still exists.
            SchoolSetupStep::query()->create([
                'school_id' => $school->id,
                'step' => $step,
                'status' => 'todo',
            ]);
        });

        $school->forceFill(['setup_completed_at' => null])->save();
    }

    public function complete(School $school): bool
    {
        if (! $this->progress($school)['complete']) {
            return false;
        }

        if ($school->setup_completed_at === null) {
            $school->forceFill(['setup_completed_at' => now()])->save();
        }

        return true;
    }

    // ---- Step 4: academic structure -------------------------------------------------------------

    public function addLevel(School $school, string $name, ?string $code): Level
    {
        return $this->tenant->runForSchool($school, function () use ($school, $name, $code) {
            $name = trim($name);

            if (Level::query()->where('school_id', $school->id)->whereRaw('lower(name) = ?', [mb_strtolower($name)])->exists()) {
                throw ValidationException::withMessages(['name' => 'This level already exists.']);
            }

            $order = (int) Level::query()->where('school_id', $school->id)->max('sort_order');

            return Level::query()->create([
                'school_id' => $school->id,
                'name' => $name,
                'code' => $code ? trim($code) : null,
                'sort_order' => $order + 1,
            ]);
        });
    }

    public function removeLevel(School $school, int $levelId): void
    {
        $this->tenant->runForSchool($school, function () use ($school, $levelId) {
            $level = Level::query()->where('school_id', $school->id)->findOrFail($levelId);

            if (SchoolClass::query()->where('school_id', $school->id)->where('level_id', $level->id)->exists()) {
                throw ValidationException::withMessages(['level' => 'Remove the classes in this level first.']);
            }

            $level->delete();
        });
    }

    // ---- Step 5: academic year and terms -------------------------------------------------------

    /**
     * @param  array{name: string, starts_on: string, ends_on: string, terms: array<int, array{id?: int|null, name: string, starts_on: string, ends_on: string}>}  $data
     */
    public function saveAcademicYear(School $school, array $data): AcademicYear
    {
        $terms = array_values($data['terms'] ?? []);

        if ($terms === []) {
            throw ValidationException::withMessages(['terms' => 'Add at least one term.']);
        }

        $yearStart = strtotime($data['starts_on']);
        $yearEnd = strtotime($data['ends_on']);

        if ($yearEnd <= $yearStart) {
            throw ValidationException::withMessages(['ends_on' => 'The academic year must end after it starts.']);
        }

        $previousEnd = null;
        foreach ($terms as $i => $term) {
            $start = strtotime($term['starts_on']);
            $end = strtotime($term['ends_on']);

            if ($end <= $start) {
                throw ValidationException::withMessages(["terms.$i.ends_on" => 'A term must end after it starts.']);
            }
            if ($start < $yearStart || $end > $yearEnd) {
                throw ValidationException::withMessages(["terms.$i.starts_on" => 'Each term must fall inside the academic year.']);
            }
            if ($previousEnd !== null && $start <= $previousEnd) {
                throw ValidationException::withMessages(["terms.$i.starts_on" => 'Terms must be in order and must not overlap.']);
            }
            $previousEnd = $end;
        }

        return $this->tenant->runForSchool($school, function () use ($school, $data, $terms) {
            return DB::transaction(function () use ($school, $data, $terms) {
                $year = AcademicYear::query()->where('school_id', $school->id)->where('is_current', true)->first();

                $clash = AcademicYear::query()->where('school_id', $school->id)
                    ->where('name', $data['name'])
                    ->when($year, fn ($q) => $q->where('id', '!=', $year->id))
                    ->exists();

                if ($clash) {
                    throw ValidationException::withMessages(['name' => 'An academic year with this name already exists.']);
                }

                if ($year) {
                    $year->update(['name' => $data['name'], 'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on']]);
                } else {
                    $year = AcademicYear::query()->create([
                        'school_id' => $school->id,
                        'name' => $data['name'],
                        'starts_on' => $data['starts_on'],
                        'ends_on' => $data['ends_on'],
                        'is_current' => true,
                    ]);
                }

                $keep = [];
                foreach ($terms as $row) {
                    $term = ! empty($row['id'])
                        ? Term::query()->where('school_id', $school->id)->where('academic_year_id', $year->id)->find($row['id'])
                        : null;

                    $payload = ['name' => $row['name'], 'starts_on' => $row['starts_on'], 'ends_on' => $row['ends_on']];

                    if ($term) {
                        $term->update($payload);
                    } else {
                        $term = Term::query()->create($payload + [
                            'school_id' => $school->id,
                            'academic_year_id' => $year->id,
                            'is_current' => false,
                        ]);
                    }

                    $keep[] = $term->id;
                }

                // Terms left out of the submitted list are removed only when nothing depends on them.
                Term::query()->where('school_id', $school->id)
                    ->where('academic_year_id', $year->id)
                    ->whereNotIn('id', $keep)
                    ->get()
                    ->each(function (Term $stale) {
                        try {
                            $stale->delete();
                        } catch (\Illuminate\Database\QueryException) {
                            throw ValidationException::withMessages(['terms' => 'A term that is already in use cannot be removed.']);
                        }
                    });

                // Current term = the one that contains today, otherwise the first upcoming/last one.
                $today = now()->toDateString();
                $ordered = Term::query()->where('school_id', $school->id)->where('academic_year_id', $year->id)->orderBy('starts_on')->get();
                $current = $ordered->first(fn (Term $t) => $t->starts_on->toDateString() <= $today && $t->ends_on->toDateString() >= $today)
                    ?? $ordered->first(fn (Term $t) => $t->starts_on->toDateString() > $today)
                    ?? $ordered->last();

                Term::query()->where('school_id', $school->id)->where('academic_year_id', $year->id)->update(['is_current' => false]);
                $current?->update(['is_current' => true]);

                return $year->refresh();
            });
        });
    }

    // ---- Step 6: departments --------------------------------------------------------------------

    public function addDepartment(School $school, string $name, ?string $code): Department
    {
        return $this->tenant->runForSchool($school, function () use ($school, $name, $code) {
            $name = trim($name);

            if (Department::query()->where('school_id', $school->id)->whereRaw('lower(name) = ?', [mb_strtolower($name)])->exists()) {
                throw ValidationException::withMessages(['name' => 'This department already exists.']);
            }

            return Department::query()->create(['school_id' => $school->id, 'name' => $name, 'code' => $code ? trim($code) : null]);
        });
    }

    public function removeDepartment(School $school, int $id): void
    {
        $this->tenant->runForSchool($school, fn () => Department::query()->where('school_id', $school->id)->findOrFail($id)->delete());
    }

    // ---- Step 7: classes and streams ------------------------------------------------------------

    public function addClass(School $school, int $levelId, string $name, ?string $stream, ?int $capacity): SchoolClass
    {
        return $this->tenant->runForSchool($school, function () use ($school, $levelId, $name, $stream, $capacity) {
            $level = Level::query()->where('school_id', $school->id)->find($levelId);

            if (! $level) {
                throw ValidationException::withMessages(['level_id' => 'Choose a level from this school.']);
            }

            $name = trim($name);
            $stream = $stream ? trim($stream) : null;

            $exists = SchoolClass::query()->where('school_id', $school->id)
                ->where('level_id', $level->id)
                ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
                ->when($stream, fn ($q) => $q->whereRaw('lower(stream) = ?', [mb_strtolower($stream)]), fn ($q) => $q->whereNull('stream'))
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages(['name' => 'This class already exists in the level.']);
            }

            return SchoolClass::query()->create([
                'school_id' => $school->id,
                'level_id' => $level->id,
                'name' => $name,
                'stream' => $stream,
                'capacity' => $capacity,
            ]);
        });
    }

    public function removeClass(School $school, int $id): void
    {
        $this->tenant->runForSchool($school, function () use ($school, $id) {
            $class = SchoolClass::query()->where('school_id', $school->id)->findOrFail($id);

            if (Student::query()->where('school_id', $school->id)->where('school_class_id', $class->id)->exists()
                || TimetableSlot::query()->where('school_id', $school->id)->where('school_class_id', $class->id)->exists()) {
                throw ValidationException::withMessages(['class' => 'This class already has students or timetable entries and cannot be removed.']);
            }

            $class->delete();
        });
    }

    // ---- Step 8: subjects -----------------------------------------------------------------------

    public function addSubject(School $school, string $name, ?string $code): Subject
    {
        return $this->tenant->runForSchool($school, function () use ($school, $name, $code) {
            $name = trim($name);
            $code = $code ? strtoupper(trim($code)) : null;

            if (Subject::query()->where('school_id', $school->id)->whereRaw('lower(name) = ?', [mb_strtolower($name)])->exists()) {
                throw ValidationException::withMessages(['name' => 'This subject already exists.']);
            }
            if ($code && Subject::query()->where('school_id', $school->id)->where('code', $code)->exists()) {
                throw ValidationException::withMessages(['code' => 'This subject code is already used.']);
            }

            return Subject::query()->create(['school_id' => $school->id, 'name' => $name, 'code' => $code]);
        });
    }

    public function removeSubject(School $school, int $id): void
    {
        $this->tenant->runForSchool($school, function () use ($school, $id) {
            $subject = Subject::query()->where('school_id', $school->id)->findOrFail($id);

            if (TimetableSlot::query()->where('school_id', $school->id)->where('subject_id', $subject->id)->exists()) {
                throw ValidationException::withMessages(['subject' => 'This subject is used in the timetable and cannot be removed.']);
            }

            // Phase 5: a subject that carries results or is allocated to a class is part of the school's records.
            if (\App\Models\Grade::query()->where('subject_id', $subject->id)->exists()
                || \App\Models\ClassSubject::query()->where('subject_id', $subject->id)->exists()) {
                throw ValidationException::withMessages(['subject' => 'This subject is in use (timetable, class allocation or results) and cannot be removed.']);
            }

            $subject->delete();
        });
    }

    // ---- Step 11: parent access ----------------------------------------------------------------

    public function setParentAccess(School $school, bool $enabled): void
    {
        $settings = (array) $school->settings;
        $settings['parent_access'] = ['enabled' => $enabled];
        $school->forceFill(['settings' => $settings])->save();
    }

    // ---- internals ------------------------------------------------------------------------------

    /** @return array<string, int> */
    private function counts(School $school): array
    {
        return [
            'structure' => Level::query()->where('school_id', $school->id)->count(),
            'departments' => Department::query()->where('school_id', $school->id)->count(),
            'classes' => SchoolClass::query()->where('school_id', $school->id)->count(),
            'subjects' => Subject::query()->where('school_id', $school->id)->count(),
            // The founding administrator is not a "staff" record, so this is real invited / added staff only.
            'staff' => Staff::query()->where('school_id', $school->id)->count()
                + StaffInvitation::query()->where('school_id', $school->id)->whereNull('revoked_at')->count(),
            'students' => Student::query()->where('school_id', $school->id)->count(),
        ];
    }

    /** @param  array<string, int>  $counts */
    private function satisfiedByData(School $school, string $step, array $counts): bool
    {
        return in_array($step, self::DATA_DRIVEN, true) && ($counts[$step] ?? 0) > 0;
    }

    private function record(School $school, string $step, string $status, ?User $by): void
    {
        $this->assertStep($step);

        $this->tenant->runForSchool($school, function () use ($school, $step, $status, $by) {
            SchoolSetupStep::query()->updateOrCreate(
                ['school_id' => $school->id, 'step' => $step],
                ['status' => $status, 'completed_by' => $by?->id, 'completed_at' => now()],
            );
        });
    }

    private function assertStep(string $step): void
    {
        if (! $this->isStep($step)) {
            throw ValidationException::withMessages(['step' => 'Unknown setup step.']);
        }
    }
}
