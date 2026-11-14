<?php

/**
 * Read-only classification of order-linked parcels whose parcel_status
 * advanced to a hub stage without recorded pickup/handoff custody.
 *
 * No rows are created, updated or deleted. Classification is derived only
 * from existing delivery/order/pickup/log/manifest records.
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Delivery;

$rows = Delivery::query()
    ->with([
        'order:id,status',
        'pickupRequest:id,delivery_id,center_id,status,requested_at,reviewed_at',
        'rider:id,name,status',
        'statusLogs' => fn ($q) => $q->orderBy('id'),
        'hubTransferParcels:id,hub_transfer_id,delivery_id,loading_status,receiving_status',
    ])
    ->whereNotNull('order_id')
    ->whereIn('parcel_status', ['received', 'scanned', 'sorted', 'dispatched'])
    ->where(function ($q) {
        $q->whereNull('picked_up_at')
            ->orWhereNull('sorting_center_handoff_at')
            ->orWhereNull('sorting_center_handoff_rider_id');
    })
    ->orderBy('id')
    ->get();

$classify = function (Delivery $delivery): array {
    $orderStatus = $delivery->order?->status;
    $terminal = in_array($delivery->status, ['delivered', 'returned_to_seller', 'delivery_failed', 'lost', 'damaged', 'cancelled'], true);
    $hasTerminalProof = match ($delivery->status) {
        'delivered' => $delivery->delivered_at !== null,
        'delivery_failed' => $delivery->failed_at !== null,
        'lost', 'damaged' => $delivery->failed_at !== null,
        'cancelled' => $delivery->cancelled_at !== null,
        'returned_to_seller' => $delivery->returned_at !== null,
        default => false,
    };

    if ($terminal && ($hasTerminalProof || in_array($orderStatus, ['delivered', 'cancelled'], true))) {
        return ['historical_terminal', 'terminal delivery status with recorded completion/exception evidence'];
    }
    if ($terminal) {
        return ['terminal_without_timestamp', 'terminal delivery status but missing its completion/exception timestamp'];
    }

    return ['active_in_flight', 'non-terminal delivery status; hub progress is unverified and hidden from customers'];
};

$summary = [];
$out = [];

foreach ($rows as $delivery) {
    [$bucket, $reason] = $classify($delivery);
    $summary[$bucket] = ($summary[$bucket] ?? 0) + 1;

    $out[] = [
        'id' => $delivery->id,
        'tracking_number' => $delivery->tracking_number,
        'order_id' => $delivery->order_id,
        'order_status' => $delivery->order?->status,
        'delivery_status' => $delivery->status,
        'parcel_status' => $delivery->parcel_status,
        'classification' => $bucket,
        'reason' => $reason,
        'center_id' => $delivery->center_id,
        'destination_center_id' => $delivery->destination_center_id,
        'service_area_id' => $delivery->service_area_id,
        'rider_id' => $delivery->rider_id,
        'rider_name' => $delivery->rider?->name,
        'custody' => [
            'picked_up_at' => $delivery->picked_up_at?->toDateTimeString(),
            'sorting_center_handoff_at' => $delivery->sorting_center_handoff_at?->toDateTimeString(),
            'sorting_center_handoff_rider_id' => $delivery->sorting_center_handoff_rider_id,
        ],
        'hub_timestamps' => [
            'received_at' => $delivery->received_at?->toDateTimeString(),
            'scanned_at' => $delivery->scanned_at?->toDateTimeString(),
            'sorted_at' => $delivery->sorted_at?->toDateTimeString(),
            'dispatched_at' => $delivery->dispatched_at?->toDateTimeString(),
        ],
        'terminal_timestamps' => [
            'delivered_at' => $delivery->delivered_at?->toDateTimeString(),
            'failed_at' => $delivery->failed_at?->toDateTimeString(),
            'cancelled_at' => $delivery->cancelled_at?->toDateTimeString(),
            'returned_at' => $delivery->returned_at?->toDateTimeString(),
            'archived_at' => $delivery->archived_at?->toDateTimeString(),
        ],
        'pickup_request' => $delivery->pickupRequest ? [
            'id' => $delivery->pickupRequest->id,
            'center_id' => $delivery->pickupRequest->center_id,
            'status' => $delivery->pickupRequest->status,
            'requested_at' => $delivery->pickupRequest->requested_at?->toDateTimeString(),
            'reviewed_at' => $delivery->pickupRequest->reviewed_at?->toDateTimeString(),
        ] : null,
        'manifest_rows' => $delivery->hubTransferParcels->map(fn ($p) => [
            'hub_transfer_id' => $p->hub_transfer_id,
            'loading_status' => $p->loading_status,
            'receiving_status' => $p->receiving_status,
        ])->values()->all(),
        'status_logs' => $delivery->statusLogs->map(fn ($log) => [
            'id' => $log->id,
            'status' => $log->status,
            'created_at' => $log->created_at?->toDateTimeString(),
        ])->values()->all(),
    ];
}

echo json_encode([
    'generated_at' => now()->toDateTimeString(),
    'total' => $rows->count(),
    'summary' => $summary,
    'parcels' => $out,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
