<?php

use App\Http\Controllers\Buyer\Api\AddressController;
use App\Http\Controllers\Buyer\Api\CartController;
use App\Http\Controllers\Buyer\Api\ChatController;
use App\Http\Controllers\Buyer\Api\DeliveryOtpController;
use App\Http\Controllers\Buyer\Api\FavoriteController;
use App\Http\Controllers\Buyer\Api\MobileAuthController;
use App\Http\Controllers\Buyer\Api\NotificationController;
use App\Http\Controllers\Buyer\Api\SellerController;
use App\Http\Controllers\Buyer\Api\VoucherController;
use App\Http\Controllers\Seller\SellerApplicationController;
use App\Http\Controllers\Shared\CategoryController;
use App\Http\Controllers\Shared\ProductController;
use App\Http\Controllers\Shared\StoreController;
use App\Http\Middleware\OptionalBuyerAuthentication;
use Illuminate\Support\Facades\Route;

// Buyer mobile/API (from buyer apps) â€” public browse
Route::middleware(OptionalBuyerAuthentication::class)->group(function () {
    Route::get('/categories', [App\Http\Controllers\Buyer\Api\CategoryController::class, 'index']);
    Route::get('/products', [App\Http\Controllers\Buyer\Api\ProductController::class, 'index']);
    Route::get('/products/{id}', [App\Http\Controllers\Buyer\Api\ProductController::class, 'show']);
    Route::get('/stores/{seller}', [App\Http\Controllers\Buyer\Api\StoreController::class, 'show']);
    Route::get('/stores/{seller}/reviews', [App\Http\Controllers\Buyer\Api\StoreController::class, 'reviews']);
    // Cascading Region -> Barangay address options (PSGC), cached server-side.
    Route::get('/psgc/{path}', [App\Http\Controllers\Buyer\Api\PsgcController::class, 'show'])
        ->where('path', '.*');
    Route::get('/help/faq', [App\Http\Controllers\Buyer\Api\HelpController::class, 'index']);
});
Route::post('/register', [App\Http\Controllers\Buyer\Api\AuthController::class, 'register'])->middleware(\App\Http\Middleware\AuthenticationThrottle::class);
Route::post('/login', [App\Http\Controllers\Buyer\Api\AuthController::class, 'login'])->middleware(\App\Http\Middleware\AuthenticationThrottle::class);

// Verified mobile login shares the website's account/OTP state and database.
Route::prefix('mobile/buyer')->middleware('throttle:10,1')->group(function () {
    Route::post('/register', [MobileAuthController::class, 'register'])->middleware(\App\Http\Middleware\AuthenticationThrottle::class);
    Route::post('/login', [MobileAuthController::class, 'login'])->middleware(\App\Http\Middleware\AuthenticationThrottle::class);
    Route::post('/verify-email', [MobileAuthController::class, 'verifyEmail']);
    Route::post('/resend-code', [MobileAuthController::class, 'resendCode']);
});

Route::middleware(['auth:sanctum', 'account.active'])->group(function () {
    // Legacy catalog resources keep their controllers, names, and auth gates
    // without shadowing the buyer browse endpoints above.
    Route::prefix('catalog')->group(function () {
        Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);
        Route::apiResource('products', ProductController::class)->only(['index', 'show']);
        Route::apiResource('stores', StoreController::class)->only(['index', 'show']);
    });

    // Buyer authenticated API
    Route::post('/logout', [App\Http\Controllers\Buyer\Api\AuthController::class, 'logout']);
    Route::get('/me', [App\Http\Controllers\Buyer\Api\AuthController::class, 'me']);
    Route::match(['PUT', 'POST'], '/profile', [App\Http\Controllers\Buyer\Api\AuthController::class, 'updateProfile']);
    Route::post('/seller/apply', [SellerApplicationController::class, 'store']);
    Route::get('/seller/status', [SellerController::class, 'status']);
    Route::get('/seller/me', [SellerController::class, 'me']);
    Route::middleware(\App\Http\Middleware\EnsureBuyerApiAccess::class)->group(function () {
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart/add', [CartController::class, 'add']);
    Route::match(['PUT', 'PATCH'], '/cart/{id}', [CartController::class, 'update'])->whereNumber('id');
    Route::delete('/cart/{id}', [CartController::class, 'destroy'])->whereNumber('id');
    Route::delete('/cart', [CartController::class, 'clear']);
    Route::apiResource('addresses', AddressController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('/orders', [App\Http\Controllers\Buyer\Api\OrderController::class, 'index']);
    Route::get('/orders/{id}', [App\Http\Controllers\Buyer\Api\OrderController::class, 'show'])->whereNumber('id');
    Route::post('/orders/checkout', [App\Http\Controllers\Buyer\Api\OrderController::class, 'checkout']);
    Route::post('/orders/preview', [App\Http\Controllers\Buyer\Api\OrderController::class, 'preview']);
    Route::post('/orders/{id}/cancel', [App\Http\Controllers\Buyer\Api\OrderController::class, 'cancel'])->whereNumber('id');
    Route::post('/orders/{id}/rate', [App\Http\Controllers\Buyer\Api\OrderController::class, 'rate'])->whereNumber('id');
    Route::get('/orders/{order}/deliveries/{delivery}/otp', [DeliveryOtpController::class, 'show'])
        ->middleware('throttle:20,1')
        ->whereNumber('order')
        ->whereNumber('delivery');
    Route::post('/orders/{id}/returns', [App\Http\Controllers\Buyer\Api\OrderController::class, 'requestReturn'])->whereNumber('id');

    Route::get('/help/complaints', [App\Http\Controllers\Buyer\Api\HelpController::class, 'myComplaints']);
    Route::post('/help/complaint', [App\Http\Controllers\Buyer\Api\HelpController::class, 'storeComplaint']);

    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites/toggle', [FavoriteController::class, 'toggle']);
    Route::get('/vouchers', [VoucherController::class, 'index']);
    Route::get('/vouchers/claimed', [VoucherController::class, 'claimed']);
    Route::post('/vouchers/{voucher}/claim', [VoucherController::class, 'claim'])->whereNumber('voucher');
    Route::post('/vouchers/validate', [VoucherController::class, 'validateCode']);
    Route::match(['POST', 'DELETE'], '/stores/{seller}/follow', [App\Http\Controllers\Buyer\Api\StoreController::class, 'follow'])->whereNumber('seller');
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read', [NotificationController::class, 'read']);
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    // Compatibility aliases retain the Flutter messaging contract.
    Route::get('/conversations', [ChatController::class, 'index']);
    Route::get('/conversations/unread-count', [ChatController::class, 'unreadCount']);
    Route::post('/conversations', [ChatController::class, 'store']);
    Route::get('/conversations/{id}/messages', [ChatController::class, 'messages'])->whereNumber('id');
    Route::post('/conversations/{id}/reply', [ChatController::class, 'reply'])->whereNumber('id');
    Route::get('/chat', [ChatController::class, 'index']);
    Route::post('/chat', [ChatController::class, 'store']);
    Route::get('/chat/{id}/messages', [ChatController::class, 'messages'])->whereNumber('id');
    Route::post('/chat/{id}/reply', [ChatController::class, 'reply'])->whereNumber('id');

    });

    require __DIR__.'/../admin/api.php';
});
