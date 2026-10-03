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
    // Corresponds with buyer checkout (/cart,/checkout) and admin orders:
    // same statuses, same history timeline, same totals.
    public function orders(Request $request)
    {
        $seller = $this->seller();
        $ids = $seller->catalogIds();
        $orderIds = OrderItem::whereIn('seller_id', $ids)->pluck('order_id')->unique()->all();
        $q = Order::with(['buyer', 'items.product'])
            ->where(function ($qq) use ($ids, $orderIds) {
                $qq->whereIn('seller_id', $ids);
                if (! empty($orderIds)) $qq->orWhereIn('id', $orderIds);
            });
        if ($request->filled('status')) $q->where('status', $request->status);
        $orders = $q->latest()->paginate(20);
        return $this->page(compact('orders'), 'orders', 'Orders');
    }

    public function orderStatus(Request $request, Order $order)
    {
        $seller = $this->seller();
        $ids = $seller->catalogIds();
        $belongs = in_array($order->seller_id, $ids)
            || OrderItem::where('order_id', $order->id)->whereIn('seller_id', $ids)->exists();
        abort_if(! $belongs, 403);

        $data = $request->validate([
            'status' => 'required|in:pending,processing,shipped,delivered,cancelled',
        ]);

        $from = $order->status;
        $order->update(['status' => $data['status']]);

        \App\Models\OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => $from,
            'to_status' => $data['status'],
            'note' => 'Status updated by seller.',
        ]);

        // Cancelling restores stock, same as buyer cancel flow.
        if ($data['status'] === 'cancelled' && $from !== 'cancelled') {
            foreach ($order->items as $item) {
                Product::where('id', $item->product_id)->increment('stock', $item->quantity);
            }
        }

        return back()->with('status', "Order #{$order->id} moved to {$data['status']}.");
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

    // ------------------------------------------------------------------ Vouchers
    public function vouchers()
    {
        $seller = $this->seller();
        $vouchers = Voucher::whereIn('seller_id', $seller->catalogIds())->latest()->get();
        return $this->page(compact('vouchers'), 'vouchers', 'Vouchers');
    }

    public function voucherStore(Request $request)
    {
        $seller = $this->seller();

        $data = $request->validate([
            'code' => 'required|string|max:30|unique:vouchers,code',
            'type' => 'required|in:fixed,percent',
            'value' => 'required|numeric|min:0.01',
            'min_spend' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
        ]);

        Voucher::create([
            'seller_id' => $seller->id,
            'code' => strtoupper($data['code']),
            'name' => strtoupper($data['code']),
            'type' => $data['type'],
            'discount_type' => $data['type'],
            'value' => $data['value'],
            'discount_value' => $data['value'],
            'min_spend' => $data['min_spend'] ?? 0,
            'usage_limit' => $data['usage_limit'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'status' => 'active',
        ]);

        return back()->with('status', 'Voucher created.');
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
        abort_if(! in_array($voucher->seller_id, $this->seller()->catalogIds()), 403);
        $voucher->delete();
        return back()->with('status', 'Voucher deleted.');
    }

    // ------------------------------------------------------------------ Customer feedback
    public function feedback()
    {
        $seller = $this->seller();
        $ids = $seller->catalogIds();

        $reviews = ProductReview::with(['product', 'buyer'])
            ->whereHas('product', fn ($q) => $q->whereIn('seller_id', $ids))
            ->latest()
            ->paginate(15);

        $rating = ProductReview::whereHas('product', fn ($q) => $q->whereIn('seller_id', $ids))
            ->avg('rating');

        $total = ProductReview::whereHas('product', fn ($q) => $q->whereIn('seller_id', $ids))->count();

        $distribution = [
            5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0,
        ];
        foreach (ProductReview::whereHas('product', fn ($q) => $q->whereIn('seller_id', $ids))
            ->selectRaw('rating, COUNT(*) as c')->groupBy('rating')->get() as $row) {
            $distribution[$row->rating] = $row->c;
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
    public function chat(Request $request)
    {
        $seller = $this->seller();
        $me = Auth::id();
        $with = $request->integer('with');

        $conversations = Message::where('sender_id', $me)->orWhere('receiver_id', $me)
            ->get()
            ->groupBy(fn ($m) => $m->sender_id === $me ? $m->receiver_id : $m->sender_id)
            ->map(function ($msgs) use ($me) {
                $last = $msgs->sortByDesc('created_at')->first();
                $otherId = $last->sender_id === $me ? $last->receiver_id : $last->sender_id;
                $other = \App\Models\User::find($otherId);
                return (object) [
                    'user' => $other,
                    'last' => $last,
                    'unread' => $msgs->where('receiver_id', $me)->where('is_read', false)->count(),
                ];
            })
            ->filter(fn ($c) => $c->user && $c->last)
            ->sortByDesc(fn ($c) => $c->last->created_at)
            ->values();

        $thread = collect();
        $otherUser = null;
        if ($with) {
            $otherUser = \App\Models\User::find($with);
            if ($otherUser) {
                $thread = Message::where(function ($q) use ($me, $with) {
                    $q->where('sender_id', $me)->where('receiver_id', $with);
                })->orWhere(function ($q) use ($me, $with) {
                    $q->where('sender_id', $with)->where('receiver_id', $me);
                })->orderBy('created_at')->get();

                Message::where('sender_id', $with)->where('receiver_id', $me)
                    ->where('is_read', false)->update(['is_read' => true]);
            }
        }

        return $this->page(compact('conversations', 'thread', 'otherUser'), 'chat', 'Chat / Messaging');
    }

    public function chatSend(Request $request)
    {
        $data = $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'body' => 'required|string|max:2000',
        ]);

        $message = Message::create([
            'sender_id' => Auth::id(),
            'receiver_id' => $data['receiver_id'],
            'body' => $data['body'],
            'is_read' => false,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect('/seller/chat?with=' . $data['receiver_id']);
    }

    public function chatMessages(Request $request, \App\Models\User $user)
    {
        $me = Auth::id();
        $after = (int) $request->query('after', 0);

        $query = Message::where(function ($q) use ($me, $user) {
            $q->where('sender_id', $me)->where('receiver_id', $user->id);
        })->orWhere(function ($q) use ($me, $user) {
            $q->where('sender_id', $user->id)->where('receiver_id', $me);
        });

        if ($after > 0) {
            $query->where('id', '>', $after);
        }

        $messages = $query->orderBy('created_at')->get();

        Message::where('sender_id', $user->id)->where('receiver_id', $me)
            ->where('is_read', false)->update(['is_read' => true]);

        return response()->json(['messages' => $messages]);
    }

    // ------------------------------------------------------------------ Notifications
    public function notifications()
    {
        $notifications = NotificationsLog::where('user_id', Auth::id())
            ->latest()
            ->paginate(15);

        return $this->page(compact('notifications'), 'notifications', 'Notifications');
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
}