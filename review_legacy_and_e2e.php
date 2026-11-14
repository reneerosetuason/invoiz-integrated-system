<?php

/**
 * Read-only review of legacy deliveries 3,4,5,6,7,8,9,10,12 plus the
 * Delivery 20 end-to-end actor map. Writes nothing to the database.
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Delivery;
use App\Models\HubTransfer;
use App\Models\HubTransportDriver;
use App\Models\LogisticsCenter;
use App\Models\LogisticsTruck;
use App\Models\Order;
use App\Models\PickupRequest;
use App\Models\Rider;
use App\Models\Seller;
use App\Models\ServiceArea;
use App\Models\User;

$ids = [3, 4, 5, 6, 7, 8, 9, 10, 12];

$deliveries = Delivery::query()
    ->with([
        'order.statusHistories',
        'pickupRequest',
        'rider.user',
        'statusLogs',
        'hubTransferParcels.transfer.origin',
        'hubTransferParcels.transfer.destination',
        'hubTransferParcels.transfer.driver',
        'hubTransferParcels.transfer.truck',
        'logisticsCenter',
        'destinationCenter',
    ])
    ->whereIn('id', $ids)
    ->orderBy('id')
    ->get();

$userName = function (?int $id): ?string {
    return $id ? User::whereKey($id)->value('name') : null;
};

$review = $deliveries->map(function (Delivery $d) use ($userName): array {
    $logs = $d->statusLogs->sortBy('id')->values()->map(fn ($l) => [
        'id' => $l->id,
        'status' => $l->status,
        'created_at' => $l->created_at?->toDateTimeString(),
        'changed_by' => $l->changed_by,
        'changed_by_name' => $userName($l->changed_by),
        'notes' => $l->notes,
    ]);

    $logStatuses = $logs->pluck('status')->all();
    $pickupLog = collect($logs)->firstWhere('status', 'picked_up') ?? collect($logs)->firstWhere('status', 'pickup');

    return [
        'delivery' => [
            'id' => $d->id,
            'tracking_number' => $d->tracking_number,
            'order_id' => $d->order_id,
            'seller_id' => $d->seller_id,
            'status' => $d->status,
            'parcel_status' => $d->parcel_status,
            'center_id' => $d->center_id,
            'center' => $d->logisticsCenter?->name,
            'destination_center_id' => $d->destination_center_id,
            'destination_center' => $d->destinationCenter?->name,
            'service_area_id' => $d->service_area_id,
            'rider_id' => $d->rider_id,
            'rider' => $d->rider ? [
                'id' => $d->rider->id,
                'name' => $d->rider->name,
                'user_id' => $d->rider->user_id,
                'user_email' => $d->rider->user?->email,
                'user_status' => $d->rider->user?->status,
                'center_id' => $d->rider->center_id,
                'status' => $d->rider->status,
                'approved_at' => $d->rider->approved_at?->toDateTimeString(),
            ] : null,
            'payment_method' => $d->payment_method,
            'amount_to_collect' => $d->amount_to_collect,
            'delivery_fee' => $d->delivery_fee,
            'created_at' => $d->created_at?->toDateTimeString(),
            'updated_at' => $d->updated_at?->toDateTimeString(),
        ],
        'custody' => [
            'picked_up_at' => $d->picked_up_at?->toDateTimeString(),
            'sorting_center_handoff_at' => $d->sorting_center_handoff_at?->toDateTimeString(),
            'sorting_center_handoff_rider_id' => $d->sorting_center_handoff_rider_id,
            'complete' => $d->picked_up_at !== null
                && $d->sorting_center_handoff_at !== null
                && $d->sorting_center_handoff_rider_id !== null,
        ],
        'hub_timestamps' => [
            'received_at' => $d->received_at?->toDateTimeString(),
            'scanned_at' => $d->scanned_at?->toDateTimeString(),
            'sorted_at' => $d->sorted_at?->toDateTimeString(),
            'dispatched_at' => $d->dispatched_at?->toDateTimeString(),
            'sorting_center_pickup_at' => $d->sorting_center_pickup_at?->toDateTimeString(),
        ],
        'terminal_timestamps' => [
            'assigned_at' => $d->assigned_at?->toDateTimeString(),
            'delivered_at' => $d->delivered_at?->toDateTimeString(),
            'failed_at' => $d->failed_at?->toDateTimeString(),
            'cancelled_at' => $d->cancelled_at?->toDateTimeString(),
            'returned_at' => $d->returned_at?->toDateTimeString(),
            'archived_at' => $d->archived_at?->toDateTimeString(),
        ],
        'failure_reason' => $d->failure_reason,
        'order' => $d->order ? [
            'id' => $d->order->id,
            'order_number' => $d->order->order_number,
            'status' => $d->order->status,
            'payment_status' => $d->order->payment_status,
            'buyer_id' => $d->order->buyer_id,
            'seller_id_column' => $d->order->seller_id,
            'created_at' => $d->order->created_at?->toDateTimeString(),
            'updated_at' => $d->order->updated_at?->toDateTimeString(),
        ] : null,
        'order_histories' => $d->order?->statusHistories?->sortBy('id')->values()->map(fn ($h) => [
            'id' => $h->id,
            'from' => $h->from_status,
            'to' => $h->to_status,
            'notes' => $h->notes,
            'created_at' => $h->created_at?->toDateTimeString(),
        ])->all(),
        'pickup_request' => $d->pickupRequest ? [
            'id' => $d->pickupRequest->id,
            'center_id' => $d->pickupRequest->center_id,
            'status' => $d->pickupRequest->status,
            'requested_at' => $d->pickupRequest->requested_at?->toDateTimeString(),
            'reviewed_at' => $d->pickupRequest->reviewed_at?->toDateTimeString(),
            'reviewed_by' => $d->pickupRequest->reviewed_by,
            'reviewed_by_name' => $userName($d->pickupRequest->reviewed_by),
            'rejection_reason' => $d->pickupRequest->rejection_reason,
        ] : null,
        'logs' => $logs->all(),
        'log_flags' => [
            'picked_up_log' => $pickupLog,
            'received_logs' => collect($logs)->where('status', 'received')->count(),
            'scanned_logs' => collect($logs)->where('status', 'scanned')->count(),
            'sorted_logs' => collect($logs)->where('status', 'sorted')->count(),
            'dispatched_logs' => collect($logs)->where('status', 'dispatched')->count(),
            'delivered_logs' => collect($logs)->where('status', 'delivered')->count(),
            'failed_logs' => collect($logs)->where('status', 'delivery_failed')->count(),
        ],
        'transfers' => $d->hubTransferParcels->map(fn ($p) => [
            'parcel_row_id' => $p->id,
            'transfer_id' => $p->hub_transfer_id,
            'reference' => $p->transfer?->reference,
            'status' => $p->transfer?->status,
            'origin' => $p->transfer?->origin?->name,
            'destination' => $p->transfer?->destination?->name,
            'driver_id' => $p->transfer?->driver_id,
            'driver' => $p->transfer?->driver?->name,
            'truck_id' => $p->transfer?->truck_id,
            'plate' => $p->transfer?->truck?->plate_number,
            'loading_status' => $p->loading_status,
            'loaded_at' => $p->loaded_at?->toDateTimeString(),
            'receiving_status' => $p->receiving_status,
            'received_at' => $p->received_at?->toDateTimeString(),
        ])->values()->all(),
    ];
});

// ---------------------------------------------------------------- E2E map
$order = Order::with(['items', 'payment', 'address'])->findOrFail(36);
$delivery = Delivery::with(['pickupRequest', 'logisticsCenter', 'destinationCenter'])->findOrFail(20);

$sellerProfile = Seller::with('user')->whereKey($order->seller_id)->first()
    ?? Seller::with('user')->where('user_id', $order->seller_id)->first();
$sellerUser = $sellerProfile?->user ?? User::find($order->seller_id);
$buyer = User::find($order->buyer_id);

$centers = LogisticsCenter::with('company')->whereIn('id', [5, 6])->get();
$staff = User::whereIn('center_id', [5, 6])
    ->whereIn('role', ['staff', 'logistics_admin', 'admin'])
    ->orderBy('center_id')
    ->get(['id', 'name', 'email', 'role', 'center_id', 'logistics_company_id', 'status', 'account_status']);

$riders = Rider::with('user')
    ->whereIn('center_id', [5, 6])
    ->orderBy('center_id')
    ->get()
    ->map(fn ($r) => [
        'id' => $r->id,
        'name' => $r->name,
        'email' => $r->email,
        'user_id' => $r->user_id,
        'user_status' => $r->user?->status,
        'center_id' => $r->center_id,
        'service_area_id' => $r->service_area_id,
        'status' => $r->status,
        'is_online' => (bool) $r->is_online,
        'is_verified' => (bool) $r->is_verified,
        'approved_at' => $r->approved_at?->toDateTimeString(),
        'license_expiry' => $r->license_expiry?->format('Y-m-d'),
        'vehicle_type' => $r->vehicle_type,
        'active_deliveries' => $r->activeDeliveryCount(),
    ]);

$drivers = HubTransportDriver::with(['user', 'truck', 'ownedTruck'])
    ->where('logistics_company_id', 1)
    ->get()
    ->map(fn ($d) => [
        'id' => $d->id,
        'name' => $d->name,
        'email' => $d->email,
        'user_id' => $d->user_id,
        'user_status' => $d->user?->status,
        'status' => $d->status,
        'is_online' => (bool) $d->is_online,
        'license_expiry' => $d->license_expiry?->format('Y-m-d'),
        'truck_id' => $d->logistics_truck_id,
        'truck_plate' => $d->truck?->plate_number,
        'owned_truck_plate' => $d->ownedTruck?->plate_number,
    ]);

$trucks = LogisticsTruck::where('logistics_company_id', 1)->get()->map(fn ($t) => [
    'id' => $t->id,
    'plate_number' => $t->plate_number,
    'vehicle_type' => $t->vehicle_type,
    'parcel_capacity' => $t->parcel_capacity,
    'capacity_kg' => $t->capacity_kg,
    'status' => $t->status,
    'driver_id' => $t->hub_transport_driver_id,
]);

$activeTransfers = HubTransfer::with(['origin', 'destination', 'driver', 'truck'])
    ->where('logistics_company_id', 1)
    ->whereIn('status', HubTransfer::ACTIVE_STATUSES)
    ->get()
    ->map(fn ($t) => [
        'id' => $t->id,
        'reference' => $t->reference,
        'status' => $t->status,
        'origin' => $t->origin?->name,
        'destination' => $t->destination?->name,
        'driver' => $t->driver?->name,
        'truck' => $t->truck?->plate_number,
        'created_at' => $t->created_at?->toDateTimeString(),
    ]);

$areas = ServiceArea::where('logistics_center_id', 6)->get(['id', 'name', 'is_active', 'logistics_center_id']);

echo json_encode([
    'generated_at' => now()->toDateTimeString(),
    'legacy_review' => $review,
    'delivery20_e2e' => [
        'order' => [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'total' => $order->total,
            'shipping_fee' => $order->shipping_fee,
            'items' => $order->items->map(fn ($i) => ['product_name' => $i->product_name, 'quantity' => $i->quantity])->all(),
            'address' => $order->address?->only(['id', 'recipient_name', 'address_line', 'barangay', 'city', 'province', 'region', 'postal_code', 'phone']),
            'seller_user' => $sellerUser ? $sellerUser->only(['id', 'name', 'email', 'role', 'status', 'account_status']) : null,
            'seller_profile' => $sellerProfile ? ['id' => $sellerProfile->id, 'store_name' => $sellerProfile->store_name, 'status' => $sellerProfile->status] : null,
            'buyer' => $buyer?->only(['id', 'name', 'email', 'role', 'status', 'account_status']),
        ],
        'delivery' => $delivery->only([
            'id', 'tracking_number', 'status', 'parcel_status', 'center_id', 'destination_center_id',
            'service_area_id', 'rider_id', 'payment_method', 'amount_to_collect', 'delivery_fee',
        ]),
        'pickup_request' => $delivery->pickupRequest?->only(['id', 'status', 'center_id', 'requested_at', 'reviewed_at', 'rejection_reason']),
        'origin_center' => $delivery->logisticsCenter?->only(['id', 'name', 'city', 'province', 'logistics_company_id', 'is_active']),
        'destination_center' => $delivery->destinationCenter?->only(['id', 'name', 'city', 'province', 'logistics_company_id', 'is_active']),
        'centers' => $centers->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'company' => $c->company?->name, 'is_active' => (bool) $c->is_active])->all(),
        'service_areas' => $areas->all(),
        'staff' => $staff->all(),
        'riders' => $riders->all(),
        'hub_drivers' => $drivers->all(),
        'trucks' => $trucks->all(),
        'active_hub_transfers' => $activeTransfers->all(),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
