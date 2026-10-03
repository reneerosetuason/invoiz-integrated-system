<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentDispute;
use App\Models\PaymentTransaction;
use App\Models\SellerPayout;
use App\Models\WithdrawalRequest;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $txQuery = PaymentTransaction::with('order.seller', 'order.buyer');

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
        $transactions = (clone $txQuery)->orderByDesc('created_at')->get();

        $payouts = SellerPayout::with('seller')->orderByDesc('created_at')->get();
        $payoutStats = [
            'paid_total' => (clone $payouts)->where('status', 'paid')->sum('amount'),
            'pending_total' => (clone $payouts)->where('status', 'pending')->sum('amount'),
        ];

        $withdrawals = WithdrawalRequest::with('seller')->orderByDesc('created_at')->get();

        $disputes = PaymentDispute::with('order', 'buyer', 'seller')->orderByDesc('created_at')->get();

        return view('admin.payments', compact(
            'stats', 'transactions', 'status', 'payouts', 'payoutStats', 'withdrawals', 'disputes'
        ));
    }

    public function markPayoutPaid(Request $request, SellerPayout $payout)
    {
        $payout->status = 'paid';
        $payout->paid_at = now();
        $payout->reference = $payout->reference ?: 'PAYOUT-' . str_pad($payout->id, 4, '0', STR_PAD_LEFT);
        $payout->save();

        return redirect()->back()->with('status', "Payout of ₱" . number_format($payout->amount, 2) . " marked as paid.");
    }

    public function approveWithdrawal(Request $request, WithdrawalRequest $withdrawal)
    {
        $withdrawal->status = 'approved';
        $withdrawal->processed_at = now();
        $withdrawal->save();

        SellerPayout::create([
            'seller_id' => $withdrawal->seller_id,
            'amount' => $withdrawal->amount,
            'status' => 'pending',
            'method' => $withdrawal->method,
        ]);

        return redirect()->back()->with('status', 'Withdrawal approved and queued for payout.');
    }

    public function rejectWithdrawal(Request $request, WithdrawalRequest $withdrawal)
    {
        $withdrawal->status = 'rejected';
        $withdrawal->processed_at = now();
        $withdrawal->note = $request->input('reason', $withdrawal->note);
        $withdrawal->save();

        return redirect()->back()->with('status', 'Withdrawal request rejected.');
    }

    public function resolveDispute(Request $request, PaymentDispute $dispute)
    {
        $request->validate([
            'resolution' => 'required|in:refund_buyer,reject_claim,under_review',
        ]);

        if ($request->resolution === 'under_review') {
            $dispute->status = 'under_review';
        } elseif ($request->resolution === 'reject_claim') {
            $dispute->status = 'rejected';
            $dispute->resolved_at = now();
        } else {
            $dispute->status = 'resolved';
            $dispute->resolved_at = now();

            if ($dispute->order && $dispute->order->payment_status === 'paid') {
                $dispute->order->payment_status = 'refunded';
                $dispute->order->save();

                $tx = PaymentTransaction::where('order_id', $dispute->order_id)->first();
                if ($tx) {
                    $tx->status = 'refunded';
                    $tx->refunded_amount = $tx->amount;
                    $tx->seller_earning = 0;
                    $tx->platform_commission = 0;
                    $tx->save();
                }
            }
        }

        $dispute->resolution_note = $request->input('note', $dispute->resolution_note);
        $dispute->save();

        return redirect()->back()->with('status', 'Dispute updated.');
    }
}
