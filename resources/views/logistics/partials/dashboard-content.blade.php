@php
    $dateQuery = array_filter(['date_from' => $dateFrom ?: null, 'date_to' => $dateTo ?: null]);
    $statusMap = array_flip($status_labels);
    $hasTrendData = array_sum($trendsChart['created']) + array_sum($trendsChart['delivered']) + array_sum($trendsChart['failed']) > 0;
@endphp
<div class="logistics-dashboard space-y-6">
    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-bold text-[#1B1B1E]">Logistics Dashboard</h1>
                @if(isset($staffCenter) && $staffCenter)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-[#E6F4F1] text-[#0E4A57] border border-[#16697A]/20">
                        <span class="w-2 h-2 rounded-full bg-[#16697A]"></span>
                        {{ $staffCenter->name }} ({{ $staffCenter->code ?? 'HUB' }})
                    </span>
                @elseif($staffCenterId === null)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700 border border-gray-200">
                        <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                        HQ Admin · All Logistics Centers
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                        Awaiting Center Assignment
                    </span>
                @endif
            </div>
            <p class="text-xs text-[#6E6E73] mt-1">
                {{ now()->format('l, F j, Y') }} &middot;
                @if(isset($staffCenter) && $staffCenter)
                    Assigned Center: <strong class="text-[#1B1B1E]">{{ $staffCenter->name }}</strong>
                    @if($staffCenter->city) &middot; {{ $staffCenter->city }} @endif
                @elseif($staffCenterId === null)
                    All Logistics Centers (Global Admin Oversight)
                @else
                    Awaiting Center Assignment
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a class="btn btn-outline" href="{{ route('tracking.index') }}">
                <x-icon name="search" class="w-4 h-4" />
                Track a Parcel
            </a>
            <a class="btn btn-primary" href="{{ route('deliveries.index') }}">
                View Deliveries
                <span aria-hidden="true">&rarr;</span>
            </a>
        </div>
    </div>

    {{-- Top 4 KPI Stat Cards + Date Filter --}}
    <div class="card p-5 space-y-4">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 pb-3 border-b border-[#E8E6E0]">
            <div>
                <h2 class="text-sm font-bold text-[#1B1B1E]">Delivery Summary</h2>
                <p class="text-xs text-[#6E6E73]">
                    {{ $dateFilterActive ? 'Deliveries registered in your selected date range.' : 'All registered deliveries across all dates.' }}
                </p>
            </div>
            <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap items-end gap-2">
                <input type="hidden" name="trends_range" value="{{ $trendsRange }}">
                <div>
                    <label class="field-label" for="date_from">From</label>
                    <input id="date_from" type="date" name="date_from" value="{{ $dateFrom }}" max="{{ $dateTo }}" class="input !w-auto">
                </div>
                <div>
                    <label class="field-label" for="date_to">To</label>
                    <input id="date_to" type="date" name="date_to" value="{{ $dateTo }}" min="{{ $dateFrom }}" class="input !w-auto">
                </div>
                <button type="submit" class="btn btn-primary">Apply Dates</button>
                @if($dateFilterActive)
                    <a class="btn btn-ghost" href="{{ route('dashboard', ['trends_range' => $trendsRange]) }}">Clear</a>
                @endif
            </form>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5">
            @foreach([
                ['key' => 'pending', 'label' => 'Pending', 'detail' => 'Waiting for a rider', 'bg' => 'bg-amber-50 text-amber-700', 'icon' => 'clock'],
                ['key' => 'in_transit', 'label' => 'In Transit', 'detail' => 'Assigned or with a rider', 'bg' => 'bg-blue-50 text-blue-700', 'icon' => 'truck'],
                ['key' => 'delivered', 'label' => 'Delivered', 'detail' => 'Handed to recipient', 'bg' => 'bg-emerald-50 text-emerald-700', 'icon' => 'check'],
                ['key' => 'failed', 'label' => 'Failed / Exceptions', 'detail' => 'Failed, lost or damaged', 'bg' => 'bg-red-50 text-red-700', 'icon' => 'alert-triangle'],
            ] as $metric)
                <a class="stat-card block hover:border-[#16697A]" href="{{ route('deliveries.index', array_merge(['status' => $metric['key']], $dateQuery)) }}">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="text-[11px] font-bold uppercase tracking-wider text-[#6E6E73]">{{ $metric['label'] }}</div>
                            <div class="text-2xl font-extrabold text-[#1B1B1E] mt-1">{{ number_format($statusGroupCounts[$metric['key']] ?? 0) }}</div>
                            <div class="text-[11px] text-[#6E6E73] mt-1">{{ $metric['detail'] }}</div>
                        </div>
                        <div class="stat-icon {{ $metric['bg'] }}">
                            <x-icon :name="$metric['icon']" class="w-4 h-4" />
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    </div>

    {{-- Today at a Glance + Next Actions Queue --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="card p-5 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-bold text-[#1B1B1E]">Today at a Glance</h2>
                    <span class="badge badge-confirmed">{{ now()->format('M j') }}</span>
                </div>
                <p class="text-xs text-[#6E6E73] mt-0.5">Live delivery activity for today</p>
                <div class="mt-4 p-3.5 rounded-xl bg-[#EAF4F3] border border-[#16697A]/15">
                    <div class="text-xs font-semibold text-[#0E4A57]">New Deliveries Today</div>
                    <div class="text-3xl font-extrabold text-[#16697A] mt-0.5">{{ number_format($todayCounts['created']) }}</div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3 mt-3 pt-3 border-t border-[#E8E6E0]">
                <div class="p-2.5 rounded-lg bg-[#FAF9F6] border border-[#E8E6E0]">
                    <div class="text-[11px] font-bold uppercase text-[#6E6E73]">Delivered Today</div>
                    <div class="text-lg font-extrabold text-emerald-700 mt-0.5">{{ number_format($todayCounts['delivered']) }}</div>
                </div>
                <div class="p-2.5 rounded-lg bg-[#FAF9F6] border border-[#E8E6E0]">
                    <div class="text-[11px] font-bold uppercase text-[#6E6E73]">Failed Today</div>
                    <div class="text-lg font-extrabold text-red-600 mt-0.5">{{ number_format($todayCounts['failed']) }}</div>
                </div>
            </div>
        </div>

        <div class="card p-5 lg:col-span-2">
            <div class="mb-3">
                <h2 class="text-sm font-bold text-[#1B1B1E]">Your Next Actions</h2>
                <p class="text-xs text-[#6E6E73]">Select a queue to review parcels that need attention.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <a class="p-3.5 rounded-xl border border-[#E8E6E0] hover:border-[#16697A] bg-[#FAF9F6] hover:bg-white transition flex items-center justify-between" href="{{ route('deliveries.index', ['status' => 'waiting_for_rider']) }}">
                    <div>
                        <div class="text-xs font-semibold text-[#1B1B1E]">Waiting for a Rider</div>
                        <div class="text-[11px] text-[#6E6E73]">Unassigned parcels</div>
                    </div>
                    <span class="text-lg font-extrabold text-[#16697A]">{{ number_format($needsAttention['waitingForRider']) }} &rarr;</span>
                </a>
                <a class="p-3.5 rounded-xl border border-[#E8E6E0] hover:border-[#16697A] bg-[#FAF9F6] hover:bg-white transition flex items-center justify-between" href="{{ route('sorting-center.index') }}">
                    <div>
                        <div class="text-xs font-semibold text-[#1B1B1E]">Awaiting Sorting / Dispatch</div>
                        <div class="text-[11px] text-[#6E6E73]">In sorting center workflow</div>
                    </div>
                    <span class="text-lg font-extrabold text-[#16697A]">{{ number_format($needsAttention['processing']) }} &rarr;</span>
                </a>
                <a class="p-3.5 rounded-xl border border-[#E8E6E0] hover:border-[#16697A] bg-[#FAF9F6] hover:bg-white transition flex items-center justify-between" href="{{ route('pickup-requests.index', ['status' => 'pending']) }}">
                    <div>
                        <div class="text-xs font-semibold text-[#1B1B1E]">Pickup Requests to Review</div>
                        <div class="text-[11px] text-[#6E6E73]">Pending seller pickups</div>
                    </div>
                    <span class="text-lg font-extrabold text-[#16697A]">{{ number_format($needsAttention['pickups']) }} &rarr;</span>
                </a>
                <a class="p-3.5 rounded-xl border border-[#E8E6E0] hover:border-[#16697A] bg-[#FAF9F6] hover:bg-white transition flex items-center justify-between" href="{{ route('deliveries.index', ['status' => 'exceptions']) }}">
                    <div>
                        <div class="text-xs font-semibold text-[#1B1B1E]">Failed, Lost or Damaged</div>
                        <div class="text-[11px] text-[#6E6E73]">Delivery exceptions</div>
                    </div>
                    <span class="text-lg font-extrabold text-red-600">{{ number_format($needsAttention['failed']) }} &rarr;</span>
                </a>
            </div>
        </div>
    </div>

    {{-- Operational Alerts --}}
    @if($alertTotal > 0)
        <div class="card p-5 border-amber-200 bg-amber-50/40">
            <div class="mb-3">
                <h2 class="text-sm font-bold text-[#1B1B1E]">Operational Alerts</h2>
                <p class="text-xs text-[#6E6E73]">Overdue work that needs immediate review.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach($operationalAlerts as $alert)
                    <a class="p-3.5 rounded-xl bg-white border border-[#E8E6E0] hover:border-[#16697A] flex items-center justify-between gap-3 transition" href="{{ $alert['url'] }}">
                        <div>
                            <div class="text-xs font-bold text-[#1B1B1E]">{{ $alert['title'] }}</div>
                            <p class="text-[11px] text-[#6E6E73] mt-0.5">
                                {{ $alert['action'] }}
                                @if($alert['oldest']) &middot; Oldest: {{ $alert['oldest']->diffForHumans() }} @endif
                            </p>
                        </div>
                        <span class="badge {{ $alert['severity'] === 'danger' ? 'badge-cancelled' : 'badge-pending' }}">
                            {{ number_format($alert['count']) }} &rarr;
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Charts & Finances --}}
    <div x-data="dashboardCharts()" x-init="initCharts()" class="space-y-6">
        <div id="statusKeyMap" hidden data-map='@json($statusMap)'></div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            {{-- Delivery Trends --}}
            <div class="card p-5 lg:col-span-2">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <div>
                        <h2 class="text-sm font-bold text-[#1B1B1E]">Delivery Trends</h2>
                        <p class="text-xs text-[#6E6E73]">Daily activity over the last {{ $trendsRange }} days</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="inline-flex rounded-lg bg-[#F0EEE9] p-0.5 text-xs">
                            <a href="{{ route('dashboard', array_merge($dateQuery, ['trends_range' => 7])) }}"
                               class="px-2.5 py-1 rounded-md font-semibold {{ $trendsRange === 7 ? 'bg-white text-[#16697A] shadow-sm' : 'text-[#6E6E73]' }}">7D</a>
                            <a href="{{ route('dashboard', array_merge($dateQuery, ['trends_range' => 30])) }}"
                               class="px-2.5 py-1 rounded-md font-semibold {{ $trendsRange === 30 ? 'bg-white text-[#16697A] shadow-sm' : 'text-[#6E6E73]' }}">30D</a>
                        </div>
                        <button type="button" class="btn btn-ghost btn-sm" @click="openTrendsModal()">Expand &#8599;</button>
                    </div>
                </div>
                @if($hasTrendData)
                    <div class="ld-chart-box">
                        <canvas id="trendsChart" role="img" aria-label="Daily created, delivered and failed shipments"></canvas>
                    </div>
                    <div x-show="selectedTrendDate" x-cloak class="mt-3 p-2.5 rounded-lg bg-[#FAF9F6] border border-[#E8E6E0] flex items-center justify-between text-xs">
                        <span><strong x-text="selectedTrendDate"></strong> &middot; <span x-text="selectedTrendDetail"></span></span>
                        <a class="font-semibold text-[#16697A]" :href="trendLink">View Deliveries &rarr;</a>
                    </div>
                @else
                    <div class="py-12 text-center text-xs text-[#6E6E73]">
                        <strong class="block text-sm text-[#1B1B1E] mb-1">No activity in this period</strong>
                        New delivery activity will appear here.
                    </div>
                @endif
            </div>

            {{-- Delivery Status Breakdown --}}
            <div class="card p-5">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <div>
                        <h2 class="text-sm font-bold text-[#1B1B1E]">Delivery Status</h2>
                        <p class="text-xs text-[#6E6E73]">{{ number_format($statusChart['total']) }} total deliveries</p>
                    </div>
                    <button type="button" class="btn btn-ghost btn-sm" @click="openStatusModal()">Expand &#8599;</button>
                </div>
                @if($statusChart['total'] > 0)
                    <div class="ld-chart-box ld-chart-box--compact">
                        <canvas id="statusChart" role="img" aria-label="Delivery status distribution"></canvas>
                    </div>
                    <div class="mt-3 space-y-1.5 max-h-40 overflow-y-auto pr-1">
                        @foreach($statusChart['labels'] as $i => $label)
                            @php $statusKey = $statusMap[$label] ?? ''; @endphp
                            <div class="flex items-center justify-between text-xs py-1 border-b border-[#E8E6E0] last:border-0">
                                <a href="{{ route('deliveries.index', array_merge($dateQuery, ['status' => $statusKey])) }}" class="flex items-center gap-2 text-[#1B1B1E] hover:text-[#16697A]">
                                    <span class="w-2.5 h-2.5 rounded-full inline-block" style="background:{{ $statusChart['colors'][$i] }}"></span>
                                    <span>{{ $label }}</span>
                                </a>
                                <span class="font-semibold text-[#1B1B1E]">{{ number_format($statusChart['data'][$i]) }} <span class="text-[#6E6E73] font-normal">({{ round($statusChart['data'][$i] / $statusChart['total'] * 100) }}%)</span></span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-12 text-center text-xs text-[#6E6E73]">
                        <strong class="block text-sm text-[#1B1B1E] mb-1">No deliveries to show</strong>
                        Status distribution will appear once deliveries are registered.
                    </div>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            {{-- Center Performance --}}
            <div class="card p-5 lg:col-span-2">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <div>
                        <h2 class="text-sm font-bold text-[#1B1B1E]">Center Performance</h2>
                        <p class="text-xs text-[#6E6E73]">{{ $staffCenterId !== null ? 'Delivery totals for your assigned center' : 'Delivery totals across logistics centers' }}</p>
                    </div>
                    <button type="button" class="btn btn-ghost btn-sm" @click="openCenterModal()">Expand &#8599;</button>
                </div>
                @if(!$centerPerformanceChart['empty'])
                    <div class="ld-chart-box" style="height:{{ max(240, count($centerPerformanceChart['labels']) * 52) }}px">
                        <canvas id="centerChart" role="img" aria-label="Center performance chart"></canvas>
                    </div>
                @else
                    <div class="py-12 text-center text-xs text-[#6E6E73]">
                        <strong class="block text-sm text-[#1B1B1E] mb-1">No center activity yet</strong>
                        Center totals will appear when deliveries are assigned.
                    </div>
                @endif
            </div>

            {{-- Today's Finances --}}
            <div class="card p-5 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h2 class="text-sm font-bold text-[#1B1B1E]">Today&rsquo;s Finances</h2>
                            <p class="text-xs text-[#6E6E73]">Recorded transactions for {{ now()->format('F j') }}</p>
                        </div>
                        <a class="btn btn-soft btn-xs" href="{{ route('transactions.index') }}">View All &rarr;</a>
                    </div>
                    <div class="grid grid-cols-1 gap-2.5">
                        <div class="p-3 rounded-xl bg-[#FAF9F6] border border-[#E8E6E0] flex items-center justify-between">
                            <span class="text-xs text-[#6E6E73]">Transaction Amount</span>
                            <strong class="text-sm font-extrabold text-[#1B1B1E]">&#8369;{{ number_format($financialToday['amount'], 2) }}</strong>
                        </div>
                        <div class="p-3 rounded-xl bg-[#FAF9F6] border border-[#E8E6E0] flex items-center justify-between">
                            <span class="text-xs text-[#6E6E73]">Rider Fees</span>
                            <strong class="text-sm font-bold text-[#1B1B1E]">&#8369;{{ number_format($financialToday['rider_fee'], 2) }}</strong>
                        </div>
                        <div class="p-3 rounded-xl bg-[#FAF9F6] border border-[#E8E6E0] flex items-center justify-between">
                            <span class="text-xs text-[#6E6E73]">Commission</span>
                            <strong class="text-sm font-bold text-emerald-700">&#8369;{{ number_format($financialToday['commission'], 2) }}</strong>
                        </div>
                        <div class="p-3 rounded-xl bg-[#FAF9F6] border border-[#E8E6E0] flex items-center justify-between">
                            <span class="text-xs text-[#6E6E73]">Transactions Count</span>
                            <strong class="text-sm font-bold text-[#1B1B1E]">{{ number_format($financialToday['count']) }}</strong>
                        </div>
                    </div>
                </div>
                <p class="text-[11px] text-[#6E6E73] mt-3">Covers today&rsquo;s recorded transactions only.</p>
            </div>
        </div>

        {{-- Expanded Chart Modals --}}
        @foreach([
            ['key' => 'Status', 'id' => 'status', 'title' => 'Delivery Status Breakdown', 'canvas' => 'statusChartModal'],
            ['key' => 'Trends', 'id' => 'trends', 'title' => 'Delivery Trends', 'canvas' => 'trendsChartModal'],
            ['key' => 'Center', 'id' => 'center', 'title' => 'Center Performance', 'canvas' => 'centerChartModal'],
        ] as $dialog)
            <template x-teleport="body">
                <div class="ld-modal-backdrop" data-dialog="{{ $dialog['id'] }}" x-show="show{{ $dialog['key'] }}Modal" x-cloak role="dialog" aria-modal="true" @keydown.escape.window="if(show{{ $dialog['key'] }}Modal) close{{ $dialog['key'] }}Modal()" @keydown.tab="trapDialog($event)">
                    <div class="ld-modal ld-modal--wide" @click.outside="close{{ $dialog['key'] }}Modal()">
                        <div class="flex items-center justify-between pb-3 mb-4 border-b border-[#E8E6E0]">
                            <div>
                                <h2 class="text-base font-bold text-[#1B1B1E]">{{ $dialog['title'] }}</h2>
                                <p class="text-xs text-[#6E6E73]">Expanded chart and breakdown table</p>
                            </div>
                            <button type="button" class="btn btn-outline btn-sm" @click="close{{ $dialog['key'] }}Modal()" data-dialog-close>Close</button>
                        </div>
                        @if(($dialog['id'] === 'status' && $statusChart['total'] > 0) || ($dialog['id'] === 'trends' && $hasTrendData) || ($dialog['id'] === 'center' && !$centerPerformanceChart['empty']))
                            <div class="ld-modal-chart mb-4"><canvas id="{{ $dialog['canvas'] }}"></canvas></div>
                        @endif
                        <table class="table">
                            @if($dialog['id'] === 'status')
                                <thead><tr><th>Status</th><th>Deliveries</th><th>Share</th></tr></thead>
                                <tbody>@forelse($statusChart['labels'] as $i => $label)<tr><td><a class="font-semibold text-[#16697A]" href="{{ route('deliveries.index', array_merge($dateQuery, ['status' => $statusMap[$label] ?? ''])) }}">{{ $label }}</a></td><td>{{ number_format($statusChart['data'][$i]) }}</td><td>{{ $statusChart['total'] ? round($statusChart['data'][$i] / $statusChart['total'] * 100) : 0 }}%</td></tr>@empty<tr><td colspan="3">No deliveries in this period.</td></tr>@endforelse</tbody>
                            @elseif($dialog['id'] === 'trends')
                                <thead><tr><th>Date</th><th>Created</th><th>Delivered</th><th>Failed</th></tr></thead>
                                <tbody>@foreach($trendsChart['labels'] as $i => $label)<tr><td>{{ $label }}</td><td>{{ number_format($trendsChart['created'][$i]) }}</td><td>{{ number_format($trendsChart['delivered'][$i]) }}</td><td>{{ number_format($trendsChart['failed'][$i]) }}</td></tr>@endforeach</tbody>
                            @else
                                <thead><tr><th>Center</th><th>Total</th><th>Delivered</th><th>Pending</th><th>Failed</th></tr></thead>
                                <tbody>@forelse($centerPerformanceChart['labels'] as $i => $label)<tr><td>{{ $label }}</td><td>{{ number_format($centerPerformanceChart['total'][$i]) }}</td><td>{{ number_format($centerPerformanceChart['delivered'][$i]) }}</td><td>{{ number_format($centerPerformanceChart['pending'][$i]) }}</td><td>{{ number_format($centerPerformanceChart['failed'][$i]) }}</td></tr>@empty<tr><td colspan="5">No center records yet.</td></tr>@endforelse</tbody>
                            @endif
                        </table>
                    </div>
                </div>
            </template>
        @endforeach
    </div>

    {{-- Recent Deliveries Table (No Horizontal Scroll + View Action) --}}
    <div class="card overflow-hidden" x-data="detailedRecords()">
        <div class="p-5 border-b border-[#E8E6E0] flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-bold text-[#1B1B1E]">Recent Deliveries</h2>
                <p class="text-xs text-[#6E6E73]" x-show="!expanded">The 6 most recently updated deliveries{{ $dateFilterActive ? ' in your date range' : '' }}.</p>
                <p class="text-xs text-[#6E6E73]" x-show="expanded" x-cloak>Search the latest {{ count($detailedRecords) }} deliveries{{ $dateFilterActive ? ' in your date range' : '' }}.</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" class="btn btn-soft btn-sm" @click="expanded = !expanded" x-text="expanded ? 'Show Latest 6' : 'Search Recent'"></button>
                <a class="btn btn-outline btn-sm" href="{{ route('deliveries.index', $dateQuery) }}">All Deliveries &rarr;</a>
            </div>
        </div>

        <div x-show="!expanded">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tracking #</th>
                        <th>Recipient</th>
                        <th>Rider</th>
                        <th>Status</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recent_deliveries as $delivery)
                        <tr>
                            <td>
                                <a class="font-bold text-[#16697A] hover:underline" href="{{ route('deliveries.show', $delivery) }}">{{ $delivery->tracking_number }}</a>
                                <div class="text-[11px] text-[#6E6E73]">{{ $delivery->updated_at?->diffForHumans() }}</div>
                            </td>
                            <td class="font-medium text-[#1B1B1E]">{{ $delivery->recipient_name ?: 'Not provided' }}</td>
                            <td class="text-[#6E6E73]">{{ $delivery->rider?->name ?? 'Unassigned' }}</td>
                            <td><x-status-badge :status="$delivery->status" /></td>
                            <td class="text-right">
                                <a href="{{ route('deliveries.show', $delivery) }}" class="btn btn-soft btn-xs">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-8 text-xs text-[#6E6E73]">No deliveries to show{{ $dateFilterActive ? ' in this date range' : ' yet' }}.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div x-show="expanded" x-cloak>
            <div class="p-4 bg-[#FAF9F6] border-b border-[#E8E6E0] grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div>
                    <label class="field-label">Search</label>
                    <input type="search" class="input" x-model="search" @input.debounce.200ms="page=1; applyFilters()" placeholder="Tracking, recipient, center...">
                </div>
                <div>
                    <label class="field-label">Status</label>
                    <select class="select" x-model="statusFilter" @change="page=1; applyFilters()">
                        <option value="">All statuses</option>
                        <template x-for="opt in statusOptions" :key="opt.value"><option :value="opt.value" x-text="opt.label"></option></template>
                    </select>
                </div>
                <div>
                    <label class="field-label">Center</label>
                    <select class="select" x-model="centerFilter" @change="page=1; applyFilters()">
                        <option value="">All centers</option>
                        <template x-for="center in centerOptions" :key="center.value"><option :value="center.value" x-text="center.label"></option></template>
                    </select>
                </div>
                <div>
                    <label class="field-label">Sort By</label>
                    <select class="select" x-model="sortBy" @change="page=1; applyFilters()">
                        <option value="updated_desc">Updated: Newest</option>
                        <option value="updated_asc">Updated: Oldest</option>
                        <option value="tracking_asc">Tracking: A-Z</option>
                        <option value="status_asc">Status: A-Z</option>
                    </select>
                </div>
            </div>

            <table class="table" x-show="paginated.length > 0">
                <thead>
                    <tr>
                        <th>Tracking #</th>
                        <th>Recipient</th>
                        <th>Center</th>
                        <th>Status</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="row in paginated" :key="row.id">
                        <tr>
                            <td>
                                <a class="font-bold text-[#16697A] hover:underline" :href="row.showUrl" x-text="row.tracking"></a>
                                <div class="text-[11px] text-[#6E6E73]" x-text="row.updated"></div>
                            </td>
                            <td class="font-medium text-[#1B1B1E]" x-text="row.recipient"></td>
                            <td class="text-[#6E6E73]" x-text="row.center"></td>
                            <td><span class="ld-status" :class="statusClass(row.statusRaw)" x-text="row.status"></span></td>
                            <td class="text-right">
                                <a class="btn btn-soft btn-xs" :href="row.showUrl">View</a>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>

            <div class="py-8 text-center text-xs text-[#6E6E73]" x-show="paginated.length === 0">
                <strong class="block text-sm text-[#1B1B1E] mb-1" x-text="all.length ? 'No records match your filters' : 'No delivery records yet'"></strong>
                <button type="button" class="btn btn-outline btn-xs mt-2" @click="clearFilters()" x-show="all.length > 0">Clear Filters</button>
            </div>

            <div class="p-3.5 border-t border-[#E8E6E0] flex items-center justify-between text-xs text-[#6E6E73]">
                <span x-text="filtered.length + ' of ' + all.length + ' records'"></span>
                <div class="flex items-center gap-2">
                    <button type="button" class="btn btn-outline btn-xs" @click="prev()" :disabled="page===1">Previous</button>
                    <span class="font-semibold text-[#1B1B1E]" x-text="'Page ' + page + ' of ' + totalPages"></span>
                    <button type="button" class="btn btn-outline btn-xs" @click="next()" :disabled="page===totalPages">Next</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Bottom Row: Rider Availability & Workload + Recent Activity + Service Areas --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="card overflow-hidden">
            <div class="p-5 border-b border-[#E8E6E0] flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-[#1B1B1E]">Rider Availability &amp; Workload</h2>
                    <p class="text-xs text-[#6E6E73]">
                        <strong class="text-emerald-700">{{ number_format($riders['online']) }} online</strong> &middot;
                        {{ number_format($riders['offline']) }} offline &middot;
                        {{ number_format($riders['total']) }} total
                    </p>
                </div>
                <a class="btn btn-outline btn-xs" href="{{ route('riders.index') }}">All Riders &rarr;</a>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Rider</th>
                        <th>Service Area</th>
                        <th>Active</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($riderWorkload as $rider)
                        <tr>
                            <td>
                                <a class="font-semibold text-[#1B1B1E] hover:text-[#16697A]" href="{{ route('riders.show', $rider) }}">{{ $rider->name }}</a>
                                <div class="text-[11px] {{ $rider->is_online ? 'text-emerald-600 font-semibold' : 'text-[#6E6E73]' }}">
                                    {{ $rider->is_online ? 'Online' : 'Offline' }}
                                </div>
                            </td>
                            <td class="text-[#6E6E73]">{{ $rider->serviceArea?->name ?? 'Unassigned' }}</td>
                            <td class="font-bold text-[#1B1B1E]">{{ number_format($rider->active_deliveries) }}</td>
                            <td class="text-right">
                                <a href="{{ route('riders.show', $rider) }}" class="btn btn-soft btn-xs">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center py-6 text-xs text-[#6E6E73]">No riders assigned yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="space-y-4">
            <div class="card p-5">
                <h2 class="text-sm font-bold text-[#1B1B1E] mb-3">Recent Activity</h2>
                <div class="space-y-2.5">
                    @forelse($activities as $activity)
                        <a href="{{ route('deliveries.index', ['search' => $activity['tracking']]) }}" class="block p-3 rounded-xl bg-[#FAF9F6] border border-[#E8E6E0] hover:border-[#16697A] transition">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-bold text-[#16697A]">{{ $activity['tracking'] ?: 'Delivery Update' }}</span>
                                <span class="text-[11px] text-[#6E6E73]">{{ $activity['created_at']?->diffForHumans() }}</span>
                            </div>
                            <div class="text-xs text-[#1B1B1E] mt-0.5">{{ $activity['message'] }}</div>
                        </a>
                    @empty
                        <div class="py-6 text-center text-xs text-[#6E6E73]">No recent activity yet.</div>
                    @endforelse
                </div>
            </div>

            <div class="card overflow-hidden">
                <div class="p-4 border-b border-[#E8E6E0] flex items-center justify-between">
                    <h2 class="text-sm font-bold text-[#1B1B1E]">Service Areas Coverage</h2>
                    <a class="btn btn-ghost btn-xs" href="{{ route('service-areas.index') }}">Manage &rarr;</a>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Service Area</th>
                            <th>Total</th>
                            <th>Active</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($serviceAreas as $area)
                            <tr>
                                <td class="font-medium text-[#1B1B1E]">{{ $area['name'] }}</td>
                                <td>{{ number_format($area['total']) }}</td>
                                <td class="font-semibold text-[#16697A]">{{ number_format($area['active']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center py-6 text-xs text-[#6E6E73]">No service area data yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
