<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\DeliveryStatusLog;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Rider;
use App\Models\SellerNotification;

class FulfillmentWorkflow
{
    /** Every workflow writer takes locks in the same order as cancellation. */
    public static function lock(Delivery $delivery): Delivery
    {
        if ($delivery->order_id) {
            Order::whereKey($delivery->order_id)->lockForUpdate()->firstOrFail();
        }

        return Delivery::whereKey($delivery->id)->lockForUpdate()->firstOrFail();
    }

    public static function assertActiveOrder(Delivery $delivery): void
    {
        abort_if($delivery->archived_at, 409, 'Restore this archived shipment before continuing its workflow.');
        if ($delivery->order_id) {
            $order = $delivery->order()->first();
            abort_if(! $order || $order->status === 'cancelled', 409, 'This order is cancelled or unavailable.');
            abort_if($order->status === 'delivered' && $delivery->status !== 'delivered', 409, 'This order is already delivered.');
        }
    }

    public static function assertReady(Delivery $delivery): void
    {
        self::assertActiveOrder($delivery);
        if (! $delivery->order_id) {
            return; // Standalone logistics shipments retain their existing workflow.
        }
        abort_unless($delivery->logisticsCenter()->where('is_active', true)->exists(), 409, 'Route this parcel to an active handling center first.');
        if ($delivery->received_at !== null) {
            abort_unless($delivery->parcel_status === 'sorted' || $delivery->parcel_status === 'dispatched', 409, 'The center must scan and sort this parcel first.');
            abort_if($delivery->destination_center_id && (int) $delivery->destination_center_id !== (int) $delivery->center_id, 409, 'Transfer this parcel to its destination center before assigning the delivery rider.');

            return;
        }
        abort_unless($delivery->order()->value('status') === 'ready_for_delivery', 409, 'The seller must mark this order as packed before pickup.');
        $pickup = $delivery->pickupRequest()->first();
        abort_unless($pickup && $pickup->status === 'approved', 409, 'Logistics must approve the pickup request first.');
        abort_unless($delivery->center_id && (int) $pickup->center_id === (int) $delivery->center_id, 409, 'Route and approve the pickup at the handling center first.');
    }

    public static function syncOrder(Delivery $delivery, string $status, string $note): void
    {
        if (! $delivery->order_id) {
            return;
        }
        $order = $delivery->order()->lockForUpdate()->firstOrFail();
        abort_if($order->status === 'cancelled', 409, 'A cancelled order cannot be shipped or delivered.');
        if ($order->status === $status || ($order->status === 'delivered' && $status !== 'delivered')) {
            return;
        }
        $from = $order->status;
        $order->update(['status' => $status]);
        if ($status === 'out_for_delivery' && $delivery->picked_up_at) {
            $order->courierPickup?->update(['status' => 'picked_up']);
        }
        OrderStatusHistory::create(['order_id' => $order->id, 'from_status' => $from, 'to_status' => $status, 'note' => $note]);
        SellerNotification::create([
            'seller_id' => $delivery->seller_id, 'order_id' => $order->id,
            'type' => 'status', 'title' => $status === 'delivered' ? 'Order delivered' : 'Parcel picked up', 'body' => $note,
        ]);
    }

    public static function releaseRider(?int $riderId): void
    {
        if (! $riderId) {
            return;
        }
        $rider = Rider::whereKey($riderId)->lockForUpdate()->first();
        if ($rider && $rider->status !== 'inactive' && ! Delivery::where('rider_id', $riderId)->whereIn('status', Delivery::ACTIVE_STATUSES)->exists()) {
            // Online/offline is a separate flag; "offline" is not a valid
            // riders.status value in the shared MySQL schema.
            $eligible = $rider->isEligibilityApproved() && $rider->vehicle_verification === 'verified'
                && $rider->vehicleTypeIsActive() && ! $rider->licenseExpired();
            $rider->update(['status' => $eligible ? 'available' : 'inactive']);
        }
    }

    public static function receive(Delivery $delivery, int $actorId): void
    {
        self::assertActiveOrder($delivery);
        if ($delivery->isIncomingTransfer()) {
            $originId = $delivery->origin_center_id ?? $delivery->center_id;
            $delivery->update([
                'origin_center_id' => $originId,
                'center_id' => $delivery->destination_center_id, 'parcel_status' => 'received', 'received_at' => now(),
                'scanned_at' => null, 'sorted_at' => null, 'dispatched_at' => null,
            ]);
            $originName = \App\Models\LogisticsCenter::whereKey($originId)->value('name') ?? "center #{$originId}";
            $destName = \App\Models\LogisticsCenter::whereKey($delivery->center_id)->value('name') ?? "center #{$delivery->center_id}";
            DeliveryStatusLog::create([
                'delivery_id' => $delivery->id, 'status' => 'received', 'changed_by' => $actorId,
                'notes' => "Inter-hub transfer received from {$originName} at {$destName}. Scan and sort before final dispatch.",
            ]);

            \App\Support\EscrowEarnings::creditInterHubDriverOnTransferReceipt($delivery);

            return;
        }
        if ($delivery->order_id) {
            abort_unless($delivery->status === 'picked_up' && $delivery->sorting_center_handoff_at && $delivery->sorting_center_handoff_rider_id, 409, 'The pickup rider must record the center handoff before staff can receive this parcel.');
        }
        $riderId = $delivery->rider_id;
        $updates = [
            'parcel_status' => 'received',
            'received_at' => now(),
            'origin_center_id' => $delivery->origin_center_id ?? $delivery->center_id,
        ];
        if ($delivery->sorting_center_handoff_at) {
            $updates += ['rider_id' => null, 'status' => 'waiting_for_rider', 'assigned_at' => null, 'accepted_at' => null];
        }
        $delivery->update($updates);
        DeliveryStatusLog::create(['delivery_id' => $delivery->id, 'status' => 'received', 'changed_by' => $actorId, 'notes' => 'Center receipt confirmed; pickup custody closed.']);
        \App\Support\EscrowEarnings::creditPickupRiderOnHubReceipt($delivery);
        self::releaseRider($riderId);
    }
}
