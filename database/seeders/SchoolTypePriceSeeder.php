<?php

namespace Database\Seeders;

use App\Models\SchoolTypePrice;
use App\Support\SchoolTypes;
use Illuminate\Database\Seeder;

/**
 * Seeds DEVELOPMENT placeholder annual prices (GMD) ONLY for school types that have no price yet.
 *
 * These numbers are NOT business-approved prices. The Platform Administrator must set the real annual
 * price per school type (Platform > Pricing) before launch; nothing here overwrites an existing price.
 */
class SchoolTypePriceSeeder extends Seeder
{
    public const PLACEHOLDERS = [
        'nursery' => 12000,
        'primary' => 18000,
        'basic_secondary' => 24000,
        'senior_secondary' => 30000,
        'combined' => 36000,
        'international' => 60000,
        'other' => 20000,
    ];

    public function run(): void
    {
        foreach (SchoolTypes::ALL as $type) {
            if (SchoolTypePrice::query()->where('school_type', $type)->exists()) {
                continue;
            }

            SchoolTypePrice::query()->create([
                'school_type' => $type,
                'amount' => self::PLACEHOLDERS[$type],
                'currency' => 'GMD',
                'effective_from' => now()->startOfYear()->toDateString(),
                'status' => 'active',
                'description' => 'PLACEHOLDER (development) - confirm the approved annual price before launch.',
            ]);
        }
    }
}