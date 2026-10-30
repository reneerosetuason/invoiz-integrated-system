<x-app-layout>
    @include('logistics.partials.delivery-theme')
    <div class="logistics-deliveries space-y-5">
    @php
        $hasFilters = trim((string) request('search')) !== '' || request('status');
    @endphp

    <div x-data="{ restoreOpen: false, restoreUrl: '', deleteOpen: false, deleteUrl: '' }" class="space-y-4">
        {{-- Page Header --}}
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('deliveries.index') }}" class="btn btn-outline btn-sm" aria-label="Back to deliveries">
                    &larr; Back
                </a>
                <div>
                    <h1 class="text-2xl font-bold text-[#1B1B1E]">Archived Deliveries</h1>
                    <p class="text-xs text-[#6E6E73] mt-0.5">Review archived records, view details, or restore a delivery.</p>
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="card p-4">
            <form method="GET" action="{{ route('deliveries.archived') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                <input type="hidden" name="per_page" value="{{ request('per_page', 10) }}">
                <div class="sm:col-span-2">
                    <label class="field-label" for="archive-search">Search Archived Deliveries</label>
                    <input id="archive-search" type="search" name="search" value="{{ request('search') }}" placeholder="Tracking number, sender or recipient..." class="input">
                </div>
                <div>
                    <label class="field-label" for="archive-status">Delivery Status</label>
                    <select id="archive-status" name="status" class="select">
                        <option value="">All statuses</option>
                        @foreach(['waiting_for_rider','assigned','picked_up','out_for_delivery','delivered','delivery_failed','lost','damaged','returned_to_seller','cancelled'] as $s)
                            <option value="{{ $s }}" @selected(request('status') === $s)>{{ delivery_status_label($s) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    @if($hasFilters)<a href="{{ route('deliveries.archived') }}" class="btn btn-ghost">Reset</a>@endif
                </div>
            </form>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-[#6E6E73]">
            <p><strong class="text-[#1B1B1E]">{{ number_format($deliveries->total()) }}</strong> archived {{ $deliveries->total() === 1 ? 'delivery' : 'deliveries' }}{{ $hasFilters ? ' matching your filters' : '' }}</p>
            <x-per-page route="deliveries.archived"/>
        </div>

        {{-- Table (No Horizontal Scroll) --}}
        <div class="card overflow-hidden">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tracking #</th>
                        <th>Recipient</th>
                        <th>Status</th>
                        <th>Archived At</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($deliveries as $delivery)
                        @php
                            $modalPayload = [
                                'kicker' => 'Archived Delivery',
                                'title' => $delivery->tracking_number,
                                'url' => route('deliveries.show', $delivery),
                                'urlLabel' => 'Open Full Delivery Page',
                                'items' => [
                                    ['label' => 'Tracking #', 'value' => $delivery->tracking_number],
                                    ['label' => 'Status', 'value' => delivery_status_label($delivery->status)],
                                    ['label' => 'Recipient', 'value' => $delivery->recipient_name ?: '—'],
                                    ['label' => 'Sender', 'value' => $delivery->sender_name ?: '—'],
                                    ['label' => 'Archived By', 'value' => $delivery->archiver?->name ?? '—'],
                                    ['label' => 'Archived At', 'value' => $delivery->archived_at?->format('M d, Y h:i A') ?? '—'],
                                    ['label' => 'Archive Note', 'value' => $delivery->archive_note ?? '—', 'wide' => true],
                                ],
                            ];
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('deliveries.show', $delivery) }}" class="font-bold text-[#16697A] hover:underline">{{ $delivery->tracking_number }}</a>
                            </td>
                            <td class="font-medium text-[#1B1B1E]">{{ $delivery->recipient_name ?: '—' }}</td>
                            <td><x-status-badge :status="$delivery->status" /></td>
                            <td class="text-xs text-[#6E6E73]">{{ $delivery->archived_at?->format('M d, Y h:i A') ?? '—' }}</td>
                            <td class="text-right">
                                <div class="inline-flex items-center justify-end gap-1.5">
                                    <button type="button" class="btn btn-soft btn-xs" data-view-record='@json($modalPayload)'>View</button>
                                    <button type="button"
                                            data-restore-url="{{ route('deliveries.restore', $delivery) }}"
                                            @click="restoreUrl = $el.dataset.restoreUrl; restoreOpen = true"
                                            class="btn btn-outline btn-xs">Restore</button>
                                    @can('permanentlyDelete')
                                        <button type="button"
                                                data-delete-url="{{ route('deliveries.destroy', $delivery) }}"
                                                @click="deleteUrl = $el.dataset.deleteUrl; deleteOpen = true"
                                                class="btn btn-danger btn-xs">Delete</button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center">
                                <p class="text-sm font-bold text-[#1B1B1E]">No archived deliveries</p>
                                <p class="mt-1 text-xs text-[#6E6E73]">Archived deliveries are kept here for records and auditing.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if($deliveries->count() > 0)
                <div class="p-4 border-t border-[#E8E6E0] flex flex-wrap items-center justify-between gap-2 text-xs text-[#6E6E73]">
                    <p>Showing <strong class="text-[#1B1B1E]">{{ $deliveries->firstItem() }}–{{ $deliveries->lastItem() }}</strong> of <strong class="text-[#1B1B1E]">{{ $deliveries->total() }}</strong> archived deliveries</p>
                    {{ $deliveries->onEachSide(2)->links() }}
                </div>
            @endif
        </div>

        {{-- Restore Modal --}}
        <div x-show="restoreOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" x-transition.opacity>
            <div class="absolute inset-0 bg-black/40" @click="restoreOpen = false; restoreUrl = ''"></div>
            <form :action="restoreUrl" method="POST" class="relative card p-6 w-full max-w-md shadow-xl" x-transition>
                @csrf
                <h4 class="text-base font-bold text-[#1B1B1E]">Restore Delivery?</h4>
                <p class="text-xs text-[#6E6E73] mt-1">The delivery will be returned to the active deliveries list with all of its history intact.</p>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" @click="restoreOpen = false; restoreUrl = ''" class="btn btn-outline">Cancel</button>
                    <button type="submit" class="btn btn-primary">Restore Delivery</button>
                </div>
            </form>
        </div>

        {{-- Permanent Delete Modal --}}
        <div x-show="deleteOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" x-transition.opacity>
            <div class="absolute inset-0 bg-black/40" @click="deleteOpen = false; deleteUrl = ''"></div>
            <form :action="deleteUrl" method="POST" class="relative card p-6 w-full max-w-md shadow-xl" x-transition>
                @csrf
                @method('DELETE')
                <h4 class="text-base font-bold text-red-700">Permanently Delete Delivery?</h4>
                <p class="text-xs text-[#6E6E73] mt-1">
                    This will <strong>permanently destroy</strong> the delivery record, its timeline, and all related data.
                </p>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" @click="deleteOpen = false; deleteUrl = ''" class="btn btn-outline">Keep Record</button>
                    <button type="submit" class="btn btn-danger">Delete Permanently</button>
                </div>
            </form>
        </div>
    </div>
    </div>
</x-app-layout>
