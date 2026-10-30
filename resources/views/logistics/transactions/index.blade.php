<x-app-layout>
    <div class="space-y-5">
        <div>
            <h1 class="text-2xl font-bold text-[#1B1B1E]">Transactions</h1>
            <p class="text-xs text-[#6E6E73] mt-0.5">Review delivery revenues, last-mile rider earnings (₱50), origin/destination hub fees (₱30), pickup rider fees (₱30/item), and inter-hub driver fees (₱20).</p>
        </div>

        @php
            $totalsArray = is_array($totals) ? $totals : (array) $totals;
            $totalsArray = array_map(fn ($v) => (float) $v, $totalsArray);
        @endphp

        {{-- Summary Cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5">
            <div class="stat-card">
                <p class="text-[11px] font-bold uppercase tracking-wider text-[#6E6E73]">Transactions</p>
                <p class="text-2xl font-extrabold text-[#1B1B1E] mt-1">{{ number_format($totalsArray['total_count'] ?? 0) }}</p>
            </div>
            <div class="stat-card">
                <p class="text-[11px] font-bold uppercase tracking-wider text-[#6E6E73]">Total Amount</p>
                <p class="text-2xl font-extrabold text-[#1B1B1E] mt-1">₱{{ number_format($totalsArray['total_amount'] ?? 0, 2) }}</p>
            </div>
            <div class="stat-card">
                <p class="text-[11px] font-bold uppercase tracking-wider text-[#6E6E73]">Total Rider Fees</p>
                <p class="text-2xl font-extrabold text-[#1B1B1E] mt-1">₱{{ number_format($totalsArray['total_rider_fee'] ?? 0, 2) }}</p>
            </div>
            <div class="stat-card">
                <p class="text-[11px] font-bold uppercase tracking-wider text-[#6E6E73]">Total Commissions</p>
                <p class="text-2xl font-extrabold text-[#16697A] mt-1">₱{{ number_format($totalsArray['total_commission'] ?? 0, 2) }}</p>
            </div>
        </div>

        {{-- Filters --}}
        <div class="card p-4">
            <form method="GET" action="{{ route('transactions.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
                <div class="sm:col-span-2">
                    <label class="field-label">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search tracking # or rider..." class="input">
                </div>
                <div>
                    <label class="field-label">Status</label>
                    <select name="status" class="select">
                        <option value="">All Statuses</option>
                        @foreach(['pending', 'completed', 'failed'] as $s)
                            <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label">Center</label>
                    <select name="center_id" class="select">
                        <option value="">All Centers</option>
                        @foreach($centers as $center)
                            <option value="{{ $center->id }}" @selected((int) request('center_id') === $center->id)>{{ $center->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label">From Date</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="input">
                </div>
                <div>
                    <label class="field-label">To Date</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="input">
                </div>
                <div class="sm:col-span-2 lg:col-span-6 flex justify-end gap-2 pt-2 border-t border-[#E8E6E0]">
                    @if(request('search') || request('status') || request('center_id') || request('date_from') || request('date_to'))
                        <a href="{{ route('transactions.index') }}" class="btn btn-ghost">Clear</a>
                    @endif
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                </div>
            </form>
        </div>

        {{-- Transactions Table (No Horizontal Scroll) --}}
        <div class="card overflow-hidden">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tracking #</th>
                        <th>Rider</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $tx)
                        @php
                            $deliveryUrl = $tx->delivery_id ? route('deliveries.show', $tx->delivery_id) : null;
                            $modalPayload = [
                                'kicker' => 'Transaction Details',
                                'title' => $tx->tracking_number,
                                'url' => $deliveryUrl,
                                'urlLabel' => $deliveryUrl ? 'Open Delivery' : null,
                                'items' => [
                                    ['label' => 'Tracking #', 'value' => $tx->tracking_number],
                                    ['label' => 'Status', 'value' => ucfirst($tx->status)],
                                    ['label' => 'Rider', 'value' => $tx->rider?->name ?? '—'],
                                    ['label' => 'Center', 'value' => $tx->logisticsCenter?->name ?? '—'],
                                    ['label' => 'Total Amount', 'value' => '₱' . number_format((float) $tx->amount, 2)],
                                    ['label' => 'Last-Mile Rider', 'value' => '₱' . number_format((float) ($tx->last_mile_rider_fee ?: $tx->rider_fee), 2)],
                                    ['label' => 'Origin Hub', 'value' => '₱' . number_format((float) ($tx->origin_hub_fee ?: 0), 2)],
                                    ['label' => 'Pickup Rider', 'value' => '₱' . number_format((float) ($tx->pickup_rider_fee ?: 0), 2)],
                                    ['label' => 'Inter-Hub Driver', 'value' => '₱' . number_format((float) ($tx->inter_hub_driver_fee ?: 0), 2)],
                                    ['label' => 'Destination Hub', 'value' => '₱' . number_format((float) ($tx->destination_hub_fee ?: 0), 2)],
                                    ['label' => 'Commission', 'value' => '₱' . number_format((float) $tx->admin_commission, 2)],
                                    ['label' => 'Date', 'value' => $tx->created_at?->format('M d, Y h:i A') ?? '—'],
                                ],
                            ];
                        @endphp
                        <tr>
                            <td>
                                @if($deliveryUrl)
                                    <a href="{{ $deliveryUrl }}" class="font-bold text-[#16697A] hover:underline">{{ $tx->tracking_number }}</a>
                                @else
                                    <span class="font-bold text-[#1B1B1E]">{{ $tx->tracking_number ?: '—' }}</span>
                                @endif
                                <div class="text-[11px] text-[#6E6E73]">{{ $tx->created_at?->format('M d, Y') }}</div>
                            </td>
                            <td class="text-[#1B1B1E]">{{ $tx->rider?->name ?? '—' }}</td>
                            <td class="font-bold text-[#1B1B1E]">₱{{ number_format((float) $tx->amount, 2) }}</td>
                            <td>
                                <x-status-badge :status="$tx->status" />
                            </td>
                            <td class="text-right">
                                <button type="button" class="btn btn-soft btn-xs" data-view-record='@json($modalPayload)'>View</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center">
                                <p class="text-sm font-bold text-[#1B1B1E]">No transactions found</p>
                                <p class="mt-1 text-xs text-[#6E6E73]">Transactions are generated when a delivery is marked delivered.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if($transactions->total() > 0)
                <div class="p-4 border-t border-[#E8E6E0] flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-[#6E6E73]">
                    <p>Showing <strong class="text-[#1B1B1E]">{{ $transactions->firstItem() }}–{{ $transactions->lastItem() }}</strong> of <strong class="text-[#1B1B1E]">{{ $transactions->total() }}</strong> transactions</p>
                    <p>Page {{ $transactions->currentPage() }} of {{ $transactions->lastPage() }}</p>
                </div>
            @endif
        </div>

        <div>{{ $transactions->links() }}</div>
    </div>
</x-app-layout>
