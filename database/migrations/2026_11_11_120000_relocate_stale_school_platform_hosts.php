<?php

use App\Models\School;
use App\Services\School\SchoolDomainService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Seeded / early-production rows used {slug}.localhost while the live school
 * site is {slug}.gambiaschoolos.com. Relocate those platform hosts so admin
 * dashboards show the URL parents actually open.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('schools') || ! Schema::hasTable('domains')) {
            return;
        }

        $domains = app(SchoolDomainService::class);

        School::query()->orderBy('id')->each(function (School $school) use ($domains) {
            $domains->assignPlatformDomain($school);
        });
    }

    public function down(): void
    {
        // Data repair; leftover localhost hosts are not restored.
    }
};
