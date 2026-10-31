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
                    DeliveryStatusLog::create([
                        'delivery_id' => $entry->delivery_id,
                        'status' => 'hub_transfer_dispatched',
                        'location' => $locked->origin?->name,
                        'notes' => 'Dispatched from origin hub via transfer '.$locked->reference.'.',
                        'changed_by' => $actor?->id,
                    ]);
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

            $delivery = null;
            if ($received) {
                $delivery = Delivery::query()->whereKey($entry->delivery_id)->lockForUpdate()->firstOrFail();
                $delivery->update([
                    'center_id' => $locked->destination_center_id,
                    'parcel_status' => 'received',
                    'received_at' => now(),
                    'scanned_at' => null,
                    'sorted_at' => null,
                    'dispatched_at' => null,
                ]);

                DeliveryStatusLog::create([
                    'delivery_id' => $delivery->id,
                    'status' => 'received',
                    'location' => $locked->destination?->name,
                    'notes' => 'Received at destination via hub transfer '.$locked->reference
                        .($condition === 'damaged' ? '; reported damaged: '.$exceptionReason : ''),
                    'changed_by' => $actor?->id,
                ]);

                // One credit per seller shipment for the entire route.
                EscrowEarnings::creditInterHubDriverOnTransferReceipt($locked, $delivery);
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
                && ($newTruck->status === 'available' || (int) $newTruck->id === (int) $oldTruckId), 422, 'Truck is not available to this company.');
            abort_unless((int) $newDriver->logistics_company_id === (int) $locked->logistics_company_id
                && ($newDriver->status === 'available' || (int) $newDriver->id === (int) $oldDriverId), 422, 'Driver is not available to this company.');
            abort_unless(LogisticsVehicleCategory::for($newTruck->vehicle_type) === 'four_wheel', 422, 'Hub transfers require an approved four-wheel vehicle.');
            abort_unless((int) $newTruck->parcel_capacity > 0 && (float) $newTruck->capacity_kg > 0, 422, 'Truck parcel and weight capacities must be registered.');
            abort_unless((int) $newDriver->logistics_truck_id === (int) $newTruck->id, 422, 'Driver is not assigned to the selected truck.');

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

    /** Correct the destination hub before the truck leaves the origin. */
    public static function reroute(HubTransfer $transfer, LogisticsCenter $destination, User $actor, string $reason): HubTransfer
    {
        return DB::transaction(function () use ($transfer, $destination, $actor, $reason): HubTransfer {
            $locked = self::lockTransfer($transfer->id);
            abort_unless(in_array($locked->status, ['loading', 'ready_for_dispatch'], true), 409, 'The destination can only be corrected before dispatch.');
            abort_unless((int) $destination->logistics_company_id === (int) $locked->logistics_company_id, 422, 'The destination hub must belong to this logistics company.');
            abort_if((int) $destination->id === (int) $locked->origin_center_id, 422, 'Destination must differ from the origin hub.');
            abort_if((int) $destination->id === (int) $locked->destination_center_id, 422, 'This is already the destination hub.');

            $previous = $locked->destination?->name ?? '—';
            Delivery::query()
                ->whereIn('id', $locked->parcels()->where('loading_status', '!=', 'removed')->select('delivery_id'))
                ->update(['destination_center_id' => $destination->id]);
            $locked->update(['destination_center_id' => $destination->id]);
            self::event($locked, 'rerouted', null, null, 'Destination corrected from '.$previous.' to '.$destination->name.': '.$reason, $actor);

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
