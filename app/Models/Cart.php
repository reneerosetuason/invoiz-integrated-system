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

    /** Unit price = effective (sale-aware) base price + variant adjustment. */
    public static function unitFor($product, $variant): float
    {
        return \App\Support\Sales::priceFor($product) + ($variant ? (float) $variant->price_adjustment : 0);
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
        return CartItem::with(['product' => fn ($q) => $q->withTrashed(), 'variant' => fn ($q) => $q->withTrashed()])
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

    /** Reject a stale/foreign variation rather than substituting the base item. */
    public static function purchaseVariant(Product $product, mixed $variantId): ?ProductVariant
    {
        if ($variantId !== null && $variantId !== '' && (! is_scalar($variantId) || ! ctype_digit((string) $variantId) || (int) $variantId < 1)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['variant_id' => 'Please choose a valid variation.']);
        }
        $variant = static::resolveVariant($product, $variantId);
        if ($variantId !== null && $variantId !== '' && ! $variant) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'variant_id' => 'This variation is no longer available. Please choose another.',
            ]);
        }
        return $variant;
    }

    /** Variation units are allocated within, and capped by, general stock. */
    public static function availableFor(?Product $product, ?ProductVariant $variant = null, ?int $variantId = null): int
    {
        if (! $product || $product->trashed() || $product->status !== 'active') return 0;
        if ($variantId && ! $variant) return 0;
        if ($variant && ($variant->trashed() || ($variant->status ?? 'active') !== 'active' || (int) $variant->product_id !== (int) $product->id)) return 0;
        return max(0, $variant
            ? min((int) $variant->stock, (int) $product->stock)
            : (int) $product->stock - (int) $product->variants()->sum('stock'));
    }

    /** Serialize changes through the cart header to enforce stock and the 200-unit cap. */
    public static function addFor(int $buyerId, Product $product, ?ProductVariant $variant, int $quantity): ?string
    {
        $cart = static::headerFor($buyerId);
        return \Illuminate\Support\Facades\DB::transaction(function () use ($cart, $product, $variant, $quantity) {
            static::whereKey($cart->id)->lockForUpdate()->firstOrFail();
            $available = static::availableFor($product, $variant);
            if ($available < 1) return $variant ? 'Selected variation is out of stock' : 'Product is out of stock';
            $line = CartItem::where('cart_id', $cart->id)->where('product_id', $product->id)->where('variant_id', $variant?->id)->first();
            $current = (int) ($line?->quantity ?? 0);
            $otherUnits = (int) $cart->items()->where('product_id', $product->id)->when($line, fn ($q) => $q->where('id', '!=', $line->id))->sum('quantity');
            $available = min($available, max(0, (int) $product->stock - $otherUnits));
            if ($current >= $available) return 'Only '.$available.' available for this item.';
            $added = min($quantity, $available - $current);
            if ((int) $cart->items()->sum('quantity') + $added > 200) return 'Cart limit is 200 items';
            if ($line) $line->update(['quantity' => $current + $added]);
            else CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'variant_id' => $variant?->id, 'quantity' => $added]);
            return null;
        });
    }

    public static function increaseFor(int $buyerId, int $itemId): ?string
    {
        $cart = static::headerFor($buyerId);
        return \Illuminate\Support\Facades\DB::transaction(function () use ($cart, $itemId) {
            static::whereKey($cart->id)->lockForUpdate()->firstOrFail();
            $line = $cart->items()->with(['product', 'variant'])->find($itemId);
            if (! $line) return 'Item not found in cart.';
            $available = static::availableFor($line->product, $line->variant, $line->variant_id);
            $otherUnits = (int) $cart->items()->where('product_id', $line->product_id)->where('id', '!=', $line->id)->sum('quantity');
            $available = min($available, max(0, (int) ($line->product?->stock ?? 0) - $otherUnits));
            if ($available < 1) return 'This item is no longer available. Remove it or choose another variation.';
            if ($line->quantity >= $available) return 'Only '.$available.' available for this item.';
            if ((int) $cart->items()->sum('quantity') >= 200) return 'Cart limit is 200 items';
            $line->increment('quantity');
            return null;
        });
    }

    /** Selection values in the web cart are cart-item IDs, never product IDs. */
    public static function selectedLinesFor(int $buyerId, array $itemIds)
    {
        $ids = array_map('intval', $itemIds);
        return static::linesFor($buyerId)
            ->filter(fn ($line) => in_array((int) $line['item']->id, $ids, true))->values();
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
