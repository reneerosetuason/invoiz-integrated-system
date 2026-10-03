@extends('layouts.website')
@section('content')
@php
  $buyer = session('buyer');
  $single = $single ?? null;
  $cartItems = $cartItems ?? collect();
  $checkoutMode = $checkoutMode ?? 'single';
  $sellerGroups = $sellerGroups ?? [];
  $grandTotal = $grandTotal ?? 0;
  $sellerQtyMap = $sellerQtyMap ?? [];
@endphp
@if(session('error'))<div style="background:#fef2f2;color:#991b1b;padding:10px;border-radius:8px;border:1px solid #fecaca;margin-bottom:10px;font-size:13px;font-weight:600">{{ session('error') }}</div>@endif
<h2 style="font-weight:800">Checkout</h2>
<div style="display:grid;grid-template-columns:1.2fr .8fr;gap:16px;margin-top:12px">
  <div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:16px">
    <h3 style="margin:0 0 10px;font-size:14px">Payment method</h3>
    <form method="POST" action="{{ url('/checkout') }}">
      @csrf
      @if($single)
        <input type="hidden" name="checkout_mode" value="single">
        <input type="hidden" name="single" value="{{ $single }}">
        <input type="hidden" name="qty" value="{{ $singleQty ?? 1 }}">
        <input type="hidden" name="variant_id" value="{{ $singleVariantId ?? '' }}">
      @elseif($checkoutMode === 'seller')
        <input type="hidden" name="checkout_mode" value="seller">
        <input type="hidden" name="seller_id" value="{{ $sellerId ?? '' }}">
      @else
        <input type="hidden" name="checkout_mode" value="all">
      @endif
      <label style="display:flex;align-items:center;gap:10px;padding:12px;border:1px solid var(--green);border-radius:10px;margin-bottom:10px;cursor:pointer;background:#f0fdf4">
        <input type="radio" name="payment_method" value="cod" checked style="accent-color:var(--green)">
        <img src="https://cdn-icons-png.flaticon.com/512/2168/2168741.png" alt="COD" style="width:28px;height:28px;object-fit:contain;background:#fff;border-radius:6px;padding:3px;border:1px solid var(--border)">
        <span style="font-weight:700">Cash on Delivery (COD)</span>
        <span style="margin-left:auto;color:var(--green);font-size:11px;font-weight:700"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-3px"><polyline points="20 6 9 17 4 12"/></svg> Selected</span>
      </label>
      <div style="margin-top:12px;padding:10px;background:#f0fdf4;border:1px solid var(--green);border-radius:8px;font-size:11px;color:var(--green-dark)">COD only: Pay when your order is delivered.</div>
      <button type="submit" style="width:100%;margin-top:14px;padding:12px;background:var(--green);color:#fff;border:none;border-radius:var(--radius);font-weight:800;cursor:pointer;font-size:13px">
        @if($checkoutMode === 'all')
          Place {{ count($sellerGroups) }} Orders — ₱{{ number_format($grandTotal,2) }}
        @else
          Place Order
        @endif
      </button>
    </form>
  </div>
  <div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:16px">
    <h3 style="margin:0 0 10px;font-size:14px">Order summary</h3>
    @if($checkoutMode === 'all')
      @php $orderNum = 1; @endphp
      @foreach($sellerGroups as $sid => $group)
        <div style="margin-bottom:12px;{{ !$loop->last ? 'padding-bottom:12px;border-bottom:1px solid var(--border-light)' : '' }}">
          <div style="font-weight:700;font-size:12px;color:var(--green);margin-bottom:6px">Order #{{ $orderNum }} — Seller {{ $sid }}</div>
          @php $orderNum++; @endphp
          @foreach($group['lines'] as $gl)
            @php $p = $gl['product']; $qty = $gl['qty']; @endphp
            <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:12px">
              <span>{{ $p->name }}@if($gl['label']) <span style="color:var(--text3)">({{ $gl['label'] }})</span>@endif × {{ $qty }}</span>
              <b style="color:var(--green)">₱{{ number_format($gl['line'],2) }}</b>
            </div>
          @endforeach
          <div style="display:flex;justify-content:space-between;margin-top:4px;font-weight:700;font-size:12px;border-top:1px dashed var(--border);padding-top:4px">
            <span>Subtotal</span>
            <span style="color:var(--green)">₱{{ number_format($group['total'],2) }}</span>
          </div>
        </div>
      @endforeach
      <div style="display:flex;justify-content:space-between;margin-top:8px;font-weight:800;font-size:14px;border-top:2px solid var(--green);padding-top:8px">
        <span>Grand Total ({{ count($sellerGroups) }} orders)</span>
        <span style="color:var(--green)">₱{{ number_format($grandTotal,2) }}</span>
      </div>
    @else
      @php
        $total = 0;
        if($single){
          $total = ($singleUnit ?? $products->first()->price) * ($singleQty ?? 1);
        } elseif($checkoutMode === 'seller'){
          $total = isset($sellerLines) ? $sellerLines->sum('line') : 0;
        } else {
          $total = isset($sellerGroups) ? collect($sellerGroups)->sum('total') : 0;
        }
      @endphp
      @if($single)
        @foreach($products as $p)
          <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border-light);font-size:12px">
            <span>{{ $p->name }}@if(!empty($singleLabel)) <span style="color:var(--text3)">({{ $singleLabel }})</span>@endif × {{ $singleQty ?? 1 }}</span>
            <b style="color:var(--green)">₱{{ number_format($total,2) }}</b>
          </div>
        @endforeach
      @elseif($checkoutMode === 'seller' && isset($sellerLines))
        @foreach($sellerLines as $sl)
          <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border-light);font-size:12px">
            <span>{{ $sl['product']->name }}@if($sl['label']) <span style="color:var(--text3)">({{ $sl['label'] }})</span>@endif × {{ $sl['qty'] }}</span>
            <b style="color:var(--green)">₱{{ number_format($sl['line'],2) }}</b>
          </div>
        @endforeach
      @else
      @foreach($products as $p)
        @php $qty = $sellerQtyMap[$p->id] ?? $cartItems->where('product_id',$p->id)->first()->quantity ?? 1; @endphp
        <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border-light);font-size:12px">
          <span>{{ $p->name }} × {{ $qty }}</span>
          <b style="color:var(--green)">₱{{ number_format($p->price * $qty,2) }}</b>
        </div>
      @endforeach
      @endif
      <div style="display:flex;justify-content:space-between;margin-top:10px;font-weight:800;font-size:14px">Total <span style="color:var(--green)">₱{{ number_format($total,2) }}</span></div>
    @endif
  </div>
</div>
@endsection
