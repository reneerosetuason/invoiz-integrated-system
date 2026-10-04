@extends('layouts.website')
@section('content')
@php
  function cFor($n){ $h=array_sum(array_map('ord', str_split($n))); $pal=['#0F766E','#1D4ED8','#7C3AED','#DB2777','#EA580C','#16A34A','#0891B2','#4F46E5']; return $pal[$h%count($pal)]; }
  $buyer = session('buyer');
  $inCart = false;
  if($buyer){
    $inCart = \App\Models\Cart::itemsFor($buyer['id'])->where('product_id',$p->id)->exists();
  }
@endphp
<a href="{{ url('/'.(request('category') ? '?category='.request('category').(request('search') ? '&search='.urlencode(request('search')) : '') : (request('search') ? '?search='.urlencode(request('search')) : ''))) }}" style="color:var(--text2);text-decoration:none;font-weight:600">← Back to store</a>
@if(session('success'))
  <div style="margin-top:10px;padding:10px 14px;background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;border-radius:8px;font-size:13px;font-weight:600">{{ session('success') }}</div>
@endif
@if(session('error'))
  <div style="margin-top:10px;padding:10px 14px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;border-radius:8px;font-size:13px;font-weight:600">{{ session('error') }}</div>
@endif
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:12px">
  @php $hasPImg = $p->image && file_exists(storage_path('app/public/'.$p->image)); @endphp
  <div style="height:420px;background:#f3f4f6;border-radius:10px;overflow:hidden;border:1px solid var(--border)">
    @if($hasPImg)
      <img src="{{ asset('storage/'.$p->image) }}" alt="{{ $p->name }}" style="width:100%;height:100%;object-fit:cover;display:block">
    @else
      <div style="width:100%;height:100%;background:{{ cFor($p->name) }};border-radius:10px;display:grid;place-items:center;color:#fff;padding:20px">
        <div>
          <div style="width:80px;height:80px;background:rgba(255,255,255,.22);border-radius:18px;display:grid;place-items:center;margin:0 auto;font-weight:800;font-size:28px">{{ strtoupper(substr($p->name,0,2)) }}</div>
          <div style="margin-top:12px;font-weight:700;font-size:18px">{{ $p->name }}</div>
          <div style="opacity:.9">{{ $p->brand }}</div>
        </div>
      </div>
    @endif
  </div>
  <div style="background:#fff;border:1px solid var(--border);border-radius:10px;padding:16px">
    <div style="color:var(--green);font-weight:800;font-size:24px">₱{{ number_format($p->price,2) }}</div>
    @if($p->compare_at_price && $p->compare_at_price > $p->price)
      <div style="display:flex;align-items:center;gap:8px;margin-top:2px">
        <span style="color:var(--text3);font-size:13px;text-decoration:line-through">₱{{ number_format($p->compare_at_price,2) }}</span>
        <span style="background:var(--danger);color:#fff;font-size:10px;font-weight:800;padding:2px 7px;border-radius:999px">−{{ (int) round((1 - (float)$p->price/(float)$p->compare_at_price)*100) }}%</span>
      </div>
    @endif
    <div style="font-weight:700;margin-top:6px;font-size:16px">{{ $p->name }}</div>
    @if($p->brand)
      <div style="color:var(--text2);font-size:12px;margin-top:2px">{{ $p->brand }}</div>
    @endif
    <div style="color:var(--text3);font-size:12px;margin-top:4px">{{ $p->stock }} left · {{ $p->category->name ?? '' }} · COD Available</div>
    <div style="font-size:12px;margin-top:4px;color:var(--text2)">Sold by <a href="{{ url('/store/'.$p->seller_id) }}" style="color:var(--primary-dark,#0E4A57);font-weight:700;text-decoration:none">{{ \App\Models\Seller::nameFor($p->seller_id) }}</a></div>
    @if($p->description)
      <div style="margin-top:10px;color:var(--text2);font-size:13px;line-height:1.6">{{ $p->description }}</div>
    @endif
    @php $vgroups = $p->variants->where('status','active')->groupBy('variant_type'); @endphp
    @if($vgroups->isNotEmpty())
      <div style="margin-top:10px;font-size:12px;color:var(--text3)">Available variations: {{ $vgroups->keys()->implode(', ') }} (choose in options)</div>
    @endif
    <div style="margin-top:16px;display:flex;gap:10px">
      @if($inCart)
        <a href="{{ url('/cart') }}" style="flex:1;text-align:center;background:#f0fdf4;color:var(--green);border:1.5px solid var(--green);padding:11px 16px;border-radius:999px;text-decoration:none;font-weight:700"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-3px"><polyline points="20 6 9 17 4 12"/></svg> In Cart — View Cart</a>
      @else
        <button type="button" onclick="openOpt()" style="flex:1;background:#fff;color:var(--green);border:1.5px solid var(--green);padding:11px 16px;border-radius:999px;font-weight:700;cursor:pointer;font-family:inherit;font-size:13px"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg> Add to Cart</button>
      @endif
      <button type="button" onclick="openOpt(true)" style="flex:1;background:var(--green);color:#fff;padding:11px 16px;border:none;border-radius:999px;font-weight:700;cursor:pointer;font-family:inherit;font-size:13px"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg> Buy Now</button>
    </div>
    <a href="{{ url('/chat/seller/'.$p->seller_id) }}" style="display:flex;align-items:center;justify-content:center;gap:8px;margin-top:10px;padding:10px;background:#fff;border:1px solid var(--border);border-radius:999px;text-decoration:none;color:var(--green-dark);font-weight:700;font-size:13px;transition:all .15s" onmouseover="this.style.borderColor='var(--green)';this.style.background='#f0fdf4'" onmouseout="this.style.borderColor='var(--border)';this.style.background='#fff'">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> Message Seller
    </a>
  </div>
</div>

@php
  $specs = [
    'Brand' => $p->brand, 'Model' => $p->model, 'SKU' => $p->sku,
    'Material' => $p->material, 'Dimensions' => $p->dimensions, 'Weight' => $p->weight,
    'Warranty' => $p->warranty, 'Origin' => $p->origin,
  ];
@endphp
<div style="background:#fff;border:1px solid var(--border);border-radius:10px;padding:16px;margin-top:16px">
  <div style="font-weight:800;font-size:14px;margin-bottom:8px">Product details</div>
  @if($p->description)
    <p style="color:var(--text2);font-size:13px;line-height:1.65;margin:0 0 12px">{{ $p->description }}</p>
  @endif
  <div style="display:grid;grid-template-columns:130px 1fr;gap:6px 12px;font-size:12.5px">
    @foreach($specs as $label => $val)
      @if(!empty($val))
        <div style="color:var(--text3)">{{ $label }}</div>
        <div style="font-weight:600">{{ $val }}</div>
      @endif
    @endforeach
    <div style="color:var(--text3)">Category</div>
    <div style="font-weight:600">{{ $p->category->name ?? '—' }}</div>
    <div style="color:var(--text3)">Stock</div>
    <div style="font-weight:600">{{ $p->stock }} available · Cash on Delivery</div>
  </div>
</div>

{{-- Options modal: quantity + variation --}}
<div id="optModal" style="display:none;position:fixed;inset:0;z-index:100;background:rgba(0,0,0,.45);align-items:center;justify-content:center;padding:16px">
  <div style="background:#fff;border-radius:14px;max-width:420px;width:100%;padding:20px;max-height:90vh;overflow:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
      <div style="font-weight:800;font-size:15px">Order options</div>
      <button type="button" onclick="closeOpt()" style="background:transparent;border:none;cursor:pointer;color:var(--text3);padding:4px"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div style="font-size:12px;color:var(--text2);margin-bottom:12px">{{ $p->name }} · <b id="optBasePrice" style="color:var(--green)">₱{{ number_format($p->price,2) }}</b></div>
    @if($vgroups->isNotEmpty())
      <div style="font-size:12px;font-weight:700;margin-bottom:6px">Variation <span style="font-weight:400;color:var(--text3)">(choose 1)</span></div>
      @foreach($vgroups as $type => $opts)
        <div style="font-size:11px;color:var(--text3);margin:8px 0 6px">{{ $type }}</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap" data-vgroup="{{ $type }}">
          @foreach($opts as $v)
            <button type="button" class="opt-pill" data-variant="{{ $v->id }}" data-adj="{{ $v->price_adjustment }}" data-label="{{ $v->variant_type }}: {{ $v->variant_value }}" onclick="pickVariant(this)" style="padding:7px 14px;border-radius:999px;border:1px solid var(--border);background:#fff;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit">{{ $v->variant_value }}{{ $v->price_adjustment > 0 ? ' (+₱'.number_format($v->price_adjustment,2).')' : '' }}</button>
          @endforeach
        </div>
      @endforeach
    @endif
    <div style="font-size:12px;font-weight:700;margin:14px 0 6px">Quantity</div>
    <div style="display:flex;align-items:center;gap:10px">
      <button type="button" onclick="optQty(-1)" style="width:30px;height:30px;border-radius:50%;background:#fff;border:1px solid var(--border);font-weight:800;font-size:14px;cursor:pointer">−</button>
      <span id="optQtyVal" style="min-width:30px;text-align:center;font-weight:800">1</span>
      <button type="button" onclick="optQty(1)" style="width:30px;height:30px;border-radius:50%;background:var(--green);color:#fff;border:none;font-weight:800;font-size:14px;cursor:pointer">+</button>
      <span style="font-size:11px;color:var(--text3)">{{ $p->stock }} available</span>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;margin:16px 0;font-weight:800">Total <span id="optTotal" style="color:var(--green);font-size:16px">₱{{ number_format($p->price,2) }}</span></div>
    <div style="display:flex;gap:10px">
      <button type="button" onclick="confirmOpt('cart')" style="flex:1;padding:11px;border-radius:999px;background:#fff;color:var(--green);border:1.5px solid var(--green);font-weight:700;cursor:pointer;font-family:inherit;font-size:13px">Add to Cart</button>
      <button type="button" onclick="confirmOpt('buy')" style="flex:1;padding:11px;border-radius:999px;background:var(--green);color:#fff;border:none;font-weight:700;cursor:pointer;font-family:inherit;font-size:13px">Buy Now</button>
    </div>
  </div>
</div>
<script>
(function(){
  var base = parseFloat("{{ $p->price }}");
  var maxQty = parseInt("{{ $p->stock }}") || 1;
  var qty = 1, variantId = '', variantAdj = 0, buyNow = false;
  var peso = function(n){ return '₱' + n.toLocaleString('en',{minimumFractionDigits:2,maximumFractionDigits:2}); };

  window.openOpt = function(isBuy){
    buyNow = !!isBuy; qty = 1; updateOpt();
    var m = document.getElementById('optModal');
    if(m) m.style.display = 'flex';
  };
  window.closeOpt = function(){
    var m = document.getElementById('optModal');
    if(m) m.style.display = 'none';
  };
  window.pickVariant = function(btn){
    document.querySelectorAll('.opt-pill').forEach(function(b){
      b.style.borderColor = 'var(--border)'; b.style.background = '#fff'; b.style.color = 'var(--text)';
    });
    btn.style.borderColor = 'var(--green)'; btn.style.background = '#f0fdf4'; btn.style.color = 'var(--green)';
    variantId = btn.getAttribute('data-variant');
    variantAdj = parseFloat(btn.getAttribute('data-adj')) || 0;
    updateOpt();
  };
  window.optQty = function(d){
    qty = Math.min(Math.max(1, qty + d), Math.max(1, maxQty));
    updateOpt();
  };
  function updateOpt(){
    document.getElementById('optQtyVal').textContent = qty;
    document.getElementById('optTotal').textContent = peso((base + variantAdj) * qty);
  }
  window.confirmOpt = function(mode){
    var url = (mode === 'buy' ? "{{ url('/buy/'.$p->id) }}" : "{{ url('/cart/add/'.$p->id) }}")
      + '?qty=' + qty + (variantId ? '&variant_id=' + variantId : '');
    window.location.href = url;
  };
  var modal = document.getElementById('optModal');
  if(modal) modal.addEventListener('click', function(e){ if(e.target === modal) closeOpt(); });
})();
</script>
@endsection
