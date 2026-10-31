@extends('buyer.layouts.website')
@section('content')

<div class="shopx">
<div class="wrap">

  <section class="hero rv" aria-labelledby="shop-hero-h">
    <div>
      <span class="chip">Cash on delivery nationwide</span>
      <h1 id="shop-hero-h">Curated for you</h1>
      <p class="lede">Premium essentials at honest prices. Vouchers &amp; verified sellers.</p>
      <div class="hero-cta">
        <a href="{{ url('/shop') }}" class="btn-p btn-solid">Shop collection
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </a>
      </div>
    </div>
    <div class="vcard">
      <img src="{{ asset('storage/logos/logo.png') }}" onerror="this.onerror=null;this.src='{{ asset('assets/shared/images/logo.png') }}'" alt="Invoiz" loading="lazy">
      <div class="vstore">Desktop Store</div>
      <div><span class="ticket">Welcome Save 15 COD</span></div>
    </div>
  </section>

  <nav class="chips rv" aria-label="Filter by category">
    <a href="{{ url('/shop') }}" class="chip-f {{ request('category')?'':'on' }}">All</a>
    @foreach($categories as $c)
      <a href="{{ url('/shop?category='.$c->id) }}" class="chip-f {{ (string)request('category')===(string)$c->id ? 'on':'' }}">{{ $c->name }}</a>
    @endforeach
  </nav>

  <div class="shead rv">
    <div class="eyebrow">Shop</div>
    @php $activeCat = $categories->firstWhere('id', (int) request('category')); @endphp
    <h2>{{ $activeCat ? $activeCat->name : 'All products' }}</h2>
    <div class="count">{{ $products->count() }} {{ $products->count() === 1 ? 'product' : 'products' }}</div>
  </div>

  <form method="GET" action="{{ !empty($saleMode) && $saleMode ? url('/sale') : url('/shop') }}" class="catalog-sort rv" style="display:flex;gap:8px;align-items:center;justify-content:flex-end;flex-wrap:wrap;margin:14px 0">
    @if(request('category'))<input type="hidden" name="category" value="{{ request('category') }}">@endif
    @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
    <label for="catalog-sort" style="font-size:12px;color:var(--muted);font-weight:600">Sort by</label>
    <select id="catalog-sort" name="sort" style="padding:9px 13px;border:1px solid var(--hair);border-radius:999px;background:#fff;color:#0E4A57;font:600 12px Inter, sans-serif">
      <option value="recommended" @selected(($sort ?? 'recommended') === 'recommended')>Recommended</option>
      <option value="newest" @selected(($sort ?? '') === 'newest')>Newest</option>
      <option value="price_asc" @selected(($sort ?? '') === 'price_asc')>Price: Low to High</option>
      <option value="price_desc" @selected(($sort ?? '') === 'price_desc')>Price: High to Low</option>
      <option value="rating" @selected(($sort ?? '') === 'rating')>Top Rated</option>
      <option value="most_reviewed" @selected(($sort ?? '') === 'most_reviewed')>Most Reviewed</option>
    </select>
    <button type="submit" class="btn-p btn-line" style="padding:9px 16px">Apply</button>
  </form>

  <div class="pgrid">
    @foreach($products as $p)
      @php
        $hasRealImage = $p->image && file_exists(storage_path('app/public/'.$p->image));
        $qs = http_build_query(array_filter(['category'=>request('category'),'search'=>request('search'),'sort'=>$sort ?? null,'source_sale'=>!empty($saleMode) && $saleMode ? 1 : null]));
        $qs = $qs ? '?'.$qs : '';
        $hasDeal = $p->compare_at_price && $p->compare_at_price > $p->price;
      @endphp
      <div class="pcard-wrap rv" style="position:relative">
      <a href="{{ url('/product/'.$p->id.$qs) }}" class="pcard">
        <div class="pimg">
          @if($hasRealImage)
            <img src="{{ asset('storage/'.$p->image) }}" alt="{{ $p->name }}" loading="lazy" decoding="async">
          @else
            <div class="pimg-fallback" role="img" aria-label="{{ $p->name }} — product image coming soon">
              <div class="mark">INVOIZ</div>
              <div class="soon">Product image coming soon</div>
            </div>
          @endif
        </div>
        <div class="pbody">
          @if($p->brand)
            <div class="pbrand">{{ $p->brand }}</div>
          @endif
          <div class="pname">{{ $p->name }}</div>
          <div class="pprice-row">
            @php
              $onSale = !empty($p->sale_price) && (float) $p->sale_price < (float) $p->price;
              $shownPrice = $onSale ? (float) $p->sale_price : (float) $p->price;
            @endphp
            <span class="pprice">₱{{ number_format($shownPrice,2) }}</span>
            @if($onSale)
              <span class="pwas">₱{{ number_format($p->price,2) }}</span>
              <span class="poff">Sale</span>
            @elseif($hasDeal)
              <span class="pwas">₱{{ number_format($p->compare_at_price,2) }}</span>
              <span class="poff">−{{ (int) round((1 - (float)$p->price/(float)$p->compare_at_price)*100) }}%</span>
            @endif
          </div>
          <div class="pmeta">
            <span>{{ $p->stock }} left</span>
            @if((float)($p->visible_rating ?? $p->rating ?? 0) > 0)<span style="color:#A16207;font-weight:700;display:inline-flex;align-items:center;gap:3px;"><svg width="12" height="12" viewBox="0 0 24 24" fill="#A16207" stroke="#A16207" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg> {{ number_format((float)($p->visible_rating ?? $p->rating),1) }} <span style="color:var(--muted);font-weight:500">{{ (int)($p->visible_reviews_count ?? 0) > 0 ? '('.$p->visible_reviews_count.')' : '(rating)' }}</span></span>@endif
            <span class="codpill"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>COD</span>
          </div>
        </div>
      </a>
      <form method="POST" action="{{ url('/wishlist/'.$p->id.'/toggle') }}" style="position:absolute;right:10px;top:10px;z-index:2;margin:0">
        @csrf
        <button type="submit" aria-label="{{ in_array((int)$p->id, $favoriteIds ?? [], true) ? 'Remove from wishlist' : 'Add to wishlist' }}" title="{{ in_array((int)$p->id, $favoriteIds ?? [], true) ? 'Remove from wishlist' : 'Add to wishlist' }}" style="width:36px;height:36px;border:0;border-radius:50%;background:rgba(255,255,255,.95);box-shadow:0 2px 8px rgba(0,0,0,.14);display:flex;align-items:center;justify-content:center;color:{{ in_array((int)$p->id, $favoriteIds ?? [], true) ? '#dc2626' : '#64748B' }};cursor:pointer">
          @if(in_array((int)$p->id, $favoriteIds ?? [], true))
            <svg width="18" height="18" viewBox="0 0 24 24" fill="#dc2626" stroke="#dc2626" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
          @else
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
          @endif
        </button>
      </form>
      </div>
    @endforeach
  </div>

  @if($products->isEmpty())
    <div class="empty rv">
      <div class="t1">No products found</div>
      <div class="t2">Try adjusting your search or filter</div>
    </div>
  @endif

</div>
</div>

@endsection
