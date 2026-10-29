<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Seller\AuthController as SellerAppAuth;
use App\Http\Controllers\Seller\ChatController as SellerAppChat;
use App\Http\Controllers\Seller\DashboardController as SellerAppDashboard;
use App\Http\Controllers\Seller\NotificationController as SellerAppNotifications;
use App\Http\Controllers\Seller\OrderController as SellerAppOrders;
use App\Http\Controllers\Seller\ProductController as SellerAppProducts;
use App\Http\Controllers\Seller\ProfileController as SellerAppProfile;
use App\Http\Controllers\Seller\ReportController as SellerAppReports;
use App\Http\Controllers\Seller\ReviewController as SellerAppReviews;
use App\Http\Controllers\Seller\VoucherController as SellerAppVouchers;

// ---- Seller Center: exact original seller app (same pages + functions) ----
Route::get('/seller/login', [SellerAppAuth::class, 'showLogin'])->name('seller.login');
Route::post('/seller/login', [SellerAppAuth::class, 'login'])->name('seller.login.post')->middleware(\App\Http\Middleware\AuthenticationThrottle::class);
Route::post('/seller/logout', [SellerAppAuth::class, 'logout'])->name('seller.logout');

Route::prefix('seller')->middleware('seller')->group(function () {
    Route::get('/dashboard', SellerAppDashboard::class)->name('seller.dashboard');

    // Orders
    Route::get('/orders', [SellerAppOrders::class, 'index'])->name('seller.orders.index');
    Route::get('/orders/{order}', [SellerAppOrders::class, 'show'])->name('seller.orders.show');
    Route::post('/orders/{order}/status', [SellerAppOrders::class, 'updateStatus'])->name('seller.orders.status');
    Route::post('/returns/{delivery}/confirm', [SellerAppOrders::class, 'confirmReturn'])->name('seller.returns.confirm');
    Route::post('/orders/{order}/pickup', [SellerAppOrders::class, 'schedulePickup'])->name('seller.orders.pickup');
    Route::get('/orders/{order}/waybill', [SellerAppOrders::class, 'waybill'])->name('seller.orders.waybill');

    // Cash handoffs: rider -> seller bank transfers the seller must verify
    // before each sale settles.
    Route::get('/remittances', [\App\Http\Controllers\Seller\RemittanceController::class, 'index'])->name('seller.remittances.index');
    Route::post('/remittances/{remittance}/confirm', [\App\Http\Controllers\Seller\RemittanceController::class, 'confirm'])->name('seller.remittances.confirm');

    // Payouts: withdraw settled, available earnings.
    Route::get('/payouts', [\App\Http\Controllers\Seller\PayoutController::class, 'index'])->name('seller.payouts.index');
    Route::post('/payouts/request', [\App\Http\Controllers\Seller\PayoutController::class, 'request'])->name('seller.payouts.request');

    // Returns / refunds
    Route::get('/returns', [\App\Http\Controllers\Seller\ReturnController::class, 'index'])->name('seller.returns.index');
    Route::post('/returns/{returnRequest}', [\App\Http\Controllers\Seller\ReturnController::class, 'update'])->name('seller.returns.update');

    // Products
    Route::get('/products', [SellerAppProducts::class, 'index'])->name('seller.products.index');
    Route::get('/products/create', [SellerAppProducts::class, 'create'])->name('seller.products.create');
    Route::post('/products', [SellerAppProducts::class, 'store'])->name('seller.products.store');
    Route::get('/products/{product}/edit', [SellerAppProducts::class, 'edit'])->name('seller.products.edit');
    Route::post('/products/{product}', [SellerAppProducts::class, 'update'])->name('seller.products.update');
    Route::post('/products/{product}/archive', [SellerAppProducts::class, 'archive'])->name('seller.products.archive');
    Route::post('/products/{product}/restore', [SellerAppProducts::class, 'restore'])->name('seller.products.restore');

    // Vouchers
    Route::get('/vouchers', [SellerAppVouchers::class, 'index'])->name('seller.vouchers.index');
    Route::post('/vouchers', [SellerAppVouchers::class, 'store'])->name('seller.vouchers.store');
    Route::post('/vouchers/{voucher}/status', [SellerAppVouchers::class, 'updateStatus'])->whereNumber('voucher')->name('seller.vouchers.status');
    Route::post('/vouchers/{voucher}', [SellerAppVouchers::class, 'update'])->name('seller.vouchers.update');
    Route::delete('/vouchers/{voucher}', [SellerAppVouchers::class, 'destroy'])->name('seller.vouchers.destroy');

// Store sales (one running sale per store).
Route::get('/sales', [\App\Http\Controllers\Seller\SaleController::class, 'index'])->name('seller.sales.index');
Route::post('/sales', [\App\Http\Controllers\Seller\SaleController::class, 'store'])->name('seller.sales.store');
Route::post('/sales/{sale}', [\App\Http\Controllers\Seller\SaleController::class, 'update'])->name('seller.sales.update');
Route::delete('/sales/{sale}', [\App\Http\Controllers\Seller\SaleController::class, 'destroy'])->name('seller.sales.destroy');

    // Reports
    Route::get('/reports', SellerAppReports::class)->name('seller.reports');

    // Chat
    Route::get('/chat', [SellerAppChat::class, 'index'])->name('seller.chat.index');
    Route::get('/chat/{conversation}', [SellerAppChat::class, 'show'])->name('seller.chat.show');
    Route::post('/chat/{conversation}/reply', [SellerAppChat::class, 'reply'])->name('seller.chat.reply');

    // Feedback
    Route::get('/feedback', [SellerAppReviews::class, 'index'])->name('seller.feedback.index');
    Route::post('/feedback/{review}/toggle', [SellerAppReviews::class, 'toggle'])->name('seller.feedback.toggle');
    Route::post('/feedback/{review}/reply', [SellerAppReviews::class, 'reply'])->name('seller.feedback.reply');

    // Notifications
    Route::get('/notifications', [SellerAppNotifications::class, 'index'])->name('seller.notifications.index');
    Route::match(['GET', 'POST'], '/notifications/{notification}/read', [SellerAppNotifications::class, 'markRead'])->name('seller.notifications.read');
    Route::post('/notifications/read-all', [SellerAppNotifications::class, 'markAllRead'])->name('seller.notifications.read-all');

    // Account
    Route::get('/account', [SellerAppProfile::class, 'index'])->name('seller.account');
    Route::post('/account/profile', [SellerAppProfile::class, 'updateProfile'])->name('seller.account.profile');
    Route::post('/account/pickup-address', [SellerAppProfile::class, 'updatePickupAddress'])->name('seller.account.pickup-address');
    Route::post('/account/vacation', [SellerAppProfile::class, 'updateVacation'])->name('seller.account.vacation');
    Route::post('/account/password', [SellerAppProfile::class, 'updatePassword'])->name('seller.account.password');
});
