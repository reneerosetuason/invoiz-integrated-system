<?php

namespace App\Http\Controllers\Logistics;

use App\Models\Delivery;
use App\Models\LogisticsCenter;
use App\Models\ServiceArea;
use App\Support\CsvExport;
use App\Support\LogisticsAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Standalone sorting operations inside a Logistics Hub.
 *
 * Read-only aggregation over the existing parcel pipeline
 * (pending_arrival -> received -> scanned -> sorted -> dispatched); every
 * mutation posts to the existing deliveries.* routes, so no workflow
 * logic is duplicated here. The per-delivery workflow on deliveries.show
 * remains the canonical action surface (assignment included).
 *
 * Staff are center-scoped at the query level, mirroring Deliveries.
 */
class SortingCenterController extends Controller
{
    public const TABS = [
        'all', 'incoming', 'received', 'scanned', 'sorted',
        'dispatched', 'returned', 'reattempt',
    ];

    public function index(Request $request): View
    {
        $context = $this->requestContext($request);
        $incomingCenterId = $context['staffCenterId'] !== null && $context['staffCenterId'] > 0
            ? $context['staffCenterId']
            : null;

        // Incoming visibility comes from real active fleet transfers, so the
        // receiving hub sees parcels before their center_id is updated.
        $base = $this->baseQuery($context);
        $incomingBase = $this->baseQuery($context, true);
        $incomingBase->incomingViaHub($incomingCenterId);

        $counts = [
            'incoming' => $incomingBase->count(),
            'received' => (clone $base)->where('parcel_status', 'received')->count(),
            'scanned' => (clone $base)->where('parcel_status', 'scanned')->count(),
            'sorted' => (clone $base)->where('parcel_status', 'sorted')->count(),
            'dispatched' => (clone $base)->where('parcel_status', 'dispatched')->count(),
            'assigned' => (clone $base)->where('status', 'assigned')->count(),
        ];
        if ($context['hasReturnedAt']) {
            $counts['returned'] = (clone $base)->whereNotNull('returned_at')->count();
        }
        if ($context['hasReattemptRequestedAt']) {
            $counts['reattempt'] = (clone $base)->whereNotNull('reattempt_requested_at')->count();
        }

        $query = $context['tab'] === 'incoming'
            ? (clone $incomingBase)
            : $this->baseQuery($context);
        $relations = ['rider', 'logisticsCenter', 'destinationCenter', 'serviceArea', 'statusLogs'];
        $relations['incomingTransferParcel'] = function ($query) use ($context, $incomingCenterId): void {
            if ($context['tab'] === 'incoming' && $incomingCenterId !== null) {
                // The incoming screen should only link the manifest leg whose
                // destination is this hub, even if stale data contains another
                // active leg for the same parcel.
                $query->whereHas('transfer', fn ($transfer) => $transfer
                    ->where('destination_center_id', $incomingCenterId));
            }
        };
        $relations[] = 'incomingTransferParcel.transfer';
        $query->with($relations);
        $this->applyFilters($query, $context);

        $centers = LogisticsCenter::where('is_active', true)->orderBy('name');
        $serviceAreas = ServiceArea::where('is_active', true)->orderBy('name');
        if ($context['companyId'] !== null) {
            if ($context['companyId'] === 0) {
                if ($context['staffCenterId'] === null) {
                    $centers->whereRaw('1 = 0');
                    $serviceAreas->whereRaw('1 = 0');
                }
            } else {
                $centers->where('logistics_company_id', $context['companyId']);
                $serviceAreas->whereHas('logisticsCenter', fn ($q) => $q->where('logistics_company_id', $context['companyId']));
            }
        } elseif ($context['staffCenterId'] !== null) {
            if ($context['staffCenterId'] === 0) {
                $centers->whereRaw('1 = 0');
                $serviceAreas->whereRaw('1 = 0');
            }
        }

        $filterServiceAreas = (clone $serviceAreas);
        $destinationId = $context['destinationCenterId'] ?: $context['centerId'];
        if ($destinationId > 0) {
            $filterServiceAreas->where('logistics_center_id', $destinationId);
        }

        return view('logistics.sorting-center.index', [
            'counts' => $counts,
            'deliveries' => $query->latest()->paginate(10)->withQueryString(),
            'tab' => $context['tab'],
            'availableTabs' => $context['availableTabs'],
            'hasReturnedAt' => $context['hasReturnedAt'],
            'hasReattemptRequestedAt' => $context['hasReattemptRequestedAt'],
            'search' => $context['search'],
            'destinationCenterId' => $context['destinationCenterId'],
            'centerId' => $context['centerId'],
            'serviceAreaId' => $context['serviceAreaId'],
            'centers' => $centers->get(),
            'serviceAreas' => $serviceAreas->get(),
            'filterServiceAreas' => $filterServiceAreas->get(),
            'staffCenterId' => $context['staffCenterId'],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $context = $this->requestContext($request);
        $incomingCenterId = $context['staffCenterId'] !== null && $context['staffCenterId'] > 0
            ? $context['staffCenterId']
            : null;

        $query = $this->baseQuery($context, $context['tab'] === 'incoming')
            ->with(['rider', 'logisticsCenter', 'destinationCenter', 'serviceArea']);
        if ($context['tab'] === 'incoming') {
            $query->incomingViaHub($incomingCenterId);
        }
        $this->applyFilters($query, $context);

        $rows = $query->latest()->lazy(500)->map(fn (Delivery $delivery) => [
            $delivery->tracking_number,
            $delivery->parcel_status,
            $delivery->status,
            $delivery->priority,
            $delivery->sender_name,
            $delivery->recipient_name,
            $delivery->rider?->name,
            $delivery->logisticsCenter?->name,
            $delivery->destinationCenter?->name,
            $delivery->serviceArea?->name,
            $delivery->received_at?->format('Y-m-d H:i:s'),
            $delivery->scanned_at?->format('Y-m-d H:i:s'),
            $delivery->sorted_at?->format('Y-m-d H:i:s'),
            $delivery->dispatched_at?->format('Y-m-d H:i:s'),
            $delivery->returned_at?->format('Y-m-d H:i:s'),
            $delivery->reattempt_requested_at?->format('Y-m-d H:i:s'),
            $delivery->created_at?->format('Y-m-d H:i:s'),
        ]);

        return CsvExport::stream(
            'invoiz-sorting-center-' . $context['tab'] . '-' . now()->format('Y-m-d') . '.csv',
            [
                'Tracking Number',
                'Parcel Status',
                'Delivery Status',
                'Priority',
                'Sender',
                'Recipient',
                'Rider',
                'Origin Center',
                'Destination Center',
                'Service Area',
                'Received At',
                'Scanned At',
                'Sorted At',
                'Dispatched At',
                'Returned At',
                'Reattempt Requested At',
                'Created At',
            ],
            $rows,
            $request->user(),
            'logistics.sorting-center',
            [
                'tab' => $context['tab'],
                'search_applied' => $context['search'] !== '',
                'requested_destination_center_id' => $context['destinationCenterId'] > 0 ? $context['destinationCenterId'] : null,
                'requested_center_id' => $context['centerId'] > 0 ? $context['centerId'] : null,
                'service_area_id' => $context['serviceAreaId'] > 0 ? $context['serviceAreaId'] : null,
                'center_scope' => $context['staffCenterId'] !== null ? $context['staffCenterId'] : 'global',
            ]
        );
    }

    private function requestContext(Request $request): array
    {
        $hasReturnedAt = Schema::hasColumn('deliveries', 'returned_at');
        $hasReattemptRequestedAt = Schema::hasColumn('deliveries', 'reattempt_requested_at');
        $availableTabs = ['all', 'incoming', 'received', 'scanned', 'sorted', 'dispatched'];
        if ($hasReturnedAt) {
            $availableTabs[] = 'returned';
        }
        if ($hasReattemptRequestedAt) {
            $availableTabs[] = 'reattempt';
        }

        $validated = $request->validate([
            'search' => 'sometimes|string|max:255',
            'tab' => ['sometimes', 'string', Rule::in($availableTabs)],
            'destination_center_id' => 'sometimes|integer',
            'destination_id' => 'sometimes|integer',
            'center_id' => 'sometimes|integer',
            'service_area_id' => 'sometimes|integer',
        ]);

        $user = Auth::user();
        // Null is unrestricted (global), 0 is an account with no valid
        // company/center (deny), positive ids narrow the query.
        $staffCenterId = LogisticsAccess::centerId($user);
        $companyId = LogisticsAccess::companyId($user);

        $destinationCenterId = (int) ($validated['destination_center_id'] ?? $validated['destination_id'] ?? $validated['center_id'] ?? 0);

        return [
            'hasReturnedAt' => $hasReturnedAt,
            'hasReattemptRequestedAt' => $hasReattemptRequestedAt,
            'availableTabs' => $availableTabs,
            'tab' => $validated['tab'] ?? 'all',
            'search' => trim((string) ($validated['search'] ?? '')),
            'destinationCenterId' => $destinationCenterId,
            'centerId' => $destinationCenterId,
            'serviceAreaId' => (int) ($validated['service_area_id'] ?? 0),
            'staffCenterId' => $staffCenterId,
            'companyId' => $companyId,
        ];
    }

    private function baseQuery(array $context, bool $includeIncoming = false)
    {
        $query = Delivery::notArchived();

        if ($context['staffCenterId'] !== null) {
            if ($context['staffCenterId'] === 0) {
                $query->whereRaw('1 = 0');
            } elseif (! $includeIncoming) {
                // The incoming tab widens the scope to active transfers
                // destined for this hub (applied by the caller).
                $query->where('center_id', $context['staffCenterId']);
            }
        }
        if ($context['companyId'] !== null) {
            if ($context['companyId'] === 0) {
                // Staff at a legacy hub without a company stay hub-scoped;
                // every other unassigned account sees nothing.
                if ($context['staffCenterId'] === null) {
                    $query->whereRaw('1 = 0');
                }
            } else {
                $query->whereIn('center_id', LogisticsCenter::query()->select('id')->where('logistics_company_id', $context['companyId']));
            }
        }

        return $query;
    }

    private function applyFilters($query, array $context): void
    {
        $search = $context['search'];
        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $query->where(function ($q) use ($like, $search) {
                $q->where('tracking_number', 'like', $like)
                    ->orWhere('sender_name', 'like', $like)
                    ->orWhere('recipient_name', 'like', $like);

                if (ctype_digit($search)) {
                    $q->orWhere('id', (int) $search)
                        ->orWhere('order_id', (int) $search);
                }
                if (Schema::hasColumn('orders', 'order_number')) {
                    $q->orWhereHas('order', fn ($order) => $order->where('order_number', 'like', $like));
                }
            });
        }

        match ($context['tab']) {
            // Incoming is resolved from active fleet transfers by the caller.
            'incoming' => null,
            'received' => $query->where('parcel_status', 'received'),
            'scanned' => $query->where('parcel_status', 'scanned'),
            'sorted' => $query->where('parcel_status', 'sorted'),
            'dispatched' => $query->where('parcel_status', 'dispatched'),
            'returned' => $context['hasReturnedAt'] ? $query->whereNotNull('returned_at') : null,
            'reattempt' => $context['hasReattemptRequestedAt'] ? $query->whereNotNull('reattempt_requested_at') : null,
            default => null,
        };

        $destinationId = $context['destinationCenterId'] ?: $context['centerId'];
        if ($destinationId > 0) {
            if ($context['tab'] === 'incoming') {
                $query->where(function ($q) use ($destinationId) {
                    $q->where('deliveries.destination_center_id', $destinationId)
                        ->orWhereHas('hubTransferParcels', function ($p) use ($destinationId) {
                            $p->where('loading_status', '!=', 'removed')
                                ->whereHas('transfer', fn ($t) => $t->whereIn('status', \App\Models\HubTransfer::ACTIVE_STATUSES)->where('destination_center_id', $destinationId));
                        });
                });
            } else {
                $query->where('deliveries.destination_center_id', $destinationId);
            }
        }
        if ($context['serviceAreaId'] > 0) {
            $query->where('deliveries.service_area_id', $context['serviceAreaId']);
        }
    }
}
