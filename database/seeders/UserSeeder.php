<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $buyers = [
            ['name' => 'Maria Santos', 'email' => 'maria@invoiz.test', 'phone' => '09171234567'],
            ['name' => 'Juan Dela Cruz', 'email' => 'juan@invoiz.test', 'phone' => '09181234567'],
            ['name' => 'Ana Reyes', 'email' => 'ana@invoiz.test', 'phone' => '09191234567'],
            ['name' => 'Pedro Garcia', 'email' => 'pedro@invoiz.test', 'phone' => '09201234567'],
            ['name' => 'Liza Mendoza', 'email' => 'liza@invoiz.test', 'phone' => '09211234567'],
        ];

        foreach ($buyers as $buyer) {
            User::updateOrCreate(
                ['email' => $buyer['email']],
                [
                    'name' => $buyer['name'],
                    'password' => Hash::make('password'),
                    'role' => 'buyer',
                    'account_status' => 'active',
                    'is_admin' => false,
                    'phone' => $buyer['phone'],
                ]
            );
        }
    }
}