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
use App\Services\ShipmentWeightService;
use App\Support\HubTransferProgress;
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
        $query = HubTransfer::query()
            ->with(['origin', 'destination'])
            ->withCount(['parcels' => fn ($q) => $q->where('loading_status', '!=', 'removed')]);
        if (! $global) {
            // Cross-company legs are visible to the company on either end of
            // the manifest, and to hub staff whose hub is on that leg.
            $this->scopeTransfersToUser($query, $request);
        } elseif ($company) {
            $query->where('logistics_company_id', $company->id);
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

        $countsQuery = HubTransfer::query();
        if (! $global) {
            $this->scopeTransfersToUser($countsQuery, $request);
        } elseif ($company) {
            $countsQuery->where('logistics_company_id', $company->id);
        }
        $counts = $countsQuery
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        $transfers = $query->latest()->paginate(15)->withQueryString();

        // Assigned fleet and driver details belong to the origin company. Load
        // them only for rows this account is entitled to see, so a foreign
        // destination company never receives them in the view payload.
        $viewerCompanyId = $global ? null : LogisticsAccess::companyId($request->user());
        $fleetVisibleTransferIds = $transfers->getCollection()
            ->filter(function (HubTransfer $transfer) use ($global, $viewerCompanyId): bool {
                if ($global) {
                    return true;
                }

                $originCompanyId = (int) ($transfer->origin?->logistics_company_id ?? 0);

                return $viewerCompanyId !== null
                    && $viewerCompanyId > 0
                    && $originCompanyId === $viewerCompanyId;
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $transfers->getCollection()
            ->whereIn('id', $fleetVisibleTransferIds)
            ->each(fn (HubTransfer $transfer) => $transfer->loadMissing(['truck', 'driver']));

        return view('logistics.hub-transfers.index', [
            'company' => $company,
            'transfers' => $transfers,
            'counts' => $counts,
            'centers' => LogisticsCenter::where('is_active', true)->whereHas('company', fn ($q) => $q->where('status', 'active'))->orderBy('name')->get(),
            'global' => $global,
            'fleetVisibleTransferIds' => $fleetVisibleTransferIds,
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

        // Hub staff are always pinned to their own hub, even if a query string
        // asks for another origin. Managers and network admins may originate
        // from any eligible hub.
        $selectedOriginId = $centerId ?: ($request->integer('origin_center_id') ?: ($company?->centers()->value('id')));

        $parcelsQuery = Delivery::query()
            ->with(['logisticsCenter', 'destinationCenter', 'order.items'])
            ->when($selectedOriginId, fn ($q) => $q->where('center_id', $selectedOriginId))
            ->whereNotNull('destination_center_id')
            ->whereColumn('destination_center_id', '!=', 'center_id')
            ->where('parcel_status', 'sorted')
            ->whereNotIn('status', ['delivered', 'cancelled'])
            ->whereNull('rider_id')
            ->whereNotIn('status', Delivery::ACTIVE_STATUSES)
            ->whereNull('archived_at')
            ->whereDoesntHave('hubTransferParcels', function ($q): void {
                $q->where('loading_status', '!=', 'removed')
                    ->whereHas('transfer', fn ($t) => $t->whereIn('status', HubTransfer::ACTIVE_STATUSES));
            })
            ->orderBy('destination_center_id')
            ->orderBy('tracking_number');

        $parcels = $parcelsQuery->limit(300)->get();
        foreach ($parcels as $parcel) {
            // Preview legacy parcels from inventory without writing during GET.
            app(ShipmentWeightService::class)->fillMissing($parcel, false);
        }
        $parcelsByDestination = $parcels->groupBy('destination_center_id');
        $destinationHubs = LogisticsCenter::whereIn('id', $parcelsByDestination->keys())->get()->keyBy('id');

        // Queue deep-links can hand us the parcel(s) the operator already chose
        // on the Sorting Center or Operations page. Only eligible parcels that
        // survived the query above are preselected.
        $requestedParcels = array_filter(array_map(
            'intval',
            array_merge((array) $request->query('parcels', []), [$request->integer('parcel')])
        ));
        $preselectedParcelIds = $parcels->whereIn('id', $requestedParcels)->pluck('id')->all();

        // Only expose fleet pairs that can actually operate a transfer:
        // an eligible four-wheel truck with an available, licensed driver
        // assigned to it and no other active manifest. The driver dropdown is
        // limited to those paired drivers, so an unassigned driver cannot be
        // selected. When no pair is free we explain why instead of leaving the
        // operator with silently disabled dropdowns.
        $trucks = collect();
        $drivers = collect();
        $fleetBlockReason = null;
        $fleetTruckIssues = collect();
        if ($company) {
            $eligibleTrucks = $company->trucks()
                ->with('driver')
                ->orderBy('plate_number')
                ->get()
                ->filter(fn (LogisticsTruck $truck) => $truck->isApproved()
                    && LogisticsVehicleCategory::for($truck->vehicle_type) === 'four_wheel'
                    && (int) $truck->parcel_capacity > 0
                    && (float) $truck->capacity_kg > 0)
                ->values();

            $pairedTrucks = $eligibleTrucks
                ->filter(fn (LogisticsTruck $truck) => $truck->driver
                    && (int) $truck->driver->logistics_company_id === (int) $company->id)
                ->values();

            $licensedTrucks = $pairedTrucks
                ->filter(fn (LogisticsTruck $truck) => $truck->driver->isLicenseValid())
                ->values();

            $trucks = $licensedTrucks
                ->filter(fn (LogisticsTruck $truck) => $truck->isAssignable()
                    && $truck->driver->status === 'available'
                    && ! $truck->hasActiveTransfer())
                ->values();

            $drivers = $trucks->pluck('driver')->sortBy('name')->values();

            if ($trucks->isEmpty()) {
                $fleetTruckIssues = $eligibleTrucks
                    ->reject(fn (LogisticsTruck $truck) => $trucks->contains(fn (LogisticsTruck $free) => $free->id === $truck->id))
                    ->map(fn (LogisticsTruck $truck): array => [
                        'plate' => $truck->plate_number,
                        'reason' => match (true) {
                            ! $truck->driver => 'No Hub Transport Driver assigned',
                            (int) $truck->driver->logistics_company_id !== (int) $company->id => 'Assigned driver belongs to another company',
                            ! $truck->driver->isLicenseValid() => 'Driver license is not approved or has expired',
                            ! $truck->isAssignable() => match ($truck->status) {
                                LogisticsTruck::STATUS_IN_TRANSIT => 'Truck is in transit on another leg',
                                LogisticsTruck::STATUS_MAINTENANCE => 'Truck is under maintenance',
                                default => 'Truck is '.$truck->status.' and cannot be assigned',
                            },
                            $truck->driver->status !== 'available' => 'Driver is not marked available',
                            default => 'Truck is already on an active hub transfer',
                        },
                    ])
                    ->values();

                $fleetBlockReason = $eligibleTrucks->isEmpty()
                    ? 'No approved four-wheel truck with registered parcel and weight capacity exists for this company.'
                    : $this->fleetBlockSummary($eligibleTrucks, $pairedTrucks, $licensedTrucks);
            }
        }

        $destinationCenters = LogisticsCenter::query()
            ->where('is_active', true)
            ->whereHas('company', fn ($q) => $q->where('status', 'active'))
            ->orderBy('name')
            ->get();

        return view('logistics.hub-transfers.create', [
            'company' => $company,
            'companies' => $companies,
            'global' => $global,
            'centers' => $company ? $this->centersFor($company) : collect(),
            'destinationCenters' => $destinationCenters,
            'originCenterId' => $selectedOriginId,
            // Hub-scoped staff are locked to their own hub; network admins and
            // company-wide managers may originate a transfer from any hub.
            'originSelectable' => $global || $centerId === null,
            'trucks' => $trucks,
            'drivers' => $drivers,
            'fleetBlockReason' => $fleetBlockReason,
            'fleetTruckIssues' => $fleetTruckIssues,
            'parcels' => $parcels,
            'parcelsByDestination' => $parcelsByDestination,
            'destinationHubs' => $destinationHubs,
            'preselectedParcelIds' => $preselectedParcelIds,
            'selectedDestinationId' => $request->integer('destination_center_id') ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(LogisticsAccess::canProcessParcels($request->user()), 403, 'Transfer manifests are loaded and dispatched by logistics staff at a hub. Management accounts have read-only transfer monitoring.');
        $company = $this->company($request, $request->integer('logistics_company_id'));
        abort_unless($company, 422, 'Select a logistics company for this transfer.');
        $validated = $request->validate([
            'logistics_company_id' => [$this->isNetworkAdmin($request) ? 'required' : 'prohibited', 'integer', 'exists:logistics_companies,id'],
            'origin_center_id' => ['required', 'integer', Rule::exists('logistics_centers', 'id')->where('logistics_company_id', $company->id)->where('is_active', true)],
            // The next hop may belong to another company in the shared network,
            // but the hub and its company must both be active. The lookup is
            // server-side; a client-supplied company id is never trusted.
            'destination_center_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_numeric($value)) {
                        return;
                    }
                    $failure = $this->destinationEligibilityFailure((int) $value);
                    if ($failure !== null) {
                        $fail($failure);
                    }
                },
            ],
            // A truck paired with a driver in Fleet Management is stored as
            // `assigned`, so both available and assigned trucks are valid here.
            'truck_id' => ['required', 'integer', Rule::exists('logistics_trucks', 'id')->where('logistics_company_id', $company->id)->whereIn('status', [LogisticsTruck::STATUS_AVAILABLE, LogisticsTruck::STATUS_ASSIGNED])],
            'driver_id' => ['required', 'integer', Rule::exists('hub_transport_drivers', 'id')->where('logistics_company_id', $company->id)->whereIn('status', ['available', 'assigned'])],
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
            abort_unless(
                (int) $truck->logistics_company_id === (int) $company->id
                    && in_array($truck->status, [LogisticsTruck::STATUS_AVAILABLE, LogisticsTruck::STATUS_ASSIGNED], true),
                422,
                'Truck is not available to this company.'
            );
            abort_unless($truck->isAssignable(), 422, 'This vehicle is not assignable to hub transfers.');
            abort_unless(
                (int) $driver->logistics_company_id === (int) $company->id
                    && in_array($driver->status, ['available', 'assigned'], true),
                422,
                'Driver is not available to this company.'
            );
            abort_unless($driver->isLicenseValid(), 422, 'Assigned driver does not have an approved, unexpired driver\'s license.');
            abort_unless(LogisticsVehicleCategory::for($truck->vehicle_type) === 'four_wheel', 422, 'Hub transfers require an approved four-wheel vehicle.');
            abort_unless((int) $truck->parcel_capacity > 0 && (float) $truck->capacity_kg > 0, 422, 'Truck parcel and weight capacities must be registered.');

            // The driver must be the one paired with this exact truck in Fleet
            // Management. This blocks mismatched truck/driver submissions.
            abort_unless(
                (int) $driver->logistics_truck_id === (int) $truck->id,
                422,
                'Truck '.$truck->plate_number.' has no assigned Hub Transport Driver. Open Fleet Management and assign an approved driver to this truck first.'
            );
            abort_unless(
                $driver->email && $driver->password,
                422,
                'The Hub Transport Driver assigned to this truck has no mobile login yet. Add an email and password for the driver in Fleet Management.'
            );

            // A second active manifest would double-book the truck or driver.
            abort_if($truck->hasActiveTransfer(), 409, 'This truck is already assigned to another active hub transfer.');
            abort_if(
                HubTransfer::query()
                    ->where('driver_id', $driver->id)
                    ->whereIn('status', HubTransfer::ACTIVE_STATUSES)
                    ->exists(),
                409,
                'This Hub Transport Driver is already assigned to another active hub transfer.'
            );

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
            $selectedDestId = (int) $validated['destination_center_id'];
            $originId = (int) $validated['origin_center_id'];
            $finalDestinationId = null;

            foreach ($validated['parcels'] as $deliveryId) {
                $parcel = Delivery::query()->whereKey($deliveryId)->lockForUpdate()->firstOrFail();
                abort_unless((int) $parcel->center_id === $originId, 422, "Parcel {$parcel->tracking_number} is not at the origin hub.");
                abort_unless(
                    $parcel->parcel_status === 'sorted',
                    422,
                    "Parcel {$parcel->tracking_number} must be received, scanned and sorted at the origin hub before it can be transferred."
                );
                abort_unless(! in_array($parcel->status, ['delivered', 'cancelled'], true) && $parcel->archived_at === null, 422, "Parcel {$parcel->tracking_number} cannot be transferred.");
                abort_if($parcel->rider_id !== null || in_array($parcel->status, Delivery::ACTIVE_STATUSES, true), 422, "Parcel {$parcel->tracking_number} is assigned to last-mile delivery and cannot be transferred.");

                // The leg may end at an intermediate hub, so it is not compared
                // with the parcel's final destination. Parcels in one manifest
                // must still share the same final destination (the buyer's
                // hub); a transfer never rewrites that final destination.
                abort_unless($parcel->destination_center_id !== null, 422, "Parcel {$parcel->tracking_number} has no destination hub assigned.");
                $parcelFinalDestinationId = (int) $parcel->destination_center_id;
                if ($finalDestinationId === null) {
                    $finalDestinationId = $parcelFinalDestinationId;
                } elseif ($finalDestinationId !== $parcelFinalDestinationId) {
                    abort(422, "Parcel {$parcel->tracking_number} is routed to a different hub (" . ($parcel->destinationCenter?->name ?? $parcel->destination_center_id) . "). All parcels in a single transfer must share the destination hub.");
                }

                // Backend duplicate transfer prevention
                $active = HubTransferService::hasActiveLeg($parcel->id, $transfer->id);
                abort_if($active, 422, "Parcel {$parcel->tracking_number} is already included in an active transfer.");

                // Sequential transfer rule: every previous leg must have been
                // received at this hub before the parcel may leave on another
                // leg. Cancelled/removed manifest entries do not count.
                $latestTransferParcel = $parcel->hubTransferParcels()
                    ->where('hub_transfer_id', '!=', $transfer->id)
                    ->where('loading_status', '!=', 'removed')
                    ->latest('id')
                    ->first();
                if ($latestTransferParcel) {
                    abort_unless(
                        $latestTransferParcel->receiving_status === 'received',
                        422,
                        "Parcel {$parcel->tracking_number} has not completed its previous transfer leg."
                    );
                }

                // A leg may route through an intermediate hub, but never back
                // to a hub the parcel already departed from or passed through.
                $previousHubs = DB::table('hub_transfer_parcels as htp')
                    ->join('hub_transfers as ht', 'ht.id', '=', 'htp.hub_transfer_id')
                    ->where('htp.delivery_id', $parcel->id)
                    ->where('htp.loading_status', '!=', 'removed')
                    ->where('htp.receiving_status', 'received')
                    ->where('ht.id', '!=', $transfer->id)
                    ->where('ht.status', '!=', 'cancelled')
                    ->get(['ht.origin_center_id', 'ht.destination_center_id'])
                    ->flatMap(fn ($leg) => [(int) $leg->origin_center_id, (int) $leg->destination_center_id])
                    ->unique()
                    ->all();
                abort_if(
                    in_array($selectedDestId, $previousHubs, true),
                    422,
                    "Parcel {$parcel->tracking_number} has already passed through the selected next-hop hub. Choose a hub it has not visited."
                );

                app(ShipmentWeightService::class)->fillMissing($parcel);
                if ($parcel->weight === null) {
                    $hasCompleteWeightData = false;
                } else {
                    $totalWeight += (float) $parcel->weight;
                }

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
        // Historical records stay readable after a hub is deactivated, so
        // authorization checks who the user is relative to the leg, not
        // whether the hubs are currently operational.
        $this->authorizeTransfer($request, $hubTransfer);
        $hubsActive = $this->bothHubsActive($hubTransfer);
        $hubTransfer->load(['company', 'origin', 'destination', 'parcels.delivery', 'events']);

        // Inactive-hub records are read-only: the flags below hide every
        // control, while the mutating endpoints keep their active-hub gate.
        $canManageOrigin = $hubsActive && $this->canManageOriginSide($request, $hubTransfer);
        $canReceiveIncoming = $hubsActive && $this->canReceiveAtDestination($request, $hubTransfer);
        $canViewOriginPrivate = $this->canViewOriginPrivate($request, $hubTransfer);
        if ($canViewOriginPrivate) {
            $hubTransfer->loadMissing(['truck', 'driver']);
        }

        // Assignable fleet and reroute targets are origin-side management data.
        // They are built only for staff physically assigned to the origin hub,
        // so a foreign destination company never receives them.
        $fleetOptions = collect();
        $rerouteHubs = collect();
        if ($canManageOrigin) {
            // Only offer fleet pairs that can actually take over this transfer:
            // a paired, approved four-wheel truck with a free driver. The current
            // truck/driver stay selectable, and this transfer's own active leg is
            // ignored when checking for conflicting manifests.
            $trucks = LogisticsTruck::query()
                ->where('logistics_company_id', $hubTransfer->logistics_company_id)
                ->whereIn('status', [LogisticsTruck::STATUS_AVAILABLE, LogisticsTruck::STATUS_ASSIGNED])
                ->whereDoesntHave('transfers', fn ($query) => $query
                    ->whereIn('status', HubTransfer::ACTIVE_STATUSES)
                    ->whereKeyNot($hubTransfer->id))
                ->orderBy('plate_number')
                ->get()
                ->filter(fn (LogisticsTruck $truck) => $truck->isAssignable())
                ->values();

            $drivers = HubTransportDriver::query()
                ->where('logistics_company_id', $hubTransfer->logistics_company_id)
                ->whereIn('logistics_truck_id', $trucks->pluck('id'))
                ->where(fn ($query) => $query->where('status', 'available')->orWhere('id', $hubTransfer->driver_id))
                ->whereDoesntHave('transfers', fn ($query) => $query
                    ->whereIn('status', HubTransfer::ACTIVE_STATUSES)
                    ->whereKeyNot($hubTransfer->id))
                ->get();

            $fleetOptions = $trucks
                ->map(fn (LogisticsTruck $truck) => ['truck' => $truck, 'driver' => $drivers->firstWhere('logistics_truck_id', $truck->id)])
                ->filter(fn (array $option) => $option['driver'] !== null)
                ->values();
            $rerouteHubs = LogisticsCenter::query()
                ->where('is_active', true)
                ->whereHas('company', fn ($q) => $q->where('status', 'active'))
                ->whereKeyNot($hubTransfer->origin_center_id)
                ->orderBy('name')
                ->get();
        }

        // Parcel-level progress is derived once so the web console and the
        // driver app render the same journey for every manifest entry.
        $parcelProgress = $hubTransfer->parcels
            ->mapWithKeys(fn (HubTransferParcel $entry) => [$entry->id => HubTransferProgress::parcelStage($hubTransfer, $entry)]);
        $manifestParcels = $hubTransfer->parcels->where('loading_status', '!=', 'removed');
        $manifestWeight = $manifestParcels->isNotEmpty()
            && $manifestParcels->every(fn (HubTransferParcel $entry) => $entry->delivery?->weight !== null)
            ? round($manifestParcels->sum(fn (HubTransferParcel $entry) => (float) $entry->delivery->weight), 2)
            : null;

        return view('logistics.hub-transfers.show', [
            'transfer' => $hubTransfer,
            'fleetOptions' => $fleetOptions,
            'rerouteHubs' => $rerouteHubs,
            'parcelProgress' => $parcelProgress,
            'manifestWeight' => $manifestWeight,
            'canManageOrigin' => $canManageOrigin,
            'canReceiveIncoming' => $canReceiveIncoming,
            'canViewOriginPrivate' => $canViewOriginPrivate,
            'hubsActive' => $hubsActive,
        ]);
    }

    public function loadParcel(Request $request, HubTransfer $hubTransfer): RedirectResponse
    {
        abort_unless(LogisticsAccess::canProcessParcels($request->user()), 403, 'Manifest loading is reserved for logistics staff at the origin hub. Management accounts have read-only transfer monitoring.');
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
        abort_unless(LogisticsAccess::canProcessParcels($request->user()), 403, 'Transfer dispatch is reserved for logistics staff at the origin hub. Management accounts have read-only transfer monitoring.');
        $this->authorizeTransfer($request, $hubTransfer);
        $this->assertOriginAccess($request, $hubTransfer);
        HubTransferService::dispatch($hubTransfer, $request->user());

        return back()->with('success', 'Transfer dispatched and marked in transit.');
    }

    public function arrive(Request $request, HubTransfer $hubTransfer): RedirectResponse
    {
        abort_unless(LogisticsAccess::canProcessParcels($request->user()), 403, 'Transfer arrival is recorded by logistics staff at the hub. Management accounts have read-only transfer monitoring.');
        $this->authorizeTransfer($request, $hubTransfer);
        $this->assertDestinationAccess($request, $hubTransfer);
        HubTransferService::arrive($hubTransfer, $request->user());

        return back()->with('success', 'Arrival recorded. Parcels still require individual receiving scans.');
    }

    public function removeParcel(Request $request, HubTransfer $hubTransfer, HubTransferParcel $parcel): RedirectResponse
    {
        abort_unless(LogisticsAccess::canProcessParcels($request->user()), 403, 'Manifest parcel removal is reserved for logistics staff at the origin hub. Management accounts have read-only transfer monitoring.');
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
        abort_unless(LogisticsAccess::canProcessParcels($request->user()), 403, 'Incoming transfer parcels are received by logistics staff at the destination hub. Management accounts have read-only transfer monitoring.');
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
        abort_unless(LogisticsAccess::canProcessParcels($request->user()), 403, 'Receiving manifests are finalized by logistics staff at the destination hub. Management accounts have read-only transfer monitoring.');
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
        $this->assertOriginAccess($request, $hubTransfer);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:1000']]);
        HubTransferService::hold($hubTransfer, $request->user(), $data['reason']);

        return back()->with('success', 'Transfer placed on hold. No parcel movement is allowed until it is resumed.');
    }

    public function resume(Request $request, HubTransfer $hubTransfer): RedirectResponse
    {
        $this->assertOriginAccess($request, $hubTransfer);
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
        abort_unless((int) $truck->logistics_company_id === (int) $hubTransfer->logistics_company_id, 422, 'The truck must belong to the same logistics company as this transfer.');
        abort_unless(
            in_array($truck->status, [LogisticsTruck::STATUS_AVAILABLE, LogisticsTruck::STATUS_ASSIGNED], true),
            422,
            'Truck is not available to this company.'
        );
        abort_unless($truck->isAssignable(), 422, 'This vehicle is not assignable to hub transfers.');
        abort_if($truck->hasActiveTransfer($hubTransfer->id), 409, 'This truck is already assigned to another active hub transfer.');
        $driver = HubTransportDriver::query()
            ->where('logistics_truck_id', $truck->id)
            ->where(fn ($query) => $query->where('status', 'available')->orWhere('id', $hubTransfer->driver_id))
            ->firstOrFail();
        abort_unless($driver->isLicenseValid(), 422, 'Assigned driver does not have an approved, unexpired driver\'s license.');
        abort_if(
            HubTransfer::query()
                ->where('driver_id', $driver->id)
                ->whereIn('status', HubTransfer::ACTIVE_STATUSES)
                ->whereKeyNot($hubTransfer->id)
                ->exists(),
            409,
            'This Hub Transport Driver is already assigned to another active hub transfer.'
        );
        HubTransferService::reassignFleet($hubTransfer, $truck, $driver, $request->user());

        return back()->with('success', 'Truck and driver reassigned for this transfer.');
    }

    public function reroute(Request $request, HubTransfer $hubTransfer): RedirectResponse
    {
        $this->assertOriginAccess($request, $hubTransfer);
        $data = $request->validate([
            'destination_center_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
        $failure = $this->destinationEligibilityFailure((int) $data['destination_center_id']);
        abort_if($failure !== null, 422, $failure);
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

    private function fleetBlockSummary(\Illuminate\Support\Collection $eligibleTrucks, \Illuminate\Support\Collection $pairedTrucks, \Illuminate\Support\Collection $licensedTrucks): string
    {
        $noDriver = $eligibleTrucks->count() - $pairedTrucks->count();
        $invalidLicense = $pairedTrucks->count() - $licensedTrucks->count();
        $unavailableTruck = $licensedTrucks
            ->reject(fn (LogisticsTruck $truck) => $truck->isAssignable())
            ->count();
        $busyDriver = $licensedTrucks
            ->filter(fn (LogisticsTruck $truck) => $truck->isAssignable() && $truck->driver->status !== 'available')
            ->count();
        $busyTruck = $licensedTrucks
            ->filter(fn (LogisticsTruck $truck) => $truck->isAssignable()
                && $truck->driver->status === 'available'
                && $truck->hasActiveTransfer())
            ->count();

        $reasons = [];
        if ($noDriver > 0) {
            $reasons[] = $noDriver.' eligible '.Str::plural('truck', $noDriver).' '.($noDriver === 1 ? 'has' : 'have').' no assigned Hub Transport Driver';
        }
        if ($invalidLicense > 0) {
            $reasons[] = $invalidLicense.' assigned '.Str::plural('driver', $invalidLicense).' '.($invalidLicense === 1 ? 'does' : 'do').' not have an approved, unexpired license';
        }
        if ($unavailableTruck > 0) {
            $reasons[] = $unavailableTruck.' '.Str::plural('truck', $unavailableTruck).' '.($unavailableTruck === 1 ? 'is' : 'are').' in transit, under maintenance, or otherwise not assignable';
        }
        if ($busyDriver > 0) {
            $reasons[] = $busyDriver.' '.Str::plural('driver', $busyDriver).' '.($busyDriver === 1 ? 'is' : 'are').' not marked available';
        }
        if ($busyTruck > 0) {
            $reasons[] = $busyTruck.' '.Str::plural('truck', $busyTruck).' '.($busyTruck === 1 ? 'is' : 'are').' already on an active hub transfer';
        }

        return 'No eligible truck and driver pair is free right now: '.implode('; ', $reasons).'.';
    }

    private function isNetworkAdmin(Request $request): bool
    {
        return LogisticsAccess::isGlobal($request->user());
    }

    /** Historical viewing is allowed on inactive hubs; mutations still require both. */
    private function bothHubsActive(HubTransfer $transfer): bool
    {
        return LogisticsCenter::query()
            ->whereKey($transfer->origin_center_id)
            ->where('is_active', true)
            ->exists()
            && LogisticsCenter::query()
                ->whereKey($transfer->destination_center_id)
                ->where('is_active', true)
                ->exists();
    }

    private function assertCenterAccess(Request $request, int $originId, int $destinationId): void
    {
        $user = $request->user();
        abort_if($originId === $destinationId, 422, 'Origin and destination must differ.');
        abort_unless(
            LogisticsCenter::whereKey($originId)->where('is_active', true)->exists()
            && LogisticsCenter::whereKey($destinationId)->where('is_active', true)->exists(),
            422,
            'Both hubs must be active.'
        );

        if (LogisticsAccess::isGlobal($user)) {
            // Global monitoring may view any active pair, but processing is
            // still blocked by parcel.operator and the staff-only gates.
            return;
        }

        $companyId = LogisticsAccess::companyId($user);
        abort_unless($companyId !== null && $companyId > 0, 403, 'Your account is not assigned to a logistics company.');

        // A leg may cross companies, but the viewer's company must be on at
        // least one end of it.
        $originCompanyId = (int) (LogisticsCenter::whereKey($originId)->value('logistics_company_id') ?? 0);
        $destCompanyId = (int) (LogisticsCenter::whereKey($destinationId)->value('logistics_company_id') ?? 0);
        abort_unless(
            $originCompanyId === $companyId || $destCompanyId === $companyId,
            403,
            'At least one hub must belong to your logistics company.'
        );

        // Null center = company-wide manager; a non-null center is a hub
        // staff restriction that must cover one side of the transfer.
        $centerId = LogisticsAccess::centerId($user);
        if ($centerId === null) {
            abort_unless($user->isCompanyManager(), 403);

            return;
        }

        abort_unless(
            $centerId > 0 && in_array($centerId, [$originId, $destinationId], true),
            403,
            'You may only operate transfers for your assigned hub.'
        );
    }

    private function authorizeTransfer(Request $request, HubTransfer $transfer): void
    {
        $user = $request->user();
        if (LogisticsAccess::isGlobal($user)) {
            return;
        }

        abort_unless(
            (int) $transfer->origin_center_id !== (int) $transfer->destination_center_id,
            404
        );

        $companyId = LogisticsAccess::companyId($user);
        abort_unless($companyId !== null && $companyId > 0, 403, 'Your account is not assigned to a logistics company.');

        $originCompanyId = (int) (LogisticsCenter::whereKey($transfer->origin_center_id)->value('logistics_company_id') ?? 0);
        $destCompanyId = (int) (LogisticsCenter::whereKey($transfer->destination_center_id)->value('logistics_company_id') ?? 0);

        // An account that belongs to neither side must not learn that this
        // transfer exists. Return the same response as a missing transfer.
        abort_unless(
            (int) $transfer->logistics_company_id === $companyId
            || $originCompanyId === $companyId
            || $destCompanyId === $companyId,
            404
        );

        $centerId = LogisticsAccess::centerId($user);
        if ($centerId === null) {
            return;
        }

        abort_unless($centerId > 0, 403, 'Your account is not assigned to a hub.');

        // A hub account outside both ends of the leg is not one of the
        // authorized sides either, so keep the same non-disclosing response.
        abort_unless(
            in_array($centerId, [(int) $transfer->origin_center_id, (int) $transfer->destination_center_id], true),
            404
        );
    }

    private function assertOriginAccess(Request $request, HubTransfer $transfer): void
    {
        $this->authorizeTransfer($request, $transfer);
        $this->assertCenterAccess($request, $transfer->origin_center_id, $transfer->destination_center_id);
        abort_unless(
            LogisticsAccess::canProcessParcels($request->user()),
            403,
            'Transfer lifecycle actions are reserved for logistics staff assigned to the origin hub. Management accounts have read-only transfer monitoring.'
        );

        if (! $this->isNetworkAdmin($request)) {
            $centerId = LogisticsAccess::centerId($request->user());
            if ($centerId !== null) {
                abort_unless($centerId > 0 && $centerId === (int) $transfer->origin_center_id, 403, 'Only origin hub staff can perform this action.');
            }
        }
    }

    private function assertDestinationAccess(Request $request, HubTransfer $transfer): void
    {
        $this->authorizeTransfer($request, $transfer);
        $this->assertCenterAccess($request, $transfer->origin_center_id, $transfer->destination_center_id);
        abort_unless(
            LogisticsAccess::canProcessParcels($request->user()),
            403,
            'Incoming transfer processing is reserved for logistics staff assigned to the destination hub. Management accounts have read-only transfer monitoring.'
        );

        if (! $this->isNetworkAdmin($request)) {
            $centerId = LogisticsAccess::centerId($request->user());
            if ($centerId !== null) {
                abort_unless($centerId > 0 && $centerId === (int) $transfer->destination_center_id, 403, 'Only destination hub staff can perform this action.');
            }
        }
    }

    /**
     * A next-hop hub is eligible when the hub exists, is active, and belongs
     * to a logistics company that is active. Shared-network transfers may
     * cross company boundaries, so the owning company is resolved from the
     * hub itself rather than from client input.
     */
    private function destinationEligibilityFailure(int $centerId): ?string
    {
        $center = LogisticsCenter::query()->whereKey($centerId)->first();
        if (! $center) {
            return 'The selected destination hub does not exist.';
        }
        if (! $center->is_active) {
            return 'The selected destination hub is not active.';
        }

        $companyActive = LogisticsCompany::query()
            ->whereKey($center->logistics_company_id)
            ->where('status', 'active')
            ->exists();

        return $companyActive ? null : 'The selected destination hub belongs to an inactive logistics company.';
    }

    /** Physical origin-side actions (load, remove, dispatch, hold, resume, cancel, reroute, reassign). */
    private function canManageOriginSide(Request $request, HubTransfer $transfer): bool
    {
        $centerId = LogisticsAccess::centerId($request->user());

        return LogisticsAccess::canProcessParcels($request->user())
            && $centerId !== null
            && (int) $centerId === (int) $transfer->origin_center_id;
    }

    /** Physical destination-side actions (arrival, receiving, finalize). */
    private function canReceiveAtDestination(Request $request, HubTransfer $transfer): bool
    {
        $centerId = LogisticsAccess::centerId($request->user());

        return LogisticsAccess::canProcessParcels($request->user())
            && $centerId !== null
            && (int) $centerId === (int) $transfer->destination_center_id;
    }

    /**
     * Fleet, assigned driver and event actor details belong to the origin
     * company. Only global monitoring and the origin company's own accounts
     * may see them; foreign destination staff get the manifest only.
     */
    private function canViewOriginPrivate(Request $request, HubTransfer $transfer): bool
    {
        return LogisticsAccess::canViewOriginPrivate($request->user(), $transfer);
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

        $centerId = LogisticsAccess::centerId($user);
        if ($centerId === null) {
            abort_unless($user->isCompanyManager(), 403, 'Your account does not have a valid transfer scope.');

            // A company manager sees every leg that touches their company,
            // including cross-company legs on either end.
            $query->where(function ($q) use ($companyId): void {
                $q->where('logistics_company_id', $companyId)
                    ->orWhereHas('origin', fn ($center) => $center->where('logistics_company_id', $companyId))
                    ->orWhereHas('destination', fn ($center) => $center->where('logistics_company_id', $companyId));
            });

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
