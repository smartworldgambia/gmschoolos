<?php

use App\Models\Level;
use App\Models\School;
use App\Models\Subject;
use App\Support\SchoolCategoryCatalog;
use App\Support\SchoolTypes;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $packByCode = collect(SchoolCategoryCatalog::levelPack('nursery'))
            ->keyBy(fn (array $level) => strtolower((string) $level['code']));

        School::query()
            ->orderBy('id')
            ->each(function (School $school) use ($packByCode) {
                $enabled = SchoolCategoryCatalog::enabledCategories($school);
                if (! in_array('nursery', $enabled, true)) {
                    return;
                }

                foreach ($packByCode as $code => $level) {
                    Level::query()
                        ->where('school_id', $school->id)
                        ->whereRaw('lower(code) = ?', [$code])
                        ->update([
                            'age_min_months' => $level['age_min_months'] ?? null,
                            'age_max_months' => $level['age_max_months'] ?? null,
                            'description' => $level['description'] ?? null,
                            'teaching_arrangement' => $level['teaching_arrangement'] ?? null,
                            'is_active' => true,
                        ]);
                }

                // Domain subjects only for nursery-category schools (not combined overlays).
                if (SchoolTypes::normalise((string) ($school->category ?? '')) !== 'nursery') {
                    return;
                }

                foreach (SchoolCategoryCatalog::nurseryLearningDomains() as $domain) {
                    Subject::query()->updateOrCreate(
                        [
                            'school_id' => $school->id,
                            'code' => $domain['code'],
                        ],
                        [
                            'name' => $domain['name'],
                            'is_specialist' => false,
                            'domain_kind' => 'learning_domain',
                        ]
                    );
                }
            });
    }

    public function down(): void
    {
        // Additive backfill; leave data in place.
    }
};
