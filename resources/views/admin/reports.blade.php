@extends('admin.layout')

@section('content')
@include('admin.partials.list-ui-styles')
@php
use App\Support\Money;
$queryString = http_build_query(array_filter(['type' => $type, 'period' => $period, 'from' => request('from'), 'to' => request('to')]));
@endphp

<link rel="stylesheet" href="{{ asset('assets/admin/css/reports.css') }}">

<div class="admin-list admin-reports">
    <header class="admin-list-head">
        <h1>Reports &amp; Analytics</h1>
    </header>

    @php $tabIcons = ['sales' => 'chart', 'customers' => 'users', 'sellers' => 'store', 'products' => 'box', 'financial' => 'money']; $tabLabels = ['sales' => 'Sales', 'customers' => 'Customers', 'sellers' => 'Sellers', 'products' => 'Products', 'financial' => 'Financial']; @endphp
    <nav class="admin-list-filters report-tabs" aria-label="Report types">
        @foreach ($types as $t)
        <a href="{{ url('/admin/reports?type='.$t.($t === 'sales' ? '&period='.$period : '')) }}"
           class="admin-list-filter {{ $type === $t ? 'is-active' : '' }}" {{ $type === $t ? 'aria-current=page' : '' }}>
            <x-ui-icon :name="$tabIcons[$t] ?? 'box'" :size="16" aria-hidden="true" /> <span>{{ $tabLabels[$t] ?? ucfirst($t) }}</span>
        </a>
        @endforeach
    </nav>

    <form method="GET" action="{{ url('/admin/reports') }}" class="report-filter">
        <input type="hidden" name="type" value="{{ $type }}" />
        <div class="report-fields {{ $type === 'sales' ? '' : 'report-fields--dates-only' }}">
            @if($type === 'sales')
            <div class="report-field">
                <label for="report-period">Group By</label>
                <select id="report-period" name="period" class="report-control" onchange="this.form.submit()">
                    @foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'yearly' => 'Yearly'] as $k => $label)
                    <option value="{{ $k }}" {{ $period === $k ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="report-field">
                <label for="report-from">From</label>
                <input id="report-from" type="date" name="from" value="{{ request('from') }}" class="report-control" />
            </div>
            <div class="report-field">
                <label for="report-to">To</label>
                <input id="report-to" type="date" name="to" value="{{ request('to') }}" class="report-control" />
            </div>
        </div>
        <div class="report-actions">
            <button type="submit" class="btn btn-primary report-primary">Update Report</button>
            <a href="{{ url('/admin/reports?type='.$type.($type==='sales'?'&period='.$period:'')) }}" class="btn report-reset">Reset</a>
        </div>
        <div class="report-exports">
            <a href="{{ url('/admin/reports/export?'.$queryString.'&format=excel') }}" class="btn btn-neutral report-export"><x-ui-icon name="download" :size="15" aria-hidden="true" /> <span>Excel</span></a>
            <a href="{{ url('/admin/reports/print?'.$queryString) }}" target="_blank" class="btn btn-secondary report-export"><x-ui-icon name="printer" :size="15" aria-hidden="true" /> <span>PDF</span></a>
        </div>
    </form>

    <div class="report-metrics" role="group" aria-label="Report summary">
        @foreach ($cards as $label => $value)
        <div class="report-metric {{ $label === 'Best Store' ? 'report-metric--text' : '' }}">
            <span>{{ $label }}</span>
            <strong>{{ $value }}</strong>
        </div>
        @endforeach
    </div>

    @if ($type === 'sales')
        <section class="report-section" aria-labelledby="sales-period-title">
            <h2 id="sales-period-title" class="report-section-title">Sales by {{ ucfirst($period) }}</h2>
            <div class="report-table-wrap">
                <table class="admin-list-table report-table report-table--sales-period" role="table" aria-labelledby="sales-period-title">
                    <thead role="rowgroup">
                        <tr role="row">
                            <th scope="col" role="columnheader">Period</th>
                            <th scope="col" role="columnheader" class="report-cell--numeric">Paid Orders</th>
                            <th scope="col" role="columnheader" class="report-cell--numeric">Sales</th>
                            <th scope="col" role="columnheader" class="report-cell--numeric">Share</th>
                        </tr>
                    </thead>
                    <tbody role="rowgroup">
                        @forelse($grouped as $g)
                        <tr class="report-row" role="row">
                            <td role="cell" data-label="Period" class="report-cell--label">{{ $g->label }}</td>
                            <td role="cell" data-label="Paid Orders" class="report-cell--secondary report-cell--orders">{{ $g->orders }}</td>
                            <td role="cell" data-label="Sales" class="report-cell--numeric report-cell--value report-cell--primary">{{ Money::format($g->sales) }}</td>
                            <td role="cell" data-label="Share" class="report-cell--numeric report-cell--muted report-cell--secondary report-cell--share">{{ $g->sales > 0 ? number_format(($g->sales / max(1, $grouped->sum('sales'))) * 100, 1) : 0 }}%</td>
                        </tr>
                        @empty
                        <tr class="report-empty-row" role="row"><td role="cell" colspan="4"><span class="report-empty"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16v16H4zM8 8h8M8 12h8M8 16h5"/></svg>No sales data for this range.</span></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div class="report-split">
            <section class="report-section" aria-labelledby="sales-category-title">
                <h2 id="sales-category-title" class="report-section-title">Sales by Category</h2>
                <div class="report-table-wrap">
                    <table class="admin-list-table report-table report-table--category" role="table" aria-labelledby="sales-category-title">
                        <thead role="rowgroup"><tr role="row"><th scope="col" role="columnheader">Category</th><th scope="col" role="columnheader" class="report-cell--numeric">Units</th><th scope="col" role="columnheader" class="report-cell--numeric">Sales</th></tr></thead>
                        <tbody role="rowgroup">
                            @forelse($byCategory as $c)
                            <tr class="report-row" role="row">
                                <td role="cell" data-label="Category" class="report-cell--label">{{ $c->name }}</td>
                                <td role="cell" data-label="Units" class="report-cell--secondary report-cell--units">{{ $c->units }}</td>
                                <td role="cell" data-label="Sales" class="report-cell--numeric report-cell--value report-cell--primary">{{ Money::format($c->sales) }}</td>
                            </tr>
                            @empty
                            <tr class="report-empty-row" role="row"><td role="cell" colspan="3"><span class="report-empty"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16v16H4zM8 8h8M8 12h8M8 16h5"/></svg>No data.</span></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="report-section" aria-labelledby="sales-seller-title">
                <h2 id="sales-seller-title" class="report-section-title">Sales by Seller</h2>
                <div class="report-table-wrap">
                    <table class="admin-list-table report-table report-table--seller" role="table" aria-labelledby="sales-seller-title">
                        <thead role="rowgroup"><tr role="row"><th scope="col" role="columnheader">Store</th><th scope="col" role="columnheader" class="report-cell--numeric">Orders</th><th scope="col" role="columnheader" class="report-cell--numeric">Sales</th><th scope="col" role="columnheader" class="report-cell--numeric">Commission</th></tr></thead>
                        <tbody role="rowgroup">
                            @forelse($bySeller as $s)
                            <tr class="report-row" role="row">
                                <td role="cell" data-label="Store" class="report-cell--label">{{ $s->store_name }}</td>
                                <td role="cell" data-label="Orders" class="report-cell--secondary report-cell--orders">{{ $s->orders }}</td>
                                <td role="cell" data-label="Sales" class="report-cell--numeric report-cell--value report-cell--primary">{{ Money::format($s->sales) }}</td>
                                <td role="cell" data-label="Commission" class="report-cell--numeric report-cell--muted report-cell--secondary report-cell--commission">{{ Money::format($s->commission) }}</td>
                            </tr>
                            @empty
                            <tr class="report-empty-row" role="row"><td role="cell" colspan="4"><span class="report-empty"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16v16H4zM8 8h8M8 12h8M8 16h5"/></svg>No data.</span></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <section class="report-section" aria-labelledby="top-products-title">
            <h2 id="top-products-title" class="report-section-title">Top Products by Sales</h2>
            <div class="report-table-wrap">
                <table class="admin-list-table report-table report-table--product-sales" role="table" aria-labelledby="top-products-title">
                    <thead role="rowgroup"><tr role="row"><th scope="col" role="columnheader">Product</th><th scope="col" role="columnheader" class="report-cell--numeric">Units</th><th scope="col" role="columnheader" class="report-cell--numeric">Sales</th></tr></thead>
                    <tbody role="rowgroup">
                        @forelse($byProduct as $p)
                        <tr class="report-row" role="row">
                            <td role="cell" data-label="Product" class="report-cell--label">{{ $p->name }}</td>
                            <td role="cell" data-label="Units" class="report-cell--secondary report-cell--units">{{ $p->units }}</td>
                            <td role="cell" data-label="Sales" class="report-cell--numeric report-cell--value report-cell--primary">{{ Money::format($p->sales) }}</td>
                        </tr>
                        @empty
                        <tr class="report-empty-row" role="row"><td role="cell" colspan="3"><span class="report-empty"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16v16H4zM8 8h8M8 12h8M8 16h5"/></svg>No data.</span></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

    @elseif ($type === 'customers')
        <section class="report-section" aria-labelledby="customer-activity-title">
            <h2 id="customer-activity-title" class="report-section-title">Customer Activity &amp; Spending</h2>
            <div class="report-table-wrap">
                <table class="admin-list-table report-table report-table--customers" role="table" aria-labelledby="customer-activity-title">
                    <thead role="rowgroup"><tr role="row">
                        <th scope="col" role="columnheader">Customer</th><th scope="col" role="columnheader">Email</th><th scope="col" role="columnheader" class="report-cell--numeric">Paid Orders</th><th scope="col" role="columnheader" class="report-cell--numeric">Total Spent</th><th scope="col" role="columnheader" class="report-cell--numeric">Avg. Order</th><th scope="col" role="columnheader">Last Order</th>
                    </tr></thead>
                    <tbody role="rowgroup">
                        @forelse($customers as $u)
                        <tr class="report-row" role="row">
                            <td role="cell" data-label="Customer" class="report-cell--label">{{ $u->name }}</td>
                            <td role="cell" data-label="Email" class="report-cell--muted report-cell--email">{{ $u->email }}</td>
                            <td role="cell" data-label="Paid Orders" class="report-cell--secondary report-cell--orders">{{ $u->orders_count }}</td>
                            <td role="cell" data-label="Total Spent" class="report-cell--numeric report-cell--value report-cell--primary">{{ Money::format($u->total_spent) }}</td>
                            <td role="cell" data-label="Avg. Order" class="report-cell--numeric report-cell--muted report-cell--secondary report-cell--average">{{ Money::format($u->avg_order) }}</td>
                            <td role="cell" data-label="Last Order" class="report-cell--muted report-cell--secondary report-cell--last">{{ optional($u->last_order)->format('M d, Y') ?? '—' }}</td>
                        </tr>
                        @empty
                        <tr class="report-empty-row" role="row"><td role="cell" colspan="6"><span class="report-empty"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16v16H4zM8 8h8M8 12h8M8 16h5"/></svg>No customers yet.</span></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

    @elseif ($type === 'sellers')
        <section class="report-section" aria-labelledby="seller-performance-title">
            <h2 id="seller-performance-title" class="report-section-title">Seller Revenue &amp; Performance</h2>
            <div class="report-table-wrap">
                <table class="admin-list-table report-table report-table--sellers" role="table" aria-labelledby="seller-performance-title">
                    <thead role="rowgroup"><tr role="row">
                        <th scope="col" role="columnheader">Store</th><th scope="col" role="columnheader" class="report-cell--numeric">Products</th><th scope="col" role="columnheader" class="report-cell--numeric">Paid Orders</th><th scope="col" role="columnheader" class="report-cell--numeric">Revenue</th><th scope="col" role="columnheader" class="report-cell--numeric">Commission</th><th scope="col" role="columnheader" class="report-cell--numeric">Avg. Order</th><th scope="col" role="columnheader">Performance</th>
                    </tr></thead>
                    <tbody role="rowgroup">
                        @forelse($sellers as $s)
                        @php $maxRev = $sellersMaxRevenue ?? 1; @endphp
                        <tr class="report-row" role="row">
                            <td role="cell" data-label="Store" class="report-cell--label">{{ $s->store_name }}</td>
                            <td role="cell" data-label="Products" class="report-cell--secondary report-cell--products">{{ $s->products_count }}</td>
                            <td role="cell" data-label="Paid Orders" class="report-cell--secondary report-cell--orders">{{ $s->paid_orders }}</td>
                            <td role="cell" data-label="Revenue" class="report-cell--numeric report-cell--value report-cell--primary">{{ Money::format($s->revenue) }}</td>
                            <td role="cell" data-label="Commission" class="report-cell--numeric report-cell--muted report-cell--secondary report-cell--commission">{{ Money::format($s->commission) }}</td>
                            <td role="cell" data-label="Avg. Order" class="report-cell--numeric report-cell--muted report-cell--secondary report-cell--average">{{ Money::format($s->avg_order) }}</td>
                            <td role="cell" data-label="Performance" class="report-cell--performance"><div class="report-performance"><span style="width:{{ round(($s->revenue / $maxRev) * 100) }}%;"></span></div></td>
                        </tr>
                        @empty
                        <tr class="report-empty-row" role="row"><td role="cell" colspan="7"><span class="report-empty"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16v16H4zM8 8h8M8 12h8M8 16h5"/></svg>No sellers yet.</span></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

    @elseif ($type === 'products')
        <section class="report-section" aria-labelledby="best-products-title">
            <h2 id="best-products-title" class="report-section-title report-product-heading"><x-ui-icon name="trophy" :size="18" aria-hidden="true" /> Best-Selling Products</h2>
            <div class="report-table-wrap">
                <table class="admin-list-table report-table report-table--product-sales" role="table" aria-labelledby="best-products-title">
                    <thead role="rowgroup"><tr role="row"><th scope="col" role="columnheader">Product</th><th scope="col" role="columnheader" class="report-cell--numeric">Units Sold</th><th scope="col" role="columnheader" class="report-cell--numeric">Sales</th></tr></thead>
                    <tbody role="rowgroup">
                        @forelse($best as $p)
                        <tr class="report-row" role="row">
                            <td role="cell" data-label="Product" class="report-cell--label">{{ $p->name }}</td>
                            <td role="cell" data-label="Units Sold" class="report-cell--secondary report-cell--units">{{ $p->units }}</td>
                            <td role="cell" data-label="Sales" class="report-cell--numeric report-cell--value report-cell--primary">{{ Money::format($p->sales) }}</td>
                        </tr>
                        @empty
                        <tr class="report-empty-row" role="row"><td role="cell" colspan="3"><span class="report-empty"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16v16H4zM8 8h8M8 12h8M8 16h5"/></svg>No sales yet.</span></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="report-section" aria-labelledby="low-products-title">
            <h2 id="low-products-title" class="report-section-title report-product-heading"><x-ui-icon name="trending-down" :size="18" aria-hidden="true" /> Low-Selling Products (≤2 units or unsold)</h2>
            <div class="report-table-wrap">
                <table class="admin-list-table report-table report-table--low-products" role="table" aria-labelledby="low-products-title">
                    <thead role="rowgroup"><tr role="row"><th scope="col" role="columnheader">Product</th><th scope="col" role="columnheader" class="report-cell--numeric">Views</th><th scope="col" role="columnheader" class="report-cell--numeric">Cart Additions</th></tr></thead>
                    <tbody role="rowgroup">
                        @forelse($low as $p)
                        <tr class="report-row" role="row">
                            <td role="cell" data-label="Product" class="report-cell--label">{{ $p->name }}</td>
                            <td role="cell" data-label="Views" class="report-cell--secondary report-cell--views">{{ number_format($p->views_count) }}</td>
                            <td role="cell" data-label="Cart Additions" class="report-cell--numeric report-cell--muted report-cell--secondary report-cell--carts">{{ number_format($p->cart_additions) }}</td>
                        </tr>
                        @empty
                        <tr class="report-empty-row" role="row"><td role="cell" colspan="3"><span class="report-empty"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16v16H4zM8 8h8M8 12h8M8 16h5"/></svg>All products are selling well.</span></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="report-section" aria-labelledby="stock-status-title">
            <h2 id="stock-status-title" class="report-section-title">Stock Status</h2>
            <div class="report-table-wrap">
                <table class="admin-list-table report-table report-table--stock" role="table" aria-labelledby="stock-status-title">
                    <thead role="rowgroup"><tr role="row"><th scope="col" role="columnheader">Product</th><th scope="col" role="columnheader" class="report-cell--numeric">Stock</th><th scope="col" role="columnheader">Status</th></tr></thead>
                    <tbody role="rowgroup">
                        @forelse($stock as $p)
                        <tr class="report-row" role="row">
                            <td role="cell" data-label="Product" class="report-cell--label">{{ $p->name }}</td>
                            <td role="cell" data-label="Stock" class="report-cell--numeric report-cell--primary">{{ $p->stock }}</td>
                            <td role="cell" data-label="Status" class="report-cell--secondary report-cell--stock"><span class="report-stock-status">{{ $p->stock_status }}</span></td>
                        </tr>
                        @empty
                        <tr class="report-empty-row" role="row"><td role="cell" colspan="3"><span class="report-empty"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16v16H4zM8 8h8M8 12h8M8 16h5"/></svg>No products.</span></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

    @else
        <section class="report-section" aria-labelledby="financial-breakdown-title">
            <h2 id="financial-breakdown-title" class="report-section-title">Financial Breakdown by Month</h2>
            <div class="report-table-wrap">
                <table class="admin-list-table report-table report-table--financial" role="table" aria-labelledby="financial-breakdown-title">
                    <thead role="rowgroup"><tr role="row">
                        <th scope="col" role="columnheader">Period</th><th scope="col" role="columnheader" class="report-cell--numeric">Paid Orders</th><th scope="col" role="columnheader" class="report-cell--numeric">Gross Sales</th><th scope="col" role="columnheader" class="report-cell--numeric">Commission</th><th scope="col" role="columnheader" class="report-cell--numeric">Delivery Fees</th>
                    </tr></thead>
                    <tbody role="rowgroup">
                        @forelse($breakdown as $b)
                        <tr class="report-row" role="row">
                            <td role="cell" data-label="Period" class="report-cell--label">{{ $b->label }}</td>
                            <td role="cell" data-label="Paid Orders" class="report-cell--secondary report-cell--orders">{{ $b->orders }}</td>
                            <td role="cell" data-label="Gross Sales" class="report-cell--numeric report-cell--value report-cell--primary">{{ Money::format($b->gross) }}</td>
                            <td role="cell" data-label="Commission" class="report-cell--numeric report-cell--muted report-cell--secondary report-cell--commission">{{ Money::format($b->commission) }}</td>
                            <td role="cell" data-label="Delivery Fees" class="report-cell--numeric report-cell--muted report-cell--secondary report-cell--delivery">{{ Money::format($b->delivery) }}</td>
                        </tr>
                        @empty
                        <tr class="report-empty-row" role="row"><td role="cell" colspan="5"><span class="report-empty"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16v16H4zM8 8h8M8 12h8M8 16h5"/></svg>No financial data for this range.</span></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
@endsection
