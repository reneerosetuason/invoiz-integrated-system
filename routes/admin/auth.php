<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminAuthController;

Route::get('/admin/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'login'])->name('admin.login.post')->middleware(\App\Http\Middleware\AuthenticationThrottle::class);
Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');
