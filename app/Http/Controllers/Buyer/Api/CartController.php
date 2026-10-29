<?php

namespace App\Http\Controllers\Buyer\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function index(Request $request)
    {
        $cart = Cart::headerFor($request->user()->id);
        $items = $cart->items()->with([
            'product' => fn ($q) => $q->withTrashed()->with('category'),
            'variant' => fn ($q) => $q->withTrashed(),
        ])->get()->map(function ($item) {
            $item->unit_price = $item->product ? max(0, Cart::unitFor($item->product, $item->variant)) : 0;
            $item->line_total = round($item->unit_price * $item->quantity, 2);
            $item->available_stock = Cart::availableFor($item->product, $item->variant, $item->variant_id);
            return $item;
        });
        return response()->json(['cart' => ['id' => $cart->id, 'subtotal' => round($items->sum('line_total'), 2), 'items' => $items]]);
    }

    public function add(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'variant_id' => ['nullable', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'between:1,200'],
        ]);
        $cart = Cart::headerFor($request->user()->id);
        DB::transaction(function () use ($cart, $data) {
            Cart::whereKey($cart->id)->lockForUpdate()->firstOrFail();
            $product = Product::storefront()->findOrFail($data['product_id']);
            $variant = Cart::purchaseVariant($product, $data['variant_id'] ?? null);
            if ($variant === null && $product->variants()->where('status', 'active')->exists()) {
                throw ValidationException::withMessages(['variant_id' => 'Please choose a product variation.']);
            }
            $line = $cart->items()->where('product_id', $product->id)->where('variant_id', $variant?->id)->first();
            $quantity = (int) ($line?->quantity ?? 0) + $data['quantity'];
            $this->validateQuantity($cart, $line, $product, $variant, $quantity);
            if ($line) {
                $line->update(['quantity' => $quantity]);
            } else {
                $cart->items()->create(['product_id' => $product->id, 'variant_id' => $variant?->id, 'quantity' => $quantity]);
            }
            $product->trackCartAddition();
        });
        return response()->json(['message' => 'Item added to cart.']);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'between:1,200']]);
        $cart = Cart::headerFor($request->user()->id);
        DB::transaction(function () use ($cart, $id, $data) {
            Cart::whereKey($cart->id)->lockForUpdate()->firstOrFail();
            $line = $cart->items()->with(['product', 'variant'])->findOrFail($id);
            $this->validateQuantity($cart, $line, $line->product, $line->variant, $data['quantity']);
            $line->update(['quantity' => $data['quantity']]);
        });
        return response()->json(['message' => 'Cart updated.']);
    }

    private function validateQuantity($cart, $line, $product, $variant, int $quantity): void
    {
        $available = Cart::availableFor($product, $variant, $line?->variant_id);
        if ($available < 1 || $quantity > $available) {
            throw ValidationException::withMessages(['quantity' => "Only {$available} item(s) available in stock. Remove unavailable items or choose another variation."]);
        }
        $otherUnits = (int) $cart->items()->where('product_id', $product->id)
            ->when($line, fn ($q) => $q->where('id', '!=', $line->id))->sum('quantity');
        if ($otherUnits + $quantity > (int) $product->stock) {
            throw ValidationException::withMessages(['quantity' => 'The total quantity across variations exceeds this product\'s general stock.']);
        }
        if ((int) $cart->items()->sum('quantity') - (int) ($line?->quantity ?? 0) + $quantity > 200) {
            throw ValidationException::withMessages(['quantity' => 'Cart limit is 200 items.']);
        }
    }

    public function destroy(Request $request, $id)
    {
        $cart = Cart::headerFor($request->user()->id);
        DB::transaction(function () use ($cart, $id) {
            Cart::whereKey($cart->id)->lockForUpdate()->firstOrFail();
            $cart->items()->findOrFail($id)->delete();
        });
        return response()->json(['message' => 'Item removed from cart.']);
    }

    public function clear(Request $request)
    {
        $cart = Cart::headerFor($request->user()->id);
        DB::transaction(function () use ($cart) {
            Cart::whereKey($cart->id)->lockForUpdate()->firstOrFail();
            $cart->items()->delete();
        });
        return response()->json(['message' => 'Cart cleared.']);
    }
}
