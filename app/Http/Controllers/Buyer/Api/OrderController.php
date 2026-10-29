<?php

namespace App\Http\Controllers\Buyer\Api;

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
use App\Models\SellerNotification;
use App\Models\Setting;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherClaim;
use App\Support\DeliveryServiceability;
use App\Support\OrderCancellation;
use App\Support\Payments;
use App\Support\Shipping;
use App\Support\Stock;
use App\Support\Vouchers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function preview(Request $request)
    {
        $data = $request->validate($this->selectionRules());
        $cart = $this->checkoutCart($request->user()->id, $data['cart_item_ids'] ?? null);
        $quote = $this->quoteCart($cart, $request->user(), $data);

        return response()->json(['quote' => [
            'subtotal' => $quote['subtotal'],
            'shipping_fee' => round($quote['shippingTotal'] - $quote['waivedTotal'], 2),
            'discount' => $quote['discount'],
            'shipping_discount' => $quote['waivedTotal'],
            'total' => $quote['total'],
            'cart_item_ids' => $cart->items->pluck('id')->all(),
        ], 'vouchers' => Vouchers::checkoutOffers($request->user()->id, $quote['sellerSubtotals'])]);
    }

    private function selectionRules(): array
    {
        return [
            'cart_item_ids' => ['sometimes', 'array', 'min:1', 'max:200'],
            'cart_item_ids.*' => ['required', 'integer', 'distinct', 'min:1'],
            'voucher_claim_id' => ['nullable', 'integer'],
            'voucher_claim_ids' => ['nullable', 'array', 'max:3'],
            'voucher_claim_ids.*' => ['integer', 'distinct'],
        ];
    }

    private function checkoutCart(int $buyerId, ?array $selected): Cart
    {
        $cart = Cart::where('buyer_id', $buyerId)->with([
            'items' => fn ($q) => $q->when($selected !== null, fn ($q) => $q->whereIn('id', $selected)),
            'items.product', 'items.variant',
        ])->first();
        if (! $cart || $cart->items->isEmpty() || ($selected !== null && $cart->items->count() !== count($selected))) {
            throw ValidationException::withMessages(['cart_item_ids' => 'Your selected cart items are empty or unavailable.']);
        }

        return $cart;
    }

    private function quoteCart(Cart $cart, User $user, array $data): array
    {
        $subtotal = 0;
        $lineItems = [];
        $sellerSubtotals = [];
        $productQuantities = [];
        $variantQuantities = [];
        foreach ($cart->items as $item) {
            $product = $item->product;
            $variant = $item->variant;
            if (! $product || $product->status !== 'active' || ($item->variant_id && (! $variant || $variant->status !== 'active' || (int) $variant->product_id !== (int) $product->id))) {
                throw ValidationException::withMessages(['cart_item_ids' => 'A selected product or variation is no longer available.']);
            }
            $available = Cart::availableFor($product, $variant, $item->variant_id);
            if ($item->quantity < 1 || $item->quantity > $available) {
                throw ValidationException::withMessages(['cart_item_ids' => 'Insufficient stock for '.$product->name.'.']);
            }
            $productQuantities[$product->id] = ($productQuantities[$product->id] ?? 0) + $item->quantity;
            if ($productQuantities[$product->id] > $product->stock) {
                throw ValidationException::withMessages(['cart_item_ids' => 'The selected variations exceed the general stock for '.$product->name.'.']);
            }
            if ($variant) {
                $variantQuantities[$variant->id] = ($variantQuantities[$variant->id] ?? 0) + $item->quantity;
                if ($variantQuantities[$variant->id] > $variant->stock) {
                    throw ValidationException::withMessages(['cart_item_ids' => 'Insufficient variation stock for '.$product->name.'.']);
                }
            }
            if (! $variant && $product->variants()->where('status', 'active')->exists()) {
                throw ValidationException::withMessages(['cart_item_ids' => 'Please select a variation for '.$product->name.'.']);
            }
            $unit = Cart::unitFor($product, $variant);
            if ($unit < 0) {
                throw ValidationException::withMessages(['cart_item_ids' => 'A selected item has an invalid price. Please contact the store.']);
            }
            $lineTotal = round($unit * $item->quantity, 2);
            $subtotal += $lineTotal;
            $sellerId = (int) $product->seller_id;
            $sellerSubtotals[$sellerId] = ($sellerSubtotals[$sellerId] ?? 0) + $lineTotal;
            $lineItems[] = [
                'product_id' => $product->id, 'product_variant_id' => $item->variant_id,
                'seller_id' => $sellerId, 'product_name' => $product->name,
                'variant_label' => Cart::labelFor($variant), 'quantity' => $item->quantity, 'price' => $unit,
            ];
        }
        $sellerIds = array_keys($sellerSubtotals);
        foreach ($sellerIds as $sellerId) {
            $store = Seller::where('user_id', $sellerId)->approved()->with('user')->first();
            if (! $store || ! $store->user?->isActive()) {
                throw ValidationException::withMessages(['cart_item_ids' => 'A selected store is no longer available.']);
            }
            if ($problem = DeliveryServiceability::pickupAddressProblem($sellerId)) {
                throw ValidationException::withMessages(['cart_item_ids' => $problem]);
            }
        }
        $onVacation = Seller::whereIn('user_id', $sellerIds)
            ->where('vacation_mode', true)->exists();
        if ($onVacation) {
            throw ValidationException::withMessages(['cart_item_ids' => 'A selected store is on vacation.']);
        }

        $groupIds = Shipping::groups($sellerIds);
        $shippingTotal = Shipping::totalForGroups($groupIds);
        $claimIds = array_values(array_unique(array_filter(array_merge(
            array_map('intval', $data['voucher_claim_ids'] ?? []),
            ! empty($data['voucher_claim_id']) ? [(int) $data['voucher_claim_id']] : []
        ))));
        $discount = 0;
        $perVoucher = [];
        $waivedTotal = 0;
        if ($claimIds) {
            $claims = VoucherClaim::with('voucher')->where('buyer_id', $user->id)->whereNull('used_at')->whereIn('id', $claimIds)->get();
            $vouchers = $claims->map(fn ($claim) => $claim->voucher)->filter();
            if ($claims->count() !== count($claimIds) || $vouchers->count() !== count($claimIds) || $vouchers->contains(fn ($v) => ! $v->is_active || ($v->usage_limit !== null && $v->used_count >= $v->usage_limit))) {
                throw ValidationException::withMessages(['voucher_claim_ids' => 'One of the claimed vouchers is unavailable.']);
            }
            $stack = Vouchers::resolveStack($vouchers, $subtotal, $groupIds, $sellerSubtotals);
            if ($stack['error']) {
                throw ValidationException::withMessages(['voucher_claim_ids' => $stack['error']]);
            }
            $discount = $stack['merch'];
            $waivedTotal = round(array_sum($stack['waived']), 2);
            $perVoucher = $stack['perVoucher'];
        }
        $total = round($subtotal - $discount + $shippingTotal - $waivedTotal, 2);

        return compact('subtotal', 'sellerSubtotals', 'lineItems', 'groupIds', 'shippingTotal', 'discount', 'waivedTotal', 'perVoucher', 'claimIds', 'total');
    }

    public function index(Request $request)
    {
        $query = Order::with(['items.product', 'payment', 'delivery', 'orderVouchers.voucher'])
            ->where('buyer_id', $request->user()->id)
            ->latest();

        if ($request->input('status') === 'return') {
            $query->whereHas('returnRequests', fn ($returns) => $returns->where('buyer_id', $request->user()->id));
        } elseif ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        $orders = $query->get();
        $this->hideBuyerVoucherCodes($orders);

        return response()->json(['orders' => $orders]);
    }

    public function checkout(Request $request)
    {
        $validated = $request->validate($this->selectionRules() + [
            'address_id' => ['required', 'exists:addresses,id'],
            'payment_method' => ['required', 'in:cash_on_delivery'],
            'voucher_claim_id' => ['nullable', 'integer', 'exists:voucher_claims,id'],
            'voucher_claim_ids' => ['nullable', 'array', 'max:3'],
            'voucher_claim_ids.*' => ['integer', 'exists:voucher_claims,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();

        $address = Address::where('id', $validated['address_id'])
            ->where('buyer_id', $user->id)
            ->firstOrFail();

        if ($problem = DeliveryServiceability::dropoffAddressProblem($address)) {
            return response()->json(['message' => $problem], 422);
        }

        try {
            $order = DB::transaction(function () use ($user, $address, $validated) {
                $header = Cart::where('buyer_id', $user->id)->lockForUpdate()->first();
                if (! $header) {
                    throw ValidationException::withMessages(['cart_item_ids' => 'Your cart is empty.']);
                }
                $lines = $header->items()->orderBy('id')->lockForUpdate()->get();
                Product::whereIn('id', $lines->pluck('product_id'))->orderBy('id')->lockForUpdate()->get();
                \App\Models\ProductVariant::whereIn('id', $lines->pluck('variant_id')->filter())->orderBy('id')->lockForUpdate()->get();
                $cart = $this->checkoutCart($user->id, $validated['cart_item_ids'] ?? null);
                if ($cart->items->sum('quantity') > 200) {
                    throw ValidationException::withMessages(['cart_item_ids' => 'Checkout is limited to 200 items.']);
                }
                $claimIds = array_values(array_unique(array_filter(array_merge($validated['voucher_claim_ids'] ?? [], [$validated['voucher_claim_id'] ?? null]))));
                $lockedClaims = collect();
                if ($claimIds) {
                    $lockedClaims = VoucherClaim::whereIn('id', $claimIds)->where('buyer_id', $user->id)
                        ->whereNull('used_at')->lockForUpdate()->get();
                    $lockedVouchers = Voucher::whereIn('id', $lockedClaims->pluck('voucher_id'))
                        ->lockForUpdate()->get()->keyBy('id');
                    if ($lockedClaims->count() !== count($claimIds)) {
                        throw ValidationException::withMessages(['voucher_claim_ids' => 'One of the claimed vouchers is no longer available.']);
                    }
                    foreach ($lockedClaims as $lc) {
                        $lv = $lockedVouchers->get($lc->voucher_id);
                        if (! $lv || ! $lv->is_active || ($lv->usage_limit !== null && $lv->used_count >= $lv->usage_limit)) {
                            throw ValidationException::withMessages(['voucher_claim_ids' => 'One of the claimed vouchers is no longer available.']);
                        }
                    }
                }
                // Recalculate after the cart, stock and voucher rows are locked.
                [
                    'subtotal' => $subtotal, 'lineItems' => $lineItems, 'groupIds' => $groupIds,
                    'shippingTotal' => $shippingTotal, 'discount' => $discount,
                    'waivedTotal' => $waivedTotal, 'perVoucher' => $perVoucher,
                    'claimIds' => $claimIds, 'total' => $total,
                ] = $this->quoteCart($cart, $user, $validated);
                $order = Order::create([
                    'seller_id' => $groupIds[0] ?? null,
                    'buyer_id' => $user->id,
                    'address_id' => $address->id,
                    'shipping_address' => $address->toShippingSnapshot(),
                    'total_amount' => $total,
                    'sub_total' => $subtotal,
                    'discount_total' => round($discount + $waivedTotal, 2),
                    'shipping_fee' => round($shippingTotal - $waivedTotal, 2),
                    'status' => 'pending',
                    'notes' => $validated['notes'] ?? null,
                ]);

                foreach ($lineItems as $li) {
                    OrderItem::create([...$li, 'order_id' => $order->id]);
                }

                Payment::create([
                    'order_id' => $order->id,
                    'method' => $validated['payment_method'],
                    'status' => 'pending',
                    'amount' => $total,
                ]);

                // Open the money ledger row (pending until delivery settles it).
                Payments::recordCheckout($order);

                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'to_status' => 'pending',
                    'note' => 'Order placed.',
                    'created_at' => now(),
                ]);

                foreach ($lockedClaims as $lc) {
                    $amount = ($perVoucher[$lc->voucher_id]['merch'] ?? 0) + ($perVoucher[$lc->voucher_id]['waived'] ?? 0);
                    OrderVoucher::create([
                        'order_id' => $order->id,
                        'voucher_id' => $lc->voucher_id,
                        'discount_amount' => round($amount, 2),
                    ]);
                    Voucher::whereKey($lc->voucher_id)->increment('used_count');
                    $lc->update(['order_id' => $order->id, 'used_at' => now()]);
                }

                // One delivery per seller group: the rider earns the flat
                // fee once per delivery, independent of any voucher.
                foreach ($groupIds as $sid) {
                    $delivery = Delivery::create([
                        'order_id' => $order->id,
                        'seller_id' => $sid,
                        'status' => 'waiting_for_rider',
                        'payment_method' => $validated['payment_method'],
                        'amount_to_collect' => $order->cashToCollectForSeller((int) $sid),
                        'delivery_fee' => Order::SHIPPING_FEE_PER_SELLER,
                    ]);

                    PickupRequest::openForDelivery($delivery);
                }

                // Deduct stock (guarded: fails the whole checkout when short).
                foreach ($cart->items as $item) {
                    if (! Stock::deduct($item->product_id, $item->variant_id, $item->quantity)) {
                        throw ValidationException::withMessages(['cart_item_ids' => "Insufficient stock for {$item->product->name}."]);
                    }
                }

                $cart->items()->whereIn('id', $cart->items->pluck('id'))->delete();

                return $order;
            });

            $order->load(['items.product', 'payment', 'delivery', 'orderVouchers.voucher']);
            $this->hideBuyerVoucherCodes(collect([$order]));

            return response()->json([
                'message' => 'Order placed successfully. Payment method: Cash on Delivery.',
                'order' => $order,
            ], 201);
        } catch (\Throwable $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            report($e);
            return response()->json(['message' => 'Unable to place order. Nothing was charged. Refresh your cart and try again.'], 500);
        }
    }

    public function show(Request $request, $id)
    {
        $order = Order::with(['items.product', 'payment', 'delivery', 'deliveries', 'orderVouchers.voucher', 'address', 'statusHistories'])
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

        $labels = ['changed_mind' => 'Changed my mind', 'ordered_by_mistake' => 'Ordered by mistake', 'found_better_price' => 'Found a better price', 'delivery_too_long' => 'Delivery is taking too long', 'wrong_item' => 'I ordered the wrong item', 'other' => 'Other'];
        $reason = $labels[$validated['reason']].($validated['reason'] === 'other' ? ': '.trim($validated['reason_details']) : '');
        OrderCancellation::byBuyer($order, $reason, (int) $request->user()->id);

        return response()->json(['message' => 'Order cancelled.']);
    }

    public function rate(Request $request, $id)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:500'],
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
                'buyer_id' => $request->user()->id,
                'product_id' => $validated['product_id'],
                'order_id' => $order->id,
            ],
            [
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
                'status' => 'visible',
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
            'order_item_id' => ['required_without:product_id', 'integer', 'min:1'],
            'product_id' => ['required_without:order_item_id', 'integer', 'min:1'],
            'reason' => ['required', 'in:damaged,wrong_item,not_as_described,changed_mind,other'],
            'reason_details' => ['nullable', 'required_if:reason,other', 'string', 'max:300', ...($request->input('reason') === 'other' ? ['min:10'] : [])],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ]);

        return DB::transaction(function () use ($request, $validated, $id) {
            $order = Order::with('items')->where('buyer_id', $request->user()->id)->lockForUpdate()->findOrFail($id);

            if ($order->status !== 'delivered') {
                return response()->json(['message' => 'You can only request a return after the order is delivered.'], 422);
            }

            $matches = ! empty($validated['order_item_id'])
                ? $order->items->where('id', (int) $validated['order_item_id'])
                : $order->items->where('product_id', (int) $validated['product_id']);
            if ($matches->count() !== 1) {
                throw ValidationException::withMessages(['order_item_id' => 'Choose the specific purchased item or variation to return.']);
            }
            $item = $matches->first();
            if (! empty($validated['product_id']) && (int) $item->product_id !== (int) $validated['product_id']) {
                throw ValidationException::withMessages(['order_item_id' => 'This item does not match the selected product.']);
            }
            $windowDays = max(1, (int) Setting::get('return_window_days', 7));
            $deliveredAt = $order->statusHistories()->where('to_status', 'delivered')->oldest('created_at')->value('created_at');
            if (! $deliveredAt) {
                $deliveredAt = $order->deliveries()->where('seller_id', $item->seller_id)->where('status', 'delivered')->max('delivered_at');
            }
            // Older data without a delivery event uses the immutable order creation time.
            $deliveredAt = \Illuminate\Support\Carbon::parse($deliveredAt ?: $order->created_at);
            if ($deliveredAt->lt(now()->subDays($windowDays))) {
                return response()->json(['message' => "The return window ({$windowDays} days after delivery) has closed."], 422);
            }

            $open = ReturnRequest::where('order_id', $order->id)
                ->where('order_item_id', $item->id)
                ->whereIn('status', ['requested', 'approved', 'item_received'])
                ->first();
            if ($open) {
                return response()->json(['message' => 'You already have an open return for this item.', 'return' => $open], 409);
            }

            $completed = ReturnRequest::where('order_item_id', $item->id)->where('status', 'refunded');
            $remaining = max(0, (int) $item->quantity - (int) (clone $completed)->sum('quantity'));
            $qty = (int) ($validated['quantity'] ?? $remaining);
            if ($qty < 1 || $qty > $remaining) {
                return response()->json(['message' => 'The quantity exceeds the remaining returnable items.'], 422);
            }
            $sellerSubtotal = $order->sellerSubtotal((int) $item->seller_id);
            $discount = $sellerSubtotal > 0
                ? $order->voucherDiscountForSeller((int) $item->seller_id) * $item->lineTotal / $sellerSubtotal
                : 0;
            $lineNet = round(max(0, $item->lineTotal - $discount), 2);
            $remainingRefund = round(max(0, $lineNet - (float) (clone $completed)->sum('refund_amount')), 2);
            $refund = $qty === $remaining
                ? $remainingRefund
                : min(round($lineNet * $qty / $item->quantity, 2), $remainingRefund);

            $return = ReturnRequest::create([
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'buyer_id' => $request->user()->id,
                'seller_id' => $item->seller_id,
                'product_id' => $item->product_id,
                'quantity' => $qty,
                'reason' => $validated['reason'],
                'reason_details' => $validated['reason_details'] ?? null,
                'refund_amount' => $refund,
                'status' => 'requested',
            ]);

            SellerNotification::create([
                'seller_id' => $item->seller_id,
                'order_id' => $order->id,
                'product_id' => $item->product_id,
                'type' => 'return_requested',
                'title' => 'New return request',
                'body' => 'Return #'.$return->id.' for '.$qty.' × '.($item->product_name ?: 'purchased item').'. Review it in Returns & Refunds.',
                'is_read' => false,
            ]);

            return response()->json([
                'message' => 'Return requested. The seller will review it shortly.',
                'return' => $return,
            ], 201);
        }, 3);
    }

    private function hideBuyerVoucherCodes($orders): void
    {
        foreach ($orders as $order) {
            $order->setAttribute('can_cancel', $order->isCancellableByBuyer());
            $order->setAttribute('returns', ReturnRequest::where('order_id', $order->id)->where('buyer_id', $order->buyer_id)->get());
            foreach ($order->items as $item) {
                $item->setAttribute('subtotal', $item->lineTotal);
                $item->setAttribute('product_image', $item->product?->image);
            }
            foreach ($order->orderVouchers ?? [] as $orderVoucher) {
                $voucher = $orderVoucher->voucher;
                if (! $voucher) {
                    continue;
                }
                if ($voucher->name === $voucher->code) {
                    $voucher->setAttribute('name', 'Store offer');
                }
                $voucher->makeHidden('code');
            }
        }
    }
}
