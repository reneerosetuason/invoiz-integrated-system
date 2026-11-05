<?php

require __DIR__.'/shared/api-auth.php';
require __DIR__.'/buyer/api.php';
require __DIR__.'/shared/api-user.php';
require __DIR__.'/logistics/api.php';

\Illuminate\Support\Facades\Route::prefix('admin/account-deletions')->middleware(['auth:sanctum', 'account.active', 'marketplace.admin'])->group(function () {
    \Illuminate\Support\Facades\Route::get('/', [\App\Http\Controllers\Admin\AccountDeletionController::class, 'index']);
    \Illuminate\Support\Facades\Route::get('/{deletion}', [\App\Http\Controllers\Admin\AccountDeletionController::class, 'show'])->whereNumber('deletion');
    \Illuminate\Support\Facades\Route::post('/{deletion}/restore', [\App\Http\Controllers\Admin\AccountDeletionController::class, 'restore'])->whereNumber('deletion')->middleware('throttle:5,1');
});
