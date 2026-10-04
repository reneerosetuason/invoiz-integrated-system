<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $sellerId = auth()->id();

        // Keep order notifications in sync before rendering.
        $this->syncNotifications($sellerId);

        $orders = Order::whereHas('items', fn ($q) => $q->where('seller_id', $sellerId));

        $totals = [
            'orders'        => (clone $orders)->count(),
            'pending'       => (clone $orders)->whereIn('status', ['pending', 'confirmed'])->count(),
            'to_ship'       => (clone $orders)->whereIn('status', ['processing', 'ready_for_delivery'])->count(),
            'delivered'     => (clone $orders)->where('status', 'delivered')->count(),
            'products'      => Product::forSeller($sellerId)->count(),
            'low_stock'     => Product::forSeller($sellerId)->where('status', '!=', 'inactive')->where('stock', '<=', 5)->count(),
            'revenue'       => $this->revenue($sellerId, ['delivered']),
            'revenue_today' => $this->revenue($sellerId, ['delivered'], today()->startOfDay(), today()->endOfDay()),
            'rating'        => round(Product::forSeller($sellerId)->whereNotNull('rating')->avg('rating') ?? 0, 1),
        ];

        // Revenue / orders for the last 30 days (delivered orders only).
        $salesSeries = $this->salesSeries($sellerId, 30);

        // Orders grouped by status (for the donut chart).
        $statusCounts = (clone $orders)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        // Top 5 products by units sold.
        $topProducts = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.seller_id', $sellerId)
            ->whereIn('orders.status', ['delivered', 'out_for_delivery', 'ready_for_delivery'])
            ->select('order_items.product_id', 'order_items.product_name', DB::raw('SUM(order_items.quantity) as units'), DB::raw('SUM(order_items.subtotal) as sales'))
            ->groupBy('order_items.product_id', 'order_items.product_name')
            ->orderByDesc('units')
            ->limit(5)
            ->get();

        $recentOrders = (clone $orders)
            ->with(['buyer', 'items', 'delivery'])
            ->latest()
            ->limit(8)
            ->get();

        $notifications = \App\Models\SellerNotification::forSeller($sellerId)
            ->latest()
            ->limit(6)
            ->get();

        $unreadNotifications = \App\Models\SellerNotification::forSeller($sellerId)
            ->where('is_read', false)
            ->count();

        return view('seller.dashboard', compact(
            'totals',
            'salesSeries',
            'statusCounts',
            'topProducts',
            'recentOrders',
            'notifications',
            'unreadNotifications',
        ));
    }

    private function revenue(int $sellerId, array $statuses, $from = null, $to = null): float
    {
        $query = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.seller_id', $sellerId)
            ->whereIn('orders.status', $statuses);

        if ($from) {
            $query->where('orders.created_at', '>=', $from);
        }

        if ($to) {
            $query->where('orders.created_at', '<=', $to);
        }

        return (float) $query->sum('order_items.subtotal');
    }

    private function salesSeries(int $sellerId, int $days): array
    {
        $labels = [];
        $values = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $day = today()->subDays($i);
            $total = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('order_items.seller_id', $sellerId)
                ->where('orders.status', 'delivered')
                ->whereDate('orders.created_at', $day->toDateString())
                ->sum('order_items.subtotal');

            $labels[] = $day->format('M j');
            $values[] = round((float) $total, 2);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    private function syncNotifications(int $sellerId): void
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
                'body'      => "Order #{$order->id} from {$order->buyer->full_name} needs your attention.",
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
                'body'      => "Order #{$order->id} was confirmed delivered to {$order->buyer->full_name}.",
            ]);
        }

        $reviews = Review::whereHas('product', fn ($q) => $q->where('seller_id', $sellerId))
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
                'body'       => "{$review->buyer->full_name} rated {$review->product->name} {$review->rating}/5.",
            ]);
        }
    }
}