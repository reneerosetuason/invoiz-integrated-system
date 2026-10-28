<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Shared\AuthController;

Route::post('auth/login', [AuthController::class, 'login']);
Route::post('auth/register', [AuthController::class, 'register']);
