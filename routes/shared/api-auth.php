<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Shared\AuthController;

Route::post('auth/login', [AuthController::class, 'login'])->middleware(\App\Http\Middleware\AuthenticationThrottle::class);
Route::post('auth/register', [AuthController::class, 'register'])->middleware(\App\Http\Middleware\AuthenticationThrottle::class);
