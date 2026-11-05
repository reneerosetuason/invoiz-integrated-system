<?php

use App\Http\Controllers\ReturnWorkflowController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/return-center', [ReturnWorkflowController::class, 'index'])->name('returns.center');
    Route::post('/return-center/{returnRequest}', [ReturnWorkflowController::class, 'update'])->name('returns.action');
    Route::get('/return-center/{returnRequest}/proof', [ReturnWorkflowController::class, 'proof'])->name('returns.proof');
});
