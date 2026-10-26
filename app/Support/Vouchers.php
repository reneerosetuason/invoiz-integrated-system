<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Voucher;
use App\Models\VoucherClaim;

/**
 * Voucher lifecycle helpers shared by buyer, seller and admin cancel paths.
 *
 * A claimed voucher is "locked" to an order while that order is live. When
 * the order is cancelled the claim is released so the buyer can use it
 * again, and the voucher's used_count is decremented. The OrderVoucher row
 * is left in place as a historical record that the voucher was applied.
 *
 * Stacking: a single checkout may combine up to three vouchers — one store
 * voucher, one platform voucher and one shipping voucher — each applying
 * independently.
 */
class Vouchers
{
    /**
     * Resolve a set of vouchers that will be stacked on one order.
     *
     * Returns:
     *   merch      => total merchandise discount
     *   waived     => [sellerId => waived shipping]
     *   perVoucher => [voucherId => ['merch' => float, 'waived' => float]]
     *   error      => ?string
     */
    public static function resolveStack(iterable $vouchers, float $subtotal, array $groupIds, array $sellerSubtotals = []): array
    {
        $buckets = ['store' => null, 'platform' => null, 'shipping' => null];

        foreach ($vouchers as $voucher) {
            if (! $voucher) {
                continue;
            }
            $type = $voucher->discount_type ?? $voucher->type;
            if ($type === 'free_shipping') {
                $bucket = 'shipping';
            } elseif ($voucher->seller_id) {
                $bucket = 'store';
            } else {
                $bucket = 'platform';
            }

            if ($buckets[$bucket] !== null) {
                $labels = ['store' => 'store', 'platform' => 'platform', 'shipping' => 'shipping'];
                return ['merch' => 0.0, 'waived' => [], 'perVoucher' => [], 'error' => "Only one {$labels[$bucket]} voucher may be used per order."];
            }
            $buckets[$bucket] = $voucher;
        }

        $merch = 0.0;
        $waived = [];
        $perVoucher = [];

        foreach ($buckets as $voucher) {
            if (! $voucher) {
                continue;
            }
            $resolved = \App\Support\Shipping::resolveVoucher($voucher, $subtotal, $groupIds, $sellerSubtotals);
            if ($resolved['error']) {
                return ['merch' => 0.0, 'waived' => [], 'perVoucher' => [], 'error' => $resolved['error']];
            }

            $merch += $resolved['merch'];
            foreach ($resolved['waived'] as $sid => $amount) {
                $waived[$sid] = ($waived[$sid] ?? 0) + $amount;
            }

            $perVoucher[$voucher->id] = [
                'merch'  => round($resolved['merch'], 2),
                'waived' => round(array_sum($resolved['waived']), 2),
            ];
        }

        $merch = round(min($merch, $subtotal), 2);

        return ['merch' => $merch, 'waived' => $waived, 'perVoucher' => $perVoucher, 'error' => null];
    }

    public static function restoreForOrder(Order $order, bool $strict = false): void
    {
        try {
            $order->loadMissing('orderVouchers');

            foreach ($order->orderVouchers as $orderVoucher) {
                $voucher = Voucher::find($orderVoucher->voucher_id);
                if (! $voucher) {
                    continue;
                }

                $claim = VoucherClaim::where('voucher_id', $voucher->id)
                    ->where('order_id', $order->id)
                    ->first();

                if ($claim) {
                    $claim->update(['order_id' => null, 'used_at' => null]);
                }

                if ((int) $voucher->used_count > 0) {
                    $voucher->decrement('used_count');
                }
            }
        } catch (\Throwable $e) {
            if ($strict) {
                throw $e;
            }
            \Log::warning('Voucher restore failed: ' . $e->getMessage());
        }
    }
}
