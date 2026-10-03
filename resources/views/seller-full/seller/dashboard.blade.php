@extends('layouts.seller')

@section('title', 'Dashboard')

@section('content')
{{-- ===== Stats cards ===== --}}
<div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
    <div class="stat-card">
        <div class="flex items-center gap-2.5">
            <div class="stat-icon" style="background:#E6F4EC;color:#2E8B57;"><x-icon name="dollar" class="h-4 w-4"/></div>
            <div>
                <div class="text-[10px] font-bold uppercase tracking-wider text-ink-light">Revenue</div>
                <div class="text-base font-extrabold">{{ peso($totals['revenue']) }}</div>
            </div>
        </div>
        <div class="mt-1.5 text-[10px] text-ink-light">Today: <span class="font-semibold text-successc">{{ peso($totals['revenue_today']) }}</span></div>
    </div>
    <div class="stat-card">
        <div class="flex items-center gap-2.5">
            <div class="stat-icon" style="background:#EAF4F3;color:#16697A;"><x-icon name="shopping-bag" class="h-4 w-4"/></div>
            <div>
                <div class="text-[10px] font-bold uppercase tracking-wider text-ink-light">Orders</div>
                <div class="text-base font-extrabold">{{ $totals['orders'] }}</div>
            </div>
        </div>
        <div class="mt-1.5 text-[10px] text-ink-light"><span class="font-semibold text-primary">{{ $totals['delivered'] }}</span> delivered</div>
    </div>
    <div class="stat-card">
        <div class="flex items-center gap-2.5">
            <div class="stat-icon" style="background:#FFF3E0;color:#B26A00;"><x-icon name="clock" class="h-4 w-4"/></div>
            <div>
                <div class="text-[10px] font-bold uppercase tracking-wider text-ink-light">To Action</div>
                <div class="text-base font-extrabold">{{ $totals['pending'] }}</div>
            </div>
        </div>
        <div class="mt-1.5 text-[10px] text-ink-light">Pending / new orders</div>
    </div>
    <div class="stat-card">
        <div class="flex items-center gap-2.5">
            <div class="stat-icon" style="background:#EDE9FE;color:#6D28D9;"><x-icon name="package" class="h-4 w-4"/></div>
            <div>
                <div class="text-[10px] font-bold uppercase tracking-wider text-ink-light">To Ship</div>
                <div class="text-base font-extrabold">{{ $totals['to_ship'] }}</div>
            </div>
        </div>
        <div class="mt-1.5 text-[10px] text-ink-light">Processing / ready</div>
    </div>
    <div class="stat-card">
        <div class="flex items-center gap-2.5">
            <div class="stat-icon" style="background:#EAF4F3;color:#16697A;"><x-icon name="box" class="h-4 w-4"/></div>
            <div>
                <div class="text-[10px] font-bold uppercase tracking-wider text-ink-light">Products</div>
                <div class="text-base font-extrabold">{{ $totals['products'] }}</div>
            </div>
        </div>
        <div class="mt-1.5 text-[10px] text-ink-light">
            @if($totals['low_stock'] > 0)
                <span class="font-semibold text-warnc">{{ $totals['low_stock'] }} low stock</span>
            @else
                <span class="font-semibold text-successc">Stock healthy</span>
            @endif
        </div>
    </div>
    <div class="stat-card">
        <div class="flex items-center gap-2.5">
            <div class="stat-icon" style="background:#FEF3C7;color:#92400E;"><x-icon name="star" class="h-4 w-4"/></div>
            <div>
                <div class="text-[10px] font-bold uppercase tracking-wider text-ink-light">Rating</div>
                <div class="text-base font-extrabold">{{ $totals['rating'] }} <span class="text-xs font-semibold text-ink-light">/5</span></div>
            </div>
        </div>
        <div class="mt-1.5 text-[10px] text-ink-light">{!! rating_stars($totals['rating']) !!}</div>
    </div>
</div>

{{-- ===== Charts ===== --}}
<div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
    <div class="card p-4 xl:col-span-2">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="text-sm font-bold">Sales — Last 30 Days</h3>
            <span class="badge badge-delivered">Delivered orders</span>
        </div>
        <div class="h-56">
            <canvas id="salesChart"></canvas>
        </div>
    </div>
    <div class="card p-4">
        <h3 class="mb-3 text-sm font-bold">Orders by Status</h3>
        <div class="h-44">
            <canvas id="statusChart"></canvas>
        </div>
        <div class="mt-3 space-y-1.5">
            @php $orderTotal = array_sum($statusCounts) ?: 1; @endphp
            @foreach($statusCounts as $status => $count)
                <div class="flex items-center gap-2 text-xs">
                    <span class="badge badge-{{ $status }}"><span class="badge-dot"></span>{{ order_status_label($status) }}</span>
                    <span class="ml-auto font-bold">{{ $count }}</span>
                    <span class="w-14 text-right text-[10px] text-ink-light">{{ round($count / $orderTotal * 100) }}%</span>
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- ===== Recent orders + top products ===== --}}
<div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
    <div class="card overflow-hidden xl:col-span-2">
        <div class="flex items-center justify-between px-4 pt-4 pb-2.5">
            <h3 class="text-sm font-bold">Recent Orders</h3>
            <a href="{{ route('seller.orders.index') }}" class="text-xs font-bold text-primary hover:underline">View all</a>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Buyer</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentOrders as $order)
                    <tr>
                        <td><a href="{{ route('seller.orders.show', $order->id) }}" class="font-bold text-primary hover:underline">#{{ $order->id }}</a></td>
                        <td>{{ $order->buyer->full_name }}</td>
                        <td class="font-semibold">{{ peso($order->seller_subtotal ?? $order->total_amount) }}</td>
                        <td><span class="badge badge-{{ $order->status }}">{{ order_status_label($order->status) }}</span></td>
                        <td class="text-ink-light">{{ $order->created_at->format('M j, g:i A') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-ink-light">No orders yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card overflow-hidden">
        <div class="px-4 pt-4 pb-2.5">
            <h3 class="text-sm font-bold">Top Products</h3>
            <p class="text-[11px] text-ink-light">By units sold</p>
        </div>
        <div class="space-y-3 px-4 pb-4">
            @forelse($topProducts as $product)
                <div class="flex items-center gap-2.5">
                    <div class="avatar h-8 w-8 text-[10px]">{{ strtoupper(substr($product->product_name, 0, 1)) }}</div>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-xs font-semibold">{{ $product->product_name }}</div>
                        <div class="text-[11px] text-ink-light">{{ $product->units }} units · {{ peso($product->sales) }}</div>
                    </div>
                    <div class="w-12">
                        <div class="h-1.5 w-full overflow-hidden rounded-full" style="background:#F0EEE9;">
                            <div class="h-full rounded-full" style="background:#16697A;width:{{ $topProducts->max('units') ? round($product->units / $topProducts->max('units') * 100) : 0 }}%"></div>
                        </div>
                    </div>
                </div>
            @empty
                <p class="py-6 text-center text-xs text-ink-light">No sales yet.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    Chart.defaults.font.family = "'Segoe UI', sans-serif";
    Chart.defaults.color = '#6E6E73';

    // Sales line chart
    new Chart(document.getElementById('salesChart'), {
        type: 'line',
        data: {
            labels: {!! json_encode($salesSeries['labels']) !!},
            datasets: [{
                label: 'Sales',
                data: {!! json_encode($salesSeries['values']) !!},
                borderColor: '#16697A',
                backgroundColor: 'rgba(22,105,122,0.10)',
                fill: true,
                tension: 0.35,
                pointRadius: 3,
                pointBackgroundColor: '#16697A',
                borderWidth: 2.5,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: (ctx) => ' ₱' + Number(ctx.parsed.y).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})
                    }
                },
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: (v) => '₱' + v }
                },
                x: { grid: { display: false } }
            }
        }
    });

    // Orders by status donut
    const statusColors = {
        pending: '#F59E0B',
        confirmed: '#0EA5E9',
        processing: '#3B82F6',
        ready_for_delivery: '#8B5CF6',
        out_for_delivery: '#F97316',
        picked_up: '#06B6D4',
        delivered: '#10B981',
        completed: '#10B981',
        cancelled: '#EF4444',
        refunded: '#EC4899',
        failed: '#6B7280'
    };
    const rawStatuses = {!! json_encode(array_keys($statusCounts)) !!};
    const labels = {!! json_encode(array_map('order_status_label', array_keys($statusCounts))) !!};
    const values = {!! json_encode(array_values($statusCounts)) !!};
    const colors = rawStatuses.map(s => statusColors[s] || '#94A3B8');

    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: colors,
                borderWidth: 2,
                borderColor: '#fff',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: { display: false }
            }
        }
    });
});
</script>
@endpush