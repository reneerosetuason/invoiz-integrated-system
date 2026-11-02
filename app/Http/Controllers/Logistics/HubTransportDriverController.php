<?php

namespace App\Http\Controllers\Logistics;

use App\Models\HubTransfer;
use App\Models\HubTransportDriver;
use App\Models\LogisticsCompany;
use App\Models\LogisticsTruck;
use App\Support\LogisticsAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HubTransportDriverController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeManager($request);

        $user = $request->user();
        $global = LogisticsAccess::isGlobal($user);
        $companies = $global
            ? LogisticsCompany::query()->where('status', 'active')->orderBy('name')->get(['id', 'name'])
            : collect();

        $companyId = $global
            ? $request->integer('logistics_company_id')
            : (int) LogisticsAccess::companyId($user);

        if (! $global) {
            abort_unless($companyId > 0, 403, 'Your account is not assigned to a logistics company.');
        }

        if ($global && $companyId > 0) {
            abort_unless($companies->contains('id', $companyId), 404);
        }

        $perPage = in_array((int) $request->query('per_page'), [10, 25, 50], true)
            ? (int) $request->query('per_page')
            : 10;
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $approval = trim((string) $request->query('approval', ''));
        $assignment = trim((string) $request->query('assignment', ''));

        $base = HubTransportDriver::query()
            ->when($companyId > 0, fn ($query) => $query->where('logistics_company_id', $companyId));

        $drivers = (clone $base)
            ->with([
                'company',
                'truck',
                'ownedTruck',
                'user',
                'application',
                'latestTransfer.origin',
                'latestTransfer.destination',
            ])
            ->withCount([
                'transfers',
                'transfers as active_transfers_count' => fn ($query) => $query->whereIn('status', HubTransfer::ACTIVE_STATUSES),
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';

                $query->where(function ($q) use ($like): void {
                    $q->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhere('license_number', 'like', $like)
                        ->orWhereHas('truck', fn ($truck) => $truck->where('plate_number', 'like', $like))
                        ->orWhereHas('ownedTruck', fn ($truck) => $truck->where('plate_number', 'like', $like));
                });
            })
            ->when($status !== '' && in_array($status, ['available', 'assigned', 'in_transit', 'inactive'], true), function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->when($approval !== '' && in_array($approval, ['approved', 'pending', 'rejected'], true), function ($query) use ($approval): void {
                $query->whereHas('application', fn ($application) => $application->where('status', $approval));
            })
            ->when($approval === 'unlinked', fn ($query) => $query->whereDoesntHave('application'))
            ->when($assignment === 'assigned', function ($query): void {
                $query->where(function ($q): void {
                    $q->whereNotNull('logistics_truck_id')->orWhereHas('ownedTruck');
                });
            })
            ->when($assignment === 'unassigned', function ($query): void {
                $query->whereNull('logistics_truck_id')->whereDoesntHave('ownedTruck');
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $summary = [
            'total' => (clone $base)->count(),
            'online' => (clone $base)->where('is_online', true)->count(),
            'unassigned' => (clone $base)
                ->whereNull('logistics_truck_id')
                ->whereDoesntHave('ownedTruck')
                ->count(),
            'active_transfers' => HubTransfer::query()
                ->whereIn('status', HubTransfer::ACTIVE_STATUSES)
                ->whereNotNull('driver_id')
                ->when($companyId > 0, fn ($query) => $query->where('logistics_company_id', $companyId))
                ->count(),
        ];

        return view('logistics.hub-transport-drivers.index', compact(
            'drivers',
            'companies',
            'companyId',
            'global',
            'summary',
        ));
    }

    public function show(Request $request, HubTransportDriver $driver): View
    {
        $this->authorizeManager($request);
        $this->authorizeDriverCompany($request, $driver);

        $driver->load([
            'company',
            'truck',
            'ownedTruck',
            'user',
            'latestTransfer.origin',
            'latestTransfer.destination',
        ]);

        $transfers = HubTransfer::query()
            ->where('driver_id', $driver->id)
            ->where('logistics_company_id', $driver->logistics_company_id)
            ->with(['origin', 'destination', 'truck'])
            ->latest()
            ->paginate(10, ['*'], 'transfers_page')
            ->withQueryString();

        $transferStats = [
            'total' => HubTransfer::query()
                ->where('driver_id', $driver->id)
                ->where('logistics_company_id', $driver->logistics_company_id)
                ->count(),
            'active' => HubTransfer::query()
                ->where('driver_id', $driver->id)
                ->where('logistics_company_id', $driver->logistics_company_id)
                ->whereIn('status', HubTransfer::ACTIVE_STATUSES)
                ->count(),
            'completed' => HubTransfer::query()
                ->where('driver_id', $driver->id)
                ->where('logistics_company_id', $driver->logistics_company_id)
                ->where('status', 'received')
                ->count(),
        ];

        $availableTrucks = LogisticsTruck::query()
            ->where('logistics_company_id', $driver->logistics_company_id)
            ->whereIn('status', ['available', 'assigned'])
            ->orderBy('plate_number')
            ->get()
            ->filter(fn (LogisticsTruck $truck) => $truck->isAssignable()
                || (int) $driver->logistics_truck_id === (int) $truck->id)
            ->values();

        $incidents = $driver->incidents()
            ->with('truck')
            ->latest('incident_date')
            ->take(10)
            ->get();

        $application = $driver->effectiveApplication();

        return view('logistics.hub-transport-drivers.show', compact(
            'driver',
            'application',
            'transfers',
            'transferStats',
            'availableTrucks',
            'incidents',
        ));
    }

    private function authorizeManager(Request $request): void
    {
        abort_unless(LogisticsAccess::isGlobal($request->user()), 403,
            'Hub Transport Driver records are managed by logistics administrators.');
    }

    private function authorizeDriverCompany(Request $request, HubTransportDriver $driver): void
    {
        abort_unless(
            LogisticsAccess::canAccessCompany($request->user(), $driver->logistics_company_id),
            403,
            'This driver belongs to a logistics company outside your access.'
        );
    }
}
