<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$col = \Illuminate\Support\Facades\DB::select('SHOW COLUMNS FROM users WHERE Field = "role"');
print_r($col);