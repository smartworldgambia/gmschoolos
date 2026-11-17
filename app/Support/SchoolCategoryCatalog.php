<?php

namespace App\Support;

use App\Models\School;

/**
 * Canonical academic level packs per school category.
 *
 * Exclusive types (nursery / primary / basic_secondary / senior_secondary) may only
 * use levels from their pack. Combined is the union of those four. International and
 * Other stay unrestricted so non-standard naming still works.
 */
class SchoolCategoryCatalog
{
    /** @var list<string> */
    public const EXCLUSIVE = [
        'nursery',
        'primary',
        'basic_secondary',
        'senior_secondary',
    ];

    /**
     * Categories granted at registration for a billing type.
     *
     * @return list<string> empty = unrestricted (international / other)
     */
    public static function defaultEnabledCategories(string $billingType): array
    {
        $type = SchoolTypes::normalise($billingType);

        return match ($type) {
            'nursery', 'primary', 'basic_secondary', 'senior_secondary' => [$type],
            'combined' => self::EXCLUSIVE,
            default => [],
        };
    }

    public static function isExclusiveType(string $type): bool
    {
        return in_array(SchoolTypes::normalise($type), self::EXCLUSIVE, true);
    }

    public static function isPackLocked(School $school): bool
    {
        $enabled = self::enabledCategories($school);

        return $enabled !== [];
    }

    /**
     * @return list<string>
     */
    public static function enabledCategories(School $school): array
    {
        $raw = $school->enabled_categories;

        if (is_array($raw)) {
            $cats = array_values(array_unique(array_filter(array_map(
                static fn ($v) => SchoolTypes::normalise((string) $v),
                $raw
            ), static fn (string $v) => in_array($v, self::EXCLUSIVE, true))));

            return $cats;
        }

        return self::defaultEnabledCategories((string) ($school->category ?? 'other'));
    }

    /**
     * Billing type after entitlements change: single pack, or combined when 2+.
     *
     * @param  list<string>  $enabled
     */
    public static function billingTypeForEnabled(array $enabled): string
    {
        $cats = array_values(array_unique(array_filter(array_map(
            static fn ($v) => SchoolTypes::normalise((string) $v),
            $enabled
        ), static fn (string $v) => in_array($v, self::EXCLUSIVE, true))));

        if (count($cats) === 0) {
            return 'other';
        }

        if (count($cats) === 1) {
            return $cats[0];
        }

        return 'combined';
    }

    /**
     * Academic levels for one exclusive category (name + code + landing metadata).
     *
     * @return list<array{name: string, code: string, description?: string, image?: string}>
     */
    public static function levelPack(string $type): array
    {
        $type = SchoolTypes::normalise($type);

        return match ($type) {
            'nursery' => [
                [
                    'name' => 'Baby Class',
                    'code' => 'baby',
                    'description' => 'Gentle care and sensory play for our youngest children.',
                    'image' => '/images/marketing/hero-primary.jpg',
                ],
                [
                    'name' => 'Toddler',
                    'code' => 'toddler',
                    'description' => 'Discovery, language, and social skills through guided play.',
                    'image' => '/images/marketing/hero-classroom.jpg',
                ],
                [
                    'name' => 'Reception',
                    'code' => 'reception',
                    'description' => 'School readiness with literacy, numeracy, and confidence.',
                    'image' => '/images/marketing/hero-students.jpg',
                ],
            ],
            'primary' => [
                [
                    'name' => 'Grade 1',
                    'code' => 'g1',
                    'description' => 'First steps in literacy, numeracy, and classroom routines.',
                    'image' => '/images/marketing/hero-primary.jpg',
                ],
                [
                    'name' => 'Grade 2',
                    'code' => 'g2',
                    'description' => 'Growing confidence in reading, writing, and problem-solving.',
                    'image' => '/images/marketing/hero-classroom.jpg',
                ],
                [
                    'name' => 'Grade 3',
                    'code' => 'g3',
                    'description' => 'Deeper skills across core subjects with guided discovery.',
                    'image' => '/images/marketing/hero-students.jpg',
                ],
                [
                    'name' => 'Grade 4',
                    'code' => 'g4',
                    'description' => 'Independent learning habits and stronger academic focus.',
                    'image' => '/images/marketing/hero-primary.jpg',
                ],
                [
                    'name' => 'Grade 5',
                    'code' => 'g5',
                    'description' => 'Preparation for upper primary challenges and leadership.',
                    'image' => '/images/marketing/hero-classroom.jpg',
                ],
                [
                    'name' => 'Grade 6',
                    'code' => 'g6',
                    'description' => 'Ready for secondary pathways with solid foundations.',
                    'image' => '/images/marketing/hero-students.jpg',
                ],
            ],
            'basic_secondary' => [
                [
                    'name' => 'Form 1',
                    'code' => 'f1',
                    'description' => 'A confident start to secondary learning and study habits.',
                    'image' => '/images/marketing/hero-classroom.jpg',
                ],
                [
                    'name' => 'Form 2',
                    'code' => 'f2',
                    'description' => 'Broader subject depth with co-curricular growth.',
                    'image' => '/images/marketing/hero-students.jpg',
                ],
                [
                    'name' => 'Form 3',
                    'code' => 'f3',
                    'description' => 'Exam readiness and pathways to senior secondary.',
                    'image' => '/images/marketing/hero-primary.jpg',
                ],
            ],
            'senior_secondary' => [
                [
                    'name' => 'Form 4',
                    'code' => 'f4',
                    'description' => 'Subject streams that open future academic pathways.',
                    'image' => '/images/marketing/hero-students.jpg',
                ],
                [
                    'name' => 'Form 5',
                    'code' => 'f5',
                    'description' => 'Focused exam preparation with mentoring support.',
                    'image' => '/images/marketing/hero-classroom.jpg',
                ],
                [
                    'name' => 'Form 6',
                    'code' => 'f6',
                    'description' => 'Advanced study preparing learners for tertiary life.',
                    'image' => '/images/marketing/hero-primary.jpg',
                ],
            ],
            default => [],
        };
    }

    /**
     * @param  list<string>  $categories
     * @return list<array{name: string, code: string, description?: string, image?: string}>
     */
    public static function levelsForCategories(array $categories): array
    {
        $levels = [];
        $seen = [];

        foreach ($categories as $category) {
            foreach (self::levelPack((string) $category) as $level) {
                $code = strtolower((string) $level['code']);
                if (isset($seen[$code])) {
                    continue;
                }
                $seen[$code] = true;
                $levels[] = $level;
            }
        }

        return $levels;
    }

    /**
     * Allowed level codes for the school, or null when unrestricted.
     *
     * @return list<string>|null
     */
    public static function allowedLevelCodes(School $school): ?array
    {
        $enabled = self::enabledCategories($school);

        if ($enabled === []) {
            return null;
        }

        return array_values(array_map(
            static fn (array $level) => strtolower((string) $level['code']),
            self::levelsForCategories($enabled)
        ));
    }

    /**
     * Allowed level names (lowercased) for matching when code is omitted.
     *
     * @return list<string>|null
     */
    public static function allowedLevelNames(School $school): ?array
    {
        $enabled = self::enabledCategories($school);

        if ($enabled === []) {
            return null;
        }

        return array_values(array_map(
            static fn (array $level) => mb_strtolower((string) $level['name']),
            self::levelsForCategories($enabled)
        ));
    }

    public static function isLevelAllowed(School $school, string $name, ?string $code = null): bool
    {
        $codes = self::allowedLevelCodes($school);

        if ($codes === null) {
            return true;
        }

        $code = $code !== null && $code !== '' ? strtolower(trim($code)) : null;

        if ($code !== null) {
            return in_array($code, $codes, true);
        }

        $names = self::allowedLevelNames($school) ?? [];

        return in_array(mb_strtolower(trim($name)), $names, true);
    }

    /**
     * Categories an exclusive school may still request to add.
     *
     * @return list<string>
     */
    public static function upgradeableCategories(School $school): array
    {
        $enabled = self::enabledCategories($school);

        if ($enabled === []) {
            return [];
        }

        return array_values(array_diff(self::EXCLUSIVE, $enabled));
    }
}
