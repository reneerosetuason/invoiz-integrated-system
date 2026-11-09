<x-app-layout>
    @php
        $canProcessParcels = \App\Support\LogisticsAccess::canProcessParcels(auth()->user());
    @endphp
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-[#1B1B1E]">Sorting Center</h1>
                <p class="mt-0.5 text-xs text-[#6E6E73]">Receive, scan, sort, and dispatch parcels at your logistics hub.</p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <a href="{{ route('sorting-center.export', array_filter(['tab' => $tab !== 'all' ? $tab : null, 'search' => $search ?: null, 'destination_center_id' => ($destinationCenterId ?: $centerId) ?: null, 'service_area_id' => $serviceAreaId ?: null])) }}"
                   class="btn btn-outline">
                    Export CSV
                </a>
                @if($canProcessParcels)
                <a href="{{ route('deliveries.scan-page') }}"
                   class="btn btn-primary">
                    Scan Parcel
                </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="space-y-4">
        {{-- Inter-Hub Flow Guide Card --}}
        <div class="card p-4 bg-gradient-to-r from-[#F0FDF4] via-[#F8FAFC] to-[#EFF6FF] border border-[#CBD5E1]" x-data="{ expanded: false }">
            <div class="flex items-center justify-between cursor-pointer" @click="expanded = !expanded">
                <div class="flex items-center gap-2.5">
                    <span class="flex items-center justify-center w-7 h-7 rounded-lg bg-[#16697A] text-white shadow-sm">
                        <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1"/></svg>
                    </span>
                    <div>
                        <h2 class="text-sm font-bold text-[#0E4A57]">Understanding the Inter-Hub Transfer Flow</h2>
                        <p class="text-xs text-[#64748B]">How parcels travel across hubs: Origin Hub → Linehaul Transit → Destination Hub → Delivery Rider</p>
                    </div>
                </div>
                <button type="button" class="text-xs font-semibold text-[#16697A] hover:underline flex items-center gap-1">
                    <span x-text="expanded ? 'Hide Guide' : 'Show 4-Stage Flow'">Show 4-Stage Flow</span>
                    <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': expanded }" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
            </div>
            <div x-show="expanded" x-collapse x-cloak class="mt-4 pt-3 border-t border-slate-200/80">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-3 text-xs">
                    <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-sm">
                        <div class="font-bold text-[#16697A] mb-1 flex items-center gap-1">
                            <span class="w-4 h-4 rounded-full bg-[#16697A] text-white inline-flex items-center justify-center text-[10px]">1</span>
                            Origin Hub Intake
                        </div>
                        <p class="text-gray-600">Pickup rider brings seller's parcel to their local hub. Staff accepts the rider handover, then <strong>Scans</strong>.</p>
                    </div>
                    <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-sm">
                        <div class="font-bold text-indigo-700 mb-1 flex items-center gap-1">
                            <span class="w-4 h-4 rounded-full bg-indigo-600 text-white inline-flex items-center justify-center text-[10px]">2</span>
                            Inter-Hub Dispatch
                        </div>
                        <p class="text-gray-600">Staff selects a <strong>Destination Hub</strong> in another area. Parcel is sorted and dispatched with a <strong>Hub Transfer</strong>.</p>
                    </div>
                    <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-sm">
                        <div class="font-bold text-amber-700 mb-1 flex items-center gap-1">
                            <span class="w-4 h-4 rounded-full bg-amber-600 text-white inline-flex items-center justify-center text-[10px]">3</span>
                            Destination Hub Intake
                        </div>
                        <p class="text-gray-600">Arrives under <strong>Incoming</strong> tab. Destination hub staff records receipt on the transfer manifest, releasing it to this hub.</p>
                    </div>
                    <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-sm">
                        <div class="font-bold text-emerald-700 mb-1 flex items-center gap-1">
                            <span class="w-4 h-4 rounded-full bg-emerald-600 text-white inline-flex items-center justify-center text-[10px]">4</span>
                            Last-Mile Delivery
                        </div>
                        <p class="text-gray-600">Sorted to local service area & dispatched to a <strong>Delivery Rider</strong> for final buyer handover and COD collection.</p>
                    </div>
                </div>
            </div>
        </div>

        @php
            $allTabs = ['all' => 'All', 'incoming' => 'Incoming', 'received' => 'Received', 'scanned' => 'Scanned', 'sorted' => 'Sorted', 'dispatched' => 'Dispatched', 'returned' => 'Returned', 'reattempt' => 'Re-attempt'];
            $tabs = array_intersect_key($allTabs, array_flip($availableTabs));
        @endphp

        {{-- Status Tabs --}}
        <div class="card p-2 flex flex-wrap items-center gap-1.5">
            @foreach($tabs as $key => $label)
                <a href="{{ route('sorting-center.index', array_filter(['tab' => $key === 'all' ? null : $key, 'search' => $search ?: null, 'destination_center_id' => ($destinationCenterId ?: $centerId) ?: null, 'service_area_id' => $serviceAreaId ?: null])) }}"
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
                    <input type="search" name="search" value="{{ $search }}" placeholder="Tracking, order ref, sender, recipient..." class="input">
                </div>
                <div class="sm:w-52">
                    <label class="field-label">Destination Hub</label>
                    <select name="destination_center_id" onchange="this.form.submit()" aria-label="Filter by destination" class="select">
                        <option value="">All Destinations</option>
                        @foreach($centers as $center)
                            <option value="{{ $center->id }}" @selected(($destinationCenterId ?: $centerId) === $center->id)>{{ $center->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:w-48">
                    <label class="field-label">Service Area</label>
                    <select name="service_area_id" onchange="this.form.submit()" aria-label="Filter by service area" class="select">
                        <option value="">All areas</option>
                        @foreach(($filterServiceAreas ?? $serviceAreas) as $area)
                            <option value="{{ $area->id }}" @selected($serviceAreaId === $area->id)>{{ $area->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="btn btn-primary">Search</button>
                    @if(($destinationCenterId ?: $centerId) || $serviceAreaId || $search)
                        <a href="{{ route('sorting-center.index', array_filter(['tab' => $tab !== 'all' ? $tab : null])) }}" class="btn btn-ghost">Reset</a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Parcel table --}}
        <div class="card overflow-hidden">
            <div class="overflow-x-auto min-h-[340px]">
                <table class="table w-full">
                    <thead>
                        <tr>
                            <th class="min-w-[170px]">Tracking</th>
                            <th class="min-w-[160px]">Route</th>
                            <th class="min-w-[110px]">Parcel</th>
                            <th class="min-w-[130px]">Delivery</th>
                            <th class="min-w-[150px]">Hub / Area</th>
                            <th class="min-w-[130px]">Rider</th>
                            <th class="text-right min-w-[190px]">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($deliveries as $delivery)
                            @php
                                $inboundEntry = $delivery->incomingTransferParcel;
                                $inboundTransfer = $inboundEntry?->transfer;
                                $routesToOtherHub = $delivery->destination_center_id
                                    && (int) $delivery->destination_center_id !== (int) $delivery->center_id;
                                $canOpenDelivery = \App\Support\LogisticsAccess::canAccessDelivery(auth()->user(), $delivery);
                                $handoffVerified = \App\Services\FulfillmentWorkflow::hasPickupHandoff($delivery);
                                $receiptUnverified = $delivery->order_id && ! $handoffVerified
                                    && in_array($delivery->parcel_status, ['received', 'scanned', 'sorted', 'dispatched'], true);
                                $custodyAccepted = $inboundEntry && $inboundEntry->receiving_status === 'received';
                                $workflow = \App\Support\ParcelWorkflow::describe($delivery, $delivery->latestHubTransferParcel, auth()->user());
                            @endphp
                            <tr class="hover:bg-[#FCFBF9] transition-colors">
                                <td class="align-middle">
                                    <div class="min-w-0">
                                        @if($canOpenDelivery)
                                            <a href="{{ route('deliveries.show', $delivery) }}" class="block truncate font-bold text-[#16697A] hover:underline" title="{{ $delivery->tracking_number }}">{{ $delivery->tracking_number }}</a>
                                        @else
                                            <span class="block truncate font-bold text-[#1B1B1E]" title="{{ $delivery->tracking_number }}">{{ $delivery->tracking_number }}</span>
                                        @endif
                                        <div class="flex flex-wrap gap-1 mt-1">
                                            @if($hasReturnedAt && $delivery->returned_at)
                                                <span class="badge badge-cancelled">Returned</span>
                                            @endif
                                            @if($hasReattemptRequestedAt && $delivery->reattempt_requested_at)
                                                <span class="badge badge-pending">Re-attempt</span>
                                            @endif
                                            @if(in_array($delivery->parcel_status, ['scanned', 'sorted'], true) && (!$delivery->destination_center_id || !$delivery->service_area_id))
                                                <span class="badge badge-pending" title="Select a destination Logistics Hub and service area before dispatch.">Needs destination</span>
                                            @endif
                                            @if($tab === 'incoming' && $delivery->parcel_status === 'pending_arrival')
                                                <span class="badge badge-confirmed" title="Collected from the seller and handed off by the pickup rider; hub staff must confirm physical arrival.">Handed off · awaiting receipt</span>
                                            @endif
                                            @if($receiptUnverified)
                                                <span class="badge badge-cancelled" title="No pickup or hub handoff is recorded for this parcel. Its hub processing is unverified; do not transfer or dispatch it until custody is corrected.">Receipt unverified</span>
                                            @endif
                                            @if($inboundTransfer && $custodyAccepted)
                                                <span class="badge badge-delivered" title="Destination staff accepted custody of this parcel. It can be scanned and sorted here while the manifest finishes its other parcels.">Custody accepted · {{ $inboundTransfer->reference }}</span>
                                            @elseif($inboundTransfer)
                                                <span class="badge badge-ready_for_delivery" title="Reserved to a fleet hub transfer. Continue receiving and custody actions from its manifest.">Hub transfer · {{ $inboundTransfer->reference }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="align-middle">
                                    <div class="text-xs leading-snug" title="{{ $delivery->sender_name }} &rarr; {{ $delivery->recipient_name }}">
                                        <div class="font-medium text-[#1B1B1E] truncate max-w-[190px]">{{ $delivery->sender_name ?: '—' }}</div>
                                        <div class="text-[11px] text-[#6E6E73] flex items-center gap-1 truncate max-w-[190px] mt-0.5">
                                            <span aria-hidden="true" class="text-[#16697A] font-bold">&rarr;</span>
                                            <span class="text-[#1B1B1E] truncate">{{ $delivery->recipient_name ?: '—' }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="align-middle">
                                    <span class="badge badge-inactive" title="Backend parcel state: {{ $delivery->parcel_status }}">{{ $workflow['label'] }}</span>
                                </td>
                                <td class="align-middle">
                                    <x-status-badge :status="$delivery->status" />
                                </td>
                                <td class="align-middle">
                                    <div class="text-xs leading-snug">
                                        <span class="block font-medium text-[#1B1B1E] truncate max-w-[180px]">{{ $delivery->logisticsCenter?->name ?? '—' }}</span>
                                        <span class="block text-[11px] text-[#6E6E73] truncate max-w-[180px] mt-0.5">{{ $delivery->serviceArea?->name ?? '—' }}</span>
                                        @if($routesToOtherHub)
                                            <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-[#16697A] truncate max-w-[180px] mt-0.5" title="Routed to another Logistics Hub">
                                                <span aria-hidden="true">&rarr;</span>
                                                to {{ $delivery->destinationCenter?->name ?? 'destination hub' }}
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="align-middle">
                                    @if($delivery->rider)
                                        <div class="flex items-center gap-1.5 min-w-0" title="{{ $delivery->rider->name }}">
                                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-[#E8F1F2] text-[#16697A] text-[10px] font-bold shrink-0">
                                                {{ strtoupper(substr($delivery->rider->name, 0, 1)) }}
                                            </span>
                                            <span class="text-xs font-semibold text-[#1B1B1E] truncate max-w-[140px]">{{ $delivery->rider->name }}</span>
                                        </div>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-medium bg-[#F0EEE9] text-[#6E6E73] border border-[#E8E6E0]" title="No delivery rider assigned">
                                            <svg class="w-3 h-3 text-[#8E8E93] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                            </svg>
                                            Unassigned
                                        </span>
                                    @endif
                                </td>
                                <td class="text-right align-middle">
                                    <div class="inline-flex items-center justify-end gap-1.5 flex-nowrap">
                                        @if($canOpenDelivery)
                                            <a href="{{ route('deliveries.show', $delivery) }}" class="btn btn-soft btn-xs whitespace-nowrap">Open</a>
                                        @endif
                                        @if($inboundEntry)
                                            @if($tab !== 'incoming')
                                                <a href="{{ route('hub-transfers.show', $inboundTransfer) }}" class="btn btn-soft btn-xs whitespace-nowrap" title="Open the active fleet transfer manifest.">Open transfer</a>
                                            @elseif($inboundEntry->receiving_status === 'pending')
                                                @if($canProcessParcels && in_array($inboundTransfer?->status, ['arrived', 'receiving', 'exception'], true))
                                                    <a href="{{ route('hub-transfers.show', $inboundTransfer) }}" class="btn btn-primary btn-xs whitespace-nowrap" title="Confirm physical receipt of this parcel on its transfer manifest before processing it here.">Verify receipt</a>
                                                @else
                                                    <a href="{{ route('hub-transfers.show', $inboundTransfer) }}" class="btn btn-outline btn-xs whitespace-nowrap">View transfer</a>
                                                @endif
                                            @elseif($inboundEntry->receiving_status === 'missing')
                                                <a href="{{ route('hub-transfers.show', $inboundTransfer) }}" class="btn btn-danger btn-xs whitespace-nowrap" title="This parcel was reported missing on arrival. Review the transfer manifest.">Reported missing</a>
                                            @else
                                                @if($canProcessParcels)
                                                <a href="{{ route('hub-transfers.show', $inboundTransfer) }}" class="btn btn-ghost btn-xs whitespace-nowrap" title="Custody is accepted. Continue scanning and sorting here; finalize the manifest once every parcel is accounted for.">Manifest</a>
                                                @else
                                                <a href="{{ route('hub-transfers.show', $inboundTransfer) }}" class="btn btn-ghost btn-xs whitespace-nowrap">View transfer</a>
                                                @endif
                                            @endif
                                        @endif
                                        @php
                                            $returnDispatched = $delivery->statusLogs->contains('status', 'return_dispatched_to_seller');
                                            $returnReceived = $delivery->statusLogs->contains('status', 'return_received_by_seller');
                                        @endphp
                                        @if($delivery->returned_at && in_array($delivery->status, ['delivery_failed', 'damaged'], true))
                                            @if($returnReceived)
                                                <span class="badge badge-delivered">Seller received</span>
                                            @elseif($returnDispatched)
                                                <span class="badge badge-processing">With seller</span>
                                            @else
                                                @if($canProcessParcels)
                                                <form method="POST" action="{{ route('deliveries.return-to-seller', $delivery) }}" class="inline-flex items-center gap-1">
                                                    @csrf
                                                    <input type="text" name="reference" maxlength="150" placeholder="Handoff ref" class="input !py-1 !px-2 !text-xs !w-24">
                                                    <button type="submit" class="btn btn-primary btn-xs whitespace-nowrap">Send to seller</button>
                                                </form>
                                                @else
                                                <span class="badge badge-processing">Awaiting return dispatch</span>
                                                @endif
                                            @endif
                                        @endif
                                        @if($delivery->parcel_status === 'pending_arrival')
                                            @if($delivery->order_id && ! $handoffVerified)
                                                <span class="badge badge-pending" title="The pickup rider has not completed the seller pickup and hub handoff yet.">Awaiting pickup</span>
                                            @elseif($handoffVerified && $delivery->center_id)
                                                {{-- First-mile handover: the pickup rider already handed this parcel to a specific hub, so staff only accept that handover. --}}
                                                @if($canProcessParcels)
                                                <form method="POST" action="{{ route('deliveries.receive', $delivery) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-primary btn-xs whitespace-nowrap" title="Accept the parcel from the pickup rider at {{ $delivery->logisticsCenter?->name ?? 'this hub' }} and close first-mile custody.">Accept handover</button>
                                                </form>
                                                @else
                                                <span class="badge badge-pending">Awaiting hub receipt</span>
                                                @endif
                                            @elseif($centers->isNotEmpty() && $canProcessParcels)
                                                <form method="POST" action="{{ route('deliveries.receive', $delivery) }}" class="inline-flex items-center gap-1">
                                                    @csrf
                                                    <select name="center_id" required aria-label="Receive at center" class="select !py-1 !px-2 !text-xs !w-28">
                                                        <option value="">Hub</option>
                                                        @foreach($centers as $center)
                                                             <option value="{{ $center->id }}" @selected((int) $delivery->center_id === (int) $center->id || (!$delivery->center_id && $loop->first))>{{ $center->name }}</option>
                                                        @endforeach
                                                    </select>
                                                    <button type="submit" class="btn btn-primary btn-xs whitespace-nowrap">Receive</button>
                                                </form>
                                            @endif
                                        @endif
                                        @if($canProcessParcels && (! $inboundTransfer || $custodyAccepted) && $delivery->parcel_status === 'received' && ! $receiptUnverified)
                                            <a href="{{ route('deliveries.scan-page', ['expect' => $delivery->tracking_number]) }}"
                                               class="btn btn-primary btn-xs whitespace-nowrap"
                                               title="Open the scanner, then scan or enter this tracking number and confirm.">Scan Parcel</a>
                                        @endif
                                        @if($canProcessParcels && (! $inboundTransfer || $custodyAccepted) && $delivery->parcel_status === 'scanned' && ! $receiptUnverified)
                                            <div class="relative inline-block text-left" data-sort-dropdown>
                                                <button type="button" data-sort-trigger aria-haspopup="true" aria-expanded="false"
                                                        class="btn btn-primary btn-xs cursor-pointer inline-flex items-center gap-1 select-none whitespace-nowrap"
                                                        title="Sort parcel to destination hub and service area">
                                                    <span>Sort</span>
                                                    <svg data-sort-chevron class="w-3 h-3 text-white/80 transition-transform duration-150" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                                </button>
                                                <form method="POST" action="{{ route('deliveries.sort', $delivery) }}"
                                                      data-sort-panel style="display:none"
                                                      class="w-64 card p-3.5 shadow-xl border border-[#CBD5E1] flex flex-col gap-2.5 bg-white text-left">
                                                    @csrf
                                                    @php
                                                        // Suggest the hub the buyer's address resolves to. It is only a
                                                        // default: staff may route the parcel through any intermediate hub.
                                                        $suggested = $delivery->suggestedDestination($serviceAreas);
                                                        $destSelected = (int) ($delivery->destination_center_id ?: 0);
                                                        if ($destSelected === 0 || $destSelected === (int) $delivery->center_id) {
                                                            $destSelected = (int) ($suggested['center_id'] ?? 0);
                                                        }
                                                        $areaSelected = (int) ($delivery->service_area_id ?: 0);
                                                        $areaFitsDestination = $serviceAreas->contains(fn ($area) => (int) $area->id === $areaSelected
                                                            && (int) $area->logistics_center_id === $destSelected);
                                                        if ($areaSelected === 0 || ! $areaFitsDestination) {
                                                            $areaSelected = (int) ($suggested['service_area_id'] ?? 0);
                                                        }
                                                    @endphp
                                                    <label class="field-label text-xs">Destination Hub
                                                        <select name="destination_center_id" id="sort-center-{{ $delivery->id }}" data-sort-center data-area-target="sort-area-{{ $delivery->id }}" required class="select mt-1 text-xs">
                                                            <option value="">Select Logistics Hub</option>
                                                            @foreach($centers as $center)
                                                                <option value="{{ $center->id }}" @selected($destSelected === (int) $center->id)>{{ $center->name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </label>
                                                    <label class="field-label text-xs">Service Area
                                                        <select name="service_area_id" id="sort-area-{{ $delivery->id }}" data-sort-area required class="select mt-1 text-xs">
                                                            <option value="">Select area</option>
                                                            @foreach($serviceAreas as $area)
                                                                <option value="{{ $area->id }}" data-center-id="{{ $area->logistics_center_id }}" @selected($areaSelected === (int) $area->id)>{{ $area->name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </label>
                                                    <button type="submit" class="btn btn-primary btn-xs mt-1 w-full justify-center whitespace-nowrap">Confirm sort</button>
                                                </form>
                                            </div>
                                        @endif
                                        @if((! $inboundTransfer || $custodyAccepted) && $delivery->parcel_status === 'sorted')
                                            @if($receiptUnverified)
                                                <span class="text-[11px] font-semibold text-rose-600" title="Record the seller pickup and hub handoff before moving this parcel.">Resolve custody first</span>
                                            @elseif($routesToOtherHub)
                                                @if($canProcessParcels)
                                                <a href="{{ route('hub-transfers.create', array_filter(['origin_center_id' => $delivery->center_id, 'parcel' => $delivery->id])) }}"
                                                   class="btn btn-outline btn-xs whitespace-nowrap"
                                                   title="Routed to {{ $delivery->destinationCenter?->name ?? 'another Logistics Hub' }}. This parcel is preselected on the transfer manifest.">Create hub transfer</a>
                                                @else
                                                <span class="badge badge-pending" title="Routed to {{ $delivery->destinationCenter?->name ?? 'another Logistics Hub' }}.">Transfer pending</span>
                                                @endif
                                            @else
                                                @if($canProcessParcels)
                                                <form method="POST" action="{{ route('deliveries.dispatch', $delivery) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-primary btn-xs whitespace-nowrap" title="Dispatch a same-hub parcel for last-mile delivery.">Dispatch</button>
                                                </form>
                                                @else
                                                <span class="badge badge-processing">Ready for dispatch</span>
                                                @endif
                                            @endif
                                        @endif
                                        @if($delivery->parcel_status === 'dispatched' && !$routesToOtherHub && !$delivery->rider_id)
                                            <a href="{{ route('deliveries.show', $delivery) }}" class="btn btn-primary btn-xs whitespace-nowrap" title="Parcel is physically dispatched at hub. Assign delivery rider in Deliveries.">Assign Rider &rarr;</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-12 text-center">
                                    @if($destinationCenterId ?: $centerId)
                                        <p class="text-sm font-bold text-[#1B1B1E]">No parcels found for this destination</p>
                                        <p class="mt-1 text-xs text-[#6E6E73]">Try selecting another destination or switch to All Destinations.</p>
                                    @else
                                        <p class="text-sm font-bold text-[#1B1B1E]">No parcels here</p>
                                        <p class="mt-1 text-xs text-[#6E6E73]">Try another tab or clear the search.</p>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @include('admin.partials.pager', ['paginator' => $deliveries])
        </div>
    </div>

    <script src="{{ asset('assets/logistics/js/sorting-center/index.js') }}"></script>
</x-app-layout>
