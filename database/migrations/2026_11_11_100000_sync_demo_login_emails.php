<?php

use App\Services\Demo\DemoLoginProvisioner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Production deploys run migrations, not seeders. Demo emails were renamed in code to
 * @gambiaschoolos.com while live rows could still be @schoolos.test / @gmschoolos.edu.gm,
 * so listed credentials failed with "do not match our records".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        if (! Schema::hasTable('users')) {
            return;
        }

        app(DemoLoginProvisioner::class)->sync();
    }

    public function down(): void
    {
        // Data repair; previous demo addresses are not restored.
    }
};
