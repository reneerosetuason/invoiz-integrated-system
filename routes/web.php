<?php

// Keep registration order stable; URLs, route names and middleware stay with their role.
Route::get('/downloads/invoiz-rider.apk', static function () {
    $configuredUrl = config('services.rider_app.android_apk_url');
    $configuredUrlParts = is_string($configuredUrl) ? parse_url($configuredUrl) : false;
    abort_unless(
        filter_var(config('services.rider_app.android_apk_verified'), FILTER_VALIDATE_BOOLEAN) === true
            && is_string($configuredUrl)
            && $configuredUrl === route('rider-app.download')
            && is_array($configuredUrlParts)
            && strtolower($configuredUrlParts['scheme'] ?? '') === 'https'
            && !isset($configuredUrlParts['user'])
            && !isset($configuredUrlParts['pass'])
            && !isset($configuredUrlParts['query'])
            && !isset($configuredUrlParts['fragment']),
        404
    );

    $storageRoot = realpath(storage_path('app'));
    $downloadDirectory = realpath(storage_path('app/rider-downloads'));
    $apkPath = realpath(storage_path('app/rider-downloads/invoiz-rider.apk'));

    abort_unless(
        $storageRoot !== false
            && $downloadDirectory !== false
            && $apkPath !== false
            && is_file($apkPath)
            && is_readable($apkPath)
            && str_starts_with($downloadDirectory, $storageRoot . DIRECTORY_SEPARATOR)
            && realpath(dirname($apkPath)) === $downloadDirectory,
        404
    );

    return response()->download($apkPath, 'invoiz-rider.apk', [
        'Content-Type' => 'application/vnd.android.package-archive',
    ]);
})->name('rider-app.download');

require __DIR__.'/buyer/landing.php';
require __DIR__.'/seller/application.php';
require __DIR__.'/shared/auth.php';
require __DIR__.'/buyer/web.php';
require __DIR__.'/seller/web.php';
require __DIR__.'/admin/auth.php';
require __DIR__.'/buyer/chat.php';
require __DIR__.'/admin/web.php';
require __DIR__.'/shared/storage.php';
require __DIR__.'/logistics/web.php';
