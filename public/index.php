<?php

// PHP 8.5 compat: Laravel 11 vendor config still references the deprecated
// PDO::MYSQL_ATTR_SSL_CA constant. Hide E_DEPRECATED from web output so it
// never leaks into the rendered pages (vendor file left untouched).
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());
