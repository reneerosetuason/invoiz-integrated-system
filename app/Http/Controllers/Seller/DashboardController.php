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
            'low_stock'     => Product::forSeller($sellerId)->where('status', '!=', 'inactive')->whereRaw('stock <= COALESCE(low_stock_threshold, 5)')->count(),
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

        // Top 5 products by units sold (sales shown net of absorbed vouchers).
        $topStatuses = ['delivered', 'out_for_delivery', 'ready_for_delivery'];
        $topProducts = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.seller_id', $sellerId)
            ->whereIn('orders.status', $topStatuses)
            ->select('order_items.product_id', 'order_items.product_name', DB::raw('SUM(order_items.quantity) as units'), DB::raw('SUM(order_items.subtotal) as sales'))
            ->groupBy('order_items.product_id', 'order_items.product_name')
            ->orderByDesc('units')
            ->limit(5)
            ->get()
            ->map(function ($row) use ($sellerId, $topStatuses) {
                $row->sales = $this->productNetSales($sellerId, (int) $row->product_id, $topStatuses);
                return $row;
            });

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

    /**
     * Net merchandise earnings: seller subtotal minus the seller's
     * pro-rata share of any voucher discounts (the seller absorbs them;
     * rider pay is computed separately and never touched).
     */
    private function revenue(int $sellerId, array $statuses, $from = null, $to = null): float
    {
        $orders = Order::with(['items', 'orderVouchers'])
            ->whereHas('items', fn ($q) => $q->where('seller_id', $sellerId))
            ->whereIn('status', $statuses)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->get();

        return round($orders->sum(fn ($o) => $o->sellerNetForSeller($sellerId)), 2);
    }

    private function salesSeries(int $sellerId, int $days): array
    {
        $from = today()->subDays($days - 1)->startOfDay();
        $orders = Order::with(['items', 'orderVouchers'])
            ->whereHas('items', fn ($q) => $q->where('seller_id', $sellerId))
            ->where('status', 'delivered')
            ->where('created_at', '>=', $from)
            ->get();

        $byDay = [];
        foreach ($orders as $order) {
            $key = $order->created_at->toDateString();
            $byDay[$key] = ($byDay[$key] ?? 0) + $order->sellerNetForSeller($sellerId);
        }

        $labels = [];
        $values = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = today()->subDays($i);
            $labels[] = $day->format('M j');
            $values[] = round((float) ($byDay[$day->toDateString()] ?? 0), 2);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /** Net sales for one product (voucher share apportioned per order). */
    private function productNetSales(int $sellerId, int $productId, array $statuses): float
    {
        $orders = Order::with(['items', 'orderVouchers'])
            ->whereHas('items', fn ($q) => $q->where('seller_id', $sellerId)->where('product_id', $productId))
            ->whereIn('status', $statuses)
            ->get();

        $net = 0.0;
        foreach ($orders as $order) {
            $orderSubtotal = $order->itemsSubtotal();
            if ($orderSubtotal <= 0) {
                continue;
            }
            $share = min(1, $order->voucherDiscountTotal() / $orderSubtotal);
            foreach ($order->items->where('seller_id', $sellerId)->where('product_id', $productId) as $item) {
                $line = $item->lineTotal ?? ((float) ($item->unit_price ?? $item->price ?? 0)) * ((int) ($item->quantity ?? 0));
                $net += $line * (1 - $share);
            }
        }

        return round($net, 2);
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

        // Backstop: anything already at/below its alert level gets one
        // unread low-stock alert (deduped inside checkLowStock).
        $lowProducts = Product::forSeller($sellerId)->where('status', '!=', 'inactive')
            ->whereRaw('stock <= COALESCE(low_stock_threshold, 5)')
            ->limit(50)->get(['id']);
        foreach ($lowProducts as $lowProduct) {
            \App\Support\Stock::checkLowStock($lowProduct->id);
        }
    }
}