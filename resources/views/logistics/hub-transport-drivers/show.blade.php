<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <a href="{{ route('hub-transport-drivers.index') }}" class="lw-back" aria-label="Back to Hub Transport Drivers">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </a>
                <div>
                    <h1 class="text-2xl font-bold text-[#1B1B1E]">Hub Transport Driver</h1>
                    <p class="mt-0.5 text-xs text-[#6E6E73]">Inter-hub linehaul driver profile and assignments.</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if($driver->status === 'in_transit')
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full ring-1 ring-inset bg-blue-50 text-blue-700 ring-blue-200">
                        <span class="h-1.5 w-1.5 rounded-full bg-current"></span> In Transit
                    </span>
                @else
                    <x-status-badge :status="$driver->status" />
                @endif
                @if($driver->is_online)
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-600">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span> Online
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-[#6E6E73]">
                        <span class="h-2 w-2 rounded-full bg-gray-300"></span> Offline
                    </span>
                @endif
            </div>
        </div>
    </x-slot>

    @php($truck = $driver->truck ?: $driver->ownedTruck)

    @if(session('success'))
        <div class="mb-4 card p-4 bg-green-50 border-green-200 text-green-800 text-xs">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="mb-4 card p-4 bg-red-50 border-red-200 text-red-800 text-xs">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="space-y-4">
        {{-- Profile Header --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-5">
                <div class="h-16 w-16 rounded-full bg-[#16697A] flex items-center justify-center text-2xl font-bold text-white flex-shrink-0">
                    {{ strtoupper(substr($driver->name, 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <h2 class="text-xl font-bold text-gray-900">{{ $driver->name }}</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ $driver->email ?: 'No email on file' }} &middot; {{ $driver->phone ?: 'No phone on file' }}</p>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-lg bg-[#EAF4F3] text-[#0E4A57]">
                            License: {{ $driver->license_number ?: '—' }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-lg bg-[#F0EEE9] text-[#6E6E73]">
                            Hub Transport Driver
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Operational Information --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-base font-bold text-gray-900 mb-5">Operational Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Logistics Company</p>
                    <p class="text-sm font-medium text-gray-900">{{ $driver->company?->name ?: '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Driver Status</p>
                    <p class="text-sm font-medium text-gray-900">{{ ucfirst(str_replace('_', ' ', $driver->status)) }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Application Approval</p>
                    @if($application)
                        <x-status-badge :status="$application->status" />
                    @else
                        <p class="text-sm font-medium text-gray-900">Provisioned driver</p>
                    @endif
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Login Account</p>
                    @if($driver->user)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full ring-1 ring-inset {{ $driver->user->isActive() ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-red-50 text-red-700 ring-red-200' }}">
                            <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                            {{ $driver->user->isActive() ? 'Active' : ucfirst((string) $driver->user->status) }}
                        </span>
                        <p class="mt-1 text-xs text-gray-500">{{ $driver->user->email }}</p>
                    @else
                        <p class="text-sm text-gray-400">No login account linked</p>
                    @endif
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Assigned Truck</p>
                    @if($truck)
                        <p class="text-sm font-semibold text-[#16697A]">{{ $truck->plate_number }}</p>
                        <p class="text-xs text-gray-500">{{ $truck->model ?: $truck->name ?: '—' }} ({{ ucfirst(str_replace('_', ' ', $truck->vehicle_type)) }})</p>
                    @else
                        <p class="text-sm text-amber-700 font-medium">Unassigned</p>
                    @endif
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Vehicle Ownership</p>
                    @if($truck)
                        <p class="text-sm font-medium text-gray-900">{{ $truck->isDriverOwned() ? 'Driver-Owned' : 'Company-Owned' }}</p>
                        <p class="text-xs text-gray-500">{{ number_format($truck->parcel_capacity) }} parcels / {{ number_format((float) $truck->capacity_kg, 1) }} kg</p>
                    @else
                        <p class="text-sm text-gray-400">—</p>
                    @endif
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Hub Transfers</p>
                    <p class="text-sm font-medium text-gray-900">{{ $transferStats['total'] }} total &middot; {{ $transferStats['active'] }} active</p>
                    <p class="text-xs text-gray-500">{{ $transferStats['completed'] }} received</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Latest Transfer</p>
                    @if($driver->latestTransfer)
                        <a href="{{ route('hub-transfers.show', $driver->latestTransfer) }}" class="text-sm font-semibold text-[#16697A] hover:underline">{{ $driver->latestTransfer->reference }}</a>
                        <p class="text-xs text-gray-500">{{ $driver->latestTransfer->origin?->name ?: '—' }} &rarr; {{ $driver->latestTransfer->destination?->name ?: '—' }}</p>
                    @else
                        <p class="text-sm text-gray-400">No transfers yet</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Driver Assignment & Status --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
                <div>
                    <h3 class="text-base font-bold text-gray-900">Driver Assignment &amp; Status</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Update the operational status, assign an approved four-wheel truck, or record administrative remarks.</p>
                </div>
                <a href="{{ route('fleet.index') }}" class="btn btn-ghost btn-sm">Open Fleet Management</a>
            </div>

            <form method="POST" action="{{ route('fleet.drivers.update', $driver) }}" class="grid grid-cols-1 md:grid-cols-3 gap-4"
                  x-data="{ driverStatus: @js(in_array($driver->status, ['inactive', 'suspended'], true) ? $driver->status : 'available') }">
                @csrf
                @method('PUT')

                <div>
                    <label class="field-label" for="driver-status-select">Driver Status</label>
                    <select id="driver-status-select" name="status" class="select w-full" x-model="driverStatus">
                        <option value="available" @selected($driver->status === 'available')>Available</option>
                        <option value="inactive" @selected($driver->status === 'inactive')>Inactive</option>
                        <option value="suspended" @selected($driver->status === 'suspended')>Suspended</option>
                    </select>
                </div>

                <div>
                    <label class="field-label" for="driver-truck-select">Assigned Truck</label>
                    <select id="driver-truck-select" name="logistics_truck_id" class="select w-full" :disabled="driverStatus === 'inactive' || driverStatus === 'suspended'">
                        <option value="">No truck (Unassigned)</option>
                        @foreach($availableTrucks as $availableTruck)
                            <option value="{{ $availableTruck->id }}" @selected((int) $driver->logistics_truck_id === (int) $availableTruck->id)>
                                {{ $availableTruck->plate_number }} — {{ ucfirst(str_replace('_', ' ', $availableTruck->vehicle_type)) }}{{ $availableTruck->isDriverOwned() ? ' [Driver-Owned]' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[11px] text-[#6E6E73]" x-show="driverStatus === 'inactive' || driverStatus === 'suspended'" x-cloak>Inactive or suspended drivers cannot hold a truck assignment. Suspension is only allowed when the driver has no active transfer.</p>
                </div>

                <div>
                    <label class="field-label" for="driver-remarks">Administrative Remarks</label>
                    <input id="driver-remarks" type="text" name="remarks" value="{{ $driver->remarks }}" placeholder="Remarks..." class="input w-full">
                </div>

                <div class="md:col-span-3 flex justify-end">
                    <button type="submit" class="btn btn-primary btn-sm">Save Driver Assignment</button>
                </div>
            </form>
        </div>

        {{-- Hub Transfer History --}}
        <div class="card overflow-hidden">
            <div class="p-4 border-b border-[#E8E6E0]">
                <h3 class="text-sm font-bold text-[#1B1B1E] uppercase tracking-wide">Hub Transfer History</h3>
                <p class="text-xs text-[#6E6E73] mt-0.5">Linehaul transfers assigned to this driver within their logistics company.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Route</th>
                            <th>Truck</th>
                            <th>Status</th>
                            <th>Planned Dispatch</th>
                            <th>Dispatched</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transfers as $transfer)
                            <tr>
                                <td class="font-mono text-xs font-semibold text-[#1B1B1E]">{{ $transfer->reference }}</td>
                                <td class="text-xs">
                                    <div class="font-medium text-[#1B1B1E]">{{ $transfer->origin?->name ?: '—' }}</div>
                                    <div class="text-[#6E6E73]">&rarr; {{ $transfer->destination?->name ?: '—' }}</div>
                                </td>
                                <td class="text-xs text-[#4A5568]">{{ $transfer->truck?->plate_number ?: '—' }}</td>
                                <td><x-status-badge :status="$transfer->status" /></td>
                                <td class="text-xs text-[#4A5568]">{{ $transfer->planned_dispatch_at?->format('M d, Y H:i') ?: '—' }}</td>
                                <td class="text-xs text-[#4A5568]">{{ $transfer->dispatched_at?->format('M d, Y H:i') ?: '—' }}</td>
                                <td class="text-right">
                                    <a href="{{ route('hub-transfers.show', $transfer) }}" class="btn btn-soft btn-xs">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-10 text-center text-xs text-[#6E6E73]">No hub transfers have been assigned to this driver yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($transfers->count() > 0)
                <div class="p-4 border-t border-[#E8E6E0] flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-[#6E6E73]">
                    <p>Showing <strong class="text-[#1B1B1E]">{{ $transfers->firstItem() }}–{{ $transfers->lastItem() }}</strong> of <strong class="text-[#1B1B1E]">{{ $transfers->total() }}</strong> transfers</p>
                    {{ $transfers->onEachSide(2)->links() }}
                </div>
            @endif
        </div>

        {{-- Vehicle Incidents --}}
        @if($incidents->isNotEmpty())
            <div class="card overflow-hidden">
                <div class="p-4 border-b border-[#E8E6E0]">
                    <h3 class="text-sm font-bold text-[#1B1B1E] uppercase tracking-wide">Vehicle Incidents</h3>
                    <p class="text-xs text-[#6E6E73] mt-0.5">Incident reports involving this Hub Transport Driver. Driver earnings are not deducted for reported incidents.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Date</th>
                                <th>Truck</th>
                                <th>Type / Severity</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($incidents as $incident)
                                <tr>
                                    <td class="font-mono text-xs font-semibold text-[#1B1B1E]">{{ $incident->reference }}</td>
                                    <td class="text-xs text-[#4A5568]">{{ $incident->incident_date?->format('M d, Y') ?: '—' }}</td>
                                    <td class="text-xs text-[#4A5568]">{{ $incident->truck?->plate_number ?: '—' }}</td>
                                    <td class="text-xs text-[#4A5568]">{{ ucfirst(str_replace('_', ' ', $incident->incident_type)) }} &middot; {{ ucfirst(str_replace('_', ' ', $incident->severity)) }}</td>
                                    <td><x-status-badge :status="$incident->status" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
