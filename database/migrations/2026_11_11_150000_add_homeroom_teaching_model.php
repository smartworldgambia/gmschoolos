<?php

use App\Models\AcademicReportTemplate;
use App\Models\Level;
use App\Models\Subject;
use App\Services\Academics\HomeroomService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Nursery/primary classrooms are homeroom-taught; IRK/CRK/PE stay specialist subjects. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('levels', function (Blueprint $table) {
            $table->string('teaching_model', 20)->nullable()->after('grade_number');
        });
        Schema::table('subjects', function (Blueprint $table) {
            $table->boolean('is_specialist')->default(false)->after('department_id');
        });

        foreach (Level::query()->withoutGlobalScopes()->get() as $level) {
            $level->forceFill([
                'teaching_model' => HomeroomService::defaultModel($level->grade_number, (string) $level->name, (string) $level->code),
            ])->saveQuietly();
        }
        foreach (Subject::query()->withoutGlobalScopes()->get() as $subject) {
            if (Subject::guessSpecialist((string) $subject->name, (string) $subject->code)) {
                $subject->forceFill(['is_specialist' => true])->saveQuietly();
            }
        }

        foreach (AcademicReportTemplate::query()->withoutGlobalScopes()
            ->whereIn('program_band', ['primary', 'basic_secondary', 'senior_secondary'])->get() as $template) {
            $config = (array) $template->config;
            $config['show_position'] = true;
            $template->forceFill(['config' => $config])->saveQuietly();
        }
    }

    public function down(): void
    {
        Schema::table('levels', function (Blueprint $table) {
            $table->dropColumn('teaching_model');
        });
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('is_specialist');
        });
    }
};
