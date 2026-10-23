<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\Admin\SellerController;
use App\Http\Controllers\Admin\OrderController;

// Unified API â€” single backend, same invoizdb.
Route::post('auth/login', [AuthController::class, 'login']);
Route::post('auth/register', [AuthController::class, 'register']);

// Buyer mobile/API (from buyer apps) â€” public browse
Route::get('/categories', [App\Http\Controllers\Api\CategoryController::class, 'index']);
Route::get('/products', [App\Http\Controllers\Api\ProductController::class, 'index']);
Route::get('/products/{id}', [App\Http\Controllers\Api\ProductController::class, 'show']);
Route::get('/stores/{seller}', [App\Http\Controllers\Api\StoreController::class, 'show']);
Route::get('/stores/{seller}/reviews', [App\Http\Controllers\Api\StoreController::class, 'reviews']);
Route::post('/register', [App\Http\Controllers\Api\AuthController::class, 'register']);
Route::post('/login', [App\Http\Controllers\Api\AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'account.active'])->group(function () {
    Route::apiResource('categories', CategoryController::class)->only(['index','show']);
    Route::apiResource('products', ProductController::class)->only(['index','show']);
    Route::apiResource('stores', StoreController::class)->only(['index','show']);

    // Buyer authenticated API
    Route::post('/logout', [App\Http\Controllers\Api\AuthController::class, 'logout']);
    Route::get('/me', [App\Http\Controllers\Api\AuthController::class, 'me']);
    Route::get('/cart', [App\Http\Controllers\Api\CartController::class, 'index']);
    Route::post('/cart/add', [App\Http\Controllers\Api\CartController::class, 'add']);
    Route::apiResource('addresses', App\Http\Controllers\Api\AddressController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('/orders', [App\Http\Controllers\Api\OrderController::class, 'index']);
    Route::post('/orders/checkout', [App\Http\Controllers\Api\OrderController::class, 'checkout']);
    Route::post('/orders/{id}/cancel', [App\Http\Controllers\Api\OrderController::class, 'cancel'])->whereNumber('id');
    Route::post('/orders/{id}/rate', [App\Http\Controllers\Api\OrderController::class, 'rate'])->whereNumber('id');
    Route::get('/orders/{order}/deliveries/{delivery}/otp', [App\Http\Controllers\Api\DeliveryOtpController::class, 'show'])
        ->middleware(['role:buyer', 'throttle:20,1'])
        ->whereNumber('order')
        ->whereNumber('delivery');

    Route::get('/favorites', [App\Http\Controllers\Api\FavoriteController::class, 'index']);
    Route::post('/favorites/toggle', [App\Http\Controllers\Api\FavoriteController::class, 'toggle']);
    Route::get('/vouchers', [App\Http\Controllers\Api\VoucherController::class, 'index']);
    Route::get('/vouchers/claimed', [App\Http\Controllers\Api\VoucherController::class, 'claimed']);
    Route::post('/vouchers/{voucher}/claim', [App\Http\Controllers\Api\VoucherController::class, 'claim'])->whereNumber('voucher');
    Route::get('/notifications', [App\Http\Controllers\Api\NotificationController::class, 'index']);
    Route::post('/notifications/read', [App\Http\Controllers\Api\NotificationController::class, 'read']);
    Route::post('/notifications/read-all', [App\Http\Controllers\Api\NotificationController::class, 'readAll']);
    Route::get('/chat', [App\Http\Controllers\Api\ChatController::class, 'index']);
    Route::post('/chat', [App\Http\Controllers\Api\ChatController::class, 'store']);
    Route::get('/chat/{id}/messages', [App\Http\Controllers\Api\ChatController::class, 'messages'])->whereNumber('id');
    Route::post('/chat/{id}/reply', [App\Http\Controllers\Api\ChatController::class, 'reply'])->whereNumber('id');

    Route::prefix('admin')->middleware('marketplace.admin')->group(function () {
        Route::apiResource('sellers', SellerController::class)->only(['index','show']);
        Route::post('sellers/{seller}/approve', [SellerController::class, 'approve']);
        Route::post('sellers/{seller}/reject', [SellerController::class, 'reject']);
        Route::apiResource('orders', OrderController::class)->only(['index','show','update']);
    });
});

Route::get('/user', fn (Request $request) => $request->user())->middleware(['auth:sanctum', 'account.active']);

require __DIR__.'/logistics/api.php';

