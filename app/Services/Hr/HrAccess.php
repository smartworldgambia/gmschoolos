<?php

namespace App\Services\Hr;

use App\Models\Staff;
use App\Models\User;

/** What a signed-in person may see and open in the HR area, and which staff record (if any) is theirs. */
class HrAccess
{
    /** Tab keys for the HR sub-navigation, in display order. Self-service tabs are open to every staff member. */
    public static function tabs(User $user): array
    {
        $tabs = [
            'overview' => 'hr.view',
            'staff' => 'hr.view',
            'leave' => 'hr.view',
            'payroll' => 'payroll.manage|payroll.approve',
            'setup' => 'hr.manage',
            'myLeave' => 'staff.self',
            'myPayslips' => 'staff.self',
        ];

        return array_keys(array_filter($tabs, fn (string $p) => $user->canAny(explode('|', $p))));
    }

    /** The HR record linked to this login (staff are linked by HR, never guessed from an e-mail address). */
    public static function ownStaff(User $user): ?Staff
    {
        return Staff::query()->where('user_id', $user->id)->first();
    }

    /** Salary, pay and bank details are for payroll roles only. */
    public static function seesPay(User $user): bool
    {
        return $user->canAny(['payroll.manage', 'payroll.approve']);
    }
}
