<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function __invoke(Request $request)
    {
        $sellerId = auth()->id();

        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        $query = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.seller_id', $sellerId)
            ->where('orders.status', 'delivered')
            ->whereDate('orders.created_at', '>=', $from)
            ->whereDate('orders.created_at', '<=', $to);

        $revenue = (float) $query->sum('order_items.subtotal');

        // Cost of goods sold = current product cost_price x quantity sold.
        $cogs = (float) $query
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->sum(DB::raw('order_items.quantity * COALESCE(products.cost_price, 0)'));

        $profit = round($revenue - $cogs, 2);
        $unitsSold = (int) $query->sum('order_items.quantity');

        $orderCount = Order::whereHas('items', fn ($q) => $q->where('seller_id', $sellerId))
            ->where('status', 'delivered')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->count();

        $avgOrderValue = $orderCount > 0 ? round($revenue / $orderCount, 2) : 0;

        $allOrders = Order::whereHas('items', fn ($q) => $q->where('seller_id', $sellerId))
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to);
        $cancelledCount = (clone $allOrders)->where('status', 'cancelled')->count();
        $totalCount = (clone $allOrders)->count();
        $cancellationRate = $totalCount > 0 ? round($cancelledCount / $totalCount * 100, 1) : 0;

        // Per-product performance.
        $productRows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->where('order_items.seller_id', $sellerId)
            ->where('orders.status', 'delivered')
            ->whereDate('orders.created_at', '>=', $from)
            ->whereDate('orders.created_at', '<=', $to)
            ->select(
                'order_items.product_id',
                'order_items.product_name',
                DB::raw('SUM(order_items.quantity) as units'),
                DB::raw('SUM(order_items.subtotal) as sales'),
                DB::raw('SUM(order_items.quantity * COALESCE(products.cost_price, 0)) as cogs')
            )
            ->groupBy('order_items.product_id', 'order_items.product_name')
            ->orderByDesc('sales')
            ->get()
            ->map(fn ($row) => (object) [
                'product_id'   => $row->product_id,
                'product_name' => $row->product_name,
                'units'        => (int) $row->units,
                'sales'        => (float) $row->sales,
                'cogs'         => (float) $row->cogs,
                'profit'       => round((float) $row->sales - (float) $row->cogs, 2),
            ]);

        // Daily sales series within the range.
        $daily = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.seller_id', $sellerId)
            ->where('orders.status', 'delivered')
            ->whereDate('orders.created_at', '>=', $from)
            ->whereDate('orders.created_at', '<=', $to)
            ->select(DB::raw('DATE(orders.created_at) as day'), DB::raw('SUM(order_items.subtotal) as sales'))
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $labels = [];
        $sales = [];
        $period = \Carbon\Carbon::parse($from);
        $end = \Carbon\Carbon::parse($to);
        while ($period->lte($end)) {
            $labels[] = $period->format('M j');
            $sales[] = round((float) ($daily[$period->toDateString()]->sales ?? 0), 2);
            $period->addDay();
        }

        $categories = Category::whereHas('products', function ($q) use ($sellerId) {
            $q->where('seller_id', $sellerId);
        })->with(['products' => function ($q) use ($sellerId) {
            $q->where('seller_id', $sellerId);
        }])->orderBy('name')->get();

        $categoryBreakdown = $categories->map(function ($category) use ($sellerId, $from, $to) {
            $sales = 0;
            foreach ($category->products as $product) {
                $sales += DB::table('order_items')
                    ->join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->where('order_items.product_id', $product->id)
                    ->where('order_items.seller_id', $sellerId)
                    ->where('orders.status', 'delivered')
                    ->whereDate('orders.created_at', '>=', $from)
                    ->whereDate('orders.created_at', '<=', $to)
                    ->sum('order_items.subtotal');
            }

            return (object) [
                'name'     => $category->name,
                'products' => $category->products->count(),
                'sales'    => (float) $sales,
            ];
        });

        $summary = [
            'revenue'           => $revenue,
            'cogs'              => $cogs,
            'profit'            => $profit,
            'profit_margin'     => $revenue > 0 ? round($profit / $revenue * 100, 1) : 0,
            'units_sold'        => $unitsSold,
            'orders'            => $orderCount,
            'avg_order_value'   => $avgOrderValue,
            'cancellation_rate' => $cancellationRate,
        ];

        // CSV export.
        if ($request->boolean('export')) {
            $headers = [
                'Content-Type'        => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="invoiz-seller-report-'.$from.'-to-'.$to.'.csv"',
            ];

            $lines = [
                ['Invoiz Seller Report'],
                ['From', $from],
                ['To', $to],
                [],
                ['Metric', 'Value'],
                ['Revenue', $revenue],
                ['Cost of Goods Sold', $cogs],
                ['Profit', $profit],
                ['Profit Margin %', $summary['profit_margin']],
                ['Units Sold', $unitsSold],
                ['Orders', $orderCount],
                ['Avg Order Value', $avgOrderValue],
                ['Cancellation Rate %', $cancellationRate],
                [],
                ['Product', 'Units Sold', 'Sales', 'COGS', 'Profit'],
            ];

            foreach ($productRows as $row) {
                $lines[] = [$row->product_name, $row->units, $row->sales, $row->cogs, $row->profit];
            }

            $content = '';
            foreach ($lines as $line) {
                $content .= implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', (string) $v).'"', $line))."\n";
            }

            return response($content, 200, $headers);
        }

        return view('seller.reports.index', compact(
            'summary',
            'from',
            'to',
            'productRows',
            'labels',
            'sales',
            'categoryBreakdown',
        ));
    }
}