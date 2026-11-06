<?php

namespace App\Http\Controllers\Logistics;

use App\Models\HubTransportDriver;
use App\Models\LogisticsCompany;
use App\Models\LogisticsTruck;
use App\Models\VehicleIncident;
use App\Models\VehicleMaintenanceRecord;
use App\Support\AccountEmailGuard;
use App\Support\LogisticsVehicleCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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

        $trucks = LogisticsTruck::query()
            ->with(['company', 'driver', 'ownerDriver'])
            ->when($companyId, fn ($q) => $q->where('logistics_company_id', $companyId))
            ->orderBy('plate_number')
            ->paginate(10, ['*'], 'trucks_page');

        $allTrucks = LogisticsTruck::query()
            ->with(['company', 'driver', 'ownerDriver'])
            ->whereIn('status', ['available', 'assigned'])
            ->orderBy('plate_number')
            ->get()
            ->filter(fn ($t) => $t->isAssignable())
            ->values();

        $drivers = HubTransportDriver::query()
            ->with(['company', 'truck', 'ownedTruck'])
            ->when($companyId, fn ($q) => $q->where('logistics_company_id', $companyId))
            ->orderBy('name')
            ->paginate(10, ['*'], 'drivers_page');

        $unassignedDrivers = HubTransportDriver::query()
            ->with('company')
            ->whereNull('logistics_truck_id')
            ->when($companyId, fn ($q) => $q->where('logistics_company_id', $companyId))
            ->orderBy('name')
            ->get();

        $pendingVehicles = LogisticsTruck::query()
            ->with(['company', 'ownerDriver'])
            ->where('ownership_type', 'driver_owned')
            ->where('verification_status', 'pending_review')
            ->when($companyId, fn ($q) => $q->where('logistics_company_id', $companyId))
            ->latest()
            ->get();

        $incidents = VehicleIncident::query()
            ->with(['truck', 'driver', 'company', 'reportedBy', 'investigatedBy'])
            ->when($companyId, fn ($q) => $q->where('logistics_company_id', $companyId))
            ->latest('incident_date')
            ->paginate(10, ['*'], 'incidents_page');

        $maintenanceRecords = VehicleMaintenanceRecord::query()
            ->with(['truck', 'incident', 'authorizedBy', 'company'])
            ->when($companyId, fn ($q) => $q->where('logistics_company_id', $companyId))
            ->latest()
            ->paginate(10, ['*'], 'maintenance_page');

        $overview = [
            'total_trucks' => LogisticsTruck::query()->when($companyId, fn ($q) => $q->where('logistics_company_id', $companyId))->count(),
            'available_trucks' => LogisticsTruck::query()->when($companyId, fn ($q) => $q->where('logistics_company_id', $companyId))->where('status', 'available')->where('verification_status', 'approved')->count(),
            'assigned_trucks' => LogisticsTruck::query()->when($companyId, fn ($q) => $q->where('logistics_company_id', $companyId))->where('status', 'assigned')->count(),
            'maintenance_trucks' => LogisticsTruck::query()->when($companyId, fn ($q) => $q->where('logistics_company_id', $companyId))->where('status', 'maintenance')->count(),
            'damaged_trucks' => LogisticsTruck::query()->when($companyId, fn ($q) => $q->where('logistics_company_id', $companyId))->where('status', 'damaged')->count(),
            'retired_trucks' => LogisticsTruck::query()->when($companyId, fn ($q) => $q->where('logistics_company_id', $companyId))->where('status', 'retired')->count(),
            'company_trucks' => LogisticsTruck::query()->when($companyId, fn ($q) => $q->where('logistics_company_id', $companyId))->where('ownership_type', 'company')->count(),
            'driver_owned_trucks' => LogisticsTruck::query()->when($companyId, fn ($q) => $q->where('logistics_company_id', $companyId))->where('ownership_type', 'driver_owned')->count(),
            'pending_vehicle_reviews' => $pendingVehicles->count(),
            'total_incidents' => VehicleIncident::query()->when($companyId, fn ($q) => $q->where('logistics_company_id', $companyId))->count(),
            'maintenance_records_count' => VehicleMaintenanceRecord::query()->when($companyId, fn ($q) => $q->where('logistics_company_id', $companyId))->count(),
            'active_repairs' => VehicleMaintenanceRecord::query()->when($companyId, fn ($q) => $q->where('logistics_company_id', $companyId))->whereIn('status', ['pending_assessment', 'approved', 'in_repair'])->count(),
            'approved_drivers' => HubTransportDriver::query()->when($companyId, fn ($q) => $q->where('logistics_company_id', $companyId))->count(),
            'unassigned_drivers' => $unassignedDrivers->count(),
        ];

        return view('logistics.fleet.index', compact(
            'companies',
            'companyId',
            'trucks',
            'drivers',
            'allTrucks',
            'unassignedDrivers',
            'pendingVehicles',
            'incidents',
            'maintenanceRecords',
            'overview'
        ));
    }

    public function storeTruck(Request $request): RedirectResponse
    {
        $this->authorizeManager($request);
        $company = $this->resolveCentralCompany($request);

        $data = $request->validate([
            'plate_number' => ['required', 'string', 'max:40', Rule::unique('logistics_trucks')->where('logistics_company_id', $company->id)],
            'name' => 'nullable|string|max:120',
            'vehicle_type' => 'required|string|max:80',
            'model' => 'nullable|string|max:120',
            'parcel_capacity' => 'required|integer|min:1',
            'capacity_kg' => 'required|numeric|min:0.01',
            'ownership_type' => 'nullable|in:company,driver_owned',
            'hub_transport_driver_id' => 'nullable|integer|exists:hub_transport_drivers,id',
            'vehicle_registration' => 'nullable|string|max:100',
            'remarks' => 'nullable|string|max:2000',
            'status' => 'nullable|in:available,maintenance,inactive',
        ]);

        abort_unless(LogisticsVehicleCategory::for($data['vehicle_type']) === 'four_wheel', 422, 'Fleet transfer vehicles must be approved four-wheel types.');

        $data['logistics_company_id'] = $company->id;
        $data['ownership_type'] = $data['ownership_type'] ?? 'company';
        $data['status'] = $data['status'] ?? 'available';
        $data['verification_status'] = ($data['ownership_type'] === 'company') ? 'approved' : 'pending_review';

        $company->trucks()->create($data);

        return back()->with('success', 'Truck registered.');
    }

    public function updateTruck(Request $request, LogisticsTruck $truck): RedirectResponse
    {
        $this->authorizeManager($request);
        $data = $request->validate([
            'plate_number' => ['sometimes', 'required', 'string', 'max:40', Rule::unique('logistics_trucks')->where('logistics_company_id', $truck->logistics_company_id)->ignore($truck->id)],
            'name' => 'nullable|string|max:120',
            'vehicle_type' => 'sometimes|required|string|max:80',
            'model' => 'nullable|string|max:120',
            'parcel_capacity' => 'sometimes|required|integer|min:1',
            'capacity_kg' => 'sometimes|required|numeric|min:0.01',
            'status' => 'required|in:available,assigned,in_transit,maintenance,damaged,retired,inactive',
            'ownership_type' => 'nullable|in:company,driver_owned',
            'verification_status' => 'nullable|in:pending_review,approved,rejected',
            'verification_notes' => 'nullable|string|max:2000',
            'vehicle_registration' => 'nullable|string|max:100',
            'remarks' => 'nullable|string|max:2000',
        ]);

        if (isset($data['vehicle_type'])) {
            abort_unless(LogisticsVehicleCategory::for($data['vehicle_type']) === 'four_wheel', 422, 'Fleet transfer vehicles must be approved four-wheel types.');
        }

        abort_if(in_array($truck->status, ['in_transit'], true) && $data['status'] !== $truck->status, 409, 'A truck assigned to an active transfer in transit cannot be modified.');

        // If marked maintenance, damaged, retired, or inactive, unassign assigned driver
        if (in_array($data['status'], ['maintenance', 'damaged', 'retired', 'inactive'], true)) {
            HubTransportDriver::where('logistics_truck_id', $truck->id)->update(['logistics_truck_id' => null]);
        }

        $truck->update($data);

        return back()->with('success', 'Truck updated.');
    }

    public function approveVehicle(Request $request, LogisticsTruck $truck): RedirectResponse
    {
        $this->authorizeManager($request);

        $truck->update([
            'verification_status' => 'approved',
            'status' => $truck->status === 'inactive' ? 'available' : $truck->status,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'verification_notes' => $request->input('notes') ?? $request->input('verification_notes'),
        ]);

        if ($truck->hub_transport_driver_id) {
            $driver = HubTransportDriver::find($truck->hub_transport_driver_id);
            if ($driver && ! $driver->logistics_truck_id) {
                $driver->update(['logistics_truck_id' => $truck->id]);
            }
        }

        return back()->with('success', 'Driver-owned vehicle approved.');
    }

    public function rejectVehicle(Request $request, LogisticsTruck $truck): RedirectResponse
    {
        $this->authorizeManager($request);

        $reason = $request->input('reason') ?? $request->input('verification_notes') ?? 'Vehicle rejected by logistics administrator.';

        $truck->update([
            'verification_status' => 'rejected',
            'status' => 'inactive',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'verification_notes' => $reason,
        ]);

        HubTransportDriver::where('logistics_truck_id', $truck->id)->update(['logistics_truck_id' => null]);

        return back()->with('success', 'Driver-owned vehicle rejected.');
    }

    public function storeDriver(Request $request): RedirectResponse
    {
        $this->authorizeManager($request);
        $company = $this->resolveCentralCompany($request);

        if (is_string($request->input('email'))) {
            $request->merge(['email' => AccountEmailGuard::normalize($request->input('email'))]);
        }

        $data = $request->validate([
            'name' => 'required|string|max:160',
            'phone' => 'nullable|string|max:30',
            'license_number' => ['nullable', 'string', 'max:100', Rule::unique('hub_transport_drivers')->where('logistics_company_id', $company->id)],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:hub_transport_drivers,email',
                function ($attribute, $value, $fail) {
                    if ($message = AccountEmailGuard::conflict((string) $value)) {
                        $fail($message);
                        return;
                    }
                    if (AccountEmailGuard::hasPendingApplication((string) $value)) {
                        $fail('This email already has a pending rider or driver application on file.');
                    }
                },
            ],
            'password' => 'required|string|min:8|confirmed',
            'logistics_truck_id' => 'required|integer|exists:logistics_trucks,id',
            'remarks' => 'nullable|string|max:2000',
        ]);

        $data['logistics_company_id'] = $company->id;

        $truck = LogisticsTruck::whereKey($data['logistics_truck_id'])->where('logistics_company_id', $company->id)->where('status', 'available')->firstOrFail();
        abort_unless(LogisticsVehicleCategory::for($truck->vehicle_type) === 'four_wheel' && $truck->parcel_capacity > 0 && $truck->capacity_kg > 0, 422, 'Select an available four-wheel truck with registered capacity.');
        abort_unless($truck->isAssignable(), 422, 'Truck is unapproved, under maintenance, or retired.');
        abort_if(HubTransportDriver::where('logistics_truck_id', $truck->id)->exists(), 422, 'This truck already has an assigned Hub Transport Driver.');
        abort_if($truck->hasActiveTransfer(), 409, 'This truck is already assigned to an active hub transfer.');

        $company->transportDrivers()->create($data);

        return back()->with('success', 'Hub Transport Driver registered.');
    }

    public function updateDriver(Request $request, HubTransportDriver $driver): RedirectResponse
    {
        $this->authorizeManager($request);
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:160',
            'phone' => 'nullable|string|max:30',
            'license_number' => ['nullable', 'string', 'max:100', Rule::unique('hub_transport_drivers')->where('logistics_company_id', $driver->logistics_company_id)->ignore($driver->id)],
            'logistics_truck_id' => ['nullable', 'integer', Rule::exists('logistics_trucks', 'id')->where('logistics_company_id', $driver->logistics_company_id)],
            'status' => 'sometimes|required|in:available,inactive',
            'remarks' => 'nullable|string|max:2000',
        ]);

        $targetStatus = $data['status'] ?? $driver->status;
        $explicitTruckId = array_key_exists('logistics_truck_id', $data) ? $data['logistics_truck_id'] : null;

        // The driver record is only created after application approval. When an
        // application exists, it must still be approved; legacy/manual driver
        // records without an application remain assignable by an administrator.
        $application = $driver->effectiveApplication();
        abort_if($application && $application->status !== 'approved', 422, 'Only approved Hub Transport Drivers can be assigned to a truck.');
        abort_if($targetStatus === 'inactive' && ! empty($explicitTruckId), 422, 'Inactive Hub Transport Drivers cannot be assigned to a truck.');

        if (isset($data['status'])) {
            abort_if(in_array($driver->status, ['assigned', 'in_transit'], true) && $data['status'] !== $driver->status, 409, 'A driver assigned to an active transfer cannot be made inactive.');
        }
        abort_if(in_array($driver->status, ['assigned', 'in_transit'], true) && array_key_exists('logistics_truck_id', $data) && (int) $data['logistics_truck_id'] !== (int) $driver->logistics_truck_id, 409, 'A driver assigned to an active transfer cannot change vehicles.');

        if (! empty($data['logistics_truck_id'])) {
            $truck = LogisticsTruck::findOrFail($data['logistics_truck_id']);
            abort_unless((int) $truck->logistics_company_id === (int) $driver->logistics_company_id, 422, 'The truck must belong to the same logistics company as the driver.');
            abort_unless(LogisticsVehicleCategory::for($truck->vehicle_type) === 'four_wheel', 422, 'Cannot assign a motorcycle to a Hub Transport Driver. Only four-wheel trucks are permitted.');
            abort_unless($truck->isAssignable() || (int) $driver->logistics_truck_id === (int) $truck->id, 422, 'A driver cannot be assigned an unapproved, unavailable, damaged, retired truck or a truck under maintenance.');
            abort_unless($truck->parcel_capacity > 0 && $truck->capacity_kg > 0, 422, 'Select an approved four-wheel truck with registered capacity.');
            abort_if(HubTransportDriver::where('logistics_truck_id', $truck->id)->where('id', '!=', $driver->id)->exists(), 422, 'This truck already has an assigned Hub Transport Driver.');
            abort_if($truck->hasActiveTransfer() && (int) $driver->logistics_truck_id !== (int) $truck->id, 409, 'This truck is already assigned to an active hub transfer.');
        }

        // Keep the fleet consistent: an inactive driver must not reserve a truck.
        if ($targetStatus === 'inactive') {
            $data['logistics_truck_id'] = null;
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($driver, $data) {
            $oldTruckId = $driver->logistics_truck_id;
            $newTruckId = array_key_exists('logistics_truck_id', $data) ? $data['logistics_truck_id'] : $oldTruckId;

            if (array_key_exists('logistics_truck_id', $data) && (int) $oldTruckId !== (int) $newTruckId) {
                // Free previous truck if unassigned or changed, unless an
                // active transfer still owns it.
                if ($oldTruckId) {
                    $oldTruck = LogisticsTruck::find($oldTruckId);
                    if ($oldTruck && $oldTruck->status === 'assigned' && ! $oldTruck->hasActiveTransfer()) {
                        $oldTruck->update(['status' => 'available']);
                    }
                }
                // Mark new truck assigned
                if ($newTruckId) {
                    $newTruck = LogisticsTruck::find($newTruckId);
                    if ($newTruck && $newTruck->status === 'available') {
                        $newTruck->update(['status' => 'assigned']);
                    }
                }
            }

            $driver->update($data);
        });

        return back(fallback: route('fleet.index'))->with('success', 'Hub Transport Driver updated.');
    }

    public function storeIncident(Request $request): RedirectResponse
    {
        $this->authorizeManager($request);

        $data = $request->validate([
            'logistics_truck_id' => 'required|integer|exists:logistics_trucks,id',
            'hub_transport_driver_id' => 'nullable|integer|exists:hub_transport_drivers,id',
            'hub_transfer_id' => 'nullable|integer|exists:hub_transfers,id',
            'incident_type' => 'required|string|max:50',
            'severity' => 'required|in:minor,moderate,severe,total_loss',
            'status' => 'nullable|in:reported,under_investigation,under_repair,resolved,closed',
            'incident_date' => 'required|date',
            'location' => 'nullable|string|max:255',
            'description' => 'required|string|max:5000',
            'damage_details' => 'nullable|string|max:5000',
            'vehicle_action_taken' => 'nullable|in:none,marked_damaged,under_repair,retired,returned_to_service',
            'photos' => 'nullable|array',
            'photos.*' => 'file|image|max:10240',
        ]);

        $truck = LogisticsTruck::findOrFail($data['logistics_truck_id']);
        $reference = 'INC-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));

        $storedPhotos = [];
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                if ($photo->isValid()) {
                    $storedPhotos[] = $photo->store("incident-evidence/{$reference}");
                }
            }
        }

        $actionTaken = $data['vehicle_action_taken'] ?? 'none';
        if ($actionTaken === 'marked_damaged' || (in_array($data['severity'], ['moderate', 'severe', 'total_loss'], true) && !in_array($actionTaken, ['retired', 'under_repair', 'returned_to_service'], true))) {
            // Vehicle becomes unavailable pending assessment. Never automatically retire without explicit admin instruction.
            $truck->update(['status' => 'damaged']);
            HubTransportDriver::where('logistics_truck_id', $truck->id)->update(['logistics_truck_id' => null]);
            $actionTaken = 'marked_damaged';
        } elseif ($actionTaken === 'under_repair') {
            $truck->update(['status' => 'maintenance']);
            HubTransportDriver::where('logistics_truck_id', $truck->id)->update(['logistics_truck_id' => null]);
        } elseif ($actionTaken === 'retired') {
            // Explicitly retired by authorized admin review
            $truck->update(['status' => 'retired']);
            HubTransportDriver::where('logistics_truck_id', $truck->id)->update(['logistics_truck_id' => null]);
            $actionTaken = 'retired';
        } elseif ($actionTaken === 'returned_to_service') {
            $truck->update(['status' => 'available']);
        }

        VehicleIncident::create([
            'reference' => $reference,
            'logistics_company_id' => $truck->logistics_company_id,
            'logistics_truck_id' => $truck->id,
            'hub_transport_driver_id' => $data['hub_transport_driver_id'] ?? null,
            'hub_transfer_id' => $data['hub_transfer_id'] ?? null,
            'incident_type' => $data['incident_type'],
            'severity' => $data['severity'],
            'status' => $data['status'] ?? 'reported',
            'incident_date' => $data['incident_date'],
            'location' => $data['location'] ?? null,
            'description' => $data['description'],
            'damage_details' => $data['damage_details'] ?? null,
            'vehicle_action_taken' => $actionTaken,
            'evidence_photos' => $storedPhotos,
            'reported_by_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Incident record created. Driver earnings are preserved without deduction.');
    }

    public function updateIncident(Request $request, VehicleIncident $incident): RedirectResponse
    {
        $this->authorizeManager($request);

        $data = $request->validate([
            'status' => 'sometimes|required|in:reported,under_investigation,under_repair,resolved,closed',
            'investigation_notes' => 'nullable|string|max:5000',
            'resolution_notes' => 'nullable|string|max:5000',
            'vehicle_action_taken' => 'nullable|in:none,marked_damaged,under_repair,retired,returned_to_service',
        ]);

        $truck = $incident->truck;

        if (!empty($data['vehicle_action_taken'])) {
            if ($data['vehicle_action_taken'] === 'returned_to_service') {
                $truck->update(['status' => 'available']);
            } elseif ($data['vehicle_action_taken'] === 'marked_damaged') {
                $truck->update(['status' => 'damaged']);
                HubTransportDriver::where('logistics_truck_id', $truck->id)->update(['logistics_truck_id' => null]);
            } elseif ($data['vehicle_action_taken'] === 'under_repair') {
                $truck->update(['status' => 'maintenance']);
                HubTransportDriver::where('logistics_truck_id', $truck->id)->update(['logistics_truck_id' => null]);
            } elseif ($data['vehicle_action_taken'] === 'retired') {
                $truck->update(['status' => 'retired']);
                HubTransportDriver::where('logistics_truck_id', $truck->id)->update(['logistics_truck_id' => null]);
            }
        }

        if (isset($data['status']) && in_array($data['status'], ['resolved', 'closed'], true)) {
            $data['resolved_at'] = now();
            $data['investigated_by'] = $request->user()->id;
        }

        $incident->update($data);

        return back()->with('success', 'Incident updated. Driver compensation remains untouched.');
    }

    public function storeMaintenanceRecord(Request $request): RedirectResponse
    {
        $this->authorizeManager($request);

        $data = $request->validate([
            'logistics_truck_id' => 'required|integer|exists:logistics_trucks,id',
            'vehicle_incident_id' => 'nullable|integer|exists:vehicle_incidents,id',
            'maintenance_type' => 'required|string|in:routine_maintenance,accident_repair,mechanical_repair,inspection,body_paint,other',
            'description' => 'required|string|max:5000',
            'service_provider' => 'nullable|string|max:255',
            'estimated_cost' => 'nullable|numeric|min:0',
            'actual_cost' => 'nullable|numeric|min:0',
            'payment_responsibility' => 'required|string|in:company,driver_owner,insurance,third_party,shared',
            'responsibility_notes' => 'nullable|string|max:2000',
            'invoice' => 'nullable|file|max:10240',
            'status' => 'required|string|in:pending_assessment,approved,in_repair,completed',
            'admin_remarks' => 'nullable|string|max:2000',
        ]);

        $truck = LogisticsTruck::findOrFail($data['logistics_truck_id']);
        $reference = 'MNT-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));

        $invoicePath = null;
        if ($request->hasFile('invoice') && $request->file('invoice')->isValid()) {
            $invoicePath = $request->file('invoice')->store("maintenance-invoices/{$reference}");
        }

        // Put truck under maintenance if in repair or assessment, and unassign active driver
        if (in_array($data['status'], ['pending_assessment', 'approved', 'in_repair'], true)) {
            $truck->update(['status' => 'maintenance']);
            HubTransportDriver::where('logistics_truck_id', $truck->id)->update(['logistics_truck_id' => null]);
        } elseif ($data['status'] === 'completed') {
            $truck->update(['status' => 'available']);
        }

        VehicleMaintenanceRecord::create([
            'reference' => $reference,
            'logistics_company_id' => $truck->logistics_company_id,
            'logistics_truck_id' => $truck->id,
            'vehicle_incident_id' => $data['vehicle_incident_id'] ?? null,
            'maintenance_type' => $data['maintenance_type'],
            'description' => $data['description'],
            'service_provider' => $data['service_provider'] ?? null,
            'estimated_cost' => $data['estimated_cost'] ?? null,
            'actual_cost' => $data['actual_cost'] ?? null,
            'payment_responsibility' => $data['payment_responsibility'],
            'responsibility_notes' => $data['responsibility_notes'] ?? null,
            'invoice_attachment_path' => $invoicePath,
            'status' => $data['status'],
            'authorized_by_id' => $request->user()->id,
            'started_at' => in_array($data['status'], ['in_repair', 'approved', 'completed'], true) ? now() : null,
            'completed_at' => $data['status'] === 'completed' ? now() : null,
            'returned_to_service_at' => $data['status'] === 'completed' ? now() : null,
            'admin_remarks' => $data['admin_remarks'] ?? null,
        ]);

        return back()->with('success', 'Maintenance record created. Vehicle marked under maintenance and driver earnings remain protected.');
    }

    public function updateMaintenanceRecord(Request $request, VehicleMaintenanceRecord $maintenanceRecord): RedirectResponse
    {
        $this->authorizeManager($request);

        $data = $request->validate([
            'status' => 'required|string|in:pending_assessment,approved,in_repair,completed,cancelled',
            'actual_cost' => 'nullable|numeric|min:0',
            'service_provider' => 'nullable|string|max:255',
            'payment_responsibility' => 'sometimes|required|string|in:company,driver_owner,insurance,third_party,shared',
            'responsibility_notes' => 'nullable|string|max:2000',
            'admin_remarks' => 'nullable|string|max:2000',
            'return_to_service' => 'nullable|boolean',
            'retire_vehicle' => 'nullable|boolean',
        ]);

        $truck = $maintenanceRecord->truck;

        if ($request->boolean('retire_vehicle')) {
            // Vehicle marked beyond repair is permanently retired by authorized admin action
            $truck->update(['status' => 'retired']);
            HubTransportDriver::where('logistics_truck_id', $truck->id)->update(['logistics_truck_id' => null]);
            $data['status'] = 'completed';
            $data['completed_at'] = now();
            $data['admin_remarks'] = ($data['admin_remarks'] ? $data['admin_remarks'] . "\n" : '') . 'Vehicle declared beyond repair and decommissioned/retired.';
        } elseif ($data['status'] === 'completed' && $request->boolean('return_to_service')) {
            // Repaired vehicle returns to Available only after authorized admin assessment
            $truck->update(['status' => 'available']);
            $data['returned_to_service_at'] = now();
            $data['completed_at'] = now();
            $data['authorized_by_id'] = $request->user()->id;
        } elseif (in_array($data['status'], ['pending_assessment', 'approved', 'in_repair'], true)) {
            $truck->update(['status' => 'maintenance']);
            HubTransportDriver::where('logistics_truck_id', $truck->id)->update(['logistics_truck_id' => null]);
        }

        $maintenanceRecord->update($data);

        return back()->with('success', 'Maintenance record updated. Driver earnings remain protected.');
    }

    private function authorizeManager(Request $request): void
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isLogisticsAdmin(), 403,
            'Only the Platform Administrator or global Logistics Admin can manage logistics fleet records.');
    }

    private function resolveCentralCompany(?Request $request = null): LogisticsCompany
    {
        $requestedCompanyId = $request?->input('logistics_company_id');
        if ($requestedCompanyId) {
            $company = LogisticsCompany::whereKey($requestedCompanyId)->where('status', 'active')->first();
            if ($company) {
                return $company;
            }
        }

        $user = $request?->user() ?? request()->user();

        if ($user?->logistics_company_id) {
            $company = LogisticsCompany::whereKey($user->logistics_company_id)->where('status', 'active')->first();
            if ($company) {
                return $company;
            }
        }

        $company = LogisticsCompany::where('status', 'active')->orderBy('id')->first();
        if ($company) {
            return $company;
        }

        $company = LogisticsCompany::find(1);
        if ($company && $company->status === 'active') {
            return $company;
        }

        abort(422, 'Unable to resolve the active central INVOIZ Logistics organization.');
    }
}
