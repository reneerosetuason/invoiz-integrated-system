@extends('admin.layout')

@section('content')
@php
use App\Support\Money;
$queryString = http_build_query(array_filter(['type' => $type, 'period' => $period, 'from' => request('from'), 'to' => request('to')]));
@endphp
<div class="card">
    <h1 class="page-title">Reports & Analytics</h1>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px;">
        @php $tabIcons = ['sales' => 'chart', 'customers' => 'users', 'sellers' => 'store', 'products' => 'box', 'financial' => 'money']; $tabLabels = ['sales' => 'Sales', 'customers' => 'Customers', 'sellers' => 'Sellers', 'products' => 'Products', 'financial' => 'Financial']; @endphp
        @foreach ($types as $t)
        <a href="{{ url('/admin/reports?type='.$t.($t === 'sales' ? '&period='.$period : '')) }}"
           style="padding:8px 18px;border-radius:999px;font-size:13px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:6px;{{ $type === $t ? 'background:#16697A;color:#fff;' : 'background:#F0EEE9;color:#374151;' }}">
            <x-ui-icon :name="$tabIcons[$t] ?? 'box'" :size="14" /> {{ $tabLabels[$t] ?? ucfirst($t) }}
        </a>
        @endforeach
    </div>

    <form method="GET" action="{{ url('/admin/reports') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-bottom:20px;background:#F7F6F2;padding:14px 16px;border-radius:12px;">
        <input type="hidden" name="type" value="{{ $type }}" />
        @if($type === 'sales')
        <div>
            <label style="display:block;font-size:11px;font-weight:700;color:#6E6E73;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Group By</label>
            <select name="period" onchange="this.form.submit()" style="min-width:130px;">
                @foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'yearly' => 'Yearly'] as $k => $label)
                <option value="{{ $k }}" {{ $period === $k ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div>
            <label style="display:block;font-size:11px;font-weight:700;color:#6E6E73;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">From</label>
            <input type="date" name="from" value="{{ request('from') }}" />
        </div>
        <div>
            <label style="display:block;font-size:11px;font-weight:700;color:#6E6E73;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">To</label>
            <input type="date" name="to" value="{{ request('to') }}" />
        </div>
        <button type="submit" class="btn btn-primary" style="min-height:42px;">Update Report</button>
        <a href="{{ url('/admin/reports?type='.$type.($type==='sales'?'&period='.$period:'')) }}" class="btn btn-neutral" style="min-height:42px;">Reset</a>
        @if($type === 'sales')
        <div style="flex-basis:100%; display:flex; gap:8px; margin-top:2px;">
            <div style="width:130px; display:flex; gap:8px;">
                <a href="{{ url('/admin/reports/export?'.$queryString.'&format=excel') }}" class="btn btn-neutral" style="min-height:36px; font-size:12.5px; padding:8px 14px; flex:1; justify-content:center;"><x-ui-icon name="download" :size="14" /> Excel</a>
                <a href="{{ url('/admin/reports/print?'.$queryString) }}" target="_blank" class="btn btn-secondary" style="min-height:36px; font-size:12.5px; padding:8px 14px; flex:1; justify-content:center;"><x-ui-icon name="printer" :size="14" /> PDF</a>
            </div>
        </div>
        @endif
        @if($type !== 'sales')
        <div style="display:flex; gap:8px; margin-left:auto;">
            <a href="{{ url('/admin/reports/export?'.$queryString.'&format=excel') }}" class="btn btn-neutral" style="min-height:42px;"><x-ui-icon name="download" :size="15" /> Excel</a>
            <a href="{{ url('/admin/reports/print?'.$queryString) }}" target="_blank" class="btn btn-secondary" style="min-height:42px;"><x-ui-icon name="printer" :size="15" /> PDF</a>
        </div>
        @endif
    </form>

    <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));margin-bottom:24px;">
        @foreach ($cards as $label => $value)
        <div class="stat-card">
            <span>{{ $label }}</span>
            <strong>{{ $value }}</strong>
        </div>
        @endforeach
    </div>

    @if ($type === 'sales')
        <h2 style="font-size:17px;margin-bottom:12px;">Sales by {{ ucfirst($period) }}</h2>
        <table style="width:100%;border-collapse:collapse;margin-bottom:28px;">
            <thead>
                <tr style="border-bottom:2px solid #E8E6E0;text-align:left;">
                    <th style="padding:11px 8px;">Period</th>
                    <th style="padding:11px 8px;">Paid Orders</th>
                    <th style="padding:11px 8px;">Sales</th>
                    <th style="padding:11px 8px;">Share</th>
                </tr>
            </thead>
            <tbody>
                @forelse($grouped as $g)
                <tr style="border-bottom:1px solid #F7F6F2;">
                    <td style="padding:12px 8px;font-weight:600;">{{ $g->label }}</td>
                    <td style="padding:12px 8px;">{{ $g->orders }}</td>
                    <td style="padding:12px 8px;font-weight:600;">{{ Money::format($g->sales) }}</td>
                    <td style="padding:12px 8px;color:#6E6E73;">{{ $g->sales > 0 ? number_format(($g->sales / max(1, $grouped->sum('sales'))) * 100, 1) : 0 }}%</td>
                </tr>
                @empty
                <tr><td colspan="4" style="padding:20px;text-align:center;color:#6E6E73;">No sales data for this range.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div style="display:grid;gap:24px;grid-template-columns:1fr 1fr;">
            <div>
                <h2 style="font-size:17px;margin-bottom:12px;">Sales by Category</h2>
                <table style="width:100%;border-collapse:collapse;">
                    <thead><tr style="border-bottom:2px solid #E8E6E0;text-align:left;"><th style="padding:11px 8px;">Category</th><th style="padding:11px 8px;">Units</th><th style="padding:11px 8px;">Sales</th></tr></thead>
                    <tbody>
                        @forelse($byCategory as $c)
                        <tr style="border-bottom:1px solid #F7F6F2;">
                            <td style="padding:12px 8px;font-weight:600;">{{ $c->name }}</td>
                            <td style="padding:12px 8px;">{{ $c->units }}</td>
                            <td style="padding:12px 8px;font-weight:600;">{{ Money::format($c->sales) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" style="padding:20px;text-align:center;color:#6E6E73;">No data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div>
                <h2 style="font-size:17px;margin-bottom:12px;">Sales by Seller</h2>
                <table style="width:100%;border-collapse:collapse;">
                    <thead><tr style="border-bottom:2px solid #E8E6E0;text-align:left;"><th style="padding:11px 8px;">Store</th><th style="padding:11px 8px;">Orders</th><th style="padding:11px 8px;">Sales</th><th style="padding:11px 8px;">Commission</th></tr></thead>
                    <tbody>
                        @forelse($bySeller as $s)
                        <tr style="border-bottom:1px solid #F7F6F2;">
                            <td style="padding:12px 8px;font-weight:600;">{{ $s->store_name }}</td>
                            <td style="padding:12px 8px;">{{ $s->orders }}</td>
                            <td style="padding:12px 8px;">{{ Money::format($s->sales) }}</td>
                            <td style="padding:12px 8px;color:#16697A;">{{ Money::format($s->commission) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" style="padding:20px;text-align:center;color:#6E6E73;">No data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <h2 style="font-size:17px;margin:28px 0 12px;">Top Products by Sales</h2>
        <table style="width:100%;border-collapse:collapse;">
            <thead><tr style="border-bottom:2px solid #E8E6E0;text-align:left;"><th style="padding:11px 8px;">Product</th><th style="padding:11px 8px;">Units</th><th style="padding:11px 8px;">Sales</th></tr></thead>
            <tbody>
                @forelse($byProduct as $p)
                <tr style="border-bottom:1px solid #F7F6F2;">
                    <td style="padding:12px 8px;font-weight:600;">{{ $p->name }}</td>
                    <td style="padding:12px 8px;">{{ $p->units }}</td>
                    <td style="padding:12px 8px;">{{ Money::format($p->sales) }}</td>
                </tr>
                @empty
                <tr><td colspan="3" style="padding:20px;text-align:center;color:#6E6E73;">No data.</td></tr>
                @endforelse
            </tbody>
        </table>

    @elseif ($type === 'customers')
        <h2 style="font-size:17px;margin-bottom:12px;">Customer Activity & Spending</h2>
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="border-bottom:2px solid #E8E6E0;text-align:left;">
                    <th style="padding:11px 8px;">Customer</th>
                    <th style="padding:11px 8px;">Email</th>
                    <th style="padding:11px 8px;">Paid Orders</th>
                    <th style="padding:11px 8px;">Total Spent</th>
                    <th style="padding:11px 8px;">Avg. Order</th>
                    <th style="padding:11px 8px;">Last Order</th>
                </tr>
            </thead>
            <tbody>
                @forelse($customers as $u)
                <tr style="border-bottom:1px solid #F7F6F2;">
                    <td style="padding:12px 8px;font-weight:600;">{{ $u->name }}</td>
                    <td style="padding:12px 8px;color:#6E6E73;">{{ $u->email }}</td>
                    <td style="padding:12px 8px;">{{ $u->orders_count }}</td>
                    <td style="padding:12px 8px;font-weight:600;">{{ Money::format($u->total_spent) }}</td>
                    <td style="padding:12px 8px;">{{ Money::format($u->avg_order) }}</td>
                    <td style="padding:12px 8px;color:#6E6E73;font-size:13px;">{{ optional($u->last_order)->format('M d, Y') ?? '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="6" style="padding:20px;text-align:center;color:#6E6E73;">No customers yet.</td></tr>
                @endforelse
            </tbody>
        </table>

    @elseif ($type === 'sellers')
        <h2 style="font-size:17px;margin-bottom:12px;">Seller Revenue & Performance</h2>
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="border-bottom:2px solid #E8E6E0;text-align:left;">
                    <th style="padding:11px 8px;">Store</th>
                    <th style="padding:11px 8px;">Products</th>
                    <th style="padding:11px 8px;">Paid Orders</th>
                    <th style="padding:11px 8px;">Revenue</th>
                    <th style="padding:11px 8px;">Commission</th>
                    <th style="padding:11px 8px;">Avg. Order</th>
                    <th style="padding:11px 8px;">Performance</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sellers as $s)
                @php $maxRev = $sellers->max('revenue') ?: 1; @endphp
                <tr style="border-bottom:1px solid #F7F6F2;">
                    <td style="padding:12px 8px;font-weight:600;">{{ $s->store_name }}</td>
                    <td style="padding:12px 8px;">{{ $s->products_count }}</td>
                    <td style="padding:12px 8px;">{{ $s->paid_orders }}</td>
                    <td style="padding:12px 8px;font-weight:600;">{{ Money::format($s->revenue) }}</td>
                    <td style="padding:12px 8px;color:#16697A;">{{ Money::format($s->commission) }}</td>
                    <td style="padding:12px 8px;">{{ Money::format($s->avg_order) }}</td>
                    <td style="padding:12px 8px;min-width:120px;">
                        <div style="background:#F0EEE9;border-radius:999px;height:8px;overflow:hidden;">
                            <div style="background:#16697A;height:100%;width:{{ round(($s->revenue / $maxRev) * 100) }}%;"></div>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" style="padding:20px;text-align:center;color:#6E6E73;">No sellers yet.</td></tr>
                @endforelse
            </tbody>
        </table>

    @elseif ($type === 'products')
        <h2 style="font-size:17px;margin-bottom:12px;display:flex;align-items:center;gap:8px;"><x-ui-icon name="trophy" :size="18" /> Best-Selling Products</h2>
        <table style="width:100%;border-collapse:collapse;margin-bottom:28px;">
            <thead><tr style="border-bottom:2px solid #E8E6E0;text-align:left;"><th style="padding:11px 8px;">Product</th><th style="padding:11px 8px;">Units Sold</th><th style="padding:11px 8px;">Sales</th></tr></thead>
            <tbody>
                @forelse($best as $p)
                <tr style="border-bottom:1px solid #F7F6F2;">
                    <td style="padding:12px 8px;font-weight:600;">{{ $p->name }}</td>
                    <td style="padding:12px 8px;">{{ $p->units }}</td>
                    <td style="padding:12px 8px;">{{ Money::format($p->sales) }}</td>
                </tr>
                @empty
                <tr><td colspan="3" style="padding:20px;text-align:center;color:#6E6E73;">No sales yet.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2 style="font-size:17px;margin-bottom:12px;display:flex;align-items:center;gap:8px;"><x-ui-icon name="trending-down" :size="18" /> Low-Selling Products (≤2 units or unsold)</h2>
        <table style="width:100%;border-collapse:collapse;margin-bottom:28px;">
            <thead><tr style="border-bottom:2px solid #E8E6E0;text-align:left;"><th style="padding:11px 8px;">Product</th><th style="padding:11px 8px;">Views</th><th style="padding:11px 8px;">Cart Additions</th></tr></thead>
            <tbody>
                @forelse($low as $p)
                <tr style="border-bottom:1px solid #F7F6F2;">
                    <td style="padding:12px 8px;font-weight:600;">{{ $p->name }}</td>
                    <td style="padding:12px 8px;">{{ number_format($p->views_count) }}</td>
                    <td style="padding:12px 8px;">{{ number_format($p->cart_additions) }}</td>
                </tr>
                @empty
                <tr><td colspan="3" style="padding:20px;text-align:center;color:#6E6E73;">All products are selling well.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2 style="font-size:17px;margin-bottom:12px;">Stock Status</h2>
        <table style="width:100%;border-collapse:collapse;">
            <thead><tr style="border-bottom:2px solid #E8E6E0;text-align:left;"><th style="padding:11px 8px;">Product</th><th style="padding:11px 8px;">Stock</th><th style="padding:11px 8px;">Status</th></tr></thead>
            <tbody>
                @forelse($stock as $p)
                <tr style="border-bottom:1px solid #F7F6F2;">
                    <td style="padding:12px 8px;font-weight:600;">{{ $p->name }}</td>
                    <td style="padding:12px 8px;">{{ $p->stock }}</td>
                    <td style="padding:12px 8px;">
                        <span style="padding:3px 10px;border-radius:12px;font-size:12px;background:{{ $p->stock_status === 'In Stock' ? '#E8F5EE' : ($p->stock_status === 'Low Stock' ? '#FDF3E3' : '#FCE9E4') }};color:{{ $p->stock_status === 'In Stock' ? '#166534' : ($p->stock_status === 'Low Stock' ? '#92400e' : '#991b1b') }};">{{ $p->stock_status }}</span>
                    </td>
                </tr>
                @empty
                <tr><td colspan="3" style="padding:20px;text-align:center;color:#6E6E73;">No products.</td></tr>
                @endforelse
            </tbody>
        </table>

    @else
        <h2 style="font-size:17px;margin-bottom:12px;">Financial Breakdown by Month</h2>
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="border-bottom:2px solid #E8E6E0;text-align:left;">
                    <th style="padding:11px 8px;">Period</th>
                    <th style="padding:11px 8px;">Paid Orders</th>
                    <th style="padding:11px 8px;">Gross Sales</th>
                    <th style="padding:11px 8px;">Commission</th>
                    <th style="padding:11px 8px;">Delivery Fees</th>
                </tr>
            </thead>
            <tbody>
                @forelse($breakdown as $b)
                <tr style="border-bottom:1px solid #F7F6F2;">
                    <td style="padding:12px 8px;font-weight:600;">{{ $b->label }}</td>
                    <td style="padding:12px 8px;">{{ $b->orders }}</td>
                    <td style="padding:12px 8px;">{{ Money::format($b->gross) }}</td>
                    <td style="padding:12px 8px;color:#16697A;">{{ Money::format($b->commission) }}</td>
                    <td style="padding:12px 8px;color:#92400e;">{{ Money::format($b->delivery) }}</td>
                </tr>
                @empty
                <tr><td colspan="5" style="padding:20px;text-align:center;color:#6E6E73;">No financial data for this range.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endif
</div>
@endsection
