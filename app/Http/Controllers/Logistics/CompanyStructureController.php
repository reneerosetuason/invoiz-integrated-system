<?php

namespace App\Http\Controllers\Logistics;

use App\Models\HubTransfer;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Models\PickupRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CompanyStructureController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeNetworkManagement($request);

        return view('logistics.companies.index', [
            'companies' => LogisticsCompany::with('centers')->withCount(['staff', 'trucks', 'transportDrivers'])->orderBy('name')->get(),
            'centers' => LogisticsCenter::with('company')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeNetworkManagement($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:logistics_companies,name'],
            'registration_number' => ['required', 'string', 'max:100', 'unique:logistics_companies,registration_number'],
        ]);

        LogisticsCompany::create([
            'name' => trim($data['name']),
            'registration_number' => trim($data['registration_number']),
            'status' => 'active',
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Logistics company registered. Assign verified hubs separately.');
    }

    public function assignCenter(Request $request, LogisticsCenter $center): RedirectResponse
    {
        $this->authorizeNetworkManagement($request);
        $data = $request->validate([
            'logistics_company_id' => 'required|exists:logistics_companies,id',
            'evidence' => 'required|string|max:2000', 'reason' => 'required|string|max:2000',
        ]);

        DB::transaction(function () use ($request, $center, $data): void {
            $locked = LogisticsCenter::whereKey($center->id)->lockForUpdate()->firstOrFail();
            $companyChanged = (int) $locked->logistics_company_id !== (int) $data['logistics_company_id'];

            if ($companyChanged) {
                // Ownership cannot move while records still depend on the
                // hub: moving it would silently re-home another company's
                // parcels, riders, cash, or coverage.
                $terminalStatuses = ['delivered', 'cancelled', 'returned_to_seller'];
                $hasActiveDeliveries = $locked->deliveries()
                        ->whereNull('archived_at')
                        ->whereNotIn('status', $terminalStatuses)
                        ->exists()
                    || $locked->destinationDeliveries()
                        ->whereNull('archived_at')
                        ->whereNotIn('status', $terminalStatuses)
                        ->exists();
                $hasRiders = $locked->riders()->where('status', '!=', 'inactive')->exists();
                $hasPickupRequests = PickupRequest::query()
                    ->where('center_id', $locked->id)
                    ->where('status', 'pending')
                    ->exists();
                $hasServiceAreas = $locked->serviceAreas()->exists();
                $hasActiveTransfers = $locked->originTransfers()->whereIn('status', HubTransfer::ACTIVE_STATUSES)->exists()
                    || $locked->destinationTransfers()->whereIn('status', HubTransfer::ACTIVE_STATUSES)->exists();

                abort_if(
                    $hasActiveDeliveries || $hasRiders || $hasPickupRequests || $hasServiceAreas || $hasActiveTransfers,
                    409,
                    'Resolve active deliveries, riders, pickup requests, service areas, and hub transfers before changing hub ownership.'
                );
            }
            $previous = $locked->logistics_company_id;
            $locked->update([
                'logistics_company_id' => $data['logistics_company_id'],
                'ownership_verified_at' => now(), 'ownership_verified_by' => $request->user()->id,
            ]);
            User::where('center_id', $locked->id)
                ->where('id', '!=', User::GLOBAL_LOGISTICS_ADMIN_ID)
                ->whereIn('role', ['staff', 'logistics_staff'])
                ->update(['logistics_company_id' => $data['logistics_company_id']]);
            DB::table('logistics_company_ownership_histories')->insert([
                'logistics_center_id' => $locked->id, 'from_company_id' => $previous, 'to_company_id' => $data['logistics_company_id'],
                'action' => $previous ? 'ownership_corrected' : 'ownership_assigned', 'evidence' => $data['evidence'],
                'reason' => $data['reason'], 'changed_by' => $request->user()->id, 'actor_name' => $request->user()->name,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        return back()->with('success', 'Hub company ownership updated and recorded.');
    }

    private function authorizeNetworkManagement(Request $request): void
    {
        abort_unless($request->user()?->isAdmin() || $request->user()?->isLogisticsAdmin(), 403,
            'Only the Platform Administrator or global Logistics Admin can manage the logistics network.');
    }
}
