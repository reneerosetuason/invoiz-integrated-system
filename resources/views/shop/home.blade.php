@extends('layouts.website')
@section('content')

<div class="hero">
  <div class="hero-left">
    <div style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);padding:5px 12px;border-radius:999px;font-size:11px;font-weight:600;display:inline-block;width:fit-content;color:#fff">✓ Trusted by 10,000+ buyers • COD nationwide</div>
    <div style="font-size:20px;font-weight:800;margin-top:10px;color:#fff;line-height:1.2">Invoiz — Curated for you</div>
    <div style="font-size:12px;margin-top:6px;opacity:.9;line-height:1.5;color:#fff">Premium essentials at honest prices. Vouchers & verified sellers.</div>
    <div style="margin-top:16px">
      <a href="{{ url('/products') }}" class="btn" style="background:#fff;color:var(--green);font-weight:700;box-shadow:0 2px 8px rgba(0,0,0,.1)">Shop collection</a>
    </div>
  </div>
  <div class="hero-right">
    <img src="{{ asset('storage/logos/logo.png') }}" alt="Invoiz" style="width:64px;height:64px;border-radius:14px">
    <div style="font-size:12px;color:var(--text2);margin-top:10px;font-weight:500">Desktop Store • Curated for you</div>
    <div style="margin-top:10px;background:var(--green);color:#fff;padding:8px 18px;border-radius:999px;font-size:11px;font-weight:700;text-align:center">Welcome Save 15 COD</div>
  </div>
</div>

<div class="cat-pills">
  <a href="{{ url('/') }}" class="cat-pill {{ request('category')?'':'active' }}">All</a>
  @foreach($categories as $c)
    <a href="{{ url('/?category='.$c->id) }}" class="cat-pill {{ (string)request('category')===(string)$c->id ? 'active':'' }}">{{ $c->name }}</a>
  @endforeach
</div>

<div class="grid" style="margin-top:16px">
  @foreach($products as $p)
    @php $hasRealImage = $p->image && file_exists(storage_path('app/public/'.$p->image)); $qs = request('category') ? '?category='.request('category').(request('search') ? '&search='.urlencode(request('search')) : '') : (request('search') ? '?search='.urlencode(request('search')) : ''); @endphp
    <a href="{{ url('/product/'.$p->id.$qs) }}" style="text-decoration:none;color:inherit">
      <div class="card">
        <div class="card-thumb">
          @if($hasRealImage)
            <img src="{{ asset('storage/'.$p->image) }}" alt="{{ $p->name }}">
          @else
            <div style="text-align:center;padding:12px">
              <div style="font-weight:700;font-size:13px;color:var(--green)">{{ $p->name }}</div>
            </div>
          @endif
        </div>
        <div class="card-body">
          <h3>{{ $p->name }}</h3>
          <div class="card-price">₱{{ number_format($p->price,2) }}</div>
          <div class="card-meta">
            @if($p->brand)
              <span>{{ $p->brand }}</span>
              <span>•</span>
            @endif
            <span>{{ $p->stock }} left</span>
            <span>•</span>
            <span class="cod">COD</span>
          </div>
        </div>
      </div>
    </a>
  @endforeach
</div>

@if($products->isEmpty())
  <div style="text-align:center;padding:60px 20px">
    <div style="font-weight:700;font-size:16px;color:var(--text)">No products found</div>
    <div style="font-size:13px;color:var(--text3);margin-top:6px">Try adjusting your search or filter</div>
  </div>
@endif

@endsection
