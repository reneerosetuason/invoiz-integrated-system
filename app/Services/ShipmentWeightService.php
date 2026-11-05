<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;

class ShipmentWeightService
{
    /** Seller inventory accepts weights such as "180 g" or "1.5 kg". Bare numbers are kg. */
    public static function kilograms(mixed $value): ?float
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }
        if (! preg_match('/^\s*(\d+(?:\.\d+)?|\.\d+)\s*(kg|kgs|kilograms?|g|grams?|mg|milligrams?|lbs?|pounds?|oz|ounces?)?\s*$/i', (string) $value, $match)) {
            return null;
        }
        $factor = match (strtolower($match[2] ?? 'kg')) {
            'g', 'gram', 'grams' => 0.001,
            'mg', 'milligram', 'milligrams' => 0.000001,
            'lb', 'lbs', 'pound', 'pounds' => 0.45359237,
            'oz', 'ounce', 'ounces' => 0.028349523125,
            default => 1,
        };
        $weight = (float) $match[1] * $factor;

        return is_finite($weight) && $weight > 0 ? $weight : null;
    }

    /** A partial total is unsafe: every ordered unit must have a usable weight. */
    public function fromOrder(Order $order): ?float
    {
        $order->loadMissing('items');
        if ($order->items->isEmpty()) {
            return null;
        }
        $products = Product::withTrashed()->whereIn('id', $order->items->pluck('product_id')->filter())->get()->keyBy('id');
        $variantIds = $order->items->map(fn ($item) => $item->product_variant_id ?: $item->variant_id)->filter();
        $variants = $variantIds->isEmpty() ? collect() : ProductVariant::withTrashed()->whereIn('id', $variantIds)->get()->keyBy('id');
        $total = 0;
        foreach ($order->items as $item) {
            $product = $products->get($item->product_id);
            $variant = $variants->get($item->product_variant_id ?: $item->variant_id);
            $weight = $variant && (int) $variant->product_id === (int) $item->product_id
                ? self::kilograms($variant->weight) : null;
            $weight ??= self::kilograms($product?->weight);
            if ($weight === null || (int) $item->quantity <= 0) {
                return null;
            }
            $total += $weight * (int) $item->quantity;
        }
        // Deliveries store decimal(8,2). Round up only after summing all units
        // so small products stay positive and truck capacity is never understated.
        $total = ceil(round($total * 100, 8)) / 100;

        return is_finite($total) && $total > 0 && $total <= 999999.99 ? $total : null;
    }

    /** Preserve measured/snapshotted weights; only repair a missing value. */
    public function fillMissing(Delivery $delivery, bool $persist = true): ?float
    {
        if ($delivery->weight !== null) {
            return (float) $delivery->weight;
        }
        if (! $delivery->order_id || ! $delivery->order) {
            return null;
        }
        $weight = $this->fromOrder($delivery->order);
        if ($weight === null) {
            return null;
        }
        if ($persist && $delivery->exists) {
            // A concurrent recorded measurement wins. Do not trigger unrelated
            // shipment status, pickup, stock or payment transitions during repair.
            Delivery::whereKey($delivery->id)->whereNull('weight')->update(['weight' => $weight]);
            $weight = (float) Delivery::whereKey($delivery->id)->value('weight');
        }
        $delivery->weight = $weight;

        return $weight;
    }
}
