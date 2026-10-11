<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$request = new Illuminate\Http\Request();
$request->headers->set('Accept', 'application/json');
$request->headers->set('Content-Type', 'application/x-www-form-urlencoded');
$request->server->set('REQUEST_METHOD', 'POST');
$request->server->set('REQUEST_URI', '/logistics/login');
$request->request->add(['email' => 'admin@logistics.com', 'password' => 'password', '_token' => 'test']);

$kernel = $app->make('Illuminate\Contracts\Http\Kernel');
$response = $kernel->handle($request);
echo 'Status: ' . $response->getStatusCode() . PHP_EOL;
echo 'Content: ' . $response->getContent() . PHP_EOL;