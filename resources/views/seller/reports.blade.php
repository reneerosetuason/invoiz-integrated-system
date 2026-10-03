@extends('seller.layout')

@section('content')
<style>
  .cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
  .sum-card { background: #fff; border: 1px solid #E5E5E5; border-radius: 16px; padding: 18px; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
  .sum-label { font-size: 11px; letter-spacing: 1.4px; font-weight: 700; color: #6B7280; text-transform: uppercase; }
  .sum-value { font-size: 22px; font-weight: 800; margin-top: 6px; }
  .grid-2 { display: grid; grid-template-columns: 1.6fr 1fr; gap: 16px; margin-bottom: 24px; }
  .panel { background: #fff; border: 1px solid #E5E5E5; border-radius: 16px; padding: 20px; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
  .panel-title { font-size: 16px; font-weight: 700; margin: 0 0 16px; letter-spacing: -.3px; }
  .top-row { display: flex; align-items: center; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #F1F2F4; }
  .top-row:last-child { border-bottom: none; }
  .top-name { font-weight: 600; font-size: 14px; color: #374151; }
  .top-meta { font-size: 13px; color: #6B7280; }
  .mon { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #F1F2F4; font-size: 14px; }
  .mon:last-child { border-bottom: none; }
  .mon b { color: #1E7A43; }
  @media (max-width: 1024px) { .cards { grid-template-columns: repeat(2,1fr); } .grid-2 { grid-template-columns: 1fr; } }
</style>

<div class="cards">
  <div class="sum-card"><div class="sum-label">Revenue (Delivered)</div><div class="sum-value" style="color:#2E8B57">₱{{ number_format($revenue, 2) }}</div></div>
  <div class="sum-card"><div class="sum-label">Total Orders</div><div class="sum-value">{{ $ordersCount }}</div></div>
  <div class="sum-card"><div class="sum-label">Avg. Order Value</div><div class="sum-value">₱{{ number_format($avgOrder, 2) }}</div></div>
  <div class="sum-card"><div class="sum-label">Commission</div><div class="sum-value">₱{{ number_format($commission, 2) }}</div></div>
</div>

<div class="grid-2">
  <div class="panel">
    <h3 class="panel-title">Sales — Last 30 Days</h3>
    @php
      $max = max(1, max(array_column($chart, 'value')));
      $W = 640; $H = 200; $padL = 48; $padR = 10; $padT = 12; $padB = 28;
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
        <line x1="{{ $padL }}" y1="{{ $gy }}" x2="{{ $W - $padR }}" y2="{{ $gy }}" stroke="#EEF0F2" stroke-width="1"/>
        <text x="{{ $padL - 8 }}" y="{{ $gy + 4 }}" text-anchor="end" font-size="11" fill="#9CA3AF">₱{{ $val >= 1000 ? number_format($val, 0) : number_format($val, $val == (int)$val ? 0 : 1) }}</text>
      @endforeach
      @foreach ($pts as $i => [$x, $y, $c])
        @if ($i % 5 === 0 || $i === $n - 1)
          <text x="{{ $x }}" y="{{ $H - 8 }}" text-anchor="middle" font-size="11" fill="#9CA3AF">{{ $c['label'] }}</text>
        @endif
      @endforeach
      <polyline points="@foreach ($pts as [$x, $y]){{ $x }},{{ $y }} @endforeach" fill="none" stroke="#116B78" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
      @foreach ($pts as [$x, $y])<circle cx="{{ $x }}" cy="{{ $y }}" r="3.5" fill="#fff" stroke="#116B78" stroke-width="2"/>@endforeach
    </svg>
  </div>

  <div class="panel">
    <h3 class="panel-title">Top Products</h3>
    @forelse ($topProducts as $p)
      <div class="top-row">
        <span class="top-name">{{ $p->name }}</span>
        <span class="top-meta">{{ $p->units }} units · ₱{{ number_format($p->sales, 2) }}</span>
      </div>
    @empty
      <div style="text-align:center;color:#9CA3AF;padding:40px 0;">No sales yet.</div>
    @endforelse
  </div>
</div>

<div class="panel">
  <h3 class="panel-title">Monthly Sales</h3>
  @forelse ($monthly as $m)
    <div class="mon">
      <span>{{ \Carbon\Carbon::createFromFormat('Y-m', $m->month)->format('F Y') }}</span>
      <span>{{ $m->orders }} orders · <b>₱{{ number_format($m->sales, 2) }}</b></span>
    </div>
  @empty
    <div style="text-align:center;color:#9CA3AF;padding:30px 0;">No sales recorded yet.</div>
  @endforelse
</div>
@endsection