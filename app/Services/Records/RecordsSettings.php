<?php

namespace App\Services\Records;

use App\Models\School;
use App\Support\TenantContext;

/**
 * Per-school Records Office settings, stored under `schools.settings['records']`:
 *  signatories  - up to 3 {name,title} printed on documents
 *  stamp_path   - optional official stamp image (private disk)
 *  reminders    - {enabled, interval_days, max}
 *  grades       - optional per-exam-type grade-symbol overrides
 */
class RecordsSettings
{
    public function school(): School
    {
        $id = app(TenantContext::class)->id();

        return School::query()->findOrFail($id);
    }

    public function all(?School $school = null): array
    {
        $school ??= $this->school();
        $s = (array) ($school->settings['records'] ?? []);

        return [
            'signatories' => array_values(array_filter((array) ($s['signatories'] ?? []), fn ($r) => filled($r['name'] ?? null))),
            'stamp_path' => $s['stamp_path'] ?? null,
            'reminders' => [
                'enabled' => (bool) ($s['reminders']['enabled'] ?? true),
                'interval_days' => max(1, (int) ($s['reminders']['interval_days'] ?? config('schoolos.external_exams.reminder_days'))),
                'max' => max(1, (int) ($s['reminders']['max'] ?? config('schoolos.external_exams.reminder_max'))),
            ],
            'grades' => [
                'gabece' => $this->grades($s, 'gabece'),
                'wassce' => $this->grades($s, 'wassce'),
            ],
        ];
    }

    /** @return list<string> */
    public function gradesFor(string $examType, ?School $school = null): array
    {
        return $this->all($school)['grades'][$examType] ?? [];
    }

    public function save(array $patch, ?School $school = null): void
    {
        $school ??= $this->school();
        $settings = (array) $school->settings;
        $settings['records'] = array_replace((array) ($settings['records'] ?? []), $patch);
        $school->forceFill(['settings' => $settings])->save();
    }

    private function grades(array $s, string $type): array
    {
        $own = $s['grades'][$type] ?? null;
        $list = is_array($own) && $own !== [] ? $own : config("schoolos.external_exams.grades.$type", []);

        return array_values(array_unique(array_map('strval', $list)));
    }
}
