<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-[#1B1B1E]">Riders</h1>
                <p class="mt-0.5 text-xs text-[#6E6E73]">Find a rider, check their workload, and review availability before assigning a delivery.</p>
            </div>
        </div>
    </x-slot>

    @php
        $currentStatus = request('status');
        $tabs = [
            ['label' => 'All', 'status' => null],
            ['label' => 'Available', 'status' => 'available'],
            ['label' => 'Delivering', 'status' => 'delivering'],
            ['label' => 'Inactive', 'status' => 'inactive'],
        ];
        $tabUrl = fn ($status) => route('riders.index', collect(request()->query())->except('page')->merge(['status' => $status])->filter(fn ($v) => $v !== null && $v !== '')->all());
        $hasFilters = trim((string) request('search')) !== '' || $currentStatus || request('center_id') || request('service_area_id') || request('online');
    @endphp

    <div class="space-y-4">
        {{-- Status Filter Tabs --}}
        <div class="card p-2 flex flex-wrap gap-1.5" aria-label="Rider status">
            @foreach($tabs as $tab)
                @php $active = ($currentStatus === $tab['status']) || (!$currentStatus && !$tab['status']); @endphp
                <a href="{{ $tabUrl($tab['status']) }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ $active ? 'bg-[#16697A] text-white' : 'text-[#6E6E73] hover:bg-[#F0EEE9] hover:text-[#1B1B1E]' }}">
                    {{ $tab['label'] }}
                </a>
            @endforeach
        </div>

        {{-- Search & Filter Toolbar --}}
        <div class="card p-4">
            <form method="GET" action="{{ route('riders.index') }}" class="flex flex-col lg:flex-row lg:items-end gap-3">
                <div class="flex-1">
                    <label class="field-label">Search Riders</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name, email, phone, vehicle, or license plate..." class="input">
                </div>

                @if(Auth::user()->isLogisticsAdmin())
                    <div class="sm:w-48">
                        <label class="field-label">Center</label>
                        <select name="center_id" title="Filter by logistics center" class="select">
                            <option value="">All centers</option>
                            @foreach($filterCenters as $c)
                                <option value="{{ $c->id }}" {{ (int) request('center_id') === $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="sm:w-48">
                    <label class="field-label">Service Area</label>
                    <select name="service_area_id" title="Filter by service area" class="select">
                        <option value="">All service areas</option>
                        @foreach($filterServiceAreas as $sa)
                            <option value="{{ $sa->id }}" {{ (int) request('service_area_id') === $sa->id ? 'selected' : '' }}>{{ $sa->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="sm:w-44">
                    <label class="field-label">Availability</label>
                    <select name="online" title="Filter by online availability" class="select">
                        <option value="">All availability</option>
                        <option value="online" {{ request('online') === 'online' ? 'selected' : '' }}>Online</option>
                        <option value="offline" {{ request('online') === 'offline' ? 'selected' : '' }}>Offline</option>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    @if($hasFilters)
                        <a href="{{ route('riders.index') }}" class="btn btn-ghost">Clear</a>
                    @endif
                </div>
            </form>
        </div>

        <div class="flex justify-end">
            <x-per-page route="riders.index"/>
        </div>

        {{-- Riders Table (No Horizontal Scroll) --}}
        <div class="card overflow-hidden">
            <table class="table">
                <thead>
                    <tr>
                        <x-th-sort field="name" label="Name"/>
                        <x-th-sort field="vehicle" label="Vehicle"/>
                        <x-th-sort field="status" label="Status"/>
                        <th>Availability</th>
                        <x-th-sort field="active_jobs" label="Active Jobs"/>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($riders as $rider)
                        <tr>
                            <td>
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full bg-[#EAF4F3] text-[#0E4A57] font-bold text-xs flex items-center justify-center shrink-0">
                                        {{ strtoupper(substr($rider->name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <a href="{{ route('riders.show', $rider) }}" class="font-semibold text-[#1B1B1E] hover:text-[#16697A]">{{ $rider->name }}</a>
                                        @if($rider->logisticsCenter)
                                            <div class="text-[11px] text-[#6E6E73]">{{ $rider->logisticsCenter->name }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-confirmed">{{ ucfirst($rider->vehicle_type) }}</span>
                            </td>
                            <td>
                                <x-status-badge :status="$rider->status" />
                            </td>
                            <td>
                                @if($rider->is_online)
                                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-600">
                                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span> Online
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-[#6E6E73]">
                                        <span class="h-2 w-2 rounded-full bg-gray-300"></span> Offline
                                    </span>
                                @endif
                            </td>
                            <td>
                                @php($atLimit = $rider->active_jobs >= \App\Models\Rider::MAX_ACTIVE_DELIVERIES)
                                <span class="inline-flex items-center px-2.5 py-1 text-xs font-bold rounded-lg {{ $atLimit ? 'bg-red-100 text-red-700' : ($rider->active_jobs > 0 ? 'bg-[#EAF4F3] text-[#0E4A57]' : 'bg-[#F0EEE9] text-[#6E6E73]') }}">
                                    {{ $rider->active_jobs }}<span class="font-medium opacity-70">/{{ \App\Models\Rider::MAX_ACTIVE_DELIVERIES }}</span>
                                </span>
                            </td>
                            <td class="text-right">
                                @php($canManage = Auth::user()->canManageRiders() && (Auth::user()->isLogisticsAdmin() || (Auth::user()->center_id && (int) Auth::user()->center_id === (int) $rider->center_id)))
                                <div class="inline-flex items-center justify-end gap-1.5">
                                    <a href="{{ route('riders.show', $rider) }}" class="btn btn-soft btn-xs">View</a>
                                    @if($canManage)
                                        @if($rider->status === 'inactive')
                                            <form action="{{ route('riders.activate', $rider) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" class="btn btn-outline btn-xs">Activate</button>
                                            </form>
                                        @else
                                            <form action="{{ route('riders.deactivate', $rider) }}" method="POST" class="inline" x-data data-confirm-message="Suspend this rider? They will lose access to rider tasks." data-confirm-reason="Reason for suspension" data-confirm-min-length="10">
                                                @csrf
                                                <input type="hidden" name="reason" value="{{ old('reason') }}">
                                                <button type="submit" class="btn btn-danger btn-xs">Suspend</button>
                                            </form>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center">
                                <p class="text-sm font-bold text-[#1B1B1E]">No riders found</p>
                                <p class="mt-1 text-xs text-[#6E6E73]">No riders match the selected filters.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if($riders->count() > 0)
                <div class="p-4 border-t border-[#E8E6E0] flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-[#6E6E73]">
                    <p>Showing <strong class="text-[#1B1B1E]">{{ $riders->firstItem() }}–{{ $riders->lastItem() }}</strong> of <strong class="text-[#1B1B1E]">{{ $riders->total() }}</strong> riders</p>
                    {{ $riders->onEachSide(2)->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
