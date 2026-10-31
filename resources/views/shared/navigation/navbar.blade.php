{{-- Shared navbar. Role sections retain the active layouts' original markup and active-state rules. --}}

{{-- ==================== BUYER ==================== --}}
@if($navigationRole === 'buyer')
@if(($navigationVariant ?? '') === 'website')
<header class="topbar buyerbar">
    @if(!$hideSidebar)<button id="buyerMenuBtn" class="hamburger" title="Toggle menu" aria-label="Toggle menu" aria-expanded="true">&#9776;</button>@endif
    <a href="{{ url('/shop') }}" class="brand">
      <div class="brand-icon"><img src="{{ asset('storage/logos/logo.png') }}" onerror="this.onerror=null;this.src='{{ asset('assets/shared/images/logo.png') }}'" alt="Invoiz" style="width:100%;height:100%;object-fit:cover;"></div>
      <span class="brand-text">INVOIZ</span>
      <span class="brand-sub">ONLINE SHOPPING</span>
    </a>
    <form method="GET" action="{{ url('/shop') }}" class="search">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--text3)" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      <input type="text" name="search" value="{{ request('search') }}" placeholder="Search products...">
      <button type="submit" class="search-btn">Search</button>
    </form>
    
    <a href="{{ url('/cart') }}" class="topbar-link" style="position:relative">
      <span style="position:relative;display:inline-flex;line-height:0">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
        @if($cartCount > 0)<span class="badge">{{ $cartBadge }}</span>@endif
      </span>
      Cart
    </a>
    <a href="{{ url('/orders') }}" class="topbar-link">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      Orders
    </a>
    @if($buyer)
      <div style="position:relative">
        <button onclick="document.getElementById('profileDrop').classList.toggle('open')" class="profile-btn">
          <img src="{{ $buyerAvatar ?: 'https://ui-avatars.com/api/?name='.urlencode($buyer['first_name'].' '.$buyer['last_name']).'&background=134e4a&color=fff&size=28' }}" alt="" class="profile-avatar">
          <span style="font-weight:600;font-size:12px">{{ $buyer['first_name'] }}</span>
          <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
        <div id="profileDrop">
          <div style="display:flex;gap:10px;align-items:center;padding-bottom:12px;border-bottom:1px solid var(--border-light)">
            <img src="{{ $buyerAvatar ?: 'https://ui-avatars.com/api/?name='.urlencode($buyer['first_name'].' '.$buyer['last_name']).'&background=134e4a&color=fff&size=48' }}" style="width:44px;height:44px;border-radius:50%">
            <div>
              <div style="font-weight:700;font-size:13px">{{ $buyer['first_name'] }} {{ $buyer['last_name'] }}</div>
              <div style="font-size:11px;color:var(--text3)">{{ $buyer['email'] }}</div>
              <div style="font-size:10px;color:var(--success);font-weight:700;margin-top:2px">● Buyer</div>
            </div>
          </div>
          <div style="padding:10px 0">
            <a href="{{ url('/profile') }}" style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;text-decoration:none;color:var(--text);font-weight:600;font-size:12px" onmouseover="this.style.background='#f5f5f5'" onmouseout="this.style.background='transparent'">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              My Profile
            </a>
            <a href="{{ url('/orders') }}" style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;text-decoration:none;color:var(--text);font-weight:600;font-size:12px" onmouseover="this.style.background='#f5f5f5'" onmouseout="this.style.background='transparent'">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              My Orders
            </a>
            <a href="{{ url('/logout') }}" style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;text-decoration:none;color:var(--danger);font-weight:600;font-size:12px;margin-top:4px;border-top:1px solid var(--border-light)" onmouseover="this.style.background='#fef2f2'" onmouseout="this.style.background='transparent'">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
              Logout
            </a>
          </div>
        </div>
      </div>
    @else
      <a href="{{ url('/login') }}" class="topbar-link btn-login">Login</a>
      <a href="{{ url('/register') }}" class="topbar-link btn-register">Register</a>
    @endif
  </header>
@elseif(($navigationVariant ?? '') === 'landing')
<header x-data="{open:false}" class="sticky top-0 z-50">
<div id="navbar" style="min-height:72px;background:rgba(251,249,246,.85);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);border-bottom:1px solid rgba(15,76,92,.12);padding-top:12px;padding-bottom:12px">
<div class="mx-auto px-4 sm:px-6 lg:px-8" style="max-width:1200px">
<div class="flex items-center gap-3" style="min-height:inherit">
<a href="{{ url('/') }}" class="flex shrink-0 items-center gap-2.5">
<img src="{{ asset('assets/shared/images/logo.png') }}" alt="INVOIZ logo" class="h-10 w-10 rounded-xl object-cover">
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
@endif

{{-- ==================== SELLER ==================== --}}
@elseif($navigationRole === 'seller')
@if(($navigationVariant ?? '') === 'center')
<header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-borderline bg-white px-4 lg:px-6 no-print">
            <button class="rounded-lg p-2 text-ink-light hover:bg-soft"
                    title="Toggle sidebar"
                    @click="window.innerWidth < 1024 ? (sidebarOpen = !sidebarOpen) : (sidebarCollapsed = !sidebarCollapsed); localStorage.setItem('sellerSidebarCollapsed', sidebarCollapsed ? '1' : '0')">
                <x-icon name="menu" class="h-5 w-5" />
            </button>

            <h1 class="truncate text-base font-bold tracking-tight">{{ $title ?? 'Dashboard' }}</h1>

            <div class="ml-auto flex items-center gap-2">
                {{-- Notifications --}}
                <div class="relative">
                    <button @click="notifyOpen = !notifyOpen; userOpen = false"
                            class="relative rounded-lg p-2 text-ink-light hover:bg-soft">
                        <x-icon name="bell" class="h-5 w-5" />
                        @if($layoutUnreadCount > 0)
                            <span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-warnc px-1 text-[9px] font-bold text-white">
                                {{ $layoutUnreadCount > 9 ? '9+' : $layoutUnreadCount }}
                            </span>
                        @endif
                    </button>

                    <div x-show="notifyOpen" @click.outside="notifyOpen = false" x-cloak x-transition
                         class="absolute right-0 mt-2 w-80 overflow-hidden rounded-2xl border border-borderline bg-white shadow-xl">
                        <div class="flex items-center justify-between border-b border-borderline px-4 py-3">
                            <span class="text-sm font-bold">Notifications</span>
                            @if($layoutUnreadCount > 0)
                                <form method="POST" action="{{ route('seller.notifications.read-all') }}">
                                    @csrf
                                    <button class="text-xs font-semibold text-primary hover:underline">Mark all read</button>
                                </form>
                            @endif
                        </div>
                        <div class="max-h-96 overflow-y-auto">
                            @forelse($layoutNotifications as $notification)
                                <a href="{{ route('seller.notifications.read', $notification->id) }}"
                                   class="flex gap-3 border-b border-borderline px-4 py-3 hover:bg-basebg">
                                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $notification->is_read ? 'bg-borderline' : 'bg-secondary' }}"></span>
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-semibold">{{ $notification->title }}</span>
                                        <span class="block truncate text-xs text-ink-light">{{ $notification->body }}</span>
                                        <span class="mt-0.5 block text-[11px] text-ink-light">{{ $notification->created_at->diffForHumans() }}</span>
                                    </span>
                                </a>
                            @empty
                                <div class="px-4 py-10 text-center text-sm text-ink-light">You're all caught up.</div>
                            @endforelse
                        </div>
                        <a href="{{ route('seller.notifications.index') }}"
                           class="block bg-basebg px-4 py-2.5 text-center text-xs font-bold text-primary hover:underline">
                            View all notifications
                        </a>
                    </div>
                </div>

                {{-- User --}}
                <div class="relative">
                    <button @click="userOpen = !userOpen; notifyOpen = false"
                            class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-soft">
                        <span class="avatar flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold text-white">
                            {{ strtoupper(substr(auth()->user()->first_name, 0, 1) . substr(auth()->user()->last_name, 0, 1)) }}
                        </span>
                        <span class="hidden text-left md:block">
                            <span class="block text-xs font-bold leading-tight">{{ auth()->user()->first_name }}</span>
                            <span class="block text-[10px] leading-tight text-ink-light">Seller</span>
                        </span>
                        <x-icon name="chevron-down" class="hidden h-3.5 w-3.5 text-ink-light md:block" />
                    </button>

                    <div x-show="userOpen" @click.outside="userOpen = false" x-cloak x-transition
                         class="absolute right-0 mt-2 w-56 overflow-hidden rounded-2xl border border-borderline bg-white py-1.5 shadow-xl">
                        <div class="border-b border-borderline px-4 py-3">
                            <div class="text-sm font-bold">{{ auth()->user()->full_name }}</div>
                            <div class="truncate text-xs text-ink-light">{{ auth()->user()->email }}</div>
                        </div>
                        <a href="{{ route('seller.account') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm hover:bg-basebg">
                            <x-icon name="user" class="h-4 w-4 text-ink-light" /> My Account
                        </a>
                        <a href="{{ route('seller.notifications.index') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm hover:bg-basebg">
                            <x-icon name="bell" class="h-4 w-4 text-ink-light" /> Notifications
                        </a>
                        <form method="POST" action="{{ route('seller.logout') }}" class="border-t border-borderline pt-1">
                            @csrf
                            <button class="flex w-full items-center gap-2.5 px-4 py-2.5 text-sm text-warnc hover:bg-red-50">
                                <x-icon name="logout" class="h-4 w-4" /> Logout
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>
@endif

{{-- ==================== LOGISTICS ==================== --}}
@elseif($navigationRole === 'logistics')
@if(($navigationVariant ?? '') === 'workspace')
<header class="sticky top-0 z-20 bg-white/90 backdrop-blur border-b border-gray-200">
                    <div class="flex items-center justify-between px-4 sm:px-6 h-16">
                        <div class="flex items-center gap-3">
                            <button type="button" class="ld-mobile-menu" @click="toggleSidebar()" :aria-expanded="sidebarVisible.toString()" aria-label="Toggle logistics menu" aria-controls="app-sidebar">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                            </button>
                            <div class="flex items-center gap-2">
                                <span class="text-lg font-bold text-gray-900">Logistics</span>
                                @php
                                    $navUser = Auth::user();
                                    $navCenter = null;
                                    $navCompany = null;
                                    try {
                                        if ($navUser && $navUser->center_id) {
                                            $navCenter = $navUser->logisticsCenter;
                                        }
                                        if ($navUser && $navUser->logistics_company_id) {
                                            $navCompany = $navUser->logisticsCompany;
                                        }
                                    } catch (\Throwable $e) {}
                                @endphp
                                @if($navCenter)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-[#E6F4F1] text-[#0E4A57] border border-[#16697A]/20" title="{{ $navCenter->name }} ({{ $navCenter->code }})">
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#16697A]"></span>
                                        <span class="max-w-[220px] truncate">{{ $navCenter->name }}</span>
                                    </span>
                                @elseif($navCompany)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-[#E6F4F1] text-[#0E4A57] border border-[#16697A]/20" title="{{ $navCompany->name }}">
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#16697A]"></span>
                                        <span class="max-w-[220px] truncate">{{ $navCompany->name }}</span>
                                    </span>
                                @elseif($navUser && in_array($navUser->role, ['admin', 'logistics_admin'], true))
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700 border border-gray-200" title="HQ Administrator - All Logistics Centers">
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-500"></span>
                                        <span>HQ Admin · All Centers</span>
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center gap-1 sm:gap-2">
                            <!-- Notification Bell -->
                            <div class="relative">
                                <button @click="notifOpen = !notifOpen" @click.outside="notifOpen = false" class="relative text-gray-500 hover:text-gray-700 p-2 rounded-lg hover:bg-gray-100">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                    </svg>
                                    <span x-show="unreadCount > 0" x-text="unreadCount" class="absolute -top-0.5 -right-0.5 h-4.5 w-4.5 bg-secondary text-white text-[10px] font-bold rounded-full flex items-center justify-center min-w-[18px] h-[18px]"></span>
                                </button>

                                <!-- Notification Dropdown -->
                                <div x-show="notifOpen" x-transition x-cloak
                                     class="absolute right-0 mt-2 w-80 sm:w-96 bg-white rounded-xl shadow-lg ring-1 ring-gray-200 z-50 max-h-[70vh] flex flex-col">
                                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                                        <h3 class="text-sm font-semibold text-gray-900">Notifications</h3>
                                        <button @click="
                                            fetch('{{ route('notifications.mark-all-read', [], false) }}', { method: 'PATCH', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' } })
                                                .then(r => r.json())
                                                .then(d => { unreadCount = d.unread_count; notifications.forEach(n => n.is_read = true); })
                                        " x-show="unreadCount > 0" class="text-xs font-medium text-teal hover:text-teal-dark">Mark all read</button>
                                    </div>

                                    <div class="overflow-y-auto flex-1 divide-y divide-gray-50">
                                        <template x-if="notificationsError">
                                            <div class="px-4 py-6 text-sm text-gray-700">
                                                <p>Notifications could not load.</p>
                                                <a href="{{ route('notifications.index') }}" class="mt-2 inline-block font-semibold text-teal underline">Open notifications</a>
                                            </div>
                                        </template>
                                        <template x-if="!notificationsError && notifications.length === 0">
                                            <div class="px-4 py-8 text-center">
                                                <p class="text-sm text-gray-400">No notifications yet.</p>
                                            </div>
                                        </template>
                                        <template x-for="n in notifications" :key="n.id">
                                            <div @click="
                                                if (!n.is_read) {
                                                    fetch('/logistics/notifications/' + n.id + '/read', { method: 'PATCH', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' } })
                                                        .then(r => r.json())
                                                        .then(d => { unreadCount = d.unread_count; n.is_read = true; });
                                                }
                                                if (n.link) window.location.href = n.link;
                                            " class="flex items-start gap-3 px-4 py-3 cursor-pointer transition-colors"
                                               :class="n.is_read ? 'bg-white hover:bg-gray-50' : 'bg-teal-light/30 hover:bg-teal-light/50'">
                                                <span class="text-lg flex-shrink-0 mt-0.5" x-text="n.icon"></span>
                                                <div class="min-w-0 flex-1">
                                                    <p class="text-sm font-medium text-gray-900" x-text="n.title"></p>
                                                    <p class="text-xs text-gray-500 mt-0.5 line-clamp-2" x-text="n.message"></p>
                                                    <p class="text-[11px] text-gray-400 mt-1" x-text="new Date(n.created_at).toLocaleString()"></p>
                                                </div>
                                                <div x-show="!n.is_read" class="h-2 w-2 rounded-full bg-teal flex-shrink-0 mt-2"></div>
                                            </div>
                                        </template>
                                    </div>
                                    <div class="border-t border-gray-100 px-4 py-2.5 text-center">
                                        <a href="{{ route('notifications.index', [], false) }}" class="text-xs font-semibold text-teal hover:text-teal-dark">View all notifications</a>
                                    </div>
                                </div>
                            </div>

                            <!-- Messages Icon -->
                            <a href="{{ route('messages.index') }}" class="relative text-gray-500 hover:text-gray-700 p-2 rounded-lg hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-secondary" title="Messages" aria-label="Messages">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                </svg>
                                @php try { $topbarUnreadMsgs = \App\Models\Logistics\Conversation::unreadForLogisticsUser((int) auth()->id()); } catch (\Throwable $e) { $topbarUnreadMsgs = 0; } @endphp
                                @if($topbarUnreadMsgs)
                                    <span class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] bg-secondary text-white text-[10px] font-bold rounded-full flex items-center justify-center px-1">{{ $topbarUnreadMsgs }}</span>
                                @endif
                            </a>

                            @php
                                $topbarUser = auth()->user();
                                $topbarPhoto = $topbarUser?->profilePhotoUrl();
                                $topbarInitial = ($topbarUser && $topbarUser->name) ? substr($topbarUser->name, 0, 1) : '?';
                                $topbarOnProfile = request()->routeIs('profile.*');
                                $topbarOnSettings = request()->routeIs('settings.*');
                                $topbarRole = $topbarUser?->roleLabel();
                            @endphp

                            <!-- Divider between messaging controls and the account menu -->
                            <div class="w-px h-6 bg-gray-200 mx-1" aria-hidden="true"></div>

                            <!-- Account menu: one profile trigger for every viewport
                                 (Hick's Law: Profile, Settings, Log out grouped into a
                                 single predictable menu; Jakob's Law: avatar/name plus
                                 chevron, identity header, Profile + Settings, Log out
                                 separated at the bottom). Notifications and Messages
                                 stay visible as separate controls. Alpine (already in
                                 use) drives the toggle — no new framework. -->
                            <div class="relative" @keydown.escape.window="if (accountOpen) { accountOpen = false; $refs.accountTrigger.focus(); }">
                                <button type="button" x-ref="accountTrigger"
                                        @click="accountOpen = !accountOpen"
                                        :aria-expanded="accountOpen ? 'true' : 'false'"
                                        aria-haspopup="menu" aria-controls="profile-menu"
                                        aria-label="Account menu" title="Account"
                                        class="flex items-center gap-2 min-h-[44px] rounded-xl px-1.5 py-1 text-gray-500 hover:text-gray-700 hover:bg-gray-100 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-secondary">
                                    @if($topbarPhoto)
                                        <img src="{{ $topbarPhoto }}" alt="Profile photo" class="h-8 w-8 rounded-full object-cover ring-2 {{ ($topbarOnProfile || $topbarOnSettings) ? 'ring-secondary' : 'ring-teal/20' }} transition flex-shrink-0">
                                    @else
                                        <span class="h-8 w-8 rounded-full bg-teal-light ring-2 {{ ($topbarOnProfile || $topbarOnSettings) ? 'ring-secondary' : 'ring-teal/20' }} flex items-center justify-center text-teal-dark text-sm font-bold transition flex-shrink-0" aria-hidden="true">{{ $topbarInitial }}</span>
                                    @endif
                                    <span class="hidden sm:block text-sm font-semibold text-[#1F2933] truncate max-w-[140px]">{{ $topbarUser->name }}</span>
                                    <svg class="h-4 w-4 flex-shrink-0 transition-transform" :class="accountOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <div id="profile-menu" x-show="accountOpen" x-transition x-cloak @click.outside="accountOpen = false"
                                     class="absolute right-0 mt-2 w-[280px] max-w-[calc(100vw-2rem)] bg-white rounded-xl shadow-lg ring-1 ring-gray-200 z-50 py-1.5 overflow-hidden" role="menu" aria-label="Account">
                                    {{-- Account header: same photo source/fallback as the
                                         trigger and Profile page; the inline onerror
                                         covers a URL that breaks after render. --}}
                                    <div class="flex items-center gap-3 px-4 py-3 border-b border-gray-100">
                                        @if($topbarPhoto)
                                            <img src="{{ $topbarPhoto }}" alt="Profile photo" onerror="this.classList.add('hidden');this.nextElementSibling.classList.remove('hidden');this.nextElementSibling.classList.add('flex');" class="h-11 w-11 rounded-full object-cover ring-2 ring-teal/20 flex-shrink-0">
                                            <span class="hidden h-11 w-11 rounded-full bg-teal-light ring-2 ring-teal/20 items-center justify-center text-teal-dark text-base font-bold flex-shrink-0" aria-hidden="true">{{ $topbarInitial }}</span>
                                        @else
                                            <span class="h-11 w-11 rounded-full bg-teal-light ring-2 ring-teal/20 flex items-center justify-center text-teal-dark text-base font-bold flex-shrink-0" aria-hidden="true">{{ $topbarInitial }}</span>
                                        @endif
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-semibold text-[#1F2933] truncate">{{ $topbarUser->name }}</p>
                                            @if($topbarRole)
                                                <p class="text-xs text-gray-500 mt-0.5 truncate">{{ $topbarRole }}</p>
                                            @endif
                                        </div>
                                    </div>
                                    <a href="{{ route('profile.show') }}" title="Open profile" role="menuitem"
                                       @if($topbarOnProfile) aria-current="page" @endif
                                       class="flex items-center gap-3 px-4 min-h-[44px] py-2.5 text-sm font-medium {{ $topbarOnProfile ? 'text-teal-dark bg-teal-light' : 'text-[#1F2933] hover:bg-teal-light' }} focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-secondary">
                                        <svg class="h-5 w-5 flex-none text-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        My Profile
                                    </a>
                                    <a href="{{ route('settings.index') }}" title="Settings" role="menuitem"
                                       @if($topbarOnSettings) aria-current="page" @endif
                                       class="flex items-center gap-3 px-4 min-h-[44px] py-2.5 text-sm font-medium {{ $topbarOnSettings ? 'text-teal-dark bg-teal-light' : 'text-[#1F2933] hover:bg-teal-light' }} focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-secondary">
                                        <svg class="h-5 w-5 flex-none text-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        Settings
                                    </a>
                                    <div class="border-t border-gray-100 my-1.5" aria-hidden="true"></div>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" role="menuitem" aria-label="Log out"
                                                class="flex items-center gap-3 w-full px-4 min-h-[44px] py-2.5 text-sm font-medium text-red-700 hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-red-500">
                                            <svg class="h-5 w-5 flex-none text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                            </svg>
                                            Log Out
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </header>
@endif

{{-- ==================== ADMIN ==================== --}}
@elseif($navigationRole === 'admin')
@if(($navigationVariant ?? '') === 'dashboard')
<header class="topbar">
        <button class="hamburger" type="button" aria-label="Toggle sidebar" aria-controls="sidebar" aria-expanded="true">
          <svg viewBox="0 0 24 24"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
        </button>
        <div>
          <div class="topbar-title">Invoiz Admin</div>
          <div class="topbar-sub">Control Panel</div>
        </div>
      </header>
@endif
@endif
