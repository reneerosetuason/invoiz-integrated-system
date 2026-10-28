<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Seller\SellerApplicationController;

// ---- Sell on INVOIZ (buyer or new seller applies, admin approves in /admin/sellers) ----
Route::get('/sell', [SellerApplicationController::class, 'create'])->name('sell');

Route::get('/seller/register', fn () => redirect()->route('sell'))->name('seller.register');
Route::get('/seller/apply', fn () => redirect()->route('sell'))->name('seller.apply');

Route::post('/sell', [SellerApplicationController::class, 'store'])->name('sell.post');
