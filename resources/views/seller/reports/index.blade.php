@extends('layouts.seller')

@section('title', 'Reports & Analytics')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4">
    <div>
        <h2 class="text-xl font-extrabold tracking-tight">Sales &amp; Performance Reports</h2>
        <p class="mt-0.5 text-sm text-ink-light">Financial and profit summary for your store.</p>
    </div>
    <a href="{{ route('seller.reports', ['from' => $from, 'to' => $to, 'export' => 1]) }}"
       class="btn btn-outline">
        <x-icon name="download" class="h-4 w-4" /> Export CSV
    </a>
</div>

{{-- Date range filter --}}
<div class="card mt-6 p-4">
    <form method="GET" action="{{ route('seller.reports') }}" class="flex flex-wrap items-end gap-3">
        <div>
            <label class="field-label">From</label>
            <input type="date" name="from" value="{{ $from }}" class="input" required>
        </div>
        <div>
            <label class="field-label">To</label>
            <input type="date" name="to" value="{{ $to }}" class="input" required>
        </div>
        <div class="flex gap-1 pb-1">
            <button type="submit" class="btn btn-primary">Generate Report</button>
            <a href="{{ route('seller.reports', ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString()]) }}" class="btn btn-soft">This month</a>
            <a href="{{ route('seller.reports', ['from' => now()->startOfWeek()->toDateString(), 'to' => now()->toDateString()]) }}" class="btn btn-soft">This week</a>
        </div>
    </form>
</div>

{{-- Summary cards --}}
<div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-4">
    <div class="stat-card">
        <div class="flex items-center gap-3">
            <div class="stat-icon" style="background:#E6F4EC;color:#2E8B57;"><x-icon name="dollar" class="h-5 w-5"/></div>
            <div>
                <div class="text-[11px] font-bold uppercase tracking-wider text-ink-light">Revenue</div>
                <div class="text-lg font-extrabold">{{ peso($summary['revenue']) }}</div>
            </div>
        </div>
        <div class="mt-2 text-[11px] text-ink-light">{{ $summary['units_sold'] }} units sold</div>
    </div>
    <div class="stat-card">
        <div class="flex items-center gap-3">
            <div class="stat-icon" style="background:#E3F0FA;color:#1B6CA8;"><x-icon name="layers" class="h-5 w-5"/></div>
            <div>
                <div class="text-[11px] font-bold uppercase tracking-wider text-ink-light">Cost of Goods</div>
                <div class="text-lg font-extrabold">{{ peso($summary['cogs']) }}</div>
            </div>
        </div>
        <div class="mt-2 text-[11px] text-ink-light">Based on product cost</div>
    </div>
    <div class="stat-card">
        <div class="flex items-center gap-3">
            <div class="stat-icon" style="background:#EAF4F3;color:#16697A;"><x-icon name="trending-up" class="h-5 w-5"/></div>
            <div>
                <div class="text-[11px] font-bold uppercase tracking-wider text-ink-light">Profit</div>
                <div class="text-lg font-extrabold">{{ peso($summary['profit']) }}</div>
            </div>
        </div>
        <div class="mt-2 text-[11px] text-ink-light">Margin: <span class="font-semibold text-successc">{{ $summary['profit_margin'] }}%</span></div>
    </div>
    <div class="stat-card">
        <div class="flex items-center gap-3">
            <div class="stat-icon" style="background:#FFF3E0;color:#B26A00;"><x-icon name="shopping-bag" class="h-5 w-5"/></div>
            <div>
                <div class="text-[11px] font-bold uppercase tracking-wider text-ink-light">Orders</div>
                <div class="text-lg font-extrabold">{{ $summary['orders'] }}</div>
            </div>
        </div>
        <div class="mt-2 text-[11px] text-ink-light">Avg: <span class="font-semibold">{{ peso($summary['avg_order_value']) }}</span> · Cancel {{ $summary['cancellation_rate'] }}%</div>
    </div>
</div>

{{-- Chart + category breakdown --}}
<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
    <div class="card p-6 xl:col-span-2">
        <h3 class="mb-4 text-base font-bold">Sales Trend</h3>
        <div class="h-72"><canvas id="reportChart"></canvas></div>
    </div>
    <div class="card p-6">
        <h3 class="mb-4 text-base font-bold">Sales by Category</h3>
        <div class="space-y-4">
            @php $maxCatSales = $categoryBreakdown->max('sales') ?: 1; @endphp
            @forelse($categoryBreakdown as $category)
                <div class="flex items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-semibold">{{ $category->name }}</div>
                        <div class="text-xs text-ink-light">{{ $category->products }} products · {{ peso($category->sales) }}</div>
                    </div>
                    <div class="w-20">
                        <div class="h-1.5 w-full overflow-hidden rounded-full" style="background:#F0EEE9;">
                            <div class="h-full rounded-full" style="background:#16697A;width:{{ round($category->sales / $maxCatSales * 100) }}%"></div>
                        </div>
                    </div>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-ink-light">No categories with products yet.</p>
            @endforelse
        </div>
    </div>
</div>

{{-- Product performance table --}}
<div class="card mt-6 overflow-hidden">
    <div class="border-b border-borderline px-6 py-4">
        <h3 class="text-base font-bold">Product Performance</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th class="text-center">Units Sold</th>
                    <th class="text-right">Sales</th>
                    <th class="text-right">COGS</th>
                    <th class="text-right">Profit</th>
                    <th class="text-right">Margin</th>
                </tr>
            </thead>
            <tbody>
                @forelse($productRows as $row)
                    <tr>
                        <td class="font-semibold">{{ $row->product_name }}</td>
                        <td class="text-center font-semibold">{{ $row->units }}</td>
                        <td class="text-right">{{ peso($row->sales) }}</td>
                        <td class="text-right text-ink-light">{{ peso($row->cogs) }}</td>
                        <td class="text-right font-bold text-successc">{{ peso($row->profit) }}</td>
                        <td class="text-right">
                            <span class="badge badge-delivered">{{ $row->sales > 0 ? round($row->profit / $row->sales * 100) : 0 }}%</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-12 text-center text-ink-light">No delivered sales in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    Chart.defaults.font.family = "'Segoe UI', sans-serif";
    Chart.defaults.color = '#6E6E73';
    new Chart(document.getElementById('reportChart'), {
        type: 'bar',
        data: {
            labels: {!! json_encode($labels) !!},
            datasets: [{
                label: 'Sales',
                data: {!! json_encode($sales) !!},
                backgroundColor: 'rgba(22,105,122,0.55)',
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: (ctx) => ' ₱' + Number(ctx.parsed.y).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) } }
            },
            scales: {
                y: { beginAtZero: true, ticks: { callback: (v) => '₱' + v } },
                x: { grid: { display: false } }
            }
        }
    });
});
</script>
@endpush