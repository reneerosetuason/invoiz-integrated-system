@php
    $dateQuery = array_filter(['date_from' => $dateFrom ?: null, 'date_to' => $dateTo ?: null]);
    $statusMap = array_flip($status_labels);
    $hasTrendData = array_sum($trendsChart['created']) + array_sum($trendsChart['delivered']) + array_sum($trendsChart['failed']) > 0;
    $statusTone = fn ($status) => match (true) {
        $status === 'delivered' => 'ld-status--delivered',
        in_array($status, ['delivery_failed', 'lost', 'damaged']) => 'ld-status--failed',
        $status === 'waiting_for_rider' => 'ld-status--pending',
        in_array($status, ['cancelled', 'returned_to_seller']) => 'ld-status--neutral',
        default => '',
    };
@endphp
<div class="logistics-dashboard">
    <header class="ld-head">
        <div>
            <h1>Delivery overview</h1>
            <p>{{ now()->format('l, F j, Y') }} &middot; {{ $staffCenterId !== null ? ($staffCenterId ? 'Your logistics center' : 'Awaiting center assignment') : 'All logistics centers' }}</p>
        </div>
        <div class="ld-actions">
            <a class="ld-button ld-button--soft" href="{{ route('tracking.index') }}">Track a parcel</a>
            <a class="ld-button" href="{{ route('deliveries.index') }}">View deliveries <span aria-hidden="true">&rarr;</span></a>
        </div>
    </header>

    <div class="ld-lead-grid">
        <section class="ld-today" aria-labelledby="ld-today-title">
            <div>
                <h2 id="ld-today-title">Today at a glance</h2>
                <p class="ld-today-date">{{ now()->format('F j') }} &middot; Live delivery activity</p>
            </div>
            <div class="ld-today-main"><strong>{{ number_format($todayCounts['created']) }}</strong><span>new {{ $todayCounts['created'] === 1 ? 'delivery' : 'deliveries' }} today</span></div>
            <dl>
                <div><dt>Delivered today</dt><dd>{{ number_format($todayCounts['delivered']) }}</dd></div>
                <div><dt>Failed today</dt><dd>{{ number_format($todayCounts['failed']) }}</dd></div>
            </dl>
        </section>
        <section class="ld-panel ld-queue" aria-labelledby="ld-queue-title">
            <div class="ld-section-head"><div><h2 id="ld-queue-title">Needs attention</h2><p>Open queues to keep parcels moving.</p></div></div>
            <div class="ld-queue-list">
                <a class="ld-queue-row" href="{{ route('deliveries.index', ['status' => 'waiting_for_rider']) }}"><span>Waiting for a rider</span><strong>{{ number_format($needsAttention['waitingForRider']) }}</strong><span class="ld-arrow" aria-hidden="true">&rarr;</span></a>
                <a class="ld-queue-row" href="{{ route('sorting-center.index') }}"><span>Awaiting sorting or dispatch</span><strong>{{ number_format($needsAttention['processing']) }}</strong><span class="ld-arrow" aria-hidden="true">&rarr;</span></a>
                <a class="ld-queue-row" href="{{ route('pickup-requests.index', ['status' => 'pending']) }}"><span>Pickup reviews</span><strong>{{ number_format($needsAttention['pickups']) }}</strong><span class="ld-arrow" aria-hidden="true">&rarr;</span></a>
                <a class="ld-queue-row" href="{{ route('deliveries.index', ['status' => 'exceptions']) }}"><span>Failed, lost or damaged</span><strong>{{ number_format($needsAttention['failed']) }}</strong><span class="ld-arrow" aria-hidden="true">&rarr;</span></a>
            </div>
        </section>
    </div>

    <section class="ld-panel ld-filter-panel" aria-labelledby="ld-shipments-title">
        <div class="ld-section-head">
            <div><h2 id="ld-shipments-title">Shipments by status</h2><p>{{ $dateFilterActive ? 'Deliveries registered in your selected date range.' : 'All registered deliveries, across all dates.' }}</p></div>
            <form method="GET" action="{{ route('dashboard') }}" class="ld-date-form">
                <input type="hidden" name="trends_range" value="{{ $trendsRange }}">
                <label class="ld-field" for="date_from">From<input id="date_from" type="date" name="date_from" value="{{ $dateFrom }}" max="{{ $dateTo }}"></label>
                <label class="ld-field" for="date_to">To<input id="date_to" type="date" name="date_to" value="{{ $dateTo }}" min="{{ $dateFrom }}"></label>
                <button type="submit" class="ld-button">Apply dates</button>
                @if($dateFilterActive)<a class="ld-text-button" href="{{ route('dashboard', ['trends_range' => $trendsRange]) }}">Clear dates</a>@endif
            </form>
        </div>
        <div class="ld-kpis">
            @foreach([
                ['key' => 'pending', 'label' => 'Pending', 'detail' => 'Waiting for a rider'],
                ['key' => 'in_transit', 'label' => 'In transit', 'detail' => 'Assigned or with a rider'],
                ['key' => 'delivered', 'label' => 'Delivered', 'detail' => 'Handed to the recipient'],
                ['key' => 'failed', 'label' => 'Failed', 'detail' => 'Failed, lost or damaged'],
            ] as $metric)
                <a class="ld-kpi" href="{{ route('deliveries.index', array_merge(['status' => $metric['key']], $dateQuery)) }}">
                    <span>{{ $metric['label'] }}</span><strong>{{ number_format($statusGroupCounts[$metric['key']] ?? 0) }}</strong><small>{{ $metric['detail'] }}</small>
                </a>
            @endforeach
        </div>
        <p class="ld-note">Select a total to view matching deliveries. Date filters apply to these totals and the records below; today&rsquo;s activity stays separate.</p>
    </section>

    @if($alertTotal > 0)
        <section class="ld-alerts" aria-labelledby="ld-alert-title">
            <div class="ld-section-head"><div><h2 id="ld-alert-title">Operational alerts</h2><p>Overdue work that needs a closer look. A parcel may appear in more than one alert.</p></div></div>
            @foreach($operationalAlerts as $alert)
                <a class="ld-alert-row" href="{{ $alert['url'] }}">
                    <div><strong>{{ $alert['title'] }}</strong><p>{{ $alert['action'] }} @if($alert['oldest']) Oldest: {{ $alert['oldest']->diffForHumans() }}.@endif</p></div>
                    <span class="ld-alert-count {{ $alert['severity'] === 'danger' ? 'ld-alert-count--danger' : '' }}" aria-label="{{ $alert['count'] }} affected parcels or requests">{{ number_format($alert['count']) }} <span aria-hidden="true">&rarr;</span></span>
                </a>
            @endforeach
        </section>
    @endif

    <div x-data="dashboardCharts()" x-init="initCharts()">
        <div id="statusKeyMap" hidden data-map='@json($statusMap)'></div>
        <div class="ld-analysis">
            <section class="ld-panel" aria-labelledby="ld-trends-title">
                <div class="ld-chart-heading"><div><h2 id="ld-trends-title">Delivery trends</h2><p>Daily activity over the last {{ $trendsRange }} days.</p></div><button type="button" class="ld-text-button" @click="openTrendsModal()" aria-label="Expand delivery trends chart">Expand <span aria-hidden="true">&#8599;</span></button></div>
                <div class="ld-chart-tools">
                    <nav class="ld-periods" aria-label="Delivery trend period">
                        <a href="{{ route('dashboard', array_merge($dateQuery, ['trends_range' => 7])) }}" aria-current="{{ $trendsRange === 7 ? 'true' : 'false' }}">7 days</a>
                        <a href="{{ route('dashboard', array_merge($dateQuery, ['trends_range' => 30])) }}" aria-current="{{ $trendsRange === 30 ? 'true' : 'false' }}">30 days</a>
                    </nav>
                    <div class="ld-legend" aria-label="Chart legend"><span><i class="ld-swatch" aria-hidden="true"></i>Created</span><span><i class="ld-swatch ld-swatch--delivered" aria-hidden="true"></i>Delivered</span><span><i class="ld-swatch ld-swatch--failed" aria-hidden="true"></i>Failed</span></div>
                </div>
                @if($hasTrendData)
                    <div class="ld-chart"><canvas id="trendsChart" role="img" aria-label="Daily created, delivered and failed shipments for the last {{ $trendsRange }} days. Expand for a table of exact values."></canvas></div>
                    <div x-show="selectedTrendDate" x-cloak class="ld-chart-result"><span><strong x-text="selectedTrendDate"></strong><br><span x-text="selectedTrendDetail"></span></span><a :href="trendLink">View deliveries &rarr;</a></div>
                @else
                    <div class="ld-empty"><strong>No activity in this period</strong><p>New delivery activity will appear here. Try the 30-day view for a wider period.</p></div>
                @endif
            </section>
            <section class="ld-panel" aria-labelledby="ld-status-title">
                <div class="ld-chart-heading"><div><h2 id="ld-status-title">Delivery status</h2><p>{{ number_format($statusChart['total']) }} deliveries{{ $dateFilterActive ? ' in this date range' : ' across all dates' }}.</p></div><button type="button" class="ld-text-button" @click="openStatusModal()" aria-label="Expand delivery status chart">Expand <span aria-hidden="true">&#8599;</span></button></div>
                @if($statusChart['total'] > 0)
                    <div class="ld-chart ld-chart--status"><canvas id="statusChart" role="img" aria-label="Delivery status distribution. Exact counts and percentages follow."></canvas></div>
                    <div class="ld-status-list">
                        @foreach($statusChart['labels'] as $i => $label)
                            @php $statusKey = $statusMap[$label] ?? ''; @endphp
                            <div class="ld-status-row"><a href="{{ route('deliveries.index', array_merge($dateQuery, ['status' => $statusKey])) }}"><i class="ld-swatch" style="background:{{ $statusChart['colors'][$i] }}" aria-hidden="true"></i>{{ $label }}</a><strong>{{ number_format($statusChart['data'][$i]) }}</strong><small>{{ round($statusChart['data'][$i] / $statusChart['total'] * 100) }}%</small></div>
                        @endforeach
                    </div>
                    <div x-show="selectedStatus" x-cloak class="ld-chart-result"><span><strong x-text="selectedStatus"></strong>: <span x-text="selectedStatusCount"></span></span><a :href="statusLink">View deliveries &rarr;</a></div>
                @else
                    <div class="ld-empty"><strong>No deliveries to show</strong><p>{{ $dateFilterActive ? 'Try a wider date range.' : 'The breakdown will appear as deliveries are registered.' }}</p></div>
                @endif
            </section>
        </div>

        <div class="ld-support-grid">
            <section class="ld-panel" aria-labelledby="ld-center-title">
                <div class="ld-chart-heading"><div><h2 id="ld-center-title">Center performance</h2><p>{{ $staffCenterId !== null ? 'Delivery totals for your assigned center.' : 'All-time delivery totals across logistics centers.' }}</p></div><button type="button" class="ld-text-button" @click="openCenterModal()" aria-label="Expand center performance chart">Expand <span aria-hidden="true">&#8599;</span></button></div>
                @if(!$centerPerformanceChart['empty'])
                    <div class="ld-chart" style="height:{{ max(280, count($centerPerformanceChart['labels']) * 60) }}px"><canvas id="centerChart" role="img" aria-label="Total, delivered, pending and failed deliveries by logistics center. Expand for exact values."></canvas></div>
                    <div x-show="selectedCenter" x-cloak class="ld-chart-result"><span><strong x-text="selectedCenter"></strong><br><span x-text="selectedCenterDetail"></span></span><a :href="centerLink">View deliveries &rarr;</a></div>
                @else
                    <div class="ld-empty"><strong>No center activity yet</strong><p>Center totals will appear when deliveries are assigned.</p></div>
                @endif
            </section>
            <section class="ld-panel" aria-labelledby="ld-financial-title">
                <div class="ld-section-head"><div><h2 id="ld-financial-title">Today&rsquo;s finances</h2><p>Recorded transactions for {{ now()->format('F j') }}.</p></div></div>
                <dl class="ld-money">
                    <div><dt>Transaction amount</dt><dd>&#8369;{{ number_format($financialToday['amount'], 2) }}</dd></div>
                    <div><dt>Rider fees</dt><dd>&#8369;{{ number_format($financialToday['rider_fee'], 2) }}</dd></div>
                    <div><dt>Commission</dt><dd>&#8369;{{ number_format($financialToday['commission'], 2) }}</dd></div>
                    <div><dt>Transactions</dt><dd>{{ number_format($financialToday['count']) }}</dd></div>
                </dl>
                <p class="ld-note ld-money-caption">These figures cover today only and do not change with the shipment date filter.</p>
                <a class="ld-text-button" href="{{ route('transactions.index') }}">View transactions &rarr;</a>
            </section>
        </div>

        @foreach([
            ['key' => 'Status', 'id' => 'status', 'title' => 'Delivery status', 'canvas' => 'statusChartModal'],
            ['key' => 'Trends', 'id' => 'trends', 'title' => 'Delivery trends', 'canvas' => 'trendsChartModal'],
            ['key' => 'Center', 'id' => 'center', 'title' => 'Center performance', 'canvas' => 'centerChartModal'],
        ] as $dialog)
            <template x-teleport="body">
                <div class="ld-dialog" data-dialog="{{ $dialog['id'] }}" x-show="show{{ $dialog['key'] }}Modal" x-cloak role="dialog" aria-modal="true" aria-labelledby="ld-{{ $dialog['id'] }}-dialog-title" @keydown.escape.window="if(show{{ $dialog['key'] }}Modal) close{{ $dialog['key'] }}Modal()" @keydown.tab="trapDialog($event)">
                    <div class="ld-dialog-scrim" @click="close{{ $dialog['key'] }}Modal()"></div>
                    <div class="ld-dialog-panel">
                        <div class="ld-section-head"><div><h2 id="ld-{{ $dialog['id'] }}-dialog-title">{{ $dialog['title'] }}</h2><p>{{ $dialog['id'] === 'trends' ? 'The last '.$trendsRange.' days. Exact values are listed below.' : 'Exact values for the chart are listed below.' }}</p></div><button type="button" class="ld-button ld-button--soft" @click="close{{ $dialog['key'] }}Modal()" data-dialog-close>Close</button></div>
                        @if(($dialog['id'] === 'status' && $statusChart['total'] > 0) || ($dialog['id'] === 'trends' && $hasTrendData) || ($dialog['id'] === 'center' && !$centerPerformanceChart['empty']))
                            <div class="ld-chart"><canvas id="{{ $dialog['canvas'] }}" role="img" aria-label="{{ $dialog['title'] }} expanded chart"></canvas></div>
                        @endif
                        <div class="ld-table-wrap" tabindex="0" aria-label="{{ $dialog['title'] }} data">
                            <table class="ld-table">
                                @if($dialog['id'] === 'status')
                                    <thead><tr><th scope="col">Status</th><th scope="col">Deliveries</th><th scope="col">Share</th></tr></thead>
                                    <tbody>@forelse($statusChart['labels'] as $i => $label)<tr><td><a href="{{ route('deliveries.index', array_merge($dateQuery, ['status' => $statusMap[$label] ?? ''])) }}">{{ $label }}</a></td><td>{{ number_format($statusChart['data'][$i]) }}</td><td>{{ $statusChart['total'] ? round($statusChart['data'][$i] / $statusChart['total'] * 100) : 0 }}%</td></tr>@empty<tr><td colspan="3">No deliveries in this period.</td></tr>@endforelse</tbody>
                                @elseif($dialog['id'] === 'trends')
                                    <thead><tr><th scope="col">Date</th><th scope="col">Created</th><th scope="col">Delivered</th><th scope="col">Failed</th></tr></thead>
                                    <tbody>@foreach($trendsChart['labels'] as $i => $label)<tr><td>{{ $label }}</td><td>{{ number_format($trendsChart['created'][$i]) }}</td><td>{{ number_format($trendsChart['delivered'][$i]) }}</td><td>{{ number_format($trendsChart['failed'][$i]) }}</td></tr>@endforeach</tbody>
                                @else
                                    <thead><tr><th scope="col">Center</th><th scope="col">Total</th><th scope="col">Delivered</th><th scope="col">Pending</th><th scope="col">Failed</th></tr></thead>
                                    <tbody>@forelse($centerPerformanceChart['labels'] as $i => $label)<tr><td>{{ $label }}</td><td>{{ number_format($centerPerformanceChart['total'][$i]) }}</td><td>{{ number_format($centerPerformanceChart['delivered'][$i]) }}</td><td>{{ number_format($centerPerformanceChart['pending'][$i]) }}</td><td>{{ number_format($centerPerformanceChart['failed'][$i]) }}</td></tr>@empty<tr><td colspan="5">No center records yet.</td></tr>@endforelse</tbody>
                                @endif
                            </table>
                        </div>
                    </div>
                </div>
            </template>
        @endforeach
    </div>

    <section class="ld-panel ld-records" aria-labelledby="ld-records-title" x-data="detailedRecords()">
        <div class="ld-section-head">
            <div><h2 id="ld-records-title">Recent deliveries</h2><p x-show="!expanded">The six most recently updated deliveries{{ $dateFilterActive ? ' in your date range' : '' }}.</p><p x-show="expanded" x-cloak>Search the latest {{ count($detailedRecords) }} deliveries{{ $dateFilterActive ? ' in your date range' : '' }}. Open all deliveries to search older records.</p></div>
            <div class="ld-actions"><button type="button" class="ld-button ld-button--soft" @click="expanded = !expanded" :aria-expanded="expanded.toString()" aria-controls="ld-record-browser" x-text="expanded ? 'Show latest six' : 'Search recent records'">Search recent records</button><a class="ld-text-button" href="{{ route('deliveries.index', $dateQuery) }}">All deliveries &rarr;</a></div>
        </div>
        <div x-show="!expanded">
            <div class="ld-table-wrap ld-desktop-table">
                <table class="ld-table"><thead><tr><th scope="col" style="width:23%">Tracking number</th><th scope="col">Recipient</th><th scope="col">Rider</th><th scope="col" style="width:19%">Status</th><th scope="col" style="width:16%">Updated</th></tr></thead>
                    <tbody>@forelse($recent_deliveries as $delivery)<tr><td><a href="{{ route('deliveries.show', $delivery) }}">{{ $delivery->tracking_number }}</a></td><td>{{ $delivery->recipient_name ?: 'Not provided' }}</td><td>{{ $delivery->rider?->name ?? 'Unassigned' }}</td><td><span class="ld-status {{ $statusTone($delivery->status) }}">{{ $status_labels[$delivery->status] ?? ucwords(str_replace('_', ' ', $delivery->status)) }}</span></td><td class="ld-muted">{{ $delivery->updated_at?->diffForHumans() }}</td></tr>@empty<tr><td colspan="5">No deliveries to show{{ $dateFilterActive ? ' in this date range' : ' yet' }}.</td></tr>@endforelse</tbody>
                </table>
            </div>
            <div class="ld-record-cards">
                @forelse($recent_deliveries as $delivery)
                    <a class="ld-record-card" href="{{ route('deliveries.show', $delivery) }}">
                        <div class="ld-record-card-head"><strong class="ld-record-id">{{ $delivery->tracking_number }}</strong><span class="ld-status {{ $statusTone($delivery->status) }}">{{ $status_labels[$delivery->status] ?? ucwords(str_replace('_', ' ', $delivery->status)) }}</span></div>
                        <dl><div><dt>Recipient</dt><dd>{{ $delivery->recipient_name ?: 'Not provided' }}</dd></div><div><dt>Rider</dt><dd>{{ $delivery->rider?->name ?? 'Unassigned' }}</dd></div></dl>
                        <p class="ld-note">Updated {{ $delivery->updated_at?->diffForHumans() }}</p>
                    </a>
                @empty<div class="ld-empty"><strong>No deliveries to show</strong><p>{{ $dateFilterActive ? 'Try a wider date range.' : 'New deliveries will appear here as they are registered.' }}</p></div>@endforelse
            </div>
        </div>
        <div id="ld-record-browser" x-show="expanded" x-cloak>
            <div class="ld-records-filters">
                <label class="ld-field">Search<input type="search" x-model="search" @input.debounce.200ms="page=1; applyFilters()" placeholder="Tracking, recipient or center" aria-label="Search recent deliveries"></label>
                <label class="ld-field">Status<select x-model="statusFilter" @change="page=1; applyFilters()"><option value="">All statuses</option><template x-for="opt in statusOptions" :key="opt.value"><option :value="opt.value" x-text="opt.label"></option></template></select></label>
                <label class="ld-field">Center<select x-model="centerFilter" @change="page=1; applyFilters()"><option value="">All centers</option><template x-for="center in centerOptions" :key="center.value"><option :value="center.value" x-text="center.label"></option></template></select></label>
                <label class="ld-field">Sort by<select x-model="sortBy" @change="page=1; applyFilters()"><option value="updated_desc">Updated: newest first</option><option value="updated_asc">Updated: oldest first</option><option value="tracking_asc">Tracking: A to Z</option><option value="status_asc">Status: A to Z</option></select></label>
            </div>
            <div class="ld-table-wrap ld-desktop-table" x-show="paginated.length > 0">
                <table class="ld-table"><thead><tr><th scope="col" style="width:24%">Tracking number</th><th scope="col">Recipient</th><th scope="col">Center</th><th scope="col" style="width:19%">Status</th><th scope="col" style="width:15%">Updated</th></tr></thead>
                    <tbody><template x-for="row in paginated" :key="row.id"><tr><td><a :href="row.showUrl" x-text="row.tracking"></a></td><td x-text="row.recipient"></td><td x-text="row.center"></td><td><span class="ld-status" :class="statusClass(row.statusRaw)" x-text="row.status"></span></td><td class="ld-muted" x-text="row.updated"></td></tr></template></tbody>
                </table>
            </div>
            <div class="ld-record-cards" x-show="paginated.length > 0"><template x-for="row in paginated" :key="row.id"><a class="ld-record-card" :href="row.showUrl"><div class="ld-record-card-head"><strong class="ld-record-id" x-text="row.tracking"></strong><span class="ld-status" :class="statusClass(row.statusRaw)" x-text="row.status"></span></div><dl><div><dt>Recipient</dt><dd x-text="row.recipient"></dd></div><div><dt>Center</dt><dd x-text="row.center"></dd></div></dl><p class="ld-note" x-text="'Updated ' + row.updated"></p></a></template></div>
            <div class="ld-empty" x-show="paginated.length === 0"><strong x-text="all.length ? 'No records match your filters' : 'No delivery records yet'"></strong><p>Try a different search or date range.</p><button type="button" class="ld-text-button" @click="clearFilters()" x-show="all.length > 0">Clear filters</button></div>
            <div class="ld-pagination" aria-live="polite"><p x-text="filtered.length + ' of ' + all.length + ' records'"></p><div class="ld-actions"><button type="button" @click="prev()" :disabled="page===1">Previous</button><span x-text="'Page ' + page + ' of ' + totalPages"></span><button type="button" @click="next()" :disabled="page===totalPages">Next</button></div></div>
        </div>
    </section>

    <div class="ld-support-grid">
        <section class="ld-panel" aria-labelledby="ld-riders-title">
            <div class="ld-section-head"><div><h2 id="ld-riders-title">Rider availability</h2><p>Online riders available for assignments.</p></div><a class="ld-text-button" href="{{ route('riders.index') }}">All riders &rarr;</a></div>
            <div class="ld-rider-summary"><span><strong>{{ number_format($riders['online']) }}</strong> online</span><span><strong>{{ number_format($riders['offline']) }}</strong> offline</span><span>{{ number_format($riders['total']) }} total riders</span></div>
            <div class="ld-rider-list">@forelse($onlineRiders as $rider)<a class="ld-rider" href="{{ route('riders.show', $rider) }}"><span class="ld-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($rider->name, 0, 1)) }}</span><div><span class="ld-rider-name">{{ $rider->name }}</span><p>{{ $rider->serviceArea?->name ?? 'No service area assigned' }}</p></div><span class="ld-rider-count"><span class="ld-online">Online</span><br>{{ $rider->active_deliveries }} active</span></a>@empty<div class="ld-empty"><strong>No riders online</strong><p>Available riders appear here when they come online.</p></div>@endforelse</div>
                <div class="ld-rider-workload" aria-labelledby="ld-workload-title"><h3 id="ld-workload-title">Rider workload</h3><div class="ld-table-wrap"><table class="ld-table"><thead><tr><th scope="col" style="width:42%">Rider</th><th scope="col" style="width:38%">Area</th><th scope="col">Active</th></tr></thead><tbody>@forelse($riderWorkload as $rider)<tr><td><a href="{{ route('riders.show', $rider) }}">{{ $rider->name }}</a><p class="ld-note">{{ $rider->is_online ? 'Online' : 'Offline' }}</p></td><td>{{ $rider->serviceArea?->name ?? 'Unassigned' }}</td><td>{{ number_format($rider->active_deliveries) }}</td></tr>@empty<tr><td colspan="3">No riders assigned yet.</td></tr>@endforelse</tbody></table></div></div>
</section>
        <section class="ld-panel" aria-labelledby="ld-activity-title"><h2 id="ld-activity-title">Recent activity</h2><div class="ld-activity">@forelse($activities as $activity)<a href="{{ route('deliveries.index', ['search' => $activity['tracking']]) }}"><span class="ld-record-id">{{ $activity['tracking'] ?: 'Delivery update' }}</span><div>{{ $activity['message'] }}</div><p>{{ $activity['created_at']?->diffForHumans() }}{{ $activity['by'] ? ' · '.$activity['by'] : '' }}{{ $activity['recipient'] ? ' · '.$activity['recipient'] : '' }}</p></a>@empty<div class="ld-empty"><strong>No updates yet</strong><p>Delivery milestones will appear here as they happen.</p></div>@endforelse</div></section>
    </div>
    <div>
        <section class="ld-panel ld-areas-panel" aria-labelledby="ld-areas-title"><div class="ld-section-head"><div><h2 id="ld-areas-title">Service areas</h2><p>Delivery coverage and active workload.</p></div><a class="ld-text-button" href="{{ route('deliveries.index') }}">All deliveries &rarr;</a></div><div class="ld-table-wrap"><table class="ld-table"><thead><tr><th scope="col" style="width:50%">Service area</th><th scope="col">Total</th><th scope="col">Active</th></tr></thead><tbody>@forelse($serviceAreas as $area)<tr><td>{{ $area['name'] }}</td><td>{{ number_format($area['total']) }}</td><td>{{ number_format($area['active']) }}</td></tr>@empty<tr><td colspan="3">No service area data yet.</td></tr>@endforelse</tbody></table></div></section>

    </div>
</div>
