<?php

namespace Database\Seeders;

use App\Services\Demo\DemoLoginProvisioner;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPlansSeeder::class,
            DemoSchoolSeeder::class,
            DemoRichDataSeeder::class,
            BrightFutureShowcaseSeeder::class,
            MarketingSeeder::class,
            LandingMediaSeeder::class,
        ]);

        app(DemoLoginProvisioner::class)->sync();
    }
}
