<?php

namespace App\Services\Accounting;

use App\Models\User;

/** Which AIS tabs the signed-in person may open. */
class AccountingAccess
{
    /** @return list<string> */
    public static function tabs(User $user): array
    {
        if (! $user->can('accounting.view')) {
            return [];
        }

        $tabs = ['overview', 'journal', 'ledger', 'trialBalance', 'income', 'balance', 'cashbook'];
        if ($user->can('accounting.manage')) {
            $tabs[] = 'setup';
        }

        return $tabs;
    }
}
