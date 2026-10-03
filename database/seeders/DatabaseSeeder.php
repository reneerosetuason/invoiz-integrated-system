<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
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
        ]);
    }
}
