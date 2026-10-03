@extends('layouts.website')
@section('content')

<style>
/* Landing — storefront look (scoped sf-*, Fraunces serif + Invoiz tokens) */
.sf-hero{position:relative;color:#fff;padding:56px 0 60px;overflow:hidden;background:var(--primary-dark,#0E4A57);border-radius:var(--radius-lg,12px);margin:20px 0 0}
.sf-hero::before{content:"";position:absolute;inset:0;background:radial-gradient(120% 140% at 82% -10%,rgba(240,162,2,.22),transparent 55%),linear-gradient(160deg,var(--primary,#16697A) 0%,var(--primary-dark,#0E4A57) 62%)}
.sf-hero::after{content:"";position:absolute;right:-6%;top:12%;width:380px;height:380px;border:1px solid rgba(255,255,255,.14);border-radius:50%}
.sf-hero-inner{position:relative;display:grid;grid-template-columns:1.15fr .85fr;gap:48px;align-items:center;padding:0 40px}
.sf-kicker{margin:0 0 14px;color:#F0A202;font-size:12.5px;font-weight:700}
.sf-hero h1{margin:0 0 16px;font-family:'Fraunces',Georgia,serif;font-weight:560;font-style:italic;font-size:42px;line-height:1.1;letter-spacing:-.01em;max-width:12ch}
.sf-hero h1 em{font-style:normal;color:#F0A202}
.sf-sub{margin:0 0 26px;color:#CFE1DC;font-size:14.5px;line-height:1.65;max-width:44ch}
.sf-actions{display:flex;gap:12px;flex-wrap:wrap}
.sf-btn{display:inline-flex;align-items:center;justify-content:center;padding:12px 22px;border-radius:999px;font-weight:700;font-size:13px;text-decoration:none;transition:all .18s}
.sf-btn-accent{background:#F0A202;color:#0E4A57}
.sf-btn-accent:hover{background:#C77F02;color:#fff}
.sf-btn-outline{background:transparent;border:1px solid rgba(255,255,255,.4);color:#fff}
.sf-btn-outline:hover{border-color:#fff}
.sf-stats{display:flex;gap:32px;margin-top:36px;padding-top:22px;border-top:1px solid rgba(255,255,255,.16)}
.sf-stats b{display:block;font-family:'Fraunces',Georgia,serif;font-size:23px;font-weight:560}
.sf-stats span{font-size:11px;color:#B9D5CC}
.sf-cartcard{position:relative;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.18);border-radius:20px;padding:24px;backdrop-filter:blur(6px)}
.sf-cartcard .tag{display:inline-block;font-size:10.5px;font-weight:700;color:#F0A202;background:rgba(240,162,2,.14);padding:5px 10px;border-radius:999px;margin-bottom:12px}
.sf-cartcard .row{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:9px 0;border-bottom:1px solid rgba(255,255,255,.12);font-size:13px}
.sf-cartcard .row:last-of-type{border-bottom:none}
.sf-cartcard .total{display:flex;justify-content:space-between;margin-top:10px;padding-top:12px;border-top:1px solid rgba(255,255,255,.25);font-family:'Fraunces',Georgia,serif;font-size:17px}
.sf-cartcard a.cta{display:block;text-align:center;margin-top:14px;padding:10px;border-radius:999px;background:#F0A202;color:#0E4A57;font-weight:800;font-size:12.5px;text-decoration:none}
.sf-cartcard a.cta:hover{background:#C77F02;color:#fff}
.sf-cartcard .ghost{color:#CFE1DC;font-size:13px;line-height:1.6}
.sf-section{padding:44px 0 0}
.sf-head{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:22px}
.sf-head h2{margin:0;font-family:'Fraunces',Georgia,serif;font-size:24px;font-weight:560;color:var(--text-primary,#1B1B1E)}
.sf-head a{font-size:12.5px;font-weight:700;color:var(--primary-dark,#0E4A57);text-decoration:none;border-bottom:1px solid currentColor;padding-bottom:1px}
.sf-pillrow{display:flex;gap:10px;overflow-x:auto;padding-bottom:4px}
.sf-pillrow::-webkit-scrollbar{display:none}
.sf-pill{padding:9px 16px;border-radius:999px;background:#fff;border:1px solid var(--border,#e5e5e5);font-weight:600;font-size:12.5px;color:var(--text2,#555);text-decoration:none;transition:all .18s;white-space:nowrap}
.sf-pill:hover{border-color:var(--primary,#16697A);color:var(--primary-dark,#0E4A57)}
.sf-pill.active{background:var(--primary-dark,#0E4A57);color:#fff;border-color:var(--primary-dark,#0E4A57)}
.sf-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-top:6px}
a.sf-plink{text-decoration:none;color:inherit;display:flex}
.sf-pcard{background:#fff;border:1px solid var(--border,#e5e5e5);border-radius:12px;overflow:hidden;display:flex;flex-direction:column;flex:1;transition:box-shadow .2s,transform .2s,border-color .2s}
a.sf-plink{text-decoration:none;color:inherit}
a.sf-plink:hover .sf-pcard{box-shadow:0 8px 24px rgba(0,0,0,.12);transform:translateY(-3px);border-color:transparent}
.sf-swatch{height:132px;display:flex;align-items:flex-end;padding:14px}
.sf-swatch .mono{width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,.92);display:grid;place-items:center;font-family:'Fraunces',Georgia,serif;font-weight:650;font-size:14px;color:#1B1B1E}
.sf-pcard .body{padding:16px;display:flex;flex-direction:column;flex:1}
.sf-cat{color:var(--text3,#999);font-size:10.5px;font-weight:600}
.sf-pcard h3{margin:6px 0 6px;font-size:14.5px;font-weight:600;color:#1B1B1E;line-height:1.35;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:39px}
.sf-desc{margin:0 0 8px;color:var(--text2,#555);font-size:12px;line-height:1.45;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:35px}
.sf-meta{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;row-gap:6px;column-gap:8px;margin-top:auto;padding-top:8px}
.sf-stock{white-space:nowrap}
.sf-price{color:var(--primary-dark,#0E4A57);font-weight:800;font-family:'Fraunces',Georgia,serif;font-size:16px}
.sf-stock{display:inline-flex;align-items:center;gap:5px;font-size:10.5px;color:var(--success,#10b981);font-weight:600}
.sf-stock i{width:5px;height:5px;border-radius:50%;background:currentColor;display:inline-block}
.sf-promo{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;padding:40px 0 8px}
.sf-promo .box{background:var(--bg-warm,#F7F6F2);border:1px solid var(--border-light,#f0f0f0);border-radius:12px;padding:24px;text-decoration:none;color:inherit;display:block;transition:box-shadow .2s,transform .2s}
.sf-promo .box:hover{box-shadow:0 8px 24px rgba(0,0,0,.10);transform:translateY(-2px)}
.sf-promo b{font-family:'Fraunces',Georgia,serif;font-size:16px;font-weight:560;color:#1B1B1E;display:block;margin-bottom:6px}
.sf-promo span{font-size:12.5px;color:var(--text2,#555);line-height:1.5}
.sf-empty{text-align:center;padding:60px 20px}
@media (max-width:860px){
  .sf-hero-inner{grid-template-columns:1fr;padding:0 24px}
  .sf-grid{grid-template-columns:repeat(2,1fr)}
  .sf-promo{grid-template-columns:1fr}
}
@media (max-width:560px){
  .sf-grid{grid-template-columns:1fr}
  .sf-hero h1{font-size:32px}
  .sf-stats{flex-wrap:wrap;row-gap:16px}
}
</style>

@php
$gradients = [
  'linear-gradient(135deg,#0F766E,#0a5049)',
  'linear-gradient(135deg,#1D4ED8,#123f9e)',
  'linear-gradient(135deg,#7C3AED,#5b21b6)',
  'linear-gradient(135deg,#DB2777,#9d1a5f)',
  'linear-gradient(135deg,#EA580C,#b8460a)',
];
$monoOf = function($name){
  $w = preg_split('/\s+/', trim($name));
  return strtoupper(substr($w[0] ?? '',0,1).substr($w[1] ?? '',0,1));
};
$qs = request('category') ? '?category='.request('category').(request('search') ? '&search='.urlencode(request('search')) : '') : (request('search') ? '?search='.urlencode(request('search')) : '');
$saleMode = $saleMode ?? false;
$pillBase = $saleMode ? url('/sale') : url('/');
$offOf = function($p){
  if(empty($p->compare_at_price) || $p->compare_at_price <= $p->price) return null;
  return (int) round((1 - (float)$p->price / (float)$p->compare_at_price) * 100);
};
@endphp

@if(empty($saleMode))
<section class="sf-hero">
  <div class="sf-hero-inner">
    <div>
      <p class="sf-kicker">Cash on delivery, nationwide</p>
      <h1>Everything you love, <em>delivered</em> to your door</h1>
      <p class="sf-sub">Shop verified sellers across fashion, groceries, gadgets and more — pay cash on delivery, track every order, all on one Invoiz account.</p>
    </div>
    <div class="sf-cartcard">
      <span class="tag">Current cart</span>
      @if($buyer && $cartLines->isNotEmpty())
        @foreach($cartLines as $line)
          <div class="row"><span>{{ Str::limit($line['name'],28) }} ×{{ $line['qty'] }}</span><b>₱{{ number_format($line['line'],2) }}</b></div>
        @endforeach
        <div class="total"><span>Total</span><span>₱{{ number_format($cartTotal,2) }}</span></div>
        <a class="cta" href="{{ url('/buy/all') }}">Checkout now</a>
      @elseif($buyer)
        <p class="ghost">Your cart is empty — fill it with things you love and check out in one tap.</p>
        <a class="cta" href="{{ url('/products') }}">Start shopping</a>
      @else
        <p class="ghost">Sign in to sync your cart, track orders and check out with cash on delivery.</p>
        <a class="cta" href="{{ url('/login') }}">Sign in to shop</a>
      @endif
    </div>
  </div>
</section>
@endif

<div class="sf-section" style="padding-bottom:0">
  <div class="sf-pillrow">
    <a class="sf-pill {{ request('category')?'':'active' }}" href="{{ $pillBase }}">All</a>
    @foreach($categories as $c)
      <a class="sf-pill {{ (string)request('category')===(string)$c->id ? 'active':'' }}" href="{{ $pillBase.'?category='.$c->id }}">{{ $c->name }}</a>
    @endforeach
  </div>
</div>

<div class="sf-section">
  <div class="sf-head">
    <h2>{{ !empty($saleMode) ? 'On sale now' : (request('category') || request('search') ? 'Results' : 'Featured products') }}</h2>
    <a href="{{ url('/products') }}">View all</a>
  </div>
  @if($products->isNotEmpty())
  <div class="sf-grid">
    @foreach($products as $i => $p)
      @php $g = $gradients[$i % count($gradients)]; @endphp
      <a class="sf-plink" href="{{ url('/product/'.$p->id.$qs) }}">
        <div class="sf-pcard">
          <div class="sf-swatch" style="background:{{ $g }}"><div class="mono">{{ $monoOf($p->name) }}</div></div>
          <div class="body">
            <div class="sf-cat">{{ $p->category->name ?? 'General' }}</div>
            <h3>{{ $p->name }}</h3>
            <p class="sf-desc">{{ $p->description ?: 'Quality pick from a verified Invoiz seller.' }}</p>
            <div class="sf-meta">
              <span>
                <span class="sf-price">₱{{ number_format($p->price,2) }}</span>
                @php $off = $offOf($p); @endphp
                @if($off)
                  <span style="text-decoration:line-through;color:var(--text3,#999);font-size:11px;margin-left:6px">₱{{ number_format($p->compare_at_price,2) }}</span>
                  <span style="display:inline-block;background:var(--danger,#ef4444);color:#fff;font-size:10px;font-weight:800;padding:2px 7px;border-radius:999px;margin-left:6px">−{{ $off }}%</span>
                @endif
              </span>
              @if($p->stock > 10)
                <span class="sf-stock"><i></i>In stock</span>
              @elseif($p->stock > 0)
                <span class="sf-stock" style="color:var(--accent-dark,#C77F02)"><i></i>Only {{ $p->stock }} left</span>
              @else
                <span class="sf-stock" style="color:var(--danger,#ef4444)"><i></i>Out of stock</span>
              @endif
            </div>
          </div>
        </div>
      </a>
    @endforeach
  </div>
  @else
    <div class="sf-empty">
      <div style="font-weight:700;font-size:16px">No products found</div>
      <div style="font-size:13px;color:var(--text3,#999);margin-top:6px">Try adjusting your search or filter</div>
    </div>
  @endif
</div>

<div class="sf-promo">
  <a class="box" href="{{ url('/buy/all') }}">
    <b>Reorder in one tap</b>
    <span>Your last cart is saved — check out everything again instantly.</span>
  </a>
  <a class="box" href="{{ url('/orders') }}">
    <b>Track every order</b>
    <span>Live status from pending to delivered, with COD receipt.</span>
  </a>
  <a class="box" href="{{ url('/messages') }}">
    <b>Talk to a seller</b>
    <span>Ask about stock and shipping before you check out.</span>
  </a>
</div>

@endsection
