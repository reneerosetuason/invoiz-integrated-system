<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$c = new ReflectionClass('App\Http\Controllers\Logistics\Auth\EmailVerificationPromptController');
echo 'Class exists: ' . ($c->hasMethod('__invoke') ? 'yes' : 'no') . PHP_EOL;
echo 'Methods: ' . implode(', ', array_map(fn($m) => $m->getName(), $c->getMethods())) . PHP_EOL;