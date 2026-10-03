@extends('layouts.website')
@section('content')
@php
  $buyer = session('buyer');
  $cartItems = $buyer ? \App\Models\Cart::where('buyer_id', $buyer['id'])->get() : collect();
  $productIds = $cartItems->pluck('product_id')->toArray();
  $products = \App\Models\Product::whereIn('id', $productIds)->get();
  $qtyMap = $cartItems->pluck('quantity','product_id')->toArray();
  $bySeller = $products->groupBy('seller_id');
  function cf($n){ $h=array_sum(array_map('ord', str_split($n))); $pal=['#0F766E','#1D4ED8','#7C3AED','#DB2777','#EA580C']; return $pal[$h%count($pal)]; }
@endphp
@if(session('success'))<div style="background:#ecfdf5;color:#065f46;padding:10px;border-radius:8px;border:1px solid #a7f3d0;margin-bottom:10px;font-size:13px;font-weight:600">{{ session('success') }}</div>@endif
@if(session('error'))<div style="background:#fef2f2;color:#991b1b;padding:10px;border-radius:8px;border:1px solid #fecaca;margin-bottom:10px;font-size:13px;font-weight:600">{{ session('error') }}</div>@endif
@if($products->isEmpty())
  <div style="padding:60px 20px;text-align:center">
    <div style="width:64px;height:64px;background:#f0fdf4;border-radius:50%;display:grid;place-items:center;margin:0 auto 12px;font-size:24px">🛒</div>
    <div style="font-weight:700;font-size:14px">Your cart is empty</div>
    <div style="color:var(--text3);font-size:12px;margin-top:4px">Add products from the store to see them here.</div>
    <a href="{{ url('/') }}" style="display:inline-block;margin-top:14px;padding:9px 16px;background:var(--green);color:#fff;border-radius:999px;text-decoration:none;font-weight:700;font-size:13px">Shop now</a>
  </div>
@else
  <h2 style="font-weight:800;margin:0 0 12px">Cart</h2>
  @php $grandTotal = 0; $grandQty = 0; @endphp
  @foreach($bySeller as $sellerId => $sellerProducts)
    @php
      $sellerTotal = 0;
      $sellerQty = 0;
      foreach($sellerProducts as $sp){ $qty = $qtyMap[$sp->id] ?? 1; $sellerTotal += $sp->price * $qty; $sellerQty += $qty; }
      $grandTotal += $sellerTotal;
      $grandQty += $sellerQty;
      $sc = cf('seller'.$sellerId);
      $sellerProductIds = $sellerProducts->pluck('id')->toArray();
    @endphp
    <div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius);margin-bottom:14px;overflow:hidden">
      <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:#f9f9f9;border-bottom:1px solid var(--border)">
        <div style="display:flex;align-items:center;gap:8px">
          <input type="checkbox" class="seller-check" data-seller="{{ $sellerId }}" data-ids="{{ implode(',',$sellerProductIds) }}" checked onchange="toggleSeller({{ $sellerId }}, this.checked)" style="accent-color:var(--green);width:16px;height:16px;cursor:pointer">
          <div style="width:32px;height:32px;background:{{ $sc }};border-radius:50%;display:grid;place-items:center;color:#fff;font-weight:800;font-size:12px">{{ $sellerId }}</div>
          <div>
            <div style="font-weight:700;font-size:13px">Seller #{{ $sellerId }}</div>
            <div style="font-size:11px;color:var(--text3)">{{ $sellerProducts->count() }} item(s) · ₱{{ number_format($sellerTotal,2) }}</div>
          </div>
        </div>
        <a href="{{ url('/buy/seller/'.$sellerId) }}" style="padding:7px 14px;border-radius:999px;background:var(--green);color:#fff;text-decoration:none;font-weight:700;font-size:12px">Buy from this store</a>
      </div>
      @foreach($sellerProducts as $p)
        @php
          $c=cf($p->name);
          $qty = $qtyMap[$p->id] ?? 1;
          $hasImg = $p->image && file_exists(storage_path('app/public/'.$p->image));
          $lineTotal = $p->price * $qty;
        @endphp
        <div style="display:flex;gap:12px;align-items:center;padding:10px 14px;{{ !$loop->last ? 'border-bottom:1px solid var(--border-light);' : '' }}">
          <input type="checkbox" class="item-check" data-seller="{{ $sellerId }}" data-price="{{ $p->price }}" data-qty="{{ $qty }}" value="{{ $p->id }}" checked onchange="updateTotal()" style="accent-color:var(--green);width:16px;height:16px;cursor:pointer;flex-shrink:0">
          @if($hasImg)
            <img src="{{ asset('storage/'.$p->image) }}" alt="{{ $p->name }}" style="width:64px;height:64px;object-fit:cover;border-radius:8px;border:1px solid var(--border)">
          @else
            <div style="width:64px;height:64px;background:{{ $c }};border-radius:8px;display:grid;place-items:center;color:#fff;font-weight:800;font-size:11px">{{ strtoupper(substr($p->name,0,2)) }}</div>
          @endif
          <div style="flex:1">
            <div style="font-weight:700;color:{{ $c }};font-size:13px">{{ $p->name }}</div>
            <div style="font-size:11px;color:var(--text3)">{{ $p->brand }} · {{ $p->category->name ?? '' }}</div>
            <div style="margin-top:4px;display:inline-block;background:{{ $c }};color:#fff;padding:3px 8px;border-radius:999px;font-weight:800;font-size:11px">₱{{ number_format($p->price,2) }} × {{ $qty }} = ₱{{ number_format($lineTotal,2) }}</div>
          </div>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;min-width:130px">
            <div style="display:flex;align-items:center;gap:4px;background:#f9f9f9;border:1px solid var(--border);border-radius:999px;padding:2px">
              <a href="{{ url('/cart/dec/'.$p->id) }}" style="width:26px;height:26px;border-radius:50%;background:#fff;border:1px solid var(--border);display:grid;place-items:center;text-decoration:none;color:var(--text);font-weight:800;font-size:12px">−</a>
              <span style="min-width:26px;text-align:center;font-weight:700;font-size:12px">{{ $qty }}</span>
              <a href="{{ url('/cart/add/'.$p->id) }}" style="width:26px;height:26px;border-radius:50%;background:var(--green);color:#fff;display:grid;place-items:center;text-decoration:none;font-weight:800;font-size:12px">+</a>
            </div>
            <div style="display:flex;gap:4px">
              <a href="{{ url('/cart/remove/'.$p->id) }}" style="padding:5px 8px;border-radius:999px;border:1px solid var(--border);text-decoration:none;color:var(--text3);font-size:10px;font-weight:600">Remove</a>
              <a href="{{ url('/buy/'.$p->id) }}" style="padding:5px 10px;border-radius:999px;background:var(--green);color:#fff;text-decoration:none;font-weight:700;font-size:11px">Buy</a>
            </div>
          </div>
        </div>
      @endforeach
    </div>
  @endforeach
  <div style="margin-top:14px;display:flex;justify-content:flex-end;gap:10px;align-items:center;flex-wrap:wrap;background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:14px">
    <div>
      <div style="font-weight:800;font-size:15px;color:var(--green)">₱<span id="selectedTotal">{{ number_format($grandTotal,2) }}</span></div>
      <div style="font-size:11px;color:var(--text3)"><span id="selectedCount">{{ $grandQty }}</span> item(s) selected</div>
    </div>
    <button type="button" onclick="buySelected()" style="padding:10px 20px;border-radius:999px;background:var(--green);color:#fff;border:none;font-weight:700;font-size:13px;cursor:pointer;font-family:inherit">Buy Selected</button>
    <a href="{{ url('/buy/all') }}" style="padding:10px 20px;border-radius:999px;background:var(--green);color:#fff;text-decoration:none;font-weight:700;font-size:13px">Buy All</a>
  </div>
  <script>
  function toggleSeller(sellerId, checked) {
    document.querySelectorAll('.item-check[data-seller="' + sellerId + '"]').forEach(function(cb) {
      cb.checked = checked;
    });
    updateTotal();
  }

  function updateTotal() {
    var total = 0;
    var count = 0;
    document.querySelectorAll('.item-check:checked').forEach(function(cb) {
      var price = parseFloat(cb.getAttribute('data-price'));
      var qty = parseInt(cb.getAttribute('data-qty'));
      total += price * qty;
      count += qty;
    });
    document.getElementById('selectedTotal').textContent = total.toLocaleString('en', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('selectedCount').textContent = count;
  }

  function buySelected() {
    var ids = [];
    document.querySelectorAll('.item-check:checked').forEach(function(cb) {
      ids.push(cb.value);
    });
    if (ids.length === 0) {
      alert('Select at least one item');
      return;
    }
    window.location.href = '{{ url("/buy/selected") }}?ids=' + ids.join(',');
  }
  </script>
@endif
@endsection
