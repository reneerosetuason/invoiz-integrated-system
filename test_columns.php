<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$columns = \Illuminate\Support\Facades\Schema::getColumnListing('users');
echo 'Users columns: ' . implode(', ', $columns) . PHP_EOL;