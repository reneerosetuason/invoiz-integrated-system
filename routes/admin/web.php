<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\SellerController;
use App\Http\Controllers\Admin\ComplaintController;
use App\Http\Controllers\Admin\CommissionController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\ChatController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\PaymentController;
use App\Models\Product;
use Illuminate\Http\Request;

Route::prefix('admin')->middleware(['auth', 'marketplace.admin'])->group(function () {
    Route::get('dashboard', function () {
        $orders = \App\Models\Order::query();
        // Revenue counts SETTLED sales only: delivered AND cash handoff
        // confirmed by the seller (payment_status paid). Anything less
        // is still the rider's cash, not platform money.
        $settledBase = \App\Models\Order::where('status', 'delivered')->where('payment_status', 'paid');
        $paidTotal = fn () => \Illuminate\Support\Facades\DB::raw('COALESCE(total_amount, total, 0)');
        $stats = [
            'revenue' => (clone $settledBase)->sum($paidTotal()),
            'today_revenue' => (clone $settledBase)->whereDate('created_at', today())->sum($paidTotal()),
            'commission_earned' => (float) \App\Models\PaymentTransaction::where('status', 'successful')->sum('platform_commission'),
            'logistics_earned' => \Illuminate\Support\Facades\Schema::hasColumn('payment_transactions', 'logistics_fee')
                ? (float) \App\Models\PaymentTransaction::where('status', 'successful')->sum('logistics_fee')
                : 0.0,
            'orders' => (clone $orders)->count(),
            'delivered' => (clone $orders)->where('status', 'delivered')->count(),
            'to_action' => (clone $orders)->whereIn('status', ['new', 'pending'])->count(),
            'to_ship' => (clone $orders)->where('status', 'processing')->count(),
            'products' => \App\Models\Product::count(),
            'low_stock' => class_exists(\App\Models\Inventory::class) ? \App\Models\Inventory::where('quantity', '<=', 5)->count() : 0,
            'rating' => (float) (\App\Models\Review::where('status', 'visible')->avg('rating') ?? 0),
            'sellers' => \App\Models\Seller::approved()->count(),
            'pending_sellers' => \App\Models\Seller::where('approval_status', 'pending')->count(),
            'buyers' => \App\Models\User::where('role', 'buyer')->count(),
            'complaints' => class_exists(\App\Models\Complaint::class) ? \App\Models\Complaint::where('status', 'open')->count() : 0,
        ];
        $chart = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $chart[] = ['label' => $day->format('M d'), 'value' => round((clone $settledBase)->whereDate('created_at', $day->toDateString())->sum($paidTotal()), 2)];
        }
        $recentOrders = \App\Models\Order::with(['buyer', 'seller'])->latest()->take(5)->get();
        $topProducts = \App\Models\OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')->join('products', 'products.id', '=', 'order_items.product_id')->where('orders.status', 'delivered')->where('orders.payment_status', 'paid')->selectRaw('products.name, SUM(order_items.quantity) as units, SUM(order_items.total_price) as sales')->groupBy('products.name')->orderByDesc('sales')->take(5)->get();
        $statusCounts = (clone $orders)->select('status', \Illuminate\Support\Facades\DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status')->map(fn ($v) => (int) $v)->toArray();
        $statusMeta = [
            'pending' => ['Pending', '#F59E0B'], 'confirmed' => ['Confirmed', '#0EA5E9'],
            'processing' => ['Processing', '#3B82F6'], 'ready_for_delivery' => ['Ready', '#8B5CF6'],
            'out_for_delivery' => ['In Transit', '#F97316'], 'shipped' => ['Shipped', '#06B6D4'],
            'delivered' => ['Delivered', '#10B981'], 'cancelled' => ['Cancelled', '#EF4444'],
            'new' => ['New', '#F59E0B'],
        ];
        $statusTotal = array_sum($statusCounts) ?: 1;
        $statusStats = [];
        foreach ($statusCounts as $status => $count) {
            [$label, $color] = $statusMeta[$status] ?? [ucfirst(str_replace('_', ' ', $status)), '#94A3B8'];
            $statusStats[] = [
                'status' => $status, 'label' => $label, 'count' => $count,
                'color' => $color, 'percent' => round($count / $statusTotal * 100),
            ];
        }
        return view('admin.dashboard', compact('stats', 'chart', 'statusStats', 'statusCounts', 'recentOrders', 'topProducts'));
    })->name('admin.dashboard');

    Route::get('sellers', [SellerController::class, 'index'])->name('admin.sellers');
    Route::get('sellers/{seller}', [SellerController::class, 'show'])->name('admin.seller.show');
    Route::get('sellers/{seller}/document/{type}', [SellerController::class, 'viewDocument'])->name('admin.seller.document');
    Route::post('sellers/{seller}/approve', [SellerController::class, 'approve'])->name('admin.seller.approve');
    Route::post('sellers/{seller}/reject', [SellerController::class, 'reject'])->name('admin.seller.reject');
    Route::post('sellers/{seller}/suspend', [SellerController::class, 'suspend'])->name('admin.seller.suspend');
    Route::post('sellers/{seller}/reinstate', [SellerController::class, 'reinstate'])->name('admin.seller.reinstate');
    Route::get('buyers', function (\Illuminate\Http\Request $request) {
        $query = \App\Models\User::where('role', 'buyer')->withCount('orders');
        if ($request->filled('search')) { $s = $request->search; $query->where(fn($qq) => $qq->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")); }
        $buyers = $query->orderBy('created_at', 'desc')->paginate(10);
        $stats = ['all' => \App\Models\User::where('role','buyer')->count(), 'active' => \App\Models\User::where('role','buyer')->where('account_status','active')->count(), 'suspended' => 0];
        return view('admin.buyers', compact('buyers','stats'));
    })->name('admin.buyers');
    Route::get('riders', function (\Illuminate\Http\Request $request) { $r = \App\Models\User::where('role','rider')->orderBy('created_at','desc')->paginate(10); return view('admin.riders', ['riders' => $r]); })->name('admin.riders');
    Route::get('products', [ProductController::class, 'index'])->name('admin.products');
    Route::post('products/{product}/status', [ProductController::class, 'updateStatus'])->name('admin.product.status');
    Route::get('categories', [CategoryController::class, 'index'])->name('admin.categories');
    Route::post('categories', [CategoryController::class, 'store'])->name('admin.categories.store');
    Route::put('categories/{category}', [CategoryController::class, 'update'])->name('admin.categories.update');
    Route::post('categories/{category}/archive', [CategoryController::class, 'archive'])->name('admin.categories.archive');
    Route::post('categories/{category}/restore', [CategoryController::class, 'restore'])->name('admin.categories.restore');
    Route::get('orders', [OrderController::class, 'index'])->name('admin.orders');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('admin.order.show');
    Route::post('orders/{order}/update', [OrderController::class, 'update'])->name('admin.order.update');
        Route::get('payments', [PaymentController::class, 'index'])->name('admin.payments');
        Route::post('payments/payouts/{id}/mark-paid', [\App\Http\Controllers\Admin\PaymentActionController::class, 'markPayoutPaid'])->name('admin.payouts.mark-paid');
        Route::post('payments/withdrawals/{id}/approve', [\App\Http\Controllers\Admin\PaymentActionController::class, 'approveWithdrawal'])->name('admin.withdrawals.approve');
        Route::post('payments/withdrawals/{id}/reject', [\App\Http\Controllers\Admin\PaymentActionController::class, 'rejectWithdrawal'])->name('admin.withdrawals.reject');
        Route::post('payments/disputes/{id}/resolve', [\App\Http\Controllers\Admin\PaymentActionController::class, 'resolveDispute'])->name('admin.disputes.resolve');
    Route::get('manage-accounts', [UserManagementController::class, 'index'])->name('admin.manage-accounts');
    Route::post('manage-accounts/{user}/action', [UserManagementController::class, 'toggleStatus'])->name('admin.manage-accounts.action');
    Route::get('complaints', [ComplaintController::class, 'index'])->name('admin.complaints');
    Route::get('complaints/{complaint}', [ComplaintController::class, 'show'])->name('admin.complaint.show');
    Route::post('complaints/{complaint}/resolve', [ComplaintController::class, 'resolve'])->name('admin.complaint.resolve');
    Route::get('commission', [CommissionController::class, 'index'])->name('admin.commission');
    Route::post('commission/rates', [CommissionController::class, 'updateRate'])->name('admin.commission.rates.update');
    Route::post('commission/rates/add', [CommissionController::class, 'storeRate'])->name('admin.commission.rates.store');
    Route::get('reports', [ReportController::class, 'index'])->name('admin.reports');
    Route::get('reports/export', [ReportController::class, 'export'])->name('admin.reports.export');
    Route::get('reports/print', [ReportController::class, 'printView'])->name('admin.reports.print');
    Route::get('reports/commission', [ReportController::class, 'commission'])->name('admin.reports.commission');
    Route::get('settings', [SettingsController::class, 'index'])->name('admin.settings');
    Route::post('settings/update', [SettingsController::class, 'update'])->name('admin.settings.update');
    Route::post('settings/announcement', [SettingsController::class, 'save'])->name('admin.settings.announcement');
    Route::post('settings/policy', [SettingsController::class, 'createPolicy'])->name('admin.settings.policy.create');
    Route::put('settings/policy/{policy}', [SettingsController::class, 'updatePolicy'])->name('admin.settings.policy.update');
    Route::get('chat', [ChatController::class, 'index'])->name('admin.chat');
    Route::post('chat/send', [ChatController::class, 'send'])->name('admin.chat.send');
    Route::get('chat/{user}/messages', [ChatController::class, 'messages'])->name('admin.chat.messages');
    // Route::get('/logistics', function () {
    // return redirect('http://127.0.0.1:8000');
// })->name('logistics');

});
