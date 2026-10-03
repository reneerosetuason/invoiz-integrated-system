<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Seller;
use App\Models\Category;
use App\Models\SellerPayout;
use App\Models\CommissionRate;
use Illuminate\Http\Request;
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
        $sellerCommissions = Order::where('payment_status', 'paid')
            ->join('sellers', 'orders.seller_id', '=', 'sellers.id')
            ->selectRaw('sellers.id as seller_id, sellers.store_name,
                         SUM(orders.sub_total) as total_sales,
                         SUM(orders.commission_amount) as total_commission')
            ->groupBy('sellers.id', 'sellers.store_name')
            ->orderByDesc('total_commission')
            ->get()
            ->map(function ($row) {
                $row->payouts_pending = SellerPayout::where('seller_id', $row->seller_id)
                    ->whereIn('status', ['pending', 'processing'])->sum('amount');
                $row->payouts_paid = SellerPayout::where('seller_id', $row->seller_id)
                    ->where('status', 'paid')->sum('amount');
                return $row;
            });

        // ------------------------------------------------------------------
        // Commission per order (recent paid orders)
        // ------------------------------------------------------------------
        $orderCommissions = (clone $query)->with('seller', 'buyer')
            ->orderByDesc('created_at')
            ->take(20)
            ->get();

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
        $rates = CommissionRate::with('category')->get()->sortBy(fn ($r) => $r->category->name ?? 'Default')->values();
        $defaultRate = CommissionRate::whereNull('category_id')->value('rate') ?? 10.00;

        // ------------------------------------------------------------------
        // Payout totals
        // ------------------------------------------------------------------
        $payoutStats = [
            'pending' => SellerPayout::whereIn('status', ['pending', 'processing'])->sum('amount'),
            'paid' => SellerPayout::where('status', 'paid')->sum('amount'),
        ];

        return view('admin.commission', compact(
            'totalSales', 'totalCommission', 'sellerCommissions', 'orderCommissions',
            'categoryCommissions', 'rates', 'defaultRate', 'payoutStats'
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

    public function storeRate(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id|unique:commission_rates,category_id',
            'rate' => 'required|numeric|min:0|max:100',
        ]);

        CommissionRate::create($request->only('category_id', 'rate'));

        return redirect()->back()->with('status', 'Commission rate added.');
    }
}
