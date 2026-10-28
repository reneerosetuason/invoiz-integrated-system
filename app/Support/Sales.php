<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductSale;
use App\Models\Sale;
use App\Models\Seller;

/**
 * Seller store sales. At most one sale runs per seller; the buyer sees the
 * sale price on the product's listing and pays it at checkout. Discounts
 * are absorbed by the seller and never touch shipping or rider pay.
 */
class Sales
{
    /** The single sale currently running for a seller, if any. */
    public static function runningFor(?int $sellerId): ?Sale
    {
        if (! $sellerId) {
            return null;
        }

        try {
            $seller = Seller::where('user_id', $sellerId)->first();
            $sellerIds = $seller ? $seller->recordIds() : [];

            return Sale::whereIn('seller_id', $sellerIds)
                ->where('status', 'active')
                ->where(function ($q) {
                    $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
                })
                ->where(function ($q) {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
                })
                ->latest('starts_at')
                ->first();
        } catch (\Throwable $e) {
            return null; // tables may not exist yet
        }
    }

    /** The sale that covers a specific product (product must be attached). */
    public static function saleFor(Product $product): ?Sale
    {
        $sale = self::runningFor($product->seller_id);
        if (! $sale) {
            return null;
        }

        $attached = ProductSale::where('sale_id', $sale->id)
            ->where('product_id', $product->id)
            ->exists();

        return $attached ? $sale : null;
    }

    public static function discountAmount(float $basePrice, Sale $sale): float
    {
        if ($sale->discount_type === 'fixed') {
            $discount = (float) $sale->discount_value;
        } else {
            $discount = round($basePrice * (float) $sale->discount_value / 100, 2);
        }

        return round(min(max(0, $discount), $basePrice), 2);
    }

    /** Effective base price of a product after any running sale. */
    public static function priceFor(Product $product): float
    {
        $base = (float) $product->price;
        $sale = self::saleFor($product);
        if (! $sale) {
            return $base;
        }

        return round($base - self::discountAmount($base, $sale), 2);
    }
}
