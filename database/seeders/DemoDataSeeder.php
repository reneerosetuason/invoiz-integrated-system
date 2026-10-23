<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Idempotent demo data for every non-framework table so each UI feature has
 * something real to render. Safe to run repeatedly:
 *
 *   php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('DemoDataSeeder skipped outside local/testing.');

            return;
        }

        $this->call([
            GeoDemoSeeder::class,
            LogisticsOperationsDemoSeeder::class,
            RiderDemoSeeder::class,
            DeliveryDemoSeeder::class,
            CenterApplicationDemoSeeder::class,
            LogisticsMessagingDemoSeeder::class,
            MarketplaceDemoSeeder::class,
        ]);
    }
}
