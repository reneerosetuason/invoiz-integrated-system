<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$kernel = $app->make('Illuminate\Contracts\Http\Kernel');

// Test 1: Logistics landing page
$r1 = $kernel->handle(new Illuminate\Http\Request([
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => '/logistics',
    'headers' => ['Accept' => 'text/html']
]));
echo '1. /logistics: ' . $r1->getStatusCode() . PHP_EOL;

// Test 2: Logistics login GET
$r2 = $kernel->handle(new Illuminate\Http\Request([
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => '/logistics/login',
    'headers' => ['Accept' => 'text/html']
]));
echo '2. /logistics/login GET: ' . $r2->getStatusCode() . PHP_EOL;

// Test 3: Main landing page
$r3 = $kernel->handle(new Illuminate\Http\Request([
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => '/',
    'headers' => ['Accept' => 'text/html']
]));
echo '3. / (main landing): ' . $r3->getStatusCode() . PHP_EOL;

// Test 4: Shop page
$r4 = $kernel->handle(new Illuminate\Http\Request([
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => '/shop',
    'headers' => ['Accept' => 'text/html']
]));
echo '4. /shop: ' . $r4->getStatusCode() . PHP_EOL;

// Test 5: API logistics login
$r5 = $kernel->handle(new Illuminate\Http\Request([
    'REQUEST_METHOD' => 'POST',
    'REQUEST_URI' => '/api/logistics/login',
    'headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
    'content' => json_encode(['email' => 'admin@logistics.com', 'password' => 'password'])
]));
echo '5. /api/logistics/login: ' . $r5->getStatusCode() . PHP_EOL;