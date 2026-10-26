<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $txQuery = PaymentTransaction::with(
            'order.seller',
            'order.buyer',
            'order.items.seller.seller',
            'order.items.product.seller'
        );

        $stats = [
            'all' => (clone $txQuery)->count(),
            'all_amount' => (clone $txQuery)->sum('amount'),
            'successful' => (clone $txQuery)->where('status', 'successful')->count(),
            'successful_amount' => (clone $txQuery)->where('status', 'successful')->sum('amount'),
            'pending' => (clone $txQuery)->where('status', 'pending')->count(),
            'pending_amount' => (clone $txQuery)->where('status', 'pending')->sum('amount'),
            'failed' => (clone $txQuery)->where('status', 'failed')->count(),
            'failed_amount' => (clone $txQuery)->where('status', 'failed')->sum('amount'),
            'refunded' => (clone $txQuery)->whereIn('status', ['refunded', 'partially_refunded'])->count(),
            'refunded_amount' => (clone $txQuery)->sum('refunded_amount'),
            'partially_refunded' => (clone $txQuery)->where('status', 'partially_refunded')->count(),
            'admin_earnings' => (clone $txQuery)->where('status', 'successful')->sum('platform_commission'),
            'commission' => (clone $txQuery)->where('status', 'successful')->sum('platform_commission'),
            'rider_fees' => (clone $txQuery)->where('status', 'successful')->sum('rider_fee'),
            'seller_earnings' => (clone $txQuery)->where('status', 'successful')->sum('seller_earning'),
        ];

        $status = $request->query('status');
        // Stat cards stay global; the list honors the status pill server-side
        // (the old client-side row filter only saw the current page).
        $transactions = (clone $txQuery)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')->paginate(10, ['*'], 'tx_page');

        return view('admin.payments', compact('stats', 'transactions', 'status'));
    }
}
