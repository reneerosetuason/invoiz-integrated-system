<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-[#1B1B1E]">Hub Transport Drivers</h1>
                <p class="mt-0.5 text-xs text-[#6E6E73]">Approved four-wheel drivers authorized to perform inter-hub linehaul transfers. Delivery riders are managed separately under Delivery Riders.</p>
            </div>
            <a href="{{ route('riders.index') }}" class="btn btn-ghost btn-sm">View Delivery Riders</a>
        </div>
    </x-slot>

    @php
        $hasFilters = trim((string) request('search')) !== ''
            || trim((string) request('status')) !== ''
            || trim((string) request('approval')) !== ''
            || trim((string) request('assignment')) !== ''
            || ($global && (int) request('logistics_company_id') > 0);
    @endphp

    <div class="space-y-4">
        {{-- Summary Cards --}}
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="card p-4 border border-[#E8E6E0] bg-white">
                <div class="text-[10px] font-bold text-[#6E6E73] uppercase tracking-wide">Registered Drivers</div>
                <div class="mt-1 text-2xl font-extrabold text-[#1B1B1E]">{{ number_format($summary['total']) }}</div>
                <div class="text-[10px] text-[#6E6E73] mt-0.5">Hub transport accounts</div>
            </div>
            <div class="card p-4 border border-emerald-200 bg-emerald-50/40">
                <div class="text-[10px] font-bold text-emerald-800 uppercase tracking-wide">Online</div>
                <div class="mt-1 text-2xl font-extrabold text-emerald-900">{{ number_format($summary['online']) }}</div>
                <div class="text-[10px] text-emerald-700 mt-0.5">Available on the driver app</div>
            </div>
            <div class="card p-4 border border-amber-200 bg-amber-50/40">
                <div class="text-[10px] font-bold text-amber-800 uppercase tracking-wide">No Truck Assigned</div>
                <div class="mt-1 text-2xl font-extrabold text-amber-900">{{ number_format($summary['unassigned']) }}</div>
                <div class="text-[10px] text-amber-700 mt-0.5">Awaiting a four-wheel vehicle</div>
            </div>
            <div class="card p-4 border border-blue-200 bg-blue-50/40">
                <div class="text-[10px] font-bold text-blue-800 uppercase tracking-wide">Active Transfers</div>
                <div class="mt-1 text-2xl font-extrabold text-blue-900">{{ number_format($summary['active_transfers']) }}</div>
                <div class="text-[10px] text-blue-700 mt-0.5">In progress across hubs</div>
            </div>
        </div>

        {{-- Search & Filter Toolbar --}}
        <div class="card p-4">
            <form method="GET" action="{{ route('hub-transport-drivers.index') }}" class="flex flex-col lg:flex-row lg:items-end gap-3">
                <div class="flex-1">
                    <label class="field-label" for="driver-search">Search Drivers</label>
                    <input id="driver-search" type="text" name="search" value="{{ request('search') }}"
                           placeholder="Search name, email, phone, license, or truck plate..." class="input">
                </div>

                @if($global)
                    <div class="sm:w-52">
                        <label class="field-label" for="driver-company">Logistics Company</label>
                        <select id="driver-company" name="logistics_company_id" class="select">
                            <option value="">All companies</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}" @selected((int) request('logistics_company_id') === (int) $company->id)>{{ $company->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="sm:w-40">
                    <label class="field-label" for="driver-status">Status</label>
                    <select id="driver-status" name="status" class="select">
                        <option value="">All statuses</option>
                        <option value="available" @selected(request('status') === 'available')>Available</option>
                        <option value="assigned" @selected(request('status') === 'assigned')>Assigned</option>
                        <option value="in_transit" @selected(request('status') === 'in_transit')>In transit</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                        <option value="suspended" @selected(request('status') === 'suspended')>Suspended</option>
                    </select>
                </div>

                <div class="sm:w-44">
                    <label class="field-label" for="driver-approval">Approval</label>
                    <select id="driver-approval" name="approval" class="select">
                        <option value="">All approvals</option>
                        <option value="approved" @selected(request('approval') === 'approved')>Approved</option>
                        <option value="pending" @selected(request('approval') === 'pending')>Pending</option>
                        <option value="rejected" @selected(request('approval') === 'rejected')>Rejected</option>
                        <option value="unlinked" @selected(request('approval') === 'unlinked')>No application record</option>
                    </select>
                </div>

                <div class="sm:w-40">
                    <label class="field-label" for="driver-assignment">Truck</label>
                    <select id="driver-assignment" name="assignment" class="select">
                        <option value="">All assignments</option>
                        <option value="assigned" @selected(request('assignment') === 'assigned')>Assigned</option>
                        <option value="unassigned" @selected(request('assignment') === 'unassigned')>Unassigned</option>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    @if($hasFilters)
                        <a href="{{ route('hub-transport-drivers.index') }}" class="btn btn-ghost">Clear</a>
                    @endif
                </div>
            </form>
        </div>

        <div class="flex justify-end">
            <x-per-page route="hub-transport-drivers.index"/>
        </div>

        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Driver</th>
                            <th>Logistics Company</th>
                            <th>Approval / Account</th>
                            <th>Assigned Truck</th>
                            <th>Hub Transfers</th>
                            <th>Status</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($drivers as $driver)
                            @php($truck = $driver->truck ?: $driver->ownedTruck)
                            @php($application = $driver->application)
                            @php($latestTransfer = $driver->latestTransfer)
                            <tr class="align-top">
                                <td>
                                    <div class="flex items-start gap-2.5">
                                        <div class="w-8 h-8 rounded-full bg-[#EAF4F3] text-[#0E4A57] font-bold text-xs flex items-center justify-center shrink-0">
                                            {{ strtoupper(substr($driver->name, 0, 1)) }}
                                        </div>
                                        <div>
                                            <a href="{{ route('hub-transport-drivers.show', $driver) }}" class="font-semibold text-[#1B1B1E] hover:text-[#16697A]">{{ $driver->name }}</a>
                                            <div class="text-[11px] text-[#6E6E73]">{{ $driver->email ?: '—' }}</div>
                                            <div class="text-[11px] text-[#6E6E73]">{{ $driver->phone ?: '—' }}</div>
                                            <div class="text-[11px] text-[#6E6E73]">License: {{ $driver->license_number ?: '—' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-xs">
                                    @if($driver->company)
                                        <div class="font-semibold text-[#1B1B1E]">{{ $driver->company->name }}</div>
                                    @else
                                        <span class="text-[#6E6E73] italic">Unassigned</span>
                                    @endif
                                </td>
                                <td class="text-xs space-y-1">
                                    <div>
                                        @if($application)
                                            <x-status-badge :status="$application->status" :label="'Application: '.ucfirst($application->status)" />
                                        @else
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full ring-1 ring-inset bg-slate-100 text-slate-700 ring-slate-200">
                                                <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                                                Provisioned driver
                                            </span>
                                        @endif
                                    </div>
                                    <div>
                                        @if($driver->user)
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full ring-1 ring-inset {{ $driver->user->isActive() ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-red-50 text-red-700 ring-red-200' }}">
                                                <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                                                Account: {{ $driver->user->isActive() ? 'Active' : ucfirst((string) $driver->user->status) }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full ring-1 ring-inset bg-gray-100 text-gray-600 ring-gray-200">
                                                <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                                                No login account
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-xs">
                                    @if($truck)
                                        <div class="font-semibold text-[#16697A]">{{ $truck->plate_number }}</div>
                                        <div class="text-[10px] text-[#6E6E73]">{{ $truck->model ?: $truck->name ?: '—' }} ({{ ucfirst(str_replace('_', ' ', $truck->vehicle_type)) }})</div>
                                        <div class="mt-0.5 flex flex-wrap gap-1">
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold {{ $truck->isDriverOwned() ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' }}">
                                                {{ $truck->isDriverOwned() ? 'Driver-Owned' : 'Company-Owned' }}
                                            </span>
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-semibold bg-gray-100 text-gray-700">{{ number_format($truck->parcel_capacity) }} pcs / {{ number_format((float) $truck->capacity_kg, 1) }} kg</span>
                                        </div>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-100 text-amber-800 border border-amber-200">Unassigned</span>
                                        <div class="text-[10px] text-amber-700 mt-0.5">Awaiting a four-wheel truck</div>
                                    @endif
                                </td>
                                <td class="text-xs">
                                    <div class="flex flex-wrap items-center gap-1">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold {{ $driver->active_transfers_count > 0 ? 'bg-blue-100 text-blue-800' : 'bg-[#F0EEE9] text-[#6E6E73]' }}">
                                            {{ $driver->active_transfers_count }} active
                                        </span>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-[#F0EEE9] text-[#6E6E73]">{{ $driver->transfers_count }} total</span>
                                    </div>
                                    @if($latestTransfer)
                                        <div class="mt-1">
                                            <a href="{{ route('hub-transfers.show', $latestTransfer) }}" class="font-mono text-[10px] font-semibold text-[#16697A] hover:underline">{{ $latestTransfer->reference }}</a>
                                            <div class="text-[10px] text-[#6E6E73]">
                                                {{ $latestTransfer->origin?->name ?: '—' }} &rarr; {{ $latestTransfer->destination?->name ?: '—' }}
                                            </div>
                                        </div>
                                    @else
                                        <div class="text-[10px] text-[#6E6E73] mt-1">No hub-transfer history</div>
                                    @endif
                                </td>
                                <td class="text-xs">
                                    @if($driver->status === 'in_transit')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full ring-1 ring-inset bg-blue-50 text-blue-700 ring-blue-200">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current"></span> In Transit
                                        </span>
                                    @else
                                        <x-status-badge :status="$driver->status" />
                                    @endif
                                    <div class="mt-1">
                                        @if($driver->is_online)
                                            <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-emerald-600">
                                                <span class="h-2 w-2 rounded-full bg-emerald-500"></span> Online
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-[#6E6E73]">
                                                <span class="h-2 w-2 rounded-full bg-gray-300"></span> Offline
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-right">
                                    <a href="{{ route('hub-transport-drivers.show', $driver) }}" class="btn btn-soft btn-xs">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-12 text-center">
                                    <p class="text-sm font-bold text-[#1B1B1E]">No Hub Transport Drivers found</p>
                                    <p class="mt-1 text-xs text-[#6E6E73]">No transport drivers match the selected filters. Delivery riders are listed separately under Delivery Riders.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($drivers->count() > 0)
                <div class="p-4 border-t border-[#E8E6E0] flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-[#6E6E73]">
                    <p>Showing <strong class="text-[#1B1B1E]">{{ $drivers->firstItem() }}–{{ $drivers->lastItem() }}</strong> of <strong class="text-[#1B1B1E]">{{ $drivers->total() }}</strong> transport drivers</p>
                    {{ $drivers->onEachSide(2)->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
