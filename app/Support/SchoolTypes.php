<?php

namespace App\Support;

/**
 * The seven school types that drive annual subscription pricing.
 * Stored on schools.category (slug) and snapshotted on each subscription.
 */
class SchoolTypes
{
    public const ALL = [
        'nursery',
        'primary',
        'basic_secondary',
        'senior_secondary',
        'combined',
        'international',
        'other',
    ];

    /** Registration form values (display labels are translated client-side) => slug. */
    public const FORM_VALUES = [
        'Nursery' => 'nursery',
        'Primary' => 'primary',
        'Basic Secondary' => 'basic_secondary',
        'Senior Secondary' => 'senior_secondary',
        'Combined' => 'combined',
        'Mixed' => 'combined',
        'International' => 'international',
        'Other' => 'other',
        // legacy form values kept so old links keep working
        'Secondary' => 'basic_secondary',
        'Tertiary' => 'combined',
    ];

    /** Normalise any stored/submitted category to one of ALL (unknown => other). */
    public static function normalise(?string $value): string
    {
        $value = trim((string) $value);

        if (isset(self::FORM_VALUES[$value])) {
            return self::FORM_VALUES[$value];
        }

        $slug = strtolower(str_replace([' ', '-'], '_', $value));

        return in_array($slug, self::ALL, true) ? $slug : 'other';
    }

    public static function isValid(?string $value): bool
    {
        return in_array((string) $value, self::ALL, true);
    }

    /** Form values presented on the registration wizard (one per type). */
    public static function formOptions(): array
    {
        return ['Nursery', 'Primary', 'Basic Secondary', 'Senior Secondary', 'Combined', 'International', 'Other'];
    }
}