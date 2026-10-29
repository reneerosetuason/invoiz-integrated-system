<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="INVOIZ - Life's short. Shop fast. Discover products, local sellers, deals, and delivery through one connected online marketplace.">
<title>INVOIZ - Life's short. Shop fast.</title>
<link rel="icon" href="{{ asset('assets/shared/images/logo.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,560;9..144,700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="{{ asset('assets/shared/js/enable-js.js') }}"></script>
<script src="https://cdn.tailwindcss.com"></script>
<script src="{{ asset('assets/buyer/js/landing-2.js') }}"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<link rel="stylesheet" href="{{ asset('assets/buyer/css/landing.css') }}">
</head>
<body class="font-sans bg-ivory text-ink antialiased text-[15px] md:text-base leading-relaxed">
@include('shared.navigation.navbar', ['navigationRole' => 'buyer', 'navigationVariant' => 'landing'])
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
<script src="{{ asset('assets/buyer/js/landing-3.js') }}"></script>
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
<div class="mb-8 max-w-2xl rv">
<h2 class="font-display tracking-[-0.02em] text-2xl leading-tight md:text-3xl">More ways to work with INVOIZ</h2>
<p class="mt-3 text-base text-teal-dark">Local shopping depends on sellers and delivery teams working together.</p>
</div>
<div class="platform-grid grid gap-5 md:grid-cols-2">
<article class="platform-card platform-card--seller rv">
<div class="platform-card__inner">
<span class="platform-card__icon" aria-hidden="true"><i data-lucide="store" class="h-5 w-5"></i></span>
<p class="platform-card__label">SELL</p>
<h3 class="platform-card__title font-display">Sell on INVOIZ</h3>
<p class="platform-card__description">Register as a buyer and apply as a seller</p>
<a href="{{ route('sell') }}" class="platform-card__action press group bg-teal-dark text-white">Become a seller <span class="nudgeright inline-block transition-transform duration-200 group-hover:translate-x-1" aria-hidden="true">→</span></a>
</div>
</article>
<article class="platform-card platform-card--logistics rv">
<div class="platform-card__inner">
<span class="platform-card__icon" aria-hidden="true"><i data-lucide="boxes" class="h-5 w-5"></i></span>
<p class="platform-card__label">DELIVER</p>
<h3 class="platform-card__title font-display">Ride with us</h3>
<p class="platform-card__description">Earn for every parcel you deliver.</p>
<a href="{{ route('logistics.login') }}" class="platform-card__action press group bg-teal-dark text-white">Open logistics <span class="nudgeright inline-block transition-transform duration-200 group-hover:translate-x-1" aria-hidden="true">→</span></a>
</div>
</article>
</div>
@php
    $riderApkUrl = config('services.rider_app.android_apk_url');
    $riderApkParts = is_string($riderApkUrl) ? parse_url($riderApkUrl) : false;
    $riderApkPath = storage_path('app/rider-downloads/invoiz-rider.apk');
    $riderStorageRoot = realpath(storage_path('app'));
    $riderDownloadDirectory = realpath(storage_path('app/rider-downloads'));
    $riderResolvedApkPath = realpath($riderApkPath);
    $riderDownloadRoute = route('rider-app.download');
    $riderApkAvailable = filter_var(config('services.rider_app.android_apk_verified'), FILTER_VALIDATE_BOOLEAN) === true
        && is_string($riderApkUrl)
        && $riderApkUrl === $riderDownloadRoute
        && is_array($riderApkParts)
        && strtolower($riderApkParts['scheme'] ?? '') === 'https'
        && !isset($riderApkParts['user'])
        && !isset($riderApkParts['pass'])
        && !isset($riderApkParts['query'])
        && !isset($riderApkParts['fragment'])
        && $riderStorageRoot !== false
        && $riderDownloadDirectory !== false
        && $riderResolvedApkPath !== false
        && is_file($riderResolvedApkPath)
        && is_readable($riderResolvedApkPath)
        && str_starts_with($riderDownloadDirectory, $riderStorageRoot . DIRECTORY_SEPARATOR)
        && realpath(dirname($riderResolvedApkPath)) === $riderDownloadDirectory;
@endphp
<section class="rider-download rv" aria-labelledby="rider-download-title">
<div class="rider-download__copy">
<span class="rider-download__icon" aria-hidden="true"><i data-lucide="bike" class="h-5 w-5"></i></span>
<p class="rider-download__label">ANDROID APP</p>
<h2 id="rider-download-title" class="font-display text-2xl leading-tight">Get the INVOIZ Rider App</h2>
<p class="rider-download__description">Manage your deliveries, view assigned parcels, update delivery statuses, and stay connected with your logistics team using the INVOIZ Rider App.</p>
<div class="rider-download__actions">
@if ($riderApkAvailable)
<a id="rider-app-download-link" class="rider-download__button" href="{{ $riderApkUrl }}" download="invoiz-rider.apk">Download for Android <span aria-hidden="true">→</span></a>
@else
<button class="rider-download__button" type="button" disabled aria-describedby="rider-app-download-status">Download for Android</button>
<p id="rider-app-download-status" class="rider-download__status" role="status">The Android installer is not yet available.</p>
@endif
</div>
<div class="rider-download__instructions">
<p class="font-semibold">Install on Android</p>
<ol class="mt-2 list-decimal space-y-1 pl-5">
<li>Download the APK file to your Android device.</li>
<li>Open the downloaded file.</li>
<li>If Android requests permission to install apps from that source, allow it only if you trust the file and its source.</li>
<li>Follow the on-screen instructions to complete installation.</li>
</ol>
<p class="mt-2">Android installation steps may vary by device and Android version.</p>
</div>
</div>
<div class="rider-download__qr-panel">
@if ($riderApkAvailable)
<div id="rider-app-download-qr" class="rider-download__qr" role="img" aria-label="QR code linking to the INVOIZ Rider App Android download"></div>
<p class="rider-download__qr-caption">Scan the QR code with your phone to access the Rider App download.</p>
@else
<div class="rider-download__qr-placeholder" role="status">QR code will be available after the APK hosting is verified.</div>
@endif
</div>
</section>
@if ($riderApkAvailable)
<script src="{{ asset('vendor/qrcodejs/qrcode.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var qrTarget = document.getElementById('rider-app-download-qr');
    var downloadLink = document.getElementById('rider-app-download-link');
    if (!qrTarget || !downloadLink || typeof QRCode === 'undefined') return;
    new QRCode(qrTarget, {
        text: downloadLink.href,
        width: 192,
        height: 192,
        colorDark: '#0E4A57',
        colorLight: '#FFFFFF',
        correctLevel: QRCode.CorrectLevel.M
    });
});
</script>
@endif
</div>
</section>
</main>
<footer class="border-t bg-ivory" style="border-color:rgba(15,76,92,.12)">
<div class="mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-8" style="max-width:1200px">
<div class="flex items-center gap-2"><img src="{{ asset('assets/shared/images/logo.png') }}" alt="INVOIZ logo" class="h-8 w-8 rounded-lg object-cover"><span class="font-extrabold">INVOIZ</span></div>
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
<script src="{{ asset('assets/buyer/js/landing-4.js') }}"></script>
<script src="{{ asset('assets/buyer/js/landing-5.js') }}"></script>
</body>
</html>
