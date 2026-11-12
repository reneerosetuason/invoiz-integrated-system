<?php

require __DIR__.'/shared/api-auth.php';
require __DIR__.'/buyer/api.php';
require __DIR__.'/shared/api-user.php';
require __DIR__.'/logistics/api.php';

foreach (['messaging', 'messages-center', 'logistics/messaging'] as $messagingPrefix) {
\Illuminate\Support\Facades\Route::middleware('auth:sanctum')->prefix($messagingPrefix)->group(function () {
    $controller = \App\Http\Controllers\MessagingController::class;
    \Illuminate\Support\Facades\Route::get('/inbox', [$controller, 'inbox']);
    \Illuminate\Support\Facades\Route::get('/unread', [$controller, 'unread']);
    \Illuminate\Support\Facades\Route::get('/contacts', [$controller, 'contacts']);
    \Illuminate\Support\Facades\Route::post('/start', [$controller, 'start']);
    \Illuminate\Support\Facades\Route::get('/threads/{key}', [$controller, 'messages']);
    \Illuminate\Support\Facades\Route::post('/threads/{key}', [$controller, 'send'])->middleware('throttle:60,1');
    \Illuminate\Support\Facades\Route::match(['patch', 'delete'], '/threads/{key}/messages/{id}', [$controller, 'update'])->whereNumber('id');
    \Illuminate\Support\Facades\Route::get('/attachments/{attachment}', [$controller, 'attachment']);
});
}

\Illuminate\Support\Facades\Route::middleware(['auth:sanctum'])->group(function () {
    \Illuminate\Support\Facades\Route::get('/remittances/payout-account', [\App\Http\Controllers\PayoutAccountController::class, 'index']);
    \Illuminate\Support\Facades\Route::post('/remittances/payout-account', [\App\Http\Controllers\PayoutAccountController::class, 'update']);
    \Illuminate\Support\Facades\Route::get('/remittances/incoming', [\App\Http\Controllers\IncomingRemittanceController::class,'index']);
    \Illuminate\Support\Facades\Route::post('/remittances/incoming/{source}/{id}/accept', [\App\Http\Controllers\IncomingRemittanceController::class,'accept'])->whereIn('source',['order','return'])->whereNumber('id');
    \Illuminate\Support\Facades\Route::get('/return-remittances', [\App\Http\Controllers\ReturnSettlementController::class, 'remittances']);
    \Illuminate\Support\Facades\Route::post('/return-remittances/{allocation}', [\App\Http\Controllers\ReturnSettlementController::class, 'payout']);
    \Illuminate\Support\Facades\Route::get('/return-center/{returnRequest}/agreement', [\App\Http\Controllers\ReturnSettlementController::class, 'show']);
    \Illuminate\Support\Facades\Route::post('/return-center/{returnRequest}/agreement', [\App\Http\Controllers\ReturnSettlementController::class, 'update']);
    \Illuminate\Support\Facades\Route::post('/return-deposits/{payment}/review', [\App\Http\Controllers\ReturnSettlementController::class, 'review']);
    \Illuminate\Support\Facades\Route::get('/return-files/{kind}/{id}', [\App\Http\Controllers\ReturnSettlementController::class, 'proof'])->whereNumber('id');
});

\Illuminate\Support\Facades\Route::prefix('admin/account-deletions')->middleware(['auth:sanctum', 'account.active', 'marketplace.admin'])->group(function () {
    \Illuminate\Support\Facades\Route::get('/', [\App\Http\Controllers\Admin\AccountDeletionController::class, 'index']);
    \Illuminate\Support\Facades\Route::get('/{deletion}', [\App\Http\Controllers\Admin\AccountDeletionController::class, 'show'])->whereNumber('deletion');
    \Illuminate\Support\Facades\Route::post('/{deletion}/restore', [\App\Http\Controllers\Admin\AccountDeletionController::class, 'restore'])->whereNumber('deletion')->middleware('throttle:5,1');
});
