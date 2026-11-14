<?php

namespace App\Http\Controllers\Logistics;

use App\Models\Delivery;
use App\Models\DeliveryStatusLog;
use App\Models\HubTransfer;
use App\Models\HubTransferParcel;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Models\Rider;
use App\Models\ServiceArea;
use App\Models\User;
use App\Services\FulfillmentWorkflow;
use App\Support\LogisticsAccess;
use App\Support\ParcelWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Unified hub operations workspace.
 *
 * Read-only aggregation over the existing parcel pipeline and hub transfers.
 * Every primary action links to the canonical page that performs the mutation,
 * so authorization and validation stay in the existing controllers/services.
 * The assigned hub always comes from the authenticated account; query strings
 * can never widen a staff member's facility scope.
 */
class OperationsController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless(
            $user && LogisticsAccess::canProcessParcels($user),
            403,
            'The Operations workspace is reserved for logistics staff assigned to an active hub.'
        );

        $global = LogisticsAccess::isGlobal($user);
        $companyId = LogisticsAccess::companyId($user);
        $centerId = LogisticsAccess::centerId($user);

        if ($companyId === null || $companyId === 0) {
            abort(403, 'Your account is not assigned to an active logistics company.');
        }
        if ($centerId === null || $centerId <= 0) {
            abort(403, 'Your account is not assigned to an active logistics hub.');
        }

        $company = $companyId ? LogisticsCompany::whereKey($companyId)->first() : null;
        $center = $centerId ? LogisticsCenter::whereKey($centerId)->first() : null;
        $canProcess = LogisticsAccess::canProcessParcels($user);

        $deliveries = Delivery::query()->notArchived();
        $this->scopeDeliveries($deliveries, $companyId, $centerId);

        $transfers = HubTransfer::query();
        $this->scopeTransfers($transfers, $companyId, $centerId);

        $awaitingReceiptQuery = (clone $deliveries)
            ->where('parcel_status', 'pending_arrival')
            ->where(function ($query): void {
                $query->whereNull('order_id')
                    ->orWhere(function ($handoff): void {
                        $handoff->whereNotNull('picked_up_at')
                            ->whereNotNull('sorting_center_handoff_at');
                    });
            });

        $readySortingQuery = (clone $deliveries)
            ->whereIn('parcel_status', ['received', 'scanned']);

        // Sorted for this hub: the next step is last-mile rider assignment.
        $readyRiderQuery = (clone $deliveries)
            ->where('parcel_status', 'sorted')
            ->whereNull('rider_id')
            ->whereNotIn('status', [...Delivery::ACTIVE_STATUSES, 'delivered', 'cancelled', 'damaged', 'lost'])
            ->where(function (Builder $query): void {
                $query->whereNull('destination_center_id')
                    ->orWhereColumn('destination_center_id', 'center_id');
            });

        // Sorted for another hub: the next step is adding the parcel to a
        // fleet transfer manifest, not last-mile assignment.
        $preparingTransferQuery = (clone $deliveries)
            ->where('parcel_status', 'sorted')
            ->whereNull('rider_id')
            ->whereNotIn('status', [...Delivery::ACTIVE_STATUSES, 'delivered', 'cancelled', 'damaged', 'lost'])
            ->whereNotNull('destination_center_id')
            ->whereColumn('destination_center_id', '!=', 'center_id');

        $incomingTransfersQuery = (clone $transfers)
            ->whereIn('status', ['in_transit', 'arrived', 'receiving', 'exception'])
            ->when($centerId, fn ($query) => $query->where('destination_center_id', $centerId))
            ->when(! $centerId && $companyId, fn ($query) => $query->whereHas(
                'destination',
                fn ($destination) => $destination->where('logistics_company_id', $companyId)
            ));

        $outgoingTransfersQuery = (clone $transfers)
            ->whereIn('status', ['loading', 'ready_for_dispatch'])
            ->when($centerId, fn ($query) => $query->where('origin_center_id', $centerId));

        $exceptionEntriesQuery = HubTransferParcel::query()
            ->where('loading_status', '!=', 'removed')
            ->where(function ($query): void {
                $query->where('condition', 'damaged')
                    ->orWhere('receiving_status', 'missing');
            })
            ->whereIn('hub_transfer_id', (clone $transfers)->select('id'));

        // The KPI contract stays the required seven counts; sorted parcels whose
        // next hop is another hub are folded into the action total because their
        // next step is manifest creation, not last-mile assignment.
        $counts = [
            'action_parcels' => (clone $awaitingReceiptQuery)->count()
                + (clone $readySortingQuery)->count()
                + (clone $readyRiderQuery)->count()
                + (clone $preparingTransferQuery)->count(),
            'incoming_transfers' => (clone $incomingTransfersQuery)->count(),
            'in_transit' => (clone $incomingTransfersQuery)->where('status', 'in_transit')->count(),
            'awaiting_receiving' => (clone $incomingTransfersQuery)
                ->whereIn('status', ['arrived', 'receiving', 'exception'])
                ->count(),
            'ready_sorting' => (clone $readySortingQuery)->count(),
            'ready_rider' => (clone $readyRiderQuery)->count(),
            'exceptions' => (clone $exceptionEntriesQuery)->count(),
        ];

        $incomingTransfers = (clone $incomingTransfersQuery)
            ->with(['origin', 'destination'])
            ->withCount([
                'parcels as pending_parcels_count' => fn ($query) => $query
                    ->where('loading_status', '!=', 'removed')
                    ->where('receiving_status', 'pending'),
                'parcels as manifest_parcels_count' => fn ($query) => $query
                    ->where('loading_status', '!=', 'removed'),
            ])
            ->orderByRaw("CASE status WHEN 'exception' THEN 0 WHEN 'arrived' THEN 1 WHEN 'receiving' THEN 2 ELSE 3 END")
            ->latest('id')
            ->limit(10)
            ->get();

        $outgoingTransfers = (clone $outgoingTransfersQuery)
            ->with(['origin', 'destination'])
            ->withCount([
                'parcels as manifest_parcels_count' => fn ($query) => $query
                    ->where('loading_status', '!=', 'removed'),
                'parcels as loaded_parcels_count' => fn ($query) => $query
                    ->where('loading_status', 'loaded'),
            ])
            ->latest('id')
            ->limit(10)
            ->get();

        $exceptionEntries = (clone $exceptionEntriesQuery)
            ->with(['delivery', 'transfer'])
            ->latest('id')
            ->limit(10)
            ->get();

        $parcelRelations = [
            'rider',
            'logisticsCenter',
            'originCenter',
            'destinationCenter',
            'serviceArea',
            'incomingTransferParcel.transfer.origin',
            'latestHubTransferParcel.transfer.origin',
        ];

        $filters = $this->extractFilters($request);
        $hasSearchOrFilter = $filters['search'] !== ''
            || $filters['parcel_status'] !== ''
            || $filters['hub_id'] !== null
            || $filters['date_received'] !== ''
            || $filters['condition'] !== '';
        $hasCustomSort = $filters['sort'] !== '' && $filters['sort'] !== 'default';

        if (! $hasSearchOrFilter && ! $hasCustomSort) {
            $rawParcels = collect()
                ->concat((clone $awaitingReceiptQuery)->with($parcelRelations)->orderByDesc('received_at')->limit(8)->get())
                ->concat((clone $readySortingQuery)->with($parcelRelations)->orderByDesc('received_at')->limit(8)->get())
                ->concat((clone $readyRiderQuery)->with($parcelRelations)->orderByDesc('sorted_at')->limit(8)->get())
                ->concat((clone $preparingTransferQuery)->with($parcelRelations)->orderByDesc('sorted_at')->limit(8)->get())
                ->unique('id')
                ->take(15);
        } else {
            $filteredQuery = (clone $deliveries)
                ->with($parcelRelations)
                // Order-linked parcels that are still pending_arrival must have
                // pickup handoff recorded before appearing in hub operations.
                ->where(function ($query): void {
                    $query->where('parcel_status', '!=', 'pending_arrival')
                        ->orWhereNull('parcel_status')
                        ->orWhereNull('order_id')
                        ->orWhere(function ($handoff): void {
                            $handoff->whereNotNull('picked_up_at')
                                ->whereNotNull('sorting_center_handoff_at');
                        });
                });

            $this->applySearchAndFilters($filteredQuery, $filters);
            $this->applyParcelSorting($filteredQuery, $filters['sort'] ?: 'newest');

            $rawParcels = $filteredQuery->limit(50)->get();
        }

        $actionParcels = $rawParcels->map(function (Delivery $delivery) use ($user, $canProcess, $centerId): array {
            $entry = $delivery->latestHubTransferParcel ?? $delivery->incomingTransferParcel;
            $workflow = ParcelWorkflow::describe($delivery, $entry, $user);
            $condition = self::resolveCondition($delivery);
            $eligibility = $this->parcelBulkEligibility($delivery, $condition, $user, $canProcess, $centerId);

            return [
                'delivery' => $delivery,
                'workflow' => $workflow,
                'condition' => $condition,
                'eligibility' => $eligibility,
                'ineligible_reasons' => $this->parcelBulkIneligibilityReasons($delivery, $condition, $user, $canProcess, $centerId, $eligibility),
            ];
        });

        // Fleet and driver details are origin-company data. Load them only on
        // transfers whose origin belongs to the viewer's company (or for
        // global oversight); foreign destination viewers never receive them.
        $fleetTransfers = $incomingTransfers
            ->toBase()
            ->merge($outgoingTransfers->toBase())
            ->merge($actionParcels->pluck('workflow')->pluck('transfer'))
            ->filter();

        $this->loadOriginFleetForOwner($fleetTransfers, $user, $global);

        $hubOptionsQuery = LogisticsCenter::query()->where('is_active', true)->orderBy('name');
        if (! $global && $companyId) {
            $hubOptionsQuery->where('logistics_company_id', $companyId);
        }
        $hubOptions = $hubOptionsQuery->get();

        $destinationCenters = LogisticsCenter::query()
            ->where('is_active', true)
            ->whereHas('company', fn ($q) => $q->where('status', 'active'))
            ->orderBy('name')
            ->get();

        $serviceAreas = Schema::hasTable('service_areas')
            ? ServiceArea::query()->where('is_active', true)->orderBy('name')->get()
            : collect();

        $eligibleRiders = collect();
        if ($user->canManageRiders() && Schema::hasTable('riders')) {
            $ridersQuery = Rider::query()
                ->with(['logisticsCenter.company', 'serviceArea'])
                ->withCount('activeDeliveries')
                ->where('status', '!=', 'inactive')
                ->orderBy('name');
            LogisticsAccess::scopeRiders($ridersQuery, $user);
            $eligibleRiders = $ridersQuery->get()->filter(function (Rider $rider) use ($companyId, $centerId): bool {
                if ($centerId !== null && $centerId > 0 && (int) $rider->center_id !== (int) $centerId) {
                    return false;
                }
                if ($companyId !== null && $companyId > 0 && $rider->logisticsCenter && (int) $rider->logisticsCenter->logistics_company_id !== (int) $companyId) {
                    return false;
                }

                return $rider->isEligibilityApproved()
                    && $rider->is_online
                    && $rider->vehicle_verification === 'verified'
                    && $rider->vehicleTypeIsActive()
                    && $rider->isLastMileVehicle()
                    && ! $rider->licenseExpired()
                    && $rider->capacityLimit() > 0
                    && ((int) ($rider->active_deliveries_count ?? $rider->activeDeliveryCount())) < Rider::MAX_ACTIVE_DELIVERIES;
            })->values();
        }

        return view('logistics.operations.index', [
            'company' => $company,
            'center' => $center,
            'global' => $global,
            'canProcess' => $canProcess,
            'canManageRiders' => $user->canManageRiders(),
            'counts' => $counts,
            'actionParcels' => $actionParcels,
            'incomingTransfers' => $incomingTransfers,
            'outgoingTransfers' => $outgoingTransfers,
            'exceptionEntries' => $exceptionEntries,
            'scopeLabel' => $this->scopeLabel($global, $company, $center),
            'filters' => $filters,
            'hasActiveFilters' => $hasSearchOrFilter || $hasCustomSort,
            'hubOptions' => $hubOptions,
            'destinationCenters' => $destinationCenters,
            'serviceAreas' => $serviceAreas,
            'eligibleRiders' => $eligibleRiders,
        ]);
    }

    /**
     * Determine parcel condition ('good', 'damaged', 'missing') from delivery
     * status and latest active/completed manifest inspection record.
     */
    public static function resolveCondition(Delivery $delivery): string
    {
        $entry = null;
        try {
            if (Schema::hasTable('hub_transfer_parcels')) {
                $entry = $delivery->relationLoaded('latestHubTransferParcel') || $delivery->relationLoaded('incomingTransferParcel')
                    ? ($delivery->latestHubTransferParcel ?? $delivery->incomingTransferParcel)
                    : ($delivery->incomingTransferParcel()->first() ?? $delivery->latestHubTransferParcel()->first());
            }
        } catch (\Throwable) {
            $entry = null;
        }

        if ($delivery->status === 'lost'
            || ($entry && $entry->loading_status !== 'removed' && ($entry->condition === 'missing' || $entry->receiving_status === 'missing'))) {
            return 'missing';
        }

        if ($delivery->status === 'damaged'
            || ($entry && $entry->loading_status !== 'removed' && $entry->condition === 'damaged')) {
            return 'damaged';
        }

        return 'good';
    }

    /**
     * @return array{search: string, parcel_status: string, hub_id: ?int, date_received: string, condition: string, sort: string}
     */
    private function extractFilters(Request $request): array
    {
        $allowedParcelStatuses = [
            'pending_arrival',
            'received',
            'scanned',
            'sorted',
            'dispatched',
            'awaiting_receiving',
            'ready_sorting',
            'ready_rider',
            'ready_transfer',
        ];
        $allowedConditions = ['good', 'damaged', 'missing'];
        $allowedSorts = ['newest', 'oldest', 'tracking', 'tracking_asc', 'tracking_desc'];

        $parcelStatus = trim((string) $request->query('parcel_status', ''));
        if (! in_array($parcelStatus, $allowedParcelStatuses, true)) {
            $parcelStatus = '';
        }

        $condition = strtolower(trim((string) $request->query('condition', '')));
        if (! in_array($condition, $allowedConditions, true)) {
            $condition = '';
        }

        $sort = strtolower(trim((string) $request->query('sort', '')));
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = '';
        }

        $dateReceived = trim((string) $request->query('date_received', ''));
        if ($dateReceived !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateReceived)) {
            $dateReceived = '';
        }

        $hubIdRaw = $request->query('hub_id');
        $hubId = is_numeric($hubIdRaw) && (int) $hubIdRaw > 0 ? (int) $hubIdRaw : null;

        return [
            'search' => trim((string) $request->query('search', '')),
            'parcel_status' => $parcelStatus,
            'hub_id' => $hubId,
            'date_received' => $dateReceived,
            'condition' => $condition,
            'sort' => $sort,
        ];
    }

    private function applySearchAndFilters(Builder $query, array $filters): void
    {
        if ($filters['search'] !== '') {
            $term = $filters['search'];
            $like = '%'.$term.'%';
            $numericId = preg_match('/^#?(\d+)$/', $term, $matches) ? (int) $matches[1] : null;

            $query->where(function (Builder $search) use ($like, $numericId): void {
                $search->where('tracking_number', 'like', $like)
                    ->orWhere('sender_name', 'like', $like)
                    ->orWhere('recipient_name', 'like', $like)
                    ->orWhereHas('logisticsCenter', fn (Builder $center) => $center->where('name', 'like', $like)->orWhere('code', 'like', $like))
                    ->orWhereHas('originCenter', fn (Builder $origin) => $origin->where('name', 'like', $like)->orWhere('code', 'like', $like))
                    ->orWhereHas('destinationCenter', fn (Builder $dest) => $dest->where('name', 'like', $like)->orWhere('code', 'like', $like))
                    ->orWhereHas('rider', fn (Builder $rider) => $rider->where('name', 'like', $like));

                if ($numericId !== null && $numericId > 0) {
                    $search->orWhere('deliveries.id', $numericId);
                }
            });
        }

        if ($filters['parcel_status'] !== '') {
            match ($filters['parcel_status']) {
                'awaiting_receiving' => $query->where('parcel_status', 'pending_arrival'),
                'ready_sorting' => $query->whereIn('parcel_status', ['received', 'scanned']),
                'ready_rider' => $query->where('parcel_status', 'sorted')
                    ->whereNull('rider_id')
                    ->whereNotIn('status', [...Delivery::ACTIVE_STATUSES, 'delivered', 'cancelled', 'damaged', 'lost'])
                    ->where(function (Builder $local): void {
                        $local->whereNull('destination_center_id')
                            ->orWhereColumn('destination_center_id', 'center_id');
                    }),
                'ready_transfer' => $query->where('parcel_status', 'sorted')
                    ->whereNull('rider_id')
                    ->whereNotIn('status', [...Delivery::ACTIVE_STATUSES, 'delivered', 'cancelled', 'damaged', 'lost'])
                    ->whereNotNull('destination_center_id')
                    ->whereColumn('destination_center_id', '!=', 'center_id'),
                default => $query->where('parcel_status', $filters['parcel_status']),
            };
        }

        if ($filters['hub_id'] !== null) {
            $hubId = $filters['hub_id'];
            $query->where(function (Builder $hub) use ($hubId): void {
                $hub->where('center_id', $hubId)
                    ->orWhere('origin_center_id', $hubId)
                    ->orWhere('destination_center_id', $hubId);
            });
        }

        if ($filters['date_received'] !== '') {
            $query->whereDate('received_at', $filters['date_received']);
        }

        if ($filters['condition'] !== '') {
            if ($filters['condition'] === 'damaged') {
                $query->where(function (Builder $damaged): void {
                    $damaged->where('status', 'damaged')
                        ->orWhereHas('hubTransferParcels', fn (Builder $hp) => $hp
                            ->where('loading_status', '!=', 'removed')
                            ->where('condition', 'damaged'));
                });
            } elseif ($filters['condition'] === 'missing') {
                $query->where(function (Builder $missing): void {
                    $missing->where('status', 'lost')
                        ->orWhereHas('hubTransferParcels', fn (Builder $hp) => $hp
                            ->where('loading_status', '!=', 'removed')
                            ->where(fn (Builder $sq) => $sq
                                ->where('condition', 'missing')
                                ->orWhere('receiving_status', 'missing')));
                });
            } elseif ($filters['condition'] === 'good') {
                $query->whereNotIn('status', ['damaged', 'lost'])
                    ->whereDoesntHave('hubTransferParcels', fn (Builder $hp) => $hp
                        ->where('loading_status', '!=', 'removed')
                        ->where(fn (Builder $sq) => $sq
                            ->whereIn('condition', ['damaged', 'missing'])
                            ->orWhere('receiving_status', 'missing')));
            }
        }
    }

    private function applyParcelSorting(Builder $query, string $sort): void
    {
        match ($sort) {
            'oldest' => $query->orderByRaw('CASE WHEN received_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('received_at')
                ->orderBy('id'),
            'tracking', 'tracking_asc' => $query->orderBy('tracking_number')->orderBy('id'),
            'tracking_desc' => $query->orderByDesc('tracking_number')->orderByDesc('id'),
            default => $query->orderByRaw('CASE WHEN received_at IS NULL THEN 1 ELSE 0 END')
                ->orderByDesc('received_at')
                ->orderByDesc('id'),
        };
    }

    /**
     * @return array{receive: bool, sort: bool, assign_rider: bool, transfer: bool}
     */
    private function parcelBulkEligibility(
        Delivery $delivery,
        string $condition,
        User $user,
        bool $canProcess,
        ?int $centerId = null
    ): array {
        $centerId = $centerId ?? LogisticsAccess::centerId($user);
        $atAssignedCenter = $centerId !== null
            && $centerId > 0
            && (int) $delivery->center_id === (int) $centerId
            && LogisticsAccess::canAccessDelivery($user, $delivery);

        $isGood = $condition === 'good';
        $notArchived = $delivery->archived_at === null;
        $hasActiveTransfer = FulfillmentWorkflow::hasActiveHubTransfer($delivery);
        $hasHandoff = $this->hasRequiredPickupHandoff($delivery);
        $isPostHubOrTerminal = in_array(
            $delivery->status,
            ['out_for_delivery', 'arrived_at_customer', 'delivered', 'cancelled'],
            true
        ) || $delivery->delivered_at !== null;
        $isTerminalOrActive = in_array(
            $delivery->status,
            [...Delivery::ACTIVE_STATUSES, 'delivered', 'cancelled', 'damaged', 'lost'],
            true
        ) || $delivery->delivered_at !== null;

        return [
            'receive' => $canProcess
                && $atAssignedCenter
                && $notArchived
                && $isGood
                && ! $hasActiveTransfer
                && $hasHandoff
                && ! in_array($delivery->status, ['delivered', 'cancelled', 'damaged', 'lost'], true)
                && $delivery->delivered_at === null
                && $delivery->received_at === null
                && $delivery->parcel_status === 'pending_arrival',
            'sort' => $canProcess
                && $atAssignedCenter
                && $notArchived
                && $isGood
                && ! $hasActiveTransfer
                && $hasHandoff
                && ! $isPostHubOrTerminal
                && $delivery->parcel_status === 'scanned',
            'assign_rider' => $user->canManageRiders()
                && ($centerId === null || $atAssignedCenter)
                && $notArchived
                && $isGood
                && ! $hasActiveTransfer
                && ! $isTerminalOrActive
                && $delivery->parcel_status === 'sorted'
                && $delivery->rider_id === null
                && ($delivery->destination_center_id === null
                    || (int) $delivery->destination_center_id === (int) $delivery->center_id)
                && LogisticsAccess::canAccessDelivery($user, $delivery),
            'transfer' => $canProcess
                && $atAssignedCenter
                && $notArchived
                && $isGood
                && ! $hasActiveTransfer
                && ! $isTerminalOrActive
                && $delivery->parcel_status === 'sorted'
                && $delivery->rider_id === null
                && $delivery->destination_center_id !== null
                && (int) $delivery->destination_center_id !== (int) $delivery->center_id,
        ];
    }

    /**
     * @param  array{receive: bool, sort: bool, assign_rider: bool, transfer: bool}  $eligibility
     * @return array{receive: ?string, sort: ?string, assign_rider: ?string, transfer: ?string}
     */
    private function parcelBulkIneligibilityReasons(
        Delivery $delivery,
        string $condition,
        User $user,
        bool $canProcess,
        ?int $centerId,
        array $eligibility
    ): array {
        $centerId = $centerId ?? LogisticsAccess::centerId($user);
        $atAssignedCenter = $centerId !== null
            && $centerId > 0
            && (int) $delivery->center_id === (int) $centerId
            && LogisticsAccess::canAccessDelivery($user, $delivery);

        $commonReason = null;
        if ($delivery->archived_at !== null) {
            $commonReason = 'Parcel is archived.';
        } elseif (! $atAssignedCenter && $centerId !== null) {
            $commonReason = 'Parcel is physically at another hub ('.($delivery->logisticsCenter?->name ?? 'foreign hub').').';
        } elseif ($condition !== 'good') {
            $commonReason = ucfirst($condition).' parcel requires individual condition verification.';
        } elseif (FulfillmentWorkflow::hasActiveHubTransfer($delivery)) {
            $commonReason = 'Locked in an active hub transfer manifest.';
        } elseif (in_array($delivery->status, ['delivered', 'cancelled'], true) || $delivery->delivered_at !== null) {
            $commonReason = 'Parcel is already '.$delivery->status.'.';
        }

        $receiveReason = null;
        if (! $eligibility['receive']) {
            if (! $canProcess) {
                $receiveReason = 'Receiving requires hub-assigned staff permissions.';
            } elseif ($commonReason !== null) {
                $receiveReason = $commonReason;
            } elseif (! $this->hasRequiredPickupHandoff($delivery)) {
                $receiveReason = 'Awaiting seller pickup and sorting center handoff.';
            } elseif ($delivery->received_at !== null || in_array($delivery->parcel_status, ['received', 'scanned', 'sorted', 'dispatched'], true)) {
                $receiveReason = 'Already received at this hub ('.($delivery->parcel_status === 'received' ? 'scan parcel next' : $delivery->parcel_status).').';
            } else {
                $receiveReason = 'Parcel is not awaiting hub receipt.';
            }
        }

        $sortReason = null;
        if (! $eligibility['sort']) {
            if (! $canProcess) {
                $sortReason = 'Sorting requires hub-assigned staff permissions.';
            } elseif ($commonReason !== null) {
                $sortReason = $commonReason;
            } elseif ($delivery->parcel_status === 'pending_arrival') {
                $sortReason = 'Must be received and scanned before sorting.';
            } elseif ($delivery->parcel_status === 'received') {
                $sortReason = 'Must be scanned before assigning to a sorting batch.';
            } elseif ($delivery->parcel_status === 'sorted') {
                $sortReason = 'Already sorted ('.($delivery->destinationCenter?->name ? 'to '.$delivery->destinationCenter->name : 'local hub').').';
            } elseif ($delivery->parcel_status === 'dispatched') {
                $sortReason = 'Already dispatched.';
            } else {
                $sortReason = 'Must be in scanned state before sorting.';
            }
        }

        $transferReason = null;
        if (! $eligibility['transfer']) {
            if (! $canProcess) {
                $transferReason = 'Transfer preparation requires hub-assigned staff permissions.';
            } elseif ($commonReason !== null) {
                $transferReason = $commonReason;
            } elseif ($delivery->rider_id !== null || in_array($delivery->status, Delivery::ACTIVE_STATUSES, true)) {
                $transferReason = 'Already assigned to a delivery rider.';
            } elseif ($delivery->parcel_status !== 'sorted') {
                $transferReason = 'Must be received, scanned, and sorted for another hub before transfer.';
            } elseif ($delivery->destination_center_id === null || (int) $delivery->destination_center_id === (int) $delivery->center_id) {
                $transferReason = 'Sorted for local delivery at this hub (not an inter-hub transfer).';
            } else {
                $transferReason = 'Ineligible for bulk hub transfer.';
            }
        }

        $riderReason = null;
        if (! $eligibility['assign_rider']) {
            if (! $user->canManageRiders()) {
                $riderReason = 'Rider assignment requires authorized personnel.';
            } elseif ($commonReason !== null) {
                $riderReason = $commonReason;
            } elseif ($delivery->rider_id !== null || in_array($delivery->status, Delivery::ACTIVE_STATUSES, true)) {
                $riderReason = 'Already assigned to '.($delivery->rider?->name ?? 'a rider').'.';
            } elseif ($delivery->parcel_status !== 'sorted') {
                $riderReason = 'Must be sorted for local delivery before assigning a last-mile rider.';
            } elseif ($delivery->destination_center_id !== null && (int) $delivery->destination_center_id !== (int) $delivery->center_id) {
                $riderReason = 'Routed to destination hub '.($delivery->destinationCenter?->name ?? ('#'.$delivery->destination_center_id)).' — requires hub transfer first.';
            } else {
                $riderReason = 'Ineligible for last-mile rider assignment.';
            }
        }

        return [
            'receive' => $receiveReason,
            'sort' => $sortReason,
            'assign_rider' => $riderReason,
            'transfer' => $transferReason,
        ];
    }

    private function hasRequiredPickupHandoff(Delivery $delivery): bool
    {
        if (! $delivery->order_id) {
            return true;
        }

        // Once a parcel has already been received at a hub (received_at set or
        // parcel_status advanced past pending_arrival), hub custody is
        // already established (matching ParcelWorkflow::describe and
        // FulfillmentWorkflow::assertReady).
        if ($delivery->received_at !== null
            || in_array($delivery->parcel_status, ['received', 'scanned', 'sorted', 'dispatched'], true)) {
            return true;
        }

        return FulfillmentWorkflow::hasPickupHandoff($delivery);
    }

    /**
     * Bulk Receive eligible parcels awaiting hub receipt.
     *
     * Validates each parcel individually inside a row-locked transaction so
     * duplicate receiving, active transfer manifests, missing pickup handoffs,
     * and damaged/missing parcels (which require individual condition
     * verification) are rejected with per-parcel feedback.
     */
    public function bulkReceive(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user && LogisticsAccess::canProcessParcels($user),
            403,
            'Parcel receiving is reserved for hub-assigned logistics staff.'
        );

        $centerId = LogisticsAccess::centerId($user);
        abort_unless($centerId !== null && $centerId > 0, 403, 'Your account is not assigned to an active logistics hub.');

        $validated = $request->validate([
            'confirmed' => 'accepted',
            'delivery_ids' => 'required|array|min:1|max:50',
            'delivery_ids.*' => 'integer',
        ]);

        $ids = array_values(array_unique(array_map('intval', $validated['delivery_ids'])));
        $results = [];
        $succeeded = 0;
        $failed = 0;

        foreach ($ids as $id) {
            $outcome = DB::transaction(function () use ($id, $user, $centerId): array {
                $locked = Delivery::query()->whereKey($id)->lockForUpdate()->first();

                if (! $locked || $locked->archived_at !== null || (int) $locked->center_id !== (int) $centerId || ! LogisticsAccess::canAccessDelivery($user, $locked)) {
                    return [
                        'delivery_id' => $id,
                        'tracking_number' => null,
                        'status' => 'failed',
                        'message' => 'Parcel not found or outside your assigned hub scope.',
                    ];
                }

                $tracking = $locked->tracking_number;

                if (in_array($locked->status, ['delivered', 'cancelled'], true)) {
                    return [
                        'delivery_id' => $id,
                        'tracking_number' => $tracking,
                        'status' => 'failed',
                        'message' => 'Delivered or cancelled parcels cannot be received.',
                    ];
                }

                $condition = self::resolveCondition($locked);
                if ($condition !== 'good') {
                    return [
                        'delivery_id' => $id,
                        'tracking_number' => $tracking,
                        'status' => 'failed',
                        'message' => 'Damaged or missing parcels require individual condition verification before receiving.',
                    ];
                }

                if ($locked->received_at !== null || in_array($locked->parcel_status, ['received', 'scanned', 'sorted', 'dispatched'], true)) {
                    return [
                        'delivery_id' => $id,
                        'tracking_number' => $tracking,
                        'status' => 'failed',
                        'message' => 'Duplicate receive blocked: parcel has already been received at this hub.',
                    ];
                }

                if ($locked->parcel_status !== 'pending_arrival') {
                    return [
                        'delivery_id' => $id,
                        'tracking_number' => $tracking,
                        'status' => 'failed',
                        'message' => 'Parcel is not awaiting hub receipt.',
                    ];
                }

                if (FulfillmentWorkflow::hasActiveHubTransfer($locked)) {
                    return [
                        'delivery_id' => $id,
                        'tracking_number' => $tracking,
                        'status' => 'failed',
                        'message' => 'Parcel is in an active hub transfer manifest. Receive and inspect it from the transfer manifest.',
                    ];
                }

                if (! $this->hasRequiredPickupHandoff($locked)) {
                    return [
                        'delivery_id' => $id,
                        'tracking_number' => $tracking,
                        'status' => 'failed',
                        'message' => 'Seller pickup and sorting center handoff must be recorded before receiving.',
                    ];
                }

                if ($locked->order_id && Schema::hasTable('orders') && $locked->order()->whereIn('status', ['cancelled', 'refunded'])->exists()) {
                    return [
                        'delivery_id' => $id,
                        'tracking_number' => $tracking,
                        'status' => 'failed',
                        'message' => 'Order was cancelled or refunded.',
                    ];
                }

                $locked->update([
                    'received_at' => $locked->received_at ?? now(),
                    'parcel_status' => 'received',
                ]);

                DeliveryStatusLog::create([
                    'delivery_id' => $locked->id,
                    'status' => 'received_at_center',
                    'changed_by' => (int) $user->id,
                    'notes' => 'Parcel received at sorting center via Operations bulk receive.',
                ]);

                return [
                    'delivery_id' => $id,
                    'tracking_number' => $tracking,
                    'status' => 'succeeded',
                    'message' => 'Received at hub and logged.',
                ];
            });

            if ($outcome['status'] === 'succeeded') {
                $succeeded++;
            } else {
                $failed++;
            }

            $results[] = $outcome;
        }

        return $this->respondBulkAction($request, 'receive', $succeeded, $failed, $results);
    }

    /**
     * Bulk Assign to Sorting Batch for scanned parcels at the operator's hub.
     */
    public function bulkSort(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user && LogisticsAccess::canProcessParcels($user),
            403,
            'Parcel sorting is reserved for hub-assigned logistics staff.'
        );

        $centerId = LogisticsAccess::centerId($user);
        abort_unless($centerId !== null && $centerId > 0, 403, 'Your account is not assigned to an active logistics hub.');
        $currentCenter = LogisticsCenter::whereKey($centerId)->first();
        $currentCenterName = $currentCenter?->name ?? ('Hub #'.$centerId);

        $validated = $request->validate([
            'confirmed' => 'accepted',
            'delivery_ids' => 'required|array|min:1|max:50',
            'delivery_ids.*' => 'integer',
            'current_center_id' => 'nullable|integer',
            'sorting_center_id' => 'nullable|integer',
            'destination_center_id' => 'required|integer',
            'service_area_id' => 'nullable|integer',
        ]);

        $requestedSortingHub = $validated['sorting_center_id'] ?? $validated['current_center_id'] ?? null;
        if ($requestedSortingHub !== null && (int) $requestedSortingHub !== (int) $centerId) {
            return $request->expectsJson()
                ? response()->json([
                    'message' => "Sorting must happen at your assigned current hub ({$currentCenterName}). Do not select an unrelated hub as the sorting location.",
                    'errors' => ['sorting_center_id' => ["Sorting location must match your current hub ({$currentCenterName})."]],
                ], 422)
                : back()->withErrors(['sorting_center_id' => "Sorting must happen at your assigned current hub ({$currentCenterName})."]);
        }

        $destinationCenter = LogisticsCenter::query()
            ->whereKey((int) $validated['destination_center_id'])
            ->where('is_active', true)
            ->whereHas('company', fn ($q) => $q->where('status', 'active'))
            ->first();

        if (! $destinationCenter) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Select an active destination hub belonging to an active logistics company.', 'errors' => ['destination_center_id' => ['Select an active destination hub.']]], 422)
                : back()->withErrors(['destination_center_id' => 'Select an active destination hub belonging to an active logistics company.']);
        }

        $serviceAreaId = isset($validated['service_area_id']) && (int) $validated['service_area_id'] > 0
            ? (int) $validated['service_area_id']
            : null;
        if (Schema::hasTable('service_areas')) {
            $centerAreasQuery = ServiceArea::query()
                ->where('logistics_center_id', $destinationCenter->id)
                ->where('is_active', true);

            if ($serviceAreaId !== null || (clone $centerAreasQuery)->exists()) {
                $serviceArea = (clone $centerAreasQuery)
                    ->whereKey((int) $serviceAreaId)
                    ->first();

                if (! $serviceArea) {
                    return $request->expectsJson()
                        ? response()->json(['message' => 'Select an active service area belonging to the chosen destination hub.', 'errors' => ['service_area_id' => ['Select an active service area for the destination hub.']]], 422)
                        : back()->withErrors(['service_area_id' => 'Select an active service area belonging to the chosen destination hub.']);
                }
                $serviceAreaId = (int) $serviceArea->id;
            }
        }

        $ids = array_values(array_unique(array_map('intval', $validated['delivery_ids'])));
        $sortingGroups = $this->buildSortingGroups($ids, $user, (int) $centerId);
        $results = [];
        $succeeded = 0;
        $failed = 0;

        foreach ($ids as $id) {
            $outcome = DB::transaction(function () use ($id, $user, $centerId, $currentCenterName, $destinationCenter, $serviceAreaId): array {
                $locked = Delivery::query()
                    ->with(['logisticsCenter', 'destinationCenter', 'serviceArea'])
                    ->whereKey($id)
                    ->lockForUpdate()
                    ->first();

                if (! $locked || $locked->archived_at !== null || ! LogisticsAccess::canAccessDelivery($user, $locked)) {
                    return [
                        'delivery_id' => $id,
                        'tracking_number' => null,
                        'status' => 'failed',
                        'message' => 'Parcel not found or outside your assigned hub scope.',
                    ];
                }

                $tracking = $locked->tracking_number;

                if ((int) $locked->center_id !== (int) $centerId) {
                    $actualHub = $locked->logisticsCenter?->name ?? ('Hub #'.($locked->center_id ?: 'Unassigned'));

                    return [
                        'delivery_id' => $id,
                        'tracking_number' => $tracking,
                        'status' => 'failed',
                        'message' => "Sorting hub mismatch: parcel is physically at {$actualHub} and cannot be sorted at {$currentCenterName}.",
                    ];
                }

                if (in_array($locked->status, ['out_for_delivery', 'arrived_at_customer', 'delivered', 'cancelled'], true)
                    || $locked->delivered_at !== null) {
                    return [
                        'delivery_id' => $id,
                        'tracking_number' => $tracking,
                        'status' => 'failed',
                        'message' => 'Parcel is already out for delivery, delivered, or cancelled.',
                    ];
                }

                $condition = self::resolveCondition($locked);
                if ($condition !== 'good') {
                    return [
                        'delivery_id' => $id,
                        'tracking_number' => $tracking,
                        'status' => 'failed',
                        'message' => 'Damaged or missing parcels require individual condition verification before sorting.',
                    ];
                }

                if (FulfillmentWorkflow::hasActiveHubTransfer($locked)) {
                    return [
                        'delivery_id' => $id,
                        'tracking_number' => $tracking,
                        'status' => 'failed',
                        'message' => 'Parcel is locked in an active hub transfer manifest.',
                    ];
                }

                if (! $this->hasRequiredPickupHandoff($locked)) {
                    return [
                        'delivery_id' => $id,
                        'tracking_number' => $tracking,
                        'status' => 'failed',
                        'message' => 'Order parcel has not completed pickup handoff to the hub.',
                    ];
                }

                if ($locked->parcel_status !== 'scanned') {
                    $reason = match ($locked->parcel_status) {
                        'pending_arrival' => 'Parcel must be received and scanned before sorting.',
                        'received' => 'Parcel must be scanned before assigning to a sorting batch.',
                        'sorted' => 'Parcel has already been sorted.',
                        'dispatched' => 'Parcel has already been dispatched.',
                        default => 'Parcel must be in scanned state before sorting.',
                    };

                    return [
                        'delivery_id' => $id,
                        'tracking_number' => $tracking,
                        'status' => 'failed',
                        'message' => $reason,
                    ];
                }

                // Never overwrite an already-assigned destination hub with a
                // different destination selected for another parcel or batch.
                if ($locked->destination_center_id !== null
                    && (int) $locked->destination_center_id > 0
                    && (int) $locked->destination_center_id !== (int) $destinationCenter->id) {
                    $existingDest = $locked->destinationCenter?->name ?? ('Hub #'.$locked->destination_center_id);

                    return [
                        'delivery_id' => $id,
                        'tracking_number' => $tracking,
                        'status' => 'failed',
                        'message' => "Destination mismatch: parcel is already routed to {$existingDest} and cannot be overwritten with {$destinationCenter->name}.",
                    ];
                }

                if ($locked->service_area_id !== null
                    && (int) $locked->service_area_id > 0
                    && $serviceAreaId !== null
                    && (int) $locked->service_area_id !== (int) $serviceAreaId) {
                    $existingArea = $locked->serviceArea?->name ?? ('Service Area #'.$locked->service_area_id);

                    return [
                        'delivery_id' => $id,
                        'tracking_number' => $tracking,
                        'status' => 'failed',
                        'message' => "Service area mismatch: parcel is already assigned to {$existingArea} and cannot be overwritten.",
                    ];
                }

                $resolvedServiceAreaId = $serviceAreaId;
                if ($resolvedServiceAreaId === null && $locked->service_area_id !== null && Schema::hasTable('service_areas')) {
                    $belongsToDestination = ServiceArea::query()
                        ->whereKey((int) $locked->service_area_id)
                        ->where('logistics_center_id', $destinationCenter->id)
                        ->where('is_active', true)
                        ->exists();
                    if ($belongsToDestination) {
                        $resolvedServiceAreaId = (int) $locked->service_area_id;
                    }
                }

                // Sorting happens at the current hub (center_id stays intact);
                // only destination_center_id, service_area_id, and sorted status update.
                $locked->update([
                    'destination_center_id' => $destinationCenter->id,
                    'service_area_id' => $resolvedServiceAreaId,
                    'parcel_status' => 'sorted',
                    'sorted_at' => $locked->sorted_at ?? now(),
                ]);

                $sortingActionLabel = (int) $destinationCenter->id === (int) $centerId
                    ? 'local delivery at '.$currentCenterName
                    : 'inter-hub transfer to '.$destinationCenter->name;

                DeliveryStatusLog::create([
                    'delivery_id' => $locked->id,
                    'status' => 'sorted',
                    'changed_by' => (int) $user->id,
                    'notes' => "Parcel sorted at {$currentCenterName} for {$sortingActionLabel}.",
                ]);

                return [
                    'delivery_id' => $id,
                    'tracking_number' => $tracking,
                    'status' => 'succeeded',
                    'message' => "Sorted at {$currentCenterName} for {$destinationCenter->name}.",
                ];
            });

            if ($outcome['status'] === 'succeeded') {
                $succeeded++;
            } else {
                $failed++;
            }

            $results[] = $outcome;
        }

        return $this->respondBulkAction($request, 'sort', $succeeded, $failed, $results, [
            'current_center_id' => (int) $centerId,
            'current_center_name' => $currentCenterName,
            'destination_center_id' => $destinationCenter->id,
            'destination_center_name' => $destinationCenter->name,
            'service_area_id' => $serviceAreaId,
            'sorting_action' => (int) $destinationCenter->id === (int) $centerId ? 'local_delivery' : 'inter_hub_transfer',
            'sorting_groups' => $sortingGroups,
        ]);
    }

    /**
     * Build compatible sorting groups for the selected parcel IDs by current
     * hub, existing destination hub, and existing service area.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array<string, mixed>>
     */
    private function buildSortingGroups(array $ids, User $user, int $centerId): array
    {
        $deliveries = Delivery::query()
            ->with(['logisticsCenter', 'destinationCenter', 'serviceArea'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $groups = [];
        foreach ($ids as $id) {
            $delivery = $deliveries->get($id);
            if (! $delivery || $delivery->archived_at !== null || ! LogisticsAccess::canAccessDelivery($user, $delivery)) {
                continue;
            }
            if ((int) $delivery->center_id !== $centerId || $delivery->parcel_status !== 'scanned' || self::resolveCondition($delivery) !== 'good' || FulfillmentWorkflow::hasActiveHubTransfer($delivery)) {
                continue;
            }

            $destId = $delivery->destination_center_id ? (int) $delivery->destination_center_id : null;
            $areaId = $delivery->service_area_id ? (int) $delivery->service_area_id : null;
            $key = $centerId.':'.($destId ?? 'unassigned').':'.($areaId ?? 'unassigned');

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'current_center_id' => $centerId,
                    'current_center_name' => $delivery->logisticsCenter?->name ?? ('Hub #'.$centerId),
                    'destination_center_id' => $destId,
                    'destination_center_name' => $delivery->destinationCenter?->name ?? 'Unassigned (Select Next Destination)',
                    'service_area_id' => $areaId,
                    'service_area_name' => $delivery->serviceArea?->name ?? null,
                    'parcel_count' => 0,
                    'parcel_ids' => [],
                    'tracking_numbers' => [],
                ];
            }

            $groups[$key]['parcel_count']++;
            $groups[$key]['parcel_ids'][] = (int) $delivery->id;
            $groups[$key]['tracking_numbers'][] = $delivery->tracking_number;
        }

        return array_values($groups);
    }

    /**
     * Bulk Assign to Rider for parcels sorted for last-mile delivery at their
     * destination hub. Pre-validates each parcel's condition, hub custody,
     * sorted state, and service-area compatibility, then delegates eligible
     * rows to DeliveryController's locked rider assignment core.
     */
    public function bulkAssignRider(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->canManageRiders(), 403, 'Rider assignments are managed by authorized logistics personnel.');

        $centerId = LogisticsAccess::centerId($user);
        $companyId = LogisticsAccess::companyId($user);
        $autoMode = $request->input('mode') === 'auto';

        $validated = $request->validate([
            'confirmed' => 'accepted',
            'mode' => 'nullable|in:auto,manual',
            'rider_id' => $autoMode ? 'nullable|integer|exists:riders,id' : 'required|integer|exists:riders,id',
            'delivery_ids' => 'required|array|min:1|max:25',
            'delivery_ids.*' => 'integer',
        ]);

        $selectedRider = ! $autoMode && ! empty($validated['rider_id'])
            ? Rider::query()->with(['logisticsCenter.company', 'serviceArea'])->whereKey((int) $validated['rider_id'])->first()
            : null;

        $ids = array_values(array_unique(array_map('intval', $validated['delivery_ids'])));
        $preFailedResults = [];
        $eligibleIds = [];
        $serviceAreaGroupsMap = [];

        foreach ($ids as $id) {
            $delivery = Delivery::query()
                ->with(['logisticsCenter', 'destinationCenter', 'serviceArea', 'rider'])
                ->whereKey($id)
                ->first();

            if (! $delivery || $delivery->archived_at !== null || ! LogisticsAccess::canAccessDelivery($user, $delivery)) {
                $preFailedResults[$id] = [
                    'delivery_id' => $id,
                    'tracking_number' => null,
                    'status' => 'failed',
                    'message' => 'Parcel not found or outside your hub scope.',
                ];
                continue;
            }

            if ($centerId !== null && $centerId > 0 && (int) $delivery->center_id !== (int) $centerId) {
                $preFailedResults[$id] = [
                    'delivery_id' => $id,
                    'tracking_number' => $delivery->tracking_number,
                    'status' => 'failed',
                    'message' => 'Parcel is physically at another hub and cannot be assigned from this hub.',
                ];
                continue;
            }

            $condition = self::resolveCondition($delivery);
            if ($condition !== 'good') {
                $preFailedResults[$id] = [
                    'delivery_id' => $id,
                    'tracking_number' => $delivery->tracking_number,
                    'status' => 'failed',
                    'message' => 'Damaged or missing parcels require individual condition verification before rider assignment.',
                ];
                continue;
            }

            if (FulfillmentWorkflow::hasActiveHubTransfer($delivery)) {
                $preFailedResults[$id] = [
                    'delivery_id' => $id,
                    'tracking_number' => $delivery->tracking_number,
                    'status' => 'failed',
                    'message' => 'Parcel is locked in an active hub transfer manifest.',
                ];
                continue;
            }

            if ($delivery->parcel_status !== 'sorted') {
                $preFailedResults[$id] = [
                    'delivery_id' => $id,
                    'tracking_number' => $delivery->tracking_number,
                    'status' => 'failed',
                    'message' => 'Parcel must be sorted before last-mile rider assignment.',
                ];
                continue;
            }

            if ($delivery->destination_center_id !== null && (int) $delivery->destination_center_id !== (int) $delivery->center_id) {
                $preFailedResults[$id] = [
                    'delivery_id' => $id,
                    'tracking_number' => $delivery->tracking_number,
                    'status' => 'failed',
                    'message' => 'Parcel is routed to another destination hub and requires a hub transfer instead of last-mile rider assignment.',
                ];
                continue;
            }

            if ($delivery->rider_id !== null || in_array($delivery->status, [...Delivery::ACTIVE_STATUSES, 'delivered', 'cancelled'], true)) {
                $preFailedResults[$id] = [
                    'delivery_id' => $id,
                    'tracking_number' => $delivery->tracking_number,
                    'status' => 'failed',
                    'message' => 'Duplicate assignment blocked: parcel is already assigned to '.($delivery->rider?->name ?? 'a rider').'.',
                ];
                continue;
            }

            $targetCenterId = (int) ($delivery->destination_center_id ?: $delivery->center_id);
            $targetAreaId = $delivery->service_area_id
                ? (int) $delivery->service_area_id
                : ($delivery->riderAssignmentScope()['service_area_id'] ?? null);
            $targetAreaName = $delivery->serviceArea?->name
                ?? ($targetAreaId && Schema::hasTable('service_areas') ? ServiceArea::whereKey($targetAreaId)->value('name') : null)
                ?? 'Unassigned Service Area';

            $groupKey = $targetCenterId.':'.($targetAreaId ?? 'none');
            if (! isset($serviceAreaGroupsMap[$groupKey])) {
                $serviceAreaGroupsMap[$groupKey] = [
                    'center_id' => $targetCenterId,
                    'center_name' => $delivery->logisticsCenter?->name ?? ('Hub #'.$targetCenterId),
                    'service_area_id' => $targetAreaId,
                    'service_area_name' => $targetAreaName,
                    'parcel_count' => 0,
                    'parcel_ids' => [],
                    'tracking_numbers' => [],
                ];
            }
            $serviceAreaGroupsMap[$groupKey]['parcel_count']++;
            $serviceAreaGroupsMap[$groupKey]['parcel_ids'][] = (int) $delivery->id;
            $serviceAreaGroupsMap[$groupKey]['tracking_numbers'][] = $delivery->tracking_number;

            if (! $autoMode && $selectedRider) {
                $riderCompanyId = $selectedRider->logisticsCenter?->logistics_company_id
                    ? (int) $selectedRider->logisticsCenter->logistics_company_id
                    : null;
                $parcelCompanyId = $delivery->logisticsCenter?->logistics_company_id
                    ? (int) $delivery->logisticsCenter->logistics_company_id
                    : $companyId;

                if ($parcelCompanyId !== null && $riderCompanyId !== null && $riderCompanyId !== (int) $parcelCompanyId) {
                    $preFailedResults[$id] = [
                        'delivery_id' => $id,
                        'tracking_number' => $delivery->tracking_number,
                        'status' => 'failed',
                        'message' => 'Cannot assign delivery. The selected rider belongs to a different logistics company.',
                    ];
                    continue;
                }

                if (! $selectedRider->isEligibilityApproved()) {
                    $preFailedResults[$id] = [
                        'delivery_id' => $id,
                        'tracking_number' => $delivery->tracking_number,
                        'status' => 'failed',
                        'message' => 'Cannot assign delivery. Rider application is not approved.',
                    ];
                    continue;
                }

                if ($selectedRider->status === 'inactive' || ! $selectedRider->is_online) {
                    $preFailedResults[$id] = [
                        'delivery_id' => $id,
                        'tracking_number' => $delivery->tracking_number,
                        'status' => 'failed',
                        'message' => 'Cannot assign delivery. Rider is inactive or offline and not available for new deliveries.',
                    ];
                    continue;
                }

                if ((int) $selectedRider->center_id !== $targetCenterId) {
                    $preFailedResults[$id] = [
                        'delivery_id' => $id,
                        'tracking_number' => $delivery->tracking_number,
                        'status' => 'failed',
                        'message' => 'Cannot assign delivery. Rider belongs to a different hub than the parcel destination hub.',
                    ];
                    continue;
                }

                if ($targetAreaId !== null && ((int) ($selectedRider->service_area_id ?? 0) !== (int) $targetAreaId)) {
                    $riderAreaName = $selectedRider->serviceArea?->name ?? 'Unassigned Area';
                    $preFailedResults[$id] = [
                        'delivery_id' => $id,
                        'tracking_number' => $delivery->tracking_number,
                        'status' => 'failed',
                        'message' => "Cannot assign delivery. Rider is authorized for {$riderAreaName}, not the parcel's delivery area ({$targetAreaName}).",
                    ];
                    continue;
                }
            } elseif ($autoMode && $targetAreaId !== null) {
                $hasAreaRider = Rider::query()
                    ->where('center_id', $targetCenterId)
                    ->where('service_area_id', $targetAreaId)
                    ->where('status', '!=', 'inactive')
                    ->where('is_online', true)
                    ->whereNotNull('approved_at')
                    ->exists();
                if (! $hasAreaRider) {
                    $preFailedResults[$id] = [
                        'delivery_id' => $id,
                        'tracking_number' => $delivery->tracking_number,
                        'status' => 'failed',
                        'message' => "No eligible online rider is available for service area {$targetAreaName}.",
                    ];
                    continue;
                }
            }

            $eligibleIds[] = $id;
        }

        $delegatedResults = [];
        if ($eligibleIds !== []) {
            $subRequest = Request::create('/logistics/deliveries/bulk-assign-rider', 'POST', [
                'mode' => $autoMode ? 'auto' : 'manual',
                'rider_id' => $validated['rider_id'] ?? null,
                'delivery_ids' => $eligibleIds,
            ]);
            $subRequest->headers->set('Accept', 'application/json');
            $subRequest->setUserResolver(fn () => $user);

            $subResponse = app(DeliveryController::class)->bulkAssignRider($subRequest);
            $subPayload = $subResponse instanceof JsonResponse ? $subResponse->getData(true) : [];
            foreach ($subPayload['results'] ?? [] as $row) {
                $rowId = (int) ($row['delivery_id'] ?? 0);
                if ($rowId > 0) {
                    $delegatedResults[$rowId] = [
                        'delivery_id' => $rowId,
                        'tracking_number' => $row['tracking_number'] ?? Delivery::whereKey($rowId)->value('tracking_number'),
                        'status' => ($row['status'] ?? '') === 'assigned' ? 'succeeded' : 'failed',
                        'message' => $row['message'] ?? '',
                    ];
                }
            }
        }

        $results = [];
        $succeeded = 0;
        $failed = 0;

        foreach ($ids as $id) {
            $outcome = $preFailedResults[$id] ?? $delegatedResults[$id] ?? [
                'delivery_id' => $id,
                'tracking_number' => null,
                'status' => 'failed',
                'message' => 'Could not assign rider to this parcel.',
            ];

            if ($outcome['status'] === 'succeeded') {
                $succeeded++;
            } else {
                $failed++;
            }

            $results[] = $outcome;
        }

        return $this->respondBulkAction($request, 'assign_rider', $succeeded, $failed, $results, [
            'service_area_groups' => array_values($serviceAreaGroupsMap),
        ]);
    }

    /**
     * Bulk Transfer (with destination grouping and confirmation) for sorted
     * parcels whose next hop is another hub. Independently validates each
     * parcel's actual destination hub, groups eligible parcels by destination,
     * prevents combining different destination hubs into one manifest, and
     * never overwrites a parcel's destination with another parcel's destination.
     */
    public function bulkTransfer(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user && LogisticsAccess::canProcessParcels($user),
            403,
            'Hub transfer preparation is reserved for hub-assigned logistics staff.'
        );

        $centerId = LogisticsAccess::centerId($user);
        abort_unless($centerId !== null && $centerId > 0, 403, 'Your account is not assigned to an active logistics hub.');
        $originCenter = LogisticsCenter::whereKey($centerId)->first();
        $originCenterName = $originCenter?->name ?? ('Hub #'.$centerId);

        $validated = $request->validate([
            'confirmed' => 'accepted',
            'destination_center_id' => 'nullable|integer',
            'delivery_ids' => 'required|array|min:1|max:50',
            'delivery_ids.*' => 'integer',
        ]);

        $ids = array_values(array_unique(array_map('intval', $validated['delivery_ids'])));
        $destinationGroups = $this->buildTransferDestinationGroups($ids, $user, (int) $centerId, $originCenterName);

        $requestedDestinationId = isset($validated['destination_center_id']) && (int) $validated['destination_center_id'] > 0
            ? (int) $validated['destination_center_id']
            : null;

        if ($requestedDestinationId === null) {
            if (count($destinationGroups) > 1) {
                $groupLabels = collect($destinationGroups)
                    ->map(fn (array $g) => $g['destination_center_name'].' ('.$g['parcel_count'].')')
                    ->implode(', ');
                $results = [];
                foreach ($ids as $id) {
                    $d = Delivery::with('destinationCenter')->find($id);
                    $results[] = [
                        'delivery_id' => $id,
                        'tracking_number' => $d?->tracking_number,
                        'status' => 'failed',
                        'message' => "Selected parcels have different destination hubs ({$groupLabels}). They cannot be processed in one transfer manifest. Process each destination group separately.",
                    ];
                }

                return $this->respondBulkAction($request, 'transfer', 0, count($ids), $results, [
                    'destination_center_id' => null,
                    'destination_center_name' => null,
                    'valid_parcel_ids' => [],
                    'manifest_url' => null,
                    'destination_groups' => $destinationGroups,
                ]);
            }

            if (count($destinationGroups) === 1) {
                $requestedDestinationId = (int) $destinationGroups[0]['destination_center_id'];
            }
        }

        if ($requestedDestinationId === null) {
            return $request->expectsJson()
                ? response()->json([
                    'message' => 'Select an active destination hub belonging to an active logistics company.',
                    'errors' => ['destination_center_id' => ['Select an active destination hub.']],
                    'destination_groups' => $destinationGroups,
                ], 422)
                : back()->withErrors(['destination_center_id' => 'Select an active destination hub belonging to an active logistics company.']);
        }

        $destinationCenter = LogisticsCenter::query()
            ->whereKey($requestedDestinationId)
            ->where('is_active', true)
            ->whereHas('company', fn ($q) => $q->where('status', 'active'))
            ->first();

        if (! $destinationCenter) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Select an active destination hub belonging to an active logistics company.', 'errors' => ['destination_center_id' => ['Select an active destination hub.']]], 422)
                : back()->withErrors(['destination_center_id' => 'Select an active destination hub belonging to an active logistics company.']);
        }

        if ((int) $destinationCenter->id === (int) $centerId) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Destination hub must be different from your origin hub.', 'errors' => ['destination_center_id' => ['Destination hub must be different from origin hub.']]], 422)
                : back()->withErrors(['destination_center_id' => 'Destination hub must be different from your origin hub.']);
        }

        $results = [];
        $validParcelIds = [];
        $succeeded = 0;
        $failed = 0;

        foreach ($ids as $id) {
            $outcome = DB::transaction(function () use ($id, $user, $centerId, $destinationCenter): array {
                $locked = Delivery::query()->with('destinationCenter')->whereKey($id)->lockForUpdate()->first();

                if (! $locked || $locked->archived_at !== null || (int) $locked->center_id !== (int) $centerId || ! LogisticsAccess::canAccessDelivery($user, $locked)) {
                    return [
                        'delivery_id' => $id,
                        'tracking_number' => null,
                        'status' => 'failed',
                        'message' => 'Parcel not found or outside your assigned hub scope.',
                    ];
                }

                $tracking = $locked->tracking_number;

                if (in_array($locked->status, [...Delivery::ACTIVE_STATUSES, 'delivered', 'cancelled'], true) || $locked->rider_id !== null) {
                    return [
                        'delivery_id' => $id,
                        'tracking_number' => $tracking,
                        'status' => 'failed',
                        'message' => 'Parcel is assigned to a rider, delivered, or cancelled.',
                    ];
                }

                $condition = self::resolveCondition($locked);
                if ($condition !== 'good') {
                    return [
                        'delivery_id' => $id,
                        'tracking_number' => $tracking,
                        'status' => 'failed',
                        'message' => 'Damaged or missing parcels require individual condition verification before transfer.',
                    ];
                }

                if (FulfillmentWorkflow::hasActiveHubTransfer($locked)) {
                    return [
                        'delivery_id' => $id,
                        'tracking_number' => $tracking,
                        'status' => 'failed',
                        'message' => 'Parcel is already attached to an active hub transfer manifest.',
                    ];
                }

                if ($locked->parcel_status !== 'sorted') {
                    return [
                        'delivery_id' => $id,
                        'tracking_number' => $tracking,
                        'status' => 'failed',
                        'message' => 'Parcel must be sorted before it can be transferred to another hub.',
                    ];
                }

                if ($locked->destination_center_id !== null && (int) $locked->destination_center_id === (int) $centerId) {
                    return [
                        'delivery_id' => $id,
                        'tracking_number' => $tracking,
                        'status' => 'failed',
                        'message' => 'Parcel is sorted for local delivery at this hub and requires last-mile rider assignment instead of a hub transfer.',
                    ];
                }

                if ((int) $locked->destination_center_id !== (int) $destinationCenter->id) {
                    $actualDest = $locked->destinationCenter?->name ?? ('Hub #'.($locked->destination_center_id ?: 'Unassigned'));

                    return [
                        'delivery_id' => $id,
                        'tracking_number' => $tracking,
                        'status' => 'failed',
                        'message' => "Destination mismatch: parcel is sorted for {$actualDest}, not {$destinationCenter->name}. Different destination hubs cannot be combined into one transfer manifest.",
                    ];
                }

                DeliveryStatusLog::create([
                    'delivery_id' => $locked->id,
                    'status' => 'transfer_prepared',
                    'changed_by' => (int) $user->id,
                    'notes' => 'Confirmed for bulk hub transfer to '.$destinationCenter->name.'.',
                ]);

                return [
                    'delivery_id' => $id,
                    'tracking_number' => $tracking,
                    'status' => 'succeeded',
                    'message' => 'Confirmed for transfer to '.$destinationCenter->name.'.',
                ];
            });

            if ($outcome['status'] === 'succeeded') {
                $succeeded++;
                $validParcelIds[] = $id;
            } else {
                $failed++;
            }

            $results[] = $outcome;
        }

        $manifestUrl = $validParcelIds !== []
            ? route('hub-transfers.create', [
                'origin_center_id' => $centerId,
                'destination_center_id' => $destinationCenter->id,
                'parcels' => $validParcelIds,
            ])
            : null;

        return $this->respondBulkAction($request, 'transfer', $succeeded, $failed, $results, [
            'origin_center_id' => (int) $centerId,
            'origin_center_name' => $originCenterName,
            'destination_center_id' => $destinationCenter->id,
            'destination_center_name' => $destinationCenter->name,
            'valid_parcel_ids' => $validParcelIds,
            'manifest_url' => $manifestUrl,
            'destination_groups' => $destinationGroups,
        ]);
    }

    /**
     * Group eligible inter-hub sorted parcels by their actual destination hub
     * so each destination group receives its own separate manifest URL.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array<string, mixed>>
     */
    private function buildTransferDestinationGroups(array $ids, User $user, int $centerId, string $originCenterName): array
    {
        $deliveries = Delivery::query()
            ->with('destinationCenter')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $groups = [];
        foreach ($ids as $id) {
            $delivery = $deliveries->get($id);
            if (! $delivery || $delivery->archived_at !== null || (int) $delivery->center_id !== $centerId || ! LogisticsAccess::canAccessDelivery($user, $delivery)) {
                continue;
            }
            if ($delivery->parcel_status !== 'sorted'
                || $delivery->rider_id !== null
                || in_array($delivery->status, [...Delivery::ACTIVE_STATUSES, 'delivered', 'cancelled'], true)
                || self::resolveCondition($delivery) !== 'good'
                || FulfillmentWorkflow::hasActiveHubTransfer($delivery)
                || $delivery->destination_center_id === null
                || (int) $delivery->destination_center_id === $centerId) {
                continue;
            }

            $destId = (int) $delivery->destination_center_id;
            $destName = $delivery->destinationCenter?->name ?? ('Hub #'.$destId);

            if (! isset($groups[$destId])) {
                $groups[$destId] = [
                    'origin_center_id' => $centerId,
                    'origin_center_name' => $originCenterName,
                    'destination_center_id' => $destId,
                    'destination_center_name' => $destName,
                    'parcel_count' => 0,
                    'parcel_ids' => [],
                    'tracking_numbers' => [],
                    'manifest_url' => null,
                ];
            }

            $groups[$destId]['parcel_count']++;
            $groups[$destId]['parcel_ids'][] = (int) $delivery->id;
            $groups[$destId]['tracking_numbers'][] = $delivery->tracking_number;
        }

        foreach ($groups as $destId => &$group) {
            $group['manifest_url'] = route('hub-transfers.create', [
                'origin_center_id' => $centerId,
                'destination_center_id' => (int) $destId,
                'parcels' => $group['parcel_ids'],
            ]);
        }
        unset($group);

        return array_values($groups);
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     * @param  array<string, mixed>  $extra
     */
    private function respondBulkAction(
        Request $request,
        string $action,
        int $succeeded,
        int $failed,
        array $results,
        array $extra = []
    ): RedirectResponse|JsonResponse {
        $actionLabel = match ($action) {
            'receive' => 'received',
            'sort' => 'sorted',
            'assign_rider' => 'assigned to rider',
            'transfer' => 'validated for transfer',
            default => 'processed',
        };

        $summary = "{$succeeded} {$actionLabel}, {$failed} failed.";

        if ($request->expectsJson()) {
            return response()->json(array_merge([
                'action' => $action,
                'message' => $summary,
                'succeeded' => $succeeded,
                'failed' => $failed,
                'results' => $results,
            ], $extra), $failed > 0 && $succeeded > 0 ? 207 : ($succeeded > 0 ? 200 : 422));
        }

        $redirect = redirect()->back()->with('bulk_operation_results', [
            'action' => $action,
            'summary' => $summary,
            'succeeded' => $succeeded,
            'failed' => $failed,
            'results' => $results,
            'extra' => $extra,
        ]);

        if ($failed === 0) {
            return $redirect->with('success', $summary);
        }

        if ($succeeded === 0) {
            return $redirect->with('error', $summary);
        }

        return $redirect->with('warning', $summary);
    }

    /**
     * @param  Collection<int, HubTransfer>  $transfers
     */
    private function loadOriginFleetForOwner(Collection $transfers, User $user, bool $global): void
    {
        $companyId = $global ? null : LogisticsAccess::companyId($user);

        foreach ($transfers as $transfer) {
            if (! $transfer instanceof HubTransfer) {
                continue;
            }

            if (! $global) {
                $originCompanyId = (int) ($transfer->origin?->logistics_company_id ?? 0);
                if ($companyId === null || $companyId <= 0 || $originCompanyId !== $companyId) {
                    continue;
                }
            }

            $transfer->loadMissing(['truck', 'driver']);
        }
    }

    private function scopeDeliveries(Builder $query, ?int $companyId, ?int $centerId): void
    {
        if ($companyId !== null) {
            if ($companyId === 0) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->whereIn('center_id', LogisticsCenter::query()
                ->where('logistics_company_id', $companyId)
                ->select('id'));
        }

        if ($centerId !== null) {
            if ($centerId === 0) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where('center_id', $centerId);
        }
    }

    private function scopeTransfers(Builder $query, ?int $companyId, ?int $centerId): void
    {
        if ($companyId !== null) {
            if ($companyId === 0) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where(function ($scoped) use ($companyId): void {
                $scoped->where('logistics_company_id', $companyId)
                    ->orWhereHas('origin', fn ($center) => $center->where('logistics_company_id', $companyId))
                    ->orWhereHas('destination', fn ($center) => $center->where('logistics_company_id', $companyId));
            });
        }

        if ($centerId !== null) {
            if ($centerId === 0) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where(fn ($scoped) => $scoped
                ->where('origin_center_id', $centerId)
                ->orWhere('destination_center_id', $centerId));
        }
    }

    private function scopeLabel(bool $global, ?LogisticsCompany $company, ?LogisticsCenter $center): string
    {
        if ($center) {
            return $center->name.($company ? ' · '.$company->name : '');
        }

        if ($company) {
            return $company->name.' · company-wide';
        }

        return $global ? 'Network oversight' : 'Unassigned scope';
    }
}
