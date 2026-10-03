<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with(['seller', 'buyer', 'items']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhereHas('buyer', function ($b) use ($search) {
                      $b->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('seller', function ($s) use ($search) {
                      $s->where('store_name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('status') && in_array($request->status, ['new','pending','processing','shipped','delivered','cancelled'])) {
            $query->where('status', $request->status);
        }

        $orders = $query->withCount('items')->orderBy('created_at', 'desc')->get();
        $stats = [
            'all' => Order::count(),
            'new' => Order::whereIn('status', ['new','pending'])->count(),
            'processing' => Order::where('status', 'processing')->count(),
            'shipped' => Order::where('status', 'shipped')->count(),
            'delivered' => Order::where('status', 'delivered')->count(),
            'cancelled' => Order::where('status', 'cancelled')->count(),
            'revenue' => Order::where('status', 'delivered')->sum('total'),
        ];
        if ($request->wantsJson()) {
            return $orders;
        }
        return view('admin.orders', compact('orders', 'stats'));
    }

    public function show(Request $request, Order $order)
    {
        $order->load(['seller', 'buyer', 'items.product.images']);
        if ($request->wantsJson()) {
            return $order;
        }
        return view('admin.order-detail', compact('order'));
    }

    public function update(Request $request, Order $order)
    {
        $request->validate([
            'status' => 'sometimes|in:new,pending,processing,shipped,delivered,cancelled',
            'delivery_status' => 'sometimes|in:pending,assigned,in_transit,delivered',
        ]);

        $from = $order->status;
        $order->update($request->only(['status', 'delivery_status']));

        // Keep the buyer timeline corresponding: every status change writes history.
        if ($request->filled('status') && $request->status !== $from) {
            \App\Models\OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => $from,
                'to_status' => $order->status,
                'note' => 'Status updated by admin.',
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Order updated', 'order' => $order]);
        }
        return redirect()->back()->with('status', 'Order updated successfully.');
    }
}
