<?php

use App\Http\Controllers\ReturnWorkflowController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/remittances/payout-account', [\App\Http\Controllers\PayoutAccountController::class, 'index'])->name('remittances.payout-account');
    Route::post('/remittances/payout-account', [\App\Http\Controllers\PayoutAccountController::class, 'update'])->name('remittances.payout-account.update');
    Route::get('/remittances/incoming', [\App\Http\Controllers\IncomingRemittanceController::class,'index'])->name('remittances.incoming');
    Route::post('/remittances/incoming/{source}/{id}/accept', [\App\Http\Controllers\IncomingRemittanceController::class,'accept'])->whereIn('source',['order','return'])->whereNumber('id')->name('remittances.incoming.accept');
    Route::get('/return-guide', fn () => response()->file(base_path('docs/flows/return-refund-ui-guide.html'), ['Content-Type' => 'text/html; charset=UTF-8']))->name('returns.guide');
    Route::get('/return-remittances', [\App\Http\Controllers\ReturnSettlementController::class, 'remittances'])->name('returns.remittances');
    Route::post('/return-remittances/{allocation}', [\App\Http\Controllers\ReturnSettlementController::class, 'payout'])->name('returns.payout');
    Route::post('/return-deposits/{payment}/review', [\App\Http\Controllers\ReturnSettlementController::class, 'review'])->name('returns.deposit.review');
    Route::get('/return-files/{kind}/{id}', [\App\Http\Controllers\ReturnSettlementController::class, 'proof'])->whereNumber('id')->name('returns.file');
    Route::get('/return-center/{returnRequest}/agreement', [\App\Http\Controllers\ReturnSettlementController::class, 'show'])->name('returns.agreement');
    Route::post('/return-center/{returnRequest}/agreement', [\App\Http\Controllers\ReturnSettlementController::class, 'update'])->name('returns.agreement.update');
    Route::get('/return-center', [ReturnWorkflowController::class, 'index'])->name('returns.center');
    Route::post('/return-center/{returnRequest}', [ReturnWorkflowController::class, 'update'])->name('returns.action');
    Route::get('/return-center/{returnRequest}/proof', [ReturnWorkflowController::class, 'proof'])->name('returns.proof');
});
