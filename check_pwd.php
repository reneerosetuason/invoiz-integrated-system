<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$u = \App\Models\User::where('email', 'seller@invoiz.test')->first();
echo 'password: ' . $u->password . PHP_EOL;
echo 'check: ' . (\Illuminate\Support\Facades\Hash::check('password123', $u->password) ? 'true' : 'false') . PHP_EOL;