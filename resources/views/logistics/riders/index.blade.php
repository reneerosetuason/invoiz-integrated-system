<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Riders</h1>
                <p class="mt-1 text-sm text-gray-500">Operational rider directory (read-only)</p>
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

    {{-- Status Filter Tabs --}}
    <div class="mb-4 flex flex-wrap gap-2">
        @foreach($tabs as $tab)
            <a href="{{ $tabUrl($tab['status']) }}"
               class="px-4 py-2 rounded-xl text-sm font-semibold transition {{ ($currentStatus === $tab['status']) || (!$currentStatus && !$tab['status']) ? 'bg-teal text-white shadow-sm' : 'bg-white border border-gray-200 text-gray-600 hover:text-teal hover:border-teal' }}">
                {{ $tab['label'] }}
            </a>
        @endforeach
    </div>

    {{-- Search toolbar --}}
    <div class="flex flex-col lg:flex-row lg:items-center gap-3 mb-6">
        <form method="GET" action="{{ route('riders.index') }}" class="flex flex-col sm:flex-row gap-3 flex-1">
            <div class="relative flex-1 min-w-[220px]">
                <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </span>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name, email, phone, vehicle, or license plate..."
                       class="w-full bg-white border border-gray-200 rounded-xl pl-10 pr-4 py-2 text-sm focus:border-teal focus:ring-teal shadow-sm placeholder-gray-400">
            </div>

            @if(Auth::user()->isAdmin())
                <select name="center_id" title="Filter by logistics center"
                        class="bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm text-gray-600 focus:border-teal focus:ring-teal shadow-sm max-w-[200px]">
                    <option value="">All centers</option>
                    @foreach($filterCenters as $c)
                        <option value="{{ $c->id }}" {{ (int) request('center_id') === $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
            @endif

            <select name="service_area_id" title="Filter by service area"
                    class="bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm text-gray-600 focus:border-teal focus:ring-teal shadow-sm max-w-[200px]">
                <option value="">All service areas</option>
                @foreach($filterServiceAreas as $sa)
                    <option value="{{ $sa->id }}" {{ (int) request('service_area_id') === $sa->id ? 'selected' : '' }}>{{ $sa->name }}</option>
                @endforeach
            </select>

            <select name="online" title="Filter by online availability for new deliveries"
                    class="bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm text-gray-600 focus:border-teal focus:ring-teal shadow-sm max-w-[200px]">
                <option value="">All availability</option>
                <option value="online" {{ request('online') === 'online' ? 'selected' : '' }}>Online</option>
                <option value="offline" {{ request('online') === 'offline' ? 'selected' : '' }}>Offline</option>
            </select>

            <button type="submit" class="inline-flex items-center justify-center px-5 py-2 bg-teal hover:bg-teal-dark text-white text-sm font-semibold rounded-xl transition shadow-sm whitespace-nowrap">
                Apply
            </button>
            @if($hasFilters)
                <a href="{{ route('riders.index') }}" title="Clear all filters"
                   class="inline-flex items-center justify-center px-4 py-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-500 text-sm font-medium rounded-xl transition whitespace-nowrap">
                    Clear
                </a>
            @endif
        </form>

        <x-per-page route="riders.index"/>
    </div>

    {{-- Riders Table --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100">
                <thead class="bg-gray-50/50">
                    <tr>
                        <x-th-sort field="name" label="Name"/>
                        <x-th-sort field="email" label="Email"/>
                        <x-th-sort field="phone" label="Phone"/>
                        <x-th-sort field="vehicle" label="Vehicle"/>
                        <x-th-sort field="plate" label="License Plate"/>
                        <x-th-sort field="status" label="Status"/>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Availability</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($riders as $rider)
                        <tr class="hover:bg-teal-light/40 transition">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="h-9 w-9 rounded-full bg-teal flex items-center justify-center text-white text-sm font-bold">
                                        {{ strtoupper(substr($rider->name, 0, 1)) }}
                                    </div>
                                    <span class="text-sm font-semibold text-gray-900">{{ $rider->name }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $rider->email }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $rider->phone }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-lg bg-teal-light text-teal-dark">
                                    {{ ucfirst($rider->vehicle_type) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono">{{ $rider->license_plate }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <x-status-badge :status="$rider->status" />
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($rider->is_online)
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-green-600">
                                        <span class="h-2 w-2 rounded-full bg-green-500"></span> Online
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-400" title="Offline — not available for new deliveries">
                                        <span class="h-2 w-2 rounded-full bg-gray-300"></span> Offline
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php($canManage = Auth::user()->isAdmin() || Auth::user()->center_id == $rider->center_id)
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('riders.show', $rider) }}" class="text-sm font-semibold text-teal hover:text-teal-dark">View</a>
                                    @if($canManage)
                                        @if($rider->status === 'inactive')
                                            <form action="{{ route('riders.activate', $rider) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">Activate</button>
                                            </form>
                                        @else
                                            <form action="{{ route('riders.deactivate', $rider) }}" method="POST" x-data x-on:submit.prevent="if (confirm('Deactivate {{ $rider->name }}?')) $el.submit()">
                                                @csrf
                                                <button type="submit" class="text-sm font-semibold text-red-500 hover:text-red-600">Deactivate</button>
                                            </form>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center">
                                    <div class="h-12 w-12 rounded-full bg-teal-light flex items-center justify-center mb-3">
                                        <svg class="h-6 w-6 text-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                    </div>
                                    <p class="text-base font-semibold text-gray-700">No results found</p>
                                    @if(trim((string) request('search')) !== '')
                                        <p class="mt-1 text-sm text-gray-500">We couldn't find any riders matching
                                            <span class="font-semibold text-gray-800">"{{ request('search') }}"</span>.
                                        </p>
                                    @else
                                        <p class="mt-1 text-sm text-gray-500">No riders are currently available for the selected filters.</p>
                                    @endif
                                    @if($hasFilters)
                                        <a href="{{ route('riders.index') }}" class="mt-4 inline-flex items-center px-4 py-2 bg-white border border-gray-200 hover:border-teal hover:text-teal-dark rounded-xl text-sm font-semibold text-gray-600 transition">
                                            Clear Search &amp; Filters
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($riders->count() > 0)
            <div class="px-6 py-3 border-t border-gray-100 bg-gray-50/50 flex flex-col sm:flex-row items-center justify-between gap-2">
                <p class="text-xs text-gray-500">
                    Showing <span class="font-semibold text-gray-700">{{ $riders->firstItem() }}–{{ $riders->lastItem() }}</span>
                    of <span class="font-semibold text-gray-700">{{ $riders->total() }}</span> riders
                </p>
                <p class="text-xs text-gray-400">Page {{ $riders->currentPage() }} of {{ $riders->lastPage() }}</p>
            </div>
        @endif
    </div>

    {{-- Pagination --}}
    <div class="mt-6">
        {{ $riders->onEachSide(2)->links() }}
    </div>
</x-app-layout>
