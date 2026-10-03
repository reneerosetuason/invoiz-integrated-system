@extends('seller.layout')

@section('content')
<style>
  .cards { display: grid; grid-template-columns: repeat(6, 1fr); gap: 16px; margin-bottom: 24px; }
  .sum-card { background: #fff; border: 1px solid #E5E5E5; border-radius: 16px; padding: 18px; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
  .sum-ico { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-bottom: 14px; }
  .sum-ico svg { width: 20px; height: 20px; stroke: currentColor; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
  .sum-label { font-size: 11px; letter-spacing: 1.4px; font-weight: 700; color: #6B7280; text-transform: uppercase; }
  .sum-value { font-size: 24px; font-weight: 800; letter-spacing: -.6px; margin: 6px 0 4px; }
  .sum-sub { font-size: 12px; color: #6B7280; }
  .sum-sub.danger { color: #D64545; font-weight: 600; }
  .sum-sub.ok { color: #2E8B57; font-weight: 600; }
  .c-teal { background: #E6F1F2; color: #116B78; }
  .c-green { background: #E6F5EE; color: #2E8B57; }
  .c-orange { background: #FFF3E0; color: #E07B00; }
  .c-purple { background: #F0EBFF; color: #8B5CF6; }
  .c-blue { background: #E3EFFF; color: #3B82F6; }
  .c-gold { background: #FFF7E0; color: #F5A623; }

  .grid-2 { display: grid; grid-template-columns: 1.6fr 1fr; gap: 16px; margin-bottom: 24px; }
  .panel { background: #fff; border: 1px solid #E5E5E5; border-radius: 16px; padding: 20px; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
  .panel-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
  .panel-title { font-size: 16px; font-weight: 700; margin: 0; letter-spacing: -.3px; }
  .panel-sub { font-size: 12px; color: #6B7280; }
  .badge-green { background: #E6F5EE; color: #1E7A43; font-size: 11px; font-weight: 700; padding: 5px 12px; border-radius: 999px; }
  .link-teal { color: #116B78; font-size: 13px; font-weight: 600; }
  .link-teal:hover { color: #0C555F; }

  .pill { display: inline-block; font-size: 11px; font-weight: 700; padding: 4px 12px; border-radius: 999px; }
  .pill-pending { background: #FFF3E0; color: #C77700; }
  .pill-processing { background: #E3EFFF; color: #1D4ED8; }
  .pill-delivered { background: #E6F5EE; color: #1E7A43; }
  .pill-shipped { background: #F0EBFF; color: #6D3FD1; }
  .pill-cancelled { background: #FDECEC; color: #B3261E; }

  table { width: 100%; border-collapse: collapse; }
  th { text-align: left; font-size: 11px; letter-spacing: 1px; text-transform: uppercase; color: #9CA3AF; font-weight: 700; padding: 0 0 10px; border-bottom: 1px solid #EEEFF1; }
  td { padding: 12px 0; border-bottom: 1px solid #F1F2F4; font-size: 14px; }
  tr:last-child td { border-bottom: none; }
  .order-no { color: #116B78; font-weight: 700; }
  .order-date { color: #6B7280; font-size: 13px; }

  .donut-wrap { display: flex; align-items: center; gap: 28px; flex-wrap: wrap; }
  .legend { flex: 1; min-width: 140px; }
  .legend-row { display: flex; align-items: center; justify-content: space-between; padding: 7px 0; font-size: 13px; }
  .legend-row .dot { width: 10px; height: 10px; border-radius: 3px; display: inline-block; margin-right: 8px; }
  .legend-row .lbl { color: #374151; }
  .legend-row .cnt { color: #6B7280; }
  .legend-row .pct { font-weight: 700; margin-left: 10px; }

  .empty { text-align: center; color: #9CA3AF; padding: 36px 0; font-size: 14px; }
  .top-prod-row { display: flex; align-items: center; justify-content: space-between; padding: 11px 0; border-bottom: 1px solid #F1F2F4; }
  .top-prod-row:last-child { border-bottom: none; }
  .top-prod-name { font-size: 14px; font-weight: 600; color: #374151; }
  .top-prod-units { font-size: 13px; color: #6B7280; }

  @media (max-width: 1400px) { .cards { grid-template-columns: repeat(3, 1fr); } }
  @media (max-width: 1024px) { .grid-2 { grid-template-columns: 1fr; } }
  @media (max-width: 640px) { .cards { grid-template-columns: repeat(2, 1fr); } }
</style>

<div class="cards">
  <div class="sum-card">
    <div class="sum-ico c-green"><svg viewBox="0 0 24 24"><path d="M12 2v20M17 6.5C17 4.6 14.8 3 12 3S7 4.6 7 6.5c0 4.5 10 4 10 11 0 1.9-2.2 3.5-5 3.5s-5-1.6-5-3.5"/></svg></div>
    <div class="sum-label">Revenue</div>
    <div class="sum-value">₱{{ number_format($revenue, 2) }}</div>
    <div class="sum-sub ok">Today: ₱{{ number_format($todayRevenue, 2) }}</div>
  </div>

  <div class="sum-card">
    <div class="sum-ico c-teal"><svg viewBox="0 0 24 24"><path d="M6 2h12l1 6H5l1-6Z"/><path d="M5 8h14l-1 12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 8Z"/></svg></div>
    <div class="sum-label">Orders</div>
    <div class="sum-value">{{ $totalOrders }}</div>
    <div class="sum-sub">{{ $delivered }} delivered</div>
  </div>

  <div class="sum-card">
    <div class="sum-ico c-orange"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg></div>
    <div class="sum-label">To Action</div>
    <div class="sum-value">{{ $toAction }}</div>
    <div class="sum-sub">Pending / new orders</div>
  </div>

  <div class="sum-card">
    <div class="sum-ico c-purple"><svg viewBox="0 0 24 24"><path d="M9 11a5 5 0 1 0 6 0M12 6v5M4 17l2-1M20 17l-2-1"/></svg></div>
    <div class="sum-label">To Ship</div>
    <div class="sum-value">{{ $toShip }}</div>
    <div class="sum-sub">Processing / ready orders</div>
  </div>

  <div class="sum-card">
    <div class="sum-ico c-teal"><svg viewBox="0 0 24 24"><path d="M4 7h16v10H4zM9 11h6"/><path d="M7 3l2 4M17 3l-2 4"/></svg></div>
    <div class="sum-label">Products</div>
    <div class="sum-value">{{ $productsCount }}</div>
    <div class="sum-sub {{ $lowStock > 0 ? 'danger' : '' }}">{{ $lowStock }} low stock</div>
  </div>

  <div class="sum-card">
    <div class="sum-ico c-gold"><svg viewBox="0 0 24 24"><path d="M12 3l2.4 4.9 5.4.8-3.9 3.8.9 5.4-4.8-2.5-4.8 2.5.9-5.4L4.2 8.7l5.4-.8L12 3Z"/></svg></div>
    <div class="sum-label">Rating</div>
    <div class="sum-value">{{ number_format($rating, 1) }} /5</div>
    <div style="color:#F5A623;font-size:15px;letter-spacing:2px;display:flex;gap:2px;">@for($i = 0; $i < round($rating); $i++)<x-ui-icon name="star" :size="15" />@endfor</div>
  </div>
</div>

<div class="grid-2">
  <div class="panel">
    <div class="panel-head">
      <h3 class="panel-title">Sales — Last 30 Days</h3>
      <span class="badge-green">Delivered orders</span>
    </div>
    @php
      $max = max(1, max(array_column($chart, 'value')));
      $W = 640; $H = 200; $padL = 48; $padR = 10; $padT = 12; $padB = 28;
      $plotW = $W - $padL - $padR; $plotH = $H - $padT - $padB;
      $n = count($chart);
      $pts = [];
      foreach ($chart as $i => $c) {
        $x = $padL + ($n === 1 ? 0 : ($i / ($n - 1)) * $plotW);
        $y = $padT + $plotH - ($c['value'] / $max) * $plotH;
        $pts[] = [$x, $y, $c];
      }
    @endphp
    <svg viewBox="0 0 {{ $W }} {{ $H }}" style="width:100%;height:auto;display:block;">
      @foreach ([0, .25, .5, .75, 1] as $g)
        @php $gy = $padT + $plotH * $g; $val = $max * (1 - $g); @endphp
        <line x1="{{ $padL }}" y1="{{ $gy }}" x2="{{ $W - $padR }}" y2="{{ $gy }}" stroke="#EEF0F2" stroke-width="1"/>
        <text x="{{ $padL - 8 }}" y="{{ $gy + 4 }}" text-anchor="end" font-size="11" fill="#9CA3AF">₱{{ $val >= 1000 ? number_format($val, 0) : number_format($val, $val == (int)$val ? 0 : 1) }}</text>
      @endforeach
      @foreach ($pts as $i => [$x, $y, $c])
        @if ($i % 5 === 0 || $i === $n - 1)
          <text x="{{ $x }}" y="{{ $H - 8 }}" text-anchor="middle" font-size="11" fill="#9CA3AF">{{ $c['label'] }}</text>
        @endif
      @endforeach
      <polyline points="@foreach ($pts as [$x, $y]){{ $x }},{{ $y }} @endforeach" fill="none" stroke="#116B78" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
      @foreach ($pts as [$x, $y])
        <circle cx="{{ $x }}" cy="{{ $y }}" r="3.5" fill="#fff" stroke="#116B78" stroke-width="2"/>
      @endforeach
    </svg>
  </div>

  <div class="panel">
    <div class="panel-head"><h3 class="panel-title">Orders by Status</h3></div>
    @php
      $pieTotal = array_sum(array_column($statusStats, 'count'));
      $cx = 80; $cy = 82; $R = 62;
      $ang = -90; // start at 12 o'clock
      $segments = [];
      foreach ($statusStats as $s) {
        $sweep = $pieTotal > 0 ? ($s['count'] / $pieTotal) * 360 : 0;
        $a1 = $ang * pi() / 180;
        $a2 = ($ang + $sweep) * pi() / 180;
        $x1 = $cx + $R * cos($a1); $y1 = $cy + $R * sin($a1);
        $x2 = $cx + $R * cos($a2); $y2 = $cy + $R * sin($a2);
        $large = $sweep > 180 ? 1 : 0;
        $segments[] = [
          'color' => $s['color'],
          'path' => "M {$cx},{$cy} L {$x1},{$y1} A {$R},{$R} 0 {$large} 1 {$x2},{$y2} Z",
        ];
        $ang += $sweep;
      }
    @endphp
    <div class="donut-wrap">
      <svg viewBox="0 0 160 165" style="width:150px;height:155px;">
        @if ($pieTotal > 0)
          @foreach ($segments as $seg)
            <path d="{{ $seg['path'] }}" fill="{{ $seg['color'] }}"/>
          @endforeach
        @else
          <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $R }}" fill="#F1F2F4"/>
        @endif
        <text x="{{ $cx }}" y="{{ $cy + 6 }}" text-anchor="middle" font-size="24" font-weight="800" fill="#1A202C">{{ $pieTotal }}</text>
        <text x="{{ $cx }}" y="{{ $cy + 22 }}" text-anchor="middle" font-size="11" fill="#9CA3AF">orders</text>
      </svg>
      <div class="legend">
        @forelse ($statusStats as $s)
          <div class="legend-row">
            <span><span class="dot" style="background:{{ $s['color'] }}"></span><span class="lbl">{{ $s['label'] }}</span></span>
            <span><span class="cnt">{{ $s['count'] }}</span><span class="pct">{{ $s['percent'] }}%</span></span>
          </div>
        @empty
          <div class="empty">No orders yet.</div>
        @endforelse
      </div>
    </div>
  </div>
</div>

<div class="grid-2">
  <div class="panel">
    <div class="panel-head">
      <h3 class="panel-title">Recent Orders</h3>
      <a class="link-teal" href="/seller/orders">View all</a>
    </div>
    <table>
      <thead>
        <tr><th>Order</th><th>Buyer</th><th>Total</th><th>Status</th><th>Date</th></tr>
      </thead>
      <tbody>
        @forelse ($recentOrders as $o)
          <tr>
            <td class="order-no">#{{ $o->id }}</td>
            <td>{{ $o->buyer->name ?? 'Guest' }}</td>
            <td>₱{{ number_format($o->total, 2) }}</td>
            <td><span class="pill pill-{{ $o->status }}">{{ ucfirst($o->status) }}</span></td>
            <td class="order-date">{{ $o->created_at->format('M d, g:i A') }}</td>
          </tr>
        @empty
          <tr><td colspan="5"><div class="empty">No orders yet.</div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="panel">
    <div class="panel-head">
      <div>
        <h3 class="panel-title">Top Products</h3>
        <div class="panel-sub">By units sold</div>
      </div>
    </div>
    @forelse ($topProducts as $p)
      <div class="top-prod-row">
        <span class="top-prod-name">{{ $p->name }}</span>
        <span class="top-prod-units">{{ $p->units }} sold</span>
      </div>
    @empty
      <div class="empty">No sales yet.</div>
    @endforelse
  </div>
</div>
@endsection