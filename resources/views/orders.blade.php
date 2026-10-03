@extends('layouts.website')
@section('content')
@php
  $activeTab = $activeTab ?? 'all';
  $q = $q ?? '';
  // Mobile-app status labels + colors.
  $tabDefs = [
    'all' => 'All',
    'pending' => 'To Ship',
    'out_for_delivery' => 'In Transit',
    'delivered' => 'Delivered',
    'cancelled' => 'Cancelled',
  ];
  $pillFor = function($status){
    return match($status) {
      'delivered' => ['Delivered', '#ecfdf5', '#065f46', '#a7f3d0'],
      'cancelled' => ['Cancelled', '#fef2f2', '#991b1b', '#fecaca'],
      'out_for_delivery' => ['In Transit', '#e0f2f1', '#0E4A57', '#99d5cf'],
      'confirmed','processing','ready_for_delivery' => ['Processing', '#fef3c7', '#92400e', '#fde68a'],
      default => ['To Ship', '#fef3c7', '#92400e', '#fde68a'],
    };
  };
@endphp
<h2 style="font-weight:800">My Orders</h2>
<form method="GET" action="{{ url('/orders') }}" style="display:flex;gap:8px;margin:12px 0">
  <input type="hidden" name="tab" value="{{ $activeTab }}">
  <input type="text" name="q" value="{{ $q }}" placeholder="Search orders by product..." style="flex:1;padding:9px 14px;border:1px solid var(--border);border-radius:999px;font-size:12.5px;font-family:inherit;outline:none">
  <button type="submit" style="padding:9px 18px;border-radius:999px;background:var(--green);color:#fff;border:none;font-weight:700;font-size:12.5px;cursor:pointer;font-family:inherit">Search</button>
  @if($q !== '')<a href="{{ url('/orders?tab='.$activeTab) }}" style="padding:9px 14px;font-size:12.5px;color:var(--text3);text-decoration:none;font-weight:600">Clear</a>@endif
</form>
<div style="display:flex;gap:6px;margin:0 0 12px;flex-wrap:wrap">
  @foreach($tabDefs as $key => $label)
    <a href="{{ url('/orders?tab='.$key.($q !== '' ? '&q='.urlencode($q) : '')) }}" style="padding:8px 16px;border-radius:999px;text-decoration:none;font-weight:{{ $activeTab===$key ? '700' : '500' }};font-size:12.5px;border:1px solid {{ $activeTab===$key ? 'var(--primary-dark,#0E4A57)' : 'var(--border)' }};background:{{ $activeTab===$key ? 'var(--primary-dark,#0E4A57)' : '#fff' }};color:{{ $activeTab===$key ? '#fff' : 'var(--text2)' }}">{{ $label }}</a>
  @endforeach
</div>
@if(session('success'))<div style="background:#ecfdf5;color:#065f46;padding:10px;border-radius:8px;border:1px solid #a7f3d0;margin:10px 0;font-size:13px;font-weight:600">{{ session('success') }}</div>@endif
@if(session('error'))<div style="background:#fef2f2;color:#991b1b;padding:10px;border-radius:8px;border:1px solid #fecaca;margin:10px 0;font-size:13px;font-weight:600">{{ session('error') }}</div>@endif

@if($orders->isEmpty())
  <div style="padding:60px 20px;text-align:center">
    <div style="width:64px;height:64px;background:#f0fdf4;border-radius:50%;display:grid;place-items:center;margin:0 auto 12px;color:var(--green)"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16.5 9.4 7.55 4.24"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.29 7 12 12 20.71 7"/><line x1="12" y1="22" x2="12" y2="12"/></svg></div>
    <div style="font-weight:700;font-size:14px">No orders yet</div>
    <div style="color:var(--text3);font-size:12px;margin-top:4px">Your orders will appear here after you buy.</div>
    <a href="{{ url('/') }}" style="display:inline-block;margin-top:14px;padding:9px 16px;background:var(--green);color:#fff;border-radius:999px;text-decoration:none;font-weight:700;font-size:13px">Shop now</a>
  </div>
@else
  <div style="color:var(--text3);font-size:12px;margin-bottom:12px">{{ $orders->count() }} order(s)</div>
  @foreach($orders as $o)
    @php
      [$pillLabel, $pillBg, $pillFg, $pillBd] = $pillFor($o->status);
    @endphp
    <a href="{{ url('/orders/'.$o->id) }}" style="display:block;background:#fff;border:1px solid var(--border);border-radius:16px;padding:14px;margin-bottom:10px;text-decoration:none;color:inherit;transition:box-shadow .15s" onmouseover="this.style.boxShadow='0 4px 16px rgba(0,0,0,.10)'" onmouseout="this.style.boxShadow='none'">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <div>
          @php
            $oNames = $o->items->take(2)->pluck('product_name')->all();
            $oRest = $o->items->count() - count($oNames);
          @endphp
          <b style="font-size:14px">{{ implode(', ', $oNames) }}{{ $oRest > 0 ? ' +'.$oRest.' more' : '' }}</b>
          <div style="color:var(--text3);font-size:11px;margin-top:2px">Order #{{ $o->id }} · {{ $o->created_at->format('M d, Y · h:i A') }} · <span style="color:var(--primary-dark);font-weight:700">Track →</span></div>
        </div>
        <span style="padding:5px 12px;border-radius:999px;background:{{ $pillBg }};color:{{ $pillFg }};font-weight:700;font-size:11px;border:1px solid {{ $pillBd }}">{{ $pillLabel }}</span>
      </div>
      <div style="color:var(--text3);font-size:12px;margin-top:6px">
        Payment: <span style="font-weight:600;color:{{ ($o->payment_status ?? 'pending') === 'paid' ? 'var(--success)' : 'var(--text2)' }}">{{ ucfirst($o->payment_status ?? 'pending') }}</span>
        · Total: <span style="font-weight:700;color:var(--green)">₱{{ number_format($o->total_amount ?? $o->total ?? 0,2) }}</span>
      </div>
      @foreach($o->items as $it)
        @php
          $prod = $it->product;
          $hasImg = $prod && $prod->image && file_exists(storage_path('app/public/'.$prod->image));
          $c = '#0F766E';
          if($prod){ $h=array_sum(array_map('ord', str_split($prod->name))); $pal=['#0F766E','#1D4ED8','#7C3AED','#DB2777','#EA580C']; $c=$pal[$h%count($pal)]; }
        @endphp
        <div style="display:flex;gap:10px;align-items:center;padding:8px 0;border-top:1px solid var(--border-light);margin-top:8px">
          @if($hasImg)
            <img src="{{ asset('storage/'.$prod->image) }}" alt="{{ $it->product_name }}" style="width:48px;height:48px;object-fit:cover;border-radius:8px;border:1px solid var(--border)">
          @else
            <div style="width:48px;height:48px;background:{{ $c }};border-radius:8px;display:grid;place-items:center;color:#fff;font-weight:800;font-size:11px">{{ strtoupper(substr($it->product_name,0,2)) }}</div>
          @endif
          <div style="flex:1">
            <div style="font-weight:700;font-size:13px">{{ $it->product_name }}</div>
            <div style="font-size:11px;color:var(--text3)">Qty {{ $it->quantity }} · {{ $prod->brand ?? '' }}</div>
          </div>
          <div style="font-weight:700;color:var(--green)">₱{{ number_format($it->price * $it->quantity,2) }}</div>
        </div>
      @endforeach
    </a>
  @endforeach
@endif
@endsection
