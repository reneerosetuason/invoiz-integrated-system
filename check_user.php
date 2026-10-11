<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$user = \App\Models\User::where('email', 'logistics_admin@test.com')->first();
if ($user) {
    echo 'User found: ' . $user->email . PHP_EOL;
    echo 'Role: ' . $user->role . PHP_EOL;
    echo 'Status: ' . $user->status . PHP_EOL;
    echo 'Password check: ' . (\Illuminate\Support\Facades\Hash::check('password123', $user->password) ? 'OK' : 'FAIL') . PHP_EOL;
} else {
    echo 'User not found' . PHP_EOL;
}