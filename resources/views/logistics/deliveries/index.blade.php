<x-app-layout>
    @include('logistics.partials.delivery-theme')
    <div class="logistics-deliveries space-y-5">
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
            ['label' => 'Exceptions', 'status' => 'exceptions'],
            ['label' => 'Lost', 'status' => 'lost'],
            ['label' => 'Damaged', 'status' => 'damaged'],
            ['label' => 'Returned to Seller', 'status' => 'returned_to_seller'],
            ['label' => 'Cancelled', 'status' => 'cancelled'],
        ];
        $tabUrl = fn ($status) => route('deliveries.index', collect(request()->query())->except('page')->merge(['status' => $status])->filter(fn ($v) => $v !== null && $v !== '')->all());
        $hasFilters = trim((string) request('search')) !== '' || $currentStatus || request('date_from') || request('date_to') || request('rider_id') || request('vehicle_type') || request('center_id') || request('service_area_id') || request('priority');
    @endphp

    <div x-data="{ archiveOpen: false, archiveUrl: '' }" class="space-y-4">
        {{-- Page Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-[#1B1B1E]">Deliveries</h1>
                <p class="text-xs text-[#6E6E73] mt-0.5">Track each shipment, assign riders and keep deliveries moving.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('deliveries.archived') }}" class="btn btn-outline">
                    <x-icon name="archive" class="w-4 h-4" />
                    Archived ({{ $archivedCount }})
                </a>
                <a href="{{ route('deliveries.export', collect(request()->query())->except(['page', 'per_page'])->all()) }}" class="btn btn-outline">
                    <x-icon name="download" class="w-4 h-4" />
                    Export CSV
                </a>
                <a href="{{ route('deliveries.create') }}" class="btn btn-primary">
                    <x-icon name="plus" class="w-4 h-4" />
                    New Delivery
                </a>
            </div>
        </div>

        {{-- Status Tabs --}}
        <div class="card p-2 flex flex-wrap gap-1.5">
            @foreach($tabs as $tab)
                @php $active = ($currentStatus === $tab['status']) || (!$currentStatus && !$tab['status']); @endphp
                <a href="{{ $tabUrl($tab['status']) }}"
                   @if($active) aria-current="page" @endif
                   class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ $active ? 'bg-[#16697A] text-white' : 'text-[#6E6E73] hover:bg-[#F0EEE9] hover:text-[#1B1B1E]' }}">
                    {{ $tab['label'] }}
                </a>
            @endforeach
        </div>

        {{-- Filter Bar --}}
        @include('logistics.partials.delivery-filters')

        {{-- Count & Per Page --}}
        <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-[#6E6E73]">
            <p><strong class="text-[#1B1B1E]">{{ number_format($deliveries->total()) }}</strong> {{ $deliveries->total() === 1 ? 'delivery' : 'deliveries' }}{{ $hasFilters ? ' matching your filters' : ' in active records' }}</p>
            <x-per-page route="deliveries.index"/>
        </div>

        {{-- Bulk Assign Form --}}
        @if(Auth::user()->canManageRiders() && $filterRiders->isNotEmpty())
            <form id="bulk-assign-form" method="POST" action="{{ route('deliveries.bulk-assign') }}" class="card p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                @csrf
                <div>
                    <p class="text-sm font-bold text-[#1B1B1E]">Bulk Assign Selected Deliveries</p>
                    <p class="text-xs text-[#6E6E73]">Select up to 25 deliveries below, then choose a rider.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <select name="rider_id" aria-label="Rider for selected deliveries" required class="select !w-52">
                        <option value="">Select rider</option>
                        @foreach($filterRiders as $rider)
                            <option value="{{ $rider->id }}">{{ $rider->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-primary">Assign Selected</button>
                    <span id="bulk-selected-count" aria-live="polite" class="text-xs font-semibold text-[#6E6E73]">0 selected</span>
                </div>
            </form>
        @endif

        @if(session('bulk_assignment_results'))
            <div class="card p-4">
                <p class="text-sm font-bold text-[#1B1B1E]">Bulk Assignment Results</p>
                <ul class="mt-2 space-y-1.5">
                    @foreach(session('bulk_assignment_results') as $result)
                        <li class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 rounded-lg bg-[#FAF9F6] px-3 py-2 text-xs border border-[#E8E6E0]">
                            <span class="font-semibold text-[#1B1B1E]">
                                Delivery #{{ $result['delivery_id'] }}
                                @if(!empty($result['tracking_number']))
                                    <span class="text-[#6E6E73]">({{ $result['tracking_number'] }})</span>
                                @endif
                            </span>
                            <span class="flex items-center gap-2">
                                <x-status-badge :status="$result['status'] ?? 'failed'" />
                                <span class="text-[#6E6E73]">{{ $result['message'] ?? '' }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Deliveries Table (No Horizontal Scroll) --}}
        <div class="card overflow-hidden">
            <table class="table">
                <thead>
                    <tr>
                        @if(Auth::user()->canManageRiders() && $filterRiders->isNotEmpty())
                            <th class="w-10"><input id="bulk-select-all" type="checkbox" aria-label="Select all assignable deliveries"></th>
                        @endif
                        <th>Tracking #</th>
                        <th>Recipient</th>
                        <th>Rider &amp; Center</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($deliveries as $delivery)
                        <tr>
                            @if(Auth::user()->canManageRiders() && $filterRiders->isNotEmpty())
                                <td>
                                    @if(in_array($delivery->status, ['waiting_for_rider', 'assigned', 'delivery_failed'], true))
                                        <input form="bulk-assign-form" type="checkbox" name="delivery_ids[]" value="{{ $delivery->id }}" aria-label="Select {{ $delivery->tracking_number }}" class="bulk-delivery-checkbox">
                                    @endif
                                </td>
                            @endif
                            <td>
                                <a href="{{ route('deliveries.show', $delivery) }}" class="font-bold text-[#16697A] hover:underline">{{ $delivery->tracking_number }}</a>
                                <div class="text-[11px] text-[#6E6E73]">{{ $delivery->created_at?->format('M d, Y') ?? '—' }}</div>
                            </td>
                            <td>
                                <div class="font-semibold text-[#1B1B1E]">{{ $delivery->recipient_name ?: '—' }}</div>
                                @if($delivery->sender_name)
                                    <div class="text-[11px] text-[#6E6E73]">From: {{ $delivery->sender_name }}</div>
                                @endif
                            </td>
                            <td>
                                @if($delivery->rider)
                                    <div class="font-semibold text-[#1B1B1E]">{{ $delivery->rider->name }}</div>
                                @else
                                    <div class="text-xs text-[#6E6E73]">Unassigned</div>
                                @endif
                                @if($delivery->logisticsCenter || $delivery->serviceArea)
                                    <div class="text-[11px] text-[#6E6E73]">
                                        {{ collect([$delivery->logisticsCenter?->name, $delivery->serviceArea?->name])->filter()->implode(' · ') }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                <x-status-badge :status="$delivery->status" />
                                @if(($delivery->priority ?? 'normal') !== 'normal')
                                    <div class="text-[10px] font-bold uppercase text-amber-700 mt-1">{{ ucfirst($delivery->priority) }} priority</div>
                                @endif
                            </td>
                            <td class="text-right">
                                <div class="inline-flex items-center justify-end gap-1.5">
                                    <a href="{{ route('deliveries.show', $delivery) }}" class="btn btn-soft btn-xs">View</a>
                                    <a href="{{ route('deliveries.edit', $delivery) }}" class="btn btn-outline btn-xs">Edit</a>
                                    <button type="button" data-archive-url="{{ route('deliveries.archive', $delivery) }}"
                                            @click="archiveUrl = $el.dataset.archiveUrl; archiveOpen = true"
                                            class="btn btn-ghost btn-xs">Archive</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ Auth::user()->canManageRiders() && $filterRiders->isNotEmpty() ? 6 : 5 }}" class="text-center py-12">
                                <div class="text-sm font-bold text-[#1B1B1E]">No deliveries found</div>
                                <p class="text-xs text-[#6E6E73] mt-1">{{ $hasFilters ? 'Try a different search or clear your filters.' : 'New deliveries will appear here.' }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if($deliveries->count() > 0)
                <div class="p-4 border-t border-[#E8E6E0] flex flex-wrap items-center justify-between gap-2 text-xs text-[#6E6E73]">
                    <p>Showing <strong class="text-[#1B1B1E]">{{ $deliveries->firstItem() }}&ndash;{{ $deliveries->lastItem() }}</strong> of <strong class="text-[#1B1B1E]">{{ number_format($deliveries->total()) }}</strong></p>
                    {{ $deliveries->onEachSide(2)->links() }}
                </div>
            @endif
        </div>

        {{-- Archive Confirmation Modal --}}
        <div x-show="archiveOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" x-transition.opacity>
            <div class="absolute inset-0 bg-black/40" @click="archiveOpen = false; archiveUrl = ''"></div>
            <form :action="archiveUrl" method="POST" class="relative card p-6 w-full max-w-md shadow-xl" x-transition>
                @csrf
                <h4 class="text-base font-bold text-[#1B1B1E]">Archive Delivery?</h4>
                <p class="text-xs text-[#6E6E73] mt-1">This delivery will be moved from active deliveries to the archive for records and auditing.</p>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" @click="archiveOpen = false; archiveUrl = ''" class="btn btn-outline">Cancel</button>
                    <button type="submit" class="btn btn-primary">Archive Delivery</button>
                </div>
            </form>
        </div>
    </div>

    @if(Auth::user()->canManageRiders() && $filterRiders->isNotEmpty())
        <script src="{{ asset('assets/logistics/js/deliveries/index.js') }}"></script>
    @endif
    </div>
</x-app-layout>
