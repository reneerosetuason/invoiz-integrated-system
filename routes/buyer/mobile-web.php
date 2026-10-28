<?php

use Illuminate\Support\Facades\Route;

// Optional Flutter web build, hosted by the same application as its API.
Route::get('/buyer-app', function () {
    $index = public_path('buyer-app/index.html');
    abort_unless(is_file($index), 404);

    return response()->file($index, ['Cache-Control' => 'no-cache']);
})->name('buyer.mobile-web');
