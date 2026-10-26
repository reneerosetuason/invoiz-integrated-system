@extends('layouts.seller')

@section('title', 'Dashboard')

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,560;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"> <!-- taste-skill-ignore: matches the existing Invoiz admin type pairing -->
<style>
    body:has(.seller-dashboard){background:#FBF9F6;color:#1B1B1E;font-family:'Inter',system-ui,-apple-system,'Segoe UI',sans-serif} /* taste-skill-ignore: existing Invoiz admin body face */
    body:has(.seller-dashboard) main{background:#FBF9F6}
    body:has(.seller-dashboard) .topbar{background:#FBF9F6;border:0;box-shadow:none;font-family:'Inter',system-ui,sans-serif} /* taste-skill-ignore: existing Invoiz admin body face */
    body:has(.seller-dashboard) .topbar h1{color:#0E4A57;font-family:'Fraunces',Georgia,serif;font-size:20px;font-weight:560;letter-spacing:-.02em}
    .seller-dashboard{--dash-ink:#1B1B1E;--dash-teal:#0E4A57;--dash-teal-mid:#16697A;--dash-ground:#FBF9F6;--dash-surface:#fff;--dash-soft:#EAF3F2;--dash-muted:#56696B;--dash-line:rgba(15,76,92,.12);width:100%;max-width:1440px;margin:0 auto;color:var(--dash-ink);font-family:'Inter',system-ui,-apple-system,'Segoe UI',sans-serif} /* taste-skill-ignore: existing Invoiz admin body face */
    .seller-dashboard *,.seller-dashboard *::before,.seller-dashboard *::after{box-sizing:border-box}
    .seller-dashboard h1,.seller-dashboard h2,.seller-dashboard h3{margin:0;color:var(--dash-teal);font-family:'Fraunces',Georgia,serif;font-weight:560;letter-spacing:-.02em}
    .seller-dashboard p{margin:0}
    .seller-dashboard a{color:inherit;text-decoration:none}
    .seller-dashboard a:focus-visible{outline:2px solid var(--dash-teal);outline-offset:3px}
    .seller-dashboard .dash-head{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin:0 0 22px}
    .seller-dashboard .dash-head h1{font-size:30px;line-height:1.12}
    .seller-dashboard .dash-head p{margin-top:7px;color:var(--dash-muted);font-size:14px;line-height:1.5}
    .seller-dashboard .dash-primary{display:inline-flex;min-height:44px;align-items:center;justify-content:center;gap:9px;padding:10px 16px;border-radius:8px;background:var(--dash-teal);color:#fff;font-size:13px;font-weight:650;transition:background-color .16s ease,transform .16s ease}
    .seller-dashboard .dash-primary:hover{background:#16697A}
    .seller-dashboard .dash-primary:active{transform:translateY(1px)}
    .seller-dashboard .dash-primary svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
    .seller-dashboard .dash-overview{display:grid;grid-template-columns:minmax(0,1.08fr) minmax(0,.92fr);gap:28px;margin:0 0 18px;padding:23px 25px;border-radius:10px;background:var(--dash-teal);color:#fff}
    .seller-dashboard .dash-overview h2{margin:0 0 8px;color:#fff;font-size:18px;line-height:1.25}
    .seller-dashboard .dash-sales-value{display:block;color:#fff;font-family:'Fraunces',Georgia,serif;font-size:clamp(32px,3.4vw,46px);font-weight:560;line-height:1.08;letter-spacing:-.035em;font-variant-numeric:tabular-nums;overflow-wrap:anywhere}
    .seller-dashboard .dash-sales-caption{margin-top:8px;color:rgba(255,255,255,.82);font-size:13px;line-height:1.5}
    .seller-dashboard .dash-sales-caption a{color:#fff;font-weight:650;text-decoration:underline;text-underline-offset:3px}
    .seller-dashboard .dash-sales-caption a:hover{text-decoration-thickness:2px}
    .seller-dashboard .dash-queue{min-width:0}
    .seller-dashboard .dash-queue-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px 18px}
    .seller-dashboard .dash-queue-link{display:flex;min-width:0;min-height:72px;flex-direction:column;justify-content:space-between;gap:8px;padding-top:2px;color:#fff;text-decoration:none}
    .seller-dashboard .dash-queue-link span{color:rgba(255,255,255,.82);font-size:12.5px;line-height:1.35}
    .seller-dashboard .dash-queue-link strong{font-family:'Fraunces',Georgia,serif;font-size:27px;font-weight:560;line-height:1;font-variant-numeric:tabular-nums}
    .seller-dashboard .dash-queue-link:hover span{text-decoration:underline;text-underline-offset:3px}
    .seller-dashboard .dash-queue-link:focus-visible{outline-color:#fff}
    .seller-dashboard .dash-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:20px;margin:0 0 24px;padding:15px 18px 17px;background:#fff;border-radius:8px}
    .seller-dashboard .dash-metric{min-width:0}
    .seller-dashboard .dash-metric span{display:block;color:var(--dash-muted);font-size:13px;line-height:1.4}
    .seller-dashboard .dash-metric strong{display:block;margin-top:5px;color:var(--dash-teal);font-family:'Fraunces',Georgia,serif;font-size:27px;font-weight:560;line-height:1.1;font-variant-numeric:tabular-nums;overflow-wrap:anywhere}
    .seller-dashboard .dash-metric small{display:block;margin-top:5px;color:var(--dash-muted);font-size:12px;line-height:1.4}
    .seller-dashboard .dash-analysis,.seller-dashboard .dash-bottom{display:grid;grid-template-columns:minmax(0,1.7fr) minmax(280px,.9fr);gap:18px;margin:0 0 20px}
    .seller-dashboard .dash-panel{min-width:0;padding:19px 20px;background:#fff;border-radius:8px}
    .seller-dashboard .dash-panel h2{font-size:20px;line-height:1.2}
    .seller-dashboard .dash-panel .dash-subtitle{margin-top:5px;color:var(--dash-muted);font-size:13px;line-height:1.45}
    .seller-dashboard .dash-sales-chart{height:254px;margin-top:16px}
    .seller-dashboard .dash-status-chart{height:168px;margin:13px auto 16px}
    .seller-dashboard .dash-status-list{display:grid;gap:9px}
    .seller-dashboard .dash-status-row{display:grid;grid-template-columns:minmax(0,1fr) auto auto;align-items:center;gap:10px;color:var(--dash-ink);font-size:12.5px;line-height:1.35}
    .seller-dashboard .dash-status-name{display:flex;min-width:0;align-items:center;gap:8px}
    .seller-dashboard .dash-status-dot{width:8px;height:8px;flex:0 0 8px;border-radius:50%;background:var(--dash-teal)}
    .seller-dashboard .dash-status-dot--mid{background:#5B9297}
    .seller-dashboard .dash-status-dot--soft{background:#A7C9C6}
    .seller-dashboard .dash-status-dot--muted{background:#849293}
    .seller-dashboard .dash-status-count{font-weight:650;font-variant-numeric:tabular-nums}
    .seller-dashboard .dash-status-share{min-width:38px;color:var(--dash-muted);font-size:12px;text-align:right;font-variant-numeric:tabular-nums}
    .seller-dashboard .dash-bottom{grid-template-columns:minmax(0,1.7fr) minmax(280px,.9fr)}
    .seller-dashboard .dash-panel-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin:0 0 12px}
    .seller-dashboard .dash-section-link{flex:0 0 auto;margin-top:3px;color:var(--dash-teal);font-size:13px;font-weight:650;text-decoration:underline;text-decoration-color:transparent;text-underline-offset:4px;transition:text-decoration-color .16s ease}
    .seller-dashboard .dash-section-link:hover{text-decoration-color:currentColor}
    .seller-dashboard .dash-order-wrap{width:100%;min-width:0;overflow-x:auto}
    .seller-dashboard .dash-order-table{width:100%;border-collapse:collapse;table-layout:fixed}
    .seller-dashboard .dash-order-table th{padding:9px 8px;border-bottom:1px solid var(--dash-line);color:var(--dash-muted);font-size:11px;font-weight:600;letter-spacing:0;text-align:left;text-transform:none}
    .seller-dashboard .dash-order-table td{padding:12px 8px;border-bottom:1px solid rgba(15,76,92,.08);color:var(--dash-ink);font-size:12.5px;line-height:1.4;vertical-align:middle}
    .seller-dashboard .dash-order-table tr:last-child td{border-bottom:0}
    .seller-dashboard .dash-order-table .dash-order-id{color:var(--dash-teal);font-weight:650}
    .seller-dashboard .dash-order-table .dash-order-total{color:var(--dash-teal);font-weight:700;font-variant-numeric:tabular-nums;white-space:nowrap}
    .seller-dashboard .dash-order-table .dash-order-date{color:var(--dash-muted);font-size:12px}
    .seller-dashboard .dash-order-table .dash-order-empty{text-align:center;color:var(--dash-muted)}
    .seller-dashboard .dash-order-status{display:inline-flex;max-width:100%;align-items:center;padding:5px 8px;border-radius:999px;background:var(--dash-soft);color:var(--dash-teal);font-size:11.5px;font-weight:600;line-height:1.25;overflow-wrap:anywhere}
    .seller-dashboard .dash-product-list{display:grid;gap:15px}
    .seller-dashboard .dash-product-row{display:grid;grid-template-columns:minmax(0,1fr) 62px;align-items:center;gap:12px;min-width:0}
    .seller-dashboard .dash-product-copy{min-width:0}
    .seller-dashboard .dash-product-name{display:block;overflow:hidden;color:var(--dash-ink);font-size:13.5px;font-weight:600;line-height:1.35;text-overflow:ellipsis;white-space:nowrap}
    .seller-dashboard .dash-product-meta{margin-top:3px;color:var(--dash-muted);font-size:12px;line-height:1.35}
    .seller-dashboard .dash-product-track{height:5px;overflow:hidden;border-radius:999px;background:var(--dash-soft)}
    .seller-dashboard .dash-product-track span{display:block;height:100%;border-radius:inherit;background:var(--dash-teal-mid)}
    .seller-dashboard .dash-interest{margin:0 0 8px}
    .seller-dashboard .dash-interest-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:24px}
    .seller-dashboard .dash-interest-group h3{margin:0 0 9px;color:var(--dash-muted);font-family:'Inter',system-ui,sans-serif;font-size:13px;font-weight:650;letter-spacing:0} /* taste-skill-ignore: matches the existing Invoiz admin type pairing */
    .seller-dashboard .dash-interest-group .dash-product-list{gap:11px}
    .seller-dashboard .dash-empty{padding:20px 0;color:var(--dash-muted);font-size:13px;line-height:1.5}
    .seller-dashboard .dash-empty--chart{display:grid;min-height:200px;place-items:center;text-align:center}
    .seller-dashboard .dash-order-table tbody tr:hover td{background:#F8FAF9}
    @media(max-width:1199.98px){
        .seller-dashboard .dash-overview{grid-template-columns:minmax(0,1fr);gap:20px}
        .seller-dashboard .dash-queue-list{grid-template-columns:repeat(3,minmax(0,1fr))}
        .seller-dashboard .dash-analysis,.seller-dashboard .dash-bottom{grid-template-columns:minmax(0,1fr) minmax(250px,.75fr)}
    }
    @media(max-width:900px){
        .seller-dashboard .dash-analysis,.seller-dashboard .dash-bottom{grid-template-columns:minmax(0,1fr)}
        .seller-dashboard .dash-status-layout{display:grid;grid-template-columns:minmax(150px,.65fr) minmax(0,1fr);align-items:center;gap:20px}
        .seller-dashboard .dash-status-chart{width:100%;margin:0}
        .seller-dashboard .dash-status-list{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px 14px}
    }
    @media(max-width:767.98px){
        body:has(.seller-dashboard) main{padding:18px 16px}
        .seller-dashboard .dash-head{align-items:flex-start;gap:14px;margin-bottom:17px}
        .seller-dashboard .dash-head h1{font-size:26px}
        .seller-dashboard .dash-head p{max-width:42ch;font-size:13px}
        .seller-dashboard .dash-primary{min-height:44px;padding-inline:12px;font-size:12.5px}
        .seller-dashboard .dash-overview{gap:20px;margin-bottom:14px;padding:19px 18px;border-radius:8px}
        .seller-dashboard .dash-overview h2{font-size:17px}
        .seller-dashboard .dash-sales-value{font-size:clamp(30px,8vw,40px)}
        .seller-dashboard .dash-queue-list{grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
        .seller-dashboard .dash-queue-link{min-height:64px}
        .seller-dashboard .dash-queue-link span{font-size:11.5px}
        .seller-dashboard .dash-queue-link strong{font-size:24px}
        .seller-dashboard .dash-metrics{grid-template-columns:repeat(2,minmax(0,1fr));gap:16px 12px;margin-bottom:16px;padding:16px 14px}
        .seller-dashboard .dash-metric strong{font-size:25px}
        .seller-dashboard .dash-analysis,.seller-dashboard .dash-bottom{gap:12px;margin-bottom:13px}
        .seller-dashboard .dash-panel{padding:16px 14px;border-radius:8px}
        .seller-dashboard .dash-panel h2{font-size:19px}
        .seller-dashboard .dash-sales-chart{height:220px;margin-top:11px}
        .seller-dashboard .dash-status-layout{grid-template-columns:minmax(112px,.55fr) minmax(0,1fr);gap:12px}
        .seller-dashboard .dash-status-chart{height:138px}
        .seller-dashboard .dash-status-list{grid-template-columns:minmax(0,1fr);gap:8px}
        .seller-dashboard .dash-order-wrap{overflow:visible}
        .seller-dashboard .dash-order-table{display:block;width:100%;table-layout:auto}
        .seller-dashboard .dash-order-table thead{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);clip-path:inset(50%);white-space:nowrap}
        .seller-dashboard .dash-order-table tbody{display:grid;width:100%}
        .seller-dashboard .dash-order-table tbody tr:not(.dash-order-empty){display:grid;grid-template-columns:minmax(0,1fr) auto;grid-template-areas:"order total" "buyer status" "date date";align-items:center;column-gap:10px;row-gap:3px;padding:10px 0;border-bottom:1px solid rgba(15,76,92,.1)}
        .seller-dashboard .dash-order-table tbody tr:last-child:not(.dash-order-empty){border-bottom:0}
        .seller-dashboard .dash-order-table tbody tr:not(.dash-order-empty) td{display:block;min-width:0;padding:2px 0;border:0;font-size:12.5px}
        .seller-dashboard .dash-order-table .dash-order-cell{grid-area:order}
        .seller-dashboard .dash-order-table .dash-buyer-cell{grid-area:buyer;overflow:hidden;color:var(--dash-muted);text-overflow:ellipsis;white-space:nowrap}
        .seller-dashboard .dash-order-table .dash-order-total-cell{grid-area:total;text-align:right}
        .seller-dashboard .dash-order-table .dash-order-status-cell{grid-area:status;text-align:right}
        .seller-dashboard .dash-order-table .dash-order-date-cell{grid-area:date;color:var(--dash-muted);font-size:11.5px}
        .seller-dashboard .dash-order-table .dash-order-status{justify-content:center}
        .seller-dashboard .dash-order-table tbody tr.dash-order-empty{display:block;padding:16px 0;border:0}
        .seller-dashboard .dash-order-table tbody tr.dash-order-empty td{display:block;padding:0;border:0;text-align:left}
        .seller-dashboard .dash-interest-grid{grid-template-columns:minmax(0,1fr);gap:18px}
        .seller-dashboard .dash-interest-group h3{font-size:13px}
    }
    @media(max-width:380px){
        .seller-dashboard .dash-head{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:end;gap:10px}
        .seller-dashboard .dash-head h1{font-size:22px}
        .seller-dashboard .dash-primary{min-height:42px;gap:5px;padding-inline:8px;font-size:11.5px;white-space:nowrap}
        .seller-dashboard .dash-queue-list{gap:8px}
        .seller-dashboard .dash-queue-link span{font-size:11px}
        .seller-dashboard .dash-status-layout{grid-template-columns:108px minmax(0,1fr);gap:9px}
    }
    @media(prefers-reduced-motion:no-preference){
        .seller-dashboard .dash-primary,.seller-dashboard .dash-section-link,.seller-dashboard .dash-queue-link{transition:background-color .16s ease,color .16s ease,transform .16s ease,text-decoration-color .16s ease}
        .seller-dashboard .dash-panel{animation:dash-panel-in .32s cubic-bezier(.22,1,.36,1) both}
        .seller-dashboard .dash-panel:nth-child(2){animation-delay:60ms}
    }
    @keyframes dash-panel-in{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
    @media(prefers-reduced-motion:reduce){.seller-dashboard *,.seller-dashboard *::before,.seller-dashboard *::after{animation:none!important;transition:none!important;scroll-behavior:auto!important}}
</style>
@endpush

@section('content')
@php
    $sellerStoreName = auth()->user()->seller?->business_name ?: 'Your store';
@endphp
<div class="seller-dashboard">
    <header class="dash-head">
        <div>
            <h1>Store overview</h1>
            <p>{{ $sellerStoreName }} · Orders, settled sales and inventory</p>
        </div>
        <a class="dash-primary" href="{{ route('seller.orders.index') }}">
            <span>{{ ($totals['pending'] ?? 0) > 0 ? 'Review orders' : 'View orders' }}</span>
            @if(($totals['pending'] ?? 0) > 0)<span>({{ $totals['pending'] }})</span>@endif
            <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M3.5 10h12M10 4.5l5.5 5.5-5.5 5.5"/></svg>
        </a>
    </header>

    <section class="dash-overview" aria-label="Settled sales and items needing attention">
        <div class="dash-revenue">
            <h2>Settled sales</h2>
            <strong class="dash-sales-value">{{ peso($totals['revenue']) }}</strong>
            <p class="dash-sales-caption">
                Today: <strong>{{ peso($totals['revenue_today']) }}</strong>
                @if(($totals['remittance_pending'] ?? 0) > 0)
                    <span aria-hidden="true"> · </span><a href="{{ route('seller.remittances.index') }}">Cash to confirm: {{ peso($totals['remittance_amount'] ?? 0) }}</a>
                @else
                    <span aria-hidden="true"> · </span><a href="{{ route('seller.remittances.index') }}">Cash settled</a>
                @endif
                <span aria-hidden="true"> · </span>Confirmed: {{ peso($totals['remittance_confirmed'] ?? 0) }}
            </p>
        </div>
        <div class="dash-queue">
            <h2>Needs attention</h2>
            <div class="dash-queue-list">
                <a class="dash-queue-link" href="{{ route('seller.orders.index') }}">
                    <span>New or pending</span><strong>{{ $totals['pending'] }}</strong>
                </a>
                <a class="dash-queue-link" href="{{ route('seller.orders.index') }}">
                    <span>Ready to ship</span><strong>{{ $totals['to_ship'] }}</strong>
                </a>
                <a class="dash-queue-link" href="{{ route('seller.products.index') }}">
                    <span>Low stock</span><strong>{{ $totals['low_stock'] }}</strong>
                </a>
            </div>
        </div>
    </section>

    <section class="dash-metrics" aria-label="Store performance">
        <div class="dash-metric"><span>Orders placed</span><strong>{{ $totals['orders'] }}</strong><small>All time</small></div>
        <div class="dash-metric"><span>Delivered</span><strong>{{ $totals['delivered'] }}</strong><small>Orders completed</small></div>
        <div class="dash-metric"><span>Products listed</span><strong>{{ $totals['products'] }}</strong><small>{{ $totals['low_stock'] }} need restocking</small></div>
        <div class="dash-metric"><span>Average rating</span><strong>{{ number_format((float) $totals['rating'], 1) }} / 5</strong><small>Across rated products</small></div>
    </section>

    <div class="dash-analysis">
        <section class="dash-panel" aria-labelledby="dash-sales-title">
            <h2 id="dash-sales-title">Sales over the last 30 days</h2>
            <p class="dash-subtitle">Settled sales from delivered orders</p>
            <div class="dash-sales-chart">
                <canvas id="salesChart" role="img" aria-label="Line chart of settled sales over the last 30 days"></canvas>
            </div>
        </section>
        <section class="dash-panel" aria-labelledby="dash-status-title">
            <h2 id="dash-status-title">Order status</h2>
            <p class="dash-subtitle">Your current order mix</p>
            <div class="dash-status-layout">
                <div class="dash-status-chart">
                    <canvas id="statusChart" role="img" aria-label="Doughnut chart showing orders by status"></canvas>
                </div>
                <div class="dash-status-list">
                    @php
                        $orderTotal = array_sum($statusCounts);
                    @endphp
                    @forelse($statusCounts as $status => $count)
                        @php
                            $statusTone = in_array($status, ['pending', 'confirmed'], true)
                                ? ''
                                : (in_array($status, ['processing', 'ready_for_delivery', 'out_for_delivery', 'picked_up'], true)
                                    ? 'dash-status-dot--mid'
                                    : (in_array($status, ['delivered', 'completed'], true) ? 'dash-status-dot--soft' : 'dash-status-dot--muted'));
                        @endphp
                        <div class="dash-status-row">
                            <span class="dash-status-name"><span class="dash-status-dot {{ $statusTone }}" aria-hidden="true"></span>{{ order_status_label($status) }}</span>
                            <strong class="dash-status-count">{{ $count }}</strong>
                            <span class="dash-status-share">{{ $orderTotal > 0 ? round($count / $orderTotal * 100) : 0 }}%</span>
                        </div>
                    @empty
                        <p class="dash-empty">No orders yet.</p>
                    @endforelse
                </div>
            </div>
        </section>
    </div>

    <div class="dash-bottom">
        <section class="dash-panel dash-orders-panel" aria-labelledby="dash-orders-title">
            <div class="dash-panel-head">
                <div><h2 id="dash-orders-title">Recent orders</h2><p class="dash-subtitle">Your latest customer activity</p></div>
                <a href="{{ route('seller.orders.index') }}" class="dash-section-link">All orders</a>
            </div>
            <div class="dash-order-wrap">
                <table class="dash-order-table" aria-label="Recent seller orders">
                    <thead><tr><th scope="col">Order</th><th scope="col">Buyer</th><th scope="col">Total</th><th scope="col">Status</th><th scope="col">Date</th></tr></thead>
                    <tbody>
                        @forelse($recentOrders as $order)
                            <tr>
                                <td class="dash-order-cell"><a href="{{ route('seller.orders.show', $order->id) }}" class="dash-order-id">#{{ $order->id }}</a></td>
                                <td class="dash-buyer-cell">{{ $order->buyer?->full_name ?? 'Buyer' }}</td>
                                <td class="dash-order-total-cell"><span class="dash-order-total">{{ peso($order->seller_subtotal ?? $order->total_amount) }}</span></td>
                                <td class="dash-order-status-cell"><span class="dash-order-status">{{ order_status_label($order->status) }}</span></td>
                                <td class="dash-order-date-cell"><time class="dash-order-date" datetime="{{ $order->created_at->toIso8601String() }}">{{ $order->created_at->format('M j, g:i A') }}</time></td>
                            </tr>
                        @empty
                            <tr class="dash-order-empty"><td colspan="5">No orders yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="dash-panel dash-top-products" aria-labelledby="dash-products-title">
            <div class="dash-panel-head">
                <div><h2 id="dash-products-title">Top products</h2><p class="dash-subtitle">By units sold</p></div>
                <a href="{{ route('seller.products.index') }}" class="dash-section-link">Inventory</a>
            </div>
            <div class="dash-product-list">
                @forelse($topProducts as $product)
                    @php
                        $topUnits = $topProducts->max('units') ?: 0;
                    @endphp
                    <div class="dash-product-row">
                        <div class="dash-product-copy">
                            <span class="dash-product-name" title="{{ $product->product_name }}">{{ $product->product_name }}</span>
                            <p class="dash-product-meta">{{ $product->units }} units · {{ peso($product->sales) }}</p>
                        </div>
                        <div class="dash-product-track" aria-hidden="true"><span style="width:{{ $topUnits ? round($product->units / $topUnits * 100) : 0 }}%"></span></div>
                    </div>
                @empty
                    <p class="dash-empty">No settled product sales yet.</p>
                @endforelse
            </div>
        </section>
    </div>

    <section class="dash-panel dash-interest" aria-labelledby="dash-interest-title">
        <div class="dash-panel-head">
            <div><h2 id="dash-interest-title">What shoppers want</h2><p class="dash-subtitle">Use product interest to guide your next restock.</p></div>
        </div>
        <div class="dash-interest-grid">
            <div class="dash-interest-group">
                <h3>Most viewed</h3>
                <div class="dash-product-list">
                    @forelse($mostViewed->where('views_count', '>', 0) as $product)
                        @php
                            $viewMax = $mostViewed->max('views_count') ?: 0;
                        @endphp
                        <div class="dash-product-row">
                            <div class="dash-product-copy">
                                <span class="dash-product-name" title="{{ $product->name }}">{{ $product->name }}</span>
                                <p class="dash-product-meta">{{ number_format($product->views_count) }} views</p>
                            </div>
                            <div class="dash-product-track" aria-hidden="true"><span style="width:{{ $viewMax ? round($product->views_count / $viewMax * 100) : 0 }}%"></span></div>
                        </div>
                    @empty
                        <p class="dash-empty">No product views yet.</p>
                    @endforelse
                </div>
            </div>
            <div class="dash-interest-group">
                <h3>Most added to cart</h3>
                <div class="dash-product-list">
                    @forelse($mostAdded->where('cart_additions', '>', 0) as $product)
                        @php
                            $cartMax = $mostAdded->max('cart_additions') ?: 0;
                        @endphp
                        <div class="dash-product-row">
                            <div class="dash-product-copy">
                                <span class="dash-product-name" title="{{ $product->name }}">{{ $product->name }}</span>
                                <p class="dash-product-meta">{{ number_format($product->cart_additions) }} cart additions</p>
                            </div>
                            <div class="dash-product-track" aria-hidden="true"><span style="width:{{ $cartMax ? round($product->cart_additions / $cartMax * 100) : 0 }}%"></span></div>
                        </div>
                    @empty
                        <p class="dash-empty">No cart additions yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.color = '#56696B';

    // Sales line chart
    new Chart(document.getElementById('salesChart'), {
        type: 'line',
        data: {
            labels: {!! json_encode($salesSeries['labels']) !!},
            datasets: [{
                label: 'Sales',
                data: {!! json_encode($salesSeries['values']) !!},
                borderColor: '#0E4A57',
                backgroundColor: 'rgba(14,74,87,0.10)',
                fill: true,
                tension: 0.35,
                pointRadius: 3,
                pointBackgroundColor: '#0E4A57',
                borderWidth: 2,
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
                    grid: { color: 'rgba(15,76,92,0.08)', drawBorder: false },
                    ticks: { callback: (v) => '₱' + v }
                },
                x: { grid: { display: false } }
            }
        }
    });

    // Orders by status donut
    const statusColors = {
        pending: '#0E4A57',
        confirmed: '#0E4A57',
        processing: '#3F7D84',
        ready_for_delivery: '#3F7D84',
        out_for_delivery: '#3F7D84',
        picked_up: '#3F7D84',
        delivered: '#A7C9C6',
        completed: '#A7C9C6',
        cancelled: '#849293',
        refunded: '#849293',
        failed: '#849293'
    };
    const rawStatuses = {!! json_encode(array_keys($statusCounts)) !!};
    const labels = {!! json_encode(array_map('order_status_label', array_keys($statusCounts))) !!};
    const values = {!! json_encode(array_values($statusCounts)) !!};
    const colors = rawStatuses.map(s => statusColors[s] || '#849293');

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
