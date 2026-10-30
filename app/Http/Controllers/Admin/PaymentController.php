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

        $hasEscrowBreakdown = \Illuminate\Support\Facades\Schema::hasColumn('payment_transactions', 'origin_hub_fee');
        $hasEscrowTable = \Illuminate\Support\Facades\Schema::hasTable('escrow_remittances');

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
            'origin_hub_fees' => $hasEscrowBreakdown ? (float) (clone $txQuery)->where('status', 'successful')->sum('origin_hub_fee') : 0.0,
            'pickup_rider_fees' => $hasEscrowBreakdown ? (float) (clone $txQuery)->where('status', 'successful')->sum('pickup_rider_fee') : 0.0,
            'inter_hub_fees' => $hasEscrowBreakdown ? (float) (clone $txQuery)->where('status', 'successful')->sum('inter_hub_driver_fee') : 0.0,
            'destination_hub_fees' => $hasEscrowBreakdown ? (float) (clone $txQuery)->where('status', 'successful')->sum('destination_hub_fee') : 0.0,
            'last_mile_rider_fees' => $hasEscrowBreakdown ? (float) (clone $txQuery)->where('status', 'successful')->sum('last_mile_rider_fee') : 0.0,
        ];

        $status = $request->query('status');
        // Stat cards stay global; the list honors the status pill server-side
        // (the old client-side row filter only saw the current page).
        $transactions = (clone $txQuery)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')->paginate(10, ['*'], 'tx_page');

        $escrowRemittances = $hasEscrowTable
            ? \App\Models\EscrowRemittance::with(['order', 'delivery', 'rider', 'logisticsCenter', 'recipientUser'])
                ->orderByDesc('created_at')
                ->paginate(15, ['*'], 'remit_page')
            : null;

        $escrowTotals = $hasEscrowTable ? [
            'held'    => (float) \App\Models\EscrowRemittance::where('status', 'held_in_escrow')->sum('amount'),
            'pending' => (float) \App\Models\EscrowRemittance::where('status', 'pending_remittance')->sum('amount'),
            'remitted'=> (float) \App\Models\EscrowRemittance::where('status', 'remitted')->sum('amount'),
        ] : ['held' => 0, 'pending' => 0, 'remitted' => 0];

        return view('admin.payments', compact('stats', 'transactions', 'status', 'escrowRemittances', 'escrowTotals'));
    }
}
