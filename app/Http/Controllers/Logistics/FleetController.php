<?php

namespace App\Http\Controllers\Logistics;

use App\Models\HubTransportDriver;
use App\Models\LogisticsCompany;
use App\Models\LogisticsTruck;
use App\Support\LogisticsVehicleCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FleetController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeManager($request);
        $companies = LogisticsCompany::where('status', 'active')->orderBy('name')->get();
        $companyId = $request->integer('logistics_company_id');
        if ($companyId) {
            abort_unless($companies->contains('id', $companyId), 404);
        }
        $trucks = LogisticsTruck::query()->with('company')->when($companyId, fn ($q) => $q->where('logistics_company_id', $companyId))->orderBy('plate_number')->paginate(10, ['*'], 'trucks_page');
        $allTrucks = LogisticsTruck::query()->with('company')->where('status', 'available')->orderBy('plate_number')->get();
        $drivers = HubTransportDriver::query()->with('company')->when($companyId, fn ($q) => $q->where('logistics_company_id', $companyId))->orderBy('name')->paginate(10, ['*'], 'drivers_page');

        return view('logistics.fleet.index', compact('companies', 'companyId', 'trucks', 'drivers', 'allTrucks'));
    }

    public function storeTruck(Request $request): RedirectResponse
    {
        $this->authorizeManager($request);
        $data = $request->validate([
            'logistics_company_id' => 'required|exists:logistics_companies,id',
            'plate_number' => ['required', 'string', 'max:40', Rule::unique('logistics_trucks')->where('logistics_company_id', $request->input('logistics_company_id'))],
            'name' => 'nullable|string|max:120', 'vehicle_type' => 'required|string|max:80', 'model' => 'nullable|string|max:120',
            'parcel_capacity' => 'required|integer|min:1', 'capacity_kg' => 'required|numeric|min:0.01', 'remarks' => 'nullable|string|max:2000',
        ]);
        abort_unless(LogisticsVehicleCategory::for($data['vehicle_type']) === 'four_wheel', 422, 'Fleet transfer vehicles must be approved four-wheel types.');
        $company = LogisticsCompany::whereKey($data['logistics_company_id'])->where('status', 'active')->firstOrFail();
        $company->trucks()->create($data);

        return back()->with('success', 'Truck registered.');
    }

    public function updateTruck(Request $request, LogisticsTruck $truck): RedirectResponse
    {
        $this->authorizeManager($request);
        $data = $request->validate([
            'plate_number' => ['required', 'string', 'max:40', Rule::unique('logistics_trucks')->where('logistics_company_id', $truck->logistics_company_id)->ignore($truck->id)],
            'name' => 'nullable|string|max:120', 'vehicle_type' => 'required|string|max:80', 'model' => 'nullable|string|max:120',
            'parcel_capacity' => 'required|integer|min:1', 'capacity_kg' => 'required|numeric|min:0.01', 'status' => 'required|in:available,maintenance,inactive', 'remarks' => 'nullable|string|max:2000',
        ]);
        abort_unless(LogisticsVehicleCategory::for($data['vehicle_type']) === 'four_wheel', 422, 'Fleet transfer vehicles must be approved four-wheel types.');
        abort_if(in_array($truck->status, ['assigned', 'in_transit'], true) && $data['status'] !== $truck->status, 409, 'A truck assigned to an active transfer cannot be made unavailable.');
        $truck->update($data);

        return back()->with('success', 'Truck updated.');
    }

    public function storeDriver(Request $request): RedirectResponse
    {
        $this->authorizeManager($request);
        $data = $request->validate([
            'logistics_company_id' => 'required|exists:logistics_companies,id',
            'name' => 'required|string|max:160', 'phone' => 'nullable|string|max:30',
            'license_number' => ['nullable', 'string', 'max:100', Rule::unique('hub_transport_drivers')->where('logistics_company_id', $request->input('logistics_company_id'))],
            'email' => ['required', 'email', 'max:255', 'unique:hub_transport_drivers,email'],
            'password' => 'required|string|min:8|confirmed',
            'logistics_truck_id' => 'required|integer|exists:logistics_trucks,id',
            'remarks' => 'nullable|string|max:2000',
        ]);
        $company = LogisticsCompany::whereKey($data['logistics_company_id'])->where('status', 'active')->firstOrFail();
        $truck = LogisticsTruck::whereKey($data['logistics_truck_id'])->where('logistics_company_id', $company->id)->where('status', 'available')->firstOrFail();
        abort_unless(LogisticsVehicleCategory::for($truck->vehicle_type) === 'four_wheel' && $truck->parcel_capacity > 0 && $truck->capacity_kg > 0, 422, 'Select an available four-wheel truck with registered capacity.');
        abort_if(HubTransportDriver::where('logistics_truck_id', $truck->id)->exists(), 422, 'This truck already has an assigned Hub Transport Driver.');
        $company->transportDrivers()->create($data);

        return back()->with('success', 'Hub Transport Driver registered.');
    }

    public function updateDriver(Request $request, HubTransportDriver $driver): RedirectResponse
    {
        $this->authorizeManager($request);
        $data = $request->validate([
            'name' => 'required|string|max:160', 'phone' => 'nullable|string|max:30',
            'license_number' => ['nullable', 'string', 'max:100', Rule::unique('hub_transport_drivers')->where('logistics_company_id', $driver->logistics_company_id)->ignore($driver->id)],
            'logistics_truck_id' => ['sometimes', 'required', 'integer', Rule::exists('logistics_trucks', 'id')->where('logistics_company_id', $driver->logistics_company_id)->where('status', 'available')],
            'status' => 'required|in:available,inactive', 'remarks' => 'nullable|string|max:2000',
        ]);
        abort_if(in_array($driver->status, ['assigned', 'in_transit'], true) && $data['status'] !== $driver->status, 409, 'A driver assigned to an active transfer cannot be made inactive.');
        abort_if(in_array($driver->status, ['assigned', 'in_transit'], true) && isset($data['logistics_truck_id']) && (int) $data['logistics_truck_id'] !== (int) $driver->logistics_truck_id, 409, 'A driver assigned to an active transfer cannot change vehicles.');
        if (isset($data['logistics_truck_id'])) {
            $truck = LogisticsTruck::findOrFail($data['logistics_truck_id']);
            abort_unless(LogisticsVehicleCategory::for($truck->vehicle_type) === 'four_wheel' && $truck->parcel_capacity > 0 && $truck->capacity_kg > 0, 422, 'Select an approved four-wheel truck with registered capacity.');
            abort_if(HubTransportDriver::where('logistics_truck_id', $truck->id)->where('id', '!=', $driver->id)->exists(), 422, 'This truck already has an assigned Hub Transport Driver.');
        }
        $driver->update($data);

        return back()->with('success', 'Hub Transport Driver updated.');
    }

    private function authorizeManager(Request $request): void
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isLogisticsAdmin(), 403,
            'Only the Platform Administrator or global Logistics Admin can manage logistics fleet records.');
    }
}
