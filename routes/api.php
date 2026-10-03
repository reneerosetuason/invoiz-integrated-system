<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\Admin\SellerController;
use App\Http\Controllers\Admin\OrderController;

// Unified API — single backend, same invoizdb.
Route::post('auth/login', [AuthController::class, 'login']);
Route::post('auth/register', [AuthController::class, 'register']);

// Buyer mobile/API (from buyer apps) — public browse
Route::get('/categories', [App\Http\Controllers\Api\CategoryController::class, 'index']);
Route::get('/products', [App\Http\Controllers\Api\ProductController::class, 'index']);
Route::get('/products/{id}', [App\Http\Controllers\Api\ProductController::class, 'show']);
Route::get('/stores/{seller}', [App\Http\Controllers\Api\StoreController::class, 'show']);
Route::get('/stores/{seller}/reviews', [App\Http\Controllers\Api\StoreController::class, 'reviews']);
Route::post('/register', [App\Http\Controllers\Api\AuthController::class, 'register']);
Route::post('/login', [App\Http\Controllers\Api\AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('categories', CategoryController::class)->only(['index','show']);
    Route::apiResource('products', ProductController::class)->only(['index','show']);
    Route::apiResource('stores', StoreController::class)->only(['index','show']);

    // Buyer authenticated API
    Route::post('/logout', [App\Http\Controllers\Api\AuthController::class, 'logout']);
    Route::get('/me', [App\Http\Controllers\Api\AuthController::class, 'me']);
    Route::get('/cart', [App\Http\Controllers\Api\CartController::class, 'index']);
    Route::post('/cart/add', [App\Http\Controllers\Api\CartController::class, 'add']);
    Route::get('/orders', [App\Http\Controllers\Api\OrderController::class, 'index']);
    Route::post('/orders/checkout', [App\Http\Controllers\Api\OrderController::class, 'checkout']);

    Route::prefix('admin')->group(function () {
        Route::apiResource('sellers', SellerController::class)->only(['index','show']);
        Route::post('sellers/{seller}/approve', [SellerController::class, 'approve']);
        Route::post('sellers/{seller}/reject', [SellerController::class, 'reject']);
        Route::apiResource('orders', OrderController::class)->only(['index','show','update']);
    });
});

Route::get('/user', fn (Request $request) => $request->user())->middleware('auth:sanctum');
