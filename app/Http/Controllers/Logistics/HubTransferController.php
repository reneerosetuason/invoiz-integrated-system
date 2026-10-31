<?php

namespace App\Http\Controllers\Logistics;

use App\Models\Delivery;
use App\Models\HubTransfer;
use App\Models\HubTransferEvent;
use App\Models\HubTransferParcel;
use App\Models\HubTransportDriver;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Models\LogisticsTruck;
use App\Services\HubTransferService;
use App\Support\LogisticsAccess;
use App\Support\LogisticsVehicleCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HubTransferController extends Controller
{
    private const TERMINAL_STATUSES = ['received', 'cancelled'];

    public function index(Request $request): View
    {
        $global = $this->isNetworkAdmin($request);
        $company = $global ? null : $this->company($request);
        $query = HubTransfer::query()->when($company, fn ($q) => $q->where('logistics_company_id', $company->id))
            ->with(['origin', 'destination', 'truck', 'driver'])
            ->withCount(['parcels' => fn ($q) => $q->where('loading_status', '!=', 'removed')]);
        if (! $global) {
            $this->scopeTransfersToUser($query, $request);
        }

        if (($search = trim((string) $request->query('search'))) !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhereHas('parcels.delivery', fn ($delivery) => $delivery->where('tracking_number', 'like', "%{$search}%"));
            });
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $countsQuery = HubTransfer::query()->when($company, fn ($q) => $q->where('logistics_company_id', $company->id));
        if (! $global) {
            $this->scopeTransfersToUser($countsQuery, $request);
        }
        $counts = $countsQuery
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('logistics.hub-transfers.index', [
            'company' => $company,
            'transfers' => $query->latest()->paginate(15)->withQueryString(),
            'counts' => $counts,
            'centers' => $company ? $this->centersFor($company) : collect(),
            'global' => $global,
        ]);
    }

    public function create(Request $request): View
    {
        $global = $this->isNetworkAdmin($request);
        $companies = $global ? LogisticsCompany::where('status', 'active')->orderBy('name')->get() : collect();
        $company = $this->company($request, $request->integer('logistics_company_id'));
        // Null means a company-wide manager; the company filter below is
        // enough. Hub staff stay limited to their assigned hub.
        $centerId = $global ? null : LogisticsAccess::centerId($request->user());
        if (! $global) {
            abort_unless($company && LogisticsAccess::canAccessCompany($request->user(), (int) $company->id), 403);
            if ($centerId === null) {
                abort_unless($request->user()->isCompanyManager(), 403);
            } else {
                abort_unless(
                    $centerId > 0
                        && LogisticsCenter::query()
                            ->whereKey($centerId)
                            ->where('logistics_company_id', $company->id)
                            ->exists(),
                    403,
                    'Your account is not assigned to a hub in this logistics company.'
                );
            }
        }

        return view('logistics.hub-transfers.create', [
            'company' => $company,
            'companies' => $companies,
            'global' => $global,
            'centers' => $company ? $this->centersFor($company) : collect(),
            'originCenterId' => $centerId,
            'trucks' => $company?->trucks()->where('status', 'available')->orderBy('plate_number')->get() ?? collect(),
            'drivers' => $company?->transportDrivers()->where('status', 'available')->orderBy('name')->get() ?? collect(),
            'parcels' => Delivery::query()->with(['logisticsCenter', 'destinationCenter'])->when($company, fn ($q) => $q->whereIn('center_id', $company->centers()->select('id')))
                ->when(! $global && $centerId !== null, fn ($q) => $q->where('center_id', $centerId > 0 ? $centerId : 0))
                ->whereIn('parcel_status', ['received', 'scanned', 'sorted'])
                ->whereNotIn('status', ['delivered', 'cancelled'])
                ->whereNull('archived_at')->orderBy('tracking_number')->limit(200)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->company($request, $request->integer('logistics_company_id'));
        abort_unless($company, 422, 'Select a logistics company for this transfer.');
        $validated = $request->validate([
            'logistics_company_id' => [$this->isNetworkAdmin($request) ? 'required' : 'prohibited', 'integer', 'exists:logistics_companies,id'],
            'origin_center_id' => ['required', 'integer', Rule::exists('logistics_centers', 'id')->where('logistics_company_id', $company->id)],
            'destination_center_id' => ['required', 'integer', Rule::exists('logistics_centers', 'id')->where('logistics_company_id', $company->id)],
            'truck_id' => ['required', 'integer', Rule::exists('logistics_trucks', 'id')->where('logistics_company_id', $company->id)->where('status', 'available')],
            'driver_id' => ['required', 'integer', Rule::exists('hub_transport_drivers', 'id')->where('logistics_company_id', $company->id)->where('status', 'available')],
            'planned_dispatch_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'parcels' => ['required', 'array', 'min:1'],
            'parcels.*' => ['required', 'integer', 'distinct', 'exists:deliveries,id'],
        ]);

        abort_if((int) $validated['origin_center_id'] === (int) $validated['destination_center_id'], 422, 'Origin and destination must differ.');
        $this->assertCenterAccess($request, (int) $validated['origin_center_id'], (int) $validated['destination_center_id']);
        if (! $this->isNetworkAdmin($request)) {
            // Company managers have a null center and may create a transfer
            // from any hub inside their own company. Hub staff stay tied to
            // the origin hub.
            $centerId = LogisticsAccess::centerId($request->user());
            if ($centerId !== null) {
                abort_unless($centerId > 0 && $centerId === (int) $validated['origin_center_id'], 403, 'Only staff at the origin hub can create its transfer.');
            }
        }

        $transfer = DB::transaction(function () use ($request, $company, $validated): HubTransfer {
            $truck = LogisticsTruck::query()->whereKey($validated['truck_id'])->lockForUpdate()->firstOrFail();
            $driver = HubTransportDriver::query()->whereKey($validated['driver_id'])->lockForUpdate()->firstOrFail();
            abort_unless($truck->logistics_company_id === $company->id && $truck->status === 'available', 422, 'Truck is not available to this company.');
            abort_unless($driver->logistics_company_id === $company->id && $driver->status === 'available', 422, 'Driver is not available to this company.');
            abort_unless(LogisticsVehicleCategory::for($truck->vehicle_type) === 'four_wheel', 422, 'Hub transfers require an approved four-wheel vehicle.');
            abort_unless((int) $truck->parcel_capacity > 0 && (float) $truck->capacity_kg > 0, 422, 'Truck parcel and weight capacities must be registered.');
            abort_unless((int) $driver->logistics_truck_id === (int) $truck->id && $driver->email && $driver->password, 422, 'Assign an authenticated Hub Transport Driver to this truck first.');

            $reference = 'HT-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
            $transfer = HubTransfer::create([
                'reference' => $reference,
                'logistics_company_id' => $company->id,
                'origin_center_id' => $validated['origin_center_id'],
                'destination_center_id' => $validated['destination_center_id'],
                'truck_id' => $truck->id,
                'driver_id' => $driver->id,
                'status' => 'loading',
                'planned_dispatch_at' => $validated['planned_dispatch_at'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            $totalWeight = 0.0;
            $hasCompleteWeightData = true;
            foreach ($validated['parcels'] as $deliveryId) {
                $parcel = Delivery::query()->whereKey($deliveryId)->lockForUpdate()->firstOrFail();
                abort_unless((int) $parcel->center_id === (int) $transfer->origin_center_id, 422, "Parcel {$parcel->tracking_number} is not at the origin hub.");
                abort_unless(in_array($parcel->parcel_status, ['received', 'scanned', 'sorted'], true), 422, "Parcel {$parcel->tracking_number} is not eligible for transfer.");
                abort_unless(! in_array($parcel->status, ['delivered', 'cancelled'], true) && $parcel->archived_at === null, 422, "Parcel {$parcel->tracking_number} cannot be transferred.");
                abort_if($parcel->rider_id !== null || in_array($parcel->status, Delivery::ACTIVE_STATUSES, true), 422, "Parcel {$parcel->tracking_number} is assigned to last-mile delivery and cannot be transferred.");
                if ($parcel->destination_center_id) {
                    abort_unless((int) $parcel->destination_center_id === (int) $transfer->destination_center_id, 422, "Parcel {$parcel->tracking_number} is routed to a different hub.");
                }
                if ($parcel->weight === null) {
                    $hasCompleteWeightData = false;
                } else {
                    $totalWeight += (float) $parcel->weight;
                }
                $active = HubTransferService::hasActiveLeg($parcel->id, $transfer->id);
                abort_if($active, 422, "Parcel {$parcel->tracking_number} is already in an active transfer.");
                $manifestEntry = HubTransferParcel::firstOrNew(['hub_transfer_id' => $transfer->id, 'delivery_id' => $parcel->id]);
                $manifestEntry->fill(['loading_status' => 'pending', 'receiving_status' => 'pending', 'condition' => null, 'exception_reason' => null]);
                $manifestEntry->save();
            }

            abort_if(count($validated['parcels']) > $truck->parcel_capacity, 422, 'The selected truck does not have enough parcel capacity.');
            abort_unless($hasCompleteWeightData, 422, 'Parcel weights must be recorded before assigning this transfer.');
            abort_if($totalWeight > (float) $truck->capacity_kg, 422, 'The selected truck does not have enough weight capacity.');
            $truck->update(['status' => 'assigned']);
            $driver->update(['status' => 'assigned']);
            $this->event($transfer, $request, 'created', null, 'loading', 'Transfer created with parcel manifest.');
            $this->event($transfer, $request, 'truck_assigned', null, null, 'Truck '.$truck->plate_number.' assigned.');
            $this->event($transfer, $request, 'driver_assigned', null, null, 'Driver '.$driver->name.' assigned.');

            return $transfer;
        });

        return redirect()->route('hub-transfers.show', $transfer)->with('success', 'Transfer and manifest created. Scan each parcel before dispatch.');
    }

    public function show(Request $request, HubTransfer $hubTransfer): View
    {
        $this->authorizeTransfer($request, $hubTransfer);
        $this->assertCenterAccess($request, $hubTransfer->origin_center_id, $hubTransfer->destination_center_id);
        $hubTransfer->load(['company', 'origin', 'destination', 'truck', 'driver', 'parcels.delivery', 'events']);

        $trucks = LogisticsTruck::query()
            ->where('logistics_company_id', $hubTransfer->logistics_company_id)
            ->where(fn ($query) => $query->where('status', 'available')->orWhere('id', $hubTransfer->truck_id))
            ->orderBy('plate_number')
            ->get();
        $drivers = HubTransportDriver::query()
            ->where('logistics_company_id', $hubTransfer->logistics_company_id)
            ->whereNotNull('logistics_truck_id')
            ->where(fn ($query) => $query->where('status', 'available')->orWhere('id', $hubTransfer->driver_id))
            ->get();
        $fleetOptions = $trucks
            ->map(fn (LogisticsTruck $truck) => ['truck' => $truck, 'driver' => $drivers->firstWhere('logistics_truck_id', $truck->id)])
            ->filter(fn (array $option) => $option['driver'] !== null)
            ->values();
        $rerouteHubs = LogisticsCenter::query()
            ->where('logistics_company_id', $hubTransfer->logistics_company_id)
            ->where('is_active', true)
            ->whereKeyNot($hubTransfer->origin_center_id)
            ->orderBy('name')
            ->get();

        return view('logistics.hub-transfers.show', [
            'transfer' => $hubTransfer,
            'fleetOptions' => $fleetOptions,
            'rerouteHubs' => $rerouteHubs,
        ]);
    }

    public function loadParcel(Request $request, HubTransfer $hubTransfer): RedirectResponse
    {
        $this->authorizeTransfer($request, $hubTransfer);
        $this->assertOriginAccess($request, $hubTransfer);
        $data = $request->validate(['tracking_number' => ['required', 'string', 'max:100']]);
        DB::transaction(function () use ($request, $hubTransfer, $data): void {
            $transfer = HubTransfer::whereKey($hubTransfer->id)->lockForUpdate()->firstOrFail();
            abort_unless($transfer->status === 'loading', 409, 'This transfer is no longer loading.');
            $entry = HubTransferParcel::where('hub_transfer_id', $transfer->id)->whereHas('delivery', fn ($q) => $q->where('tracking_number', $data['tracking_number']))->lockForUpdate()->firstOrFail();
            abort_if($entry->loading_status === 'removed', 422, 'This parcel was removed from the manifest.');
            abort_if($entry->loading_status === 'loaded', 422, 'This parcel has already been scanned.');
            $entry->update(['loading_status' => 'loaded', 'loaded_by' => $request->user()->id, 'loaded_at' => now()]);
            $this->event($transfer, $request, 'parcel_loaded', null, null, 'Parcel loaded: '.$data['tracking_number'], $entry->delivery_id);
            if (! $transfer->parcels()->whereNotIn('loading_status', ['loaded', 'removed'])->exists()) {
                $transfer->update(['status' => 'ready_for_dispatch']);
                HubTransferService::event($transfer, 'manifest_ready', 'loading', 'ready_for_dispatch', 'Automatic: every manifest parcel was scanned.', null);
            }
        });

        return back()->with('success', 'Parcel loading scan recorded.');
    }

    public function dispatch(Request $request, HubTransfer $hubTransfer): RedirectResponse
    {
        $this->authorizeTransfer($request, $hubTransfer);
        $this->assertOriginAccess($request, $hubTransfer);
        HubTransferService::dispatch($hubTransfer, $request->user());

        return back()->with('success', 'Transfer dispatched and marked in transit.');
    }

    public function arrive(Request $request, HubTransfer $hubTransfer): RedirectResponse
    {
        $this->authorizeTransfer($request, $hubTransfer);
        $this->assertDestinationAccess($request, $hubTransfer);
        HubTransferService::arrive($hubTransfer, $request->user());

        return back()->with('success', 'Arrival recorded. Parcels still require individual receiving scans.');
    }

    public function removeParcel(Request $request, HubTransfer $hubTransfer, HubTransferParcel $parcel): RedirectResponse
    {
        $this->assertOriginAccess($request, $hubTransfer);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        DB::transaction(function () use ($request, $hubTransfer, $parcel, $data): void {
            $transfer = HubTransfer::whereKey($hubTransfer->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($transfer->status, ['loading', 'ready_for_dispatch'], true), 409, 'Parcels can only be removed before dispatch.');
            $entry = HubTransferParcel::where('hub_transfer_id', $transfer->id)->whereKey($parcel->id)->lockForUpdate()->firstOrFail();
            abort_if($entry->loading_status === 'removed', 409, 'This parcel is already removed from the manifest.');
            $entry->update(['loading_status' => 'removed', 'receiving_status' => 'removed']);
            $remainingToLoad = $transfer->parcels()->whereNotIn('loading_status', ['loaded', 'removed'])->exists();
            $transfer->update(['status' => $remainingToLoad ? 'loading' : 'ready_for_dispatch']);
            $this->event($transfer, $request, 'parcel_removed', null, null, 'Removed tracking '.$entry->delivery?->tracking_number.': '.$data['reason'], $entry->delivery_id);
        });

        return back()->with('success', 'Parcel removed from the transfer manifest.');
    }

    public function receiveParcel(Request $request, HubTransfer $hubTransfer): RedirectResponse
    {
        $this->authorizeTransfer($request, $hubTransfer);
        $this->assertDestinationAccess($request, $hubTransfer);
        $data = $request->validate([
            'tracking_number' => ['required', 'string', 'max:100'],
            'condition' => ['required', Rule::in(['good', 'damaged', 'missing'])],
            'exception_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $entry = HubTransferParcel::query()
            ->where('hub_transfer_id', $hubTransfer->id)
            ->whereHas('delivery', fn ($query) => $query->where('tracking_number', $data['tracking_number']))
            ->first();

        if (! $entry) {
            return back()->with('error', 'That tracking number is not on this transfer manifest. Check the parcel before recording its receipt.');
        }

        $result = HubTransferService::receiveParcel(
            $hubTransfer,
            (int) $entry->delivery_id,
            $data['condition'],
            $data['exception_reason'] ?? null,
            $request->user(),
        );

        if ($result['duplicate']) {
            return back()->with('error', 'This parcel has already been accounted for.');
        }

        return back()->with('success', 'Receiving result recorded.');
    }

    public function complete(Request $request, HubTransfer $hubTransfer): RedirectResponse
    {
        $this->assertDestinationAccess($request, $hubTransfer);
        HubTransferService::complete($hubTransfer, $request->user());

        return back()->with('success', 'Transfer receiving finalized.');
    }

    public function cancel(Request $request, HubTransfer $hubTransfer): RedirectResponse
    {
        $this->assertOriginAccess($request, $hubTransfer);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:1000']]);
        HubTransferService::cancel($hubTransfer, $request->user(), $data['reason']);

        return back()->with('success', 'Transfer cancelled before dispatch. Parcels remain at the origin hub.');
    }

    public function hold(Request $request, HubTransfer $hubTransfer): RedirectResponse
    {
        $this->authorizeTransfer($request, $hubTransfer);
        $this->assertCenterAccess($request, $hubTransfer->origin_center_id, $hubTransfer->destination_center_id);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:1000']]);
        HubTransferService::hold($hubTransfer, $request->user(), $data['reason']);

        return back()->with('success', 'Transfer placed on hold. No parcel movement is allowed until it is resumed.');
    }

    public function resume(Request $request, HubTransfer $hubTransfer): RedirectResponse
    {
        $this->authorizeTransfer($request, $hubTransfer);
        $this->assertCenterAccess($request, $hubTransfer->origin_center_id, $hubTransfer->destination_center_id);
        HubTransferService::resume($hubTransfer, $request->user());

        return back()->with('success', 'Transfer resumed at its previous stage.');
    }

    public function reassign(Request $request, HubTransfer $hubTransfer): RedirectResponse
    {
        $this->assertOriginAccess($request, $hubTransfer);
        $data = $request->validate([
            'truck_id' => ['required', 'integer', 'exists:logistics_trucks,id'],
        ]);
        $truck = LogisticsTruck::whereKey($data['truck_id'])->firstOrFail();
        $driver = HubTransportDriver::query()
            ->where('logistics_truck_id', $truck->id)
            ->where(fn ($query) => $query->where('status', 'available')->orWhere('id', $hubTransfer->driver_id))
            ->firstOrFail();
        HubTransferService::reassignFleet($hubTransfer, $truck, $driver, $request->user());

        return back()->with('success', 'Truck and driver reassigned for this transfer.');
    }

    public function reroute(Request $request, HubTransfer $hubTransfer): RedirectResponse
    {
        $this->assertOriginAccess($request, $hubTransfer);
        $data = $request->validate([
            'destination_center_id' => ['required', 'integer', Rule::exists('logistics_centers', 'id')->where('logistics_company_id', $hubTransfer->logistics_company_id)],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
        HubTransferService::reroute(
            $hubTransfer,
            LogisticsCenter::whereKey($data['destination_center_id'])->firstOrFail(),
            $request->user(),
            $data['reason'],
        );

        return back()->with('success', 'Destination hub corrected. Every manifest parcel now routes to the new hub.');
    }

    private function company(Request $request, ?int $requestedId = null): ?LogisticsCompany
    {
        $user = $request->user();
        if ($this->isNetworkAdmin($request)) {
            return $requestedId ? LogisticsCompany::whereKey($requestedId)->where('status', 'active')->firstOrFail() : null;
        }

        $companyId = LogisticsAccess::companyId($user);
        abort_unless($companyId !== null && $companyId > 0, 403, 'Your account is not assigned to a logistics company.');

        return LogisticsCompany::whereKey($companyId)->where('status', 'active')->firstOrFail();
    }

    private function centersFor(LogisticsCompany $company)
    {
        return $company->centers()->where('is_active', true)->orderBy('name')->get();
    }

    private function isNetworkAdmin(Request $request): bool
    {
        return LogisticsAccess::isGlobal($request->user());
    }

    private function assertCenterAccess(Request $request, int $originId, int $destinationId): void
    {
        $user = $request->user();
        abort_if($originId === $destinationId, 403, 'Origin and destination must differ.');

        if (LogisticsAccess::isGlobal($user)) {
            // Global accounts may operate every company, but both hubs must
            // still belong to the same company.
            $companyId = (int) (LogisticsCenter::whereKey($originId)->value('logistics_company_id') ?? 0);
            $company = LogisticsCompany::whereKey($companyId)->where('status', 'active')->firstOrFail();
            $hubs = LogisticsCenter::query()
                ->where('logistics_company_id', $company->id)
                ->whereIn('id', [$originId, $destinationId])
                ->count();
            abort_unless($hubs === 2, 403, 'Both hubs must belong to the same logistics company.');

            return;
        }

        $companyId = LogisticsAccess::companyId($user);
        abort_unless($companyId > 0, 403, 'Your account is not assigned to a logistics company.');

        $hubs = LogisticsCenter::query()
            ->where('logistics_company_id', $companyId)
            ->whereIn('id', [$originId, $destinationId])
            ->count();
        abort_unless($hubs === 2, 403, 'Both hubs must belong to your logistics company.');

        // Null center = company-wide manager; a non-null center is a hub
        // staff restriction that must cover one side of the transfer.
        $centerId = LogisticsAccess::centerId($user);
        if ($centerId === null) {
            abort_unless($user->isCompanyManager(), 403);
        } else {
            abort_unless(
                $centerId > 0 && in_array($centerId, [$originId, $destinationId], true),
                403,
                'You may only operate transfers for your assigned hub.'
            );
        }
    }

    private function authorizeTransfer(Request $request, HubTransfer $transfer): void
    {
        abort_unless(
            (int) $transfer->logistics_company_id > 0
                && LogisticsAccess::canAccessCompany($request->user(), (int) $transfer->logistics_company_id),
            404
        );

        $hubCount = LogisticsCenter::query()
            ->where('logistics_company_id', $transfer->logistics_company_id)
            ->whereIn('id', [$transfer->origin_center_id, $transfer->destination_center_id])
            ->count();
        abort_unless(
            (int) $transfer->origin_center_id !== (int) $transfer->destination_center_id && $hubCount === 2,
            404
        );
    }

    private function assertOriginAccess(Request $request, HubTransfer $transfer): void
    {
        $this->authorizeTransfer($request, $transfer);
        $this->assertCenterAccess($request, $transfer->origin_center_id, $transfer->destination_center_id);

        if (! $this->isNetworkAdmin($request)) {
            $centerId = LogisticsAccess::centerId($request->user());
            if ($centerId !== null) {
                abort_unless($centerId > 0 && $centerId === (int) $transfer->origin_center_id, 403);
            }
        }
    }

    private function assertDestinationAccess(Request $request, HubTransfer $transfer): void
    {
        $this->authorizeTransfer($request, $transfer);
        $this->assertCenterAccess($request, $transfer->origin_center_id, $transfer->destination_center_id);

        if (! $this->isNetworkAdmin($request)) {
            $centerId = LogisticsAccess::centerId($request->user());
            if ($centerId !== null) {
                abort_unless($centerId > 0 && $centerId === (int) $transfer->destination_center_id, 403);
            }
        }
    }

    /**
     * Restrict a transfers query to the account's company and, for hub staff,
     * to transfers touching their assigned hub. Company managers keep a null
     * center and therefore see every transfer inside their company only.
     */
    private function scopeTransfersToUser(Builder $query, Request $request): void
    {
        $user = $request->user();
        if (LogisticsAccess::isGlobal($user)) {
            return;
        }

        $companyId = LogisticsAccess::companyId($user);
        abort_unless($companyId !== null && $companyId > 0, 403, 'Your account is not assigned to a logistics company.');

        $query->where('logistics_company_id', $companyId)
            ->whereHas('origin', fn ($center) => $center->where('logistics_company_id', $companyId))
            ->whereHas('destination', fn ($center) => $center->where('logistics_company_id', $companyId));

        $centerId = LogisticsAccess::centerId($user);
        if ($centerId === null) {
            abort_unless($user->isCompanyManager(), 403, 'Your account does not have a valid transfer scope.');

            return;
        }

        abort_unless(
            $centerId > 0
                && LogisticsCenter::query()
                    ->whereKey($centerId)
                    ->where('logistics_company_id', $companyId)
                    ->exists(),
            403,
            'Your account is not assigned to a hub in this logistics company.'
        );

        $query->where(fn ($q) => $q
            ->where('origin_center_id', $centerId)
            ->orWhere('destination_center_id', $centerId));
    }

    private function event(HubTransfer $transfer, Request $request, string $type, ?string $old, ?string $new, ?string $notes, ?int $deliveryId = null): void
    {
        HubTransferEvent::create(['hub_transfer_id' => $transfer->id, 'delivery_id' => $deliveryId, 'event_type' => $type,
            'previous_status' => $old, 'new_status' => $new, 'notes' => $notes, 'actor_id' => $request->user()->id,
            'actor_name' => $request->user()->name]);
    }
}
