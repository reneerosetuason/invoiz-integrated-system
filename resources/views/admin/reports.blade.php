@extends('admin.layout')

@section('content')
@include('admin.partials.list-ui-styles')
@php
use App\Support\Money;
$queryString = http_build_query(array_filter(['type' => $type, 'period' => $period, 'from' => request('from'), 'to' => request('to')]));
@endphp

<style>
  .admin-reports{--report-ink:#1B1B1E;--report-teal:#0E4A57;--report-muted:#56696B;--report-line:rgba(15,76,92,.12);max-width:1280px;margin:0 auto;color:var(--report-ink);font-family:'Inter',system-ui,-apple-system,'Segoe UI',sans-serif}
  .admin-reports .admin-list-head{margin-bottom:18px}
  .admin-reports .admin-list-head h1{font-size:28px;font-weight:560}
  .report-tabs{width:100%;gap:8px;margin:0 0 18px;overflow-x:auto;scroll-snap-type:x mandatory;scrollbar-width:none;-webkit-overflow-scrolling:touch}
  .report-tabs::-webkit-scrollbar{display:none}
  .admin-reports .report-tabs .admin-list-filter{min-height:44px;padding:8px 14px;border-radius:10px;font-size:13px;scroll-snap-align:start}
  .admin-reports .report-tabs .admin-list-filter svg,.admin-reports .report-export svg{stroke-width:1.7}
  .admin-reports .report-tabs .admin-list-filter svg{width:16px;height:16px;flex:0 0 16px}
  .admin-reports a{text-decoration-thickness:1px;text-underline-offset:3px}
  .admin-reports .report-filter{display:grid;grid-template-columns:minmax(0,1fr) auto auto;align-items:end;gap:10px 14px;margin:0 0 22px;padding:0 0 18px;background:transparent}
  .report-fields{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;min-width:0}
  .report-fields--dates-only{grid-template-columns:repeat(2,minmax(0,1fr))}
  .report-field{min-width:0}
  .admin-reports .report-field label{margin:0 0 5px;color:var(--report-muted);font-size:13px;font-weight:550;letter-spacing:0;text-transform:none}
  .admin-reports .report-control{display:block;width:100%;min-width:0;height:44px;padding:8px 11px;border:1px solid var(--report-line);border-radius:12px;background:#fff;color:var(--report-ink);font-family:inherit;font-size:14px;line-height:1.2;box-shadow:none}
  .admin-reports .report-control:focus{border-color:var(--report-teal);background:#fff;box-shadow:none}
  .report-actions,.report-exports{display:flex;align-items:center;gap:7px;min-width:0}
  .admin-reports .report-primary,.admin-reports .report-reset,.admin-reports .report-export{display:inline-flex;min-height:44px;align-items:center;justify-content:center;gap:7px;padding:9px 12px;border-radius:9px;font-family:inherit;font-size:13px;font-weight:650;line-height:1.2;white-space:nowrap;text-decoration:none;box-shadow:none}
  .admin-reports .report-primary{background:var(--report-teal);color:#fff}
  .admin-reports .report-primary:hover{background:#16697A}
  .admin-reports .report-reset{padding-inline:8px;border:0;background:transparent;color:var(--report-muted)}
  .admin-reports .report-reset:hover{color:var(--report-teal);text-decoration:underline}
  .admin-reports .report-export{border:1px solid var(--report-line);background:#fff;color:var(--report-teal)}
  .admin-reports .report-export:hover{background:#EDF3F1}
  .admin-reports .report-export svg,.admin-reports .report-primary svg{width:15px;height:15px;flex:0 0 15px}
  .admin-reports .report-primary:active,.admin-reports .report-reset:active,.admin-reports .report-export:active,.admin-reports .report-tabs .admin-list-filter:active{transform:scale(.98)}
  .admin-reports a:focus-visible,.admin-reports button:focus-visible,.admin-reports input:focus-visible,.admin-reports select:focus-visible{outline:2px solid var(--report-teal);outline-offset:2px}
  .report-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:0 0 28px;padding:0;border:0;border-radius:0;background:transparent}
  .report-metric{min-width:0;min-height:94px;padding:14px 16px;border:1px solid #D8E3E2;border-top:3px solid var(--report-teal);border-radius:14px;background:#fff;box-shadow:0 2px 8px rgba(15,76,92,.045)}
  .report-metric span{display:block;color:var(--report-muted);font-size:13px;font-weight:600;line-height:1.35}
  .report-metric strong{display:block;max-width:100%;margin-top:5px;color:var(--report-teal);font-family:'Fraunces',Georgia,serif;font-size:clamp(20px,2.25vw,30px);font-weight:560;line-height:1.16;font-variant-numeric:tabular-nums;white-space:nowrap}
  .report-metric--text strong{white-space:normal;overflow-wrap:anywhere}
  .report-section{min-width:0;margin:0 0 24px;padding:17px 18px 15px;border:1px solid #D8E3E2;border-radius:14px;background:#fff;box-shadow:0 2px 8px rgba(15,76,92,.045)}
  .report-section-title{margin:0 0 11px;color:var(--report-teal);font-family:'Fraunces',Georgia,serif;font-size:20px;font-weight:560;line-height:1.25;letter-spacing:-.015em}
  .report-split{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin:0 0 26px}
  .report-split .report-section{margin:0}
  .report-table-wrap{width:100%;min-width:0}
  .admin-reports .report-table{display:table;width:100%;min-width:0;border-collapse:collapse;table-layout:fixed}
  .admin-reports .report-table thead{position:static;width:auto;height:auto;overflow:visible;clip:auto;clip-path:none;white-space:normal}
  .admin-reports .report-table tbody{display:table-row-group;width:auto;padding:0}
  .admin-reports .report-table th{padding:11px;color:var(--report-teal);background:#EEF4F3;border-bottom:1px solid var(--report-line);font-size:12.5px;font-weight:650;letter-spacing:0;text-align:left;text-transform:none;vertical-align:bottom}
  .admin-reports .report-table td{padding:13px 11px;border-bottom:1px solid var(--report-line);color:var(--report-ink);font-size:14px;line-height:1.4;vertical-align:middle;overflow-wrap:anywhere}
  .admin-reports .report-table th:first-child,.admin-reports .report-table td:first-child{padding-left:0}
  .admin-reports .report-table th:last-child,.admin-reports .report-table td:last-child{padding-right:0}
  .admin-reports .report-table tbody tr:last-child td{border-bottom:0}
  .admin-reports .report-table tbody tr:hover td{background:#F1F6F5}
  .admin-reports .report-table .report-cell--label{font-weight:600}
  .admin-reports .report-table .report-cell--numeric{font-variant-numeric:tabular-nums;text-align:right}
  .admin-reports .report-table .report-cell--muted{color:var(--report-muted)}
  .admin-reports .report-table .report-cell--value{font-weight:650}
  @media(min-width:640px){
    .report-table--sales-period th:nth-child(1){width:42%}.report-table--sales-period th:nth-child(2){width:18%}.report-table--sales-period th:nth-child(3){width:22%}.report-table--sales-period th:nth-child(4){width:18%}
    .report-table--category th:nth-child(1){width:52%}.report-table--category th:nth-child(2){width:20%}.report-table--category th:nth-child(3){width:28%}
    .report-table--seller th:nth-child(1){width:39%}.report-table--seller th:nth-child(2){width:15%}.report-table--seller th:nth-child(3){width:23%}.report-table--seller th:nth-child(4){width:23%}
    .report-table--product-sales th:nth-child(1),.report-table--low-products th:nth-child(1),.report-table--stock th:nth-child(1){width:56%}
    .report-table--product-sales th:nth-child(2),.report-table--low-products th:nth-child(2),.report-table--stock th:nth-child(2){width:20%}
    .report-table--product-sales th:nth-child(3),.report-table--low-products th:nth-child(3),.report-table--stock th:nth-child(3){width:24%}
    .report-table--customers th:nth-child(1){width:18%}.report-table--customers th:nth-child(2){width:24%}.report-table--customers th:nth-child(3){width:12%}.report-table--customers th:nth-child(4){width:17%}.report-table--customers th:nth-child(5){width:15%}.report-table--customers th:nth-child(6){width:14%}
    .report-table--sellers th:nth-child(1){width:17%}.report-table--sellers th:nth-child(2){width:11%}.report-table--sellers th:nth-child(3){width:12%}.report-table--sellers th:nth-child(4){width:16%}.report-table--sellers th:nth-child(5){width:15%}.report-table--sellers th:nth-child(6){width:14%}.report-table--sellers th:nth-child(7){width:15%}
    .report-table--financial th:nth-child(1){width:27%}.report-table--financial th:nth-child(2){width:13%}.report-table--financial th:nth-child(3){width:20%}.report-table--financial th:nth-child(4){width:20%}.report-table--financial th:nth-child(5){width:20%}
  }
  .report-empty-row td{padding:18px 0!important;color:var(--report-muted)!important;text-align:left}
  .report-empty{display:inline-flex;align-items:center;gap:8px;font-size:13.5px}
  .report-empty svg{width:16px;height:16px;flex:0 0 16px;fill:none;stroke:currentColor;stroke-width:1.5;stroke-linecap:round;stroke-linejoin:round}
  .report-performance{width:100%;height:7px;overflow:hidden;border-radius:999px;background:#EAF3F2}
  .report-performance span{display:block;height:100%;border-radius:inherit;background:#3F8F9C}
  .report-stock-status{display:inline-flex;min-height:28px;align-items:center;padding:4px 9px;border-radius:999px;background:#EAF3F2;color:var(--report-teal);font-size:12px;font-weight:600;line-height:1.25}
  .report-product-heading{display:flex;align-items:center;gap:8px}
  .report-product-heading svg{width:18px;height:18px;flex:0 0 18px;color:var(--report-teal)}
  @media(max-width:1099.98px){
    .admin-reports .report-filter{grid-template-columns:minmax(0,1fr) auto;gap:12px}
    .admin-reports .report-fields{grid-column:1/-1;grid-template-columns:repeat(2,minmax(0,1fr))}
    .admin-reports .report-actions{grid-column:1;grid-row:2}
    .admin-reports .report-exports{grid-column:2;grid-row:2;justify-self:end}
    .report-split{grid-template-columns:minmax(0,1fr);gap:24px}
    .report-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}
  }
  @media(max-width:767.98px){
    body:has(.admin-reports) .content{padding:20px 16px}
    .admin-reports .admin-list-head h1{font-size:24px}
    .report-metrics{grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}
    .report-section{margin-bottom:18px;padding:14px 13px 12px}
    .report-split{gap:14px}
  }
  @media(min-width:640px) and (max-width:1099.98px){
    .admin-reports .report-table th,.admin-reports .report-table td{padding-right:7px;padding-left:7px;font-size:12.5px}
  }
  @media(max-width:639.98px){
    .admin-reports .report-table{display:block;width:100%}
    .admin-reports .report-table thead{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);clip-path:inset(50%);white-space:nowrap}
    .admin-reports .report-table tbody{display:grid;gap:0;width:100%;padding:0}
    .admin-reports .report-table tr.report-row{display:grid;grid-template-columns:minmax(0,1fr) auto;column-gap:10px;align-items:start;margin:0;padding:10px 0;border-bottom:1px solid var(--report-line);background:transparent}
    .admin-reports .report-table--sales-period tr.report-row{grid-template-areas:"label primary" "orders share"}
    .admin-reports .report-table--category tr.report-row,.admin-reports .report-table--product-sales tr.report-row{grid-template-areas:"label primary" "units units"}
    .admin-reports .report-table--seller tr.report-row{grid-template-areas:"label primary" "orders commission"}
    .admin-reports .report-table--customers tr.report-row{grid-template-areas:"label primary" "email email" "orders average" "last last"}
    .admin-reports .report-table--sellers tr.report-row{grid-template-areas:"label primary" "products orders" "commission average" "performance performance"}
    .admin-reports .report-table--low-products tr.report-row{grid-template-areas:"label label" "views carts"}
    .admin-reports .report-table--stock tr.report-row{grid-template-areas:"label primary" "stock stock"}
    .admin-reports .report-table--financial tr.report-row{grid-template-areas:"label primary" "orders commission" "delivery delivery"}
    .admin-reports .report-table tr.report-row:last-child{border-bottom:0}
    .admin-reports .report-table tr.report-row td{display:flex;min-width:0;height:auto;align-items:baseline;gap:5px;padding:4px 0;border:0;background:transparent;font-size:12.5px;line-height:1.35}
    .admin-reports .report-table tr.report-row td::before{content:attr(data-label);display:inline;color:var(--report-muted);font-size:11.5px;font-weight:500;line-height:1.3}
    .admin-reports .report-table tr.report-row td.report-cell--label{display:block;grid-area:label;font-size:14px;font-weight:650;overflow-wrap:anywhere}
    .admin-reports .report-table tr.report-row td.report-cell--label::before,.admin-reports .report-table tr.report-row td.report-cell--primary::before{display:none}
    .admin-reports .report-table tr.report-row td.report-cell--primary{grid-area:primary;justify-content:flex-end;text-align:right;font-size:15px;font-weight:700;white-space:nowrap;overflow-wrap:normal}
    .admin-reports .report-table tr.report-row td.report-cell--secondary{font-variant-numeric:tabular-nums}
    .admin-reports .report-table tr.report-row td.report-cell--orders{grid-area:orders}
    .admin-reports .report-table tr.report-row td.report-cell--share{grid-area:share;justify-content:flex-end;text-align:right}
    .admin-reports .report-table tr.report-row td.report-cell--units{grid-area:units}
    .admin-reports .report-table tr.report-row td.report-cell--commission{grid-area:commission;justify-content:flex-end;text-align:right}
    .admin-reports .report-table tr.report-row td.report-cell--email{grid-area:email;display:block;overflow-wrap:anywhere}
    .admin-reports .report-table tr.report-row td.report-cell--average{grid-area:average;justify-content:flex-end;text-align:right}
    .admin-reports .report-table tr.report-row td.report-cell--last{grid-area:last}
    .admin-reports .report-table tr.report-row td.report-cell--products{grid-area:products}
    .admin-reports .report-table tr.report-row td.report-cell--performance{grid-area:performance;align-items:center;padding-top:7px}
    .admin-reports .report-table tr.report-row td.report-cell--views{grid-area:views}
    .admin-reports .report-table tr.report-row td.report-cell--carts{grid-area:carts;justify-content:flex-end;text-align:right}
    .admin-reports .report-table tr.report-row td.report-cell--stock{grid-area:stock}
    .admin-reports .report-table tr.report-row td.report-cell--delivery{grid-area:delivery}
    .admin-reports .report-table tr.report-row td.report-cell--label::before{display:none}
    .admin-reports .report-table tr.report-empty-row{display:block;padding:0;border:0}
    .admin-reports .report-table tr.report-empty-row td{display:block;padding:14px 0!important}
    .report-metric strong{font-size:clamp(17px,5.4vw,23px)}
    .report-metric span{font-size:12px}
    .report-section{margin-bottom:22px}
    .report-split{margin-bottom:22px}
  }
  @media(max-width:479.98px){
    .admin-reports .report-filter{grid-template-columns:repeat(3,minmax(0,1fr));gap:9px 7px;margin-bottom:20px;padding-bottom:14px}
    .admin-reports .report-fields{grid-column:1/-1;grid-template-columns:minmax(0,1fr)}
    .admin-reports .report-actions,.admin-reports .report-exports{display:contents}
    .admin-reports .report-primary{grid-column:1/-1}
    .admin-reports .report-reset,.admin-reports .report-export{width:100%;min-width:0;padding-inline:6px;font-size:12px}
    .admin-reports .report-export{gap:5px}
    .admin-reports .report-export svg{width:14px;height:14px;flex-basis:14px}
    .report-metrics{gap:8px}
    .report-metric{min-height:88px;padding:12px 10px}
    .report-metric strong{font-size:clamp(15px,5.1vw,21px)}
  }
  @media(prefers-reduced-motion:no-preference){
    .admin-reports .admin-list-head,.admin-reports .report-tabs,.admin-reports .report-filter,.admin-reports .report-metrics,.admin-reports .report-section,.admin-reports .report-split{animation:report-enter .3s cubic-bezier(.22,1,.36,1) both}
    .admin-reports .report-tabs{animation-delay:55ms}.admin-reports .report-filter{animation-delay:110ms}.admin-reports .report-metrics{animation-delay:165ms}.admin-reports .report-section,.admin-reports .report-split{animation-delay:220ms}
    .admin-reports .admin-list-filter,.admin-reports .report-primary,.admin-reports .report-reset,.admin-reports .report-export{transition:background-color .16s ease,color .16s ease,transform .16s cubic-bezier(.22,1,.36,1)}
  }
  @keyframes report-enter{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
  @media(prefers-reduced-motion:reduce){.admin-reports *,.admin-reports *::before,.admin-reports *::after{animation:none!important;transition:none!important;scroll-behavior:auto!important}}
  @media print{
    .admin-reports .report-tabs,.admin-reports .report-filter{display:none!important}
    .sb2--admin,.topbar{display:none!important}
    .shell,.main{display:block!important;min-height:0!important}
    .content{max-width:none!important;padding:0!important}
    body{background:#fff!important}
    .admin-reports{max-width:none;color:#111}
    .admin-reports .report-metrics{background:transparent;padding-inline:0}
    .admin-reports .report-metric,.admin-reports .report-section{box-shadow:none}
    .admin-reports .report-section,.admin-reports .report-split{break-inside:avoid}
    .admin-reports .report-table th,.admin-reports .report-table td{color:#111!important}
  }
</style>

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
