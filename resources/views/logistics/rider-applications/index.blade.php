<x-app-layout>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Rider Applications</h1>
                <p class="text-sm text-gray-500 mt-1">Review applications and provision rider accounts</p>
            </div>
        </div>

        {{-- Filter toolbar --}}
        <div class="flex flex-col xl:flex-row gap-3">
            <form method="GET" action="{{ route('rider-applications.index') }}" class="flex flex-col lg:flex-row gap-3 flex-1">
                <div class="relative flex-1 min-w-[240px]">
                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name, email, or phone..."
                           class="w-full pl-10 rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">
                </div>
                <select name="status" class="rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">
                    <option value="">All Statuses</option>
                    @foreach(['pending', 'approved', 'rejected'] as $s)
                        <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="bg-gray-900 hover:bg-gray-800 text-white font-semibold px-5 py-2.5 rounded-xl transition text-sm">
                    Apply
                </button>
            </form>
            @if(request('search') || request('status'))
                <a href="{{ route('rider-applications.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 hover:border-teal hover:text-teal-dark rounded-xl text-sm font-semibold text-gray-600 transition self-start lg:self-auto">
                    Clear Filters
                </a>
            @endif
        </div>

        {{-- Applications table --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50/50">
                        <tr>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Phone</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Vehicle</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Submitted</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($applications as $application)
                            <tr class="hover:bg-teal-light/40 transition">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <div class="h-9 w-9 rounded-full bg-teal flex items-center justify-center text-white text-sm font-bold">
                                            {{ strtoupper(substr($application->name, 0, 1)) }}
                                        </div>
                                        <span class="text-sm font-semibold text-gray-900">{{ $application->name }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $application->email }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $application->phone }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ ucfirst($application->vehicle_type) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($application->status === 'pending')
                                        <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200">Pending</span>
                                    @elseif($application->status === 'approved')
                                        <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200">Approved</span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-red-50 text-red-700 ring-1 ring-inset ring-red-200">Rejected</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $application->created_at?->format('M d, Y') ?? '—' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <a href="{{ route('rider-applications.show', $application) }}" class="text-sm font-semibold text-teal hover:text-teal-dark">Review</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-16 text-center">
                                    <div class="flex flex-col items-center">
                                        <p class="text-sm font-semibold text-gray-900">No applications found</p>
                                        <p class="mt-1 text-sm text-gray-500">No rider applications match the current filters.</p>
                                        @if(request('search') || request('status'))
                                            <a href="{{ route('rider-applications.index') }}" class="mt-4 inline-flex items-center px-4 py-2 bg-white border border-gray-200 hover:border-teal hover:text-teal-dark rounded-xl text-sm font-semibold text-gray-600 transition">Clear Filters</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($applications->total() > 0)
                <div class="px-6 py-3 border-t border-gray-100 bg-gray-50/50 flex flex-col sm:flex-row items-center justify-between gap-2">
                    <p class="text-xs text-gray-500">Showing {{ $applications->firstItem() }}–{{ $applications->lastItem() }} of {{ $applications->total() }} applications</p>
                    <p class="text-xs text-gray-400">Page {{ $applications->currentPage() }} of {{ $applications->lastPage() }}</p>
                </div>
            @endif
        </div>

        <div class="mt-6">{{ $applications->links() }}</div>
    </div>
</x-app-layout>
