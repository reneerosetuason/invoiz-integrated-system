@extends('layouts.website')
@section('content')
@php $activeTab = $activeTab ?? 'all'; @endphp
<h2 style="font-weight:800">My Orders</h2>
@if(session('success'))<div style="background:#ecfdf5;color:#065f46;padding:10px;border-radius:8px;border:1px solid #a7f3d0;margin:10px 0;font-size:13px;font-weight:600">{{ session('success') }}</div>@endif
@if(session('error'))<div style="background:#fef2f2;color:#991b1b;padding:10px;border-radius:8px;border:1px solid #fecaca;margin:10px 0;font-size:13px;font-weight:600">{{ session('error') }}</div>@endif
<div style="display:flex;gap:6px;margin:12px 0;flex-wrap:wrap">
  <a href="{{ url('/orders?tab=all') }}" style="padding:8px 16px;border-radius:999px;text-decoration:none;font-weight:600;font-size:12px;border:1px solid {{ $activeTab==='all' ? 'var(--green)' : 'var(--border)' }};background:{{ $activeTab==='all' ? 'var(--green)' : '#fff' }};color:{{ $activeTab==='all' ? '#fff' : 'var(--text2)' }}">All</a>
  <a href="{{ url('/orders?tab=pending') }}" style="padding:8px 16px;border-radius:999px;text-decoration:none;font-weight:600;font-size:12px;border:1px solid {{ $activeTab==='pending' ? '#f59e0b' : 'var(--border)' }};background:{{ $activeTab==='pending' ? '#f59e0b' : '#fff' }};color:{{ $activeTab==='pending' ? '#fff' : 'var(--text2)' }}">Pending</a>
  <a href="{{ url('/orders?tab=out_for_delivery') }}" style="padding:8px 16px;border-radius:999px;text-decoration:none;font-weight:600;font-size:12px;border:1px solid {{ $activeTab==='out_for_delivery' ? '#3b82f6' : 'var(--border)' }};background:{{ $activeTab==='out_for_delivery' ? '#3b82f6' : '#fff' }};color:{{ $activeTab==='out_for_delivery' ? '#fff' : 'var(--text2)' }}">Out for Delivery</a>
  <a href="{{ url('/orders?tab=delivered') }}" style="padding:8px 16px;border-radius:999px;text-decoration:none;font-weight:600;font-size:12px;border:1px solid {{ $activeTab==='delivered' ? 'var(--success)' : 'var(--border)' }};background:{{ $activeTab==='delivered' ? 'var(--success)' : '#fff' }};color:{{ $activeTab==='delivered' ? '#fff' : 'var(--text2)' }}">Delivered</a>
</div>
@if($orders->isEmpty())
  <div style="padding:60px 20px;text-align:center">
    <div style="width:64px;height:64px;background:#f0fdf4;border-radius:50%;display:grid;place-items:center;margin:0 auto 12px;font-size:24px">📦</div>
    <div style="font-weight:700;font-size:14px">No orders yet</div>
    <div style="color:var(--text3);font-size:12px;margin-top:4px">Your orders will appear here after you buy.</div>
    <a href="{{ url('/') }}" style="display:inline-block;margin-top:14px;padding:9px 16px;background:var(--green);color:#fff;border-radius:999px;text-decoration:none;font-weight:700;font-size:13px">Shop now</a>
  </div>
@else
  <div style="color:var(--text3);font-size:12px;margin-bottom:12px">{{ $orders->count() }} order(s)</div>
  @foreach($orders as $o)
    @php
      $statusColor = match($o->status) {
        'pending' => ['#fef3c7','#92400e','#fde68a'],
        'confirmed' => ['#dbeafe','#1e40af','#bfdbfe'],
        'shipped' => ['#e0e7ff','#3730a3','#c7d2fe'],
        'delivered' => ['#ecfdf5','#065f46','#a7f3d0'],
        'cancelled' => ['#fef2f2','#991b1b','#fecaca'],
        default => ['#f3f4f6','#374151','#d1d5db'],
      };
    @endphp
    <div style="background:#fff;border:1px solid var(--border);border-radius:10px;padding:14px;margin-bottom:12px">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <div>
          <b style="font-size:14px">Order #{{ $o->order_number ?? $o->id }}</b>
          <div style="color:var(--text3);font-size:11px;margin-top:2px">{{ $o->created_at->format('M d, Y · h:i A') }}</div>
        </div>
        <span style="padding:5px 12px;border-radius:999px;background:{{ $statusColor[0] }};color:{{ $statusColor[1] }};font-weight:700;font-size:11px;border:1px solid {{ $statusColor[2] }}">{{ ucfirst($o->status) }}</span>
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
    </div>
  @endforeach
@endif
@endsection
