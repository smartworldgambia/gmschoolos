<?php

namespace App\Services\Procurement;

use App\Models\User;

/** Which procurement tabs a person may open (the server decides; the page only draws what it is given). */
class ProcurementAccess
{
    /** @return list<string> */
    public static function tabs(User $user): array
    {
        $tabs = [];
        if ($user->canAny(['procurement.request', 'procurement.manage', 'procurement.approve'])) {
            $tabs[] = 'requests';
        }
        if ($user->canAny(['procurement.manage', 'procurement.approve'])) {
            array_push($tabs, 'orders', 'invoices');
        }
        if ($user->can('procurement.manage')) {
            $tabs[] = 'suppliers';
        }
        if ($user->canAny(['procurement.manage', 'procurement.approve'])) {
            $tabs[] = 'reports';
        }
        if ($user->can('procurement.approve')) {
            $tabs[] = 'setup';
        }

        return $tabs;
    }
}
