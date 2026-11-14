<?php
// Read-only diagnostic. Loads the parcel and its related records; writes nothing.
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Delivery;
use App\Models\DeliveryStatusLog;
use App\Models\HubTransferParcel;
use App\Models\OrderStatusHistory;
use App\Services\FulfillmentWorkflow;

$d = Delivery::where('tracking_number', 'TRK-20261102-2KLN')->firstOrFail();

echo "=== Delivery ===\n";
echo json_encode($d->only([
    'id', 'tracking_number', 'order_id', 'seller_id', 'rider_id', 'center_id',
    'destination_center_id', 'service_area_id', 'origin_center_id',
    'status', 'parcel_status', 'assigned_at', 'accepted_at', 'picked_up_at',
    'received_at', 'scanned_at', 'sorted_at', 'dispatched_at',
    'sorting_center_handoff_at', 'sorting_center_handoff_rider_id',
    'sorting_center_pickup_at', 'sorting_center_pickup_rider_id',
    'archived_at', 'created_at', 'updated_at',
]), JSON_PRETTY_PRINT) . "\n";

echo "\n=== hasPickupHandoff === " . (FulfillmentWorkflow::hasPickupHandoff($d) ? 'true' : 'false') . "\n";

echo "\n=== Order ===\n";
echo json_encode($d->order?->only(['id', 'status', 'payment_status', 'total_amount', 'created_at', 'updated_at']), JSON_PRETTY_PRINT) . "\n";

echo "\n=== Pickup request ===\n";
echo json_encode($d->pickupRequest?->only(['id', 'delivery_id', 'center_id', 'status', 'requested_at', 'reviewed_at', 'reviewed_by', 'rejection_reason', 'created_at', 'updated_at']), JSON_PRETTY_PRINT) . "\n";

echo "\n=== Assigned rider ===\n";
echo json_encode($d->rider?->only(['id', 'name', 'center_id', 'status', 'vehicle_type']), JSON_PRETTY_PRINT) . "\n";

echo "\n=== Handoff rider ===\n";
echo json_encode($d->sortingCenterHandoffRider?->only(['id', 'name', 'center_id', 'status', 'vehicle_type']), JSON_PRETTY_PRINT) . "\n";

echo "\n=== Hub transfer manifest entries ===\n";
$entries = HubTransferParcel::where('delivery_id', $d->id)->with('transfer')->get();
echo json_encode($entries->map(fn ($e) => [
    'manifest_id' => $e->id,
    'loading_status' => $e->loading_status,
    'receiving_status' => $e->receiving_status,
    'condition' => $e->condition,
    'transfer_id' => $e->hub_transfer_id,
    'transfer_reference' => $e->transfer?->reference,
    'transfer_status' => $e->transfer?->status,
    'origin_center_id' => $e->transfer?->origin_center_id,
    'destination_center_id' => $e->transfer?->destination_center_id,
    'transfer_dispatched_at' => $e->transfer?->dispatched_at,
]), JSON_PRETTY_PRINT) . "\n";

echo "\n=== Delivery status logs ===\n";
foreach (DeliveryStatusLog::where('delivery_id', $d->id)->orderBy('created_at')->get() as $log) {
    echo "{$log->created_at} | {$log->status} | by {$log->changed_by} | {$log->notes}\n";
}

echo "\n=== Order status histories ===\n";
foreach (OrderStatusHistory::where('order_id', $d->order_id)->orderBy('created_at')->get() as $h) {
    echo "{$h->created_at} | {$h->from_status} -> {$h->to_status} | {$h->note}\n";
}
