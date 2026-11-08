<?php

namespace App\Services\Reporting;

use App\Models\AcademicReportTemplate;
use App\Models\School;
use App\Support\TenantContext;

/**
 * Per-school report-card options stored under schools.settings['academic_reports'].
 * Term weights and the grading scale itself live with Examinations — this only stores
 * presentation and annual-weighting choices, never a second grading scheme.
 */
class AcademicReportSettings
{
    public function school(): School
    {
        $id = app(TenantContext::class)->id();

        return School::query()->findOrFail($id);
    }

    public function all(?School $school = null): array
    {
        $school ??= $this->school();
        $s = (array) ($school->settings['academic_reports'] ?? []);
        $defaults = (array) config('schoolos.academic_reports', []);

        $weights = $s['term_weights'] ?? ($defaults['term_weights'] ?? []);
        $clean = [];
        foreach ((array) $weights as $seq => $w) {
            $n = (int) $seq;
            if ($n >= 1 && $n <= 3 && is_numeric($w)) {
                $clean[(string) $n] = round((float) $w, 2);
            }
        }

        return [
            'show_position' => (bool) ($s['show_position'] ?? $defaults['show_position'] ?? false),
            'show_photo' => (bool) ($s['show_photo'] ?? true),
            'show_dob' => (bool) ($s['show_dob'] ?? false),
            'term_weights' => $clean,
            'pass_letter' => (string) ($s['pass_letter'] ?? 'D'),
        ];
    }

    public function save(array $patch, ?School $school = null): void
    {
        $school ??= $this->school();
        $settings = (array) $school->settings;
        $settings['academic_reports'] = array_replace((array) ($settings['academic_reports'] ?? []), $patch);
        $school->forceFill(['settings' => $settings])->save();
    }

    public function ensureTemplates(?School $school = null): void
    {
        $school ??= $this->school();
        if (AcademicReportTemplate::query()->where('school_id', $school->id)->exists()) {
            return;
        }

        foreach ($this->defaultTemplates() as $row) {
            AcademicReportTemplate::query()->create($row + [
                'school_id' => $school->id,
                'is_default' => $row['program_band'] === 'primary',
            ]);
        }
    }

    public function templateForBand(string $band, ?School $school = null): ?AcademicReportTemplate
    {
        $this->ensureTemplates($school);
        $school ??= $this->school();

        return AcademicReportTemplate::query()->where('school_id', $school->id)->where('program_band', $band)->first()
            ?? AcademicReportTemplate::query()->where('school_id', $school->id)->where('is_default', true)->first()
            ?? AcademicReportTemplate::query()->where('school_id', $school->id)->first();
    }

    public static function bandForGrade(?int $gradeNumber): string
    {
        if ($gradeNumber === null || $gradeNumber < 1) {
            return 'nursery';
        }
        if ($gradeNumber <= 6) {
            return 'primary';
        }
        if ($gradeNumber <= 9) {
            return 'basic_secondary';
        }

        return 'senior_secondary';
    }

    /** @return list<array{key:string,name:string,program_band:string,config:array}> */
    public function defaultTemplates(): array
    {
        $base = [
            'show_logo' => true,
            'show_photo' => true,
            'show_scale' => true,
            'show_attendance' => true,
            'show_comments' => true,
            'show_signatures' => true,
            'show_stamp' => true,
            'show_position' => false,
            'footer' => 'This is a school-issued academic report. It is not an official WAEC result or certificate.',
        ];

        return [
            ['key' => 'nursery', 'name' => 'Nursery report', 'program_band' => 'nursery', 'config' => $base + ['title' => 'Nursery Progress Report', 'show_position' => false]],
            ['key' => 'primary', 'name' => 'Primary report card', 'program_band' => 'primary', 'config' => $base + ['title' => 'Primary Report Card']],
            ['key' => 'basic_secondary', 'name' => 'Basic cycle report card', 'program_band' => 'basic_secondary', 'config' => $base + ['title' => 'Basic Cycle Report Card']],
            ['key' => 'senior_secondary', 'name' => 'Senior secondary report card', 'program_band' => 'senior_secondary', 'config' => $base + ['title' => 'Senior Secondary Report Card']],
        ];
    }
}
