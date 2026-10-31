<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EscrowRemittance;
use App\Models\PaymentDispute;
use App\Models\Order;
use App\Models\Seller;
use Illuminate\Support\Facades\DB;
use App\Models\SellerPayout;
use App\Models\WithdrawalRequest;
use App\Support\AuditLogger;
use App\Support\Payments;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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

    /**
     * Release one escrow remittance. Only rows already marked ready
     * (pending_remittance) are payable; held-in-escrow money must stay
     * locked until the logistics workflow marks it earned.
     */
    public function remitEscrow(Request $request, $id)
    {
        $released = DB::transaction(function () use ($request, $id): ?array {
            $remittance = EscrowRemittance::query()->lockForUpdate()->findOrFail($id);
            if ($remittance->status !== 'pending_remittance') {
                return null;
            }

            $reference = $request->input('bank_reference') ?: ('REMIT-' . strtoupper(Str::random(8)));
            $remittance->update([
                'status'         => 'remitted',
                'bank_reference' => $reference,
                'remitted_at'    => now(),
                'remitted_by'    => auth()->id(),
            ]);

            // Auto-approve and collect the admin's 10% commission for this order
            if ($remittance->order_id) {
                EscrowRemittance::where('order_id', $remittance->order_id)
                    ->where('recipient_role', EscrowRemittance::ROLE_ADMIN)
                    ->where('status', '!=', 'remitted')
                    ->update([
                        'status'         => 'remitted',
                        'bank_reference' => 'ADMIN-COMM-' . $remittance->order_id,
                        'remitted_at'    => now(),
                        'remitted_by'    => auth()->id(),
                        'notes'          => 'Automatically collected 10% platform commission upon payout release.',
                    ]);
            }

            (new AuditLogger)->record(
                auth()->user(),
                'escrow.remit',
                'escrow_remittance',
                $remittance->id,
                ['status' => 'pending_remittance'],
                ['status' => 'remitted', 'bank_reference' => $reference],
                'Escrow bank remittance released'
            );

            return [
                'amount' => (float) $remittance->amount,
                'name' => $remittance->recipient_name,
                'reference' => $reference,
            ];
        });

        if ($released === null) {
            return back()->with('status', 'That remittance is not ready for release.');
        }

        return back()->with('status', 'Remittance of ₱' . number_format($released['amount'], 2)
            . " released to {$released['name']} (Ref: {$released['reference']}). Admin 10% commission booked.");
    }

    public function remitAllPendingEscrow(Request $request)
    {
        $batch = 'BATCH-REMIT-' . strtoupper(Str::random(8));

        $result = DB::transaction(function () use ($batch): array {
            $rows = EscrowRemittance::query()
                ->where('status', 'pending_remittance')
                ->lockForUpdate()
                ->get();

            $count = 0;
            $total = 0.0;
            foreach ($rows as $row) {
                $row->update([
                    'status'         => 'remitted',
                    'bank_reference' => $batch,
                    'remitted_at'    => now(),
                    'remitted_by'    => auth()->id(),
                ]);

                (new AuditLogger)->record(
                    auth()->user(),
                    'escrow.remit',
                    'escrow_remittance',
                    $row->id,
                    ['status' => 'pending_remittance'],
                    ['status' => 'remitted', 'bank_reference' => $batch],
                    'Batch escrow bank remittance released'
                );

                $count++;
                $total += (float) $row->amount;
            }

            // Auto-approve and collect platform commission for all orders in this batch
            $orderIds = $rows->pluck('order_id')->filter()->unique();
            if ($orderIds->isNotEmpty()) {
                EscrowRemittance::whereIn('order_id', $orderIds)
                    ->where('recipient_role', EscrowRemittance::ROLE_ADMIN)
                    ->where('status', '!=', 'remitted')
                    ->update([
                        'status'         => 'remitted',
                        'bank_reference' => 'ADMIN-COMM-BATCH',
                        'remitted_at'    => now(),
                        'remitted_by'    => auth()->id(),
                        'notes'          => 'Automatically collected 10% platform commission upon batch payout release.',
                    ]);
            }

            return ['count' => $count, 'total' => $total];
        });

        if ($result['count'] === 0) {
            return back()->with('status', 'No escrow remittances are ready for release.');
        }

        return back()->with('status', "Released {$result['count']} escrow remittance(s) totalling ₱"
            . number_format($result['total'], 2) . " (Batch: {$batch}). Admin 10% commissions booked.");
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
