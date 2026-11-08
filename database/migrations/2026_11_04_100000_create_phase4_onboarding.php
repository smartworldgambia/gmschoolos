<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 - school registration and provisioning (additive only; nothing is dropped or rewritten).
 *
 *  - schools                     : official registration number, district, terms/privacy acceptance, setup completion
 *  - departments                 : school departments (setup wizard step)
 *  - school_setup_steps          : saved progress of the setup wizard (resume later)
 *  - school_registration_reviews : duplicate-institution flags awaiting platform review (trial-abuse control)
 *  - staff_invitations           : single-use, expiring invitations created from the setup wizard
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('registration_number', 60)->nullable()->after('name');
            $table->string('district', 100)->nullable()->after('region');
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('terms_version', 20)->nullable();
            $table->timestamp('setup_completed_at')->nullable();

            $table->index('registration_number');
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('code', 20)->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        Schema::create('school_setup_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('step', 40);
            $table->string('status', 12)->default('done'); // done | skipped
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'step']);
        });

        Schema::create('school_registration_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->json('reasons');                 // e.g. ["similar_name", "same_phone"]
            $table->json('matched_school_ids')->nullable();
            $table->string('status', 24)->default('pending'); // pending | cleared | confirmed_duplicate
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->unique('school_id');
        });

        Schema::create('staff_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('email', 190);
            $table->string('role', 60);
            $table->string('token_hash', 64)->unique(); // sha256 of the emailed token; the token itself is never stored
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_invitations');
        Schema::dropIfExists('school_registration_reviews');
        Schema::dropIfExists('school_setup_steps');
        Schema::dropIfExists('departments');

        Schema::table('schools', function (Blueprint $table) {
            $table->dropIndex(['registration_number']);
            $table->dropColumn(['registration_number', 'district', 'terms_accepted_at', 'terms_version', 'setup_completed_at']);
        });
    }
};
