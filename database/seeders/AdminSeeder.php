<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_SEED_EMAIL', 'cmiavenus@gmail.com');
        $password = env('ADMIN_SEED_PASSWORD', 'skyler091101');

        // Compatible with shared invoizdb schema (buyer/seller apps require
        // first_name, last_name, sex, phone, birthday, age as NOT NULL).
        DB::table('users')->updateOrInsert(['email' => $email], [
            'first_name' => 'Admin',
            'last_name' => 'Administrator',
            'name' => 'Administrator',
            'sex' => 'other',
            'password' => Hash::make($password),
            'phone' => '09110100000',
            'birthday' => '1990-01-01',
            'age' => 36,
            'approval_status' => 'approved',
            'role' => 'admin',
            'status' => 'active',
            'account_status' => 'active',
            'is_admin' => true,
            'email_verified_at' => now(),
            'updated_at' => now(),
            'created_at' => DB::raw('COALESCE(created_at, NOW())'),
        ]);
    }
}
