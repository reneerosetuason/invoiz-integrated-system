@extends('layouts.website')
@section('content')
@php
  $buyer = session('buyer');
  // Line-based cart: each row is one CartItem (product + optional variant)
  // with its own price, stock cap and controls.
  $lines = ($buyer ? \App\Models\Cart::linesFor($buyer['id']) : collect())->values();
  $bySeller = $lines->groupBy(fn($l) => $l['product']->seller_id);
  function cf($n){ $h=array_sum(array_map('ord', str_split($n))); $pal=['#0F766E','#1D4ED8','#7C3AED','#DB2777','#EA580C']; return $pal[$h%count($pal)]; }
@endphp
@if(session('success'))<div style="background:#ecfdf5;color:#065f46;padding:10px 14px;border-radius:12px;border:1px solid #a7f3d0;margin-bottom:12px;font-size:13px;font-weight:600">{{ session('success') }}</div>@endif
@if(session('error'))<div style="background:#fef2f2;color:#991b1b;padding:10px 14px;border-radius:12px;border:1px solid #fecaca;margin-bottom:12px;font-size:13px;font-weight:600">{{ session('error') }}</div>@endif
@if($lines->isEmpty())
  <div style="padding:60px 20px;text-align:center">
    <div style="width:64px;height:64px;background:#f0fdf4;border-radius:50%;display:grid;place-items:center;margin:0 auto 12px;color:var(--green)"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg></div>
    <div style="font-weight:700;font-size:14px">Your cart is empty</div>
    <div style="color:var(--text3);font-size:12px;margin-top:4px">Add products from the store to see them here.</div>
    <a href="{{ url('/shop') }}" style="display:inline-block;margin-top:14px;padding:9px 16px;background:var(--green);color:#fff;border-radius:999px;text-decoration:none;font-weight:700;font-size:13px">Shop now</a>
  </div>
@else
  <div style="display:flex;align-items:center;justify-content:space-between;margin:0 0 12px">
    <h2 style="font-weight:800;margin:0">Cart</h2>
    <span style="font-size:12px;color:var(--text3)">{{ $lines->sum('qty') }} item(s)</span>
  </div>
  @php $grandTotal = $lines->sum('line'); $grandQty = $lines->sum('qty'); @endphp
  @foreach($bySeller as $sellerId => $sellerLines)
    @php
      $sellerTotal = $sellerLines->sum('line');
      $sellerQty = $sellerLines->sum('qty');
      $sc = cf('seller'.$sellerId);
      $storeName = \App\Models\Seller::nameFor($sellerId);
      $storeLogo = null;
      try { $sl = \App\Models\Seller::where('user_id', $sellerId)->first(); if ($sl && $sl->logo) $storeLogo = asset('storage/' . ltrim($sl->logo, '/')); } catch (\Throwable $e) {}
    @endphp
    <div style="background:#fff;border:1px solid var(--border);border-radius:16px;margin-bottom:14px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.04)">
      <div style="display:flex;align-items:center;padding:12px 16px;background:linear-gradient(180deg,#fafafa,#f4f5f6);border-bottom:1px solid var(--border)">
        <div style="display:flex;align-items:center;gap:10px">
          <input type="checkbox" class="seller-check" data-seller="{{ $sellerId }}" checked onchange="toggleSeller({{ $sellerId }}, this.checked)" style="accent-color:var(--green);width:17px;height:17px;cursor:pointer">
          @if($storeLogo)
            <img src="{{ $storeLogo }}" alt="{{ $storeName }}" style="width:34px;height:34px;border-radius:50%;object-fit:cover;border:1px solid var(--border)">
          @else
            <div style="width:34px;height:34px;background:{{ $sc }};border-radius:50%;display:grid;place-items:center;color:#fff;font-weight:800;font-size:12px">{{ strtoupper(mb_substr($storeName,0,1)) }}</div>
          @endif
          <div>
            <div style="font-weight:700;font-size:13px"><a href="{{ url('/store/'.$sellerId) }}" style="color:inherit;text-decoration:none">{{ $storeName }}</a></div>
            <div style="font-size:11px;color:var(--text3)">{{ $sellerLines->count() }} line(s) · ₱{{ number_format($sellerTotal,2) }}</div>
          </div>
        </div>
      </div>
      @foreach($sellerLines as $l)
        @php
          $p = $l['product']; $qty = $l['qty'];
          $c = cf($p->name);
          $hasImg = $p->image && file_exists(storage_path('app/public/'.$p->image));
          $avail = $l['variant'] ? (int) $l['variant']->stock : (int) $p->stock;
          $atMax = $qty >= $avail;
        @endphp
        <div style="display:flex;gap:12px;align-items:center;padding:12px 16px;{{ !$loop->last ? 'border-bottom:1px solid var(--border-light);' : '' }}">
          <input type="checkbox" class="item-check" data-seller="{{ $sellerId }}" data-price="{{ $l['unit'] }}" data-qty="{{ $qty }}" value="{{ $l['item']->id }}" checked onchange="updateTotal()" style="accent-color:var(--green);width:17px;height:17px;cursor:pointer;flex-shrink:0">
          @if($hasImg)
            <img src="{{ asset('storage/'.$p->image) }}" alt="{{ $p->name }}" style="width:64px;height:64px;object-fit:cover;border-radius:12px;border:1px solid var(--border);flex-shrink:0">
          @else
            <div style="width:64px;height:64px;background:{{ $c }};border-radius:12px;display:grid;place-items:center;color:#fff;font-weight:800;font-size:11px;flex-shrink:0">{{ strtoupper(substr($p->name,0,2)) }}</div>
          @endif
          <div style="flex:1;min-width:0">
            <div style="font-weight:700;font-size:13px"><a href="{{ url('/product/'.$p->id) }}" style="color:inherit;text-decoration:none">{{ $p->name }}</a></div>
            <div style="font-size:11px;color:var(--text3);margin-top:1px">{{ $p->brand }} · {{ $p->category->name ?? '' }}</div>
            @if($l['label'])
              <div style="margin-top:5px;display:inline-block;background:#EAF4F3;color:var(--green-dark);border:1px solid #BFE3D0;padding:2px 9px;border-radius:999px;font-weight:700;font-size:10.5px">{{ $l['label'] }}</div>
            @endif
            <div style="margin-top:5px;font-size:11px;color:{{ $avail <= 5 ? '#C77700' : 'var(--text3)' }};font-weight:{{ $avail <= 5 ? '700' : '400' }}">{{ $avail }} available{{ $l['label'] ? ' for this variation' : '' }}</div>
            <div style="margin-top:4px;font-weight:800;font-size:12.5px;color:var(--green)">₱{{ number_format($l['unit'],2) }} × {{ $qty }} = ₱{{ number_format($l['line'],2) }}</div>
          </div>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px">
            <div style="display:flex;align-items:center;gap:4px;background:#f9f9f9;border:1px solid var(--border);border-radius:999px;padding:2px">
              <a href="{{ url('/cart/dec/'.$l['item']->id) }}" style="width:26px;height:26px;border-radius:50%;background:#fff;border:1px solid var(--border);display:grid;place-items:center;text-decoration:none;color:var(--text);font-weight:800;font-size:12px">−</a>
              <span style="min-width:26px;text-align:center;font-weight:700;font-size:12px">{{ $qty }}</span>
              @if($atMax)
                <span title="Only {{ $avail }} available" style="width:26px;height:26px;border-radius:50%;background:#e5e5e5;color:#9CA3AF;display:grid;place-items:center;font-weight:800;font-size:12px;cursor:not-allowed">+</span>
              @else
                <a href="{{ url('/cart/inc/'.$l['item']->id) }}" style="width:26px;height:26px;border-radius:50%;background:var(--green);color:#fff;display:grid;place-items:center;text-decoration:none;font-weight:800;font-size:12px">+</a>
              @endif
            </div>
            <div style="display:flex;gap:4px;justify-content:flex-end">
              <a href="{{ url('/cart/remove/'.$l['item']->id) }}" style="padding:5px 8px;border-radius:999px;border:1px solid var(--border);text-decoration:none;color:var(--text3);font-size:10px;font-weight:600">Remove</a>
            </div>
          </div>
        </div>
      @endforeach
    </div>
  @endforeach
  <div style="margin-top:14px;display:flex;justify-content:flex-end;gap:12px;align-items:center;flex-wrap:wrap;background:#fff;border:1px solid var(--border);border-radius:16px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,.04);position:sticky;bottom:12px">
    <div style="text-align:right">
      <div style="font-weight:800;font-size:17px;color:var(--green)">₱<span id="selectedTotal">{{ number_format($grandTotal,2) }}</span></div>
      <div style="font-size:11px;color:var(--text3)"><span id="selectedCount">{{ $grandQty }}</span> item(s) selected</div>
    </div>
    <button type="button" onclick="placeOrder()" style="padding:12px 32px;border-radius:999px;background:var(--green);color:#fff;border:none;font-weight:800;font-size:14px;cursor:pointer;font-family:inherit;box-shadow:0 4px 12px rgba(22,105,122,.25)">Place Order</button>
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
@endsection
