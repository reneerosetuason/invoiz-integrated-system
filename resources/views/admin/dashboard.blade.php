@extends('admin.layout')

@section('content')
<style>
  .dash-head{ background:linear-gradient(135deg,#16697A 0%,#0E4A57 100%); border-radius:18px; padding:22px 24px; color:#fff; display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:18px; position:relative; overflow:hidden; }
  .dash-head::after{ content:''; position:absolute; right:-30px; top:-30px; width:140px; height:140px; background:rgba(255,255,255,.08); border-radius:50%; }
  .dash-head h2{ font-size:20px; font-weight:800; margin:0; letter-spacing:-.4px; }
  .dash-head p{ font-size:13px; color:rgba(255,255,255,.85); margin:4px 0 0; }
  .dash-head .date{ background:rgba(255,255,255,.14); border:1px solid rgba(255,255,255,.2); padding:8px 14px; border-radius:999px; font-size:12px; font-weight:600; backdrop-filter:blur(6px); }
  .cards{ display:grid; grid-template-columns:repeat(6,1fr); gap:12px; margin-bottom:18px; }
  .sum-card{ background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:14px; position:relative; overflow:hidden; transition:transform .15s, box-shadow .15s; }
  .sum-card:hover{ transform:translateY(-3px); box-shadow:0 10px 24px rgba(16,24,40,.08); }
  .sum-card::before{ content:''; position:absolute; top:0; left:0; right:0; height:3px; }
  .sum-card:nth-child(1)::before{ background:#2E8B57; } .sum-card:nth-child(2)::before{ background:#16697A; } .sum-card:nth-child(3)::before{ background:#F59E0B; }
  .sum-card:nth-child(4)::before{ background:#8B5CF6; } .sum-card:nth-child(5)::before{ background:#0E4A57; } .sum-card:nth-child(6)::before{ background:#F59E0B; }
  .sum-ico{ width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; margin-bottom:10px; }
  .sum-ico svg{ width:18px; height:18px; stroke:currentColor; fill:none; stroke-width:1.8; }
  .c-green{ background:#E6F5EE; color:#2E8B57; } .c-teal{ background:#E6F1F2; color:#16697A; } .c-orange{ background:#FEF3C7; color:#92400e; }
  .c-purple{ background:#F0EBFF; color:#7C3AED; } .c-dark{ background:#E0F2FE; color:#0C4A57; } .c-gold{ background:#FFF7E0; color:#B45309; }
  .sum-label{ font-size:10.5px; letter-spacing:.7px; font-weight:700; color:#6B7280; text-transform:uppercase; }
  .sum-value{ font-size:18px; font-weight:800; letter-spacing:-.4px; margin:4px 0 0; }
  .sum-sub{ font-size:11px; color:#9CA3AF; margin-top:3px; }
  .grid-2{ display:grid; grid-template-columns:1.65fr 1fr; gap:14px; margin-bottom:14px; }
  .panel{ background:#fff; border:1px solid #E5E7EB; border-radius:16px; padding:18px; box-shadow:0 1px 3px rgba(16,24,40,.04); }
  .panel-head{ display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; }
  .panel-title{ font-size:14px; font-weight:800; letter-spacing:-.2px; display:flex; align-items:center; gap:8px; }
  .panel-title svg{ width:16px; height:16px; stroke:#16697A; fill:none; stroke-width:1.8; }
  .panel-sub{ font-size:12px; color:#6B7280; }
  .badge{ background:#E6F5EE; color:#166534; font-size:11px; font-weight:700; padding:4px 10px; border-radius:999px; border:1px solid #BFE3D0; }
  .link{ color:#16697A; font-size:13px; font-weight:700; text-decoration:none; }
  .link:hover{ text-decoration:underline; }
  table{ width:100%; border-collapse:collapse; }
  th{ text-align:left; font-size:11px; letter-spacing:.7px; text-transform:uppercase; color:#9CA3AF; font-weight:700; padding:0 0 10px; border-bottom:1px solid #EEEFF1; }
  td{ padding:10px 0; border-bottom:1px solid #F1F2F4; font-size:13px; }
  tr:last-child td{ border-bottom:none; }
  .pill{ display:inline-flex; align-items:center; gap:5px; font-size:11px; font-weight:700; padding:4px 10px; border-radius:999px; border:1px solid; }
  .pill-pending{ background:#FFFBEB; color:#92400e; border-color:#FDE68A; } .pill-pending .dot{ background:#F59E0B; }
  .pill-processing{ background:#EFF6FF; color:#1D4ED8; border-color:#BFDBFE; } .pill-processing .dot{ background:#3B82F6; }
  .pill-delivered{ background:#ECFDF5; color:#065F46; border-color:#A7F3D0; } .pill-delivered .dot{ background:#10B981; }
  .pill-shipped{ background:#F5F3FF; color:#5B21B6; border-color:#DDD6FE; } .pill-shipped .dot{ background:#8B5CF6; }
  .pill-cancelled{ background:#FEF2F2; color:#991B1B; border-color:#FECACA; } .pill-cancelled .dot{ background:#EF4444; }
  .dot{ width:6px; height:6px; border-radius:50%; display:inline-block; }
  .order-no{ color:#16697A; font-weight:700; font-family:monospace; font-size:12.5px; }
  .donut-wrap{ display:flex; align-items:center; gap:18px; }
  .legend{ flex:1; }
  .legend-row{ display:flex; align-items:center; justify-content:space-between; padding:6px 0; font-size:13px; border-bottom:1px solid #F9FAFB; }
  .legend-row:last-child{ border-bottom:none; }
  .top-prod{ display:flex; align-items:center; gap:12px; padding:10px 0; border-bottom:1px solid #F1F2F4; }
  .top-prod:last-child{ border-bottom:none; }
  .rank{ width:28px; height:28px; border-radius:8px; background:#F3F4F6; color:#374151; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:12px; flex-shrink:0; }
  .rank-1{ background:linear-gradient(135deg,#F59E0B 0%,#FBBF24 100%); color:#fff; } .rank-2{ background:#E5E7EB; color:#374151; } .rank-3{ background:#FDE68A; color:#92400e; }
  .bar{ flex:1; height:6px; background:#F3F4F6; border-radius:999px; overflow:hidden; }
  .bar-fill{ height:100%; background:linear-gradient(90deg,#16697A 0%,#1A9CB0 100%); border-radius:999px; }
  @media(max-width:1000px){ .cards{ grid-template-columns:repeat(3,1fr); } }
  @media(max-width:1024px){ .grid-2{ grid-template-columns:1fr; } }
  @media(max-width:640px){ .cards{ grid-template-columns:repeat(2,1fr); } .dash-head{ flex-direction:column; align-items:flex-start; } }
</style>

<div class="dash-head">
  <div>
    <h2>Welcome back, {{ auth()->user()->name ?? 'Admin' }}</h2>
    <p>Here's what's happening with your marketplace today — revenue, orders, and top products at a glance.</p>
  </div>
  <div class="date">
    <div style="font-weight:700;">{{ now()->format('l, F d') }}</div>
    <div style="font-size:11px; opacity:.9;">{{ $stats['orders'] }} orders total</div>
  </div>
</div>

<div class="cards">
  <div class="sum-card">
    <div class="sum-ico c-green"><svg viewBox="0 0 24 24"><path d="M12 2v20M17 6.5C17 4.6 14.8 3 12 3S7 4.6 7 6.5c0 4.5 10 4 10 11 0 1.9-2.2 3.5-5 3.5s-5-1.6-5-3.5"/></svg></div>
    <div class="sum-label">Revenue</div>
    <div class="sum-value" style="color:#065F46;">₱{{ number_format($stats['revenue'], 2) }}</div>
    <div class="sum-sub">Delivered orders · exact</div>
  </div>
  <div class="sum-card">
    <div class="sum-ico c-teal"><svg viewBox="0 0 24 24"><path d="M6 2h12l1 6H5l1-6Z"/><path d="M5 8h14l-1 12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 8Z"/></svg></div>
    <div class="sum-label">Orders</div>
    <div class="sum-value">{{ $stats['orders'] }}</div>
    <div class="sum-sub">{{ $stats['delivered'] }} delivered</div>
  </div>
  <div class="sum-card">
    <div class="sum-ico c-orange"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg></div>
    <div class="sum-label">To Action</div>
    <div class="sum-value">{{ $stats['to_action'] }}</div>
    <div class="sum-sub">Needs your review</div>
  </div>
  <div class="sum-card">
    <div class="sum-ico c-purple"><svg viewBox="0 0 24 24"><path d="M9 11a5 5 0 1 0 6 0M12 6v5M4 17l2-1M20 17l-2-1"/></svg></div>
    <div class="sum-label">To Ship</div>
    <div class="sum-value">{{ $stats['to_ship'] }}</div>
    <div class="sum-sub">Ready to ship</div>
  </div>
  <div class="sum-card">
    <div class="sum-ico c-dark"><svg viewBox="0 0 24 24"><path d="M4 7h16v10H4zM9 11h6"/><path d="M7 3l2 4M17 3l-2 4"/></svg></div>
    <div class="sum-label">Products</div>
    <div class="sum-value" style="{{ $stats['low_stock'] > 0 ? 'color:#DC2626;' : '' }}">{{ $stats['products'] }}</div>
    <div class="sum-sub" style="{{ $stats['low_stock'] > 0 ? 'color:#DC2626; font-weight:600;' : '' }}">{{ $stats['low_stock'] }} low stock</div>
  </div>
  <div class="sum-card">
    <div class="sum-ico c-gold"><svg viewBox="0 0 24 24"><path d="M12 3l2.4 4.9 5.4.8-3.9 3.8.9 5.4-4.8-2.5-4.8 2.5.9-5.4L4.2 8.7l5.4-.8L12 3Z"/></svg></div>
    <div class="sum-label">Rating</div>
    <div class="sum-value">{{ number_format($stats['rating'], 1) }} <span style="font-size:12px; color:#F59E0B;display:inline-flex;"><x-ui-icon name="star" :size="13" /></span></div>
    <div class="sum-sub">{{ $stats['sellers'] }} active sellers</div>
  </div>
</div>

<div class="grid-2">
  <div class="panel">
    <div class="panel-head">
      <h3 class="panel-title"><svg viewBox="0 0 24 24"><path d="M3 3h18v18H3z"/><path d="M3 9h18M9 21V9"/></svg> Sales — Last 30 Days</h3>
      <span style="background:#ECFDF5; color:#065F46; font-size:11px; font-weight:700; padding:4px 10px; border-radius:999px; border:1px solid #A7F3D0;">Delivered</span>
    </div>
    @php
      $max = max(1, max(array_column($chart, 'value')));
      $W = 640; $H = 170; $padL = 48; $padR = 10; $padT = 10; $padB = 24;
      $plotW = $W - $padL - $padR; $plotH = $H - $padT - $padB;
      $n = count($chart); $pts = [];
      foreach ($chart as $i => $c) {
        $x = $padL + ($n === 1 ? 0 : ($i / ($n - 1)) * $plotW);
        $y = $padT + $plotH - ($c['value'] / $max) * $plotH;
        $pts[] = [$x, $y, $c];
      }
    @endphp
    <svg viewBox="0 0 {{ $W }} {{ $H }}" style="width:100%;height:auto;display:block;">
      @foreach ([0, .25, .5, .75, 1] as $g)
        @php $gy = $padT + $plotH * $g; $val = $max * (1 - $g); @endphp
        <line x1="{{ $padL }}" y1="{{ $gy }}" x2="{{ $W - $padR }}" y2="{{ $gy }}" stroke="#F3F4F6" stroke-width="1"/>
        <text x="{{ $padL - 8 }}" y="{{ $gy + 4 }}" text-anchor="end" font-size="11" fill="#9CA3AF">₱{{ $val >= 1000 ? number_format($val/1000,1).'k' : number_format($val,0) }}</text>
      @endforeach
      @foreach ($pts as $i => [$x, $y, $c])
        @if ($i % 5 === 0 || $i === $n - 1)
          <text x="{{ $x }}" y="{{ $H - 8 }}" text-anchor="middle" font-size="11" fill="#9CA3AF">{{ $c['label'] }}</text>
        @endif
      @endforeach
      <polyline points="@foreach ($pts as [$x, $y]){{ $x }},{{ $y }} @endforeach" fill="none" stroke="#16697A" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
      @foreach ($pts as [$x, $y])<circle cx="{{ $x }}" cy="{{ $y }}" r="3.5" fill="#fff" stroke="#16697A" stroke-width="2"/>@endforeach
    </svg>
  </div>

  <div class="panel">
    <div class="panel-head"><h3 class="panel-title"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg> Orders by Status</h3></div>
    @php
      $pieTotal = array_sum(array_column($statusStats, 'count'));
      $cx = 80; $cy = 82; $R = 62;
      $ang = -90; $segments = [];
      foreach ($statusStats as $s) {
        $sweep = $pieTotal > 0 ? ($s['count'] / $pieTotal) * 360 : 0;
        $a1 = $ang * pi() / 180; $a2 = ($ang + $sweep) * pi() / 180;
        $x1 = $cx + $R * cos($a1); $y1 = $cy + $R * sin($a1);
        $x2 = $cx + $R * cos($a2); $y2 = $cy + $R * sin($a2);
        $large = $sweep > 180 ? 1 : 0;
        $segments[] = ['color' => $s['color'], 'path' => "M {$cx},{$cy} L {$x1},{$y1} A {$R},{$R} 0 {$large} 1 {$x2},{$y2} Z"];
        $ang += $sweep;
      }
    @endphp
    <div style="display:flex; align-items:center; gap:18px;">
      <svg viewBox="0 0 160 165" style="width:118px;height:122px; flex-shrink:0;">
        @if ($pieTotal > 0)
          @foreach ($segments as $seg)
            <path d="{{ $seg['path'] }}" fill="{{ $seg['color'] }}" style="transition:transform .15s; transform-origin:center;"/>
          @endforeach
        @else
          <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $R }}" fill="#F3F4F6"/>
        @endif
        <text x="{{ $cx }}" y="{{ $cy + 6 }}" text-anchor="middle" font-size="22" font-weight="800" fill="#1A202C">{{ $pieTotal }}</text>
        <text x="{{ $cx }}" y="{{ $cy + 20 }}" text-anchor="middle" font-size="10" fill="#9CA3AF" font-weight="600">TOTAL ORDERS</text>
      </svg>
      <div class="legend" style="flex:1;">
        @forelse ($statusStats as $s)
          <div class="legend-row">
            <span style="display:flex; align-items:center; gap:8px;"><span style="width:10px; height:10px; border-radius:3px; background:{{ $s['color'] }}; display:inline-block;"></span><span style="font-weight:600; color:#374151;">{{ $s['label'] }}</span></span>
            <span><span style="color:#6B7280;">{{ $s['count'] }}</span><span style="font-weight:800; margin-left:8px; color:#1A202C;">{{ $s['percent'] }}%</span></span>
          </div>
        @empty
          <div style="text-align:center; color:#9CA3AF; padding:20px 0;">No orders.</div>
        @endforelse
      </div>
    </div>
  </div>
</div>

<div class="grid-2">
  <div class="panel">
    <div class="panel-head">
      <h3 class="panel-title"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/></svg> Recent Orders</h3>
      <a class="link" href="/admin/orders">View all →</a>
    </div>
    <table>
      <thead><tr><th>Order</th><th>Buyer</th><th>Store</th><th>Total</th><th>Status</th></tr></thead>
      <tbody>
        @forelse ($recentOrders as $o)
          <tr>
            <td><span class="order-no">#{{ $o->order_number }}</span><div style="font-size:11px; color:#9CA3AF;">{{ $o->created_at->format('M d') }}</div></td>
            <td>
              <div style="display:flex; align-items:center; gap:8px;">
                <span style="width:28px; height:28px; border-radius:50%; background:#E6F1F2; color:#16697A; display:inline-flex; align-items:center; justify-content:center; font-weight:700; font-size:11px;">{{ strtoupper(mb_substr($o->buyer->name ?? 'G',0,1)) }}</span>
                <span style="font-weight:600; font-size:13px;">{{ Str::limit($o->buyer->name ?? 'Guest', 12) }}</span>
              </div>
            </td>
            <td style="font-size:13px; color:#374151;">{{ Str::limit($o->seller->store_name ?? '—', 14) }}</td>
            <td style="font-weight:700; font-size:13px;">₱{{ number_format($o->total, 2) }}</td>
            <td><span class="pill pill-{{ $o->status }}"><span class="dot"></span> {{ ucfirst($o->status) }}</span></td>
          </tr>
        @empty
          <tr><td colspan="5" style="text-align:center; padding:24px; color:#9CA3AF;">No orders yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="panel">
    <div class="panel-head">
      <div>
        <h3 class="panel-title">Top Products</h3>
        <div style="font-size:11px; color:#6B7280; margin-top:2px;">By units sold · this month</div>
      </div>
      <span style="background:#F3F4F6; color:#374151; font-size:11px; font-weight:700; padding:4px 10px; border-radius:999px;">Top 5</span>
    </div>
    @php $maxUnits = $topProducts->max('units') ?: 1; @endphp
    @forelse ($topProducts as $idx => $p)
      <div class="top-prod">
        <span class="rank {{ $idx===0 ? 'rank-1' : ($idx===1 ? 'rank-2' : ($idx===2 ? 'rank-3' : '')) }}">{{ $idx+1 }}</span>
        <div style="flex:1; min-width:0;">
          <div style="font-size:13px; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $p->name }}</div>
          <div style="display:flex; align-items:center; gap:8px; margin-top:4px;">
            <div class="bar" style="flex:1; max-width:120px;"><div class="bar-fill" style="width:{{ round(($p->units / $maxUnits)*100) }}%;"></div></div>
            <span style="font-size:11px; color:#6B7280; font-weight:600;">{{ $p->units }} sold</span>
          </div>
        </div>
        <span style="font-size:13px; font-weight:800; color:#16697A;">₱{{ number_format($p->sales, 2) }}</span>
      </div>
    @empty
      <div style="text-align:center; padding:24px; color:#9CA3AF;">No sales yet.</div>
    @endforelse
  </div>
</div>
@endsection
