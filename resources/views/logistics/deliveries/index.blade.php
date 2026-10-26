<x-app-layout>
    @include('logistics.partials.delivery-theme')
    <div class="logistics-deliveries delivery-page-index">
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

    <div x-data="{ archiveOpen: false, archiveUrl: '' }" class="space-y-6">
        <header class="delivery-head">
            <div>
                <h1>Deliveries</h1>
                <p>Track each shipment, assign riders and keep deliveries moving.</p>
            </div>
            <div class="delivery-head-actions">
                <a href="{{ route('deliveries.archived') }}" class="delivery-button">Archived <span class="text-xs">({{ $archivedCount }})</span></a>
                <a href="{{ route('deliveries.export', collect(request()->query())->except(['page', 'per_page'])->all()) }}" class="delivery-button">Export CSV</a>
                <a href="{{ route('deliveries.create') }}" class="delivery-button delivery-button--primary">New delivery</a>
            </div>
        </header>
        <nav class="delivery-tabs" aria-label="Delivery status">
            @foreach($tabs as $tab)
                <a href="{{ $tabUrl($tab['status']) }}"
                   @if(($currentStatus === $tab['status']) || (!$currentStatus && !$tab['status'])) aria-current="page" @endif
                   class="{{ ($currentStatus === $tab['status']) || (!$currentStatus && !$tab['status']) ? 'bg-teal text-white' : 'bg-white border border-gray-200 text-gray-600' }}">{{ $tab['label'] }}</a>
            @endforeach
        </nav>
        @include('logistics.partials.delivery-filters')
        <div class="delivery-list-tools">
            <p><strong>{{ number_format($deliveries->total()) }}</strong> {{ $deliveries->total() === 1 ? 'delivery' : 'deliveries' }}{{ $hasFilters ? ' matching your filters' : ' in active records' }}</p>
            <x-per-page route="deliveries.index"/>
        </div>

    @if($filterRiders->isNotEmpty())
        <form id="bulk-assign-form" method="POST" action="{{ route('deliveries.bulk-assign') }}" class="delivery-bulk">
            @csrf
            <div>
                <p class="text-sm font-semibold text-gray-900">Bulk assign selected deliveries</p>
                <p class="mt-0.5 text-xs text-gray-500">Select up to 25 deliveries below, then choose a rider. Already-assigned deliveries stay with their current rider.</p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <select name="rider_id" aria-label="Rider for selected deliveries" required class="h-10 min-w-48 rounded-xl border border-gray-200 bg-white px-3 text-sm text-gray-700 focus:border-teal focus:outline-none">
                    <option value="">Select rider</option>
                    @foreach($filterRiders as $rider)
                        <option value="{{ $rider->id }}">{{ $rider->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl bg-teal px-4 text-sm font-semibold text-white transition hover:bg-teal-dark">
                    Assign selected
                </button>
                <span id="bulk-selected-count" aria-live="polite" class="text-xs font-medium text-gray-500">0 selected</span>
            </div>
        </form>
    @endif

    @if(session('bulk_assignment_results'))
        <div class="mb-4 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-sm font-semibold text-gray-900">Bulk assignment results</p>
            <ul class="mt-3 space-y-2">
                @foreach(session('bulk_assignment_results') as $result)
                    <li class="flex flex-col gap-1 rounded-xl bg-gray-50 px-3 py-2 text-sm sm:flex-row sm:items-center sm:justify-between">
                        <span class="font-medium text-gray-700">
                            Delivery #{{ $result['delivery_id'] }}
                            @if(!empty($result['tracking_number']))
                                <span class="font-mono text-xs text-gray-500">{{ $result['tracking_number'] }}</span>
                            @endif
                        </span>
                        <span class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset {{ ($result['status'] ?? 'failed') === 'failed' ? 'bg-red-50 text-red-700 ring-red-200' : 'bg-emerald-50 text-emerald-700 ring-emerald-200' }}">
                                {{ ucwords(str_replace('_', ' ', $result['status'] ?? 'failed')) }}
                            </span>
                            <span class="text-xs text-gray-500">{{ $result['message'] ?? '' }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="delivery-table-wrap" aria-label="Delivery records">
        <table class="delivery-table">
            <thead>
                <tr>
                    @if($filterRiders->isNotEmpty())
                        <th scope="col" class="delivery-select"><label><input id="bulk-select-all" type="checkbox" aria-label="Select all assignable deliveries"></label></th>
                    @endif
                    <th scope="col" class="delivery-shipment">Shipment &amp; actions</th>
                    <th scope="col" class="delivery-parties">Sender &amp; recipient</th>
                    <th scope="col" class="delivery-assignment">Rider &amp; routing</th>
                    <th scope="col" class="delivery-state">Status &amp; priority</th>
                    <th scope="col" class="delivery-amount">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse($deliveries as $delivery)
                    <tr>
                        @if($filterRiders->isNotEmpty())
                            <td class="delivery-select">
                                @if(in_array($delivery->status, ['waiting_for_rider', 'assigned', 'delivery_failed'], true))
                                    <label><input form="bulk-assign-form" type="checkbox" name="delivery_ids[]" value="{{ $delivery->id }}" aria-label="Select {{ $delivery->tracking_number }}" class="bulk-delivery-checkbox"></label>
                                @endif
                            </td>
                        @endif
                        <td class="delivery-shipment">
                            <a href="{{ route('deliveries.show', $delivery) }}" class="delivery-record-link">{{ $delivery->tracking_number }}</a>
                            <small>Created {{ $delivery->created_at->format('M d, Y') }}</small>
                            <div class="delivery-row-actions">
                                <a href="{{ route('deliveries.show', $delivery) }}" aria-label="View {{ $delivery->tracking_number }}">View</a>
                                <a href="{{ route('deliveries.edit', $delivery) }}" aria-label="Edit {{ $delivery->tracking_number }}">Edit</a>
                                <button type="button" data-archive-url="{{ route('deliveries.archive', $delivery) }}"
                                        @click="archiveUrl = $el.dataset.archiveUrl; archiveOpen = true"
                                        aria-label="Archive {{ $delivery->tracking_number }}">Archive</button>
                            </div>
                        </td>
                        <td class="delivery-parties" data-label="Sender &amp; recipient">
                            <p><span class="text-gray-500">From</span> {{ $delivery->sender_name ?: 'Not recorded' }}</p>
                            <p><span class="text-gray-500">To</span> {{ $delivery->recipient_name ?: 'Not recorded' }}</p>
                            <small>Destination: {{ $delivery->destinationCenter?->name ?? 'Not set' }}</small>
                        </td>
                        <td class="delivery-assignment" data-label="Rider &amp; routing">
                            <p class="font-medium">{{ $delivery->rider?->name ?? 'Unassigned' }}</p>
                            <small>Center: {{ $delivery->logisticsCenter?->name ?? 'Not set' }}</small>
                            <small>Area: {{ $delivery->serviceArea?->name ?? 'Not set' }}</small>
                        </td>
                        <td class="delivery-state" data-label="Status &amp; priority">
                            <x-status-badge :status="$delivery->status" />
                            <small>{{ ucfirst($delivery->priority ?? 'normal') }} priority</small>
                        </td>
                        <td class="delivery-amount" data-label="Amount">
                            @if($delivery->transaction)
                                <span class="font-semibold">&#8369;{{ number_format($delivery->transaction->amount, 2) }}</span>
                            @else
                                <span class="text-gray-500">&mdash;</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $filterRiders->isNotEmpty() ? 6 : 5 }}">
                        <div class="delivery-empty">
                            <h2>No deliveries found</h2>
                            <p class="text-gray-500">{{ $hasFilters ? 'Try a different search or clear your filters to see more records.' : 'New deliveries will appear here. Create one to get started.' }}</p>
                            @if($hasFilters)
                                <a href="{{ route('deliveries.index') }}" class="delivery-button">Clear search &amp; filters</a>
                            @else
                                <a href="{{ route('deliveries.create') }}" class="delivery-button delivery-button--primary">New delivery</a>
                            @endif
                        </div>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
        @if($deliveries->count() > 0)
            <div class="delivery-table-footer">
                <p>Showing <strong>{{ $deliveries->firstItem() }}&ndash;{{ $deliveries->lastItem() }}</strong> of <strong>{{ number_format($deliveries->total()) }}</strong> deliveries</p>
                <p>Page {{ $deliveries->currentPage() }} of {{ $deliveries->lastPage() }}</p>
            </div>
        @endif
    </section>

    <div class="mt-4">
        {{ $deliveries->onEachSide(2)->links() }}
    </div>

    {{-- Archive Confirmation Modal --}}
    <div x-show="archiveOpen" x-cloak class="delivery-modal fixed inset-0 z-50 flex items-center justify-center p-4" x-transition.opacity>
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

@if($filterRiders->isNotEmpty())
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const selectAll = document.getElementById('bulk-select-all');
        const countLabel = document.getElementById('bulk-selected-count');
        const checkboxes = () => Array.from(document.querySelectorAll('.bulk-delivery-checkbox'));

        if (!selectAll || !countLabel) return;

        const updateCount = () => {
            const boxes = checkboxes();
            const selected = boxes.filter((box) => box.checked).length;

            countLabel.textContent = `${selected} selected${selected === 25 ? ' (maximum)' : ''}`;
            boxes.forEach(box => { box.disabled = selected >= 25 && !box.checked; });
            selectAll.checked = boxes.length > 0 && selected === Math.min(boxes.length, 25);
            selectAll.indeterminate = selected > 0 && !selectAll.checked;
            const submit = document.querySelector('#bulk-assign-form button[type=submit]');
            if (submit) submit.disabled = selected === 0;
        };

        selectAll.addEventListener('change', () => {
            checkboxes().forEach((box, index) => { box.checked = selectAll.checked && index < 25; });
            updateCount();
        });

        checkboxes().forEach((box) => box.addEventListener('change', updateCount));

        updateCount();
    });
</script>
@endif
    </div>
</x-app-layout>
