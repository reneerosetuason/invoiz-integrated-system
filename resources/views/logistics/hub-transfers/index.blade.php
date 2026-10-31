<x-app-layout>
    <div class="space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-[#16697A]">{{ $company?->name ?? 'All logistics companies' }}</p>
                <h1 class="text-2xl font-bold text-[#1B1B1E] mt-0.5">Hub Transfers</h1>
                <p class="text-xs text-[#6E6E73] mt-0.5">Move parcels between approved Logistics Hubs.</p>
            </div>
            <a href="{{ route('hub-transfers.create') }}" class="btn btn-primary">
                <x-icon name="plus" class="w-4 h-4" />
                Create Transfer
            </a>
        </div>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-8">
            @foreach(['pending'=>'Pending','loading'=>'Loading','ready_for_dispatch'=>'Ready','in_transit'=>'In Transit','arrived'=>'Arrived','receiving'=>'Receiving','received'=>'Completed','exception'=>'Exceptions'] as $key=>$label)
                <div class="card p-4">
                    <div class="text-[11px] font-bold text-[#6E6E73] uppercase tracking-wide">{{ $label }}</div>
                    <div class="mt-1 text-2xl font-bold text-[#1B1B1E]">{{ $counts[$key] ?? 0 }}</div>
                </div>
            @endforeach
        </div>

        <div class="card p-4">
            <form method="GET" class="flex flex-col sm:flex-row sm:items-end gap-3">
                <div class="flex-1">
                    <label class="field-label">Search Transfers</label>
                    <input name="search" value="{{ request('search') }}" placeholder="Reference or tracking number..." class="input">
                </div>
                <div class="sm:w-56">
                    <label class="field-label">Status</label>
                    <select name="status" class="select">
                        <option value="">All statuses</option>
                        @foreach(['pending','loading','ready_for_dispatch','in_transit','arrived','receiving','received','exception','cancelled'] as $status)
                            <option value="{{ $status }}" @selected(request('status')===$status)>{{ str($status)->replace('_',' ')->title() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    @if(request('search') || request('status'))
                        <a href="{{ route('hub-transfers.index') }}" class="btn btn-ghost">Clear</a>
                    @endif
                </div>
            </form>
        </div>

        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Route</th>
                            <th>Truck / Driver</th>
                            <th>Parcels</th>
                            <th>Status</th>
                            <th>Dispatch</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transfers as $transfer)
                            <tr>
                                <td class="font-mono font-bold text-[#16697A]">
                                    <a href="{{ route('hub-transfers.show', $transfer) }}" class="hover:underline">{{ $transfer->reference }}</a>
                                </td>
                                <td>
                                    <span class="font-medium text-[#1B1B1E]">{{ $transfer->origin?->name }}</span>
                                    <span class="text-[#6E6E73]">&rarr;</span>
                                    <span class="font-medium text-[#1B1B1E]">{{ $transfer->destination?->name }}</span>
                                </td>
                                <td>
                                    <div class="font-medium text-[#1B1B1E]">{{ $transfer->truck?->plate_number ?? '—' }}</div>
                                    <div class="text-[11px] text-[#6E6E73]">{{ $transfer->driver?->name ?? '—' }}</div>
                                </td>
                                <td>
                                    <span class="font-semibold text-[#1B1B1E]">{{ $transfer->parcels_count }}</span>
                                </td>
                                <td>
                                    <x-status-badge :status="$transfer->status" />
                                </td>
                                <td class="text-xs text-[#6E6E73]">
                                    {{ $transfer->dispatched_at?->format('M d, Y H:i') ?? '—' }}
                                </td>
                                <td class="text-right">
                                    <a href="{{ route('hub-transfers.show', $transfer) }}" class="btn btn-soft btn-xs">Details</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-12 text-center">
                                    <p class="text-sm font-bold text-[#1B1B1E]">No hub transfers found</p>
                                    <p class="mt-1 text-xs text-[#6E6E73]">Create a hub transfer to move sorted parcels between Logistics Hubs.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($transfers->total() > 0)
                <div class="p-4 border-t border-[#E8E6E0] flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-[#6E6E73]">
                    <p>Showing <strong class="text-[#1B1B1E]">{{ $transfers->firstItem() }}–{{ $transfers->lastItem() }}</strong> of <strong class="text-[#1B1B1E]">{{ $transfers->total() }}</strong> transfers</p>
                    <p>Page {{ $transfers->currentPage() }} of {{ $transfers->lastPage() }}</p>
                </div>
            @endif
        </div>

        <div>{{ $transfers->links() }}</div>
    </div>
</x-app-layout>
