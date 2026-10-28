@extends('buyer.layouts.website')
@section('content')
@php
  $activeTab = $activeTab ?? 'all';
  $q = $q ?? '';
  $tabDefs = [
    'all' => 'All',
    'pending' => 'To Ship',
    'out_for_delivery' => 'In Transit',
    'delivered' => 'Delivered',
    'cancelled' => 'Cancelled',
  ];
  $pillFor = function($status){
    return match($status) {
      'delivered' => ['Delivered', 'st-done', true],
      'cancelled' => ['Cancelled', 'st-cancel', false],
      'out_for_delivery' => ['In Transit', 'st-transit', false],
      'confirmed','processing','ready_for_delivery' => ['Processing', 'st-neutral', false],
      default => ['To Ship', 'st-ship', false],
    };
  };
@endphp
<div class="ordx">
<div class="ordwrap">
  <div class="hrow rv">
    <h1>My Orders</h1>
    @if(!$orders->isEmpty())
      <span class="hsub">{{ $orders->total() }} {{ $orders->total() == 1 ? 'order' : 'orders' }}</span>
    @endif
  </div>
  <form method="GET" action="{{ url('/orders') }}" class="osearch rv" role="search">
    <input type="hidden" name="tab" value="{{ $activeTab }}">
    <div class="field">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      <input type="text" name="q" value="{{ $q }}" placeholder="Search orders by product..." aria-label="Search orders by product">
      <button type="submit" class="btn-p btn-solid">Search</button>
    </div>
    @if($q !== '')<a href="{{ url('/orders?tab='.$activeTab) }}" class="clear">Clear</a>@endif
  </form>
  <nav class="chips rv" aria-label="Filter by status">
    @foreach($tabDefs as $key => $label)
      <a href="{{ url('/orders?tab='.$key.($q !== '' ? '&q='.urlencode($q) : '')) }}" class="chip-f {{ $activeTab===$key ? 'on':'' }}">{{ $label }}</a>
    @endforeach
  </nav>
@if(session('success'))<div class="rv" role="status" style="background:#ecfdf5;color:#065f46;padding:10px;border-radius:8px;border:1px solid #a7f3d0;margin:10px 0;font-size:13px;font-weight:600">{{ session('success') }}</div>@endif
@if(session('error'))<div class="rv" role="alert" style="background:#fef2f2;color:#991b1b;padding:10px;border-radius:8px;border:1px solid #fecaca;margin:10px 0;font-size:13px;font-weight:600">{{ session('error') }}</div>@endif

@if($orders->isEmpty())
  <div class="empty rv">
    <div class="ic"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M16.5 9.4 7.55 4.24"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.29 7 12 12 20.71 7"/><line x1="12" y1="22" x2="12" y2="12"/></svg></div>
    <h1>No orders yet</h1>
    <p>Your orders will appear here after you buy.</p>
    <div style="margin-top:16px"><a href="{{ url('/') }}" class="btn-p btn-solid" style="text-decoration:none">Shop now</a></div>
  </div>
@else
  @foreach($orders as $o)
    @php
      [$pillLabel, $pillClass, $pillCheck] = $pillFor($o->status);
      $oNames = $o->items->take(2)->pluck('product_name')->all();
      $oRest = $o->items->count() - count($oNames);
      $oRef = $o->order_number ?? ('INV-'.$o->id);
      $oStore = '';
      try { $oStore = \App\Models\Seller::nameFor(optional($o->items->first())->seller_id ?? 0); } catch (\Throwable $e) {}
      $isPaid = ($o->payment_status ?? 'pending') === 'paid';
      $detailUrl = url('/orders/'.$o->id);
    @endphp
    <article class="ocard rv" aria-label="Order {{ $oRef }}">
      <div class="orow1">
        <div class="odate">{{ $o->created_at->format('M d, Y · h:i A') }}</div>
        <span class="st {{ $pillClass }}">@if($pillCheck)<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>@endif{{ $pillLabel }}</span>
      </div>
      <div class="otitle"><a href="{{ $detailUrl }}">{{ implode(', ', $oNames) }}{{ $oRest > 0 ? ' +'.$oRest.' more' : '' }}</a></div>
      <div class="ometa"><span class="inv">{{ $oRef }}</span>@if($oStore && $oStore !== 'Store') · Sold by <b>{{ $oStore }}</b>@endif</div>
      @foreach($o->items as $it)
        @php
          $prod = $it->product;
          $hasImg = $prod && $prod->image && file_exists(storage_path('app/public/'.$prod->image));
          $variantText = $it->variant_label ?? '';
          $qParts = array_values(array_filter([$prod->brand ?? '', $variantText], fn($v) => $v !== '' && $v !== null));
        @endphp
        <div class="oline">
          @if($hasImg)
            <img src="{{ asset('storage/'.$prod->image) }}" alt="{{ $it->product_name }}" loading="lazy" decoding="async">
          @else
            <div class="othumb-fb" role="img" aria-label="{{ $it->product_name }} — no image">{{ strtoupper(substr($it->product_name,0,2)) }}</div>
          @endif
          <div class="on">
            <div class="onm">{{ $it->product_name }}</div>
            <div class="oq">Qty {{ $it->quantity }}{{ !empty($qParts) ? ' · '.implode(' · ', $qParts) : '' }}</div>
          </div>
          <div class="op">₱{{ number_format($it->price * $it->quantity,2) }}</div>
        </div>
      @endforeach
      <div class="ofoot">
        <span class="paychip {{ $isPaid ? 'paid' : '' }}">{{ $isPaid ? 'Paid' : 'Pending' }}</span>
        <span class="ototal">₱{{ number_format($o->total_amount ?? $o->total ?? 0,2) }}</span>
        <a href="{{ $detailUrl }}" class="btn-p btn-line btn-sm trackbtn" aria-label="Track order {{ $oRef }}">Track
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </a>
      </div>
    </article>
  @endforeach
  @include('admin.partials.pager', ['paginator' => $orders])
@endif
</div>
</div>
@endsection
