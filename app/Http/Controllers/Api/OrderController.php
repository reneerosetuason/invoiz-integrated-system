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
use App\Models\PickupRequest;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\Review;
use App\Models\Seller;
use App\Models\Voucher;
use App\Models\VoucherClaim;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\DeliveryServiceability;
use App\Support\Shipping;
use App\Support\Vouchers;

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

        $orders = $query->get();
        $this->hideBuyerVoucherCodes($orders);
        return response()->json(['orders' => $orders]);
    }

    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'address_id'       => ['required', 'exists:addresses,id'],
            'payment_method'   => ['required', 'in:cash_on_delivery'],
            'voucher_claim_id' => ['nullable', 'integer', 'exists:voucher_claims,id'],
            'voucher_claim_ids' => ['nullable', 'array', 'max:3'],
            'voucher_claim_ids.*' => ['integer', 'exists:voucher_claims,id'],
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

        if ($problem = DeliveryServiceability::dropoffAddressProblem($address)) {
            return response()->json(['message' => $problem], 422);
        }

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
        $sellerSubtotals = [];
        foreach ($cart->items as $item) {
            $unit = \App\Support\Sales::priceFor($item->product) + ($item->variant?->price_adjustment ?? 0);
            $lineTotal = round($unit * $item->quantity, 2);
            $subtotal += $lineTotal;
            $sellerId = (int) $item->product->seller_id;
            $sellerSubtotals[$sellerId] = ($sellerSubtotals[$sellerId] ?? 0) + $lineTotal;

            $lineItems[] = [
                'product_id'    => $item->product_id,
                'product_variant_id' => $item->variant_id,
                'seller_id'     => $item->product->seller_id,
                'product_name'  => $item->product->name,
                'variant_label' => $item->variant
                    ? $item->variant->variant_type.': '.$item->variant->variant_value
                    : null,
                'quantity'      => $item->quantity,
                'price'         => $unit,
            ];
        }

        foreach (array_unique(array_column($lineItems, 'seller_id')) as $sellerId) {
            if ($problem = DeliveryServiceability::pickupAddressProblem((int) $sellerId)) {
                return response()->json(['message' => $problem], 422);
            }
        }

        // Shipping: flat fee once per seller group (never per item).
        // The rider earns it once per delivery; vouchers never touch it.
        $sellerIds = array_keys($sellerSubtotals);
        $onVacation = Seller::where(function ($q) use ($sellerIds) {
            $q->whereIn('id', $sellerIds)->orWhereIn('user_id', $sellerIds);
        })->where('vacation_mode', true)->first();
        if ($onVacation) {
            return response()->json([
                'message' => ($onVacation->store_name ?: 'This store') . ' is on vacation and is not accepting orders right now. Please remove their items to continue.',
            ], 422);
        }

        $groupIds = Shipping::groups(array_column($lineItems, 'seller_id'));
        $shippingTotal = Shipping::totalForGroups($groupIds);

        // Voucher / discount calculation (merchandise and/or free shipping).
        // Up to three vouchers stack: one store + one platform + one shipping.
        $discount = 0.00;
        $waived = [];
        $perVoucher = [];
        $claimIds = array_values(array_unique(array_filter(array_merge(
            array_map('intval', (array) ($validated['voucher_claim_ids'] ?? [])),
            ! empty($validated['voucher_claim_id']) ? [(int) $validated['voucher_claim_id']] : []
        ))));
        if ($claimIds) {
            $claims = VoucherClaim::with('voucher')->where('buyer_id', $user->id)
                ->whereNull('used_at')->whereIn('id', $claimIds)->get()->keyBy('id');
            $vouchers = $claims->map(fn ($c) => $c->voucher)->filter();
            if ($claims->count() !== count($claimIds) || $vouchers->count() !== count($claimIds) || $vouchers->contains(fn ($v) => ! $v->is_active)) {
                return response()->json(['message' => 'One of the claimed vouchers is unavailable.'], 422);
            }
            $stack = Vouchers::resolveStack($vouchers, $subtotal, $groupIds, $sellerSubtotals);
            if ($stack['error']) {
                return response()->json(['message' => $stack['error']], 422);
            }
            $discount = $stack['merch'];
            $waived = $stack['waived'];
            $perVoucher = $stack['perVoucher'];
        }
        $waivedTotal = round(array_sum($waived), 2);

        $total = round($subtotal - $discount + $shippingTotal - $waivedTotal, 2);

        try {
            $order = DB::transaction(function () use ($user, $address, $cart, $lineItems, $subtotal, $discount, $shippingTotal, $waivedTotal, $total, $claimIds, $perVoucher, $validated, $groupIds) {
                $lockedClaims = collect();
                if ($claimIds) {
                    $lockedClaims = VoucherClaim::whereIn('id', $claimIds)->where('buyer_id', $user->id)
                        ->whereNull('used_at')->lockForUpdate()->get();
                    $lockedVouchers = Voucher::whereIn('id', $lockedClaims->pluck('voucher_id'))
                        ->lockForUpdate()->get()->keyBy('id');
                    if ($lockedClaims->count() !== count($claimIds)) {
                        throw new \Exception('One of the claimed vouchers is no longer available.');
                    }
                    foreach ($lockedClaims as $lc) {
                        $lv = $lockedVouchers->get($lc->voucher_id);
                        if (! $lv || ! $lv->is_active || ($lv->usage_limit !== null && $lv->used_count >= $lv->usage_limit)) {
                            throw new \Exception('One of the claimed vouchers is no longer available.');
                        }
                    }
                }
                $order = Order::create([
                    'seller_id'      => $groupIds[0] ?? null,
                    'buyer_id'       => $user->id,
                    'address_id'     => $address->id,
                    'shipping_address' => $address->toShippingSnapshot(),
                    'total_amount'   => $total,
                    'sub_total'      => $subtotal,
                    'discount_total' => round($discount + $waivedTotal, 2),
                    'shipping_fee'   => round($shippingTotal - $waivedTotal, 2),
                    'status'         => 'pending',
                    'notes'          => $validated['notes'] ?? null,
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

                // Open the money ledger row (pending until delivery settles it).
                \App\Support\Payments::recordCheckout($order);

                OrderStatusHistory::create([
                    'order_id'   => $order->id,
                    'to_status'  => 'pending',
                    'note'       => 'Order placed.',
                    'created_at' => now(),
                ]);

                foreach ($lockedClaims as $lc) {
                    $amount = ($perVoucher[$lc->voucher_id]['merch'] ?? 0) + ($perVoucher[$lc->voucher_id]['waived'] ?? 0);
                    OrderVoucher::create([
                        'order_id'        => $order->id,
                        'voucher_id'      => $lc->voucher_id,
                        'discount_amount' => round($amount, 2),
                    ]);
                    Voucher::whereKey($lc->voucher_id)->increment('used_count');
                    $lc->update(['order_id' => $order->id, 'used_at' => now()]);
                }

                // One delivery per seller group: the rider earns the flat
                // fee once per delivery, independent of any voucher.
                foreach ($groupIds as $sid) {
                    $delivery = Delivery::create([
                        'order_id'         => $order->id,
                        'seller_id'        => $sid,
                        'status'           => 'waiting_for_rider',
                        'payment_method'   => $validated['payment_method'],
                        'amount_to_collect' => $order->cashToCollectForSeller((int) $sid),
                        'delivery_fee'     => Order::SHIPPING_FEE_PER_SELLER,
                    ]);

                    PickupRequest::openForDelivery($delivery);
                }

                // Deduct stock (guarded: fails the whole checkout when short).
                foreach ($cart->items as $item) {
                    if (! \App\Support\Stock::deduct($item->product_id, $item->variant_id, $item->quantity)) {
                        throw new \Exception("Insufficient stock for \"{$item->product->name}\".");
                    }
                }

                $cart->items()->delete();

                return $order;
            });

            $order->load(['items', 'payment', 'delivery', 'orderVouchers.voucher']);
            $this->hideBuyerVoucherCodes(collect([$order]));

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
        $this->hideBuyerVoucherCodes(collect([$order]));

        return response()->json(['order' => $order]);
    }

    public function cancel(Request $request, $id)
    {
        $validated = $request->validate([
            'reason' => ['required', 'in:changed_mind,ordered_by_mistake,found_better_price,delivery_too_long,wrong_item,other'],
            'reason_details' => ['nullable', 'required_if:reason,other', 'string', 'min:10', 'max:300'],
        ]);
        $order = Order::with('items')->where('buyer_id', $request->user()->id)->findOrFail($id);

        if (! $order->isCancellableByBuyer()) {
            return response()->json(['message' => 'This order can no longer be cancelled — it is already on its way to you.'], 422);
        }

        $labels = ['changed_mind'=>'Changed my mind','ordered_by_mistake'=>'Ordered by mistake','found_better_price'=>'Found a better price','delivery_too_long'=>'Delivery is taking too long','wrong_item'=>'I ordered the wrong item','other'=>'Other'];
        $reason = $labels[$validated['reason']] . ($validated['reason'] === 'other' ? ': '.trim($validated['reason_details']) : '');
        $fromStatus = $order->status;
        $order->update(['status' => 'cancelled', 'cancellation_reason' => $reason]);

        OrderStatusHistory::create([
            'order_id'   => $order->id,
            'from_status' => $fromStatus,
            'to_status'  => 'cancelled',
            'note'       => 'Cancelled by buyer: '.$reason,
            'created_at' => now(),
        ]);

        // Only pre-shipment cancels give stock back (pending/confirmed reach here).
        \App\Support\Stock::restoreOrder($order->fresh('items'));
        \App\Support\Payments::voidForCancel($order);
        \App\Support\Vouchers::restoreForOrder($order);

        return response()->json(['message' => 'Order cancelled.']);
    }

    public function rate(Request $request, $id)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'rating'     => ['required', 'integer', 'between:1,5'],
            'comment'    => ['nullable', 'string', 'max:500'],
        ]);

        $order = Order::where('buyer_id', $request->user()->id)->findOrFail($id);
        if ($order->status !== 'delivered') {
            return response()->json(['message' => 'You can rate products after the order is delivered.'], 422);
        }

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

    public function requestReturn(Request $request, $id)
    {
        $validated = $request->validate([
            'product_id'     => ['required', 'exists:products,id'],
            'reason'         => ['required', 'in:damaged,wrong_item,not_as_described,changed_mind,other'],
            'reason_details' => ['nullable', 'required_if:reason,other', 'string', 'min:10', 'max:300'],
            'quantity'       => ['nullable', 'integer', 'min:1', 'max:9999'],
        ]);

        $order = Order::with('items')->where('buyer_id', $request->user()->id)->findOrFail($id);

        if ($order->status !== 'delivered') {
            return response()->json(['message' => 'You can only request a return after the order is delivered.'], 422);
        }

        $windowDays = max(1, (int) \App\Models\Setting::get('return_window_days', 7));
        if ($order->updated_at && $order->updated_at->lt(now()->subDays($windowDays))) {
            return response()->json(['message' => "The return window ({$windowDays} days after delivery) has closed."], 422);
        }

        $item = $order->items->firstWhere('product_id', (int) $validated['product_id']);
        if (! $item) {
            return response()->json(['message' => 'That product is not part of this order.'], 422);
        }

        $open = ReturnRequest::where('order_id', $order->id)
            ->where('order_item_id', $item->id)
            ->whereIn('status', ['requested', 'approved', 'item_received'])
            ->exists();
        if ($open) {
            return response()->json(['message' => 'You already have an open return for this item.'], 409);
        }

        $qty = min((int) ($validated['quantity'] ?? $item->quantity), (int) $item->quantity);
        $unit = (float) ($item->unit_price ?? $item->price ?? 0);
        $refund = round($unit * $qty, 2);

        $return = ReturnRequest::create([
            'order_id'      => $order->id,
            'order_item_id' => $item->id,
            'buyer_id'      => $request->user()->id,
            'seller_id'     => $item->seller_id,
            'product_id'    => $item->product_id,
            'quantity'      => $qty,
            'reason'        => $validated['reason'],
            'reason_details' => $validated['reason_details'] ?? null,
            'refund_amount' => $refund,
            'status'        => 'requested',
        ]);

        return response()->json([
            'message' => 'Return requested. The seller will review it shortly.',
            'return'  => $return,
        ], 201);
    }

    private function hideBuyerVoucherCodes($orders): void
    {
        foreach ($orders as $order) {
            foreach ($order->orderVouchers ?? [] as $orderVoucher) {
                $voucher = $orderVoucher->voucher;
                if (! $voucher) continue;
                if ($voucher->name === $voucher->code) $voucher->setAttribute('name', 'Store offer');
                $voucher->makeHidden('code');
            }
        }
    }
}
