<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\SellerController;
use App\Http\Controllers\Admin\OrderController;

Route::prefix('admin')->middleware('marketplace.admin')->group(function () {
        Route::apiResource('sellers', SellerController::class)->only(['index','show']);
        Route::post('sellers/{seller}/approve', [SellerController::class, 'approve']);
        Route::post('sellers/{seller}/reject', [SellerController::class, 'reject']);
        Route::apiResource('orders', OrderController::class)->only(['index','show','update']);
    });
