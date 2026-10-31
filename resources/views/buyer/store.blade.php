@extends('buyer.layouts.website')
@section('content')
@php
  $initial = strtoupper(substr($name,0,1));
  $h = array_sum(array_map('ord', str_split($name)));
  $pal = ['#0F766E','#1D4ED8','#7C3AED','#DB2777','#EA580C'];
  $color = $pal[$h % count($pal)];
@endphp
<link rel="stylesheet" href="{{ asset('assets/buyer/css/store.css') }}">

<a href="{{ url('/') }}" style="color:var(--text2);text-decoration:none;font-weight:600;font-size:12px">← Back to store</a>

<div class="st-head" style="margin-top:12px">
  @if(!empty($storeLogo))
    <img src="{{ $storeLogo }}" alt="{{ $name }}" class="st-avatar" style="--store-color:{{ $color }};object-fit:cover;padding:0">
  @else
    <div class="st-avatar" style="--store-color:{{ $color }}">{{ $initial }}</div>
  @endif
  <div style="flex:1;min-width:200px">
    <div style="font-weight:800;font-size:18px">{{ $name }}</div>
    <div style="font-size:11px;color:var(--text3);margin-top:2px">Verified seller · Cash on Delivery</div>
    <div class="st-stats">
      <div><b>{{ $rating ? number_format($rating,1) : '—' }}</b><span>Rating</span></div>
      <div><b>{{ number_format($followers) }}</b><span>Followers</span></div>
      <div><b>{{ number_format($products->count()) }}</b><span>Products</span></div>
    </div>
  </div>
  <div class="st-actions">
    @if($buyer && $buyer['id'] != $sellerId)
      <form method="POST" action="{{ url('/store/'.$sellerId.'/follow') }}" style="margin:0">
        @csrf
        <button type="submit" class="st-btn {{ $following ? 'st-following' : 'st-follow' }}">{{ $following ? 'Following' : '+ Follow' }}</button>
      </form>
      <a class="st-btn st-chat" href="{{ url('/chat/seller/'.$sellerId) }}">Chat</a>
    @elseif(!$buyer)
      <a class="st-btn st-follow" href="{{ url('/login') }}">+ Follow</a>
    @endif
  </div>
</div>

@if(session('success'))<div style="background:#ecfdf5;color:#065f46;padding:10px;border-radius:8px;border:1px solid #a7f3d0;margin-top:12px;font-size:13px;font-weight:600">{{ session('success') }}</div>@endif

@if(!empty($vacation))
<div style="background:#FFFBEB;color:#92400e;padding:11px 14px;border-radius:10px;border:1px solid #FDE68A;margin-top:12px;font-size:13px;font-weight:600;display:flex;align-items:center;gap:8px;">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#B45309" stroke-width="2" style="flex-shrink:0;"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
  <span>{{ $vacation }}</span>
</div>
@endif

<div class="st-tabs">
  <a class="st-tab {{ $tab==='products' ? 'active' : '' }}" href="{{ url('/store/'.$sellerId) }}">Products ({{ $products->count() }})</a>
  <a class="st-tab {{ $tab==='reviews' ? 'active' : '' }}" href="{{ url('/store/'.$sellerId.'?tab=reviews') }}">Reviews ({{ $reviews->count() }})</a>
</div>

@if($tab === 'reviews')
  @forelse($reviews as $r)    <div class="st-rev">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <b style="font-size:13px">{{ trim(($r->buyer->first_name ?? '').' '.($r->buyer->last_name ?? '')) ?: 'Buyer' }}</b>
        <span class="st-stars" style="display:inline-flex;gap:1px">@for($s = 1; $s <= 5; $s++)<svg width="13" height="13" viewBox="0 0 20 20" class="{{ $s <= (int)$r->rating ? 'st-star-fill' : 'st-star-empty' }}"><path d="M10 1.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L10 14.8l-5.2 2.8 1-5.8-4.3-4.1 5.9-.9L10 1.5z"/></svg>@endfor</span>
      </div>
      <div style="font-size:11px;color:var(--text3);margin-top:2px">{{ $r->product->name ?? '' }} · {{ $r->created_at->diffForHumans() }}</div>
      @if($r->comment)<div style="font-size:13px;margin-top:6px">{{ $r->comment }}</div>@endif
      @if($r->seller_reply)
        <div style="margin-top:8px;padding:8px 10px;border-left:3px solid var(--green);background:#F7F6F2;border-radius:6px">
          <div style="font-size:11px;font-weight:700;color:var(--text2)">Seller reply</div>
          <div style="font-size:12.5px;margin-top:3px">{{ $r->seller_reply }}</div>
        </div>
      @endif
    </div>
  @empty
    <div style="text-align:center;color:var(--text3);padding:40px">No reviews yet.</div>
  @endforelse
@else
  <form method="GET" action="{{ url('/store/'.$sellerId) }}" style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap">
    <input type="text" name="search" value="{{ $storeSearch ?? '' }}" placeholder="Search in this store…" style="flex:1;min-width:180px;padding:9px 14px;border:1px solid var(--border);border-radius:999px;font-size:12.5px;font-family:inherit;outline:none">
    <select name="sort" onchange="this.form.submit()" style="padding:9px 12px;border:1px solid var(--border);border-radius:999px;font-size:12.5px;font-family:inherit;background:#fff;cursor:pointer">
      <option value="newest" {{ ($storeSort ?? '')==='newest' ? 'selected' : '' }}>Newest</option>
      <option value="price_asc" {{ ($storeSort ?? '')==='price_asc' ? 'selected' : '' }}>Price: Low to High</option>
      <option value="price_desc" {{ ($storeSort ?? '')==='price_desc' ? 'selected' : '' }}>Price: High to Low</option>
      <option value="rating" {{ ($storeSort ?? '')==='rating' ? 'selected' : '' }}>Top Rated</option>
      <option value="most_reviewed" {{ ($storeSort ?? '')==='most_reviewed' ? 'selected' : '' }}>Most Reviewed</option>
    </select>
    <button type="submit" style="padding:9px 18px;border-radius:999px;background:var(--green);color:#fff;border:none;font-weight:700;font-size:12.5px;cursor:pointer;font-family:inherit">Search</button>
    @if(!empty($storeSearch))<a href="{{ url('/store/'.$sellerId) }}" style="padding:9px 14px;font-size:12.5px;color:var(--text3);text-decoration:none;font-weight:600">Clear</a>@endif
  </form>
  @if($products->isEmpty())
    <div style="text-align:center;color:var(--text3);padding:40px">{{ !empty($storeSearch) ? 'No products match your search.' : 'No products in this store yet.' }}</div>
  @else
    <div class="st-grid">
      @foreach($products as $p)
        @php
          $hh = array_sum(array_map('ord', str_split($p->name)));
          $cc = $pal[$hh % count($pal)];
          $w = preg_split('/\s+/', trim($p->name));
          $mono = strtoupper(substr($w[0] ?? '',0,1).substr($w[1] ?? '',0,1));
          $pImg = (!empty($p->image) && file_exists(storage_path('app/public/'.$p->image))) ? asset('storage/'.$p->image) : null;
        @endphp
        <div style="position:relative;display:flex">
        <a class="st-link" href="{{ url('/product/'.$p->id.'?'.http_build_query(array_filter(['search'=>$storeSearch ?? null,'sort'=>$storeSort ?? null,'source_store'=>$sellerId]))) }}">
          <div class="st-card" style="flex:1">
            @if($pImg)
              <div class="st-sw" style="padding:0;overflow:hidden;background:#f3f4f6;aspect-ratio:4/3;height:auto"><img src="{{ $pImg }}" alt="{{ $p->name }}" style="width:100%;height:100%;object-fit:cover;display:block" loading="lazy"></div>
            @else
              <div class="st-sw" style="background:{{ $cc }};aspect-ratio:4/3;height:auto"><div class="mono">{{ $mono }}</div></div>
            @endif
            <div class="st-body">
              <div class="st-name">{{ $p->name }}</div>
              @if((float)($p->visible_rating ?? $p->rating ?? 0) > 0)
                <div class="st-rating" style="display:flex;align-items:center;gap:3px;">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="#A16207" stroke="#A16207" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                  <span>{{ number_format((float)($p->visible_rating ?? $p->rating),1) }}</span>
                  <span style="color:var(--text3);font-weight:500">{{ (int)($p->visible_reviews_count ?? 0) > 0 ? '('.$p->visible_reviews_count.')' : '(rating)' }}</span>
                </div>
              @else
                <div class="st-rating" style="color:var(--text3);font-weight:500">No ratings yet</div>
              @endif
              <div class="st-price">₱{{ number_format($p->price,2) }}</div>
            </div>
          </div>
        </a>
        <form method="POST" action="{{ url('/wishlist/'.$p->id.'/toggle') }}" style="position:absolute;right:10px;top:10px;z-index:2;margin:0">
          @csrf
          <button type="submit" aria-label="{{ in_array((int)$p->id, $favoriteIds ?? [], true) ? 'Remove from wishlist' : 'Add to wishlist' }}" style="width:34px;height:34px;border:0;border-radius:50%;background:rgba(255,255,255,.95);box-shadow:0 2px 8px rgba(0,0,0,.14);display:flex;align-items:center;justify-content:center;color:{{ in_array((int)$p->id, $favoriteIds ?? [], true) ? '#dc2626' : '#64748B' }};cursor:pointer">
            @if(in_array((int)$p->id, $favoriteIds ?? [], true))
              <svg width="17" height="17" viewBox="0 0 24 24" fill="#dc2626" stroke="#dc2626" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            @else
              <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            @endif
          </button>
        </form>
        </div>
      @endforeach
    </div>
  @endif
@endif
@endsection
