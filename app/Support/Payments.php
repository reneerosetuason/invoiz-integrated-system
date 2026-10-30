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
        return [$userId];
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
            throw $e;
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

        $groups = $order->shippingGroupIds();
        $out = [];
        foreach ($groups as $sid) {
            $sid = (int) $sid;
            $calc = \App\Support\EscrowEarnings::calculateForSeller($order, $sid, null, $rate);

            $out[$sid] = [
                'merch'                => $calc['net_merch'],
                'commission'           => $calc['admin_commission'],
                'waived'               => $calc['shipping_waived'],
                'logistics'            => $calc['merch_logistics_deducted'],
                'earning'              => $calc['seller_earning'],
                'origin_hub_fee'       => $calc['origin_hub_fee'],
                'pickup_rider_fee'     => $calc['pickup_rider_fee'],
                'inter_hub_driver_fee' => $calc['inter_hub_driver_fee'],
                'destination_hub_fee'  => $calc['destination_hub_fee'],
                'last_mile_rider_fee'  => $calc['last_mile_rider_fee'],
                'seller_deficit'       => $calc['seller_deficit'],
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
        \Illuminate\Support\Facades\DB::transaction(function () use ($order, $force) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $order->loadMissing(['items', 'orderVouchers.voucher']);
            $split = self::splitForSellers($order);
            $sellerEarning = round(array_sum(array_column($split, 'earning')), 2);
            $commission = round(array_sum(array_column($split, 'commission')), 2);
            $riderFee = round(array_sum(array_column($split, 'last_mile_rider_fee')), 2);
            $originHubFee = round(array_sum(array_column($split, 'origin_hub_fee')), 2);
            $pickupRiderFee = round(array_sum(array_column($split, 'pickup_rider_fee')), 2);
            $interHubDriverFee = round(array_sum(array_column($split, 'inter_hub_driver_fee')), 2);
            $destinationHubFee = round(array_sum(array_column($split, 'destination_hub_fee')), 2);
            $lastMileRiderFee = $riderFee;
            $logisticsFee = round(array_sum(array_column($split, 'logistics')), 2);
            $sellerDeficit = round(array_sum(array_column($split, 'seller_deficit')), 2);

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

            $updateData = [
                'status'              => 'successful',
                'seller_earning'      => $sellerEarning,
                'platform_commission' => $commission,
                'rider_fee'           => $riderFee,
                'logistics_fee'       => $logisticsFee,
                'paid_at'             => now(),
            ];

            if (\Illuminate\Support\Facades\Schema::hasColumn('payment_transactions', 'origin_hub_fee')) {
                $updateData += [
                    'origin_hub_fee'       => $originHubFee,
                    'pickup_rider_fee'     => $pickupRiderFee,
                    'inter_hub_driver_fee' => $interHubDriverFee,
                    'destination_hub_fee'  => $destinationHubFee,
                    'last_mile_rider_fee'  => $lastMileRiderFee,
                    'seller_deficit'       => $sellerDeficit,
                ];
            }

            $tx->update($updateData);
            $order->update([
                'payment_status'    => 'paid',
                'commission_amount' => $commission,
            ]);
            if ($order->payment) {
                $order->payment->update(['status' => 'paid', 'paid_at' => now()]);
            }

            \App\Support\EscrowEarnings::syncRemittancesForOrder($order, $tx);
        });
    }

    /** Void a pending ledger row when an unpaid order is cancelled. */
    public static function voidForCancel(Order $order, bool $strict = false): void
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
            \App\Support\EscrowEarnings::syncRemittancesForOrder($order, $tx);
        } catch (\Throwable $e) {
            if ($strict) {
                throw $e;
            }
            \Log::warning('Payment void failed: ' . $e->getMessage());
        }
    }

    /** Refund only the remaining balance; failures roll back the caller's transaction. */
    public static function refund(Order $order, ?float $amount = null, ?string $note = null): float
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($order, $amount, $note): float {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            $total = round((float) ($locked->total_amount ?? $locked->total ?? 0), 2);
            $tx = PaymentTransaction::where('order_id', $locked->id)->lockForUpdate()->first();
            $remaining = round(max(0, $total - (float) ($tx?->refunded_amount ?? 0)), 2);
            if ($remaining <= 0) {
                return 0.0;
            }
            if ($amount !== null && (! is_finite($amount) || $amount <= 0)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['amount' => 'The refund amount must be positive.']);
            }
            $refund = round(min($amount ?? $remaining, $remaining), 2);
            if (! $tx) {
                $tx = PaymentTransaction::create([
                    'order_id' => $locked->id,
                    'transaction_ref' => 'TXN-ORDER-' . $locked->id,
                    'gateway' => 'cash_on_delivery',
                    'amount' => $total,
                    'status' => 'pending',
                ]);
            }
            $refundedTotal = round((float) $tx->refunded_amount + $refund, 2);
            $status = $refundedTotal >= $total ? 'refunded' : 'partially_refunded';
            $tx->update(['status' => $status, 'refunded_amount' => $refundedTotal]);
            $locked->update(['payment_status' => $status]);
            if ($payment = $locked->payment()->lockForUpdate()->first()) {
                $payment->update(['status' => $status]);
            }
            if ($note) {
                \Log::info('Refund for order ' . $locked->id . ': ' . $note);
            }
            return $refund;
        });
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
