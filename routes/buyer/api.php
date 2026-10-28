<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Shared\AuthController;
use App\Http\Controllers\Shared\CategoryController;
use App\Http\Controllers\Shared\ProductController;
use App\Http\Controllers\Shared\StoreController;
use App\Http\Controllers\Admin\OrderController;

// Buyer mobile/API (from buyer apps) â€” public browse
Route::get('/categories', [App\Http\Controllers\Buyer\Api\CategoryController::class, 'index']);
Route::get('/products', [App\Http\Controllers\Buyer\Api\ProductController::class, 'index']);
Route::get('/products/{id}', [App\Http\Controllers\Buyer\Api\ProductController::class, 'show']);
Route::get('/stores/{seller}', [App\Http\Controllers\Buyer\Api\StoreController::class, 'show']);
Route::get('/stores/{seller}/reviews', [App\Http\Controllers\Buyer\Api\StoreController::class, 'reviews']);
Route::post('/register', [App\Http\Controllers\Buyer\Api\AuthController::class, 'register']);
Route::post('/login', [App\Http\Controllers\Buyer\Api\AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'account.active'])->group(function () {
    // Legacy catalog resources keep their controllers, names, and auth gates
    // without shadowing the buyer browse endpoints above.
    Route::prefix('catalog')->group(function () {
        Route::apiResource('categories', CategoryController::class)->only(['index','show']);
        Route::apiResource('products', ProductController::class)->only(['index','show']);
        Route::apiResource('stores', StoreController::class)->only(['index','show']);
    });

    // Buyer authenticated API
    Route::post('/logout', [App\Http\Controllers\Buyer\Api\AuthController::class, 'logout']);
    Route::get('/me', [App\Http\Controllers\Buyer\Api\AuthController::class, 'me']);
    Route::get('/cart', [App\Http\Controllers\Buyer\Api\CartController::class, 'index']);
    Route::post('/cart/add', [App\Http\Controllers\Buyer\Api\CartController::class, 'add']);
    Route::patch('/cart/{id}', [App\Http\Controllers\Buyer\Api\CartController::class, 'update'])->whereNumber('id');
    Route::delete('/cart/{id}', [App\Http\Controllers\Buyer\Api\CartController::class, 'destroy'])->whereNumber('id');
    Route::delete('/cart', [App\Http\Controllers\Buyer\Api\CartController::class, 'clear']);
    Route::apiResource('addresses', App\Http\Controllers\Buyer\Api\AddressController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('/orders', [App\Http\Controllers\Buyer\Api\OrderController::class, 'index']);
    Route::get('/orders/{id}', [App\Http\Controllers\Buyer\Api\OrderController::class, 'show'])->whereNumber('id');
    Route::post('/orders/checkout', [App\Http\Controllers\Buyer\Api\OrderController::class, 'checkout']);
    Route::post('/orders/{id}/cancel', [App\Http\Controllers\Buyer\Api\OrderController::class, 'cancel'])->whereNumber('id');
    Route::post('/orders/{id}/rate', [App\Http\Controllers\Buyer\Api\OrderController::class, 'rate'])->whereNumber('id');
    Route::get('/orders/{order}/deliveries/{delivery}/otp', [App\Http\Controllers\Buyer\Api\DeliveryOtpController::class, 'show'])
        ->middleware(['role:buyer', 'throttle:20,1'])
        ->whereNumber('order')
        ->whereNumber('delivery');
    Route::post('/orders/{id}/returns', [App\Http\Controllers\Buyer\Api\OrderController::class, 'requestReturn'])->whereNumber('id');

    Route::get('/favorites', [App\Http\Controllers\Buyer\Api\FavoriteController::class, 'index']);
    Route::post('/favorites/toggle', [App\Http\Controllers\Buyer\Api\FavoriteController::class, 'toggle']);
    Route::get('/vouchers', [App\Http\Controllers\Buyer\Api\VoucherController::class, 'index']);
    Route::get('/vouchers/claimed', [App\Http\Controllers\Buyer\Api\VoucherController::class, 'claimed']);
    Route::post('/vouchers/{voucher}/claim', [App\Http\Controllers\Buyer\Api\VoucherController::class, 'claim'])->whereNumber('voucher');
    Route::get('/notifications', [App\Http\Controllers\Buyer\Api\NotificationController::class, 'index']);
    Route::post('/notifications/read', [App\Http\Controllers\Buyer\Api\NotificationController::class, 'read']);
    Route::post('/notifications/read-all', [App\Http\Controllers\Buyer\Api\NotificationController::class, 'readAll']);
    Route::get('/chat', [App\Http\Controllers\Buyer\Api\ChatController::class, 'index']);
    Route::post('/chat', [App\Http\Controllers\Buyer\Api\ChatController::class, 'store']);
    Route::get('/chat/{id}/messages', [App\Http\Controllers\Buyer\Api\ChatController::class, 'messages'])->whereNumber('id');
    Route::post('/chat/{id}/reply', [App\Http\Controllers\Buyer\Api\ChatController::class, 'reply'])->whereNumber('id');

    require __DIR__.'/../admin/api.php';
});
