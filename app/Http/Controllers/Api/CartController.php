<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

class CartController extends Controller
{
    private function ensureCart($userId): Cart
    {
        return Cart::firstOrCreate(['buyer_id' => $userId]);
    }

    public function index(Request $request)
    {
        $cart = $this->ensureCart($request->user()->id);

        $items = $cart->items()
            ->with(['product.category', 'variant'])
            ->get()
            ->map(function ($item) {
                $item->unit_price = $item->product->price + ($item->variant?->price_adjustment ?? 0);
                $item->line_total = round($item->unit_price * $item->quantity, 2);
                return $item;
            });

        $subtotal = round($items->sum('line_total'), 2);

        return response()->json([
            'cart' => [
                'id'       => $cart->id,
                'subtotal' => $subtotal,
                'items'    => $items,
            ],
        ]);
    }

    public function add(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'variant_id' => ['nullable', 'exists:product_variants,id'],
            'quantity'   => ['required', 'integer', 'min:1'],
        ]);

        $product = Product::where('status', 'active')->findOrFail($validated['product_id']);

        if ($product->status === 'out_of_stock') {
            return response()->json(['message' => 'This product is out of stock.'], 422);
        }

        $variant = null;
        if (! empty($validated['variant_id'])) {
            $variant = ProductVariant::where('product_id', $product->id)
                ->where('status', 'active')
                ->findOrFail($validated['variant_id']);
        }

        // Validate stock.
        $available = $variant ? $variant->stock : $product->stock;
        $cart = $this->ensureCart($request->user()->id);

        $existing = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $product->id)
            ->where('variant_id', $validated['variant_id'] ?? null)
            ->first();

        $newQty = ($existing->quantity ?? 0) + $validated['quantity'];
        if ($newQty > $available) {
            return response()->json([
                'message' => "Only {$available} item(s) available in stock.",
            ], 422);
        }

        if ($existing) {
            $existing->update(['quantity' => $newQty]);
        } else {
            CartItem::create([
                'cart_id'    => $cart->id,
                'product_id' => $product->id,
                'variant_id' => $validated['variant_id'] ?? null,
                'quantity'   => $validated['quantity'],
            ]);
        }

        return response()->json(['message' => 'Item added to cart.']);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $item = CartItem::where('cart_id', $this->ensureCart($request->user()->id)->id)
            ->findOrFail($id);

        $available = $item->variant_id
            ? $item->variant->stock
            : $item->product->stock;

        if ($validated['quantity'] > $available) {
            return response()->json([
                'message' => "Only {$available} item(s) available in stock.",
            ], 422);
        }

        $item->update(['quantity' => $validated['quantity']]);

        return response()->json(['message' => 'Cart updated.']);
    }

    public function destroy(Request $request, $id)
    {
        $item = CartItem::where('cart_id', $this->ensureCart($request->user()->id)->id)
            ->findOrFail($id);

        $item->delete();

        return response()->json(['message' => 'Item removed from cart.']);
    }

    public function clear(Request $request)
    {
        $cart = $this->ensureCart($request->user()->id);
        $cart->items()->delete();

        return response()->json(['message' => 'Cart cleared.']);
    }
}