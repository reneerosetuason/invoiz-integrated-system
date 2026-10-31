<x-app-layout>
    <div class="space-y-6">
        <div class="lw-head flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Remittances</h1>
                <p class="text-sm text-gray-500 mt-1">Track cash collected by riders and handed to sellers. A payment is complete when the seller confirms receipt.</p>
            </div>
            <a href="{{ route('remittances.export', request()->only('status')) }}"
               class="inline-flex items-center gap-2 rounded-xl border border-teal/30 bg-white px-4 py-2.5 text-sm font-semibold text-teal transition hover:border-teal hover:text-teal-dark">
                Export CSV
            </a>
        </div>

        @php
            $totalsArray = array_map(fn ($v) => (float) $v, $totals);
        @endphp

        {{-- Summary cards --}}
        <div class="lw-stat-grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Awaiting handoff</p>
                <p class="text-3xl font-bold text-amber-600">₱{{ number_format($totalsArray['pending'], 2) }}</p>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Sent to seller</p>
                <p class="text-3xl font-bold text-blue-600">₱{{ number_format($totalsArray['sent'], 2) }}</p>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Confirmed</p>
                <p class="text-3xl font-bold text-emerald-600">₱{{ number_format($totalsArray['confirmed'], 2) }}</p>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Total</p>
                <p class="text-3xl font-bold text-teal-dark">₱{{ number_format($totalsArray['total'], 2) }}</p>
            </div>
        </div>

        {{-- Filters --}}
        <div class="flex flex-col sm:flex-row gap-3">
            <form method="GET" action="{{ route('remittances.index') }}" class="flex flex-wrap gap-3 flex-1">
                <select name="status" class="rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">
                    <option value="">All Statuses</option>
                    @foreach(['pending', 'sent', 'confirmed'] as $s)
                        <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="bg-gray-900 hover:bg-gray-800 text-white font-semibold px-5 py-2.5 rounded-xl transition text-sm">Apply</button>
            </form>
            @if(request('status'))
                <a href="{{ route('remittances.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 hover:border-teal hover:text-teal-dark rounded-xl text-sm font-semibold text-gray-600 transition">Clear</a>
            @endif
        </div>

        {{-- Remittances table --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50/50">
                        <tr>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Order #</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Tracking #</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Rider</th>
                            <th class="px-6 py-3.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">COD to Collect</th>
                            <th class="px-6 py-3.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Collected</th>
                            <th class="px-6 py-3.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Rider Fee</th>
                            <th class="px-6 py-3.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">For Seller</th>
                            <th class="px-6 py-3.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Variance</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Sent</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Confirmed</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($remittances as $remittance)
                            @php($info = $reconciliation[$remittance->id] ?? [])
                            <tr class="hover:bg-teal-light/40 transition">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                    {{ $remittance->order?->order_number ?? ($remittance->order_id ? '#' . $remittance->order_id : '—') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($remittance->delivery)
                                        <a href="{{ route('deliveries.show', $remittance->delivery_id) }}" class="text-sm font-mono font-semibold text-teal hover:text-teal-dark">{{ $remittance->delivery->tracking_number }}</a>
                                    @else
                                        <span class="text-sm text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $remittance->rider?->name ?? '—' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-600">
                                    {{ ($info['expected'] ?? null) !== null ? '₱' . number_format((float) $info['expected'], 2) : '—' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    @if(($info['received'] ?? null) !== null)
                                        <span class="font-medium text-gray-900">₱{{ number_format((float) $info['received'], 2) }}</span>
                                    @else
                                        <span class="text-xs font-semibold text-amber-600">Not recorded</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-600">₱{{ number_format((float) ($info['rider_fee'] ?? 0), 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900">₱{{ number_format((float) ($info['seller_amount'] ?? $remittance->amount), 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    @if(($info['variance'] ?? null) !== null)
                                        @php($variance = (float) $info['variance'])
                                        <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full ring-1 ring-inset {{ abs($variance) > 0.009 ? 'bg-rose-50 text-rose-700 ring-rose-200' : 'bg-emerald-50 text-emerald-700 ring-emerald-200' }}">
                                            {{ $variance > 0 ? '+' : '' }}{{ number_format($variance, 2) }}
                                        </span>
                                    @else
                                        <span class="text-sm text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($remittance->status === 'confirmed')
                                        <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200">Confirmed</span>
                                    @elseif($remittance->status === 'sent')
                                        <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-200">Sent</span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200">Pending</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $remittance->sent_at?->format('M d, Y') ?? '—' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $remittance->confirmed_at?->format('M d, Y') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-6 py-16 text-center">
                                    <div class="flex flex-col items-center">
                                        <p class="text-sm font-semibold text-gray-900">No remittances found</p>
                                        <p class="mt-1 text-sm text-gray-500">A remittance is opened when a Cash-on-Delivery parcel is delivered.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($remittances->total() > 0)
                <div class="px-6 py-3 border-t border-gray-100 bg-gray-50/50 flex flex-col sm:flex-row items-center justify-between gap-2">
                    <p class="text-xs text-gray-500">Showing {{ $remittances->firstItem() }}–{{ $remittances->lastItem() }} of {{ $remittances->total() }} remittances</p>
                    <p class="text-xs text-gray-400">Page {{ $remittances->currentPage() }} of {{ $remittances->lastPage() }}</p>
                </div>
            @endif
        </div>

        <div class="mt-6">{{ $remittances->links() }}</div>
    </div>
</x-app-layout>
