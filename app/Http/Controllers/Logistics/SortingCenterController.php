<?php

namespace App\Http\Controllers\Logistics;

use App\Models\Delivery;
use App\Models\LogisticsCenter;
use App\Models\ServiceArea;
use App\Support\CsvExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Standalone Sorting Center operational hub.
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
        $base = $this->baseQuery($context);

        $counts = [
            'incoming' => (clone $base)->incoming($context['staffCenterId'])->count(),
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

        $query = $this->baseQuery($context)
            ->with(['rider', 'logisticsCenter', 'destinationCenter', 'originCenter', 'interHubRider', 'serviceArea', 'statusLogs']);
        $this->applyFilters($query, $context);

        $centers = LogisticsCenter::where('is_active', true)->orderBy('name');
        $serviceAreas = ServiceArea::where('is_active', true)->orderBy('name');
        if ($context['staffCenterId'] !== null) {
            if ($context['staffCenterId'] === 0) {
                $centers->whereRaw('1 = 0');
                $serviceAreas->whereRaw('1 = 0');
            } else {
                $centers->whereKey($context['staffCenterId']);
                $serviceAreas->where('logistics_center_id', $context['staffCenterId']);
            }
        }

        return view('logistics.sorting-center.index', [
            'counts' => $counts,
            'deliveries' => $query->latest()->paginate(10)->withQueryString(),
            'tab' => $context['tab'],
            'availableTabs' => $context['availableTabs'],
            'hasReturnedAt' => $context['hasReturnedAt'],
            'hasReattemptRequestedAt' => $context['hasReattemptRequestedAt'],
            'search' => $context['search'],
            'centerId' => $context['centerId'],
            'serviceAreaId' => $context['serviceAreaId'],
            'centers' => $centers->get(),
            'serviceAreas' => $serviceAreas->get(),
            'destinationCenters' => LogisticsCenter::where('is_active', true)->orderBy('name')->get(),
            'destinationAreas' => ServiceArea::where('is_active', true)->orderBy('name')->get(),
            'transferDrivers' => \App\Models\Rider::where('status', '!=', 'inactive')->orderBy('name')->get()->groupBy('center_id'),
            'staffCenterId' => $context['staffCenterId'],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $context = $this->requestContext($request);

        $query = $this->baseQuery($context)
            ->with(['rider', 'logisticsCenter', 'destinationCenter', 'serviceArea']);
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
            'center_id' => 'sometimes|integer',
            'service_area_id' => 'sometimes|integer',
        ]);

        $user = Auth::user();
        $isCenterScoped = $user
            && $user->isLogisticsStaff()
            && ! $user->isAdmin()
            && ! $user->isLogisticsAdmin();
        // Null is global, 0 is a scoped staff account with no center.
        $staffCenterId = $isCenterScoped ? (int) ($user->center_id ?? 0) : null;

        return [
            'hasReturnedAt' => $hasReturnedAt,
            'hasReattemptRequestedAt' => $hasReattemptRequestedAt,
            'availableTabs' => $availableTabs,
            'tab' => $validated['tab'] ?? 'all',
            'search' => trim((string) ($validated['search'] ?? '')),
            'centerId' => (int) ($validated['center_id'] ?? 0),
            'serviceAreaId' => (int) ($validated['service_area_id'] ?? 0),
            'staffCenterId' => $staffCenterId,
        ];
    }

    private function baseQuery(array $context)
    {
        $query = Delivery::notArchived();

        if ($context['staffCenterId'] !== null) {
            if ($context['staffCenterId'] === 0) {
                $query->whereRaw('1 = 0');
            } else {
                $query->atOrIncomingTo($context['staffCenterId']);
            }
        }

        return $query;
    }

    private function applyFilters($query, array $context): void
    {
        $search = $context['search'];
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('tracking_number', 'like', '%' . $search . '%')
                    ->orWhere('sender_name', 'like', '%' . $search . '%')
                    ->orWhere('recipient_name', 'like', '%' . $search . '%');
            });
        }

        match ($context['tab']) {
            'incoming' => $query->incoming($context['staffCenterId']),
            'received' => $query->where('parcel_status', 'received'),
            'scanned' => $query->where('parcel_status', 'scanned'),
            'sorted' => $query->where('parcel_status', 'sorted'),
            'dispatched' => $query->where('parcel_status', 'dispatched'),
            'returned' => $context['hasReturnedAt'] ? $query->whereNotNull('returned_at') : null,
            'reattempt' => $context['hasReattemptRequestedAt'] ? $query->whereNotNull('reattempt_requested_at') : null,
            default => null,
        };

        // Admins may narrow by center; staff are already center-locked.
        if ($context['centerId'] > 0 && $context['staffCenterId'] === null) {
            $query->where('center_id', $context['centerId']);
        }
        if ($context['serviceAreaId'] > 0) {
            $query->where('service_area_id', $context['serviceAreaId']);
        }
    }
}
