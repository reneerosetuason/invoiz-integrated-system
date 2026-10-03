<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\OrderVoucher;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Review;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with(['items', 'payment', 'delivery', 'orderVouchers.voucher'])
            ->where('buyer_id', $request->user()->id)
            ->latest();

        if ($request->has('status') && $request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json(['orders' => $query->get()]);
    }

    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'address_id'       => ['required', 'exists:addresses,id'],
            'payment_method'   => ['required', 'in:cash_on_delivery'],
            'voucher_code'     => ['nullable', 'string', 'max:50'],
            'notes'            => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $cart = Cart::where('buyer_id', $user->id)->with('items.product', 'items.variant')->first();

        if (! $cart || $cart->items->isEmpty()) {
            return response()->json(['message' => 'Your cart is empty.'], 422);
        }

        $address = Address::where('id', $validated['address_id'])
            ->where('buyer_id', $user->id)
            ->firstOrFail();

        // Validate stock for all items up front.
        foreach ($cart->items as $item) {
            $available = $item->variant_id ? $item->variant->stock : $item->product->stock;
            if ($item->quantity > $available) {
                return response()->json([
                    'message' => "Insufficient stock for \"{$item->product->name}\".",
                ], 422);
            }
        }

        $subtotal = 0;
        $lineItems = [];
        foreach ($cart->items as $item) {
            $unit = $item->product->price + ($item->variant?->price_adjustment ?? 0);
            $lineTotal = round($unit * $item->quantity, 2);
            $subtotal += $lineTotal;

            $lineItems[] = [
                'product_id'    => $item->product_id,
                'seller_id'     => $item->product->seller_id,
                'product_name'  => $item->product->name,
                'variant_label' => $item->variant
                    ? $item->variant->variant_type.': '.$item->variant->variant_value
                    : null,
                'quantity'      => $item->quantity,
                'price'         => $unit,
            ];
        }

        // Voucher / discount calculation.
        $discount = 0.00;
        $voucher = null;
        if (! empty($validated['voucher_code'])) {
            $voucher = Voucher::active()->where('code', $validated['voucher_code'])->first();
            if (! $voucher) {
                return response()->json(['message' => 'Invalid or expired voucher code.'], 422);
            }
            if ($subtotal < $voucher->min_spend) {
                return response()->json([
                    'message' => "This voucher requires a minimum spend of ₱{$voucher->min_spend}.",
                ], 422);
            }
            if ($voucher->discount_type === 'fixed') {
                $discount = $voucher->discount_value;
            } else {
                $discount = round($subtotal * $voucher->discount_value / 100, 2);
                if ($voucher->max_discount && $discount > $voucher->max_discount) {
                    $discount = $voucher->max_discount;
                }
            }
            if ($discount > $subtotal) {
                $discount = $subtotal;
            }
        }

        $total = round($subtotal - $discount, 2);

        try {
            $order = DB::transaction(function () use ($user, $address, $cart, $lineItems, $subtotal, $discount, $total, $voucher, $validated) {
                $order = Order::create([
                    'buyer_id'     => $user->id,
                    'address_id'   => $address->id,
                    'total_amount' => $total,
                    'status'       => 'pending',
                    'notes'        => $validated['notes'] ?? null,
                ]);

                foreach ($lineItems as $li) {
                    OrderItem::create([...$li, 'order_id' => $order->id]);
                }

                Payment::create([
                    'order_id' => $order->id,
                    'method'   => $validated['payment_method'],
                    'status'   => 'pending',
                    'amount'   => $total,
                ]);

                Delivery::create(['order_id' => $order->id]);

                OrderStatusHistory::create([
                    'order_id'   => $order->id,
                    'to_status'  => 'pending',
                    'note'       => 'Order placed.',
                    'created_at' => now(),
                ]);

                if ($voucher) {
                    OrderVoucher::create([
                        'order_id'        => $order->id,
                        'voucher_id'      => $voucher->id,
                        'discount_amount' => $discount,
                    ]);
                    $voucher->increment('used_count');
                }

                // Deduct stock.
                foreach ($cart->items as $item) {
                    if ($item->variant_id) {
                        $item->variant->decrement('stock', $item->quantity);
                    } else {
                        $item->product->decrement('stock', $item->quantity);
                    }
                }

                $cart->items()->delete();

                return $order;
            });

            $order->load(['items', 'payment', 'delivery', 'orderVouchers.voucher']);

            return response()->json([
                'message' => 'Order placed successfully. Payment method: Cash on Delivery.',
                'order'   => $order,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Unable to place order. '.$e->getMessage()], 500);
        }
    }

    public function show(Request $request, $id)
    {
        $order = Order::with(['items', 'payment', 'delivery', 'orderVouchers.voucher', 'address', 'statusHistories'])
            ->where('buyer_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json(['order' => $order]);
    }

    public function cancel(Request $request, $id)
    {
        $order = Order::where('buyer_id', $request->user()->id)->findOrFail($id);

        if (! in_array($order->status, ['pending', 'confirmed'])) {
            return response()->json(['message' => 'This order can no longer be cancelled.'], 422);
        }

        $order->update(['status' => 'cancelled']);

        OrderStatusHistory::create([
            'order_id'   => $order->id,
            'from_status' => 'pending',
            'to_status'  => 'cancelled',
            'note'       => 'Order cancelled by buyer.',
            'created_at' => now(),
        ]);

        // Restore stock.
        foreach ($order->items as $item) {
            Product::where('id', $item->product_id)->increment('stock', $item->quantity);
        }

        return response()->json(['message' => 'Order cancelled.']);
    }

    public function rate(Request $request, $id)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'rating'     => ['required', 'integer', 'between:1,5'],
            'comment'    => ['nullable', 'string', 'max:2000'],
        ]);

        $order = Order::where('buyer_id', $request->user()->id)->findOrFail($id);

        $belongsToOrder = $order->items()->where('product_id', $validated['product_id'])->exists();
        if (! $belongsToOrder) {
            return response()->json(['message' => 'You can only rate products from this order.'], 422);
        }

        $review = Review::updateOrCreate(
            [
                'buyer_id'   => $request->user()->id,
                'product_id' => $validated['product_id'],
                'order_id'   => $order->id,
            ],
            [
                'rating'  => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
                'status'  => 'visible',
            ]
        );

        // Recompute cached product rating.
        $avg = Review::where('product_id', $validated['product_id'])
            ->where('status', 'visible')
            ->avg('rating');
        Product::where('id', $validated['product_id'])->update([
            'rating' => $avg ? round($avg, 1) : null,
        ]);

        return response()->json(['message' => 'Thank you for your feedback.', 'review' => $review]);
    }
}