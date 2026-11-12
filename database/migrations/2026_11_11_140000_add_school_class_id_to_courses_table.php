<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** An LMS course is one subject taught to one class (ICT in 10A is not ICT in Nursery A). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('school_class_id')->nullable()->after('subject_id')->constrained('school_classes')->nullOnDelete();
            $table->index(['school_id', 'subject_id', 'school_class_id']);
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex(['school_id', 'subject_id', 'school_class_id']);
            $table->dropConstrainedForeignId('school_class_id');
        });
    }
};
