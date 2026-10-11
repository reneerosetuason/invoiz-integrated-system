<?php

use App\Http\Controllers\Logistics\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Logistics\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Logistics\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Logistics\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Logistics\Auth\NewPasswordController;
use App\Http\Controllers\Logistics\Auth\PasswordController;
use App\Http\Controllers\Logistics\Auth\PasswordResetLinkController;
use App\Http\Controllers\Logistics\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', function () {
        return redirect()->route('login');
    })->name('register');

    Route::post('register', function () {
        return redirect()->route('login');
    });

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    // Email-based password reset is intentionally disabled for Logistics:
    // passwords are reset by the Logistics Manager only.
    Route::post('forgot-password', function () {
        return redirect()->route('password.request');
    });

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});