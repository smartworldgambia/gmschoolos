<?php

namespace App\Services\Records;

use App\Models\Grade;
use App\Models\Level;
use App\Models\Student;
use App\Models\StudentTermRecord;
use App\Services\Results\ResultsService;

/**
 * Builds the immutable snapshot behind a school transcript.
 *
 *  Grade 9  : exactly nine terms - Grade 7 T1-3, Grade 8 T1-3, Grade 9 T1-3.
 *  Grade 12 : exactly eight terms - Grade 10 T1-3, Grade 11 T1-3, Grade 12 T1-2.
 *             Grade 12 Term 3 is NOT included unless an authorised administrator approved an exception.
 *
 * Sources per term: PUBLISHED platform grades first; otherwise a manually recorded historical term
 * (`student_term_records`, for schools that adopted the platform part-way). A term with neither is reported as
 * missing - never invented. If a term holds several published examinations the most recent one per subject is shown.
 *
 * A school transcript is NOT an official WAEC result or certificate.
 */
class TranscriptCompiler
{
    public const TYPES = ['transcript_g9', 'transcript_g12'];

    public function __construct(protected ResultsService $results, protected RecordsSettings $settings) {}

    /** @return list<array{0:int,1:int}> */
    public static function requiredTerms(string $type, ?array $exception = null): array
    {
        $out = [];
        $grades = $type === 'transcript_g9' ? [7, 8, 9] : [10, 11, 12];
        foreach ($grades as $g) {
            $last = ($type === 'transcript_g12' && $g === 12) ? 2 : 3;
            for ($t = 1; $t <= $last; $t++) {
                $out[] = [$g, $t];
            }
        }
        if ($type === 'transcript_g12' && ! empty($exception['include_grade12_term3'])) {
            $out[] = [12, 3];
        }

        return $out;
    }

    public function compile(Student $student, string $type, ?array $exception = null): array
    {
        $student->loadMissing('schoolClass.level');
        $required = self::requiredTerms($type, $exception);
        $levels = Level::query()->get()->keyBy('id');
        $currentGradeNo = $student->schoolClass?->level?->grade_number;

        $published = Grade::query()->with(['subject', 'term.academicYear'])
            ->where('student_id', $student->id)->where('is_published', true)->whereNotNull('term_id')
            ->orderBy('examination_id')->orderBy('id')->get();

        // [grade_no][term_seq][subject_id] => latest published grade
        $platform = [];
        foreach ($published as $g) {
            $gradeNo = $g->level_id ? ($levels[$g->level_id]->grade_number ?? null) : $currentGradeNo;
            $seq = $g->term?->sequence;
            if (! $gradeNo || ! $seq) {
                continue;
            }
            $platform[$gradeNo][$seq][$g->subject_id] = $g;
        }

        $manual = StudentTermRecord::query()->where('student_id', $student->id)->get()
            ->keyBy(fn ($r) => $r->grade_number.'-'.$r->term_sequence);

        $bands = $this->results->scale()->bands;
        $describe = fn (?string $letter) => $letter ? ($bands->firstWhere('letter', $letter)?->remark) : null;

        $terms = [];
        $missing = [];
        $years = [];
        $all = [];
        foreach ($required as [$gradeNo, $seq]) {
            $label = sprintf('G%d-T%d', $gradeNo, $seq);
            $entries = [];
            $source = null;
            $year = null;

            if (! empty($platform[$gradeNo][$seq])) {
                $source = 'platform';
                foreach ($platform[$gradeNo][$seq] as $g) {
                    $year ??= $g->term?->academicYear?->name;
                    $entries[] = [
                        'subject' => $g->subject?->name ?? '-', 'mark' => $g->total !== null ? (float) $g->total : null,
                        'grade' => $g->letter_grade, 'description' => $describe($g->letter_grade),
                    ];
                }
            } elseif ($rec = $manual->get("$gradeNo-$seq")) {
                $source = 'manual';
                $year = $rec->academic_year_label;
                foreach ((array) $rec->entries as $e) {
                    $entries[] = [
                        'subject' => (string) ($e['subject'] ?? ''), 'mark' => isset($e['mark']) && $e['mark'] !== '' ? (float) $e['mark'] : null,
                        'grade' => $e['grade'] ?? null, 'description' => $describe($e['grade'] ?? null),
                    ];
                }
            }

            usort($entries, fn ($a, $b) => strcmp($a['subject'], $b['subject']));
            $marks = array_values(array_filter(array_column($entries, 'mark'), fn ($m) => $m !== null));
            $avg = $marks ? round(array_sum($marks) / count($marks), 2) : null;

            if ($entries === []) {
                $missing[] = $label;
            } else {
                $all = array_merge($all, $marks);
                if ($year) {
                    $years[$year] = true;
                }
            }

            $terms[] = [
                'grade_number' => $gradeNo, 'term_sequence' => $seq, 'label' => $label,
                'academic_year' => $year, 'source' => $source, 'status' => $entries === [] ? 'missing' : 'recorded',
                'entries' => $entries, 'average' => $avg, 'subjects_count' => count($entries),
            ];
        }

        $school = $student->school()->first();

        return [
            'kind' => $type,
            'school' => [
                'name' => $school->name, 'address' => $school->address, 'phone' => $school->phone,
                'email' => $school->email, 'logo_path' => $school->logo_path,
            ],
            'student' => [
                'full_name' => $student->full_name, 'student_number' => $student->student_number,
                'programme' => $student->schoolClass?->level?->name ?? ($type === 'transcript_g9' ? 'Grade 9' : 'Grade 12'),
                'photo_path' => $student->photo_path,
            ],
            'academic_years' => array_keys($years),
            'terms' => $terms,
            'missing' => $missing,
            'summary' => [
                'terms_required' => count($required), 'terms_recorded' => count($required) - count($missing),
                'overall_average' => $all ? round(array_sum($all) / count($all), 2) : null,
            ],
            'exception' => $exception,
            'compiled_at' => now()->toIso8601String(),
        ];
    }
}
