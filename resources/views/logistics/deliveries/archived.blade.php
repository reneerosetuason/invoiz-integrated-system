<x-app-layout>
    @php
        $hasFilters = trim((string) request('search')) !== '' || request('status');
    @endphp

    <div x-data="{ restoreOpen: false, restoreUrl: '', deleteOpen: false, deleteUrl: '' }" class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-4">
                <a href="{{ route('deliveries.index') }}"
                   class="p-2 bg-white border border-gray-200 rounded-xl text-gray-500 hover:text-teal-dark hover:border-teal transition shadow-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Archived Deliveries</h1>
                    <p class="text-sm text-gray-500 mt-1">Preserved records for auditing — nothing is lost</p>
                </div>
            </div>
        </div>

        {{-- Search toolbar --}}
        <div class="flex flex-col lg:flex-row lg:items-start gap-3 mb-2">
            <form method="GET" action="{{ route('deliveries.archived') }}" class="flex flex-col sm:flex-row gap-3 flex-1">
                <div class="relative flex-1 min-w-[240px]">
                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </span>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search archived deliveries..."
                           class="w-full bg-white border border-gray-200 rounded-xl pl-10 pr-4 py-2 text-sm focus:border-teal focus:ring-teal shadow-sm placeholder-gray-400">
                </div>

                <select name="status" class="bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm text-gray-600 focus:border-teal focus:ring-teal shadow-sm">
                    <option value="">All statuses</option>
                    @foreach(['waiting_for_rider','assigned','picked_up','out_for_delivery','delivered','delivery_failed','cancelled'] as $s)
                        <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                    @endforeach
                </select>

                <button type="submit" class="inline-flex items-center justify-center px-5 py-2 bg-teal hover:bg-teal-dark text-white text-sm font-semibold rounded-xl transition shadow-sm whitespace-nowrap">
                    Apply
                </button>
                @if($hasFilters)
                    <a href="{{ route('deliveries.archived') }}"
                       class="inline-flex items-center justify-center px-4 py-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-500 text-sm font-medium rounded-xl transition whitespace-nowrap">
                        Clear
                    </a>
                @endif
            </form>

            <x-per-page route="deliveries.archived"/>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50/50">
                        <tr>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Tracking #</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Recipient</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Archived By</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Archived At</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Note</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($deliveries as $delivery)
                            <tr class="hover:bg-teal-light/40 transition">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono font-medium text-gray-900">
                                    <a href="{{ route('deliveries.show', $delivery) }}" class="hover:text-teal-dark">{{ $delivery->tracking_number }}</a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $delivery->recipient_name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap"><x-status-badge :status="$delivery->status" /></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $delivery->archiver?->name ?? '—' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $delivery->archived_at?->format('M d, Y h:i A') }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500 max-w-[220px] truncate" title="{{ $delivery->archive_note }}">{{ $delivery->archive_note ?? '—' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <button type="button"
                                                data-restore-url="{{ route('deliveries.restore', $delivery) }}"
                                                @click="restoreUrl = $el.dataset.restoreUrl; restoreOpen = true"
                                                class="text-sm font-semibold text-teal hover:text-teal-dark">Restore</button>
                                        @can('permanentlyDelete')
                                            <button type="button"
                                                    data-delete-url="{{ route('deliveries.destroy', $delivery) }}"
                                                    @click="deleteUrl = $el.dataset.deleteUrl; deleteOpen = true"
                                                    class="text-sm font-semibold text-red-500 hover:text-red-700">Delete Permanently</button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-16 text-center">
                                    <div class="flex flex-col items-center">
                                        <div class="h-12 w-12 rounded-full bg-teal-light flex items-center justify-center mb-3">
                                            <svg class="h-6 w-6 text-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                                            </svg>
                                        </div>
                                        <p class="text-base font-semibold text-gray-700">No archived deliveries</p>
                                        <p class="mt-1 text-sm text-gray-500">Archived deliveries are kept here for records and auditing.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($deliveries->count() > 0)
                <div class="px-6 py-3 border-t border-gray-100 bg-gray-50/50 flex flex-col sm:flex-row items-center justify-between gap-2">
                    <p class="text-xs text-gray-500">
                        Showing <span class="font-semibold text-gray-700">{{ $deliveries->firstItem() }}–{{ $deliveries->lastItem() }}</span>
                        of <span class="font-semibold text-gray-700">{{ $deliveries->total() }}</span> archived deliveries
                    </p>
                    <p class="text-xs text-gray-400">Page {{ $deliveries->currentPage() }} of {{ $deliveries->lastPage() }}</p>
                </div>
            @endif
        </div>

        <div class="mt-4">
            {{ $deliveries->onEachSide(2)->links() }}
        </div>

        {{-- Restore Modal --}}
        <div x-show="restoreOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" x-transition.opacity>
            <div class="absolute inset-0 bg-black/40" @click="restoreOpen = false; restoreUrl = ''"></div>
            <form :action="restoreUrl" method="POST" class="relative bg-white rounded-2xl shadow-xl w-full max-w-md" x-transition>
                @csrf
                <div class="px-6 py-5 border-b border-gray-100">
                    <h4 class="font-bold text-gray-900">Restore Delivery?</h4>
                    <p class="text-sm text-gray-500 mt-1">The delivery will be returned to the active deliveries list with all of its history intact.</p>
                </div>
                <div class="px-6 py-4 bg-gray-50 rounded-b-2xl flex justify-end gap-3">
                    <button type="button" @click="restoreOpen = false; restoreUrl = ''"
                            class="px-5 py-2.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-xl transition">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 bg-teal hover:bg-teal-dark text-white text-sm font-bold rounded-xl transition">Restore Delivery</button>
                </div>
            </form>
        </div>

        {{-- Permanent Delete Modal --}}
        <div x-show="deleteOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" x-transition.opacity>
            <div class="absolute inset-0 bg-black/40" @click="deleteOpen = false; deleteUrl = ''"></div>
            <form :action="deleteUrl" method="POST" class="relative bg-white rounded-2xl shadow-xl w-full max-w-md" x-transition>
                @csrf
                @method('DELETE')
                <div class="px-6 py-5 border-b border-gray-100">
                    <h4 class="font-bold text-red-700">Permanently Delete Delivery?</h4>
                    <p class="text-sm text-gray-600 mt-1">
                        This will <strong>permanently destroy</strong> the delivery record, its timeline, and all related data.
                        This action cannot be undone and is restricted to the Logistics Manager.
                    </p>
                </div>
                <div class="px-6 py-4 bg-gray-50 rounded-b-2xl flex justify-end gap-3">
                    <button type="button" @click="deleteOpen = false; deleteUrl = ''"
                            class="px-5 py-2.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-xl transition">Keep Record</button>
                    <button type="submit" class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-bold rounded-xl transition">Delete Permanently</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
