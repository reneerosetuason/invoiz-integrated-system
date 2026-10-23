<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('DatabaseSeeder is local/testing only. Provision production accounts explicitly.');

            return;
        }

        $this->call([
            AdminSeeder::class,
            LogisticsUserSeeder::class,
            CategorySeeder::class,
            UserSeeder::class,
            SellerSeeder::class,
            ProductSeeder::class,
            OrderSeeder::class,
            ComplaintSeeder::class,
            AnnouncementSeeder::class,
            SellerDataSeeder::class,
            PaymentSeeder::class,
            SettingsSeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
