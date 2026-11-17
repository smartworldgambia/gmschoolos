<?php

use App\Support\SchoolCategoryCatalog;
use App\Support\SchoolTypes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->json('enabled_categories')->nullable()->after('category');
        });

        DB::table('schools')->orderBy('id')->chunkById(100, function ($schools) {
            foreach ($schools as $school) {
                $enabled = SchoolCategoryCatalog::defaultEnabledCategories(
                    SchoolTypes::normalise((string) ($school->category ?? 'other'))
                );

                DB::table('schools')->where('id', $school->id)->update([
                    'enabled_categories' => json_encode(array_values($enabled)),
                ]);
            }
        });

        Schema::create('school_category_upgrade_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->json('current_categories');
            $table->json('requested_categories');
            $table->string('target_billing_type', 40);
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_category_upgrade_requests');

        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('enabled_categories');
        });
    }
};
