<?php

namespace Database\Seeders;

use App\Services\Marketing\MarketingContent;
use Illuminate\Database\Seeder;

class MarketingSeeder extends Seeder
{
    public function run(): void
    {
        // Demo landing endorsement for Bright Future (editable in Platform → Marketing).
        // Replace with an approved real quote before production launch.
        app(MarketingContent::class)->saveTestimonial([
            'quote' => 'Gambia SchoolOS brought attendance, fees and report cards into one place. Our teachers and parents finally work from the same picture every day.',
            'name' => 'Awa Ceesay',
            'role' => 'Headteacher',
            'school' => 'Bright Future School',
            'photo_url' => null,
            'rating' => 5,
            'published' => true,
        ]);
    }
}
