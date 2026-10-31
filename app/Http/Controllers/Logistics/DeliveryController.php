<?php

namespace App\Http\Controllers\Logistics;

use App\Models\Delivery;
use App\Services\FulfillmentWorkflow;
use App\Models\DeliveryFailure;
use App\Models\DeliveryStatusLog;
use App\Models\LogisticsSetting;
use App\Models\Order;
use App\Models\Rider;
use App\Models\RiderEarning;
use App\Models\RiderNotification;
use App\Models\Notification;
use App\Models\LogisticsCenter;
use App\Models\ServiceArea;
use App\Models\Transaction;
use App\Models\PickupRequest;
use App\Rules\PhilippinePhone;
use App\Services\ReturnToSellerService;
use App\Services\ShipmentExceptionService;
use App\Support\Cod;
use App\Support\CsvExport;
use App\Support\LogisticsAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeliveryController extends Controller
{
    private function perPage(Request $request): int
    {
        return in_array((int) $request->query('per_page'), [10, 25, 50]) ? (int) $request->query('per_page') : 10;
    }

    private function applySearch($query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
        $query->where(function ($w) use ($like, $search) {
            $w->where('tracking_number', 'like', $like)
              ->orWhere('sender_name', 'like', $like)
              ->orWhere('recipient_name', 'like', $like)
              ->orWhere('sender_phone', 'like', $like)
              ->orWhere('recipient_phone', 'like', $like)
              ->orWhere('sender_address', 'like', $like)
              ->orWhere('recipient_address', 'like', $like)
              ->orWhere('notes', 'like', $like)
              ->orWhereHas('rider', fn ($r) => $r->where('name', 'like', $like));

            if (ctype_digit($search)) {
                $w->orWhere('id', (int) $search)
                  ->orWhere('order_id', (int) $search);
            }
        });
    }

    private function filteredQuery(Request $request): array
    {
        $perPage = $this->perPage($request);
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));
        $riderId = (int) $request->query('rider_id', 0);
        $vehicle = trim((string) $request->query('vehicle_type', ''));
        $centerId = (int) $request->query('center_id', 0);
        $serviceAreaId = (int) $request->query('service_area_id', 0);
        $priority = trim((string) $request->query('priority', ''));

        $dir = strtolower($request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = Delivery::with(['rider', 'logisticsCenter', 'destinationCenter', 'serviceArea', 'transaction'])->notArchived();

        // Staff are always restricted to their assigned logistics center. This
        // is enforced at the query level so a query parameter can never bypass
        // it. A staff account without a center is scoped to no rows, never all.
        $staffCenterId = $this->centerScopeId();
        $this->applyCompanyScope($query);
        $this->applyCenterScope($query);

        $this->applySearch($query, $search);

        if ($status === 'exceptions') {
            $query->whereIn('status', ['delivery_failed', 'lost', 'damaged']);
        } elseif (($group = Delivery::statusGroup($status)) !== null) {
            $query->whereIn('status', $group);
        } elseif ($status !== '' && in_array($status, ['waiting_for_rider', 'assigned', 'accepted', 'going_to_pickup', 'arrived_at_shop', 'picked_up', 'out_for_delivery', 'arrived_at_customer', 'delivered', 'delivery_failed', 'lost', 'damaged', 'returned_to_seller', 'cancelled'])) {
            $query->where('status', $status);
        }

        if ($dateFrom !== '' && strtotime($dateFrom)) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo !== '' && strtotime($dateTo)) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($riderId > 0) {
            $query->where('rider_id', $riderId);
        }

        if ($vehicle !== '') {
            $query->whereHas('rider', fn ($r) => $r->whereRaw('LOWER(vehicle_type) = ?', [strtolower($vehicle)]));
        }

        // The requested center can only narrow an already company/center
        // scoped query, so a forged id can never widen access.
        if ($centerId > 0) {
            $query->where('center_id', $centerId);
        }

        if ($serviceAreaId > 0) {
            $query->where('service_area_id', $serviceAreaId);
        }

        if ($priority !== '' && in_array($priority, ['normal', 'high', 'urgent'])) {
            $query->where('priority', $priority);
        }

        $sort = (string) $request->query('sort', '');
        $this->applySort($query, $sort, $dir);

        return compact(
            'query',
            'perPage',
            'search',
            'status',
            'dateFrom',
            'dateTo',
            'riderId',
            'vehicle',
            'centerId',
            'serviceAreaId',
            'priority',
            'sort',
            'dir',
            'staffCenterId'
        );
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filteredQuery($request);

        $rows = $filters['query']
            ->lazy(500)
            ->map(fn (Delivery $delivery) => [
                $delivery->tracking_number,
                $delivery->order_id,
                $delivery->status,
                $delivery->parcel_status,
                $delivery->priority,
                $delivery->sender_name,
                $delivery->recipient_name,
                $delivery->rider?->name,
                $delivery->logisticsCenter?->name,
                $delivery->destinationCenter?->name,
                $delivery->serviceArea?->name,
                $delivery->payment_method,
                $delivery->amount_to_collect,
                $delivery->cod_amount_received,
                $delivery->delivery_fee,
                $delivery->transaction?->amount,
                $delivery->created_at?->format('Y-m-d H:i:s'),
                $delivery->delivered_at?->format('Y-m-d H:i:s'),
                $delivery->returned_at?->format('Y-m-d H:i:s'),
            ]);

        return CsvExport::stream(
            'invoiz-logistics-deliveries-' . now()->format('Y-m-d') . '.csv',
            [
                'Tracking Number',
                'Order ID',
                'Status',
                'Parcel Status',
                'Priority',
                'Sender',
                'Recipient',
                'Rider',
                'Origin Center',
                'Destination Center',
                'Service Area',
                'Payment Method',
                'COD To Collect',
                'Cash Collected',
                'Delivery Fee',
                'Transaction Amount',
                'Created At',
                'Delivered At',
                'Returned At',
            ],
            $rows,
            $request->user(),
            'logistics.deliveries',
            [
                'search_applied' => $filters['search'] !== '',
                'status' => $filters['status'] !== '' ? $filters['status'] : null,
                'date_from' => $filters['dateFrom'] !== '' ? $filters['dateFrom'] : null,
                'date_to' => $filters['dateTo'] !== '' ? $filters['dateTo'] : null,
                'rider_id' => $filters['riderId'] > 0 ? $filters['riderId'] : null,
                'vehicle_type' => $filters['vehicle'] !== '' ? $filters['vehicle'] : null,
                'requested_center_id' => $filters['centerId'] > 0 ? $filters['centerId'] : null,
                'service_area_id' => $filters['serviceAreaId'] > 0 ? $filters['serviceAreaId'] : null,
                'priority' => $filters['priority'] !== '' ? $filters['priority'] : null,
                'sort' => $filters['sort'] !== '' ? $filters['sort'] : 'created_at',
                'direction' => $filters['dir'],
                'center_scope' => $filters['staffCenterId'] !== null ? $filters['staffCenterId'] : 'global',
            ]
        );
    }

    public function index(Request $request): View
    {
        $filters = $this->filteredQuery($request);
        $query = $filters['query'];
        $staffCenterId = $filters['staffCenterId'];

        $deliveries = $query->paginate($filters['perPage'])->withQueryString();

        // Company isolation: accounts only receive rider, service-area, and
        // center options from their own company (and own hub when assigned).
        $riderOptions = Rider::query();
        $serviceAreaOptions = ServiceArea::query();
        $user = Auth::user();
        LogisticsAccess::scopeRiders($riderOptions, $user);
        LogisticsAccess::scopeServiceAreas($serviceAreaOptions, $user);

        $filterCenters = LogisticsCenter::orderBy('name');
        LogisticsAccess::scopeCenters($filterCenters, $user);
        if ($staffCenterId !== null) {
            if ($staffCenterId === 0) {
                $filterCenters->whereRaw('1 = 0');
            } else {
                $filterCenters->whereKey($staffCenterId);
            }
        }

        $archivedQuery = Delivery::query()->whereNotNull('archived_at');
        $this->applyCompanyScope($archivedQuery);
        $this->applyCenterScope($archivedQuery);

        return view('logistics.deliveries.index', [
            'deliveries' => $deliveries,
            'archivedCount' => $archivedQuery->count(),
            'filterRiders' => $riderOptions->orderBy('name')->get(['id', 'name']),
            'vehicleTypes' => array_keys(config('logistics.vehicle_capacities', [])),
            'filterCenters' => $filterCenters->get(['id', 'name']),
            'filterServiceAreas' => $serviceAreaOptions->orderBy('name')->get(['id', 'name', 'logistics_center_id']),
            'filterPriorities' => ['normal', 'high', 'urgent'],
            'staffCenterId' => $staffCenterId,
        ]);
    }

    /**
     * Apply the requested ordering. Column-based sorts use a whitelist; relation
     * sorts use a single LEFT JOIN (each is 1:1 on deliveries, so no rows are
     * duplicated) followed by a select('deliveries.*') to keep only delivery data.
     */
    private function applySort($query, string $sortKey, string $dir): void
    {
        $dir = strtolower($dir) === 'asc' ? 'asc' : 'desc';

        switch ($sortKey) {
            case 'rider':
                $query->leftJoin('riders', 'riders.id', '=', 'deliveries.rider_id')
                      ->orderByRaw('CASE WHEN riders.name IS NULL THEN 1 ELSE 0 END')
                      ->orderBy('riders.name', $dir)
                      ->select('deliveries.*');
                break;

            case 'center':
                $query->leftJoin('logistics_centers', 'logistics_centers.id', '=', 'deliveries.center_id')
                      ->orderByRaw('CASE WHEN logistics_centers.name IS NULL THEN 1 ELSE 0 END')
                      ->orderBy('logistics_centers.name', $dir)
                      ->select('deliveries.*');
                break;

            case 'destination':
                $query->leftJoin('logistics_centers as dest_centers', 'dest_centers.id', '=', 'deliveries.destination_center_id')
                      ->orderByRaw('CASE WHEN dest_centers.name IS NULL THEN 1 ELSE 0 END')
                      ->orderBy('dest_centers.name', $dir)
                      ->select('deliveries.*');
                break;

            case 'service_area':
                $query->leftJoin('service_areas', 'service_areas.id', '=', 'deliveries.service_area_id')
                      ->orderByRaw('CASE WHEN service_areas.name IS NULL THEN 1 ELSE 0 END')
                      ->orderBy('service_areas.name', $dir)
                      ->select('deliveries.*');
                break;

            case 'amount':
                // Delivery->transaction is 1:1 (unique constraint on
                // transactions.delivery_id), so this join cannot duplicate rows.
                $query->leftJoin('transactions', 'transactions.delivery_id', '=', 'deliveries.id')
                      ->orderByRaw('CASE WHEN transactions.amount IS NULL THEN 1 ELSE 0 END')
                      ->orderBy('transactions.amount', $dir)
                      ->select('deliveries.*');
                break;

            default:
                $column = match ($sortKey) {
                    'tracking_number' => 'tracking_number',
                    'sender' => 'sender_name',
                    'recipient' => 'recipient_name',
                    'status' => 'status',
                    'date' => 'created_at',
                    'priority' => 'priority',
                    default => 'created_at',
                };
                $query->orderBy($column, $dir)->orderBy('id', $dir);
                break;
        }
    }

    private function isCenterScopedStaff(): bool
    {
        $user = Auth::user();

        // Center-scoped accounts are logistics staff with an assigned hub.
        // Company managers are company-wide, not center-scoped, and global
        // accounts are unrestricted.
        return (bool) ($user
            && $user->isLogisticsStaff()
            && ! $this->isLogisticsManager()
            && ! $user->isCompanyManager());
    }

    /**
     * Null means no center restriction (global or company-wide manager),
     * 0 means a scoped account with no center, and a positive id means that
     * account's assigned center.
     */
    private function centerScopeId(): ?int
    {
        return LogisticsAccess::centerId(Auth::user());
    }

    private function applyCompanyScope(Builder $query, string $column = 'center_id'): void
    {
        LogisticsAccess::applyCompanyScope($query, Auth::user(), $column);
    }

    private function applyCenterScope(Builder $query, string $column = 'center_id'): void
    {
        LogisticsAccess::applyCenterScope($query, Auth::user(), $column);
    }

    /**
     * Deny cross-company actions on a logistics center. Center-scoped staff
     * are restricted to their own hub by the caller (receiving) or by the
     * existing delivery-center checks; a destination may be any hub inside
     * the same company so intra-company routing keeps working.
     */
    private function assertCompanyCenter(int $centerId): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 403, 'You do not have access to this logistics center.');

        if (LogisticsAccess::isGlobal($user)) {
            return;
        }

        $companyId = LogisticsAccess::companyId($user);

        if ($companyId <= 0) {
            // Staff at a hub without a company assignment may only use their
            // own hub; unowned hubs cannot reach into any company's network.
            $ownCenterId = LogisticsAccess::centerId($user);
            abort_unless($ownCenterId !== null && $ownCenterId > 0 && (int) $ownCenterId === $centerId,
                403, 'Your account is not assigned to a logistics company.');
            return;
        }

        $centerCompanyId = (int) (LogisticsCenter::query()
            ->whereKey($centerId)
            ->value('logistics_company_id') ?? 0);

        abort_unless($centerCompanyId > 0 && $centerCompanyId === $companyId, 403,
            'This logistics center belongs to another logistics company.');
    }

    /**
     * Company-wide rider check: a rider may serve any hub inside the same
     * company, but never another company's parcels.
     */
    private function riderBelongsToUserCompany(?Rider $rider): bool
    {
        return LogisticsAccess::canAccessRider(Auth::user(), $rider);
    }

    private function hasActiveHubTransfer(Delivery $delivery): bool
    {
        return FulfillmentWorkflow::hasActiveHubTransfer($delivery);
    }

    private function ensureCenterAccess(Delivery $delivery, bool $allowIncoming = false): void
    {
        if ($allowIncoming && $delivery->isIncomingTransfer((int) (Auth::user()?->center_id ?? 0))) {
            return;
        }

        abort_unless(LogisticsAccess::canAccessDelivery(Auth::user(), $delivery), 403,
            'You do not have access to deliveries from this logistics center.');
    }

    /**
     * Admin-equivalent for the logistics back office: Platform Administrators
     * and the designated global Logistics Admin.
     */
    private function isLogisticsManager(): bool
    {
        return LogisticsAccess::isGlobal(Auth::user());
    }

    public function show(Delivery $delivery): View
    {
        $this->ensureCenterAccess($delivery, true);

        $delivery->load([
            'statusLogs.changer',
            'proofs.rider',
            'rider',
            'creator',
            'items',
            'logisticsCenter',
            'destinationCenter',
            'serviceArea',
            'sortingCenterHandoffRider',
            'sortingCenterPickupRider',
            'order.buyer',
            'order.items.seller',
            'pickupRequest.center',
            'pickupRequest.reviewer',
            'transaction',
        ]);

        // Read-only order-side context for the unified shipping waybill via Waybill helper.
        $wbContext = \App\Support\Waybill::context($delivery);
        $waybillOrder = $wbContext['waybillOrder'];
        $waybillAddress = $wbContext['waybillAddress'];
        $waybillPayment = $wbContext['waybillPayment'];
        $waybillDiscount = $wbContext['waybillDiscount'];
        $waybillStores = $wbContext['waybillStores'];

        $riderEligibility = $this->riderEligibilityFor($delivery);
        $hasActiveHubTransfer = $this->hasActiveHubTransfer($delivery);

        $centerOptions = LogisticsCenter::where('is_active', true)->orderBy('name');
        $serviceAreaOptions = ServiceArea::where('is_active', true)->orderBy('name');
        $user = Auth::user();
        LogisticsAccess::scopeCenters($centerOptions, $user);
        LogisticsAccess::scopeServiceAreas($serviceAreaOptions, $user);
        return view('logistics.deliveries.show', [
            'delivery' => $delivery,
            'waybillOrder' => $waybillOrder,
            'waybillAddress' => $waybillAddress,
            'waybillPayment' => $waybillPayment,
            'waybillDiscount' => $waybillDiscount,
            'waybillStores' => $waybillStores,
            'riderEligibility' => $riderEligibility,
            'hasActiveHubTransfer' => $hasActiveHubTransfer,
            'failureReasons' => config('logistics.failure_reasons'),
            'cancellationReasons' => config('logistics.cancellation_reasons'),
            'exceptionReasons' => config('logistics.exception_reasons'),
            'returnState' => app(ReturnToSellerService::class)->state($delivery),
            'transitions' => config('logistics.transitions'),
            'centers' => $centerOptions->get(),
            'serviceAreas' => $serviceAreaOptions->get(),
        ]);
    }

    /**
     * Deterministic rider ranking shared by the manual picker and the
     * one-click assisted assignment.
     *
     * Eligible riders are ordered by distance to the pickup point when the
     * delivery and a fresh rider GPS ping both have coordinates. When the
     * pickup coordinates are unavailable the destination is used instead; if
     * neither exists, or the rider has no fresh ping, distance is unavailable
     * and workload (least-loaded), then name decide the order. This is a
     * recommendation only; assignment still runs through the locked,
     * revalidated flow.
     */
    public function riderEligibilityFor(Delivery $delivery)
    {
        $weight = (float) ($delivery->weight ?? 0);
        $riderQuery = Rider::query();
        LogisticsAccess::scopeRiders($riderQuery, Auth::user());

        $targetLat = $delivery->sender_lat !== null ? (float) $delivery->sender_lat : null;
        $targetLng = $delivery->sender_lng !== null ? (float) $delivery->sender_lng : null;
        $distanceBasis = $targetLat !== null && $targetLng !== null ? 'pickup' : null;

        if ($distanceBasis === null) {
            $targetLat = $delivery->recipient_lat !== null ? (float) $delivery->recipient_lat : null;
            $targetLng = $delivery->recipient_lng !== null ? (float) $delivery->recipient_lng : null;
            $distanceBasis = $targetLat !== null && $targetLng !== null ? 'destination' : null;
        }

        $freshAfter = now()->subMinutes(max(1, (int) config('logistics.dispatch.location_fresh_minutes', 30)));
        $freshBefore = now()->addMinutes(5);

        return $riderQuery
            ->withCount(['activeDeliveries as active_deliveries'])
            ->with('latestLocation')
            ->get()
            ->map(function (Rider $rider) use ($weight, $delivery, $targetLat, $targetLng, $distanceBasis, $freshAfter, $freshBefore) {
                $reason = null;
                $activeDeliveries = (int) ($rider->active_deliveries ?? 0);
                $distanceKm = null;
                $locationFresh = false;
                $location = $rider->latestLocation;

                if ($location !== null
                    && $location->recorded_at !== null
                    && $location->recorded_at->gte($freshAfter)
                    && $location->recorded_at->lte($freshBefore)) {
                    $locationFresh = true;

                    if ($targetLat !== null && $targetLng !== null) {
                        $distanceKm = $this->distanceInKm(
                            (float) $location->latitude,
                            (float) $location->longitude,
                            $targetLat,
                            $targetLng,
                        );
                    }
                }

                if (!$rider->isEligibilityApproved()) {
                    $reason = 'Rider application not approved';
                } elseif ($rider->status === 'inactive') {
                    $reason = 'Account inactive';
                } elseif (!$rider->is_online) {
                    $reason = 'Offline - not available for new deliveries';
                } elseif ($rider->vehicle_verification !== 'verified') {
                    $reason = 'Vehicle not verified';
                } elseif (!$rider->vehicleTypeIsActive()) {
                    $reason = 'Vehicle type deactivated';
                } elseif (!$rider->isLastMileVehicle()) {
                    $reason = 'Only approved two-wheel vehicles can receive last-mile deliveries';
                } elseif ($rider->licenseExpired()) {
                    $reason = "Driver's license expired";
                } elseif ($activeDeliveries >= Rider::MAX_ACTIVE_DELIVERIES) {
                    $reason = 'At delivery capacity ('.Rider::MAX_ACTIVE_DELIVERIES.' active)';
                } elseif (!$rider->matchesDestinationCenter($delivery)) {
                    $reason = 'Different logistics center';
                } elseif (!$rider->matchesServiceArea($delivery)) {
                    $reason = 'Different service area';
                } else {
                    $capacity = $rider->capacityLimit();
                    if ($capacity <= 0) {
                        $reason = 'Vehicle capacity is not configured';
                    } elseif ($weight > $capacity) {
                        $reason = "Vehicle capacity insufficient ({$capacity} kg)";
                    }
                }

                return [
                    'rider' => $rider,
                    'eligible' => $reason === null,
                    'reason' => $reason,
                    'capacity' => $rider->capacityLimit(),
                    'is_online' => (bool) $rider->is_online,
                    'active_deliveries' => $activeDeliveries,
                    'distance_km' => $distanceKm !== null ? round($distanceKm, 2) : null,
                    'distance_basis' => $distanceBasis,
                    'distance_sort' => $distanceKm ?? PHP_FLOAT_MAX,
                    'location_fresh' => $locationFresh,
                    'location_recorded_at' => $location?->recorded_at?->toIso8601String(),
                    'score' => $reason === null ? (100 - ($activeDeliveries * 10)) : 0,
                ];
            })
            ->sortBy([
                ['eligible', 'desc'],
                ['distance_sort', 'asc'],
                ['active_deliveries', 'asc'],
                ['rider.name', 'asc'],
            ])
            ->values()
            ->map(function (array $item, int $index) {
                $item['rank'] = $index + 1;
                $item['recommended'] = $item['eligible'] && $index === 0;

                return $item;
            });
    }

    private function distanceInKm(float $fromLat, float $fromLng, float $toLat, float $toLng): float
    {
        $earthRadiusKm = 6371.0088;
        $latDelta = deg2rad($toLat - $fromLat);
        $lngDelta = deg2rad($toLng - $fromLng);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($fromLat)) * cos(deg2rad($toLat)) * sin($lngDelta / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(max(0, 1 - $a)));
    }

    public function create(): View
    {
        if ($this->centerScopeId() === 0) {
            abort(403, 'Your staff account must be assigned to a logistics center first.');
        }

        return view('logistics.deliveries.create');
    }

    private function validateDelivery(Request $request): array
    {
        return $request->validate([
            'sender_name' => 'required|string|max:255',
            'sender_phone' => ['required', new PhilippinePhone],
            'sender_address' => 'required|string|max:500',
            'recipient_name' => 'required|string|max:255',
            'recipient_phone' => ['required', new PhilippinePhone],
            'recipient_address' => 'required|string|max:500',
            'package_type' => 'nullable|in:Document,Parcel,Fragile,Electronics,Groceries,Other',
            'package_description' => 'nullable|string|max:500',
            'weight' => 'nullable|numeric|min:0.01|max:5000',
            'notes' => 'nullable|string|max:1000',
            'estimated_delivery_at' => 'nullable|date|after_or_equal:today',
            'priority' => 'nullable|in:normal,high,urgent',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($this->centerScopeId() === 0) {
            abort(403, 'Your staff account must be assigned to a logistics center first.');
        }

        $validated = $this->validateDelivery($request);

        $validated['status'] = 'waiting_for_rider';
        $validated['priority'] = $validated['priority'] ?? 'normal';
        $validated['created_by'] = Auth::id();
        if ($this->isCenterScopedStaff()) {
            $validated['center_id'] = Auth::user()->center_id;
        }

        $delivery = DB::transaction(function () use ($validated) {
            $delivery = Delivery::create($validated);

            DeliveryStatusLog::create([
                'delivery_id' => $delivery->id,
                'status' => 'waiting_for_rider',
                'notes' => 'Delivery created.',
                'changed_by' => Auth::id(),
            ]);

            PickupRequest::openForDelivery($delivery, notify: false);

            return $delivery;
        });

        Notification::create([
            'type' => 'new_delivery_request',
            'title' => 'New Delivery Request',
            'message' => "Order #{$delivery->id} is ready for pickup and needs a rider.",
            'icon' => '📦',
            'priority' => 'high',
            'center_id' => $delivery->center_id,
            'audience' => $delivery->center_id ? 'center' : 'admin',
            'link' => route('deliveries.show', $delivery),
        ]);

        return redirect()->route('deliveries.show', $delivery)->with('success', 'Delivery created successfully.');
    }

    public function edit(Delivery $delivery): View
    {
        $this->ensureCenterAccess($delivery);

        return view('logistics.deliveries.edit', [
            'delivery' => $delivery,
        ]);
    }

    public function update(Request $request, Delivery $delivery): RedirectResponse
    {
        $this->ensureCenterAccess($delivery);

        $validated = $this->validateDelivery($request);

        $delivery->update($validated);

        return redirect()->route('deliveries.show', $delivery)->with('success', 'Delivery updated successfully.');
    }

    public function autoAssign(Request $request, Delivery $delivery): RedirectResponse
    {
        $this->ensureCenterAccess($delivery);

        if ($delivery->status !== 'waiting_for_rider') {
            return back()->with('error', 'Assisted assignment is only available while the delivery is waiting for a rider.');
        }

        $recommended = $this->riderEligibilityFor($delivery)->firstWhere('recommended', true);

        if (!$recommended) {
            return back()->with('error', 'No eligible rider is available right now. Check rider availability, capacity, center, and service area.');
        }

        // Reuse the existing locked assignment flow so the recommendation is
        // revalidated against fresh data before anything is written.
        return $this->assignRider(
            $request->merge(['rider_id' => $recommended['rider']->id]),
            $delivery
        );
    }

    public function bulkAssignRider(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'rider_id' => 'required|integer|exists:riders,id',
            'delivery_ids' => 'required|array|min:1|max:25',
            'delivery_ids.*' => 'integer',
        ]);

        $ids = array_values(array_unique(array_map('intval', $validated['delivery_ids'])));
        $riderId = (int) $validated['rider_id'];

        $deliveries = Delivery::query()->whereIn('id', $ids);
        $this->applyCompanyScope($deliveries);
        $this->applyCenterScope($deliveries);
        $deliveries = $deliveries->get()->keyBy('id');

        $results = [];
        $assigned = 0;
        $failed = 0;

        foreach ($ids as $id) {
            $delivery = $deliveries->get($id);

            if (!$delivery) {
                $failed++;
                $results[] = [
                    'delivery_id' => $id,
                    'status' => 'failed',
                    'message' => 'Delivery not found or not accessible.',
                ];
                continue;
            }

            $result = $this->assignRiderToDelivery($delivery, $riderId);

            if (isset($result['error'])) {
                $failed++;
                $results[] = [
                    'delivery_id' => $id,
                    'status' => 'failed',
                    'message' => $result['error'],
                ];
                continue;
            }

            if (!empty($result['already_assigned'])) {
                $assigned++;
                $results[] = [
                    'delivery_id' => $id,
                    'status' => 'already_assigned',
                    'message' => 'This rider is already assigned to the delivery.',
                ];
                continue;
            }

            $this->notifyRiderAssigned($result['delivery'], $result['rider']);

            $assigned++;
            $results[] = [
                'delivery_id' => $id,
                'status' => 'assigned',
                'message' => "Assigned to {$result['rider']->name}.",
                'tracking_number' => $result['delivery']->tracking_number,
            ];
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => "{$assigned} assigned, {$failed} failed.",
                'assigned' => $assigned,
                'failed' => $failed,
                'results' => $results,
            ], $failed > 0 ? 207 : 200);
        }

        $response = redirect()->back()
            ->with('success', "{$assigned} ".($assigned === 1 ? 'delivery' : 'deliveries').' assigned.')
            ->with('bulk_assignment_results', $results);

        if ($failed > 0) {
            $response->with('error', "{$failed} ".($failed === 1 ? 'delivery' : 'deliveries').' could not be assigned.');
        }

        return $response;
    }

    public function assignRider(Request $request, Delivery $delivery): RedirectResponse
    {
        $this->ensureCenterAccess($delivery);

        $validated = $request->validate([
            'rider_id' => 'required|exists:riders,id',
        ]);

        $assignment = $this->assignRiderToDelivery($delivery, (int) $validated['rider_id']);

        if (isset($assignment['error'])) {
            return back()->withErrors(['rider_id' => $assignment['error']]);
        }

        if (!empty($assignment['already_assigned'])) {
            return back()->with('success', 'This rider is already assigned to the delivery.');
        }

        $this->notifyRiderAssigned($assignment['delivery'], $assignment['rider']);

        return redirect()->back()->with('success', "Rider {$assignment['rider']->name} assigned successfully.");
    }

    /**
     * Locked, revalidated assignment core shared by single and bulk flows.
     * Bulk calls this once per delivery so one rejection never rolls back
     * assignments that already succeeded.
     */
    private function assignRiderToDelivery(Delivery $delivery, int $riderId): array
    {
        abort_unless(Auth::user()?->canManageRiders(), 403,
            'Rider assignments are managed by logistics personnel.');

        return DB::transaction(function () use ($delivery, $riderId): array {
            // Lock both rows so concurrent assignment posts cannot give a
            // rider two active deliveries or reset a delivery mid-transition.
            $lockedDelivery = FulfillmentWorkflow::lock($delivery);
            if ($this->hasActiveHubTransfer($lockedDelivery)) return ['error' => 'This parcel is reserved for hub-to-hub transfer.'];
            FulfillmentWorkflow::assertReady($lockedDelivery);
            if ($lockedDelivery->order_id && $lockedDelivery->status === 'delivery_failed') {
                abort_unless($lockedDelivery->returned_at && $lockedDelivery->received_at, 409, 'A different rider can only be assigned after the failed parcel is returned to its center.');
            }
            $riderIds = collect([$riderId, $lockedDelivery->rider_id])
                ->filter()
                ->unique()
                ->sort()
                ->values();
            $lockedRiders = Rider::query()
                ->whereIn('id', $riderIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $rider = $lockedRiders->get($riderId);

            if (!$rider) {
                return ['error' => 'The selected rider is no longer available. Refresh and choose another rider.'];
            }

            // Recheck the company/center scope against the locked row so a
            // concurrent ownership change cannot be bypassed after the
            // route-level check.
            abort_unless(LogisticsAccess::canAccessDelivery(Auth::user(), $lockedDelivery), 403,
                'You do not have access to deliveries from this logistics center.');

            // Riders are company-owned through their center; never allow a
            // delivery to be handed to another company's rider.
            if (! $this->riderBelongsToUserCompany($rider)) {
                return ['error' => 'Cannot assign delivery. The selected rider belongs to a different logistics company.'];
            }

            if ($lockedDelivery->archived_at) {
                return ['error' => 'Archived deliveries cannot be assigned. Restore the delivery first.'];
            }

            if (in_array($lockedDelivery->status, ['delivered', 'cancelled'], true)) {
                return ['error' => 'Cannot assign delivery. Delivered and cancelled deliveries cannot be reassigned.'];
            }

            // Reassignment is only supported before the parcel is picked up, or
            // after a failed attempt. A rider may be swapped while the job is
            // still assigned, accepted, or en route to the pickup point, but
            // never once it is in transit. This prevents resetting active
            // in-transit work back to "assigned" through a direct request.
            if (!in_array($lockedDelivery->status, ['waiting_for_rider', 'assigned', 'accepted', 'going_to_pickup', 'arrived_at_shop', 'delivery_failed'], true)) {
                return ['error' => 'This delivery can only be assigned while waiting for a rider, before pickup, or after a failed attempt.'];
            }

            if ((int) $lockedDelivery->rider_id === (int) $rider->id
                && in_array($lockedDelivery->status, ['assigned', 'accepted', 'going_to_pickup', 'arrived_at_shop'], true)) {
                return ['already_assigned' => true];
            }

            if (!$rider->isEligibilityApproved()) {
                return ['error' => 'Cannot assign delivery. Rider application is not approved.'];
            }

            if ($rider->status === 'inactive') {
                return ['error' => 'Cannot assign delivery. Rider account is inactive.'];
            }

            if (!$rider->is_online) {
                return ['error' => 'Cannot assign delivery. Rider is offline and not available for new deliveries.'];
            }

            if ($rider->vehicle_verification !== 'verified') {
                return ['error' => 'Cannot assign delivery. Rider vehicle has not been verified.'];
            }

            if (!$rider->vehicleTypeIsActive()) {
                return ['error' => 'Cannot assign delivery. The rider\'s vehicle type has been deactivated.'];
            }

            if (!$rider->isLastMileVehicle()) {
                return ['error' => 'Cannot assign delivery. Last-mile delivery requires an approved two-wheel vehicle.'];
            }

            if ($rider->licenseExpired()) {
                return ['error' => 'Cannot assign delivery. The rider\'s driver\'s license expired on '
                    .$rider->license_expiry->format('M d, Y').'. Ask the rider to renew and re-verify it.'];
            }

            // Capacity: a rider may hold up to MAX_ACTIVE_DELIVERIES concurrent
            // active deliveries. A failed delivery is not an active status, so
            // retrying the same parcel never counts against the limit.
            if ($rider->activeDeliveryCount() >= Rider::MAX_ACTIVE_DELIVERIES) {
                return ['error' => 'Cannot assign delivery. Rider already has the maximum of '
                    .Rider::MAX_ACTIVE_DELIVERIES.' active deliveries.'];
            }

            $preferences = LogisticsSetting::forUser((int) Auth::id())->delivery ?? [];
            $isReassignment = $lockedDelivery->rider_id
                && (int) $lockedDelivery->rider_id !== (int) $rider->id;

            if ($isReassignment && !($preferences['allow_reassignment'] ?? true)) {
                return ['error' => 'Rider reassignment is disabled in your Delivery Preferences (Settings). Enable "Allow Rider Reassignment" to move this delivery to another rider.'];
            }

            $maxAttempts = (int) ($preferences['max_attempts'] ?? 2);
            $failedAttempts = DeliveryStatusLog::where('delivery_id', $lockedDelivery->id)
                ->where('status', 'delivery_failed')
                ->count();

            if ($failedAttempts >= $maxAttempts) {
                return ['error' => "This delivery has used all {$maxAttempts} of its {$maxAttempts} delivery attempts. Raise \"Maximum Delivery Attempts\" in Settings to allow another retry."];
            }

            $weight = (float) ($lockedDelivery->weight ?? 0);
            $capacity = $rider->capacityLimit();
            if ($capacity <= 0) {
                return ['error' => 'Cannot assign delivery. Rider vehicle capacity is not configured.'];
            }
            if ($weight > 0 && $capacity > 0 && $weight > $capacity) {
                return ['error' => 'Vehicle capacity exceeded. Please assign a suitable vehicle.'];
            }

            if (!$rider->matchesDestinationCenter($lockedDelivery)) {
                return ['error' => 'Cannot assign delivery. Rider belongs to a different logistics center than the delivery destination.'];
            }

            if (!$rider->matchesServiceArea($lockedDelivery)) {
                return ['error' => 'Cannot assign delivery. Rider is assigned to a different service area than the delivery.'];
            }

            $previousRiderId = $lockedDelivery->rider_id;
            if ($lockedDelivery->order_id && $lockedDelivery->received_at) {
                abort_if($lockedDelivery->sorting_center_pickup_at && ! $lockedDelivery->returned_at, 409, 'The current rider still has this parcel. Record its return to center before reassignment.');
                $lockedDelivery->sorting_center_pickup_at = null;
                $lockedDelivery->sorting_center_pickup_rider_id = null;
                $lockedDelivery->accepted_at = null;
                $lockedDelivery->assigned_at = now();
            }

            $lockedDelivery->rider_id = $rider->id;
            $lockedDelivery->status = 'assigned';
            $lockedDelivery->assigned_at = $lockedDelivery->assigned_at ?? now();
            $lockedDelivery->save();

            DeliveryStatusLog::create([
                'delivery_id' => $lockedDelivery->id,
                'status' => 'assigned',
                'notes' => "Assigned to rider {$rider->name} (" . ucfirst($rider->vehicle_type) . ', plate ' . $rider->license_plate . ').',
                'changed_by' => Auth::id(),
            ]);

            $rider->update(['status' => 'delivering']);

            if ($previousRiderId && (int) $previousRiderId !== (int) $rider->id) {
                $previousRider = $lockedRiders->get($previousRiderId);
                $this->syncRiderStatus($previousRider);
            }

            return [
                'delivery' => $lockedDelivery,
                'rider' => $rider,
            ];
        });
    }

    private function notifyRiderAssigned(Delivery $delivery, Rider $rider): void
    {
        Notification::create([
            'type' => 'rider_accepted_delivery',
            'title' => 'Rider Assigned to Delivery',
            'message' => "Rider {$rider->name} was assigned to Order #{$delivery->id}.",
            'icon' => '🛵',
            'priority' => 'normal',
            'center_id' => $delivery->center_id,
            'audience' => $delivery->center_id ? 'center' : 'admin',
            'link' => route('deliveries.show', $delivery),
        ]);

        // In-app rider notification for the newly assigned rider only, after
        // the assignment transaction succeeded. Reassignment notifies the
        // new holder, never the previous one; notifyOnce suppresses
        // duplicates from retried posts.
        RiderNotification::notifyOnce(
            $rider->id,
            'delivery_assigned',
            ['delivery_id' => $delivery->id, 'tracking_number' => $delivery->tracking_number],
            'New Delivery Assignment',
            "You have been assigned delivery {$delivery->tracking_number}.",
        );
    }

    public function updateStatus(Request $request, Delivery $delivery): RedirectResponse
    {
        $this->ensureCenterAccess($delivery);

        $validated = $request->validate([
            'status' => 'required|in:waiting_for_rider,assigned,accepted,going_to_pickup,arrived_at_shop,picked_up,out_for_delivery,arrived_at_customer,delivered,delivery_failed,cancelled',
            'reason' => 'nullable|string',
            'other_reason' => 'nullable|string|min:3|max:500',
            'override_reason' => 'nullable|required_if:override,1|min:10|max:300',
            'override' => 'nullable|boolean',
        ]);

        $target = $validated['status'];
        $current = $delivery->status;
        $isOverride = $request->boolean('override');
        FulfillmentWorkflow::assertActiveOrder($delivery);
        if ($isOverride) {
            abort_unless(Auth::user()->canManageRiders() && Auth::user()->isLogisticsAdmin(), 403, 'Only a logistics administrator can override a rider milestone.');
        }
        if ($delivery->order_id && ! in_array($target, ['cancelled', 'delivery_failed'], true) && ! $isOverride) {
            return back()->with('error', 'Use rider actions for collection and delivery. A logistics administrator may use a documented exception override.');
        }
        if ($delivery->order_id && in_array($target, ['waiting_for_rider', 'assigned', 'accepted', 'going_to_pickup', 'arrived_at_shop'], true)) {
            return back()->with('error', 'Use rider assignment and acceptance actions to change this stage.');
        }
        if ($delivery->order_id && $target === 'delivered' && (! $delivery->rider_id || ! $delivery->proofs()->exists())) {
            return back()->with('error', 'A delivery rider and uploaded proof are required even for an exception override.');
        }


        if ($target === $current) {
            return back()->with('error', 'The delivery is already in this status.');
        }

        if (in_array($current, [...Delivery::EXCEPTION_STATUSES, 'returned_to_seller'], true)) {
            return back()->with('error', 'Lost, damaged, or returned shipments must be handled through the exception and return workflow.');
        }

        $allowed = config("logistics.transitions.$current", []);
        $isValidTransition = in_array($target, $allowed);

        if (!$isValidTransition && !$isOverride) {
            return back()->with('error', "Invalid status transition: cannot change from \"{$current}\" to \"{$target}\". Use the override option with a reason if you are sure.");
        }

        $reasonText = null;

        if ($target === 'delivery_failed') {
            $reasons = config('logistics.failure_reasons');
            $request->validate(['reason' => 'required|in:' . implode(',', $reasons)]);
            $reasonText = $validated['reason'];
            if ($reasonText === 'Other') {
                $request->validate(['other_reason' => 'required|min:3|max:500']);
                $reasonText .= ': ' . $validated['other_reason'];
            }
        }

        if ($target === 'cancelled') {
            $reasons = config('logistics.cancellation_reasons');
            $request->validate(['reason' => 'required|in:' . implode(',', $reasons)]);
            $reasonText = $validated['reason'];
            if ($reasonText === 'Other') {
                $request->validate(['other_reason' => 'required|min:3|max:500']);
                $reasonText .= ': ' . $validated['other_reason'];
            }
        }

        if ($isOverride) {
            $reasonText = trim('Status override: ' . ($validated['override_reason'] ?? ''));
        }

        if ($target === 'delivered') {
            $requireProof = LogisticsSetting::forUser(Auth::id())->delivery['require_proof'] ?? true;

            if ($requireProof && !$delivery->proofs()->exists()) {
                return back()->with('error', 'Proof of delivery is required before marking this delivery as delivered. Upload a photo or signature confirmation first.');
            }
        }

        $codAmount = null;

        if ($target === 'delivered' && Cod::isCod($delivery)) {
            $validated = array_merge($validated, $request->validate([
                'amount_received' => ['required', 'numeric', 'min:0'],
            ], [
                'amount_received.required' => 'Enter the cash amount received from the customer before marking a COD delivery as delivered.',
                'amount_received.numeric' => 'The cash amount received must be a valid number.',
                'amount_received.min' => 'The cash amount received cannot be negative.',
            ]));

            $codAmount = round((float) $validated['amount_received'], 2);
        }

        $previousStatus = $current;
        $conflict = false;

        try {
            DB::transaction(function () use (&$delivery, $target, $reasonText, $isOverride, $previousStatus, $codAmount, &$conflict) {
                $locked = FulfillmentWorkflow::lock($delivery);

                // The route model was read before this transaction. If another
                // request moved the delivery first, refuse this stale write so
                // two completions cannot both write logs or financial rows.
                if ($locked->status !== $previousStatus) {
                    $conflict = true;

                    return;
                }

                $delivery = $locked;
                FulfillmentWorkflow::assertActiveOrder($delivery);
                if ($delivery->order_id && $target === 'cancelled') {
                    \App\Support\OrderCancellation::bySeller($delivery->order, $reasonText ?: 'Cancelled by logistics.', (int) $delivery->seller_id);
                    $delivery->refresh();
                    return;
                }
                if ($delivery->order_id && $target === 'picked_up') {
                    FulfillmentWorkflow::assertReady($delivery);
                    abort_if($delivery->received_at, 409, 'Seller pickup has already completed.');
                }
                if ($delivery->order_id && in_array($target, ['out_for_delivery', 'arrived_at_customer'], true)) {
                    abort_unless($delivery->sorting_center_pickup_at && $delivery->rider_id, 409, 'The delivery rider must collect the dispatched parcel first.');
                }
                $delivery->status = $target;

                $now = now();
                match ($target) {
                    'assigned' => $delivery->assigned_at = $delivery->assigned_at ?? $now,
                    'picked_up' => $delivery->picked_up_at = $now,
                    'delivered' => $delivery->delivered_at = $now,
                    'delivery_failed' => $delivery->failed_at = $now,
                    'cancelled' => $delivery->cancelled_at = $now,
                    default => null,
                };

                if ($target === 'delivery_failed') {
                    $delivery->failure_reason = $reasonText;
                } elseif ($target === 'cancelled') {
                    $delivery->cancellation_reason = $reasonText;
                }

                if ($target === 'delivered' && $codAmount !== null) {
                    $delivery->cod_amount_received = $codAmount;
                    $delivery->cod_received_at = $now;
                }

                $delivery->save();
                if ($delivery->order_id && in_array($target, ['picked_up', 'out_for_delivery', 'delivered'], true)) {
                    FulfillmentWorkflow::syncOrder($delivery, $target === 'delivered' ? 'delivered' : 'out_for_delivery', $reasonText ?: 'Logistics recorded the shipment milestone.');
                }

                if ($target === 'delivery_failed' && $delivery->rider_id) {
                    DeliveryFailure::firstOrCreate(
                        ['delivery_id' => $delivery->id],
                        [
                            'rider_id' => $delivery->rider_id,
                            'reason' => $reasonText ?? 'Delivery failed',
                            'reported_at' => $now,
                        ]
                    );
                }

                DeliveryStatusLog::create([
                    'delivery_id' => $delivery->id,
                    'status' => $target,
                    'notes' => $isOverride
                        ? ($reasonText . " (overridden from \"{$previousStatus}\" by administrator)")
                        : $reasonText,
                    'changed_by' => Auth::id(),
                ]);

                if ($target === 'delivered') {
                    app(TransactionController::class)->storeForDelivery($delivery);
                    // Cash is with the rider now: open the handoff the seller
                    // must verify before the sale settles. Settlement failure
                    // rolls back the delivery transition and status log.
                    \App\Support\Remittances::ensureForDeliveryOrFail($delivery->fresh());

                    if ($delivery->rider_id) {
                        $earned = round((float) ($delivery->delivery_fee ?? Order::SHIPPING_FEE_PER_SELLER), 2);
                        RiderEarning::firstOrCreate(
                            ['delivery_id' => $delivery->id, 'type' => 'delivery'],
                            [
                                'rider_id' => $delivery->rider_id,
                                'amount' => $earned,
                                'earned_on' => now()->toDateString(),
                                'description' => "Delivery {$delivery->tracking_number}",
                            ]
                        );
                    }

                    if ($codAmount !== null) {
                        $expected = Cod::expected($delivery);

                        app(\App\Support\AuditLogger::class)->record(
                            Auth::user(),
                            'cod.collected',
                            'delivery',
                            $delivery->id,
                            [],
                            [
                                'amount_to_collect' => $expected,
                                'amount_received' => $codAmount,
                                'variance' => round($codAmount - (float) $expected, 2),
                            ],
                            null,
                            [
                                'order_id' => $delivery->order_id,
                                'rider_id' => $delivery->rider_id,
                                'tracking_number' => $delivery->tracking_number,
                            ],
                        );
                    }
                }
            });
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Delivery update failed. No changes were saved.');
        }

        if ($conflict) {
            return back()->with('error', 'This delivery was updated by another request. Reload the page and try again.');
        }

        $rider = $delivery->rider;
        $this->syncRiderStatus($rider);

        $riderName = $rider->name ?? 'Rider';
        $statusMessages = [
            'picked_up' => ['Rider Picked Up Order', "{$riderName} has picked up Order #{$delivery->id} from sender.", '📦', 'normal'],
            'out_for_delivery' => ['Out for Delivery', "Order #{$delivery->id} is now out for delivery.", '🚚', 'normal'],
            'delivered' => ['Delivery Completed', "Order #{$delivery->id} was successfully delivered.", '✅', 'normal'],
            'delivery_failed' => ['Delivery Failed', "Order #{$delivery->id} failed. Reason: {$reasonText}", '⚠️', 'high'],
            'cancelled' => ['Delivery Cancelled', "Order #{$delivery->id} was cancelled. Reason: {$reasonText}", '❌', 'high'],
        ];

        if (isset($statusMessages[$target])) {
            [$title, $message, $icon, $priority] = $statusMessages[$target];
            Notification::create([
                'type' => 'delivery_' . $target,
                'title' => $title,
                'message' => $message,
                'icon' => $icon,
                'priority' => $priority,
                'center_id' => $delivery->center_id,
                'audience' => $delivery->center_id ? 'center' : 'admin',
                'link' => route('deliveries.show', $delivery),
            ]);
        }

        // Staff-marked failures affect the assigned rider, who may not know.
        // Rider-initiated failures need no notice (the actor already knows).
        if ($target === 'delivery_failed' && $delivery->rider_id) {
            RiderNotification::notifyOnce(
                $delivery->rider_id,
                'delivery_failed',
                ['delivery_id' => $delivery->id, 'tracking_number' => $delivery->tracking_number],
                'Delivery Failed',
                "Delivery {$delivery->tracking_number} has been marked as failed."
                    . ($reasonText ? " Reason: {$reasonText}" : ''),
            );
        }

        $label = str_replace('_', ' ', $target);

        return redirect()->back()->with('success', ucfirst($label) . ($isOverride ? ' (override applied).' : '.'));
    }

    public function reportException(
        Request $request,
        Delivery $delivery,
        ShipmentExceptionService $exceptions,
    ): RedirectResponse {
        $this->ensureCenterAccess($delivery);

        $validated = $request->validate([
            'type' => 'required|in:lost,damaged',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:8192',
        ]);

        if ($validated['type'] === 'damaged' && ! $request->hasFile('photo')) {
            return back()->withErrors(['photo' => 'A photo is required when reporting a damaged parcel.']);
        }

        $reported = $exceptions->report(
            $delivery,
            $validated['type'],
            $validated['reason'],
            $validated['notes'] ?? null,
            $request->file('photo'),
            $request->user(),
            (string) $request->user()->role,
        );

        $this->syncRiderStatus($reported->rider);

        return back()->with('success', ucfirst($validated['type']).' parcel reported. No refund or stock movement was triggered.');
    }

    public function dispatchReturnToSeller(
        Request $request,
        Delivery $delivery,
        ReturnToSellerService $returns,
    ): RedirectResponse {
        $this->ensureCenterAccess($delivery);

        $validated = $request->validate([
            'reference' => 'nullable|string|max:150',
        ]);

        $returns->dispatch($delivery, $request->user(), $validated['reference'] ?? null);

        return back()->with('success', 'Return dispatch to seller recorded. Stock and refunds wait for seller receipt confirmation.');
    }

    private function syncRiderStatus(?Rider $rider): void
    {
        if (!$rider || $rider->status === 'inactive') {
            return;
        }

        $hasActive = $rider->deliveries()
            ->whereIn('status', Delivery::ACTIVE_STATUSES)
            ->exists();

        $eligible = $rider->vehicle_verification === 'verified'
            && $rider->vehicleTypeIsActive()
            && $rider->isLastMileVehicle()
            && $rider->approved_at !== null
            && !$rider->licenseExpired();

        $status = !$eligible
            ? 'inactive'
            : ($hasActive ? 'delivering' : 'available');

        $rider->update(['status' => $status]);
    }

    public function archive(Request $request, Delivery $delivery): RedirectResponse
    {
        if (!$this->isLogisticsManager() && !Auth::user()->isLogisticsStaff()) {
            abort(403, 'Only logistics staff and administrators can archive deliveries.');
        }
        $this->ensureCenterAccess($delivery);

        $validated = $request->validate([
            'archive_note' => 'nullable|string|max:255',
        ]);

        if ($delivery->archived_at) {
            return back()->with('error', 'This delivery is already archived.');
        }

        $delivery->update([
            'archived_at' => now(),
            'archived_by' => Auth::id(),
            'archive_note' => $validated['archive_note'] ?? null,
        ]);

        DeliveryStatusLog::create([
            'delivery_id' => $delivery->id,
            'status' => 'archived',
            'notes' => 'Delivery archived. ' . ($validated['archive_note'] ?? ''),
            'changed_by' => Auth::id(),
        ]);

        return redirect()->route('deliveries.index')->with('success', 'Delivery archived successfully.');
    }

    public function restore(Request $request, Delivery $delivery): RedirectResponse
    {
        if (!$this->isLogisticsManager() && !Auth::user()->isLogisticsStaff()) {
            abort(403, 'Only logistics staff and administrators can restore deliveries.');
        }
        $this->ensureCenterAccess($delivery);

        if (!$delivery->archived_at) {
            return back()->with('error', 'This delivery is not archived.');
        }

        $delivery->update([
            'archived_at' => null,
            'archived_by' => null,
            'archive_note' => null,
        ]);

        DeliveryStatusLog::create([
            'delivery_id' => $delivery->id,
            'status' => 'restored',
            'notes' => 'Delivery restored from archive.',
            'changed_by' => Auth::id(),
        ]);

        return redirect()->route('deliveries.archived')->with('success', 'Delivery restored successfully.');
    }

    public function destroy(Delivery $delivery): RedirectResponse
    {
        if (!Auth::user()->can('permanentlyDelete')) {
            abort(403, 'Only administrators can permanently delete deliveries. Archive it instead.');
        }

        $delivery->delete();

        return redirect()->route('deliveries.archived')->with('success', 'Delivery permanently deleted.');
    }

    public function receive(Request $request, Delivery $delivery): RedirectResponse
    {
        $validated = $request->validate([
            'center_id' => ['required', 'integer', Rule::exists('logistics_centers', 'id')->where('is_active', true)],
        ]);

        $user = Auth::user();
        $this->assertCompanyCenter((int) $validated['center_id']);
        if ($this->isCenterScopedStaff()
            && (!$user->center_id
                || (int) $user->center_id !== (int) $validated['center_id']
                || ($delivery->center_id !== null && (int) $delivery->center_id !== (int) $user->center_id && ! $delivery->isIncomingTransfer((int) $user->center_id)))) {
            abort(403, 'You can only receive parcels at your assigned logistics center.');
        }

        $received = DB::transaction(function () use ($delivery, $validated): bool {
            $lockedDelivery = FulfillmentWorkflow::lock($delivery);
            abort_if(FulfillmentWorkflow::hasActiveHubTransfer($lockedDelivery), 409, 'Confirm receipt on the active fleet transfer manifest.');
            abort_if($lockedDelivery->status === 'delivered', 409, 'This parcel has already been delivered.');

            if ($lockedDelivery->parcel_status !== 'pending_arrival' && ! $lockedDelivery->isIncomingTransfer((int) $validated['center_id'])) {
                return false;
            }

            if ($lockedDelivery->isIncomingTransfer((int) $validated['center_id'])) {
                $lockedDelivery->update([
                    'center_id' => $validated['center_id'],
                    'scanned_at' => null,
                    'sorted_at' => null,
                    'dispatched_at' => null,
                ]);
                FulfillmentWorkflow::receive($lockedDelivery, (int) Auth::id());
            } else {
                abort_if($lockedDelivery->center_id && (int) $lockedDelivery->center_id !== (int) $validated['center_id'], 409, 'Receive this parcel at its assigned handling center.');
                $lockedDelivery->update(['center_id' => $validated['center_id']]);
                FulfillmentWorkflow::receive($lockedDelivery, (int) Auth::id());
            }
            PickupRequest::where('delivery_id', $lockedDelivery->id)->update(['center_id' => $validated['center_id']]);

            return true;
        });

        if (!$received) {
            return back()->with('error', 'This parcel has already been received.');
        }

        return redirect()->back()->with('success', 'Parcel received and logged.');
    }

    public function scan(Delivery $delivery): RedirectResponse
    {
        $user = Auth::user();
        abort_unless(LogisticsAccess::canAccessDelivery($user, $delivery), 403,
            'You can only scan parcels at your assigned logistics center.');

        $scanned = DB::transaction(function () use ($delivery): bool {
            $lockedDelivery = FulfillmentWorkflow::lock($delivery);
            abort_if($this->hasActiveHubTransfer($lockedDelivery), 409,
                'This parcel remains in fleet-transfer custody until receiving is finalized.');
            if ($lockedDelivery->parcel_status !== 'received') {
                return false;
            }

            $lockedDelivery->update([
                'parcel_status' => 'scanned',
                'scanned_at' => now(),
            ]);

            DeliveryStatusLog::create([
                'delivery_id' => $lockedDelivery->id,
                'status' => 'scanned',
                'notes' => 'Parcel scanned and verified.',
                'changed_by' => Auth::id(),
            ]);

            return true;
        });

        if (!$scanned) {
            return back()->with('error', 'Parcel must be received and not already scanned.');
        }

        return redirect()->back()->with('success', 'Parcel scanned successfully.');
    }

    public function scanPage(Request $request): View
    {
        $expect = strtoupper(trim((string) $request->query('expect', '')));
        $user = Auth::user();

        $expected = null;
        if ($expect !== '' && preg_match('/^TRK-\d{8}-[A-Z0-9]{4}$/', $expect)) {
            $expected = Delivery::notArchived()->where('tracking_number', $expect)->first();
            if ($expected && ! LogisticsAccess::canAccessDelivery($user, $expected)) {
                $expected = null;
            }
        }

        // Registered parcels the operator can scan right now (same center for
        // center-scoped staff). Drives the manual-entry helper and its empty
        // state, so no one has to guess a tracking number.
        $awaitingScanQuery = Delivery::notArchived()
            ->where('parcel_status', 'received');
        $this->applyCompanyScope($awaitingScanQuery);
        $this->applyCenterScope($awaitingScanQuery);
        $awaitingScan = $awaitingScanQuery
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'tracking_number', 'recipient_name']);

        return view('logistics.deliveries.scan', [
            'expect' => $expect,
            'expectedDelivery' => $expected,
            'awaitingScan' => $awaitingScan,
        ]);
    }

    public function scanByTracking(Request $request): JsonResponse
    {
        $rawTracking = strtoupper(trim((string) $request->input('tracking_number', '')));
        $request->merge(['tracking_number' => $rawTracking]);

        $validated = $request->validate([
            'tracking_number' => ['required', 'string', 'max:40', 'regex:/^TRK-\d{8}-[A-Z0-9]{4}$/i'],
        ]);

        $trackingNumber = strtoupper($validated['tracking_number']);

        $delivery = Delivery::notArchived()->where('tracking_number', $trackingNumber)->first();

        if (! $delivery) {
            return response()->json([
                'ok' => false,
                'error' => 'not_found',
                'message' => 'No parcel found with tracking number ' . $trackingNumber . '.',
            ], 404);
        }

        $user = Auth::user();
        if (! LogisticsAccess::canAccessDelivery($user, $delivery)) {
            return response()->json([
                'ok' => false,
                'error' => 'forbidden',
                'message' => 'This parcel belongs to another logistics center.',
            ], 403);
        }

        // The state checks below run again inside a row lock so two
        // near-simultaneous scans of the same parcel cannot both pass the
        // `received` check and write duplicate scanned rows. Responses and
        // transition rules are unchanged.
        return DB::transaction(function () use ($delivery, $user) {
            $fresh = Delivery::whereKey($delivery->id)->lockForUpdate()->first();

            if (! $fresh || $fresh->archived_at) {
                return response()->json([
                    'ok' => false,
                    'error' => 'not_found',
                    'message' => 'No parcel found with tracking number ' . $delivery->tracking_number . '.',
                ], 404);
            }

            if (! LogisticsAccess::canAccessDelivery($user, $fresh)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'forbidden',
                    'message' => 'This parcel belongs to another logistics center.',
                ], 403);
            }

            if ($this->hasActiveHubTransfer($fresh)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'transfer_in_progress',
                    'message' => 'This parcel remains in fleet-transfer custody until receiving is finalized.',
                ], 409);
            }

            if ($fresh->parcel_status === 'scanned') {
                return response()->json([
                    'ok' => true,
                    'already' => true,
                    'message' => 'Parcel was already scanned' . ($fresh->scanned_at ? ' on ' . $fresh->scanned_at->format('M d, Y h:i A') : '') . '.',
                    'delivery' => $this->scanResultPayload($fresh),
                ]);
            }

            if ($fresh->parcel_status !== 'received') {
                return response()->json([
                    'ok' => false,
                    'error' => 'invalid_state',
                    'message' => 'Parcel must be received before scanning (current status: ' . str_replace('_', ' ', $fresh->parcel_status ?? 'pending_arrival') . ').',
                    'delivery' => $this->scanResultPayload($fresh),
                ], 422);
            }

            $fresh->update([
                'parcel_status' => 'scanned',
                'scanned_at' => now(),
            ]);

            DeliveryStatusLog::create([
                'delivery_id' => $fresh->id,
                'status' => 'scanned',
                'notes' => 'Parcel scanned and verified via QR.',
                'changed_by' => Auth::id(),
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Parcel scanned successfully.',
                'delivery' => $this->scanResultPayload($fresh),
            ]);
        });
    }

    private function scanResultPayload(Delivery $delivery): array
    {
        return [
            'tracking_number' => $delivery->tracking_number,
            'recipient_name' => $delivery->recipient_name,
            'parcel_status' => $delivery->parcel_status,
            'scanned_at' => $delivery->scanned_at?->toIso8601String(),
            'show_url' => route('deliveries.show', $delivery, false),
        ];
    }

    public function sort(Request $request, Delivery $delivery): RedirectResponse
    {
        $validated = $request->validate([
            'destination_center_id' => [
                'required',
                'integer',
                Rule::exists('logistics_centers', 'id')->where('is_active', true),
            ],
            'service_area_id' => [
                'required',
                'integer',
                Rule::exists('service_areas', 'id')->where(fn ($query) => $query
                    ->where('logistics_center_id', $request->input('destination_center_id'))
                    ->where('is_active', true)),
            ],
        ]);
        $this->assertCompanyCenter((int) $validated['destination_center_id']);
        $this->ensureCenterAccess($delivery);

        if ($delivery->parcel_status !== 'scanned') {
            return back()->with('error', 'Parcel must be received and scanned before sorting.');
        }

        $user = Auth::user();
        if ($this->isCenterScopedStaff()
            && (!$user->center_id || $delivery->center_id === null || (int) $delivery->center_id !== (int) $user->center_id)) {
            abort(403, 'You can only sort parcels at your assigned logistics center.');
        }

        $destinationName = LogisticsCenter::whereKey($validated['destination_center_id'])->value('name');
        $sortedDelivery = DB::transaction(function () use ($delivery, $validated, $destinationName): ?Delivery {
            $lockedDelivery = FulfillmentWorkflow::lock($delivery);
            abort_if($this->hasActiveHubTransfer($lockedDelivery), 409, 'This parcel is reserved for hub-to-hub transfer.');
            if ($lockedDelivery->parcel_status !== 'scanned') {
                return null;
            }

            $lockedDelivery->update([
                'destination_center_id' => $validated['destination_center_id'],
                'service_area_id' => $validated['service_area_id'],
                'parcel_status' => 'sorted',
                'sorted_at' => now(),
            ]);

            DeliveryStatusLog::create([
                'delivery_id' => $lockedDelivery->id,
                'status' => 'sorted',
                'notes' => 'Parcel sorted. Destination: ' . ($destinationName ?? 'selected center') . '.',
                'changed_by' => Auth::id(),
            ]);

            return $lockedDelivery;
        });

        if (!$sortedDelivery) {
            return back()->with('error', 'Parcel must be received and scanned before sorting.');
        }

        $delivery = $sortedDelivery;

        // A sorted parcel is actionable for its assigned delivery rider.
        // Unassigned parcels notify nobody; later assignment covers them.
        if ($delivery->rider_id) {
            $holdingCenter = $delivery->logisticsCenter?->name ?? 'the sorting center';
            RiderNotification::notifyOnce(
                $delivery->rider_id,
                'parcel_ready',
                ['delivery_id' => $delivery->id, 'tracking_number' => $delivery->tracking_number],
                'Parcel Ready for Pickup',
                "Parcel {$delivery->tracking_number} is sorted and ready for pickup at {$holdingCenter}.",
            );
        }

        return redirect()->back()->with('success', 'Parcel sorted successfully.');
    }

    public function dispatch(Delivery $delivery): RedirectResponse
    {
        $user = Auth::user();
        $dispatched = DB::transaction(function () use ($delivery, $user): bool {
            $lockedDelivery = FulfillmentWorkflow::lock($delivery);
            abort_if($this->hasActiveHubTransfer($lockedDelivery), 409, 'This parcel is reserved for hub-to-hub transfer.');
            abort_unless(LogisticsAccess::canAccessDelivery($user, $lockedDelivery), 403,
                'You do not have access to dispatches from this logistics center.');

            // A parcel may only be dispatched with a rider from the same
            // company, even if the assignment was written before this rule.
            if ($lockedDelivery->rider_id) {
                abort_unless($this->riderBelongsToUserCompany($lockedDelivery->rider), 403,
                    'This delivery is assigned to a rider from another logistics company.');
            }

            FulfillmentWorkflow::assertActiveOrder($lockedDelivery);

            // Routing decision: a parcel sorted for another Logistics Hub must
            // move on a fleet transfer, never as a last-mile dispatch.
            if ($lockedDelivery->destination_center_id
                && (int) $lockedDelivery->destination_center_id !== (int) $lockedDelivery->center_id) {
                abort(409, 'This parcel is routed to another Logistics Hub. Create a fleet hub transfer instead of dispatching it for last-mile delivery.');
            }

            if ($lockedDelivery->order_id) {
                abort_unless($lockedDelivery->received_at && $lockedDelivery->scanned_at && $lockedDelivery->sorted_at, 409, 'Receive, scan and sort this parcel first.');
                abort_unless($lockedDelivery->rider_id && in_array($lockedDelivery->status, ['assigned', 'accepted'], true), 409, 'Assign a delivery rider before dispatch.');
                FulfillmentWorkflow::assertReady($lockedDelivery);
            }
            if ($lockedDelivery->parcel_status === 'dispatched') { return true; }

            if ($lockedDelivery->parcel_status !== 'sorted') {
                return false;
            }

            $lockedDelivery->update([
                'parcel_status' => 'dispatched',
                'dispatched_at' => now(),
            ]);

            DeliveryStatusLog::create([
                'delivery_id' => $lockedDelivery->id,
                'status' => 'dispatched',
                'notes' => 'Parcel dispatched from handling center.',
                'changed_by' => Auth::id(),
            ]);

            return true;
        });

        if (!$dispatched) {
            return back()->with('error', 'Parcel must be sorted before it can be dispatched from the center.');
        }

        return redirect()->back()->with('success', 'Parcel dispatched successfully.');
    }

    public function archived(Request $request): View
    {
        if (!$this->isLogisticsManager() && !Auth::user()->isLogisticsStaff()) {
            abort(403, 'Only logistics staff and administrators can access archived deliveries.');
        }

        $perPage = $this->perPage($request);
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));

        $query = Delivery::with(['rider', 'archiver'])->whereNotNull('archived_at');

        // Staff only ever see their own center's archive; centerless staff
        // accounts receive an empty archive rather than every center's.
        $this->applyCompanyScope($query);
        $this->applyCenterScope($query);

        $this->applySearch($query, $search);

        if ($status !== '') {
            $query->where('status', $status);
        }

        $deliveries = $query->orderByDesc('archived_at')->paginate($perPage)->withQueryString();

        return view('logistics.deliveries.archived', [
            'deliveries' => $deliveries,
        ]);
    }
}


