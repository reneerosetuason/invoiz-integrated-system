<x-app-layout>
    @php
        $currentStatus = request('status');
        $tabs = [
            ['label' => 'All', 'status' => null],
            ['label' => 'Waiting for Rider', 'status' => 'waiting_for_rider'],
            ['label' => 'Assigned', 'status' => 'assigned'],
            ['label' => 'Picked Up', 'status' => 'picked_up'],
            ['label' => 'Out for Delivery', 'status' => 'out_for_delivery'],
            ['label' => 'Delivered', 'status' => 'delivered'],
            ['label' => 'Failed', 'status' => 'delivery_failed'],
            ['label' => 'Cancelled', 'status' => 'cancelled'],
        ];
        $tabUrl = fn ($status) => route('deliveries.index', collect(request()->query())->except('page')->merge(['status' => $status])->filter(fn ($v) => $v !== null && $v !== '')->all());
        $hasFilters = trim((string) request('search')) !== '' || $currentStatus || request('date_from') || request('date_to') || request('rider_id') || request('vehicle_type') || request('center_id') || request('service_area_id') || request('priority');
    @endphp

    <div x-data="{ archiveOpen: false, archiveUrl: '' }" class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Deliveries</h1>
                <p class="text-sm text-gray-500 mt-1">Manage and track all delivery orders</p>
            </div>
            <div class="flex items-center gap-3">
                @if($archivedCount > 0)
                    <a href="{{ route('deliveries.archived') }}"
                       class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-gray-700 bg-white border border-gray-200 shadow-sm hover:bg-gray-50 hover:border-gray-300 transition">
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                        Archived
                        <span class="bg-gray-200 text-gray-700 text-xs font-bold px-2 py-0.5 rounded-full">{{ $archivedCount }}</span>
                    </a>
                @endif
                <a href="{{ route('deliveries.create') }}"
                   class="inline-flex items-center justify-center gap-2 bg-teal hover:bg-teal-dark text-white font-semibold px-5 py-2.5 rounded-xl transition shadow-sm">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    New Delivery
                </a>
            </div>
        </div>

    {{-- Status Filter Tabs (preserve search/dates/sort/per-page) --}}
    <div class="flex flex-wrap gap-2 mb-4">
        @foreach($tabs as $tab)
            <a href="{{ $tabUrl($tab['status']) }}"
               class="px-4 py-2 rounded-full text-sm font-medium transition whitespace-nowrap {{ ($currentStatus === $tab['status']) || (!$currentStatus && !$tab['status']) ? 'bg-teal text-white shadow-sm' : 'bg-white border border-gray-200 text-gray-600 hover:border-teal hover:text-teal-dark' }}">
                {{ $tab['label'] }}
            </a>
        @endforeach
    </div>

    {{-- Search + Filters toolbar --}}
    <div class="flex flex-col xl:flex-row xl:items-start gap-3 mb-6">
        <form method="GET" action="{{ route('deliveries.index') }}" class="flex flex-col lg:flex-row lg:flex-wrap gap-3 flex-1">
            <div class="relative flex-1 min-w-[240px]">
                <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </span>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search ID, order #, tracking #, sender, recipient, rider, phone, or address..."
                       class="w-full bg-white border border-gray-200 rounded-xl pl-10 pr-4 py-2 text-sm focus:border-teal focus:ring-teal shadow-sm placeholder-gray-400">
            </div>

            <input type="date" name="date_from" value="{{ request('date_from') }}" max="{{ request('date_to') }}" title="From date"
                   class="bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm text-gray-600 focus:border-teal focus:ring-teal shadow-sm">
            <span class="hidden lg:flex items-center text-xs text-gray-400">to</span>
            <input type="date" name="date_to" value="{{ request('date_to') }}" min="{{ request('date_from') }}" title="To date"
                   class="bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm text-gray-600 focus:border-teal focus:ring-teal shadow-sm">

            <select name="rider_id" title="Filter by rider"
                    class="bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm text-gray-600 focus:border-teal focus:ring-teal shadow-sm max-w-[160px]">
                <option value="">All riders</option>
                @foreach($filterRiders as $r)
                    <option value="{{ $r->id }}" {{ request('rider_id') == $r->id ? 'selected' : '' }}>{{ $r->name }}</option>
                @endforeach
            </select>

            <select name="vehicle_type" title="Filter by vehicle type"
                    class="bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm text-gray-600 focus:border-teal focus:ring-teal shadow-sm">
                <option value="">All vehicles</option>
                @foreach($vehicleTypes as $vt)
                    <option value="{{ $vt }}" {{ request('vehicle_type') === $vt ? 'selected' : '' }}>{{ ucfirst($vt) }}</option>
                @endforeach
            </select>

            @if(Auth::user()->isAdmin())
                <select name="center_id" title="Filter by logistics center"
                        class="bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm text-gray-600 focus:border-teal focus:ring-teal shadow-sm max-w-[200px]">
                    <option value="">All centers</option>
                    @foreach($filterCenters as $c)
                        <option value="{{ $c->id }}" {{ (int) request('center_id') === $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
            @endif

            <select name="service_area_id" title="Filter by service area"
                    class="bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm text-gray-600 focus:border-teal focus:ring-teal shadow-sm max-w-[200px]">
                <option value="">All service areas</option>
                @foreach($filterServiceAreas as $sa)
                    <option value="{{ $sa->id }}" {{ (int) request('service_area_id') === $sa->id ? 'selected' : '' }}>{{ $sa->name }}</option>
                @endforeach
            </select>

            <select name="priority" title="Filter by priority"
                    class="bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm text-gray-600 focus:border-teal focus:ring-teal shadow-sm">
                <option value="">All priorities</option>
                @foreach($filterPriorities as $p)
                    <option value="{{ $p }}" {{ request('priority') === $p ? 'selected' : '' }}>{{ ucfirst($p) }}</option>
                @endforeach
            </select>

            <button type="submit" class="inline-flex items-center justify-center gap-2 px-5 py-2 bg-teal hover:bg-teal-dark text-white text-sm font-semibold rounded-xl transition shadow-sm whitespace-nowrap">
                Apply
            </button>
            @if($hasFilters)
                <a href="{{ route('deliveries.index') }}" title="Clear all filters"
                   class="inline-flex items-center justify-center px-4 py-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-500 text-sm font-medium rounded-xl transition whitespace-nowrap">
                    Clear
                </a>
            @endif
        </form>

        <x-per-page route="deliveries.index"/>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <x-th-sort field="tracking_number" label="Tracking #"/>
                        <x-th-sort field="sender" label="Sender"/>
                        <x-th-sort field="recipient" label="Recipient"/>
                        <x-th-sort field="rider" label="Rider"/>
                        <x-th-sort field="destination" label="Destination"/>
                        <x-th-sort field="service_area" label="Service Area"/>
                        <x-th-sort field="center" label="Center"/>
                        <x-th-sort field="priority" label="Priority"/>
                        <x-th-sort field="amount" label="Amount"/>
                        <x-th-sort field="status" label="Status"/>
                        <x-th-sort field="date" label="Date"/>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse($deliveries as $delivery)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-mono font-semibold text-gray-900">
                                {{ $delivery->tracking_number }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $delivery->sender_name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $delivery->recipient_name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                @if($delivery->rider)
                                    {{ $delivery->rider->name }}
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-full bg-gray-100 text-gray-500 ring-1 ring-inset ring-gray-200">Unassigned</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $delivery->destinationCenter?->name ?? '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $delivery->serviceArea?->name ?? '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $delivery->logisticsCenter?->name ?? '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php $prio = $delivery->priority ?? 'normal'; @endphp
                                <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full {{ $prio === 'urgent' ? 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-200' : ($prio === 'high' ? 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200' : 'bg-gray-100 text-gray-600 ring-1 ring-inset ring-gray-200') }}">
                                    {{ ucfirst($prio) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                @if($delivery->transaction)
                                    ₱{{ number_format((float) $delivery->transaction->amount, 2) }}
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <x-status-badge :status="$delivery->status" />
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $delivery->created_at->format('M d, Y') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-3">
                                <a href="{{ route('deliveries.show', $delivery) }}" class="text-teal hover:text-teal-dark font-semibold">View</a>
                                <a href="{{ route('deliveries.edit', $delivery) }}" class="text-gray-600 hover:text-gray-900">Edit</a>
                                <button type="button"
                                        data-archive-url="{{ route('deliveries.archive', $delivery) }}"
                                        @click="archiveUrl = $el.dataset.archiveUrl; archiveOpen = true"
                                        class="text-gray-500 hover:text-gray-900 font-semibold">
                                    Archive
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center justify-center text-gray-400">
                                    <svg class="h-16 w-16 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                    <p class="text-base font-semibold text-gray-700">No results found</p>
                                    @if(trim((string) request('search')) !== '')
                                        <p class="text-sm text-gray-500 mt-1">We couldn't find any deliveries matching
                                            <span class="font-semibold text-gray-800">"{{ request('search') }}"</span>.
                                        </p>
                                    @else
                                        <p class="text-sm text-gray-500 mt-1">No deliveries match your current filters.</p>
                                    @endif
                                    @if($hasFilters)
                                        <a href="{{ route('deliveries.index') }}" class="mt-4 inline-flex items-center px-4 py-2 bg-white border border-gray-200 hover:border-teal hover:text-teal-dark rounded-xl text-sm font-semibold text-gray-600 transition">
                                            Clear Search &amp; Filters
                                        </a>
                                    @else
                                        <a href="{{ route('deliveries.create') }}" class="mt-4 text-sm font-semibold text-teal hover:text-teal-dark">
                                            Create a new delivery
                                        </a>
                                    @endif
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
                    of <span class="font-semibold text-gray-700">{{ $deliveries->total() }}</span> deliveries
                </p>
                <p class="text-xs text-gray-400">Page {{ $deliveries->currentPage() }} of {{ $deliveries->lastPage() }}</p>
            </div>
        @endif
    </div>

    <div class="mt-4">
        {{ $deliveries->onEachSide(2)->links() }}
    </div>

    {{-- Archive Confirmation Modal --}}
    <div x-show="archiveOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" x-transition.opacity>
        <div class="absolute inset-0 bg-black/40" @click="archiveOpen = false; archiveUrl = ''"></div>
        <form :action="archiveUrl" method="POST" class="relative bg-white rounded-2xl shadow-xl w-full max-w-md" x-transition>
            @csrf
            <div class="px-6 py-5 border-b border-gray-100">
                <h4 class="font-bold text-gray-900">Archive Delivery?</h4>
                <p class="text-sm text-gray-500 mt-1">This delivery will be removed from active deliveries but preserved in the archive for records and auditing.</p>
            </div>
            <div class="px-6 py-4 bg-gray-50 rounded-b-2xl flex justify-end gap-3">
                <button type="button" @click="archiveOpen = false; archiveUrl = ''"
                        class="px-5 py-2.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-xl transition">
                    Cancel
                </button>
                <button type="submit"
                        class="px-5 py-2.5 bg-gray-800 hover:bg-black text-white text-sm font-bold rounded-xl transition">
                    Archive Delivery
                </button>
            </div>
        </form>
    </div>
</div>
</x-app-layout>
