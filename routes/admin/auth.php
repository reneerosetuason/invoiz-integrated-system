<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminAuthController;

// Admin sign-in lives on the unified login (/login): admins are detected by
// role there and sent to /admin/dashboard. There is no separate admin login
// page anymore; only the admin logout endpoint remains.
Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');
