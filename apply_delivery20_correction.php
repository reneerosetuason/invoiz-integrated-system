<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Delivery;
use App\Models\DeliveryStatusLog;
use App\Services\FulfillmentWorkflow;
use Illuminate\Support\Facades\DB;

$tracking = 'TRK-20261102-2KLN';

$result = DB::transaction(function () use ($tracking) {
    $delivery = Delivery::where('id', 20)->lockForUpdate()->first();

    if (! $delivery) {
        throw new RuntimeException('Delivery 20 not found.');
    }
    if ($delivery->tracking_number !== $tracking) {
        throw new RuntimeException('Delivery 20 tracking mismatch: ' . $delivery->tracking_number);
    }
    if ($delivery->parcel_status !== 'sorted') {
        throw new RuntimeException('Unexpected parcel_status: ' . $delivery->parcel_status . ' (already corrected?)');
    }
    if (FulfillmentWorkflow::hasPickupHandoff($delivery)) {
        throw new RuntimeException('Custody evidence now exists; refusing to roll back verified hub processing.');
    }

    $before = $delivery->only([
        'parcel_status', 'received_at', 'scanned_at', 'sorted_at',
        'destination_center_id', 'service_area_id', 'status', 'order_id', 'rider_id',
    ]);

    $delivery->update([
        'parcel_status' => 'pending_arrival',
        'received_at' => null,
        'scanned_at' => null,
        'sorted_at' => null,
        'destination_center_id' => null,
        'service_area_id' => null,
    ]);

    DeliveryStatusLog::create([
        'delivery_id' => $delivery->id,
        'status' => 'custody_correction',
        'notes' => 'Custody audit: receipt, scan and sort were recorded with no pickup rider, pickup time or hub handoff. Hub processing reset to pending arrival; the original log rows are retained as audit history.',
        'changed_by' => null,
    ]);

    return [
        'before' => $before,
        'after' => $delivery->fresh()->only([
            'parcel_status', 'received_at', 'scanned_at', 'sorted_at',
            'destination_center_id', 'service_area_id', 'status', 'order_id', 'rider_id',
        ]),
        'pickup_request_status' => $delivery->pickupRequest?->status,
        'order_status' => $delivery->order?->status,
    ];
});

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
