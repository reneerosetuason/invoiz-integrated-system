<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\DeliveryStatusLog;
use App\Models\HubTransfer;
use App\Models\HubTransferParcel;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\PickupRequest;
use App\Models\Rider;
use App\Models\SellerNotification;
use Illuminate\Support\Facades\Schema;

class FulfillmentWorkflow
{
    /** Caller holds the order/delivery locks and has authorized the handling staff. */
    public static function reviewDestination(Delivery $delivery, int $actorId): void
    {
        self::assertActiveOrder($delivery);
        abort_unless($delivery->received_at && !$delivery->sorting_center_pickup_at
            && in_array($delivery->status, ['waiting_for_rider', 'assigned'], true)
            && !self::hasActiveHubTransfer($delivery), 409, 'Only an uncollected parcel at the hub can have its delivery assignment corrected.');
        abort_unless($delivery->lastMileRouteBlockReason(), 409, 'This parcel already has a local delivery route.');
        $suggested = $delivery->suggestedDestination();
        if ($delivery->destination_center_id && (int) $delivery->destination_center_id !== (int) $delivery->center_id) {
            $suggested = ['center_id' => (int) $delivery->destination_center_id, 'service_area_id' => $delivery->service_area_id];
        }
        abort_unless($suggested['center_id'] && (int) $suggested['center_id'] !== (int) $delivery->center_id, 422, 'Confirm a destination hub and service area through sorting; the address does not identify one hub reliably.');
        $riderId = $delivery->rider_id;
        $delivery->update(['destination_center_id' => $suggested['center_id'], 'service_area_id' => $suggested['service_area_id'],
            'rider_id' => null, 'assigned_at' => null, 'accepted_at' => null, 'status' => 'waiting_for_rider']);
        self::releaseRider($riderId);
        DeliveryStatusLog::create(['delivery_id' => $delivery->id, 'status' => 'destination_reviewed', 'changed_by' => $actorId,
            'notes' => 'Uncollected last-mile assignment cleared; route reviewed for inter-hub transport to hub #'.$suggested['center_id'].'.']);
    }

    /** Every workflow writer takes locks in the same order as cancellation. */
    public static function lock(Delivery $delivery): Delivery
    {
        if ($delivery->order_id) {
            Order::whereKey($delivery->order_id)->lockForUpdate()->firstOrFail();
        }
        return Delivery::whereKey($delivery->id)->lockForUpdate()->firstOrFail();
    }

    public static function assertActiveOrder(Delivery $delivery, bool $allowDeliveredOrder = false): void
    {
        abort_if($delivery->archived_at, 409, 'Restore this archived shipment before continuing its workflow.');
        abort_if($delivery->status === 'delivered', 409, 'This parcel is already delivered.');
        if ($delivery->order_id) {
            $order = $delivery->order()->first();
            abort_if(! $order || $order->status === 'cancelled', 409, 'This order is cancelled or unavailable.');
            if (! $allowDeliveredOrder) {
                abort_if($order->status === 'delivered', 409, 'This order is already delivered.');
            }
        }
    }

    /**
     * An active fleet transfer owns custody until the destination hub accepts
     * this specific parcel. Arrival alone never releases it, and accepting
     * custody never finalizes the trip: other manifest parcels, exceptions and
     * driver settlement remain on the transfer until complete() is called.
     */
    public static function hasActiveHubTransfer(Delivery $delivery): bool
    {
        try {
            if (! Schema::hasTable('hub_transfer_parcels') || ! Schema::hasTable('hub_transfers')) {
                return false;
            }

            return HubTransferParcel::query()
                ->where('delivery_id', $delivery->id)
                ->where('loading_status', '!=', 'removed')
                ->where('receiving_status', '!=', 'received')
                ->whereHas('transfer', fn ($query) => $query->whereIn('status', HubTransfer::ACTIVE_STATUSES))
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function assertReady(Delivery $delivery): void
    {
        self::assertActiveOrder($delivery);
        abort_if(self::hasActiveHubTransfer($delivery), 409, 'This parcel is in an active hub transfer and cannot be assigned for last-mile delivery.');
        abort_if($delivery->lastMileRouteBlockReason() !== null, 409, $delivery->lastMileRouteBlockReason() ?? 'Review the destination hub.');
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
        if (in_array($status, ['shipped', 'out_for_delivery'], true) && $delivery->picked_up_at) {
            $order->courierPickup?->update(['status' => 'picked_up']);
        }
        OrderStatusHistory::create(['order_id' => $order->id, 'from_status' => $from, 'to_status' => $status, 'note' => $note]);
        SellerNotification::create([
            'seller_id' => $delivery->seller_id, 'order_id' => $order->id,
            'type' => 'status', 'title' => $status === 'delivered' ? 'Order delivered' : ($status === 'shipped' ? 'Order shipped' : 'Parcel out for delivery'), 'body' => $note,
        ]);
    }

    public static function releaseRider(?int $riderId): void
    {
        if (! $riderId) {
            return;
        }
        $rider = Rider::whereKey($riderId)->lockForUpdate()->first();
        if ($rider && $rider->status !== 'inactive'
            && ! Delivery::where('rider_id', $riderId)->whereIn('status', Delivery::ACTIVE_STATUSES)->exists()) {
            // Online/offline is tracked by riders.is_online; the status enum
            // only accepts available/delivering/inactive.
            $eligible = $rider->isEligibilityApproved() && $rider->vehicle_verification === 'verified'
                && $rider->vehicleTypeIsActive() && ! $rider->licenseExpired();
            $rider->update(['status' => $eligible ? 'available' : 'inactive']);
        }
    }

    /**
     * Evidence that the parcel physically left the seller and reached the
     * hub: the pickup rider must have completed the pickup *and* recorded
     * the handoff. Approval, rider assignment, or the seller marking the
     * order ready are not custody transfers on their own.
     */
    public static function hasPickupHandoff(Delivery $delivery): bool
    {
        return $delivery->picked_up_at !== null
            && $delivery->sorting_center_handoff_at !== null
            && $delivery->sorting_center_handoff_rider_id !== null;
    }

    /** Order-linked parcels need recorded pickup+handoff before hub receipt. */
    public static function assertReceivable(Delivery $delivery): void
    {
        if (! $delivery->order_id) {
            return; // Standalone logistics shipments keep their direct-intake workflow.
        }
        abort_unless(
            self::hasPickupHandoff($delivery),
            409,
            'This parcel has not been collected from the seller. The pickup rider must complete the pickup and record the hub handoff before staff can receive it.'
        );
    }

    public static function receive(Delivery $delivery, int $actorId): void
    {
        self::assertActiveOrder($delivery, allowDeliveredOrder: true);
        self::assertReceivable($delivery);
        $riderId = $delivery->rider_id;
        $updates = ['parcel_status' => 'received', 'received_at' => now()];
        if (!$delivery->destination_center_id && ($destination = $delivery->suggestedDestination()['center_id'])) {
            $updates['destination_center_id'] = $destination;
        }
        if ($delivery->sorting_center_handoff_at) {
            $updates += ['rider_id' => null, 'status' => 'waiting_for_rider', 'assigned_at' => null, 'accepted_at' => null];
        }
        $delivery->update($updates);
        DeliveryStatusLog::create(['delivery_id' => $delivery->id, 'status' => 'received', 'changed_by' => $actorId, 'notes' => 'Center receipt confirmed; pickup custody closed.']);
        // Receipt proves the seller-to-hub leg is finished; never overwrite a
        // reviewed rejection or an already completed request.
        PickupRequest::where('delivery_id', $delivery->id)
            ->whereIn('status', ['pending', 'approved'])
            ->update(['status' => 'completed', 'reviewed_at' => now()]);
        // First hub receipt closes the first-mile leg and credits the pickup
        // rider once. Later destination receipts go through HubTransferService.
        \App\Support\EscrowEarnings::creditPickupRiderOnHubReceipt($delivery->fresh());
        self::releaseRider($riderId);
    }
}
