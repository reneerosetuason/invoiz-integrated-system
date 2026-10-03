<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Category;
use App\Models\Seller;
use App\Models\User;
use App\Models\SellerPayout;
use App\Models\PaymentTransaction;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private function range(Request $request, $query)
    {
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }
        return $query;
    }

    private function periodKey($date, string $period): string
    {
        return match ($period) {
            'weekly' => 'W' . $date->format('W') . ' ' . $date->format('Y'),
            'monthly' => $date->format('M Y'),
            'yearly' => $date->format('Y'),
            default => $date->format('M d, Y'),
        };
    }

    private function periodRange(Request $request, string $period): ?array
    {
        if ($request->filled('from') || $request->filled('to')) return null;
        return match ($period) {
            'daily' => [now()->startOfDay(), now()->endOfDay()],
            'weekly' => [now()->subDays(6)->startOfDay(), now()->endOfDay()],
            'monthly' => [now()->subDays(29)->startOfDay(), now()->endOfDay()],
            'yearly' => [now()->subDays(364)->startOfDay(), now()->endOfDay()],
            default => null,
        };
    }

    public function index(Request $request)
    {
        $type = $request->query('type', 'sales');
        $period = $request->query('period', 'monthly');
        $types = ['sales', 'customers', 'sellers', 'products', 'financial'];
        if (! in_array($type, $types)) $type = 'sales';

        $data = [
            'type' => $type,
            'period' => $period,
            'types' => $types,
        ];

        if ($type === 'sales') {
            $data += $this->salesData($request, $period);
        } elseif ($type === 'customers') {
            $data += $this->customersData($request);
        } elseif ($type === 'sellers') {
            $data += $this->sellersData($request);
        } elseif ($type === 'products') {
            $data += $this->productsData($request);
        } else {
            $data += $this->financialData($request);
        }

        return view('admin.reports', $data);
    }

    private function salesData(Request $request, string $period): array
    {
        $base = $this->range($request, Order::where('payment_status', 'paid'));
        if ($range = $this->periodRange($request, $period)) {
            $base->whereBetween('created_at', $range);
        }
        $orders = $base->get();
        $gross = $orders->sum('total');

        $grouped = $orders->groupBy(fn ($o) => $this->periodKey($o->created_at, $period))
            ->map(fn ($g, $key) => (object) [
                'label' => $key,
                'orders' => $g->count(),
                'sales' => $g->sum('total'),
                'commission' => $g->sum('commission_amount'),
            ])
            ->sortByDesc('sales')
            ->values();

        $orderIds = $orders->pluck('id');
        if ($orderIds->isEmpty()) {
            $byCategory = collect();
            $bySeller = collect();
            $byProduct = collect();
        } else {
            $byCategory = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->join('products', 'products.id', '=', 'order_items.product_id')
                ->join('categories', 'categories.id', '=', 'products.category_id')
                ->whereIn('orders.id', $orderIds)
                ->selectRaw('categories.name, SUM(order_items.total_price) as sales, SUM(order_items.quantity) as units')
                ->groupBy('categories.name')->orderByDesc('sales')->get();

            $bySeller = DB::table('orders')
                ->join('sellers', 'orders.seller_id', '=', 'sellers.id')
                ->whereIn('orders.id', $orderIds)
                ->selectRaw('sellers.store_name, COUNT(*) as orders, SUM(orders.total) as sales, SUM(orders.commission_amount) as commission')
                ->groupBy('sellers.store_name')->orderByDesc('sales')->get();

            $byProduct = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->join('products', 'products.id', '=', 'order_items.product_id')
                ->whereIn('orders.id', $orderIds)
                ->selectRaw('products.name, SUM(order_items.quantity) as units, SUM(order_items.total_price) as sales')
                ->groupBy('products.name')->orderByDesc('sales')->limit(15)->get();
        }

        $refunds = PaymentTransaction::whereIn('status', ['refunded', 'partially_refunded'])->sum('refunded_amount');

        return [
            'cards' => [
                'Gross Sales' => Money::format($gross),
                'Paid Orders' => number_format($orders->count()),
                'Avg. Order Value' => Money::format($orders->count() ? $gross / $orders->count() : 0),
                'Refunds' => Money::format($refunds),
            ],
            'grouped' => $grouped,
            'byCategory' => $byCategory,
            'bySeller' => $bySeller,
            'byProduct' => $byProduct,
        ];
    }

    private function customersData(Request $request): array
    {
        $customers = User::where('role', 'buyer')
            ->withCount(['orders' => fn ($q) => $q->where('payment_status', 'paid')])
            ->get()
            ->map(function ($u) {
                $paid = $u->orders()->where('payment_status', 'paid')->get();
                $u->total_spent = $paid->sum('total');
                $u->avg_order = $paid->count() ? $paid->sum('total') / $paid->count() : 0;
                $u->last_order = $paid->sortByDesc('created_at')->first()?->created_at;
                return $u;
            })
            ->sortByDesc('total_spent')
            ->values();

        $active = $customers->where('orders_count', '>', 0)->count();

        return [
            'cards' => [
                'Total Customers' => number_format($customers->count()),
                'Active Buyers' => number_format($active),
                'Total Revenue' => Money::format($customers->sum('total_spent')),
                'Avg. Spend / Customer' => Money::format($customers->count() ? $customers->sum('total_spent') / $customers->count() : 0),
            ],
            'customers' => $customers,
        ];
    }

    private function sellersData(Request $request): array
    {
        $sellers = Seller::with('user')
            ->withCount(['products'])
            ->get()
            ->map(function ($s) {
                $paid = Order::where('seller_id', $s->id)->where('payment_status', 'paid');
                $s->paid_orders = (clone $paid)->count();
                $s->revenue = (clone $paid)->sum('total');
                $s->commission = (clone $paid)->sum('commission_amount');
                $s->avg_order = $s->paid_orders ? $s->revenue / $s->paid_orders : 0;
                $s->payouts_pending = SellerPayout::where('seller_id', $s->id)->whereIn('status', ['pending', 'processing'])->sum('amount');
                $s->payouts_paid = SellerPayout::where('seller_id', $s->id)->where('status', 'paid')->sum('amount');
                return $s;
            })
            ->sortByDesc('revenue')
            ->values();

        return [
            'cards' => [
                'Total Sellers' => number_format($sellers->count()),
                'Gross Seller Revenue' => Money::format($sellers->sum('revenue')),
                'Total Commission' => Money::format($sellers->sum('commission')),
                'Best Store' => $sellers->first()?->store_name ?? '—',
            ],
            'sellers' => $sellers,
        ];
    }

    private function productsData(Request $request): array
    {
        $best = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.payment_status', 'paid')
            ->selectRaw('products.name, SUM(order_items.quantity) as units, SUM(order_items.total_price) as sales, MAX(products.views_count) as views, MAX(products.cart_additions) as carts')
            ->groupBy('products.name')->orderByDesc('units')->limit(10)->get();

        $soldProductIds = DB::table('order_items')->select('product_id')->distinct()->pluck('product_id');
        $low = Product::whereNotIn('id', $soldProductIds)
            ->orWhereIn('id', DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.payment_status', 'paid')
                ->selectRaw('order_items.product_id')
                ->groupBy('order_items.product_id')
                ->havingRaw('SUM(order_items.quantity) <= 2')
                ->pluck('order_items.product_id'))
            ->limit(10)->get();

        $stock = Product::leftJoin('product_variants', 'product_variants.product_id', '=', 'products.id')
            ->selectRaw('products.name,
                COALESCE(SUM(product_variants.stock), 0) as stock')
            ->groupBy('products.name')
            ->orderBy('stock')
            ->limit(15)
            ->get()
            ->map(function ($p) {
                $p->stock_status = $p->stock <= 0 ? 'Out of Stock' : ($p->stock <= 10 ? 'Low Stock' : 'In Stock');
                return $p;
            });

        $totalViews = Product::sum('views_count');
        $totalCarts = Product::sum('cart_additions');

        return [
            'cards' => [
                'Product Views' => number_format($totalViews),
                'Cart Additions' => number_format($totalCarts),
                'View → Cart Rate' => number_format($totalViews ? ($totalCarts / $totalViews) * 100 : 0, 1) . '%',
                'Products Sold (units)' => number_format(DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')->where('orders.payment_status', 'paid')->sum('order_items.quantity')),
            ],
            'best' => $best,
            'low' => $low,
            'stock' => $stock,
        ];
    }

    private function financialData(Request $request): array
    {
        $orders = $this->range($request, Order::where('payment_status', 'paid'))->get();

        $gross = $orders->sum('total');
        $commissions = $orders->sum('commission_amount');
        $deliveryFees = $orders->sum('shipping_fee');
        $refunds = PaymentTransaction::whereIn('status', ['refunded', 'partially_refunded'])->sum('refunded_amount');
        $payoutsPaid = SellerPayout::where('status', 'paid')->sum('amount');
        $payoutsPending = SellerPayout::whereIn('status', ['pending', 'processing'])->sum('amount');
        $net = $gross - $refunds;

        $breakdown = $orders->groupBy(fn ($o) => $o->created_at->format('M Y'))
            ->map(fn ($g, $key) => (object) [
                'label' => $key,
                'orders' => $g->count(),
                'gross' => $g->sum('total'),
                'commission' => $g->sum('commission_amount'),
                'delivery' => $g->sum('shipping_fee'),
                'refunds' => 0,
            ])
            ->values();

        $driver = DB::getDriverName();
        $dateExpr = $driver === 'sqlite' ? "strftime('%m %Y', created_at)" : "DATE_FORMAT(created_at, '%m %Y')";
        $refundsByMonth = DB::table('payment_transactions')
            ->whereIn('status', ['refunded', 'partially_refunded'])
            ->selectRaw("$dateExpr as label, SUM(refunded_amount) as refunds")
            ->groupBy('label')
            ->pluck('refunds', 'label');

        $monthMap = [];
        foreach ($refundsByMonth as $label => $amount) {
            try {
                $monthMap[\Carbon\Carbon::createFromFormat('m Y', $label)->format('M Y')] = (float) $amount;
            } catch (\Exception $e) {
                continue;
            }
        }

        foreach ($breakdown as $b) {
            $b->refunds = $monthMap[$b->label] ?? 0;
        }

        return [
            'cards' => [
                'Gross Sales' => Money::format($gross),
                'Net Sales' => Money::format($net),
                'Commissions' => Money::format($commissions),
                'Refunds' => Money::format($refunds),
                'Seller Payouts (Paid)' => Money::format($payoutsPaid),
                'Payouts Pending' => Money::format($payoutsPending),
                'Delivery Fees' => Money::format($deliveryFees),
                'Paid Orders' => number_format($orders->count()),
            ],
            'breakdown' => $breakdown,
        ];
    }

    // ---------------------------------------------------------------------
    // Export: CSV / Excel / PDF (print)
    // ---------------------------------------------------------------------
    private function buildReport(Request $request): array
    {
        $type = $request->query('type', 'sales');
        $period = $request->query('period', 'monthly');

        $meta = 'Filters: ' . ($type === 'sales' ? 'Group by ' . ucfirst($period) . ' · ' : '')
            . 'From: ' . ($request->filled('from') ? $request->from : 'start')
            . ' · To: ' . ($request->filled('to') ? $request->to : 'today')
            . ' · Generated: ' . now()->format('M d, Y g:i A');

        $salesDataCache = $type === 'sales' ? $this->salesData($request, $period) : null;

        $report = match ($type) {
            'customers' => [
                'title' => 'Customer Report',
                'headings' => ['Customer', 'Email', 'Registered', 'Paid Orders', 'Total Spent', 'Avg. Order', 'Last Order'],
                'rows' => $this->customersData($request)['customers']
                    ->map(fn ($u) => [
                        $u->name,
                        $u->email,
                        $u->created_at->format('M d, Y'),
                        $u->orders_count,
                        number_format($u->total_spent, 2),
                        number_format($u->avg_order, 2),
                        optional($u->last_order)->format('M d, Y') ?? '-',
                    ])
                    ->toArray(),
                'totals' => [3 => $this->customersData($request)['customers']->sum('orders_count'), 4 => number_format($this->customersData($request)['customers']->sum('total_spent'), 2)],
            ],
            'sellers' => [
                'title' => 'Seller Report',
                'headings' => ['Store', 'Owner Email', 'Products', 'Paid Orders', 'Revenue', 'Commission', 'Pending Payout', 'Paid Payout', 'Avg. Order'],
                'rows' => $this->sellersData($request)['sellers']
                    ->map(fn ($s) => [
                        $s->store_name,
                        $s->user->email ?? '',
                        $s->products_count,
                        $s->paid_orders,
                        number_format($s->revenue, 2),
                        number_format($s->commission, 2),
                        number_format($s->payouts_pending, 2),
                        number_format($s->payouts_paid, 2),
                        number_format($s->avg_order, 2),
                    ])
                    ->toArray(),
                'totals' => [
                    2 => $this->sellersData($request)['sellers']->sum('products_count'),
                    3 => $this->sellersData($request)['sellers']->sum('paid_orders'),
                    4 => number_format($this->sellersData($request)['sellers']->sum('revenue'), 2),
                    5 => number_format($this->sellersData($request)['sellers']->sum('commission'), 2),
                ],
            ],
            'products' => [
                'title' => 'Product Report (Best Sellers)',
                'headings' => ['Product', 'Units Sold', 'Sales', 'Product Views', 'Cart Additions'],
                'rows' => $this->productsData($request)['best']
                    ->map(fn ($p) => [$p->name, $p->units, number_format($p->sales, 2), number_format($p->views), number_format($p->carts)])
                    ->toArray(),
                'totals' => [
                    1 => $this->productsData($request)['best']->sum('units'),
                    2 => number_format($this->productsData($request)['best']->sum('sales'), 2),
                ],
            ],
            'financial' => [
                'title' => 'Financial Report',
                'headings' => ['Period', 'Paid Orders', 'Gross Sales', 'Commission', 'Delivery Fees', 'Refunds'],
                'rows' => $this->financialData($request)['breakdown']
                    ->map(fn ($b) => [
                        $b->label,
                        $b->orders,
                        number_format($b->gross, 2),
                        number_format($b->commission, 2),
                        number_format($b->delivery, 2),
                        number_format($b->refunds ?? 0, 2),
                    ])
                    ->toArray(),
                'totals' => [
                    1 => $this->financialData($request)['breakdown']->sum('orders'),
                    2 => number_format($this->financialData($request)['breakdown']->sum('gross'), 2),
                    3 => number_format($this->financialData($request)['breakdown']->sum('commission'), 2),
                    4 => number_format($this->financialData($request)['breakdown']->sum('delivery'), 2),
                    5 => number_format($this->financialData($request)['breakdown']->sum('refunds'), 2),
                ],
            ],
            default => [
                'title' => 'Sales Report (' . ucfirst($period) . ')',
                'headings' => ['Period', 'Paid Orders', 'Gross Sales', 'Avg. Order', 'Commission'],
                'rows' => collect($salesDataCache['grouped'])
                    ->map(fn ($g) => [
                        $g->label,
                        $g->orders,
                        number_format($g->sales, 2),
                        number_format($g->orders ? $g->sales / $g->orders : 0, 2),
                        number_format($g->commission, 2),
                    ])
                    ->toArray(),
                'totals' => [
                    1 => collect($salesDataCache['grouped'])->sum('orders'),
                    2 => number_format(collect($salesDataCache['grouped'])->sum('sales'), 2),
                    4 => number_format(collect($salesDataCache['grouped'])->sum('commission'), 2),
                ],
                'cards' => $salesDataCache['cards'] ?? [],
                'byCategory' => $salesDataCache['byCategory'] ?? collect(),
                'byProduct' => $salesDataCache['byProduct'] ?? collect(),
            ],
        };

        $report['meta'] = $meta;
        $report['type'] = $type;

        return $report;
    }

    public function export(Request $request)
    {
        $format = $request->query('format', 'csv');
        $report = $this->buildReport($request);
        $title = $report['title'];
        $headings = $report['headings'];
        $rows = $report['rows'];
        $filename = str_replace(' ', '_', strtolower($title)) . '_' . now()->format('Ymd_His');

        if ($format === 'excel') {
            $html = '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8">';
            $html .= '<style>table{border-collapse:collapse;font-family:Arial;font-size:11px;}th{background:#16697A;color:#fff;padding:3px 6px;border:.5pt solid #999;text-align:left;}td{padding:2px 6px;border:.5pt solid #CCC;}caption{font-size:13px;font-weight:bold;text-align:left;}.meta{font-size:10px;color:#666;}.total td{font-weight:bold;background:#EAF4F3;}</style>';
            $html .= '</head><body>';
            $html .= '<table cellspacing="0"><caption>' . e($title) . '</caption>';
            $html .= '<tr><td colspan="' . count($headings) . '" class="meta">' . e($report['meta']) . '</td></tr>';
            $html .= '<tr>';
            foreach ($headings as $h) $html .= '<th>' . e($h) . '</th>';
            $html .= '</tr>';
            foreach ($rows as $row) {
                $html .= '<tr>' . implode('', array_map(fn ($c) => '<td>' . e((string) $c) . '</td>', $row)) . '</tr>';
            }
            if (! empty($report['totals'])) {
                $html .= '<tr class="total"><td>TOTAL</td>';
                foreach ($headings as $i => $h) {
                    if ($i === 0) continue;
                    $html .= '<td>' . e((string) ($report['totals'][$i] ?? '')) . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</table></body></html>';

            return response($html)
                ->header('Content-Type', 'application/vnd.ms-excel')
                ->header('Content-Disposition', "attachment; filename=\"{$filename}.xls\"");
        }

        if ($format === 'pdf') {
            session()->flash('print_report', ['title' => $title, 'headings' => $headings, 'rows' => $rows]);
            return redirect()->route('admin.reports.print', $request->query());
        }

        return new StreamedResponse(function () use ($title, $headings, $rows, $report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [$title]);
            fputcsv($out, [$report['meta']]);
            fputcsv($out, $headings);
            foreach ($rows as $row) fputcsv($out, $row);
            if (! empty($report['totals'])) {
                $totalRow = ['TOTAL'];
                foreach (range(1, count($headings) - 1) as $i) {
                    $totalRow[] = $report['totals'][$i] ?? '';
                }
                fputcsv($out, $totalRow);
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
        ]);
    }

    public function printView(Request $request)
    {
        $report = $this->buildReport($request);

        return view('admin.report-print', $report);
    }

    // Legacy commission report (kept for compatibility)
    public function commission(Request $request)
    {
        return redirect()->route('admin.commission', $request->query());
    }
}
