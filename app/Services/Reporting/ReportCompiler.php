<?php

namespace App\Services\Reporting;

use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\ClassSubject;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentMovement;
use App\Models\Term;
use App\Services\Records\RecordsSettings;
use App\Services\Records\TranscriptCompiler;
use App\Services\Results\ResultsService;
use Illuminate\Support\Collection;

/**
 * Builds the snapshot stored on an academic report. Uses published examination grades only.
 * Missing results are listed as incomplete — they are never treated as zero.
 */
class ReportCompiler
{
    public function __construct(
        protected ResultsService $results,
        protected AcademicReportSettings $settings,
        protected TranscriptCompiler $transcripts,
        protected RecordsSettings $records,
    ) {}

    public function compileTermly(Student $student, Term $term, array $extras = []): array
    {
        $student->loadMissing('schoolClass.level', 'schoolClass.classTeacher');
        $term->loadMissing('academicYear');
        $school = $student->school()->first();
        $cfg = $this->settings->all($school);
        $band = AcademicReportSettings::bandForGrade($student->schoolClass?->level?->grade_number);
        $template = $this->settings->templateForBand($band, $school);

        $subjects = $this->termSubjects($student, $term);
        $attendance = $this->attendance($student, $term->starts_on?->toDateString(), $term->ends_on?->toDateString());
        $totals = array_values(array_filter(array_column($subjects, 'total'), fn ($v) => $v !== null));
        $average = $totals ? round(array_sum($totals) / count($totals), 2) : null;
        $letter = $average !== null ? $this->results->letterFor($average) : ['letter' => null, 'remark' => null];
        $missing = array_values(array_filter($subjects, fn ($s) => ($s['status'] ?? '') === 'missing'));
        $position = ($cfg['show_position'] || ($template?->config['show_position'] ?? false))
            ? $this->classPosition($student, $term, $average)
            : null;

        return $this->base($student, $school, $template, $extras) + [
            'kind' => 'termly',
            'academic_year' => $term->academicYear?->name,
            'academic_year_id' => $term->academic_year_id,
            'term' => ['id' => $term->id, 'name' => $term->name, 'sequence' => $term->sequence],
            'class' => $this->classSnapshot($student->schoolClass),
            'subjects' => $subjects,
            'missing_subjects' => array_column($missing, 'name'),
            'incomplete' => $missing !== [],
            'summary' => [
                'subjects_count' => count($subjects),
                'recorded_count' => count($subjects) - count($missing),
                'total_marks' => $totals ? round(array_sum($totals), 2) : null,
                'average' => $average,
                'grade' => $letter['letter'],
                'remark' => $letter['remark'],
                'position' => $position['position'] ?? null,
                'out_of' => $position['out_of'] ?? null,
            ],
            'attendance' => $attendance,
            'scale' => $this->scaleRows(),
            'compiled_at' => now()->toIso8601String(),
        ];
    }

    public function compileAnnual(Student $student, AcademicYear $year, array $extras = []): array
    {
        $student->loadMissing('schoolClass.level', 'schoolClass.classTeacher');
        $school = $student->school()->first();
        $cfg = $this->settings->all($school);
        $band = AcademicReportSettings::bandForGrade($student->schoolClass?->level?->grade_number);
        $template = $this->settings->templateForBand($band, $school);
        $terms = Term::query()->where('academic_year_id', $year->id)->orderBy('sequence')->orderBy('starts_on')->get();
        $weights = $cfg['term_weights'];

        $perTerm = [];
        foreach ($terms as $term) {
            $perTerm[$term->id] = $this->publishedBySubject($student, $term->id);
        }

        $subjectIds = [];
        foreach ($perTerm as $map) {
            foreach ($map as $sid => $_) {
                $subjectIds[$sid] = true;
            }
        }
        foreach ($this->curriculumSubjectIds($student) as $sid) {
            $subjectIds[$sid] = true;
        }

        $subjects = [];
        $incomplete = false;
        $annualMarks = [];
        $names = \App\Models\Subject::query()->whereIn('id', array_keys($subjectIds))->pluck('name', 'id');

        foreach (array_keys($subjectIds) as $sid) {
            $termRows = [];
            $present = [];
            foreach ($terms as $term) {
                $g = $perTerm[$term->id][$sid] ?? null;
                $mark = $g && $g->total !== null ? (float) $g->total : null;
                $termRows[] = [
                    'term_id' => $term->id,
                    'term' => $term->name,
                    'sequence' => $term->sequence,
                    'total' => $mark,
                    'grade' => $g?->letter_grade,
                    'ca_score' => $g?->ca_score !== null ? (float) $g->ca_score : null,
                    'exam_score' => $g?->exam_score !== null ? (float) $g->exam_score : null,
                    'status' => $mark === null ? 'missing' : 'recorded',
                ];
                if ($mark !== null) {
                    $present[] = ['sequence' => $term->sequence, 'mark' => $mark];
                }
            }

            $allPresent = count($present) === $terms->count() && $terms->isNotEmpty();
            $average = $this->annualAverage($present, $terms, $weights, $allPresent);
            $letter = $average !== null ? $this->results->letterFor($average) : ['letter' => null, 'remark' => null];
            $rowIncomplete = ! $allPresent || $present === [];
            if ($rowIncomplete) {
                $incomplete = true;
            }
            if ($average !== null) {
                $annualMarks[] = $average;
            }

            $subjects[] = [
                'subject_id' => $sid,
                'name' => $names[$sid] ?? '-',
                'terms' => $termRows,
                'annual_average' => $average,
                'grade' => $letter['letter'],
                'remark' => $letter['remark'],
                'incomplete' => $rowIncomplete,
            ];
        }

        usort($subjects, fn ($a, $b) => strcmp($a['name'], $b['name']));

        $overall = $annualMarks ? round(array_sum($annualMarks) / count($annualMarks), 2) : null;
        $overallLetter = $overall !== null ? $this->results->letterFor($overall) : ['letter' => null, 'remark' => null];
        $attendance = $this->attendance($student, $year->starts_on?->toDateString(), $year->ends_on?->toDateString());

        return $this->base($student, $school, $template, $extras) + [
            'kind' => 'annual',
            'academic_year' => $year->name,
            'academic_year_id' => $year->id,
            'terms' => $terms->map(fn (Term $t) => ['id' => $t->id, 'name' => $t->name, 'sequence' => $t->sequence])->values()->all(),
            'term_weights' => $weights,
            'weighting' => $this->weightingLabel($terms, $weights),
            'class' => $this->classSnapshot($student->schoolClass),
            'subjects' => $subjects,
            'incomplete' => $incomplete || $subjects === [],
            'summary' => [
                'subjects_count' => count($subjects),
                'average' => $overall,
                'grade' => $overallLetter['letter'],
                'remark' => $overallLetter['remark'],
                'terms_expected' => $terms->count(),
            ],
            'attendance' => $attendance,
            'scale' => $this->scaleRows(),
            'compiled_at' => now()->toIso8601String(),
        ];
    }

    public function compileFinal(Student $student, AcademicYear $year, array $extras = []): array
    {
        $student->loadMissing('schoolClass.level');
        $gradeNo = $student->schoolClass?->level?->grade_number;
        $transcriptType = $gradeNo === 12 ? 'transcript_g12' : ($gradeNo === 9 ? 'transcript_g9' : null);
        $history = $transcriptType
            ? $this->transcripts->compile($student, $transcriptType)
            : $this->openHistory($student);

        $annual = $this->compileAnnual($student, $year, $extras);
        $movements = StudentMovement::query()->where('student_id', $student->id)->orderBy('effective_on')->orderBy('id')
            ->get()->map(fn (StudentMovement $m) => [
                'event' => $m->event,
                'from_class_id' => $m->from_class_id,
                'to_class_id' => $m->to_class_id,
                'reason' => $m->reason,
                'effective_on' => $m->effective_on?->toDateString(),
            ])->all();

        $standing = match ($student->status) {
            'graduated' => 'completed',
            'withdrawn', 'transferred_out' => $student->status,
            default => 'enrolled',
        };

        $missing = $history['missing'] ?? [];
        $incomplete = $missing !== [] || ($annual['incomplete'] ?? false);

        return $this->base($student, $student->school()->first(), $this->settings->templateForBand(
            AcademicReportSettings::bandForGrade($gradeNo),
            $student->school()->first()
        ), $extras) + [
            'kind' => 'final',
            'academic_year' => $year->name,
            'academic_year_id' => $year->id,
            'programme' => $student->schoolClass?->level?->name,
            'completion_status' => $standing,
            'history' => $history,
            'annual_summary' => $annual['summary'] ?? null,
            'promotion_history' => $movements,
            'incomplete' => $incomplete,
            'summary' => [
                'average' => $history['summary']['overall_average'] ?? ($annual['summary']['average'] ?? null),
                'grade' => $annual['summary']['grade'] ?? null,
                'terms_required' => $history['summary']['terms_required'] ?? null,
                'terms_recorded' => $history['summary']['terms_recorded'] ?? null,
            ],
            'scale' => $this->scaleRows(),
            'disclaimer' => 'This is a school-issued completion report. It is not an official WAEC result or certificate.',
            'compiled_at' => now()->toIso8601String(),
        ];
    }

    public function previewClass(SchoolClass $class, string $type, AcademicYear $year, ?Term $term): Collection
    {
        $students = Student::query()->where('school_class_id', $class->id)->where('status', 'active')
            ->orderBy('first_name')->orderBy('last_name')->get();

        return $students->map(function (Student $s) use ($type, $year, $term) {
            $payload = match ($type) {
                'annual' => $this->compileAnnual($s, $year),
                'final' => $this->compileFinal($s, $year),
                default => $term ? $this->compileTermly($s, $term) : ['incomplete' => true, 'summary' => [], 'missing_subjects' => []],
            };

            return [
                'id' => $s->id,
                'name' => $s->full_name,
                'student_number' => $s->student_number,
                'incomplete' => (bool) ($payload['incomplete'] ?? true),
                'average' => $payload['summary']['average'] ?? null,
                'grade' => $payload['summary']['grade'] ?? null,
                'missing' => $payload['missing_subjects'] ?? ($payload['history']['missing'] ?? []),
            ];
        });
    }

    /**
     * @param  list<array{sequence:int,mark:float}>  $present
     */
    protected function annualAverage(array $present, Collection $terms, array $weights, bool $allPresent): ?float
    {
        if ($present === []) {
            return null;
        }

        if ($allPresent && $this->weightsUsable($terms, $weights)) {
            $sum = 0.0;
            foreach ($present as $row) {
                $sum += $row['mark'] * ((float) $weights[(string) $row['sequence']] / 100);
            }

            return round($sum, 2);
        }

        $marks = array_column($present, 'mark');

        return round(array_sum($marks) / count($marks), 2);
    }

    protected function weightsUsable(Collection $terms, array $weights): bool
    {
        if ($terms->isEmpty() || $weights === []) {
            return false;
        }
        $sum = 0.0;
        foreach ($terms as $term) {
            $seq = (string) $term->sequence;
            if (! isset($weights[$seq])) {
                return false;
            }
            $sum += (float) $weights[$seq];
        }

        return abs($sum - 100.0) < 0.05;
    }

    protected function weightingLabel(Collection $terms, array $weights): string
    {
        return $this->weightsUsable($terms, $weights) ? 'weighted' : 'equal_available';
    }

    /**
     * Published exam grades for a learner/parent view. Missing subjects are omitted — families
     * only see results the school has released, not a staff incomplete checklist.
     */
    public function publishedTermResults(Student $student, Term $term): ?array
    {
        $term->loadMissing('academicYear');
        $map = $this->publishedBySubject($student, $term->id);
        if ($map === []) {
            return null;
        }

        $rows = [];
        foreach ($map as $sid => $g) {
            $rows[] = [
                'subject_id' => $sid,
                'name' => $g->subject?->name ?? '-',
                'ca_score' => $g->ca_score !== null ? (float) $g->ca_score : null,
                'exam_score' => $g->exam_score !== null ? (float) $g->exam_score : null,
                'total' => $g->total !== null ? (float) $g->total : null,
                'grade' => $g->letter_grade,
                'remark' => $g->remark,
            ];
        }
        usort($rows, fn ($a, $b) => strcmp($a['name'], $b['name']));
        $totals = array_values(array_filter(array_column($rows, 'total'), fn ($v) => $v !== null));
        $average = $totals ? round(array_sum($totals) / count($totals), 2) : null;
        $letter = $average !== null ? $this->results->letterFor($average) : ['letter' => null];

        return [
            'term' => $term->name,
            'academic_year' => $term->academicYear?->name,
            'subjects' => $rows,
            'average' => $average,
            'grade' => $letter['letter'],
        ];
    }

    /** @return array<int,Grade> subject_id => latest published grade */
    protected function publishedBySubject(Student $student, int $termId): array
    {
        $grades = Grade::query()->with('subject')
            ->where('student_id', $student->id)
            ->where('term_id', $termId)
            ->where('is_published', true)
            ->orderBy('examination_id')
            ->orderBy('id')
            ->get();

        $out = [];
        foreach ($grades as $g) {
            $out[$g->subject_id] = $g;
        }

        return $out;
    }

    protected function termSubjects(Student $student, Term $term): array
    {
        $published = $this->publishedBySubject($student, $term->id);
        $expected = $this->curriculumSubjectIds($student);
        if ($expected === []) {
            $expected = array_keys($published);
        }

        $names = \App\Models\Subject::query()->whereIn('id', array_unique(array_merge($expected, array_keys($published))))
            ->pluck('name', 'id');

        $rows = [];
        foreach ($expected as $sid) {
            $g = $published[$sid] ?? null;
            $letter = $g && $g->total !== null ? $this->results->letterFor((float) $g->total) : ['letter' => null, 'remark' => null];
            $rows[] = [
                'subject_id' => $sid,
                'name' => $names[$sid] ?? '-',
                'ca_score' => $g?->ca_score !== null ? (float) $g->ca_score : null,
                'exam_score' => $g?->exam_score !== null ? (float) $g->exam_score : null,
                'total' => $g?->total !== null ? (float) $g->total : null,
                'grade' => $g?->letter_grade ?? $letter['letter'],
                'remark' => $g?->remark ?? $letter['remark'],
                'teacher_remark' => $g?->remark,
                'status' => $g && $g->total !== null ? 'recorded' : 'missing',
            ];
        }
        usort($rows, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $rows;
    }

    /** @return list<int> */
    protected function curriculumSubjectIds(Student $student): array
    {
        if (! $student->school_class_id) {
            return [];
        }

        return ClassSubject::query()->where('school_class_id', $student->school_class_id)
            ->orderBy('id')->pluck('subject_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    protected function attendance(Student $student, ?string $from, ?string $to): array
    {
        $records = AttendanceRecord::query()->where('student_id', $student->id)
            ->when($from, fn ($q) => $q->whereDate('attended_on', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('attended_on', '<=', $to))
            ->get();

        $present = 0;
        $absent = 0;
        $excused = 0;
        foreach ($records->groupBy(fn ($r) => $r->attended_on?->toDateString()) as $day) {
            $statuses = $day->pluck('status');
            if ($statuses->contains('absent') && ! $statuses->contains('present') && ! $statuses->contains('late') && ! $statuses->contains('left_early')) {
                $absent++;
            } elseif ($statuses->contains('excused') && ! $statuses->contains('present') && ! $statuses->contains('late')) {
                $excused++;
            } else {
                $present++;
            }
        }
        $total = $present + $absent + $excused;

        return [
            'present' => $present,
            'absent' => $absent,
            'excused' => $excused,
            'total' => $total,
            'percentage' => $total > 0 ? round(100 * $present / $total, 1) : null,
        ];
    }

    protected function classPosition(Student $student, Term $term, ?float $average): ?array
    {
        if ($average === null || ! $student->school_class_id) {
            return null;
        }

        $peers = Student::query()->where('school_class_id', $student->school_class_id)->where('status', 'active')->pluck('id');
        $avgs = [];
        foreach ($peers as $id) {
            $totals = Grade::query()->where('student_id', $id)->where('term_id', $term->id)->where('is_published', true)
                ->whereNotNull('total')->pluck('total');
            if ($totals->isEmpty()) {
                continue;
            }
            $avgs[(int) $id] = round((float) $totals->avg(), 2);
        }
        if ($avgs === [] || ! isset($avgs[$student->id])) {
            return ['position' => null, 'out_of' => count($avgs)];
        }
        arsort($avgs);
        $pos = 0;
        $last = null;
        $i = 0;
        foreach ($avgs as $id => $avg) {
            $i++;
            if ($last === null || $avg < $last) {
                $pos = $i;
            }
            $last = $avg;
            if ($id === (int) $student->id) {
                return ['position' => $pos, 'out_of' => count($avgs)];
            }
        }

        return null;
    }

    protected function openHistory(Student $student): array
    {
        $grades = Grade::query()->with(['subject', 'term.academicYear', 'examination'])
            ->where('student_id', $student->id)->where('is_published', true)->whereNotNull('term_id')
            ->orderBy('id')->get();

        $terms = [];
        $years = [];
        $all = [];
        foreach ($grades->groupBy('term_id') as $set) {
            $term = $set->first()->term;
            $entries = [];
            $bySubject = [];
            foreach ($set as $g) {
                $bySubject[$g->subject_id] = $g;
            }
            foreach ($bySubject as $g) {
                $entries[] = [
                    'subject' => $g->subject?->name ?? '-',
                    'mark' => $g->total !== null ? (float) $g->total : null,
                    'grade' => $g->letter_grade,
                ];
            }
            usort($entries, fn ($a, $b) => strcmp($a['subject'], $b['subject']));
            $marks = array_values(array_filter(array_column($entries, 'mark'), fn ($m) => $m !== null));
            $avg = $marks ? round(array_sum($marks) / count($marks), 2) : null;
            $year = $term?->academicYear?->name;
            if ($year) {
                $years[$year] = true;
            }
            if ($avg !== null) {
                $all[] = $avg;
            }
            $terms[] = [
                'grade_number' => $student->schoolClass?->level?->grade_number,
                'term_sequence' => $term?->sequence,
                'label' => trim(($year ?? '').' '.($term?->name ?? '')),
                'academic_year' => $year,
                'source' => 'platform',
                'status' => $entries === [] ? 'missing' : 'recorded',
                'entries' => $entries,
                'average' => $avg,
                'subjects_count' => count($entries),
            ];
        }

        return [
            'kind' => 'history',
            'academic_years' => array_keys($years),
            'terms' => $terms,
            'missing' => [],
            'summary' => [
                'terms_required' => count($terms),
                'terms_recorded' => count($terms),
                'overall_average' => $all ? round(array_sum($all) / count($all), 2) : null,
            ],
        ];
    }

    protected function scaleRows(): array
    {
        return $this->results->scale()->bands->map(fn ($b) => [
            'letter' => $b->letter, 'min' => (float) $b->min_score, 'remark' => $b->remark,
        ])->values()->all();
    }

    protected function classSnapshot(?SchoolClass $class): array
    {
        return [
            'id' => $class?->id,
            'name' => $class?->name,
            'level' => $class?->level?->name,
            'teacher' => $class?->classTeacher?->name,
        ];
    }

    protected function base(Student $student, $school, $template, array $extras): array
    {
        $cfg = $this->settings->all($school);

        return [
            'school' => [
                'name' => $school?->name,
                'address' => $school?->address,
                'phone' => $school?->phone,
                'email' => $school?->email,
                'logo_path' => $school?->logo_path,
                'motto' => $school?->tagline,
            ],
            'student' => [
                'full_name' => $student->full_name,
                'student_number' => $student->student_number,
                'photo_path' => $cfg['show_photo'] ? $student->photo_path : null,
                'date_of_birth' => $cfg['show_dob'] ? $student->date_of_birth?->toDateString() : null,
                'gender' => $student->gender,
            ],
            'template' => [
                'id' => $template?->id,
                'key' => $template?->key,
                'name' => $template?->name,
                'program_band' => $template?->program_band,
                'config' => $template?->config ?? [],
            ],
            'comments' => [
                'class_teacher' => $extras['class_teacher_comment'] ?? null,
                'headteacher' => $extras['headteacher_comment'] ?? null,
            ],
            'behaviour' => $extras['behaviour'] ?? null,
            'participation' => $extras['participation'] ?? null,
            'promotion_status' => $extras['promotion_status'] ?? null,
            'promotion_decision' => $extras['promotion_decision'] ?? null,
        ];
    }
}
