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
<style>[x-cloak]{display:none!important}html{scroll-behavior:smooth}body{overflow-x:hidden}.landing-display{font-size:clamp(2.35rem,4.5vw + .65rem,5rem);line-height:.96}.landing-section-title{font-size:clamp(1.5rem,1.15rem + 1vw,2.1rem);line-height:1.1}.product-image{aspect-ratio:4/3;object-fit:cover}.hide-scrollbar{scrollbar-width:none}.hide-scrollbar::-webkit-scrollbar{display:none}.marketplace-band{background:radial-gradient(circle at 12% 18%,rgba(240,162,2,.18),transparent 28%),radial-gradient(circle at 85% 12%,rgba(22,105,122,.14),transparent 30%),linear-gradient(135deg,#F8FAF9 0%,#fff 52%,#EAF4F3 100%)}</style>
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
  <a href="{{ url('/shop') }}" aria-label="Cart" class="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-xl border border-gray-200 text-teal hover:bg-teal-light"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l2.2 10.5a2 2 0 002 1.6h6.8a2 2 0 001.9-1.4L20 7H6m4 13a1 1 0 100-2 1 1 0 000 2zm7 0a1 1 0 100-2 1 1 0 000 2z"/></svg></a>
  <a href="{{ url('/login') }}" class="inline-flex min-h-[44px] items-center rounded-xl border px-4 text-sm font-bold">Login</a>
  <a href="{{ url('/register') }}" class="inline-flex min-h-[44px] items-center rounded-xl bg-amber px-4 text-sm font-extrabold">Sign Up</a>
@endauth
</nav>
<button @click="open=!open" class="ml-auto lg:hidden inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-xl border" aria-label="Toggle menu"><svg x-show="!open" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg><svg x-show="open" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
</div>
<div class="hidden border-t border-gray-100 py-2 lg:block"><nav class="flex items-center gap-1 text-sm font-semibold text-gray-600">
<a href="{{ url('/') }}" class="px-3 py-2 hover:text-teal-dark">Home</a><a href="#categories" class="px-3 py-2">Categories</a><a href="{{ url('/shop') }}" class="px-3 py-2">Shop</a><a href="#deals" class="px-3 py-2">Deals</a><a href="#stores" class="px-3 py-2">Stores</a><a href="#how-it-works" class="px-3 py-2">How It Works</a><a href="#help" class="px-3 py-2">Help</a>
<span class="ml-auto rounded-full bg-amber-light px-3 py-1 text-xs font-bold text-amber-700">One backend · invoizdb · buyer / seller / admin connected</span></nav></div>
<div x-show="open" x-cloak class="lg:hidden border-t py-3 grid gap-1 text-sm font-semibold">
<a href="{{ url('/') }}" class="px-3 py-2">Home</a><a href="{{ url('/shop') }}" class="px-3 py-2">Shop</a><a href="#deals" class="px-3 py-2">Deals</a>
<div class="grid grid-cols-2 gap-2 mt-2">
@auth<form method="POST" action="{{ url('/logout') }}">@csrf<button class="w-full rounded-xl border py-3 font-bold">Logout</button></form>
@else<a href="{{ url('/login') }}" class="rounded-xl border py-3 text-center font-bold">Login</a><a href="{{ url('/register') }}" class="rounded-xl bg-amber py-3 text-center font-extrabold">Sign Up</a>@endauth
</div></div>
</div></header>
<main>
<section id="home" class="marketplace-band overflow-hidden"><div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 lg:py-14">
<div class="grid items-center gap-8 lg:grid-cols-[1.02fr_.98fr]">
<div>
<p class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1 text-xs font-extrabold text-teal-dark shadow-sm">● INVOIZ marketplace — single backend</p>
<h1 class="landing-display mt-4 font-extrabold">Life's short.<br><span class="text-teal">Shop fast.</span></h1>
<p class="mt-4 text-lg font-extrabold text-teal-dark">Shop local. Shop smart. Shop INVOIZ.</p>
<p class="mt-3 max-w-xl text-sm text-gray-600 sm:text-base">Discover great products from local sellers, enjoy exclusive deals, and get orders delivered. One account works everywhere: buyers shop, sellers manage stores, admins oversee the platform.</p>
@if(session('success'))<div class="mt-4 rounded-xl bg-emerald-50 text-emerald-700 text-sm p-3">{{ session('success') }}</div>@endif
<div class="mt-6 flex flex-col gap-3 sm:flex-row">
<a href="{{ url('/shop') }}" class="inline-flex min-h-[48px] items-center justify-center rounded-2xl bg-amber px-6 text-sm font-extrabold shadow-card">Shop Now</a>
<a href="{{ url('/shop') }}" class="inline-flex min-h-[48px] items-center justify-center rounded-2xl border bg-white px-6 text-sm font-extrabold text-teal-dark">Explore Products</a>
</div>
<div class="mt-6 grid max-w-lg grid-cols-3 gap-3">
<div class="rounded-2xl bg-white p-3 text-center shadow-sm"><p class="text-lg font-extrabold text-teal-dark">500+</p><p class="text-[11px] text-gray-500">Local picks</p></div>
<div class="rounded-2xl bg-white p-3 text-center shadow-sm"><p class="text-lg font-extrabold text-teal-dark">24h</p><p class="text-[11px] text-gray-500">Deal drops</p></div>
<div class="rounded-2xl bg-white p-3 text-center shadow-sm"><p class="text-lg font-extrabold text-teal-dark">Fast</p><p class="text-[11px] text-gray-500">Delivery flow</p></div>
</div></div>
<div class="relative"><div class="rounded-[2rem] border bg-white p-4 shadow-soft">
<div class="grid gap-4 sm:grid-cols-2">
<div class="overflow-hidden rounded-3xl bg-gray-100"><img src="https://images.unsplash.com/photo-1607082349566-187342175e2f?auto=format&fit=crop&w=900&q=80" alt="Marketplace" class="h-64 w-full object-cover sm:h-full"></div>
<div class="grid gap-4"><div class="rounded-3xl bg-teal p-5 text-white"><p class="text-xs font-bold uppercase text-white/70">Flash Deal</p><p class="mt-2 text-3xl font-extrabold">Up to 40% off</p><p class="mt-2 text-sm text-white/80">Daily finds from local sellers.</p></div>
<div class="rounded-3xl bg-amber-light p-5"><p class="text-xs font-bold uppercase text-amber-700">Doorstep delivery</p><p class="mt-2 text-lg font-extrabold">Packed, picked up, sorted, delivered.</p></div></div></div></div></div>
</div></div></section>
<section id="categories" class="bg-white py-10 border-y border-gray-100"><div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
<div class="flex items-end justify-between"><div><h2 class="landing-section-title font-extrabold">Shop by Category</h2><p class="text-sm text-gray-600">Quick paths to what shoppers look for most.</p></div><a href="{{ url('/shop') }}" class="text-sm font-bold text-teal">View all</a></div>
<div class="mt-6 grid grid-cols-2 sm:grid-cols-5 gap-3">
@foreach(['Fashion','Mobiles & Gadgets','Home & Living','Beauty','Food & Groceries','Kids','Accessories','Sports','Toys & Games','More'] as $c)
<a href="{{ url('/shop') }}" class="rounded-2xl border bg-offwhite p-4 text-center shadow-sm hover:shadow-card"><span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-teal-light text-teal font-extrabold">{{ substr($c,0,1) }}</span><span class="mt-3 block text-sm font-extrabold">{{ $c }}</span></a>
@endforeach
</div></div></section>
<section id="deals" class="py-12"><div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
<div class="flex items-end justify-between"><div><h2 class="landing-section-title font-extrabold">Today's Deals</h2><p class="text-sm text-gray-600">Live products from <b>invoizdb</b> appear inside the shop.</p></div><a href="{{ url('/shop') }}" class="text-sm font-bold text-teal">Open shop →</a></div>
<div class="mt-6 grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4">
@forelse(($deals ?? []) as $d)
<article class="overflow-hidden rounded-2xl border bg-white shadow-sm"><img src="{{ $d['image'] }}" alt="{{ $d['name'] }}" class="product-image w-full"><div class="p-3"><h3 class="text-sm font-bold line-clamp-2 min-h-[2.5rem]">{{ $d['name'] }}</h3><p class="text-base font-extrabold text-teal-dark mt-2">₱{{ number_format($d['price']) }}</p></div></article>
@empty
@foreach([['Everyday Canvas Tote',799,'https://images.unsplash.com/photo-1590874103328-eac38a683ce7?auto=format&fit=crop&w=700&q=80'],['Wireless Earbuds Pro',1490,'https://images.unsplash.com/photo-1606220588913-b3aacb4d2f46?auto=format&fit=crop&w=700&q=80'],['Ceramic Dinner Set',949,'https://images.unsplash.com/photo-1610701596007-11502861dcfa?auto=format&fit=crop&w=700&q=80'],['Daily Glow Skincare Kit',449,'https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=700&q=80'],['Premium Coffee Beans',520,'https://images.unsplash.com/photo-1447933601403-0c6688de566e?auto=format&fit=crop&w=700&q=80'],['Kids Learning Blocks',680,'https://images.unsplash.com/photo-1566576912321-d58ddd7a6088?auto=format&fit=crop&w=700&q=80']] as $p)
<article class="overflow-hidden rounded-2xl border bg-white shadow-sm"><img src="{{ $p[2] }}" alt="{{ $p[0] }}" class="product-image w-full"><div class="p-3"><h3 class="text-sm font-bold line-clamp-2 min-h-[2.5rem]">{{ $p[0] }}</h3><p class="text-base font-extrabold text-teal-dark mt-2">₱{{ number_format($p[1]) }}</p><a href="{{ url('/shop') }}" class="text-xs font-bold text-teal">Shop →</a></div></article>
@endforeach
@endforelse
</div></div></section>
<section id="stores" class="py-12 bg-white border-y"><div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
<h2 class="landing-section-title font-extrabold">Discover Local Stores</h2>
<div class="mt-6 grid gap-4 md:grid-cols-3">
@foreach([['Makati Finds','Fashion & Accessories'],['Casa Lokal','Home & Living'],['Gadget Lane PH','Mobiles & Gadgets']] as $s)
<article class="rounded-3xl border p-5 shadow-sm"><h3 class="font-extrabold">{{ $s[0] }}</h3><p class="text-xs font-bold text-amber-700">{{ $s[1] }}</p><a href="{{ url('/shop') }}" class="text-xs font-bold text-teal">Visit in shop →</a></article>
@endforeach
</div></div></section>
<section id="seller" class="py-8"><div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
<div class="rounded-3xl bg-white p-6 shadow-soft ring-1 ring-gray-100 lg:flex lg:items-center lg:justify-between">
<div><p class="text-xs font-extrabold uppercase text-amber-700">Seller opportunity</p><h2 class="text-2xl font-extrabold">Have something to sell?</h2><p class="text-sm text-gray-600">Sellers log in with the same page — you'll go straight to the seller dashboard.</p></div>
<a href="{{ url('/login') }}" class="mt-4 lg:mt-0 inline-flex min-h-[48px] items-center rounded-2xl bg-teal px-6 text-sm font-extrabold text-white">Seller Login</a>
</div></div></section>
<section id="how-it-works" class="py-12"><div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
<h2 class="landing-section-title font-extrabold">How INVOIZ Works</h2>
<div class="mt-7 grid gap-3 md:grid-cols-2 xl:grid-cols-6">
@foreach(['Buyer discovers a product','Buyer places an order','Seller prepares the order','Rider picks up the parcel','Logistics sorts & assigns','Rider delivers to buyer'] as $i=>$s)
<div class="rounded-2xl border bg-white p-4 shadow-sm"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-teal text-sm font-extrabold text-white">{{ $i+1 }}</span><p class="mt-3 text-sm font-extrabold">{{ $s }}</p></div>
@endforeach
</div></div></section>
<section class="bg-white py-12 border-y"><div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
<div class="rounded-3xl bg-gradient-to-br from-teal via-teal-dark to-[#0B3B46] p-7 text-white">
<div class="grid gap-6 lg:grid-cols-[1fr_auto] lg:items-center"><div><h2 class="text-2xl font-extrabold">Shop, grow your business, or earn by delivering.</h2><p class="text-sm text-white/75">One backend · one database (invoizdb) · buyer / seller / admin connected.</p></div>
<div class="grid gap-2 sm:grid-cols-2"><a href="{{ url('/register') }}" class="rounded-2xl bg-white px-5 py-3 text-sm font-extrabold text-teal-dark text-center">Become a Buyer</a><a href="{{ url('/login') }}" class="rounded-2xl border border-white/25 px-5 py-3 text-sm font-extrabold text-center">Seller / Admin Login</a></div></div>
</div></div></section>
</main>
<footer id="help" class="bg-charcoal text-white"><div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
<div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
<div><div class="flex items-center gap-2"><img src="{{ asset('images/logo.png') }}" class="h-8 w-8 rounded-lg"><span class="font-extrabold">INVOIZ</span></div><p class="text-sm text-white/70 mt-3">Life's short. Shop fast.</p></div>
<div><h3 class="font-extrabold text-sm">Shop</h3><ul class="mt-3 space-y-2 text-sm text-white/65"><li><a href="{{ url('/shop') }}">All products</a></li><li><a href="{{ url('/cart') }}">Cart</a></li><li><a href="{{ url('/orders') }}">Orders</a></li></ul></div>
<div><h3 class="font-extrabold text-sm">Ecosystem</h3><ul class="mt-3 space-y-2 text-sm text-white/65"><li><a href="{{ url('/seller/dashboard') }}">Seller Center</a></li><li><a href="{{ url('/admin/dashboard') }}">Admin Center</a></li><li><a href="{{ url('/buyer/chat') }}">Buyer Chat</a></li></ul></div>
<div><h3 class="font-extrabold text-sm">Account</h3><ul class="mt-3 space-y-2 text-sm text-white/65"><li><a href="{{ url('/login') }}">Login</a></li><li><a href="{{ url('/register') }}">Sign Up (buyer)</a></li></ul></div>
</div>
<div class="mt-8 border-t border-white/10 pt-6 text-xs text-white/50">© 2026 INVOIZ · Unified single backend · DB: invoizdb · Register → buyer · Login → role redirect.</div>
</div></footer>
</body>
</html>
