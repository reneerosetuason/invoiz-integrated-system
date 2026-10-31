{{-- Shared sidebar. Role sections retain the active layouts' original markup and active-state rules. --}}

{{-- ==================== BUYER ==================== --}}
@if($navigationRole === 'buyer')
@if(($navigationVariant ?? '') === 'website')
<aside id="sidebar" class="sb2 sb2--buyer" aria-label="Buyer sidebar">
      <nav class="sidebar-nav" aria-label="Buyer navigation">
        <div role="group" aria-labelledby="bbLblMenu">
          <div class="nav-section sb-hide-collapsed" id="bbLblMenu">Menu</div>
          <a href="{{ url('/shop') }}" title="Home" class="{{ request()->is('shop') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
            <span class="sb-label sb-hide-collapsed">Home</span>
          </a>
          <a href="{{ url('/sale') }}" title="Sale" class="{{ request()->is('sale','products','product','product/*') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20.12 10.13 13.87 3.88a2 2 0 0 0-2.83 0L2.05 12.87a2 2 0 0 0 0 2.83l6.25 6.25a2 2 0 0 0 2.83 0l8.99-8.99a2 2 0 0 0 0-2.83z"/><circle cx="7.5" cy="7.5" r="1.5"/></svg>
            <span class="sb-label sb-hide-collapsed">Sale</span>
          </a>
          <a href="{{ url('/cart') }}" title="Cart" class="{{ request()->is('cart') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            <span class="sb-label sb-hide-collapsed">Cart</span>
            @if($cartCount > 0)<span class="sb-badge" data-count="{{ $cartCount }}"><span class="sb-num">{{ $cartBadge }}</span><span class="sr-only"> items in cart</span></span>@endif
          </a>
        </div>
        <div role="group" aria-labelledby="bbLblCat">
          <div class="nav-section sb-hide-collapsed" id="bbLblCat">Categories</div>
          @foreach(\App\Models\Category::active()->orderBy('name')->limit(8)->get() as $sc)
            <a class="sb-cat {{ (string)request('category')===(string)$sc->id ? 'active' : '' }}" href="{{ url('/shop?category='.$sc->id) }}" title="{{ $sc->name }}">
              <span>{{ $sc->name }}</span>
            </a>
          @endforeach
        </div>
        @if($buyer)
          <div role="group" aria-labelledby="bbLblAcct">
            <div class="nav-section sb-hide-collapsed" id="bbLblAcct">Account</div>
            <a href="{{ url('/orders') }}" title="My Orders" class="{{ request()->is('orders') ? 'active' : '' }}">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              <span class="sb-label sb-hide-collapsed">My Orders</span>
              @if($pendingOrders > 0)<span class="sb-badge" data-count="{{ $pendingOrders }}"><span class="sb-num">{{ $pendingOrders > 99 ? '99+' : $pendingOrders }}</span><span class="sr-only"> pending orders</span></span>@endif
            </a>
            <a href="{{ url('/profile') }}" title="My Profile" class="{{ request()->is('profile') ? 'active' : '' }}">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              <span class="sb-label sb-hide-collapsed">My Profile</span>
            </a>
            @php
              $switchStore = null;
              try {
                if (auth()->check()) {
                  $ss = \App\Models\Seller::where('user_id', auth()->id())->first();
                  if ($ss && $ss->isApproved()) $switchStore = $ss;
                }
              } catch (\Throwable $e) {}
            @endphp
            @if($switchStore && auth()->check() && auth()->user()->canSell())
              <form method="POST" action="{{ route('role.switch') }}" class="role-switch-form">
                @csrf
                <input type="hidden" name="role" value="seller">
                <button type="submit" class="role-switch-link" title="Switch to Seller">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9z"/><path d="M9 22V12h6v10"/></svg>
                  <span class="sb-label sb-hide-collapsed">Switch to Seller</span>
                </button>
              </form>
            @endif
          </div>
        @else
          <div role="group" aria-labelledby="bbLblAcct">
            <div class="nav-section sb-hide-collapsed" id="bbLblAcct">Account</div>
            <a href="{{ url('/login') }}" title="Login" class="{{ request()->is('login') ? 'active' : '' }}">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
              <span class="sb-label sb-hide-collapsed">Login</span>
            </a>
            <a href="{{ url('/register') }}" title="Register" class="{{ request()->is('register') ? 'active' : '' }}">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
              <span class="sb-label sb-hide-collapsed">Register</span>
            </a>
          </div>
        @endif
        <div role="group" aria-labelledby="bbLblMore">
          <div class="nav-section sb-hide-collapsed" id="bbLblMore">More</div>
          <a href="{{ url('/wishlist') }}" title="Wishlist" class="{{ request()->is('wishlist') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 0 0-.1-7.8z"/></svg>
            <span class="sb-label sb-hide-collapsed">Wishlist</span>
            @if($wishlistCount > 0)<span class="sb-badge"><span class="sb-num">{{ $wishlistCount > 99 ? '99+' : $wishlistCount }}</span></span>@endif
          </a>
          <a href="{{ url('/vouchers') }}" title="Vouchers" class="{{ request()->is('vouchers') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 12V7a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v5"/><path d="M4 12a2 2 0 0 1 0 4v1a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-1a2 2 0 0 1 0-4"/><path d="M13 5v2m0 4v2m0 4v2"/></svg>
            <span class="sb-label sb-hide-collapsed">Vouchers</span>
          </a>
          <a href="{{ url('/messages') }}" title="Messages" class="{{ request()->is('messages','chat','chat/*') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            <span class="sb-label sb-hide-collapsed">Messages</span>
            @if($unreadMsgs > 0)<span class="sb-badge" data-count="{{ $unreadMsgs }}"><span class="sb-num">{{ $unreadMsgs > 99 ? '99+' : $unreadMsgs }}</span><span class="sr-only"> unread messages</span></span>@endif
          </a>
          <a href="{{ url('/notifications') }}" title="Notifications" class="{{ request()->is('notifications') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <span class="sb-label sb-hide-collapsed">Notifications</span>
            @if($notifCount > 0)<span class="sb-badge" data-count="{{ $notifCount }}"><span class="sb-num">{{ $notifCount > 99 ? '99+' : $notifCount }}</span><span class="sr-only"> unread notifications</span></span>@endif
          </a>
          <a href="{{ url('/help') }}" title="Help &amp; FAQ" class="{{ request()->is('help') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <span class="sb-label sb-hide-collapsed">Help &amp; FAQ</span>
          </a>
        </div>
      </nav>
      <div class="sb-foot">
        @if($buyer)
          @php
$bName = trim(($buyer['first_name'] ?? '').' '.($buyer['last_name'] ?? ''));
@endphp
          @php
$bInitials = strtoupper(substr($buyer['first_name'] ?? '', 0, 1).substr($buyer['last_name'] ?? '', 0, 1)) ?: 'B';
@endphp
          <div class="sb-pillwrap">
            <div class="sb-pill">
              <a href="{{ url('/profile') }}" class="sb-who" data-tip="{{ $bName }} · Buyer">
                @if($bName)
                  <img src="{{ $buyerAvatar ?: 'https://ui-avatars.com/api/?name='.urlencode($bName).'&background=16697A&color=fff&size=72' }}" alt="" class="sb-avatar">
                @else
                  <span class="sb-avatar" aria-hidden="true">{{ $bInitials }}</span>
                @endif
                <span class="sb-fade">
                  <span class="sb-name" title="{{ $bName }}">{{ $bName }}</span>
                  <span class="sb-role">Buyer</span>
                </span>
              </a>
              <form method="POST" action="{{ route('logout') }}" style="margin:0;flex-shrink:0">
                @csrf
                <button type="submit" class="sb-logout" data-tip="Log out" aria-label="Log out">
                  <span class="sb-logout-circle"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span>
                </button>
              </form>
            </div>
          </div>
        @endif
        <div class="sb-version sb-hide-collapsed no-print">INVOIZ v1</div>
      </div>
    </aside>
@endif

{{-- ==================== SELLER ==================== --}}
@elseif($navigationRole === 'seller')
@if(($navigationVariant ?? '') === 'center')
<aside x-show="sidebarOpen" x-transition
           @keydown.tab="if (sidebarOpen && window.innerWidth < 1024) { const items = [...$el.querySelectorAll('a[href], button:not([disabled])')].filter(e => e.getClientRects().length); if (items.length) { const first = items[0], last = items[items.length - 1]; if ($event.shiftKey && document.activeElement === first) { $event.preventDefault(); last.focus(); } else if (!$event.shiftKey && document.activeElement === last) { $event.preventDefault(); first.focus(); } } }"
           :class="sidebarCollapsed ? 'sidebar-collapsed lg:w-[72px]' : ''"
            class="sb2 fixed inset-y-0 left-0 z-50 flex w-64 flex-col
                   lg:sticky lg:top-0 lg:h-screen lg:shrink-0 lg:translate-x-0 lg:z-auto print:hidden no-print transition-[width] duration-200"
            aria-label="Seller Center sidebar"
            x-cloak>
        {{-- Brand header --}}
        <div class="sb-brand-header relative px-4 pb-3 pt-4">
            <div class="sb-logo-row flex items-center gap-2.5">
                <span class="sb-logo"><img src="{{ asset('assets/shared/images/logo.png') }}" alt="INVOIZ" class="h-9 w-9 shrink-0 rounded-xl object-cover"></span>
                <div class="sb-hide-collapsed min-w-0">
                    <div class="text-xl font-extrabold tracking-tight text-[#0E4A57]">INVOIZ</div>
                    <div class="sb-eyebrow mt-0.5">Seller Center</div>
                </div>
            </div>

            <div class="sb-store sb-hide-collapsed mt-2 border-y py-2.5" style="border-color:rgba(15,76,92,.12)">
                <div class="flex items-center gap-2.5">
                    @if(auth()->user()->seller?->logo)
                        <img src="{{ asset('storage/'.auth()->user()->seller->logo) }}" alt="" class="h-9 w-9 shrink-0 rounded-full object-cover" style="outline:2px solid rgba(22,105,122,.25);outline-offset:1px">
                    @else
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#EAF4F3] text-xs font-bold text-[#0E4A57]" style="outline:2px solid rgba(22,105,122,.25);outline-offset:1px">
                            {{ strtoupper(substr(auth()->user()->seller->business_name ?? 'S', 0, 1)) }}
                        </div>
                    @endif
                    <div class="min-w-0">
                        <div class="truncate text-sm font-semibold text-[#0E4A57]" title="{{ auth()->user()->seller->business_name ?? 'Store' }}">{{ auth()->user()->seller->business_name }}</div>
                        <div class="truncate text-xs text-[#6E6E73]">{{ auth()->user()->seller->line_of_business }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Nav --}}
        <nav class="sidebar-nav" aria-label="Seller navigation">
            <div class="nav-section sb-hide-collapsed">Main</div>
            <a href="{{ route('seller.dashboard') }}" title="Dashboard" class="{{ request()->routeIs('seller.dashboard') ? 'active' : '' }}">
                <x-icon name="home" class="h-4 w-4 shrink-0" /><span class="sb-label sb-hide-collapsed">Dashboard</span>
            </a>
            <a href="{{ route('seller.orders.index') }}" title="Orders" class="{{ request()->routeIs('seller.orders.*') ? 'active' : '' }}">
                <x-icon name="shopping-bag" class="h-4 w-4 shrink-0" /><span class="sb-label sb-hide-collapsed">Orders</span>
            </a>
            <a href="{{ route('seller.remittances.index') }}" title="Cash Remittances" class="{{ request()->routeIs('seller.remittances.*') ? 'active' : '' }}">
                <x-icon name="dollar" class="h-4 w-4 shrink-0" /><span class="sb-label sb-hide-collapsed">Remittances</span>
            </a>
            <a href="{{ route('seller.payouts.index') }}" title="Payouts" class="{{ request()->routeIs('seller.payouts.*') ? 'active' : '' }}">
                <x-icon name="dollar" class="h-4 w-4 shrink-0" /><span class="sb-label sb-hide-collapsed">Payouts</span>
            </a>

            <div class="nav-section sb-hide-collapsed">Store</div>
            <a href="{{ route('seller.products.index') }}" title="Inventory" class="{{ request()->routeIs('seller.products.*') ? 'active' : '' }}">
                <x-icon name="box" class="h-4 w-4 shrink-0" /><span class="sb-label sb-hide-collapsed">Inventory</span>
            </a>
            <a href="{{ route('seller.vouchers.index') }}" title="Vouchers" class="{{ request()->routeIs('seller.vouchers.*') ? 'active' : '' }}">
                <x-icon name="ticket" class="h-4 w-4 shrink-0" /><span class="sb-label sb-hide-collapsed">Vouchers</span>
            </a>
            <a href="{{ route('seller.sales.index') }}" title="Store Sales" class="{{ request()->routeIs('seller.sales.*') ? 'active' : '' }}">
                <x-icon name="dollar" class="h-4 w-4 shrink-0" /><span class="sb-label sb-hide-collapsed">Store Sales</span>
            </a>
            <a href="{{ route('seller.feedback.index') }}" title="Customer Feedback" class="{{ request()->routeIs('seller.feedback.*') ? 'active' : '' }}">
                <x-icon name="star" class="h-4 w-4 shrink-0" /><span class="sb-label sb-hide-collapsed">Customer Feedback</span>
            </a>
            <a href="{{ route('seller.returns.index') }}" title="Returns & Refunds" class="{{ request()->routeIs('seller.returns.*') ? 'active' : '' }}">
                <x-icon name="refresh" class="h-4 w-4 shrink-0" /><span class="sb-label sb-hide-collapsed">Returns</span>
            </a>
            <a href="{{ route('seller.reports') }}" title="Reports" class="{{ request()->routeIs('seller.reports') ? 'active' : '' }}">
                <x-icon name="chart" class="h-4 w-4 shrink-0" /><span class="sb-label sb-hide-collapsed">Reports</span>
            </a>

            <div class="nav-section sb-hide-collapsed">Support</div>
            <a href="{{ route('seller.chat.index') }}" title="Chat / Messaging" class="{{ request()->routeIs('seller.chat.*') ? 'active' : '' }}">
                <x-icon name="chat" class="h-4 w-4 shrink-0" /><span class="sb-label sb-hide-collapsed">Chat / Messaging</span>
            </a>

            <div class="nav-section sb-hide-collapsed">Account</div>
            <a href="{{ route('seller.notifications.index') }}" title="Notifications" class="{{ request()->routeIs('seller.notifications.*') ? 'active' : '' }}">
                <x-icon name="bell" class="h-4 w-4 shrink-0" /><span class="sb-label sb-hide-collapsed">Notifications</span>@if(($layoutUnreadCount ?? 0) > 0)<span class="sb-badge" data-count="{{ $layoutUnreadCount }}"><span class="sb-num">{{ $layoutUnreadCount > 99 ? '99+' : $layoutUnreadCount }}</span><span class="sr-only"> unread notification{{ $layoutUnreadCount == 1 ? '' : 's' }}</span></span>@endif
            </a>
            <a href="{{ route('seller.account') }}" title="Account Management" class="{{ request()->routeIs('seller.account') ? 'active' : '' }}">
                <x-icon name="user" class="h-4 w-4 shrink-0" /><span class="sb-label sb-hide-collapsed">Account Management</span>
            </a>
            @if(auth()->check() && auth()->user()->canSell())
            <form method="POST" action="{{ route('role.switch') }}" class="role-switch-form">
                @csrf
                <input type="hidden" name="role" value="buyer">
                <button type="submit" class="role-switch-link" title="Switch to Buyer">
                    <x-icon name="shopping-bag" class="h-4 w-4 shrink-0" /><span class="sb-label sb-hide-collapsed">Switch to Buyer</span>
                </button>
            </form>
            @endif
        </nav>

        {{-- Pinned footer stack: pill + version with reserved height, never overlapping nav items. --}}
        <div class="sb-foot">
            @php
$sbName = auth()->user()->full_name ?: (auth()->user()->seller->business_name ?? '');
@endphp
            @php
$sbInitials = strtoupper(substr(auth()->user()->first_name ?? '', 0, 1).substr(auth()->user()->last_name ?? '', 0, 1));
@endphp
            <div class="sb-pillwrap">
                <div class="sb-pill">
                    <a href="{{ route('seller.account') }}" class="sb-who" data-tip="{{ $sbName }} · Seller">
                        @if(auth()->user()->seller?->logo)
                            <img src="{{ asset('storage/'.auth()->user()->seller->logo) }}" alt="" class="sb-avatar">
                        @else
                            <span class="sb-avatar" aria-hidden="true">{{ $sbInitials }}</span>
                        @endif
                        <span class="sb-fade">
                            <span class="sb-name" title="{{ $sbName }}">{{ $sbName }}</span>
                            <span class="sb-role">Seller</span>
                        </span>
                    </a>
                    <form method="POST" action="{{ route('seller.logout') }}" class="shrink-0">
                        @csrf
                        <button type="submit" class="sb-logout" data-tip="Log out" aria-label="Log out">
                            <span class="sb-logout-circle"><x-icon name="logout" class="h-4 w-4 shrink-0" /></span>
                        </button>
                    </form>
                </div>
            </div>
            <div class="sb-version sb-hide-collapsed no-print">Invoiz Seller · v1.0</div>
        </div>
    </aside>
@endif

{{-- ==================== LOGISTICS ==================== --}}
@elseif($navigationRole === 'logistics')
@if(($navigationVariant ?? '') === 'workspace')
@php
    $user = Auth::user();
    $isAdmin = in_array($user->role, ['admin', 'logistics_admin'], true);
    $isStaff = in_array($user->role, ['staff', 'logistics_staff'], true);
    $isRider = $user->role === 'rider';
    $logisticsCenter = null;
    try {
        if ($user && $user->center_id) {
            $logisticsCenter = $user->logisticsCenter;
        }
    } catch (\Throwable $e) {}
    $userName = $user->name ?? 'User';
    $userEmail = $user->email ?? '';
    $userInitial = substr($userName, 0, 1);
    $accountTitle = $user->role === 'admin' ? $user->roleLabel() : $userName;
    $profilePhotoUrl = $user->profilePhotoUrl();
    $pickupPendingCount = 0;
    try {
        $pickupPendingCount = (int) \App\Models\PickupRequest::query()->pendingReview($user)->count();
    } catch (\Throwable $e) {}

    $activeDashboard = request()->routeIs('dashboard');
    $activeDeliveries = request()->routeIs('deliveries.*');
    $activeScan      = request()->routeIs('deliveries.scan-page');
    $activeDeliveriesMain = $activeDeliveries && ! $activeScan;
    $activeSorting = request()->routeIs('sorting-center.*');
    $activeRiders    = request()->routeIs('riders.*');
    $activePickups   = request()->routeIs('pickup-requests.*');
    $activeStaff     = request()->routeIs('staff.*');
    $activeRiderApps = request()->routeIs('rider-applications.*');
    $activeCenterApps = request()->routeIs('center-applications.*');
    $activeAppsGroup = $activeRiderApps || $activeCenterApps;
    $activeCenters   = request()->routeIs('centers.*');
    $activeAreas     = request()->routeIs('service-areas.*');
    $activeTransactions = request()->routeIs('transactions.*');
    $activeRemittances = request()->routeIs('remittances.*');
    $activeReports   = request()->routeIs('reports.*');
    $activeArchived  = request()->routeIs('deliveries.archived');
    $activeMessages  = request()->routeIs('messages.*');
    $msgUnreadCount = 0;
    try { $msgUnreadCount = \App\Models\Logistics\Conversation::unreadForLogisticsUser((int) auth()->id()); } catch (\Throwable $e) {}
    $activeSettings  = request()->routeIs('settings.*');
@endphp

<link rel="stylesheet" href="{{ asset('assets/logistics/css/sidebar.css') }}">

<aside id="app-sidebar" class="sb2 sb2--log sidebar-root sidebar-pane fixed inset-y-0 left-0 z-40"
       :class="{ 'is-expanded': sidebarHover, 'sidebar-collapsed': !sidebarHover }" aria-label="Logistics sidebar" :aria-expanded="sidebarHover ? 'true' : 'false'">

    <div class="sb-brand-header">
        <div class="sb-logo-row flex items-center justify-between gap-2">
            <a href="{{ route('dashboard') }}" aria-label="INVOIZ logistics home" data-tip="INVOIZ logistics home"
               x-show="sidebarHover" x-cloak class="flex items-center gap-2.5 min-w-0">
                <img src="{{ asset('assets/shared/images/logo.png') }}" alt="" class="h-9 w-9 shrink-0 rounded-xl object-cover">
                <span class="min-w-0">
                    <span class="block text-xl font-extrabold tracking-tight text-[#0E4A57]">INVOIZ</span>
                    <span class="sb-eyebrow block mt-0.5">Logistics</span>
                    @if($logisticsCenter)
                        <span class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-[#E6F4F1] text-[#0E4A57] border border-[#16697A]/20 truncate max-w-[170px]" title="{{ $logisticsCenter->name }}">
                            <svg class="w-3 h-3 text-[#16697A] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <span class="truncate">{{ $logisticsCenter->name }}</span>
                        </span>
                    @elseif($isAdmin)
                        <span class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-gray-100 text-gray-700 truncate max-w-[170px]" title="HQ Admin · All Centers">
                            <svg class="w-3 h-3 text-gray-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            <span>HQ Admin (All Hubs)</span>
                        </span>
                    @endif
                </span>
            </a>
            <button type="button" @click="toggleSidebar()" class="sb-hamb"
                    :aria-expanded="sidebarHover ? 'true' : 'false'" aria-controls="app-sidebar"
                    aria-label="Toggle navigation menu" data-tip="Toggle navigation">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>
    </div>

    <nav class="sidebar-nav flex flex-col" aria-label="Logistics navigation"
         x-init="$nextTick(() => { try { $el.scrollTop = Number(sessionStorage.getItem(sidebarKey + ':scroll')) || 0; } catch (e) {} })"
         @scroll.passive="(() => { try { sessionStorage.setItem(sidebarKey + ':scroll', $el.scrollTop); } catch (e) {} })()">

        {{-- Dashboard --}}
        <a href="{{ route('dashboard') }}"
           aria-label="Dashboard" data-tip="Dashboard"
           @if($activeDashboard) aria-current="page" @endif
           class="{{ $activeDashboard ? 'active' : '' }} relative group flex items-center h-10 w-full rounded-xl transition-colors gap-3"
           :class="sidebarHover ? 'px-3 justify-start' : 'justify-center'">
            <svg class="h-5 w-5 flex-shrink-0 {{ $activeDashboard ? 'text-teal-dark' : 'text-teal' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block {{ $activeDashboard ? 'text-teal-dark' : 'text-[#1F2933]' }}">Dashboard</span>
        </a>

        @if(!$isRider)

        {{-- Deliveries (dropdown parent: the link navigates, the arrow toggles the child) --}}
        <div class="sb-parent relative group flex items-center h-10 w-full rounded-xl transition-colors {{ $activeDeliveriesMain ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light hover:text-teal-dark' }}"
             :class="sidebarHover ? 'ps-3 pe-1 justify-start gap-1' : 'justify-center'">
            <a href="{{ route('deliveries.index') }}"
               aria-label="Deliveries" data-tip="Deliveries"
               @if($activeDeliveriesMain) aria-current="page" @endif
               class="{{ $activeDeliveriesMain ? 'active' : '' }} flex items-center gap-3 flex-1 min-w-0 h-full"
               :class="sidebarHover ? '' : 'justify-center'">
                <svg class="h-5 w-5 flex-shrink-0 {{ $activeDeliveriesMain ? 'text-teal-dark' : 'text-teal' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                </svg>
                <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block {{ $activeDeliveriesMain ? 'text-teal-dark' : 'text-[#1F2933]' }}">Deliveries</span>
            </a>
            <button type="button" x-show="sidebarHover" x-cloak
                    @click="deliveriesOpen = !deliveriesOpen"
                    :aria-expanded="deliveriesOpen ? 'true' : 'false'"
                    aria-controls="subnav-deliveries"
                    :aria-label="deliveriesOpen ? 'Collapse Deliveries' : 'Expand Deliveries'" data-tip="deliveriesOpen ? 'Collapse Deliveries' : 'Expand Deliveries'"
                    class="flex items-center justify-center h-10 w-10 rounded-xl text-teal-dark hover:bg-teal/20 transition-colors flex-shrink-0">
                <svg class="h-4 w-4 transition-transform motion-safe:transition-transform" :class="deliveriesOpen ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </button>
        </div>

        {{-- Scan Parcel (child of Deliveries) --}}
        <div class="sb-sub" id="subnav-deliveries" x-show="sidebarHover && deliveriesOpen" x-cloak x-collapse>
            <a href="{{ route('deliveries.scan-page') }}"
               aria-label="Scan Parcel" data-tip="Scan Parcel"
               @if($activeScan) aria-current="page" @endif
               class="{{ $activeScan ? 'active' : '' }} relative group flex items-center h-10 w-full rounded-xl transition-colors pl-14 pr-3 {{ $activeScan ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light/60' }}">
                <span class="text-[13px] font-medium whitespace-nowrap block {{ $activeScan ? 'text-teal-dark' : 'text-gray-600' }}">Scan Parcel</span>
            </a>
        </div>

        {{-- Sorting Center (operational hub; detail workflow untouched) --}}
        <a href="{{ route('sorting-center.index') }}"
           aria-label="Sorting Center" data-tip="Sorting Center"
           @if($activeSorting) aria-current="page" @endif
           class="{{ $activeSorting ? 'active' : '' }} relative group flex items-center h-10 w-full rounded-xl transition-colors gap-3 {{ $activeSorting ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light hover:text-teal-dark' }}"
           :class="sidebarHover ? 'px-3 justify-start' : 'justify-center'">
            <svg class="h-5 w-5 flex-shrink-0 {{ $activeSorting ? 'text-teal-dark' : 'text-teal' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block {{ $activeSorting ? 'text-teal-dark' : 'text-[#1F2933]' }}">Sorting Center</span>
        </a>

        {{-- Riders --}}
        <a href="{{ route('riders.index') }}"
           aria-label="Riders" data-tip="Riders"
           @if($activeRiders) aria-current="page" @endif
           class="{{ $activeRiders ? 'active' : '' }} relative group flex items-center h-10 w-full rounded-xl transition-colors gap-3 {{ $activeRiders ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light hover:text-teal-dark' }}"
           :class="sidebarHover ? 'px-3 justify-start' : 'justify-center'">
            <svg class="h-5 w-5 flex-shrink-0 {{ $activeRiders ? 'text-teal-dark' : 'text-teal' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block {{ $activeRiders ? 'text-teal-dark' : 'text-[#1F2933]' }}">Riders</span>
        </a>

        {{-- Pickup Requests --}}
        <a href="{{ route('pickup-requests.index') }}"
           aria-label="Pickup Requests{{ $pickupPendingCount > 0 ? ' ('.$pickupPendingCount.' pending)' : '' }}" data-tip="Pickup Requests{{ $pickupPendingCount > 0 ? ' ('.$pickupPendingCount.' pending)' : '' }}"
           data-pickup-count="{{ $pickupPendingCount }}"
           @if($activePickups) aria-current="page" @endif
           class="{{ $activePickups ? 'active' : '' }} relative group flex items-center h-10 w-full rounded-xl transition-colors gap-3 {{ $activePickups ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light hover:text-teal-dark' }}"
           :class="sidebarHover ? 'px-3 justify-start' : 'justify-center'">
            <svg class="h-5 w-5 flex-shrink-0 {{ $activePickups ? 'text-teal-dark' : 'text-teal' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block {{ $activePickups ? 'text-teal-dark' : 'text-[#1F2933]' }}">Pickup Requests</span>
            @if($pickupPendingCount > 0)
                <span class="flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold"
                      :class="sidebarHover ? 'static ml-auto' : 'absolute -top-0.5 -right-0.5'">{{ $pickupPendingCount }}</span>
            @endif
        </a>

        @if($isAdmin)

        <div x-show="sidebarHover" x-cloak class="border-t border-teal/10 my-0.5 w-full"></div>

        {{-- Management section --}}
        <p x-show="sidebarHover" x-cloak class="nav-section">Management</p>

        {{-- Staff --}}
        <a href="{{ route('staff.index') }}"
           aria-label="Staff" data-tip="Staff"
           @if($activeStaff) aria-current="page" @endif
           class="{{ $activeStaff ? 'active' : '' }} relative group flex items-center h-10 w-full rounded-xl transition-colors gap-3 {{ $activeStaff ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light hover:text-teal-dark' }}"
           :class="sidebarHover ? 'px-3 justify-start' : 'justify-center'">
            <svg class="h-5 w-5 flex-shrink-0 {{ $activeStaff ? 'text-teal-dark' : 'text-teal' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block {{ $activeStaff ? 'text-teal-dark' : 'text-[#1F2933]' }}">Staff</span>
        </a>

        {{-- Applications (dropdown parent: the link navigates, the arrow toggles the child) --}}
        <div class="sb-parent relative group flex items-center h-10 w-full rounded-xl transition-colors {{ $activeRiderApps ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light hover:text-teal-dark' }}"
             :class="sidebarHover ? 'ps-3 pe-1 justify-start gap-1' : 'justify-center'">
            <a href="{{ route(Auth::user()->canManageRiders() ? 'rider-applications.index' : 'center-applications.index') }}"
               aria-label="Applications" data-tip="Applications"
               @if($activeRiderApps) aria-current="page" @endif
               class="{{ $activeRiderApps ? 'active' : '' }} flex items-center gap-3 flex-1 min-w-0 h-full"
               :class="sidebarHover ? '' : 'justify-center'">
                <svg class="h-5 w-5 flex-shrink-0 {{ $activeAppsGroup ? 'text-teal-dark' : 'text-teal' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block {{ $activeAppsGroup ? 'text-teal-dark' : 'text-[#1F2933]' }}">Applications</span>
            </a>
            <button type="button" x-show="sidebarHover" x-cloak
                    @click="applicationsOpen = !applicationsOpen"
                    :aria-expanded="applicationsOpen ? 'true' : 'false'"
                    aria-controls="subnav-applications"
                    :aria-label="applicationsOpen ? 'Collapse Applications' : 'Expand Applications'" data-tip="applicationsOpen ? 'Collapse Applications' : 'Expand Applications'"
                    class="flex items-center justify-center h-10 w-10 rounded-xl text-teal-dark hover:bg-teal/20 transition-colors flex-shrink-0">
                <svg class="h-4 w-4 transition-transform motion-safe:transition-transform" :class="applicationsOpen ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </button>
        </div>

        {{-- Center Applications (child of Applications) --}}
        <div class="sb-sub" id="subnav-applications" x-show="sidebarHover && applicationsOpen" x-cloak x-collapse>
            <a href="{{ route('center-applications.index') }}"
               aria-label="Center Applications" data-tip="Center Applications"
               @if($activeCenterApps) aria-current="page" @endif
               class="{{ $activeCenterApps ? 'active' : '' }} relative group flex items-center h-10 w-full rounded-xl transition-colors pl-14 pr-3 {{ $activeCenterApps ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light/60' }}">
                <span class="text-[13px] font-medium whitespace-nowrap block {{ $activeCenterApps ? 'text-teal-dark' : 'text-gray-600' }}">Center Applications</span>
            </a>
        </div>

        {{-- Centers --}}
        <a href="{{ route('centers.index') }}"
           aria-label="Centers" data-tip="Centers"
           @if($activeCenters) aria-current="page" @endif
           class="{{ $activeCenters ? 'active' : '' }} relative group flex items-center h-10 w-full rounded-xl transition-colors gap-3 {{ $activeCenters ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light hover:text-teal-dark' }}"
           :class="sidebarHover ? 'px-3 justify-start' : 'justify-center'">
            <svg class="h-5 w-5 flex-shrink-0 {{ $activeCenters ? 'text-teal-dark' : 'text-teal' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block {{ $activeCenters ? 'text-teal-dark' : 'text-[#1F2933]' }}">Centers</span>
        </a>

        {{-- Service Areas --}}
        <a href="{{ route('service-areas.index') }}"
           aria-label="Service Areas" data-tip="Service Areas"
           @if($activeAreas) aria-current="page" @endif
           class="{{ $activeAreas ? 'active' : '' }} relative group flex items-center h-10 w-full rounded-xl transition-colors gap-3 {{ $activeAreas ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light hover:text-teal-dark' }}"
           :class="sidebarHover ? 'px-3 justify-start' : 'justify-center'">
            <svg class="h-5 w-5 flex-shrink-0 {{ $activeAreas ? 'text-teal-dark' : 'text-teal' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block {{ $activeAreas ? 'text-teal-dark' : 'text-[#1F2933]' }}">Service Areas</span>
        </a>
        @endif

        <div x-show="sidebarHover" x-cloak class="border-t border-teal/10 my-0.5 w-full"></div>

        {{-- Finance section --}}
        <p x-show="sidebarHover" x-cloak class="nav-section">Finance</p>

        {{-- Transactions --}}
        <a href="{{ route('transactions.index') }}"
           aria-label="Transactions" data-tip="Transactions"
           @if($activeTransactions) aria-current="page" @endif
           class="{{ $activeTransactions ? 'active' : '' }} relative group flex items-center h-10 w-full rounded-xl transition-colors gap-3 {{ $activeTransactions ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light hover:text-teal-dark' }}"
           :class="sidebarHover ? 'px-3 justify-start' : 'justify-center'">
            <svg class="h-5 w-5 flex-shrink-0 {{ $activeTransactions ? 'text-teal-dark' : 'text-teal' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block {{ $activeTransactions ? 'text-teal-dark' : 'text-[#1F2933]' }}">Transactions</span>
        </a>

        {{-- Remittances (read-only COD handoffs) --}}
        <a href="{{ route('remittances.index') }}"
           aria-label="Remittances" data-tip="Remittances"
           @if($activeRemittances) aria-current="page" @endif
           class="{{ $activeRemittances ? 'active' : '' }} relative group flex items-center h-10 w-full rounded-xl transition-colors gap-3 {{ $activeRemittances ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light hover:text-teal-dark' }}"
           :class="sidebarHover ? 'px-3 justify-start' : 'justify-center'">
            <svg class="h-5 w-5 flex-shrink-0 {{ $activeRemittances ? 'text-teal-dark' : 'text-teal' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block {{ $activeRemittances ? 'text-teal-dark' : 'text-[#1F2933]' }}">Remittances</span>
        </a>

        {{-- Reports --}}
        <a href="{{ route('reports.index') }}"
           aria-label="Reports" data-tip="Reports"
           @if($activeReports) aria-current="page" @endif
           class="{{ $activeReports ? 'active' : '' }} relative group flex items-center h-10 w-full rounded-xl transition-colors gap-3 {{ $activeReports ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light hover:text-teal-dark' }}"
           :class="sidebarHover ? 'px-3 justify-start' : 'justify-center'">
            <svg class="h-5 w-5 flex-shrink-0 {{ $activeReports ? 'text-teal-dark' : 'text-teal' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block {{ $activeReports ? 'text-teal-dark' : 'text-[#1F2933]' }}">Reports</span>
        </a>

        {{-- Archived --}}
        {{-- Messages (logistics inbox; unread badge from logistics_conversations) --}}
        <a href="{{ route('messages.index') }}"
           aria-label="Messages" data-tip="Messages"
           @if($activeMessages) aria-current="page" @endif
           class="{{ $activeMessages ? 'active' : '' }} relative group flex items-center h-10 w-full rounded-xl transition-colors gap-3 {{ $activeMessages ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light hover:text-teal-dark' }}"
           :class="sidebarHover ? 'px-3 justify-start' : 'justify-center'">
            <svg class="h-5 w-5 flex-shrink-0 {{ $activeMessages ? 'text-teal-dark' : 'text-teal' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block {{ $activeMessages ? 'text-teal-dark' : 'text-[#1F2933]' }}">Messages</span>
            @if($msgUnreadCount > 0)
                <span class="flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-teal-dark text-white text-[10px] font-bold"
                      :class="sidebarHover ? 'static ml-auto' : 'absolute -top-0.5 -right-0.5'">{{ $msgUnreadCount > 99 ? '99+' : $msgUnreadCount }}<span class="sr-only"> unread messages</span></span>
            @endif
        </a>
        <a href="{{ route('deliveries.archived') }}"
           aria-label="Archived" data-tip="Archived"
           @if($activeArchived) aria-current="page" @endif
           class="{{ $activeArchived ? 'active' : '' }} relative group flex items-center h-10 w-full rounded-xl transition-colors gap-3 {{ $activeArchived ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light hover:text-teal-dark' }}"
           :class="sidebarHover ? 'px-3 justify-start' : 'justify-center'">
            <svg class="h-5 w-5 flex-shrink-0 {{ $activeArchived ? 'text-teal-dark' : 'text-teal' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block {{ $activeArchived ? 'text-teal-dark' : 'text-[#1F2933]' }}">Archived</span>
        </a>
        @endif
    </nav>
    <div class="sb-foot">
        <div class="sb-pillwrap">
            <div class="sb-pill">
                <a href="{{ route('profile.show') }}" class="sb-who" data-tip="Profile" aria-label="Open profile">
                    @if($profilePhotoUrl)
                        <img src="{{ $profilePhotoUrl }}" alt="" class="sb-avatar">
                    @else
                        <span class="sb-avatar" aria-hidden="true">{{ strtoupper($userInitial) }}</span>
                    @endif
                    <span class="sb-fade">
                        <span class="sb-name">{{ $accountTitle }}</span>
                        <span class="sb-role">{{ $user->roleLabel() }}</span>
                        @if($logisticsCenter)
                            <span class="inline-flex items-center gap-1 text-[11px] text-[#16697A] font-semibold truncate max-w-[140px]" title="{{ $logisticsCenter->name }}">
                                <svg class="w-3 h-3 text-[#16697A] shrink-0 inline-block" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span class="truncate">{{ $logisticsCenter->name }}</span>
                            </span>
                        @endif
                    </span>
                </a>
                <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                    @csrf
                    <button type="submit" class="sb-logout" data-tip="Log out" aria-label="Log out">
                        <span class="sb-logout-circle">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H5a2 2 0 00-2 2v10a2 2 0 002 2h4m7-14l5 5-5 5m5-5H9" />
                            </svg>
                        </span>
                    </button>
                </form>
            </div>
        </div>
        <div x-show="sidebarHover" x-cloak class="sb-version">INVOIZ Logistics</div>
    </div>
</aside>
@endif

{{-- ==================== ADMIN ==================== --}}
@elseif($navigationRole === 'admin')
@if(($navigationVariant ?? '') === 'dashboard')
<aside class="sidebar sb2 sb2--admin" id="sidebar">
      @php
        $acctLogo = public_path('storage/logos/invoiz-admin.png');
        $hasAcctLogo = file_exists($acctLogo);
        $adminName = auth()->user()->name ?? 'Administrator';
        $adminEmail = auth()->user()->email ?? '';
      @endphp

      <div class="sb-brand-header">
        <div class="sb-logo-row">
          <span class="sb-logo" data-tip="INVOIZ"><img src="{{ asset('assets/shared/images/logo.png') }}" alt="INVOIZ"></span>
          <span class="sb-brand-copy sb-hide-collapsed">
            <span class="sb-brand-name">INVOIZ</span>
            <span class="sb-brand-sub">Admin control panel</span>
          </span>
        </div>
      </div>

      <nav class="sidebar-nav" aria-label="Admin navigation">
        <div class="admin-nav-group" role="group" aria-label="Overview">
          <div class="nav-section sb-hide-collapsed" aria-hidden="true">Overview</div>
          <a class="nav-link" href="/admin/dashboard" title="Dashboard">
            <x-icon name="home" class="nav-icon" aria-hidden="true" />
            <span class="nav-text sb-label sb-hide-collapsed">Dashboard</span>
          </a>
        </div>

        <div class="admin-nav-group" role="group" aria-label="Management">
          <div class="nav-section sb-hide-collapsed" aria-hidden="true">Management</div>
          <a class="nav-link" href="/admin/sellers" title="Manage Sellers">
            <x-icon name="store" class="nav-icon" aria-hidden="true" />
            <span class="nav-text sb-label sb-hide-collapsed">Manage Sellers</span>
          </a>
          <a class="nav-link" href="/admin/buyers" title="Manage Buyers">
            <x-icon name="users" class="nav-icon" aria-hidden="true" />
            <span class="nav-text sb-label sb-hide-collapsed">Manage Buyers</span>
          </a>
          <a class="nav-link" href="/admin/riders" title="Rider Directory">
            <x-icon name="truck" class="nav-icon" aria-hidden="true" />
            <span class="nav-text sb-label sb-hide-collapsed">Rider Directory</span>
          </a>
          <a class="nav-link" href="/admin/products" title="Manage Products">
            <x-icon name="package" class="nav-icon" aria-hidden="true" />
            <span class="nav-text sb-label sb-hide-collapsed">Manage Products</span>
          </a>
          <a class="nav-link" href="/admin/categories" title="Manage Categories">
            <x-icon name="layers" class="nav-icon" aria-hidden="true" />
            <span class="nav-text sb-label sb-hide-collapsed">Manage Categories</span>
          </a>
          <a class="nav-link" href="/admin/orders" title="Manage Orders">
            <x-icon name="clipboard" class="nav-icon" aria-hidden="true" />
            <span class="nav-text sb-label sb-hide-collapsed">Manage Orders</span>
          </a>
        </div>

        <div class="admin-nav-group" role="group" aria-label="Finance">
          <div class="nav-section sb-hide-collapsed" aria-hidden="true">Finance</div>
          <a class="nav-link" href="/admin/payments" title="Manage Payments">
            <x-icon name="card" class="nav-icon" aria-hidden="true" />
            <span class="nav-text sb-label sb-hide-collapsed">Manage Payments</span>
          </a>
          <a class="nav-link {{ request()->is('logistics/remittances*') ? 'active' : '' }}" href="{{ route('remittances.index') }}" title="COD Remittances">
            <x-icon name="dollar" class="nav-icon" aria-hidden="true" />
            <span class="nav-text sb-label sb-hide-collapsed">COD Remittances</span>
          </a>
          <a class="nav-link" href="/admin/reports" title="Reports">
            <x-icon name="chart" class="nav-icon" aria-hidden="true" />
            <span class="nav-text sb-label sb-hide-collapsed">Reports</span>
          </a>
          <a class="nav-link" href="/admin/commission" title="Commission">
            <x-icon name="dollar" class="nav-icon" aria-hidden="true" />
            <span class="nav-text sb-label sb-hide-collapsed">Commission</span>
          </a>
        </div>

        <div class="admin-nav-group" role="group" aria-label="Support">
          <div class="nav-section sb-hide-collapsed" aria-hidden="true">Support</div>
          <a class="nav-link" href="/admin/complaints" title="Complaints">
            <x-icon name="alert-triangle" class="nav-icon" aria-hidden="true" />
            <span class="nav-text sb-label sb-hide-collapsed">Complaints</span>
          </a>
          <a class="nav-link" href="/admin/chat" title="Chat / Messaging">
            <x-icon name="chat" class="nav-icon" aria-hidden="true" />
            <span class="nav-text sb-label sb-hide-collapsed">Chat / Messaging</span>
          </a>
        </div>

        <div class="admin-nav-group" role="group" aria-label="System">
          <div class="nav-section sb-hide-collapsed" aria-hidden="true">System</div>
          <a class="nav-link" href="/admin/settings" title="System Settings">
            <x-icon name="settings" class="nav-icon" aria-hidden="true" />
            <span class="nav-text sb-label sb-hide-collapsed">System Settings</span>
          </a>
        </div>
      </nav>

      <div class="sidebar-foot">
        <div class="sb-pill">
          <div class="sb-who" title="{{ $adminName }}{{ $adminEmail ? ' · '.$adminEmail : '' }}">
            @if ($hasAcctLogo)
              <img src="{{ asset('storage/logos/invoiz-admin.png') }}" alt="" class="sb-avatar">
            @else
              <span class="sb-avatar sb-avatar-fallback" aria-hidden="true">{{ mb_strtoupper(mb_substr($adminName, 0, 1)) }}</span>
            @endif
            <span class="sb-fade">
              <span class="sb-name" title="{{ $adminName }}">{{ $adminName }}</span>
              <span class="sb-role" title="{{ $adminEmail }}">{{ $adminEmail }}</span>
            </span>
          </div>
          <form method="POST" action="{{ url('/admin/logout') }}" style="margin:0;flex-shrink:0">
            @csrf
            <button type="submit" class="logout-btn sb-logout" data-tip="Log out" aria-label="Log out">
              <span class="sb-logout-circle"><x-icon name="logout" class="h-4 w-4" aria-hidden="true" /></span>
            </button>
          </form>
        </div>
        <div class="sb-version sb-hide-collapsed">INVOIZ Admin · v1.0</div>
      </div>
    </aside>
@endif
@endif
