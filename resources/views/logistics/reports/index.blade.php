<x-app-layout>
    @php
        $tabs = [
            'all' => 'All Reports',
            'delivery' => 'Delivery Report',
            'center' => 'Center Report',
            'area' => 'Service Area Report',
            'rider' => 'Rider Report',
            'financial' => 'Financial Report',
        ];
        $tab = in_array(request('tab', 'delivery'), array_keys($tabs)) ? request('tab', 'delivery') : 'delivery';
        $tabUrl = fn ($t) => route('reports.index', collect(request()->query())->except('tab')->merge(['tab' => $t])->all());
        $f = $filters;
    @endphp

    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-[#1B1B1E]">Reports</h1>
                <p class="text-xs text-[#6E6E73] mt-0.5">Choose a report, set your filters, then download a PDF or CSV.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('reports.export.csv', request()->query()) }}" class="btn btn-outline">
                    <x-icon name="download" class="w-4 h-4" />
                    Download CSV
                </a>
                <a href="{{ route('reports.export', request()->query()) }}" class="btn btn-primary">
                    <x-icon name="download" class="w-4 h-4" />
                    {{ $tab === 'all' ? 'Download Consolidated PDF' : 'Download PDF' }}
                </a>
            </div>
        </div>

        {{-- Tabs --}}
        <div class="card p-2 flex flex-wrap gap-1.5">
            @foreach($tabs as $key => $label)
                <a href="{{ $tabUrl($key) }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ $tab === $key ? 'bg-[#16697A] text-white' : 'text-[#6E6E73] hover:bg-[#F0EEE9] hover:text-[#1B1B1E]' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('reports.index') }}" class="card p-4">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="flex flex-col lg:flex-row lg:items-end gap-3">
                <div>
                    <label class="field-label">From</label>
                    <input type="date" name="date_from" value="{{ $f['dateFrom'] ?? request('date_from') }}" class="input">
                </div>
                <div>
                    <label class="field-label">To</label>
                    <input type="date" name="date_to" value="{{ $f['dateTo'] ?? request('date_to') }}" class="input">
                </div>
                @if($tab !== 'center')
                    <div>
                        <label class="field-label">Center</label>
                        <select name="center_id" class="select">
                            <option value="0">All Centers</option>
                            @foreach($centers as $c)
                                <option value="{{ $c->id }}" @selected((int) ($f['centerId'] ?? 0) === $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                @if($tab === 'delivery' || $tab === 'rider' || $tab === 'all')
                    <div>
                        <label class="field-label">Rider</label>
                        <select name="rider_id" class="select">
                            <option value="0">All Riders</option>
                            @foreach($riders as $r)
                                <option value="{{ $r->id }}" @selected((int) ($f['riderId'] ?? 0) === $r->id)>{{ $r->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                @if($tab === 'delivery' || $tab === 'area' || $tab === 'all')
                    <div>
                        <label class="field-label">Service Area</label>
                        <select name="service_area_id" class="select">
                            <option value="0">All Areas</option>
                            @foreach($serviceAreas as $a)
                                <option value="{{ $a->id }}" @selected((int) ($f['serviceAreaId'] ?? 0) === $a->id)>{{ $a->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <button type="submit" class="btn btn-primary">Apply</button>
            </div>
        </form>

        @if($tab === 'all')
            <div class="card p-4 bg-[#EAF4F3] border-[#16697A]/20 text-xs text-[#0E4A57]">
                <strong>All Reports</strong> — consolidated view of Delivery, Center, Service Area, Rider, and Financial reports under the selected filters.
            </div>
        @endif

        {{-- Delivery Report --}}
        @if($tab === 'delivery' || $tab === 'all')
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3.5">
                @php
                    $cards = [
                        ['Total', $deliveryStats['total'] ?? 0],
                        ['Received', $deliveryStats['received'] ?? 0],
                        ['Scanned', $deliveryStats['scanned'] ?? 0],
                        ['Sorted', $deliveryStats['sorted'] ?? 0],
                        ['Waiting', $deliveryStats['waiting_for_rider'] ?? 0],
                        ['Assigned', $deliveryStats['assigned'] ?? 0],
                        ['Picked Up', $deliveryStats['picked_up'] ?? 0],
                        ['Out for Delivery', $deliveryStats['out_for_delivery'] ?? 0],
                        ['Delivered', $deliveryStats['delivered'] ?? 0],
                        ['Failed', $deliveryStats['failed'] ?? 0],
                        ['Cancelled', $deliveryStats['cancelled'] ?? 0],
                    ];
                @endphp
                @foreach($cards as [$label, $val])
                    <div class="stat-card">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-[#6E6E73]">{{ $label }}</p>
                        <p class="text-2xl font-extrabold text-[#1B1B1E] mt-1">{{ $val }}</p>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Center Report --}}
        @if($tab === 'center' || $tab === 'all')
            <div class="card overflow-hidden">
                <div class="px-5 py-3.5 border-b border-[#E8E6E0]">
                    <h2 class="text-sm font-bold text-[#1B1B1E]">Center Report</h2>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Center</th>
                            <th>City</th>
                            <th>Deliveries</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($centerStats as $c)
                            @php
                                $centerModal = [
                                    'kicker' => 'Center Report Summary',
                                    'title' => $c['name'],
                                    'items' => [
                                        ['label' => 'Center', 'value' => $c['name']],
                                        ['label' => 'City', 'value' => $c['city'] ?? '—'],
                                        ['label' => 'Deliveries', 'value' => (string) ($c['deliveries_count'] ?? 0)],
                                        ['label' => 'Service Areas', 'value' => (string) ($c['service_areas_count'] ?? 0)],
                                        ['label' => 'Riders', 'value' => (string) ($c['riders_count'] ?? 0)],
                                    ],
                                ];
                            @endphp
                            <tr>
                                <td class="font-semibold text-[#1B1B1E]">{{ $c['name'] }}</td>
                                <td class="text-[#6E6E73]">{{ $c['city'] }}</td>
                                <td class="font-bold text-[#1B1B1E]">{{ $c['deliveries_count'] ?? 0 }}</td>
                                <td class="text-right">
                                    <button type="button" class="btn btn-soft btn-xs" data-view-record='@json($centerModal)'>View</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-10 text-center text-xs text-[#6E6E73]">No centers found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Service Area Report --}}
        @if($tab === 'area' || $tab === 'all')
            <div class="card overflow-hidden">
                <div class="px-5 py-3.5 border-b border-[#E8E6E0]">
                    <h2 class="text-sm font-bold text-[#1B1B1E]">Service Area Report</h2>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Area</th>
                            <th>Center</th>
                            <th>Deliveries</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($areaStats as $a)
                            @php
                                $centerName = \App\Models\ServiceArea::find($a['id'])?->logisticsCenter?->name ?? '—';
                                $areaModal = [
                                    'kicker' => 'Service Area Summary',
                                    'title' => $a['name'],
                                    'items' => [
                                        ['label' => 'Area', 'value' => $a['name']],
                                        ['label' => 'Center', 'value' => $centerName],
                                        ['label' => 'Deliveries', 'value' => (string) ($a['deliveries_count'] ?? 0)],
                                        ['label' => 'Riders', 'value' => (string) ($a['riders_count'] ?? 0)],
                                    ],
                                ];
                            @endphp
                            <tr>
                                <td class="font-semibold text-[#1B1B1E]">{{ $a['name'] }}</td>
                                <td class="text-[#6E6E73]">{{ $centerName }}</td>
                                <td class="font-bold text-[#1B1B1E]">{{ $a['deliveries_count'] ?? 0 }}</td>
                                <td class="text-right">
                                    <button type="button" class="btn btn-soft btn-xs" data-view-record='@json($areaModal)'>View</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-10 text-center text-xs text-[#6E6E73]">No service areas found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Rider Report --}}
        @if($tab === 'rider' || $tab === 'all')
            <div class="card overflow-hidden">
                <div class="px-5 py-3.5 border-b border-[#E8E6E0]">
                    <h2 class="text-sm font-bold text-[#1B1B1E]">Rider Report</h2>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Rider</th>
                            <th>Vehicle</th>
                            <th>Delivered</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($riderStats as $r)
                            @php
                                $riderModal = [
                                    'kicker' => 'Rider Report Summary',
                                    'title' => $r['name'],
                                    'items' => [
                                        ['label' => 'Rider', 'value' => $r['name']],
                                        ['label' => 'Vehicle', 'value' => ucfirst($r['vehicle_type'] ?? '—')],
                                        ['label' => 'Total Deliveries', 'value' => (string) ($r['total_deliveries'] ?? 0)],
                                        ['label' => 'Active Deliveries', 'value' => (string) ($r['active_deliveries'] ?? 0)],
                                        ['label' => 'Completed Deliveries', 'value' => (string) ($r['completed_deliveries'] ?? 0)],
                                    ],
                                ];
                            @endphp
                            <tr>
                                <td class="font-semibold text-[#1B1B1E]">{{ $r['name'] }}</td>
                                <td class="text-[#6E6E73]">{{ ucfirst($r['vehicle_type'] ?? '—') }}</td>
                                <td class="font-bold text-[#1B1B1E]">{{ $r['completed_deliveries'] ?? 0 }}</td>
                                <td class="text-right">
                                    <button type="button" class="btn btn-soft btn-xs" data-view-record='@json($riderModal)'>View</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-10 text-center text-xs text-[#6E6E73]">No riders found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Financial Report --}}
        @if($tab === 'financial' || $tab === 'all')
            @php
                $fs = $financialStats;
                $currency = fn ($v) => '₱' . number_format((float) ($v ?? 0), 2);
            @endphp
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5">
                <div class="stat-card">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-[#6E6E73]">Transactions</p>
                    <p class="text-2xl font-extrabold text-[#1B1B1E] mt-1">{{ number_format((int) ($fs['total_transactions'] ?? 0)) }}</p>
                </div>
                <div class="stat-card">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-[#6E6E73]">Total Amount</p>
                    <p class="text-2xl font-extrabold text-[#1B1B1E] mt-1">{{ $currency($fs['total_amount'] ?? 0) }}</p>
                </div>
                <div class="stat-card">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-[#6E6E73]">Rider Fees</p>
                    <p class="text-2xl font-extrabold text-[#1B1B1E] mt-1">{{ $currency($fs['total_rider_fees'] ?? 0) }}</p>
                </div>
                <div class="stat-card">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-[#6E6E73]">Management Commissions</p>
                    <p class="text-2xl font-extrabold text-[#16697A] mt-1">{{ $currency($fs['total_commissions'] ?? 0) }}</p>
                </div>
            </div>
            <div class="card p-5">
                <h3 class="text-sm font-bold text-[#1B1B1E] mb-3">Completed Transactions Breakdown</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="p-3.5 rounded-xl bg-[#FAF9F6] border border-[#E8E6E0]">
                        <p class="text-[11px] font-bold uppercase text-[#6E6E73]">Completed Amount</p>
                        <p class="text-lg font-extrabold text-[#1B1B1E] mt-1">{{ $currency($fs['completed_amount'] ?? 0) }}</p>
                    </div>
                    <div class="p-3.5 rounded-xl bg-[#FAF9F6] border border-[#E8E6E0]">
                        <p class="text-[11px] font-bold uppercase text-[#6E6E73]">Completed Rider Fees</p>
                        <p class="text-lg font-extrabold text-[#1B1B1E] mt-1">{{ $currency($fs['completed_rider_fees'] ?? 0) }}</p>
                    </div>
                    <div class="p-3.5 rounded-xl bg-[#FAF9F6] border border-[#E8E6E0]">
                        <p class="text-[11px] font-bold uppercase text-[#6E6E73]">Completed Commissions</p>
                        <p class="text-lg font-extrabold text-[#16697A] mt-1">{{ $currency($fs['completed_commissions'] ?? 0) }}</p>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
