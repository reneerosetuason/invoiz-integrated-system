<?php

use App\Http\Controllers\Logistics\Api\AddressController;
use App\Http\Controllers\Logistics\Api\AuthController;
use App\Http\Controllers\Logistics\Api\CenterApplicationController;
use App\Http\Controllers\Logistics\Api\RiderApplicationController;
use App\Http\Controllers\Logistics\Api\RiderController;
use App\Http\Controllers\Logistics\Api\RiderDeliveryController;
use App\Http\Controllers\Logistics\Api\RiderEarningsController;
use App\Http\Controllers\Logistics\Api\RiderEmailVerificationController;
use App\Http\Controllers\Logistics\Api\RiderHistoryController;
use App\Http\Controllers\Logistics\Api\RiderLocationController;
use App\Http\Controllers\Logistics\Api\RiderConversationController;
use App\Http\Controllers\Logistics\Api\RiderMessageController;
use App\Http\Controllers\Logistics\Api\RiderNotificationController;
use App\Http\Controllers\Logistics\Api\RiderPasswordController;
use App\Http\Controllers\Logistics\Api\RiderRemittanceController;
use App\Http\Controllers\Logistics\Api\VehicleTypeController;
use Illuminate\Support\Facades\Route;

Route::prefix('logistics')->group(function () {

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1');

Route::get('/vehicle-types', [VehicleTypeController::class, 'index']);
Route::get('/address/provinces', [AddressController::class, 'provinces']);
Route::get('/address/provinces/{province}/municipalities', [AddressController::class, 'municipalities']);
Route::get('/address/municipalities/{municipality}/barangays', [AddressController::class, 'barangays']);
Route::post('/rider/apply', [RiderApplicationController::class, 'store']);
Route::get('/rider/application-status', [RiderApplicationController::class, 'status']);
Route::post('/center/apply', [CenterApplicationController::class, 'store']);
Route::get('/center/application-status', [CenterApplicationController::class, 'status']);

Route::post('/rider/email/request-code', [RiderEmailVerificationController::class, 'send'])
    ->middleware('throttle:5,1');
Route::post('/rider/email/resend', [RiderEmailVerificationController::class, 'send'])
    ->middleware('throttle:5,1');
Route::post('/rider/email/verify', [RiderEmailVerificationController::class, 'verify'])
    ->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::middleware('role:rider')->prefix('rider')->group(function () {
        Route::get('/profile', [RiderController::class, 'profile']);
        Route::patch('/profile', [RiderController::class, 'updateProfile']);
        Route::patch('/password', [RiderPasswordController::class, 'update']);
        Route::patch('/status', [RiderController::class, 'updateStatus']);
        Route::get('/dashboard', [RiderController::class, 'dashboard']);

        Route::get('/deliveries', [RiderDeliveryController::class, 'index']);
        // Registered before show() so "lookup" is not captured by {delivery}.
        Route::get('/deliveries/lookup', [RiderDeliveryController::class, 'lookup']);
        Route::get('/deliveries/{delivery}', [RiderDeliveryController::class, 'show']);
        Route::post('/deliveries/{delivery}/accept', [RiderDeliveryController::class, 'accept']);
        Route::patch('/deliveries/{delivery}/status', [RiderDeliveryController::class, 'updateStatus']);
        Route::post('/deliveries/{delivery}/pickup', [RiderDeliveryController::class, 'pickup']);
        Route::post('/deliveries/{delivery}/sorting-center-handoff', [RiderDeliveryController::class, 'sortingCenterHandoff']);
        Route::post('/deliveries/{delivery}/sorting-center-pickup', [RiderDeliveryController::class, 'sortingCenterPickup']);
        Route::post('/deliveries/{delivery}/complete', [RiderDeliveryController::class, 'complete']);
        Route::post('/deliveries/{delivery}/failed', [RiderDeliveryController::class, 'failed']);

        Route::post('/location', [RiderLocationController::class, 'store']);

        Route::get('/earnings', [RiderEarningsController::class, 'index']);
        Route::get('/history', [RiderHistoryController::class, 'index']);

        // Cash handoffs rider -> seller (bank-to-bank, verified by seller).
        Route::get('/remittances', [RiderRemittanceController::class, 'index']);
        Route::post('/remittances/{remittance}/sent', [RiderRemittanceController::class, 'markSent']);

        Route::get('/notifications', [RiderNotificationController::class, 'index']);
        Route::patch('/notifications/read-all', [RiderNotificationController::class, 'markAllRead']);
        Route::patch('/notifications/{notification}/read', [RiderNotificationController::class, 'markRead']);

        Route::get('/messages', [RiderMessageController::class, 'index']);
        Route::get('/messages/poll', [RiderMessageController::class, 'poll']);
        Route::post('/messages', [RiderMessageController::class, 'store']);

        Route::get('/conversations', [RiderConversationController::class, 'index']);
        Route::get('/conversations/{conversation}', [RiderConversationController::class, 'show']);
        Route::post('/conversations/{conversation}/messages', [RiderConversationController::class, 'store']);
        Route::patch('/conversations/{conversation}/messages/{message}', [RiderConversationController::class, 'update']);
        Route::delete('/conversations/{conversation}/messages/{message}', [RiderConversationController::class, 'destroy']);
    });
});

});