<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$user = \App\Models\User::where('email', 'logistics_admin@test.com')->first();
if (!$user) {
    $user = \App\Models\User::create([
        'last_name' => 'Admin',
        'first_name' => 'Logistics',
        'email' => 'logistics_admin@test.com',
        'password' => \Illuminate\Support\Facades\Hash::make('password123'),
        'role' => 'logistics_admin',
        'status' => 'active',
        'name' => 'Logistics Admin',
        'account_status' => 'active',
        'phone' => '09123456789',
        'birthday' => '1990-01-01',
        'sex' => 'male',
        'age' => 30,
    ]);
    echo 'Created logistics_admin user' . PHP_EOL;
} else {
    echo 'User already exists' . PHP_EOL;
}