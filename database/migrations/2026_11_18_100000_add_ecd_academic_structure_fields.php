<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('levels', function (Blueprint $table) {
            $table->unsignedSmallInteger('age_min_months')->nullable()->after('grade_number');
            $table->unsignedSmallInteger('age_max_months')->nullable()->after('age_min_months');
            $table->text('description')->nullable()->after('age_max_months');
            $table->boolean('is_active')->default(true)->after('description');
            $table->string('teaching_arrangement', 40)->nullable()->after('teaching_model');
        });

        Schema::table('school_classes', function (Blueprint $table) {
            $table->string('teaching_arrangement', 40)->nullable()->after('capacity');
            $table->json('learning_centres')->nullable()->after('teaching_arrangement');
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->string('domain_kind', 40)->nullable()->after('is_specialist');
        });

        Schema::create('class_educators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 20); // lead | assistant | specialist
            $table->timestamps();

            $table->unique(['school_class_id', 'user_id', 'role']);
            $table->index(['school_id', 'school_class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_educators');

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('domain_kind');
        });

        Schema::table('school_classes', function (Blueprint $table) {
            $table->dropColumn(['teaching_arrangement', 'learning_centres']);
        });

        Schema::table('levels', function (Blueprint $table) {
            $table->dropColumn([
                'age_min_months',
                'age_max_months',
                'description',
                'is_active',
                'teaching_arrangement',
            ]);
        });
    }
};
