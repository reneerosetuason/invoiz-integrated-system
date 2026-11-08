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
            ['label' => 'Parcels needing action', 'value' => $counts['action_parcels'], 'hint' => 'Receipt, scanning, hub transfer, or last-mile assignment', 'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
            ['label' => 'Incoming transfers', 'value' => $counts['incoming_transfers'], 'hint' => 'Dispatched, arrived, or under receiving', 'icon' => 'M4 7h11m0 0-3-3m3 3-3 3M20 17H9m0 0 3 3m-3-3 3-3M5 12h14'],
            ['label' => 'In transit', 'value' => $counts['in_transit'], 'hint' => 'Trucks currently on the road', 'icon' => 'M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1'],
            ['label' => 'Awaiting receiving', 'value' => $counts['awaiting_receiving'], 'hint' => 'Physically arrived; custody not yet accepted', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['label' => 'Ready for sorting', 'value' => $counts['ready_sorting'], 'hint' => 'Received or scanned at this scope', 'icon' => 'M7 7h10M7 12h10M7 17h6'],
            ['label' => 'Ready for rider', 'value' => $counts['ready_rider'], 'hint' => 'Sorted for this hub, no rider yet', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2a3 3 0 00-5.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2a3 3 0 015.356-1.857M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
            ['label' => 'Damaged or missing', 'value' => $counts['exceptions'], 'hint' => 'Manifest exceptions still on record', 'icon' => 'M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z'],
        ];
    @endphp

    <div class="space-y-4">
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
                    <a href="{{ route('deliveries.scan-page') }}" class="btn btn-primary">Scan Parcel</a>
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
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-7">
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

        <div class="grid gap-4 xl:grid-cols-3">
            {{-- Action queue: next valid step per parcel, resolved from backend state. --}}
            <div class="card xl:col-span-2 overflow-hidden">
                <div class="flex items-center justify-between border-b border-[#E8E6E0] px-4 py-3">
                    <div>
                        <h2 class="text-sm font-bold uppercase tracking-wide text-[#1B1B1E]">Parcels needing action here</h2>
                        <p class="mt-0.5 text-[11px] text-[#6E6E73]">Only the next valid step is offered; arrival alone never accepts custody.</p>
                    </div>
                    <span class="badge badge-inactive">{{ $actionParcels->count() }} shown</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="table w-full">
                        <thead>
                            <tr>
                                <th class="min-w-[150px]">Tracking</th>
                                <th class="min-w-[170px]">Current state</th>
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
                                @endphp
                                <tr class="hover:bg-[#FCFBF9]">
                                    <td class="align-middle">
                                        <a href="{{ route('deliveries.show', $delivery) }}" class="font-mono text-xs font-bold text-[#16697A] hover:underline">{{ $delivery->tracking_number }}</a>
                                        <div class="mt-0.5 text-[10px] text-[#8E8E93]">{{ $delivery->recipient_city ?: $delivery->recipient_name ?: '—' }}</div>
                                    </td>
                                    <td class="align-middle">
                                        <span class="inline-flex items-center rounded border px-2 py-0.5 text-[10px] font-bold {{ $tone[$workflow['tone']] ?? $tone['gray'] }}">{{ $workflow['label'] }}</span>
                                    </td>
                                    <td class="align-middle text-xs">
                                        <div class="font-medium text-[#1B1B1E]">{{ $delivery->logisticsCenter?->name ?? 'In transit' }}</div>
                                        @if($delivery->destinationCenter)
                                            <div class="text-[10px] text-[#6E6E73]">&rarr; {{ $delivery->destinationCenter->name }}</div>
                                        @endif
                                        <div class="text-[10px] text-[#6E6E73]">{{ $workflow['custody'] }}</div>
                                        @php
                                            $responsible = $delivery->rider?->name ?? $workflow['transfer']?->driver?->name;
                                        @endphp
                                        @if($responsible)
                                            <div class="text-[10px] text-[#8E8E93]">{{ $delivery->rider ? 'Rider' : 'Transport driver' }}: {{ $responsible }}</div>
                                        @endif
                                    </td>
                                    <td class="align-middle">
                                        @if($next['enabled'] && $next['url'])
                                            <a href="{{ $next['url'] }}" class="btn btn-primary btn-xs whitespace-nowrap">{{ $next['label'] }}</a>
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
                                    <td colspan="4" class="py-10 text-center text-xs text-[#6E6E73]">No parcels are waiting on this hub right now.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
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
                                        <div class="text-[10px] text-[#6E6E73]">{{ $transfer->truck?->plate_number ?? 'No truck' }}</div>
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
                                        <div class="text-[10px] text-[#6E6E73]">{{ $transfer->driver?->name ?? 'No driver' }}</div>
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
</x-app-layout>
