<?php

namespace App\Services;

use App\Models\GcashEscrowDeposit;
use App\Models\Order;
use App\Models\Seller;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\EscrowEarnings;
use App\Support\Vouchers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class GcashEscrowService
{
    public static function quoteForStores(array $subtotals, iterable $vouchers): array
    {
        $vouchers = collect($vouchers);
        $stack = Vouchers::resolveStack($vouchers, array_sum($subtotals), array_keys($subtotals), $subtotals);
        if ($stack['error']) {
            throw ValidationException::withMessages(['voucher_claim_ids' => $stack['error']]);
        }
        $allocations = Vouchers::allocateForOrders($vouchers, $stack, $subtotals);
        $totals = [];
        foreach ($subtotals as $sid => $subtotal) {
            $totals[$sid] = round($subtotal + Order::SHIPPING_FEE_PER_SELLER - array_sum($allocations[$sid] ?? []), 2);
        }
        $subtotal = round(array_sum($subtotals), 2);
        $shipping = count($subtotals) * Order::SHIPPING_FEE_PER_SELLER;
        $total = round(array_sum($totals), 2);

        return ['accounts' => self::accounts($totals), 'subtotal' => $subtotal, 'shipping_fee' => $shipping,
            'discount' => round($subtotal + $shipping - $total, 2), 'total' => $total];
    }

    public static function accounts(array $totals): array
    {
        return collect($totals)->map(function ($total, $id) {
            $seller = Seller::approved()->where('user_id', $id)->first();
            $hasQr = $seller?->gcash_qr_path && Storage::disk('public')->exists($seller->gcash_qr_path);

            return ['seller_id' => (int) $id, 'store_name' => $seller?->display_name ?? 'Store #'.$id,
                'account_name' => $seller?->gcash_account_name, 'amount' => round($total, 2),
                'qr_url' => $hasQr ? route('buyer.gcash.qr', ['seller' => $id]) : null, 'available' => (bool) $hasQr,
                'unavailable_reason' => $hasQr ? null : 'seller_qr_missing'];
        })->values()->all();
    }

    public static function storeCheckoutProofs(Request $request, array $sellerIds, array &$paths): void
    {
        if ($request->input('payment_method') !== 'gcash') {
            return;
        }
        foreach ($sellerIds as $sellerId) {
            self::checkoutSeller((int) $sellerId);
            $file = $request->file('gcash_proofs.'.$sellerId);
            if (count($sellerIds) === 1) {
                $file ??= $request->file('gcash_proof');
            }
            if (! $file) {
                throw ValidationException::withMessages(['gcash_proof' => 'Upload a separate payment receipt for each store. Missing receipt for store #'.$sellerId.'.']);
            }
            $paths[$sellerId] = $file->store('payment-proofs', 'private');
        }
    }

    public static function isGcash(Order $order): bool
    {
        return $order->payment?->method === 'gcash';
    }

    public static function funded(Order $order): bool
    {
        $deposit = GcashEscrowDeposit::where('order_id', $order->id)->first();

        return $deposit && $deposit->status === 'funded' && $deposit->confirmed_at
            && $order->payment_status === 'paid' && $order->payment?->status === 'paid'
            && (int) round((float) $deposit->amount * 100) === (int) round((float) $order->total_amount * 100);
    }

    public static function assertFunded(Order $order): void
    {
        if (self::isGcash($order) && ! self::funded($order)) {
            throw ValidationException::withMessages(['gcash' => 'Verify the buyer receipt and remit the full order payment to escrow. Admin must confirm the deposit before this order can proceed.']);
        }
    }

    public static function checkoutSeller(int $sellerId): Seller
    {
        $seller = Seller::approved()->where('user_id', $sellerId)->first();
        if (! $seller || ! $seller->gcash_qr_path || ! Storage::disk('public')->exists($seller->gcash_qr_path)) {
            throw ValidationException::withMessages(['payment_method' => 'This store has not added a GCash receiving QR yet. Choose COD or contact the store.']);
        }

        return $seller;
    }

    public static function verifyReceipt(Order $order, User $seller): GcashEscrowDeposit
    {
        return DB::transaction(function () use ($order, $seller) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            self::assertSeller($order, $seller);
            if (! $order->payment?->has_payment_proof) {
                throw ValidationException::withMessages(['gcash' => 'A buyer receipt is required.']);
            }
            $deposit = GcashEscrowDeposit::firstOrCreate(['order_id' => $order->id], [
                'seller_id' => $seller->id, 'amount' => $order->total_amount,
                'status' => 'receipt_verified', 'receipt_verified_at' => now(),
            ]);
            self::audit($seller, 'gcash.receipt_verified', $deposit);

            return $deposit;
        });
    }

    public static function assertSeller(Order $order, User $seller): void
    {
        abort_unless($order->items()->where('seller_id', $seller->id)->exists(), 404);
        abort_if($order->items()->where('seller_id', '!=', $seller->id)->exists(), 409, 'A shared legacy order needs admin review before funding.');
        if (! self::isGcash($order) || in_array($order->status, ['cancelled', 'refunded', 'returned'], true)) {
            throw ValidationException::withMessages(['gcash' => 'This order cannot receive an escrow deposit.']);
        }
    }

    public static function audit(User $actor, string $action, GcashEscrowDeposit $deposit): void
    {
        (new AuditLogger)->record($actor, $action, 'gcash_escrow_deposit', $deposit->id, [], [
            'order_id' => $deposit->order_id, 'amount' => $deposit->amount, 'status' => $deposit->status,
            'reference' => $deposit->reference,
            'proof_path' => $deposit->proof_path,
        ], 'Manual GCash escrow reconciliation');
    }

    public static function confirm(GcashEscrowDeposit $deposit, User $admin, bool $approved, string $note): void
    {
        abort_unless($admin->isAdmin(), 403);
        DB::transaction(function () use ($deposit, $admin, $approved, $note) {
            $order = Order::lockForUpdate()->findOrFail($deposit->order_id);
            $deposit = GcashEscrowDeposit::lockForUpdate()->findOrFail($deposit->id);
            if ($deposit->status !== 'submitted' || in_array($order->status, ['cancelled', 'refunded', 'returned'], true)) {
                throw ValidationException::withMessages(['gcash' => 'This deposit is no longer awaiting review.']);
            }
            if (! self::isGcash($order) || (int) round((float) $deposit->amount * 100) !== (int) round((float) $order->total_amount * 100)) {
                throw ValidationException::withMessages(['amount' => 'Escrow must receive the whole buyer payment, including shipping.']);
            }
            $deposit->update(['status' => $approved ? 'funded' : 'rejected', 'confirmed_at' => $approved ? now() : null,
                'confirmed_by' => $admin->id, 'review_note' => $note]);
            if ($approved) {
                $order->payment->update(['status' => 'paid', 'paid_at' => now()]);
                $order->update(['payment_status' => 'paid']);
                // Settlement remains pending until fulfilment; no seller wallet credit here.
                EscrowEarnings::syncRemittancesForOrder($order);
            }
            self::audit($admin, $approved ? 'gcash.escrow_confirmed' : 'gcash.escrow_rejected', $deposit);
        });
    }
}
