@extends('admin.layout')

@section('content')
@php
  $statusPalette = [
    'delivered' => '#0E4A57',
    'out_for_delivery' => '#3F8F9C',
    'pending' => '#A9CFD3',
    'cancelled' => '#B4655A',
  ];
  $pieTotal = array_sum(array_column($statusStats, 'count'));
  $cx = 100; $cy = 100; $outerR = 78; $innerR = 53;
  $ang = -90; $segments = [];
  foreach ($statusStats as $s) {
    $sweep = $pieTotal > 0 ? ($s['count'] / $pieTotal) * 360 : 0;
    $a1 = $ang * pi() / 180; $a2 = ($ang + $sweep) * pi() / 180;
    $x1 = $cx + $outerR * cos($a1); $y1 = $cy + $outerR * sin($a1);
    $x2 = $cx + $outerR * cos($a2); $y2 = $cy + $outerR * sin($a2);
    $ix2 = $cx + $innerR * cos($a2); $iy2 = $cy + $innerR * sin($a2);
    $ix1 = $cx + $innerR * cos($a1); $iy1 = $cy + $innerR * sin($a1);
    $large = $sweep > 180 ? 1 : 0;
    $segments[] = [
      'status' => $s['status'],
      'color' => $statusPalette[$s['status']] ?? '#CFC8BA',
      'path' => "M {$x1},{$y1} A {$outerR},{$outerR} 0 {$large} 1 {$x2},{$y2} L {$ix2},{$iy2} A {$innerR},{$innerR} 0 {$large} 0 {$ix1},{$iy1} Z",
      'full' => $sweep >= 359.999,
    ];
    $ang += $sweep;
  }
  $max = max(1, max(array_column($chart, 'value')));
  $W = 720; $H = 280; $padL = 82; $padR = 22; $padT = 16; $padB = 36;
  $plotW = $W - $padL - $padR; $plotH = $H - $padT - $padB;
  $n = count($chart); $pts = [];
  foreach ($chart as $i => $c) {
    $x = $padL + ($n === 1 ? 0 : ($i / ($n - 1)) * $plotW);
    $y = $padT + $plotH - ($c['value'] / $max) * $plotH;
    $pts[] = [$x, $y, $c];
  }
  $linePoints = [];
  foreach ($pts as [$x, $y]) $linePoints[] = $x.','.$y;
  $linePoints = implode(' ', $linePoints);
  $areaPoints = $padL.','.($padT + $plotH).' '.$linePoints.' '.($padL + $plotW).','.($padT + $plotH);
@endphp
<link rel="stylesheet" href="{{ asset('assets/admin/css/dashboard.css') }}">
<div id="adminDashboard">
  <header class="dash-head">
    <div>
      <p class="dash-date">{{ now()->format('l, F d') }}</p>
      <h1>Welcome back, {{ auth()->user()->name ?? 'Admin' }}</h1>
      <p class="dash-description">Here's what's happening with your marketplace today — revenue, orders, and top products at a glance.</p>
    </div>
    <div class="orders-total">{{ $stats['orders'] }} orders total</div>
  </header>

  <section class="cards" aria-label="Marketplace metrics">
    <div class="sum-card">
      <div class="sum-ico c-green" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 2v20M17 6.5C17 4.6 14.8 3 12 3S7 4.6 7 6.5c0 4.5 10 4 10 11 0 1.9-2.2 3.5-5 3.5s-5-1.6-5-3.5"/></svg></div>
      <div class="sum-label">Revenue</div>
      <div class="sum-value money">₱{{ number_format($stats['revenue'], 2) }}</div>
      <div class="sum-sub">Settled sales · exact</div>
    </div>
    <div class="sum-card">
      <div class="sum-ico c-teal" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 2v20M17 6.5C17 4.6 14.8 3 12 3S7 4.6 7 6.5c0 4.5 10 4 10 11 0 1.9-2.2 3.5-5 3.5s-5-1.6-5-3.5"/></svg></div>
      <div class="sum-label">Platform Cut</div>
      <div class="sum-value money">₱{{ number_format($stats['commission_earned'], 2) }}</div>
      <div class="sum-sub reserved">10% commission · logistics ₱{{ number_format($stats['logistics_earned'], 2) }}</div>
    </div>
    <div class="sum-card">
      <div class="sum-ico c-teal" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 2h12l1 6H5l1-6Z"/><path d="M5 8h14l-1 12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 8Z"/></svg></div>
      <div class="sum-label">Orders</div>
      <div class="sum-value">{{ $stats['orders'] }}</div>
      <div class="sum-sub">{{ $stats['delivered'] }} delivered</div>
    </div>
    <div class="sum-card">
      <div class="sum-ico c-orange" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg></div>
      <div class="sum-label">To Action</div>
      <div class="sum-value">{{ $stats['to_action'] }}</div>
      <div class="sum-sub">Needs your review</div>
    </div>
    <div class="sum-card">
      <div class="sum-ico c-purple" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M9 11a5 5 0 1 0 6 0M12 6v5M4 17l2-1M20 17l-2-1"/></svg></div>
      <div class="sum-label">To Ship</div>
      <div class="sum-value">{{ $stats['to_ship'] }}</div>
      <div class="sum-sub">Ready to ship</div>
    </div>
    <div class="sum-card">
      <div class="sum-ico c-dark" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 7h16v10H4zM9 11h6"/><path d="M7 3l2 4M17 3l-2 4"/></svg></div>
      <div class="sum-label">Products</div>
      <div class="sum-value">{{ $stats['products'] }}</div>
      <div class="sum-sub">{{ $stats['low_stock'] }} low stock</div>
    </div>
    <div class="sum-card">
      <div class="sum-ico c-gold" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3l2.4 4.9 5.4.8-3.9 3.8.9 5.4-4.8-2.5-4.8 2.5.9-5.4L4.2 8.7l5.4-.8L12 3Z"/></svg></div>
      <div class="sum-label">Rating</div>
      <div class="sum-value">{{ number_format($stats['rating'], 1) }} <span class="rating-star" aria-hidden="true"><x-ui-icon name="star" :size="16" /></span></div>
      <div class="sum-sub">{{ $stats['sellers'] }} active sellers</div>
    </div>
  </section>

  <div class="dashboard-row">
    <section class="panel">
      <header class="panel-head">
        <h2 class="panel-title with-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3h18v18H3z"/><path d="M3 9h18M9 21V9"/></svg> Sales — Last 30 Days</h2>
        <span class="delivered-chip"><svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>Delivered</span>
      </header>
      <div class="chart-frame">
      <svg id="salesChart" class="sales-chart" viewBox="0 0 {{ $W }} {{ $H }}" role="group" aria-label="Sales over the last 30 days. Use the arrow keys to inspect daily values." tabindex="0">
        @foreach ([0, .25, .5, .75, 1] as $g)
          @php $gy = $padT + $plotH * $g; $val = $max * (1 - $g); @endphp
          <text class="sales-tick" x="{{ $padL - 10 }}" y="{{ $gy + 4 }}" text-anchor="end">₱{{ $val >= 1000 ? number_format($val/1000,1).'k' : number_format($val,0) }}</text>
        @endforeach
        <g class="sales-x-axis">
          @foreach ($pts as $i => [$x, $y, $c])
            @if ($i % 7 === 0 && $i < $n - 2 || $i === $n - 1)
              <text class="sales-date-label" x="{{ $x }}" y="{{ $H - 8 }}" text-anchor="{{ $i === 0 ? 'start' : ($i === $n - 1 ? 'end' : 'middle') }}">{{ $c['label'] }}</text>
            @endif
          @endforeach
        </g>
        <polygon class="sales-area" points="{{ $areaPoints }}" aria-hidden="true" />
        <polyline class="sales-line" points="{{ $linePoints }}" aria-hidden="true" />
        @foreach ($pts as [$x, $y, $c])<circle class="sales-point" cx="{{ $x }}" cy="{{ $y }}" r="3" aria-hidden="true" />@endforeach
      </svg>
      <div class="chart-tooltip" id="salesChartTooltip" role="status" aria-live="polite" aria-hidden="true"></div>
      </div>
    </section>

    <section class="panel">
      <header class="panel-head"><h2 class="panel-title">Orders by Status</h2></header>
      <div class="status-layout">
        <svg class="donut-chart" viewBox="0 0 200 200" role="img" aria-label="Order totals by status">
          @if ($pieTotal > 0)
            @if (count($segments) === 1 && $segments[0]['full'])
              <circle class="donut-slice" data-order-status="{{ $segments[0]['status'] }}" cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $outerR }}" fill="{{ $segments[0]['color'] }}" />
            @else
              @foreach ($segments as $seg)
                <path class="donut-slice" data-order-status="{{ $seg['status'] }}" d="{{ $seg['path'] }}" fill="{{ $seg['color'] }}" />
              @endforeach
            @endif
            <circle class="donut-hole" cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $innerR }}" aria-hidden="true" />
          @else
            <circle class="donut-empty" cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $outerR }}" />
            <circle class="donut-hole" cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $innerR }}" aria-hidden="true" />
          @endif
          <text class="donut-total" x="{{ $cx }}" y="{{ $cy - 2 }}" text-anchor="middle" dominant-baseline="central">{{ $pieTotal }}</text>
          <text class="donut-label" x="{{ $cx }}" y="{{ $cy + 24 }}" text-anchor="middle">TOTAL ORDERS</text>
        </svg>
        <div class="legend">
          @forelse ($statusStats as $s)
            @php $statusColor = $statusPalette[$s['status']] ?? '#CFC8BA'; @endphp
            <button class="legend-row status-filter" type="button" data-status-filter="{{ $s['status'] }}" aria-label="Filter recent orders by {{ $s['label'] }}" aria-pressed="false" aria-controls="recentOrdersBody">
              <span class="legend-name"><span class="legend-dot" style="background:{{ $statusColor }}" aria-hidden="true"></span><span class="legend-label">{{ $s['label'] }}</span></span>
              <span class="legend-values">{{ $s['count'] }}<strong>{{ $s['percent'] }}%</strong></span>
            </button>
          @empty
            <div class="empty-state">No orders.</div>
          @endforelse
        </div>
      </div>
    </section>
  </div>

  <div class="dashboard-row">
    <section class="panel panel-recent-orders">
      <header class="panel-head">
        <h2 class="panel-title with-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M16 13H8M16 17H8"/></svg> Recent Orders</h2>
        <a class="link" href="/admin/orders">View all <span aria-hidden="true">→</span></a>
      </header>
      <div class="order-filter-toolbar" id="orderFilterToolbar" hidden>
        <span class="filter-summary" id="orderFilterSummary" role="status" aria-live="polite"></span>
        <button class="clear-order-filter" id="clearOrderFilter" type="button">Clear filter</button>
      </div>
      <table class="orders-table" role="table" aria-label="Recent orders">
        <colgroup><col style="width:17%"><col style="width:22%"><col style="width:20%"><col style="width:16%"><col style="width:25%"></colgroup>
        <thead role="rowgroup"><tr role="row"><th role="columnheader" scope="col">Order</th><th role="columnheader" scope="col">Buyer</th><th role="columnheader" scope="col">Store</th><th role="columnheader" scope="col">Total</th><th role="columnheader" scope="col">Status</th></tr></thead>
        <tbody id="recentOrdersBody" role="rowgroup">
          @forelse ($recentOrders as $o)
            @php $buyerName = $o->buyer->name ?? 'Guest'; $storeName = $o->seller->store_name ?? '—'; @endphp
            <tr class="recent-order" data-order-status="{{ $o->status }}" role="row">
              <td class="order-cell" role="cell"><span class="order-no">#{{ $o->order_number }}</span><span class="order-date">{{ $o->created_at->format('M d') }}</span></td>
              <td class="buyer-cell" role="cell"><div class="buyer-profile"><span class="buyer-avatar" aria-hidden="true">{{ strtoupper(mb_substr($buyerName,0,1)) }}</span><span class="buyer-name" title="{{ $buyerName }}">{{ Str::limit($buyerName, 12) }}</span></div></td>
              <td class="store-cell" role="cell"><span class="store-name" title="{{ $storeName }}">{{ Str::limit($storeName, 14) }}</span></td>
              <td class="total-cell" role="cell">₱{{ number_format($o->total, 2) }}</td>
              <td class="status-cell" role="cell"><span class="pill pill-{{ $o->status }} status-chip status-{{ $o->status }}">@if ($o->status === 'delivered')<svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>@else<span class="dot" aria-hidden="true"></span>@endif{{ $o->status === 'out_for_delivery' ? 'Out for delivery' : ucfirst($o->status) }}</span></td>
            </tr>
          @empty
            <tr role="row"><td class="empty-order" role="cell" colspan="5">No orders yet.</td></tr>
          @endforelse
          <tr class="filter-empty" role="row" hidden><td class="empty-order" role="cell" colspan="5">No recent orders match this status.</td></tr>
        </tbody>
      </table>
    </section>

    <section class="panel panel-top-products">
      <header class="panel-head">
        <div><h2 class="panel-title">Top Products</h2><p class="panel-sub">By units sold · this month</p></div>
        <span class="top-chip">Top 5</span>
      </header>
      @php $maxUnits = $topProducts->max('units') ?: 1; @endphp
      @forelse ($topProducts as $idx => $p)
        <div class="top-prod">
          <span class="rank">{{ $idx+1 }}</span>
          <div class="product-info">
            <div class="product-name" title="{{ $p->name }}">{{ $p->name }}</div>
            <div class="product-progress"><div class="bar"><div class="bar-fill" style="width:{{ round(($p->units / $maxUnits)*100) }}%;"></div></div><span class="units-sold">{{ $p->units }} sold</span></div>
          </div>
          <span class="product-price">₱{{ number_format($p->sales, 2) }}</span>
        </div>
      @empty
        <div class="empty-state">No sales yet.</div>
      @endforelse
    </section>
  </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/admin/js/dashboard.js') }}" data-config="{{ json_encode([($chart)], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) }}"></script>
@endpush
