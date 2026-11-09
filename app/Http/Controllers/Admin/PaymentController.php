<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

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

        $hasEscrowBreakdown = Schema::hasColumn('payment_transactions', 'origin_hub_fee');
        $hasEscrowTable = Schema::hasTable('escrow_remittances');

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
            'admin_earnings' => $hasEscrowTable ? (float) \App\Models\EscrowRemittance::acceptedAdminCommission()->sum('amount') : 0.0,
            'commission' => $hasEscrowTable ? (float) \App\Models\EscrowRemittance::acceptedAdminCommission()->sum('amount') : 0.0,
            'rider_fees' => (clone $txQuery)->whereIn('status', ['successful', 'partially_refunded', 'refunded'])->sum('rider_fee'),
            'seller_earnings' => (clone $txQuery)->whereIn('status', ['successful', 'partially_refunded', 'refunded'])->sum('seller_earning'),
            'origin_hub_fees' => $hasEscrowBreakdown ? (float) (clone $txQuery)->whereIn('status', ['successful', 'partially_refunded', 'refunded'])->sum('origin_hub_fee') : 0.0,
            'pickup_rider_fees' => $hasEscrowBreakdown ? (float) (clone $txQuery)->whereIn('status', ['successful', 'partially_refunded', 'refunded'])->sum('pickup_rider_fee') : 0.0,
            'inter_hub_fees' => $hasEscrowBreakdown ? (float) (clone $txQuery)->whereIn('status', ['successful', 'partially_refunded', 'refunded'])->sum('inter_hub_driver_fee') : 0.0,
            'destination_hub_fees' => $hasEscrowBreakdown ? (float) (clone $txQuery)->whereIn('status', ['successful', 'partially_refunded', 'refunded'])->sum('destination_hub_fee') : 0.0,
            'last_mile_rider_fees' => $hasEscrowBreakdown ? (float) (clone $txQuery)->whereIn('status', ['successful', 'partially_refunded', 'refunded'])->sum('last_mile_rider_fee') : 0.0,
        ];

        $status = $request->query('status');
        // Stat cards stay global; the list honors the status pill server-side
        // (the old client-side row filter only saw the current page).
        $transactions = (clone $txQuery)
            ->when($status, fn ($q) => $status === 'refunded' ? $q->whereIn('status', ['refunded', 'partially_refunded']) : $q->where('status', $status))
            ->orderByDesc('created_at')->paginate(10, ['*'], 'tx_page');

        $orderPayouts=$hasEscrowTable?\App\Models\EscrowRemittance::with(['order','delivery','logisticsCenter','hubTransfer'])
            ->where('recipient_role','!=',\App\Models\EscrowRemittance::ROLE_ADMIN)->get():collect();
        $returnPayouts=Schema::hasTable('return_allocations')?\App\Models\ReturnAllocation::whereIn('role',\App\Support\ReturnRemittanceInbox::ROLES)
            ->with(['settlement.order','recipientUser','rider','driver','center'])->get():collect();
        $combinedPayouts=\App\Support\RemittanceFeed::combine($orderPayouts,$returnPayouts);
        $escrowRemittances=\App\Support\RemittanceFeed::paginate($combinedPayouts,15,'remit_page');
        $returnFundingReceipts=Schema::hasTable('return_fund_payments')?\App\Models\ReturnFundPayment::with('settlement.returnRequest.item')
            ->where('status','submitted')->latest('id')->paginate(10,['*'],'funding_page'):null;
        $escrowAdjustments=Schema::hasTable('return_allocations')?\App\Models\ReturnAllocation::whereNotIn('role',\App\Support\ReturnRemittanceInbox::ROLES)
            ->whereIn('status',['eligible','remitted'])->count():0;

        $escrowTotals = $hasEscrowTable ? [
            'held' => (float) \App\Models\EscrowRemittance::where('status', 'held_in_escrow')->sum('amount'),
            'pending' => (float) \App\Models\EscrowRemittance::where('status', 'pending_remittance')->sum('amount'),
            'remitted' => (float) \App\Models\EscrowRemittance::where('status', 'remitted')->sum('amount'),
        ] : ['held' => 0, 'pending' => 0, 'remitted' => 0];
        foreach(['held'=>'held_in_escrow','pending'=>'pending_remittance','remitted'=>'remitted'] as $key=>$state)
            $escrowTotals[$key]+=(float)$combinedPayouts->where('source','return')->where('status',$state)->sum('amount');

        $adminRemittanceQuery = \App\Models\EscrowRemittance::where('recipient_role', \App\Models\EscrowRemittance::ROLE_ADMIN)->where('amount', '>', 0);
        $adminRemittances = $hasEscrowTable ? (clone $adminRemittanceQuery)->with(['order.payment', 'delivery'])->latest('id')
            ->paginate(10, ['*'], 'admin_remit_page')->withQueryString() : null;
        $adminRemittanceTotals = $hasEscrowTable ? [
            'accepted' => (float) (clone $adminRemittanceQuery)->where('status', 'accepted')->sum('amount'),
            'remitted' => (float) (clone $adminRemittanceQuery)->where('status', 'remitted')->sum('amount'),
            'awaiting_release' => (float) (clone $adminRemittanceQuery)->whereIn('status', ['held_in_escrow', 'pending_remittance'])->sum('amount'),
        ] : ['accepted' => 0, 'remitted' => 0, 'awaiting_release' => 0];

        $pendingEscrowRemittances = $hasEscrowTable ? \App\Models\EscrowRemittance::where('status', 'pending_remittance')
            ->whereDoesntHave('order.payment', fn ($query) => $query->where('method', 'gcash'))->get() : collect();

        return view('admin.payments', compact('stats', 'transactions', 'status', 'escrowRemittances', 'escrowTotals', 'adminRemittances', 'adminRemittanceTotals', 'pendingEscrowRemittances','returnFundingReceipts','escrowAdjustments'));
    }
}
