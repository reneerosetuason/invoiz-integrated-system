<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $fillable = ['buyer_id'];

    /** Get-or-create the cart header row for a buyer (shared `carts` table). */
    public static function headerFor(int $buyerId): self
    {
        return static::firstOrCreate(['buyer_id' => $buyerId]);
    }

    /**
     * Query builder over the buyer's line items (shared `cart_items` table).
     * Exposes product_id + quantity, so it is a drop-in for flat-cart queries.
     */
    public static function itemsFor(int $buyerId)
    {
        $header = static::where('buyer_id', $buyerId)->first();
        return CartItem::where('cart_id', $header ? $header->id : 0);
    }

    /** Validate a variant belongs to the product and is active (null if none). */
    public static function resolveVariant($product, $variantId)
    {
        if (!$variantId) {
            return null;
        }
        try {
            return ProductVariant::where('id', $variantId)
                ->where('product_id', $product->id)
                ->where('status', 'active')
                ->first();
        } catch (\Throwable $e) {
            // Pre-migration DBs without `status`: match by product only.
            return ProductVariant::where('id', $variantId)
                ->where('product_id', $product->id)
                ->first();
        }
    }

    /** Unit price = base price + variant adjustment. */
    public static function unitFor($product, $variant): float
    {
        return (float) $product->price + ($variant ? (float) $variant->price_adjustment : 0);
    }

    /** Snapshot label for orders, e.g. "Color: White". */
    public static function labelFor($variant): ?string
    {
        return $variant ? ($variant->variant_type . ': ' . $variant->variant_value) : null;
    }

    /**
     * Full cart lines for checkout/cart display. Each line:
     * item, product, variant, unit, qty, label, line (unit × qty).
     */
    public static function linesFor(int $buyerId)
    {
        $header = static::where('buyer_id', $buyerId)->first();
        if (!$header) {
            return collect();
        }
        return CartItem::with(['product', 'variant'])
            ->where('cart_id', $header->id)
            ->get()
            ->filter(fn($it) => $it->product)
            ->map(function ($it) {
                $unit = static::unitFor($it->product, $it->variant);
                $qty = (int) $it->quantity;
                return [
                    'item' => $it,
                    'product' => $it->product,
                    'variant' => $it->variant,
                    'unit' => $unit,
                    'qty' => $qty,
                    'label' => static::labelFor($it->variant),
                    'line' => $unit * $qty,
                ];
            })->values();
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function items()
    {
        return $this->hasMany(CartItem::class, 'cart_id');
    }
}