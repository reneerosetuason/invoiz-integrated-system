<?php

declare(strict_types=1);

// Runs before Laravel boots; override database settings and cached config.
$root = dirname(__DIR__, 3);
$runtime = sys_get_temp_dir().'/invoiz-lab-ci-'.bin2hex(random_bytes(8));
$cache = 'devops/lab-act-3/.runtime/cache-'.bin2hex(random_bytes(8));
mkdir($root.'/'.$cache, 0700, true);
foreach (['', '/storage/framework/views', '/storage/framework/sessions', '/storage/framework/cache/data', '/storage/logs', '/storage/app/private', '/storage/app/public', '/cache'] as $suffix) {
    if (!mkdir($runtime.$suffix, 0700, true) && !is_dir($runtime.$suffix)) {
        throw new RuntimeException('Unable to create isolated CI runtime.');
    }
}
$environment = [
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'false',
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('x', 32)),
    'APP_URL' => 'http://localhost',
    'APP_MAINTENANCE_DRIVER' => 'file',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
    'DB_HOST' => 'invalid.example',
    'DB_PORT' => '0',
    'DB_USERNAME' => '',
    'DB_PASSWORD' => '',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'array',
    'BCRYPT_ROUNDS' => '4',
    'LOG_CHANNEL' => 'stderr',
    'LOG_DEPRECATIONS_CHANNEL' => 'null',
    'LARAVEL_STORAGE_PATH' => $runtime.'/storage',
    'VIEW_COMPILED_PATH' => $runtime.'/storage/framework/views',
    // Relative cache paths also work with Laravel's Windows path resolver.
    'APP_CONFIG_CACHE' => $cache.'/config.php',
    'APP_ROUTES_CACHE' => $cache.'/routes.php',
    'APP_EVENTS_CACHE' => $cache.'/events.php',
    'APP_PACKAGES_CACHE' => $cache.'/packages.php',
    'APP_SERVICES_CACHE' => $cache.'/services.php',
];
foreach ($environment as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $_SERVER[$name] = $value;
}
require $root.'/vendor/autoload.php';

// Laravel's immutable dotenv loader cannot overwrite these forced settings.
// Runtime storage is temporary; package caches use the ignored lab directory.
