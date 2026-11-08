<?php

namespace Database\Seeders;

use App\Support\Rbac;
use Illuminate\Database\Seeder;

/**
 * Roles/permissions plus the per-school-type annual price list.
 * (Name kept for compatibility; tiered "plans" no longer exist - pricing is one annual price per school type.)
 */
class RolesAndPlansSeeder extends Seeder
{
    public function run(): void
    {
        Rbac::sync();

        $this->call(SchoolTypePriceSeeder::class);
    }
}