<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Message;
use App\Models\NotificationsLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\SellerProfile;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SellerCenterController extends Controller
{
    protected function seller(): Seller
    {
        return Seller::find(session('seller_id')) ?? abort(403);
    }

    protected function page(array $data, string $active, string $title)
    {
        return view('seller.' . $active, $data)->with('active', $active)->with('title', $title);
    }

    // ------------------------------------------------------------------ Orders
    // Same order pipeline as the original seller app (accept → prepare →
    // pack → hand over → deliver), applied to this seller's items only.
    // Buyer checkout, seller fulfillment and admin oversight all read the
    // same statuses, history timeline and totals.
    protected const ORDER_TRANSITIONS = [
        'pending'             => ['confirmed', 'cancelled'],
        'confirmed'           => ['processing', 'cancelled'],
        'processing'          => ['ready_for_delivery'],
        'ready_for_delivery'  => ['out_for_delivery'],
        'out_for_delivery'    => ['delivered'],
        'delivered'           => [],
        'cancelled'           => [],
    ];

    /** Both id conventions that can appear as seller_id in the shared DB. */
    protected function catalogIds(): array
    {
        return $this->seller()->catalogIds();
    }

    protected function sellerOrderQuery()
    {
        $ids = $this->catalogIds();
        return Order::where(function ($q) use ($ids) {
            $q->whereIn('seller_id', $ids)
              ->orWhereHas('items', fn ($iq) => $iq->whereIn('seller_id', $ids));
        });
    }

    public function orders(Request $request)
    {
        $ids = $this->catalogIds();
        $status = $request->input('status', 'all');

        $query = Order::with(['buyer', 'items', 'payment', 'delivery', 'courierPickup'])
            ->whereHas('items', fn ($q) => $q->whereIn('seller_id', $ids))
            ->latest();

        if ($status !== 'all' && array_key_exists($status, self::ORDER_TRANSITIONS)) {
            $query->where('status', $status);
        }

        $orders = $query->get()->map(function (Order $order) use ($ids) {
            $order->seller_items = $order->items->whereIn('seller_id', $ids)->values();
            $order->seller_subtotal = round($order->seller_items->sum(fn ($it) => $it->line_total ?? (($it->unit_price ?? $it->price ?? 0) * $it->quantity)), 2);
            return $order;
        });

        $counts = ['all' => 0];
        foreach (array_keys(self::ORDER_TRANSITIONS) as $s) {
            $counts[$s] = Order::whereHas('items', fn ($q) => $q->whereIn('seller_id', $ids))->where('status', $s)->count();
        }
        $counts['all'] = Order::whereHas('items', fn ($q) => $q->whereIn('seller_id', $ids))->count();

        return $this->page(compact('orders', 'status', 'counts'), 'orders', 'Orders');
    }

    public function orderShow($id)
    {
        $ids = $this->catalogIds();
        $order = Order::with(['buyer', 'address', 'items', 'payment', 'delivery', 'courierPickup', 'orderVouchers.voucher', 'statusHistories'])
            ->whereHas('items', fn ($q) => $q->whereIn('seller_id', $ids))
            ->findOrFail($id);
        $order->seller_items = $order->items->whereIn('seller_id', $ids)->values();
        $order->seller_subtotal = round($order->seller_items->sum(fn ($it) => $it->line_total ?? (($it->unit_price ?? $it->price ?? 0) * $it->quantity)), 2);
        $transitions = self::ORDER_TRANSITIONS[$order->status] ?? [];
        return view('seller.orders-show', compact('order', 'transitions'))->with('active', 'orders')->with('title', 'Order #' . $order->id);
    }

    /**
     * Accepts the original `action` buttons (accept/process/pack/handover/
     * deliver/cancel) as well as a direct `status` value (used by the
     * quick-update dropdown).
     */
    public function orderStatus(Request $request, Order $order)
    {
        $ids = $this->catalogIds();
        $belongs = in_array($order->seller_id, $ids)
            || OrderItem::where('order_id', $order->id)->whereIn('seller_id', $ids)->exists();
        abort_if(! $belongs, 403);

        $action = $request->input('action');
        $target = match ($action) {
            'accept'   => 'confirmed',
            'process'  => 'processing',
            'pack'     => 'ready_for_delivery',
            'handover' => 'out_for_delivery',
            'deliver'  => 'delivered',
            'cancel'   => 'cancelled',
            default    => $request->input('status'),
        };

        if (! $target || ! in_array($target, self::ORDER_TRANSITIONS[$order->status] ?? [], true)) {
            return back()->with('error', 'This action is not allowed for the current order status.');
        }

        $note = $request->input('note');
        $me = Auth::id();

        \Illuminate\Support\Facades\DB::transaction(function () use ($order, $target, $action, $note, $me) {
            $from = $order->status;
            $order->update(['status' => $target]);

            \App\Models\OrderStatusHistory::create([
                'order_id'    => $order->id,
                'from_status' => $from,
                'to_status'   => $target,
                'note'        => $note ?: $this->defaultOrderNote($action ?? $target),
                'created_at'  => now(),
            ]);

            $this->syncDelivery($order, $target);

            \App\Models\SellerNotification::create([
                'seller_id' => $me,
                'order_id'  => $order->id,
                'type'      => 'status',
                'title'     => $this->orderNotificationTitle($action ?? $target),
                'body'      => "Order #{$order->id} is now {$target}.",
            ]);
        });

        if ($target === 'cancelled') {
            foreach ($order->items as $item) {
                Product::where('id', $item->product_id)->increment('stock', $item->quantity);
            }
        }

        return back()->with('success', $this->orderFlashMessage($action ?? $target));
    }

    public function schedulePickup(Request $request, Order $order)
    {
        $ids = $this->catalogIds();
        $belongs = in_array($order->seller_id, $ids)
            || OrderItem::where('order_id', $order->id)->whereIn('seller_id', $ids)->exists();
        abort_if(! $belongs, 403);
        abort_if(! in_array($order->status, ['ready_for_delivery', 'processing'], true), 403);

        $validated = $request->validate([
            'courier'         => ['required', 'string', 'max:100'],
            'pickup_at'       => ['required', 'date', 'after_or_equal:today'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'notes'           => ['nullable', 'string', 'max:1000'],
        ]);

        \App\Models\CourierPickup::create([
            'order_id'        => $order->id,
            'seller_id'       => Auth::id(),
            'courier'         => $validated['courier'],
            'pickup_at'       => $validated['pickup_at'],
            'tracking_number' => $validated['tracking_number'] ?? null,
            'status'          => 'scheduled',
            'notes'           => $validated['notes'] ?? null,
        ]);

        return back()->with('success', 'Courier pickup scheduled successfully.');
    }

    public function waybill(Order $order)
    {
        $ids = $this->catalogIds();
        $belongs = in_array($order->seller_id, $ids)
            || OrderItem::where('order_id', $order->id)->whereIn('seller_id', $ids)->exists();
        abort_if(! $belongs, 403);
        $order->load(['buyer', 'address', 'items', 'delivery', 'courierPickup']);
        $order->seller_items = $order->items->whereIn('seller_id', $ids)->values();
        $order->seller_subtotal = round($order->seller_items->sum(fn ($it) => $it->line_total ?? (($it->unit_price ?? $it->price ?? 0) * $it->quantity)), 2);
        return view('seller.orders-waybill', compact('order'))->with('active', 'orders')->with('title', 'Waybill #' . $order->id);
    }

    protected function syncDelivery(Order $order, string $target): void
    {
        $delivery = $order->delivery;
        if (! $delivery) {
            $delivery = \App\Models\Delivery::create(['order_id' => $order->id]);
        }
        if ($target === 'out_for_delivery') {
            $data = ['status' => 'picked_up'];
            if (! $delivery->picked_up_at) {
                $data['picked_up_at'] = now();
            }
            $delivery->update($data);
            if ($order->courierPickup) {
                $order->courierPickup->update(['status' => 'picked_up']);
            }
        }
        if ($target === 'delivered') {
            $delivery->update(['status' => 'delivered', 'delivered_at' => now()]);
            if ($order->payment) {
                $order->payment->update(['status' => 'paid', 'paid_at' => now()]);
            }
        }
    }

    protected function defaultOrderNote(?string $action): string
    {
        return match ($action) {
            'accept'   => 'Order accepted by seller.',
            'process'  => 'Order is being prepared.',
            'pack'     => 'Items packed and ready for pickup.',
            'handover' => 'Order handed over to courier.',
            'deliver'  => 'Order delivered to customer.',
            'cancel'   => 'Order cancelled by seller.',
            default    => 'Status updated by seller.',
        };
    }

    protected function orderNotificationTitle(?string $action): string
    {
        return match ($action) {
            'accept'   => 'Order accepted',
            'process'  => 'Order is being prepared',
            'pack'     => 'Order packed',
            'handover' => 'Order handed to courier',
            'deliver'  => 'Delivery confirmed',
            'cancel'   => 'Order cancelled',
            default    => 'Order updated',
        };
    }

    protected function orderFlashMessage(?string $action): string
    {
        return match ($action) {
            'accept'   => 'Order accepted. You can now start preparing it.',
            'process'  => 'Order is now being prepared.',
            'pack'     => 'Order packed. Ready for courier handover.',
            'handover' => 'Order handed over to the courier.',
            'deliver'  => 'Delivery confirmed. The order has been completed.',
            'cancel'   => 'Order cancelled.',
            default    => 'Order updated.',
        };
    }

    // ------------------------------------------------------------------ Inventory
    public function inventory()
    {
        $seller = $this->seller();

        $products = Product::with(['variants', 'category'])
            ->whereIn('seller_id', $seller->catalogIds())
            ->get()
            ->map(function ($p) {
                $variant = $p->variants->first();
                $inv = $variant ? Inventory::where('seller_id', $p->seller_id)
                    ->where('product_variant_id', $variant->id)->first() : null;
                $stock = $inv?->quantity ?? ($variant->stock ?? 0);
                $p->stock = $stock;
                $p->stock_status = $stock <= 0 ? 'out' : ($stock <= 5 ? 'low' : 'in');
                return $p;
            });

        $stats = [
            'total' => $products->count(),
            'in_stock' => $products->where('stock_status', 'in')->count(),
            'low' => $products->where('stock_status', 'low')->count(),
            'out' => $products->where('stock_status', 'out')->count(),
        ];

        return $this->page(compact('products', 'stats'), 'inventory', 'Inventory');
    }

    public function inventoryUpdate(Request $request, Product $product)
    {
        $seller = $this->seller();

        abort_if(! in_array($product->seller_id, $seller->catalogIds()), 403);

        $data = $request->validate([
            'stock' => 'required|integer|min:0|max:99999',
        ]);

        $variant = $product->variants()->firstOrCreate(
            ['sku' => ($product->sku ?? 'SKU') . '-D'],
            ['attributes' => ['Default'], 'price' => $product->price, 'weight' => 0.5, 'variant_type' => 'Default', 'variant_value' => 'Default']
        );

        $variant->stock = $data['stock'];
        $variant->save();

        Inventory::updateOrCreate(
            ['seller_id' => $seller->id, 'product_variant_id' => $variant->id],
            ['quantity' => $data['stock']]
        );

        return back()->with('status', "Stock updated for \"{$product->name}\".");
    }

    // ------------------------------------------------------------------ Products
    // Full catalog management, same as the original seller app: searchable /
    // filterable list, create with variants + image, edit, archive/restore.
    public function products(Request $request)
    {
        $ids = $this->catalogIds();
        $query = Product::with('category')->whereIn('seller_id', $ids)->withCount('variants');

        if ($request->filled('q')) {
            $query->where('name', 'like', '%' . $request->input('q') . '%');
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('low_stock')) {
            $query->where('stock', '<=', 5);
        }

        $products = $query->latest()->get();
        $categories = \App\Models\Category::active()->orderBy('name')->get();

        return $this->page([
            'products'   => $products,
            'categories' => $categories,
            'filters'    => $request->only(['q', 'category_id', 'status', 'low_stock']),
        ], 'products', 'Products');
    }

    public function productCreate()
    {
        $categories = \App\Models\Category::active()->orderBy('name')->get();
        return view('seller.products.create', compact('categories'))->with('active', 'products')->with('title', 'Add Product');
    }

    public function productStore(Request $request)
    {
        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:150'],
            'category_id'  => ['required', 'exists:categories,id'],
            'description'  => ['nullable', 'string', 'max:5000'],
            'price'        => ['required', 'numeric', 'min:0'],
            'cost_price'   => ['nullable', 'numeric', 'min:0'],
            'stock'        => ['required', 'integer', 'min:0'],
            'weight'       => ['nullable', 'string', 'max:50'],
            'dimensions'   => ['nullable', 'string', 'max:100'],
            'image'        => ['nullable', 'image', 'max:5120'],
            'status'       => ['required', 'in:active,inactive,out_of_stock'],
        ]);

        $validated['seller_id'] = Auth::id();
        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        $product = \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $request) {
            $product = Product::create($validated);
            $this->saveProductVariants($product, $request);
            return $product;
        });

        return redirect('/seller/products/' . $product->id . '/edit')
            ->with('status', 'Product added successfully.');
    }

    public function productEdit(Product $product)
    {
        abort_if(! in_array($product->seller_id, $this->catalogIds()), 403);
        $product->load('variants');
        $categories = \App\Models\Category::active()->orderBy('name')->get();
        return view('seller.products.edit', compact('product', 'categories'))->with('active', 'products')->with('title', 'Edit Product');
    }

    public function productUpdate(Request $request, Product $product)
    {
        abort_if(! in_array($product->seller_id, $this->catalogIds()), 403);

        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:150'],
            'category_id'  => ['required', 'exists:categories,id'],
            'description'  => ['nullable', 'string', 'max:5000'],
            'price'        => ['required', 'numeric', 'min:0'],
            'cost_price'   => ['nullable', 'numeric', 'min:0'],
            'stock'        => ['required', 'integer', 'min:0'],
            'weight'       => ['nullable', 'string', 'max:50'],
            'dimensions'   => ['nullable', 'string', 'max:100'],
            'image'        => ['nullable', 'image', 'max:5120'],
            'status'       => ['required', 'in:active,inactive,out_of_stock'],
        ]);

        if ($request->hasFile('image')) {
            if ($product->image) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($product->image);
            }
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($product, $validated, $request) {
            $product->update($validated);
            $this->saveProductVariants($product, $request);
        });

        return back()->with('status', 'Product updated successfully.');
    }

    public function productArchive(Product $product)
    {
        abort_if(! in_array($product->seller_id, $this->catalogIds()), 403);
        $product->update(['status' => 'inactive']);
        return back()->with('status', 'Product archived.');
    }

    public function productRestore(Product $product)
    {
        abort_if(! in_array($product->seller_id, $this->catalogIds()), 403);
        $product->update(['status' => 'active']);
        return back()->with('status', 'Product restored.');
    }

    protected function saveProductVariants(Product $product, Request $request): void
    {
        $types = $request->input('variants.type', []);
        $values = $request->input('variants.value', []);
        $adjustments = $request->input('variants.adjustment', []);
        $stocks = $request->input('variants.stock', []);

        if (empty($types) || empty(array_filter($types))) {
            return;
        }

        $product->variants()->delete();

        foreach ($types as $index => $type) {
            $value = trim($values[$index] ?? '');
            if ($type === null || trim((string) $type) === '' || $value === '') {
                continue;
            }
            $product->variants()->create([
                'variant_type'     => trim($type),
                'variant_value'    => $value,
                'price_adjustment' => (float) ($adjustments[$index] ?? 0),
                'stock'            => (int) ($stocks[$index] ?? 0),
                'status'           => 'active',
            ]);
        }

        $totalVariantStock = $product->variants()->sum('stock');
        if ($totalVariantStock > 0 && $product->status === 'out_of_stock') {
            $product->update(['status' => 'active']);
        }
    }

    // ------------------------------------------------------------------ Vouchers
    public function vouchers()
    {
        $ids = $this->catalogIds();
        $vouchers = Voucher::where(function ($q) use ($ids) {
            $q->whereNull('seller_id')->orWhereIn('seller_id', $ids);
        })->latest()->get();
        return $this->page(compact('vouchers'), 'vouchers', 'Vouchers');
    }

    public function voucherStore(Request $request)
    {
        $data = $request->validate([
            'code'           => 'required|string|max:50|unique:vouchers,code',
            'name'           => 'nullable|string|max:150',
            'description'    => 'nullable|string|max:1000',
            'type'           => 'nullable|in:fixed,percent',
            'discount_type'  => 'nullable|in:fixed,percent',
            'value'          => 'nullable|numeric|min:0.01',
            'discount_value' => 'nullable|numeric|min:0.01',
            'min_spend'      => 'nullable|numeric|min:0',
            'max_discount'   => 'nullable|numeric|min:0',
            'starts_at'      => 'nullable|date',
            'valid_from'     => 'nullable|date',
            'ends_at'        => 'nullable|date',
            'valid_until'    => 'nullable|date',
            'usage_limit'    => 'nullable|integer|min:1',
            'status'         => 'nullable|in:active,inactive',
        ]);

        $type = $data['type'] ?? $data['discount_type'] ?? 'fixed';
        $value = $data['value'] ?? $data['discount_value'] ?? 0;
        $code = strtoupper($data['code']);

        Voucher::create([
            'seller_id'      => Auth::id(),
            'code'           => $code,
            'name'           => $data['name'] ?? $code,
            'description'    => $data['description'] ?? null,
            'type'           => $type,
            'discount_type'  => $type,
            'value'          => $value,
            'discount_value' => $value,
            'min_spend'      => $data['min_spend'] ?? 0,
            'max_discount'   => $data['max_discount'] ?? null,
            'starts_at'      => $data['starts_at'] ?? $data['valid_from'] ?? null,
            'valid_from'     => $data['valid_from'] ?? $data['starts_at'] ?? null,
            'ends_at'        => $data['ends_at'] ?? $data['valid_until'] ?? null,
            'valid_until'    => $data['valid_until'] ?? $data['ends_at'] ?? null,
            'usage_limit'    => $data['usage_limit'] ?? null,
            'status'         => $data['status'] ?? 'active',
        ]);

        return back()->with('status', 'Voucher created.');
    }

    public function voucherUpdate(Request $request, Voucher $voucher)
    {
        $ids = $this->catalogIds();
        abort_if($voucher->seller_id !== null && ! in_array($voucher->seller_id, $ids), 403);

        $data = $request->validate([
            'name'           => 'required|string|max:150',
            'description'    => 'nullable|string|max:1000',
            'type'           => 'nullable|in:fixed,percent',
            'discount_type'  => 'nullable|in:fixed,percent',
            'value'          => 'nullable|numeric|min:0',
            'discount_value' => 'nullable|numeric|min:0',
            'min_spend'      => 'nullable|numeric|min:0',
            'max_discount'   => 'nullable|numeric|min:0',
            'starts_at'      => 'nullable|date',
            'valid_from'     => 'nullable|date',
            'ends_at'        => 'nullable|date',
            'valid_until'    => 'nullable|date',
            'usage_limit'    => 'nullable|integer|min:1',
            'status'         => 'required|in:active,inactive',
        ]);

        $type = $data['type'] ?? $data['discount_type'] ?? $voucher->type;
        $value = $data['value'] ?? $data['discount_value'] ?? $voucher->value;

        $voucher->update([
            'name'           => $data['name'],
            'description'    => $data['description'] ?? null,
            'type'           => $type,
            'discount_type'  => $type,
            'value'          => $value,
            'discount_value' => $value,
            'min_spend'      => $data['min_spend'] ?? 0,
            'max_discount'   => $data['max_discount'] ?? null,
            'starts_at'      => $data['starts_at'] ?? $data['valid_from'] ?? null,
            'valid_from'     => $data['valid_from'] ?? $data['starts_at'] ?? null,
            'ends_at'        => $data['ends_at'] ?? $data['valid_until'] ?? null,
            'valid_until'    => $data['valid_until'] ?? $data['ends_at'] ?? null,
            'usage_limit'    => $data['usage_limit'] ?? null,
            'status'         => $data['status'],
        ]);

        return back()->with('status', 'Voucher updated successfully.');
    }

    public function voucherToggle(Request $request, Voucher $voucher)
    {
        abort_if(! in_array($voucher->seller_id, $this->seller()->catalogIds()), 403);
        $voucher->status = ($voucher->status === 'active') ? 'inactive' : 'active';
        $voucher->save();
        return back()->with('status', 'Voucher status updated.');
    }

    public function voucherDestroy(Request $request, Voucher $voucher)
    {
        abort_if(! in_array($voucher->seller_id, $this->catalogIds()), 403);
        if (($voucher->used_count ?? 0) > 0) {
            return back()->with('error', 'This voucher has already been used and cannot be deleted.');
        }
        $voucher->delete();
        return back()->with('status', 'Voucher deleted.');
    }

    public function feedbackToggle(Request $request, \App\Models\Review $review)
    {
        $ids = $this->catalogIds();
        abort_if(! \App\Models\Product::where('id', $review->product_id)->whereIn('seller_id', $ids)->exists(), 403);

        $review->update([
            'status' => $review->status === 'visible' ? 'hidden' : 'visible',
        ]);

        $avg = \App\Models\Review::where('product_id', $review->product_id)
            ->where('status', 'visible')
            ->avg('rating');
        Product::where('id', $review->product_id)->update([
            'rating' => $avg ? round($avg, 1) : null,
        ]);

        return back()->with('status', $review->status === 'visible'
            ? 'Feedback shown on the product.'
            : 'Feedback hidden from the product.');
    }

    // ------------------------------------------------------------------ Customer feedback
    // Buyer ratings land in `reviews` (same table the buyer app writes),
    // so the seller sees exactly what buyers left.
    public function feedback()
    {
        $seller = $this->seller();
        $ids = $seller->catalogIds();

        $reviews = \App\Models\Review::with(['product', 'buyer'])
            ->whereHas('product', fn ($q) => $q->whereIn('seller_id', $ids))
            ->latest()
            ->paginate(15);
        $reviews->getCollection()->each(fn ($r) => $r->setAttribute('review', $r->review ?? $r->comment));

        $allRatings = \App\Models\Review::whereHas('product', fn ($q) => $q->whereIn('seller_id', $ids))->pluck('rating');
        $rating = round($allRatings->avg() ?? 0, 1);
        $total = $allRatings->count();

        $distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($allRatings as $star) {
            if (isset($distribution[$star])) {
                $distribution[$star]++;
            }
        }

        return $this->page(compact('reviews', 'rating', 'total', 'distribution'), 'feedback', 'Customer Feedback');
    }

    // ------------------------------------------------------------------ Reports
    public function reports()
    {
        $seller = $this->seller();
        $ids = $seller->catalogIds();
        $orderIds = OrderItem::whereIn('seller_id', $ids)->pluck('order_id')->unique()->all();
        $orders = Order::where(function ($q) use ($ids, $orderIds) {
            $q->whereIn('seller_id', $ids);
            if (! empty($orderIds)) $q->orWhereIn('id', $orderIds);
        });

        $revenue = (clone $orders)->where('status', 'delivered')->sum('total');
        $ordersCount = (clone $orders)->count();
        $avgOrder = (clone $orders)->where('status', 'delivered')->avg('total') ?? 0;
        $commission = (clone $orders)->sum('commission_amount');

        $chart = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $chart[] = [
                'label' => $day->format('M d'),
                'value' => round((clone $orders)->where('status', 'delivered')->whereDate('created_at', $day->toDateString())->sum('total'), 2),
            ];
        }

        $topProducts = OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where(function ($q) use ($ids) {
                $q->whereIn('orders.seller_id', $ids)->orWhereIn('order_items.seller_id', $ids);
            })
            ->selectRaw('products.name, SUM(order_items.quantity) as units, SUM(order_items.total_price) as sales')
            ->groupBy('products.name')
            ->orderByDesc('sales')
            ->take(5)
            ->get();

        $driver = \Illuminate\Support\Facades\DB::getDriverName();
        $monthExpr = $driver === 'sqlite' ? "strftime('%Y-%m', created_at)" : "DATE_FORMAT(created_at, '%Y-%m')";
        $monthly = Order::where(function ($q) use ($ids, $orderIds) {
                $q->whereIn('seller_id', $ids);
                if (! empty($orderIds)) $q->orWhereIn('id', $orderIds);
            })
            ->where('status', 'delivered')
            ->selectRaw("$monthExpr as month, SUM(total) as sales, COUNT(*) as orders")
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->take(6)
            ->get();

        return $this->page(compact('revenue', 'ordersCount', 'avgOrder', 'commission', 'chart', 'topProducts', 'monthly'), 'reports', 'Reports');
    }

    // ------------------------------------------------------------------ Chat
    // Conversation threads shared with buyers (same table the buyer app
    // reads), so both sides always see the same messages.
    public function chatIndex()
    {
        $me = Auth::id();
        $conversations = \App\Models\Conversation::with(['buyer', 'lastMessage.sender'])
            ->where(function ($q) use ($me) {
                $q->where('seller_id', $me)
                  ->orWhereIn('seller_id', $this->catalogIds())
                  ->orWhereHas('buyer', function ($buyer) use ($me) {
                      $buyer->whereHas('orders.items', function ($items) use ($me) {
                          $items->whereIn('seller_id', [$me]);
                      });
                  });
            })
            ->withCount(['messages as unread_count' => function ($q) use ($me) {
                $q->where('is_read', false)->where('sender_id', '!=', $me);
            }])
            ->latest('updated_at')
            ->get();

        return view('seller.chat.index', compact('conversations'))->with('active', 'chat')->with('title', 'Chat / Messaging');
    }

    public function chatShow($id)
    {
        $me = Auth::id();
        $conversation = \App\Models\Conversation::with(['buyer', 'messages.sender'])
            ->where(function ($q) use ($me) {
                $q->where('seller_id', $me)
                  ->orWhereIn('seller_id', $this->catalogIds())
                  ->orWhereHas('buyer', function ($buyer) use ($me) {
                      $buyer->whereHas('orders.items', function ($items) use ($me) {
                          $items->whereIn('seller_id', [$me]);
                      });
                  });
            })
            ->findOrFail($id);

        \App\Models\Message::where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $me)
            ->update(['is_read' => true]);

        if (! $conversation->seller_id) {
            $conversation->update(['seller_id' => $me]);
        }

        $messages = $conversation->messages()->with('sender')->orderBy('created_at')->get();

        return view('seller.chat.show', compact('conversation', 'messages'))->with('active', 'chat')->with('title', 'Chat / Messaging');
    }

    public function chatReply(Request $request, $id)
    {
        $me = Auth::id();
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $conversation = \App\Models\Conversation::where(function ($q) use ($me) {
            $q->where('seller_id', $me)
              ->orWhereIn('seller_id', $this->catalogIds())
              ->orWhereHas('buyer', function ($buyer) use ($me) {
                  $buyer->whereHas('orders.items', function ($items) use ($me) {
                      $items->whereIn('seller_id', [$me]);
                  });
              });
        })->findOrFail($id);

        if (! $conversation->seller_id) {
            $conversation->update(['seller_id' => $me]);
        }

        \App\Models\Message::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $me,
            'receiver_id'     => $conversation->buyer_id,
            'body'            => $validated['body'],
            'is_read'         => false,
        ]);

        $conversation->touch();

        return back()->with('status', 'Message sent.');
    }

    // ------------------------------------------------------------------ Notifications
    public function notifications()
    {
        $notifications = \App\Models\SellerNotification::forSeller(Auth::id())
            ->latest()
            ->paginate(30);

        return $this->page(compact('notifications'), 'notifications', 'Notifications');
    }

    public function notificationRead($id)
    {
        \App\Models\SellerNotification::forSeller(Auth::id())
            ->where('id', $id)
            ->update(['is_read' => true]);

        return back();
    }

    public function notificationsReadAll()
    {
        \App\Models\SellerNotification::forSeller(Auth::id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return back()->with('status', 'All notifications marked as read.');
    }

    // ------------------------------------------------------------------ Account
    public function account()
    {
        $seller = $this->seller();
        $profile = $seller->profile ?? new SellerProfile(['seller_id' => $seller->id]);
        return $this->page(compact('seller', 'profile'), 'account', 'Account Management');
    }

    public function accountUpdate(Request $request)
    {
        $seller = $this->seller();

        $data = $request->validate([
            'store_name' => 'required|string|max:255',
            'business_info' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'operating_hours' => 'nullable|string|max:255',
            'primary_color' => 'nullable|string|max:9',
            'accent_color' => 'nullable|string|max:9',
            'logo' => 'nullable|string|max:255',
        ]);

        $seller->store_name = $data['store_name'];
        $seller->primary_color = $data['primary_color'] ?? $seller->primary_color;
        $seller->accent_color = $data['accent_color'] ?? $seller->accent_color;
        $seller->logo = $data['logo'] ?? $seller->logo;
        $seller->save();

        SellerProfile::updateOrCreate(
            ['seller_id' => $seller->id],
            [
                'business_info' => $data['business_info'] ?? null,
                'description' => $data['description'] ?? null,
                'contact_email' => $data['contact_email'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
                'address' => $data['address'] ?? null,
                'operating_hours' => $data['operating_hours'] ?? null,
            ]
        );

        return back()->with('status', 'Store account updated.');
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'middle_initial' => ['nullable', 'string', 'max:10'],
            'phone'      => ['required', 'string', 'max:30'],
            'email'      => ['required', 'email', \Illuminate\Validation\Rule::unique('users', 'email')->ignore($user->id)],
            'sex'        => ['required', 'in:male,female,other'],
        ]);

        $user->update($validated);

        return back()->with('status', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password'     => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        if (! \Illuminate\Support\Facades\Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'The current password is incorrect.']);
        }

        $user->update(['password' => \Illuminate\Support\Facades\Hash::make($validated['new_password'])]);

        return back()->with('status', 'Password updated successfully.');
    }
}