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
use App\Http\Controllers\Logistics\Api\RiderPasswordResetController;
use App\Http\Controllers\Logistics\Api\RiderRemittanceController;
use App\Http\Controllers\Logistics\Api\VehicleTypeController;
use App\Http\Controllers\Logistics\Api\HubCourierController;
use Illuminate\Support\Facades\Route;

Route::prefix('logistics')->group(function () {

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1');
Route::post('/courier/login', [HubCourierController::class, 'login'])->middleware('throttle:10,1');
Route::middleware('auth:sanctum')->prefix('courier')->group(function () {
    Route::get('/profile', [HubCourierController::class, 'profile']);
    Route::get('/dashboard', [HubCourierController::class, 'dashboard']);
    Route::patch('/status', [HubCourierController::class, 'status']);
    Route::get('/transfers/{transfer}', [HubCourierController::class, 'show']);
    Route::post('/transfers/{transfer}/origin-arrival', [HubCourierController::class, 'originArrival']);
    Route::post('/transfers/{transfer}/load', [HubCourierController::class, 'loadParcel']);
    Route::post('/transfers/{transfer}/complete-loading', [HubCourierController::class, 'completeLoading']);
    Route::post('/transfers/{transfer}/dispatch', [HubCourierController::class, 'dispatch']);
    Route::post('/transfers/{transfer}/destination-arrival', [HubCourierController::class, 'destinationArrival']);
});

Route::get('/vehicle-types', [VehicleTypeController::class, 'index']);
Route::get('/address/provinces', [AddressController::class, 'provinces']);
Route::get('/address/provinces/{province}/municipalities', [AddressController::class, 'municipalities']);
Route::get('/address/municipalities/{municipality}/barangays', [AddressController::class, 'barangays']);
Route::post('/rider/apply', [RiderApplicationController::class, 'store']);
Route::get('/rider/application-status', [RiderApplicationController::class, 'status'])->middleware('throttle:10,1');
Route::post('/center/apply', [CenterApplicationController::class, 'store']);
Route::get('/center/application-status', [CenterApplicationController::class, 'status'])->middleware('throttle:10,1');

Route::post('/rider/email/request-code', [RiderEmailVerificationController::class, 'send'])
    ->middleware('throttle:5,1');
Route::post('/rider/email/resend', [RiderEmailVerificationController::class, 'send'])
    ->middleware('throttle:5,1');
Route::post('/rider/email/verify', [RiderEmailVerificationController::class, 'verify'])
    ->middleware('throttle:10,1');

Route::post('/rider/password/forgot', [RiderPasswordResetController::class, 'forgot'])
    ->middleware('throttle:5,1');
Route::post('/rider/password/reset', [RiderPasswordResetController::class, 'reset'])
    ->middleware('throttle:5,1');

// Private rider documents are served through a short-lived signed URL
// generated for the authenticated rider (see RiderController::profile). The
// `signed` middleware rejects tampered or expired links before the controller
// runs; no bearer token is required by the external viewer.
Route::get('/rider/documents/{document}', [RiderController::class, 'document'])
    ->middleware('signed')
    ->name('api.logistics.rider.documents.show');

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
        Route::post('/deliveries/{delivery}/exception', [RiderDeliveryController::class, 'reportException']);
        Route::post('/deliveries/{delivery}/reattempt', [RiderDeliveryController::class, 'requestReattempt']);
        Route::post('/deliveries/{delivery}/return-to-center', [RiderDeliveryController::class, 'returnToCenter']);

        Route::post('/location', [RiderLocationController::class, 'store']);

        Route::get('/earnings', [RiderEarningsController::class, 'index']);
        Route::get('/history', [RiderHistoryController::class, 'index']);

        // Cash handoffs rider -> seller (bank-to-bank, verified by seller).
        Route::get('/remittances', [RiderRemittanceController::class, 'index']);
        Route::post('/remittances/{remittance}/sent', [RiderRemittanceController::class, 'markSent']);
        Route::get('/remittances/earnings-payouts', [RiderRemittanceController::class, 'earningsPayouts']);
        Route::post('/remittances/{id}/accept-payout', [RiderRemittanceController::class, 'acceptPayout']);

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
