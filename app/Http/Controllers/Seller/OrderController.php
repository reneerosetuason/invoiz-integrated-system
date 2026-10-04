<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;

use App\Models\CourierPickup;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\SellerNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    protected const TRANSITIONS = [
        'pending'             => ['confirmed', 'cancelled'],
        'confirmed'           => ['processing', 'cancelled'],
        'processing'          => ['ready_for_delivery'],
        'ready_for_delivery'  => ['out_for_delivery'],
        'out_for_delivery'    => ['delivered'],
        'delivered'           => [],
        'cancelled'           => [],
    ];

    public function index(Request $request)
    {
        $sellerId = auth()->id();

        $status = $request->input('status', 'all');

        $query = Order::with(['buyer', 'items', 'payment', 'delivery', 'courierPickup'])
            ->whereHas('items', fn ($q) => $q->where('seller_id', $sellerId))
            ->latest();

        if ($status !== 'all' && array_key_exists($status, self::TRANSITIONS)) {
            $query->where('status', $status);
        }

        $orders = $query->get()->map(function (Order $order) use ($sellerId) {
            $order->seller_items = $order->items->where('seller_id', $sellerId)->values();
            $order->seller_subtotal = round($order->seller_items->sum('subtotal'), 2);

            return $order;
        });

        $counts = [
            'all'               => Order::whereHas('items', fn ($q) => $q->where('seller_id', $sellerId))->count(),
            'pending'           => Order::whereHas('items', fn ($q) => $q->where('seller_id', $sellerId))->where('status', 'pending')->count(),
            'confirmed'         => Order::whereHas('items', fn ($q) => $q->where('seller_id', $sellerId))->where('status', 'confirmed')->count(),
            'processing'        => Order::whereHas('items', fn ($q) => $q->where('seller_id', $sellerId))->where('status', 'processing')->count(),
            'ready_for_delivery' => Order::whereHas('items', fn ($q) => $q->where('seller_id', $sellerId))->where('status', 'ready_for_delivery')->count(),
            'out_for_delivery'  => Order::whereHas('items', fn ($q) => $q->where('seller_id', $sellerId))->where('status', 'out_for_delivery')->count(),
            'delivered'         => Order::whereHas('items', fn ($q) => $q->where('seller_id', $sellerId))->where('status', 'delivered')->count(),
            'cancelled'         => Order::whereHas('items', fn ($q) => $q->where('seller_id', $sellerId))->where('status', 'cancelled')->count(),
        ];

        return view('seller.orders.index', compact('orders', 'status', 'counts'));
    }

    public function show($id)
    {
        $sellerId = auth()->id();

        $order = Order::with([
            'buyer',
            'address',
            'items',
            'payment',
            'delivery',
            'courierPickup',
            'orderVouchers.voucher',
            'statusHistories',
        ])
            ->whereHas('items', fn ($q) => $q->where('seller_id', $sellerId))
            ->findOrFail($id);

        $order->seller_items = $order->items->where('seller_id', $sellerId)->values();
        $order->seller_subtotal = round($order->seller_items->sum('subtotal'), 2);

        return view('seller.orders.show', compact('order'));
    }

    public function updateStatus(Request $request, $id)
    {
        $sellerId = auth()->id();
        $action = $request->input('action');

        $order = Order::with(['items', 'delivery', 'payment'])
            ->whereHas('items', fn ($q) => $q->where('seller_id', $sellerId))
            ->findOrFail($id);

        $target = match ($action) {
            'accept'      => 'confirmed',
            'process'     => 'processing',
            'pack'        => 'ready_for_delivery',
            'handover'    => 'out_for_delivery',
            'deliver'     => 'delivered',
            'cancel'      => 'cancelled',
            default       => null,
        };

        if (! $target) {
            return back()->with('error', 'Unknown action.');
        }

        if (! in_array($target, self::TRANSITIONS[$order->status] ?? [], true)) {
            return back()->with('error', 'This action is not allowed for the current order status.');
        }

        $note = $request->input('note');

        DB::transaction(function () use ($order, $target, $action, $note) {
            $from = $order->status;
            $order->update(['status' => $target]);

            OrderStatusHistory::create([
                'order_id'    => $order->id,
                'from_status' => $from,
                'to_status'   => $target,
                'note'        => $note ?: $this->defaultNote($action),
                'created_at'  => now(),
            ]);

            $this->syncDelivery($order, $target, $action);

            SellerNotification::create([
                'seller_id' => auth()->id(),
                'order_id'  => $order->id,
                'type'      => 'status',
                'title'     => $this->notificationTitle($action),
                'body'      => "Order #{$order->id} is now {$target}.",
            ]);
        });

        $flash = $this->flashMessage($action);

        return back()->with('success', $flash);
    }

    public function schedulePickup(Request $request, $id)
    {
        $sellerId = auth()->id();

        $order = Order::whereHas('items', fn ($q) => $q->where('seller_id', $sellerId))
            ->whereIn('status', ['ready_for_delivery', 'processing'])
            ->findOrFail($id);

        $validated = $request->validate([
            'courier'         => ['required', 'string', 'max:100'],
            'pickup_at'       => ['required', 'date', 'after_or_equal:today'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'notes'           => ['nullable', 'string', 'max:1000'],
        ]);

        CourierPickup::create([
            'order_id'        => $order->id,
            'seller_id'       => $sellerId,
            'courier'         => $validated['courier'],
            'pickup_at'       => $validated['pickup_at'],
            'tracking_number' => $validated['tracking_number'] ?? null,
            'status'          => 'scheduled',
            'notes'           => $validated['notes'] ?? null,
        ]);

        return back()->with('success', 'Courier pickup scheduled successfully.');
    }

    public function waybill($id)
    {
        $sellerId = auth()->id();

        $order = Order::with(['buyer', 'address', 'items', 'delivery', 'courierPickup'])
            ->whereHas('items', fn ($q) => $q->where('seller_id', $sellerId))
            ->findOrFail($id);

        $order->seller_items = $order->items->where('seller_id', $sellerId)->values();
        $order->seller_subtotal = round($order->seller_items->sum('subtotal'), 2);

        return view('seller.orders.waybill', compact('order'));
    }

    private function syncDelivery(Order $order, string $target, string $action): void
    {
        $delivery = $order->delivery;

        if (! $delivery) {
            $delivery = Delivery::create(['order_id' => $order->id]);
        }

        if ($target === 'out_for_delivery') {
            $data = ['status' => 'picked_up'];
            if (! $delivery->picked_up_at) {
                $data['picked_up_at'] = now();
            }
            $delivery->update($data);

            if ($order->courierPickup) {
                $order->courierPickup->update(['status' => 'picked_up']);
            }
        }

        if ($target === 'delivered') {
            $delivery->update([
                'status'       => 'delivered',
                'delivered_at' => now(),
            ]);

            if ($order->payment) {
                $order->payment->update([
                    'status'  => 'paid',
                    'paid_at' => now(),
                ]);
            }
        }
    }

    private function defaultNote(string $action): string
    {
        return match ($action) {
            'accept'   => 'Order accepted by seller.',
            'process'  => 'Order is being prepared.',
            'pack'     => 'Items packed and ready for pickup.',
            'handover' => 'Order handed over to courier.',
            'deliver'  => 'Order delivered to customer.',
            'cancel'   => 'Order cancelled by seller.',
            default    => 'Status updated.',
        };
    }

    private function notificationTitle(string $action): string
    {
        return match ($action) {
            'accept'   => 'Order accepted',
            'process'  => 'Order is being prepared',
            'pack'     => 'Order packed',
            'handover' => 'Order handed to courier',
            'deliver'  => 'Delivery confirmed',
            'cancel'   => 'Order cancelled',
            default    => 'Order updated',
        };
    }

    private function flashMessage(string $action): string
    {
        return match ($action) {
            'accept'   => 'Order accepted. You can now start preparing it.',
            'process'  => 'Order is now being prepared.',
            'pack'     => 'Order packed. Ready for courier handover.',
            'handover' => 'Order handed over to the courier.',
            'deliver'  => 'Delivery confirmed. The order has been completed.',
            'cancel'   => 'Order cancelled.',
            default    => 'Order updated.',
        };
    }
}