<?php

namespace App\Http\Controllers\Logistics\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\HubTransfer;
use App\Models\HubTransferEvent;
use App\Models\HubTransferParcel;
use App\Models\HubTransportDriver;
use App\Models\LogisticsCenter;
use App\Models\LogisticsTruck;
use App\Support\HubTransferProgress;
use App\Support\LogisticsVehicleCategory;
use Illuminate\Database\Eloquent\Builder;
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
        $driver->tokens()->delete();
        $token = $driver->createToken('hub-courier-mobile', ['hub-courier'])->plainTextToken;

        return response()->json(['token' => $token, 'courier' => $this->profilePayload($driver->fresh(['company', 'truck', 'ownedTruck']))]);
    }

    public function profile(Request $request): JsonResponse
    {
        $driver = $this->driver($request)->load(['company', 'truck', 'ownedTruck']);

        return response()->json(['courier' => $this->profilePayload($driver)]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $driver = $this->driver($request);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:160',
            'phone' => 'sometimes|nullable|string|max:30',
        ]);

        $driver->update($validated);

        if (array_key_exists('phone', $validated) && $driver->user) {
            $driver->user->update(['phone' => $validated['phone']]);
        }

        return response()->json([
            'message' => 'Profile updated successfully.',
            'courier' => $this->profilePayload($driver->fresh(['company', 'truck', 'ownedTruck'])),
        ]);
    }

    public function updatePhoto(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        $driver = $this->driver($request);
        $stored = $request->file('photo')->store('avatars', 'public');

        if ($driver->avatar && \Illuminate\Support\Facades\Storage::disk('public')->exists($driver->avatar)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($driver->avatar);
        }

        $driver->update(['avatar' => $stored]);

        if ($driver->user) {
            $driver->user->update(['profile_picture' => $stored]);
        }

        return response()->json([
            'message' => 'Profile photo updated successfully.',
            'courier' => $this->profilePayload($driver->fresh(['company', 'truck', 'ownedTruck'])),
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $driver = $this->driver($request)->load(['company', 'truck', 'ownedTruck']);
        $transfers = $this->driverTransfers($driver)
            ->with(['origin', 'destination', 'truck', 'company', 'parcels.delivery', 'events'])
            ->latest()->limit(50)->get();

        // Completed trips are counted independently of the dashboard's display
        // window. Deriving the count from the latest 50 rows under-reported
        // trips once a driver had more recent assignments.
        $completedTrips = $this->driverTransfers($driver)
            ->where('status', 'received')
            ->orderByDesc('received_at')
            ->orderByDesc('id');

        $completedTransfers = (clone $completedTrips)
            ->with(['origin', 'destination', 'truck', 'company', 'parcels.delivery', 'events'])
            ->limit(10)
            ->get();

        $earnings = \App\Models\HubTransferEarning::acceptedRemittances()->where('hub_transport_driver_id', $driver->id)
            ->whereIn('recipient_role', ['driver', \App\Models\HubTransferEarning::ROLE_INTER_HUB_DRIVER])
            ->get();

        $recentIncidents = \App\Models\VehicleIncident::where('hub_transport_driver_id', $driver->id)
            ->latest('incident_date')
            ->take(5)
            ->get();

        return response()->json([
            'courier' => $this->profilePayload($driver),
            'active_transfer' => $this->transferPayload($transfers->first(fn ($t) => in_array($t->status, HubTransfer::ACTIVE_STATUSES, true))),
            'available_transfers' => $transfers->filter(fn ($t) => ! in_array($t->status, ['received', 'cancelled'], true))->map(fn ($t) => $this->transferPayload($t))->values(),
            'completed_transfers' => $completedTransfers->map(fn ($t) => $this->transferPayload($t))->values(),
            'earnings_summary' => [
                'total_earnings' => (float) $earnings->sum('amount'),
                'base_earnings' => (float) $earnings->sum('base_amount'),
                'vehicle_allowance' => (float) $earnings->sum('vehicle_allowance'),
                'completed_trips_count' => (clone $completedTrips)->count(),
            ],
            'recent_incidents' => $recentIncidents->map(fn ($inc) => [
                'id' => $inc->id,
                'reference' => $inc->reference,
                'incident_type' => $inc->incident_type,
                'severity' => $inc->severity,
                'status' => $inc->status,
                'incident_date' => $inc->incident_date?->toIso8601String(),
                'description' => $inc->description,
            ]),
        ]);
    }

    public function earnings(Request $request): JsonResponse
    {
        $driver = $this->driver($request);
        $roles = ['driver', \App\Models\HubTransferEarning::ROLE_INTER_HUB_DRIVER];
        $earnings = \App\Models\HubTransferEarning::acceptedRemittances()->with('transfer')
            ->where('hub_transport_driver_id', $driver->id)
            ->whereIn('recipient_role', $roles)
            ->latest()
            ->paginate(20);

        $total = (float) \App\Models\HubTransferEarning::acceptedRemittances()->where('hub_transport_driver_id', $driver->id)->whereIn('recipient_role', $roles)->sum('amount');
        $base = (float) \App\Models\HubTransferEarning::acceptedRemittances()->where('hub_transport_driver_id', $driver->id)->whereIn('recipient_role', $roles)->sum('base_amount');
        $allowance = (float) \App\Models\HubTransferEarning::acceptedRemittances()->where('hub_transport_driver_id', $driver->id)->whereIn('recipient_role', $roles)->sum('vehicle_allowance');

        return response()->json([
            'summary' => [
                'total_earnings' => $total,
                'base_earnings' => $base,
                'vehicle_allowance' => $allowance,
            ],
            'earnings' => $earnings->items(),
            'pagination' => [
                'current_page' => $earnings->currentPage(),
                'last_page' => $earnings->lastPage(),
                'per_page' => $earnings->perPage(),
                'total' => $earnings->total(),
            ],
        ]);
    }

    public function remittances(Request $request): JsonResponse
    {
        $driver = $this->driver($request);
        $payouts = \App\Models\EscrowRemittance::query()
            ->where('hub_transport_driver_id', $driver->id)
            ->where('recipient_role', \App\Models\EscrowRemittance::ROLE_INTER_HUB_DRIVER)
            ->where('amount', '>', 0)
            ->with(['hubTransfer:id,reference', 'delivery:id,tracking_number'])
            ->latest('id')->paginate(20);

        return response()->json([
            'remittances' => $payouts->getCollection()->map(fn ($payout) => [
                'id' => $payout->id,
                'amount' => (float) $payout->amount,
                'status' => $payout->status,
                'bank_reference' => $payout->bank_reference,
                'remitted_at' => $payout->remitted_at?->toIso8601String(),
                'transfer_reference' => $payout->hubTransfer?->reference,
                'tracking_number' => $payout->delivery?->tracking_number,
                'can_accept' => $payout->status === \App\Models\EscrowRemittance::STATUS_REMITTED,
            ]),
            'pagination' => ['current_page' => $payouts->currentPage(), 'last_page' => $payouts->lastPage(), 'total' => $payouts->total()],
        ]);
    }

    public function acceptRemittance(Request $request, int $id): JsonResponse
    {
        $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
        $driver = $this->driver($request);
        $payout = \App\Models\EscrowRemittance::whereKey($id)
            ->where('hub_transport_driver_id', $driver->id)
            ->where('recipient_role', \App\Models\EscrowRemittance::ROLE_INTER_HUB_DRIVER)->firstOrFail();
        if (! $payout->accept($driver, $request->input('note'))) {
            return response()->json(['message' => 'Only a released, unaccepted remittance can be confirmed. Refresh your remittances.'], 409);
        }

        return response()->json([
            'message' => 'Receipt confirmed. Trip earnings are recorded after all remittances for the trip have been accepted.',
            'remittance' => ['id' => $payout->id, 'status' => $payout->status, 'amount' => (float) $payout->amount],
        ]);
    }

    public function reportIncident(Request $request): JsonResponse
    {
        $driver = $this->driver($request)->load(['truck', 'ownedTruck']);
        $truck = $driver->truck ?: $driver->ownedTruck;
        abort_unless($truck !== null, 422, 'You must have an assigned or registered truck to report an incident.');

        $data = $request->validate([
            'incident_type' => 'required|string|max:50',
            'severity' => 'required|in:minor,moderate,severe,total_loss',
            'incident_date' => 'nullable|date',
            'location' => 'nullable|string|max:255',
            'description' => 'required|string|max:5000',
            'damage_details' => 'nullable|string|max:5000',
            'hub_transfer_id' => 'nullable|integer|exists:hub_transfers,id',
            'photos' => 'nullable|array',
            'photos.*' => 'file|image|max:10240',
        ]);

        $reference = 'INC-'.now()->format('Ymd').'-'.\Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(6));

        $storedPhotos = [];
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                if ($photo->isValid()) {
                    $storedPhotos[] = $photo->store("incident-evidence/{$reference}");
                }
            }
        }

        $actionTaken = 'none';
        if (in_array($data['severity'], ['severe', 'total_loss', 'moderate'], true)) {
            // Vehicle becomes unavailable (marked damaged) pending admin assessment.
            // A reported incident must never automatically permanently retire a vehicle without admin review.
            $actionTaken = 'marked_damaged';
            $truck->update(['status' => 'damaged']);
            HubTransportDriver::where('logistics_truck_id', $truck->id)->update(['logistics_truck_id' => null]);
        }

        $incident = \App\Models\VehicleIncident::create([
            'reference' => $reference,
            'logistics_company_id' => $driver->logistics_company_id,
            'logistics_truck_id' => $truck->id,
            'hub_transport_driver_id' => $driver->id,
            'hub_transfer_id' => $data['hub_transfer_id'] ?? null,
            'incident_type' => $data['incident_type'],
            'severity' => $data['severity'],
            'status' => 'reported',
            'incident_date' => $data['incident_date'] ?? now(),
            'location' => $data['location'] ?? null,
            'description' => $data['description'],
            'damage_details' => $data['damage_details'] ?? null,
            'vehicle_action_taken' => $actionTaken,
            'evidence_photos' => $storedPhotos,
            'reported_by_id' => $driver->user_id,
        ]);

        return response()->json([
            'message' => 'Incident reported successfully.',
            'incident' => $incident,
        ], 201);
    }

    public function incidents(Request $request): JsonResponse
    {
        $driver = $this->driver($request);
        $incidents = \App\Models\VehicleIncident::where('hub_transport_driver_id', $driver->id)
            ->latest('incident_date')
            ->paginate(20);

        return response()->json([
            'incidents' => $incidents->items(),
            'pagination' => [
                'current_page' => $incidents->currentPage(),
                'last_page' => $incidents->lastPage(),
                'per_page' => $incidents->perPage(),
                'total' => $incidents->total(),
            ],
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
            $this->assertHubsActive($locked);
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
            $this->assertHubsActive($locked);
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
            $this->assertHubsActive($locked);
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
            $this->assertHubsActive($locked);
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
            $locked = HubTransfer::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $this->assertHubsActive($locked);
            \App\Services\HubTransferService::arrive($locked, null, $this->driver($request));
        });

        return $this->show($request, $transfer);
    }

    public function transfers(Request $request): JsonResponse
    {
        $driver = $this->driver($request);
        $validated = $request->validate([
            'filter' => 'sometimes|string|in:all,active,pending,ready,in_transit,completed',
        ]);
        $filter = (string) ($validated['filter'] ?? 'all');

        $query = $this->driverTransfers($driver)->with(['origin', 'destination', 'truck', 'parcels.delivery', 'events']);

        // Filters share HubTransfer's lifecycle with the dashboard counters so
        // a completed trip is selected by the same status everywhere.
        match ($filter) {
            'active' => $query->whereIn('status', HubTransfer::ACTIVE_STATUSES),
            'pending' => $query->whereIn('status', ['pending', 'assigned', 'loading']),
            'ready' => $query->where('status', 'ready_for_dispatch'),
            'in_transit' => $query->where('status', 'in_transit'),
            'completed' => $query->where('status', 'received'),
            default => $query,
        };

        $transfers = $query->latest()->get();

        return response()->json([
            'filter' => $filter,
            'transfers' => $transfers->map(fn ($t) => $this->transferPayload($t))->values(),
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $driver = $this->driver($request);

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', \Illuminate\Validation\Rules\Password::defaults(), 'confirmed'],
        ]);

        if (! $driver->password || ! Hash::check($validated['current_password'], $driver->password)) {
            return response()->json([
                'message' => 'The current password is incorrect.',
                'errors' => [
                    'current_password' => ['The current password is incorrect.'],
                ],
            ], 422);
        }

        $newHash = Hash::make($validated['password']);
        $driver->update(['password' => $newHash]);

        if ($driver->user) {
            $driver->user->update(['password' => $newHash]);
        }

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $driver = $this->driver($request);
        $transfers = HubTransfer::with(['origin', 'destination', 'events'])
            ->where('driver_id', $driver->id)
            ->where('logistics_company_id', $driver->logistics_company_id)
            ->latest()
            ->take(20)
            ->get();

        $notifications = collect();

        foreach ($transfers as $transfer) {
            $notifications->push([
                'id' => 100000 + $transfer->id,
                'type' => 'hub_transfer_assignment',
                'title' => 'Assigned Transfer: ' . $transfer->reference,
                'body' => "Manifest {$transfer->reference} from {$transfer->origin?->name} to {$transfer->destination?->name}. Status: {$transfer->status}.",
                'data' => [
                    'hub_transfer_id' => $transfer->id,
                    'reference' => $transfer->reference,
                    'status' => $transfer->status,
                ],
                'is_read' => in_array($transfer->status, ['received', 'cancelled'], true),
                'created_at' => $transfer->created_at?->toIso8601String(),
            ]);

            foreach ($transfer->events as $event) {
                $notifications->push([
                    'id' => 200000 + $event->id,
                    'type' => 'transfer_update',
                    'title' => ucwords(str_replace('_', ' ', (string) $event->event_type)) . ': ' . $transfer->reference,
                    'body' => $event->notes ?: "Transfer {$transfer->reference} update: {$event->event_type}.",
                    'data' => [
                        'hub_transfer_id' => $transfer->id,
                        'event_type' => $event->event_type,
                    ],
                    'is_read' => true,
                    'created_at' => $event->created_at?->toIso8601String(),
                ]);
            }
        }

        $sorted = $notifications->sortByDesc('created_at')->values();

        return response()->json([
            'notifications' => $sorted,
            'unread_count' => $sorted->where('is_read', false)->count(),
            'pagination' => [
                'current_page' => 1,
                'last_page' => 1,
                'total' => $sorted->count(),
                'per_page' => 20,
            ],
        ]);
    }

    public function markNotificationRead(Request $request, int $id): JsonResponse
    {
        return response()->json(['message' => 'Notification marked as read.']);
    }

    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        return response()->json(['message' => 'All notifications marked as read.']);
    }

    /**
     * A leg may only be advanced while both of its hubs are active. The
     * endpoints come from the persisted transfer, never from request input,
     * so supplied hub or company ids cannot steer the check elsewhere.
     */
    private function assertHubsActive(HubTransfer $transfer): void
    {
        abort_unless(
            LogisticsCenter::query()->whereKey($transfer->origin_center_id)->where('is_active', true)->exists()
                && LogisticsCenter::query()->whereKey($transfer->destination_center_id)->where('is_active', true)->exists(),
            422,
            'One or both hubs are inactive. This transfer cannot be advanced.',
        );
    }

    private function driver(Request $request): HubTransportDriver
    {
        $driver = $request->user();
        abort_unless($driver instanceof HubTransportDriver && $request->user()->currentAccessToken()?->can('hub-courier'), 403, 'Hub Transport Driver access is required.');
        abort_unless($driver->status !== 'inactive' && $driver->company?->status === 'active', 403, 'Hub transport driver account is not active.');

        return $driver;
    }

    private function driverTransfers(HubTransportDriver $driver): Builder
    {
        return HubTransfer::query()
            ->where('driver_id', $driver->id)
            ->where('logistics_company_id', $driver->logistics_company_id);
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
            && ! in_array($truck->status, ['inactive', 'maintenance', 'damaged', 'retired'], true)
            && $truck->verification_status === 'approved';
    }

    public function profilePayload(HubTransportDriver $driver): array
    {
        $assignedTruck = $driver->truck;
        $ownedTruck = $driver->ownedTruck;
        $activeTruck = $assignedTruck ?: $ownedTruck;

        $ownershipType = $ownedTruck ? 'driver_owned' : ($assignedTruck ? $assignedTruck->ownership_type : 'company');
        $verificationStatus = $ownedTruck ? $ownedTruck->verification_status : ($assignedTruck ? $assignedTruck->verification_status : 'approved');

        return [
            'id' => $driver->id,
            'name' => $driver->name,
            'email' => $driver->email,
            'phone' => $driver->phone,
            'avatar' => $driver->avatar,
            'company' => $driver->company?->name,
            'status' => $driver->status,
            'is_online' => $driver->is_online,
            'vehicle_arrangement' => $ownershipType,
            'verification_status' => $verificationStatus,
            'verification_notes' => $ownedTruck?->verification_notes,
            'has_assigned_truck' => $assignedTruck !== null,
            'vehicle' => $activeTruck ? [
                'id' => $activeTruck->id,
                'name' => $activeTruck->name,
                'type' => $activeTruck->vehicle_type,
                'model' => $activeTruck->model,
                'plate_number' => $activeTruck->plate_number,
                'parcel_capacity' => (int) $activeTruck->parcel_capacity,
                'capacity_kg' => (float) $activeTruck->capacity_kg,
                'status' => $activeTruck->status,
                'ownership_type' => $activeTruck->ownership_type,
                'verification_status' => $activeTruck->verification_status,
                'is_assignable' => $activeTruck->isAssignable(),
            ] : null,
        ];
    }

    private function transferPayload(?HubTransfer $transfer): ?array
    {
        if (! $transfer) {
            return null;
        }
        $parcels = $transfer->parcels->where('loading_status', '!=', 'removed')->values();
        $totalWeight = $parcels->isNotEmpty() && $parcels->every(fn ($p) => $p->delivery?->weight !== null)
            ? round($parcels->sum(fn ($p) => (float) $p->delivery->weight), 2)
            : null;

        return ['id' => $transfer->id, 'reference' => $transfer->reference, 'status' => $transfer->status,
            'status_label' => HubTransferProgress::transferLabel((string) $transfer->status), 'notes' => $transfer->notes,
            'planned_dispatch_at' => $transfer->planned_dispatch_at?->toIso8601String(), 'origin_arrived_at' => $transfer->origin_arrived_at?->toIso8601String(),
            'dispatched_at' => $transfer->dispatched_at?->toIso8601String(),
            'destination_arrived_at' => $transfer->destination_arrived_at?->toIso8601String(),
            'received_at' => $transfer->received_at?->toIso8601String(), 'company' => $transfer->company?->name,
            'origin' => $transfer->origin ? ['id' => $transfer->origin->id, 'name' => $transfer->origin->name, 'address' => $transfer->origin->address] : null,
            'destination' => $transfer->destination ? ['id' => $transfer->destination->id, 'name' => $transfer->destination->name, 'address' => $transfer->destination->address] : null,
            'vehicle' => $transfer->truck ? ['type' => $transfer->truck->vehicle_type, 'model' => $transfer->truck->model,
                'plate_number' => $transfer->truck->plate_number,
                'parcel_capacity' => (int) $transfer->truck->parcel_capacity, 'capacity_kg' => (float) $transfer->truck->capacity_kg] : null,
            'parcel_count' => $parcels->count(), 'loaded_count' => $parcels->where('loading_status', 'loaded')->count(),
            'received_count' => $parcels->where('receiving_status', 'received')->count(),
            'missing_count' => $parcels->where('receiving_status', 'missing')->count(),
            'total_weight_kg' => $totalWeight,
            'parcels' => $parcels->map(function ($p) use ($transfer): array {
                $stage = HubTransferProgress::parcelStage($transfer, $p);

                return ['id' => $p->delivery_id, 'tracking_number' => $p->delivery?->tracking_number,
                    'weight_kg' => $p->delivery?->weight !== null ? (float) $p->delivery->weight : null,
                    'loading_status' => $p->loading_status, 'receiving_status' => $p->receiving_status,
                    'condition' => $p->condition, 'exception_reason' => $p->exception_reason,
                    'journey_status' => $stage['status'], 'journey_label' => $stage['label'],
                    'parcel_status' => $p->delivery?->parcel_status,
                    'loaded_at' => $p->loaded_at?->toIso8601String(), 'received_at' => $p->received_at?->toIso8601String()];
            })->values(),
            'events' => $transfer->events->map(fn ($e) => ['type' => $e->event_type, 'notes' => $e->notes, 'at' => $e->created_at?->toIso8601String()])->values()];
    }

    private function event(HubTransfer $transfer, Request $request, string $type, string $notes, ?int $deliveryId = null, ?string $previous = null, ?string $next = null): void
    {
        HubTransferEvent::create(['hub_transfer_id' => $transfer->id, 'delivery_id' => $deliveryId, 'event_type' => $type,
            'previous_status' => $previous, 'new_status' => $next, 'notes' => $notes, 'hub_transport_driver_id' => $this->driver($request)->id,
            'actor_name' => $this->driver($request)->name]);
    }
}
