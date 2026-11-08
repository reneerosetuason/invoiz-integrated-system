<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\DeliveryStatusLog;
use App\Models\HubTransfer;
use App\Models\HubTransferEvent;
use App\Models\HubTransferParcel;
use App\Models\HubTransportDriver;
use App\Models\LogisticsCenter;
use App\Models\LogisticsTruck;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Support\EscrowEarnings;
use App\Support\LogisticsVehicleCategory;
use Illuminate\Support\Facades\DB;

/**
 * Sole writer for hub-transfer state transitions that also touch a parcel.
 *
 * Keeping dispatch/arrival/receipt here means a parcel can never be moved by
 * two competing code paths (web staff, courier API, delivery controller).
 */
class HubTransferService
{
    public static function hasActiveLeg(int $deliveryId, ?int $exceptTransferId = null): bool
    {
        return HubTransferParcel::query()
            ->where('delivery_id', $deliveryId)
            ->where('loading_status', '!=', 'removed')
            ->whereHas('transfer', function ($query) use ($exceptTransferId): void {
                $query->whereIn('status', HubTransfer::ACTIVE_STATUSES);
                if ($exceptTransferId !== null) {
                    $query->whereKeyNot($exceptTransferId);
                }
            })
            ->exists();
    }

    /** Lock and validate a transfer before any state change. */
    public static function lockTransfer(int $transferId): HubTransfer
    {
        return HubTransfer::query()->whereKey($transferId)->lockForUpdate()->firstOrFail();
    }

    public static function dispatch(HubTransfer $transfer, ?User $actor = null, ?HubTransportDriver $driver = null): HubTransfer
    {
        return DB::transaction(function () use ($transfer, $actor, $driver): HubTransfer {
            $locked = self::lockTransfer($transfer->id);
            if (in_array($locked->status, ['in_transit', 'arrived', 'receiving', 'exception', 'received'], true)) {
                return $locked;
            }

            abort_unless($locked->status === 'ready_for_dispatch', 409, 'All manifest parcels must be loaded before dispatch.');
            $manifest = $locked->parcels()->where('loading_status', '!=', 'removed')->with('delivery')->get();
            abort_if($manifest->isEmpty(), 422, 'This transfer has no parcels to dispatch.');
            abort_if($manifest->contains(fn ($entry) => $entry->loading_status !== 'loaded'), 409, 'Loading verification is incomplete.');
            // Each leg may only leave once the origin hub finished receiving,
            // scanning and sorting the parcel toward the next hub.
            abort_if(
                $manifest->contains(fn ($entry) => $entry->delivery?->parcel_status !== 'sorted'),
                409,
                'Every manifest parcel must be scanned and sorted at the origin hub before dispatch.'
            );

            $truck = LogisticsTruck::query()->whereKey($locked->truck_id)->lockForUpdate()->firstOrFail();
            $transportDriver = HubTransportDriver::query()->whereKey($locked->driver_id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $truck->logistics_company_id === (int) $locked->logistics_company_id
                && $truck->status === 'assigned'
                && LogisticsVehicleCategory::for($truck->vehicle_type) === 'four_wheel'
                && (int) $truck->parcel_capacity > 0
                && (float) $truck->capacity_kg > 0, 409, 'Assigned truck is unavailable or has no valid capacity.');
            abort_unless((int) $transportDriver->logistics_company_id === (int) $locked->logistics_company_id
                && $transportDriver->status === 'assigned'
                && $transportDriver->is_online
                && (int) $transportDriver->logistics_truck_id === (int) $truck->id, 409, 'Assigned Hub Transport Driver is unavailable.');
            abort_unless($transportDriver->isLicenseValid(), 409, 'Assigned Hub Transport Driver has an invalid or expired driver\'s license.');
            abort_if($manifest->count() > (int) $truck->parcel_capacity, 422, 'Truck parcel capacity is exceeded.');
            abort_if($manifest->contains(fn ($entry) => $entry->delivery?->weight === null), 422, 'Parcel weights must be recorded before dispatch.');
            abort_if($manifest->sum(fn ($entry) => (float) $entry->delivery->weight) > (float) $truck->capacity_kg, 422, 'Truck weight capacity is exceeded.');

            $locked->update([
                'status' => 'in_transit',
                'dispatched_by' => $actor?->id,
                'dispatched_at' => now(),
            ]);
            $truck->update(['status' => 'in_transit']);
            $transportDriver->update(['status' => 'in_transit']);

            foreach ($manifest as $entry) {
                if ($entry->delivery) {
                    $entry->delivery->update(['parcel_status' => 'dispatched']);
                    DeliveryStatusLog::create([
                        'delivery_id' => $entry->delivery_id,
                        'status' => 'hub_transfer_dispatched',
                        'location' => $locked->origin?->name,
                        'notes' => 'Dispatched from origin hub via transfer '.$locked->reference.'.',
                        'changed_by' => $actor?->id,
                    ]);

                    $order = $entry->delivery->order;
                    if ($order && $order->status !== 'cancelled') {
                        OrderStatusHistory::firstOrCreate(
                            [
                                'order_id' => $order->id,
                                'notes' => 'Parcel in transit between logistics hubs via manifest '.$locked->reference.'.',
                            ],
                            [
                                'from_status' => $order->status,
                                'to_status' => $order->status,
                            ]
                        );
                    }
                }
            }

            self::event($locked, 'dispatched', 'ready_for_dispatch', 'in_transit', 'Transfer dispatched.', $actor, null, $driver);

            return $locked;
        });
    }

    public static function arrive(HubTransfer $transfer, ?User $actor = null, ?HubTransportDriver $driver = null): HubTransfer
    {
        return DB::transaction(function () use ($transfer, $actor, $driver): HubTransfer {
            $locked = self::lockTransfer($transfer->id);
            if (in_array($locked->status, ['arrived', 'receiving', 'exception', 'received'], true)) {
                return $locked;
            }
            abort_unless($locked->status === 'in_transit', 409, 'Only an in-transit transfer can arrive.');

            $locked->update([
                'status' => 'arrived',
                'arrived_by' => $actor?->id,
                'arrived_at' => now(),
                'destination_arrived_at' => now(),
            ]);
            LogisticsTruck::whereKey($locked->truck_id)->update(['status' => 'assigned']);
            HubTransportDriver::whereKey($locked->driver_id)->update(['status' => 'assigned']);
            self::event($locked, 'arrived', 'in_transit', 'arrived', 'Truck arrival confirmed.', $actor, null, $driver);

            return $locked;
        });
    }

    /**
     * Receive one manifest parcel at the destination hub. This is the only
     * method that moves a parcel between hubs.
     *
     * @return array{transfer: HubTransfer, entry: HubTransferParcel, delivery: ?Delivery, duplicate: bool}
     */
    public static function receiveParcel(
        HubTransfer $transfer,
        int $deliveryId,
        string $condition,
        ?string $exceptionReason,
        ?User $actor = null,
    ): array {
        return DB::transaction(function () use ($transfer, $deliveryId, $condition, $exceptionReason, $actor): array {
            $locked = self::lockTransfer($transfer->id);
            if ($locked->status === 'received') {
                abort(409, 'This transfer has already been completed.');
            }
            abort_unless(in_array($locked->status, ['arrived', 'receiving', 'exception'], true), 409, 'Transfer is not ready for receiving.');

            $entry = HubTransferParcel::query()
                ->where('hub_transfer_id', $locked->id)
                ->where('delivery_id', $deliveryId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($entry->receiving_status !== 'pending') {
                return [
                    'transfer' => $locked,
                    'entry' => $entry,
                    'delivery' => Delivery::find($deliveryId),
                    'duplicate' => true,
                ];
            }

            if (in_array($condition, ['missing', 'damaged'], true)) {
                abort_if(trim((string) $exceptionReason) === '', 422, 'A reason is required for missing or damaged parcels.');
            }

            $received = $condition !== 'missing';
            $entry->update([
                'receiving_status' => $received ? 'received' : 'missing',
                'condition' => $condition,
                'exception_reason' => $exceptionReason,
                'received_by' => $actor?->id,
                'received_at' => $received ? now() : null,
            ]);

            $delivery = Delivery::query()->whereKey($entry->delivery_id)->lockForUpdate()->firstOrFail();
            if ($received) {
                $delivery->update([
                    'center_id' => $locked->destination_center_id,
                    'status' => $condition === 'damaged' ? 'damaged' : $delivery->status,
                    'parcel_status' => 'received',
                    'received_at' => now(),
                    'return_reason' => $condition === 'damaged' ? ($exceptionReason ? 'Damaged in transit: '.$exceptionReason : 'Damaged in transit') : $delivery->return_reason,
                    'scanned_at' => null,
                    'sorted_at' => null,
                    'dispatched_at' => null,
                ]);

                DeliveryStatusLog::create([
                    'delivery_id' => $delivery->id,
                    'status' => $condition === 'damaged' ? 'damaged' : 'received',
                    'location' => $locked->destination?->name,
                    'notes' => 'Received at destination via hub transfer '.$locked->reference
                        .($condition === 'damaged' ? '; reported damaged: '.$exceptionReason : ''),
                    'changed_by' => $actor?->id,
                ]);

                if ($delivery->order && $delivery->order->status !== 'cancelled') {
                    $note = $condition === 'damaged'
                        ? 'Logistics Alert: Parcel received with damage at destination facility ('.$locked->destination?->name.'). Inspection in progress.'
                        : 'Parcel arrived at destination facility ('.$locked->destination?->name.').';
                    OrderStatusHistory::create([
                        'order_id' => $delivery->order->id,
                        'from_status' => $delivery->order->status,
                        'to_status' => $delivery->order->status,
                        'note' => $note,
                    ]);
                }

                // Inter-hub transport compensation is trip-based, credited upon complete() below.
            } else {
                $delivery->update([
                    'status' => 'lost',
                    'return_reason' => $exceptionReason ? 'Missing in transit: '.$exceptionReason : 'Reported missing during transfer',
                ]);

                DeliveryStatusLog::create([
                    'delivery_id' => $delivery->id,
                    'status' => 'lost',
                    'location' => $locked->destination?->name,
                    'notes' => 'Parcel reported missing during hub transfer '.$locked->reference.': '.$exceptionReason,
                    'changed_by' => $actor?->id,
                ]);

                if ($delivery->order && $delivery->order->status !== 'cancelled') {
                    OrderStatusHistory::create([
                        'order_id' => $delivery->order->id,
                        'from_status' => $delivery->order->status,
                        'to_status' => $delivery->order->status,
                        'note' => 'Logistics Alert: Parcel reported missing during hub transfer '.$locked->reference.'. Investigation in progress.',
                    ]);
                }
            }

            self::event(
                $locked,
                $received ? 'parcel_received' : 'parcel_missing',
                null,
                null,
                $exceptionReason ?? ('Condition: '.$condition),
                $actor,
                $entry->delivery_id,
            );

            $hasException = $locked->parcels()
                ->where(fn ($query) => $query->where('receiving_status', 'missing')->orWhere('condition', 'damaged'))
                ->exists();
            if ($hasException) {
                $locked->update(['status' => 'exception']);
            } elseif ($locked->parcels()->where('receiving_status', 'pending')->exists()) {
                $locked->update(['status' => 'receiving']);
            }

            return [
                'transfer' => $locked,
                'entry' => $entry->fresh(),
                'delivery' => $delivery?->fresh(),
                'duplicate' => false,
            ];
        });
    }

    public static function complete(HubTransfer $transfer, ?User $actor = null): HubTransfer
    {
        return DB::transaction(function () use ($transfer, $actor): HubTransfer {
            $locked = self::lockTransfer($transfer->id);
            if ($locked->status === 'received') {
                return $locked;
            }
            abort_unless(in_array($locked->status, ['arrived', 'receiving', 'exception'], true), 409, 'Transfer cannot be completed from its current state.');
            abort_if($locked->parcels()->where('receiving_status', 'pending')->exists(), 409, 'Every parcel must be received or recorded as missing before completion.');

            $previous = $locked->status;
            $locked->update([
                'status' => 'received',
                'received_by' => $actor?->id,
                'received_at' => now(),
            ]);
            LogisticsTruck::whereKey($locked->truck_id)->update(['status' => 'available']);
            HubTransportDriver::whereKey($locked->driver_id)->update(['status' => 'available']);
            self::event($locked, 'completed', $previous, 'received', 'Receiving was finalized.', $actor);

            // Credit the Hub Transport Driver once per completed transfer trip
            EscrowEarnings::creditInterHubDriverForCompletedTrip($locked);
            // Bind every order's remittance to this completed trip before any
            // release/acceptance can confirm its flat trip compensation.
            foreach ($locked->parcels()->where('loading_status', '!=', 'removed')->with('delivery.order')->get() as $entry) {
                if ($entry->delivery?->order) {
                    EscrowEarnings::syncRemittancesForOrder($entry->delivery->order);
                }
            }

            return $locked;
        });
    }

    /**
     * Cancel a transfer that has not left the origin hub. Parcels stay where
     * they are; only the manifest entry and the fleet reservation are undone.
     */
    public static function cancel(HubTransfer $transfer, User $actor, string $reason): HubTransfer
    {
        return DB::transaction(function () use ($transfer, $actor, $reason): HubTransfer {
            $locked = self::lockTransfer($transfer->id);
            if ($locked->status === 'cancelled') {
                return $locked;
            }
            abort_unless(in_array($locked->status, ['loading', 'ready_for_dispatch'], true), 409, 'A transfer can only be cancelled before dispatch.');

            $locked->parcels()
                ->whereNotIn('loading_status', ['removed'])
                ->update(['loading_status' => 'removed', 'receiving_status' => 'removed']);
            $previous = $locked->status;
            $locked->update(['status' => 'cancelled']);
            LogisticsTruck::whereKey($locked->truck_id)->update(['status' => 'available']);
            HubTransportDriver::whereKey($locked->driver_id)->update(['status' => 'available']);
            self::event($locked, 'cancelled', $previous, 'cancelled', 'Cancelled before dispatch: '.$reason, $actor);

            return $locked;
        });
    }

    /** Pause an active transfer; no parcel movement is allowed while held. */
    public static function hold(HubTransfer $transfer, User $actor, string $reason): HubTransfer
    {
        return DB::transaction(function () use ($transfer, $actor, $reason): HubTransfer {
            $locked = self::lockTransfer($transfer->id);
            if ($locked->status === 'on_hold') {
                return $locked;
            }
            abort_unless(in_array($locked->status, HubTransfer::ACTIVE_STATUSES, true), 409, 'Only an active transfer can be held.');
            $previous = $locked->status;
            $locked->update(['status' => 'on_hold']);
            self::event($locked, 'held', $previous, 'on_hold', 'Placed on hold: '.$reason, $actor);

            return $locked;
        });
    }

    /** Resume a held transfer to the stage it was paused at. */
    public static function resume(HubTransfer $transfer, User $actor): HubTransfer
    {
        return DB::transaction(function () use ($transfer, $actor): HubTransfer {
            $locked = self::lockTransfer($transfer->id);
            abort_unless($locked->status === 'on_hold', 409, 'Only a held transfer can be resumed.');
            $held = $locked->events()->where('event_type', 'held')->latest('id')->first();
            $previous = $held?->previous_status;
            abort_unless($previous && in_array($previous, HubTransfer::ACTIVE_STATUSES, true), 409, 'The previous transfer stage could not be restored.');
            $locked->update(['status' => $previous]);
            self::event($locked, 'resumed', 'on_hold', $previous, 'Resumed after hold.', $actor);

            return $locked;
        });
    }

    /** Replace the truck and driver before departure. */
    public static function reassignFleet(
        HubTransfer $transfer,
        LogisticsTruck $truck,
        HubTransportDriver $driver,
        User $actor,
    ): HubTransfer {
        return DB::transaction(function () use ($transfer, $truck, $driver, $actor): HubTransfer {
            $locked = self::lockTransfer($transfer->id);
            abort_unless(in_array($locked->status, ['loading', 'ready_for_dispatch'], true), 409, 'Fleet can only be reassigned before dispatch.');

            $newTruck = LogisticsTruck::query()->whereKey($truck->id)->lockForUpdate()->firstOrFail();
            $newDriver = HubTransportDriver::query()->whereKey($driver->id)->lockForUpdate()->firstOrFail();
            $oldTruckId = $locked->truck_id;
            $oldDriverId = $locked->driver_id;

            abort_unless((int) $newTruck->logistics_company_id === (int) $locked->logistics_company_id
                && in_array($newTruck->status, [LogisticsTruck::STATUS_AVAILABLE, LogisticsTruck::STATUS_ASSIGNED], true), 422, 'Truck is not available to this company.');
            abort_unless((int) $newDriver->logistics_company_id === (int) $locked->logistics_company_id
                && ($newDriver->status === 'available' || (int) $newDriver->id === (int) $oldDriverId), 422, 'Driver is not available to this company.');
            abort_unless($newTruck->isAssignable(), 422, 'This vehicle is not assignable to hub transfers.');
            abort_if($newTruck->hasActiveTransfer($locked->id), 409, 'This truck is already assigned to another active hub transfer.');
            abort_unless($newDriver->isLicenseValid(), 422, 'Assigned driver does not have an approved, unexpired driver\'s license.');
            abort_unless(LogisticsVehicleCategory::for($newTruck->vehicle_type) === 'four_wheel', 422, 'Hub transfers require an approved four-wheel vehicle.');
            abort_unless((int) $newTruck->parcel_capacity > 0 && (float) $newTruck->capacity_kg > 0, 422, 'Truck parcel and weight capacities must be registered.');
            abort_unless((int) $newDriver->logistics_truck_id === (int) $newTruck->id, 422, 'Driver is not assigned to the selected truck.');
            abort_if(
                HubTransfer::query()
                    ->where('driver_id', $newDriver->id)
                    ->whereIn('status', HubTransfer::ACTIVE_STATUSES)
                    ->whereKeyNot($locked->id)
                    ->exists(),
                409,
                'This Hub Transport Driver is already assigned to another active hub transfer.'
            );

            $manifest = $locked->parcels()->where('loading_status', '!=', 'removed')->with('delivery')->get();
            abort_if($manifest->count() > (int) $newTruck->parcel_capacity, 422, 'The selected truck does not have enough parcel capacity.');
            abort_if($manifest->sum(fn ($entry) => (float) ($entry->delivery?->weight ?? 0)) > (float) $newTruck->capacity_kg, 422, 'The selected truck does not have enough weight capacity.');

            if ((int) $oldTruckId !== (int) $newTruck->id) {
                LogisticsTruck::whereKey($oldTruckId)->update(['status' => 'available']);
                self::event($locked, 'truck_reassigned', null, null, 'Truck changed from '.($locked->truck?->plate_number ?? '—').' to '.$newTruck->plate_number, $actor);
            }
            if ((int) $oldDriverId !== (int) $newDriver->id) {
                HubTransportDriver::whereKey($oldDriverId)->update(['status' => 'available']);
                self::event($locked, 'driver_reassigned', null, null, 'Driver changed from '.($locked->driver?->name ?? '—').' to '.$newDriver->name, $actor);
            }

            $newTruck->update(['status' => 'assigned']);
            $newDriver->update(['status' => 'assigned']);
            $locked->update(['truck_id' => $newTruck->id, 'driver_id' => $newDriver->id]);

            return $locked;
        });
    }

    /**
     * Correct the transfer's next-hop hub before the truck leaves the origin.
     *
     * The shared network is cross-company: any active hub can be a next hop,
     * even when it belongs to another logistics company. The manifest parcels
     * move with the corrected leg, and the previous/new hub, reason, actor and
     * timestamp are recorded on the immutable transfer event trail. Custody is
     * still at the origin, so nothing has to be reversed; once receiving has
     * started, correcting the route requires a new transfer instead.
     */
    public static function reroute(HubTransfer $transfer, LogisticsCenter $destination, User $actor, string $reason): HubTransfer
    {
        return DB::transaction(function () use ($transfer, $destination, $actor, $reason): HubTransfer {
            $locked = self::lockTransfer($transfer->id);
            abort_unless(in_array($locked->status, ['loading', 'ready_for_dispatch'], true), 409, 'The destination can only be corrected before dispatch.');
            abort_unless(LogisticsCenter::query()->whereKey($destination->id)->where('is_active', true)->exists(), 422, 'The destination hub must be active.');
            abort_if((int) $destination->id === (int) $locked->origin_center_id, 422, 'Destination must differ from the origin hub.');
            abort_if((int) $destination->id === (int) $locked->destination_center_id, 422, 'This is already the destination hub.');

            $deliveryIds = $locked->parcels()
                ->where('loading_status', '!=', 'removed')
                ->pluck('delivery_id');

            // A parcel must never be sent back through a hub it already
            // visited on an earlier completed leg.
            $alreadyVisited = DB::table('hub_transfer_parcels as htp')
                ->join('hub_transfers as ht', 'ht.id', '=', 'htp.hub_transfer_id')
                ->whereIn('htp.delivery_id', $deliveryIds)
                ->where('htp.loading_status', '!=', 'removed')
                ->where('htp.receiving_status', 'received')
                ->where('ht.id', '!=', $locked->id)
                ->where('ht.status', '!=', 'cancelled')
                ->where(function ($leg) use ($destination): void {
                    $leg->where('ht.origin_center_id', $destination->id)
                        ->orWhere('ht.destination_center_id', $destination->id);
                })
                ->exists();
            abort_if($alreadyVisited, 422, 'Every parcel on this manifest has already passed through the selected hub. Choose a hub they have not visited.');

            $previousId = (int) $locked->destination_center_id;
            $previous = $locked->destination?->name ?? ('Hub #'.$previousId);

            Delivery::query()->whereIn('id', $deliveryIds)->update(['destination_center_id' => $destination->id]);
            $locked->update(['destination_center_id' => $destination->id]);
            self::event(
                $locked,
                'rerouted',
                null,
                null,
                'Next-hop hub corrected from '.$previous.' (Hub #'.$previousId.') to '.$destination->name.' (Hub #'.$destination->id.'). Reason: '.$reason,
                $actor,
            );

            return $locked;
        });
    }

    public static function event(
        HubTransfer $transfer,
        string $type,
        ?string $previous,
        ?string $next,
        string $notes,
        ?User $actor = null,
        ?int $deliveryId = null,
        ?HubTransportDriver $driver = null,
    ): HubTransferEvent {
        return HubTransferEvent::create([
            'hub_transfer_id' => $transfer->id,
            'delivery_id' => $deliveryId,
            'event_type' => $type,
            'previous_status' => $previous,
            'new_status' => $next,
            'notes' => $notes,
            'actor_id' => $actor?->id,
            'hub_transport_driver_id' => $driver?->id,
            'actor_name' => $driver?->name ?? $actor?->name,
        ]);
    }
}
