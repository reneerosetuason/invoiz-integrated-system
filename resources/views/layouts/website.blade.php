<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Invoiz — Online Shopping</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('frontend/css/app.css') }}">
  <link rel="stylesheet" href="{{ asset('frontend/css/theme.css') }}">
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,560;9..144,700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('css/app-sidebar.css') }}?v={{ filemtime(public_path('css/app-sidebar.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/shop.css') }}?v={{ filemtime(public_path('css/shop.css')) }}">
  <script>document.documentElement.classList.add('js')</script>
  <script src="{{ asset('js/app-sidebar.js') }}?v={{ filemtime(public_path('js/app-sidebar.js')) }}" defer></script>
  <script src="{{ asset('js/shop.js') }}?v={{ filemtime(public_path('js/shop.js')) }}" defer></script>
  <style>
    /* Buyer navbar — taste-page language (ivory ground, deep teal primary,
       flat fills only). Scoped under .buyerbar so landing/shop styles keep theirs. */
    .buyerbar.topbar{height:60px;background:#FBF9F6;border-bottom:1px solid rgba(15,76,92,.12);gap:14px;padding:0 20px}
    .buyerbar .hamburger{width:36px;height:36px;border-radius:999px;border:1px solid transparent;color:#0E4A57;font-size:15px}
    .buyerbar .hamburger:hover{background:rgba(22,105,122,.08);border-color:transparent;color:#0E4A57}
    .buyerbar .hamburger:focus-visible{outline:2px solid #0E4A57;outline-offset:2px}
    .buyerbar .brand{gap:10px}
    .buyerbar .brand-icon{width:34px;height:34px;border-radius:10px;border:1px solid rgba(15,76,92,.14);background:#fff}
    .buyerbar .brand-text{color:#0E4A57;font-weight:800;font-size:17px;letter-spacing:-.02em}
    .buyerbar .brand-sub{color:rgba(14,74,87,.68);font-size:10px;font-weight:700;letter-spacing:.14em;margin-left:2px}
    .buyerbar .search{height:42px;max-width:560px;background:#F0EEE9;border:1px solid transparent;border-radius:999px;padding:0 6px 0 14px;gap:8px}
    .buyerbar .search:focus-within{background:#fff;border-color:rgba(22,105,122,.4)}
    .buyerbar .search input{font-size:13px;color:#1B1B1E}
    .buyerbar .search-btn{background:#0E4A57;border-radius:999px;padding:9px 20px;font-size:13px}
    .buyerbar .search-btn:hover{background:#0B3D48}
    .buyerbar .topbar-link{color:#0E4A57;font-size:13px;font-weight:600;padding:8px 12px;border-radius:10px}
    .buyerbar .topbar-link svg{color:rgba(22,105,122,.7);transition:transform .2s ease,color .2s ease}
    .buyerbar .topbar-link:hover{background:rgba(22,105,122,.07);color:#0E4A57}
    .buyerbar .topbar-link:hover svg{transform:translateX(2px);color:#0E4A57}
    .buyerbar .topbar-link:focus-visible{outline:2px solid #0E4A57;outline-offset:2px}
    .buyerbar .badge{background:#0E4A57;font-variant-numeric:tabular-nums;min-width:18px;height:18px;font-size:10px;font-weight:700}
    .buyerbar .btn-login{border:1px solid rgba(15,76,92,.25);border-radius:999px;padding:9px 18px;color:#0E4A57;font-weight:700}
    .buyerbar .btn-login:hover{border-color:#0E4A57;background:rgba(22,105,122,.06);color:#0E4A57}
    .buyerbar .btn-register{background:#0E4A57;border-radius:999px;padding:10px 20px}
    .buyerbar .btn-register:hover{background:#0B3D48}
    .buyerbar .profile-btn{border:1px solid rgba(15,76,92,.16);background:#fff;padding:4px 12px 4px 4px;gap:9px}
    .buyerbar .profile-btn:hover{border-color:rgba(22,105,122,.45);background:#fff}
    .buyerbar .profile-btn:focus-visible{outline:2px solid #0E4A57;outline-offset:2px}
    .buyerbar .profile-avatar{box-shadow:0 0 0 2px #EAF4F3}
    .buyerbar #profileDrop{border:1px solid rgba(15,76,92,.12);border-radius:16px;box-shadow:0 10px 30px rgba(10,47,58,.18)}
  </style>
</head>
<body>
  @php
    $hideSidebar = (request()->is('/') || request()->is('products')) && !session('buyer');
  @endphp
  <header class="topbar buyerbar">
    @if(!$hideSidebar)<button id="buyerMenuBtn" class="hamburger" title="Toggle menu" aria-label="Toggle menu" aria-expanded="true">&#9776;</button>@endif
    <a href="{{ url('/shop') }}" class="brand">
      <div class="brand-icon"><img src="{{ asset('storage/logos/logo.png') }}" onerror="this.onerror=null;this.src='{{ asset('images/logo.png') }}'" alt="Invoiz" style="width:100%;height:100%;object-fit:cover;"></div>
      <span class="brand-text">INVOIZ</span>
      <span class="brand-sub">ONLINE SHOPPING</span>
    </a>
    <form method="GET" action="{{ url('/shop') }}" class="search">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--text3)" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      <input type="text" name="search" value="{{ request('search') }}" placeholder="Search products...">
      <button type="submit" class="search-btn">Search</button>
    </form>
    @php
      $buyer = session('buyer');
      $buyerPicturePath = $buyer ? \App\Models\User::whereKey($buyer['id'])->value('profile_picture') : null;
      $buyerAvatar = $buyerPicturePath ? asset('storage/' . ltrim($buyerPicturePath, '/')) : null;
      $cartCount = 0;
      $wishlistCount = 0;
      $unreadMsgs = 0;
      $pendingOrders = 0;
      if ($buyer) {
          $cartCount = \App\Models\Cart::itemsFor($buyer['id'])->sum('quantity');
          $wishlistCount = \App\Models\Favorite::where('buyer_id', $buyer['id'])->count();
          $convIds = \App\Models\Conversation::where('buyer_id', $buyer['id'])->pluck('id');
          $unreadMsgs = \App\Models\Message::whereIn('conversation_id', $convIds)
              ->where('sender_id', '!=', $buyer['id'])->where('is_read', 0)->count();
          $pendingOrders = \App\Models\Order::where('buyer_id', $buyer['id'])
              ->where('status', 'pending')->count();
      }
      $cartBadge = $cartCount > 99 ? '99+' : (string)$cartCount;
      $notifCount = $buyer ? \App\Support\BuyerNotifications::count($buyer['id']) : 0;
    @endphp
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
  <div class="shell">
    @if(!$hideSidebar)
    <div class="sb-scrim" id="buyerScrim" hidden></div>
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
          @php($bName = trim(($buyer['first_name'] ?? '').' '.($buyer['last_name'] ?? '')))
          @php($bInitials = strtoupper(substr($buyer['first_name'] ?? '', 0, 1).substr($buyer['last_name'] ?? '', 0, 1)) ?: 'B')
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
    <div class="main">
      <main class="container" style="padding-top:0;flex:1">
        @yield('content')
      </main>
      <footer class="footer buyerfoot">
        <div class="buyerfoot-mark">INVOIZ</div>
        <div>&copy; 2026 Invoiz Online Shopping. All rights reserved.</div>
      </footer>
    </div>
  </div>
  <script src="{{ asset('frontend/js/app.js') }}"></script>
  @include('partials.support-chat')
</body>
</html>
