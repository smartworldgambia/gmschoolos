<?php

namespace App\Support;

use App\Models\School;
use Illuminate\Support\Facades\Schema;

/**
 * Canonical demo login addresses. Seeders, the provisioner, and login aliasing share this list
 * so a stale SCHOOLOS_BASE_DOMAIN (e.g. gmschoolos.edu.gm) cannot silently mint the wrong emails.
 */
final class DemoLoginCatalog
{
    public const PASSWORD = 'password';

    public const CANONICAL_ROOT = 'gambiaschoolos.com';

    /** @var list<string> */
    public const LEGACY_ROOTS = [
        'schoolos.test',
        'gmschoolos.edu.gm',
        'gambiaschoolos.com',
    ];

    /** Hosts that must never be used as the live demo mail domain. */
    private const REJECTED_ROOTS = [
        '',
        'localhost',
        '127.0.0.1',
        'schoolos.test',
        'gmschoolos.edu.gm',
    ];

    public static function mailHost(): string
    {
        foreach ([
            parse_url((string) config('app.url'), PHP_URL_HOST),
            config('schoolos.base_domain'),
        ] as $candidate) {
            $host = strtolower(preg_replace('/^www\./', '', trim((string) $candidate)) ?: '');
            if ($host !== '' && ! in_array($host, self::REJECTED_ROOTS, true)) {
                return $host;
            }
        }

        return self::CANONICAL_ROOT;
    }

    public static function platformEmail(string $localPart): string
    {
        return $localPart.'@'.self::mailHost();
    }

    public static function schoolMailHost(string $slug): string
    {
        return $slug.'.'.self::mailHost();
    }

    /** @return array<string, array{name: string, role: string}> */
    public static function platformDirectory(): array
    {
        return [
            'platform' => ['name' => 'Platform Admin', 'role' => 'platform_admin'],
            'finance' => ['name' => 'Platform Finance', 'role' => 'platform_finance'],
            'support' => ['name' => 'Platform Support', 'role' => 'platform_support'],
            'ops' => ['name' => 'Platform Operations', 'role' => 'platform_operations'],
            'audit' => ['name' => 'Platform Auditor', 'role' => 'platform_auditor'],
        ];
    }

    /**
     * @return array<string, array{local: string, name: string, role: string, phone: string, email: string}>
     */
    public static function schoolDirectory(string $slug, bool $includeExtras = true): array
    {
        $domain = self::schoolMailHost($slug);
        $rows = [
            'school_admin' => ['local' => 'admin', 'name' => 'School Administrator', 'role' => 'school_admin', 'phone' => '+220 3000101'],
            'headteacher' => ['local' => 'head', 'name' => 'Haddy Njie', 'role' => 'headteacher', 'phone' => '+220 3000102'],
            'deputy_headteacher' => ['local' => 'deputy', 'name' => 'Ousman Ceesay', 'role' => 'deputy_headteacher', 'phone' => '+220 3000103'],
            'academic_coordinator' => ['local' => 'academics', 'name' => 'Isatou Sowe', 'role' => 'academic_coordinator', 'phone' => '+220 3000104'],
            'head_of_department' => ['local' => 'hod', 'name' => 'Modou Bah', 'role' => 'head_of_department', 'phone' => '+220 3000105'],
            'teacher' => ['local' => 'teacher', 'name' => 'Fatou Jallow', 'role' => 'teacher', 'phone' => '+220 3000106'],
            'teacher_b' => ['local' => 'teacher2', 'name' => 'Musa Touray', 'role' => 'class_teacher', 'phone' => '+220 3000107'],
            'teacher_c' => ['local' => 'teacher3', 'name' => 'Awa Camara', 'role' => 'teacher', 'phone' => '+220 3000108'],
            'exam_officer' => ['local' => 'exam', 'name' => 'Kebba Jallow', 'role' => 'exam_officer', 'phone' => '+220 3000109'],
            'admissions_officer' => ['local' => 'admissions', 'name' => 'Satang Colley', 'role' => 'admissions_officer', 'phone' => '+220 3000110'],
            'finance_officer' => ['local' => 'finance', 'name' => 'Binta Sanyang', 'role' => 'finance_officer', 'phone' => '+220 3000111'],
            'accountant' => ['local' => 'accountant', 'name' => 'Lamin Darboe', 'role' => 'accountant', 'phone' => '+220 3000112'],
            'records_officer' => ['local' => 'records', 'name' => 'Mariama Bojang', 'role' => 'records_officer', 'phone' => '+220 3000113'],
            'librarian' => ['local' => 'library', 'name' => 'Ya Sohna', 'role' => 'librarian', 'phone' => '+220 3000114'],
            'hr_officer' => ['local' => 'hr', 'name' => 'Ebrima Manneh', 'role' => 'hr_officer', 'phone' => '+220 3000115'],
            'storekeeper' => ['local' => 'store', 'name' => 'Omar Kinteh', 'role' => 'storekeeper', 'phone' => '+220 3000116'],
            'transport_officer' => ['local' => 'transport', 'name' => 'Alhagie Sillah', 'role' => 'transport_officer', 'phone' => '+220 3000117'],
            'student' => ['local' => 'student', 'name' => 'Amadou Jallow', 'role' => 'student', 'phone' => '+220 3000118'],
            'parent' => ['local' => 'parent', 'name' => 'Parent Jallow', 'role' => 'parent', 'phone' => '+220 3000119'],
        ];

        if ($slug === 'brightfuture') {
            $rows['school_admin']['name'] = 'Mr. Lamin Jallow';
            if ($includeExtras) {
                $rows['student_lamin'] = ['local' => 'lamin', 'name' => 'Lamin Bah', 'role' => 'student', 'phone' => '+220 3000120'];
                $rows['student_omar'] = ['local' => 'omar', 'name' => 'Omar Sanyang', 'role' => 'student', 'phone' => '+220 3000121'];
            }
        }

        foreach ($rows as &$row) {
            $row['email'] = $row['local'].'@'.$domain;
        }
        unset($row);

        return $rows;
    }

    /** @return list<string> */
    public static function schoolLocals(): array
    {
        return array_values(array_unique(array_column(self::schoolDirectory('demo', true), 'local')));
    }

    public static function isDemoEmail(string $email): bool
    {
        $email = strtolower(trim($email));
        if (! str_contains($email, '@')) {
            return false;
        }

        [$local, $host] = explode('@', $email, 2);

        $platformHosts = array_values(array_unique(array_merge([self::mailHost()], self::LEGACY_ROOTS)));
        if (isset(self::platformDirectory()[$local]) && in_array($host, $platformHosts, true)) {
            return true;
        }

        if (! in_array($local, self::schoolLocals(), true)) {
            return false;
        }

        $slug = self::slugFromSchoolHost($host);

        return $slug !== null && in_array($slug, self::demoSchoolSlugs(), true);
    }

    /**
     * Other addresses the same demo person may still have in a database that was
     * seeded before the live domain was gambiaschoolos.com.
     *
     * @return list<string>
     */
    public static function aliasesFor(string $email): array
    {
        $email = strtolower(trim($email));
        if (! str_contains($email, '@')) {
            return [];
        }

        [$local, $host] = explode('@', $email, 2);
        $aliases = [];

        $platformHosts = array_values(array_unique(array_merge([self::mailHost()], self::LEGACY_ROOTS)));
        if (isset(self::platformDirectory()[$local]) && in_array($host, $platformHosts, true)) {
            foreach ($platformHosts as $candidate) {
                $aliases[] = $local.'@'.$candidate;
            }

            return array_values(array_unique(array_diff($aliases, [$email])));
        }

        $slug = self::slugFromSchoolHost($host);
        if ($slug && in_array($local, self::schoolLocals(), true)) {
            foreach (self::schoolHostsForSlug($slug) as $candidate) {
                $aliases[] = $local.'@'.$candidate;
            }
        }

        return array_values(array_unique(array_diff($aliases, [$email])));
    }

    /** @return list<string> */
    public static function schoolHostsForSlug(string $slug): array
    {
        $hosts = [$slug.'.'.self::mailHost(), $slug.'.test'];
        foreach (self::LEGACY_ROOTS as $root) {
            $hosts[] = $slug.'.'.$root;
        }

        return array_values(array_unique($hosts));
    }

    public static function slugFromSchoolHost(string $host): ?string
    {
        $host = strtolower(trim($host));
        if (preg_match('/^([a-z0-9-]+)\.test$/', $host, $matches) === 1) {
            return $matches[1];
        }

        foreach (array_merge([self::mailHost()], self::LEGACY_ROOTS) as $root) {
            $suffix = '.'.$root;
            if (str_ends_with($host, $suffix)) {
                $slug = substr($host, 0, -strlen($suffix));
                if ($slug !== '' && ! str_contains($slug, '.')) {
                    return $slug;
                }
            }
        }

        return null;
    }

    /** @return list<string> */
    public static function demoSchoolSlugs(): array
    {
        $slugs = ['brightfuture', 'smartworld'];

        if (Schema::hasTable('schools')) {
            $slugs = array_merge($slugs, School::query()->pluck('slug')->all());
        }

        return array_values(array_unique($slugs));
    }
}
