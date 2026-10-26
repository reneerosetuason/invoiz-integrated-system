@extends('layouts.website')
@section('content')
<div class="wishlistx buyerfeature-page">
  <div class="bfp-wrap">
    <header class="bfp-head">
      <div>
        <h1>Wishlist</h1>
        <p class="bfp-description wish-description">Your saved items</p>
      </div>
      <span class="bfp-meta">{{ $favorites->total() }} saved {{ $favorites->total() === 1 ? 'product' : 'products' }}</span>
    </header>

    @if(session('success'))
      <div class="bfp-flash bfp-flash-success" role="status">{{ session('success') }}</div>
    @endif
    @if(session('error'))
      <div class="bfp-flash bfp-flash-error" role="alert">{{ session('error') }}</div>
    @endif

    @if($favorites->isEmpty())
      <div class="bfp-empty">
        <svg class="bfp-empty-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/></svg>
        <h2>Your wishlist is empty</h2>
        <p>Save products while browsing and they’ll be collected here.</p>
        <a href="{{ url('/shop') }}" class="btn-p btn-solid">Browse products</a>
      </div>
    @else
      <div class="pgrid wish-grid">
        @foreach($favorites as $favorite)
          @php($p = $favorite->product)
          @if($p)
            <article class="pcard wish-card">
              <div class="pimg wish-media">
                <a href="{{ url('/product/'.$p->id) }}" class="wish-image-link" aria-label="View {{ $p->name }}">
                  @if($p->primary_image_url)
                    <img src="{{ $p->primary_image_url }}" alt="{{ $p->name }}" loading="lazy" decoding="async">
                  @else
                    <div class="pimg-fallback" role="img" aria-label="{{ $p->name }} — product image coming soon">
                      <div class="mark">INVOIZ</div>
                      <div class="soon">Product image coming soon</div>
                    </div>
                  @endif
                </a>
                <form method="POST" action="{{ url('/wishlist/'.$p->id.'/toggle') }}" class="wish-heart-form">
                  @csrf
                  <button type="submit" class="wish-heart" aria-label="Remove {{ $p->name }} from wishlist" aria-pressed="true" title="Remove">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/></svg>
                  </button>
                </form>
              </div>
              <div class="pbody wish-body">
                <div class="pbrand wish-category">{{ $p->category->name ?? 'Product' }}</div>
                <a href="{{ url('/product/'.$p->id) }}" class="pname wish-name">{{ $p->name }}</a>
                <div class="pprice wish-price">₱{{ number_format($p->price, 2) }}</div>
                <div class="wish-actions">
                  @if($p->status === 'active')
                    <a href="{{ url('/cart/add/'.$p->id) }}" class="btn-p bfp-action wish-add">
                      <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                      Add to cart
                    </a>
                  @else
                    <span class="wish-unavailable">Unavailable</span>
                  @endif
                </div>
              </div>
            </article>
          @endif
        @endforeach
      </div>
      <div class="bfp-pager">{{ $favorites->links() }}</div>
    @endif
  </div>
</div>
@endsection
