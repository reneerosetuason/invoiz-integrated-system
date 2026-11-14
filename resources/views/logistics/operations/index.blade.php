<x-app-layout>
    @php
        $tone = [
            'teal' => 'bg-[#E6F4F1] text-[#0E4A57] border-[#B9DED6]',
            'amber' => 'bg-amber-50 text-amber-800 border-amber-200',
            'red' => 'bg-red-50 text-red-700 border-red-200',
            'indigo' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
            'green' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'gray' => 'bg-[#F0EEE9] text-[#6E6E73] border-[#E8E6E0]',
        ];
        $cards = [
            ['label' => 'Parcels needing action', 'value' => $counts['action_parcels'], 'hint' => 'Pickup review, rider assignment, receipt, sorting, or transfer', 'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
            ['label' => 'Incoming transfers', 'value' => $counts['incoming_transfers'], 'hint' => 'Dispatched, arrived, or under receiving', 'icon' => 'M4 7h11m0 0-3-3m3 3-3 3M20 17H9m0 0 3 3m-3-3 3-3M5 12h14'],
            ['label' => 'In transit', 'value' => $counts['in_transit'], 'hint' => 'Trucks currently on the road', 'icon' => 'M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1'],
            ['label' => 'Awaiting receiving', 'value' => $counts['awaiting_receiving'], 'hint' => 'Physically arrived; custody not yet accepted', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['label' => 'Ready for sorting', 'value' => $counts['ready_sorting'], 'hint' => 'Received or scanned at this scope', 'icon' => 'M7 7h10M7 12h10M7 17h6'],
            ['label' => 'Ready for last-mile rider', 'value' => $counts['ready_rider'], 'hint' => 'Sorted for this hub, no rider yet', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2a3 3 0 00-5.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2a3 3 0 015.356-1.857M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
            ['label' => 'Damaged or missing', 'value' => $counts['exceptions'], 'hint' => 'Manifest exceptions still on record', 'icon' => 'M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z'],
        ];
        $conditionTone = [
            'good' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'damaged' => 'bg-amber-50 text-amber-800 border-amber-200',
            'missing' => 'bg-red-50 text-red-700 border-red-200',
        ];
        $filters = $filters ?? [
            'search' => '',
            'parcel_status' => '',
            'hub_id' => null,
            'date_received' => '',
            'condition' => '',
            'sort' => '',
        ];
        $hasActiveFilters = $hasActiveFilters ?? false;
        $hubOptions = $hubOptions ?? collect();
        $destinationCenters = $destinationCenters ?? collect();
        $serviceAreas = $serviceAreas ?? collect();
        $eligibleRiders = $eligibleRiders ?? collect();
        $canManageRiders = $canManageRiders ?? false;

        $parcelRowsMeta = $actionParcels->map(function ($item) {
            $d = $item['delivery'];
            return [
                'id' => (int) $d->id,
                'tracking_number' => $d->tracking_number,
                'parcel_status' => $d->parcel_status,
                'workflow_label' => $item['workflow']['label'] ?? $d->parcel_status,
                'condition' => $item['condition'] ?? 'good',
                'center_id' => $d->center_id ? (int) $d->center_id : null,
                'current_hub_name' => $d->logisticsCenter?->name ?? 'Unassigned Hub',
                'origin_center_id' => $d->origin_center_id ? (int) $d->origin_center_id : null,
                'origin_hub_name' => $d->originCenter?->name ?? null,
                'destination_center_id' => $d->destination_center_id ? (int) $d->destination_center_id : null,
                'destination_hub_name' => $d->destinationCenter?->name ?? null,
                'service_area_id' => $d->service_area_id ? (int) $d->service_area_id : null,
                'service_area_name' => $d->serviceArea?->name ?? null,
                'rider_id' => $d->rider_id ? (int) $d->rider_id : null,
                'rider_name' => $d->rider?->name ?? null,
                'next_key' => $item['workflow']['next']['key'] ?? null,
                'eligibility' => $item['eligibility'] ?? [
                    'receive' => false,
                    'sort' => false,
                    'assign_rider' => false,
                    'transfer' => false,
                ],
                'ineligible_reasons' => $item['ineligible_reasons'] ?? [
                    'receive' => null,
                    'sort' => null,
                    'assign_rider' => null,
                    'transfer' => null,
                ],
            ];
        })->values();

        $serviceAreasMeta = $serviceAreas->map(fn ($sa) => [
            'id' => (int) $sa->id,
            'name' => $sa->name,
            'logistics_center_id' => (int) $sa->logistics_center_id,
        ])->values();

        $destinationCentersMeta = $destinationCenters->map(fn ($dc) => [
            'id' => (int) $dc->id,
            'name' => $dc->name,
        ])->values();

        $ridersMeta = $eligibleRiders->map(fn ($r) => [
            'id' => (int) $r->id,
            'name' => $r->name,
            'center_id' => $r->center_id ? (int) $r->center_id : null,
            'service_area_id' => $r->service_area_id ? (int) $r->service_area_id : null,
            'service_area_name' => $r->serviceArea?->name ?? 'Any area in hub',
            'vehicle_type' => ucfirst($r->vehicle_type ?? 'Two-wheel'),
            'is_online' => (bool) $r->is_online,
            'active_deliveries' => (int) ($r->active_deliveries_count ?? 0),
            'max_deliveries' => (int) \App\Models\Rider::MAX_ACTIVE_DELIVERIES,
        ])->values();
    @endphp

    <div id="operations-workspace-root"
         class="space-y-4"
         data-parcels='@json($parcelRowsMeta)'
         x-data="operationsWorkspace({
             baseUrl: @js(route('operations.index')),
             scanLookupUrl: @js(route('deliveries.scan-lookup', [], false)),
             scanVerifyUrl: @js(route('deliveries.scan-verify', [], false)),
             bulkReceiveUrl: @js(route('operations.bulk-receive', [], false)),
             bulkSortUrl: @js(route('operations.bulk-sort', [], false)),
             bulkAssignRiderUrl: @js(route('operations.bulk-assign-rider', [], false)),
             bulkTransferUrl: @js(route('operations.bulk-transfer', [], false)),
             filters: @js($filters),
             parcels: @js($parcelRowsMeta),
             serviceAreas: @js($serviceAreasMeta),
             destinationCenters: @js($destinationCentersMeta),
             riders: @js($ridersMeta),
             centerId: @js($center?->id),
             centerName: @js($center?->name ?? $scopeLabel),
         })">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-[#1B1B1E]">Operations</h1>
                <p class="mt-0.5 text-xs text-[#6E6E73]">
                    One queue for receiving, sorting, hub transfers, and last-mile handoff.
                </p>
                <div class="mt-2 inline-flex items-center gap-2 rounded-lg border border-[#D7E9E5] bg-[#F0F9F7] px-3 py-1.5 text-xs">
                    <span class="font-semibold text-[#0E4A57]">{{ $scopeLabel }}</span>
                    @unless($canProcess)
                        <span class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide bg-[#F0EEE9] text-[#6E6E73]">Read-only monitoring</span>
                    @endunless
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if($canProcess)
                    <a href="{{ route('deliveries.scan-page') }}" @click.prevent="focusQuickScanner()" class="btn btn-primary">Scan Parcel</a>
                @endif
                <a href="{{ route('sorting-center.index') }}" class="btn btn-outline">Sorting Center</a>
                <a href="{{ route('hub-transfers.index') }}" class="btn btn-outline">Hub Transfers</a>
            </div>
        </div>

        @if(! $canProcess)
            <div class="rounded-lg border border-[#D7E9E5] bg-[#F0F9F7] p-3 text-xs text-[#0E4A57]">
                Management monitoring is read-only. Receiving, scanning, sorting, manifest loading, and receiving inspection remain with staff assigned to the hub.
            </div>
        @endif

        {{-- Real counts only; every card comes from the queried scope above. --}}
        <div id="operations-kpi-grid" class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-7">
            @foreach($cards as $card)
                <div class="card p-3.5">
                    <div class="flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#E6F4F1] text-[#16697A]">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $card['icon'] }}"/>
                            </svg>
                        </span>
                        <span class="text-[10px] font-bold uppercase tracking-wide text-[#6E6E73]">{{ $card['label'] }}</span>
                    </div>
                    <div class="mt-2 text-2xl font-extrabold text-[#1B1B1E]">{{ number_format($card['value']) }}</div>
                    <p class="mt-0.5 text-[10px] leading-snug text-[#8E8E93]">{{ $card['hint'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- Quick Barcode / Manual Tracking Scanner for Hub Staff --}}
        @if($canProcess)
            <div id="operations-quick-scanner" class="card p-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-[#16697A]"></span>
                            <h2 class="text-xs font-bold uppercase tracking-wide text-[#1B1B1E]">Quick Parcel Scan &amp; Verification</h2>
                        </div>
                        <p class="mt-0.5 text-[11px] text-[#6E6E73]">
                            Scan a barcode/QR with your handheld scanner or enter a tracking number manually to inspect status, route, and condition before confirming.
                        </p>
                    </div>
                    <div class="flex flex-1 max-w-xl flex-col sm:flex-row items-stretch sm:items-center gap-2">
                        <input id="operations-scan-input"
                               type="text"
                               x-model="scanInput"
                               @keydown.enter.prevent="lookupScan()"
                               placeholder="Scan barcode or enter TRK-YYYYMMDD-XXXX"
                               autocomplete="off"
                               spellcheck="false"
                               class="flex-1 rounded-lg border border-[#D8D5CE] bg-white px-3 py-2 text-xs font-mono text-[#1B1B1E] focus:border-[#16697A] focus:outline-none">
                        <button type="button"
                                @click="lookupScan()"
                                :disabled="scanBusy"
                                class="btn btn-primary btn-xs px-4 py-2 whitespace-nowrap disabled:opacity-60">
                            <span x-text="scanBusy ? 'Checking…' : 'Lookup / Scan'"></span>
                        </button>
                        <a href="{{ route('deliveries.scan-page') }}" class="btn btn-outline btn-xs px-3 py-2 whitespace-nowrap">Camera Scanner</a>
                    </div>
                </div>

                <div x-show="scanError" x-cloak class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-xs text-red-700 flex items-start justify-between gap-3">
                    <div>
                        <span class="font-bold uppercase text-[10px] tracking-wide block" x-text="scanWarningType ? ('Warning: ' + scanWarningType.replace('_', ' ')) : 'Scan Warning'"></span>
                        <span x-text="scanError"></span>
                    </div>
                    <button type="button" @click="clearScan()" class="text-red-600 hover:underline text-[11px] font-semibold">Dismiss</button>
                </div>

                <div x-show="scanResult" x-cloak class="mt-3 rounded-lg border p-3.5"
                     :class="['missing', 'ineligible', 'unauthorized'].includes(scanWarningType) ? 'border-red-200 bg-red-50/70' : (scanAlready ? 'border-amber-200 bg-amber-50/70' : 'border-[#B9DED6] bg-[#F0F9F7]')">
                    <template x-if="scanResult">
                        <div>
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <span class="text-[10px] font-bold uppercase tracking-wide"
                                          :class="['missing', 'ineligible', 'unauthorized'].includes(scanWarningType) ? 'text-red-700' : (scanAlready ? 'text-amber-800' : 'text-[#0E4A57]')"
                                          x-text="scanWarningType === 'missing' ? 'Missing Parcel Alert' : (scanWarningType === 'ineligible' ? 'Ineligible for Scan' : (scanAlready ? 'Duplicate Scan — Already Verified' : (scanCanConfirm ? 'Matched Parcel — Ready to Verify' : 'Parcel Verified')))"></span>
                                    <div class="mt-0.5 font-mono text-sm font-bold text-[#1B1B1E]" x-text="scanResult.tracking_number"></div>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <button type="button"
                                            x-show="scanCanConfirm"
                                            @click="confirmScan()"
                                            :disabled="scanBusy"
                                            class="btn btn-primary btn-xs">
                                        Confirm &amp; Verify Scan
                                    </button>
                                    <button type="button"
                                            x-show="!scanCanConfirm && scanResult.condition_code === 'good' && scanResult.parcel_status === 'pending_arrival' && scanResult.next_action && scanResult.next_action.key === 'receive' && scanResult.next_action.enabled"
                                            @click="openQuickAction(scanResult.id, 'receive', scanResult)"
                                            class="btn btn-primary btn-xs">
                                        Receive at hub
                                    </button>
                                    <button type="button"
                                            x-show="!scanCanConfirm && scanResult.condition_code === 'good' && scanResult.parcel_status === 'scanned'"
                                            @click="openQuickAction(scanResult.id, 'sort', scanResult)"
                                            class="btn btn-primary btn-xs">
                                        Sort parcel
                                    </button>
                                    <button type="button"
                                            x-show="!scanCanConfirm && scanResult.condition_code === 'good' && scanResult.parcel_status === 'sorted' && scanResult.next_action && scanResult.next_action.key === 'assign_rider' && scanResult.next_action.enabled"
                                            @click="openQuickAction(scanResult.id, 'assign_rider', scanResult)"
                                            class="btn btn-primary btn-xs">
                                        Assign rider
                                    </button>
                                    <button type="button"
                                            x-show="!scanCanConfirm && scanResult.condition_code === 'good' && scanResult.parcel_status === 'sorted' && scanResult.next_action && scanResult.next_action.key === 'create_transfer' && scanResult.next_action.enabled"
                                            @click="openQuickAction(scanResult.id, 'transfer', scanResult)"
                                            class="btn btn-primary btn-xs">
                                        Add to Hub Transfer
                                    </button>
                                    <a x-show="!scanCanConfirm && scanResult.next_action && scanResult.next_action.key === 'open_transfer' && scanResult.next_action.enabled && scanResult.next_action.url"
                                       :href="scanResult.next_action ? scanResult.next_action.url : '#'"
                                       class="btn btn-primary btn-xs">
                                        <span x-text="scanResult.next_action ? scanResult.next_action.label : 'Open Transfer'"></span>
                                    </a>
                                    <a x-show="scanResult.show_url" :href="scanResult.show_url" class="btn btn-outline btn-xs">Open Parcel</a>
                                    <button type="button" @click="clearScan()" class="btn btn-ghost btn-xs">Close</button>
                                </div>
                            </div>

                            <div class="mt-2.5 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 text-xs">
                                <div>
                                    <span class="text-[10px] text-[#6E6E73] block">Status</span>
                                    <span class="font-semibold text-[#1B1B1E]" x-text="scanResult.workflow_label || scanResult.parcel_status"></span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-[#6E6E73] block">Condition</span>
                                    <span class="font-bold"
                                          :class="scanResult.condition_code === 'damaged' ? 'text-amber-700' : (scanResult.condition_code === 'missing' ? 'text-red-700' : 'text-emerald-700')"
                                          x-text="scanResult.condition || 'Good'"></span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-[#6E6E73] block">Origin Hub</span>
                                    <span class="font-medium text-[#1B1B1E]" x-text="scanResult.origin_hub || '—'"></span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-[#6E6E73] block">Destination Hub</span>
                                    <span class="font-medium text-[#1B1B1E]" x-text="scanResult.destination_hub || '—'"></span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-[#6E6E73] block">Sender</span>
                                    <span class="font-medium text-[#1B1B1E]" x-text="scanResult.sender_name || '—'"></span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-[#6E6E73] block">Recipient</span>
                                    <span class="font-medium text-[#1B1B1E]" x-text="scanResult.recipient_name || '—'"></span>
                                </div>
                            </div>

                            <p x-show="scanConditionWarning" x-cloak class="mt-2 rounded border border-amber-200 bg-amber-100 px-2.5 py-1.5 text-[11px] font-medium text-amber-800" x-text="scanConditionWarning"></p>
                            <p x-show="scanMessage" x-cloak class="mt-2 text-[11px] font-medium text-[#0E4A57]" x-text="scanMessage"></p>
                        </div>
                    </template>
                </div>
            </div>
        @endif

        {{-- Search, Filters, and Sorting Controls --}}
        <div class="card p-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
                <div class="sm:col-span-2">
                    <label for="ops-search" class="block text-[10px] font-bold uppercase tracking-wide text-[#6E6E73] mb-1">
                        Search Parcels
                    </label>
                    <input id="ops-search"
                           type="text"
                           x-model="filters.search"
                           @keydown.enter.prevent="applyFilters()"
                           placeholder="Tracking #, ID, sender, recipient, hub, or rider…"
                           class="w-full rounded-lg border border-[#D8D5CE] bg-white px-3 py-1.5 text-xs text-[#1B1B1E] focus:border-[#16697A] focus:outline-none">
                </div>

                <div>
                    <label for="ops-status" class="block text-[10px] font-bold uppercase tracking-wide text-[#6E6E73] mb-1">
                        Parcel Status
                    </label>
                    <select id="ops-status"
                            x-model="filters.parcel_status"
                            @change="applyFilters()"
                            class="w-full rounded-lg border border-[#D8D5CE] bg-white px-2.5 py-1.5 text-xs text-[#1B1B1E] focus:border-[#16697A] focus:outline-none">
                        <option value="">All queue stages</option>
                        <option value="pickup_review">Pickup requests needing review</option>
                        <option value="pickup_assignment">Needs pickup rider</option>
                        <option value="pickup_in_progress">Pickup in progress / awaiting handoff</option>
                        <option value="pending_arrival">Pending Arrival (Awaiting Receipt)</option>
                        <option value="received">Received (Awaiting Scan)</option>
                        <option value="scanned">Scanned (Ready for Sorting)</option>
                        <option value="sorted">Sorted (All)</option>
                        <option value="ready_rider">Sorted — Ready for Rider</option>
                        <option value="ready_transfer">Sorted — Ready for Hub Transfer</option>
                        <option value="dispatched">Dispatched</option>
                    </select>
                </div>

                <div>
                    <label for="ops-hub" class="block text-[10px] font-bold uppercase tracking-wide text-[#6E6E73] mb-1">
                        Hub (Origin / Dest)
                    </label>
                    <select id="ops-hub"
                            x-model="filters.hub_id"
                            @change="applyFilters()"
                            class="w-full rounded-lg border border-[#D8D5CE] bg-white px-2.5 py-1.5 text-xs text-[#1B1B1E] focus:border-[#16697A] focus:outline-none">
                        <option value="">All hubs</option>
                        @foreach($hubOptions as $hubOption)
                            <option value="{{ $hubOption->id }}">{{ $hubOption->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="ops-condition" class="block text-[10px] font-bold uppercase tracking-wide text-[#6E6E73] mb-1">
                        Condition
                    </label>
                    <select id="ops-condition"
                            x-model="filters.condition"
                            @change="applyFilters()"
                            class="w-full rounded-lg border border-[#D8D5CE] bg-white px-2.5 py-1.5 text-xs text-[#1B1B1E] focus:border-[#16697A] focus:outline-none">
                        <option value="">All conditions</option>
                        <option value="good">Good</option>
                        <option value="damaged">Damaged</option>
                        <option value="missing">Missing</option>
                    </select>
                </div>

                <div>
                    <label for="ops-sort" class="block text-[10px] font-bold uppercase tracking-wide text-[#6E6E73] mb-1">
                        Sort By
                    </label>
                    <select id="ops-sort"
                            x-model="filters.sort"
                            @change="applyFilters()"
                            class="w-full rounded-lg border border-[#D8D5CE] bg-white px-2.5 py-1.5 text-xs text-[#1B1B1E] focus:border-[#16697A] focus:outline-none">
                        <option value="">Queue priority</option>
                        <option value="newest">Newest received</option>
                        <option value="oldest">Oldest received</option>
                        <option value="tracking_asc">Tracking # (A–Z)</option>
                        <option value="tracking_desc">Tracking # (Z–A)</option>
                    </select>
                </div>
            </div>

            <div class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-[#F0EEE9] pt-3">
                <div class="flex flex-wrap items-center gap-2">
                    <label for="ops-date-received" class="text-[10px] font-bold uppercase tracking-wide text-[#6E6E73]">
                        Date Received:
                    </label>
                    <input id="ops-date-received"
                           type="date"
                           x-model="filters.date_received"
                           @change="applyFilters()"
                           class="rounded-lg border border-[#D8D5CE] bg-white px-2.5 py-1 text-xs text-[#1B1B1E] focus:border-[#16697A] focus:outline-none">
                    @if($hasActiveFilters)
                        <span class="rounded bg-[#E6F4F1] px-2 py-0.5 text-[10px] font-bold text-[#0E4A57]">
                            Filtered results ({{ $actionParcels->count() }})
                        </span>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" @click="applyFilters()" class="btn btn-primary btn-xs">Apply Filters</button>
                    @if($hasActiveFilters)
                        <button type="button" @click="resetFilters()" class="btn btn-outline btn-xs">Reset</button>
                    @endif
                </div>
            </div>
        </div>

        {{-- Context-Sensitive Bulk Action Toolbar --}}
        @if($canProcess || $canManageRiders)
            <div x-show="selectedIds.length > 0" x-cloak
                 class="rounded-xl border border-[#B9DED6] bg-[#F0F9F7] p-3.5 shadow-sm space-y-2.5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center rounded-lg bg-[#16697A] px-2.5 py-1 text-xs font-bold text-white">
                            <span x-text="selectedIds.length"></span>&nbsp;selected
                        </span>
                        <span x-show="ineligibleSelectedCount() > 0"
                              class="inline-flex items-center rounded-lg border border-amber-300 bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-800">
                            <span x-text="ineligibleSelectedCount()"></span>&nbsp;not eligible for any bulk action
                        </span>
                        <button type="button"
                                @click="showSelectionDetails = !showSelectionDetails"
                                class="text-xs font-semibold text-[#16697A] hover:underline">
                            <span x-text="showSelectionDetails ? 'Hide selection breakdown' : 'Inspect hubs, destinations & eligibility'"></span>
                        </button>
                        <button type="button" @click="clearSelection()" class="text-xs font-semibold text-[#0E4A57] hover:underline">
                            Clear selection
                        </button>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @if($canProcess)
                            <button type="button"
                                    @click="openBulkModal('receive')"
                                    :disabled="bulkBusy || eligibleCount('receive') === 0"
                                    class="btn btn-primary btn-xs disabled:opacity-50">
                                Bulk Receive (<span x-text="eligibleCount('receive')"></span>)
                            </button>
                            <button type="button"
                                    @click="openBulkModal('sort')"
                                    :disabled="bulkBusy || eligibleCount('sort') === 0"
                                    class="btn btn-outline btn-xs bg-white disabled:opacity-50">
                                Bulk Assign to Sorting Batch (<span x-text="eligibleCount('sort')"></span>)
                            </button>
                            <button type="button"
                                    @click="openBulkModal('transfer')"
                                    :disabled="bulkBusy || eligibleCount('transfer') === 0"
                                    class="btn btn-outline btn-xs bg-white disabled:opacity-50">
                                Bulk Transfer (<span x-text="eligibleCount('transfer')"></span>)
                            </button>
                        @endif
                        @if($canManageRiders)
                            <button type="button"
                                    @click="openBulkModal('assign_rider')"
                                    :disabled="bulkBusy || eligibleCount('assign_rider') === 0"
                                    class="btn btn-outline btn-xs bg-white disabled:opacity-50">
                                Bulk Assign to Rider (<span x-text="eligibleCount('assign_rider')"></span>)
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Selection Hub, Destination, and Service Area Summary Badges --}}
                <div class="flex flex-wrap items-center gap-2 text-[11px] text-[#0E4A57]">
                    <span class="rounded border border-[#B9DED6] bg-white px-2 py-0.5">
                        <strong>Current / Sorting Hub:</strong> <span x-text="selectedCurrentHubsLabel()"></span>
                    </span>
                    <span class="rounded border border-[#B9DED6] bg-white px-2 py-0.5">
                        <strong>Next Destination Hub:</strong> <span x-text="selectedDestinationsLabel()"></span>
                    </span>
                    <span class="rounded border border-[#B9DED6] bg-white px-2 py-0.5">
                        <strong>Service Area:</strong> <span x-text="selectedServiceAreasLabel()"></span>
                    </span>
                </div>

                {{-- Automatic Multi-Hub / Mixed-Destination / Mixed-Service-Area Warnings --}}
                <div x-show="hasMixedTransferDestinations()" x-cloak
                     class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-900 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <strong class="font-bold">Mixed Destination Warning:</strong>
                        Selected parcels have different destination hubs. They cannot be processed in one transfer manifest.
                    </div>
                    <button type="button" @click="openBulkModal('transfer')" class="btn btn-outline btn-xs bg-white text-amber-900 border-amber-300">
                        Review Destination Groups (<span x-text="transferDestinationGroups().length"></span>)
                    </button>
                </div>

                <div x-show="hasMixedRiderServiceAreas()" x-cloak
                     class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-900 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <strong class="font-bold">Mixed Service Area Warning:</strong>
                        Some selected parcels belong to different service areas. Select a compatible rider or process the parcels separately.
                    </div>
                    <button type="button" @click="openBulkModal('assign_rider')" class="btn btn-outline btn-xs bg-white text-amber-900 border-amber-300">
                        Review Service Area Groups (<span x-text="riderServiceAreaGroups().length"></span>)
                    </button>
                </div>

                <div x-show="hasSortingConflict()" x-cloak
                     class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-900 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <strong class="font-bold">Sorting Compatibility Notice:</strong>
                        Selected parcels have different sorting hubs or pre-assigned destination hubs. Process each compatible sorting group separately so no destination is overwritten.
                    </div>
                    <button type="button" @click="openBulkModal('sort')" class="btn btn-outline btn-xs bg-white text-amber-900 border-amber-300">
                        Review Sorting Groups (<span x-text="sortingGroups().length"></span>)
                    </button>
                </div>

                {{-- Inspectable Selection Breakdown --}}
                <div x-show="showSelectionDetails" x-cloak class="rounded-lg border border-[#D8D5CE] bg-white p-3 text-xs space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="font-bold uppercase tracking-wide text-[10px] text-[#6E6E73]">Selected Parcels Eligibility &amp; Routing Breakdown</span>
                        <span class="text-[11px] text-[#6E6E73]">Damaged parcels require individual condition verification; Delivered requires individual proof.</span>
                    </div>
                    <div class="max-h-48 overflow-y-auto divide-y divide-[#F0EEE9]">
                        <template x-for="p in selectedParcels()" :key="p.id">
                            <div class="py-1.5 flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-bold text-[#16697A]" x-text="p.tracking_number"></span>
                                    <span class="rounded bg-[#F0EEE9] px-1.5 py-0.5 text-[10px] font-semibold text-[#1B1B1E]" x-text="p.workflow_label || p.parcel_status"></span>
                                </div>
                                <div class="text-[11px] text-[#1B1B1E]">
                                    <span class="text-[#6E6E73]">Current Hub:</span> <strong x-text="p.current_hub_name || '—'"></strong>
                                    <span class="mx-1 text-[#8E8E93]">&rarr;</span>
                                    <span class="text-[#6E6E73]">Next Dest:</span> <strong x-text="p.destination_hub_name || 'Not sorted yet'"></strong>
                                    <span class="mx-1 text-[#8E8E93]">&middot;</span>
                                    <span class="text-[#6E6E73]">Area:</span> <strong x-text="p.service_area_name || 'Unassigned'"></strong>
                                </div>
                                <div class="text-[11px]" :class="parcelActionSummary(p).eligible ? 'text-emerald-700 font-semibold' : 'text-amber-800'">
                                    <span x-text="parcelActionSummary(p).text"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        @endif

        {{-- Per-Parcel Bulk Operation Results Feedback --}}
        <div x-show="bulkReport" x-cloak class="card p-4 border-l-4"
             :class="bulkReport && bulkReport.failed === 0 ? 'border-l-emerald-600' : (bulkReport && bulkReport.succeeded > 0 ? 'border-l-amber-500' : 'border-l-red-600')">
            <template x-if="bulkReport">
                <div>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 class="text-xs font-bold uppercase tracking-wide text-[#1B1B1E]" x-text="bulkReport.message"></h3>
                            <p class="text-[11px] text-[#6E6E73]">Individual validation results for each selected parcel:</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <template x-if="Array.isArray(bulkReport.destination_groups) && bulkReport.destination_groups.length > 0">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <template x-for="dg in bulkReport.destination_groups" :key="dg.destination_center_id">
                                        <a x-show="dg.manifest_url"
                                           :href="dg.manifest_url"
                                           class="btn btn-primary btn-xs">
                                            Open <span x-text="dg.destination_center_name"></span> Manifest (<span x-text="dg.parcel_count"></span>) &rarr;
                                        </a>
                                    </template>
                                </div>
                            </template>
                            <a x-show="bulkReport.manifest_url && (!Array.isArray(bulkReport.destination_groups) || bulkReport.destination_groups.length === 0)"
                               :href="bulkReport.manifest_url"
                               class="btn btn-primary btn-xs">
                                Open Preselected Transfer Manifest &rarr;
                            </a>
                            <button type="button" @click="window.location.reload()" class="btn btn-outline btn-xs">
                                Refresh Queue
                            </button>
                            <button type="button" @click="bulkReport = null" class="btn btn-ghost btn-xs">
                                Dismiss
                            </button>
                        </div>
                    </div>
                    <ul class="mt-2.5 max-h-44 overflow-y-auto divide-y divide-[#F0EEE9] text-xs">
                        <template x-for="row in bulkReport.results" :key="row.delivery_id">
                            <li class="py-1.5 flex items-center justify-between gap-3">
                                <span class="font-mono font-bold text-[#1B1B1E]" x-text="row.tracking_number || ('Parcel #' + row.delivery_id)"></span>
                                <span class="flex-1 text-[#6E6E73]" x-text="row.message"></span>
                                <span class="rounded px-2 py-0.5 text-[10px] font-bold uppercase"
                                      :class="row.status === 'succeeded' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200'"
                                      x-text="row.status"></span>
                            </li>
                        </template>
                    </ul>
                </div>
            </template>
        </div>

        {{-- Bulk Action Confirmation Modal --}}
        <div x-show="activeModal" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
             @keydown.escape.window="closeBulkModal()">
            <div class="w-full max-w-lg rounded-2xl bg-white p-5 shadow-xl border border-[#E8E6E0] max-h-[90vh] overflow-y-auto" @click.outside="closeBulkModal()">
                <h3 class="text-base font-bold text-[#1B1B1E]" x-text="modalTitle()"></h3>
                <p class="mt-1 text-xs text-[#6E6E73]">
                    You have selected <strong x-text="selectedIds.length"></strong> parcel(s):
                    <strong class="text-emerald-700" x-text="eligibleCount(activeModal)"></strong> eligible,
                    <strong class="text-amber-700" x-text="ineligibleCount(activeModal)"></strong> ineligible (will be safely skipped and reported with reasons).
                </p>

                {{-- Show reasons for any ineligible parcels in the current selection --}}
                <div x-show="ineligibleParcels(activeModal).length > 0" x-cloak
                     class="mt-2.5 rounded-lg border border-amber-200 bg-amber-50 p-2.5 text-[11px] text-amber-900">
                    <div class="font-bold">Ineligible parcels in selection (<span x-text="ineligibleParcels(activeModal).length"></span>):</div>
                    <ul class="mt-1 max-h-24 overflow-y-auto space-y-0.5">
                        <template x-for="ip in ineligibleParcels(activeModal)" :key="ip.id">
                            <li>
                                <span class="font-mono font-bold" x-text="ip.tracking_number"></span>:
                                <span x-text="ineligibleReasonForParcel(ip, activeModal)"></span>
                            </li>
                        </template>
                    </ul>
                </div>

                <template x-if="activeModal === 'sort'">
                    <div class="mt-3 space-y-3">
                        <div class="rounded-lg border border-[#B9DED6] bg-[#F0F9F7] p-3 text-xs space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-bold uppercase tracking-wide text-[#0E4A57]">Current Sorting Hub (Physical Custody)</span>
                                <span class="rounded bg-white border border-[#B9DED6] px-2 py-0.5 text-[10px] font-bold text-[#0E4A57]">Locked to Custody Hub</span>
                            </div>
                            <div class="font-bold text-[#1B1B1E]" x-text="currentSortingHubLabel()"></div>
                            <p class="text-[11px] text-[#6E6E73]">
                                Sorting occurs at the parcel's actual current hub and never alters current hub custody until transfer or dispatch.
                            </p>
                        </div>

                        <div x-show="sortingGroups().length > 1" x-cloak
                             class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900 space-y-2">
                            <div class="font-bold">
                                Selected parcels belong to <span x-text="sortingGroups().length"></span> different sorting or pre-assigned destination groups:
                            </div>
                            <p class="text-[11px]">
                                To prevent overwriting a parcel's existing destination or combining incompatible hubs, select one compatible group below to sort first:
                            </p>
                            <div class="space-y-1.5">
                                <template x-for="sg in sortingGroups()" :key="sg.key">
                                    <div class="flex items-center justify-between gap-2 rounded border border-amber-200 bg-white px-2.5 py-1.5">
                                        <div>
                                            <div class="font-semibold text-[#1B1B1E]">
                                                <span x-text="sg.current_hub_name"></span>
                                                <span class="text-[#16697A]">&rarr;</span>
                                                <span x-text="sg.destination_hub_name"></span>
                                                (<span x-text="sg.parcel_ids.length"></span>)
                                            </div>
                                            <div class="text-[10px] text-[#6E6E73]" x-text="'Area: ' + sg.service_area_name + ' · ' + sg.tracking_numbers.join(', ')"></div>
                                        </div>
                                        <button type="button"
                                                @click="selectOnlySortingGroup(sg)"
                                                class="btn btn-primary btn-xs whitespace-nowrap">
                                            Select Group (<span x-text="sg.parcel_ids.length"></span>)
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Next Destination Hub</label>
                            <select x-model="bulkSortDestinationId"
                                    @change="bulkSortServiceAreaId = ''"
                                    class="w-full rounded-lg border border-[#D8D5CE] px-3 py-2 text-xs">
                                <option value="">Select next destination hub…</option>
                                @foreach($destinationCenters as $destCenter)
                                    <option value="{{ $destCenter->id }}">{{ $destCenter->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div x-show="filteredServiceAreas().length > 0">
                            <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Service Area</label>
                            <select x-model="bulkSortServiceAreaId"
                                    class="w-full rounded-lg border border-[#D8D5CE] px-3 py-2 text-xs">
                                <option value="">Select service area…</option>
                                <template x-for="sa in filteredServiceAreas()" :key="sa.id">
                                    <option :value="sa.id" x-text="sa.name"></option>
                                </template>
                            </select>
                        </div>

                        <div x-show="bulkSortDestinationId" class="rounded-lg border border-[#E8E6E0] bg-[#FAF9F6] px-3 py-2 text-xs">
                            <span class="text-[10px] font-bold uppercase tracking-wide text-[#6E6E73] block">Sorting Action</span>
                            <span class="font-bold text-[#0E4A57]" x-text="sortingActionLabel()"></span>
                        </div>
                    </div>
                </template>

                <template x-if="activeModal === 'assign_rider'">
                    <div class="mt-3 space-y-3">
                        <div class="rounded-lg border border-[#E8E6E0] bg-[#FAF9F6] p-3 text-xs space-y-2">
                            <div class="font-bold uppercase tracking-wide text-[10px] text-[#6E6E73]">
                                Delivery Service Area Groups (<span x-text="riderServiceAreaGroups().length"></span>)
                            </div>
                            <template x-for="rg in riderServiceAreaGroups()" :key="rg.key">
                                <div class="flex items-center justify-between gap-2 rounded border border-[#E8E6E0] bg-white px-2.5 py-1.5">
                                    <div>
                                        <div class="font-semibold text-[#1B1B1E]">
                                            <span x-text="rg.current_hub_name"></span> &middot; <span x-text="rg.service_area_name"></span>
                                            (<span x-text="rg.parcel_ids.length"></span> parcel<span x-text="rg.parcel_ids.length === 1 ? '' : 's'"></span>)
                                        </div>
                                        <div class="text-[10px] font-mono text-[#6E6E73]" x-text="rg.tracking_numbers.join(', ')"></div>
                                    </div>
                                    <button type="button"
                                            x-show="riderServiceAreaGroups().length > 1"
                                            @click="selectOnlyRiderGroup(rg)"
                                            class="btn btn-outline btn-xs whitespace-nowrap">
                                        Select Group (<span x-text="rg.parcel_ids.length"></span>)
                                    </button>
                                </div>
                            </template>
                        </div>

                        <div x-show="hasMixedRiderServiceAreas()" x-cloak
                             class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                            Some selected parcels belong to different service areas. Select a compatible rider or process the parcels separately.
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Assignment Mode</label>
                            <select x-model="bulkRiderMode" class="w-full rounded-lg border border-[#D8D5CE] px-3 py-2 text-xs">
                                <option value="manual">Assign to selected rider (single compatible service area)</option>
                                <option value="auto">Assisted auto-assign (best eligible rider per parcel &amp; service area)</option>
                            </select>
                        </div>
                        <div x-show="bulkRiderMode === 'manual'">
                            <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Select Compatible Rider</label>
                            <select x-model="bulkRiderId"
                                    :disabled="hasMixedRiderServiceAreas()"
                                    class="w-full rounded-lg border border-[#D8D5CE] px-3 py-2 text-xs disabled:bg-gray-100">
                                <option value="">Choose an eligible rider…</option>
                                <template x-for="riderOption in compatibleRidersForSelection()" :key="riderOption.id">
                                    <option :value="riderOption.id"
                                            x-text="riderOption.name + ' (' + riderOption.vehicle_type + ' · ' + riderOption.service_area_name + ' · ' + riderOption.active_deliveries + '/' + riderOption.max_deliveries + ' active)'">
                                    </option>
                                </template>
                            </select>
                            <p x-show="!hasMixedRiderServiceAreas() && compatibleRidersForSelection().length === 0"
                               class="mt-1 text-[11px] text-amber-800">
                                No online, approved two-wheel rider is currently available for this service area.
                            </p>
                        </div>
                    </div>
                </template>

                <template x-if="activeModal === 'transfer'">
                    <div class="mt-3 space-y-3">
                        <div class="rounded-lg border border-[#B9DED6] bg-[#F0F9F7] p-3 text-xs">
                            <span class="text-[10px] font-bold uppercase tracking-wide text-[#0E4A57] block">Origin / Current Hub</span>
                            <span class="font-bold text-[#1B1B1E]" x-text="currentSortingHubLabel()"></span>
                        </div>

                        {{-- Group eligible transfer parcels by their actual destination_center_id --}}
                        <div class="rounded-lg border border-[#E8E6E0] bg-[#FAF9F6] p-3 text-xs space-y-2">
                            <div class="font-bold uppercase tracking-wide text-[10px] text-[#6E6E73]">
                                Destination Hub Groups (<span x-text="transferDestinationGroups().length"></span>)
                            </div>
                            <template x-for="tg in transferDestinationGroups()" :key="tg.destination_center_id">
                                <div class="flex items-center justify-between gap-2 rounded border border-[#E8E6E0] bg-white px-2.5 py-2">
                                    <div>
                                        <div class="font-bold text-[#1B1B1E]">
                                            <span x-text="tg.destination_center_name"></span>
                                            <span class="text-[#16697A]">(<span x-text="tg.parcel_count"></span> parcel<span x-text="tg.parcel_count === 1 ? '' : 's'"></span>)</span>
                                        </div>
                                        <div class="text-[10px] font-mono text-[#6E6E73]" x-text="tg.tracking_numbers.join(', ')"></div>
                                    </div>
                                    <button type="button"
                                            x-show="transferDestinationGroups().length > 1"
                                            @click="selectOnlyTransferGroup(tg)"
                                            class="btn btn-primary btn-xs whitespace-nowrap">
                                        Select Group (<span x-text="tg.parcel_count"></span>)
                                    </button>
                                </div>
                            </template>
                        </div>

                        <div x-show="hasMixedTransferDestinations()" x-cloak
                             class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800">
                            <strong>Selected parcels have different destination hubs. They cannot be processed in one transfer manifest.</strong>
                            Click <em>Select Group</em> on one destination hub above to prepare its transfer manifest without overwriting any other parcel's destination.
                        </div>

                        <div x-show="!hasMixedTransferDestinations()">
                            <label class="block text-xs font-semibold text-[#1B1B1E] mb-1">Confirm Next Destination Hub</label>
                            <select x-model="bulkTransferDestinationId" class="w-full rounded-lg border border-[#D8D5CE] px-3 py-2 text-xs">
                                <option value="">Select destination hub to confirm…</option>
                                @foreach($destinationCenters as $destCenter)
                                    @if(! $center || (int) $destCenter->id !== (int) $center->id)
                                        <option value="{{ $destCenter->id }}">{{ $destCenter->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                            <p class="mt-1 text-[11px] text-[#6E6E73]">
                                Every selected parcel's existing destination will be independently verified against this destination hub before opening the transfer manifest.
                            </p>
                        </div>
                    </div>
                </template>

                <p x-show="modalError" x-cloak class="mt-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700" x-text="modalError"></p>

                <div class="mt-5 flex items-center justify-end gap-2">
                    <button type="button" @click="closeBulkModal()" :disabled="bulkBusy" class="btn btn-outline btn-xs">Cancel</button>
                    <button type="button"
                            @click="executeBulkAction()"
                            :disabled="bulkBusy || (activeModal === 'transfer' && hasMixedTransferDestinations()) || (activeModal === 'sort' && hasSortingConflict()) || (activeModal === 'assign_rider' && bulkRiderMode === 'manual' && hasMixedRiderServiceAreas())"
                            class="btn btn-primary btn-xs disabled:opacity-50">
                        <span x-text="bulkBusy ? 'Processing…' : 'Confirm & Execute'"></span>
                    </button>
                </div>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
            {{-- Action queue: next valid step per parcel, resolved from backend state. --}}
            <div id="operations-queue-container" class="card xl:col-span-2 overflow-hidden">
                <div class="flex items-center justify-between border-b border-[#E8E6E0] px-4 py-3">
                    <div>
                        <h2 class="text-sm font-bold uppercase tracking-wide text-[#1B1B1E]">Hub workload</h2>
                        <p class="mt-0.5 text-[11px] text-[#6E6E73]">Pickup requests, rider assignments, and hub processing in one queue. Waiting parcels show what must happen next.</p>
                        <div class="mt-1 flex flex-wrap gap-3 text-[11px] font-semibold text-[#16697A]">
                            <a href="{{ route('operations.index', ['parcel_status' => 'pickup_review']) }}">Pickup reviews: {{ $pickupCounts['review'] }}</a>
                            <a href="{{ route('operations.index', ['parcel_status' => 'pickup_assignment']) }}">Needs pickup rider: {{ $pickupCounts['assign'] }}</a>
                        </div>
                    </div>
                    <span class="badge badge-inactive">{{ $parcelPaginator->firstItem() ?? 0 }}?{{ $parcelPaginator->lastItem() ?? 0 }} of {{ $parcelPaginator->total() }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="table w-full">
                        <thead>
                            <tr>
                                @if($canProcess || $canManageRiders)
                                    <th class="w-9 text-center">
                                        <input type="checkbox"
                                               aria-label="Select all displayed parcels"
                                               :checked="allSelected()"
                                               @change="toggleAll($event.target.checked)"
                                               class="rounded border-[#D8D5CE] text-[#16697A] focus:ring-[#16697A]">
                                    </th>
                                @endif
                                <th class="min-w-[150px]">Tracking</th>
                                <th class="min-w-[170px]">Current state · condition</th>
                                <th class="min-w-[150px]">Location · custody</th>
                                <th class="min-w-[160px]">Next step</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($actionParcels as $item)
                                @php
                                    $delivery = $item['delivery'];
                                    $workflow = $item['workflow'];
                                    $next = $workflow['next'];
                                    $condition = $item['condition'] ?? 'good';
                                @endphp
                                <tr class="hover:bg-[#FCFBF9]" :class="isSelected({{ (int) $delivery->id }}) ? 'bg-[#F0F9F7]/60' : ''">
                                    @if($canProcess || $canManageRiders)
                                        <td class="align-middle text-center">
                                            <input type="checkbox"
                                                   value="{{ (int) $delivery->id }}"
                                                   aria-label="Select parcel {{ $delivery->tracking_number }}"
                                                   :checked="isSelected({{ (int) $delivery->id }})"
                                                   @change="toggleParcel({{ (int) $delivery->id }})"
                                                   class="rounded border-[#D8D5CE] text-[#16697A] focus:ring-[#16697A]">
                                        </td>
                                    @endif
                                    <td class="align-middle">
                                        <a href="{{ route('deliveries.show', $delivery) }}" class="font-mono text-xs font-bold text-[#16697A] hover:underline">{{ $delivery->tracking_number }}</a>
                                        <div class="mt-0.5 text-[10px] text-[#8E8E93]">
                                            {{ $delivery->sender_name ? $delivery->sender_name.' → ' : '' }}{{ $delivery->recipient_city ?: $delivery->recipient_name ?: '—' }}
                                        </div>
                                        @if($delivery->received_at)
                                            <div class="text-[10px] text-[#8E8E93]">Received {{ $delivery->received_at->format('M d, H:i') }}</div>
                                        @endif
                                    </td>
                                    <td class="align-middle">
                                        <div class="flex flex-wrap items-center gap-1">
                                            <span class="inline-flex items-center rounded border px-2 py-0.5 text-[10px] font-bold {{ $tone[$workflow['tone']] ?? $tone['gray'] }}">{{ $workflow['label'] }}</span>
                                            <span class="inline-flex items-center rounded border px-1.5 py-0.5 text-[10px] font-bold {{ $conditionTone[$condition] ?? $conditionTone['good'] }}">
                                                {{ ucfirst($condition) }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="align-middle text-xs">
                                        <div class="font-medium text-[#1B1B1E]" title="Current / Sorting Hub">{{ $delivery->logisticsCenter?->name ?? 'In transit' }}</div>
                                        @if($delivery->destinationCenter)
                                            <div class="text-[10px] text-[#0E4A57] font-medium" title="Next Destination Hub">&rarr; {{ $delivery->destinationCenter->name }}</div>
                                        @endif
                                        @if($delivery->serviceArea)
                                            <div class="text-[10px] text-[#6E6E73]" title="Delivery Service Area">Area: {{ $delivery->serviceArea->name }}</div>
                                        @endif
                                        <div class="text-[10px] text-[#6E6E73]">{{ $workflow['custody'] }}</div>
                                        @php
                                            $transfer = $workflow['transfer'];
                                            // The controller loads driver details only
                                            // for viewers from the origin company.
                                            $driverVisible = $transfer && $transfer->relationLoaded('driver');
                                            $responsible = $delivery->rider?->name ?? ($driverVisible ? $transfer->driver?->name : null);
                                        @endphp
                                        @if($responsible)
                                            <div class="text-[10px] text-[#8E8E93]">{{ $delivery->rider ? 'Rider' : 'Transport driver' }}: {{ $responsible }}</div>
                                        @endif
                                    </td>
                                    <td class="align-middle">
                                        @if($next['enabled'])
                                            @if($condition !== 'good' && in_array($next['key'], ['receive', 'scan', 'sort', 'assign_rider', 'create_transfer'], true))
                                                <a href="{{ route('deliveries.show', $delivery) }}" class="btn btn-outline btn-xs whitespace-nowrap" title="Damaged or missing parcels require individual condition verification.">Verify condition</a>
                                            @elseif(in_array($next['key'], ['review_pickup', 'assign_pickup_rider'], true))
                                                @include('logistics.operations.pickup-action', ['pickup' => $delivery->pickupRequest])
                                            @elseif($next['key'] === 'scan')
                                                <button type="button" @click="quickScanParcel(@js($delivery->tracking_number))" class="btn btn-primary btn-xs whitespace-nowrap">{{ $next['label'] }}</button>
                                            @elseif($next['key'] === 'receive')
                                                <button type="button" @click="openQuickAction({{ (int) $delivery->id }}, 'receive')" class="btn btn-primary btn-xs whitespace-nowrap">{{ $next['label'] }}</button>
                                            @elseif($next['key'] === 'sort')
                                                <button type="button" @click="openQuickAction({{ (int) $delivery->id }}, 'sort')" class="btn btn-primary btn-xs whitespace-nowrap">{{ $next['label'] }}</button>
                                            @elseif($next['key'] === 'assign_rider')
                                                <button type="button" @click="openQuickAction({{ (int) $delivery->id }}, 'assign_rider')" class="btn btn-primary btn-xs whitespace-nowrap">{{ $next['label'] }}</button>
                                            @elseif($next['key'] === 'create_transfer')
                                                <button type="button" @click="openQuickAction({{ (int) $delivery->id }}, 'transfer')" class="btn btn-primary btn-xs whitespace-nowrap">{{ $next['label'] }}</button>
                                            @elseif($next['url'])
                                                <a href="{{ $next['url'] }}" class="btn btn-primary btn-xs whitespace-nowrap">{{ $next['label'] }}</a>
                                            @endif
                                        @else
                                            <span class="inline-flex flex-col">
                                                <span class="text-[11px] font-semibold text-[#6E6E73]">{{ $next['label'] }}</span>
                                                @if($next['reason'])
                                                    <span class="text-[10px] text-[#8E8E93]">{{ $next['reason'] }}</span>
                                                @endif
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ ($canProcess || $canManageRiders) ? 5 : 4 }}" class="py-10 text-center text-xs text-[#6E6E73]">
                                        @if($hasActiveFilters)
                                            No parcels matched your active search or filter criteria.
                                        @else
                                            No parcels are waiting on this hub right now.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($parcelPaginator->hasPages())
                    <div class="border-t border-[#E8E6E0] px-4 py-3">{{ $parcelPaginator->fragment('operations-queue-container')->links() }}</div>
                @endif
            </div>

            {{-- Exceptions --}}
            <div class="card overflow-hidden">
                <div class="border-b border-[#E8E6E0] px-4 py-3">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-[#1B1B1E]">Exceptions</h2>
                    <p class="mt-0.5 text-[11px] text-[#6E6E73]">Damaged or missing manifest outcomes. These never count as received.</p>
                </div>
                <div class="divide-y divide-[#F0EEE9]">
                    @forelse($exceptionEntries as $entry)
                        <a href="{{ route('hub-transfers.show', $entry->transfer) }}" class="block px-4 py-3 hover:bg-[#FCFBF9]">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-mono text-xs font-bold text-[#16697A]">{{ $entry->delivery?->tracking_number ?? '—' }}</span>
                                <span class="rounded border border-red-200 bg-red-50 px-1.5 py-0.5 text-[10px] font-bold text-red-700">
                                    {{ $entry->receiving_status === 'missing' ? 'Missing' : 'Damaged' }}
                                </span>
                            </div>
                            <div class="mt-1 text-[11px] text-[#6E6E73]">{{ $entry->transfer?->reference }}</div>
                            <div class="mt-0.5 line-clamp-2 text-[11px] text-[#1B1B1E]">{{ $entry->exception_reason ?: 'No reason recorded' }}</div>
                        </a>
                    @empty
                        <div class="px-4 py-10 text-center text-xs text-[#6E6E73]">No damaged or missing parcels in this scope.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            {{-- Incoming transfers --}}
            <div class="card overflow-hidden">
                <div class="flex items-center justify-between border-b border-[#E8E6E0] px-4 py-3">
                    <div>
                        <h2 class="text-sm font-bold uppercase tracking-wide text-[#1B1B1E]">Incoming transfers</h2>
                        <p class="mt-0.5 text-[11px] text-[#6E6E73]">Open a manifest to record arrival and receiving per parcel.</p>
                    </div>
                    <a href="{{ route('hub-transfers.index') }}" class="text-[11px] font-bold text-[#16697A] hover:underline">All transfers</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="table w-full">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Route</th>
                                <th>Pending</th>
                                <th>Status</th>
                                <th class="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($incomingTransfers as $transfer)
                                <tr class="hover:bg-[#FCFBF9]">
                                    <td class="align-middle">
                                        <div class="font-mono text-xs font-bold text-[#16697A]">{{ $transfer->reference }}</div>
                                        <div class="text-[10px] text-[#6E6E73]">{{ $transfer->relationLoaded('truck') ? ($transfer->truck?->plate_number ?? 'No truck') : 'Fleet managed by origin company' }}</div>
                                    </td>
                                    <td class="align-middle text-[11px] text-[#1B1B1E]">
                                        {{ $transfer->origin?->name ?? '—' }}
                                        <span class="text-[#16697A]">→</span>
                                        {{ $transfer->destination?->name ?? '—' }}
                                    </td>
                                    <td class="align-middle text-xs">
                                        <span class="font-bold text-[#1B1B1E]">{{ $transfer->pending_parcels_count }}</span>
                                        <span class="text-[#6E6E73]">/ {{ $transfer->manifest_parcels_count }}</span>
                                    </td>
                                    <td class="align-middle">
                                        <span class="badge {{ $transfer->status === 'exception' ? 'badge-cancelled' : ($transfer->status === 'in_transit' ? 'badge-processing' : 'badge-pending') }}">
                                            {{ \App\Support\HubTransferProgress::transferLabel($transfer->status) }}
                                        </span>
                                    </td>
                                    <td class="text-right align-middle">
                                        <a href="{{ route('hub-transfers.show', $transfer) }}" class="btn btn-soft btn-xs whitespace-nowrap">Open</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-10 text-center text-xs text-[#6E6E73]">No incoming transfers.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Outgoing transfers --}}
            <div class="card overflow-hidden">
                <div class="flex items-center justify-between border-b border-[#E8E6E0] px-4 py-3">
                    <div>
                        <h2 class="text-sm font-bold uppercase tracking-wide text-[#1B1B1E]">Outgoing transfers</h2>
                        <p class="mt-0.5 text-[11px] text-[#6E6E73]">Loading and ready-to-dispatch manifests from this scope.</p>
                    </div>
                    @if($canProcess)
                        <a href="{{ route('hub-transfers.create', $center ? ['origin_center_id' => $center->id] : []) }}" class="btn btn-outline btn-xs">New transfer</a>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="table w-full">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Route</th>
                                <th>Loaded</th>
                                <th>Status</th>
                                <th class="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($outgoingTransfers as $transfer)
                                <tr class="hover:bg-[#FCFBF9]">
                                    <td class="align-middle">
                                        <div class="font-mono text-xs font-bold text-[#16697A]">{{ $transfer->reference }}</div>
                                        <div class="text-[10px] text-[#6E6E73]">{{ $transfer->relationLoaded('driver') ? ($transfer->driver?->name ?? 'No driver') : 'Fleet managed by origin company' }}</div>
                                    </td>
                                    <td class="align-middle text-[11px] text-[#1B1B1E]">
                                        {{ $transfer->origin?->name ?? '—' }}
                                        <span class="text-[#16697A]">→</span>
                                        {{ $transfer->destination?->name ?? '—' }}
                                    </td>
                                    <td class="align-middle text-xs">
                                        <span class="font-bold text-[#1B1B1E]">{{ $transfer->loaded_parcels_count }}</span>
                                        <span class="text-[#6E6E73]">/ {{ $transfer->manifest_parcels_count }}</span>
                                    </td>
                                    <td class="align-middle">
                                        <span class="badge badge-pending">{{ \App\Support\HubTransferProgress::transferLabel($transfer->status) }}</span>
                                    </td>
                                    <td class="text-right align-middle">
                                        <a href="{{ route('hub-transfers.show', $transfer) }}" class="btn btn-soft btn-xs whitespace-nowrap">Open</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-10 text-center text-xs text-[#6E6E73]">No outgoing transfers.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            window.operationsWorkspace = function (config) {
                return {
                    baseUrl: config.baseUrl || '/logistics/operations',
                    scanLookupUrl: config.scanLookupUrl || '/logistics/deliveries/scan-lookup',
                    scanVerifyUrl: config.scanVerifyUrl || '/logistics/deliveries/scan-verify',
                    bulkReceiveUrl: config.bulkReceiveUrl || '/logistics/operations/bulk-receive',
                    bulkSortUrl: config.bulkSortUrl || '/logistics/operations/bulk-sort',
                    bulkAssignRiderUrl: config.bulkAssignRiderUrl || '/logistics/operations/bulk-assign-rider',
                    bulkTransferUrl: config.bulkTransferUrl || '/logistics/operations/bulk-transfer',
                    filters: {
                        search: (config.filters && config.filters.search) ? String(config.filters.search) : '',
                        parcel_status: (config.filters && config.filters.parcel_status) ? String(config.filters.parcel_status) : '',
                        hub_id: (config.filters && config.filters.hub_id) ? String(config.filters.hub_id) : '',
                        date_received: (config.filters && config.filters.date_received) ? String(config.filters.date_received) : '',
                        condition: (config.filters && config.filters.condition) ? String(config.filters.condition) : '',
                        sort: (config.filters && config.filters.sort) ? String(config.filters.sort) : '',
                    },
                    parcels: Array.isArray(config.parcels) ? config.parcels : [],
                    serviceAreas: Array.isArray(config.serviceAreas) ? config.serviceAreas : [],
                    destinationCenters: Array.isArray(config.destinationCenters) ? config.destinationCenters : [],
                    riders: Array.isArray(config.riders) ? config.riders : [],
                    centerId: config.centerId ? Number(config.centerId) : null,
                    centerName: config.centerName ? String(config.centerName) : 'Assigned Hub',
                    selectedIds: [],
                    showSelectionDetails: false,
                    scanInput: '',
                    scanBusy: false,
                    scanError: '',
                    scanMessage: '',
                    scanWarningType: '',
                    scanConditionWarning: '',
                    scanCanConfirm: false,
                    scanAlready: false,
                    scanResult: null,
                    activeModal: '',
                    modalError: '',
                    bulkBusy: false,
                    bulkReport: null,
                    bulkSortDestinationId: '',
                    bulkSortServiceAreaId: '',
                    bulkRiderMode: 'manual',
                    bulkRiderId: '',
                    bulkTransferDestinationId: '',

                    init() {
                        if (!Array.isArray(this.parcels) || this.parcels.length === 0) {
                            const rootEl = document.getElementById('operations-workspace-root');
                            if (rootEl && rootEl.dataset && rootEl.dataset.parcels) {
                                try {
                                    const parsed = JSON.parse(rootEl.dataset.parcels);
                                    if (Array.isArray(parsed)) {
                                        this.parcels = parsed;
                                    }
                                } catch (e) {}
                            }
                        }
                    },

                    csrfToken() {
                        const meta = document.querySelector('meta[name="csrf-token"]');
                        return meta ? meta.getAttribute('content') : '';
                    },

                    applyFilters() {
                        const params = new URLSearchParams();
                        Object.entries(this.filters).forEach(([key, val]) => {
                            const trimmed = String(val || '').trim();
                            if (trimmed !== '') {
                                params.set(key, trimmed);
                            }
                        });
                        const qs = params.toString();
                        window.location.href = qs ? (this.baseUrl + '?' + qs) : this.baseUrl;
                    },

                    resetFilters() {
                        window.location.href = this.baseUrl;
                    },

                    isSelected(id) {
                        return this.selectedIds.includes(Number(id));
                    },

                    toggleParcel(id) {
                        const numId = Number(id);
                        if (this.isSelected(numId)) {
                            this.selectedIds = this.selectedIds.filter(item => item !== numId);
                        } else {
                            this.selectedIds.push(numId);
                        }
                    },

                    allSelected() {
                        return this.parcels.length > 0 && this.selectedIds.length === this.parcels.length;
                    },

                    toggleAll(checked) {
                        this.selectedIds = checked ? this.parcels.map(p => Number(p.id)) : [];
                    },

                    clearSelection() {
                        this.selectedIds = [];
                        this.showSelectionDetails = false;
                    },

                    selectedParcels() {
                        if (!Array.isArray(this.selectedIds) || this.selectedIds.length === 0) {
                            return [];
                        }
                        const selectedSet = new Set(this.selectedIds.map(id => Number(id)));
                        return this.parcels.filter(p => selectedSet.has(Number(p.id)));
                    },

                    isParcelEligible(parcel, action) {
                        if (!parcel || !action) return false;
                        if (parcel.eligibility && typeof parcel.eligibility[action] === 'boolean') {
                            return parcel.eligibility[action];
                        }
                        const isGood = String(parcel.condition || 'good').toLowerCase() === 'good';
                        if (!isGood) return false;
                        if (this.centerId && parcel.center_id && Number(parcel.center_id) !== Number(this.centerId)) {
                            return false;
                        }
                        const status = String(parcel.parcel_status || '');
                        const nextKey = String(parcel.next_key || '');
                        if (action === 'receive') return status === 'pending_arrival' && nextKey === 'receive';
                        if (action === 'sort') return status === 'scanned';
                        if (action === 'assign_rider') return status === 'sorted' && nextKey === 'assign_rider' && !parcel.rider_id;
                        if (action === 'transfer') return status === 'sorted' && nextKey === 'create_transfer';
                        return false;
                    },

                    eligibleParcels(action) {
                        if (!action) return [];
                        return this.selectedParcels().filter(p => this.isParcelEligible(p, action));
                    },

                    ineligibleParcels(action) {
                        if (!action) return [];
                        return this.selectedParcels().filter(p => !this.isParcelEligible(p, action));
                    },

                    eligibleCount(action) {
                        return this.eligibleParcels(action).length;
                    },

                    ineligibleCount(action) {
                        return this.ineligibleParcels(action).length;
                    },

                    ineligibleSelectedCount() {
                        return this.selectedParcels().filter(p => {
                            return !this.isParcelEligible(p, 'receive')
                                && !this.isParcelEligible(p, 'sort')
                                && !this.isParcelEligible(p, 'assign_rider')
                                && !this.isParcelEligible(p, 'transfer');
                        }).length;
                    },

                    ineligibleReasonForParcel(parcel, action) {
                        if (!parcel || !action) return 'Ineligible for this action.';
                        if (parcel.ineligible_reasons && parcel.ineligible_reasons[action]) {
                            return parcel.ineligible_reasons[action];
                        }
                        if (String(parcel.condition || 'good').toLowerCase() !== 'good') {
                            return 'Damaged or missing parcels require individual condition verification.';
                        }
                        if (this.centerId && parcel.center_id && Number(parcel.center_id) !== Number(this.centerId)) {
                            return 'Parcel is currently in custody at ' + (parcel.current_hub_name || 'another hub') + '.';
                        }
                        return 'Current stage (' + (parcel.workflow_label || parcel.parcel_status) + ') is not eligible for ' + action.replace('_', ' ') + '.';
                    },

                    parcelActionSummary(parcel) {
                        if (!parcel) return { eligible: false, text: 'Unknown parcel' };
                        if (this.isParcelEligible(parcel, 'receive')) return { eligible: true, text: 'Eligible: Bulk Receive' };
                        if (this.isParcelEligible(parcel, 'sort')) return { eligible: true, text: 'Eligible: Bulk Sort' };
                        if (this.isParcelEligible(parcel, 'transfer')) return { eligible: true, text: 'Eligible: Bulk Transfer (' + (parcel.destination_hub_name || 'Next Hub') + ')' };
                        if (this.isParcelEligible(parcel, 'assign_rider')) return { eligible: true, text: 'Eligible: Assign Rider (' + (parcel.service_area_name || 'Local Area') + ')' };
                        const firstReason = (parcel.ineligible_reasons && (parcel.ineligible_reasons.sort || parcel.ineligible_reasons.transfer || parcel.ineligible_reasons.assign_rider || parcel.ineligible_reasons.receive))
                            || this.ineligibleReasonForParcel(parcel, 'sort');
                        return { eligible: false, text: 'Ineligible: ' + firstReason };
                    },

                    selectedCurrentHubsLabel() {
                        const hubs = [...new Set(this.selectedParcels().map(p => p.current_hub_name || 'Unassigned Hub'))];
                        return hubs.length > 0 ? hubs.join(', ') : '—';
                    },

                    selectedDestinationsLabel() {
                        const dests = [...new Set(this.selectedParcels().map(p => p.destination_hub_name || 'Not sorted yet'))];
                        return dests.length > 0 ? dests.join(', ') : '—';
                    },

                    selectedServiceAreasLabel() {
                        const areas = [...new Set(this.selectedParcels().map(p => p.service_area_name || 'Unassigned'))];
                        return areas.length > 0 ? areas.join(', ') : '—';
                    },

                    currentSortingHubLabel() {
                        const eligibleSort = this.eligibleParcels(this.activeModal || 'sort');
                        const list = eligibleSort.length > 0 ? eligibleSort : this.selectedParcels();
                        const hubs = [...new Set(list.map(p => p.current_hub_name).filter(Boolean))];
                        if (hubs.length === 1) return hubs[0];
                        if (hubs.length > 1) return hubs.join(' / ') + ' (Multiple Hubs)';
                        return this.centerName || 'Assigned Hub';
                    },

                    sortingActionLabel() {
                        const destId = Number(this.bulkSortDestinationId || 0);
                        if (!destId) return 'Select Next Destination Hub';
                        const destObj = this.destinationCenters.find(dc => Number(dc.id) === destId);
                        const destName = destObj ? destObj.name : ('Hub #' + destId);
                        const eligibleSort = this.eligibleParcels('sort');
                        const currentCenterId = eligibleSort.length > 0 && eligibleSort[0].center_id
                            ? Number(eligibleSort[0].center_id)
                            : Number(this.centerId || 0);
                        if (currentCenterId && destId === currentCenterId) {
                            return 'Local Last-Mile Sorting at ' + destName + ' (Ready for Rider Assignment)';
                        }
                        return 'Inter-Hub Transfer Routing → ' + destName + ' (Ready for Hub Transfer Manifest)';
                    },

                    sortingGroups() {
                        const eligible = this.eligibleParcels('sort');
                        const map = new Map();
                        eligible.forEach(p => {
                            const cId = p.center_id ? Number(p.center_id) : 0;
                            const dId = p.destination_center_id ? Number(p.destination_center_id) : 0;
                            const saId = p.service_area_id ? Number(p.service_area_id) : 0;
                            const key = cId + ':' + dId + ':' + saId;
                            if (!map.has(key)) {
                                map.set(key, {
                                    key,
                                    center_id: cId || null,
                                    current_hub_name: p.current_hub_name || this.centerName || 'Current Hub',
                                    destination_center_id: dId || null,
                                    destination_hub_name: p.destination_hub_name || 'Unassigned Destination',
                                    service_area_id: saId || null,
                                    service_area_name: p.service_area_name || 'Unassigned Area',
                                    parcel_ids: [],
                                    tracking_numbers: [],
                                });
                            }
                            const grp = map.get(key);
                            grp.parcel_ids.push(Number(p.id));
                            grp.tracking_numbers.push(p.tracking_number);
                        });
                        return Array.from(map.values());
                    },

                    hasSortingConflict() {
                        const groups = this.sortingGroups();
                        if (groups.length <= 1) return false;
                        const centerIds = new Set(groups.map(g => g.center_id));
                        if (centerIds.size > 1) return true;
                        const assignedDestGroups = groups.filter(g => g.destination_center_id !== null);
                        const distinctAssignedDests = new Set(assignedDestGroups.map(g => g.destination_center_id + ':' + (g.service_area_id || 0)));
                        return distinctAssignedDests.size > 1 || (distinctAssignedDests.size === 1 && groups.some(g => g.destination_center_id === null));
                    },

                    selectOnlySortingGroup(group) {
                        if (!group || !Array.isArray(group.parcel_ids)) return;
                        this.selectedIds = [...group.parcel_ids];
                        this.bulkSortDestinationId = group.destination_center_id ? String(group.destination_center_id) : '';
                        this.bulkSortServiceAreaId = group.service_area_id ? String(group.service_area_id) : '';
                        this.modalError = '';
                    },

                    transferDestinationGroups() {
                        const eligible = this.eligibleParcels('transfer');
                        const map = new Map();
                        eligible.forEach(p => {
                            const dId = p.destination_center_id ? Number(p.destination_center_id) : 0;
                            if (!dId) return;
                            if (!map.has(dId)) {
                                map.set(dId, {
                                    destination_center_id: dId,
                                    destination_center_name: p.destination_hub_name || ('Hub #' + dId),
                                    parcel_count: 0,
                                    parcel_ids: [],
                                    tracking_numbers: [],
                                });
                            }
                            const grp = map.get(dId);
                            grp.parcel_count += 1;
                            grp.parcel_ids.push(Number(p.id));
                            grp.tracking_numbers.push(p.tracking_number);
                        });
                        return Array.from(map.values());
                    },

                    hasMixedTransferDestinations() {
                        return this.transferDestinationGroups().length > 1;
                    },

                    selectOnlyTransferGroup(group) {
                        if (!group || !Array.isArray(group.parcel_ids)) return;
                        this.selectedIds = [...group.parcel_ids];
                        this.bulkTransferDestinationId = String(group.destination_center_id);
                        this.modalError = '';
                    },

                    riderServiceAreaGroups() {
                        const eligible = this.eligibleParcels('assign_rider');
                        const map = new Map();
                        eligible.forEach(p => {
                            const cId = p.center_id ? Number(p.center_id) : 0;
                            const saId = p.service_area_id ? Number(p.service_area_id) : 0;
                            const key = cId + ':' + saId;
                            if (!map.has(key)) {
                                map.set(key, {
                                    key,
                                    center_id: cId || null,
                                    current_hub_name: p.current_hub_name || this.centerName || 'Current Hub',
                                    service_area_id: saId || null,
                                    service_area_name: p.service_area_name || 'Unassigned Area',
                                    parcel_ids: [],
                                    tracking_numbers: [],
                                });
                            }
                            const grp = map.get(key);
                            grp.parcel_ids.push(Number(p.id));
                            grp.tracking_numbers.push(p.tracking_number);
                        });
                        return Array.from(map.values());
                    },

                    hasMixedRiderServiceAreas() {
                        return this.riderServiceAreaGroups().length > 1;
                    },

                    selectOnlyRiderGroup(group) {
                        if (!group || !Array.isArray(group.parcel_ids)) return;
                        this.selectedIds = [...group.parcel_ids];
                        this.bulkRiderId = '';
                        this.modalError = '';
                    },

                    compatibleRidersForSelection() {
                        const groups = this.riderServiceAreaGroups();
                        if (groups.length === 0) {
                            return this.riders;
                        }
                        if (groups.length > 1) {
                            return [];
                        }
                        const group = groups[0];
                        return this.riders.filter(r => {
                            if (group.center_id && r.center_id && Number(r.center_id) !== Number(group.center_id)) {
                                return false;
                            }
                            if (group.service_area_id !== null && Number(r.service_area_id || 0) !== Number(group.service_area_id)) {
                                return false;
                            }
                            return true;
                        });
                    },

                    filteredServiceAreas() {
                        const destId = Number(this.bulkSortDestinationId || 0);
                        if (!destId) return [];
                        return this.serviceAreas.filter(sa => Number(sa.logistics_center_id) === destId);
                    },

                    modalTitle() {
                        const isSingle = this.selectedIds.length === 1;
                        if (this.activeModal === 'receive') return isSingle ? 'Confirm Parcel Receipt at Hub' : 'Confirm Bulk Receive';
                        if (this.activeModal === 'sort') return isSingle ? 'Sort Parcel — Select Next Destination Hub' : 'Confirm Bulk Assign to Sorting Batch';
                        if (this.activeModal === 'assign_rider') return isSingle ? 'Assign Rider to Parcel' : 'Confirm Bulk Assign to Rider';
                        if (this.activeModal === 'transfer') return isSingle ? 'Confirm Transfer Destination Hub' : 'Confirm Bulk Transfer & Destination Hub';
                        return 'Confirm Operation';
                    },

                    focusQuickScanner() {
                        const scannerEl = document.getElementById('operations-quick-scanner');
                        if (scannerEl && typeof scannerEl.scrollIntoView === 'function') {
                            scannerEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                        const inputEl = document.getElementById('operations-scan-input');
                        if (inputEl && typeof inputEl.focus === 'function') {
                            inputEl.focus();
                        }
                    },

                    quickScanParcel(trackingNumber) {
                        this.scanInput = String(trackingNumber || '').trim().toUpperCase();
                        this.focusQuickScanner();
                        this.lookupScan();
                    },

                    upsertParcelFromScan(scanMeta) {
                        if (!scanMeta || !scanMeta.id) return;
                        const numId = Number(scanMeta.id);
                        const nextKey = (scanMeta.next_action && scanMeta.next_action.enabled) ? scanMeta.next_action.key : null;
                        const isGood = (scanMeta.condition_code || 'good') === 'good';
                        const existing = this.parcels.find(p => Number(p.id) === numId) || {};
                        const updated = {
                            ...existing,
                            id: numId,
                            tracking_number: scanMeta.tracking_number,
                            parcel_status: scanMeta.parcel_status,
                            workflow_label: scanMeta.workflow_label || scanMeta.parcel_status,
                            condition: scanMeta.condition_code || 'good',
                            center_id: scanMeta.center_id ? Number(scanMeta.center_id) : (existing.center_id || this.centerId),
                            current_hub_name: scanMeta.current_hub || existing.current_hub_name || this.centerName,
                            origin_hub_name: scanMeta.origin_hub || existing.origin_hub_name || null,
                            destination_center_id: scanMeta.destination_center_id ? Number(scanMeta.destination_center_id) : null,
                            destination_hub_name: scanMeta.destination_hub || existing.destination_hub_name || null,
                            service_area_id: scanMeta.service_area_id ? Number(scanMeta.service_area_id) : null,
                            service_area_name: scanMeta.service_area_name || existing.service_area_name || null,
                            next_key: nextKey,
                            eligibility: {
                                receive: isGood && nextKey === 'receive',
                                sort: isGood && scanMeta.parcel_status === 'scanned',
                                assign_rider: isGood && nextKey === 'assign_rider',
                                transfer: isGood && nextKey === 'create_transfer',
                            },
                        };
                        const idx = this.parcels.findIndex(p => Number(p.id) === numId);
                        if (idx >= 0) {
                            this.parcels.splice(idx, 1, updated);
                        } else {
                            this.parcels.push(updated);
                        }
                    },

                    openQuickAction(parcelId, action, scanMeta = null) {
                        const numId = Number(parcelId);
                        if (!numId || !action) return;
                        if (scanMeta) {
                            this.upsertParcelFromScan(scanMeta);
                        }
                        this.selectedIds = [numId];
                        this.openBulkModal(action);
                    },

                    openBulkModal(action) {
                        this.modalError = '';
                        this.activeModal = action;
                        if (action === 'sort') {
                            const sGroups = this.sortingGroups();
                            if (sGroups.length === 1 && sGroups[0].destination_center_id) {
                                this.bulkSortDestinationId = String(sGroups[0].destination_center_id);
                                this.bulkSortServiceAreaId = sGroups[0].service_area_id ? String(sGroups[0].service_area_id) : '';
                            } else {
                                this.bulkSortDestinationId = '';
                                this.bulkSortServiceAreaId = '';
                            }
                        } else if (action === 'transfer') {
                            const tGroups = this.transferDestinationGroups();
                            if (tGroups.length === 1) {
                                this.bulkTransferDestinationId = String(tGroups[0].destination_center_id);
                            } else {
                                this.bulkTransferDestinationId = '';
                            }
                        } else if (action === 'assign_rider') {
                            const compat = this.compatibleRidersForSelection();
                            if (!compat.some(r => String(r.id) === String(this.bulkRiderId))) {
                                this.bulkRiderId = '';
                            }
                        }
                    },

                    closeBulkModal() {
                        if (this.bulkBusy) return;
                        this.activeModal = '';
                        this.modalError = '';
                    },

                    refreshWorkspaceData() {
                        fetch(window.location.href, {
                            headers: {
                                'Accept': 'text/html',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        }).then(r => r.text()).then(html => {
                            if (typeof DOMParser === 'undefined') return;
                            const doc = new DOMParser().parseFromString(html, 'text/html');
                            const nextRoot = doc.getElementById('operations-workspace-root');
                            if (nextRoot && nextRoot.dataset && nextRoot.dataset.parcels) {
                                try {
                                    this.parcels = JSON.parse(nextRoot.dataset.parcels);
                                } catch (e) {}
                            }
                            const currentKpi = document.getElementById('operations-kpi-grid');
                            const nextKpi = doc.getElementById('operations-kpi-grid');
                            if (currentKpi && nextKpi) {
                                currentKpi.innerHTML = nextKpi.innerHTML;
                            }
                            const currentQueue = document.getElementById('operations-queue-container');
                            const nextQueue = doc.getElementById('operations-queue-container');
                            if (currentQueue && nextQueue) {
                                currentQueue.innerHTML = nextQueue.innerHTML;
                                if (window.Alpine && typeof window.Alpine.initTree === 'function') {
                                    window.Alpine.initTree(currentQueue);
                                }
                            }
                        }).catch(() => {});
                    },

                    executeBulkAction() {
                        if (this.bulkBusy || !this.activeModal || this.selectedIds.length === 0) return;
                        this.modalError = '';

                        let url = '';
                        const processedIds = [...this.selectedIds];
                        const payload = {
                            confirmed: true,
                            delivery_ids: processedIds,
                        };

                        if (this.activeModal === 'receive') {
                            url = this.bulkReceiveUrl;
                        } else if (this.activeModal === 'sort') {
                            if (this.hasSortingConflict()) {
                                this.modalError = 'Selected parcels have different sorting locations or pre-assigned destinations. Select one compatible sorting group first.';
                                return;
                            }
                            if (!this.bulkSortDestinationId) {
                                this.modalError = 'Select a next destination hub for the sorting batch.';
                                return;
                            }
                            if (this.filteredServiceAreas().length > 0 && !this.bulkSortServiceAreaId) {
                                this.modalError = 'Select an active service area for the chosen destination hub.';
                                return;
                            }
                            url = this.bulkSortUrl;
                            const sGroups = this.sortingGroups();
                            if (sGroups.length === 1 && sGroups[0].center_id) {
                                payload.current_center_id = Number(sGroups[0].center_id);
                            } else if (this.centerId) {
                                payload.current_center_id = Number(this.centerId);
                            }
                            payload.destination_center_id = Number(this.bulkSortDestinationId);
                            if (this.bulkSortServiceAreaId) {
                                payload.service_area_id = Number(this.bulkSortServiceAreaId);
                            }
                        } else if (this.activeModal === 'assign_rider') {
                            if (this.bulkRiderMode === 'manual') {
                                if (this.hasMixedRiderServiceAreas()) {
                                    this.modalError = 'Some selected parcels belong to different service areas. Select a compatible group or use assisted auto-assign.';
                                    return;
                                }
                                if (!this.bulkRiderId) {
                                    this.modalError = 'Select an eligible compatible rider or switch to assisted auto-assign.';
                                    return;
                                }
                            }
                            url = this.bulkAssignRiderUrl;
                            payload.mode = this.bulkRiderMode;
                            if (this.bulkRiderMode === 'manual') {
                                payload.rider_id = Number(this.bulkRiderId);
                            }
                        } else if (this.activeModal === 'transfer') {
                            if (this.hasMixedTransferDestinations()) {
                                this.modalError = 'Selected parcels have different destination hubs. They cannot be processed in one transfer manifest. Select one destination group above.';
                                return;
                            }
                            if (!this.bulkTransferDestinationId) {
                                this.modalError = 'Confirm the destination hub before proceeding with bulk transfer.';
                                return;
                            }
                            url = this.bulkTransferUrl;
                            payload.destination_center_id = Number(this.bulkTransferDestinationId);
                        }

                        this.bulkBusy = true;
                        fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken(),
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify(payload),
                        }).then(async r => {
                            let data = null;
                            try { data = await r.json(); } catch (e) { data = null; }
                            this.bulkBusy = false;
                            if (!data) {
                                this.modalError = 'Unexpected server response. Please refresh and try again.';
                                return;
                            }
                            if (r.status === 422 && !data.results) {
                                this.modalError = data.message || 'Validation failed. Check your selections.';
                                return;
                            }
                            this.activeModal = '';
                            this.bulkReport = data;
                            this.selectedIds = [];
                            this.showSelectionDetails = false;
                            if (this.scanResult && processedIds.includes(Number(this.scanResult.id)) && data.succeeded > 0) {
                                this.clearScan();
                            }
                            this.refreshWorkspaceData();
                        }).catch(() => {
                            this.bulkBusy = false;
                            this.modalError = 'Network error. Please check your connection and try again.';
                        });
                    },

                    clearScan() {
                        this.scanError = '';
                        this.scanMessage = '';
                        this.scanWarningType = '';
                        this.scanConditionWarning = '';
                        this.scanCanConfirm = false;
                        this.scanAlready = false;
                        this.scanResult = null;
                    },

                    lookupScan() {
                        if (this.scanBusy) return;
                        const code = String(this.scanInput || '').trim().toUpperCase();
                        this.clearScan();
                        if (!code) {
                            this.scanError = 'Enter or scan a tracking number.';
                            return;
                        }
                        if (!/^TRK-\d{8}-[A-Z0-9]{4}$/.test(code)) {
                            this.scanError = '"' + code + '" is not a valid tracking number (expected TRK-YYYYMMDD-XXXX).';
                            return;
                        }

                        this.scanBusy = true;
                        fetch(this.scanLookupUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken(),
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({ tracking_number: code }),
                        }).then(async r => {
                            let data = null;
                            try { data = await r.json(); } catch (e) { data = null; }
                            this.scanBusy = false;
                            if (!data) {
                                this.scanError = 'Could not read scan lookup response.';
                                return;
                            }
                            this.scanWarningType = data.warning_type || '';
                            this.scanConditionWarning = data.condition_warning || '';
                            this.scanResult = data.delivery || null;
                            if (this.scanResult) {
                                this.upsertParcelFromScan(this.scanResult);
                            }
                            this.scanAlready = !!data.already;
                            this.scanCanConfirm = !!data.can_confirm;
                            if (data.ok) {
                                this.scanMessage = data.message || '';
                            } else {
                                this.scanError = data.message || 'Parcel cannot be scanned in its current state.';
                            }
                        }).catch(() => {
                            this.scanBusy = false;
                            this.scanError = 'Could not reach the server. Please try again.';
                        });
                    },

                    confirmScan() {
                        if (this.scanBusy || !this.scanCanConfirm || !this.scanResult) return;
                        this.scanBusy = true;
                        this.scanError = '';
                        fetch(this.scanVerifyUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken(),
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({ tracking_number: this.scanResult.tracking_number }),
                        }).then(async r => {
                            let data = null;
                            try { data = await r.json(); } catch (e) { data = null; }
                            this.scanBusy = false;
                            if (!data) {
                                this.scanError = 'Could not read scan verification response.';
                                return;
                            }
                            if (data.delivery) {
                                this.scanResult = data.delivery;
                                this.upsertParcelFromScan(this.scanResult);
                            }
                            this.scanWarningType = data.warning_type || '';
                            this.scanConditionWarning = data.condition_warning || this.scanConditionWarning;
                            if (data.ok) {
                                this.scanCanConfirm = false;
                                this.scanAlready = !!data.already;
                                this.scanMessage = data.message || 'Parcel scanned and verified.';
                                this.refreshWorkspaceData();
                            } else {
                                this.scanError = data.message || 'Parcel verification failed.';
                            }
                        }).catch(() => {
                            this.scanBusy = false;
                            this.scanError = 'Could not reach the server. Please try again.';
                        });
                    },
                };
            };
        </script>
    @endpush
</x-app-layout>
