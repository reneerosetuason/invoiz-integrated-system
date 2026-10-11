<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$u = \App\Models\User::where('email', 'admin@invoiz.test')->first();
$u->password = \Illuminate\Support\Facades\Hash::make('password123');
$u->save();
echo 'updated admin';