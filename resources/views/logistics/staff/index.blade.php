<x-app-layout>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Staff</h1>
                <p class="text-sm text-gray-500 mt-1">Manage logistics center staff accounts</p>
            </div>
            <a href="{{ route('staff.create') }}"
               class="inline-flex items-center justify-center gap-2 bg-teal hover:bg-teal-dark text-white font-semibold px-5 py-2.5 rounded-xl transition shadow-sm">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Add Staff
            </a>
        </div>

        {{-- Search + filter toolbar --}}
        <div class="flex flex-col xl:flex-row gap-3">
            <form method="GET" action="{{ route('staff.index') }}" class="flex flex-col lg:flex-row gap-3 flex-1">
                <div class="relative flex-1 min-w-[240px]">
                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name, email, or phone..."
                           class="w-full pl-10 rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">
                </div>
                <select name="center_id" class="rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">
                    <option value="">All Centers</option>
                    @foreach($centers as $center)
                        <option value="{{ $center->id }}" @selected((int) request('center_id') === $center->id)>{{ $center->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="bg-gray-900 hover:bg-gray-800 text-white font-semibold px-5 py-2.5 rounded-xl transition text-sm">
                    Apply
                </button>
            </form>
            @if(request('search') || request('center_id'))
                <a href="{{ route('staff.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 hover:border-teal hover:text-teal-dark rounded-xl text-sm font-semibold text-gray-600 transition self-start lg:self-auto">
                    Clear Filters
                </a>
            @endif
        </div>

        {{-- Staff table --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50/50">
                        <tr>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Phone</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Center</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($staff as $member)
                            <tr class="hover:bg-teal-light/40 transition">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <div class="h-9 w-9 rounded-full bg-teal flex items-center justify-center text-white text-sm font-bold">
                                            {{ strtoupper(substr($member->name, 0, 1)) }}
                                        </div>
                                        <span class="text-sm font-semibold text-gray-900">{{ $member->name }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $member->email }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $member->phone }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $member->logisticsCenter?->name ?? '—' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($member->status === 'active')
                                        <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200">Active</span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600 ring-1 ring-inset ring-gray-200">Inactive</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <a href="{{ route('staff.show', $member) }}" class="text-sm font-semibold text-teal hover:text-teal-dark">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-16 text-center">
                                    <div class="flex flex-col items-center">
                                        <div class="h-12 w-12 rounded-full bg-teal-light flex items-center justify-center mb-3">
                                            <svg class="h-6 w-6 text-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                        </div>
                                        <p class="text-sm font-semibold text-gray-900">No staff found</p>
                                        <p class="mt-1 text-sm text-gray-500">No staff accounts match the current filters.</p>
                                        @if(request('search') || request('center_id'))
                                            <a href="{{ route('staff.index') }}" class="mt-4 inline-flex items-center px-4 py-2 bg-white border border-gray-200 hover:border-teal hover:text-teal-dark rounded-xl text-sm font-semibold text-gray-600 transition">Clear Filters</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($staff->total() > 0)
                <div class="px-6 py-3 border-t border-gray-100 bg-gray-50/50 flex flex-col sm:flex-row items-center justify-between gap-2">
                    <p class="text-xs text-gray-500">Showing {{ $staff->firstItem() }}–{{ $staff->lastItem() }} of {{ $staff->total() }} staff</p>
                    <p class="text-xs text-gray-400">Page {{ $staff->currentPage() }} of {{ $staff->lastPage() }}</p>
                </div>
            @endif
        </div>

        <div class="mt-6">{{ $staff->links() }}</div>
    </div>
</x-app-layout>
