@extends('layouts.website')
@section('content')
<style>@media(max-width:1023.98px){#supportChatBtn{bottom:120px}}</style>
@php
  $buyer = session('buyer');
  // Line-based cart: each row is one CartItem (product + optional variant)
  // with its own price, stock cap and controls.
  $lines = ($buyer ? \App\Models\Cart::linesFor($buyer['id']) : collect())->values();
  $bySeller = $lines->groupBy(fn($l) => $l['product']->seller_id);
@endphp
<div class="cartx">
<div class="cartwrap">
@if(session('success'))<div class="rv" role="status" style="background:#ecfdf5;color:#065f46;padding:10px 14px;border-radius:12px;border:1px solid #a7f3d0;margin-bottom:12px;font-size:13px;font-weight:600">{{ session('success') }}</div>@endif
@if(session('error'))<div class="rv" role="alert" style="background:#fef2f2;color:#991b1b;padding:10px 14px;border-radius:12px;border:1px solid #fecaca;margin-bottom:12px;font-size:13px;font-weight:600">{{ session('error') }}</div>@endif
@if($lines->isEmpty())
  <div class="empty rv">
    <div class="ic"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg></div>
    <h1>Your cart is empty</h1>
    <p>Add products from the store to see them here.</p>
    <div style="margin-top:16px"><a href="{{ url('/shop') }}" class="btn-p btn-solid" style="text-decoration:none">Shop now</a></div>
  </div>
@else
  @php
    $grandTotal = $lines->sum('line'); $grandQty = $lines->sum('qty');
    $lineCount = $lines->count();
  @endphp
  <div class="hrow rv">
    <h1>Cart</h1>
    <span class="hsub">{{ $grandQty }} {{ $grandQty == 1 ? 'item' : 'items' }}</span>
  </div>
  <div class="cartgrid">
    <div>
      @foreach($bySeller as $sellerId => $sellerLines)
        @php
          $sellerTotal = $sellerLines->sum('line');
          $sellerLineCount = $sellerLines->count();
          $storeName = \App\Models\Seller::nameFor($sellerId);
          $storeLogo = null;
          try { $sl = \App\Models\Seller::where('user_id', $sellerId)->first(); if ($sl && $sl->logo) $storeLogo = asset('storage/' . ltrim($sl->logo, '/')); } catch (\Throwable $e) {}
        @endphp
        <section class="sgroup rv" aria-label="Items from {{ $storeName }}">
          <div class="sghead">
            <label class="chk"><input type="checkbox" class="seller-check" data-seller="{{ $sellerId }}" checked onchange="toggleSeller({{ $sellerId }}, this.checked)" aria-label="Select all items from {{ $storeName }}"></label>
            <span class="sava" aria-hidden="true">
              <span class="sava-fb">{{ strtoupper(mb_substr($storeName,0,1)) }}</span>
              @if($storeLogo)
                <img src="{{ $storeLogo }}" alt="" loading="lazy" onerror="this.remove()">
              @endif
            </span>
            <div style="min-width:0">
              <div class="sname"><a href="{{ url('/store/'.$sellerId) }}">{{ $storeName }}</a></div>
              <div class="ssub">{{ $sellerLineCount }} {{ $sellerLineCount == 1 ? 'line' : 'lines' }} · ₱{{ number_format($sellerTotal,2) }}</div>
            </div>
          </div>
          @foreach($sellerLines as $l)
            @php
              $p = $l['product']; $qty = $l['qty'];
              $hasImg = $p->image && file_exists(storage_path('app/public/'.$p->image));
              $avail = $l['variant'] ? (int) $l['variant']->stock : (int) $p->stock;
              $atMax = $qty >= $avail;
              $metaParts = array_values(array_filter([$p->brand, ($p->category->name ?? '')], fn($v) => $v !== '' && $v !== null));
            @endphp
            <div class="irow">
              <label class="chk"><input type="checkbox" class="item-check" data-seller="{{ $sellerId }}" data-price="{{ $l['unit'] }}" data-qty="{{ $qty }}" value="{{ $l['item']->id }}" checked onchange="updateTotal()" aria-label="Select {{ $p->name }}"></label>
              @if($hasImg)
                <img class="ithumb" src="{{ asset('storage/'.$p->image) }}" alt="{{ $p->name }}" loading="lazy" decoding="async">
              @else
                <div class="ithumb-fb" role="img" aria-label="{{ $p->name }} — no image"><span>{{ strtoupper(substr($p->name,0,2)) }}</span><small>INVOIZ</small></div>
              @endif
              <div class="ibody">
                <div class="iname"><a href="{{ url('/product/'.$p->id) }}">{{ $p->name }}</a></div>
                @if(!empty($metaParts))
                  <div class="imeta">{{ implode(' · ', $metaParts) }}</div>
                @endif
                @if($l['label'])
                  <div><span class="vchip">{{ $l['label'] }}</span></div>
                @endif
                <div class="iavail">{{ $avail }} available{{ $l['label'] ? ' for this variation' : '' }}</div>
                <div class="iprice">₱{{ number_format($l['unit'],2) }} × {{ $qty }} = <b>₱{{ number_format($l['line'],2) }}</b></div>
              </div>
              <div class="iact">
                <div class="step" role="group" aria-label="Quantity for {{ $p->name }}">
                  <a href="{{ url('/cart/dec/'.$l['item']->id) }}" aria-label="Decrease quantity">−</a>
                  <span class="qv" aria-live="polite">{{ $qty }}</span>
                  @if($atMax)
                    <span class="stepdis" title="Only {{ $avail }} available" aria-disabled="true" aria-label="Maximum reached">+</span>
                  @else
                    <a href="{{ url('/cart/inc/'.$l['item']->id) }}" aria-label="Increase quantity">+</a>
                  @endif
                </div>
                <a href="{{ url('/cart/remove/'.$l['item']->id) }}" class="rmbtn"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>Remove</a>
              </div>
            </div>
          @endforeach
        </section>
      @endforeach
    </div>
    <aside class="sumcard rv" aria-label="Order summary">
      <div class="sumbody" aria-live="polite">
        <div class="eyebrow">Order summary</div>
        <div class="sumsel"><span id="selectedCount">{{ $grandQty }}</span> item(s) selected</div>
        <div class="sumtot">₱<span id="selectedTotal">{{ number_format($grandTotal,2) }}</span></div>
        <div class="sumcod">Pay cash on delivery</div>
      </div>
      <button type="button" class="btn-p btn-solid" onclick="placeOrder()">Place Order</button>
    </aside>
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

  function placeOrder() {
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
</div>
</div>
@endsection
