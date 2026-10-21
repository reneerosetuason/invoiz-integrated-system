<?php

namespace App\Http\Controllers\Logistics;

use App\Models\Delivery;
use App\Models\DeliveryFailure;
use App\Models\DeliveryStatusLog;
use App\Models\LogisticsSetting;
use App\Models\Rider;
use App\Models\RiderNotification;
use App\Models\Notification;
use App\Models\LogisticsCenter;
use App\Models\ServiceArea;
use App\Models\Transaction;
use App\Models\PickupRequest;
use App\Rules\PhilippinePhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

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

    public function index(Request $request): View
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

        $user = Auth::user();
        $staffCenterId = null;

        // Staff are always restricted to their assigned logistics center. This
        // is enforced at the query level so a query parameter can never bypass it.
        if ($this->isCenterScopedStaff()) {
            $staffCenterId = (int) $user->center_id;
            $query->where('center_id', $staffCenterId);
        }

        $this->applySearch($query, $search);

        if ($status !== '' && in_array($status, ['waiting_for_rider', 'assigned', 'accepted', 'going_to_pickup', 'arrived_at_shop', 'picked_up', 'out_for_delivery', 'arrived_at_customer', 'delivered', 'delivery_failed', 'cancelled'])) {
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

        // Center filter is only applied for admins; staff are already scoped above.
        if ($centerId > 0 && $this->isLogisticsManager()) {
            $query->where('center_id', $centerId);
        }

        if ($serviceAreaId > 0) {
            $query->where('service_area_id', $serviceAreaId);
        }

        if ($priority !== '' && in_array($priority, ['normal', 'high', 'urgent'])) {
            $query->where('priority', $priority);
        }

        $this->applySort($query, (string) $request->query('sort', ''), $dir);

        $deliveries = $query->paginate($perPage)->withQueryString();

        // Company isolation: staff never receive another center's rider or
        // service-area options, even though the filters are not required to
        // make the request work.
        $riderOptions = Rider::query();
        $serviceAreaOptions = ServiceArea::query();

        if ($staffCenterId !== null) {
            $riderOptions->where('center_id', $staffCenterId);
            $serviceAreaOptions->where('logistics_center_id', $staffCenterId);
        }

        return view('logistics.deliveries.index', [
            'deliveries' => $deliveries,
            'archivedCount' => Delivery::whereNotNull('archived_at')->count(),
            'filterRiders' => $riderOptions->orderBy('name')->get(['id', 'name']),
            'vehicleTypes' => array_keys(config('logistics.vehicle_capacities', [])),
            'filterCenters' => LogisticsCenter::orderBy('name')->get(['id', 'name']),
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

        return (bool) ($user
            && $user->isLogisticsStaff()
            && !$this->isLogisticsManager()
            && $user->center_id);
    }

    private function ensureCenterAccess(Delivery $delivery): void
    {
        $user = Auth::user();

        if (!$user || !$this->isCenterScopedStaff()) {
            return;
        }

        if ($delivery->center_id !== null && (int) $user->center_id !== (int) $delivery->center_id) {
            abort(403, 'You do not have access to deliveries from this logistics center.');
        }
    }

    /**
     * Admin-equivalent for the logistics back office: the site 'admin' role
     * (or is_admin flag) plus 'logistics_admin'. isAdmin() alone does not
     * cover logistics_admin, so archive flows check this instead.
     */
    private function isLogisticsManager(): bool
    {
        $user = Auth::user();
        return (bool) ($user && ($user->isAdmin() || in_array($user->role, ['admin', 'logistics_admin'], true)));
    }

    public function show(Delivery $delivery): View
    {
        $this->ensureCenterAccess($delivery);

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

        // Read-only order-side context for the unified shipping waybill.
        // No writes; the Buyer/Seller/Order systems are never modified here.
        $waybillOrder = $delivery->order;
        $waybillAddress = null;
        $waybillPayment = null;
        $waybillDiscount = 0.0;
        $waybillStores = collect();
        if ($waybillOrder) {
            $shippingSnapshot = $waybillOrder->shipping_address;
            $waybillAddress = is_array($shippingSnapshot) && $shippingSnapshot !== []
                ? (object) $shippingSnapshot
                : DB::table('addresses')->where('id', $waybillOrder->address_id)->first();
            $waybillPayment = DB::table('payments')
                ->where('order_id', $waybillOrder->id)
                ->first();
            $waybillDiscount = (float) DB::table('order_vouchers')
                ->where('order_id', $waybillOrder->id)
                ->sum('discount_amount');
            $sellerIds = $waybillOrder->items
                ->pluck('seller_id')
                ->filter()
                ->unique()
                ->values();
            if ($sellerIds->isNotEmpty()) {
                $waybillStores = DB::table('sellers')
                    ->whereIn('user_id', $sellerIds)
                    ->get()
                    ->keyBy('user_id');
            }
        }

        $weight = (float) ($delivery->weight ?? 0);

        $viewer = Auth::user();
        $riderQuery = Rider::query();

        if ($this->isCenterScopedStaff() && $viewer->center_id) {
            $riderQuery->where('center_id', $viewer->center_id);
        }

        $riderEligibility = $riderQuery
            ->get()
            ->map(function (Rider $rider) use ($weight, $delivery) {
                $reason = null;

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
                } elseif ($rider->licenseExpired()) {
                    $reason = 'Driver\'s license expired';
                } elseif ($rider->activeDeliveryCount() >= Rider::MAX_ACTIVE_DELIVERIES) {
                    $reason = 'At delivery capacity ('.Rider::MAX_ACTIVE_DELIVERIES.' active)';
                } elseif (!$rider->matchesDestinationCenter($delivery)) {
                    $reason = 'Different logistics center';
                } elseif (!$rider->matchesServiceArea($delivery)) {
                    $reason = 'Different service area';
                } else {
                    $capacity = $rider->capacityLimit();
                    if ($capacity > 0 && $weight > $capacity) {
                        $reason = "Vehicle capacity insufficient ({$capacity} kg)";
                    }
                }

                return [
                    'rider' => $rider,
                    'eligible' => $reason === null,
                    'reason' => $reason,
                    'capacity' => $rider->capacityLimit(),
                    'is_online' => (bool) $rider->is_online,
                ];
            })
            ->sortBy([['eligible', 'desc'], ['is_online', 'desc'], ['rider.name', 'asc']])
            ->values();

        $centerOptions = LogisticsCenter::where('is_active', true)->orderBy('name');
        $user = Auth::user();
        if ($this->isCenterScopedStaff() && $user->center_id) {
            $centerOptions->whereKey($user->center_id);
        }

        return view('logistics.deliveries.show', [
            'delivery' => $delivery,
            'waybillOrder' => $waybillOrder,
            'waybillAddress' => $waybillAddress,
            'waybillPayment' => $waybillPayment,
            'waybillDiscount' => $waybillDiscount,
            'waybillStores' => $waybillStores,
            'riderEligibility' => $riderEligibility,
            'failureReasons' => config('logistics.failure_reasons'),
            'cancellationReasons' => config('logistics.cancellation_reasons'),
            'transitions' => config('logistics.transitions'),
            'centers' => $centerOptions->get(),
            'serviceAreas' => ServiceArea::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
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

            PickupRequest::create([
                'delivery_id' => $delivery->id,
                'requested_at' => now(),
            ]);

            return $delivery;
        });

        Notification::create([
            'type' => 'new_delivery_request',
            'title' => 'New Delivery Request',
            'message' => "Order #{$delivery->id} is ready for pickup and needs a rider.",
            'icon' => '????',
            'priority' => 'high',
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

    public function assignRider(Request $request, Delivery $delivery): RedirectResponse
    {
        $this->ensureCenterAccess($delivery);

        $validated = $request->validate([
            'rider_id' => 'required|exists:riders,id',
        ]);

        $assignment = DB::transaction(function () use ($delivery, $validated): array {
            // Lock both rows so concurrent assignment posts cannot give a
            // rider two active deliveries or reset a delivery mid-transition.
            $lockedDelivery = Delivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            $riderIds = collect([$validated['rider_id'], $lockedDelivery->rider_id])
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
            $rider = $lockedRiders->get($validated['rider_id']);

            if (!$rider) {
                return ['error' => 'The selected rider is no longer available. Refresh and choose another rider.'];
            }

            if ($lockedDelivery->archived_at) {
                return ['error' => 'Archived deliveries cannot be assigned. Restore the delivery first.'];
            }

            if (in_array($lockedDelivery->status, ['delivered', 'cancelled'], true)) {
                return ['error' => 'Cannot assign delivery. Delivered and cancelled deliveries cannot be reassigned.'];
            }

            // Reassignment is only supported before a delivery is in transit,
            // or after a failed attempt. This prevents resetting active work
            // back to "assigned" through a direct request.
            if (!in_array($lockedDelivery->status, ['waiting_for_rider', 'assigned', 'delivery_failed'], true)) {
                return ['error' => 'This delivery can only be assigned while waiting for a rider or after a failed attempt.'];
            }

            if ((int) $lockedDelivery->rider_id === (int) $rider->id && $lockedDelivery->status === 'assigned') {
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
                return ['error' => 'Cannot assign delivery. The rider’s vehicle type has been deactivated.'];
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

            $preferences = LogisticsSetting::forUser(Auth::id())->delivery ?? [];
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

        if (isset($assignment['error'])) {
            return back()->withErrors(['rider_id' => $assignment['error']]);
        }

        if (!empty($assignment['already_assigned'])) {
            return back()->with('success', 'This rider is already assigned to the delivery.');
        }

        $delivery = $assignment['delivery'];
        $rider = $assignment['rider'];

        Notification::create([
            'type' => 'rider_accepted_delivery',
            'title' => 'Rider Assigned to Delivery',
            'message' => "Rider {$rider->name} was assigned to Order #{$delivery->id}.",
            'icon' => '????',
            'priority' => 'normal',
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

        return redirect()->back()->with('success', "Rider {$rider->name} assigned successfully.");
    }

    public function updateStatus(Request $request, Delivery $delivery): RedirectResponse
    {
        $this->ensureCenterAccess($delivery);

        $validated = $request->validate([
            'status' => 'required|in:waiting_for_rider,assigned,accepted,going_to_pickup,arrived_at_shop,picked_up,out_for_delivery,arrived_at_customer,delivered,delivery_failed,cancelled',
            'reason' => 'nullable|string',
            'other_reason' => 'nullable|string|min:3|max:500',
            'override_reason' => 'nullable|required_if:override,1|min:3|max:500',
            'override' => 'nullable|boolean',
        ]);

        $target = $validated['status'];
        $current = $delivery->status;
        $isOverride = $request->boolean('override');

        if ($target === $current) {
            return back()->with('error', 'The delivery is already in this status.');
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

        $rider = $delivery->rider;
        $previousStatus = $current;

        DB::transaction(function () use ($delivery, $target, $reasonText, $isOverride, $previousStatus) {
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

            $delivery->save();

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
        });

        $this->syncRiderStatus($rider);

        if ($target === 'delivered') {
            app(TransactionController::class)->storeForDelivery($delivery);
            // Cash is with the rider now: open the handoff the seller must
            // verify before the sale settles.
            \App\Support\Remittances::ensureForDelivery($delivery->fresh());
        }

        $riderName = $rider->name ?? 'Rider';
        $statusMessages = [
            'picked_up' => ['Rider Picked Up Order', "{$riderName} has picked up Order #{$delivery->id} from sender.", '????', 'normal'],
            'out_for_delivery' => ['Out for Delivery', "Order #{$delivery->id} is now out for delivery.", '????', 'normal'],
            'delivered' => ['Delivery Completed', "Order #{$delivery->id} was successfully delivered.", '???', 'normal'],
            'delivery_failed' => ['Delivery Failed', "Order #{$delivery->id} failed. Reason: {$reasonText}", '??????', 'high'],
            'cancelled' => ['Delivery Cancelled', "Order #{$delivery->id} was cancelled. Reason: {$reasonText}", '????', 'high'],
        ];

        if (isset($statusMessages[$target])) {
            [$title, $message, $icon, $priority] = $statusMessages[$target];
            Notification::create([
                'type' => 'delivery_' . $target,
                'title' => $title,
                'message' => $message,
                'icon' => $icon,
                'priority' => $priority,
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
        if ($this->isCenterScopedStaff()
            && (!$user->center_id || (int) $user->center_id !== (int) $validated['center_id'])) {
            abort(403, 'You can only receive parcels at your assigned logistics center.');
        }

        $received = DB::transaction(function () use ($delivery, $validated): bool {
            $lockedDelivery = Delivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            if ($lockedDelivery->parcel_status !== 'pending_arrival') {
                return false;
            }

            $lockedDelivery->update([
                'center_id' => $validated['center_id'],
                'parcel_status' => 'received',
                'received_at' => now(),
            ]);

            PickupRequest::where('delivery_id', $lockedDelivery->id)->update([
                'center_id' => $validated['center_id'],
            ]);

            DeliveryStatusLog::create([
                'delivery_id' => $lockedDelivery->id,
                'status' => 'received',
                'notes' => 'Parcel received at logistics center.',
                'changed_by' => Auth::id(),
            ]);

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
        if ($this->isCenterScopedStaff()
            && (!$user->center_id || $delivery->center_id === null || (int) $delivery->center_id !== (int) $user->center_id)) {
            abort(403, 'You can only scan parcels at your assigned logistics center.');
        }

        $scanned = DB::transaction(function () use ($delivery): bool {
            $lockedDelivery = Delivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();
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

        $expected = null;
        if ($expect !== '' && preg_match('/^TRK-\d{8}-[A-Z0-9]{4}$/', $expect)) {
            $expected = Delivery::notArchived()->where('tracking_number', $expect)->first();
            if ($expected) {
                $user = Auth::user();
                if ($this->isCenterScopedStaff()
                    && (!$user->center_id || $expected->center_id === null || (int) $user->center_id !== (int) $expected->center_id)) {
                    $expected = null;
                }
            }
        }

        return view('logistics.deliveries.scan', [
            'expect' => $expect,
            'expectedDelivery' => $expected,
        ]);
    }

    public function scanByTracking(Request $request): JsonResponse
    {
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
        if ($this->isCenterScopedStaff()
            && (!$user->center_id || $delivery->center_id === null || (int) $user->center_id !== (int) $delivery->center_id)) {
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

            if ($this->isCenterScopedStaff()
                && (!$user->center_id || $fresh->center_id === null || (int) $user->center_id !== (int) $fresh->center_id)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'forbidden',
                    'message' => 'This parcel belongs to another logistics center.',
                ], 403);
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
            'show_url' => route('deliveries.show', $delivery),
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
            $lockedDelivery = Delivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();
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
        $isCenterStaff = $user->isLogisticsStaff() && ! $user->isLogisticsAdmin() && ! $user->is_admin;
        $dispatched = DB::transaction(function () use ($delivery, $user, $isCenterStaff): bool {
            $lockedDelivery = Delivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            if ($isCenterStaff && (! $user->center_id || ! $lockedDelivery->center_id || $lockedDelivery->center_id != $user->center_id)) {
                abort(403, 'You can only dispatch parcels at your assigned logistics center.');
            }

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

        // Staff only ever see their own center's archive.
        $user = Auth::user();
        if ($this->isCenterScopedStaff()) {
            $query->where('center_id', (int) $user->center_id);
        }

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




