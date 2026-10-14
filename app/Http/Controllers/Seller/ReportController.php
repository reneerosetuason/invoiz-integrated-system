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

        // Delivered orders in range with items + vouchers loaded once;
        // every figure below nets out the seller's absorbed voucher share.
        // Earnings record only SETTLED sales: delivered AND cash handoff
        // confirmed by the seller (payment_status paid).
        $scopedOrders = Order::with(['items', 'orderVouchers'])
            ->whereHas('items', fn ($q) => $q->where('seller_id', $sellerId))
            ->where('status', 'delivered')
            ->where('payment_status', 'paid')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->get();

        $revenue = round($scopedOrders->sum(fn ($o) => $o->sellerNetForSeller($sellerId)), 2);
        $voucherAbsorbed = round($scopedOrders->sum(fn ($o) => $o->voucherDiscountForSeller($sellerId)), 2);

        // Cost of goods sold = current product cost_price x quantity sold.
        $costs = Product::where('seller_id', $sellerId)->pluck('cost_price', 'id');
        $cogs = 0.0;
        $unitsSold = 0;
        foreach ($scopedOrders as $order) {
            foreach ($order->items->where('seller_id', $sellerId) as $item) {
                $unitsSold += (int) $item->quantity;
                $cogs += (int) $item->quantity * (float) ($costs[$item->product_id] ?? 0);
            }
        }
        $cogs = round($cogs, 2);

        $profit = round($revenue - $cogs, 2);

        $orderCount = Order::whereHas('items', fn ($q) => $q->where('seller_id', $sellerId))
            ->where('status', 'delivered')
            ->where('payment_status', 'paid')
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

        // Per-product performance (sales net of absorbed voucher share,
        // apportioned per order by each line's share of the order subtotal).
        $byProduct = [];
        foreach ($scopedOrders as $order) {
            $orderSubtotal = $order->itemsSubtotal();
            $share = $orderSubtotal > 0 ? min(1, $order->voucherDiscountTotal() / $orderSubtotal) : 0;
            $sellerSub = $order->sellerSubtotal($sellerId);
            $extra = $sellerSub > 0
                ? ($order->shippingWaivedForSeller($sellerId) + $order->logisticsFeeForSeller($sellerId)) / $sellerSub
                : 0;
            foreach ($order->items->where('seller_id', $sellerId) as $item) {
                $line = $item->lineTotal ?? ((float) ($item->unit_price ?? $item->price ?? 0)) * ((int) ($item->quantity ?? 0));
                $key = (int) $item->product_id;
                if (! isset($byProduct[$key])) {
                    $byProduct[$key] = ['product_id' => $key, 'product_name' => $item->product_name, 'units' => 0, 'sales' => 0.0, 'cogs' => 0.0];
                }
                $byProduct[$key]['units'] += (int) $item->quantity;
                $byProduct[$key]['sales'] += max(0, $line * (1 - $share) - $line * $extra);
                $byProduct[$key]['cogs'] += (int) $item->quantity * (float) ($costs[$item->product_id] ?? 0);
            }
        }
        $productRows = collect($byProduct)->sortByDesc('sales')->values()
            ->map(fn ($row) => (object) [
                'product_id'   => $row['product_id'],
                'product_name' => $row['product_name'],
                'units'        => (int) $row['units'],
                'sales'        => round((float) $row['sales'], 2),
                'cogs'         => round((float) $row['cogs'], 2),
                'profit'       => round((float) $row['sales'] - (float) $row['cogs'], 2),
            ]);

        // Daily net sales series within the range.
        $dailyNet = [];
        foreach ($scopedOrders as $order) {
            $day = $order->created_at->toDateString();
            $dailyNet[$day] = ($dailyNet[$day] ?? 0) + $order->sellerNetForSeller($sellerId);
        }

        $labels = [];
        $sales = [];
        $period = \Carbon\Carbon::parse($from);
        $end = \Carbon\Carbon::parse($to);
        while ($period->lte($end)) {
            $labels[] = $period->format('M j');
            $sales[] = round((float) ($dailyNet[$period->toDateString()] ?? 0), 2);
            $period->addDay();
        }

        $categories = Category::whereHas('products', function ($q) use ($sellerId) {
            $q->where('seller_id', $sellerId);
        })->with(['products' => function ($q) use ($sellerId) {
            $q->where('seller_id', $sellerId);
        }])->orderBy('name')->get();

        // Net category sales, aggregated from the net per-product rows above.
        $netByProduct = $productRows->keyBy('product_id');
        $categoryBreakdown = $categories->map(function ($category) use ($netByProduct) {
            $sales = 0.0;
            foreach ($category->products as $product) {
                $sales += (float) ($netByProduct[$product->id]->sales ?? 0);
            }

            return (object) [
                'name'     => $category->name,
                'products' => $category->products->count(),
                'sales'    => round($sales, 2),
            ];
        });

        $summary = [
            'revenue'           => $revenue,
            'voucher_absorbed'  => $voucherAbsorbed,
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
                ['Revenue (net of vouchers)', $revenue],
                ['Voucher discounts absorbed', $voucherAbsorbed],
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