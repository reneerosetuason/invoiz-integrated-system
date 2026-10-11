<x-app-layout>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Transactions</h1>
            <p class="text-sm text-gray-500 mt-1">Delivery fees, rider fees (₱15/parcel), and management commissions (10%)</p>
        </div>

        @php
            $totalsArray = is_array($totals) ? $totals : (array) $totals;
            $totalsArray = array_map(fn ($v) => (float) $v, $totalsArray);
        @endphp

        {{-- Summary cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Transactions</p>
                <p class="text-3xl font-bold text-gray-900">{{ number_format($totalsArray['total_count'] ?? 0) }}</p>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Total Amount</p>
                <p class="text-3xl font-bold text-gray-900">₱{{ number_format($totalsArray['total_amount'] ?? 0, 2) }}</p>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Total Rider Fees</p>
                <p class="text-3xl font-bold text-gray-900">₱{{ number_format($totalsArray['total_rider_fee'] ?? 0, 2) }}</p>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Total Commissions</p>
                <p class="text-3xl font-bold text-teal-dark">₱{{ number_format($totalsArray['total_commission'] ?? 0, 2) }}</p>
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
                <select name="status" class="rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">
                    <option value="">All Statuses</option>
                    @foreach(['pending', 'completed', 'failed'] as $s)
                        <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
                <select name="center_id" class="rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">
                    <option value="">All Centers</option>
                    @foreach($centers as $center)
                        <option value="{{ $center->id }}" @selected((int) request('center_id') === $center->id)>{{ $center->name }}</option>
                    @endforeach
                </select>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">
                <span class="self-center text-gray-400 text-sm">to</span>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">
                <button type="submit" class="bg-gray-900 hover:bg-gray-800 text-white font-semibold px-5 py-2.5 rounded-xl transition text-sm">Apply</button>
            </form>
            @if(request('search') || request('status') || request('center_id') || request('date_from') || request('date_to'))
                <a href="{{ route('transactions.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 hover:border-teal hover:text-teal-dark rounded-xl text-sm font-semibold text-gray-600 transition self-start lg:self-auto">Clear</a>
            @endif
        </div>

        {{-- Transactions table --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50/50">
                        <tr>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Tracking #</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Rider</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Center</th>
                            <th class="px-6 py-3.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Rider Fee</th>
                            <th class="px-6 py-3.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Commission</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($transactions as $tx)
                            <tr class="hover:bg-teal-light/40 transition">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <a href="{{ route('deliveries.show', $tx->delivery_id) }}" class="text-sm font-mono font-semibold text-teal hover:text-teal-dark">{{ $tx->tracking_number }}</a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $tx->rider?->name ?? '—' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $tx->logisticsCenter?->name ?? '—' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900">₱{{ number_format((float) $tx->amount, 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">₱{{ number_format((float) $tx->rider_fee, 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-teal-dark font-semibold">₱{{ number_format((float) $tx->admin_commission, 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($tx->status === 'completed')
                                        <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200">Completed</span>
                                    @elseif($tx->status === 'failed')
                                        <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-red-50 text-red-700 ring-1 ring-inset ring-red-200">Failed</span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200">Pending</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $tx->created_at?->format('M d, Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-16 text-center">
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

        <div class="mt-6">{{ $transactions->links() }}</div>
    </div>
</x-app-layout>
