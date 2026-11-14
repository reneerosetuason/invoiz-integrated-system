<?php

/**
 * Single-use, guarded restoration of Delivery 20 routing.
 *
 * The destination hub and service area were derived from the buyer's Pila,
 * Laguna address during the premature sort at 05:53 on 2026-11-02. The sort
 * itself was invalid (no pickup/handoff custody), so the parcel was reset to
 * pending_arrival, but the route target remains valid. This restores only the
 * two routing assignments and never touches pickup/receipt/scan/sort state.
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Delivery;
use App\Models\DeliveryStatusLog;
use App\Models\LogisticsCenter;
use App\Models\ServiceArea;
use Illuminate\Support\Facades\DB;

DB::transaction(function (): void {
    $delivery = Delivery::query()->whereKey(20)->lockForUpdate()->firstOrFail();

    if ($delivery->tracking_number !== 'TRK-20261102-2KLN'
        || $delivery->parcel_status !== 'pending_arrival'
        || $delivery->received_at !== null
        || $delivery->scanned_at !== null
        || $delivery->sorted_at !== null
        || $delivery->picked_up_at !== null
        || $delivery->sorting_center_handoff_at !== null
        || $delivery->destination_center_id !== null
        || $delivery->service_area_id !== null) {
        throw new RuntimeException('Delivery 20 is not in the expected corrected state; refusing to modify it.');
    }

    $origin = LogisticsCenter::query()->findOrFail($delivery->center_id);
    $destination = LogisticsCenter::query()->findOrFail(6);
    $area = ServiceArea::query()->findOrFail(9);

    if ($destination->name !== 'pila hub'
        || (int) $destination->logistics_company_id !== (int) $origin->logistics_company_id
        || $area->name !== 'Pila'
        || (int) $area->logistics_center_id !== (int) $destination->id
        || ! $destination->is_active
        || ! $area->is_active) {
        throw new RuntimeException('Routing targets no longer match the origin company; refusing to modify Delivery 20.');
    }

    $delivery->update([
        'destination_center_id' => $destination->id,
        'service_area_id' => $area->id,
    ]);

    DeliveryStatusLog::create([
        'delivery_id' => $delivery->id,
        'status' => 'routing_restored',
        'notes' => 'Routing audit: destination pila hub (center 6) and service area Pila (9) were derived from the buyer address during the premature sort. The route remains valid, so the assignments were restored without changing pickup, receipt, scan or sort state.',
        'changed_by' => null,
    ]);
});

$delivery = Delivery::query()->findOrFail(20);
echo json_encode([
    'id' => $delivery->id,
    'tracking_number' => $delivery->tracking_number,
    'parcel_status' => $delivery->parcel_status,
    'received_at' => $delivery->received_at,
    'scanned_at' => $delivery->scanned_at,
    'sorted_at' => $delivery->sorted_at,
    'destination_center_id' => $delivery->destination_center_id,
    'service_area_id' => $delivery->service_area_id,
    'latest_log' => DeliveryStatusLog::where('delivery_id', $delivery->id)->latest('id')->value('status'),
], JSON_PRETTY_PRINT)."\n";
