<?php

namespace App\Services\SeniorSecondary;

use App\Exceptions\AcademicRuleException;

/**
 * Pure, school-configurable admission rules. NOTHING about any named school lives in code: every threshold,
 * required subject, combination, weight and grade-to-points mapping comes from the round's `rules` JSON.
 *
 * rules = {
 *   subjects:           [{code,name}]            subjects the applicant may declare a GABECE grade for
 *   grade_points:       {A1:1, B2:2, ...}        grade symbol -> points (required)
 *   direction:          lower_better|higher_better
 *   required_subjects:  [code]                   must be present and a "pass"
 *   pass_limit:         number|null              best-direction cut-off for a subject to count as a pass
 *   elective_count:     int|null                 how many best non-required subjects are added (null = all)
 *   weights:            {code: number}           multiplier applied to a subject's points (default 1)
 *   combinations:       [{name, subjects:[code], min:int}]   programme / subject combinations
 *   min_subjects:       int                      minimum number of graded subjects
 *   aggregate_threshold:number|null              eligible only if the aggregate meets this
 * }
 *
 * The result is a RECOMMENDATION. A human administrator reviews it and may override it with a reason.
 */
class RulesEngine
{
    /** @return array<string,mixed> */
    public function normalise(array $in): array
    {
        $points = [];
        foreach ((array) ($in['grade_points'] ?? []) as $grade => $pt) {
            $g = strtoupper(trim((string) $grade));
            if ($g === '' || ! is_numeric($pt)) {
                throw AcademicRuleException::because('err.ss.rulesGradePoints');
            }
            $points[$g] = (float) $pt;
        }
        if ($points === []) {
            throw AcademicRuleException::because('err.ss.rulesGradePoints');
        }

        $subjects = [];
        foreach ((array) ($in['subjects'] ?? []) as $s) {
            $code = strtoupper(trim((string) ($s['code'] ?? '')));
            $name = trim((string) ($s['name'] ?? ''));
            if ($code === '' || $name === '') {
                continue;
            }
            $subjects[$code] = ['code' => $code, 'name' => $name];
        }
        if ($subjects === []) {
            throw AcademicRuleException::because('err.ss.rulesSubjects');
        }
        $known = array_keys($subjects);

        $codes = fn (array $list) => array_values(array_unique(array_filter(array_map(fn ($c) => strtoupper(trim((string) $c)), $list))));
        $required = $codes((array) ($in['required_subjects'] ?? []));
        if (array_diff($required, $known) !== []) {
            throw AcademicRuleException::because('err.ss.rulesUnknownSubject');
        }

        $weights = [];
        foreach ((array) ($in['weights'] ?? []) as $code => $w) {
            $code = strtoupper(trim((string) $code));
            if ($code === '' || $w === '' || $w === null) {
                continue;
            }
            if (! in_array($code, $known, true) || ! is_numeric($w) || (float) $w <= 0 || (float) $w > 10) {
                throw AcademicRuleException::because('err.ss.rulesWeights');
            }
            $weights[$code] = (float) $w;
        }

        $combos = [];
        foreach ((array) ($in['combinations'] ?? []) as $c) {
            $name = trim((string) ($c['name'] ?? ''));
            $list = $codes((array) ($c['subjects'] ?? []));
            if ($name === '' && $list === []) {
                continue;
            }
            if ($name === '' || $list === [] || array_diff($list, $known) !== []) {
                throw AcademicRuleException::because('err.ss.rulesCombination');
            }
            $min = max(1, min(count($list), (int) ($c['min'] ?? count($list))));
            $combos[] = ['name' => $name, 'subjects' => $list, 'min' => $min];
        }

        $direction = ($in['direction'] ?? 'lower_better') === 'higher_better' ? 'higher_better' : 'lower_better';
        $num = fn ($v) => ($v === null || $v === '') ? null : (is_numeric($v) ? (float) $v : throw AcademicRuleException::because('err.ss.rulesNumber'));

        return [
            'subjects' => array_values($subjects),
            'grade_points' => $points,
            'direction' => $direction,
            'required_subjects' => $required,
            'pass_limit' => $num($in['pass_limit'] ?? null),
            'elective_count' => ($in['elective_count'] ?? '') === '' || ($in['elective_count'] ?? null) === null ? null : max(0, (int) $in['elective_count']),
            'weights' => $weights,
            'combinations' => $combos,
            'min_subjects' => max(0, (int) ($in['min_subjects'] ?? 0)),
            'aggregate_threshold' => $num($in['aggregate_threshold'] ?? null),
        ];
    }

    /**
     * @param  array<string,mixed>  $rules  normalised rules
     * @param  array<string,string>  $grades  subject code => grade symbol declared by the applicant
     * @return array{aggregate:?float,eligible:bool,reasons:list<string>,counted:list<array<string,mixed>>,combination:?string}
     */
    public function assess(array $rules, array $grades, ?string $programme = null): array
    {
        $lower = ($rules['direction'] ?? 'lower_better') === 'lower_better';
        $points = $rules['grade_points'];
        $weights = $rules['weights'] ?? [];
        $reasons = [];

        $scored = [];
        foreach ($grades as $code => $grade) {
            $code = strtoupper($code);
            $grade = strtoupper(trim($grade));
            if ($grade === '') {
                continue;
            }
            if (! array_key_exists($grade, $points)) {
                $reasons[] = 'unknownGrade:'.$code;

                continue;
            }
            $scored[$code] = ['code' => $code, 'grade' => $grade, 'points' => $points[$grade], 'weighted' => $points[$grade] * ($weights[$code] ?? 1)];
        }

        $passes = function (array $s) use ($rules, $lower) {
            $limit = $rules['pass_limit'] ?? null;
            if ($limit === null) {
                return true;
            }

            return $lower ? $s['points'] <= $limit : $s['points'] >= $limit;
        };

        if (count($scored) < (int) ($rules['min_subjects'] ?? 0)) {
            $reasons[] = 'minSubjects';
        }

        $counted = [];
        $sum = 0.0;
        foreach ($rules['required_subjects'] as $code) {
            if (! isset($scored[$code])) {
                $reasons[] = 'missingRequired:'.$code;

                continue;
            }
            if (! $passes($scored[$code])) {
                $reasons[] = 'failedRequired:'.$code;
            }
            $counted[] = $scored[$code] + ['role' => 'required'];
            $sum += $scored[$code]['weighted'];
        }

        $electives = array_values(array_filter($scored, fn ($s) => ! in_array($s['code'], $rules['required_subjects'], true)));
        usort($electives, fn ($a, $b) => $lower ? $a['weighted'] <=> $b['weighted'] : $b['weighted'] <=> $a['weighted']);
        $take = $rules['elective_count'];
        if ($take !== null && count($electives) < $take) {
            $reasons[] = 'notEnoughElectives';
        }
        foreach ($take === null ? $electives : array_slice($electives, 0, $take) as $s) {
            $counted[] = $s + ['role' => 'elective'];
            $sum += $s['weighted'];
        }

        // Subject combinations: when configured, one must be satisfied (the chosen programme if the applicant chose one).
        $matched = null;
        if (! empty($rules['combinations'])) {
            $pool = $programme
                ? array_values(array_filter($rules['combinations'], fn ($c) => $c['name'] === $programme))
                : $rules['combinations'];
            foreach ($pool as $combo) {
                $ok = count(array_filter($combo['subjects'], fn ($c) => isset($scored[$c]) && $passes($scored[$c])));
                if ($ok >= $combo['min']) {
                    $matched = $combo['name'];
                    break;
                }
            }
            if ($matched === null) {
                $reasons[] = 'combination';
            }
        }

        $aggregate = $counted === [] ? null : round($sum, 2);
        $threshold = $rules['aggregate_threshold'] ?? null;
        if ($threshold !== null) {
            if ($aggregate === null) {
                $reasons[] = 'noAggregate';
            } elseif ($lower ? $aggregate > $threshold : $aggregate < $threshold) {
                $reasons[] = 'threshold';
            }
        }
        if ($aggregate === null && ! in_array('noAggregate', $reasons, true)) {
            $reasons[] = 'noAggregate';
        }

        return [
            'aggregate' => $aggregate,
            'eligible' => $reasons === [],
            'reasons' => array_values(array_unique($reasons)),
            'counted' => $counted,
            'combination' => $matched,
        ];
    }
}
