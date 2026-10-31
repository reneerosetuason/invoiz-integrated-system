<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;

use App\Models\CourierPickup;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\PickupRequest;
use App\Models\SellerNotification;
use App\Services\ReturnToSellerService;
use App\Support\Remittances;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    protected const TRANSITIONS = [
        'pending'             => ['confirmed', 'cancelled'],
        'confirmed'           => ['processing', 'cancelled'],
        'processing'          => ['ready_for_delivery', 'cancelled'],
        'ready_for_delivery'  => ['cancelled'],
        'out_for_delivery'    => [],
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

        $orders = $query->paginate(10);
        $orders->setCollection($orders->getCollection()->map(function (Order $order) use ($sellerId) {
            $order->seller_items = $order->items->where('seller_id', $sellerId)->values();
            $order->seller_subtotal = round($order->seller_items->sum('subtotal'), 2);
            // Costs absorbed by this seller (pro-rata voucher share + waived
            // shipping for their group) + net earnings.
            $order->seller_discount = $order->voucherDiscountForSeller($sellerId);
            $order->seller_shipping_waived = $order->shippingWaivedForSeller($sellerId);
            $order->seller_net = $order->sellerNetForSeller($sellerId);

            return $order;
        }));

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
            'deliveries.statusLogs',
            'courierPickup',
            'orderVouchers.voucher',
            'statusHistories',
        ])
            ->whereHas('items', fn ($q) => $q->where('seller_id', $sellerId))
            ->findOrFail($id);

        $order->seller_items = $order->items->where('seller_id', $sellerId)->values();
        $order->seller_subtotal = round($order->seller_items->sum('subtotal'), 2);
        // Costs absorbed by this seller (pro-rata voucher share + waived
        // shipping for their group) + net earnings.
        $order->seller_discount = $order->voucherDiscountForSeller($sellerId);
        $order->seller_shipping_waived = $order->shippingWaivedForSeller($sellerId);
        $order->seller_net = $order->sellerNetForSeller($sellerId);

        $sellerKeys = array_map('intval', Remittances::sellerKeys($sellerId));
        $sellerDeliveries = $order->deliveries
            ->filter(fn ($delivery) => in_array((int) $delivery->seller_id, $sellerKeys, true))
            ->values();

        $sellerDelivery = $sellerDeliveries->first();
        $returnState = null;

        if ($sellerDelivery) {
            $returns = app(ReturnToSellerService::class);

            foreach ($sellerDeliveries as $delivery) {
                $state = $returns->state($delivery);
                if ($state['state'] !== 'not_queued') {
                    $sellerDelivery = $delivery;
                    $returnState = $state;
                    break;
                }
            }

            $returnState ??= $returns->state($sellerDelivery);
        }

        $order->setRelation('delivery', $sellerDelivery);

        return view('seller.orders.show', compact('order', 'returnState'));
    }

    public function confirmReturn(Request $request, Delivery $delivery, ReturnToSellerService $returns)
    {
        $validated = $request->validate([
            'note' => 'nullable|string|max:1000',
        ]);

        $returns->confirm($delivery, $request->user(), $validated['note'] ?? null);

        return back()->with('success', 'Returned parcel confirmed. Stock has been restored once for this return.');
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

        DB::transaction(function () use ($order, $target, $action, $note, $sellerId) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! in_array($target, self::TRANSITIONS[$order->status] ?? [], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['order' => 'The order changed. Refresh before trying again.']);
            }
            if ($target === 'cancelled') {
                \App\Support\OrderCancellation::bySeller($order, $note ?: 'Cancelled by seller.', $sellerId);
            } else {
                $from = $order->status;
                $order->update(['status' => $target]);
                OrderStatusHistory::create([
                    'order_id' => $order->id, 'from_status' => $from, 'to_status' => $target,
                    'note' => $note ?: $this->defaultNote($action),
                ]);
                app(\App\Services\OrderShipmentService::class)->ensure($order, $target === 'ready_for_delivery');
            }
            SellerNotification::create([
                'seller_id' => $sellerId, 'order_id' => $order->id, 'type' => 'status',
                'title' => $this->notificationTitle($action), 'body' => "Order #{$order->id} is now {$target}.",
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

        DB::transaction(function () use ($order, $sellerId, $validated) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($order->status, ['processing', 'ready_for_delivery'], true), 409, 'The order can no longer be scheduled for pickup.');
            $delivery = app(\App\Services\OrderShipmentService::class)->ensure($order, $order->status === 'ready_for_delivery');
            abort_if($delivery->picked_up_at, 409, 'The parcel has already left the seller.');
            CourierPickup::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'seller_id'       => $sellerId,
                    'courier'         => $validated['courier'],
                    'pickup_at'       => $validated['pickup_at'],
                    'tracking_number' => $validated['tracking_number'] ?? null,
                    'status'          => 'scheduled',
                    'notes'           => $validated['notes'] ?? null,
                ]
            );
        });

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

        // The slip must carry the same machine-readable tracking as the
        // logistics label so counter scans accept it. Backfill the TRK number
        // (same format the logistics side auto-generates) when missing.
        if ($order->delivery && empty($order->delivery->tracking_number)) {
            do {
                $tracking = 'TRK-' . now()->format('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(4));
            } while (Delivery::where('tracking_number', $tracking)->exists());
            $order->delivery->update(['tracking_number' => $tracking]);
            $order->delivery->refresh();
        }

        return view('seller.orders.waybill', compact('order'));
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