<?php

namespace App\Http\Controllers\Logistics;

use App\Models\Delivery;
use App\Models\HubTransfer;
use App\Models\HubTransferParcel;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Models\User;
use App\Support\LogisticsAccess;
use App\Support\ParcelWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        abort_unless($user && ($user->isLogisticsStaff() || LogisticsAccess::isGlobal($user)), 403);

        $global = LogisticsAccess::isGlobal($user);
        $companyId = LogisticsAccess::companyId($user);
        $centerId = LogisticsAccess::centerId($user);

        if (! $global && ($companyId === null || $companyId === 0)) {
            abort(403, 'Your account is not assigned to an active logistics company.');
        }
        if (! $global && $centerId === 0) {
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
            ->whereNotIn('status', [...Delivery::ACTIVE_STATUSES, 'delivered', 'cancelled'])
            ->whereColumn('destination_center_id', 'center_id');

        // Sorted for another hub: the next step is adding the parcel to a
        // fleet transfer manifest, not last-mile assignment.
        $preparingTransferQuery = (clone $deliveries)
            ->where('parcel_status', 'sorted')
            ->whereNull('rider_id')
            ->whereNotIn('status', [...Delivery::ACTIVE_STATUSES, 'delivered', 'cancelled'])
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
            'destinationCenter',
            'incomingTransferParcel.transfer.origin',
            'latestHubTransferParcel.transfer.origin',
        ];

        $actionParcels = collect()
            ->concat((clone $awaitingReceiptQuery)->with($parcelRelations)->orderByDesc('received_at')->limit(8)->get())
            ->concat((clone $readySortingQuery)->with($parcelRelations)->orderByDesc('received_at')->limit(8)->get())
            ->concat((clone $readyRiderQuery)->with($parcelRelations)->orderByDesc('sorted_at')->limit(8)->get())
            ->concat((clone $preparingTransferQuery)->with($parcelRelations)->orderByDesc('sorted_at')->limit(8)->get())
            ->unique('id')
            ->take(15)
            ->map(fn (Delivery $delivery) => [
                'delivery' => $delivery,
                'workflow' => ParcelWorkflow::describe(
                    $delivery,
                    $delivery->latestHubTransferParcel ?? $delivery->incomingTransferParcel,
                    $user,
                ),
            ]);

        // Fleet and driver details are origin-company data. Load them only on
        // transfers whose origin belongs to the viewer's company (or for
        // global oversight); foreign destination viewers never receive them.
        $fleetTransfers = $incomingTransfers
            ->toBase()
            ->merge($outgoingTransfers->toBase())
            ->merge($actionParcels->pluck('workflow')->pluck('transfer'))
            ->filter();

        $this->loadOriginFleetForOwner($fleetTransfers, $user, $global);

        return view('logistics.operations.index', [
            'company' => $company,
            'center' => $center,
            'global' => $global,
            'canProcess' => $canProcess,
            'counts' => $counts,
            'actionParcels' => $actionParcels,
            'incomingTransfers' => $incomingTransfers,
            'outgoingTransfers' => $outgoingTransfers,
            'exceptionEntries' => $exceptionEntries,
            'scopeLabel' => $this->scopeLabel($global, $company, $center),
        ]);
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
