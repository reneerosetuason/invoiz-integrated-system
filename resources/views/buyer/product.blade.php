@extends('buyer.layouts.website')
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
        <span aria-label="{{ number_format((float)$p->rating,1) }} out of 5 stars" style="display:inline-flex;align-items:center;gap:4px;">
          @if($p->rating)
            <svg width="13" height="13" viewBox="0 0 24 24" fill="#A16207" stroke="#A16207" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            <span>{{ number_format((float)$p->rating,1) }}</span>
          @else
            <span>No ratings yet</span>
          @endif
        </span>
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
        <div style="margin:14px 0 10px">
          @foreach($vgroups as $type => $opts)
            <div style="font-size:12px;font-weight:700;margin:6px 0 6px">{{ $type }}</div>
            <div style="display:flex;gap:8px;flex-wrap:wrap" data-vgroup="{{ $type }}">
              @foreach($opts as $v)
                @php $vOut = min((int) $p->stock, (int) $v->stock) < 1; @endphp
                <button type="button" class="opt-pill" data-variant="{{ $v->id }}" data-adj="{{ $v->price_adjustment }}" data-stock="{{ max(0, min((int) $p->stock, (int) $v->stock)) }}" data-label="{{ $v->variant_type }}: {{ $v->variant_value }}" @if($vOut) disabled title="Out of stock" @else onclick="pickVariant(this)" @endif style="padding:7px 14px;border-radius:999px;border:1px solid var(--border);background:{{ $vOut ? '#f3f4f6' : '#fff' }};font-size:12px;font-weight:600;cursor:{{ $vOut ? 'not-allowed' : 'pointer' }};font-family:inherit;{{ $vOut ? 'opacity:.5;text-decoration:line-through;' : '' }}">{{ $v->variant_value }}{{ $v->price_adjustment > 0 ? ' (+₱'.number_format($v->price_adjustment,2).')' : '' }}</button>
              @endforeach
            </div>
          @endforeach
        </div>
      @endif

      <div style="margin:12px 0 16px">
        <div style="font-size:12px;font-weight:700;margin-bottom:6px">Quantity</div>
        <div style="display:flex;align-items:center;gap:10px">
          <button type="button" onclick="optQty(-1)" aria-label="Decrease quantity" style="width:32px;height:32px;border-radius:50%;background:#fff;border:1px solid var(--border);font-weight:800;font-size:14px;cursor:pointer">−</button>
          <span id="pageQtyVal" style="min-width:32px;text-align:center;font-weight:800;font-size:14px">1</span>
          <button type="button" onclick="optQty(1)" aria-label="Increase quantity" style="width:32px;height:32px;border-radius:50%;background:var(--green);color:#fff;border:none;font-weight:800;font-size:14px;cursor:pointer">+</button>
          <span id="pageAvail" style="font-size:12px;color:var(--text3);margin-left:6px">{{ $p->stock }} available</span>
        </div>
      </div>

      <div class="p-actions">
        <button type="button" onclick="confirmOpt('cart')" class="btn-p btn-line opt-btn-cart"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>Add to Cart</button>
        <button type="button" onclick="confirmOpt('buy')" class="btn-p btn-solid opt-btn-buy"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>Buy Now</button>
      </div>

      @if($inCart)
        <div style="margin-top:10px"><a href="{{ url('/cart') }}" class="btn-p btn-line"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>In Cart — View Cart</a></div>
      @endif

      <form method="POST" action="{{ url('/wishlist/'.$p->id.'/toggle') }}" style="margin-top:10px">
        @csrf
        <button type="submit" class="btn-p btn-line" style="width:100%;display:flex;align-items:center;justify-content:center;gap:6px;">
          @if($isFavorited)
            <svg width="16" height="16" viewBox="0 0 24 24" fill="#dc2626" stroke="#dc2626" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            <span>Saved to Wishlist · Remove</span>
          @else
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            <span>Add to Wishlist</span>
          @endif
        </button>
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
          <span aria-label="{{ $review->rating }} out of 5 stars" style="display:inline-flex;align-items:center;gap:2px;">
            @for($star=1;$star<=5;$star++)
              @if($star <= (int)$review->rating)
                <svg width="12" height="12" viewBox="0 0 24 24" fill="#A16207" stroke="#A16207"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
              @else
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#D8D5CC" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
              @endif
            @endfor
          </span>
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
  <div style="background:#fff;border-radius:20px;max-width:420px;width:100%;padding:20px;max-height:90vh;overflow:auto;box-shadow:0 20px 40px rgba(0,0,0,0.25)" role="dialog" aria-modal="true" aria-label="Order options">
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
            @php $vOut = min((int) $p->stock, (int) $v->stock) < 1; @endphp
            <button type="button" class="opt-pill" data-variant="{{ $v->id }}" data-adj="{{ $v->price_adjustment }}" data-stock="{{ max(0, min((int) $p->stock, (int) $v->stock)) }}" data-label="{{ $v->variant_type }}: {{ $v->variant_value }}" @if($vOut) disabled title="Out of stock" @else onclick="pickVariant(this)" @endif style="padding:7px 14px;border-radius:999px;border:1px solid var(--border);background:{{ $vOut ? '#f3f4f6' : '#fff' }};font-size:12px;font-weight:600;cursor:{{ $vOut ? 'not-allowed' : 'pointer' }};font-family:inherit;{{ $vOut ? 'opacity:.5;text-decoration:line-through;' : '' }}">{{ $v->variant_value }}{{ $v->price_adjustment > 0 ? ' (+₱'.number_format($v->price_adjustment,2).')' : '' }}</button>
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
<script id="product-config-script" src="{{ asset('assets/buyer/js/product.js') }}?v={{ filemtime(public_path('assets/buyer/js/product.js')) }}" data-config="{{ json_encode([($effPrice), ((int) $p->stock), ($vgroups->isNotEmpty()), ('/buy/'.$p->id), ('/cart/add/'.$p->id), (csrf_token()), ((int)$p->id)], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) }}"></script>
@endsection
