<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Seller;
use App\Models\Category;
use App\Models\SellerPayout;
use App\Models\CommissionRate;
use App\Support\EscrowEarnings;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CommissionController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::where('payment_status', 'paid');

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $totalSales = (clone $query)->sum('sub_total');
        $totalCommission = (clone $query)->sum('commission_amount');

        // ------------------------------------------------------------------
        // Commission per seller (+ payout status)
        // ------------------------------------------------------------------
        // NOTE: orders.seller_id is NOT set by checkout, so joining on it
        // always yields zero rows. Attribute via order_items.seller_id
        // instead, using the canonical per-seller split (gross sales,
        // commission on net merch, absorbed voucher share).
        $paidForSellers = Order::with(['items', 'orderVouchers.voucher'])
            ->where('payment_status', 'paid')
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->to))
            ->get();
        $perSeller = [];
        foreach ($paidForSellers as $po) {
            foreach (\App\Support\Payments::splitForSellers($po) as $sid => $share) {
                $sid = (int) $sid;
                if (! isset($perSeller[$sid])) {
                    $perSeller[$sid] = ['sales' => 0.0, 'commission' => 0.0, 'absorbed' => 0.0];
                }
                $perSeller[$sid]['sales'] += $share['merch'] + $po->voucherDiscountForSeller($sid);
                $perSeller[$sid]['commission'] += $share['commission'];
                $perSeller[$sid]['absorbed'] += $share['waived'] + $po->voucherDiscountForSeller($sid);
            }
        }
        $sellerCommissions = collect($perSeller)
            ->map(function ($agg, $sid) {
                $sellerRow = Seller::where('user_id', $sid)->first();
                $catalogIds = $sellerRow ? $sellerRow->catalogIds() : [$sid];
                return (object) [
                    'seller_id'       => $sellerRow ? $sellerRow->id : $sid,
                    'store_name'      => $sellerRow ? $sellerRow->display_name : Seller::nameFor($sid),
                    'total_sales'     => round($agg['sales'], 2),
                    'total_commission'=> round($agg['commission'], 2),
                    'absorbed'        => round($agg['absorbed'], 2),
                    'payouts_pending' => (float) SellerPayout::whereIn('seller_id', $catalogIds)
                        ->whereIn('status', ['pending', 'processing'])->sum('amount'),
                    'payouts_paid'    => (float) SellerPayout::whereIn('seller_id', $catalogIds)
                        ->where('status', 'paid')->sum('amount'),
                ];
            })
            ->sortByDesc('total_commission')
            ->values();
        // In-memory aggregate: slice the current page manually. Totals and
        // cards above always use the full set.
        $sellerCommissions = $this->paginateCollection($sellerCommissions, $request, 10, 'sellers_page');

        // ------------------------------------------------------------------
        // Commission per order (recent paid orders)
        // ------------------------------------------------------------------
        $orderCommissions = (clone $query)->with('seller', 'buyer')
            ->orderByDesc('created_at')
            ->paginate(10, ['*'], 'orders_page');

        // ------------------------------------------------------------------
        // Commission per category (uses per-category rates)
        // ------------------------------------------------------------------
        $categoryCommissions = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('commission_rates', 'commission_rates.category_id', '=', 'categories.id')
            ->where('orders.payment_status', 'paid')
            ->selectRaw("categories.id, categories.name,
                         SUM(order_items.total_price) as category_sales,
                         COUNT(DISTINCT orders.id) as orders_count,
                         COALESCE(commission_rates.rate, 10.00) as rate,
                         SUM(order_items.total_price * COALESCE(commission_rates.rate, 10.00) / 100) as category_commission")
            ->groupBy('categories.id', 'categories.name', 'commission_rates.rate')
            ->orderByDesc('category_commission')
            ->get();

        // ------------------------------------------------------------------
        // Commission rates (editable)
        // ------------------------------------------------------------------
        CommissionRate::firstOrCreate(['category_id' => null], ['rate' => 10.00]);
        $rates = CommissionRate::with('category')->get()->sortBy(fn ($r) => $r->category?->name ?? 'Default')->values();
        $defaultRate = CommissionRate::whereNull('category_id')->value('rate') ?? 10.00;
        $assignedCategoryIds = CommissionRate::whereNotNull('category_id')->pluck('category_id');
        $availableCategories = Category::whereNotIn('id', $assignedCategoryIds)->orderBy('name')->get();

        // ------------------------------------------------------------------
        // Payout totals
        // ------------------------------------------------------------------
        $payoutStats = [
            'pending' => SellerPayout::whereIn('status', ['pending', 'processing'])->sum('amount'),
            'paid' => SellerPayout::where('status', 'paid')->sum('amount'),
        ];

        return view('admin.commission', compact(
            'totalSales', 'totalCommission', 'sellerCommissions', 'orderCommissions',
            'categoryCommissions', 'rates', 'defaultRate', 'payoutStats', 'availableCategories'
        ));
    }

    public function updateRate(Request $request)
    {
        $request->validate([
            'rate_id' => 'required|exists:commission_rates,id',
            'rate' => 'required|numeric|min:0|max:100',
        ]);

        $rate = CommissionRate::find($request->rate_id);
        $rate->rate = $request->rate;
        $rate->save();

        $label = $rate->category ? $rate->category->name : 'Default';
        return redirect()->back()->with('status', "Commission rate for {$label} set to {$rate->rate}%.");
    }

    public function updateEarnings(Request $request)
    {
        $validated = $request->validate([
            'origin_hub_fee'        => 'required|numeric|min:0|max:5000',
            'pickup_rider_per_item' => 'required|numeric|min:0|max:5000',
            'inter_hub_driver_fee'  => 'required|numeric|min:0|max:5000',
            'destination_hub_fee'   => 'required|numeric|min:0|max:5000',
            'last_mile_rider_fee'   => 'required|numeric|min:0|max:5000',
        ]);

        EscrowEarnings::updateRates($validated);

        return redirect()->back()->with('status', 'Escrow earnings distribution schedule updated successfully.');
    }

    public function storeRate(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id|unique:commission_rates,category_id',
            'rate' => 'required|numeric|min:0|max:100',
        ]);

        CommissionRate::create($request->only('category_id', 'rate'));

        return redirect()->back()->with('status', 'Commission rate added.');
    }

    private function paginateCollection($items, Request $request, int $per, string $pageName): LengthAwarePaginator
    {
        $page = max(1, (int) $request->query($pageName, 1));
        return new LengthAwarePaginator(
            $items->forPage($page, $per)->values(),
            $items->count(),
            $per,
            $page,
            ['path' => $request->url(), 'query' => $request->query(), 'pageName' => $pageName]
        );
    }
}
