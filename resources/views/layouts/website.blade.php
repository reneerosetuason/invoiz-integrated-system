<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Invoiz — Online Shopping</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,450;0,9..144,560;0,9..144,650;1,9..144,560&family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('frontend/css/app.css') }}">
  <link rel="stylesheet" href="{{ asset('frontend/css/theme.css') }}">
</head>
<body>
  @php
    $hideSidebar = (request()->is('/') || request()->is('products')) && !session('buyer');
  @endphp
  <header class="topbar">
    @if(!$hideSidebar)<button class="hamburger" onclick="toggleSidebar()" title="Toggle menu">&#9776;</button>@endif
    <a href="{{ url('/') }}" class="brand">
      <div class="brand-icon"><img src="{{ asset('storage/logos/logo.png') }}" onerror="this.onerror=null;this.src='{{ asset('images/logo.png') }}'" alt="Invoiz" style="width:100%;height:100%;object-fit:cover;"></div>
      <span class="brand-text">Invoiz</span>
      <span class="brand-sub">ONLINE SHOPPING</span>
    </a>
    <form method="GET" action="{{ url('/') }}" class="search">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--text3)" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      <input type="text" name="search" value="{{ request('search') }}" placeholder="Search products...">
      <button type="submit" class="search-btn">Search</button>
    </form>
    @php
      $buyer = session('buyer');
      $cartCount = 0;
      $unreadMsgs = 0;
      $pendingOrders = 0;
      if ($buyer) {
          $cartCount = \App\Models\Cart::itemsFor($buyer['id'])->sum('quantity');
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
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
      Cart @if($cartCount > 0)<span class="badge">{{ $cartBadge }}</span>@endif
    </a>
    <a href="{{ url('/orders') }}" class="topbar-link">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      Orders
    </a>
    @if($buyer)
      <div style="position:relative">
        <button onclick="document.getElementById('profileDrop').classList.toggle('open')" class="profile-btn">
          <img src="https://ui-avatars.com/api/?name={{ urlencode($buyer['first_name'].' '.$buyer['last_name']) }}&background=134e4a&color=fff&size=28" alt="" class="profile-avatar">
          <span style="font-weight:600;font-size:12px">{{ $buyer['first_name'] }}</span>
          <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
        <div id="profileDrop">
          <div style="display:flex;gap:10px;align-items:center;padding-bottom:12px;border-bottom:1px solid var(--border-light)">
            <img src="https://ui-avatars.com/api/?name={{ urlencode($buyer['first_name'].' '.$buyer['last_name']) }}&background=134e4a&color=fff&size=48" style="width:44px;height:44px;border-radius:50%">
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
    <aside id="sidebar" class="sidebar">
      <div class="sidebar-label" style="margin-top:0">Menu</div>
      <a class="side-link {{ request()->is('/') ? 'active' : '' }}" href="{{ url('/') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
        Home
      </a>
      <a class="side-link {{ request()->is('sale','products','product','product/*') ? 'active' : '' }}" href="{{ url('/sale') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.12 10.13 13.87 3.88a2 2 0 0 0-2.83 0L2.05 12.87a2 2 0 0 0 0 2.83l6.25 6.25a2 2 0 0 0 2.83 0l8.99-8.99a2 2 0 0 0 0-2.83z"/><circle cx="7.5" cy="7.5" r="1.5"/></svg>
        Sale
      </a>
      <a class="side-link {{ request()->is('cart') ? 'active' : '' }}" href="{{ url('/cart') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
        Cart
        @if($cartCount > 0)<span style="margin-left:auto;background:var(--danger);color:#fff;font-size:9px;font-weight:800;padding:2px 6px;border-radius:999px">{{ $cartBadge }}</span>@endif
      </a>
      <div class="sidebar-label">Categories</div>
      @foreach(\App\Models\Category::active()->orderBy('name')->limit(8)->get() as $sc)
        <a class="side-link {{ (string)request('category')===(string)$sc->id ? 'active' : '' }}" href="{{ url('/?category='.$sc->id) }}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41 11 3H4v7l9.59 9.59a2 2 0 0 0 2.82 0l4.18-4.18a2 2 0 0 0 0-2.82z"/><circle cx="7.5" cy="7.5" r="1.5"/></svg>
          {{ $sc->name }}
        </a>
      @endforeach
      @if($buyer)
        <div class="sidebar-label">Account</div>
        <a class="side-link {{ request()->is('orders') ? 'active' : '' }}" href="{{ url('/orders') }}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
          My Orders
          @if($pendingOrders > 0)<span style="margin-left:auto;background:var(--accent,#F0A202);color:#fff;font-size:9px;font-weight:800;padding:2px 6px;border-radius:999px">{{ $pendingOrders }}</span>@endif
        </a>
        <a class="side-link {{ request()->is('profile') ? 'active' : '' }}" href="{{ url('/profile') }}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          My Profile
        </a>
        <a class="side-link" href="{{ url('/logout') }}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Logout
        </a>
      @else
        <div class="sidebar-label">Account</div>
        <a class="side-link {{ request()->is('login') ? 'active' : '' }}" href="{{ url('/login') }}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
          Login
        </a>
        <a class="side-link {{ request()->is('register') ? 'active' : '' }}" href="{{ url('/register') }}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
          Register
        </a>
      @endif
      <div class="sidebar-label">More</div>
      <a class="side-link {{ request()->is('messages','chat','chat/*') ? 'active' : '' }}" href="{{ url('/messages') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        Messages
        @if($unreadMsgs > 0)<span style="margin-left:auto;background:var(--danger);color:#fff;font-size:9px;font-weight:800;padding:2px 6px;border-radius:999px">{{ $unreadMsgs > 99 ? '99+' : $unreadMsgs }}</span>@endif
      </a>
      <a class="side-link {{ request()->is('notifications') ? 'active' : '' }}" href="{{ url('/notifications') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        Notifications
        @if($notifCount > 0)<span style="margin-left:auto;background:var(--primary,#16697A);color:#fff;font-size:9px;font-weight:800;padding:2px 6px;border-radius:999px">{{ $notifCount > 99 ? '99+' : $notifCount }}</span>@endif
      </a>
      <div class="sidebar-footer">
        <div style="font-weight:600;color:var(--text2)">Invoiz v1</div>
        <div style="margin-top:2px">Desktop</div>
      </div>
    </aside>
    @endif
    <div class="main">
      <main class="container" style="padding-top:0;flex:1">
        @yield('content')
      </main>
      <footer class="footer">
        <div>&copy; 2026 Invoiz Online Shopping. All rights reserved.</div>
      </footer>
    </div>
  </div>
  <script src="{{ asset('frontend/js/app.js') }}"></script>
</body>
</html>
