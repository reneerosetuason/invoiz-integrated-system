@extends('layouts.website')
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
      <img src="{{ asset('storage/logos/logo.png') }}" onerror="this.onerror=null;this.src='{{ asset('images/logo.png') }}'" alt="Invoiz" loading="lazy">
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

  <div class="pgrid">
    @foreach($products as $p)
      @php
        $hasRealImage = $p->image && file_exists(storage_path('app/public/'.$p->image));
        $qs = request('category') ? '?category='.request('category').(request('search') ? '&search='.urlencode(request('search')) : '') : (request('search') ? '?search='.urlencode(request('search')) : '');
        $hasDeal = $p->compare_at_price && $p->compare_at_price > $p->price;
      @endphp
      <a href="{{ url('/product/'.$p->id.$qs) }}" class="pcard rv">
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
            <span class="pprice">₱{{ number_format($p->price,2) }}</span>
            @if($hasDeal)
              <span class="pwas">₱{{ number_format($p->compare_at_price,2) }}</span>
              <span class="poff">−{{ (int) round((1 - (float)$p->price/(float)$p->compare_at_price)*100) }}%</span>
            @endif
          </div>
          <div class="pmeta">
            <span>{{ $p->stock }} left</span>
            <span class="codpill"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>COD</span>
          </div>
        </div>
      </a>
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
