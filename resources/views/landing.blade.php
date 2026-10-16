<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="INVOIZ - Life's short. Shop fast. Discover products, local sellers, deals, and delivery through one connected online marketplace.">
<title>INVOIZ - Life's short. Shop fast.</title>
<link rel="icon" href="{{ asset('images/logo.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,560;9..144,700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script>document.documentElement.classList.add('js');</script>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config={theme:{extend:{colors:{teal:{DEFAULT:'#16697A',dark:'#0E4A57',pale:'#EAF4F3'},amber:{DEFAULT:'#F0A202',dark:'#C77F02',deep:'#B45309'},ivory:'#FBF9F6',ink:'#1B1B1E',muted:'#6E6E73'},fontFamily:{sans:['Inter','system-ui','-apple-system','Segoe UI','sans-serif'],display:['Fraunces','Georgia','serif']}}}};
</script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<style>
:root{--accent-amber:#F0A202}
[x-cloak]{display:none!important}
html{scroll-behavior:smooth}
body{overflow-x:hidden}
.font-display{font-family:'Fraunces',Georgia,'Times New Roman',serif}
.tnum{font-variant-numeric:tabular-nums}
.hairline{border:1px solid rgba(15,76,92,.12)}
.eyebrow{display:flex;align-items:center;gap:.5rem;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.18em;color:#0E4A57}
.eyebrow::before{content:"";width:.45rem;height:.45rem;background:#0E4A57;flex-shrink:0}
a:focus-visible,button:focus-visible,input:focus-visible{outline:2px solid #0E4A57;outline-offset:2px}
.lnk{background-image:linear-gradient(currentColor,currentColor);background-size:0% 1px;background-repeat:no-repeat;background-position:left 100%;transition:background-size .25s cubic-bezier(.22,1,.36,1)}
.lnk:hover{background-size:100% 1px}
.press{transition:transform .2s cubic-bezier(.22,1,.36,1)}
.press:active{transform:scale(.98)}
@media(hover:hover) and (pointer:fine){
  .shine{position:relative;overflow:hidden}
  .shine::after{content:"";position:absolute;top:0;bottom:0;width:40%;left:-60%;background:linear-gradient(100deg,transparent,rgba(255,255,255,.45),transparent);transform:skewX(-18deg);transition:left .6s cubic-bezier(.22,1,.36,1)}
  .shine:hover::after{left:130%}
}
#navbar{transition:min-height .3s cubic-bezier(.22,1,.36,1),box-shadow .3s ease}
#navbar.scrolled{min-height:56px!important;box-shadow:0 10px 30px rgba(15,76,92,.10)}
.js .rv{opacity:0;transform:translateY(24px);transition:opacity .7s cubic-bezier(.22,1,.36,1),transform .7s cubic-bezier(.22,1,.36,1)}
.js .rv.in{opacity:1;transform:none}
.drawline{transition:transform 1.1s cubic-bezier(.22,1,.36,1) .15s}
.draw-h{transform:scaleX(0);transform-origin:left}
.drawn .draw-h{transform:scaleX(1)}
.draw-v{transform:scaleY(0);transform-origin:top}
.drawn .draw-v{transform:scaleY(1)}
@keyframes fillbar{from{width:0}to{width:100%}}
.fillbar{animation:fillbar 6s linear forwards}
.fillbar.paused{animation-play-state:paused}
@keyframes drift{0%,100%{transform:translate(0,0)}50%{transform:translate(34px,-26px)}}
@keyframes drift2{0%,100%{transform:translate(0,0)}50%{transform:translate(-28px,22px)}}
.drift{animation:drift 26s ease-in-out infinite}
.drift2{animation:drift2 30s ease-in-out infinite}
.count-card{transition:transform .25s cubic-bezier(.22,1,.36,1),box-shadow .25s cubic-bezier(.22,1,.36,1),border-color .25s}
.count-card:hover{transform:translateY(-4px);box-shadow:0 16px 40px rgba(15,76,92,.10);border-color:rgba(15,76,92,.22)}
.count-card:hover .zoom{transform:scale(1.05)}
.count-card:hover .nudgeright{transform:translateX(4px)}
.nudgeright{transition:transform .25s cubic-bezier(.22,1,.36,1)}
.zoom{transition:transform .5s cubic-bezier(.22,1,.36,1)}
.no-scrollbar{scrollbar-width:none}
.no-scrollbar::-webkit-scrollbar{display:none}
@media (prefers-reduced-motion: reduce) {
  html{scroll-behavior:auto}
  *, *::before, *::after { animation: none !important; transition: none !important; }
  .js .rv{opacity:1;transform:none}
  .drift,.drift2{animation:none}
}
</style>
</head>
<body class="font-sans bg-ivory text-ink antialiased text-[15px] md:text-base leading-relaxed">
<header x-data="{open:false}" class="sticky top-0 z-50">
<div id="navbar" style="min-height:72px;background:rgba(251,249,246,.85);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);border-bottom:1px solid rgba(15,76,92,.12);padding-top:12px;padding-bottom:12px">
<div class="mx-auto px-4 sm:px-6 lg:px-8" style="max-width:1200px">
<div class="flex items-center gap-3" style="min-height:inherit">
<a href="{{ url('/') }}" class="flex shrink-0 items-center gap-2.5">
<img src="{{ asset('images/logo.png') }}" alt="INVOIZ logo" class="h-10 w-10 rounded-xl object-cover">
<span><span class="block text-xl font-extrabold tracking-tight text-ink leading-none">INVOIZ</span><span class="mt-1 block text-[9px] font-bold uppercase text-muted" style="letter-spacing:.22em">Life's short. Shop fast.</span></span>
</a>
<form action="{{ url('/shop') }}" method="GET" role="search" class="hidden min-w-0 flex-1 md:flex" style="max-width:420px">
<div class="flex min-h-[42px] w-full items-center rounded-full bg-white px-4" style="border:1px solid rgba(15,76,92,.12)">
<span class="flex text-teal"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg></span>
<input name="search" type="search" placeholder="Search products, stores..." class="min-w-0 flex-1 bg-transparent px-2 py-2 text-sm text-ink outline-none placeholder:text-muted">
</div></form>
<nav class="ml-auto hidden shrink-0 items-center gap-6 text-sm font-semibold lg:flex" aria-label="Primary">
<a href="{{ url('/shop') }}" class="lnk text-ink">Shop</a>
<a href="#stores" class="lnk text-ink">Stores</a>
<a href="#how" class="lnk text-ink">How it works</a>
<a href="{{ url('/help') }}" class="lnk text-ink">Help</a>
@auth
  @if(auth()->user()->isAdmin())
    <a href="{{ url('/admin/dashboard') }}" class="lnk text-ink">Admin Dashboard</a>
  @elseif(auth()->user()->role==='seller')
    @php $dualSeller = \App\Models\Seller::where('user_id', auth()->id())->first(); @endphp
    @if($dualSeller && $dualSeller->isApproved())
      <a href="{{ url('/choose') }}" class="lnk text-ink">Switch account</a>
    @endif
    <a href="{{ url('/seller/dashboard') }}" class="lnk text-ink">Seller Dashboard</a>
  @else
    @php $dualSeller = \App\Models\Seller::where('user_id', auth()->id())->first(); @endphp
    @if($dualSeller && $dualSeller->isApproved())
      <a href="{{ url('/choose') }}" class="lnk text-ink">Switch account</a>
    @endif
    <a href="{{ url('/shop') }}" class="lnk text-ink">My Shop</a>
  @endif
  <form method="POST" action="{{ url('/logout') }}">@csrf<button class="lnk font-semibold">Logout</button></form>
@else
  <a href="{{ url('/cart') }}" class="lnk text-ink">Cart</a>
  <a href="{{ url('/login') }}" class="rounded-full px-4 py-2 font-bold text-teal-dark" style="border:1px solid rgba(15,76,92,.25)">Login</a>
  <a href="{{ url('/register') }}" class="press shine rounded-full bg-teal-dark px-5 py-2 font-bold text-white">Sign Up</a>
@endauth
</nav>
<button @click="open=!open" class="ml-auto lg:hidden inline-flex min-h-[44px] min-w-[44px] items-center justify-center" aria-label="Toggle menu"><svg x-show="!open" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg><svg x-show="open" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
</div>
<div x-show="open" x-cloak class="lg:hidden pb-4 grid gap-1 text-sm font-semibold">
<form action="{{ url('/shop') }}" method="GET" role="search" class="pb-2">
<div class="flex min-h-[44px] items-center rounded-full bg-white px-4" style="border:1px solid rgba(15,76,92,.12)">
<svg class="h-4 w-4 shrink-0 text-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
<input name="search" type="search" placeholder="Search products, stores..." class="min-w-0 flex-1 bg-transparent px-2 py-2 text-sm text-ink outline-none placeholder:text-muted">
</div></form>
<a href="{{ url('/shop') }}" @click="open=false" class="py-2">Shop</a><a href="#stores" @click="open=false" class="py-2">Stores</a><a href="#how" @click="open=false" class="py-2">How it works</a><a href="{{ url('/help') }}" @click="open=false" class="py-2">Help</a>
<div class="grid grid-cols-2 gap-2 mt-2">
@auth<form method="POST" action="{{ url('/logout') }}">@csrf<button class="w-full rounded-full bg-white py-3 font-bold" style="border:1px solid rgba(15,76,92,.12)">Logout</button></form>
@else<a href="{{ url('/login') }}" class="rounded-full bg-white py-3 text-center font-bold" style="border:1px solid rgba(15,76,92,.25)">Login</a><a href="{{ url('/register') }}" class="rounded-full bg-teal-dark py-3 text-center font-bold text-white">Sign Up</a>@endauth
</div></div>
</div></div></header>
<main>
@php $slides = collect($deals ?? [])->take(4)->values(); @endphp
<section id="market" aria-label="Featured deals">
<div class="mx-auto px-4 sm:px-6 lg:px-8 pt-6 md:pt-10" style="max-width:1200px">
<div x-data="heroSlider({{ $slides->count() }})" x-init="init()" @mouseenter="pause()" @mouseleave="play()" @focusin="pause()" @focusout="play()" role="region" aria-roledescription="carousel" aria-label="Featured deals" tabindex="0" @keydown.arrow-right.prevent="next()" @keydown.arrow-left.prevent="prev()" @touchstart.passive="tx=$event.touches[0].clientX" @touchend="swipe($event)" class="relative">
<div class="grid">
@forelse($slides as $i => $d)
<div x-show="ready && active==={{ $i }}" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-x-8" x-transition:enter-end="opacity-100 translate-x-0" x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="relative overflow-hidden p-5 md:p-8 [grid-area:1/1]" style="border-radius:24px;border:1px solid rgba(15,76,92,.12);background:linear-gradient(100deg,#FDFCF8 0%,#F4F0E7 52%,#E2EEEB 100%)" role="group" aria-roledescription="slide" aria-label="{{ $i+1 }} of {{ max($slides->count(),1) }}">
<div class="relative z-10 grid items-center gap-6 lg:grid-cols-2" style="min-height:340px">
<div>
<p class="eyebrow rv">Today's deal</p>
<h1 class="rv font-display mt-3 leading-[1.05] tracking-[-0.02em] line-clamp-2" style="font-weight:560;font-size:clamp(2.25rem,5vw,4rem);transition-delay:80ms">{{ $d['name'] }}</h1>
<p class="rv mt-3 text-base md:text-lg" style="transition-delay:160ms"><span class="font-bold tnum">₱{{ number_format($d['price']) }}</span><span class="text-muted"> · {{ $d['store'] ?: 'Local seller' }}</span>@if(!empty($d['pct']))<span class="ml-2 font-bold text-teal-dark tnum">−{{ $d['pct'] }}% today</span>@endif</p>
<div class="rv mt-5 flex flex-wrap items-center gap-4" style="transition-delay:240ms">
<a href="{{ url('/shop') }}" class="press shine rounded-full bg-teal-dark px-7 text-sm font-bold text-white" style="height:48px;line-height:48px">Start shopping</a>
<form method="POST" action="{{ url('/wishlist/'.$d['id'].'/toggle') }}">@csrf<button type="submit" class="press rounded-full bg-white px-5 text-sm font-bold text-teal-dark" style="height:48px;border:1px solid rgba(15,76,92,.18)">{{ in_array((int)$d['id'], $favoriteIds ?? [], true) ? '♥ Saved' : '♡ Save product' }}</button></form>
</div>
</div>
<div class="relative">
@if(!empty($d['image']))<img src="{{ $d['image'] }}" alt="{{ $d['name'] }}" @if($i > 0) loading="lazy"@endif x-transition:enter="transition ease-out duration-[900ms]" x-transition:enter-start="opacity-0 scale-[1.04] translate-x-6" x-transition:enter-end="opacity-100 scale-100 translate-x-0" class="relative h-[280px] w-full object-contain md:h-[380px]" style="-webkit-mask-image:linear-gradient(to right,transparent 0,#000 20%),linear-gradient(to bottom,#000 78%,transparent 99%);-webkit-mask-composite:source-in;mask-image:linear-gradient(to right,transparent 0,#000 20%),linear-gradient(to bottom,#000 78%,transparent 99%);mask-composite:intersect">@else<span class="flex h-[280px] w-full items-center justify-center bg-teal-pale text-5xl font-extrabold text-teal md:h-[380px]">{{ strtoupper(mb_substr($d['name'],0,1)) }}</span>@endif
<div class="pointer-events-none absolute inset-y-0 left-0 hidden w-1/3 md:block" style="background:linear-gradient(to right,#F4F0E7 0%,rgba(244,240,231,0) 100%)" aria-hidden="true"></div>
<div class="pointer-events-none absolute inset-x-0 top-0 h-20 md:hidden" style="background:linear-gradient(to bottom,#F5F1E8 0%,rgba(245,241,232,0) 100%)" aria-hidden="true"></div>
<div class="pointer-events-none absolute inset-y-0 left-0 hidden w-2/5 md:block" style="backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);-webkit-mask-image:linear-gradient(to right,#000,transparent);mask-image:linear-gradient(to right,#000,transparent)" aria-hidden="true"></div>
</div>
</div>
</div>
@empty
<div class="grid items-center gap-8 [grid-area:1/1] lg:grid-cols-[1.05fr_.95fr]">
<div>
<p class="eyebrow rv">Invoiz marketplace</p>
<h1 class="rv font-display mt-3 leading-[1.05] tracking-[-0.02em]" style="font-weight:560;font-size:clamp(2.25rem,5vw,4rem);transition-delay:80ms">Life's short. Shop fast.</h1>
<p class="rv mt-3 max-w-md text-base md:text-lg" style="transition-delay:160ms">Everyday goods from local sellers, delivered cash-on-delivery.</p>
<div class="rv mt-5 flex flex-wrap items-center gap-4" style="transition-delay:240ms">
<a href="{{ url('/shop') }}" class="press shine rounded-full bg-teal-dark px-7 text-sm font-bold text-white" style="height:48px;line-height:48px">Start shopping</a>
</div>
</div>
<div class="rv relative" style="transition-delay:120ms">
<div class="relative overflow-hidden p-6 md:p-10" style="border-radius:24px;border:1px solid rgba(15,76,92,.12);background:linear-gradient(100deg,#FDFCF8 0%,#F4F0E7 52%,#E2EEEB 100%)">
<span class="relative mx-auto flex aspect-[3/4] w-full items-center justify-center font-display font-bold text-teal" style="max-width:380px;font-size:clamp(3rem,6vw,5rem)">INVOIZ</span>
</div>
</div>
</div>
@endforelse
</div>
@if($slides->count() > 1)
<div class="mt-6 flex items-center gap-4">
<div class="flex items-center gap-2" role="tablist" aria-label="Slides">
@foreach($slides as $i => $d)
<button type="button" role="tab" :aria-selected="active==={{ $i }}" aria-label="Go to slide {{ $i+1 }}" @click="go({{ $i }})" class="h-1.5 overflow-hidden rounded-full bg-teal/15" style="width:2rem">
<span class="block h-full bg-teal" x-bind:key="'p'+active" :class="active==={{ $i }} && !paused && !reducedMotion ? 'fillbar' : ''" :style="active==={{ $i }} ? 'width:100%' : 'width:0'"></span>
</button>
@endforeach
</div>
<p class="text-[13px] text-muted tnum"><span x-text="String(active+1).padStart(2,'0')">01</span> / {{ str_pad(max($slides->count(),1),2,'0',STR_PAD_LEFT) }}</p>
<div class="ml-auto hidden gap-2 md:flex">
<button type="button" @click="prev()" aria-label="Previous slide" class="flex h-11 w-11 items-center justify-center rounded-full bg-white text-teal-dark transition hover:border-teal" style="border:1px solid rgba(15,76,92,.12)"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg></button>
<button type="button" @click="next()" aria-label="Next slide" class="flex h-11 w-11 items-center justify-center rounded-full bg-white text-teal-dark transition hover:border-teal" style="border:1px solid rgba(15,76,92,.12)"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></button>
</div>
</div>
@endif
</div>
@if(session('success'))<div class="mt-6 max-w-xl rounded-2xl bg-white p-3 text-sm font-semibold text-teal-dark" style="border:1px solid rgba(15,76,92,.12)">{{ session('success') }}</div>@endif
</div>
</section>
<section aria-label="Why shop with Invoiz">
<div class="mx-auto px-4 sm:px-6 lg:px-8" style="max-width:1200px">
<div class="rv mt-12 md:mt-16 border-y py-8" style="border-color:rgba(15,76,92,.12)">
<ul class="grid gap-x-6 gap-y-5 sm:grid-cols-3">
<li><p class="flex items-center gap-2.5"><i data-lucide="banknote" class="h-5 w-5 shrink-0 text-teal" style="stroke-width:1.5"></i><span class="text-sm font-bold">Cash on delivery</span></p><p class="mt-1 pl-[30px] text-[13px] text-muted">Pay at your door, no prepayment.</p></li>
<li><p class="flex items-center gap-2.5"><i data-lucide="truck" class="h-5 w-5 shrink-0 text-teal" style="stroke-width:1.5"></i><span class="text-sm font-bold">Rider-tracked delivery</span></p><p class="mt-1 pl-[30px] text-[13px] text-muted">Follow your parcel to arrival.</p></li>
<li><p class="flex items-center gap-2.5"><i data-lucide="rotate-ccw" class="h-5 w-5 shrink-0 text-teal" style="stroke-width:1.5"></i><span class="text-sm font-bold">Easy returns</span></p><p class="mt-1 pl-[30px] text-[13px] text-muted">Report a problem, we make it right.</p></li>
</ul>
</div>
<div class="rv mt-10 flex flex-wrap items-baseline justify-center gap-x-12 gap-y-4 text-center">
<div><p class="font-display tnum" style="font-weight:560;font-size:clamp(1.75rem,3vw,2.5rem)" data-countup="{{ (int)($stats['sellers'] ?? 0) }}">{{ number_format($stats['sellers'] ?? 0) }}</p><p class="mt-1 text-[11px] font-bold uppercase text-muted" style="letter-spacing:.18em">Local sellers</p></div>
<div><p class="font-display tnum" style="font-weight:560;font-size:clamp(1.75rem,3vw,2.5rem)" data-countup="{{ (int)($stats['products'] ?? 0) }}">{{ number_format($stats['products'] ?? 0) }}</p><p class="mt-1 text-[11px] font-bold uppercase text-muted" style="letter-spacing:.18em">Products live</p></div>
<div><p class="font-display tnum" style="font-weight:560;font-size:clamp(1.75rem,3vw,2.5rem)" data-countup="{{ (int)($stats['orders'] ?? 0) }}">{{ number_format($stats['orders'] ?? 0) }}</p><p class="mt-1 text-[11px] font-bold uppercase text-muted" style="letter-spacing:.18em">Orders placed</p></div>
</div>
</div>
</section>
<section id="deals">
<div class="mx-auto px-4 sm:px-6 lg:px-8 py-14 md:py-24" style="max-width:1200px">
<div class="rv flex flex-wrap items-center gap-x-5 gap-y-3">
<div>
<p class="eyebrow">Limited time</p>
<h2 class="font-display mt-2 tracking-[-0.02em]" style="font-weight:560;font-size:clamp(1.75rem,3vw,2.25rem);line-height:1.1">Today's deals</h2>
</div>
<p class="rounded-full bg-white px-3.5 py-1.5 text-[13px] font-bold tnum" style="border:1px solid rgba(15,76,92,.12)">ENDS IN <span id="deal-countdown" class="text-ink">--:--:--</span></p>
<a href="{{ url('/sale') }}" class="group ml-auto inline-flex items-center gap-1.5 text-sm font-semibold text-teal-dark">View all <span class="nudgeright inline-block transition-transform duration-200 group-hover:translate-x-1">→</span></a>
</div>
<form method="GET" action="{{ url('/') }}" style="display:flex;justify-content:flex-end;align-items:center;gap:8px;margin-top:16px">
<label for="landing-sort" class="text-xs font-semibold text-muted">Sort featured products</label>
<select id="landing-sort" name="sort" style="padding:8px 12px;border:1px solid rgba(15,76,92,.18);border-radius:999px;background:#fff;font-size:12px">
<option value="recommended" @selected(($sort ?? 'recommended') === 'recommended')>Recommended</option>
<option value="newest" @selected(($sort ?? '') === 'newest')>Newest</option>
<option value="price_asc" @selected(($sort ?? '') === 'price_asc')>Price: Low to High</option>
<option value="price_desc" @selected(($sort ?? '') === 'price_desc')>Price: High to Low</option>
<option value="rating" @selected(($sort ?? '') === 'rating')>Top Rated</option>
<option value="most_reviewed" @selected(($sort ?? '') === 'most_reviewed')>Most Reviewed</option>
</select>
<button type="submit" class="rounded-full px-4 py-2 text-xs font-bold text-white" style="background:#0E4A57">Apply</button>
</form>
<div class="mt-8 flex gap-4 overflow-x-auto pb-2 snap-x snap-mandatory no-scrollbar md:grid md:grid-cols-3 md:overflow-visible xl:grid-cols-6">
@forelse(($deals ?? []) as $d)
<div class="relative min-w-[68%] snap-start sm:min-w-[42%] md:min-w-0 rv">
<a href="{{ url('/product/'.$d['id'].'?'.http_build_query(array_filter(['source_landing'=>1,'sort'=>$sort ?? null]))) }}" class="count-card block h-full overflow-hidden rounded-[14px] bg-white" style="border:1px solid rgba(15,76,92,.12)">
<div class="relative overflow-hidden">
@if(!empty($d['image']))<img src="{{ $d['image'] }}" alt="{{ $d['name'] }}" loading="lazy" class="zoom aspect-[4/5] w-full object-cover">@else<span class="flex aspect-[4/5] w-full flex-col items-center justify-center gap-1 px-3 text-center" style="background:linear-gradient(180deg,#FDFCF8,#EAF4F3)"><span class="font-display font-bold text-teal-dark" style="font-size:1.25rem">INVOIZ</span><span class="text-[11px] text-muted">Product image coming soon</span></span>@endif
@if(!empty($d['pct']))<span class="absolute left-2.5 top-2.5 rounded-full bg-amber px-2 py-0.5 text-[13px] font-bold text-ink tnum">−{{ $d['pct'] }}%</span>@endif
</div>
<div class="p-3.5">
<h3 class="font-display font-medium leading-snug line-clamp-2 min-h-[2.75rem" style="font-size:1.02rem">{{ $d['name'] }}</h3>
@if(!empty($d['rating']))<p class="mt-1 text-xs font-semibold text-amber-dark">★ {{ number_format((float)$d['rating'],1) }} <span class="font-normal text-muted">product rating</span></p>@endif
<div class="mt-1.5 flex items-baseline gap-2">
<span class="text-base font-bold tnum">₱{{ number_format($d['price']) }}</span>
@if(!empty($d['compare_at']))<span class="text-xs text-muted line-through tnum">₱{{ number_format($d['compare_at']) }}</span>@endif
</div>
</div></a>
<form method="POST" action="{{ url('/wishlist/'.$d['id'].'/toggle') }}" class="absolute right-2 top-2 z-10">@csrf<button type="submit" aria-label="{{ in_array((int)$d['id'], $favoriteIds ?? [], true) ? 'Remove from wishlist' : 'Add to wishlist' }}" class="flex h-9 w-9 items-center justify-center rounded-full bg-white text-lg text-red-600 shadow">{{ in_array((int)$d['id'], $favoriteIds ?? [], true) ? '♥' : '♡' }}</button></form>
</div>
@empty
<p class="col-span-full rounded-[14px] bg-white p-6 text-base" style="border:1px solid rgba(15,76,92,.12)">No deals right now — <a href="{{ url('/shop') }}" class="lnk font-semibold text-teal-dark">browse the shop</a>.</p>
@endforelse
</div>
<script>
(function(){
  function tick(){
    var el = document.getElementById('deal-countdown');
    if(!el) return;
    var now = new Date(), end = new Date(now); end.setHours(23,59,59,999);
    var s = Math.max(0, Math.floor((end - now) / 1000));
    var h = String(Math.floor(s/3600)).padStart(2,'0'), m = String(Math.floor(s%3600/60)).padStart(2,'0'), ss = String(s%60).padStart(2,'0');
    el.textContent = h + ':' + m + ':' + ss;
  }
  tick(); setInterval(tick, 1000);
})();
</script>
</div>
</section>
<section id="categories">
<div class="mx-auto px-4 sm:px-6 lg:px-8 py-14 md:py-24" style="max-width:1200px">
<div class="rv">
<p class="eyebrow">Browse</p>
<h2 class="font-display mt-2 tracking-[-0.02em]" style="font-weight:560;font-size:clamp(1.75rem,3vw,2.25rem);line-height:1.1">Shop by category</h2>
</div>
<div class="mt-8 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-4">
@forelse(($categories ?? collect()) as $c)
<a href="{{ url('/shop?category='.$c->id) }}" class="count-card group block overflow-hidden rounded-[14px] bg-white rv" style="border:1px solid rgba(15,76,92,.12)">
@if(!empty(($c->cover_images ?? [])[0]))<img src="{{ $c->cover_images[0] }}" alt="" loading="lazy" class="zoom h-36 w-full object-cover md:h-44" style="filter:saturate(.88)">@else<span class="flex h-36 w-full items-center justify-center text-3xl font-extrabold text-teal md:h-44" style="background:linear-gradient(180deg,#FDFCF8,#EAF4F3)">{{ strtoupper(mb_substr($c->name,0,1)) }}</span>@endif
<div class="flex items-center justify-between gap-2 px-3.5 py-3" style="border-top:1px solid rgba(15,76,92,.12)">
<span class="min-w-0"><span class="block truncate font-display font-medium" style="font-size:1.02rem">{{ $c->name }}</span><span class="mt-0.5 block text-[13px] text-muted tnum">{{ $c->products_count }} {{ $c->products_count == 1 ? 'item' : 'items' }}</span></span>
<span class="nudgeright shrink-0 text-teal transition-transform duration-200 group-hover:translate-x-1">→</span>
</div></a>
@empty
<p class="col-span-full rounded-[14px] bg-white p-6 text-base text-muted rv" style="border:1px solid rgba(15,76,92,.12)">Categories appear here once sellers list products.</p>
@endforelse
</div>
</div>
</section>
<section id="stores">
<div class="mx-auto px-4 sm:px-6 lg:px-8 py-14 md:py-24" style="max-width:1200px">
<div class="rv">
<p class="eyebrow">Local sellers</p>
<h2 class="font-display mt-2 tracking-[-0.02em]" style="font-weight:560;font-size:clamp(1.75rem,3vw,2.25rem);line-height:1.1">Stores</h2>
</div>
<div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
@forelse(($stores ?? collect()) as $s)
<article class="count-card rounded-[14px] bg-white p-5 rv" style="border:1px solid rgba(15,76,92,.12)">
<div class="flex items-center gap-3">
@if(!empty($s['logo']))<img src="{{ $s['logo'] }}" alt="" loading="lazy" class="h-12 w-12 shrink-0 rounded-full object-cover" style="outline:2px solid rgba(22,105,122,.25);outline-offset:1px">@else<span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-teal-pale text-lg font-extrabold text-teal" style="outline:2px solid rgba(22,105,122,.25);outline-offset:1px">{{ strtoupper(mb_substr($s['name'],0,1)) }}</span>@endif
<div class="min-w-0"><h3 class="truncate font-display font-medium" style="font-size:1.05rem">{{ $s['name'] }}</h3><p class="truncate text-[11px] font-bold uppercase text-muted" style="letter-spacing:.14em">{{ $s['line'] }}</p></div>
</div>
<div class="mt-4 flex items-center justify-between pt-3" style="border-top:1px solid rgba(15,76,92,.12)">
<span class="text-[13px] text-muted tnum">{{ $s['products'] }} {{ $s['products'] == 1 ? 'product' : 'products' }}</span>
<a href="{{ url('/store/'.$s['user_id']) }}" class="group inline-flex items-center gap-1 text-sm font-semibold text-teal-dark">Visit store <span class="nudgeright inline-block transition-transform duration-200 group-hover:translate-x-1">→</span></a>
</div>
</article>
@empty
<p class="col-span-full rounded-[14px] bg-white p-6 text-base text-muted rv" style="border:1px solid rgba(15,76,92,.12)">Stores appear here once sellers add products.</p>
@endforelse
</div>
</div>
</section>
<section id="how">
<div class="mx-auto px-4 sm:px-6 lg:px-8 py-14 md:py-24" style="max-width:1200px">
<div class="rv">
<p class="eyebrow">Process</p>
<h2 class="font-display mt-2 tracking-[-0.02em]" style="font-weight:560;font-size:clamp(1.75rem,3vw,2.25rem);line-height:1.1">How it works</h2>
</div>
<ol id="howline" class="relative mt-8 grid gap-8 p-0 md:grid-cols-3 md:gap-6 xl:grid-cols-6" style="list-style:none">
<span class="drawline draw-h absolute top-5 hidden md:left-[9%] md:right-[9%] md:block md:border-t-2 md:border-dashed xl:hidden" style="border-color:rgba(22,105,122,.3)" aria-hidden="true"></span>
<span class="drawline draw-v absolute bottom-4 left-[19px] top-4 block border-l-2 border-dashed md:hidden" style="border-color:rgba(22,105,122,.3)" aria-hidden="true"></span>
<li class="rv relative" style="transition-delay:0ms"><p class="flex h-10 w-10 items-center justify-center rounded-full bg-teal text-base font-bold text-white tnum">1</p><p class="mt-3 flex items-center gap-2 text-sm font-semibold"><i data-lucide="search" class="h-4 w-4 text-teal" style="stroke-width:1.5"></i>Find it</p><p class="mt-1 text-[13px] leading-relaxed text-muted">Search products across local stores.</p></li>
<li class="rv relative" style="transition-delay:90ms"><p class="flex h-10 w-10 items-center justify-center rounded-full bg-teal text-base font-bold text-white tnum">2</p><p class="mt-3 flex items-center gap-2 text-sm font-semibold"><i data-lucide="shopping-bag" class="h-4 w-4 text-teal" style="stroke-width:1.5"></i>Check out</p><p class="mt-1 text-[13px] leading-relaxed text-muted">Pay cash when your order arrives.</p></li>
<li class="rv relative" style="transition-delay:180ms"><p class="flex h-10 w-10 items-center justify-center rounded-full bg-teal text-base font-bold text-white tnum">3</p><p class="mt-3 flex items-center gap-2 text-sm font-semibold"><i data-lucide="package" class="h-4 w-4 text-teal" style="stroke-width:1.5"></i>Seller packs</p><p class="mt-1 text-[13px] leading-relaxed text-muted">Your items are packed for shipping.</p></li>
<li class="rv relative" style="transition-delay:270ms"><p class="flex h-10 w-10 items-center justify-center rounded-full bg-teal text-base font-bold text-white tnum">4</p><p class="mt-3 flex items-center gap-2 text-sm font-semibold"><i data-lucide="bike" class="h-4 w-4 text-teal" style="stroke-width:1.5"></i>Rider picks up</p><p class="mt-1 text-[13px] leading-relaxed text-muted">A rider collects your parcel.</p></li>
<li class="rv relative" style="transition-delay:360ms"><p class="flex h-10 w-10 items-center justify-center rounded-full bg-teal text-base font-bold text-white tnum">5</p><p class="mt-3 flex items-center gap-2 text-sm font-semibold"><i data-lucide="truck" class="h-4 w-4 text-teal" style="stroke-width:1.5"></i>Hub sorts it</p><p class="mt-1 text-[13px] leading-relaxed text-muted">Parcels are sorted and assigned.</p></li>
<li class="rv relative" style="transition-delay:450ms"><p class="flex h-10 w-10 items-center justify-center rounded-full bg-teal text-base font-bold text-white tnum">6</p><p class="mt-3 flex items-center gap-2 text-sm font-semibold"><i data-lucide="home" class="h-4 w-4 text-teal" style="stroke-width:1.5"></i>Doorstep delivery</p><p class="mt-1 text-[13px] leading-relaxed text-muted">Pay cash and receive your order.</p></li>
</ol>
</div>
</section>
<section id="join">
<div class="mx-auto px-4 sm:px-6 lg:px-8 py-14 md:py-24" style="max-width:1200px">
<div class="grid gap-4 md:grid-cols-2">
<div class="rv relative overflow-hidden rounded-3xl p-8 md:p-10" style="background:linear-gradient(160deg,#FDFCF8,#F1ECE1);border:1px solid rgba(15,76,92,.12)">
<div class="orb-drift pointer-events-none absolute -right-16 -top-16 h-56 w-56 rounded-full" style="background:radial-gradient(circle,rgba(190,170,140,.28),transparent 65%);filter:blur(50px)" aria-hidden="true"></div>
<div class="relative">
<p class="flex h-11 w-11 items-center justify-center rounded-2xl bg-teal/10"><i data-lucide="store" class="h-5 w-5 text-teal"></i></p>
<p class="eyebrow mt-4">Sell</p>
<h2 class="font-display mt-2 tracking-[-0.02em]" style="font-weight:560;font-size:clamp(1.5rem,2.6vw,2rem);line-height:1.1">Sell on INVOIZ</h2>
<p class="mt-2 max-w-sm text-[15px] text-teal-dark">Register as a buyer and apply as a seller</p>
<a href="{{ url('/sell') }}" class="press group mt-6 inline-flex items-center gap-2 rounded-full bg-teal-dark px-6 text-sm font-bold text-white" style="height:44px">Become a seller <span class="nudgeright inline-block transition-transform duration-200 group-hover:translate-x-1">→</span></a>
</div>
</div>
<div class="rv relative overflow-hidden rounded-3xl p-8 md:p-10" style="background:linear-gradient(160deg,#F2F8F7,#E2EFEC);border:1px solid rgba(15,76,92,.12)">
<div class="orb-drift pointer-events-none absolute -right-16 -top-16 h-56 w-56 rounded-full" style="background:radial-gradient(circle,rgba(22,105,122,.22),transparent 65%);filter:blur(50px)" aria-hidden="true"></div>
<div class="relative">
<p class="flex h-11 w-11 items-center justify-center rounded-2xl bg-teal/10"><i data-lucide="bike" class="h-5 w-5 text-teal"></i></p>
<p class="eyebrow mt-4">Deliver</p>
<h2 class="font-display mt-2 tracking-[-0.02em]" style="font-weight:560;font-size:clamp(1.5rem,2.6vw,2rem);line-height:1.1">Ride with us</h2>
<p class="mt-2 max-w-sm text-[15px] text-teal-dark">Earn for every parcel you deliver.</p>
<a href="{{ url('/logistics') }}" class="press group mt-6 inline-flex items-center gap-2 rounded-full bg-teal px-6 text-sm font-bold text-white" style="height:44px;border:1px solid rgba(15,76,92,.12)">Open logistics <span class="nudgeright inline-block transition-transform duration-200 group-hover:translate-x-1">→</span></a>
</div>
</div>
</div>
</div>
</section>
</main>
<footer class="border-t bg-ivory" style="border-color:rgba(15,76,92,.12)">
<div class="mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-8" style="max-width:1200px">
<div class="flex items-center gap-2"><img src="{{ asset('images/logo.png') }}" alt="INVOIZ logo" class="h-8 w-8 rounded-lg object-cover"><span class="font-extrabold">INVOIZ</span></div>
<p class="mt-3 text-sm text-muted">Life's short. Shop fast.</p>
<div class="mt-8 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
<div><h3 class="text-[11px] font-bold uppercase text-teal-dark" style="letter-spacing:.18em">Shop</h3><ul class="mt-3 space-y-2 text-sm text-muted"><li><a href="{{ url('/shop') }}" class="lnk hover:text-ink">All products</a></li><li><a href="{{ url('/sale') }}" class="lnk hover:text-ink">Today's deals</a></li><li><a href="{{ url('/cart') }}" class="lnk hover:text-ink">Cart</a></li><li><a href="{{ url('/orders') }}" class="lnk hover:text-ink">Orders</a></li></ul></div>
<div><h3 class="text-[11px] font-bold uppercase text-teal-dark" style="letter-spacing:.18em">Sell</h3><ul class="mt-3 space-y-2 text-sm text-muted"><li><a href="{{ url('/sell') }}" class="lnk hover:text-ink">Become a seller</a></li><li><a href="{{ url('/seller/dashboard') }}" class="lnk hover:text-ink">Seller Center</a></li><li><a href="{{ url('/logistics') }}" class="lnk hover:text-ink">Logistics</a></li></ul></div>
<div><h3 class="text-[11px] font-bold uppercase text-teal-dark" style="letter-spacing:.18em">Help</h3><ul class="mt-3 space-y-2 text-sm text-muted"><li><a href="{{ url('/help') }}" class="lnk hover:text-ink">Help &amp; FAQ</a></li><li><a href="{{ url('/notifications') }}" class="lnk hover:text-ink">Notifications</a></li></ul></div>
<div><h3 class="text-[11px] font-bold uppercase text-teal-dark" style="letter-spacing:.18em">Account</h3><ul class="mt-3 space-y-2 text-sm text-muted"><li><a href="{{ url('/login') }}" class="lnk hover:text-ink">Login</a></li><li><a href="{{ url('/register') }}" class="lnk hover:text-ink">Sign Up</a></li><li><a href="{{ url('/profile') }}" class="lnk hover:text-ink">Profile</a></li></ul></div>
</div>
<p class="mt-8 text-xs text-muted">© 2026 INVOIZ. All rights reserved.</p>
</div></footer>
<button id="toTop" aria-label="Back to top" class="fixed bottom-5 right-5 z-50 hidden h-11 w-11 items-center justify-center rounded-full border-teal bg-white text-lg font-bold text-teal-dark" style="border:1px solid rgba(15,76,92,.25)">↑</button>
<script>
function heroSlider(n){
  return {
    active: 0, total: n, paused: false, tx: 0, timer: null,
    reducedMotion: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
    ready: false,
    init(){ this.ready = true; this.play(); },
    play(){ if (this.reducedMotion || this.total < 2 || this.paused === 'hold') return; this.stop(); var self = this; this.timer = setInterval(function(){ self.next(); }, 6000); },
    stop(){ if (this.timer) { clearInterval(this.timer); this.timer = null; } },
    pause(){ this.paused = true; this.stop(); },
    next(){ this.active = (this.active + 1) % Math.max(this.total, 1); this.restart(); },
    prev(){ this.active = (this.active - 1 + Math.max(this.total, 1)) % Math.max(this.total, 1); this.restart(); },
    go(i){ this.active = i; this.restart(); },
    restart(){ if (!this.paused && !this.reducedMotion && this.total > 1) { this.play(); } },
    swipe(e){ var dx = e.changedTouches[0].clientX - this.tx; if (dx < -40) this.next(); else if (dx > 40) this.prev(); }
  };
}
(function(){
  var top = document.getElementById('toTop');
  function onScroll(){ var show = window.scrollY > 600; top.classList.toggle('hidden', !show); top.classList.toggle('flex', show); }
  window.addEventListener('scroll', onScroll, {passive:true}); onScroll();
  top.addEventListener('click', function(){ window.scrollTo({top:0, behavior:'smooth'}); });
  var nav = document.getElementById('navbar');
  function onNav(){ if (!nav) return; nav.classList.toggle('scrolled', window.scrollY > 24); }
  window.addEventListener('scroll', onNav, {passive:true}); onNav();
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var els = document.querySelectorAll('.rv');
  if ('IntersectionObserver' in window && !reduce) {
    var io = new IntersectionObserver(function(es){ es.forEach(function(e){ if(e.isIntersecting){ e.target.classList.add('in'); io.unobserve(e.target); } }); }, {threshold:.2});
    els.forEach(function(s){ io.observe(s); });
  } else { els.forEach(function(s){ s.classList.add('in'); }); }
  document.querySelectorAll('[data-countup]').forEach(function(el){
    var end = parseInt(el.getAttribute('data-countup'), 10) || 0;
    function render(v){ el.textContent = v.toLocaleString('en-US'); }
    if (reduce || !('IntersectionObserver' in window)) { render(end); return; }
    var done = false;
    var cio = new IntersectionObserver(function(es){ es.forEach(function(e){
      if (e.isIntersecting && !done) { done = true; cio.disconnect();
        var t0 = null;
        function frame(t){ if (!t0) t0 = t; var p = Math.min(1, (t - t0) / 1200); render(Math.round(end * (1 - Math.pow(1 - p, 3)))); if (p < 1) requestAnimationFrame(frame); }
        requestAnimationFrame(frame);
      }
    }); }, {threshold:.4});
    cio.observe(el);
  });
  var how = document.getElementById('howline');
  if (how && 'IntersectionObserver' in window && !reduce) {
    var hio = new IntersectionObserver(function(es){ es.forEach(function(e){ if(e.isIntersecting){ how.classList.add('drawn'); hio.disconnect(); } }); }, {threshold:.25});
    hio.observe(how);
  } else if (how) { how.classList.add('drawn'); }
  var orbs = document.querySelectorAll('.orb-drift');
  if (orbs.length && !reduce) {
    var ticking = false;
    window.addEventListener('scroll', function(){
      if (ticking) return; ticking = true;
      requestAnimationFrame(function(){ var y = window.scrollY || 0; orbs.forEach(function(o, i){ o.style.transform = 'translateY(' + (y * (i % 2 ? 0.03 : -0.04)) + 'px)'; }); ticking = false; });
    }, {passive:true});
  }
})();
</script>
<script>lucide.createIcons();</script>
</body>
</html>
