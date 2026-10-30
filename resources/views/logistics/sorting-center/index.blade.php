<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-[#1B1B1E]">Sorting Center</h1>
                <p class="mt-0.5 text-xs text-[#6E6E73]">Process parcels at your center: Receive, Scan, Sort, and Dispatch.</p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <a href="{{ route('sorting-center.export', array_filter(['tab' => $tab !== 'all' ? $tab : null, 'search' => $search ?: null, 'center_id' => $centerId ?: null, 'service_area_id' => $serviceAreaId ?: null])) }}"
                   class="btn btn-outline">
                    Export CSV
                </a>
                <a href="{{ route('deliveries.scan-page') }}"
                   class="btn btn-primary">
                    Scan Parcel
                </a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-4">
        @php
            $allTabs = ['all' => 'All', 'incoming' => 'Incoming', 'received' => 'Received', 'scanned' => 'Scanned', 'sorted' => 'Sorted', 'dispatched' => 'Dispatched', 'returned' => 'Returned', 'reattempt' => 'Re-attempt'];
            $tabs = array_intersect_key($allTabs, array_flip($availableTabs));
        @endphp

        {{-- Status Tabs --}}
        <div class="card p-2 flex flex-wrap items-center gap-1.5">
            @foreach($tabs as $key => $label)
                <a href="{{ route('sorting-center.index', array_filter(['tab' => $key === 'all' ? null : $key, 'search' => $search ?: null, 'center_id' => $centerId ?: null, 'service_area_id' => $serviceAreaId ?: null])) }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ $tab === $key ? 'bg-[#16697A] text-white' : 'text-[#6E6E73] hover:bg-[#F0EEE9] hover:text-[#1B1B1E]' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        {{-- Filter Bar --}}
        <div class="card p-4">
            <form method="GET" action="{{ route('sorting-center.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                @if($tab !== 'all')
                    <input type="hidden" name="tab" value="{{ $tab }}">
                @endif
                <div class="flex-1">
                    <label class="field-label">Search Parcels</label>
                    <input type="search" name="search" value="{{ $search }}" placeholder="Tracking, sender, recipient..." class="input">
                </div>
                @if(!$staffCenterId)
                    <div class="sm:w-52">
                        <label class="field-label">Center</label>
                        <select name="center_id" onchange="this.form.submit()" aria-label="Filter by center" class="select">
                            <option value="">All centers</option>
                            @foreach($centers as $center)
                                <option value="{{ $center->id }}" @selected($centerId === $center->id)>{{ $center->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="sm:w-52">
                    <label class="field-label">Service Area</label>
                    <select name="service_area_id" onchange="this.form.submit()" aria-label="Filter by service area" class="select">
                        <option value="">All areas</option>
                        @foreach($serviceAreas as $area)
                            <option value="{{ $area->id }}" @selected($serviceAreaId === $area->id)>{{ $area->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Search</button>
            </form>
        </div>

        {{-- Parcel Table (No Horizontal Scroll) --}}
        <div class="card overflow-visible">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tracking #</th>
                        <th>Parcel Stage</th>
                        <th>Delivery Status</th>
                        <th>Rider</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($deliveries as $delivery)
                        <tr>
                            <td>
                                <a href="{{ route('deliveries.show', $delivery) }}" class="font-bold text-[#16697A] hover:underline">{{ $delivery->tracking_number }}</a>
                                <div class="flex flex-wrap gap-1 mt-1">
                                    @if($hasReturnedAt && $delivery->returned_at)
                                        <span class="badge badge-cancelled">Returned</span>
                                    @endif
                                    @if($hasReattemptRequestedAt && $delivery->reattempt_requested_at)
                                        <span class="badge badge-pending">Re-attempt</span>
                                    @endif
                                    @if(in_array($delivery->parcel_status, ['scanned', 'sorted'], true) && (!$delivery->destination_center_id || !$delivery->service_area_id))
                                        <span class="badge badge-pending" title="Select a destination center and service area before dispatch.">Needs destination</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <x-status-badge :status="$delivery->parcel_status" />
                            </td>
                            <td>
                                <x-status-badge :status="$delivery->status" />
                            </td>
                            <td class="text-xs text-[#6E6E73]">
                                {{ $delivery->rider?->name ?? 'Unassigned' }}
                            </td>
                            <td class="text-right">
                                <div class="inline-flex flex-wrap items-center justify-end gap-1.5">
                                    <a href="{{ route('deliveries.show', $delivery) }}" class="btn btn-soft btn-xs">View</a>
                                    @php
                                        $returnDispatched = $delivery->statusLogs->contains('status', 'return_dispatched_to_seller');
                                        $returnReceived = $delivery->statusLogs->contains('status', 'return_received_by_seller');
                                    @endphp
                                    @if($delivery->returned_at && in_array($delivery->status, ['delivery_failed', 'damaged'], true))
                                        @if($returnReceived)
                                            <span class="badge badge-completed">Seller received</span>
                                        @elseif($returnDispatched)
                                            <span class="badge badge-confirmed">With seller</span>
                                        @else
                                            <form method="POST" action="{{ route('deliveries.return-to-seller', $delivery) }}" class="inline-flex items-center gap-1">
                                                @csrf
                                                <input type="text" name="reference" maxlength="150" placeholder="Handoff ref" class="input !w-28 !py-1 !px-2 !text-xs">
                                                <button type="submit" class="btn btn-primary btn-xs">Send to seller</button>
                                            </form>
                                        @endif
                                    @endif
                                    @if($delivery->parcel_status === 'pending_arrival' || $delivery->isIncomingTransfer())
                                        @if($centers->isNotEmpty())
                                            <form method="POST" action="{{ route('deliveries.receive', $delivery) }}" class="inline-flex items-center gap-1">
                                                @csrf
                                                <select name="center_id" required aria-label="Receive at center" class="select !w-36 !py-1 !px-2 !text-xs">
                                                    <option value="">Center</option>
                                                    @foreach($centers as $center)
                                                        <option value="{{ $center->id }}" @selected((int) $delivery->center_id === (int) $center->id || (!$delivery->center_id && $loop->first))>{{ $center->name }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="btn btn-primary btn-xs">Receive</button>
                                            </form>
                                        @endif
                                    @endif
                                    @if($delivery->parcel_status === 'scanned')
                                        <details class="relative inline-block text-left">
                                            <summary class="btn btn-primary btn-xs list-none cursor-pointer">Sort</summary>
                                            <form method="POST" action="{{ route('deliveries.sort', $delivery) }}"
                                                  class="absolute right-0 z-20 mt-1 w-60 card p-3 shadow-lg flex flex-col gap-2">
                                                @csrf
                                                <label class="field-label">Destination Center
                                                    <select name="destination_center_id" id="sort-center-{{ $delivery->id }}" data-sort-center data-area-target="sort-area-{{ $delivery->id }}" required
                                                            class="select mt-1">
                                                        <option value="">Select center</option>
                                                        @foreach($destinationCenters as $center)
                                                            <option value="{{ $center->id }}" @selected((int) $delivery->destination_center_id === (int) $center->id)>{{ $center->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </label>
                                                <label class="field-label">Service Area
                                                    <select name="service_area_id" id="sort-area-{{ $delivery->id }}" data-sort-area required
                                                            class="select mt-1">
                                                        <option value="">Select area</option>
                                                        @foreach($destinationAreas as $area)
                                                            <option value="{{ $area->id }}" data-center-id="{{ $area->logistics_center_id }}" @selected((int) $delivery->service_area_id === (int) $area->id)>{{ $area->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </label>
                                                <button type="submit" class="btn btn-primary btn-sm">Confirm Sort</button>
                                            </form>
                                        </details>
                                    @endif
                                    @if($delivery->parcel_status === 'sorted')
                                        <form method="POST" action="{{ route('deliveries.dispatch', $delivery) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="btn btn-primary btn-xs">Dispatch</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center">
                                <p class="text-sm font-bold text-[#1B1B1E]">No parcels here</p>
                                <p class="mt-1 text-xs text-[#6E6E73]">Try another tab or clear the search.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('admin.partials.pager', ['paginator' => $deliveries])
    </div>

    <script src="{{ asset('assets/logistics/js/sorting-center/index.js') }}"></script>
</x-app-layout>
