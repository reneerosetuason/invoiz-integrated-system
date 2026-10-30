<?php

namespace App\Http\Controllers\Logistics;

use App\Models\Delivery;
use App\Models\DeliveryStatusLog;
use App\Models\LogisticsCenter;
use App\Models\PickupRequest;
use App\Models\Rider;
use App\Models\ServiceArea;
use App\Models\Transaction;
use App\Models\User;
use App\Support\LogisticsAlerts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Human-readable labels for the delivery statuses actually used by the
     * Logistics System. Kept here so the dashboard reads as plain language.
     */
    private const STATUS_LABELS = [
        'waiting_for_rider' => 'Waiting for Rider',
        'assigned'          => 'Assigned',
        'accepted'          => 'Accepted',
        'going_to_pickup'   => 'Going to Pickup',
        'arrived_at_shop'   => 'Arrived at Shop',
        'picked_up'         => 'Picked Up',
        'out_for_delivery'  => 'Out for Delivery',
        'arrived_at_customer' => 'Arrived at Customer',
        'delivered'         => 'Delivered',
        'delivery_failed'   => 'Delivery Failed',
        'lost'              => 'Lost',
        'damaged'           => 'Damaged',
        'returned_to_seller' => 'Returned to Seller',
        'cancelled'         => 'Cancelled',
    ];

    private const STATUS_COLORS = [
        'waiting_for_rider' => '#F59E0B',
        'assigned'          => '#3B82F6',
        'accepted'          => '#06B6D4',
        'going_to_pickup'   => '#8B5CF6',
        'arrived_at_shop'   => '#6366F1',
        'picked_up'         => '#10B981',
        'out_for_delivery'  => '#A855F7',
        'arrived_at_customer' => '#EC4899',
        'delivered'         => '#059669',
        'delivery_failed'   => '#EF4444',
        'lost'              => '#991B1B',
        'damaged'           => '#F97316',
        'returned_to_seller' => '#64748B',
        'cancelled'         => '#6B7280',
    ];

    private const TREND_COLORS = [
        'created'   => '#16697A',
        'delivered' => '#059669',
        'failed'    => '#EF4444',
    ];

    public function index(Request $request): View
    {
        $user = Auth::user();
        // Null is global, 0 is a center-scoped staff account without a center.
        $staffCenterId = $user && $this->isCenterScopedStaff($user)
            ? (int) ($user->center_id ?? 0)
            : null;

        $trendsRange = $request->query('trends_range', '7');
        $trendsRange = in_array($trendsRange, ['7', '30'], true) ? (int) $trendsRange : 7;

        // Dashboard-wide shipment date filter. Dates are normalised to the
        // project's Y-m-d format; anything invalid is treated as "not set" so
        // a bad query string can never error or widen the company scope. A
        // reversed range (date_to before date_from) is not an error: both
        // bounds are applied as-is, which safely matches no shipments.
        $dateFrom = $this->normalizeDate($request->query('date_from'));
        $dateTo = $this->normalizeDate($request->query('date_to'));
        $dateFilterActive = $dateFrom !== null || $dateTo !== null;

        // ?????? Delivery status counts (single grouped query) ??????????????????????????????????????????
        $statusCounts = $this->applyDeliveryDateRange(
            $this->deliveryQuery($staffCenterId),
            $dateFrom,
            $dateTo
        )
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $deliveries = [
            'total' => (int) $statusCounts->sum(),
            'waiting_for_rider' => (int) ($statusCounts['waiting_for_rider'] ?? 0),
            'assigned' => (int) ($statusCounts['assigned'] ?? 0),
            'out_for_delivery' => (int) ($statusCounts['out_for_delivery'] ?? 0),
            'delivered' => (int) ($statusCounts['delivered'] ?? 0),
            'failed' => (int) (($statusCounts['delivery_failed'] ?? 0) + ($statusCounts['lost'] ?? 0) + ($statusCounts['damaged'] ?? 0)),
        ];

        // Use the same grouped counts and rider center/date scope for the
        // redesigned status cards and the deliveries list.
        $statusGroupCounts = [];
        foreach (Delivery::STATUS_GROUPS as $groupKey => $groupStatuses) {
            $statusGroupCounts[$groupKey] = (int) collect($groupStatuses)
                ->sum(fn ($status) => (int) ($statusCounts[$status] ?? 0));
        }

        // Needs attention: actionable operational counts, scoped to the
        // viewer's center exactly like the delivery list.
        $needsAttention = [
            'waitingForRider' => $deliveries['waiting_for_rider'],
            'failed' => $deliveries['failed'],
            // Parcels at a center that still need sorting/dispatch. This is
            // a parcel-stage measure; it must not depend on the rider status.
            'processing' => (int) $this->deliveryQuery($staffCenterId)
                ->whereIn('parcel_status', ['received', 'scanned', 'sorted'])
                ->whereNotIn('status', ['delivered', 'cancelled', 'delivery_failed', 'lost', 'damaged', 'returned_to_seller'])
                ->count(),
            'pickups' => (int) PickupRequest::query()->pendingReview($user)->count(),
        ];

        // Today's activity is intentionally separate from the all-time KPI
        // cards above so "Total" and "Delivered" cannot be misread as today.
        $today = now()->toDateString();
        $todayCounts = [
            'created' => (int) $this->deliveryQuery($staffCenterId)
                ->whereDate('created_at', $today)->count(),
            'delivered' => (int) $this->deliveryQuery($staffCenterId)
                ->whereNotNull('delivered_at')->whereDate('delivered_at', $today)->count(),
            'failed' => (int) $this->deliveryQuery($staffCenterId)
                ->whereNotNull('failed_at')->whereDate('failed_at', $today)->count(),
        ];

        $operationalAlerts = LogisticsAlerts::forUser($user);

        // ?????? Riders: online/offline availability ????????????????????????????????????????????????????????????????????????
        $riderQuery = Rider::query();
        if ($staffCenterId !== null) {
            if ($staffCenterId === 0) {
                $riderQuery->whereRaw('1 = 0');
            } else {
                $riderQuery->where('center_id', $staffCenterId);
            }
        }

        $riderCounts = (clone $riderQuery)
            ->selectRaw('COUNT(CASE WHEN is_online = 1 THEN 1 END) as online, COUNT(CASE WHEN is_online = 0 THEN 1 END) as offline, COUNT(*) as total')
            ->first();

        $riders = [
            'total' => (int) $riderCounts->total,
            'online' => (int) $riderCounts->online,
            'offline' => (int) $riderCounts->offline,
        ];

        // ?????? Rider availability + workload: online riders / rider list ??????
        $onlineRiders = (clone $riderQuery)
            ->where('is_online', true)
            ->withCount(['activeDeliveries as active_deliveries'])
            ->with('serviceArea')
            ->orderBy('name')
            ->limit(8)
            ->get();

        $riderWorkload = (clone $riderQuery)
            ->withCount(['activeDeliveries as active_deliveries'])
            ->with('serviceArea')
            ->orderByDesc('is_online')
            ->orderByDesc('active_deliveries')
            ->orderBy('name')
            ->limit(10)
            ->get();

        // ?????? Financial summary for today (uses stored transaction values) ???
        $financialQuery = Transaction::query();
        if ($staffCenterId !== null) {
            if ($staffCenterId === 0) {
                $financialQuery->whereRaw('1 = 0');
            } else {
                $financialQuery->where('logistics_center_id', $staffCenterId);
            }
        }
        $financial = $financialQuery->whereDate('created_at', $today)
            ->selectRaw('
                COUNT(*) as count,
                COALESCE(SUM(amount), 0) as amount,
                COALESCE(SUM(rider_fee), 0) as rider_fee,
                COALESCE(SUM(admin_commission), 0) as commission
            ')->first();

        $financialToday = [
            'period' => 'Today',
            'count' => (int) ($financial->count ?? 0),
            'amount' => (float) ($financial->amount ?? 0),
            'rider_fee' => (float) ($financial->rider_fee ?? 0),
            'commission' => (float) ($financial->commission ?? 0),
        ];

        // ?????? Service Area overview (total + active deliveries per area) ??????
        $areaTotals = $this->deliveryQuery($staffCenterId)
            ->select('service_area_id')
            ->selectRaw('count(*) as total')
            ->groupBy('service_area_id')
            ->pluck('total', 'service_area_id');

        $areaActive = $this->deliveryQuery($staffCenterId)
            ->whereIn('status', Delivery::ACTIVE_STATUSES)
            ->select('service_area_id')
            ->selectRaw('count(*) as active')
            ->groupBy('service_area_id')
            ->pluck('active', 'service_area_id');

        if ($areaTotals->isNotEmpty()) {
            $areaNames = ServiceArea::whereIn('id', $areaTotals->keys())->pluck('name', 'id');
        } else {
            $areaNames = collect();
        }

        $serviceAreas = $areaTotals->map(function ($total, $id) use ($areaActive, $areaNames) {
            return [
                'id' => (int) $id,
                'name' => $areaNames[$id] ?? 'Unassigned Area',
                'total' => (int) $total,
                'active' => (int) ($areaActive[$id] ?? 0),
            ];
        })->values()
            ->sortByDesc('active')
            ->values()
            ->take(8);

        // ?????? Recent logistics activity (real status-log events) ??????????????????????????????
        $activityQuery = DeliveryStatusLog::query()
            ->select('delivery_status_logs.*')
            ->join('deliveries', 'deliveries.id', '=', 'delivery_status_logs.delivery_id')
            ->whereNull('deliveries.archived_at')
            ->with(['delivery:id,tracking_number,recipient_name', 'changer:id,name']);

        if ($staffCenterId !== null) {
            if ($staffCenterId === 0) {
                $activityQuery->whereRaw('1 = 0');
            } else {
                $activityQuery->where('deliveries.center_id', $staffCenterId);
            }
        }

        $activities = $activityQuery
            ->orderByDesc('delivery_status_logs.created_at')
            ->limit(8)
            ->get()
            ->map(function ($log) {
                return [
                    'tracking' => $log->delivery?->tracking_number ?? '',
                    'recipient' => $log->delivery?->recipient_name ?? '',
                    'message' => $this->activityMessage($log->status),
                    'status' => $log->status,
                    'created_at' => $log->created_at,
                    'by' => $log->changer?->name,
                ];
            });

        // ?????? Chart: Delivery Status Overview (doughnut) ?????????????????????????????????????????????????????????
        $statusChart = $this->buildStatusChartData($statusCounts, $staffCenterId);

        // ?????? Chart: Delivery Trends (last 7 / 30 days) ????????????????????????????????????????????????????????????
        $trendsChart = $this->buildTrendsChartData($staffCenterId, $trendsRange);

        // ?????? Chart: Center Performance (bar) ??????????????????????????????????????????????????????????????????????????????????????????
        $centerPerformanceChart = $this->buildCenterPerformanceData($staffCenterId);

        // ?????? Detailed Records: latest 50 for frontend-only search/sort/paginate.
        // Intentionally lightweight (not the full Deliveries manager): the
        // limit applies before any filtering, and the view links out to
        // deliveries.index for server-side search across all records.
        $recentDeliveriesForView = $this->applyDeliveryDateRange($this->deliveryQuery($staffCenterId), $dateFrom, $dateTo)
            ->latest('updated_at')
            ->with(['rider', 'serviceArea', 'logisticsCenter'])
            ->take(6)
            ->get();

        $detailedRecords = $this->applyDeliveryDateRange($this->deliveryQuery($staffCenterId), $dateFrom, $dateTo)
            ->latest('updated_at')
            ->with(['logisticsCenter'])
            ->take(50)
            ->get()
            ->map(fn($d) => [
                'id' => $d->id,
                'tracking' => $d->tracking_number,
                'recipient' => $d->recipient_name ?? '—',
                'center' => $d->logisticsCenter?->name ?? ($d->center_id ? 'Center #'.$d->center_id : 'Unassigned'),
                'center_id' => $d->center_id,
                'status' => ucwords(str_replace('_',' ', $d->status)),
                'statusRaw' => $d->status,
                'updated' => $d->updated_at?->diffForHumans(),
                'updated_at' => $d->updated_at?->toIso8601String(),
                'showUrl' => route('deliveries.show', $d),
            ])
            ->values();

        return view('logistics.dashboard', [
            'deliveries' => $deliveries,
            'statusGroupCounts' => $statusGroupCounts,
            'riders' => $riders,
            'onlineRiders' => $onlineRiders,
            'riderWorkload' => $riderWorkload,
            'financialToday' => $financialToday,
            'status_counts' => $statusCounts,
            'status_labels' => self::STATUS_LABELS,
            'needsAttention' => $needsAttention,
            'todayCounts' => $todayCounts,
            'operationalAlerts' => $operationalAlerts,
            'alertTotal' => (int) collect($operationalAlerts)->sum('count'),
            'serviceAreas' => $serviceAreas,
            'activities' => $activities,
            'staffCenterId' => $staffCenterId,
            'activeStatuses' => Delivery::ACTIVE_STATUSES,
            'recent_deliveries' => $recentDeliveriesForView,
            'detailedRecords' => $detailedRecords,
            'statusChart' => $statusChart,
            'trendsChart' => $trendsChart,
            'trendsRange' => $trendsRange,
            'centerPerformanceChart' => $centerPerformanceChart,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'dateFilterActive' => $dateFilterActive,
        ]);
    }

    /**
     * Normalise an incoming date to the project's Y-m-d format. Returns null
     * for blank, malformed, or impossible calendar dates (for example
     * 2026-02-30) so the caller simply skips that bound.
     */
    private function normalizeDate($value): ?string
    {
        $value = trim((string) $value);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year) ? $value : null;
    }

    /**
     * Apply the dashboard shipment date filter to a delivery query by the
     * shipment's registration date (created_at). This is the same business
     * date used by the Deliveries list, so dashboard counts stay consistent
     * with the filtered list. Only applied when at least one bound is set.
     */
    private function applyDeliveryDateRange($query, ?string $dateFrom, ?string $dateTo)
    {
        if ($dateFrom !== null) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        return $query;
    }

    /**
     * Base delivery query with the existing staff center-scoping applied at the
     * query level (never bypassable via a query parameter).
     */
    private function deliveryQuery(?int $staffCenterId)
    {
        $query = Delivery::notArchived();

        if ($staffCenterId === null) {
            return $query;
        }

        if ($staffCenterId === 0) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('center_id', $staffCenterId);
    }

    private function isCenterScopedStaff(User $user): bool
    {
        return $user->isLogisticsStaff()
            && !$user->isAdmin()
            && !$user->isLogisticsAdmin();
    }

    private function buildStatusChartData($statusCounts, ?int $staffCenterId): array
    {
        $labels = [];
        $data = [];
        $colors = [];
        $total = (int) $statusCounts->sum();

        foreach (self::STATUS_LABELS as $key => $label) {
            $count = (int) ($statusCounts[$key] ?? 0);
            if ($count === 0) {
                continue;
            }
            // Map internal parcel_status values to human labels if needed
            $labels[] = $label;
            $data[] = $count;
            $colors[] = self::STATUS_COLORS[$key] ?? '#6B7280';
        }

        // Include parcel_status breakdown for sorting center stages if they exist
        $parcelCounts = $this->deliveryQueryForParcelStatus($staffCenterId);
        foreach (['pending_arrival' => 'Pending Arrival', 'received' => 'Received', 'scanned' => 'Scanned', 'sorted' => 'Sorted', 'dispatched' => 'Dispatched'] as $pKey => $pLabel) {
            $count = (int) ($parcelCounts[$pKey] ?? 0);
            if ($count > 0 && !in_array($pLabel, $labels, true)) {
                // Only add if not already covered by delivery status
                // Keep chart focused on delivery status; parcel stages are secondary
            }
        }

        return [
            'labels' => $labels,
            'data' => $data,
            'colors' => $colors,
            'total' => $total,
        ];
    }

    private function deliveryQueryForParcelStatus(?int $staffCenterId)
    {
        return $this->deliveryQuery($staffCenterId)
            ->selectRaw('parcel_status, count(*) as total')
            ->groupBy('parcel_status')
            ->pluck('total', 'parcel_status');
    }

    private function buildTrendsChartData(?int $staffCenterId, int $range): array
    {
        $end = now()->toDateString();
        $start = now()->subDays($range - 1)->toDateString();

        $dates = [];
        $period = new \DatePeriod(
            new \DateTime($start),
            new \DateInterval('P1D'),
            (new \DateTime($end))->modify('+1 day')
        );
        foreach ($period as $date) {
            $dates[] = $date->format('Y-m-d');
        }

        $createdByDate = $this->deliveryQuery($staffCenterId)
            ->whereBetween('created_at', [$start . ' 00:00:00', $end . ' 23:59:59'])
            ->selectRaw('DATE(created_at) as d, count(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd')
            ->map(fn ($v) => (int) $v);

        $deliveredByDate = $this->deliveryQuery($staffCenterId)
            ->where('status', 'delivered')
            ->whereNotNull('delivered_at')
            ->whereBetween('delivered_at', [$start . ' 00:00:00', $end . ' 23:59:59'])
            ->selectRaw('DATE(delivered_at) as d, count(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd')
            ->map(fn ($v) => (int) $v);

        $failedByDate = $this->deliveryQuery($staffCenterId)
            ->whereIn('status', ['delivery_failed', 'lost', 'damaged'])
            ->whereNotNull('failed_at')
            ->whereBetween('failed_at', [$start . ' 00:00:00', $end . ' 23:59:59'])
            ->selectRaw('DATE(failed_at) as d, count(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd')
            ->map(fn ($v) => (int) $v);

        $labels = [];
        $created = [];
        $delivered = [];
        $failed = [];

        foreach ($dates as $d) {
            $labels[] = \Carbon\Carbon::parse($d)->format('M j');
            $created[] = (int) ($createdByDate[$d] ?? 0);
            $delivered[] = (int) ($deliveredByDate[$d] ?? 0);
            $failed[] = (int) ($failedByDate[$d] ?? 0);
        }

        return [
            'labels' => $labels,
            'dates' => $dates,
            'created' => $created,
            'delivered' => $delivered,
            'failed' => $failed,
            'colors' => self::TREND_COLORS,
        ];
    }

    private function buildCenterPerformanceData(?int $staffCenterId): array
    {
        if ($staffCenterId === 0) {
            return ['labels' => [], 'total' => [], 'delivered' => [], 'pending' => [], 'failed' => [], 'empty' => true];
        }

        if ($staffCenterId !== null) {
            $centers = LogisticsCenter::where('id', $staffCenterId)->where('is_active', true)->get(['id', 'name']);
        } else {
            $centers = LogisticsCenter::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        }

        if ($centers->isEmpty()) {
            return ['labels' => [], 'total' => [], 'delivered' => [], 'pending' => [], 'failed' => [], 'empty' => true];
        }

        $centerIds = $centers->pluck('id');

        $totals = Delivery::notArchived()->whereIn('center_id', $centerIds)
            ->selectRaw('center_id, count(*) as c')->groupBy('center_id')->pluck('c', 'center_id');
        $delivered = Delivery::notArchived()->whereIn('center_id', $centerIds)->where('status', 'delivered')
            ->selectRaw('center_id, count(*) as c')->groupBy('center_id')->pluck('c', 'center_id');
        $pending = Delivery::notArchived()->whereIn('center_id', $centerIds)->where('status', 'waiting_for_rider')
            ->selectRaw('center_id, count(*) as c')->groupBy('center_id')->pluck('c', 'center_id');
        $failed = Delivery::notArchived()->whereIn('center_id', $centerIds)->whereIn('status', ['delivery_failed', 'lost', 'damaged'])
            ->selectRaw('center_id, count(*) as c')->groupBy('center_id')->pluck('c', 'center_id');

        // Staff sees only own center; admin sees all. Enforce scoping already via $centerIds.
        $labels = [];
        $totalData = [];
        $deliveredData = [];
        $pendingData = [];
        $failedData = [];

        foreach ($centers as $center) {
            $labels[] = $center->name;
            $totalData[] = (int) ($totals[$center->id] ?? 0);
            $deliveredData[] = (int) ($delivered[$center->id] ?? 0);
            $pendingData[] = (int) ($pending[$center->id] ?? 0);
            $failedData[] = (int) ($failed[$center->id] ?? 0);
        }

        // If all totals are zero and staff, show empty state
        $hasData = array_sum($totalData) > 0;

        return [
            'labels' => $labels,
            'total' => $totalData,
            'delivered' => $deliveredData,
            'pending' => $pendingData,
            'failed' => $failedData,
            'empty' => !$hasData,
        ];
    }

    private function activityMessage(?string $status): string
    {
        return match ($status) {
            'waiting_for_rider' => 'registered, waiting for rider',
            'assigned' => 'assigned to a rider',
            'accepted' => 'accepted by rider',
            'going_to_pickup' => 'rider en route to pickup',
            'arrived_at_shop' => 'rider arrived at shop',
            'picked_up' => 'parcel picked up',
            'out_for_delivery' => 'marked out for delivery',
            'arrived_at_customer' => 'rider arrived at customer',
            'delivered' => 'delivered successfully',
            'delivery_failed' => 'failed delivery',
            'lost' => 'reported lost',
            'damaged' => 'reported damaged',
            'returned_to_seller' => 'returned to seller',
            'cancelled' => 'cancelled',
            default => 'status updated',
        };
    }
}




