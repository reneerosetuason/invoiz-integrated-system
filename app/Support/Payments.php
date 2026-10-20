<?php

namespace App\Support;

use App\Models\CommissionRate;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Seller;
use App\Models\SellerPayout;
use App\Models\WithdrawalRequest;

/**
 * Single home for payment money wiring so orders, payments,
 * payment_status and payment_transactions always agree:
 *
 * - Checkout creates the buyer Payment (pending) + a pending ledger row.
 * - Seller marking delivered settles it: buyer total splits into
 *   seller_earning + platform_commission + rider_fee (always balances to
 *   the order total), payment flips to paid, order payment_status to paid.
 * - Cancelling voids a pending ledger row (COD money never moved).
 * - Disputes refund through the same ledger.
 * - Voucher money never touches the rider: rider_fee is buyer shipping
 *   only; absorbed discounts come out of the seller's share.
 */
class Payments
{
    /** Platform cut applied to net merchandise earnings. */
    public static function defaultCommissionRate(): float
    {
        try {
            $rate = CommissionRate::whereNull('category_id')->value('rate');
            if ($rate !== null) {
                return (float) $rate;
            }
        } catch (\Throwable $e) {
        }
        return 10.0;
    }

    /** All seller identity flavours one user id can own (sellers.id + user id). */
    public static function catalogIdsForUser(int $userId): array
    {
        $ids = [$userId];
        try {
            $seller = Seller::where('user_id', $userId)->first();
            if ($seller) {
                $ids = array_merge($ids, $seller->catalogIds());
            }
        } catch (\Throwable $e) {
        }
        return array_values(array_unique(array_map('intval', $ids)));
    }

    /** Ledger row for an order (buyer total split once known). */
    public static function transactionFor(Order $order): ?PaymentTransaction
    {
        return PaymentTransaction::where('order_id', $order->id)->first();
    }

    /** Create the pending ledger row at checkout (idempotent). */
    public static function recordCheckout(Order $order): void
    {
        try {
            if (self::transactionFor($order)) {
                return;
            }
            PaymentTransaction::create([
                'order_id'        => $order->id,
                'transaction_ref' => 'TXN-ORDER-' . $order->id,
                'gateway'         => 'cash_on_delivery',
                'amount'          => round((float) ($order->total_amount ?? $order->total ?? 0), 2),
                'status'          => 'pending',
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Payment ledger checkout failed: ' . $e->getMessage());
        }
    }

    /**
     * Per-seller money split for one order, recomputed from live data so
     * ledger, dashboard and reports can never disagree.
     *
     * @return array [sellerKey => ['merch' => net merch, 'commission' => cut, 'waived' => absorbed shipping, 'earning' => payable]]
     */
    public static function splitForSellers(Order $order, ?float $rate = null): array
    {
        $order->loadMissing(['items', 'orderVouchers.voucher']);
        $rate = $rate ?? self::defaultCommissionRate();

        $groups = [];
        foreach ($order->items as $item) {
            $sid = (int) $item->seller_id;
            $line = $item->lineTotal ?? ((float) ($item->unit_price ?? $item->price ?? 0)) * ((int) ($item->quantity ?? 0));
            if (! isset($groups[$sid])) {
                $groups[$sid] = ['sub' => 0.0];
            }
            $groups[$sid]['sub'] += $line;
        }
        $out = [];
        foreach ($groups as $sid => $g) {
            // Canonical per-seller math (capped shares, loaded relations).
            // The seller funds the flat logistics fee per delivery so the
            // admin cut stays exactly the configured rate.
            $merchNet = round($g['sub'] - $order->voucherDiscountForSeller($sid), 2);
            $commission = round($merchNet * $rate / 100, 2);
            $waived = $order->shippingWaivedForSeller($sid);
            $logistics = $order->logisticsFeeForSeller($sid);
            $out[$sid] = [
                'merch'      => $merchNet,
                'commission' => $commission,
                'waived'     => $waived,
                'logistics'  => $logistics,
                'earning'    => round(max(0, $merchNet - $commission - $waived - $logistics), 2),
            ];
        }
        return $out;
    }

    /**
     * Settle when the seller confirms the rider's cash handoff (COD cash
     * verified in hand): payment + order flag flip to paid and the ledger
     * row books the final split. Idempotent unless forced.
     */
    public static function settleForDelivery(Order $order, bool $force = false): void
    {
        try {
            $order->loadMissing(['items', 'orderVouchers.voucher']);
            $split = self::splitForSellers($order);
            $sellerEarning = round(array_sum(array_column($split, 'earning')), 2);
            $commission = round(array_sum(array_column($split, 'commission')), 2);
            $riderFee = round($order->shippingTotal(), 2);
            $logisticsFee = round(count($order->shippingGroupIds()) * Order::LOGISTICS_FEE_PER_DELIVERY, 2);

            $tx = self::transactionFor($order);
            if (! $tx) {
                $tx = PaymentTransaction::create([
                    'order_id'        => $order->id,
                    'transaction_ref' => 'TXN-ORDER-' . $order->id,
                    'gateway'         => 'cash_on_delivery',
                    'amount'          => round((float) ($order->total_amount ?? $order->total ?? 0), 2),
                    'status'          => 'pending',
                ]);
            }
            if ($tx->status === 'successful' && ! $force) {
                return;
            }
            $tx->update([
                'status'              => 'successful',
                'seller_earning'      => $sellerEarning,
                'platform_commission' => $commission,
                'rider_fee'           => $riderFee,
                'logistics_fee'       => $logisticsFee,
                'paid_at'             => now(),
            ]);
            $order->update([
                'payment_status'    => 'paid',
                'commission_amount' => $commission,
            ]);
            if ($order->payment) {
                $order->payment->update(['status' => 'paid', 'paid_at' => now()]);
            }
        } catch (\Throwable $e) {
            \Log::warning('Payment settle failed: ' . $e->getMessage());
        }
    }

    /** Void a pending ledger row when an unpaid order is cancelled. */
    public static function voidForCancel(Order $order): void
    {
        try {
            $tx = self::transactionFor($order);
            if ($tx && $tx->status === 'pending') {
                $tx->update(['status' => 'failed']);
            } elseif (! $tx) {
                PaymentTransaction::create([
                    'order_id'        => $order->id,
                    'transaction_ref' => 'TXN-ORDER-' . $order->id,
                    'gateway'         => 'cash_on_delivery',
                    'amount'          => round((float) ($order->total_amount ?? $order->total ?? 0), 2),
                    'status'          => 'failed',
                ]);
            }
        } catch (\Throwable $e) {
            \Log::warning('Payment void failed: ' . $e->getMessage());
        }
    }

    /**
     * Refund (part of) an order to the buyer through the ledger. Marks the
     * order fully refunded when the whole total is returned, otherwise
     * partially refunded. Never throws.
     */
    public static function refund(Order $order, ?float $amount = null, ?string $note = null): void
    {
        try {
            $order->loadMissing('items');
            $tx = self::transactionFor($order);
            $total = (float) ($order->total_amount ?? $order->total ?? 0);

            if (! $tx) {
                $tx = PaymentTransaction::create([
                    'order_id'        => $order->id,
                    'transaction_ref' => 'TXN-ORDER-' . $order->id,
                    'gateway'         => 'cash_on_delivery',
                    'amount'          => round($total, 2),
                    'status'          => 'pending',
                ]);
            }

            $refund = $amount === null ? $total : (float) $amount;
            $refundedTotal = round((float) $tx->refunded_amount + $refund, 2);
            $full = $refundedTotal >= round($total, 2) - 0.001;

            $tx->update([
                'status'          => $full ? 'refunded' : 'partially_refunded',
                'refunded_amount' => $refundedTotal,
            ]);

            $order->update([
                'payment_status' => $full ? 'refunded' : 'partially_refunded',
            ]);

            if ($order->payment) {
                $order->payment->update(['status' => $full ? 'refunded' : 'partially_refunded']);
            }

            if ($note) {
                \Log::info('Refund for order ' . $order->id . ': ' . $note);
            }
        } catch (\Throwable $e) {
            \Log::warning('Payment refund failed: ' . $e->getMessage());
        }
    }

    /**
     * Withdrawable balance for a seller login (user id): settled earnings
     * across all of their catalog identities minus paid out / in-flight
     * amounts. Never throws (fail-open is handled by the caller).
     */
    public static function availableBalanceForUser(int $userId): float
    {
        $catalogIds = self::catalogIdsForUser($userId);
        $earned = 0.0;
        $orderIds = \App\Models\OrderItem::whereIn('seller_id', $catalogIds)
            ->distinct()->pluck('order_id');
        foreach (Order::with(['items', 'orderVouchers.voucher'])
            ->whereIn('id', $orderIds)->cursor() as $order) {
            $tx = self::transactionFor($order);
            if (! $tx || $tx->status !== 'successful') {
                continue;
            }
            foreach (self::splitForSellers($order) as $sid => $share) {
                if (in_array((int) $sid, $catalogIds, true)) {
                    $earned += $share['earning'];
                }
            }
        }
        $sellerRows = Seller::where('user_id', $userId)->pluck('id')->all();
        $committed = 0.0;
        if ($sellerRows) {
            $committed += (float) SellerPayout::whereIn('seller_id', $sellerRows)
                ->whereIn('status', ['pending', 'processing', 'paid'])->sum('amount');
            $committed += (float) WithdrawalRequest::whereIn('seller_id', $sellerRows)
                ->where('status', 'pending')->sum('amount');
        }
        return round(max(0, $earned - $committed), 2);
    }
}
