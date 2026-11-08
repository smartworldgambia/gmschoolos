<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('isolation_tier')->default('shared')->after('status');
            // shared | premium_isolated | dedicated_db
            $table->string('db_connection')->nullable()->after('isolation_tier');
            $table->string('partition_key')->nullable()->after('db_connection');
            $table->timestamp('isolation_reviewed_at')->nullable()->after('partition_key');
        });

        Schema::create('tenant_database_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('connection_name')->unique();
            $table->string('driver')->default('mysql');
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(3306);
            $table->string('database');
            $table->string('username');
            $table->text('password_encrypted')->nullable();
            $table->string('status')->default('planned'); // planned, provisioning, active, retired
            $table->json('notes')->nullable();
            $table->timestamps();
            $table->unique('school_id');
        });

        Schema::create('workload_models', function (Blueprint $table) {
            $table->id();
            $table->string('profile'); // A, B, C
            $table->string('label');
            $table->unsignedInteger('assumed_active_users');
            $table->unsignedInteger('assumed_schools');
            $table->decimal('requests_per_user_per_min', 8, 2)->default(0.5);
            $table->decimal('estimated_rps', 12, 2);
            $table->unsignedInteger('estimated_db_connections');
            $table->unsignedInteger('recommended_app_replicas');
            $table->unsignedInteger('recommended_workers');
            $table->json('assumptions')->nullable();
            $table->json('cost_notes')->nullable();
            $table->boolean('is_certified')->default(false);
            $table->timestamps();
            $table->unique('profile');
        });

        Schema::create('capacity_certification_runs', function (Blueprint $table) {
            $table->id();
            $table->string('gate'); // gate1, gate2, gate3, gate4
            $table->string('profile'); // A, B, C
            $table->string('status'); // passed, failed, incomplete, not_run
            $table->string('environment')->default('staging');
            $table->unsignedInteger('vus')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->decimal('p95_ms', 10, 2)->nullable();
            $table->decimal('error_rate_pct', 8, 4)->nullable();
            $table->decimal('rps', 12, 2)->nullable();
            $table->json('thresholds')->nullable();
            $table->json('results')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ran_at')->nullable();
            $table->timestamps();
            $table->index(['gate', 'profile', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capacity_certification_runs');
        Schema::dropIfExists('workload_models');
        Schema::dropIfExists('tenant_database_connections');

        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn([
                'isolation_tier', 'db_connection', 'partition_key', 'isolation_reviewed_at',
            ]);
        });
    }
};
