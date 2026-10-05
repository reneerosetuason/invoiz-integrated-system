@extends('layouts.website')
@section('content')
@php
  $initial = strtoupper(substr($name,0,1));
  $h = array_sum(array_map('ord', str_split($name)));
  $pal = ['#0F766E','#1D4ED8','#7C3AED','#DB2777','#EA580C'];
  $color = $pal[$h % count($pal)];
@endphp
<style>
.st-head{background:#fff;border:1px solid var(--border);border-radius:14px;padding:20px;display:flex;gap:16px;align-items:center;flex-wrap:wrap}
.st-avatar{width:72px;height:72px;border-radius:50%;background:{{ $color }};display:grid;place-items:center;color:#fff;font-weight:800;font-size:26px;flex-shrink:0}
.st-stats{display:flex;gap:28px;margin-top:10px}
.st-stats b{display:block;font-size:16px}
.st-stats span{font-size:11px;color:var(--text3)}
.st-actions{display:flex;gap:10px;margin-left:auto}
.st-btn{padding:9px 20px;border-radius:999px;font-weight:700;font-size:13px;cursor:pointer;text-decoration:none;font-family:inherit}
.st-follow{background:var(--green);color:#fff;border:none}
.st-following{background:#fff;color:var(--text2);border:1px solid var(--border)}
.st-chat{background:#fff;color:var(--green-dark);border:1px solid var(--border)}
.st-tabs{display:flex;gap:8px;margin:16px 0}
.st-tab{padding:8px 18px;border-radius:999px;font-weight:700;font-size:12.5px;text-decoration:none;border:1px solid var(--border);color:var(--text2);background:#fff}
.st-tab.active{background:var(--primary-dark,#0E4A57);color:#fff;border-color:var(--primary-dark,#0E4A57)}
.st-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
.st-card{background:#fff;border:1px solid var(--border);border-radius:12px;overflow:hidden;display:flex;flex-direction:column;transition:box-shadow .2s,transform .2s}
a.st-link{text-decoration:none;color:inherit;display:flex}
a.st-link:hover .st-card{box-shadow:0 8px 24px rgba(0,0,0,.12);transform:translateY(-3px)}
.st-sw{height:120px;display:flex;align-items:flex-end;padding:12px}
.st-sw .mono{width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.92);display:grid;place-items:center;font-weight:800;font-size:13px;color:#1B1B1E}
.st-body{padding:14px;display:flex;flex-direction:column;flex:1}
.st-name{font-size:13.5px;font-weight:600;color:#1B1B1E;line-height:1.35;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:37px}
.st-price{color:var(--primary-dark,#0E4A57);font-weight:800;font-size:15px;margin-top:auto;padding-top:8px}
.st-rev{background:#fff;border:1px solid var(--border);border-radius:10px;padding:12px;margin-bottom:10px}
.st-stars{color:#F0A202;font-weight:800;font-size:12px}
.st-stars .st-star-fill{fill:#F0A202}
.st-stars .st-star-empty{fill:#E5E7EB}
@media (max-width:860px){ .st-grid{grid-template-columns:repeat(2,1fr)} .st-actions{margin-left:0} }
@media (max-width:560px){ .st-grid{grid-template-columns:1fr} }
</style>

<a href="{{ url('/') }}" style="color:var(--text2);text-decoration:none;font-weight:600;font-size:12px">← Back to store</a>

<div class="st-head" style="margin-top:12px">
  @if(!empty($storeLogo))
    <img src="{{ $storeLogo }}" alt="{{ $name }}" class="st-avatar" style="object-fit:cover;padding:0">
  @else
    <div class="st-avatar">{{ $initial }}</div>
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
        <a class="st-link" href="{{ url('/product/'.$p->id) }}">
          <div class="st-card" style="flex:1">
            @if($pImg)
              <div class="st-sw" style="padding:0;overflow:hidden;background:#f3f4f6;aspect-ratio:4/3;height:auto"><img src="{{ $pImg }}" alt="{{ $p->name }}" style="width:100%;height:100%;object-fit:cover;display:block" loading="lazy"></div>
            @else
              <div class="st-sw" style="background:{{ $cc }};aspect-ratio:4/3;height:auto"><div class="mono">{{ $mono }}</div></div>
            @endif
            <div class="st-body">
              <div class="st-name">{{ $p->name }}</div>
              <div class="st-price">₱{{ number_format($p->price,2) }}</div>
            </div>
          </div>
        </a>
      @endforeach
    </div>
  @endif
@endif
@endsection
