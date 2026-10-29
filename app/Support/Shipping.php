<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Voucher;

/**
 * Single home for shipping + voucher money rules so every checkout
 * (web, mobile API) behaves identically:
 * - Buyer pays a flat ₱50 shipping fee ONCE per seller group (never per item).
 * - Merchandise vouchers (fixed/percent) reduce the buyer total and are
 *   absorbed by the sellers (pro-rata share, see Order model).
 * - Free-shipping vouchers waive the buyer shipping fee; the waived amount
 *   is absorbed by the seller(s), never by logistics or the rider, whose
 *   ₱50-per-delivery pay is computed independently of vouchers.
 */
class Shipping
{
    /** Sorted distinct seller ids = one delivery group each. */
    public static function groups(array $sellerIds): array
    {
        $ids = array_map('intval', $sellerIds);
        $ids = array_filter($ids, fn ($v) => $v !== 0);
        $ids = array_values(array_unique($ids));
        sort($ids);
        return $ids;
    }

    public static function totalForGroups(array $groupIds): float
    {
        return round(count($groupIds) * Order::SHIPPING_FEE_PER_SELLER, 2);
    }

    /**
     * Resolve a voucher against an order-in-progress.
     *
     * Returns ['merch' => float, 'waived' => [sellerId => float], 'error' => ?string].
     * A seller-scoped voucher only applies when that seller is in the order.
     */
    public static function resolveVoucher(?Voucher $voucher, float $subtotal, array $groupIds, array $sellerSubtotals = []): array
    {
        if (! $voucher) {
            return ['merch' => 0.0, 'waived' => [], 'error' => null];
        }
        $eligibleGroups = $groupIds;
        $applicableSubtotal = $subtotal;
        if ($voucher->seller_id) {
            $ownerId = Vouchers::sellerUserId($voucher);
            $eligibleGroups = array_values(array_filter($groupIds, fn ($id) => (int) $id === $ownerId));
            if (! $eligibleGroups) {
                return ['merch' => 0.0, 'waived' => [], 'error' => 'This voucher does not apply to the items in your order.'];
            }
            if ($sellerSubtotals) {
                $applicableSubtotal = 0.0;
                foreach ($sellerSubtotals as $sellerId => $sellerSubtotal) {
                    if ((int) $sellerId === $ownerId) {
                        $applicableSubtotal += (float) $sellerSubtotal;
                    }
                }
            }
        }
        if ($applicableSubtotal < (float) ($voucher->min_spend ?? 0)) {
            return ['merch' => 0.0, 'waived' => [], 'error' => "This voucher requires a minimum spend of ₱{$voucher->min_spend}."];
        }

        $type = $voucher->discount_type ?? $voucher->type;
        if ($type === 'free_shipping') {
            $shipping = self::totalForGroups($eligibleGroups);
            $cap = (float) ($voucher->discount_value ?? 0) > 0
                ? (float) $voucher->discount_value
                : $shipping;
            if ($voucher->max_discount !== null && (float) $voucher->max_discount >= 0) {
                $cap = min($cap, (float) $voucher->max_discount);
            }
            $waived = Order::allocateShippingWaiver($eligibleGroups, round(min($cap, $shipping), 2));
            return ['merch' => 0.0, 'waived' => $waived, 'error' => null];
        }

        if ($type === 'fixed') {
            $discount = (float) $voucher->discount_value;
        } else {
            $discount = round($applicableSubtotal * (float) $voucher->discount_value / 100, 2);
            if ($voucher->max_discount && $discount > $voucher->max_discount) {
                $discount = (float) $voucher->max_discount;
            }
        }
        $discount = round(min(max(0, $discount), $applicableSubtotal), 2);
        return ['merch' => $discount, 'waived' => [], 'error' => null];
    }
}
