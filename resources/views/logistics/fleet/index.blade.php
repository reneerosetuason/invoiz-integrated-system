<x-app-layout>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-[#16697A]">Fleet &amp; Transport Linehaul</p>
                <h1 class="text-2xl font-bold text-[#1B1B1E] mt-0.5">Fleet Management</h1>
                <p class="text-xs text-[#6E6E73] mt-0.5">Manage four-wheel trucks, driver-owned vehicle approvals, Hub Transport Drivers, vehicle lifecycles, and incident reports across INVOIZ facilities.</p>
            </div>
            <div>
                <button type="button" onclick="document.getElementById('register-truck-modal').showModal()" class="btn btn-primary text-xs">
                    + Register Company Truck
                </button>
            </div>
        </div>

        @if(session('success'))
            <div class="card p-4 bg-green-50 border-green-200 text-green-800 text-xs flex items-center justify-between">
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if($errors->any())
            <div class="card p-4 bg-red-50 border-red-200 text-red-800 text-xs">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Fleet Overview Cards --}}
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-9">
            <div class="card p-3.5 border border-[#E8E6E0] bg-white">
                <div class="text-[10px] font-bold text-[#6E6E73] uppercase tracking-wide">Total Trucks</div>
                <div class="mt-1 text-2xl font-extrabold text-[#1B1B1E]">{{ $overview['total_trucks'] ?? $trucks->total() }}</div>
                <div class="text-[10px] text-[#6E6E73] mt-0.5">{{ $overview['company_trucks'] ?? 0 }} Co. / {{ $overview['driver_owned_trucks'] ?? 0 }} Driver</div>
            </div>
            <div class="card p-3.5 border border-emerald-200 bg-emerald-50/40">
                <div class="text-[10px] font-bold text-emerald-800 uppercase tracking-wide">Available</div>
                <div class="mt-1 text-2xl font-extrabold text-emerald-900">{{ $overview['available_trucks'] ?? 0 }}</div>
                <div class="text-[10px] text-emerald-700 mt-0.5">Assignable</div>
            </div>
            <div class="card p-3.5 border border-blue-200 bg-blue-50/40">
                <div class="text-[10px] font-bold text-blue-800 uppercase tracking-wide">Assigned</div>
                <div class="mt-1 text-2xl font-extrabold text-blue-900">{{ $overview['assigned_trucks'] ?? 0 }}</div>
                <div class="text-[10px] text-blue-700 mt-0.5">Active custody</div>
            </div>
            <div class="card p-3.5 border border-amber-200 bg-amber-50/40">
                <div class="text-[10px] font-bold text-amber-800 uppercase tracking-wide">Maintenance</div>
                <div class="mt-1 text-2xl font-extrabold text-amber-900">{{ $overview['maintenance_trucks'] ?? 0 }}</div>
                <div class="text-[10px] text-amber-700 mt-0.5">Under repair</div>
            </div>
            <div class="card p-3.5 border border-orange-200 bg-orange-50/40">
                <div class="text-[10px] font-bold text-orange-800 uppercase tracking-wide">Damaged</div>
                <div class="mt-1 text-2xl font-extrabold text-orange-900">{{ $overview['damaged_trucks'] ?? 0 }}</div>
                <div class="text-[10px] text-orange-700 mt-0.5">Unusable</div>
            </div>
            <div class="card p-3.5 border border-slate-300 bg-slate-100">
                <div class="text-[10px] font-bold text-slate-700 uppercase tracking-wide">Retired</div>
                <div class="mt-1 text-2xl font-extrabold text-slate-800">{{ $overview['retired_trucks'] ?? 0 }}</div>
                <div class="text-[10px] text-slate-600 mt-0.5">Decommissioned</div>
            </div>
            <div class="card p-3.5 border border-purple-200 bg-purple-50/40">
                <div class="text-[10px] font-bold text-purple-800 uppercase tracking-wide">Pending Reviews</div>
                <div class="mt-1 text-2xl font-extrabold text-purple-900">{{ $overview['pending_vehicle_reviews'] ?? 0 }}</div>
                <div class="text-[10px] text-purple-700 mt-0.5">Driver vehicles</div>
            </div>
            <div class="card p-3.5 border border-rose-200 bg-rose-50/40">
                <div class="text-[10px] font-bold text-rose-800 uppercase tracking-wide">Incidents</div>
                <div class="mt-1 text-2xl font-extrabold text-rose-900">{{ $overview['total_incidents'] ?? 0 }}</div>
                <div class="text-[10px] text-rose-700 mt-0.5">Reported logs</div>
            </div>
            <div class="card p-3.5 border border-indigo-200 bg-indigo-50/40">
                <div class="text-[10px] font-bold text-indigo-800 uppercase tracking-wide">Maintenance Logs</div>
                <div class="mt-1 text-2xl font-extrabold text-indigo-900">{{ $overview['maintenance_records_count'] ?? 0 }}</div>
                <div class="text-[10px] text-indigo-700 mt-0.5">{{ $overview['active_repairs'] ?? 0 }} in progress</div>
            </div>
        </div>

        {{-- Informational Notice --}}
        <div class="rounded-xl border border-[#16697A]/20 bg-[#E6F4F1]/60 px-4 py-3 flex items-start gap-3">
            <svg class="w-5 h-5 text-[#16697A] shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div class="text-xs text-[#0E4A57] leading-relaxed">
                <span class="font-bold">Fleet Operations Policy:</span>
                Company trucks receive standard transfer compensation. Driver-owned vehicles require administrative document review and receive trip compensation plus configured vehicle-use allowance.
                Vehicles marked damaged, under maintenance, or retired are strictly blocked from new transfers. <strong>Driver earnings are never deducted for damage or incident reports.</strong>
            </div>
        </div>

        {{-- Pending Driver-Owned Vehicles Section --}}
        @if($pendingVehicles->isNotEmpty())
            <section class="card p-5 space-y-4 border-2 border-purple-300 bg-purple-50/20">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-purple-600 animate-pulse"></span>
                            <h2 class="text-sm font-bold text-purple-950 uppercase tracking-wide">Driver-Owned Vehicles Awaiting Verification ({{ $pendingVehicles->count() }})</h2>
                        </div>
                        <p class="text-xs text-purple-800 mt-0.5">Review submitted vehicle registration documents before approving trucks for inter-hub transfer operations.</p>
                    </div>
                </div>

                <div class="card overflow-hidden bg-white">
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Driver / Applicant</th>
                                    <th>Plate Number</th>
                                    <th>Vehicle Details</th>
                                    <th>Capacities</th>
                                    <th>OR / CR Documents</th>
                                    <th class="text-right">Verification Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($pendingVehicles as $pendingTruck)
                                    <tr class="align-top">
                                        <td>
                                            <div class="font-bold text-[#1B1B1E]">{{ $pendingTruck->ownerDriver?->name ?? 'Applicant' }}</div>
                                            <div class="text-[10px] text-[#6E6E73]">{{ $pendingTruck->ownerDriver?->email }}</div>
                                            <div class="text-[10px] text-[#6E6E73]">{{ $pendingTruck->ownerDriver?->phone }}</div>
                                        </td>
                                        <td class="font-mono font-bold text-[#1B1B1E]">
                                            {{ $pendingTruck->plate_number }}
                                        </td>
                                        <td class="text-xs">
                                            <div class="font-semibold text-[#1B1B1E]">{{ $pendingTruck->model ?: $pendingTruck->name }}</div>
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-gray-100 text-gray-800">
                                                {{ ucfirst(str_replace('_', ' ', $pendingTruck->vehicle_type)) }}
                                            </span>
                                        </td>
                                        <td class="text-xs font-semibold text-[#1B1B1E]">
                                            <div>{{ number_format($pendingTruck->parcel_capacity) }} parcels</div>
                                            <div>{{ number_format($pendingTruck->capacity_kg, 1) }} kg</div>
                                        </td>
                                        <td class="text-xs">
                                            @if($pendingTruck->vehicle_registration)
                                                <div class="font-mono text-[11px] text-[#1B1B1E]">Reg: {{ $pendingTruck->vehicle_registration }}</div>
                                            @endif
                                            @if($pendingTruck->document_path)
                                                <a href="{{ asset('storage/' . $pendingTruck->document_path) }}" target="_blank" class="inline-flex items-center gap-1 text-[11px] font-semibold text-[#16697A] hover:underline mt-0.5">
                                                    <span>View OR/CR Doc</span>
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                                                </a>
                                            @else
                                                <span class="text-[10px] text-[#6E6E73] italic">No file attached</span>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <form method="POST" action="{{ route('fleet.trucks.approve', $pendingTruck) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-xs bg-emerald-600 hover:bg-emerald-700 text-white font-semibold">
                                                        Approve Vehicle
                                                    </button>
                                                </form>
                                                <button type="button" onclick="document.getElementById('reject-truck-modal-{{ $pendingTruck->id }}').showModal()" class="btn btn-xs bg-rose-600 hover:bg-rose-700 text-white font-semibold">
                                                    Reject
                                                </button>
                                            </div>

                                            <dialog id="reject-truck-modal-{{ $pendingTruck->id }}" class="modal p-6 rounded-xl shadow-xl max-w-md w-full bg-white text-left">
                                                <h3 class="text-sm font-bold text-[#1B1B1E]">Reject Vehicle {{ $pendingTruck->plate_number }}</h3>
                                                <p class="text-xs text-[#6E6E73] mt-1">Please provide the reason for rejection (e.g. Expired OR/CR, unreadable document, capacity mismatch):</p>
                                                <form method="POST" action="{{ route('fleet.trucks.reject', $pendingTruck) }}" class="mt-4 space-y-3">
                                                    @csrf
                                                    <textarea name="reason" rows="3" required placeholder="Reason for rejection..." class="textarea w-full text-xs"></textarea>
                                                    <div class="flex items-center justify-end gap-2">
                                                        <button type="button" onclick="document.getElementById('reject-truck-modal-{{ $pendingTruck->id }}').close()" class="btn btn-ghost btn-xs">Cancel</button>
                                                        <button type="submit" class="btn btn-xs bg-rose-600 hover:bg-rose-700 text-white">Confirm Rejection</button>
                                                    </div>
                                                </form>
                                            </dialog>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        @endif

        {{-- Four-Wheel Trucks Section --}}
        <section class="card p-5 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div>
                    <h2 class="text-sm font-bold text-[#1B1B1E] uppercase tracking-wide">Four-Wheel Fleet Vehicles</h2>
                    <p class="text-xs text-[#6E6E73] mt-0.5">Inter-hub fleet vehicles, ownership arrangements, driver assignments, and lifecycle operational status.</p>
                </div>
            </div>

            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Plate Number</th>
                                <th>Ownership</th>
                                <th>Vehicle Type / Model</th>
                                <th>Parcel / Weight</th>
                                <th>Assigned Driver</th>
                                <th>Verification</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($trucks as $truck)
                                <tr class="align-top">
                                    <td class="font-mono font-bold text-[#1B1B1E]">
                                        {{ $truck->plate_number }}
                                        @if($truck->company)
                                            <div class="text-[10px] text-[#6E6E73] font-normal">{{ $truck->company->name }}</div>
                                        @endif
                                        @if($truck->vehicle_registration)
                                            <div class="text-[9px] text-[#6E6E73]">OR/CR: {{ $truck->vehicle_registration }}</div>
                                        @endif
                                    </td>
                                    <td class="text-xs">
                                        @if($truck->isDriverOwned())
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-purple-100 text-purple-800 border border-purple-200">
                                                Driver-Owned
                                            </span>
                                            @if($truck->ownerDriver)
                                                <div class="text-[10px] text-purple-900 mt-0.5 font-medium">{{ $truck->ownerDriver->name }}</div>
                                            @endif
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-200">
                                                Company-Owned
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-xs">
                                        <div class="font-semibold text-[#1B1B1E]">{{ $truck->model ?: $truck->name ?: '—' }}</div>
                                        <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-medium bg-gray-100 text-gray-800 mt-0.5">
                                            {{ ucfirst(str_replace('_', ' ', $truck->vehicle_type)) }}
                                        </span>
                                    </td>
                                    <td class="text-xs font-semibold text-[#1B1B1E]">
                                        <div>{{ number_format($truck->parcel_capacity) }} pcs</div>
                                        <div class="text-[#6E6E73] font-normal">{{ number_format($truck->capacity_kg, 1) }} kg</div>
                                    </td>
                                    <td class="text-xs">
                                        @if($truck->driver)
                                            <div class="font-semibold text-[#16697A]">{{ $truck->driver->name }}</div>
                                            <div class="text-[10px] text-[#6E6E73]">{{ $truck->driver->email }}</div>
                                        @else
                                            <span class="text-xs text-[#6E6E73] italic">Unassigned</span>
                                        @endif
                                    </td>
                                    <td class="text-xs">
                                        @if($truck->verification_status === 'approved')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                                Approved
                                            </span>
                                        @elseif($truck->verification_status === 'rejected')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-200">
                                                Rejected
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                                Pending Review
                                            </span>
                                        @endif
                                        @if($truck->verification_notes)
                                            <div class="text-[9px] text-[#6E6E73] mt-0.5 truncate max-w-[120px]" title="{{ $truck->verification_notes }}">{{ $truck->verification_notes }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $badgeClass = match($truck->status) {
                                                'available' => 'badge-delivered',
                                                'maintenance' => 'bg-amber-100 text-amber-800 border-amber-200',
                                                'damaged' => 'bg-orange-100 text-orange-800 border-orange-200',
                                                'retired' => 'bg-slate-200 text-slate-800 border-slate-300 font-bold',
                                                'assigned', 'in_transit' => 'bg-blue-100 text-blue-800 border-blue-200',
                                                default => 'badge-inactive',
                                            };
                                        @endphp
                                        <span class="badge {{ $badgeClass }}">{{ str($truck->status)->replace('_', ' ')->title() }}</span>
                                        @if($truck->isRetired())
                                            <div class="text-[9px] text-slate-600 font-medium mt-0.5">Decommissioned</div>
                                        @endif
                                        @if($truck->remarks)
                                            <div class="text-[10px] text-amber-800 font-medium mt-1 max-w-[140px] truncate" title="{{ $truck->remarks }}">
                                                {{ $truck->remarks }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        <div class="space-y-1.5 inline-block text-left">
                                            <form method="POST" action="{{ route('fleet.trucks.update', $truck) }}" class="space-y-1.5 inline-block text-left">
                                                @csrf
                                                @method('PUT')
                                                <div class="flex items-center gap-1.5">
                                                    <select name="status" class="select !py-1 !px-2 !text-xs !w-28" aria-label="Truck Status">
                                                        <option value="available" @selected($truck->status === 'available')>Available</option>
                                                        <option value="maintenance" @selected($truck->status === 'maintenance')>Maintenance</option>
                                                        <option value="damaged" @selected($truck->status === 'damaged')>Damaged</option>
                                                        <option value="retired" @selected($truck->status === 'retired')>Retired</option>
                                                        <option value="inactive" @selected($truck->status === 'inactive')>Inactive</option>
                                                    </select>
                                                    <button type="submit" class="btn btn-soft btn-xs">Save</button>
                                                    <button type="button" onclick="document.getElementById('edit-truck-modal-{{ $truck->id }}').showModal()" class="btn btn-ghost btn-xs text-[#16697A] font-semibold">Edit</button>
                                                </div>
                                                <div>
                                                    <input name="remarks" value="{{ $truck->remarks }}" placeholder="Status / maintenance remarks..." class="input !py-0.5 !px-2 !text-[11px] !w-44" aria-label="Maintenance Remarks">
                                                </div>
                                            </form>
                                        </div>

                                        {{-- Edit Truck Modal --}}
                                        <dialog id="edit-truck-modal-{{ $truck->id }}" class="modal p-6 rounded-xl shadow-xl max-w-lg w-full bg-white text-left">
                                            <h3 class="text-base font-bold text-[#1B1B1E]">Edit Truck Details — {{ $truck->plate_number }}</h3>
                                            <p class="text-xs text-[#6E6E73] mt-0.5">Update vehicle specifications, capacities, plate, and lifecycle operational status.</p>
                                            <form method="POST" action="{{ route('fleet.trucks.update', $truck) }}" class="mt-4 space-y-3">
                                                @csrf
                                                @method('PUT')
                                                <div>
                                                    <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Plate Number *</label>
                                                    <input name="plate_number" value="{{ $truck->plate_number }}" required class="input w-full text-xs font-mono uppercase">
                                                </div>
                                                <div class="grid grid-cols-2 gap-3">
                                                    <div>
                                                        <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Vehicle Type *</label>
                                                        <select name="vehicle_type" required class="select w-full text-xs">
                                                            <option value="light truck" @selected(in_array($truck->vehicle_type, ['light truck', 'light_truck']))>Light Truck</option>
                                                            <option value="truck" @selected($truck->vehicle_type === 'truck')>Truck</option>
                                                            <option value="van" @selected($truck->vehicle_type === 'van')>Van</option>
                                                            <option value="l300" @selected($truck->vehicle_type === 'l300')>L300 / Utility Vehicle</option>
                                                            <option value="cargo van" @selected(in_array($truck->vehicle_type, ['cargo van', 'cargo_van']))>Cargo Van</option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Brand / Model</label>
                                                        <input name="model" value="{{ $truck->model ?: $truck->name }}" placeholder="e.g. Isuzu Elf" class="input w-full text-xs">
                                                    </div>
                                                </div>
                                                <div class="grid grid-cols-2 gap-3">
                                                    <div>
                                                        <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Parcel Capacity (pcs) *</label>
                                                        <input name="parcel_capacity" type="number" min="1" value="{{ $truck->parcel_capacity }}" required class="input w-full text-xs">
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Max Weight Capacity (kg) *</label>
                                                        <input name="capacity_kg" type="number" step="0.01" min="0.01" value="{{ $truck->capacity_kg }}" required class="input w-full text-xs">
                                                    </div>
                                                </div>
                                                <div class="grid grid-cols-2 gap-3">
                                                    <div>
                                                        <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Operational Status *</label>
                                                        <select name="status" required class="select w-full text-xs">
                                                            <option value="available" @selected($truck->status === 'available')>Available</option>
                                                            <option value="assigned" @selected($truck->status === 'assigned')>Assigned</option>
                                                            <option value="in_transit" @selected($truck->status === 'in_transit')>In Transit</option>
                                                            <option value="maintenance" @selected($truck->status === 'maintenance')>Maintenance (Under Repair)</option>
                                                            <option value="damaged" @selected($truck->status === 'damaged')>Damaged (Unavailable)</option>
                                                            <option value="retired" @selected($truck->status === 'retired')>Retired (Decommissioned)</option>
                                                            <option value="inactive" @selected($truck->status === 'inactive')>Inactive</option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">OR / CR Registration</label>
                                                        <input name="vehicle_registration" value="{{ $truck->vehicle_registration }}" placeholder="Certificate number" class="input w-full text-xs">
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Operational Remarks</label>
                                                    <textarea name="remarks" rows="2" class="textarea w-full text-xs">{{ $truck->remarks }}</textarea>
                                                </div>
                                                <div class="flex items-center justify-end gap-2 pt-2">
                                                    <button type="button" onclick="document.getElementById('edit-truck-modal-{{ $truck->id }}').close()" class="btn btn-ghost btn-xs">Cancel</button>
                                                    <button type="submit" class="btn btn-primary btn-xs">Update Truck</button>
                                                </div>
                                            </form>
                                        </dialog>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-6 text-xs text-[#6E6E73]">No trucks registered yet. Registered company trucks or approved applications will appear here automatically.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div>{{ $trucks->links() }}</div>
        </section>

        {{-- Hub Transport Drivers Section --}}
        <section class="card p-5 space-y-4">
            <div>
                <h2 class="text-sm font-bold text-[#1B1B1E] uppercase tracking-wide">Hub Transport Drivers</h2>
                <p class="text-xs text-[#6E6E73] mt-0.5">Approved transport drivers authorized to perform inter-hub linehaul transfer routes.</p>
                <p class="text-[11px] text-[#0E4A57] bg-[#E6F4F1] border border-[#16697A]/20 rounded mt-2 px-3 py-2">
                    To make a truck available in Hub Transfer, select the approved driver and the company four-wheel truck here, then save.
                    Hub Transfer only lists trucks that already have an assigned driver.
                </p>
            </div>

            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>License Number</th>
                                <th>Assigned Truck</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($drivers as $driver)
                                <tr class="align-top">
                                    <td>
                                        <div class="font-bold text-[#1B1B1E]">{{ $driver->name }}</div>
                                        @if($driver->company)
                                            <div class="text-[10px] text-[#6E6E73]">{{ $driver->company->name }}</div>
                                        @endif
                                        @php
                                            $driverApplication = $driver->effectiveApplication();
                                        @endphp
                                        @if($driverApplication)
                                            <span class="inline-flex items-center mt-1 px-1.5 py-0.5 rounded text-[9px] font-bold {{ $driverApplication->status === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                                Application: {{ str($driverApplication->status)->replace('_', ' ')->title() }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center mt-1 px-1.5 py-0.5 rounded text-[9px] font-bold bg-gray-100 text-gray-700">
                                                Manual driver record
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-xs text-[#1B1B1E]">
                                        {{ $driver->email }}
                                    </td>
                                    <td class="text-xs text-[#6E6E73]">
                                        {{ $driver->phone ?: '—' }}
                                    </td>
                                    <td class="text-xs text-[#6E6E73]">
                                        {{ $driver->license_number ?: '—' }}
                                    </td>
                                    <td class="text-xs">
                                        @if($driver->truck)
                                            <div class="font-semibold text-[#16697A]">{{ $driver->truck->plate_number }}</div>
                                            <div class="text-[10px] text-[#6E6E73]">{{ $driver->truck->name ?: $driver->truck->model }} ({{ ucfirst(str_replace('_', ' ', $driver->truck->vehicle_type)) }})</div>
                                            @if($driver->truck->isDriverOwned())
                                                <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[9px] font-bold bg-purple-100 text-purple-800">Own Truck</span>
                                            @endif
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-100 text-amber-800 border border-amber-200">
                                                Unassigned
                                            </span>
                                            <div class="text-[10px] text-amber-700 mt-0.5">Awaiting 4-wheel truck</div>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $driverBadgeClass = $driver->status === 'available' ? 'badge-delivered' : (in_array($driver->status, ['assigned', 'in_transit'], true) ? 'bg-blue-100 text-blue-800 border-blue-200' : 'badge-inactive');
                                        @endphp
                                        <span class="badge {{ $driverBadgeClass }}">{{ str($driver->status)->replace('_', ' ')->title() }}</span>
                                        @if($driver->remarks)
                                            <div class="text-[10px] text-[#6E6E73] mt-1 max-w-[130px] truncate" title="{{ $driver->remarks }}">
                                                {{ $driver->remarks }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        <form method="POST" action="{{ route('fleet.drivers.update', $driver) }}" class="space-y-1.5 inline-block text-left">
                                            @csrf
                                            @method('PUT')
                                            <div class="flex items-center gap-1.5">
                                                <select name="status" class="select !py-1 !px-2 !text-xs !w-24" aria-label="Driver Status">
                                                    <option value="available" @selected($driver->status === 'available')>Available</option>
                                                    <option value="inactive" @selected($driver->status === 'inactive')>Inactive</option>
                                                </select>
                                                <select name="logistics_truck_id" class="select !py-1 !px-2 !text-xs !w-48" aria-label="Assign Truck">
                                                    <option value="">No truck (Unassigned)</option>
                                                    @foreach($allTrucks->where('logistics_company_id', $driver->logistics_company_id) as $availTruck)
                                                        @if($availTruck->status === 'available' || $driver->logistics_truck_id == $availTruck->id)
                                                            <option value="{{ $availTruck->id }}" @selected($driver->logistics_truck_id == $availTruck->id)>
                                                                {{ $availTruck->plate_number }} — {{ ucfirst(str_replace('_', ' ', $availTruck->vehicle_type)) }} {{ $availTruck->isDriverOwned() ? '[Driver-Owned]' : '' }}
                                                            </option>
                                                        @endif
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="btn btn-soft btn-xs">Save</button>
                                            </div>
                                            <div>
                                                <input name="remarks" value="{{ $driver->remarks }}" placeholder="Driver remarks..." class="input !py-0.5 !px-2 !text-[11px] !w-full" aria-label="Driver Remarks">
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-6 text-xs text-[#6E6E73]">No transport drivers registered yet. Approved applications will appear here automatically.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div>{{ $drivers->links() }}</div>
        </section>

        {{-- Vehicle Incidents & Damage Reports Section --}}
        <section class="card p-5 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div>
                    <h2 class="text-sm font-bold text-[#1B1B1E] uppercase tracking-wide">Vehicle Incidents &amp; Damage Records</h2>
                    <p class="text-xs text-[#6E6E73] mt-0.5">Reported accidents, mechanical breakdowns, and damage investigations. Driver compensation is never deducted for incident reports.</p>
                </div>
                <div>
                    <button type="button" onclick="document.getElementById('report-incident-modal').showModal()" class="btn btn-soft btn-xs text-rose-700 hover:bg-rose-100">
                        + Record Incident / Damage
                    </button>
                </div>
            </div>

            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Incident Ref</th>
                                <th>Date &amp; Location</th>
                                <th>Vehicle / Driver</th>
                                <th>Severity / Type</th>
                                <th>Status</th>
                                <th>Action Taken</th>
                                <th class="text-right">Investigation / Resolution</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($incidents as $incident)
                                <tr class="align-top">
                                    <td class="font-mono font-bold text-[#1B1B1E]">
                                        {{ $incident->reference }}
                                    </td>
                                    <td class="text-xs">
                                        <div class="font-semibold text-[#1B1B1E]">{{ $incident->incident_date?->format('M d, Y h:i A') }}</div>
                                        <div class="text-[10px] text-[#6E6E73]">{{ $incident->location ?: 'Location unrecorded' }}</div>
                                    </td>
                                    <td class="text-xs">
                                        <div class="font-mono font-bold text-[#16697A]">{{ $incident->truck?->plate_number }}</div>
                                        <div class="text-[10px] text-[#6E6E73]">Driver: {{ $incident->driver?->name ?? 'Unassigned' }}</div>
                                    </td>
                                    <td class="text-xs">
                                        @php
                                            $sevClass = match($incident->severity) {
                                                'total_loss' => 'bg-red-200 text-red-900 border-red-300 font-extrabold',
                                                'severe' => 'bg-rose-100 text-rose-800 border-rose-200 font-bold',
                                                'moderate' => 'bg-amber-100 text-amber-800 border-amber-200',
                                                default => 'bg-gray-100 text-gray-800',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] {{ $sevClass }}">
                                            {{ strtoupper(str_replace('_', ' ', $incident->severity)) }}
                                        </span>
                                        <div class="text-[10px] text-[#6E6E73] mt-0.5 capitalize">{{ $incident->incident_type }}</div>
                                    </td>
                                    <td>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-800">
                                            {{ ucfirst(str_replace('_', ' ', $incident->status)) }}
                                        </span>
                                    </td>
                                    <td class="text-xs">
                                        <span class="font-mono text-[11px] text-[#1B1B1E]">{{ ucfirst(str_replace('_', ' ', $incident->vehicle_action_taken ?: 'None')) }}</span>
                                    </td>
                                    <td class="text-right">
                                        <form method="POST" action="{{ route('fleet.incidents.update', $incident) }}" class="space-y-1.5 inline-block text-left">
                                            @csrf
                                            @method('PUT')
                                            <div class="flex items-center gap-1.5">
                                                <select name="status" class="select !py-1 !px-2 !text-xs !w-28" aria-label="Incident Status">
                                                    <option value="reported" @selected($incident->status === 'reported')>Reported</option>
                                                    <option value="under_investigation" @selected($incident->status === 'under_investigation')>Investigating</option>
                                                    <option value="under_repair" @selected($incident->status === 'under_repair')>Under Repair</option>
                                                    <option value="resolved" @selected($incident->status === 'resolved')>Resolved</option>
                                                    <option value="closed" @selected($incident->status === 'closed')>Closed</option>
                                                </select>
                                                <select name="vehicle_action_taken" class="select !py-1 !px-2 !text-xs !w-36" aria-label="Vehicle Action">
                                                    <option value="none">No Status Change</option>
                                                    <option value="returned_to_service">Return to Service (Available)</option>
                                                    <option value="under_repair">Put Under Maintenance</option>
                                                    <option value="marked_damaged">Mark as Damaged</option>
                                                    <option value="retired">Retire (Decommission)</option>
                                                </select>
                                                <button type="submit" class="btn btn-soft btn-xs">Update</button>
                                            </div>
                                            <div>
                                                <input name="resolution_notes" value="{{ $incident->resolution_notes }}" placeholder="Resolution / investigation notes..." class="input !py-0.5 !px-2 !text-[11px] !w-full" aria-label="Resolution Notes">
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-6 text-xs text-[#6E6E73]">No incident or damage records filed. Fleet condition is healthy.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div>{{ $incidents->links() }}</div>
        </section>

        {{-- Fleet Maintenance & Repair Records Section --}}
        <section class="card p-5 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div>
                    <h2 class="text-sm font-bold text-[#1B1B1E] uppercase tracking-wide">Fleet Maintenance &amp; Repair Records</h2>
                    <p class="text-xs text-[#6E6E73] mt-0.5">Service history, routine maintenance, accident repairs, and payment responsibility. Driver compensation is never deducted for vehicle maintenance or repair expenses.</p>
                </div>
                <div>
                    <button type="button" onclick="document.getElementById('record-maintenance-modal').showModal()" class="btn btn-soft btn-xs text-indigo-700 hover:bg-indigo-100">
                        + Record Vehicle Maintenance / Repair
                    </button>
                </div>
            </div>

            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Vehicle / Plate</th>
                                <th>Maintenance Type</th>
                                <th>Repair Shop / Provider</th>
                                <th>Estimated &amp; Actual Cost</th>
                                <th>Payment Responsibility</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($maintenanceRecords as $record)
                                <tr class="align-top">
                                    <td class="font-mono font-bold text-[#1B1B1E]">
                                        {{ $record->reference }}
                                        @if($record->incident)
                                            <div class="text-[10px] text-[#16697A] font-normal">Inc: {{ $record->incident->reference }}</div>
                                        @endif
                                    </td>
                                    <td class="text-xs">
                                        <div class="font-mono font-bold text-[#16697A]">{{ $record->truck?->plate_number }}</div>
                                        <div class="text-[10px] text-[#6E6E73]">{{ $record->truck?->model ?: $record->truck?->name }} ({{ $record->truck?->isDriverOwned() ? 'Driver-Owned' : 'Company' }})</div>
                                    </td>
                                    <td class="text-xs">
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-gray-100 text-gray-800">
                                            {{ ucfirst(str_replace('_', ' ', $record->maintenance_type)) }}
                                        </span>
                                        <div class="text-[11px] text-[#1B1B1E] mt-0.5 max-w-[200px] truncate" title="{{ $record->description }}">{{ $record->description }}</div>
                                    </td>
                                    <td class="text-xs">
                                        <div class="font-semibold text-[#1B1B1E]">{{ $record->service_provider ?: 'Internal / Unspecified' }}</div>
                                        @if($record->invoice_attachment_path)
                                            <a href="{{ asset('storage/' . $record->invoice_attachment_path) }}" target="_blank" class="text-[10px] text-[#16697A] underline block mt-0.5">View Invoice</a>
                                        @endif
                                    </td>
                                    <td class="text-xs">
                                        @if($record->actual_cost !== null)
                                            <div class="font-bold text-[#1B1B1E]">₱{{ number_format($record->actual_cost, 2) }}</div>
                                            <div class="text-[10px] text-[#6E6E73]">Est: ₱{{ number_format($record->estimated_cost ?? 0, 2) }}</div>
                                        @elseif($record->estimated_cost !== null)
                                            <div class="font-semibold text-[#1B1B1E]">Est: ₱{{ number_format($record->estimated_cost, 2) }}</div>
                                        @else
                                            <span class="text-[#6E6E73] italic">Unrecorded</span>
                                        @endif
                                    </td>
                                    <td class="text-xs">
                                        @php
                                            $respClass = match($record->payment_responsibility) {
                                                'company' => 'bg-blue-100 text-blue-800 border-blue-200',
                                                'driver_owner' => 'bg-purple-100 text-purple-800 border-purple-200',
                                                'insurance' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                                'third_party' => 'bg-orange-100 text-orange-800 border-orange-200',
                                                default => 'bg-gray-100 text-gray-800',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold {{ $respClass }}">
                                            {{ strtoupper(str_replace('_', ' ', $record->payment_responsibility)) }}
                                        </span>
                                        @if($record->responsibility_notes)
                                            <div class="text-[9px] text-[#6E6E73] mt-0.5 max-w-[130px] truncate" title="{{ $record->responsibility_notes }}">{{ $record->responsibility_notes }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $statusClass = match($record->status) {
                                                'completed' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                                'in_repair' => 'bg-amber-100 text-amber-800 border-amber-200',
                                                'approved' => 'bg-blue-100 text-blue-800 border-blue-200',
                                                default => 'bg-gray-100 text-gray-800',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold {{ $statusClass }}">
                                            {{ ucfirst(str_replace('_', ' ', $record->status)) }}
                                        </span>
                                        @if($record->returned_to_service_at)
                                            <div class="text-[9px] text-emerald-700 mt-0.5">Returned: {{ $record->returned_to_service_at->format('M d, Y') }}</div>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        <button type="button" onclick="document.getElementById('edit-maintenance-modal-{{ $record->id }}').showModal()" class="btn btn-soft btn-xs">
                                            Assess / Update
                                        </button>

                                        {{-- Edit / Assess Maintenance Modal --}}
                                        <dialog id="edit-maintenance-modal-{{ $record->id }}" class="modal p-6 rounded-xl shadow-xl max-w-lg w-full bg-white text-left">
                                            <h3 class="text-base font-bold text-[#1B1B1E]">Assess Maintenance — {{ $record->reference }}</h3>
                                            <p class="text-xs text-[#6E6E73] mt-0.5">Vehicle: {{ $record->truck?->plate_number }} | Current Status: {{ ucfirst(str_replace('_', ' ', $record->status)) }}</p>
                                            <form method="POST" action="{{ route('fleet.maintenance.update', $record) }}" class="mt-4 space-y-3">
                                                @csrf
                                                @method('PUT')
                                                <div class="grid grid-cols-2 gap-3">
                                                    <div>
                                                        <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Status *</label>
                                                        <select name="status" required class="select w-full text-xs">
                                                            <option value="pending_assessment" @selected($record->status === 'pending_assessment')>Pending Assessment</option>
                                                            <option value="approved" @selected($record->status === 'approved')>Approved</option>
                                                            <option value="in_repair" @selected($record->status === 'in_repair')>In Repair</option>
                                                            <option value="completed" @selected($record->status === 'completed')>Completed</option>
                                                            <option value="cancelled" @selected($record->status === 'cancelled')>Cancelled</option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Actual Repair Cost (₱)</label>
                                                        <input name="actual_cost" type="number" step="0.01" value="{{ $record->actual_cost }}" placeholder="0.00" class="input w-full text-xs">
                                                    </div>
                                                </div>
                                                <div class="grid grid-cols-2 gap-3">
                                                    <div>
                                                        <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Payment Responsibility *</label>
                                                        <select name="payment_responsibility" required class="select w-full text-xs">
                                                            <option value="company" @selected($record->payment_responsibility === 'company')>Company</option>
                                                            <option value="driver_owner" @selected($record->payment_responsibility === 'driver_owner')>Driver-Owner (Normal Mnt.)</option>
                                                            <option value="insurance" @selected($record->payment_responsibility === 'insurance')>Insurance</option>
                                                            <option value="third_party" @selected($record->payment_responsibility === 'third_party')>Third Party</option>
                                                            <option value="shared" @selected($record->payment_responsibility === 'shared')>Shared</option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Service Provider / Shop</label>
                                                        <input name="service_provider" value="{{ $record->service_provider }}" placeholder="Repair facility..." class="input w-full text-xs">
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Responsibility / Assessment Notes</label>
                                                    <textarea name="responsibility_notes" rows="2" class="textarea w-full text-xs" placeholder="Findings, insurance coverage, agreement details...">{{ $record->responsibility_notes }}</textarea>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Admin Remarks</label>
                                                    <textarea name="admin_remarks" rows="2" class="textarea w-full text-xs" placeholder="Final inspection, safety notes...">{{ $record->admin_remarks }}</textarea>
                                                </div>

                                                <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 space-y-2">
                                                    <div class="text-[11px] font-bold text-[#1B1B1E] uppercase tracking-wide">Vehicle Lifecycle Actions</div>
                                                    <label class="flex items-start gap-2 cursor-pointer">
                                                        <input type="checkbox" name="return_to_service" value="1" class="checkbox checkbox-xs text-emerald-600 rounded mt-0.5">
                                                        <span class="text-xs text-[#1B1B1E]">
                                                            <strong>Authorize Return to Available Service:</strong> Repair is complete and verified. Vehicle status will be safely restored to Available.
                                                        </span>
                                                    </label>
                                                    <label class="flex items-start gap-2 cursor-pointer">
                                                        <input type="checkbox" name="retire_vehicle" value="1" class="checkbox checkbox-xs text-red-600 rounded mt-0.5">
                                                        <span class="text-xs text-red-800">
                                                            <strong>Retire Vehicle (Beyond Repair):</strong> Mark vehicle permanently decommissioned/retired. Vehicle will not receive future assignments while preserving all history.
                                                        </span>
                                                    </label>
                                                </div>

                                                <div class="flex items-center justify-end gap-2 pt-2">
                                                    <button type="button" onclick="document.getElementById('edit-maintenance-modal-{{ $record->id }}').close()" class="btn btn-ghost btn-xs">Cancel</button>
                                                    <button type="submit" class="btn btn-primary btn-xs">Save Assessment</button>
                                                </div>
                                            </form>
                                        </dialog>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-6 text-xs text-[#6E6E73]">No fleet maintenance or repair records logged.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div>{{ $maintenanceRecords->links() }}</div>
        </section>
    </div>

    {{-- Register Company Truck Modal --}}
    <dialog id="register-truck-modal" class="modal p-6 rounded-xl shadow-xl max-w-lg w-full bg-white text-left">
        <h3 class="text-base font-bold text-[#1B1B1E]">Register Newly Acquired Company Truck</h3>
        <p class="text-xs text-[#6E6E73] mt-0.5">Add a four-wheel vehicle to the company fleet for inter-hub transport operations.</p>
        <form method="POST" action="{{ route('fleet.trucks.store') }}" class="mt-4 space-y-3">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Plate Number *</label>
                <input name="plate_number" required placeholder="e.g. NCD 4821" class="input w-full text-xs font-mono uppercase">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Vehicle Type *</label>
                    <select name="vehicle_type" required class="select w-full text-xs">
                        <option value="light truck">Light Truck</option>
                        <option value="truck">Truck</option>
                        <option value="van">Van</option>
                        <option value="l300">L300 / Utility Vehicle</option>
                        <option value="cargo van">Cargo Van</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Brand / Model</label>
                    <input name="model" placeholder="e.g. Isuzu Elf / Mitsubishi L300" class="input w-full text-xs">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Parcel Capacity (pcs) *</label>
                    <input name="parcel_capacity" type="number" min="1" required placeholder="e.g. 500" class="input w-full text-xs">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Max Weight Capacity (kg) *</label>
                    <input name="capacity_kg" type="number" step="0.01" min="0.01" required placeholder="e.g. 2000.00" class="input w-full text-xs">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">OR / CR Registration Number</label>
                <input name="vehicle_registration" placeholder="Official OR/CR certificate number" class="input w-full text-xs">
            </div>
            <div>
                <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Operational Remarks</label>
                <textarea name="remarks" rows="2" placeholder="Vehicle notes, registration expiry..." class="textarea w-full text-xs"></textarea>
            </div>
            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('register-truck-modal').close()" class="btn btn-ghost btn-xs">Cancel</button>
                <button type="submit" class="btn btn-primary btn-xs">Save Truck to Fleet</button>
            </div>
        </form>
    </dialog>

    {{-- Record Incident Modal --}}
    <dialog id="report-incident-modal" class="modal p-6 rounded-xl shadow-xl max-w-lg w-full bg-white text-left">
        <h3 class="text-base font-bold text-[#1B1B1E]">Record Incident / Vehicle Damage</h3>
        <p class="text-xs text-[#6E6E73] mt-0.5">Logs vehicle damage and updates operational status. Driver compensation is never deducted.</p>
        <form method="POST" action="{{ route('fleet.incidents.store') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Involved Vehicle *</label>
                <select name="logistics_truck_id" required class="select w-full text-xs">
                    <option value="">Select vehicle...</option>
                    @foreach($trucks as $trk)
                        <option value="{{ $trk->id }}">{{ $trk->plate_number }} — {{ $trk->model ?: $trk->name }} ({{ ucfirst(str_replace('_', ' ', $trk->vehicle_type)) }})</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Involved Driver (optional)</label>
                    <select name="hub_transport_driver_id" class="select w-full text-xs">
                        <option value="">None / Unspecified</option>
                        @foreach($drivers as $drv)
                            <option value="{{ $drv->id }}">{{ $drv->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Incident Type *</label>
                    <select name="incident_type" required class="select w-full text-xs">
                        <option value="accident">Collision / Accident</option>
                        <option value="mechanical_breakdown">Mechanical Breakdown</option>
                        <option value="weather_damage">Weather / Flood Damage</option>
                        <option value="theft_vandalism">Theft / Vandalism</option>
                        <option value="other">Other Incident</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Severity *</label>
                    <select name="severity" required class="select w-full text-xs">
                        <option value="minor">Minor (Scratch / Cosmetic)</option>
                        <option value="moderate">Moderate (Drivable with repair needed)</option>
                        <option value="severe">Severe (Inoperable / Heavy Repair)</option>
                        <option value="total_loss">Total Loss (Decommission / Retire)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Vehicle Action *</label>
                    <select name="vehicle_action_taken" required class="select w-full text-xs">
                        <option value="marked_damaged">Mark as Damaged</option>
                        <option value="under_repair">Put Under Maintenance</option>
                        <option value="retired">Retire Truck Permanently</option>
                        <option value="none">No Fleet Status Change</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Incident Date *</label>
                    <input name="incident_date" type="datetime-local" value="{{ now()->format('Y-m-d\TH:i') }}" required class="input w-full text-xs">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Location</label>
                    <input name="location" placeholder="e.g. SLEX Km 42, Calamba" class="input w-full text-xs">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Description *</label>
                <textarea name="description" rows="2" required placeholder="Explain what occurred..." class="textarea w-full text-xs"></textarea>
            </div>
            <div>
                <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Damage Details</label>
                <textarea name="damage_details" rows="2" placeholder="Describe the physical damage, parts affected..." class="textarea w-full text-xs"></textarea>
            </div>
            <div>
                <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Evidence Photos (optional)</label>
                <input name="photos[]" type="file" multiple accept="image/*" class="file-input w-full text-xs">
            </div>
            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('report-incident-modal').close()" class="btn btn-ghost btn-xs">Cancel</button>
                <button type="submit" class="btn btn-primary btn-xs">Submit Incident Report</button>
            </div>
        </form>
    </dialog>

    {{-- Record Vehicle Maintenance / Repair Modal --}}
    <dialog id="record-maintenance-modal" class="modal p-6 rounded-xl shadow-xl max-w-lg w-full bg-white text-left">
        <h3 class="text-base font-bold text-[#1B1B1E]">Record Vehicle Maintenance / Repair</h3>
        <p class="text-xs text-[#6E6E73] mt-0.5">Logs vehicle maintenance, provider details, and payment responsibility. Driver earnings are never deducted.</p>
        <form method="POST" action="{{ route('fleet.maintenance.store') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Involved Vehicle *</label>
                <select name="logistics_truck_id" required class="select w-full text-xs">
                    <option value="">Select vehicle...</option>
                    @foreach($trucks as $trk)
                        <option value="{{ $trk->id }}">{{ $trk->plate_number }} — {{ $trk->model ?: $trk->name }} ({{ $trk->isDriverOwned() ? 'Driver-Owned' : 'Company' }})</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Related Incident (optional)</label>
                    <select name="vehicle_incident_id" class="select w-full text-xs">
                        <option value="">None / Routine Service</option>
                        @foreach($incidents as $inc)
                            <option value="{{ $inc->id }}">{{ $inc->reference }} ({{ $inc->truck?->plate_number }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Maintenance Type *</label>
                    <select name="maintenance_type" required class="select w-full text-xs">
                        <option value="routine_maintenance">Routine Maintenance (Oil, Tires, Brakes)</option>
                        <option value="accident_repair">Accident / Collision Repair</option>
                        <option value="mechanical_repair">Mechanical Breakdown</option>
                        <option value="body_paint">Body &amp; Paint</option>
                        <option value="inspection">Safety / Emission Inspection</option>
                        <option value="other">Other Service</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Service Provider / Repair Shop</label>
                <input name="service_provider" placeholder="e.g. Isuzu Service Center Calamba / Goodyear" class="input w-full text-xs">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Estimated Cost (₱)</label>
                    <input name="estimated_cost" type="number" step="0.01" min="0" placeholder="0.00" class="input w-full text-xs">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Actual Cost (₱)</label>
                    <input name="actual_cost" type="number" step="0.01" min="0" placeholder="0.00" class="input w-full text-xs">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Payment Responsibility *</label>
                    <select name="payment_responsibility" required class="select w-full text-xs">
                        <option value="company">Company</option>
                        <option value="driver_owner">Driver-Owner (Normal Mnt.)</option>
                        <option value="insurance">Insurance Coverage</option>
                        <option value="third_party">Third Party at Fault</option>
                        <option value="shared">Shared Responsibility</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Initial Status *</label>
                    <select name="status" required class="select w-full text-xs">
                        <option value="pending_assessment">Pending Assessment</option>
                        <option value="approved">Approved</option>
                        <option value="in_repair">In Repair</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Maintenance / Damage Description *</label>
                <textarea name="description" rows="2" required placeholder="Describe parts replaced, damage repairs, or maintenance work performed..." class="textarea w-full text-xs"></textarea>
            </div>
            <div>
                <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Responsibility / Agreement Notes</label>
                <textarea name="responsibility_notes" rows="1" placeholder="Agreement details, insurance policy number, or fault assessment..." class="textarea w-full text-xs"></textarea>
            </div>
            <div>
                <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Invoice / Receipt Attachment (optional)</label>
                <input name="invoice" type="file" accept="image/*,.pdf" class="file-input w-full text-xs">
            </div>
            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('record-maintenance-modal').close()" class="btn btn-ghost btn-xs">Cancel</button>
                <button type="submit" class="btn btn-primary btn-xs">Save Maintenance Record</button>
            </div>
        </form>
    </dialog>
</x-app-layout>
