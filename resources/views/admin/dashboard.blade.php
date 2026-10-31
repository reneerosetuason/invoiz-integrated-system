@extends('admin.layout')

@section('content')
<style>
  .dash-head {
    background: linear-gradient(135deg, #0E4A57 0%, #16697A 60%, #1A8296 100%);
    border-radius: 16px;
    padding: 22px 26px;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 20px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 4px 16px rgba(14, 74, 87, 0.12);
  }
  .dash-head::after {
    content: '';
    position: absolute;
    right: -25px;
    top: -25px;
    width: 150px;
    height: 150px;
    background: radial-gradient(circle, rgba(255,255,255,0.14) 0%, rgba(255,255,255,0) 70%);
    border-radius: 50%;
    pointer-events: none;
  }
  .dash-head h2 { font-size: 21px; font-weight: 800; margin: 0; letter-spacing: -0.4px; color: #fff; }
  .dash-head p { font-size: 13px; color: rgba(255,255,255,0.85); margin: 4px 0 0; }
  .dash-head .date-pill {
    background: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.25);
    padding: 8px 16px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    backdrop-filter: blur(8px);
    white-space: nowrap;
    text-align: right;
  }

  /* Metric cards */
  .metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
  }
  .metric-card {
    background: #fff;
    border: 1px solid #E8E6E0;
    border-radius: 14px;
    padding: 14px 16px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: transform .15s ease, box-shadow .15s ease;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    position: relative;
    overflow: hidden;
  }
  .metric-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.06);
  }
  .metric-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
  }
  .metric-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: #6E6E73;
  }
  .metric-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }
  .metric-icon svg { width: 17px; height: 17px; stroke: currentColor; fill: none; stroke-width: 1.8; }
  .metric-val {
    font-size: 20px;
    font-weight: 800;
    letter-spacing: -0.4px;
    color: #1B1B1E;
    line-height: 1.15;
  }
  .metric-sub {
    font-size: 11.5px;
    color: #8E8E93;
    margin-top: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  /* Colors */
  .mc-emerald { background: #ECFDF5; color: #10B981; }
  .mc-teal { background: #EAF4F3; color: #16697A; }
  .mc-blue { background: #F0F9FF; color: #0284C7; }
  .mc-amber { background: #FFFBEB; color: #D97706; }
  .mc-indigo { background: #EEF2FF; color: #6366F1; }
  .mc-slate { background: #F1F5F9; color: #475569; }

  /* Panels Grid */
  .grid-2 {
    display: grid;
    grid-template-columns: 1.6fr 1fr;
    gap: 16px;
    margin-bottom: 20px;
  }
  .panel {
    background: #fff;
    border: 1px solid #E8E6E0;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
  }
  .panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
    gap: 8px;
  }
  .panel-title {
    font-size: 14.5px;
    font-weight: 800;
    color: #1B1B1E;
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
  }
  .panel-title svg { width: 16px; height: 16px; stroke: #16697A; fill: none; stroke-width: 2; }
  .panel-badge {
    font-size: 11px;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 999px;
    background: #EAF4F3;
    color: #0E4A57;
  }

  /* Donut Layout */
  .donut-wrap {
    display: flex;
    align-items: center;
    gap: 20px;
  }
  .donut-legend {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 8px;
  }
  .legend-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 12.5px;
    padding: 2px 0;
  }
  .legend-name {
    display: flex;
    align-items: center;
    gap: 7px;
    font-weight: 600;
    color: #374151;
  }
  .legend-dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    flex-shrink: 0;
  }
  .legend-counts {
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .legend-count { font-size: 12px; color: #6E6E73; }
  .legend-pct {
    font-size: 11px;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 4px;
    background: #F7F6F2;
    color: #1B1B1E;
  }

  /* Single-line Clean Table */
  .clean-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: auto;
  }
  .clean-table th {
    font-size: 10.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: #6E6E73;
    padding: 9px 10px;
    border-bottom: 1px solid #E8E6E0;
    background: #FAFAF8;
    text-align: left;
    white-space: nowrap;
  }
  .clean-table td {
    padding: 10px 10px;
    border-bottom: 1px solid #F1F0EC;
    font-size: 12.5px;
    color: #1B1B1E;
    white-space: nowrap;
    vertical-align: middle;
  }
  .clean-table tr:hover td { background: #FAF9F6; }
  .clean-table tr:last-child td { border-bottom: none; }

  /* Pill Statuses */
  .pill-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 999px;
    white-space: nowrap;
  }
  .pill-delivered { background: #ECFDF5; color: #065F46; }
  .pill-processing { background: #EFF6FF; color: #1D4ED8; }
  .pill-pending { background: #FFFBEB; color: #92400E; }
  .pill-shipped { background: #EAF4F3; color: #0E4A57; }
  .pill-cancelled { background: #FEF2F2; color: #991B1B; }

  /* Top Products */
  .top-prod-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 9px 0;
    border-bottom: 1px solid #F4F3EF;
  }
  .top-prod-row:last-child { border-bottom: none; }
  .rank-badge {
    width: 24px;
    height: 24px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 800;
    flex-shrink: 0;
  }
  .rank-1 { background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; }
  .rank-2 { background: #F1F5F9; color: #334155; border: 1px solid #E2E8F0; }
  .rank-3 { background: #FFF7ED; color: #9A3412; border: 1px solid #FED7AA; }
  .rank-n { background: #F7F6F2; color: #6E6E73; }

  .bar-bg {
    flex: 1;
    height: 6px;
    background: #F0EEE9;
    border-radius: 999px;
    overflow: hidden;
  }
  .bar-fg {
    height: 100%;
    background: linear-gradient(90deg, #16697A 0%, #208B9F 100%);
    border-radius: 999px;
  }

  @media (max-width: 1024px) {
    .grid-2 { grid-template-columns: 1fr; }
  }
  @media (max-width: 640px) {
    .dash-head { flex-direction: column; align-items: flex-start; }
    .date-pill { align-self: flex-start; text-align: left; }
  }
</style>

{{-- Dashboard Banner --}}
<div class="dash-head">
  <div>
    <h2>Welcome back, {{ auth()->user()->name ?? 'Administrator' }}</h2>
    <p>Marketplace oversight — real-time sales, order fulfillment, and product movement at a glance.</p>
  </div>
  <div class="date-pill">
    <div>{{ now()->format('l, F d') }}</div>
    <div style="font-size: 11px; opacity: .85; font-weight: 600;">{{ $stats['orders'] }} total orders</div>
  </div>
</div>

{{-- Top 7 Metric Cards --}}
<div class="metrics-grid">
  <div class="metric-card">
    <div class="metric-top">
      <span class="metric-label">Revenue</span>
      <span class="metric-icon mc-emerald">
        <svg viewBox="0 0 24 24"><path d="M12 2v20M17 6.5C17 4.6 14.8 3 12 3S7 4.6 7 6.5c0 4.5 10 4 10 11 0 1.9-2.2 3.5-5 3.5s-5-1.6-5-3.5"/></svg>
      </span>
    </div>
    <div class="metric-val" style="color: #065F46;">₱{{ number_format($stats['revenue'], 2) }}</div>
    <div class="metric-sub">Delivered &amp; settled</div>
  </div>

  <div class="metric-card">
    <div class="metric-top">
      <span class="metric-label">Platform Cut</span>
      <span class="metric-icon mc-teal">
        <svg viewBox="0 0 24 24"><path d="M12 2v20M17 6.5C17 4.6 14.8 3 12 3S7 4.6 7 6.5c0 4.5 10 4 10 11 0 1.9-2.2 3.5-5 3.5s-5-1.6-5-3.5"/></svg>
      </span>
    </div>
    <div class="metric-val" style="color: #0E4A57;">₱{{ number_format($stats['commission_earned'], 2) }}</div>
    <div class="metric-sub">10% commission</div>
  </div>

  <div class="metric-card">
    <div class="metric-top">
      <span class="metric-label">Orders</span>
      <span class="metric-icon mc-blue">
        <svg viewBox="0 0 24 24"><path d="M6 2h12l1 6H5l1-6Z"/><path d="M5 8h14l-1 12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 8Z"/></svg>
      </span>
    </div>
    <div class="metric-val">{{ $stats['orders'] }}</div>
    <div class="metric-sub">{{ $stats['delivered'] }} delivered</div>
  </div>

  <div class="metric-card">
    <div class="metric-top">
      <span class="metric-label">To Action</span>
      <span class="metric-icon mc-amber">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
      </span>
    </div>
    <div class="metric-val" style="{{ $stats['to_action'] > 0 ? 'color: #D97706;' : '' }}">{{ $stats['to_action'] }}</div>
    <div class="metric-sub">Needs review</div>
  </div>

  <div class="metric-card">
    <div class="metric-top">
      <span class="metric-label">To Ship</span>
      <span class="metric-icon mc-indigo">
        <svg viewBox="0 0 24 24"><path d="M9 11a5 5 0 1 0 6 0M12 6v5M4 17l2-1M20 17l-2-1"/></svg>
      </span>
    </div>
    <div class="metric-val">{{ $stats['to_ship'] }}</div>
    <div class="metric-sub">Preparing</div>
  </div>

  <div class="metric-card">
    <div class="metric-top">
      <span class="metric-label">Products</span>
      <span class="metric-icon mc-slate">
        <svg viewBox="0 0 24 24"><path d="M4 7h16v10H4zM9 11h6"/><path d="M7 3l2 4M17 3l-2 4"/></svg>
      </span>
    </div>
    <div class="metric-val" style="{{ $stats['low_stock'] > 0 ? 'color: #DC2626;' : '' }}">{{ $stats['products'] }}</div>
    <div class="metric-sub" style="{{ $stats['low_stock'] > 0 ? 'color: #DC2626; font-weight:600;' : '' }}">{{ $stats['low_stock'] }} low stock</div>
  </div>

  <div class="metric-card">
    <div class="metric-top">
      <span class="metric-label">Rating</span>
      <span class="metric-icon mc-amber">
        <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
      </span>
    </div>
    <div class="metric-val" style="display: flex; align-items: center; gap: 5px;">
      <span>{{ number_format($stats['rating'], 1) }}</span>
      <svg width="15" height="15" viewBox="0 0 24 24" fill="#F59E0B" stroke="#F59E0B" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
    </div>
    <div class="metric-sub">{{ $stats['sellers'] }} active sellers</div>
  </div>
</div>

{{-- Row 1: Charts --}}
<div class="grid-2">
  {{-- Sales Chart --}}
  <div class="panel">
    <div class="panel-head">
      <h3 class="panel-title">
        <svg viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>
        Sales — Last 30 Days
      </h3>
      <span class="panel-badge">Delivered Revenue</span>
    </div>

    @php
      $max = max(1, max(array_column($chart, 'value')));
      $W = 640; $H = 175;
      $padL = 56; $padR = 12; $padT = 12; $padB = 26;
      $plotW = $W - $padL - $padR;
      $plotH = $H - $padT - $padB;
      $n = count($chart);
      $pts = [];
      foreach ($chart as $i => $c) {
        $x = $padL + ($n === 1 ? 0 : ($i / ($n - 1)) * $plotW);
        $y = $padT + $plotH - ($c['value'] / $max) * $plotH;
        $pts[] = [$x, $y, $c];
      }
      $lineCoords = [];
      foreach ($pts as [$x, $y]) { $lineCoords[] = round($x,1).','.round($y,1); }
      $lineStr = implode(' ', $lineCoords);
      $areaStr = $padL.','.($padT + $plotH).' '.$lineStr.' '.($padL + $plotW).','.($padT + $plotH);
    @endphp

    <svg viewBox="0 0 {{ $W }} {{ $H }}" style="width: 100%; height: auto; display: block;" aria-label="Sales last 30 days">
      <defs>
        <linearGradient id="salesGrad" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="#16697A" stop-opacity="0.25"/>
          <stop offset="100%" stop-color="#16697A" stop-opacity="0.0"/>
        </linearGradient>
      </defs>

      {{-- Grid lines --}}
      @foreach ([0, 0.25, 0.5, 0.75, 1] as $g)
        @php
          $gy = $padT + $plotH * $g;
          $val = $max * (1 - $g);
        @endphp
        <line x1="{{ $padL }}" y1="{{ $gy }}" x2="{{ $W - $padR }}" y2="{{ $gy }}" stroke="#F1F0EC" stroke-width="1" stroke-dasharray="3,3"/>
        <text x="{{ $padL - 8 }}" y="{{ $gy + 4 }}" text-anchor="end" font-size="10.5" fill="#9CA3AF" font-weight="600">
          ₱{{ $val >= 1000 ? number_format($val/1000, 1) . 'k' : number_format($val, 0) }}
        </text>
      @endforeach

      {{-- X-Axis dates --}}
      @foreach ($pts as $i => [$x, $y, $c])
        @if ($i % 6 === 0 || $i === $n - 1)
          <text x="{{ $x }}" y="{{ $H - 8 }}" text-anchor="middle" font-size="10" fill="#9CA3AF" font-weight="600">
            {{ $c['label'] }}
          </text>
        @endif
      @endforeach

      {{-- Area fill --}}
      <polygon points="{{ $areaStr }}" fill="url(#salesGrad)"/>

      {{-- Line --}}
      <polyline points="{{ $lineStr }}" fill="none" stroke="#16697A" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>

      {{-- Data points (subtle) --}}
      @foreach ($pts as $i => [$x, $y, $c])
        @if($c['value'] > 0 || $i === $n - 1)
          <circle cx="{{ $x }}" cy="{{ $y }}" r="3" fill="#fff" stroke="#16697A" stroke-width="2"/>
        @endif
      @endforeach
    </svg>
  </div>

  {{-- Orders by Status Donut --}}
  <div class="panel">
    <div class="panel-head">
      <h3 class="panel-title">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 2a10 10 0 0 1 10 10"/></svg>
        Orders by Status
      </h3>
      <span class="panel-badge">{{ $stats['orders'] }} Total</span>
    </div>

    @php
      $donutColors = [
        'delivered' => '#10B981',
        'out_for_delivery' => '#16697A',
        'shipped' => '#0284C7',
        'processing' => '#3B82F6',
        'ready_for_delivery' => '#8B5CF6',
        'pending' => '#F59E0B',
        'new' => '#F59E0B',
        'confirmed' => '#0EA5E9',
        'cancelled' => '#EF4444',
      ];
      $pieTotal = array_sum(array_column($statusStats, 'count'));
      $cx = 75; $cy = 75; $outerR = 64; $innerR = 43;
      $ang = -90; $donutSegments = [];
      foreach ($statusStats as $s) {
        $sweep = $pieTotal > 0 ? ($s['count'] / $pieTotal) * 360 : 0;
        $a1 = $ang * pi() / 180; $a2 = ($ang + $sweep) * pi() / 180;
        $x1 = $cx + $outerR * cos($a1); $y1 = $cy + $outerR * sin($a1);
        $x2 = $cx + $outerR * cos($a2); $y2 = $cy + $outerR * sin($a2);
        $ix2 = $cx + $innerR * cos($a2); $iy2 = $cy + $innerR * sin($a2);
        $ix1 = $cx + $innerR * cos($a1); $iy1 = $cy + $innerR * sin($a1);
        $large = $sweep > 180 ? 1 : 0;
        $color = $donutColors[$s['status']] ?? ($s['color'] ?? '#94A3B8');
        $donutSegments[] = [
          'label' => $s['label'],
          'count' => $s['count'],
          'percent' => $s['percent'],
          'color' => $color,
          'path' => "M {$x1},{$y1} A {$outerR},{$outerR} 0 {$large} 1 {$x2},{$y2} L {$ix2},{$iy2} A {$innerR},{$innerR} 0 {$large} 0 {$ix1},{$iy1} Z",
        ];
        $ang += $sweep;
      }
    @endphp

    <div class="donut-wrap">
      <svg viewBox="0 0 150 150" style="width: 125px; height: 125px; flex-shrink: 0;" aria-label="Status Donut">
        @if ($pieTotal > 0)
          @foreach ($donutSegments as $seg)
            @if($seg['count'] > 0)
              <path d="{{ $seg['path'] }}" fill="{{ $seg['color'] }}" stroke="#fff" stroke-width="1.5" style="transition: transform 0.2s; transform-origin: center;"/>
            @endif
          @endforeach
        @else
          <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $outerR }}" fill="#F3F4F6"/>
          <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $innerR }}" fill="#fff"/>
        @endif
        <text x="{{ $cx }}" y="{{ $cy + 4 }}" text-anchor="middle" font-size="20" font-weight="800" fill="#1B1B1E">{{ $pieTotal }}</text>
        <text x="{{ $cx }}" y="{{ $cy + 17 }}" text-anchor="middle" font-size="9" fill="#9CA3AF" font-weight="700" letter-spacing="0.5">ORDERS</text>
      </svg>

      <div class="donut-legend">
        @forelse ($donutSegments as $s)
          @if($s['count'] > 0)
            <div class="legend-item">
              <span class="legend-name">
                <span class="legend-dot" style="background: {{ $s['color'] }};"></span>
                <span>{{ $s['label'] }}</span>
              </span>
              <span class="legend-counts">
                <span class="legend-count">{{ $s['count'] }}</span>
                <span class="legend-pct">{{ $s['percent'] }}%</span>
              </span>
            </div>
          @endif
        @empty
          <div style="text-align: center; color: #9CA3AF; padding: 20px 0; font-size: 12px;">No orders recorded yet.</div>
        @endforelse
      </div>
    </div>
  </div>
</div>

{{-- Row 2: Recent Orders & Top Products --}}
<div class="grid-2">
  {{-- Recent Orders (Single-line per row, 100% width, no horizontal scroll) --}}
  <div class="panel" style="overflow: hidden;">
    <div class="panel-head">
      <h3 class="panel-title">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/></svg>
        Recent Orders
      </h3>
      <a href="{{ url('/admin/orders') }}" style="font-size: 12px; font-weight: 700; color: #16697A; text-decoration: none;">View all →</a>
    </div>

    <div style="overflow-x: auto;">
      <table class="clean-table">
        <thead>
          <tr>
            <th>Order #</th>
            <th>Buyer</th>
            <th>Store</th>
            <th>Total</th>
            <th>Status</th>
            <th style="text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($recentOrders as $o)
            @php
              $pillCls = match($o->status) {
                'delivered' => 'pill-delivered',
                'processing' => 'pill-processing',
                'shipped', 'out_for_delivery' => 'pill-shipped',
                'cancelled' => 'pill-cancelled',
                default => 'pill-pending',
              };
            @endphp
            <tr>
              <td>
                <a href="{{ url('/admin/orders/'.$o->id) }}" style="font-weight: 700; color: #16697A; text-decoration: none; font-family: monospace;" title="{{ $o->order_number }}">#{{ Str::limit($o->order_number, 14) }}</a>
              </td>
              <td>
                <span style="font-weight: 600;" title="{{ $o->buyer->name ?? 'Guest' }}">{{ Str::limit($o->buyer->name ?? 'Guest', 14) }}</span>
              </td>
              <td>
                <span style="color: #6E6E73;" title="{{ $o->seller->store_name ?? 'Invoiz Store' }}">{{ Str::limit($o->seller->store_name ?? 'Invoiz Store', 14) }}</span>
              </td>
              <td style="font-weight: 700; color: #1B1B1E;">
                ₱{{ number_format((float) ($o->total_amount ?? $o->total), 2) }}
              </td>
              <td>
                <span class="pill-badge {{ $pillCls }}">{{ ucfirst(str_replace('_', ' ', $o->status)) }}</span>
              </td>
              <td style="text-align: right;">
                <a href="{{ url('/admin/orders/'.$o->id) }}" class="btn btn-soft btn-xs" style="padding: 3px 8px; font-weight: 700; font-size: 11.5px; border-radius: 6px; text-decoration: none;">View</a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" style="text-align: center; color: #9CA3AF; padding: 24px 0;">No orders placed yet.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Top Products --}}
  <div class="panel">
    <div class="panel-head">
      <div>
        <h3 class="panel-title">Top Products</h3>
        <p style="font-size: 11.5px; color: #6E6E73; margin: 2px 0 0;">By units sold · this month</p>
      </div>
      <span class="panel-badge">Top 5</span>
    </div>

    @php $maxUnits = $topProducts->max('units') ?: 1; @endphp
    @forelse ($topProducts as $idx => $p)
      <div class="top-prod-row">
        <span class="rank-badge {{ $idx === 0 ? 'rank-1' : ($idx === 1 ? 'rank-2' : ($idx === 2 ? 'rank-3' : 'rank-n')) }}">
          {{ $idx + 1 }}
        </span>
        <div style="flex: 1; min-width: 0;">
          <div style="font-size: 13px; font-weight: 700; color: #1B1B1E; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $p->name }}</div>
          <div style="display: flex; align-items: center; gap: 8px; margin-top: 3px;">
            <div class="bar-bg"><div class="bar-fg" style="width: {{ round(($p->units / $maxUnits) * 100) }}%;"></div></div>
            <span style="font-size: 11px; font-weight: 600; color: #6E6E73; white-space: nowrap;">{{ $p->units }} sold</span>
          </div>
        </div>
        <span style="font-size: 13px; font-weight: 800; color: #0E4A57; white-space: nowrap;">
          ₱{{ number_format($p->sales, 2) }}
        </span>
      </div>
    @empty
      <div style="text-align: center; color: #9CA3AF; padding: 28px 0; font-size: 12.5px;">No product sales recorded yet.</div>
    @endforelse
  </div>
</div>
@endsection
