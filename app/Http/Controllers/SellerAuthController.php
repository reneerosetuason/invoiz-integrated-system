<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SellerAuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            $seller = Seller::where('user_id', Auth::id())->approved()->first();
            if ($seller) {
                session()->put('seller_id', $seller->id);
                return redirect('/seller/dashboard');
            }
        }
        return view('seller.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::user();

            $seller = Seller::where('user_id', $user->id)
                ->approved()
                ->first();

            if (! $seller) {
                Auth::logout();
                return back()->withErrors(['email' => 'No approved seller account is linked to this email.']);
            }

            $request->session()->put('seller_id', $seller->id);
            $request->session()->regenerate();

            return redirect()->intended('/seller/dashboard');
        }

        return back()->withErrors(['email' => 'Invalid credentials.']);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }

    public function dashboard(Request $request)
    {
        $seller = Seller::find($request->session()->get('seller_id'));

        if (! $seller) {
            Auth::logout();
            return redirect('/seller/login');
        }

        // Keep order notifications in sync before rendering (same as original).
        $this->syncSellerNotifications(Auth::id());

        // Seller catalog spans BOTH id conventions in the shared DB:
        // products/order_items use the seller's USER id, admin orders use SELLERS id.
        $catalogIds = $seller->catalogIds();
        $orderIds = \App\Models\OrderItem::whereIn('seller_id', $catalogIds)->pluck('order_id')->unique()->all();
        $orders = Order::where(function ($q) use ($catalogIds, $orderIds) {
            $q->whereIn('seller_id', $catalogIds);
            if (! empty($orderIds)) $q->orWhereIn('id', $orderIds);
        });

        $totalOrders = (clone $orders)->count();
        $delivered = (clone $orders)->where('status', 'delivered')->count();
        $revenue = (clone $orders)->where('status', 'delivered')->sum('total');
        $todayRevenue = (clone $orders)->where('status', 'delivered')->whereDate('created_at', today())->sum('total');
        $toAction = (clone $orders)->whereIn('status', ['new', 'pending'])->count();
        $toShip = (clone $orders)->where('status', 'processing')->count();

        $productsCount = Product::whereIn('seller_id', $catalogIds)->count();
        $lowStock = Inventory::where('seller_id', $seller->id)->where('quantity', '<=', 5)->count();

        $recentOrders = Order::with('buyer')
            ->where(function ($q) use ($catalogIds, $orderIds) {
                $q->whereIn('seller_id', $catalogIds);
                if (! empty($orderIds)) $q->orWhereIn('id', $orderIds);
            })
            ->latest()
            ->take(5)
            ->get();

        // 30-day sales line chart (delivered revenue per day)
        $chart = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $sum = (clone $orders)
                ->where('status', 'delivered')
                ->whereDate('created_at', $day->toDateString())
                ->sum('total');
            $chart[] = [
                'label' => $day->format('M d'),
                'value' => round($sum, 2),
            ];
        }

        // Orders by status (pie) — Pending + Processing lead
        $statusMap = [
            'pending' => ['label' => 'Pending', 'color' => '#DCC08D', 'statuses' => ['new', 'pending']],
            'processing' => ['label' => 'Processing', 'color' => '#A3BED8', 'statuses' => ['processing']],
            'delivered' => ['label' => 'Delivered', 'color' => '#9DBFA4', 'statuses' => ['delivered']],
            'cancelled' => ['label' => 'Cancelled', 'color' => '#D6A5A0', 'statuses' => ['cancelled']],
            'shipped' => ['label' => 'Shipped', 'color' => '#B5AACB', 'statuses' => ['shipped']],
        ];

        $statusStats = [];
        foreach ($statusMap as $key => $def) {
            $count = (clone $orders)->whereIn('status', $def['statuses'])->count();
            if ($count > 0) {
                $statusStats[] = [
                    'label' => $def['label'],
                    'status' => $key,
                    'count' => $count,
                    'color' => $def['color'],
                    'percent' => $totalOrders > 0 ? round(($count / $totalOrders) * 100) : 0,
                ];
            }
        }

        // Top products by units sold (both seller-id conventions)
        $topProducts = OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where(function ($q) use ($seller, $catalogIds) {
                $q->whereIn('orders.seller_id', $catalogIds)
                  ->orWhereIn('order_items.seller_id', $catalogIds);
            })
            ->selectRaw('products.id, products.name, SUM(order_items.quantity) as units')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('units')
            ->take(5)
            ->get();

        $rating = (float) \App\Models\ProductReview::whereHas('product', fn ($q) => $q->whereIn('seller_id', $catalogIds))
            ->avg('rating') ?? 0;

        return view('seller.dashboard', compact(
            'seller',
            'totalOrders',
            'delivered',
            'revenue',
            'todayRevenue',
            'toAction',
            'toShip',
            'productsCount',
            'lowStock',
            'rating',
            'recentOrders',
            'chart',
            'statusStats',
            'topProducts'
        ))->with('active', 'dashboard')->with('title', 'Dashboard');
    }

    /**
     * Create notifications for new / delivered orders and new visible
     * reviews that don't have one yet (same as the original seller app).
     */
    protected function syncSellerNotifications(int $sellerId): void
    {
        $newOrders = Order::whereHas('items', fn ($q) => $q->where('seller_id', $sellerId))
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereDoesntHave('sellerNotifications', fn ($q) => $q->where('seller_id', $sellerId)->where('type', 'new_order'))
            ->limit(50)
            ->get();

        foreach ($newOrders as $order) {
            \App\Models\SellerNotification::create([
                'seller_id' => $sellerId,
                'order_id'  => $order->id,
                'type'      => 'new_order',
                'title'     => 'New order received',
                'body'      => "Order #{$order->id} needs your attention.",
            ]);
        }

        $delivered = Order::whereHas('items', fn ($q) => $q->where('seller_id', $sellerId))
            ->where('status', 'delivered')
            ->whereDoesntHave('sellerNotifications', fn ($q) => $q->where('seller_id', $sellerId)->where('type', 'delivered'))
            ->limit(50)
            ->get();

        foreach ($delivered as $order) {
            \App\Models\SellerNotification::create([
                'seller_id' => $sellerId,
                'order_id'  => $order->id,
                'type'      => 'delivered',
                'title'     => 'Order delivered',
                'body'      => "Order #{$order->id} was confirmed delivered.",
            ]);
        }

        $reviews = \App\Models\Review::whereHas('product', fn ($q) => $q->where('seller_id', $sellerId))
            ->where('status', 'visible')
            ->whereDoesntHave('product.sellerNotifications', fn ($q) => $q->where('seller_id', $sellerId)->where('type', 'review'))
            ->limit(50)
            ->get();

        foreach ($reviews as $review) {
            \App\Models\SellerNotification::create([
                'seller_id'  => $sellerId,
                'order_id'   => $review->order_id,
                'product_id' => $review->product_id,
                'type'       => 'review',
                'title'      => 'New customer review',
                'body'       => "Rated {$review->rating}/5.",
            ]);
        }
    }
}