<x-app-layout>
    <div class="space-y-6">
        <div class="lw-head flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Document Change Requests</h1>
                <p class="text-sm text-gray-500 mt-1">Review rider-submitted replacements. A replacement only becomes official after you approve it.</p>
            </div>
        </div>

        {{-- Status summary --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <a href="{{ route('document-change-requests.index', ['status' => 'pending']) }}"
               class="bg-white rounded-2xl shadow-sm border {{ $status === 'pending' ? 'border-amber-300 ring-2 ring-amber-100' : 'border-gray-100' }} p-5 hover:border-amber-300 transition">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Pending Review</p>
                <p class="mt-2 text-3xl font-bold text-amber-600">{{ (int) ($counts['pending'] ?? 0) }}</p>
            </a>
            <a href="{{ route('document-change-requests.index', ['status' => 'approved']) }}"
               class="bg-white rounded-2xl shadow-sm border {{ $status === 'approved' ? 'border-emerald-300 ring-2 ring-emerald-100' : 'border-gray-100' }} p-5 hover:border-emerald-300 transition">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Approved</p>
                <p class="mt-2 text-3xl font-bold text-emerald-600">{{ (int) ($counts['approved'] ?? 0) }}</p>
            </a>
            <a href="{{ route('document-change-requests.index', ['status' => 'rejected']) }}"
               class="bg-white rounded-2xl shadow-sm border {{ $status === 'rejected' ? 'border-red-300 ring-2 ring-red-100' : 'border-gray-100' }} p-5 hover:border-red-300 transition">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Rejected</p>
                <p class="mt-2 text-3xl font-bold text-red-600">{{ (int) ($counts['rejected'] ?? 0) }}</p>
            </a>
        </div>

        {{-- Filter toolbar --}}
        <div class="flex flex-col xl:flex-row gap-3">
            <form method="GET" action="{{ route('document-change-requests.index') }}" class="flex flex-col sm:flex-row gap-3 flex-1">
                <div class="relative flex-1 min-w-[240px]">
                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by rider name, email, or phone..."
                           class="w-full pl-10 rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">
                </div>
                <select name="status" class="rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">
                    <option value="">All Statuses</option>
                    @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="bg-gray-900 hover:bg-gray-800 text-white font-semibold px-5 py-2.5 rounded-xl transition text-sm">Apply</button>
            </form>
            @if(request('search') || $status !== '')
                <a href="{{ route('document-change-requests.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 hover:border-teal hover:text-teal-dark rounded-xl text-sm font-semibold text-gray-600 transition self-start">
                    Clear Filters
                </a>
            @endif
        </div>

        {{-- Requests table --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50/50">
                        <tr>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Rider</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Document</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Proposed Expiry</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Submitted</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Reviewed By</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($requests as $item)
                            <tr class="hover:bg-teal-light/40 transition">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <div class="h-9 w-9 rounded-full bg-teal flex items-center justify-center text-white text-sm font-bold">
                                            {{ strtoupper(substr($item->rider?->name ?? '?', 0, 1)) }}
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-gray-900">{{ $item->rider?->name ?? 'Deleted rider' }}</p>
                                            <p class="text-xs text-gray-500">{{ $item->rider?->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $item->typeLabel() }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm {{ $item->document_type === 'drivers_license' && $item->proposed_expiry?->isPast() ? 'text-red-600 font-semibold' : 'text-gray-500' }}">
                                    {{ $item->proposed_expiry?->format('M d, Y') ?? '—' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $item->created_at?->format('M d, Y g:i A') ?? '—' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($item->status === 'pending')
                                        <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200">{{ $item->statusLabel() }}</span>
                                    @elseif($item->status === 'approved')
                                        <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200">{{ $item->statusLabel() }}</span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-red-50 text-red-700 ring-1 ring-inset ring-red-200">{{ $item->statusLabel() }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    @if($item->reviewer)
                                        <span class="block font-medium text-gray-700">{{ $item->reviewer->name }}</span>
                                        <span class="text-xs text-gray-400">{{ $item->reviewed_at?->format('M d, Y g:i A') }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <a href="{{ route('document-change-requests.show', $item) }}" class="text-sm font-semibold text-teal hover:text-teal-dark">Review</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-16 text-center">
                                    <div class="flex flex-col items-center">
                                        <p class="text-sm font-semibold text-gray-900">No document change requests found</p>
                                        <p class="mt-1 text-sm text-gray-500">Rider submissions will appear here for review.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($requests->total() > 0)
                <div class="px-6 py-3 border-t border-gray-100 bg-gray-50/50 flex flex-col sm:flex-row items-center justify-between gap-2">
                    <p class="text-xs text-gray-500">Showing {{ $requests->firstItem() }}–{{ $requests->lastItem() }} of {{ $requests->total() }} requests</p>
                    <p class="text-xs text-gray-400">Page {{ $requests->currentPage() }} of {{ $requests->lastPage() }}</p>
                </div>
            @endif
        </div>

        <div class="mt-6">{{ $requests->links() }}</div>
    </div>
</x-app-layout>
