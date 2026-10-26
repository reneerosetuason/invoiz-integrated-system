<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentDispute;
use App\Models\Order;
use App\Models\Seller;
use Illuminate\Support\Facades\DB;
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
        $created = DB::transaction(function () use ($id): bool {
            $record = WithdrawalRequest::findOrFail($id);
            Seller::query()->whereKey($record->seller_id)->lockForUpdate()->firstOrFail();
            $withdrawal = WithdrawalRequest::query()->lockForUpdate()->findOrFail($id);
            if ($withdrawal->status !== 'pending') {
                return false;
            }
            SellerPayout::create([
                'seller_id' => $withdrawal->seller_id,
                'amount' => $withdrawal->amount,
                'status' => 'pending',
                'method' => $withdrawal->method,
            ]);
            $withdrawal->update(['status' => 'approved', 'processed_at' => now()]);
            return true;
        });
        if (! $created) {
            return back()->with('status', 'That withdrawal was already processed.');
        }

        return back()->with('status', 'Withdrawal approved and a payout was created.');
    }

    public function rejectWithdrawal(Request $request, $id)
    {
        $rejected = DB::transaction(function () use ($id, $request): bool {
            $record = WithdrawalRequest::findOrFail($id);
            Seller::query()->whereKey($record->seller_id)->lockForUpdate()->firstOrFail();
            $withdrawal = WithdrawalRequest::query()->lockForUpdate()->findOrFail($id);
            if ($withdrawal->status !== 'pending') {
                return false;
            }
            $withdrawal->update([
                'status' => 'rejected',
                'note' => $request->input('note'),
                'processed_at' => now(),
            ]);
            return true;
        });
        if (! $rejected) {
            return back()->with('status', 'That withdrawal was already processed.');
        }

        return back()->with('status', 'Withdrawal rejected.');
    }

    public function resolveDispute(Request $request, $id)
    {
        $validated = $request->validate([
            'resolution' => 'required|in:refund_buyer,reject',
            'note'       => 'nullable|string|max:500',
        ]);

        $status = DB::transaction(function () use ($id, $validated): ?string {
            $record = PaymentDispute::findOrFail($id);
            $order = Order::query()->lockForUpdate()->findOrFail($record->order_id);
            $dispute = PaymentDispute::query()->lockForUpdate()->findOrFail($id);
            if (in_array($dispute->status, ['resolved', 'rejected'], true)) {
                return null;
            }
            if ($validated['resolution'] === 'refund_buyer') {
                Payments::refund($order, (float) $dispute->amount ?: null, $validated['note'] ?? null);
                $status = 'resolved';
            } else {
                $status = 'rejected';
            }
            $dispute->update([
                'status' => $status,
                'resolution_note' => $validated['note'] ?? null,
                'resolved_at' => now(),
            ]);
            return $status;
        });
        if ($status === null) {
            return back()->with('status', 'That dispute was already processed.');
        }

        return back()->with('status', $status === 'resolved'
            ? 'Dispute resolved — the buyer was refunded.'
            : 'Dispute rejected.');
    }
}
