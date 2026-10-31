<x-app-layout>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-[#1B1B1E]">Remittances & Hub Earnings</h1>
                <p class="text-xs text-[#6E6E73] mt-0.5">Track rider COD cash collections and verify your logistics center's income (₱30 origin hub / ₱30 destination hub per shipment).</p>
            </div>
            <a href="{{ route('remittances.export', request()->only('status')) }}" class="btn btn-outline">
                <x-icon name="download" class="w-4 h-4" />
                Export CSV
            </a>
        </div>

        @php
            $totalsArray = array_map(fn ($v) => (float) $v, $totals);
            $activeTab = $viewTab ?? 'rider_handoffs';
        @endphp

        {{-- Summary Cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3.5">
            <div class="stat-card">
                <p class="text-[11px] font-bold uppercase tracking-wider text-[#6E6E73]">Awaiting Handoff</p>
                <p class="text-2xl font-extrabold text-amber-600 mt-1">₱{{ number_format($totalsArray['pending'], 2) }}</p>
                <p class="text-[10px] text-[#6E6E73] mt-0.5">Cash with riders</p>
            </div>
            <div class="stat-card">
                <p class="text-[11px] font-bold uppercase tracking-wider text-[#6E6E73]">Sent to Seller</p>
                <p class="text-2xl font-extrabold text-blue-600 mt-1">₱{{ number_format($totalsArray['sent'], 2) }}</p>
                <p class="text-[10px] text-[#6E6E73] mt-0.5">In bank transit</p>
            </div>
            <div class="stat-card">
                <p class="text-[11px] font-bold uppercase tracking-wider text-[#6E6E73]">Confirmed at Hub</p>
                <p class="text-2xl font-extrabold text-emerald-600 mt-1">₱{{ number_format($totalsArray['confirmed'], 2) }}</p>
                <p class="text-[10px] text-emerald-700 mt-0.5">Cash received & settled</p>
            </div>
            <div class="stat-card">
                <p class="text-[11px] font-bold uppercase tracking-wider text-[#6E6E73]">Total COD Handled</p>
                <p class="text-2xl font-extrabold text-[#16697A] mt-1">₱{{ number_format($totalsArray['total'], 2) }}</p>
                <p class="text-[10px] text-[#6E6E73] mt-0.5">Total cash collection</p>
            </div>
            <div class="stat-card bg-[#E6F4F1] border-2 border-[#16697A]/30">
                <div class="flex items-center justify-between">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-[#0E4A57]">Logistics Cut (Income)</p>
                    <span class="text-[10px] font-bold bg-[#16697A] text-white px-1.5 py-0.5 rounded">₱30/shipment</span>
                </div>
                <p class="text-2xl font-extrabold text-[#0E4A57] mt-1">₱{{ number_format($hubAcceptedTotal ?? 0, 2) }}</p>
                <div class="mt-1 flex items-center justify-between text-[10px]">
                    <span class="font-semibold text-emerald-700 flex items-center gap-1">
                        <svg class="w-3 h-3 text-emerald-600 inline-block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>{{ number_format($hubAcceptedTotal ?? 0, 2) }} Recorded</span>
                    </span>
                    @if(($hubAwaitingAcceptTotal ?? 0) > 0)
                        <span class="font-bold text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded animate-pulse" title="Released by Admin, ready for hub staff to accept">
                            ₱{{ number_format($hubAwaitingAcceptTotal, 2) }} Ready to Accept
                        </span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Subnav Tabs --}}
        <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 pb-2">
            <a href="{{ route('remittances.index', array_merge(request()->except(['tab', 'page']), ['tab' => 'rider_handoffs'])) }}"
               class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 {{ $activeTab === 'rider_handoffs' ? 'bg-[#16697A] text-white shadow-sm' : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200' }}">
                <svg class="w-4 h-4 inline-block shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/></svg>
                <span>Rider COD Cash Handoffs</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] {{ $activeTab === 'rider_handoffs' ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-600' }}">
                    {{ $remittances->total() }}
                </span>
            </a>
            <a href="{{ route('remittances.index', array_merge(request()->except(['tab', 'page']), ['tab' => 'hub_payouts'])) }}"
               class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 {{ $activeTab === 'hub_payouts' ? 'bg-[#16697A] text-white shadow-sm' : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200' }}">
                <svg class="w-4 h-4 inline-block shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <span>Hub Income & Payout Confirmation (Logistics Cut)</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold {{ $activeTab === 'hub_payouts' ? 'bg-white text-[#16697A]' : 'bg-[#E6F4F1] text-[#0E4A57]' }}">
                    ₱{{ number_format($hubAcceptedTotal ?? 0, 2) }} Recorded
                </span>
                @if(($hubAwaitingAcceptTotal ?? 0) > 0)
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-500 text-white animate-pulse">
                        +₱{{ number_format($hubAwaitingAcceptTotal, 2) }} Ready to Accept
                    </span>
                @endif
            </a>
        </div>

        @if($activeTab === 'rider_handoffs')
            {{-- Filters --}}
            <div class="card p-4">
                <form method="GET" action="{{ route('remittances.index') }}" class="flex flex-wrap items-end gap-3">
                    <input type="hidden" name="tab" value="rider_handoffs">
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
                            <a href="{{ route('remittances.index', ['tab' => 'rider_handoffs']) }}" class="btn btn-ghost">Clear</a>
                        @endif
                    </div>
                </form>
            </div>

            {{-- Remittances Table --}}
            <div class="card overflow-hidden">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tracking #</th>
                            <th>Rider</th>
                            <th>COD Collected</th>
                            <th>Logistics Cut (Income)</th>
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

                                $orderHubRows = $hubEscrows[$remittance->order_id] ?? collect();
                                $hubCut = (float) $orderHubRows->sum('amount');
                                if ($hubCut <= 0) { $hubCut = 30.0; }
                                $isRemitted = $orderHubRows->contains(fn($r) => $r->status === 'remitted') || $remittance->status === 'confirmed';

                                $modalPayload = [
                                    'kicker' => 'Remittance & Stakeholder Reconciliation',
                                    'title' => $trackingNo !== '—' ? $trackingNo : ('Order ' . $orderNo),
                                    'url' => $remittance->delivery ? route('deliveries.show', $remittance->delivery_id) : '',
                                    'urlLabel' => 'Open Delivery',
                                    'items' => [
                                        ['label' => 'Order #', 'value' => $orderNo],
                                        ['label' => 'Tracking #', 'value' => $trackingNo],
                                        ['label' => 'Rider', 'value' => $remittance->rider?->name ?? '—'],
                                        ['label' => 'Handoff Status', 'value' => ucfirst($remittance->status)],
                                        ['label' => 'COD Cash Collected', 'value' => ($info['expected'] ?? null) !== null ? '₱' . number_format((float) $info['expected'], 2) : '₱' . number_format((float) $remittance->amount, 2)],
                                        ['label' => 'Logistics Cut (Hub Fee)', 'value' => '₱' . number_format($hubCut, 2) . ' (' . ($isRemitted ? '✓ Remitted / Credited to Hub' : 'Pending Confirmation') . ')'],
                                        ['label' => 'Last-Mile Rider Fee', 'value' => '₱' . number_format((float) ($info['rider_fee'] ?? 50), 2)],
                                        ['label' => 'For Seller', 'value' => '₱' . number_format((float) ($info['seller_amount'] ?? $remittance->amount), 2)],
                                        ['label' => 'Variance', 'value' => $variance !== null ? (($variance > 0 ? '+' : '') . number_format($variance, 2) . ' (Balanced)') : '0.00 (Balanced)'],
                                        ['label' => 'Sent Date', 'value' => $remittance->sent_at?->format('M d, Y h:i A') ?? '—'],
                                        ['label' => 'Confirmed Date', 'value' => $remittance->confirmed_at?->format('M d, Y h:i A') ?? '—'],
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
                                <td class="font-bold text-[#1B1B1E]">₱{{ number_format((float) ($info['expected'] ?? $remittance->amount), 2) }}</td>
                                <td>
                                    @php
                                        $hubFirst = $orderHubRows->first();
                                        $hubStatus = $hubFirst?->status ?? ($isRemitted ? 'remitted' : 'pending_remittance');
                                    @endphp
                                    <div class="font-extrabold text-[#0E4A57]">₱{{ number_format($hubCut, 2) }}</div>
                                    @if($hubStatus === 'accepted')
                                        <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-1.5 py-0.5 rounded">
                                            <svg class="w-3 h-3 text-emerald-600 inline-block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                            Accepted by Hub
                                        </span>
                                    @elseif($hubStatus === 'remitted' && $hubFirst)
                                        <form method="POST" action="{{ route('remittances.hub-payouts.accept', $hubFirst->id) }}" class="inline mt-0.5" onsubmit="return confirm('Accept and confirm receipt of ₱{{ number_format($hubCut, 2) }} into hub records?');">
                                            @csrf
                                            <button type="submit" class="btn btn-xs bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[10px] py-0.5 px-1.5 rounded shadow-sm inline-flex items-center gap-0.5">
                                                Accept Remittance
                                            </button>
                                        </form>
                                    @elseif($hubStatus === 'pending_remittance')
                                        <span class="inline-flex items-center gap-0.5 text-[10px] font-semibold text-amber-700 bg-amber-50 border border-amber-200 px-1.5 py-0.5 rounded">
                                            ⏳ Awaiting Admin
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-0.5 text-[10px] font-semibold text-gray-600 bg-gray-50 border border-gray-200 px-1.5 py-0.5 rounded">
                                            Held in Escrow
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if($variance !== null)
                                        <span class="badge {{ abs($variance) > 0.009 ? 'badge-cancelled' : 'badge-completed' }}" title="{{ abs($variance) > 0.009 ? 'Discrepancy detected' : 'Exact match: collected cash matches payouts perfectly with 0 discrepancy' }}">
                                            {{ $variance > 0 ? '+' : '' }}{{ number_format($variance, 2) }}
                                            @if(abs($variance) <= 0.009)
                                                <span class="text-[10px] font-normal opacity-85 ml-0.5">(Balanced)</span>
                                            @endif
                                        </span>
                                    @else
                                        <span class="badge badge-completed">0.00 <span class="text-[10px] font-normal opacity-85 ml-0.5">(Balanced)</span></span>
                                    @endif
                                </td>
                                <td>
                                    <x-status-badge :status="$remittance->status" />
                                </td>
                                <td class="text-right">
                                    <div class="inline-flex items-center justify-end gap-1.5">
                                        <button type="button" class="btn btn-soft btn-xs" data-view-record='@json($modalPayload)'>View</button>
                                        @if($remittance->status !== 'confirmed')
                                            <form method="POST" action="{{ route('remittances.confirm-received', $remittance) }}" class="inline" onsubmit="return confirm('Confirm receipt of ₱{{ number_format((float) ($info['seller_amount'] ?? $remittance->amount), 2) }} COD cash from rider {{ $remittance->rider?->name ?? 'rider' }}?');">
                                                @csrf
                                                <button type="submit" class="btn btn-xs bg-emerald-600 hover:bg-emerald-700 text-white font-medium shadow-sm transition inline-flex items-center gap-1">
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                                    Confirm Cash Received
                                                </button>
                                            </form>
                                        @else
                                            <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                                                <svg class="w-3.5 h-3.5 text-emerald-600 inline-block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                                Confirmed at Hub
                                            </span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-12 text-center">
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

        @else
            {{-- TAB 2: Hub Earnings & Escrow Payout Confirmation --}}
            <div class="card overflow-hidden">
                <div class="p-4 bg-gradient-to-r from-[#E6F4F1] to-white border-b border-[#CBD5E1]">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <h2 class="text-base font-bold text-[#0E4A57]">Logistics Hub Income & Settlement Confirmation</h2>
                            <p class="text-xs text-[#64748B] mt-0.5">Every shipment processed by this hub earns a guaranteed ₱30.00 fee. Inter-hub transfers earn an additional ₱30.00 destination hub fee.</p>
                        </div>
                        <div class="text-right">
                            <span class="text-xs text-[#64748B]">Recorded Hub Revenue:</span>
                            <div class="text-2xl font-black text-[#0E4A57]">₱{{ number_format($hubAcceptedTotal ?? 0, 2) }}</div>
                            @if(($hubAwaitingAcceptTotal ?? 0) > 0)
                                <div class="text-xs font-bold text-amber-600 mt-0.5">₱{{ number_format($hubAwaitingAcceptTotal, 2) }} awaiting acceptance below</div>
                            @endif
                        </div>
                    </div>
                </div>

                <table class="table">
                    <thead>
                        <tr>
                            <th>Order / Tracking #</th>
                            <th>Shipment Role</th>
                            <th>Amount Earned</th>
                            <th>Rule Applied</th>
                            <th>Settlement Status</th>
                            <th>Date</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($hubPayoutsList as $payout)
                            @php
                                $dUrl = $payout->delivery_id ? route('deliveries.show', $payout->delivery_id) : null;
                            @endphp
                            <tr>
                                <td>
                                    @if($payout->delivery)
                                        <a href="{{ $dUrl }}" class="font-bold text-[#16697A] hover:underline">{{ $payout->delivery->tracking_number }}</a>
                                    @else
                                        <span class="font-bold text-[#1B1B1E]">#{{ $payout->order_id }}</span>
                                    @endif
                                    <div class="text-[11px] text-[#6E6E73]">Order: {{ $payout->order?->order_number ?? ('#' . $payout->order_id) }}</div>
                                </td>
                                <td>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold {{ $payout->recipient_role === 'destination_hub' ? 'bg-indigo-100 text-indigo-800' : 'bg-teal-light text-teal-dark' }}">
                                        {{ $payout->recipient_role === 'destination_hub' ? 'Destination Hub Intake (₱30)' : 'Origin Hub Handling (₱30)' }}
                                    </span>
                                </td>
                                <td class="font-extrabold text-base text-[#0E4A57]">
                                    ₱{{ number_format((float) $payout->amount, 2) }}
                                </td>
                                <td class="text-xs text-[#6E6E73]">
                                    {{ $payout->rule_applied }}
                                </td>
                                <td>
                                    @if($payout->status === 'accepted')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                            <svg class="w-3.5 h-3.5 text-emerald-700 inline-block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                            Confirmed in Hub Records
                                        </span>
                                    @elseif($payout->status === 'remitted')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-800 border border-blue-200">
                                            Sent by Admin (Awaiting Acceptance)
                                        </span>
                                    @elseif($payout->status === 'pending_remittance')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                                            <svg class="w-3.5 h-3.5 text-amber-700 inline-block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                            Awaiting Admin Settlement
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
                                            Held in Escrow
                                        </span>
                                    @endif
                                </td>
                                <td class="text-xs text-[#6E6E73]">
                                    {{ $payout->remitted_at ? $payout->remitted_at->format('M d, Y h:i A') : ($payout->updated_at?->format('M d, Y') ?? '—') }}
                                </td>
                                <td class="text-right">
                                    @if($payout->status === 'remitted')
                                        <form method="POST" action="{{ route('remittances.hub-payouts.accept', $payout->id) }}" class="inline" onsubmit="return confirm('Accept and confirm receipt of ₱{{ number_format($payout->amount, 2) }} into hub revenue records?');">
                                            @csrf
                                            <button type="submit" class="btn btn-xs bg-emerald-600 hover:bg-emerald-700 text-white font-bold shadow-sm inline-flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                                Accept Remittance
                                            </button>
                                        </form>
                                    @elseif($payout->status === 'accepted')
                                        <span class="text-xs text-emerald-700 font-bold inline-flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5 text-emerald-600 inline-block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                            Recorded
                                        </span>
                                    @else
                                        <span class="text-xs text-[#6E6E73]">Awaiting Release</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-12 text-center">
                                    <p class="text-sm font-bold text-[#1B1B1E]">No hub earnings recorded yet</p>
                                    <p class="mt-1 text-xs text-[#6E6E73]">When deliveries are marked delivered and settled, hub fees are automatically credited here.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                @if($hubPayoutsList->total() > 0)
                    <div class="p-4 border-t border-[#E8E6E0] flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-[#6E6E73]">
                        <p>Showing <strong class="text-[#1B1B1E]">{{ $hubPayoutsList->firstItem() }}–{{ $hubPayoutsList->lastItem() }}</strong> of <strong class="text-[#1B1B1E]">{{ $hubPayoutsList->total() }}</strong> hub earnings records</p>
                        <p>Page {{ $hubPayoutsList->currentPage() }} of {{ $hubPayoutsList->lastPage() }}</p>
                    </div>
                @endif
            </div>

            <div>{{ $hubPayoutsList->links() }}</div>
        @endif
    </div>
</x-app-layout>