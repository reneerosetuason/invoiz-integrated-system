<x-app-layout>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Logistics Centers</h1>
                <p class="text-sm text-gray-500 mt-1">Manage logistics hubs and service facilities</p>
            </div>
            <a href="{{ route('centers.create') }}"
               class="inline-flex items-center justify-center gap-2 bg-teal hover:bg-teal-dark text-white font-semibold px-5 py-2.5 rounded-xl transition shadow-sm">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Add Center
            </a>
        </div>

        <div class="flex flex-col xl:flex-row gap-3">
            <form method="GET" action="{{ route('centers.index') }}" class="flex flex-col lg:flex-row gap-3 flex-1">
                <div class="relative flex-1 min-w-[240px]">
                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name, city, province, or address..."
                           class="w-full pl-10 rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">
                </div>
                <select name="status" title="Filter by status"
                        class="bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm text-gray-600 focus:border-teal focus:ring-teal shadow-sm">
                    <option value="">All statuses</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
                <button type="submit" class="bg-gray-900 hover:bg-gray-800 text-white font-semibold px-5 py-2.5 rounded-xl transition text-sm">Apply</button>
            </form>
            @if(request('search') || request('status'))
                <a href="{{ route('centers.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 hover:border-teal hover:text-teal-dark rounded-xl text-sm font-semibold text-gray-600 transition self-start lg:self-auto">Clear</a>
            @endif
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50/50">
                        <tr>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Center</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">City</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Province</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Phone</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Areas</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Riders</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Deliveries</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($centers as $center)
                            <tr class="hover:bg-teal-light/40 transition">
                                <td class="px-6 py-4">
                                    <a href="{{ route('centers.show', $center) }}" class="text-sm font-semibold text-teal hover:text-teal-dark">{{ $center->name }}</a>
                                    <p class="text-xs text-gray-400 mt-0.5">{{ $center->address }}</p>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $center->city }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $center->province ?? '—' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $center->phone ?? '—' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $center->service_areas_count }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $center->riders_count }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $center->deliveries_count }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($center->is_active)
                                        <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200">Active</span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600 ring-1 ring-inset ring-gray-200">Inactive</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <a href="{{ route('centers.show', $center) }}" class="text-sm font-semibold text-teal hover:text-teal-dark">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-6 py-16 text-center">
                                    <div class="flex flex-col items-center">
                                        <p class="text-sm font-semibold text-gray-900">No centers found</p>
                                        <p class="mt-1 text-sm text-gray-500">No logistics centers match the current filters.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($centers->total() > 0)
                <div class="px-6 py-3 border-t border-gray-100 bg-gray-50/50 flex flex-col sm:flex-row items-center justify-between gap-2">
                    <p class="text-xs text-gray-500">Showing {{ $centers->firstItem() }}–{{ $centers->lastItem() }} of {{ $centers->total() }} centers</p>
                    <p class="text-xs text-gray-400">Page {{ $centers->currentPage() }} of {{ $centers->lastPage() }}</p>
                </div>
            @endif
        </div>

        <div class="mt-6">{{ $centers->links() }}</div>
    </div>
</x-app-layout>
