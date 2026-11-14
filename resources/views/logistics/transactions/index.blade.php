<x-app-layout>
    <div class="space-y-6">
        <div class="lw-head">
            <h1 class="text-2xl font-bold text-gray-900">Transactions</h1>
            <p class="text-sm text-gray-500 mt-1">Centralized, read-only logistics financial monitoring — Pickup fees (₱30.00 per parcel), Hub-to-Door fees (₱50.00 per parcel), and management commissions (10%).</p>
        </div>

        @php
            $totalsArray = is_array($totals) ? $totals : (array) $totals;
            $totalsArray = array_map(fn ($v) => (float) $v, $totalsArray);
            $activeAssignmentType = $assignmentType ?? request('assignment_type', '');
            $activeRiderId = (int) ($riderId ?? request('rider_id', 0));
            $ridersList = $riders ?? collect();
            $riderGroups = $riderAssignmentSummary ?? [];
            $riderGroupMap = $riderAssignmentMap ?? [];
        @endphp

        {{-- Summary cards (6 cards) --}}
        <div class="lw-stat-grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5" data-summary-card="total-transactions">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Total Transactions</p>
                <p class="text-2xl font-bold text-gray-900">{{ number_format($totalsArray['total_count'] ?? 0) }}</p>
                <p class="text-xs text-gray-400 mt-1">Authorized records</p>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5" data-summary-card="total-amount">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Total Transaction Amount</p>
                <p class="text-2xl font-bold text-gray-900">₱{{ number_format($totalsArray['total_amount'] ?? 0, 2) }}</p>
                <p class="text-xs text-gray-400 mt-1">Gross delivery value</p>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5" data-summary-card="total-pickup-fees">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Total Pickup Fees</p>
                <p class="text-2xl font-bold text-gray-900">₱{{ number_format($totalsArray['total_pickup_fees'] ?? 0, 2) }}</p>
                <p class="text-xs text-gray-400 mt-1">{{ number_format($totalsArray['total_pickup_parcels'] ?? 0) }} {{ ($totalsArray['total_pickup_parcels'] ?? 0) == 1 ? 'parcel' : 'parcels' }} × ₱30.00</p>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5" data-summary-card="total-hub-to-door-fees">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Total Hub-to-Door Fees</p>
                <p class="text-2xl font-bold text-gray-900">₱{{ number_format($totalsArray['total_hub_to_door_fees'] ?? 0, 2) }}</p>
                <p class="text-xs text-gray-400 mt-1">{{ number_format($totalsArray['total_hub_to_door_parcels'] ?? 0) }} {{ ($totalsArray['total_hub_to_door_parcels'] ?? 0) == 1 ? 'parcel' : 'parcels' }} × ₱50.00</p>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5" data-summary-card="total-rider-fees">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Total Rider Fees</p>
                <p class="text-2xl font-bold text-gray-900">₱{{ number_format($totalsArray['total_rider_fees'] ?? $totalsArray['total_rider_fee'] ?? 0, 2) }}</p>
                <p class="text-xs text-gray-400 mt-1">Pickup + Hub-to-Door</p>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5" data-summary-card="total-commissions">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Total Commissions</p>
                <p class="text-2xl font-bold text-teal-dark">₱{{ number_format($totalsArray['total_commissions'] ?? $totalsArray['total_commission'] ?? 0, 2) }}</p>
                <p class="text-xs text-gray-400 mt-1">10% management rate</p>
            </div>
        </div>

        {{-- Filters --}}
        <div class="flex flex-col xl:flex-row gap-3">
            <form method="GET" action="{{ route('transactions.index') }}" class="flex flex-col lg:flex-row lg:flex-wrap gap-3 flex-1">
                <div class="relative flex-1 min-w-[200px]">
                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search tracking # or rider..."
                           class="w-full pl-10 rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">
                </div>
                <select name="assignment_type" class="rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm" aria-label="Assignment Type">
                    <option value="">All Assignment Types</option>
                    <option value="pickup" @selected($activeAssignmentType === 'pickup')>Pickup (₱30.00 / parcel)</option>
                    <option value="hub_to_door" @selected($activeAssignmentType === 'hub_to_door')>Hub-to-Door (₱50.00 / parcel)</option>
                </select>
                <select name="rider_id" class="rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm" aria-label="Assigned Rider">
                    <option value="">All Riders</option>
                    @foreach($ridersList as $riderOption)
                        <option value="{{ $riderOption->id }}" @selected($activeRiderId === (int) $riderOption->id)>{{ $riderOption->name }}</option>
                    @endforeach
                </select>
                <select name="center_id" class="rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm" aria-label="Logistics Hub">
                    <option value="">All Logistics Hubs</option>
                    @foreach($centers as $center)
                        <option value="{{ $center->id }}" @selected((int) request('center_id') === $center->id)>{{ $center->name }}</option>
                    @endforeach
                </select>
                <select name="status" class="rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm" aria-label="Status">
                    <option value="">All Statuses</option>
                    @foreach(['pending', 'completed', 'failed'] as $s)
                        <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm" aria-label="Date From">
                <span class="self-center text-gray-400 text-sm">to</span>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm" aria-label="Date To">
                <button type="submit" class="bg-gray-900 hover:bg-gray-800 text-white font-semibold px-5 py-2.5 rounded-xl transition text-sm">Apply</button>
            </form>
            @if(request('search') || request('assignment_type') || request('rider_id') || request('status') || request('center_id') || request('date_from') || request('date_to'))
                <a href="{{ route('transactions.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 hover:border-teal hover:text-teal-dark rounded-xl text-sm font-semibold text-gray-600 transition self-start lg:self-auto">Clear</a>
            @endif
        </div>

        {{-- Transactions table --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50/50">
                        <tr>
                            <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Tracking Number</th>
                            <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Assignment Type</th>
                            <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Assigned Rider</th>
                            <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Logistics Hub</th>
                            <th class="px-5 py-3.5 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Number of Assigned Parcels</th>
                            <th class="px-5 py-3.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Fee per Parcel</th>
                            <th class="px-5 py-3.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Rider Fee</th>
                            <th class="px-5 py-3.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Transaction Amount</th>
                            <th class="px-5 py-3.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Management Commission</th>
                            <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($transactions as $tx)
                            @php
                                $legs = $tx->assignmentLegs($activeAssignmentType ?: null);
                                if ($activeRiderId > 0) {
                                    $legs = array_values(array_filter($legs, fn ($leg) => (int) ($leg['rider_id'] ?? 0) === $activeRiderId));
                                }
                                $legCount = count($legs);
                                $rowRiderFee = round(array_sum(array_column($legs, 'total_fee')), 2);
                                $rowCommission = $tx->managementCommission();
                            @endphp
                            <tr class="hover:bg-teal-light/40 transition" data-transaction-id="{{ $tx->id }}">
                                <td class="px-5 py-4 whitespace-nowrap">
                                    @if($tx->delivery_id)
                                        <a href="{{ route('deliveries.show', $tx->delivery_id) }}" class="text-sm font-mono font-semibold text-teal hover:text-teal-dark">{{ $tx->tracking_number }}</a>
                                    @else
                                        <span class="text-sm font-mono font-semibold text-gray-800">{{ $tx->tracking_number }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap text-sm">
                                    @if($legCount > 0)
                                        <div class="flex flex-col gap-1">
                                            @foreach($legs as $leg)
                                                @if($leg['type'] === 'pickup')
                                                    <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold rounded-full bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-200 w-fit">Pickup</span>
                                                @else
                                                    <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold rounded-full bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-200 w-fit">Hub-to-Door</span>
                                                @endif
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-400">Unassigned</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap text-sm text-gray-700">
                                    @if($legCount > 0)
                                        <div class="flex flex-col gap-1">
                                            @foreach($legs as $leg)
                                                <span>{{ $leg['rider_name'] }}</span>
                                            @endforeach
                                        </div>
                                    @else
                                        {{ $tx->rider?->name ?? '—' }}
                                    @endif
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap text-sm text-gray-600">
                                    @if($legCount > 1 && $legs[0]['hub_name'] !== $legs[1]['hub_name'])
                                        <div class="flex flex-col gap-1">
                                            @foreach($legs as $leg)
                                                <span>{{ $leg['hub_name'] }}</span>
                                            @endforeach
                                        </div>
                                    @else
                                        {{ $legs[0]['hub_name'] ?? $tx->logisticsCenter?->name ?? '—' }}
                                    @endif
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap text-center text-sm font-medium text-gray-800">
                                    @if($legCount > 0)
                                        <div class="flex flex-col items-center gap-1">
                                            @foreach($legs as $leg)
                                                @php
                                                    $groupKey = ($leg['type'] ?? 'unknown') . ':' . ($leg['rider_id'] ?? 'unassigned');
                                                    $riderBatchParcels = $riderGroupMap[$groupKey]['parcels'] ?? 1;
                                                @endphp
                                                <span>
                                                    1
                                                    @if($riderBatchParcels > 1 && $leg['rider_id'])
                                                        <span class="text-xs text-gray-400">({{ $riderBatchParcels }} assigned to rider)</span>
                                                    @endif
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        0
                                    @endif
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap text-right text-sm text-gray-700">
                                    @if($legCount > 0)
                                        <div class="flex flex-col items-end gap-1">
                                            @foreach($legs as $leg)
                                                <span>₱{{ number_format((float) $leg['fee_per_parcel'], 2) }}</span>
                                            @endforeach
                                        </div>
                                    @else
                                        ₱0.00
                                    @endif
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap text-right text-sm font-semibold text-gray-900">
                                    ₱{{ number_format($rowRiderFee, 2) }}
                                    @if($legCount > 1)
                                        <div class="text-xs font-normal text-gray-500">₱30.00 + ₱50.00</div>
                                    @endif
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900">₱{{ number_format((float) $tx->amount, 2) }}</td>
                                <td class="px-5 py-4 whitespace-nowrap text-right text-sm text-teal-dark font-semibold">₱{{ number_format($rowCommission, 2) }}</td>
                                <td class="px-5 py-4 whitespace-nowrap">
                                    @if($tx->status === 'completed')
                                        <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200">Completed</span>
                                    @elseif($tx->status === 'failed')
                                        <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-red-50 text-red-700 ring-1 ring-inset ring-red-200">Failed</span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200">Pending</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap text-sm text-gray-500">{{ $tx->created_at?->format('M d, Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-6 py-16 text-center">
                                    <div class="flex flex-col items-center">
                                        <p class="text-sm font-semibold text-gray-900">No transactions found</p>
                                        <p class="mt-1 text-sm text-gray-500">Transactions are generated when a delivery is marked delivered.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($transactions->total() > 0)
                <div class="px-6 py-3 border-t border-gray-100 bg-gray-50/50 flex flex-col sm:flex-row items-center justify-between gap-2">
                    <p class="text-xs text-gray-500">Showing {{ $transactions->firstItem() }}–{{ $transactions->lastItem() }} of {{ $transactions->total() }} transactions</p>
                    <p class="text-xs text-gray-400">Page {{ $transactions->currentPage() }} of {{ $transactions->lastPage() }}</p>
                </div>
            @endif
        </div>

        @if(count($riderGroups) > 0)
            {{-- Rider Assignment Breakdown by Assignment Type --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden" data-section="rider-assignment-breakdown">
                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                    <h2 class="text-sm font-bold text-gray-900">Rider Fee Breakdown by Assignment Type</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Calculated separately per assignment type: Pickup = assigned parcels × ₱30.00; Hub-to-Door = assigned parcels × ₱50.00.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50/30">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Assigned Rider</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Assignment Type</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Logistics Hub</th>
                                <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Number of Assigned Parcels</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Fee per Parcel</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Rider Fee</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($riderGroups as $group)
                                <tr>
                                    <td class="px-6 py-3 text-sm font-medium text-gray-900">{{ $group['rider_name'] }}</td>
                                    <td class="px-6 py-3 text-sm">
                                        @if($group['type'] === 'pickup')
                                            <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold rounded-full bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-200">Pickup</span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold rounded-full bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-200">Hub-to-Door</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 text-sm text-gray-600">{{ $group['hub_name'] }}</td>
                                    <td class="px-6 py-3 text-center text-sm font-semibold text-gray-900">{{ $group['parcels'] }}</td>
                                    <td class="px-6 py-3 text-right text-sm text-gray-700">₱{{ number_format((float) $group['fee_per_parcel'], 2) }}</td>
                                    <td class="px-6 py-3 text-right text-sm font-bold text-gray-900">₱{{ number_format((float) $group['total_fee'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="mt-6">{{ $transactions->links() }}</div>
    </div>
</x-app-layout>
