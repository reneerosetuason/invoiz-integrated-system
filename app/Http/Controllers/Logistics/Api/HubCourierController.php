<?php

namespace App\Http\Controllers\Logistics\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\HubTransfer;
use App\Models\HubTransferEvent;
use App\Models\HubTransferParcel;
use App\Models\HubTransportDriver;
use App\Models\LogisticsTruck;
use App\Support\LogisticsVehicleCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class HubCourierController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $driver = HubTransportDriver::whereRaw('LOWER(email) = ?', [strtolower($data['email'])])->first();
        abort_unless($driver && $driver->password && Hash::check($data['password'], $driver->password), 401, 'Invalid courier credentials.');
        abort_unless($driver->status !== 'inactive' && $driver->company?->status === 'active', 403, 'Hub transport driver account is not active.');
        abort_unless($this->validVehicle($driver), 403, 'An active, approved four-wheel vehicle with registered capacity must be assigned before sign-in.');
        $driver->tokens()->delete();
        $token = $driver->createToken('hub-courier-mobile', ['hub-courier'])->plainTextToken;

        return response()->json(['token' => $token, 'courier' => $this->profilePayload($driver->fresh(['company', 'truck']))]);
    }

    public function profile(Request $request): JsonResponse
    {
        $driver = $this->driver($request)->load(['company', 'truck']);

        return response()->json(['courier' => $this->profilePayload($driver)]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $driver = $this->driver($request);
        $transfers = HubTransfer::with(['origin', 'destination', 'truck', 'parcels.delivery'])
            ->where('driver_id', $driver->id)->where('logistics_company_id', $driver->logistics_company_id)
            ->latest()->limit(50)->get();

        return response()->json([
            'courier' => $this->profilePayload($driver->fresh(['company', 'truck'])),
            'active_transfer' => $this->transferPayload($transfers->first(fn ($t) => in_array($t->status, HubTransfer::ACTIVE_STATUSES, true))),
            'available_transfers' => $transfers->filter(fn ($t) => ! in_array($t->status, ['received', 'cancelled'], true))->map(fn ($t) => $this->transferPayload($t))->values(),
            'completed_transfers' => $transfers->where('status', 'received')->take(10)->map(fn ($t) => $this->transferPayload($t))->values(),
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => 'required|in:online,offline']);
        $driver = $this->driver($request)->load('truck');
        if ($data['status'] === 'online') {
            abort_unless($this->validVehicle($driver), 422, 'Your assigned vehicle is unavailable or missing capacity.');
        }
        $driver->update(['is_online' => $data['status'] === 'online']);

        return response()->json(['status' => $driver->is_online ? 'online' : 'offline', 'courier' => $this->profilePayload($driver->fresh(['company', 'truck']))]);
    }

    public function show(Request $request, int $transfer): JsonResponse
    {
        return response()->json(['transfer' => $this->transferPayload($this->assignedTransfer($request, $transfer))]);
    }

    public function originArrival(Request $request, int $transfer): JsonResponse
    {
        $item = $this->assignedTransfer($request, $transfer);
        DB::transaction(function () use ($request, $item): void {
            $locked = HubTransfer::whereKey($item->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'loading' && $locked->origin_arrived_at === null, 409, 'Origin arrival is not available in the current state.');
            $locked->update(['origin_arrived_at' => now()]);
            $this->event($locked, $request, 'courier_origin_arrived', 'Courier arrived at origin hub.');
        });

        return $this->show($request, $transfer);
    }

    public function loadParcel(Request $request, int $transfer): JsonResponse
    {
        $data = $request->validate(['tracking_number' => 'required|string|max:100']);
        $item = $this->assignedTransfer($request, $transfer);
        DB::transaction(function () use ($request, $item, $data): void {
            $locked = HubTransfer::whereKey($item->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'loading' && $locked->origin_arrived_at !== null, 409, 'Arrive at the origin hub before scanning parcels.');
            $entry = HubTransferParcel::where('hub_transfer_id', $locked->id)->whereHas('delivery', fn ($q) => $q->where('tracking_number', $data['tracking_number']))->lockForUpdate()->firstOrFail();
            abort_if($entry->loading_status !== 'pending', 422, 'This parcel is already loaded or is not part of the active manifest.');
            $delivery = $entry->delivery;
            abort_unless($delivery !== null, 422, 'This parcel is no longer available.');
            abort_unless((int) $delivery->center_id === (int) $locked->origin_center_id, 422, 'This parcel is not at the origin hub.');
            abort_if(in_array($delivery->status, ['delivered', 'cancelled'], true) || $delivery->archived_at !== null, 422, 'This parcel cannot be loaded.');
            abort_if($delivery->rider_id !== null || in_array($delivery->status, Delivery::ACTIVE_STATUSES, true), 422, 'This parcel is assigned to last-mile delivery.');
            abort_if($delivery->weight === null, 422, 'Record this parcel weight before loading.');
            $truck = LogisticsTruck::whereKey($locked->truck_id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $truck->logistics_company_id === (int) $request->user()->logistics_company_id, 409, 'The assigned vehicle belongs to a different company.');
            $loaded = $locked->parcels()->where('loading_status', 'loaded')->with('delivery')->get();
            abort_if($loaded->count() + 1 > (int) $truck->parcel_capacity, 422, 'Loading this parcel would exceed the vehicle parcel capacity.');
            $loadedWeight = $loaded->sum(fn ($p) => (float) ($p->delivery?->weight ?? 0));
            abort_if($loadedWeight + (float) $delivery->weight > (float) $truck->capacity_kg, 422, 'Loading this parcel would exceed the vehicle weight capacity.');
            $entry->update(['loading_status' => 'loaded', 'loaded_at' => now(), 'loaded_by_hub_driver_id' => $this->driver($request)->id]);
            $entry->load('delivery');
            $this->event($locked, $request, 'courier_parcel_loaded', 'Parcel loaded: '.$data['tracking_number'], $entry->delivery_id);
        });

        return $this->show($request, $transfer);
    }

    /**
     * Explicitly finalize loading only when every manifest parcel is aboard
     * and the vehicle capacity still covers the manifest.
     */
    public function completeLoading(Request $request, int $transfer): JsonResponse
    {
        $item = $this->assignedTransfer($request, $transfer);
        DB::transaction(function () use ($request, $item): void {
            $locked = HubTransfer::whereKey($item->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($locked->status, ['loading', 'ready_for_dispatch'], true), 409, 'Loading cannot be completed in the current state.');
            abort_unless($locked->origin_arrived_at !== null, 409, 'Confirm arrival at the origin hub before completing loading.');
            $manifest = $locked->parcels()->where('loading_status', '!=', 'removed')->with('delivery')->get();
            abort_if($manifest->isEmpty(), 422, 'This transfer has no parcels to load.');
            abort_if($manifest->contains(fn ($entry) => $entry->loading_status !== 'loaded'), 409, 'Scan every manifest parcel before completing loading.');
            $truck = LogisticsTruck::whereKey($locked->truck_id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $truck->logistics_company_id === (int) $request->user()->logistics_company_id, 409, 'The assigned vehicle belongs to a different company.');
            abort_if($manifest->count() > (int) $truck->parcel_capacity, 422, 'The vehicle parcel capacity is exceeded.');
            abort_if($manifest->contains(fn ($entry) => $entry->delivery?->weight === null), 422, 'Parcel weights must be recorded before loading can be completed.');
            abort_if($manifest->sum(fn ($entry) => (float) $entry->delivery->weight) > (float) $truck->capacity_kg, 422, 'The vehicle weight capacity is exceeded.');
            if ($locked->status !== 'ready_for_dispatch') {
                $locked->update(['status' => 'ready_for_dispatch']);
                $this->event($locked, $request, 'courier_loading_completed', 'Loading completed for '.$manifest->count().' parcels.', null, 'loading', 'ready_for_dispatch');
            }
        });

        return $this->show($request, $transfer);
    }

    public function dispatch(Request $request, int $transfer): JsonResponse
    {
        $item = $this->assignedTransfer($request, $transfer);
        DB::transaction(function () use ($request, $item): void {
            $locked = HubTransfer::whereKey($item->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->origin_arrived_at !== null, 409, 'Confirm arrival at the origin hub before dispatch.');
            $driver = HubTransportDriver::whereKey($this->driver($request)->id)->lockForUpdate()->firstOrFail();
            $truck = LogisticsTruck::whereKey($locked->truck_id)->lockForUpdate()->firstOrFail();
            abort_unless($driver->is_online && $driver->status === 'assigned' && (int) $driver->logistics_truck_id === (int) $truck->id
                && $truck->status === 'assigned' && $this->validVehicle($driver), 409, 'Assigned courier or vehicle is unavailable.');
            \App\Services\HubTransferService::dispatch($locked, null, $driver);
        });

        return $this->show($request, $transfer);
    }

    public function destinationArrival(Request $request, int $transfer): JsonResponse
    {
        $item = $this->assignedTransfer($request, $transfer);
        DB::transaction(function () use ($request, $item): void {
            \App\Services\HubTransferService::arrive($item, null, $this->driver($request));
        });

        return $this->show($request, $transfer);
    }

    private function driver(Request $request): HubTransportDriver
    {
        $driver = $request->user();
        abort_unless($driver instanceof HubTransportDriver && $request->user()->currentAccessToken()?->can('hub-courier'), 403, 'Hub Transport Driver access is required.');
        abort_unless($driver->status !== 'inactive' && $driver->company?->status === 'active', 403, 'Hub transport driver account is not active.');

        return $driver;
    }

    private function assignedTransfer(Request $request, int $id): HubTransfer
    {
        $driver = $this->driver($request);
        $transfer = HubTransfer::with(['origin', 'destination', 'truck', 'parcels.delivery', 'events'])
            ->whereKey($id)->where('driver_id', $driver->id)->where('logistics_company_id', $driver->logistics_company_id)->firstOrFail();
        abort_unless($this->validVehicle($driver), 403, 'Your assigned four-wheel vehicle is unavailable or missing registered capacity.');
        abort_unless((int) $transfer->truck_id === (int) $driver->logistics_truck_id, 403, 'This transfer is assigned to a different vehicle.');

        return $transfer;
    }

    private function validVehicle(HubTransportDriver $driver): bool
    {
        $truck = $driver->truck;

        return $truck !== null
            && (int) $truck->logistics_company_id === (int) $driver->logistics_company_id
            && LogisticsVehicleCategory::for($truck->vehicle_type) === 'four_wheel'
            && (int) $truck->parcel_capacity > 0
            && (float) $truck->capacity_kg > 0
            && ! in_array($truck->status, ['inactive', 'maintenance'], true);
    }

    private function profilePayload(HubTransportDriver $driver): array
    {
        $truck = $driver->truck;

        return ['id' => $driver->id, 'name' => $driver->name, 'email' => $driver->email, 'phone' => $driver->phone,
            'company' => $driver->company?->name, 'status' => $driver->status, 'is_online' => $driver->is_online,
            'vehicle' => $truck ? ['name' => $truck->name, 'type' => $truck->vehicle_type, 'plate_number' => $truck->plate_number,
                'parcel_capacity' => (int) $truck->parcel_capacity, 'capacity_kg' => (float) $truck->capacity_kg, 'status' => $truck->status] : null];
    }

    private function transferPayload(?HubTransfer $transfer): ?array
    {
        if (! $transfer) {
            return null;
        }
        $parcels = $transfer->parcels->where('loading_status', '!=', 'removed');

        return ['id' => $transfer->id, 'reference' => $transfer->reference, 'status' => $transfer->status, 'notes' => $transfer->notes,
            'planned_dispatch_at' => $transfer->planned_dispatch_at?->toIso8601String(), 'origin_arrived_at' => $transfer->origin_arrived_at?->toIso8601String(),
            'destination_arrived_at' => $transfer->destination_arrived_at?->toIso8601String(), 'company' => $transfer->company?->name,
            'origin' => $transfer->origin ? ['id' => $transfer->origin->id, 'name' => $transfer->origin->name, 'address' => $transfer->origin->address] : null,
            'destination' => $transfer->destination ? ['id' => $transfer->destination->id, 'name' => $transfer->destination->name, 'address' => $transfer->destination->address] : null,
            'vehicle' => $transfer->truck ? ['type' => $transfer->truck->vehicle_type, 'plate_number' => $transfer->truck->plate_number,
                'parcel_capacity' => (int) $transfer->truck->parcel_capacity, 'capacity_kg' => (float) $transfer->truck->capacity_kg] : null,
            'parcel_count' => $parcels->count(), 'loaded_count' => $parcels->where('loading_status', 'loaded')->count(),
            'total_weight_kg' => $parcels->every(fn ($p) => $p->delivery?->weight !== null) ? $parcels->sum(fn ($p) => (float) $p->delivery->weight) : null,
            'parcels' => $parcels->map(fn ($p) => ['tracking_number' => $p->delivery?->tracking_number, 'loading_status' => $p->loading_status,
                'receiving_status' => $p->receiving_status, 'condition' => $p->condition, 'exception_reason' => $p->exception_reason])->values(),
            'events' => $transfer->events->map(fn ($e) => ['type' => $e->event_type, 'notes' => $e->notes, 'at' => $e->created_at?->toIso8601String()])->values()];
    }

    private function event(HubTransfer $transfer, Request $request, string $type, string $notes, ?int $deliveryId = null, ?string $previous = null, ?string $next = null): void
    {
        HubTransferEvent::create(['hub_transfer_id' => $transfer->id, 'delivery_id' => $deliveryId, 'event_type' => $type,
            'previous_status' => $previous, 'new_status' => $next, 'notes' => $notes, 'hub_transport_driver_id' => $this->driver($request)->id,
            'actor_name' => $this->driver($request)->name]);
    }
}
