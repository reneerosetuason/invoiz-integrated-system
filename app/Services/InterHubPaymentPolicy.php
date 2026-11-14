<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Support\EscrowEarnings;

/** Caps the actual driver payment, rather than creating a separate funding obligation. */
class InterHubPaymentPolicy
{
    public static function budget(Order $order, int $seller, array $rates, float $fraction = 1): int
    {
        $order->loadMissing(['items', 'orderVouchers.voucher']);
        $items = $order->items->where('seller_id', $seller);
        $revenue = max(0, $order->sellerSubtotal($seller) - $order->voucherDiscountForSeller($seller));
        $revenueCents = (int) round($revenue * 100);
        $cap = intdiv($revenueCents * (int) round(($rates['seller_logistics_cap_percent'] ?? 5) * 100), 10000);
        // Existing product cost is optional. Never require new checkout or inventory fields.
        $products = Product::withTrashed()->whereIn('id', $items->pluck('product_id'))->get()->keyBy('id');
        if ($items->isNotEmpty() && $items->every(fn ($i) => $products->get($i->product_id)?->cost_price !== null)) {
            $cost = $items->sum(fn ($i) => (float) $products[$i->product_id]->cost_price * $i->quantity);
            $commission = EscrowEarnings::commissionForSellerItems($order, $seller, $revenue)['amount'];
            $otherFees = $rates['origin_hub_fee'] + $rates['destination_hub_fee']
                + $rates['pickup_rider_per_item'] * $items->sum('quantity') + $rates['last_mile_rider_fee']
                - max(0, Order::SHIPPING_FEE_PER_SELLER - $order->shippingWaivedForSeller($seller));
            $minimumProfitCents = intdiv($revenueCents * (int) round(($rates['seller_min_profit_percent'] ?? 5) * 100) + 9999, 10000);
            $marginCents = $revenueCents - (int) round($cost * 100) - (int) round($commission * 100)
                - (int) round($otherFees * 100) - $minimumProfitCents;
            $cap = min($cap, max(0, $marginCents));
        }

        return max(0, (int) floor($cap * max(0, min(1, $fraction))));
    }

    public static function apply(array $quote, array $budgets): array
    {
        $quote['pricing_version'] = 2;
        $quote['uncapped_total'] = $quote['total'];
        $quote['total'] = min($quote['total'], array_sum($budgets));
        $weights = [];
        foreach ($quote['parcels'] as $p) {
            $weights[$p['id']] = max((float) $p['weight'], $p['length_cm'] * $p['width_cm'] * $p['height_cm'] / $quote['rates']['trip_volumetric_divisor']);
        }
        $allocated = array_fill_keys(array_keys($weights), 0);
        $left = $quote['total'];
        while ($left > 0 && $weights) {
            $sum = array_sum($weights);
            $limited = false;
            foreach ($weights as $id => $weight) {
                $room = max(0, ($budgets[$id] ?? 0) - $allocated[$id]);
                if ($left * $weight / $sum >= $room) {
                    $allocated[$id] += $room;
                    $left -= $room;
                    unset($weights[$id]);
                    $limited = true;
                    break;
                }
            }
            if ($limited) {
                continue;
            }
            $remainders = [];
            $round = $left;
            foreach ($weights as $id => $weight) {
                $share = $round * $weight / $sum;
                $whole = (int) floor($share);
                $allocated[$id] += $whole;
                $left -= $whole;
                $remainders[$id] = $share - $whole;
            }
            $ids = array_keys($remainders);
            usort($ids, fn ($a, $b) => ($remainders[$b] <=> $remainders[$a]) ?: ($a <=> $b));
            foreach ($ids as $id) {
                if ($left <= 0) {
                    break;
                }
                $allocated[$id]++;
                $left--;
            }
        }
        ksort($allocated);
        $quote['allocations'] = $allocated;
        $quote['seller_budgets'] = $allocated;
        $quote['platform_adjustment_cents'] = 0;

        return $quote;
    }
}
