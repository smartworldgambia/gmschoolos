<?php

namespace App\Services\Inventory;

use App\Models\User;

class InventoryAccess
{
    /** @return list<string> */
    public static function tabs(User $user): array
    {
        return $user->can('inventory.manage') ? ['items', 'stock', 'assets', 'setup'] : [];
    }
}
