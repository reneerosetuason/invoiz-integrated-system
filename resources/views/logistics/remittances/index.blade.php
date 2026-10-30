<x-app-layout>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-[#1B1B1E]">Remittances</h1>
                <p class="text-xs text-[#6E6E73] mt-0.5">Track cash collected by riders and handed to sellers. A payment is complete when the seller confirms receipt.</p>
            </div>
            <a href="{{ route('remittances.export', request()->only('status')) }}" class="btn btn-outline">
                <x-icon name="download" class="w-4 h-4" />
                Export CSV
            </a>
        </div>

        @php
            $totalsArray = array_map(fn ($v) => (float) $v, $totals);
        @endphp

        {{-- Summary Cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5">
            <div class="stat-card">
                <p class="text-[11px] font-bold uppercase tracking-wider text-[#6E6E73]">Awaiting Handoff</p>
                <p class="text-2xl font-extrabold text-amber-600 mt-1">₱{{ number_format($totalsArray['pending'], 2) }}</p>
            </div>
            <div class="stat-card">
                <p class="text-[11px] font-bold uppercase tracking-wider text-[#6E6E73]">Sent to Seller</p>
                <p class="text-2xl font-extrabold text-blue-600 mt-1">₱{{ number_format($totalsArray['sent'], 2) }}</p>
            </div>
            <div class="stat-card">
                <p class="text-[11px] font-bold uppercase tracking-wider text-[#6E6E73]">Confirmed</p>
                <p class="text-2xl font-extrabold text-emerald-600 mt-1">₱{{ number_format($totalsArray['confirmed'], 2) }}</p>
            </div>
            <div class="stat-card">
                <p class="text-[11px] font-bold uppercase tracking-wider text-[#6E6E73]">Total</p>
                <p class="text-2xl font-extrabold text-[#16697A] mt-1">₱{{ number_format($totalsArray['total'], 2) }}</p>
            </div>
        </div>

        {{-- Filters --}}
        <div class="card p-4">
            <form method="GET" action="{{ route('remittances.index') }}" class="flex flex-wrap items-end gap-3">
                <div class="w-56">
                    <label class="field-label">Status</label>
                    <select name="status" class="select">
                        <option value="">All Statuses</option>
                        @foreach(['pending', 'sent', 'confirmed'] as $s)
                            <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    @if(request('status'))
                        <a href="{{ route('remittances.index') }}" class="btn btn-ghost">Clear</a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Remittances Table (No Horizontal Scroll) --}}
        <div class="card overflow-hidden">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tracking #</th>
                        <th>Rider</th>
                        <th>For Seller</th>
                        <th>Variance</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($remittances as $remittance)
                        @php
                            $info = $reconciliation[$remittance->id] ?? [];
                            $variance = ($info['variance'] ?? null) !== null ? (float) $info['variance'] : null;
                            $orderNo = $remittance->order?->order_number ?? ($remittance->order_id ? '#' . $remittance->order_id : '—');
                            $trackingNo = $remittance->delivery?->tracking_number ?? '—';
                            $modalPayload = [
                                'kicker' => 'Remittance Reconciliation',
                                'title' => $trackingNo !== '—' ? $trackingNo : ('Order ' . $orderNo),
                                'url' => $remittance->delivery ? route('deliveries.show', $remittance->delivery_id) : '',
                                'urlLabel' => 'Open Delivery',
                                'items' => [
                                    ['label' => 'Order #', 'value' => $orderNo],
                                    ['label' => 'Tracking #', 'value' => $trackingNo],
                                    ['label' => 'Rider', 'value' => $remittance->rider?->name ?? '—'],
                                    ['label' => 'Status', 'value' => ucfirst($remittance->status)],
                                    ['label' => 'COD to Collect', 'value' => ($info['expected'] ?? null) !== null ? '₱' . number_format((float) $info['expected'], 2) : '—'],
                                    ['label' => 'Collected', 'value' => ($info['received'] ?? null) !== null ? '₱' . number_format((float) $info['received'], 2) : 'Not recorded'],
                                    ['label' => 'Rider Fee', 'value' => '₱' . number_format((float) ($info['rider_fee'] ?? 0), 2)],
                                    ['label' => 'For Seller', 'value' => '₱' . number_format((float) ($info['seller_amount'] ?? $remittance->amount), 2)],
                                    ['label' => 'Variance', 'value' => $variance !== null ? (($variance > 0 ? '+' : '') . number_format($variance, 2)) : '—'],
                                    ['label' => 'Sent Date', 'value' => $remittance->sent_at?->format('M d, Y') ?? '—'],
                                    ['label' => 'Confirmed Date', 'value' => $remittance->confirmed_at?->format('M d, Y') ?? '—'],
                                ],
                            ];
                        @endphp
                        <tr>
                            <td>
                                @if($remittance->delivery)
                                    <a href="{{ route('deliveries.show', $remittance->delivery_id) }}" class="font-bold text-[#16697A] hover:underline">{{ $remittance->delivery->tracking_number }}</a>
                                @else
                                    <span class="text-[#6E6E73]">—</span>
                                @endif
                                <div class="text-[11px] text-[#6E6E73]">Order: {{ $orderNo }}</div>
                            </td>
                            <td class="text-[#1B1B1E]">{{ $remittance->rider?->name ?? '—' }}</td>
                            <td class="font-bold text-[#1B1B1E]">₱{{ number_format((float) ($info['seller_amount'] ?? $remittance->amount), 2) }}</td>
                            <td>
                                @if($variance !== null)
                                    <span class="badge {{ abs($variance) > 0.009 ? 'badge-cancelled' : 'badge-completed' }}">
                                        {{ $variance > 0 ? '+' : '' }}{{ number_format($variance, 2) }}
                                    </span>
                                @else
                                    <span class="text-xs text-[#6E6E73]">—</span>
                                @endif
                            </td>
                            <td>
                                <x-status-badge :status="$remittance->status" />
                            </td>
                            <td class="text-right">
                                <button type="button" class="btn btn-soft btn-xs" data-view-record='@json($modalPayload)'>View</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center">
                                <p class="text-sm font-bold text-[#1B1B1E]">No remittances found</p>
                                <p class="mt-1 text-xs text-[#6E6E73]">A remittance is opened when a Cash-on-Delivery parcel is delivered.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if($remittances->total() > 0)
                <div class="p-4 border-t border-[#E8E6E0] flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-[#6E6E73]">
                    <p>Showing <strong class="text-[#1B1B1E]">{{ $remittances->firstItem() }}–{{ $remittances->lastItem() }}</strong> of <strong class="text-[#1B1B1E]">{{ $remittances->total() }}</strong> remittances</p>
                    <p>Page {{ $remittances->currentPage() }} of {{ $remittances->lastPage() }}</p>
                </div>
            @endif
        </div>

        <div>{{ $remittances->links() }}</div>
    </div>
</x-app-layout>
