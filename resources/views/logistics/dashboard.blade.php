<x-app-layout>
    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Dashboard Overview</h1>
                <p class="mt-1 text-sm text-gray-500">{{ now()->format('l, F j, Y') }} · Financial figures shown are for <span class="font-semibold text-gray-700">Today</span></p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('tracking.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-gray-700 bg-white border border-gray-200 shadow-sm hover:bg-gray-50 hover:border-gray-300 transition">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Track Package
                </a>
                <a href="{{ route('deliveries.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-teal hover:bg-teal-dark shadow-sm shadow-teal/20 hover:shadow-teal/30 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                    View Deliveries
                </a>
            </div>
        </div>

        {{-- LEVEL 1 — Needs Attention (actionable problems first) --}}
        <section class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden" aria-label="Items needing attention">
            @php $attentionTotal = $needsAttention['waitingForRider'] + $needsAttention['failed'] + $needsAttention['processing']; @endphp
            <div class="px-5 sm:px-6 py-4 border-b border-gray-100 flex items-center gap-2.5">
                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full {{ $attentionTotal > 0 ? 'bg-red-100 text-red-600' : 'bg-emerald-100 text-emerald-600' }}" aria-hidden="true">
                    @if($attentionTotal > 0)
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    @else
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    @endif
                </span>
                <h2 class="text-base font-bold text-gray-900">Needs Attention</h2>
                @if($attentionTotal > 0)
                    <span class="ml-auto inline-flex items-center px-2.5 py-1 rounded-full bg-red-100 text-red-700 text-xs font-bold">{{ $attentionTotal }} open {{ $attentionTotal === 1 ? 'item' : 'items' }}</span>
                @endif
            </div>

            @if($attentionTotal === 0)
                <div class="px-5 sm:px-6 py-10 text-center">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-emerald-50 text-emerald-500 mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <p class="text-sm font-semibold text-gray-700">All current deliveries are progressing normally.</p>
                    <p class="text-sm text-gray-500 mt-1">No items require Logistics attention right now.</p>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-3 divide-y sm:divide-y-0 sm:divide-x divide-gray-100">
                    <a href="{{ route('deliveries.index', ['status' => 'waiting_for_rider']) }}" class="flex items-center justify-between gap-3 px-5 sm:px-6 py-5 border-l-4 border-l-amber-400 hover:bg-amber-50/40 transition group {{ $needsAttention['waitingForRider'] === 0 ? 'opacity-60' : '' }}">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">Waiting for rider</p>
                            <p class="text-xs text-gray-500 mt-0.5">Unassigned deliveries</p>
                        </div>
                        <div class="text-right">
                            <span class="inline-flex items-center justify-center min-w-[2.5rem] px-2 py-1 rounded-lg bg-amber-100 text-amber-700 text-lg font-extrabold">{{ $needsAttention['waitingForRider'] }}</span>
                            <p class="text-xs font-semibold text-teal group-hover:underline mt-1">View</p>
                        </div>
                    </a>
                    <a href="{{ route('deliveries.index', ['status' => 'delivery_failed']) }}" class="flex items-center justify-between gap-3 px-5 sm:px-6 py-5 border-l-4 border-l-red-400 hover:bg-red-50/40 transition group {{ $needsAttention['failed'] === 0 ? 'opacity-60' : '' }}">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">Failed deliveries</p>
                            <p class="text-xs text-gray-500 mt-0.5">Failed out for delivery</p>
                        </div>
                        <div class="text-right">
                            <span class="inline-flex items-center justify-center min-w-[2.5rem] px-2 py-1 rounded-lg bg-red-100 text-red-700 text-lg font-extrabold">{{ $needsAttention['failed'] }}</span>
                            <p class="text-xs font-semibold text-teal group-hover:underline mt-1">View</p>
                        </div>
                    </a>
                    <a href="{{ route('deliveries.index', ['status' => 'waiting_for_rider']) }}" class="flex items-center justify-between gap-3 px-5 sm:px-6 py-5 border-l-4 border-l-teal hover:bg-teal-light/40 transition group {{ $needsAttention['processing'] === 0 ? 'opacity-60' : '' }}">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">Requires processing</p>
                            <p class="text-xs text-gray-500 mt-0.5">Parcels at center not yet dispatched</p>
                        </div>
                        <div class="text-right">
                            <span class="inline-flex items-center justify-center min-w-[2.5rem] px-2 py-1 rounded-lg bg-teal-light text-teal-dark text-lg font-extrabold">{{ $needsAttention['processing'] }}</span>
                            <p class="text-xs font-semibold text-teal group-hover:underline mt-1">View</p>
                        </div>
                    </a>
                </div>
            @endif
        </section>

        {{-- LEVEL 2 — Operations at a glance (primary KPIs) --}}
        <section aria-label="Operations at a glance">
            @php $completionRate = $deliveries['total'] > 0 ? (int) round($deliveries['delivered'] / $deliveries['total'] * 100) : 0; @endphp
            <p class="text-xs font-bold text-gray-500 uppercase tracking-wider">Operations at a glance</p>
            <div class="mt-3 grid grid-cols-2 lg:grid-cols-5 gap-4">
                <a href="{{ route('deliveries.index') }}" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 sm:p-6 hover:shadow-md hover:border-teal/30 transition">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Deliveries</p>
                    <p class="mt-1 text-3xl sm:text-4xl font-extrabold tracking-tight text-gray-900">{{ $deliveries['total'] }}</p>
                    <p class="text-xs text-gray-500 mt-1">All time</p>
                </a>
                <a href="{{ route('deliveries.index', ['status' => 'delivered']) }}" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 sm:p-6 hover:shadow-md hover:border-emerald-300 transition">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Delivered</p>
                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-500 flex-shrink-0" aria-hidden="true"></span>
                    </div>
                    <p class="mt-1 text-3xl sm:text-4xl font-extrabold tracking-tight text-gray-900">{{ $deliveries['delivered'] }}</p>
                    <p class="text-xs text-emerald-600 font-medium mt-1">{{ $completionRate }}% of all deliveries</p>
                </a>
                <a href="{{ route('deliveries.index', ['status' => 'waiting_for_rider']) }}" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 sm:p-6 hover:shadow-md hover:border-amber-300 transition">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Waiting for Rider</p>
                        <span class="h-2.5 w-2.5 rounded-full bg-amber-400 flex-shrink-0" aria-hidden="true"></span>
                    </div>
                    <p class="mt-1 text-3xl sm:text-4xl font-extrabold tracking-tight text-gray-900">{{ $deliveries['waiting_for_rider'] }}</p>
                    <p class="text-xs text-amber-600 font-medium mt-1">Needs dispatch</p>
                </a>
                <a href="{{ route('deliveries.index', ['status' => 'delivery_failed']) }}" class="bg-white rounded-2xl border {{ $deliveries['failed'] > 0 ? 'border-red-200 ring-1 ring-red-100' : 'border-gray-100' }} shadow-sm p-5 sm:p-6 hover:shadow-md hover:border-red-300 transition">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Delivery Failed</p>
                        <span class="h-2.5 w-2.5 rounded-full flex-shrink-0 {{ $deliveries['failed'] > 0 ? 'bg-red-500 motion-safe:animate-pulse' : 'bg-gray-300' }}" aria-hidden="true"></span>
                    </div>
                    <p class="mt-1 text-3xl sm:text-4xl font-extrabold tracking-tight {{ $deliveries['failed'] > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $deliveries['failed'] }}</p>
                    <p class="text-xs font-medium mt-1 {{ $deliveries['failed'] > 0 ? 'text-red-500' : 'text-gray-400' }}">{{ $deliveries['failed'] > 0 ? 'Requires attention' : 'No issues' }}</p>
                </a>
                <a href="{{ route('riders.index', ['online' => 'online']) }}" class="bg-white rounded-2xl border border-emerald-100 shadow-sm p-5 sm:p-6 hover:shadow-md hover:border-emerald-300 transition col-span-2 lg:col-span-1">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Riders Online</p>
                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-500 flex-shrink-0" aria-hidden="true"></span>
                    </div>
                    <p class="mt-1 text-3xl sm:text-4xl font-extrabold tracking-tight text-gray-900">{{ $riders['online'] }}</p>
                    <p class="text-xs text-gray-500 mt-1">of {{ $riders['total'] }} total riders · Available now</p>
                </a>
            </div>
            {{-- Secondary delivery statuses (compact) --}}
            <div class="mt-3 grid grid-cols-2 gap-3">
                <a href="{{ route('deliveries.index', ['status' => 'assigned']) }}" class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3 flex items-center justify-between gap-3 hover:border-blue-300 transition">
                    <span class="flex items-center gap-2 min-w-0">
                        <span class="h-2 w-2 rounded-full bg-blue-500 flex-shrink-0" aria-hidden="true"></span>
                        <span class="text-sm font-semibold text-gray-700 truncate">Assigned <span class="font-normal text-gray-400">· With rider</span></span>
                    </span>
                    <span class="text-xl font-bold text-gray-900 flex-shrink-0">{{ $deliveries['assigned'] }}</span>
                </a>
                <a href="{{ route('deliveries.index', ['status' => 'out_for_delivery']) }}" class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3 flex items-center justify-between gap-3 hover:border-purple-300 transition">
                    <span class="flex items-center gap-2 min-w-0">
                        <span class="h-2 w-2 rounded-full bg-purple-500 flex-shrink-0" aria-hidden="true"></span>
                        <span class="text-sm font-semibold text-gray-700 truncate">Out for Delivery <span class="font-normal text-gray-400">· In transit</span></span>
                    </span>
                    <span class="text-xl font-bold text-gray-900 flex-shrink-0">{{ $deliveries['out_for_delivery'] }}</span>
                </a>
            </div>
        </section>

        {{-- LEVEL 2 (secondary) — Riders + Financial summary --}}
        <section class="grid grid-cols-1 md:grid-cols-2 gap-4" aria-label="Riders and financial summary">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Riders</p>
                <div class="mt-3 grid grid-cols-3 gap-3">
                    <a href="{{ route('riders.index') }}" class="rounded-xl border border-gray-100 p-3 hover:border-teal/30 transition">
                        <p class="text-xs text-gray-500">Total Riders</p>
                        <p class="text-xl font-bold text-gray-900">{{ $riders['total'] }}</p>
                    </a>
                    <a href="{{ route('riders.index', ['online' => 'online']) }}" class="rounded-xl border border-emerald-100 bg-emerald-50/40 p-3 hover:border-emerald-300 transition">
                        <p class="text-xs text-emerald-600">Online</p>
                        <p class="text-xl font-bold text-emerald-700">{{ $riders['online'] }}</p>
                    </a>
                    <a href="{{ route('riders.index', ['online' => 'offline']) }}" class="rounded-xl border border-gray-100 p-3 hover:border-gray-300 transition">
                        <p class="text-xs text-gray-500">Offline</p>
                        <p class="text-xl font-bold text-gray-900">{{ $riders['offline'] }}</p>
                    </a>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Financial — Today</p>
                <div class="mt-3 grid grid-cols-3 gap-3">
                    <a href="{{ route('transactions.index') }}" class="rounded-xl border border-gray-100 p-3 hover:border-teal/30 transition">
                        <p class="text-xs text-gray-500">Today's Transactions</p>
                        <p class="text-xl font-bold text-gray-900">{{ number_format($financialToday['count']) }}</p>
                    </a>
                    <a href="{{ route('transactions.index') }}" class="rounded-xl border border-gray-100 p-3 hover:border-teal/30 transition">
                        <p class="text-xs text-gray-500">Today's Rider Fees</p>
                        <p class="text-xl font-bold text-teal-dark">{{ '₱' . number_format($financialToday['rider_fee'], 2) }}</p>
                    </a>
                    <a href="{{ route('transactions.index') }}" class="rounded-xl border border-gray-100 p-3 hover:border-teal/30 transition">
                        <p class="text-xs text-gray-500">Today's Admin Commission</p>
                        <p class="text-xl font-bold text-teal-dark">{{ '₱' . number_format($financialToday['commission'], 2) }}</p>
                    </a>
                </div>
            </div>
        </section>

        {{-- Charts: compact 3-column row with expandable details --}}
        <section class="grid grid-cols-1 md:grid-cols-3 gap-4" x-data="dashboardCharts()" x-init="initCharts()">
            {{-- Delivery Status (Doughnut) --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-3 sm:p-4 flex flex-col" style="min-height: 260px; max-height: 300px;">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h2 class="text-xs font-bold text-gray-900 leading-tight">Delivery Status</h2>
                        <p class="text-[10px] text-gray-500 mt-0.5 leading-snug">Grouped by status</p>
                    </div>
                    <div class="flex items-center gap-1.5 flex-shrink-0">
                        <span class="text-[10px] font-semibold text-gray-600 bg-gray-50 px-2 py-0.5 rounded-full whitespace-nowrap">{{ $statusChart['total'] }} total</span>
                        <button @click="openStatusModal()" class="inline-flex items-center gap-1 text-[10px] font-semibold text-teal hover:text-teal-dark bg-teal-light/50 hover:bg-teal-light px-2 py-0.5 rounded-full transition" aria-label="Expand Delivery Status chart">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                            Expand
                        </button>
                    </div>
                </div>
                @if($statusChart['total'] === 0)
                    <div class="flex-1 flex flex-col items-center justify-center py-6 text-center">
                        <div class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-gray-100 text-gray-400 mb-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/></svg>
                        </div>
                        <p class="text-[11px] font-semibold text-gray-700">No deliveries yet</p>
                        <p class="text-[10px] text-gray-500 mt-0.5">New deliveries will appear here</p>
                    </div>
                @else
                    <div class="mt-2 flex-1 flex flex-col items-center min-h-0">
                        <div class="relative w-full flex-1 flex items-center justify-center" style="height: 150px; max-height: 170px;">
                            <canvas id="statusChart" aria-label="Delivery status doughnut chart" role="img" class="max-w-full cursor-pointer" @click="handleStatusClick($event)"></canvas>
                        </div>
                        <div class="mt-2 w-full grid grid-cols-2 gap-x-2 gap-y-1 content-start overflow-y-auto pr-0.5" style="max-height: 56px;">
                            @foreach($statusChart['labels'] as $i => $label)
                                @php $count = $statusChart['data'][$i]; $color = $statusChart['colors'][$i]; @endphp
                                <div class="flex items-center justify-between gap-1 text-[10px] leading-tight">
                                    <span class="flex items-center gap-1 min-w-0"><span class="h-1.5 w-1.5 rounded-full flex-shrink-0" style="background: {{ $color }}"></span><span class="text-gray-600 truncate" title="{{ $label }}">{{ \Illuminate\Support\Str::limit($label, 14) }}</span></span>
                                    <span class="font-semibold text-gray-900 whitespace-nowrap text-[10px]">{{ $count }}<span class="text-gray-400 font-medium"> {{ round($count / $statusChart['total'] * 100) }}%</span></span>
                                </div>
                            @endforeach
                        </div>
                        <div x-show="selectedStatus" x-cloak class="mt-2 w-full bg-teal-light/40 border border-teal/20 rounded-lg px-2.5 py-2 flex items-center justify-between gap-2">
                            <span class="text-[11px] text-gray-700"><span class="font-semibold" x-text="selectedStatus"></span> <span x-text="selectedStatusCount ? '(' + selectedStatusCount + ')' : ''"></span></span>
                            <a :href="statusLink" class="text-[10px] font-semibold text-teal hover:text-teal-dark whitespace-nowrap">View records →</a>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Delivery Trends (Line) --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-3 sm:p-4 flex flex-col" style="min-height: 260px; max-height: 300px;">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h2 class="text-xs font-bold text-gray-900 leading-tight">Delivery Trends</h2>
                        <p class="text-[10px] text-gray-500 mt-0.5 leading-snug">Daily created / delivered / failed</p>
                    </div>
                    <button @click="openTrendsModal()" class="flex-shrink-0 inline-flex items-center gap-1 text-[10px] font-semibold text-teal hover:text-teal-dark bg-teal-light/50 hover:bg-teal-light px-2 py-0.5 rounded-full transition" aria-label="Expand Delivery Trends chart">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                        Expand
                    </button>
                </div>
                <div class="mt-2 flex items-center gap-1 flex-wrap">
                    <a href="{{ route('dashboard', ['trends_range' => 7]) }}" class="px-2 py-0.5 rounded-full text-[10px] font-semibold transition {{ $trendsRange === 7 ? 'bg-teal text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">7d</a>
                    <a href="{{ route('dashboard', ['trends_range' => 30]) }}" class="px-2 py-0.5 rounded-full text-[10px] font-semibold transition {{ $trendsRange === 30 ? 'bg-teal text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">30d</a>
                    <span class="ml-auto hidden sm:inline-flex items-center gap-2 text-[10px] text-gray-400">
                        <span class="inline-flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full" style="background: {{ $trendsChart['colors']['created'] }}"></span>Created</span>
                        <span class="inline-flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full" style="background: {{ $trendsChart['colors']['delivered'] }}"></span>Deliv.</span>
                        <span class="inline-flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full" style="background: {{ $trendsChart['colors']['failed'] }}"></span>Failed</span>
                    </span>
                </div>
                @php $hasTrendData = array_sum($trendsChart['created']) + array_sum($trendsChart['delivered']) + array_sum($trendsChart['failed']) > 0; @endphp
                @if(!$hasTrendData)
                    <div class="flex-1 flex flex-col items-center justify-center py-6 text-center">
                        <div class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-gray-100 text-gray-400 mb-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/></svg>
                        </div>
                        <p class="text-[11px] font-semibold text-gray-700">No trend data yet</p>
                        <p class="text-[10px] text-gray-500 mt-0.5">Activity will appear once deliveries are created</p>
                    </div>
                @else
                    <div class="mt-2 relative flex-1" style="height: 180px; max-height: 210px;">
                        <canvas id="trendsChart" aria-label="Delivery trends line chart" role="img" class="cursor-pointer"></canvas>
                    </div>
                    <div class="mt-1.5 flex sm:hidden flex-wrap items-center justify-center gap-2 text-[10px] leading-none">
                        <span class="inline-flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full" style="background: {{ $trendsChart['colors']['created'] }}"></span> Created</span>
                        <span class="inline-flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full" style="background: {{ $trendsChart['colors']['delivered'] }}"></span> Delivered</span>
                        <span class="inline-flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full" style="background: {{ $trendsChart['colors']['failed'] }}"></span> Failed</span>
                    </div>
                    <div x-show="selectedTrendDate" x-cloak class="mt-2 bg-teal-light/40 border border-teal/20 rounded-lg px-2.5 py-2 flex items-center justify-between gap-2">
                        <span class="text-[11px] text-gray-700"><span class="font-semibold" x-text="selectedTrendDate"></span> <span class="text-gray-500" x-text="selectedTrendDetail"></span></span>
                        <a :href="trendLink" class="text-[10px] font-semibold text-teal hover:text-teal-dark whitespace-nowrap">View records →</a>
                    </div>
                @endif
            </div>

            {{-- Center Performance (Bar) --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-3 sm:p-4 flex flex-col" style="min-height: 260px; max-height: 300px;">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h2 class="text-xs font-bold text-gray-900 leading-tight">Center Performance</h2>
                        <p class="text-[10px] text-gray-500 mt-0.5 leading-snug">
                            @if($staffCenterId) Your assigned center @else Across logistics centers @endif
                        </p>
                    </div>
                    <div class="flex items-center gap-1.5 flex-shrink-0">
                        @if(!$staffCenterId)
                            <span class="text-[10px] font-semibold text-gray-600 bg-gray-50 px-2 py-0.5 rounded-full whitespace-nowrap">{{ count($centerPerformanceChart['labels']) }} centers</span>
                        @else
                            <span class="text-[10px] font-semibold text-teal-dark bg-teal-light px-2 py-0.5 rounded-full whitespace-nowrap">Your center</span>
                        @endif
                        <button @click="openCenterModal()" class="inline-flex items-center gap-1 text-[10px] font-semibold text-teal hover:text-teal-dark bg-teal-light/50 hover:bg-teal-light px-2 py-0.5 rounded-full transition" aria-label="Expand Center Performance chart">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                            Expand
                        </button>
                    </div>
                </div>
                @if($centerPerformanceChart['empty'])
                    <div class="flex-1 flex flex-col items-center justify-center py-6 text-center">
                        <div class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-gray-100 text-gray-400 mb-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        </div>
                        <p class="text-[11px] font-semibold text-gray-700">No center data yet</p>
                        <p class="text-[10px] text-gray-500 mt-0.5">Deliveries will appear once centers handle parcels</p>
                    </div>
                @else
                    <div class="mt-2 relative flex-1" style="height: 190px; max-height: 220px;">
                        <canvas id="centerChart" aria-label="Center performance bar chart" role="img" class="cursor-pointer"></canvas>
                    </div>
                    <p class="mt-1.5 text-[9px] text-gray-400 text-center leading-none truncate">Total · Delivered · Pending · Failed per center</p>
                    <div x-show="selectedCenter" x-cloak class="mt-2 bg-teal-light/40 border border-teal/20 rounded-lg px-2.5 py-2 flex items-center justify-between gap-2">
                        <span class="text-[11px] text-gray-700"><span class="font-semibold" x-text="selectedCenter"></span> <span class="text-gray-500" x-text="selectedCenterDetail"></span></span>
                        <a :href="centerLink" class="text-[10px] font-semibold text-teal hover:text-teal-dark whitespace-nowrap">View records →</a>
                    </div>
                @endif
            </div>

            {{-- Expand Modals --}}
            <template x-teleport="body">
                <div x-show="showStatusModal" x-cloak @keydown.escape.window="closeStatusModal()" class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="Delivery Status details">
                    <div @click="closeStatusModal()" class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>
                    <div x-show="showStatusModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="relative bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden">
                        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                            <div>
                                <h3 class="text-sm font-bold text-gray-900">Delivery Status — Details</h3>
                                <p class="text-xs text-gray-500">Grouped by current delivery status · {{ $statusChart['total'] }} total</p>
                            </div>
                            <button @click="closeStatusModal()" class="p-1.5 rounded-full hover:bg-gray-100 text-gray-500 hover:text-gray-700" aria-label="Close details"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                        </div>
                        <div class="p-5 flex-1 overflow-y-auto">
                            <div class="relative mx-auto" style="height: 280px; max-width: 380px;">
                                <canvas id="statusChartModal" aria-label="Delivery status details chart" role="img"></canvas>
                            </div>
                            <div class="mt-4 grid grid-cols-2 gap-2">
                                @foreach($statusChart['labels'] as $i => $label)
                                    @php $count = $statusChart['data'][$i]; $color = $statusChart['colors'][$i]; $key = array_search($label, $status_labels); $statusKey = $key !== false ? array_keys($status_labels)[$i] ?? '' : ''; @endphp
                                    <a href="{{ route('deliveries.index', ['status' => array_search($label, $status_labels) ? array_keys($status_labels)[array_search($label, array_values($status_labels))] : '']) }}" class="flex items-center justify-between gap-2 text-xs border border-gray-100 rounded-lg px-2.5 py-2 hover:bg-gray-50">
                                        <span class="flex items-center gap-1.5 min-w-0"><span class="h-2.5 w-2.5 rounded-full flex-shrink-0" style="background: {{ $color }}"></span><span class="text-gray-700 truncate">{{ $label }}</span></span>
                                        <span class="font-bold text-gray-900">{{ $count }}</span>
                                    </a>
                                @endforeach
                            </div>
                            @php
                                $statusMap = [];
                                foreach ($statusChart['labels'] as $idx => $lbl) {
                                    foreach ($status_labels as $k => $v) {
                                        if ($v === $lbl) { $statusMap[$lbl] = $k; break; }
                                    }
                                }
                            @endphp
                            <div class="hidden" id="statusKeyMap" data-map='@json($statusMap)'></div>
                        </div>
                        <div class="px-5 py-3 border-t border-gray-100 flex justify-end">
                            <button @click="closeStatusModal()" class="px-4 py-1.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700 hover:bg-gray-200">Close</button>
                        </div>
                    </div>
                </div>
            </template>

            <template x-teleport="body">
                <div x-show="showTrendsModal" x-cloak @keydown.escape.window="closeTrendsModal()" class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="Delivery Trends details">
                    <div @click="closeTrendsModal()" class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>
                    <div x-show="showTrendsModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="relative bg-white rounded-2xl shadow-xl w-full max-w-3xl max-h-[90vh] flex flex-col overflow-hidden">
                        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                            <div>
                                <h3 class="text-sm font-bold text-gray-900">Delivery Trends — Details</h3>
                                <p class="text-xs text-gray-500">Daily created, delivered and failed · <span class="font-medium" x-text="trendsRangeLabel"></span></p>
                            </div>
                            <button @click="closeTrendsModal()" class="p-1.5 rounded-full hover:bg-gray-100 text-gray-500 hover:text-gray-700" aria-label="Close details"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                        </div>
                        <div class="p-5 flex-1 overflow-y-auto">
                            <div class="flex items-center gap-1.5 mb-3">
                                <a :href="'{{ route('dashboard') }}?trends_range=7'" class="px-2.5 py-1 rounded-full text-[11px] font-semibold" :class="trendsRange === 7 ? 'bg-teal text-white' : 'bg-gray-100 text-gray-600'">7 days</a>
                                <a :href="'{{ route('dashboard') }}?trends_range=30'" class="px-2.5 py-1 rounded-full text-[11px] font-semibold" :class="trendsRange === 30 ? 'bg-teal text-white' : 'bg-gray-100 text-gray-600'">30 days</a>
                                <a :href="'{{ route('dashboard') }}?trends_range=90'" class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-gray-50 text-gray-400 cursor-not-allowed" title="Frontend-only preview (backend currently supports 7/30)">90 days (preview)</a>
                                <span class="ml-auto text-[10px] text-gray-400">Frontend expanded view</span>
                            </div>
                            <div class="relative" style="height: 300px;">
                                <canvas id="trendsChartModal" aria-label="Delivery trends detailed chart" role="img"></canvas>
                            </div>
                            <p class="mt-2 text-[10px] text-gray-400 text-center">Click a point to see date details · 90-day is frontend preview (backend 7/30)</p>
                            <div x-show="selectedTrendDate" class="mt-3 bg-teal-light/40 border border-teal/20 rounded-lg px-3 py-2.5 flex items-center justify-between gap-2">
                                <span class="text-xs text-gray-700"><span class="font-bold" x-text="selectedTrendDate"></span> — <span x-text="selectedTrendDetail"></span></span>
                                <a :href="trendLink" class="text-xs font-semibold text-teal hover:text-teal-dark">View records →</a>
                            </div>
                        </div>
                        <div class="px-5 py-3 border-t border-gray-100 flex justify-end">
                            <button @click="closeTrendsModal()" class="px-4 py-1.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700 hover:bg-gray-200">Close</button>
                        </div>
                    </div>
                </div>
            </template>

            <template x-teleport="body">
                <div x-show="showCenterModal" x-cloak @keydown.escape.window="closeCenterModal()" class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="Center Performance details">
                    <div @click="closeCenterModal()" class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>
                    <div x-show="showCenterModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="relative bg-white rounded-2xl shadow-xl w-full max-w-3xl max-h-[90vh] flex flex-col overflow-hidden">
                        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                            <div>
                                <h3 class="text-sm font-bold text-gray-900">Center Performance — Details</h3>
                                <p class="text-xs text-gray-500">@if($staffCenterId) Your assigned center @else All centers comparison @endif · Total / Delivered / Pending / Failed</p>
                            </div>
                            <button @click="closeCenterModal()" class="p-1.5 rounded-full hover:bg-gray-100 text-gray-500 hover:text-gray-700" aria-label="Close details"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                        </div>
                        <div class="p-5 flex-1 overflow-y-auto">
                            <div class="relative" style="height: 320px;">
                                <canvas id="centerChartModal" aria-label="Center performance detailed chart" role="img"></canvas>
                            </div>
                            <div x-show="selectedCenter" class="mt-3 bg-teal-light/40 border border-teal/20 rounded-lg px-3 py-2.5 flex items-center justify-between gap-2">
                                <span class="text-xs text-gray-700"><span class="font-bold" x-text="selectedCenter"></span> — <span x-text="selectedCenterDetail"></span></span>
                                <a :href="centerLink" class="text-xs font-semibold text-teal hover:text-teal-dark">View records →</a>
                            </div>
                            <p class="mt-2 text-[10px] text-gray-400 text-center">Full center names shown in tooltip · Click a bar for details</p>
                        </div>
                        <div class="px-5 py-3 border-t border-gray-100 flex justify-end">
                            <button @click="closeCenterModal()" class="px-4 py-1.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700 hover:bg-gray-200">Close</button>
                        </div>
                    </div>
                </div>
            </template>
        </section>

        {{-- Detailed Records — collapsible, paginated, searchable --}}
        <section class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden" x-data="detailedRecords()" x-init="init()">
            <div class="px-4 sm:px-5 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-gray-100">
                <div class="min-w-0">
                    <h2 class="text-xs font-bold text-gray-900 leading-tight">Detailed Records</h2>
                    <p class="text-[10px] text-gray-500 mt-0.5">Search, filter and paginate recent deliveries · <span class="font-medium" x-text="filtered.length + ' of ' + all.length"></span></p>
                </div>
                <button @click="expanded = !expanded" class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-teal hover:text-teal-dark bg-teal-light/50 hover:bg-teal-light px-3 py-1 rounded-full transition flex-shrink-0" :aria-expanded="expanded.toString()" aria-label="Toggle detailed records">
                    <span x-text="expanded ? 'Collapse' : 'Expand'"></span>
                    <svg class="w-3 h-3 transition-transform" :class="expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
            </div>

            <div x-show="expanded" x-collapse x-cloak>
                <div class="p-3 sm:p-4 bg-gray-50/50 border-b border-gray-100 space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2">
                        <label class="flex flex-col gap-1">
                            <span class="text-[10px] font-semibold text-gray-600">Search</span>
                            <input x-model="search" @input.debounce.300ms="applyFilters()" type="text" placeholder="Tracking #, recipient, center…" class="w-full rounded-lg border-gray-200 text-xs px-2.5 py-1.5 focus:border-teal focus:ring-teal" aria-label="Search recent deliveries">
                        </label>
                        <label class="flex flex-col gap-1">
                            <span class="text-[10px] font-semibold text-gray-600">Status</span>
                            <select x-model="statusFilter" @change="applyFilters()" class="w-full rounded-lg border-gray-200 text-xs px-2 py-1.5 focus:border-teal focus:ring-teal" aria-label="Filter by status">
                                <option value="">All statuses</option>
                                <template x-for="opt in statusOptions" :key="opt.value">
                                    <option :value="opt.value" x-text="opt.label"></option>
                                </template>
                            </select>
                        </label>
                        <label class="flex flex-col gap-1">
                            <span class="text-[10px] font-semibold text-gray-600">Center</span>
                            <select x-model="centerFilter" @change="applyFilters()" class="w-full rounded-lg border-gray-200 text-xs px-2 py-1.5 focus:border-teal focus:ring-teal" aria-label="Filter by center">
                                <option value="">All centers</option>
                                <template x-for="c in centerOptions" :key="c.value">
                                    <option :value="c.value" x-text="c.label"></option>
                                </template>
                            </select>
                        </label>
                        <label class="flex flex-col gap-1">
                            <span class="text-[10px] font-semibold text-gray-600">Sort by</span>
                            <select x-model="sortBy" @change="applyFilters()" class="w-full rounded-lg border-gray-200 text-xs px-2 py-1.5 focus:border-teal focus:ring-teal" aria-label="Sort records">
                                <option value="updated_desc">Updated — newest</option>
                                <option value="updated_asc">Updated — oldest</option>
                                <option value="tracking_asc">Tracking — A→Z</option>
                                <option value="status_asc">Status — A→Z</option>
                            </select>
                        </label>
                    </div>
                    <p class="text-[10px] text-gray-400">Frontend-only filtering of the current page’s records · Use <a href="{{ route('deliveries.index') }}" class="text-teal hover:underline font-medium">Deliveries →</a> for server-side search & pagination</p>
                </div>

                <div x-show="paginated.length === 0 && all.length > 0" class="py-10 text-center">
                    <p class="text-xs font-semibold text-gray-700">No records match your filters</p>
                    <p class="text-[11px] text-gray-500 mt-1">Try adjusting search or filters</p>
                    <button @click="clearFilters()" class="mt-3 text-[11px] font-semibold text-teal hover:text-teal-dark">Clear filters</button>
                </div>

                <div x-show="all.length === 0" class="py-10 text-center">
                    <p class="text-xs font-semibold text-gray-700">No delivery records yet</p>
                    <p class="text-[11px] text-gray-500 mt-1">New deliveries will appear here once created</p>
                </div>

                {{-- Desktop table --}}
                <div x-show="paginated.length > 0" class="hidden sm:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-[10px] font-semibold text-gray-500 uppercase tracking-wider">Tracking #</th>
                                <th class="px-3 py-2 text-left text-[10px] font-semibold text-gray-500 uppercase tracking-wider">Recipient</th>
                                <th class="px-3 py-2 text-left text-[10px] font-semibold text-gray-500 uppercase tracking-wider">Center</th>
                                <th class="px-3 py-2 text-left text-[10px] font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-3 py-2 text-right text-[10px] font-semibold text-gray-500 uppercase tracking-wider">Updated</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="row in paginated" :key="row.id">
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-2 text-xs font-mono font-medium text-gray-900 whitespace-nowrap"><a :href="row.showUrl" class="hover:text-teal-dark" x-text="row.tracking"></a></td>
                                    <td class="px-3 py-2 text-xs text-gray-600 truncate max-w-[140px]" x-text="row.recipient"></td>
                                    <td class="px-3 py-2 text-xs text-gray-600 truncate max-w-[120px]" x-text="row.center"></td>
                                    <td class="px-3 py-2"><span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold" :class="statusClass(row.statusRaw)" x-text="row.status"></span></td>
                                    <td class="px-3 py-2 text-[11px] text-gray-500 text-right whitespace-nowrap" x-text="row.updated"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                {{-- Mobile cards --}}
                <div class="sm:hidden divide-y divide-gray-100" x-show="paginated.length > 0">
                    <template x-for="row in paginated" :key="row.id">
                        <a :href="row.showUrl" class="block px-3 py-3 hover:bg-gray-50">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-mono font-bold text-gray-900 truncate" x-text="row.tracking"></span>
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold flex-shrink-0" :class="statusClass(row.statusRaw)" x-text="row.status"></span>
                            </div>
                            <p class="text-[11px] text-gray-600 truncate mt-0.5" x-text="row.recipient + ' · ' + row.center"></p>
                            <p class="text-[10px] text-gray-400 mt-0.5" x-text="row.updated"></p>
                        </a>
                    </template>
                </div>

                <div class="flex flex-col sm:flex-row items-center justify-between gap-2 px-3 sm:px-4 py-3 border-t border-gray-100 bg-gray-50/30">
                    <p class="text-[11px] text-gray-500"><span x-text="'Page ' + page + ' of ' + totalPages"></span> · <span x-text="filtered.length + ' records'"></span></p>
                    <div class="flex items-center gap-1">
                        <button @click="prev()" :disabled="page===1" class="px-2.5 py-1 rounded-lg text-[11px] font-semibold border disabled:opacity-40 disabled:cursor-not-allowed" :class="page===1 ? 'bg-white text-gray-400 border-gray-200' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50'">Prev</button>
                        <span class="text-[11px] text-gray-500 px-1" x-text="page + ' / ' + totalPages"></span>
                        <button @click="next()" :disabled="page===totalPages" class="px-2.5 py-1 rounded-lg text-[11px] font-semibold border disabled:opacity-40 disabled:cursor-not-allowed" :class="page===totalPages ? 'bg-white text-gray-400 border-gray-200' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50'">Next</button>
                        <a href="{{ route('deliveries.index') }}" class="ml-2 text-[11px] font-semibold text-teal hover:text-teal-dark">Full view →</a>
                    </div>
                </div>
            </div>

            <div x-show="!expanded" class="px-4 py-3 text-center">
                <p class="text-[11px] text-gray-500">Expanded view shows search, filters and paginated records · <button @click="expanded=true" class="font-semibold text-teal hover:text-teal-dark">Expand</button></p>
            </div>
        </section>

        {{-- Rider availability --}}
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 sm:p-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-bold text-gray-900">Rider Availability</h2>
                    <a href="{{ route('riders.index') }}" class="text-sm font-semibold text-teal hover:text-teal-dark">View Riders →</a>
                </div>
                <div class="flex items-center gap-6 mt-4">
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full bg-green-500"></span>
                        <span class="text-sm text-gray-600">Online — Available</span>
                        <span class="text-lg font-bold text-gray-900 ml-1">{{ $riders['online'] }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full bg-gray-300"></span>
                        <span class="text-sm text-gray-600">Offline — Not available for new deliveries</span>
                        <span class="text-lg font-bold text-gray-900 ml-1">{{ $riders['offline'] }}</span>
                    </div>
                </div>

                <div class="mt-5">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Currently Online</p>
                    @forelse ($onlineRiders as $rider)
                        <a href="{{ route('riders.show', $rider) }}" class="flex items-center gap-3 py-2.5 px-2 -mx-2 rounded-lg hover:bg-teal-light/40 transition group">
                            <span class="relative flex-shrink-0">
                                <span class="h-9 w-9 rounded-full bg-teal flex items-center justify-center text-white text-sm font-bold">{{ strtoupper(substr($rider->name, 0, 1)) }}</span>
                                <span class="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full bg-green-500 border-2 border-white"></span>
                            </span>
                            <span class="flex-1 min-w-0">
                                <span class="block text-sm font-semibold text-gray-900 truncate">{{ $rider->name }}</span>
                                <span class="block text-xs text-gray-500 truncate">{{ $rider->serviceArea?->name ?? 'No service area' }}</span>
                            </span>
                            <span class="text-xs text-gray-500">{{ $rider->active_deliveries }} active</span>
                        </a>
                    @empty
                        <div class="py-6 text-center text-sm text-gray-500">No riders are currently available for new assignments.</div>
                    @endforelse
                </div>
            </div>

            {{-- Recent activity --}}
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 sm:p-6">
                <h2 class="text-base font-bold text-gray-900">Recent Activity</h2>
                <div class="mt-4">
                    @forelse ($activities as $activity)
                        <a href="{{ route('deliveries.index', ['search' => $activity['tracking']]) }}" class="flex items-start gap-3 py-2.5 px-1 -mx-1 rounded-lg hover:bg-gray-50 transition group">
                            <span class="mt-1 flex-shrink-0 h-2.5 w-2.5 rounded-full mt-1.5 {{ $activity['status'] === 'delivery_failed' ? 'bg-red-400' : ($activity['status'] === 'delivered' ? 'bg-emerald-400' : 'bg-teal') }}"></span>
                            <span class="flex-1 min-w-0">
                                <span class="block text-sm text-gray-700"><span class="font-mono font-semibold text-gray-900">{{ $activity['tracking'] }}</span> — {{ $activity['message'] }}</span>
                                <span class="block text-xs text-gray-400 mt-0.5">{{ $activity['created_at'] ? $activity['created_at']->diffForHumans() : '' }}{{ $activity['by'] ? ' · by ' . $activity['by'] : '' }}{{ $activity['recipient'] ? ' · ' . $activity['recipient'] : '' }}</span>
                            </span>
                        </a>
                    @empty
                        <div class="py-6 text-center text-sm text-gray-500">No activity recorded yet.</div>
                    @endforelse
                </div>
            </div>
        </section>

        {{-- Service area + rider workload --}}
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-5 sm:px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-base font-bold text-gray-900">Service Area Overview</h2>
                    <a href="{{ route('deliveries.index') }}" class="text-sm font-semibold text-teal hover:text-teal-dark">View All →</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Service Area</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Deliveries</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Active</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($serviceAreas as $area)
                                <tr class="hover:bg-teal-light/40 transition">
                                    <td class="px-5 py-3.5 text-sm font-medium text-gray-900">{{ $area['name'] }}</td>
                                    <td class="px-5 py-3.5 text-sm text-right text-gray-900">{{ $area['total'] }}</td>
                                    <td class="px-5 py-3.5 text-sm text-right text-teal-dark font-semibold">{{ $area['active'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-5 py-10 text-center text-sm text-gray-500">No service area data yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-5 sm:px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-base font-bold text-gray-900">Rider Workload</h2>
                    <a href="{{ route('riders.index') }}" class="text-sm font-semibold text-teal hover:text-teal-dark">View Riders →</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Rider</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Area</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Active</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($riderWorkload as $rider)
                                <tr class="hover:bg-teal-light/40 transition">
                                    <td class="px-5 py-3.5 whitespace-nowrap">
                                        <div class="flex items-center gap-2.5">
                                            @if($rider->is_online)
                                                <span class="h-2 w-2 rounded-full bg-green-500 flex-shrink-0" title="Online — available"></span>
                                            @else
                                                <span class="h-2 w-2 rounded-full bg-gray-300 flex-shrink-0" title="Offline — not available for new deliveries"></span>
                                            @endif
                                            <a href="{{ route('riders.show', $rider) }}" class="text-sm font-medium text-gray-900 hover:text-teal-dark">{{ $rider->name }}</a>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5 text-sm text-gray-500">{{ $rider->serviceArea?->name ?? '—' }}</td>
                                    <td class="px-5 py-3.5 text-sm text-right">
                                        @if($rider->active_deliveries > 0)
                                            <span class="inline-flex items-center justify-center min-w-[1.75rem] px-2 py-0.5 rounded-full bg-teal-light text-teal-dark text-xs font-bold">{{ $rider->active_deliveries }}</span>
                                        @else
                                            <span class="text-xs text-gray-400">No active delivery</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-5 py-10 text-center text-sm text-gray-500">No riders yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        {{-- Recent deliveries --}}
        <section class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-5 sm:px-6 py-4 border-b border-gray-100">
                <h2 class="text-base font-bold text-gray-900">Recent Deliveries</h2>
                <a href="{{ route('deliveries.index') }}" class="text-sm font-semibold text-teal hover:text-teal-dark transition">View All →</a>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Tracking #</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Recipient</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Rider</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Updated</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($recent_deliveries as $delivery)
                            <tr class="hover:bg-gray-50 transition-colors duration-150">
                                <td class="px-4 py-3.5 text-sm font-mono font-medium text-gray-900 whitespace-nowrap">
                                    <a href="{{ route('deliveries.show', $delivery) }}" class="hover:text-teal-dark transition-colors">{{ $delivery->tracking_number }}</a>
                                </td>
                                <td class="px-4 py-3.5 text-sm text-gray-600">{{ $delivery->recipient_name }}</td>
                                <td class="px-4 py-3.5 text-sm text-gray-600 whitespace-nowrap">
                                    @if($delivery->rider)
                                        <span class="inline-flex items-center gap-1.5 text-gray-700">{{ $delivery->rider->name }}</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-full bg-gray-100 text-gray-500 ring-1 ring-inset ring-gray-200">Unassigned</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5">
                                    <x-status-badge :status="$delivery->status" />
                                </td>
                                <td class="px-4 py-3.5 text-sm text-gray-500 whitespace-nowrap">{{ $delivery->updated_at->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-12">
                                    <div class="flex flex-col items-center text-center">
                                        <div class="w-14 h-14 rounded-2xl bg-teal-light text-teal-dark flex items-center justify-center mb-3">
                                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                                        </div>
                                        <p class="text-sm font-semibold text-gray-700">No recent deliveries yet</p>
                                        <p class="text-sm text-gray-500 mt-1">Deliveries will appear here as orders come in.</p>
                                        <a href="{{ route('deliveries.index') }}" class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white bg-teal hover:bg-teal-dark transition">Go to Deliveries</a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
    function dashboardCharts() {
        return {
            selectedStatus: null,
            selectedStatusCount: null,
            statusLink: '#',
            selectedTrendDate: null,
            selectedTrendDetail: null,
            trendLink: '#',
            selectedCenter: null,
            selectedCenterDetail: null,
            centerLink: '#',
            showStatusModal: false,
            showTrendsModal: false,
            showCenterModal: false,
            trendsRange: @json($trendsRange),
            trendsRangeLabel: @json($trendsRange === 30 ? 'Last 30 days' : 'Last 7 days'),
            statusChartInstance: null,
            trendsChartInstance: null,
            centerChartInstance: null,
            statusModalInstance: null,
            trendsModalInstance: null,
            centerModalInstance: null,
            initCharts() {
                this.$nextTick(() => this.renderCharts());
            },
            renderCharts() {
                // Status doughnut
                const statusEl = document.getElementById('statusChart');
                if (statusEl) {
                    const statusData = @json($statusChart);
                    if (statusData.total > 0 && statusData.labels.length) {
                        if (this.statusChartInstance) this.statusChartInstance.destroy();
                        this.statusChartInstance = new Chart(statusEl, {
                            type: 'doughnut',
                            data: {
                                labels: statusData.labels,
                                datasets: [{
                                    data: statusData.data,
                                    backgroundColor: statusData.colors,
                                    borderWidth: 2,
                                    borderColor: '#ffffff',
                                    hoverOffset: 4
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                cutout: '64%',
                                layout: { padding: { top: 4, right: 4, bottom: 4, left: 4 } },
                                animation: { duration: 400 },
                                onClick: (e, els) => {
                                    if (els.length) {
                                        const idx = els[0].index;
                                        const label = statusData.labels[idx];
                                        const count = statusData.data[idx];
                                        this.selectedStatus = label;
                                        this.selectedStatusCount = count;
                                        const mapEl = document.getElementById('statusKeyMap');
                                        const map = mapEl ? JSON.parse(mapEl.dataset.map || '{}') : {};
                                        const key = map[label] || '';
                                        this.statusLink = key ? `{{ route('deliveries.index') }}?status=${key}` : `{{ route('deliveries.index') }}`;
                                    }
                                },
                                plugins: {
                                    legend: { display: false },
                                    tooltip: {
                                        backgroundColor: '#111827',
                                        titleFont: { size: 11 },
                                        bodyFont: { size: 11 },
                                        padding: 8,
                                        cornerRadius: 8,
                                        callbacks: {
                                            label: (ctx) => {
                                                const label = ctx.label || '';
                                                const value = ctx.parsed || 0;
                                                const total = ctx.dataset.data.reduce((a,b)=>a+b,0);
                                                const pct = total ? Math.round(value/total*100) : 0;
                                                return `${label}: ${value} (${pct}%)`;
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    }
                }
                // Trends line
                const trendsEl = document.getElementById('trendsChart');
                if (trendsEl) {
                    const trends = @json($trendsChart);
                    const hasData = trends && (trends.created.reduce((a,b)=>a+b,0) + trends.delivered.reduce((a,b)=>a+b,0) + trends.failed.reduce((a,b)=>a+b,0) > 0);
                    if (hasData) {
                        const is30 = trends.labels.length > 7;
                        if (this.trendsChartInstance) this.trendsChartInstance.destroy();
                        this.trendsChartInstance = new Chart(trendsEl, {
                            type: 'line',
                            data: {
                                labels: trends.labels,
                                datasets: [
                                    { label: 'Created', data: trends.created, borderColor: trends.colors.created, backgroundColor: trends.colors.created + '1A', borderWidth: 1.8, tension: 0.35, fill: true, pointRadius: 2, pointHoverRadius: 4, pointBackgroundColor: trends.colors.created },
                                    { label: 'Delivered', data: trends.delivered, borderColor: trends.colors.delivered, backgroundColor: trends.colors.delivered + '1A', borderWidth: 1.8, tension: 0.35, fill: true, pointRadius: 2, pointHoverRadius: 4, pointBackgroundColor: trends.colors.delivered },
                                    { label: 'Failed', data: trends.failed, borderColor: trends.colors.failed, backgroundColor: 'transparent', borderWidth: 1.8, tension: 0.35, fill: false, pointRadius: 2, pointHoverRadius: 4, pointBackgroundColor: trends.colors.failed }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                interaction: { mode: 'index', intersect: false },
                                layout: { padding: { top: 4, right: 8, bottom: 0, left: 4 } },
                                animation: { duration: 350 },
                                onClick: (e, els) => {
                                    if (els.length) {
                                        const idx = els[0].index;
                                        const label = trends.labels[idx];
                                        const created = trends.created[idx] || 0;
                                        const delivered = trends.delivered[idx] || 0;
                                        const failed = trends.failed[idx] || 0;
                                        this.selectedTrendDate = label;
                                        this.selectedTrendDetail = `${created} created · ${delivered} delivered · ${failed} failed`;
                                        const dateStr = trends.dates ? trends.dates[idx] : '';
                                        this.trendLink = dateStr ? `{{ route('deliveries.index') }}?date_from=${dateStr}&date_to=${dateStr}` : `{{ route('deliveries.index') }}`;
                                    }
                                },
                                scales: {
                                    y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 }, color: '#6B7280', padding: 6 }, grid: { color: '#F3F4F6' }, border: { display: false } },
                                    x: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 10 }, color: '#6B7280', maxRotation: 0, autoSkip: true, maxTicksLimit: is30 ? 8 : 7, padding: 6 } }
                                },
                                plugins: {
                                    legend: { display: false },
                                    tooltip: { backgroundColor: '#111827', titleFont: { size: 11 }, bodyFont: { size: 11 }, padding: 8, cornerRadius: 8, mode: 'index', intersect: false }
                                }
                            }
                        });
                    }
                }
                // Center bar
                const centerEl = document.getElementById('centerChart');
                if (centerEl) {
                    const center = @json($centerPerformanceChart);
                    if (!center.empty && center.labels.length) {
                        const longNames = center.labels.some(l => l.length > 14);
                        const isHorizontal = longNames && center.labels.length <= 8;
                        const displayLabels = center.labels.map(l => l.length > 18 ? l.slice(0, 18) + '…' : l);
                        const baseOptions = {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            layout: { padding: { top: 4, right: 8, bottom: 4, left: 4 } },
                            animation: { duration: 350 },
                            onClick: (e, els) => {
                                if (els.length) {
                                    const idx = els[0].index;
                                    const label = center.labels[idx];
                                    const total = center.total[idx] || 0;
                                    const delivered = center.delivered[idx] || 0;
                                    this.selectedCenter = label;
                                    this.selectedCenterDetail = `${total} total · ${delivered} delivered`;
                                    this.centerLink = `{{ route('deliveries.index') }}` + (label ? `?search=${encodeURIComponent(label)}` : '');
                                }
                            },
                            plugins: {
                                legend: { position: 'bottom', labels: { usePointStyle: true, pointStyleWidth: 8, padding: 14, font: { size: 10 }, color: '#4B5563', boxPadding: 4 } },
                                tooltip: {
                                    backgroundColor: '#111827',
                                    titleFont: { size: 11 },
                                    bodyFont: { size: 11 },
                                    padding: 8,
                                    cornerRadius: 8,
                                    mode: 'index',
                                    intersect: false,
                                    callbacks: { title: (items) => { const idx = items[0]?.dataIndex; return center.labels[idx] || ''; } }
                                }
                            }
                        };
                        const verticalScales = {
                            y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 }, color: '#6B7280', padding: 6 }, grid: { color: '#F3F4F6' }, border: { display: false } },
                            x: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 10 }, color: '#4B5563', maxRotation: 32, autoSkip: true, maxTicksLimit: 6, padding: 6, callback: function(v){ const l=this.getLabelForValue(v); return l.length>12?l.slice(0,12)+'…':l; } } }
                        };
                        const horizontalScales = {
                            x: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 }, color: '#6B7280', padding: 6 }, grid: { color: '#F3F4F6' }, border: { display: false } },
                            y: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 10 }, color: '#4B5563', padding: 6 } }
                        };
                        if (this.centerChartInstance) this.centerChartInstance.destroy();
                        this.centerChartInstance = new Chart(centerEl, {
                            type: 'bar',
                            data: {
                                labels: displayLabels,
                                datasets: [
                                    { label: 'Total', data: center.total, backgroundColor: '#16697A', borderRadius: 5, barPercentage: 0.48, categoryPercentage: 0.58, borderSkipped: false },
                                    { label: 'Delivered', data: center.delivered, backgroundColor: '#059669', borderRadius: 5, barPercentage: 0.48, categoryPercentage: 0.58, borderSkipped: false },
                                    { label: 'Pending', data: center.pending, backgroundColor: '#F59E0B', borderRadius: 5, barPercentage: 0.48, categoryPercentage: 0.58, borderSkipped: false },
                                    { label: 'Failed', data: center.failed, backgroundColor: '#EF4444', borderRadius: 5, barPercentage: 0.48, categoryPercentage: 0.58, borderSkipped: false }
                                ]
                            },
                            options: Object.assign({}, baseOptions, { indexAxis: isHorizontal ? 'y' : 'x', scales: isHorizontal ? horizontalScales : verticalScales })
                        });
                    }
                }
            }
            ,
            openStatusModal() {
                this.showStatusModal = true;
                this.$nextTick(() => {
                    const el = document.getElementById('statusChartModal');
                    if (!el) return;
                    const statusData = @json($statusChart);
                    if (statusData.total === 0) return;
                    if (this.statusModalInstance) this.statusModalInstance.destroy();
                    this.statusModalInstance = new Chart(el, {
                        type: 'doughnut',
                        data: { labels: statusData.labels, datasets: [{ data: statusData.data, backgroundColor: statusData.colors, borderWidth: 2, borderColor: '#ffffff', hoverOffset: 6 }] },
                        options: { responsive: true, maintainAspectRatio: false, cutout: '58%', layout: { padding: 8 }, animation: { duration: 300 }, plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 12, font: { size: 11 } } }, tooltip: { backgroundColor: '#111827', padding: 8, cornerRadius: 8 } } }
                    });
                });
            },
            closeStatusModal() { this.showStatusModal = false; },
            openTrendsModal() {
                this.showTrendsModal = true;
                this.$nextTick(() => {
                    const el = document.getElementById('trendsChartModal');
                    if (!el) return;
                    const trends = @json($trendsChart);
                    const hasData = trends && (trends.created.reduce((a,b)=>a+b,0) + trends.delivered.reduce((a,b)=>a+b,0) + trends.failed.reduce((a,b)=>a+b,0) > 0);
                    if (!hasData) return;
                    if (this.trendsModalInstance) this.trendsModalInstance.destroy();
                    this.trendsModalInstance = new Chart(el, {
                        type: 'line',
                        data: {
                            labels: trends.labels,
                            datasets: [
                                { label: 'Created', data: trends.created, borderColor: trends.colors.created, backgroundColor: trends.colors.created + '1A', borderWidth: 2, tension: 0.3, fill: true, pointRadius: 3, pointHoverRadius: 5 },
                                { label: 'Delivered', data: trends.delivered, borderColor: trends.colors.delivered, backgroundColor: trends.colors.delivered + '1A', borderWidth: 2, tension: 0.3, fill: true, pointRadius: 3, pointHoverRadius: 5 },
                                { label: 'Failed', data: trends.failed, borderColor: trends.colors.failed, backgroundColor: 'transparent', borderWidth: 2, tension: 0.3, fill: false, pointRadius: 3, pointHoverRadius: 5 }
                            ]
                        },
                        options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, layout: { padding: 8 }, animation: { duration: 300 }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false } } }, plugins: { legend: { position: 'bottom' } } }
                    });
                });
            },
            closeTrendsModal() { this.showTrendsModal = false; },
            openCenterModal() {
                this.showCenterModal = true;
                this.$nextTick(() => {
                    const el = document.getElementById('centerChartModal');
                    if (!el) return;
                    const center = @json($centerPerformanceChart);
                    if (center.empty) return;
                    if (this.centerModalInstance) this.centerModalInstance.destroy();
                    const displayLabels = center.labels.map(l => l.length > 20 ? l.slice(0,20)+'…' : l);
                    this.centerModalInstance = new Chart(el, {
                        type: 'bar',
                        data: {
                            labels: displayLabels,
                            datasets: [
                                { label: 'Total', data: center.total, backgroundColor: '#16697A', borderRadius: 5 },
                                { label: 'Delivered', data: center.delivered, backgroundColor: '#059669', borderRadius: 5 },
                                { label: 'Pending', data: center.pending, backgroundColor: '#F59E0B', borderRadius: 5 },
                                { label: 'Failed', data: center.failed, backgroundColor: '#EF4444', borderRadius: 5 }
                            ]
                        },
                        options: { responsive: true, maintainAspectRatio: false, indexAxis: center.labels.some(l=>l.length>14) ? 'y' : 'x', layout: { padding: 8 }, animation: { duration: 300 }, scales: { y: { beginAtZero: true }, x: { grid: { display: false } } }, plugins: { legend: { position: 'bottom' } } }
                    });
                });
            },
            closeCenterModal() { this.showCenterModal = false; },
            handleStatusClick(e) {
                // Handled via onClick in chart options
            }
        }
    }
    function detailedRecords() {
        return {
            expanded: false,
            search: '',
            statusFilter: '',
            centerFilter: '',
            sortBy: 'updated_desc',
            page: 1,
            perPage: 5,
            all: @json($detailedRecords),
            filtered: [],
            paginated: [],
            totalPages: 1,
            statusOptions: @json(collect($status_labels)->map(fn($v,$k)=>['value'=>$k,'label'=>$v])->values()),
            centerOptions: @json($centerPerformanceChart['labels'] ? collect($centerPerformanceChart['labels'])->map(fn($l)=>['value'=>$l,'label'=>$l])->values() : []),
            init() {
                // Build center options from actual deliveries if chart has no labels (staff empty)
                if (this.centerOptions.length === 0) {
                    const centers = Array.from(new Set(this.all.map(r=>r.center).filter(Boolean)));
                    this.centerOptions = centers.map(c=>({value:c,label:c}));
                }
                this.applyFilters();
            },
            applyFilters() {
                let rows = this.all.slice();
                const q = this.search.toLowerCase().trim();
                if (q) {
                    rows = rows.filter(r => (r.tracking + ' ' + r.recipient + ' ' + r.center).toLowerCase().includes(q));
                }
                if (this.statusFilter) {
                    rows = rows.filter(r => r.statusRaw === this.statusFilter);
                }
                if (this.centerFilter) {
                    rows = rows.filter(r => r.center === this.centerFilter);
                }
                rows.sort((a,b) => {
                    if (this.sortBy === 'updated_desc') return new Date(b.updated_at) - new Date(a.updated_at);
                    if (this.sortBy === 'updated_asc') return new Date(a.updated_at) - new Date(b.updated_at);
                    if (this.sortBy === 'tracking_asc') return a.tracking.localeCompare(b.tracking);
                    if (this.sortBy === 'status_asc') return a.status.localeCompare(b.status);
                    return 0;
                });
                this.filtered = rows;
                this.totalPages = Math.max(1, Math.ceil(this.filtered.length / this.perPage));
                if (this.page > this.totalPages) this.page = this.totalPages;
                this.paginate();
            },
            paginate() {
                const start = (this.page - 1) * this.perPage;
                this.paginated = this.filtered.slice(start, start + this.perPage);
            },
            prev() { if (this.page > 1) { this.page--; this.paginate(); } },
            next() { if (this.page < this.totalPages) { this.page++; this.paginate(); } },
            clearFilters() { this.search=''; this.statusFilter=''; this.centerFilter=''; this.sortBy='updated_desc'; this.page=1; this.applyFilters(); },
            statusClass(raw) {
                // Mini-pill hues mirror the canonical status badge so the same
                // status reads identically in both places.
                const map = { 'waiting_for_rider':'bg-amber-100 text-amber-700', 'assigned':'bg-blue-100 text-blue-700', 'accepted':'bg-cyan-100 text-cyan-700', 'going_to_pickup':'bg-sky-100 text-sky-700', 'arrived_at_shop':'bg-sky-100 text-sky-700', 'picked_up':'bg-indigo-100 text-indigo-700', 'out_for_delivery':'bg-purple-100 text-purple-700', 'arrived_at_customer':'bg-violet-100 text-violet-700', 'delivered':'bg-emerald-100 text-emerald-700', 'delivery_failed':'bg-red-100 text-red-700', 'cancelled':'bg-gray-100 text-gray-600' };
                return map[raw] || 'bg-gray-100 text-gray-600';
            }
        }
    }
    </script>
    @endpush
</x-app-layout>
