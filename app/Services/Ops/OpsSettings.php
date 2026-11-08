<?php

namespace App\Services\Ops;

use App\Exceptions\AcademicRuleException;
use App\Models\School;
use App\Support\TenantContext;

/**
 * Per-school operating rules for the Phase 9 modules, stored under `schools.settings.ops.<group>`.
 * The defaults live in config('schoolos.ops'); only keys that exist there can be stored, and each is cast to the default's type.
 * Defaults are starting points chosen by the platform (many are "off" or "no limit"): they are NOT legal or ministry rules.
 */
class OpsSettings
{
    public function __construct(private readonly TenantContext $tenant) {}

    /** @return array<string,mixed> */
    public function group(string $group, ?School $school = null): array
    {
        $defaults = (array) config("schoolos.ops.$group", []);
        $school ??= $this->tenant->school();
        $saved = (array) data_get($school?->settings, "ops.$group", []);

        return array_replace($defaults, array_intersect_key($saved, $defaults));
    }

    public function get(string $group, string $key, mixed $fallback = null, ?School $school = null): mixed
    {
        return $this->group($group, $school)[$key] ?? $fallback;
    }

    /** @param  array<string,mixed>  $values */
    public function save(string $group, array $values): array
    {
        $school = $this->tenant->school();
        $defaults = (array) config("schoolos.ops.$group", []);
        if (! $school || $defaults === []) {
            throw AcademicRuleException::because('err.ops.settings');
        }

        $clean = [];
        foreach ($defaults as $key => $default) {
            if (! array_key_exists($key, $values)) {
                continue;
            }
            $v = $values[$key];
            if (is_bool($default)) {
                $clean[$key] = filter_var($v, FILTER_VALIDATE_BOOLEAN);
            } elseif (is_int($default)) {
                if ($v === null || $v === '' || ! is_numeric($v) || (int) $v < 0 || (int) $v > 1000000) {
                    throw AcademicRuleException::because('err.ops.settingNumber');
                }
                $clean[$key] = (int) $v;
            } elseif ($default === null) {
                // optional whole number: blank means "no limit"
                if ($v === null || $v === '') {
                    $clean[$key] = null;
                } elseif (! is_numeric($v) || (int) $v < 0 || (int) $v > 1000) {
                    throw AcademicRuleException::because('err.ops.settingNumber');
                } else {
                    $clean[$key] = (int) $v;
                }
            } else {
                $text = mb_substr(trim((string) $v), 0, 200);
                if ($key === 'invoice_prefix' && ! preg_match('/^[A-Za-z0-9]{1,10}$/', $text)) {
                    throw AcademicRuleException::because('err.fin.prefix');
                }
                $clean[$key] = $text;
            }
        }

        $settings = (array) $school->settings;
        $settings['ops'][$group] = array_replace($this->group($group, $school), $clean);
        $school->forceFill(['settings' => $settings])->save();

        return $settings['ops'][$group];
    }
}
