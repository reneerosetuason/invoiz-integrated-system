@extends('layouts.website')
@section('content')
@php
  $buyer = \App\Support\BuyerSession::current();
  $inCart = false;
  if($buyer){
    $inCart = \App\Models\Cart::itemsFor($buyer['id'])->where('product_id',$p->id)->exists();
  }
  $hasDeal = $p->compare_at_price && $p->compare_at_price > $p->price;
  $backQuery = array_filter(['category'=>request('category'),'search'=>request('search'),'sort'=>request('sort')]);
  $backQs = $backQuery ? '?'.http_build_query($backQuery) : '';
  $backHref = request('source_store')
    ? url('/store/'.(int)request('source_store').'?'.http_build_query(array_filter(['search'=>request('search'),'sort'=>request('sort')])))
    : (request('source_sale') ? url('/sale'.$backQs) : (request('source_landing') ? url('/?'.http_build_query(array_filter(['sort'=>request('sort')]))) : url('/shop'.$backQs)));
@endphp
<div class="shopx">
<div class="wrap">
@if(session('error'))<div role="alert" style="color:#991b1b;margin-bottom:12px">{{ session('error') }}</div>@endif
@if($errors->any())<div role="alert" style="color:#991b1b;margin-bottom:12px">@foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif


  <div class="backrow rv">
    <a href="{{ $backHref }}" class="backlink"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>Back to store</a>
    <nav class="crumbs" aria-label="Breadcrumb">
      <a href="{{ url('/shop') }}" class="u-line">Shop</a>
      <span aria-hidden="true">/</span>
      @if($p->category)
        <a href="{{ url('/shop?category='.$p->category->id) }}" class="u-line">{{ $p->category->name }}</a>
        <span aria-hidden="true">/</span>
      @endif
      <span class="here" aria-current="page">{{ $p->name }}</span>
    </nav>
  </div>

  @if(session('success'))
    <div class="rv" role="status" style="margin-bottom:14px;padding:10px 14px;background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;border-radius:8px;font-size:13px;font-weight:600">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="rv" role="alert" style="margin-bottom:14px;padding:10px 14px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;border-radius:8px;font-size:13px;font-weight:600">{{ session('error') }}</div>
  @endif

  <div class="pdetail">
    @php $hasPImg = $p->image && file_exists(storage_path('app/public/'.$p->image)); @endphp
    <div class="gallery rv">
      <div class="gmain">
        @if($hasPImg)
          <img src="{{ asset('storage/'.$p->image) }}" alt="{{ $p->name }}" fetchpriority="high">
        @else
          <div class="pimg-fallback" role="img" aria-label="{{ $p->name }} — product image coming soon">
            <div class="mark">INVOIZ</div>
            <div class="soon">Product image coming soon</div>
          </div>
        @endif
      </div>
    </div>

    <div class="pinfo rv">
      @if($p->category)
        <div class="eyebrow">{{ $p->category->name }}</div>
      @endif
      <h1 class="ptitle">{{ $p->name }}</h1>
      @if($p->brand)
        <div class="pbrandline">{{ $p->brand }}</div>
      @endif
      <div class="soldby">Sold by <a href="{{ url('/store/'.$sellerUserId) }}" class="u-line">{{ \App\Models\Seller::nameFor($sellerUserId) }}</a></div>

      <div style="display:flex;align-items:center;gap:6px;margin-top:8px;color:#A16207;font-size:13px;font-weight:700">
        <span aria-label="{{ number_format((float)$p->rating,1) }} out of 5 stars">{{ $p->rating ? '★ '.number_format((float)$p->rating,1) : 'No ratings yet' }}</span>
        <span style="color:var(--text3);font-weight:500">{{ $p->reviews->count() ? '('.$p->reviews->count().' '.($p->reviews->count() === 1 ? 'review' : 'reviews').')' : ($p->rating ? '(rating on file)' : '(0 reviews)') }}</span>
      </div>

      <div class="pricerow">
        @php $effPrice = $salePrice !== null && $salePrice < (float) $p->price ? $salePrice : (float) $p->price; @endphp
        <span class="bignum">₱{{ number_format($effPrice,2) }}</span>
        @if($salePrice !== null && $salePrice < (float) $p->price)
          <span class="pwas">₱{{ number_format($p->price,2) }}</span>
          <span class="poff">−{{ (int) round((1 - (float)$salePrice/(float)$p->price)*100) }}% {{ $sale?->name ? '· '.$sale->name : 'Sale' }}</span>
        @elseif($hasDeal)
          <span class="pwas">₱{{ number_format($p->compare_at_price,2) }}</span>
          <span class="poff">−{{ (int) round((1 - (float)$p->price/(float)$p->compare_at_price)*100) }}%</span>
        @endif
      </div>

      @if($p->description)
        <p class="pdesc">{{ $p->description }}</p>
      @endif

      <div class="prow">
        <span>{{ $p->stock }} left</span>
        <span class="codpill"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>Cash on delivery</span>
      </div>

      @php $vgroups = $p->variants->where('status','active')->groupBy('variant_type'); @endphp
      @if($vgroups->isNotEmpty())
        <div class="vhelper">Available variations: {{ $vgroups->keys()->implode(', ') }} (choose in options)</div>
      @endif

      <div class="p-actions">
        <button type="button" onclick="openOpt()" class="btn-p btn-line"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>Add to Cart</button>
        <button type="button" onclick="openOpt(true)" class="btn-p btn-solid"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>Buy Now</button>
      </div>

      @if($inCart)
        <div style="margin-top:10px"><a href="{{ url('/cart') }}" class="btn-p btn-line"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>In Cart — View Cart</a></div>
      @endif

      <form method="POST" action="{{ url('/wishlist/'.$p->id.'/toggle') }}" style="margin-top:10px">
        @csrf
        <button type="submit" class="btn-p btn-line" style="width:100%">{{ $isFavorited ? '♥ Saved to Wishlist · Remove' : '♡ Add to Wishlist' }}</button>
      </form>

      <a href="{{ url('/chat/seller/'.$sellerUserId) }}" class="msglink u-line">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>Message Seller
      </a>

      <div class="trust">
        <div class="ti"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg><div><b>Cash on delivery</b><span>Pay at your door, no prepayment.</span></div></div>
        <div class="ti"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg><div><b>Rider-tracked delivery</b><span>Follow your parcel to arrival.</span></div></div>
        <div class="ti"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg><div><b>Easy returns</b><span>Report a problem, we make it right.</span></div></div>
      </div>
    </div>
  </div>

  @php
    $specs = [
      'Brand' => $p->brand, 'Model' => $p->model, 'SKU' => $p->sku,
      'Material' => $p->material, 'Dimensions' => $p->dimensions, 'Weight' => $p->weight,
      'Warranty' => $p->warranty, 'Origin' => $p->origin,
    ];
  @endphp
  <section class="details rv" aria-labelledby="pdetails-h">
    <div class="eyebrow">Details</div>
    <h2 id="pdetails-h">Product details</h2>
    @if($p->description)
      <p class="desc">{{ $p->description }}</p>
    @endif
    <dl class="specs">
      @foreach($specs as $label => $val)
        @if(!empty($val))
          <div class="row"><dt>{{ $label }}</dt><dd>{{ $val }}</dd></div>
        @endif
      @endforeach
      <div class="row"><dt>Category</dt><dd>{{ $p->category->name ?? '—' }}</dd></div>
      <div class="row"><dt>Stock</dt><dd>{{ $p->stock }} available · Cash on Delivery</dd></div>
    </dl>
  </section>

  <section class="details rv" aria-labelledby="reviews-h" style="margin-top:18px">
    <div class="eyebrow">Buyer feedback</div>
    <h2 id="reviews-h">Ratings and reviews</h2>
    @forelse($p->reviews as $review)
      <article style="padding:14px 0;border-bottom:1px solid var(--border-light)">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
          <strong style="font-size:13px">{{ $review->buyer?->full_name ?? trim(($review->buyer?->first_name ?? '').' '.($review->buyer?->last_name ?? '')) ?: 'Buyer' }}</strong>
          <span aria-label="{{ $review->rating }} out of 5 stars" style="color:#A16207;letter-spacing:1px">@for($star=1;$star<=5;$star++){{ $star <= (int)$review->rating ? '★' : '☆' }}@endfor</span>
        </div>
        @if($review->comment)<p style="margin:6px 0 0;font-size:13px;color:var(--text2);line-height:1.5">{{ $review->comment }}</p>@endif
        @if($review->seller_reply)
          <div style="margin:8px 0 0;padding:8px 10px;border-left:3px solid var(--green);background:#F7F6F2;border-radius:6px">
            <div style="font-size:11px;font-weight:700;color:var(--text2)">Seller reply</div>
            <p style="margin:3px 0 0;font-size:12.5px;color:var(--text2);line-height:1.5">{{ $review->seller_reply }}</p>
          </div>
        @endif
        <div style="font-size:11px;color:var(--text3);margin-top:5px">{{ $review->created_at?->diffForHumans() }}</div>
      </article>
    @empty
      <p style="font-size:13px;color:var(--text2)">No reviews yet. Buyers can rate this product after delivery.</p>
    @endforelse
  </section>

</div>
</div>

{{-- Order options retain the chosen stock, price and quantity through checkout. --}}
<div id="optModal" style="display:none;position:fixed;inset:0;z-index:100;background:rgba(10,47,58,.45);align-items:center;justify-content:center;padding:16px">
  <div style="border-radius:20px;max-width:420px;width:100%;padding:20px;max-height:90vh;overflow:auto" role="dialog" aria-modal="true" aria-label="Order options">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
      <div style="font-weight:800;font-size:15px">Order options</div>
      <button type="button" onclick="closeOpt()" aria-label="Close options" style="background:transparent;border:none;cursor:pointer;color:var(--text3);padding:4px"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div style="font-size:12px;color:var(--text2);margin-bottom:12px">{{ $p->name }} · <b id="optBasePrice" style="color:var(--green)">₱{{ number_format($effPrice,2) }}</b></div>
    @if($vgroups->isNotEmpty())
      <div style="font-size:12px;font-weight:700;margin-bottom:6px">Variation <span style="font-weight:400;color:var(--text3)">(choose 1)</span></div>
      @foreach($vgroups as $type => $opts)
        <div style="font-size:11px;color:var(--text3);margin:8px 0 6px">{{ $type }}</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap" data-vgroup="{{ $type }}">
          @foreach($opts as $v)
            @php $vOut = ((int) $v->stock) < 1; @endphp
            <button type="button" class="opt-pill" data-variant="{{ $v->id }}" data-adj="{{ $v->price_adjustment }}" data-stock="{{ (int) $v->stock }}" data-label="{{ $v->variant_type }}: {{ $v->variant_value }}" @if($vOut) disabled title="Out of stock" @else onclick="pickVariant(this)" @endif style="padding:7px 14px;border-radius:999px;border:1px solid var(--border);background:{{ $vOut ? '#f3f4f6' : '#fff' }};font-size:12px;font-weight:600;cursor:{{ $vOut ? 'not-allowed' : 'pointer' }};font-family:inherit;{{ $vOut ? 'opacity:.5;text-decoration:line-through;' : '' }}">{{ $v->variant_value }}{{ $v->price_adjustment > 0 ? ' (+₱'.number_format($v->price_adjustment,2).')' : '' }}</button>
          @endforeach
        </div>
      @endforeach
    @endif
    <div style="font-size:12px;font-weight:700;margin:14px 0 6px">Quantity</div>
    <div style="display:flex;align-items:center;gap:10px">
      <button type="button" onclick="optQty(-1)" aria-label="Decrease quantity" style="width:30px;height:30px;border-radius:50%;background:#fff;border:1px solid var(--border);font-weight:800;font-size:14px;cursor:pointer">−</button>
      <span id="optQtyVal" style="min-width:30px;text-align:center;font-weight:800">1</span>
      <button type="button" onclick="optQty(1)" aria-label="Increase quantity" style="width:30px;height:30px;border-radius:50%;background:var(--green);color:#fff;border:none;font-weight:800;font-size:14px;cursor:pointer">+</button>
      <span id="optAvail" style="font-size:11px;color:var(--text3)">{{ $p->stock }} available</span>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;margin:16px 0;font-weight:800">Total <span id="optTotal" style="color:var(--green);font-size:16px">₱{{ number_format($effPrice,2) }}</span></div>
    <p id="optError" role="alert" hidden style="color:#991b1b;margin-bottom:10px">This item is out of stock.</p>
    <div style="display:flex;gap:10px">
      <button type="button" onclick="confirmOpt('cart')" class="opt-confirm" style="flex:1;padding:11px;border-radius:999px;background:#fff;color:var(--green);border:1.5px solid var(--green);font-weight:700;cursor:pointer;font-family:inherit;font-size:13px">Add to Cart</button>
      <button type="button" onclick="confirmOpt('buy')" class="opt-confirm" style="flex:1;padding:11px;border-radius:999px;background:var(--green);color:#fff;border:none;font-weight:700;cursor:pointer;font-family:inherit;font-size:13px">Buy Now</button>
    </div>
  </div>
</div>
<script>
(function(){
  var base = Number(@json($effPrice));
  var baseMax = Math.min(200, Math.max(0, Number(@json((int) $p->stock)) || 0));
  var hasVariants = @json($vgroups->isNotEmpty());
  var maxQty = baseMax, qty = 1, variantId = '', variantAdj = 0;
  var submitting = false, opener = null, previousOverflow = '';
  var modal = document.getElementById('optModal');
  var peso = function(n){ return '₱' + n.toLocaleString('en',{minimumFractionDigits:2,maximumFractionDigits:2}); };
  function resetPills(){
    document.querySelectorAll('.opt-pill').forEach(function(b){
      b.style.borderColor = 'var(--border)'; b.style.background = '#fff'; b.style.color = 'var(--text)';
      b.setAttribute('aria-pressed', 'false');
    });
  }
  function updateOpt(){
    document.getElementById('optQtyVal').textContent = qty;
    document.getElementById('optBasePrice').textContent = peso(base + variantAdj);
    document.getElementById('optTotal').textContent = peso((base + variantAdj) * qty);
    document.getElementById('optAvail').textContent = maxQty + ' available' + (variantId ? ' for this variation' : '');
    document.getElementById('optError').hidden = maxQty > 0;
    document.querySelectorAll('.opt-confirm').forEach(function(button){
      button.disabled = submitting || maxQty < 1 || (hasVariants && !variantId);
    });
  }
  window.openOpt = function(){
    opener = document.activeElement;
    qty = baseMax > 0 ? 1 : 0; variantId = ''; variantAdj = 0; maxQty = baseMax; submitting = false;
    resetPills();
    var first = modal.querySelector('.opt-pill:not(:disabled)');
    if(first) window.pickVariant(first);
    else if(hasVariants) { maxQty = 0; qty = 0; }
    updateOpt();
    previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    modal.style.display = 'flex';
    modal.querySelector('button[aria-label="Close options"]').focus();
  };
  window.closeOpt = function(){
    modal.style.display = 'none';
    document.body.style.overflow = previousOverflow;
    if(opener && opener.isConnected) opener.focus({preventScroll:true});
  };
  window.pickVariant = function(btn){
    if(btn.disabled) return;
    resetPills();
    btn.style.borderColor = 'var(--green)'; btn.style.background = '#f0fdf4'; btn.style.color = 'var(--green)';
    btn.setAttribute('aria-pressed', 'true');
    variantId = btn.getAttribute('data-variant');
    variantAdj = Number(btn.getAttribute('data-adj')) || 0;
    maxQty = Math.min(200, Math.max(0, Number(btn.getAttribute('data-stock')) || 0));
    qty = maxQty > 0 ? Math.min(Math.max(1, qty), maxQty) : 0;
    updateOpt();
  };
  window.optQty = function(d){
    qty = maxQty > 0 ? Math.min(Math.max(1, qty + d), maxQty) : 0;
    updateOpt();
  };
  window.confirmOpt = function(mode){
    if(submitting || maxQty < 1 || qty < 1 || (hasVariants && !variantId)) return;
    submitting = true;
    updateOpt();
    if(mode === 'buy'){
      window.location.href = @json(url('/buy/'.$p->id)) + '?qty=' + qty + (variantId ? '&variant_id=' + variantId : '');
      return;
    }
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = @json(url('/cart/add/'.$p->id));
    var fields = {_token: @json(csrf_token()), qty: qty, variant_id: variantId || ''};
    Object.keys(fields).forEach(function(name){
      var input = document.createElement('input');
      input.type = 'hidden'; input.name = name; input.value = fields[name]; form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
  };
  modal.addEventListener('click', function(e){ if(e.target === modal) window.closeOpt(); });
  modal.addEventListener('keydown', function(e){
    if(e.key === 'Escape') { e.preventDefault(); window.closeOpt(); return; }
    if(e.key !== 'Tab') return;
    var targets = Array.from(modal.querySelectorAll('button:not(:disabled)'));
    var first = targets[0], last = targets[targets.length-1];
    if(e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if(!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  });
  window.addEventListener('pageshow', function(){ submitting = false; updateOpt(); });
})();
</script>
@endsection
