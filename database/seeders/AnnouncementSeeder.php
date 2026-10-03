<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Seeder;

class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('is_admin', true)->first();
        if (!$admin) return;

        Announcement::create([
            'title' => 'Welcome to Invoiz',
            'body' => 'We are excited to launch our baby and kids e-commerce platform. Sellers can now register and start listing their products.',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        Announcement::create([
            'title' => 'Platform Maintenance Notice',
            'body' => 'Scheduled maintenance on Sunday, 2:00 AM - 4:00 AM. Some features may be temporarily unavailable.',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
    }
}
