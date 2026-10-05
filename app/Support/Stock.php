<?php

namespace App\Support;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;

/**
 * Single place for every stock movement so buyer, seller and admin
 * always agree:
 * - placing an order DEDUCTS (guarded: never below zero, fails loudly)
 * - cancelling a NOT-YET-SHIPPED order RESTORES the same side
 *   (variant stock when the line has a variant, else product stock)
 * - shipped / delivered orders never move stock on cancel.
 */
class Stock
{
    /** Order statuses that have left the seller (no stock movement allowed). */
    public const SHIPPED = ['out_for_delivery', 'shipped', 'delivered'];

    public static function isShipped(?string $status): bool
    {
        return in_array($status, self::SHIPPED, true);
    }

    /**
     * Atomically deduct $qty. Returns false when stock is insufficient
     * (nothing is deducted).
     */
    public static function deduct(?int $productId, ?int $variantId, int $qty): bool
    {
        if ($qty < 1 || ! $productId) {
            return false;
        }
        if ($variantId) {
            $affected = ProductVariant::where('id', $variantId)
                ->where('product_id', $productId)
                ->where('stock', '>=', $qty)
                ->decrement('stock', $qty);
            return $affected > 0;
        }
        $affected = Product::where('id', $productId)
            ->where('stock', '>=', $qty)
            ->decrement('stock', $qty);
        return $affected > 0;
    }

    /** Return stock for one order line (same side it was taken from). */
    public static function restore(OrderItem $item): void
    {
        $variantId = $item->product_variant_id ?? null;
        $qty = max(1, (int) $item->quantity);
        if ($variantId) {
            ProductVariant::where('id', $variantId)->increment('stock', $qty);
        } else {
            Product::where('id', $item->product_id)->increment('stock', $qty);
        }
    }

    /** Return stock for a whole order, but only if it never shipped. */
    public static function restoreOrder(\App\Models\Order $order): bool
    {
        if (self::isShipped($order->status)) {
            return false;
        }
        foreach ($order->items as $item) {
            self::restore($item);
        }
        return true;
    }
}
