<?php

namespace App\Http\Controllers\Logistics;

use App\Models\Delivery;
use App\Models\Rider;
use App\Models\Transaction;
use App\Models\LogisticsCenter;
use App\Models\ServiceArea;
use App\Support\CsvExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'delivery');
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));
        $centerId = (int) $request->query('center_id', 0);
        $riderId = (int) $request->query('rider_id', 0);
        $serviceAreaId = (int) $request->query('service_area_id', 0);

        $user = Auth::user();
        $staffCenterId = $this->staffCenterScope($user);

        if ($staffCenterId !== null) {
            $centerId = $staffCenterId;
        }

        $deliveryStats = $this->deliveryStats($dateFrom, $dateTo, $centerId, $riderId, $serviceAreaId, $staffCenterId);
        $centerStats = $this->centerStats($dateFrom, $dateTo, $staffCenterId);
        $areaStats = $this->areaStats($dateFrom, $dateTo, $centerId, $staffCenterId);
        $riderStats = $this->riderStats($dateFrom, $dateTo, $centerId, $riderId, $staffCenterId);
        $financialStats = $this->financialStats($dateFrom, $dateTo, $centerId, $staffCenterId);

        $centersQuery = LogisticsCenter::query();
        $ridersQuery = Rider::query();
        $serviceAreasQuery = ServiceArea::query();

        if ($staffCenterId === 0) {
            $centersQuery->whereRaw('1 = 0');
            $ridersQuery->whereRaw('1 = 0');
            $serviceAreasQuery->whereRaw('1 = 0');
        } elseif ($staffCenterId !== null) {
            $centersQuery->whereKey($staffCenterId);
            $ridersQuery->where('center_id', $staffCenterId);
            $serviceAreasQuery->where('logistics_center_id', $staffCenterId);
        } else {
            $centersQuery->where('is_active', true);
            $serviceAreasQuery->where('is_active', true);
        }

        return view('logistics.reports.index', [
            'tab' => $tab,
            'deliveryStats' => $deliveryStats,
            'centerStats' => $centerStats,
            'areaStats' => $areaStats,
            'riderStats' => $riderStats,
            'financialStats' => $financialStats,
            'centers' => $centersQuery->orderBy('name')->get(),
            'riders' => $ridersQuery->orderBy('name')->get(['id', 'name']),
            'serviceAreas' => $serviceAreasQuery->orderBy('name')->get(['id', 'name', 'logistics_center_id']),
            'filters' => compact('dateFrom', 'dateTo', 'centerId', 'riderId', 'serviceAreaId'),
        ]);
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $tab = $request->query('tab', 'delivery');
        $validTabs = ['all', 'delivery', 'center', 'area', 'rider', 'financial'];
        if (!in_array($tab, $validTabs, true)) {
            $tab = 'delivery';
        }

        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));
        $centerId = (int) $request->query('center_id', 0);
        $riderId = (int) $request->query('rider_id', 0);
        $serviceAreaId = (int) $request->query('service_area_id', 0);

        $user = Auth::user();
        $staffCenterId = $this->staffCenterScope($user);

        if ($staffCenterId !== null) {
            $centerId = $staffCenterId;
        }

        $parsedFrom = $dateFrom !== '' && strtotime($dateFrom) ? $dateFrom : null;
        $parsedTo = $dateTo !== '' && strtotime($dateTo) ? $dateTo : null;

        switch ($tab) {
            case 'all':
                $stats = [
                    'delivery' => $this->deliveryStats($dateFrom, $dateTo, $centerId, $riderId, $serviceAreaId, $staffCenterId),
                    'center' => $this->centerStats($dateFrom, $dateTo, $staffCenterId),
                    'area' => $this->areaStats($dateFrom, $dateTo, $centerId, $staffCenterId),
                    'rider' => $this->riderStats($dateFrom, $dateTo, $centerId, $riderId, $staffCenterId),
                    'financial' => $this->financialStats($dateFrom, $dateTo, $centerId, $staffCenterId),
                ];
                break;
            case 'delivery':
                $stats = $this->deliveryStats($dateFrom, $dateTo, $centerId, $riderId, $serviceAreaId, $staffCenterId);
                break;
            case 'center':
                $stats = $this->centerStats($dateFrom, $dateTo, $staffCenterId);
                break;
            case 'area':
                $stats = $this->areaStats($dateFrom, $dateTo, $centerId, $staffCenterId);
                break;
            case 'rider':
                $stats = $this->riderStats($dateFrom, $dateTo, $centerId, $riderId, $staffCenterId);
                break;
            case 'financial':
            default:
                $stats = $this->financialStats($dateFrom, $dateTo, $centerId, $staffCenterId);
                break;
        }

        $centerName = null;
        if ($centerId > 0) {
            $centerName = LogisticsCenter::find($centerId)?->name;
        }

        $reportTitles = [
            'all' => 'Consolidated Logistics Report',
            'delivery' => 'Delivery Report',
            'center' => 'Center Report',
            'area' => 'Service Area Report',
            'rider' => 'Rider Report',
            'financial' => 'Financial Report',
        ];

        $common = [
            'title' => $reportTitles[$tab],
            'dateFrom' => $parsedFrom,
            'dateTo' => $parsedTo,
            'preparedBy' => $this->preparedByName($user),
            'generatedAt' => now()->format('Y-m-d H:i:s'),
            'centerName' => $centerName,
        ];

        if ($tab === 'all') {
            $pdf = Pdf::loadView('logistics.reports.pdf-all', array_merge($common, ['stats' => $stats]));
        } else {
            $pdf = Pdf::loadView('logistics.reports.pdf', array_merge($common, ['tab' => $tab, 'stats' => $stats]));
        }

        $pdf->setPaper('a4', 'portrait');

        $filename = 'invoiz-logistics-' . ($tab === 'all' ? 'all-reports' : $tab . '-report') . '-' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $tab = $request->query('tab', 'delivery');
        $validTabs = ['all', 'delivery', 'center', 'area', 'rider', 'financial'];
        if (! in_array($tab, $validTabs, true)) {
            $tab = 'delivery';
        }

        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));
        $centerId = (int) $request->query('center_id', 0);
        $riderId = (int) $request->query('rider_id', 0);
        $serviceAreaId = (int) $request->query('service_area_id', 0);

        $user = Auth::user();
        $staffCenterId = $this->staffCenterScope($user);

        if ($staffCenterId !== null) {
            $centerId = $staffCenterId;
        }

        $stats = match ($tab) {
            'all' => [
                'delivery' => $this->deliveryStats($dateFrom, $dateTo, $centerId, $riderId, $serviceAreaId, $staffCenterId),
                'center' => $this->centerStats($dateFrom, $dateTo, $staffCenterId),
                'area' => $this->areaStats($dateFrom, $dateTo, $centerId, $staffCenterId),
                'rider' => $this->riderStats($dateFrom, $dateTo, $centerId, $riderId, $staffCenterId),
                'financial' => $this->financialStats($dateFrom, $dateTo, $centerId, $staffCenterId),
            ],
            'center' => $this->centerStats($dateFrom, $dateTo, $staffCenterId),
            'area' => $this->areaStats($dateFrom, $dateTo, $centerId, $staffCenterId),
            'rider' => $this->riderStats($dateFrom, $dateTo, $centerId, $riderId, $staffCenterId),
            'financial' => $this->financialStats($dateFrom, $dateTo, $centerId, $staffCenterId),
            default => $this->deliveryStats($dateFrom, $dateTo, $centerId, $riderId, $serviceAreaId, $staffCenterId),
        };

        [$headers, $rows] = $this->csvRows($tab, $stats);

        return CsvExport::stream(
            'invoiz-logistics-' . $tab . '-report-' . now()->format('Y-m-d') . '.csv',
            $headers,
            $rows,
            $user,
            'logistics.reports.' . $tab,
            [
                'tab' => $tab,
                'date_from' => $dateFrom !== '' ? $dateFrom : null,
                'date_to' => $dateTo !== '' ? $dateTo : null,
                'center_id' => $centerId > 0 ? $centerId : null,
                'rider_id' => $riderId > 0 ? $riderId : null,
                'service_area_id' => $serviceAreaId > 0 ? $serviceAreaId : null,
                'center_scope' => $staffCenterId !== null ? (int) $staffCenterId : 'global',
            ]
        );
    }

    private function csvRows(string $tab, array $stats): array
    {
        if ($tab === 'all') {
            $rows = [];
            foreach (['delivery', 'center', 'area', 'rider', 'financial'] as $section) {
                [$sectionHeaders, $sectionRows] = $this->csvRows($section, $stats[$section] ?? []);
                $rows[] = [ucfirst($section) . ' Report'];
                $rows[] = $sectionHeaders;
                foreach ($sectionRows as $row) {
                    $rows[] = $row;
                }
                $rows[] = [];
            }

            return [[], $rows];
        }

        if ($tab === 'center') {
            return [
                ['Center', 'City', 'Deliveries', 'Service Areas', 'Riders'],
                array_map(fn ($center) => [
                    $center['name'] ?? '',
                    $center['city'] ?? '',
                    (int) ($center['deliveries_count'] ?? 0),
                    (int) ($center['service_areas_count'] ?? 0),
                    (int) ($center['riders_count'] ?? 0),
                ], $stats),
            ];
        }

        if ($tab === 'area') {
            $centerNames = LogisticsCenter::pluck('name', 'id');

            return [
                ['Service Area', 'Center', 'Deliveries', 'Riders'],
                array_map(fn ($area) => [
                    $area['name'] ?? '',
                    $centerNames[(int) ($area['logistics_center_id'] ?? 0)] ?? '',
                    (int) ($area['deliveries_count'] ?? 0),
                    (int) ($area['riders_count'] ?? 0),
                ], $stats),
            ];
        }

        if ($tab === 'rider') {
            return [
                ['Rider', 'Vehicle', 'Total Deliveries', 'Active Deliveries', 'Completed Deliveries'],
                array_map(fn ($rider) => [
                    $rider['name'] ?? '',
                    ucfirst((string) ($rider['vehicle_type'] ?? '')),
                    (int) ($rider['total_deliveries'] ?? 0),
                    (int) ($rider['active_deliveries'] ?? 0),
                    (int) ($rider['completed_deliveries'] ?? 0),
                ], $stats),
            ];
        }

        if ($tab === 'financial') {
            return [
                ['Metric', 'Value'],
                [
                    ['Transactions', (int) ($stats['total_transactions'] ?? 0)],
                    ['Total Amount', (float) ($stats['total_amount'] ?? 0)],
                    ['Total Rider Fees', (float) ($stats['total_rider_fees'] ?? 0)],
                    ['Management Commissions', (float) ($stats['total_commissions'] ?? 0)],
                    ['Completed Amount', (float) ($stats['completed_amount'] ?? 0)],
                    ['Completed Rider Fees', (float) ($stats['completed_rider_fees'] ?? 0)],
                    ['Completed Commissions', (float) ($stats['completed_commissions'] ?? 0)],
                ],
            ];
        }

        return [
            ['Metric', 'Value'],
            [
                ['Total Deliveries', (int) ($stats['total'] ?? 0)],
                ['Received', (int) ($stats['received'] ?? 0)],
                ['Scanned', (int) ($stats['scanned'] ?? 0)],
                ['Sorted', (int) ($stats['sorted'] ?? 0)],
                ['Waiting for Rider', (int) ($stats['waiting_for_rider'] ?? 0)],
                ['Assigned', (int) ($stats['assigned'] ?? 0)],
                ['Picked Up', (int) ($stats['picked_up'] ?? 0)],
                ['Out for Delivery', (int) ($stats['out_for_delivery'] ?? 0)],
                ['Delivered', (int) ($stats['delivered'] ?? 0)],
                ['Failed', (int) ($stats['failed'] ?? 0)],
                ['Cancelled', (int) ($stats['cancelled'] ?? 0)],
            ],
        ];
    }

    /**
     * Null means global access, 0 means a centerless scoped account, and a
     * positive id means that account's assigned center.
     */
    private function staffCenterScope($user): ?int
    {
        if (! $user) {
            return 0;
        }

        if ($user->isLogisticsAdmin()) {
            return null;
        }

        if (! $user->isLogisticsStaff()) {
            return 0;
        }

        $centerId = (int) ($user->center_id ?? 0);

        return $centerId > 0 ? $centerId : 0;
    }

    private function preparedByName($user): string
    {
        // Use the user-facing role label (e.g. "Logistics Manager" for the
        // internal admin role, "Logistics Staff" for staff) rather than the
        // raw internal name/role so reports read as plain language.
        return (string) $user->roleLabel();
    }

    private function baseQuery(?string $dateFrom, ?string $dateTo, int $centerId, ?int $staffCenterId): \Illuminate\Database\Eloquent\Builder
    {
        $query = Delivery::query();

        $effectiveCenter = $staffCenterId !== null ? $staffCenterId : $centerId;
        if ($staffCenterId === 0) {
            $query->whereRaw('1 = 0');
        } elseif ($effectiveCenter > 0) {
            $query->where('center_id', $effectiveCenter);
        }

        if ($dateFrom !== '' && strtotime($dateFrom)) {
            $query->whereDate('deliveries.created_at', '>=', $dateFrom);
        }
        if ($dateTo !== '' && strtotime($dateTo)) {
            $query->whereDate('deliveries.created_at', '<=', $dateTo);
        }

        return $query;
    }

    private function deliveryStats(?string $dateFrom, ?string $dateTo, int $centerId, int $riderId, int $serviceAreaId, ?int $staffCenterId): array
    {
        $query = $this->baseQuery($dateFrom, $dateTo, $centerId, $staffCenterId);

        if ($riderId > 0) {
            $query->where('rider_id', $riderId);
        }
        if ($serviceAreaId > 0) {
            $query->where('service_area_id', $serviceAreaId);
        }

        $statusCounts = $query->toBase()
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $parcelQuery = $this->baseQuery($dateFrom, $dateTo, $centerId, $staffCenterId);
        if ($riderId > 0) {
            $parcelQuery->where('rider_id', $riderId);
        }
        if ($serviceAreaId > 0) {
            $parcelQuery->where('service_area_id', $serviceAreaId);
        }
        $parcelCounts = $parcelQuery->toBase()
            ->select('parcel_status', DB::raw('COUNT(*) as count'))
            ->groupBy('parcel_status')
            ->pluck('count', 'parcel_status')
            ->toArray();

        $total = array_sum($statusCounts);

        return [
            'total' => $total,
            'received' => $parcelCounts['received'] ?? 0,
            'scanned' => $parcelCounts['scanned'] ?? 0,
            'sorted' => $parcelCounts['sorted'] ?? 0,
            'waiting_for_rider' => $statusCounts['waiting_for_rider'] ?? 0,
            'assigned' => $statusCounts['assigned'] ?? 0,
            'picked_up' => $statusCounts['picked_up'] ?? 0,
            'out_for_delivery' => $statusCounts['out_for_delivery'] ?? 0,
            'delivered' => $statusCounts['delivered'] ?? 0,
            'failed' => (int) (($statusCounts['delivery_failed'] ?? 0)
                + ($statusCounts['lost'] ?? 0)
                + ($statusCounts['damaged'] ?? 0)),
            'cancelled' => $statusCounts['cancelled'] ?? 0,
        ];
    }

    private function centerStats(?string $dateFrom, ?string $dateTo, ?int $staffCenterId): array
    {
        $query = LogisticsCenter::query()
            ->withCount(['serviceAreas', 'riders'])
            ->withCount(['deliveries' => function ($q) use ($dateFrom, $dateTo) {
                if ($dateFrom !== '' && strtotime($dateFrom)) {
                    $q->whereDate('created_at', '>=', $dateFrom);
                }
                if ($dateTo !== '' && strtotime($dateTo)) {
                    $q->whereDate('created_at', '<=', $dateTo);
                }
            }]);

        if ($staffCenterId === 0) {
            $query->whereRaw('1 = 0');
        } elseif ($staffCenterId) {
            $query->where('id', $staffCenterId);
        }

        return $query->orderBy('name')->get()->toArray();
    }

    private function areaStats(?string $dateFrom, ?string $dateTo, int $centerId, ?int $staffCenterId): array
    {
        $effectiveCenter = $staffCenterId !== null ? $staffCenterId : $centerId;
        $query = ServiceArea::query()->withCount([
            'deliveries' => function ($q) use ($dateFrom, $dateTo) {
                if ($dateFrom !== '' && strtotime($dateFrom)) {
                    $q->whereDate('deliveries.created_at', '>=', $dateFrom);
                }
                if ($dateTo !== '' && strtotime($dateTo)) {
                    $q->whereDate('deliveries.created_at', '<=', $dateTo);
                }
            },
            'riders',
        ]);

        if ($staffCenterId === 0) {
            $query->whereRaw('1 = 0');
        } elseif ($effectiveCenter > 0) {
            $query->where('logistics_center_id', $effectiveCenter);
        }

        return $query->orderBy('name')->get()->toArray();
    }

    private function riderStats(?string $dateFrom, ?string $dateTo, int $centerId, int $riderId, ?int $staffCenterId): array
    {
        $effectiveCenter = $staffCenterId !== null ? $staffCenterId : $centerId;
        $applyDateFilter = function ($q) use ($dateFrom, $dateTo) {
            if ($dateFrom !== '' && strtotime($dateFrom)) {
                $q->whereDate('deliveries.created_at', '>=', $dateFrom);
            }
            if ($dateTo !== '' && strtotime($dateTo)) {
                $q->whereDate('deliveries.created_at', '<=', $dateTo);
            }
        };

        $query = Rider::query()
            ->withCount([
                'deliveries as total_deliveries' => $applyDateFilter,
                'activeDeliveries as active_deliveries',
            ])
            ->withCount(['deliveries as completed_deliveries' => function ($q) use ($applyDateFilter) {
                $q->where('status', 'delivered');
                $applyDateFilter($q);
            }]);

        if ($staffCenterId === 0) {
            $query->whereRaw('1 = 0');
        } elseif ($effectiveCenter > 0) {
            $query->where('center_id', $effectiveCenter);
        }

        if ($riderId > 0) {
            $query->where('id', $riderId);
        }

        return $query->orderBy('name')->get()->toArray();
    }

    private function financialStats(?string $dateFrom, ?string $dateTo, int $centerId, ?int $staffCenterId): array
    {
        $query = Transaction::query();

        $effectiveCenter = $staffCenterId !== null ? $staffCenterId : $centerId;
        if ($staffCenterId === 0) {
            $query->whereRaw('1 = 0');
        } elseif ($effectiveCenter > 0) {
            $query->where('logistics_center_id', $effectiveCenter);
        }

        if ($dateFrom !== '' && strtotime($dateFrom)) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo !== '' && strtotime($dateTo)) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $totals = $query->toBase()->selectRaw('
            COUNT(*) as total_transactions,
            COALESCE(SUM(amount), 0) as total_amount,
            COALESCE(SUM(rider_fee), 0) as total_rider_fees,
            COALESCE(SUM(admin_commission), 0) as total_commissions,
            COALESCE(SUM(CASE WHEN status = ? THEN amount ELSE 0 END), 0) as completed_amount,
            COALESCE(SUM(CASE WHEN status = ? THEN rider_fee ELSE 0 END), 0) as completed_rider_fees,
            COALESCE(SUM(CASE WHEN status = ? THEN admin_commission ELSE 0 END), 0) as completed_commissions
        ', ['completed', 'completed', 'completed'])->first();

        return (array) $totals;
    }
}




