<?php

namespace App\Services\Finance;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who may see which learner's money records. Staff with `fees.view` see the whole school; a learner sees only their own
 * records and a guardian only their linked children's. Everything is tenant-scoped by the models themselves.
 */
class FinanceAccess
{
    /** Learner ids the user is limited to, or null when they are not limited (school staff). */
    public static function limitedTo(User $user): ?array
    {
        if ($user->isLearnerPortal()) {
            return array_filter([$user->studentProfile?->id]);
        }

        if ($user->isGuardianPortal()) {
            return $user->guardianProfile?->students()->pluck('students.id')->all() ?? [];
        }

        return null;
    }

    public static function canSee(User $user, int $studentId): bool
    {
        if (! $user->can('fees.view') || $user->isBlocked()) {
            return false;
        }

        $limit = self::limitedTo($user);

        return $limit === null || in_array($studentId, array_map('intval', $limit), true);
    }

    public static function scope(Builder $query, User $user, string $column = 'student_id'): Builder
    {
        $limit = self::limitedTo($user);

        return $limit === null ? $query : $query->whereIn($column, $limit ?: [0]);
    }

    public static function isStaff(User $user): bool
    {
        return self::limitedTo($user) === null;
    }

    /** The finance sub-pages this user may open, as stable keys for the tab bar (empty for a family, who only have "my fees"). */
    public static function tabs(User $user): array
    {
        if (! self::isStaff($user)) {
            return [];
        }

        $tabs = ['overview' => 'fees.view'];
        $tabs += [
            'invoices' => 'fees.manage|payments.record|payments.verify|fees.approve',
            'payments' => 'payments.verify|payments.record',
            'adjustments' => 'fees.manage|fees.approve',
            'reconciliation' => 'payments.verify',
            'reports' => 'reports.view',
            'setup' => 'fees.manage',
        ];

        return array_keys(array_filter($tabs, fn (string $perm) => $user->canAny(explode('|', $perm))));
    }

    public static function invoicesFor(User $user): Builder
    {
        return self::scope(Invoice::query(), $user);
    }
}
