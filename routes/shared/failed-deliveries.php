<?php

use App\Http\Controllers\FailedDeliveryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/failed-deliveries', [FailedDeliveryController::class, 'index'])->name('failed-deliveries.index');
    Route::get('/failed-deliveries/{delivery}', [FailedDeliveryController::class, 'show'])->name('failed-deliveries.show');
    Route::post('/failed-deliveries/{delivery}', [FailedDeliveryController::class, 'update'])->name('failed-deliveries.update');
    Route::post('/failed-delivery-payouts/{fee}', [FailedDeliveryController::class, 'payout'])->name('failed-deliveries.payout');
    Route::get('/failed-delivery-proofs/{kind}/{id}', [FailedDeliveryController::class, 'proof'])->whereIn('kind', ['deposit', 'payout', 'handover', 'driver', 'seller'])->whereNumber('id')->name('failed-deliveries.proof');
});
