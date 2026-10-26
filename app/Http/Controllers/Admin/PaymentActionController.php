<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentDispute;
use App\Models\SellerPayout;
use App\Models\WithdrawalRequest;
use App\Support\Payments;
use Illuminate\Http\Request;

class PaymentActionController extends Controller
{
    public function markPayoutPaid($id)
    {
        $payout = SellerPayout::findOrFail($id);

        if ($payout->status === 'paid') {
            return back()->with('status', 'That payout is already marked paid.');
        }

        $payout->update(['status' => 'paid', 'paid_at' => now()]);

        return back()->with('status', 'Payout marked as paid.');
    }

    public function approveWithdrawal($id)
    {
        $withdrawal = WithdrawalRequest::findOrFail($id);

        if ($withdrawal->status !== 'pending') {
            return back()->with('status', 'That withdrawal was already processed.');
        }

        $withdrawal->update(['status' => 'approved', 'processed_at' => now()]);

        // Approval provisions a payout for the finance team to settle.
        SellerPayout::create([
            'seller_id' => $withdrawal->seller_id,
            'amount'    => $withdrawal->amount,
            'status'    => 'pending',
            'method'    => $withdrawal->method,
        ]);

        return back()->with('status', 'Withdrawal approved and a payout was created.');
    }

    public function rejectWithdrawal(Request $request, $id)
    {
        $withdrawal = WithdrawalRequest::findOrFail($id);

        if ($withdrawal->status !== 'pending') {
            return back()->with('status', 'That withdrawal was already processed.');
        }

        $withdrawal->update([
            'status'       => 'rejected',
            'note'         => $request->input('note'),
            'processed_at' => now(),
        ]);

        return back()->with('status', 'Withdrawal rejected.');
    }

    public function resolveDispute(Request $request, $id)
    {
        $validated = $request->validate([
            'resolution' => 'required|in:refund_buyer,reject',
            'note'       => 'nullable|string|max:500',
        ]);

        $dispute = PaymentDispute::with('order')->findOrFail($id);

        if ($validated['resolution'] === 'refund_buyer') {
            Payments::refund($dispute->order, (float) $dispute->amount ?: null, $validated['note'] ?? null);
            $status = 'resolved';
        } else {
            $status = 'rejected';
        }

        $dispute->update([
            'status'          => $status,
            'resolution_note' => $validated['note'] ?? null,
            'resolved_at'     => now(),
        ]);

        return back()->with('status', $status === 'resolved'
            ? 'Dispute resolved — the buyer was refunded.'
            : 'Dispute rejected.');
    }
}
