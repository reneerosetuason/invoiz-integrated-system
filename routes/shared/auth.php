<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Shared\UnifiedAuthController;

// ---- Unified auth (single login/register for everyone) ----
Route::get('/login', [UnifiedAuthController::class, 'showLogin'])->name('login');
Route::post('/login', [UnifiedAuthController::class, 'login'])->name('login.post');
Route::get('/register', [UnifiedAuthController::class, 'showRegister'])->name('register');
Route::post('/register', [UnifiedAuthController::class, 'register'])->name('register.post');
Route::post('/logout', [UnifiedAuthController::class, 'logout'])->name('logout');
Route::get('/logout', [UnifiedAuthController::class, 'logout']);
Route::get('/home', function () {
    if (! Auth::check()) {
        return redirect('/shop');
    }

    $user = Auth::user();
    if ($user->isAdmin()) {
        return redirect('/admin/dashboard');
    }

    if ($user->canSell()) {
        return match (session('active_role')) {
            'seller' => redirect('/seller/dashboard'),
            'buyer' => redirect('/shop'),
            default => redirect('/choose'),
        };
    }

    return redirect('/shop');
});

// Email code verification (Gmail OTP)
Route::get('/verify', [UnifiedAuthController::class, 'showVerify'])->name('verify');
Route::post('/verify', [UnifiedAuthController::class, 'verifyCode'])->name('verify.post');
Route::post('/verify/resend', [UnifiedAuthController::class, 'resendCode'])->name('verify.resend');

// Password reset (one shared flow for buyer, seller, admin and logistics logins)
Route::get('/forgot-password', [\App\Http\Controllers\Shared\Auth\PasswordResetController::class, 'showLinkForm'])->name('auth.password.request');
Route::post('/forgot-password', [\App\Http\Controllers\Shared\Auth\PasswordResetController::class, 'sendLink'])->name('auth.password.email');
Route::get('/reset-password/{token}', [\App\Http\Controllers\Shared\Auth\PasswordResetController::class, 'showResetForm'])->name('auth.password.reset');
Route::post('/reset-password', [\App\Http\Controllers\Shared\Auth\PasswordResetController::class, 'reset'])->name('auth.password.update');

// Continue with Gmail (Google OAuth)
Route::get('/auth/google', [UnifiedAuthController::class, 'googleRedirect'])->name('google.redirect');
Route::get('/auth/google/callback', [UnifiedAuthController::class, 'googleCallback'])->name('google.callback');

// Dual buyer+seller logins pick an account here before entering a dashboard.
Route::middleware('auth')->group(function () {
    Route::get('/choose', [UnifiedAuthController::class, 'showChoose'])->name('choose');
    Route::get('/choose/buyer', fn () => redirect()->route('choose'))->name('choose.buyer');
    Route::get('/choose/seller', fn () => redirect()->route('choose'))->name('choose.seller');
    Route::post('/switch-role', [UnifiedAuthController::class, 'switchRole'])->name('role.switch');
});

// (Unified /verify + /auth/google routes above cover all accounts.)
