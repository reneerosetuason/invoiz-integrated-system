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
        return redirect()->route('logistics.login');
    })->name('logistics.register');

    Route::post('register', function () {
        return redirect()->route('logistics.login');
    });

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('logistics.login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('logistics.password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('logistics.verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('logistics.verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('logistics.verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logistics.logout');
});
