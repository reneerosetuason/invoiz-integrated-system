@php
    $marketplaceUrl = config('invoiz.guest.marketplace_url');
    $sellerUrl = config('invoiz.platforms.seller_url');
    $riderDeeplink = config('invoiz.platforms.rider_deeplink', 'invoizrider://login');
    $shopHref = $marketplaceUrl ?: '#deals';
    $storesHref = $marketplaceUrl ?: '#stores';
    $categoriesHref = $marketplaceUrl ?: '#categories';

    $categories = [
        ['name' => 'Fashion', 'icon' => 'M6 3h12l-1 18H7L6 3zm3 0a3 3 0 006 0', 'color' => 'bg-rose-50 text-rose-600'],
        ['name' => 'Mobiles & Gadgets', 'icon' => 'M8 2h8a2 2 0 012 2v16a2 2 0 01-2 2H8a2 2 0 01-2-2V4a2 2 0 012-2zm4 17h.01', 'color' => 'bg-teal-light text-teal'],
        ['name' => 'Home & Living', 'icon' => 'M3 11l9-8 9 8v9a1 1 0 01-1 1h-5v-6H9v6H4a1 1 0 01-1-1v-9z', 'color' => 'bg-amber-light text-amber-700'],
        ['name' => 'Beauty', 'icon' => 'M12 3c4 4 6 7 6 11a6 6 0 01-12 0c0-4 2-7 6-11z', 'color' => 'bg-pink-50 text-pink-600'],
        ['name' => 'Food & Groceries', 'icon' => 'M4 7h16l-2 14H6L4 7zm4 0a4 4 0 018 0', 'color' => 'bg-emerald-50 text-emerald-600'],
        ['name' => 'Kids', 'icon' => 'M12 12a4 4 0 100-8 4 4 0 000 8zm-7 9a7 7 0 0114 0', 'color' => 'bg-sky-50 text-sky-600'],
        ['name' => 'Accessories', 'icon' => 'M7 8a5 5 0 0110 0v4a5 5 0 01-10 0V8zm2 8h6', 'color' => 'bg-violet-50 text-violet-600'],
        ['name' => 'Sports & Outdoors', 'icon' => 'M12 21a9 9 0 100-18 9 9 0 000 18zm-8-9h16M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18', 'color' => 'bg-lime-50 text-lime-700'],
        ['name' => 'Toys & Games', 'icon' => 'M5 12h14M12 5v14M8 8l8 8M16 8l-8 8', 'color' => 'bg-orange-50 text-orange-600'],
        ['name' => 'More', 'icon' => 'M5 12h.01M12 12h.01M19 12h.01', 'color' => 'bg-gray-100 text-gray-600'],
    ];

    $products = [
        ['name' => 'Everyday Canvas Tote', 'price' => '&#8369;799', 'old' => '&#8369;1,299', 'discount' => '38% OFF', 'rating' => '4.8', 'store' => 'Makati Finds', 'image' => 'https://images.unsplash.com/photo-1590874103328-eac38a683ce7?auto=format&fit=crop&w=700&q=80'],
        ['name' => 'Wireless Earbuds Pro', 'price' => '&#8369;1,490', 'old' => '&#8369;2,299', 'discount' => '35% OFF', 'rating' => '4.7', 'store' => 'Gadget Lane PH', 'image' => 'https://images.unsplash.com/photo-1606220588913-b3aacb4d2f46?auto=format&fit=crop&w=700&q=80'],
        ['name' => 'Ceramic Dinner Set', 'price' => '&#8369;949', 'old' => '&#8369;1,499', 'discount' => '37% OFF', 'rating' => '4.9', 'store' => 'Casa Lokal', 'image' => 'https://images.unsplash.com/photo-1610701596007-11502861dcfa?auto=format&fit=crop&w=700&q=80'],
        ['name' => 'Daily Glow Skincare Kit', 'price' => '&#8369;449', 'old' => '&#8369;699', 'discount' => '36% OFF', 'rating' => '4.6', 'store' => 'Glow Manila', 'image' => 'https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=700&q=80'],
        ['name' => 'Premium Coffee Beans', 'price' => '&#8369;520', 'old' => '&#8369;750', 'discount' => '31% OFF', 'rating' => '4.8', 'store' => 'Bean District', 'image' => 'https://images.unsplash.com/photo-1447933601403-0c6688de566e?auto=format&fit=crop&w=700&q=80'],
        ['name' => 'Kids Learning Blocks', 'price' => '&#8369;680', 'old' => '&#8369;999', 'discount' => '32% OFF', 'rating' => '4.7', 'store' => 'Play & Learn Co.', 'image' => 'https://images.unsplash.com/photo-1566576912321-d58ddd7a6088?auto=format&fit=crop&w=700&q=80'],
    ];

    $stores = [
        ['name' => 'Makati Finds', 'category' => 'Fashion & Accessories', 'desc' => 'Curated everyday bags, wallets, and small style essentials.', 'count' => '120+ products'],
        ['name' => 'Casa Lokal', 'category' => 'Home & Living', 'desc' => 'Kitchen, dining, and cozy home picks for modern Filipino homes.', 'count' => '85 products'],
        ['name' => 'Gadget Lane PH', 'category' => 'Mobiles & Gadgets', 'desc' => 'Useful tech accessories, audio gear, and mobile essentials.', 'count' => '150+ products'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="INVOIZ - Life's short. Shop fast. Discover products, local sellers, deals, and delivery through one connected online marketplace.">
    <title>INVOIZ - Life's short. Shop fast.</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        teal: { DEFAULT: '#16697A', dark: '#0E4A57', light: '#EAF4F3' },
                        amber: { DEFAULT: '#F0A202', light: '#FFF8E1' },
                        offwhite: '#F8FAF9',
                        charcoal: '#1F2933',
                    },
                    fontFamily: { sans: ['Segoe UI','system-ui','-apple-system','sans-serif'] },
                    boxShadow: {
                        soft: '0 18px 45px rgba(14, 74, 87, 0.10)',
                        card: '0 10px 25px rgba(31, 41, 51, 0.08)',
                    },
                },
            },
        };
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak]{display:none!important}
        html{scroll-behavior:smooth}
        body{overflow-x:hidden}
        :focus-visible{outline:2px solid #16697A;outline-offset:2px;border-radius:8px}
        .landing-display{font-size:clamp(2.35rem,4.5vw + .65rem,5rem);line-height:.96;letter-spacing:0}
        .landing-section-title{font-size:clamp(1.5rem,1.15rem + 1vw,2.1rem);line-height:1.1;letter-spacing:0}
        .product-image{aspect-ratio:4/3;object-fit:cover}
        .hide-scrollbar{scrollbar-width:none}
        .hide-scrollbar::-webkit-scrollbar{display:none}
        .marketplace-band{background:
            radial-gradient(circle at 12% 18%, rgba(240,162,2,.18), transparent 28%),
            radial-gradient(circle at 85% 12%, rgba(22,105,122,.14), transparent 30%),
            linear-gradient(135deg,#F8FAF9 0%,#ffffff 52%,#EAF4F3 100%)}
        @media (prefers-reduced-motion: reduce) {
            html{scroll-behavior:auto}
            *, *::before, *::after { animation: none !important; transition: none !important; }
        }
    </style>
</head>
<body class="font-sans bg-offwhite text-charcoal antialiased">
    <header x-data="{ open:false }" class="sticky top-0 z-50 border-b border-gray-200 bg-white/95 backdrop-blur">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex min-h-[72px] items-center gap-3">
                <a href="#home" class="flex shrink-0 items-center gap-2.5 rounded-xl" aria-label="INVOIZ home">
                    <img src="{{ asset('images/logo.png') }}" alt="INVOIZ logo" class="h-10 w-10 rounded-xl object-cover ring-1 ring-gray-200">
                    <span class="text-xl font-extrabold tracking-tight text-teal-dark">INVOIZ</span>
                </a>

                <form action="{{ $marketplaceUrl ?: '#deals' }}" method="GET" role="search" class="hidden min-w-0 flex-1 md:flex">
                    <label for="site-search" class="sr-only">Search products and stores</label>
                    <div class="flex min-h-[46px] w-full items-center overflow-hidden rounded-2xl border-2 border-teal/20 bg-white shadow-sm focus-within:border-teal">
                        <span class="flex h-full items-center px-4 text-teal" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                        </span>
                        <input id="site-search" name="q" type="search" placeholder="Search for products, stores, and more..." class="min-w-0 flex-1 border-0 bg-transparent py-3 pr-3 text-sm outline-none placeholder:text-gray-400 focus:ring-0">
                        <button type="submit" class="m-1 inline-flex min-h-[38px] items-center justify-center rounded-xl bg-teal px-5 text-sm font-bold text-white transition hover:bg-teal-dark">
                            Search
                        </button>
                    </div>
                </form>

                <nav aria-label="Account navigation" class="ml-auto hidden shrink-0 items-center gap-2 lg:flex">
                    <a href="#deals" class="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-xl border border-gray-200 bg-white text-teal transition hover:border-teal/30 hover:bg-teal-light" aria-label="Cart">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l2.2 10.5a2 2 0 002 1.6h6.8a2 2 0 001.9-1.4L20 7H6m4 13a1 1 0 100-2 1 1 0 000 2zm7 0a1 1 0 100-2 1 1 0 000 2z"/></svg>
                    </a>
                    <a href="{{ route('login') }}" class="inline-flex min-h-[44px] items-center justify-center rounded-xl border border-gray-200 bg-white px-4 text-sm font-bold text-gray-700 transition hover:bg-gray-50">Login</a>
                    <a href="{{ route('register') }}" class="inline-flex min-h-[44px] items-center justify-center rounded-xl bg-amber px-4 text-sm font-extrabold text-charcoal shadow-sm transition hover:brightness-95">Sign Up</a>
                </nav>

                <button @click="open=!open" class="ml-auto inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-xl border border-gray-200 text-gray-700 lg:hidden" aria-label="Toggle menu" aria-controls="mobile-menu" :aria-expanded="open.toString()">
                    <svg x-show="!open" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    <svg x-show="open" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="pb-3 md:hidden">
                <form action="{{ $marketplaceUrl ?: '#deals' }}" method="GET" role="search">
                    <label for="mobile-search" class="sr-only">Search products and stores</label>
                    <div class="flex min-h-[46px] items-center rounded-2xl border border-teal/20 bg-white px-3 shadow-sm">
                        <svg class="h-5 w-5 shrink-0 text-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                        <input id="mobile-search" name="q" type="search" placeholder="Search products, stores..." class="min-w-0 flex-1 border-0 bg-transparent px-3 py-3 text-sm outline-none focus:ring-0">
                        <a href="#deals" aria-label="Cart" class="inline-flex min-h-[38px] min-w-[38px] items-center justify-center rounded-xl bg-teal-light text-teal">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l2.2 10.5a2 2 0 002 1.6h6.8a2 2 0 001.9-1.4L20 7H6"/></svg>
                        </a>
                    </div>
                </form>
            </div>

            <div class="hidden border-t border-gray-100 py-2 lg:block">
                <nav aria-label="Marketplace navigation" class="flex items-center gap-1 text-sm font-semibold text-gray-600">
                    <a href="#home" class="rounded-lg px-3 py-2 hover:bg-gray-50 hover:text-teal-dark">Home</a>
                    <a href="#categories" class="rounded-lg px-3 py-2 hover:bg-gray-50 hover:text-teal-dark">Categories</a>
                    <a href="#deals" class="rounded-lg px-3 py-2 hover:bg-gray-50 hover:text-teal-dark">Deals</a>
                    <a href="#stores" class="rounded-lg px-3 py-2 hover:bg-gray-50 hover:text-teal-dark">Stores</a>
                    <a href="#how-it-works" class="rounded-lg px-3 py-2 hover:bg-gray-50 hover:text-teal-dark">How It Works</a>
                    <a href="#help" class="rounded-lg px-3 py-2 hover:bg-gray-50 hover:text-teal-dark">Help &amp; Support</a>
                    @if($marketplaceUrl)
                        <a href="{{ $marketplaceUrl }}" class="ml-auto rounded-lg px-3 py-2 text-teal-dark hover:bg-teal-light">Browse Marketplace</a>
                    @else
                        <span class="ml-auto rounded-full bg-amber-light px-3 py-1 text-xs font-bold text-amber-700">Public marketplace browsing - Coming soon</span>
                    @endif
                </nav>
            </div>

            <div id="mobile-menu" x-show="open" x-cloak x-transition class="lg:hidden border-t border-gray-100 py-3">
                <nav aria-label="Mobile navigation" class="grid gap-1 text-sm font-semibold">
                    <a href="#home" @click="open=false" class="rounded-lg px-3 py-2 hover:bg-gray-50">Home</a>
                    <a href="#categories" @click="open=false" class="rounded-lg px-3 py-2 hover:bg-gray-50">Categories</a>
                    <a href="#deals" @click="open=false" class="rounded-lg px-3 py-2 hover:bg-gray-50">Deals</a>
                    <a href="#stores" @click="open=false" class="rounded-lg px-3 py-2 hover:bg-gray-50">Stores</a>
                    <a href="#how-it-works" @click="open=false" class="rounded-lg px-3 py-2 hover:bg-gray-50">How It Works</a>
                    <a href="#help" @click="open=false" class="rounded-lg px-3 py-2 hover:bg-gray-50">Help &amp; Support</a>
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <a href="{{ route('login') }}" class="inline-flex min-h-[44px] items-center justify-center rounded-xl border border-gray-200 bg-white px-4 font-bold">Login</a>
                        <a href="{{ route('register') }}" class="inline-flex min-h-[44px] items-center justify-center rounded-xl bg-amber px-4 font-extrabold text-charcoal">Sign Up</a>
                    </div>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <section id="home" class="marketplace-band overflow-hidden">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 lg:py-14">
                <div class="grid items-center gap-8 lg:grid-cols-[1.02fr_.98fr]">
                    <div>
                        <p class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1 text-xs font-extrabold text-teal-dark shadow-sm ring-1 ring-teal/10">
                            <span class="h-2 w-2 rounded-full bg-amber"></span>
                            INVOIZ marketplace
                        </p>
                        <h1 class="landing-display mt-4 font-extrabold text-charcoal">
                            Life's short.<br><span class="text-teal">Shop fast.</span>
                        </h1>
                        <p class="mt-4 text-lg font-extrabold text-teal-dark">Shop local. Shop smart. Shop INVOIZ.</p>
                        <p class="mt-3 max-w-xl text-sm leading-relaxed text-gray-600 sm:text-base">
                            Discover great products from local sellers, enjoy exclusive deals, and get your orders delivered to your doorstep.
                        </p>
                        <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                            <a href="{{ $shopHref }}" class="inline-flex min-h-[48px] items-center justify-center rounded-2xl bg-amber px-6 text-sm font-extrabold text-charcoal shadow-card transition hover:brightness-95">Shop Now</a>
                            <a href="{{ $categoriesHref }}" class="inline-flex min-h-[48px] items-center justify-center rounded-2xl border border-teal/20 bg-white px-6 text-sm font-extrabold text-teal-dark shadow-sm transition hover:bg-teal-light">Explore Products</a>
                        </div>
                        <div class="mt-6 grid max-w-lg grid-cols-3 gap-3">
                            <div class="rounded-2xl bg-white p-3 text-center shadow-sm"><p class="text-lg font-extrabold text-teal-dark">500+</p><p class="text-[11px] font-semibold text-gray-500">Local picks</p></div>
                            <div class="rounded-2xl bg-white p-3 text-center shadow-sm"><p class="text-lg font-extrabold text-teal-dark">24h</p><p class="text-[11px] font-semibold text-gray-500">Deal drops</p></div>
                            <div class="rounded-2xl bg-white p-3 text-center shadow-sm"><p class="text-lg font-extrabold text-teal-dark">Fast</p><p class="text-[11px] font-semibold text-gray-500">Delivery flow</p></div>
                        </div>
                    </div>

                    <div class="relative">
                        <div class="rounded-[2rem] border border-white bg-white p-4 shadow-soft">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="overflow-hidden rounded-3xl bg-gray-100">
                                    <img src="https://images.unsplash.com/photo-1607082349566-187342175e2f?auto=format&fit=crop&w=900&q=80" alt="Shopping bags and online marketplace products" class="h-64 w-full object-cover sm:h-full">
                                </div>
                                <div class="grid gap-4">
                                    <div class="rounded-3xl bg-teal p-5 text-white">
                                        <p class="text-xs font-bold uppercase tracking-wider text-white/70">Flash Deal</p>
                                        <p class="mt-2 text-3xl font-extrabold">Up to 40% off</p>
                                        <p class="mt-2 text-sm text-white/80">Daily finds from local sellers.</p>
                                    </div>
                                    <div class="rounded-3xl bg-amber-light p-5">
                                        <p class="text-xs font-bold uppercase tracking-wider text-amber-700">Doorstep delivery</p>
                                        <p class="mt-2 text-lg font-extrabold text-charcoal">Packed, picked up, sorted, delivered.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="absolute -bottom-8 left-4 right-4 rounded-2xl bg-white px-4 py-3 shadow-card ring-1 ring-gray-100 sm:left-auto sm:right-8 sm:w-72">
                            <div class="flex items-center gap-3">
                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-teal-light text-teal">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4-8-4m16 0v10l-8 4-8-4V7"/></svg>
                                </span>
                                <div><p class="text-sm font-extrabold text-charcoal">Order ready for pickup</p><p class="text-xs text-gray-500">Seller to rider to buyer</p></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="categories" class="bg-white py-10 border-y border-gray-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-end justify-between gap-4">
                    <div><h2 class="landing-section-title font-extrabold">Shop by Category</h2><p class="mt-1 text-sm text-gray-600">Quick paths to the things shoppers look for most.</p></div>
                    <a href="{{ $categoriesHref }}" class="hidden text-sm font-bold text-teal hover:text-teal-dark sm:inline-flex">View all</a>
                </div>
                <div class="mt-6 grid grid-flow-col auto-cols-[8.5rem] gap-3 overflow-x-auto pb-2 hide-scrollbar sm:grid-flow-row sm:grid-cols-5 lg:grid-cols-5">
                    @foreach($categories as $category)
                        <a href="{{ $categoriesHref }}" class="group rounded-2xl border border-gray-100 bg-offwhite p-4 text-center shadow-sm transition hover:-translate-y-0.5 hover:shadow-card">
                            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full {{ $category['color'] }}">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $category['icon'] }}"/></svg>
                            </span>
                            <span class="mt-3 block text-sm font-extrabold text-gray-800">{{ $category['name'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="deals" class="py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div><h2 class="landing-section-title font-extrabold">Today's Deals</h2><p class="mt-1 text-sm text-gray-600">Limited-time offers from local sellers.</p></div>
                    <div class="inline-flex w-fit items-center gap-2 rounded-full bg-amber-light px-3 py-1.5 text-xs font-extrabold text-amber-700"><span class="h-2 w-2 rounded-full bg-amber"></span>Demo marketplace content</div>
                </div>
                <div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
                    @foreach($products as $product)
                        <article class="group overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-card">
                            <div class="relative">
                                <img src="{{ $product['image'] }}" alt="{{ $product['name'] }}" class="product-image w-full bg-gray-100">
                                <span class="absolute left-2 top-2 rounded-full bg-amber px-2 py-1 text-[11px] font-extrabold text-charcoal">{{ $product['discount'] }}</span>
                                <button type="button" class="absolute right-2 top-2 inline-flex h-9 w-9 items-center justify-center rounded-full bg-white/95 text-gray-500 shadow-sm transition hover:text-rose-500" aria-label="Add {{ $product['name'] }} to wishlist">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.3 6.3a4.5 4.5 0 016.4 0L12 7.6l1.3-1.3a4.5 4.5 0 116.4 6.4L12 20.4l-7.7-7.7a4.5 4.5 0 010-6.4z"/></svg>
                                </button>
                            </div>
                            <div class="p-3">
                                <h3 class="line-clamp-2 min-h-[2.5rem] text-sm font-bold text-gray-900">{{ $product['name'] }}</h3>
                                <div class="mt-2 flex items-baseline gap-2"><p class="text-base font-extrabold text-teal-dark">{!! $product['price'] !!}</p><p class="text-xs text-gray-400 line-through">{!! $product['old'] !!}</p></div>
                                <div class="mt-2 flex items-center justify-between gap-2 text-xs">
                                    <span class="inline-flex items-center gap-1 font-bold text-amber-700"><svg class="h-3.5 w-3.5 fill-current" viewBox="0 0 20 20"><path d="M10 1.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L10 14.8l-5.2 2.8 1-5.8-4.3-4.1 5.9-.9L10 1.5z"/></svg>{{ $product['rating'] }}</span>
                                    <span class="truncate text-gray-500">{{ $product['store'] }}</span>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
                @if(! $marketplaceUrl)
                    <p class="mt-4 text-xs text-gray-500">Product cards are presentational previews. Public shopping links can point to the configured marketplace URL when it is available.</p>
                @endif
            </div>
        </section>

        <section id="benefits" class="bg-white py-8 border-y border-gray-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach([
                        ['Secure Transactions','Protected account flow and role-based access.','M9 12l2 2 4-4m5-2a9 9 0 11-18 0 9 9 0 0118 0z'],
                        ['Fast & Reliable Delivery','Rider and logistics workflows support the last mile.','M13 10V3L4 14h7v7l9-11h-7z'],
                        ['Local Sellers','Discover local businesses and neighborhood stores.','M3 7h18M5 7l1 14h12l1-14M9 7V5a3 3 0 016 0v2'],
                        ['Deals & Vouchers','Promotions and discount highlights for shoppers.','M12 8v8m-4-4h8M4 4h16v16H4V4z'],
                    ] as $benefit)
                        <div class="flex items-center gap-3 rounded-2xl bg-offwhite p-4">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-teal-light text-teal"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $benefit[2] }}"/></svg></span>
                            <div><h3 class="text-sm font-extrabold">{{ $benefit[0] }}</h3><p class="text-xs leading-relaxed text-gray-500">{{ $benefit[1] }}</p></div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="stores" class="py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div><h2 class="landing-section-title font-extrabold">Discover Local Stores</h2><p class="mt-1 text-sm text-gray-600">INVOIZ helps shoppers discover local businesses.</p></div>
                    <a href="{{ $storesHref }}" class="text-sm font-bold text-teal hover:text-teal-dark">Explore stores</a>
                </div>
                <div class="mt-6 grid gap-4 md:grid-cols-3">
                    @foreach($stores as $store)
                        <article class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-card">
                            <div class="flex items-center gap-3">
                                <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-teal text-lg font-extrabold text-white">{{ substr($store['name'], 0, 1) }}</span>
                                <div class="min-w-0"><h3 class="truncate text-base font-extrabold">{{ $store['name'] }}</h3><p class="truncate text-xs font-bold text-amber-700">{{ $store['category'] }}</p></div>
                            </div>
                            <p class="mt-4 text-sm leading-relaxed text-gray-600">{{ $store['desc'] }}</p>
                            <p class="mt-4 inline-flex rounded-full bg-teal-light px-3 py-1 text-xs font-extrabold text-teal-dark">{{ $store['count'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="seller" class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid gap-5 overflow-hidden rounded-3xl bg-white p-6 shadow-soft ring-1 ring-gray-100 lg:grid-cols-[1fr_auto] lg:items-center">
                    <div>
                        <p class="text-xs font-extrabold uppercase tracking-wider text-amber-700">Seller opportunity</p>
                        <h2 class="mt-2 text-2xl font-extrabold text-charcoal">Have something to sell?</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-gray-600">Grow your business and reach more customers through INVOIZ. Seller tools remain part of the wider marketplace integration, not this Logistics backend.</p>
                    </div>
                    <a href="{{ $sellerUrl ?: route('register') }}" class="inline-flex min-h-[48px] items-center justify-center rounded-2xl bg-teal px-6 text-sm font-extrabold text-white transition hover:bg-teal-dark">Become an INVOIZ Seller</a>
                </div>
            </div>
        </section>

        <section id="rider-app" class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div x-data="{ tried:false, fallback:false }" class="grid gap-5 overflow-hidden rounded-3xl bg-teal p-6 text-white shadow-soft lg:grid-cols-[1fr_auto] lg:items-center">
                    <div>
                        <p class="text-xs font-extrabold uppercase tracking-wider text-white/70">Rider community</p>
                        <h2 class="mt-2 text-2xl font-extrabold">Want to earn with INVOIZ?</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-white/80">Deliver orders, serve local customers, and be part of the INVOIZ delivery community.</p>
                        <p class="mt-2 text-xs font-mono text-white/75">{{ $riderDeeplink }}</p>
                        <p x-show="fallback" x-cloak class="mt-3 rounded-xl bg-amber-light px-3 py-2 text-xs font-semibold text-amber-800">App did not open. Please ensure the Rider App is installed. You can also use web login as fallback.</p>
                        <p x-show="tried && !fallback" class="mt-3 text-xs text-white/70">Attempting to open app...</p>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-1">
                        <button @click="tried=true; fallback=false; window.location.href='{{ $riderDeeplink }}'; setTimeout(()=>{ if(!document.hidden) fallback=true; }, 1200)" class="inline-flex min-h-[48px] items-center justify-center rounded-2xl bg-white px-6 text-sm font-extrabold text-teal-dark transition hover:bg-gray-50">Try Opening Rider App</button>
                        <a href="{{ route('login') }}" class="inline-flex min-h-[48px] items-center justify-center rounded-2xl border border-white/25 px-6 text-sm font-extrabold text-white transition hover:bg-white/10">Access Platform via Web Login</a>
                    </div>
                </div>
            </div>
        </section>

        <section id="logistics" class="py-12 bg-white border-y border-gray-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid gap-8 lg:grid-cols-[.9fr_1.1fr] lg:items-center">
                    <div>
                        <p class="text-xs font-extrabold uppercase tracking-wider text-teal">Powered by INVOIZ Logistics</p>
                        <h2 class="mt-2 landing-section-title font-extrabold">The delivery engine behind every order.</h2>
                        <p class="mt-3 text-sm leading-relaxed text-gray-600">Behind every order is a connected logistics system that helps track, sort, assign, and deliver parcels. Shoppers do not need to pick a logistics portal; it works behind the buying experience.</p>
                        <a href="{{ route('login') }}" class="mt-5 inline-flex min-h-[46px] items-center justify-center rounded-2xl border border-teal/20 bg-white px-5 text-sm font-extrabold text-teal-dark transition hover:bg-teal-light">Learn More</a>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        @foreach([['Scan','Parcels are verified.'],['Sort','Centers prepare routes.'],['Assign','Riders receive deliveries.']] as $item)
                            <div class="rounded-3xl bg-offwhite p-5 text-center">
                                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-teal shadow-sm"><svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7V4h3M17 4h3v3M4 17v3h3M20 17v3h-3M8 12h8"/></svg></div>
                                <h3 class="mt-3 font-extrabold">{{ $item[0] }}</h3><p class="mt-1 text-sm text-gray-600">{{ $item[1] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <section id="how-it-works" class="py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="max-w-2xl"><h2 class="landing-section-title font-extrabold">How INVOIZ Works</h2><p class="mt-2 text-sm text-gray-600">A familiar marketplace flow, from product discovery to delivery confirmation.</p></div>
                <div class="mt-7 grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                    @foreach([
                        ['1','Buyer discovers a product'],
                        ['2','Buyer places an order'],
                        ['3','Seller prepares the order'],
                        ['4','Rider picks up the parcel'],
                        ['5','Logistics manages sorting and assignment'],
                        ['6','Rider delivers to the buyer'],
                    ] as $step)
                        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-teal text-sm font-extrabold text-white">{{ $step[0] }}</span><p class="mt-3 text-sm font-extrabold text-gray-900">{{ $step[1] }}</p></div>
                    @endforeach
                </div>
                <div class="mt-8 rounded-3xl bg-white p-6 text-center shadow-sm ring-1 ring-gray-100">
                    <p class="text-xs font-extrabold uppercase tracking-wider text-gray-500">How the system connects</p>
                    <p class="mt-2 text-lg font-extrabold text-charcoal">Buyer, seller, rider, and logistics work together around one shopping journey.</p>
                </div>
            </div>
        </section>

        <section id="ecosystem" class="bg-white py-12 border-y border-gray-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="rounded-3xl bg-gradient-to-br from-teal via-teal-dark to-[#0B3B46] p-7 text-white shadow-soft">
                    <div class="grid gap-6 lg:grid-cols-[1fr_auto] lg:items-center">
                        <div>
                            <p class="text-xs font-extrabold uppercase tracking-wider text-white/65">More ways to be part of INVOIZ</p>
                            <h2 class="mt-2 text-2xl font-extrabold">Shop with INVOIZ, grow your business, or earn by delivering.</h2>
                            <p class="mt-2 max-w-2xl text-sm text-white/75">The buyer experience comes first, with seller and rider opportunities introduced naturally as part of the marketplace ecosystem.</p>
                            <p class="sr-only">What is INVOIZ? One Connected Ecosystem Ready to experience INVOIZ?</p>
                        </div>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <a href="{{ $sellerUrl ?: route('register') }}" class="inline-flex min-h-[48px] items-center justify-center rounded-2xl bg-white px-5 text-sm font-extrabold text-teal-dark transition hover:bg-gray-50">Become a Seller</a>
                            <button type="button" onclick="window.location.href='{{ $riderDeeplink }}'" class="inline-flex min-h-[48px] items-center justify-center rounded-2xl border border-white/25 px-5 text-sm font-extrabold text-white transition hover:bg-white/10">Become a Rider</button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer id="help" class="bg-charcoal text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
            <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <div class="flex items-center gap-2"><img src="{{ asset('images/logo.png') }}" alt="INVOIZ" class="h-8 w-8 rounded-lg object-cover"><span class="text-lg font-extrabold">INVOIZ</span></div>
                    <p class="mt-3 text-sm text-white/70">Life's short. Shop fast.</p>
                    <p class="mt-2 text-xs text-white/50">Built for the Philippine marketplace and delivery ecosystem.</p>
                </div>
                <div>
                    <h3 class="text-sm font-extrabold">Shop</h3>
                    <ul class="mt-3 space-y-2 text-sm text-white/65">
                        <li><a href="#categories" class="hover:text-white">Categories</a></li>
                        <li><a href="#deals" class="hover:text-white">Deals</a></li>
                        <li><a href="#stores" class="hover:text-white">Stores</a></li>
                        <li><a href="{{ $shopHref }}" class="hover:text-white">{{ $marketplaceUrl ? 'Browse Marketplace' : 'Public marketplace browsing - Coming soon' }}</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-sm font-extrabold">Ecosystem</h3>
                    <ul class="mt-3 space-y-2 text-sm text-white/65">
                        <li><a href="#seller" class="hover:text-white">Become a Seller</a></li>
                        <li><a href="#rider-app" class="hover:text-white">Become a Rider</a></li>
                        <li><a href="#logistics" class="hover:text-white">INVOIZ Logistics</a></li>
                        <li><span>Rider App: {{ $riderDeeplink }}</span></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-sm font-extrabold">Help &amp; Support</h3>
                    <ul class="mt-3 space-y-2 text-sm text-white/65">
                        <li><a href="{{ route('login') }}" class="hover:text-white">Login</a></li>
                        <li><a href="{{ route('register') }}" class="hover:text-white">Sign Up</a></li>
                        <li><a href="#how-it-works" class="hover:text-white">How It Works</a></li>
                    </ul>
                </div>
            </div>
            <div class="mt-8 border-t border-white/10 pt-6 text-xs text-white/50 sm:flex sm:items-center sm:justify-between">
                <p>&copy; {{ date('Y') }} INVOIZ. All rights reserved.</p>
                <p class="mt-2 sm:mt-0">No Buyer/Seller backend is created in this Logistics project.</p>
            </div>
        </div>
    </footer>
</body>
</html>
