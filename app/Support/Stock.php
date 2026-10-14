<?php

namespace App\Support;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SellerNotification;

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
            if ($affected > 0) {
                self::checkLowStock($productId, $variantId);
            }
            return $affected > 0;
        }
        $affected = Product::where('id', $productId)
            ->where('stock', '>=', $qty)
            ->decrement('stock', $qty);
        if ($affected > 0) {
            self::checkLowStock($productId);
        }
        return $affected > 0;
    }

    /**
     * Fire (or resolve) the seller's low-stock alert for a product.
     *
     * Creates one unread `low_stock` notification per product when stock
     * drops to/below the seller's alert level (refreshing its numbers if
     * one is already unread), and auto-marks it read once stock recovers.
     * Never throws — stock movement must never fail because of alerts.
     */
    public static function checkLowStock(?int $productId, ?int $variantId = null): void
    {
        if (! $productId) {
            return;
        }
        try {
            $product = Product::find($productId);
            if (! $product || ! $product->seller_id) {
                return;
            }
            $threshold = max(0, (int) ($product->low_stock_threshold ?? 5));

            $low = false;
            $remaining = (int) $product->stock;
            $detail = '';
            if ($variantId) {
                $variant = ProductVariant::find($variantId);
                if ($variant && (int) $variant->product_id === (int) $product->id) {
                    $remaining = (int) $variant->stock;
                    if ($remaining <= $threshold) {
                        $low = true;
                        $label = trim(($variant->variant_type ?? '') . ' ' . ($variant->variant_value ?? ''));
                        $detail = $label !== '' ? " (Variation: {$label})" : '';
                    }
                }
            } elseif ($remaining <= $threshold) {
                $low = true;
            }

            $unread = SellerNotification::forSeller($product->seller_id)
                ->where('type', 'low_stock')
                ->where('product_id', $product->id)
                ->where('is_read', false);

            if ($low) {
                $title = "Low stock alert: {$product->name}";
                $body = "Only {$remaining} left in stock{$detail} (your alert level is {$threshold}). Restock soon to avoid missing sales.";
                $existing = (clone $unread)->latest('id')->first();
                if ($existing) {
                    $existing->update(['title' => $title, 'body' => $body]);
                } else {
                    SellerNotification::create([
                        'seller_id'  => $product->seller_id,
                        'product_id' => $product->id,
                        'type'       => 'low_stock',
                        'title'      => $title,
                        'body'       => $body,
                    ]);
                }
            } else {
                $unread->update(['is_read' => true]);
            }
        } catch (\Throwable $e) {
            \Log::warning('Low-stock check failed: ' . $e->getMessage());
        }
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
        // Stock came back (e.g. cancelled order) — resolve the alert if
        // the level is healthy again.
        self::checkLowStock($item->product_id, $variantId);
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
