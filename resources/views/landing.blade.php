<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="INVOIZ - Life's short. Shop fast. Discover products, local sellers, deals, and delivery through one connected online marketplace.">
<title>INVOIZ - Life's short. Shop fast.</title>
<link rel="icon" href="{{ asset('images/logo.png') }}">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config={theme:{extend:{colors:{teal:{DEFAULT:'#16697A',dark:'#0E4A57',light:'#EAF4F3'},amber:{DEFAULT:'#F0A202',light:'#FFF8E1'},offwhite:'#F8FAF9',charcoal:'#1F2933'},fontFamily:{sans:['Segoe UI','system-ui','-apple-system','sans-serif']},boxShadow:{soft:'0 18px 45px rgba(14,74,87,0.10)',card:'0 10px 25px rgba(31,41,51,0.08)'}}}};
</script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>
[x-cloak]{display:none!important}
html{scroll-behavior:smooth}
body{overflow-x:hidden}
.landing-display{font-size:clamp(2.35rem,4.5vw + .65rem,5rem);line-height:.96;letter-spacing:-.02em}
.landing-section-title{font-size:clamp(1.5rem,1.15rem + 1vw,2.1rem);line-height:1.1;letter-spacing:-.01em}
.eyebrow{display:inline-flex;align-items:center;gap:.5rem;font-size:.72rem;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:#0E4A57}
/* Uniform image treatment: every card image fills its box, never raw size */
.img-frame{position:relative;overflow:hidden;background:#eef2f1}
.img-frame img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block;transition:transform .6s cubic-bezier(.2,.6,.2,1)}
.group:hover .img-frame img{transform:scale(1.06)}
.ratio-43{aspect-ratio:4/3}
.ratio-slide{height:16rem}
@media(min-width:640px){.ratio-slide{height:20rem}}
.product-image{aspect-ratio:4/3;object-fit:cover}
.hide-scrollbar{scrollbar-width:none}
.hide-scrollbar::-webkit-scrollbar{display:none}
.marketplace-band{background:
  radial-gradient(42rem 22rem at 8% -40px, rgba(240,162,2,.20), transparent 60%),
  radial-gradient(36rem 20rem at 95% 10%, rgba(22,105,122,.16), transparent 60%),
  linear-gradient(135deg,#F8FAF9 0%,#ffffff 52%,#EAF4F3 100%)}
.card-hover{transition:transform .25s ease, box-shadow .25s ease}
.card-hover:hover{transform:translateY(-4px)}
.reveal{opacity:0;transform:translateY(18px);transition:opacity .6s ease,transform .6s ease}
.reveal.visible{opacity:1;transform:none}
@media (prefers-reduced-motion: reduce) {
  html{scroll-behavior:auto}
  *, *::before, *::after { animation: none !important; transition: none !important; }
  .reveal{opacity:1;transform:none}
}
</style>
</head>
<body class="font-sans bg-offwhite text-charcoal antialiased">
<header x-data="{open:false}" class="sticky top-0 z-50 border-b border-gray-200 bg-white/95 backdrop-blur">
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
<div class="flex min-h-[72px] items-center gap-3">
<a href="{{ url('/') }}" class="flex shrink-0 items-center gap-2.5"><img src="{{ asset('images/logo.png') }}" alt="INVOIZ logo" class="h-10 w-10 rounded-xl object-cover ring-1 ring-gray-200"><span class="text-xl font-extrabold tracking-tight text-teal-dark">INVOIZ</span></a>
<form action="{{ url('/shop') }}" method="GET" role="search" class="hidden min-w-0 flex-1 md:flex">
<div class="flex min-h-[46px] w-full items-center overflow-hidden rounded-2xl border-2 border-teal/20 bg-white shadow-sm focus-within:border-teal">
<span class="flex px-4 text-teal"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg></span>
<input name="search" type="search" placeholder="Search for products, stores, and more..." class="min-w-0 flex-1 border-0 bg-transparent py-3 pr-3 text-sm outline-none">
<button class="m-1 min-h-[38px] rounded-xl bg-teal px-5 text-sm font-bold text-white hover:bg-teal-dark">Search</button>
</div></form>
<nav class="ml-auto hidden shrink-0 items-center gap-2 lg:flex">
@auth
  @if(auth()->user()->isAdmin())
    <a href="{{ url('/admin/dashboard') }}" class="inline-flex min-h-[44px] items-center rounded-xl bg-teal px-4 text-sm font-bold text-white">Admin Dashboard</a>
  @elseif(auth()->user()->role==='seller')
    @php $dualSeller = \App\Models\Seller::where('user_id', auth()->id())->first(); @endphp
    @if($dualSeller && $dualSeller->isApproved())
      <a href="{{ url('/choose') }}" class="inline-flex min-h-[44px] items-center rounded-xl border border-teal/30 px-4 text-sm font-bold text-teal-dark">Switch account</a>
    @endif
    <a href="{{ url('/seller/dashboard') }}" class="inline-flex min-h-[44px] items-center rounded-xl bg-teal px-4 text-sm font-bold text-white">Seller Dashboard</a>
  @else
    @php $dualSeller = \App\Models\Seller::where('user_id', auth()->id())->first(); @endphp
    @if($dualSeller && $dualSeller->isApproved())
      <a href="{{ url('/choose') }}" class="inline-flex min-h-[44px] items-center rounded-xl border border-teal/30 px-4 text-sm font-bold text-teal-dark">Switch account</a>
    @endif
    <a href="{{ url('/shop') }}" class="inline-flex min-h-[44px] items-center rounded-xl bg-teal px-4 text-sm font-bold text-white">My Shop</a>
  @endif
  <form method="POST" action="{{ url('/logout') }}">@csrf<button class="inline-flex min-h-[44px] items-center rounded-xl border px-4 text-sm font-bold">Logout</button></form>
@else
  <a href="{{ url('/cart') }}" aria-label="Cart" class="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-xl border border-gray-200 text-teal hover:bg-teal-light"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l2.2 10.5a2 2 0 002 1.6h6.8a2 2 0 001.9-1.4L20 7H6m4 13a1 1 0 100-2 1 1 0 000 2zm7 0a1 1 0 100-2 1 1 0 000 2z"/></svg></a>
  <a href="{{ url('/login') }}" class="inline-flex min-h-[44px] items-center rounded-xl border px-4 text-sm font-bold">Login</a>
  <a href="{{ url('/register') }}" class="inline-flex min-h-[44px] items-center rounded-xl bg-teal px-4 text-sm font-extrabold text-white transition hover:bg-teal-dark">Sign Up</a>
@endauth
</nav>
<button @click="open=!open" class="ml-auto lg:hidden inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-xl border" aria-label="Toggle menu"><svg x-show="!open" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg><svg x-show="open" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
</div>
<div class="hidden border-t border-gray-100 py-2 lg:block"><nav class="flex items-center gap-1 text-sm font-semibold text-gray-600">
<a href="{{ url('/') }}" class="px-3 py-2 hover:text-teal-dark">Home</a><a href="#categories" class="px-3 py-2">Categories</a><a href="{{ url('/shop') }}" class="px-3 py-2">Shop</a><a href="#deals" class="px-3 py-2">Deals</a><a href="#stores" class="px-3 py-2">Stores</a><a href="#how-it-works" class="px-3 py-2">How It Works</a><a href="#help" class="px-3 py-2">Help</a></nav></div>
<div x-show="open" x-cloak class="lg:hidden border-t py-3 grid gap-1 text-sm font-semibold">
<form action="{{ url('/shop') }}" method="GET" role="search" class="px-3 pb-2">
<div class="flex min-h-[44px] items-center rounded-xl border border-teal/20 bg-white px-3">
<svg class="h-4 w-4 shrink-0 text-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
<input name="search" type="search" placeholder="Search products, stores..." class="min-w-0 flex-1 border-0 bg-transparent px-2 py-2 text-sm outline-none">
<button class="text-sm font-bold text-teal">Go</button>
</div></form>
<a href="{{ url('/') }}" @click="open=false" class="px-3 py-2">Home</a><a href="{{ url('/shop') }}" @click="open=false" class="px-3 py-2">Shop</a><a href="#categories" @click="open=false" class="px-3 py-2">Categories</a><a href="#deals" @click="open=false" class="px-3 py-2">Deals</a><a href="#stores" @click="open=false" class="px-3 py-2">Stores</a><a href="#how-it-works" @click="open=false" class="px-3 py-2">How It Works</a>
<div class="grid grid-cols-2 gap-2 mt-2">
@auth<form method="POST" action="{{ url('/logout') }}">@csrf<button class="w-full rounded-xl border py-3 font-bold">Logout</button></form>
@else<a href="{{ url('/login') }}" class="rounded-xl border py-3 text-center font-bold">Login</a><a href="{{ url('/register') }}" class="rounded-xl bg-teal py-3 text-center font-extrabold text-white">Sign Up</a>@endauth
</div></div>
</div></header>
<main>
<section id="home" class="marketplace-band overflow-hidden"><div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 lg:py-14">
<div class="grid items-center gap-8 lg:grid-cols-[1.02fr_.98fr]">
<div>
<p class="inline-flex items-center gap-2 rounded-full bg-white/80 px-3 py-1 text-xs font-extrabold text-teal-dark shadow-sm ring-1 ring-teal/10 backdrop-blur"><span class="relative flex h-2 w-2"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber opacity-75"></span><span class="relative inline-flex h-2 w-2 rounded-full bg-amber"></span></span>INVOIZ marketplace · live deals daily</p>
<h1 class="landing-display mt-4 font-extrabold">Life's short.<br><span class="bg-gradient-to-r from-teal to-teal-dark bg-clip-text text-transparent">Shop fast.</span></h1>
<p class="mt-4 text-lg font-extrabold text-teal-dark">Shop local. Shop smart. Shop INVOIZ.</p>
<p class="mt-3 max-w-xl text-sm text-gray-600 sm:text-base">Discover great products from local sellers, enjoy exclusive deals, and get orders delivered. One account works everywhere: buyers shop, sellers manage stores, admins oversee the platform.</p>
@if(session('success'))<div class="mt-4 rounded-xl bg-emerald-50 text-emerald-700 text-sm p-3">{{ session('success') }}</div>@endif
<div class="mt-6 flex flex-col gap-3 sm:flex-row">
<a href="{{ url('/shop') }}" class="inline-flex min-h-[48px] items-center justify-center rounded-2xl border bg-white px-6 text-sm font-extrabold text-teal-dark shadow-sm transition hover:-translate-y-0.5 hover:shadow-card">Explore Products</a>
<a href="#deals" class="inline-flex min-h-[48px] items-center justify-center rounded-2xl px-6 text-sm font-extrabold text-teal-dark transition hover:text-teal">Today's deals ↓</a>
</div>
<div class="mt-6 grid max-w-lg grid-cols-3 gap-3">
<div class="rounded-2xl bg-white p-3 text-center shadow-sm"><p class="text-lg font-extrabold text-teal-dark">{{ number_format($stats['products'] ?? 0) }}+</p><p class="text-[11px] text-gray-500">Local picks</p></div>
<div class="rounded-2xl bg-white p-3 text-center shadow-sm"><p class="text-lg font-extrabold text-teal-dark">{{ number_format($stats['orders'] ?? 0) }}</p><p class="text-[11px] text-gray-500">Orders placed</p></div>
<div class="rounded-2xl bg-white p-3 text-center shadow-sm"><p class="text-lg font-extrabold text-teal-dark">{{ number_format($stats['sellers'] ?? 0) }}</p><p class="text-[11px] text-gray-500">Local sellers</p></div>
</div></div>
<div class="relative"><div class="rounded-[2rem] border bg-white p-4 shadow-soft" x-data="{active:0, total:{{ count($deals ?? []) }}}" x-init="total > 1 && setInterval(() => { active = (active + 1) % total }, 4000)">
@php $dealList = $deals ?? []; @endphp
<div class="grid gap-4 sm:grid-cols-2">
<div class="relative overflow-hidden rounded-3xl bg-gray-100 ratio-slide">
@forelse($dealList as $i => $dd)
<a href="{{ url('/product/'.$dd['id']) }}" x-show="active === {{ $i }}" x-transition.opacity.duration.500ms class="group absolute inset-0 block">
@if(!empty($dd['image']))<img src="{{ $dd['image'] }}" alt="{{ $dd['name'] }}" class="absolute inset-0 h-full w-full object-cover">@else<div class="flex h-full w-full items-center justify-center bg-teal-light text-3xl font-extrabold text-teal">{{ strtoupper(mb_substr($dd['name'],0,1)) }}</div>@endif
<span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/60 to-transparent p-4 pt-10 text-left"><span class="block truncate text-sm font-extrabold text-white">{{ $dd['name'] }}</span><span class="text-xs font-bold text-amber">₱{{ number_format($dd['price']) }}@if(!empty($dd['pct'])) · {{ $dd['pct'] }}% OFF @endif</span></span>
</a>
@empty
<img src="https://images.unsplash.com/photo-1607082349566-187342175e2f?auto=format&fit=crop&w=900&q=80" alt="Marketplace" class="absolute inset-0 h-full w-full object-cover">
@endforelse
<span class="absolute left-4 top-4 rounded-full bg-amber px-3 py-1 text-xs font-extrabold text-charcoal shadow">On sale now</span>
<button type="button" x-show="total > 1" @click="active = (active - 1 + total) % total" aria-label="Previous" class="absolute left-2 top-1/2 -translate-y-1/2 rounded-full bg-white/90 px-2.5 py-1.5 text-sm font-extrabold text-teal-dark shadow hover:bg-white">‹</button>
<button type="button" x-show="total > 1" @click="active = (active + 1) % total" aria-label="Next" class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-white/90 px-2.5 py-1.5 text-sm font-extrabold text-teal-dark shadow hover:bg-white">›</button>
</div>
<div class="grid gap-4">
<div class="rounded-3xl bg-teal p-5 text-white"><p class="text-xs font-bold uppercase text-white/70">Flash Deal</p><p class="mt-2 text-3xl font-extrabold">Up to 40% off</p><p class="mt-2 text-sm text-white/80">Daily finds from local sellers.</p><a href="#deals" class="mt-3 inline-block rounded-xl bg-white/15 px-4 py-2 text-xs font-extrabold hover:bg-white/25">See today's deals</a></div>
<div class="rounded-3xl bg-amber-light p-5"><p class="text-xs font-bold uppercase text-amber-700">Doorstep delivery</p><p class="mt-2 text-lg font-extrabold">Packed, picked up, sorted, delivered.</p></div>
</div></div>
<div x-show="total > 1" class="mt-3 flex items-center justify-center gap-2">
@for($d = 0; $d < count($dealList); $d++)
<button type="button" aria-label="Slide {{ $d + 1 }}" @click="active = {{ $d }}" :class="active === {{ $d }} ? 'bg-teal w-8' : 'bg-teal/30 w-2'" class="h-2 rounded-full transition"></button>
@endfor
</div>
</div></div>
</div></div></section>
<section id="categories" class="bg-white py-10 border-y border-gray-100"><div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
<div class="flex items-end justify-between"><div><p class="eyebrow">Browse</p><h2 class="landing-section-title mt-1 font-extrabold">Shop by Category</h2><p class="mt-1 text-sm text-gray-600">Quick paths to what shoppers look for most.</p></div><a href="{{ url('/shop') }}" class="shrink-0 rounded-xl border px-4 py-2 text-sm font-bold text-teal transition hover:bg-teal-light">View all</a></div>
<div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-5">
@forelse(($categories ?? collect()) as $ci => $c)
@php $cimgs = $c->cover_images ?? []; @endphp
<a href="{{ url('/shop?category='.$c->id) }}" class="card-hover group overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm hover:shadow-card">
<div class="img-frame ratio-43" x-data="{active:0, total:{{ count($cimgs) }}}" x-init="total > 1 && setInterval(() => { active = (active + 1) % total }, 3500 + ({{ $ci }} * 700))">
@forelse($cimgs as $ii => $cimg)
<img src="{{ $cimg }}" alt="{{ $c->name }}" loading="lazy" x-show="active === {{ $ii }}" x-transition.opacity.duration.500ms>
@empty
<div class="flex h-full w-full items-center justify-center bg-teal-light text-2xl font-extrabold text-teal">{{ strtoupper(mb_substr($c->name,0,1)) }}</div>
@endforelse
</div>
<div class="p-3 text-center"><span class="block text-sm font-extrabold group-hover:text-teal-dark">{{ $c->name }}</span><span class="text-[11px] text-gray-500">{{ $c->products_count }} item(s)</span></div>
</a>
@empty
@foreach(['Fashion','Gadgets','Home & Living','Beauty','Groceries'] as $c)
<a href="{{ url('/shop') }}" class="rounded-3xl border bg-offwhite p-4 text-center shadow-sm"><span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-teal-light text-teal font-extrabold">{{ substr($c,0,1) }}</span><span class="mt-3 block text-sm font-extrabold">{{ $c }}</span></a>
@endforeach
@endforelse
</div></div></section>
<section id="deals" class="py-12"><div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
<div class="flex items-end justify-between gap-3"><div><p class="eyebrow">Limited time</p><h2 class="landing-section-title mt-1 font-extrabold">Today's Deals</h2><p class="mt-1 text-sm text-gray-600">Real discounts from local sellers — ends in <span id="deal-countdown" class="font-extrabold tabular-nums text-teal-dark">--:--:--</span></p></div><a href="{{ url('/sale') }}" class="shrink-0 rounded-xl border px-4 py-2 text-sm font-bold text-teal transition hover:bg-teal-light">Open shop →</a></div>
<div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
@forelse(($deals ?? []) as $d)
<a href="{{ url('/product/'.$d['id']) }}" class="card-hover group overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm hover:shadow-card">
<div class="img-frame ratio-43">
@if(!empty($d['image']))<img src="{{ $d['image'] }}" alt="{{ $d['name'] }}" loading="lazy">@else<div class="flex h-full w-full items-center justify-center bg-teal-light text-2xl font-extrabold text-teal">{{ strtoupper(mb_substr($d['name'],0,1)) }}</div>@endif
@if(!empty($d['pct']))<span class="absolute left-2 top-2 z-10 rounded-full bg-amber px-2 py-1 text-[11px] font-extrabold text-charcoal shadow">−{{ $d['pct'] }}%</span>@endif
</div>
<div class="p-3"><h3 class="line-clamp-2 min-h-[2.5rem] text-sm font-bold text-gray-900 group-hover:text-teal-dark">{{ $d['name'] }}</h3>
<div class="mt-2 flex items-baseline gap-2"><p class="text-base font-extrabold text-teal-dark">₱{{ number_format($d['price']) }}</p>@if(!empty($d['compare_at']))<p class="text-xs text-gray-400 line-through">₱{{ number_format($d['compare_at']) }}</p>@endif</div>
<div class="mt-2 flex items-center justify-between gap-2 text-xs">@if(!empty($d['rating']))<span class="inline-flex items-center gap-1 font-bold text-amber-700"><svg class="h-3.5 w-3.5 fill-current" viewBox="0 0 20 20"><path d="M10 1.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L10 14.8l-5.2 2.8 1-5.8-4.3-4.1 5.9-.9L10 1.5z"/></svg>{{ $d['rating'] }}</span>@else<span></span>@endif<span class="truncate text-gray-500">{{ $d['store'] }}</span></div>
</div></a>
@empty
<p class="col-span-full rounded-2xl bg-white p-6 text-center text-sm text-gray-500">No deals right now — <a href="{{ url('/shop') }}" class="font-bold text-teal">browse the shop</a>.</p>
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
</script></div></section>
<section id="stores" class="py-12 bg-white border-y"><div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
<p class="eyebrow">Local sellers</p><h2 class="landing-section-title mt-1 font-extrabold">Discover Local Stores</h2>
<div class="mt-6 grid gap-4 md:grid-cols-3">
@forelse(($stores ?? collect()) as $s)
<article class="card-hover rounded-3xl border border-gray-100 bg-white p-5 shadow-sm hover:shadow-card">
<div class="flex items-center gap-3">
@if(!empty($s['logo']))<img src="{{ $s['logo'] }}" alt="{{ $s['name'] }}" class="h-14 w-14 shrink-0 rounded-2xl object-cover ring-1 ring-gray-200">@else<span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-teal to-teal-dark text-lg font-extrabold text-white">{{ strtoupper(mb_substr($s['name'],0,1)) }}</span>@endif
<div class="min-w-0"><h3 class="truncate text-base font-extrabold">{{ $s['name'] }}</h3><p class="truncate text-xs font-bold text-amber-700">{{ $s['line'] }}</p></div></div>
<div class="mt-4 flex items-center justify-between"><span class="inline-flex rounded-full bg-teal-light px-3 py-1 text-xs font-extrabold text-teal-dark">{{ $s['products'] }} product(s)</span><a href="{{ url('/store/'.$s['user_id']) }}" class="inline-flex items-center gap-1 rounded-xl bg-teal px-4 py-2 text-xs font-extrabold text-white transition hover:bg-teal-dark">Visit store →</a></div>
</article>
@empty
<p class="rounded-2xl bg-white p-6 text-center text-sm text-gray-500 md:col-span-3">Stores will appear here once sellers add products.</p>
@endforelse
</div></div></section>
<section id="how-it-works" class="py-12"><div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
<h2 class="landing-section-title font-extrabold">How INVOIZ Works</h2>
<div class="mt-7 grid gap-3 md:grid-cols-2 xl:grid-cols-6">
@foreach(['Buyer discovers a product','Buyer places an order','Seller prepares the order','Rider picks up the parcel','Logistics sorts & assigns','Rider delivers to buyer'] as $i=>$s)
<div class="card-hover rounded-3xl border border-gray-100 bg-white p-4 shadow-sm hover:shadow-card"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-teal to-teal-dark text-sm font-extrabold text-white">{{ $i+1 }}</span><p class="mt-3 text-sm font-extrabold">{{ $s }}</p></div>
@endforeach
</div></div></section>
<section id="rider" class="pb-12"><div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
<div class="relative grid gap-6 overflow-hidden rounded-3xl border-2 border-teal/25 bg-white p-6 shadow-soft sm:p-8 lg:grid-cols-[1fr_auto] lg:items-center">
<div class="pointer-events-none absolute -right-20 -top-20 h-64 w-64 rounded-full bg-teal-light blur-2xl" aria-hidden="true"></div>
<div class="pointer-events-none absolute -bottom-24 -left-16 h-56 w-56 rounded-full bg-amber-light blur-2xl" aria-hidden="true"></div>
<div class="relative">
<p class="inline-flex items-center gap-2 rounded-full bg-teal-light px-3 py-1 text-xs font-extrabold uppercase tracking-wider text-teal-dark"><svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>Rider community</p>
<h2 class="mt-3 text-2xl font-extrabold tracking-tight text-charcoal">Want to earn delivering with INVOIZ?</h2>
<p class="mt-2 max-w-2xl text-sm leading-relaxed text-gray-600">Riders deliver orders through the rider app. Tap below on a phone with the app installed, or continue in the browser.</p>
<ul class="mt-4 flex flex-wrap gap-2 text-xs font-bold text-teal-dark">
<li class="inline-flex items-center gap-1.5 rounded-full bg-teal-light px-3 py-1.5"><span class="text-teal">✓</span>Flexible hours</li>
<li class="inline-flex items-center gap-1.5 rounded-full bg-teal-light px-3 py-1.5"><span class="text-teal">✓</span>Serve your neighborhood</li>
<li class="inline-flex items-center gap-1.5 rounded-full bg-teal-light px-3 py-1.5"><span class="text-teal">✓</span>Track every delivery</li>
</ul>
</div>
<div class="relative grid gap-2 sm:grid-cols-2 lg:grid-cols-1">
<a href="invoizrider://login" class="inline-flex min-h-[48px] items-center justify-center gap-2 rounded-2xl bg-teal px-6 text-sm font-extrabold text-white shadow-card transition hover:-translate-y-0.5 hover:bg-teal-dark"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>Open Rider App</a>
<a href="{{ url('/login') }}" class="inline-flex min-h-[48px] items-center justify-center rounded-2xl border-2 border-teal/30 px-6 text-sm font-extrabold text-teal-dark transition hover:-translate-y-0.5 hover:border-teal hover:bg-teal-light">Continue on Web</a>
</div></div></div></section>
</main>
<footer id="help" class="bg-charcoal text-white"><div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
<div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
<div><div class="flex items-center gap-2"><img src="{{ asset('images/logo.png') }}" class="h-8 w-8 rounded-lg"><span class="font-extrabold">INVOIZ</span></div><p class="text-sm text-white/70 mt-3">Life's short. Shop fast.</p></div>
<div><h3 class="font-extrabold text-sm">Shop</h3><ul class="mt-3 space-y-2 text-sm text-white/65"><li><a href="{{ url('/shop') }}">All products</a></li><li><a href="{{ url('/cart') }}">Cart</a></li><li><a href="{{ url('/orders') }}">Orders</a></li></ul></div>
<div><h3 class="font-extrabold text-sm">Ecosystem</h3><ul class="mt-3 space-y-2 text-sm text-white/65"><li><a href="{{ url('/seller/dashboard') }}">Seller Center</a></li><li><a href="{{ url('/admin/dashboard') }}">Admin Center</a></li><li><a href="{{ url('/buyer/chat') }}">Buyer Chat</a></li><li><a href="#rider">Rider App</a></li></ul></div>
<div><h3 class="font-extrabold text-sm">Account</h3><ul class="mt-3 space-y-2 text-sm text-white/65"><li><a href="{{ url('/login') }}">Login</a></li><li><a href="{{ url('/register') }}">Sign Up (buyer)</a></li></ul></div>
</div>
<div class="mt-8 border-t border-white/10 pt-6 text-xs text-white/50 sm:flex sm:items-center sm:justify-between">
<p>© 2026 INVOIZ. All rights reserved.</p>
<p class="mt-2 flex gap-4 sm:mt-0"><a href="{{ url('/shop') }}" class="hover:text-white">Shop</a><a href="{{ url('/sell') }}" class="hover:text-white">Sell</a><a href="{{ url('/login') }}" class="hover:text-white">Login</a></p>
</div>
</div></footer>
<button id="toTop" aria-label="Back to top" class="fixed bottom-5 right-5 z-50 hidden h-11 w-11 items-center justify-center rounded-full bg-teal text-white shadow-soft transition hover:bg-teal-dark">↑</button>
<script>
(function(){
  var els = document.querySelectorAll('main section');
  els.forEach(function(s){ s.classList.add('reveal'); });
  var io = new IntersectionObserver(function(es){ es.forEach(function(e){ if(e.isIntersecting){ e.target.classList.add('visible'); io.unobserve(e.target); } }); }, {threshold:.08});
  els.forEach(function(s){ io.observe(s); });
  var top = document.getElementById('toTop');
  function onScroll(){ var show = window.scrollY > 600; top.classList.toggle('hidden', !show); top.classList.toggle('flex', show); }
  window.addEventListener('scroll', onScroll, {passive:true}); onScroll();
  top.addEventListener('click', function(){ window.scrollTo({top:0, behavior:'smooth'}); });
})();
</script>
</body>
</html>
