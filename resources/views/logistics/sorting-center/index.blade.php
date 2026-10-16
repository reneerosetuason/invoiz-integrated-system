<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <h2 class="text-2xl font-bold text-gray-900 leading-tight">Sorting Center</h2>
                <p class="mt-1 text-sm text-gray-500">Receive, scan, sort, and dispatch parcels — assignment happens on the delivery record</p>
            </div>
            <a href="{{ route('deliveries.scan-page') }}"
               class="inline-flex h-10 shrink-0 items-center justify-center rounded-lg bg-teal px-4 text-sm font-semibold text-white transition-colors hover:bg-teal-dark">
                Scan Parcel
            </a>
        </div>
    </x-slot>

    <div class="space-y-3">
        <div>
            {{-- Tabs + search + filters --}}
            <div class="space-y-3">
                @php
                    $allTabs = ['all' => 'All', 'incoming' => 'Incoming', 'received' => 'Received', 'scanned' => 'Scanned', 'sorted' => 'Sorted', 'dispatched' => 'Dispatched', 'returned' => 'Returned', 'reattempt' => 'Re-attempt'];
                    $tabs = array_intersect_key($allTabs, array_flip($availableTabs));
                @endphp
                <nav class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0" aria-label="Sorting center status filters">
                    <div class="flex w-max min-w-full items-center gap-2 pb-1">
                        @foreach($tabs as $key => $label)
                            <a href="{{ route('sorting-center.index', array_filter(['tab' => $key === 'all' ? null : $key, 'search' => $search ?: null, 'center_id' => $centerId ?: null, 'service_area_id' => $serviceAreaId ?: null])) }}"
                               class="inline-flex h-9 items-center justify-center whitespace-nowrap rounded-lg px-3 text-[13px] font-semibold transition-colors {{ $tab === $key ? 'bg-teal text-white shadow-sm' : 'border border-gray-200 bg-white text-gray-600 hover:border-teal hover:text-teal-dark' }}">
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>
                </nav>

                <form method="GET" action="{{ route('sorting-center.index') }}" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    @if($tab !== 'all')
                        <input type="hidden" name="tab" value="{{ $tab }}">
                    @endif
                    <input type="search" name="search" value="{{ $search }}" placeholder="Tracking, sender, recipient..."
                           class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm text-gray-700 focus:border-teal focus:outline-none">
                    @if(!$staffCenterId)
                        <select name="center_id" onchange="this.form.submit()" aria-label="Filter by center"
                                class="h-10 w-full shrink-0 rounded-lg border border-gray-200 bg-white px-3 text-sm text-gray-700 focus:border-teal focus:outline-none sm:w-44">
                            <option value="">All centers</option>
                            @foreach($centers as $center)
                                <option value="{{ $center->id }}" @selected($centerId === $center->id)>{{ $center->name }}</option>
                            @endforeach
                        </select>
                    @endif
                    <select name="service_area_id" onchange="this.form.submit()" aria-label="Filter by service area"
                            class="h-10 w-full shrink-0 rounded-lg border border-gray-200 bg-white px-3 text-sm text-gray-700 focus:border-teal focus:outline-none sm:w-44">
                        <option value="">All areas</option>
                        @foreach($serviceAreas as $area)
                            <option value="{{ $area->id }}" @selected($serviceAreaId === $area->id)>{{ $area->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="inline-flex h-10 w-full shrink-0 items-center justify-center rounded-lg bg-teal px-4 text-sm font-semibold text-white transition-colors hover:bg-teal-dark sm:w-auto">Search</button>
                </form>
            </div>

            {{-- Parcel table --}}
            <div class="mt-3 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-[1230px] divide-y divide-gray-100 text-sm" style="width: 100%; table-layout: fixed;">
                        <colgroup>
                            <col style="width: 16%;">
                            <col style="width: 17%;">
                            <col style="width: 13%;">
                            <col style="width: 14%;">
                            <col style="width: 15%;">
                            <col style="width: 10%;">
                            <col style="width: 15%;">
                        </colgroup>
                        <thead class="bg-gray-50/50">
                            <tr>
                                <th class="px-4 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Tracking</th>
                                <th class="px-4 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Route</th>
                                <th class="px-4 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Parcel</th>
                                <th class="px-4 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Delivery</th>
                                <th class="px-4 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Center / Area</th>
                                <th class="px-4 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Rider</th>
                                <th class="px-4 py-3.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($deliveries as $delivery)
                                <tr class="hover:bg-teal-light/40 transition">
                                    <td class="px-4 py-4 align-middle">
                                        <div class="min-w-0">
                                            <span class="block truncate whitespace-nowrap text-sm font-bold text-gray-900" title="{{ $delivery->tracking_number }}">{{ $delivery->tracking_number }}</span>
                                            @if($hasReturnedAt && $delivery->returned_at)
                                                <span class="mt-1 inline-flex items-center whitespace-nowrap rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-red-700 ring-1 ring-inset ring-red-100">Returned</span>
                                            @endif
                                            @if($hasReattemptRequestedAt && $delivery->reattempt_requested_at)
                                                <span class="mt-1 inline-flex items-center whitespace-nowrap rounded-full bg-orange-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-orange-700 ring-1 ring-inset ring-orange-100">Re-attempt</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 align-middle text-sm leading-5 text-gray-500">
                                        <div class="break-words" title="{{ $delivery->sender_name }} -> {{ $delivery->recipient_name }}">
                                            {{ $delivery->sender_name }} <span aria-hidden="true">-&gt;</span> {{ $delivery->recipient_name }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 align-middle whitespace-nowrap">
                                        <span class="inline-flex items-center whitespace-nowrap rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-gray-700 ring-1 ring-inset ring-gray-200">{{ str_replace('_', ' ', $delivery->parcel_status) }}</span>
                                    </td>
                                    <td class="px-4 py-4 align-middle whitespace-nowrap">
                                        <span class="inline-flex items-center whitespace-nowrap rounded-full bg-teal-light/60 px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-teal-dark ring-1 ring-inset ring-teal/10">{{ str_replace('_', ' ', $delivery->status) }}</span>
                                    </td>
                                    <td class="px-4 py-4 align-middle">
                                        <div class="min-w-0 text-sm leading-5 text-gray-500" title="{{ ($delivery->logisticsCenter?->name ?? '-') . ' / ' . ($delivery->serviceArea?->name ?? '-') }}">
                                            <span class="block truncate">{{ $delivery->logisticsCenter?->name ?? '-' }}</span>
                                            <span class="block truncate text-xs text-gray-400">{{ $delivery->serviceArea?->name ?? '-' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 align-middle text-sm text-gray-500">
                                        <div class="truncate whitespace-nowrap" title="{{ $delivery->rider?->name ?? '-' }}">{{ $delivery->rider?->name ?? '-' }}</div>
                                    </td>
                                    <td class="px-4 py-4 align-middle whitespace-nowrap">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('deliveries.show', $delivery) }}"
                                               class="inline-flex h-8 items-center justify-center rounded-lg border border-teal/30 px-3 text-xs font-semibold text-teal transition hover:border-teal hover:text-teal-dark">Open</a>
                                            @if($delivery->parcel_status === 'pending_arrival')
                                                @if($centers->isNotEmpty())
                                                    <form method="POST" action="{{ route('deliveries.receive', $delivery) }}" class="inline-flex items-center gap-1">
                                                        @csrf
                                                        <select name="center_id" required aria-label="Receive at center" class="h-8 max-w-36 rounded-lg border border-gray-200 px-1.5 text-xs">
                                                            <option value="">Center</option>
                                                            @foreach($centers as $center)
                                                                <option value="{{ $center->id }}" @selected((int) $delivery->center_id === (int) $center->id || (!$delivery->center_id && $loop->first))>{{ $center->name }}</option>
                                                            @endforeach
                                                        </select>
                                                        <button type="submit" class="inline-flex h-8 items-center justify-center rounded-lg bg-teal px-3 text-xs font-semibold text-white transition hover:bg-teal-dark">Receive</button>
                                                    </form>
                                                @endif
                                            @endif
                                            @if($delivery->parcel_status === 'scanned')
                                                <details class="relative inline">
                                                    <summary class="inline-flex h-8 cursor-pointer list-none items-center justify-center rounded-lg bg-purple-600 px-3 text-xs font-semibold text-white transition hover:bg-purple-700">Sort</summary>
                                                    <form method="POST" action="{{ route('deliveries.sort', $delivery) }}"
                                                          class="absolute right-0 z-10 mt-1 w-56 bg-white rounded-xl shadow-lg ring-1 ring-gray-200 p-3 flex flex-col gap-2">
                                                        @csrf
                                                        <label class="text-[11px] font-semibold text-gray-500">Destination center
                                                            <select name="destination_center_id" id="sort-center-{{ $delivery->id }}" data-sort-center data-area-target="sort-area-{{ $delivery->id }}" required
                                                                    class="mt-1 w-full text-[13px] rounded-lg border border-gray-200 px-2 py-1.5">
                                                                <option value="">Select center</option>
                                                                @foreach($centers as $center)
                                                                    <option value="{{ $center->id }}" @selected((int) $delivery->destination_center_id === (int) $center->id)>{{ $center->name }}</option>
                                                                @endforeach
                                                            </select>
                                                        </label>
                                                        <label class="text-[11px] font-semibold text-gray-500">Service area
                                                            <select name="service_area_id" id="sort-area-{{ $delivery->id }}" data-sort-area required
                                                                    class="mt-1 w-full text-[13px] rounded-lg border border-gray-200 px-2 py-1.5">
                                                                <option value="">Select area</option>
                                                                @foreach($serviceAreas as $area)
                                                                    <option value="{{ $area->id }}" data-center-id="{{ $area->logistics_center_id }}" @selected((int) $delivery->service_area_id === (int) $area->id)>{{ $area->name }}</option>
                                                                @endforeach
                                                            </select>
                                                        </label>
                                                        <button type="submit" class="text-[12px] font-bold text-white bg-purple-600 hover:bg-purple-700 rounded-lg px-2.5 py-1.5">Confirm sort</button>
                                                    </form>
                                                </details>
                                            @endif
                                            @if($delivery->parcel_status === 'sorted')
                                                <form method="POST" action="{{ route('deliveries.dispatch', $delivery) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="inline-flex h-8 items-center justify-center rounded-lg bg-gray-700 px-3 text-xs font-semibold text-white transition hover:bg-gray-900">Dispatch</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-16 text-center">
                                        <p class="text-sm font-semibold text-gray-900">No parcels here</p>
                                        <p class="mt-1 text-sm text-gray-500">Try another tab or clear the search.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @include('admin.partials.pager', ['paginator' => $deliveries])
        </div>
    </div>

    <script>
        document.querySelectorAll('[data-sort-center]').forEach((centerSelect) => {
            const areaSelect = document.getElementById(centerSelect.dataset.areaTarget);
            if (!areaSelect) return;

            const filterAreas = () => {
                Array.from(areaSelect.options).forEach((option) => {
                    if (!option.value) return;
                    option.hidden = option.dataset.centerId !== centerSelect.value;
                    if (option.hidden && option.selected) areaSelect.value = '';
                });
            };

            centerSelect.addEventListener('change', filterAreas);
            filterAreas();
        });
    </script>
</x-app-layout>
