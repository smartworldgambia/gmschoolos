<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->boolean('primary_on_verify')->default(false)->after('is_custom');
            $table->timestamp('last_checked_at')->nullable()->after('verified_at');
            $table->string('verification_error', 255)->nullable()->after('last_checked_at');
            $table->string('verification_method', 20)->nullable()->after('verification_error');
            $table->index(['school_id', 'is_primary']);
            $table->index(['is_custom', 'verified_at']);
        });

        $this->backfillPlatformDomains();
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropIndex(['school_id', 'is_primary']);
            $table->dropIndex(['is_custom', 'verified_at']);
            $table->dropColumn(['primary_on_verify', 'last_checked_at', 'verification_error', 'verification_method']);
        });
    }

    /**
     * Every school must own a dedicated platform URL ({slug}.{base_domain}).
     * Schools created before domains were provisioned get one here; platform rows
     * are marked verified and become primary when the school has no primary host.
     */
    protected function backfillPlatformDomains(): void
    {
        $base = strtolower((string) config('schoolos.base_domain', 'localhost'));
        $now = now();

        DB::table('domains')
            ->where('is_custom', false)
            ->whereNull('verified_at')
            ->update(['verified_at' => $now]);

        DB::table('schools')->orderBy('id')->each(function ($school) use ($base, $now) {
            $host = strtolower($school->slug.'.'.$base);
            $hasPrimary = DB::table('domains')->where('school_id', $school->id)->where('is_primary', true)->exists();
            $existing = DB::table('domains')->where('host', $host)->first();

            if ($existing) {
                if ((int) $existing->school_id === (int) $school->id && ! $hasPrimary) {
                    DB::table('domains')->where('id', $existing->id)->update(['is_primary' => true]);
                }

                return;
            }

            DB::table('domains')->insert([
                'school_id' => $school->id,
                'host' => $host,
                'is_primary' => ! $hasPrimary,
                'is_custom' => false,
                'ssl_active' => false,
                'verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }
};
