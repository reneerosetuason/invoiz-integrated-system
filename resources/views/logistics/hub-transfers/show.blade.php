<x-app-layout>
    <div class="space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div class="flex items-start gap-3">
                <a href="{{ route('hub-transfers.index') }}" class="btn btn-outline btn-sm" aria-label="Back to hub transfers">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <span>Back</span>
                </a>
                <div>
                    <h1 class="text-2xl font-bold text-[#1B1B1E] font-mono">{{ $transfer->reference }}</h1>
                    <p class="text-xs text-[#6E6E73] mt-0.5">
                        <strong class="text-[#1B1B1E]">{{ $transfer->origin?->name }}</strong>
                        <span class="mx-1">&rarr;</span>
                        <strong class="text-[#1B1B1E]">{{ $transfer->destination?->name }}</strong>
                    </p>
                </div>
            </div>
            <div>
                <x-status-badge :status="$transfer->status" />
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="card p-4">
                <div class="text-[10px] font-bold uppercase tracking-wider text-[#6E6E73]">Truck &amp; Driver</div>
                <div class="mt-1 font-bold text-sm text-[#1B1B1E]">{{ $transfer->truck?->plate_number }} · {{ $transfer->truck?->name ?: $transfer->truck?->vehicle_type }}</div>
                <div class="text-xs text-[#6E6E73] mt-0.5">Driver: <strong class="text-[#1B1B1E]">{{ $transfer->driver?->name }}</strong></div>
            </div>
            <div class="card p-4">
                <div class="text-[10px] font-bold uppercase tracking-wider text-[#6E6E73]">Manifest Status</div>
                <div class="mt-1 font-bold text-sm text-[#1B1B1E]">{{ $transfer->parcels->where('loading_status','loaded')->count() }} loaded / {{ $transfer->parcels->where('loading_status','!=','removed')->count() }} expected</div>
                <div class="text-xs text-[#6E6E73] mt-0.5">{{ $transfer->parcels->where('receiving_status','received')->count() }} received · {{ $transfer->parcels->where('receiving_status','missing')->count() }} missing</div>
            </div>
            <div class="card p-4">
                <div class="text-[10px] font-bold uppercase tracking-wider text-[#6E6E73]">Schedule Timeline</div>
                <div class="mt-1 text-xs text-[#1B1B1E]">Planned: {{ $transfer->planned_dispatch_at?->format('M d, Y H:i') ?? '—' }}</div>
                <div class="text-xs text-[#6E6E73]">Dispatched: {{ $transfer->dispatched_at?->format('M d, Y H:i') ?? '—' }}</div>
                <div class="text-xs text-[#6E6E73]">Arrived: {{ $transfer->arrived_at?->format('M d, Y H:i') ?? '—' }}</div>
            </div>
        </div>

        @if($errors->any())
            <div class="card p-4 bg-red-50 border-red-200 text-red-800 text-xs">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- Manifest Table --}}
        <div class="card overflow-hidden">
            <div class="p-4 border-b border-[#E8E6E0] flex items-center justify-between">
                <h2 class="text-sm font-bold text-[#1B1B1E] uppercase tracking-wide">Parcel manifest</h2>
                <span class="text-xs text-[#6E6E73]">{{ $transfer->parcels->count() }} total parcels</span>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tracking #</th>
                            <th>Loading</th>
                            <th>Receiving</th>
                            <th>Condition / Exception</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transfer->parcels as $entry)
                            <tr>
                                <td class="font-mono font-bold text-[#16697A]">
                                    <a href="{{ route('deliveries.show', $entry->delivery) }}" class="hover:underline">{{ $entry->delivery?->tracking_number }}</a>
                                </td>
                                <td>
                                    <span class="badge {{ $entry->loading_status === 'loaded' ? 'badge-delivered' : 'badge-inactive' }}">
                                        {{ str($entry->loading_status)->title() }}
                                    </span>
                                    @if($entry->loaded_at)
                                        <div class="text-[10px] text-[#6E6E73] mt-0.5">{{ $entry->loaded_at->format('M d, H:i') }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $entry->receiving_status === 'received' ? 'badge-delivered' : ($entry->receiving_status === 'missing' ? 'badge-cancelled' : 'badge-inactive') }}">
                                        {{ str($entry->receiving_status)->title() }}
                                    </span>
                                </td>
                                <td>
                                    <div class="text-xs text-[#1B1B1E]">
                                        {{ $entry->condition ? str($entry->condition)->title() : '—' }}
                                        @if($entry->exception_reason)
                                            <span class="text-[#6E6E73]">({{ $entry->exception_reason }})</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-right">
                                    @if($entry->loading_status === 'removed')
                                        <span class="text-xs text-[#6E6E73] italic">Removed</span>
                                    @elseif(in_array($transfer->status, ['loading', 'ready_for_dispatch'], true))
                                        <div class="inline-flex items-center justify-end gap-1.5 flex-wrap">
                                            @if($entry->loading_status !== 'loaded')
                                                <form method="POST" action="{{ route('hub-transfers.load', $transfer) }}" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="tracking_number" value="{{ $entry->delivery?->tracking_number }}">
                                                    <button type="submit" class="btn btn-primary btn-xs">Scan Loaded</button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('hub-transfers.parcels.remove', [$transfer, $entry]) }}" class="inline-flex items-center gap-1">
                                                @csrf
                                                @method('DELETE')
                                                <input name="reason" required placeholder="Reason" class="input !py-1 !px-2 !text-xs !w-24">
                                                <button type="submit" class="btn btn-danger btn-xs">Remove</button>
                                            </form>
                                        </div>
                                    @elseif(in_array($transfer->status, ['arrived', 'receiving', 'exception'], true) && $entry->receiving_status === 'pending')
                                        <form method="POST" action="{{ route('hub-transfers.receive', $transfer) }}" class="inline-flex items-center justify-end gap-1.5 flex-wrap">
                                            @csrf
                                            <input type="hidden" name="tracking_number" value="{{ $entry->delivery?->tracking_number }}">
                                            <select name="condition" class="select !py-1 !px-2 !text-xs !w-24">
                                                <option value="good">Good</option>
                                                <option value="damaged">Damaged</option>
                                                <option value="missing">Missing</option>
                                            </select>
                                            <input name="exception_reason" placeholder="Notes..." class="input !py-1 !px-2 !text-xs !w-28">
                                            <button type="submit" class="btn btn-primary btn-xs">Record</button>
                                        </form>
                                    @else
                                        <span class="text-xs text-[#6E6E73]">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Workflow Controls --}}
        <div class="card p-5 space-y-4">
            <h2 class="text-sm font-bold text-[#1B1B1E] uppercase tracking-wide">Transfer Actions &amp; Lifecycle</h2>

            @if($transfer->status === 'on_hold')
                <div class="card p-3 bg-amber-50 border-amber-200 text-amber-800 text-xs">
                    This transfer is currently on hold. Dispatch, arrival, receiving, and completion are paused until resumed.
                </div>
            @endif

            <div class="flex flex-wrap items-center gap-2">
                @if($transfer->status === 'ready_for_dispatch')
                    <form method="POST" action="{{ route('hub-transfers.dispatch', $transfer) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">Confirm Dispatch</button>
                    </form>
                @endif
                @if($transfer->status === 'in_transit')
                    <form method="POST" action="{{ route('hub-transfers.arrive', $transfer) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">Confirm Arrival</button>
                    </form>
                @endif
                @if(in_array($transfer->status, ['arrived', 'receiving', 'exception'], true) && !$transfer->parcels->contains(fn($p) => $p->receiving_status === 'pending'))
                    <form method="POST" action="{{ route('hub-transfers.complete', $transfer) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">Finalize Receiving</button>
                    </form>
                @endif
                @if($transfer->status === 'on_hold')
                    <form method="POST" action="{{ route('hub-transfers.resume', $transfer) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">Resume Transfer</button>
                    </form>
                @elseif(in_array($transfer->status, \App\Models\HubTransfer::ACTIVE_STATUSES, true))
                    <form method="POST" action="{{ route('hub-transfers.hold', $transfer) }}" class="flex flex-wrap items-center gap-2">
                        @csrf
                        <input name="reason" required minlength="3" placeholder="Reason for hold..." class="input !w-56">
                        <button type="submit" class="btn btn-outline text-amber-700 border-amber-300">Hold Transfer</button>
                    </form>
                @endif
            </div>

            @if(in_array($transfer->status, ['loading', 'ready_for_dispatch'], true))
                <div class="grid gap-4 pt-4 border-t border-[#E8E6E0] md:grid-cols-3">
                    <form method="POST" action="{{ route('hub-transfers.cancel', $transfer) }}" class="space-y-2">
                        @csrf
                        <label class="field-label">Cancel Transfer</label>
                        <input name="reason" required minlength="3" placeholder="Cancellation reason..." class="input">
                        <button type="submit" class="btn btn-danger btn-sm w-full">Cancel Before Dispatch</button>
                    </form>
                    <form method="POST" action="{{ route('hub-transfers.reroute', $transfer) }}" class="space-y-2">
                        @csrf
                        <label class="field-label">Correct Destination Hub</label>
                        <select name="destination_center_id" required class="select">
                            @foreach($rerouteHubs as $hub)
                                <option value="{{ $hub->id }}" @selected($hub->id === $transfer->destination_center_id)>{{ $hub->name }}</option>
                            @endforeach
                        </select>
                        <input name="reason" required minlength="3" placeholder="Correction reason..." class="input">
                        <button type="submit" class="btn btn-outline btn-sm w-full">Update Destination</button>
                    </form>
                    <form method="POST" action="{{ route('hub-transfers.reassign', $transfer) }}" class="space-y-2">
                        @csrf
                        <label class="field-label">Reassign Fleet Truck &amp; Driver</label>
                        <select name="truck_id" required class="select">
                            @foreach($fleetOptions as $option)
                                <option value="{{ $option['truck']->id }}" @selected($option['truck']->id === $transfer->truck_id)>{{ $option['truck']->plate_number }} · {{ $option['driver']->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-outline btn-sm w-full">Reassign Truck &amp; Driver</button>
                    </form>
                </div>
            @endif
        </div>

        {{-- Event History Timeline --}}
        <div class="card p-5">
            <h2 class="text-sm font-bold text-[#1B1B1E] uppercase tracking-wide mb-1">Transfer History</h2>
            <p class="text-xs text-[#6E6E73] mb-4">Manual staff/driver updates and automatic system events.</p>
            <ol class="space-y-3">
                @foreach($transfer->events->sortBy('created_at') as $event)
                    <li class="border-l-2 border-[#16697A] pl-3">
                        <div class="text-xs font-bold text-[#1B1B1E]">{{ str($event->event_type)->replace('_',' ')->title() }}</div>
                        <div class="text-[11px] text-[#6E6E73] mt-0.5">
                            {{ $event->created_at->format('M d, Y H:i') }} &middot;
                            {{ $event->actor_name ?: 'System (automatic)' }}
                            @if($event->notes) &middot; {{ $event->notes }} @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </div>
</x-app-layout>
