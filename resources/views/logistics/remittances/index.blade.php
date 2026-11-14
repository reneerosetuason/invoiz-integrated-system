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
            $currentUser = auth()->user();
            $isSiteAdmin = $currentUser && $currentUser->isAdmin();
            $isHqLogisticsAdmin = $currentUser && $currentUser->isLogisticsAdmin();
            $totalsArray = array_map(fn ($v) => (float) $v, $totals);
            $centersList = $centers ?? collect();
            $activeCenterId = (int) ($filterCenterId ?? request('center_id', 0));
            $activePayoutStatus = $payoutStatus ?? request('payout_status', '');
        @endphp

        <div class="rounded-xl border border-teal/20 bg-white p-4 sm:flex sm:items-center sm:justify-between sm:gap-4">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">Remittances Monitoring &amp; Hub Remittances Separation</h2>
                <p class="mt-1 text-sm text-gray-600">
                    This page monitors COD handoffs and Site Admin hub remittance transfers across authorized hubs.
                    Actual hub receipt confirmations are performed exclusively by assigned Logistics Staff in Hub Remittances.
                </p>
            </div>
            @if($isSiteAdmin)
                <a href="{{ route('admin.payments') }}#escrow-remittances" class="mt-3 inline-flex min-h-[44px] shrink-0 items-center rounded-xl border border-teal px-4 text-sm font-semibold text-teal sm:mt-0">Manage Site Admin Remittances &rarr;</a>
            @elseif(!$isHqLogisticsAdmin)
                <a href="{{ route('remittances.hub-payouts.index') }}" class="mt-3 inline-flex min-h-[44px] shrink-0 items-center rounded-xl border border-teal px-4 text-sm font-semibold text-teal sm:mt-0">Open Hub Remittances &rarr;</a>
            @else
                <span class="mt-3 inline-flex min-h-[38px] shrink-0 items-center rounded-xl bg-gray-100 px-3.5 text-xs font-semibold text-gray-600 sm:mt-0">HQ Admin · Read-Only Monitoring</span>
            @endif
        </div>

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
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search order #, tracking #, or reference..."
                       class="min-w-[200px] flex-1 rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">
                <select name="status" class="rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm" aria-label="COD Status">
                    <option value="">All COD Statuses</option>
                    @foreach(['pending', 'sent', 'confirmed'] as $s)
                        <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
                <select name="payout_status" class="rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm" aria-label="Hub Remittance Status">
                    <option value="">All Hub Remittance Statuses</option>
                    @foreach(['pending_remittance' => 'Pending Admin Release', 'remitted' => 'Remitted (Awaiting Staff)', 'accepted' => 'Confirmed by Staff', 'held_in_escrow' => 'Held in Escrow', 'voided' => 'Voided'] as $val => $lbl)
                        <option value="{{ $val }}" @selected($activePayoutStatus === $val)>{{ $lbl }}</option>
                    @endforeach
                </select>
                <select name="center_id" class="rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm" aria-label="Logistics Hub">
                    <option value="">All Logistics Hubs</option>
                    @foreach($centersList as $center)
                        <option value="{{ $center->id }}" @selected($activeCenterId === (int) $center->id)>{{ $center->name }}</option>
                    @endforeach
                </select>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm" aria-label="Date From">
                <span class="self-center text-gray-400 text-sm">to</span>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm" aria-label="Date To">
                <button type="submit" class="bg-gray-900 hover:bg-gray-800 text-white font-semibold px-5 py-2.5 rounded-xl transition text-sm">Apply</button>
            </form>
            @if(request('status') || request('payout_status') || request('center_id') || request('date_from') || request('date_to') || request('search'))
                <a href="{{ route('remittances.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 hover:border-teal hover:text-teal-dark rounded-xl text-sm font-semibold text-gray-600 transition self-start">Clear</a>
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

        {{-- Hub Remittances (Site Admin Releases & Hub Confirmation History) --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mt-8" data-section="hub-remittances-monitoring">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-base font-bold text-gray-900">Hub Remittances &amp; Confirmation History</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Track pending Site Admin releases, remitted hub payouts, and staff receipt confirmations across authorized hubs.</p>
                </div>
                <div class="flex flex-wrap gap-4 text-xs">
                    <div class="px-3 py-1.5 rounded-xl bg-amber-50 text-amber-800 font-semibold">Pending Admin Release: ₱{{ number_format((float) ($hubPendingAdminTotal ?? 0), 2) }}</div>
                    <div class="px-3 py-1.5 rounded-xl bg-blue-50 text-blue-800 font-semibold">Awaiting Hub Staff Receipt: ₱{{ number_format((float) ($hubAwaitingAcceptTotal ?? 0), 2) }}</div>
                    <div class="px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-800 font-semibold">Confirmed by Hub Staff: ₱{{ number_format((float) ($hubAcceptedTotal ?? 0), 2) }}</div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50/30">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Reference / Order</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Source</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Receiving Hub</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Release &amp; Confirmation History</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($hubPayoutsList ?? [] as $payout)
                            <tr class="hover:bg-teal-light/40 transition" data-hub-remittance-id="{{ $payout->id }}">
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <div class="font-semibold text-gray-900">{{ $payout->bank_reference ?: 'Payout #'.$payout->id }}</div>
                                    <div class="text-xs text-gray-500">Order {{ $payout->order?->order_number ?? ($payout->order_id ? '#'.$payout->order_id : '—') }} · {{ $payout->delivery?->tracking_number ?? '—' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">Site Admin (Platform Escrow)</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ $payout->logisticsCenter?->name ?? '—' }}
                                    <div class="text-xs font-normal text-gray-500">{{ $payout->roleLabel() }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-bold text-gray-900">₱{{ number_format((float) $payout->amount, 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if($payout->status === 'accepted')
                                        <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200">Confirmed</span>
                                    @elseif($payout->status === 'remitted')
                                        <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-200">Remitted · Awaiting Hub</span>
                                    @elseif($payout->status === 'pending_remittance')
                                        <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200">Pending Release</span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-700">{{ ucfirst(str_replace('_', ' ', $payout->status)) }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-600">
                                    <div>Released: {{ $payout->remitted_at?->format('M d, Y h:i A') ?? 'Not released' }}</div>
                                    @if($payout->notes)
                                        <div class="mt-0.5 text-gray-500 whitespace-pre-line">{{ $payout->notes }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-xs">
                                    @if($isSiteAdmin && $payout->status === 'pending_remittance')
                                        <a href="{{ route('admin.payments') }}#escrow-remittances" class="inline-flex items-center rounded-lg border border-teal px-3 py-1.5 font-semibold text-teal hover:bg-teal-light">Release in Admin</a>
                                    @else
                                        <span class="text-gray-400 font-medium">Read-only</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-500">No hub remittance records found for the current filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if(isset($hubPayoutsList) && method_exists($hubPayoutsList, 'hasPages') && $hubPayoutsList->hasPages())
                <div class="px-6 py-3 border-t border-gray-100 bg-gray-50/50">
                    {{ $hubPayoutsList->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
