<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Invoiz — Online Shopping</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    :root{
      --green:#134e4a;
      --green-light:#0d9488;
      --green-dark:#0f766e;
      --bg:#f5f5f5;
      --card:#fff;
      --text:#1a1a1a;
      --text2:#555;
      --text3:#999;
      --border:#e5e5e5;
      --border-light:#f0f0f0;
      --success:#10b981;
      --danger:#ef4444;
      --radius:10px;
    }
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Inter',system-ui,sans-serif;background:var(--bg);color:var(--text);font-size:13px;line-height:1.5}

    /* Topbar */
    .topbar{height:56px;background:#fff;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;padding:0 20px;position:sticky;top:0;z-index:50}
    .hamburger{width:32px;height:32px;background:transparent;border:1px solid var(--border);border-radius:6px;display:grid;place-items:center;cursor:pointer;color:var(--text);font-size:16px;transition:all .2s;flex-shrink:0}
    .hamburger:hover{background:var(--bg);border-color:var(--green);color:var(--green)}
    .brand{display:flex;align-items:center;gap:8px;text-decoration:none}
    .brand-icon{width:32px;height:32px;border:1px solid var(--border);border-radius:8px;display:grid;place-items:center;overflow:hidden}
    .brand-icon img{width:100%;height:100%;object-fit:cover}
    .brand-text{color:var(--text);font-weight:700;font-size:14px}
    .brand-sub{color:var(--text3);font-size:11px;font-weight:500;margin-left:4px}
    .search{flex:1;max-width:520px;height:38px;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius);display:flex;align-items:center;padding:0 10px;gap:6px;transition:all .2s}
    .search:focus-within{border-color:var(--green);background:#fff}
    .search input{flex:1;border:none;background:transparent;outline:none;font-size:12px;font-family:inherit;color:var(--text)}
    .search input::placeholder{color:var(--text3)}
    .search-btn{background:var(--green);color:#fff;border:none;border-radius:6px;padding:7px 16px;cursor:pointer;font-size:12px;font-weight:700;font-family:inherit;transition:all .2s}
    .search-btn:hover{background:var(--green-dark)}
    .topbar-link{color:var(--text2);text-decoration:none;font-weight:600;font-size:12px;display:flex;align-items:center;gap:6px;padding:6px 10px;border-radius:6px;transition:all .15s;white-space:nowrap}
    .topbar-link:hover{background:var(--bg);color:var(--green)}
    .btn-login{border:1px solid var(--border)}
    .btn-login:hover{border-color:var(--green);color:var(--green)}
    .btn-register{background:var(--green);color:#fff}
    .btn-register:hover{background:var(--green-dark)}
    .badge{position:absolute;top:-2px;right:-2px;min-width:16px;height:16px;padding:0 4px;border-radius:999px;background:var(--danger);color:#fff;font-size:9px;font-weight:800;display:grid;place-items:center}

    /* Shell */
    .shell{display:flex;min-height:calc(100vh - 56px)}

    /* Sidebar */
    .sidebar{width:220px;background:#fff;border-right:1px solid var(--border);padding:16px 0;position:sticky;top:56px;height:calc(100vh - 56px);overflow:auto;transition:all .3s;flex-shrink:0}
    .sidebar.collapsed{width:0;padding:0;overflow:hidden;border:none}
    .sidebar-label{font-size:10px;font-weight:700;letter-spacing:1px;color:var(--text3);text-transform:uppercase;padding:0 20px;margin-bottom:6px;margin-top:16px}
    .sidebar-label:first-child{margin-top:0}
    .side-link{display:flex;align-items:center;gap:10px;padding:8px 20px;text-decoration:none;color:var(--text2);font-weight:600;font-size:13px;transition:all .15s;border-left:3px solid transparent}
    .side-link:hover{background:#f8f8f8;color:var(--text)}
    .side-link.active{background:#f0fdf9;color:var(--green);border-left-color:var(--green);font-weight:700}
    .side-link svg{width:16px;height:16px;flex-shrink:0;opacity:.6}
    .side-link:hover svg,.side-link.active svg{opacity:1}
    .sidebar-footer{padding:16px 20px;border-top:1px solid var(--border-light);font-size:11px;color:var(--text3);text-align:center;margin-top:auto}

    /* Main */
    .main{flex:1;min-width:0;display:flex;flex-direction:column}
    .container{max-width:1200px;margin:0 auto;padding:0 20px;width:100%}

    /* Hero */
    .hero{background:linear-gradient(135deg,var(--green) 0%,var(--green-dark) 100%);border-radius:var(--radius);display:flex;overflow:hidden;margin:20px 0;box-shadow:0 4px 20px rgba(0,0,0,.15);position:relative}
    .hero-left{flex:1;padding:20px;color:#fff;display:flex;flex-direction:column;justify-content:center}
    .hero-right{width:240px;background:#fff;border-radius:var(--radius);margin:8px;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:16px;text-align:center}

    /* Grid */
    .grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
    @media(max-width:1100px){.grid{grid-template-columns:repeat(3,1fr)}}
    @media(max-width:800px){.grid{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:500px){.grid{grid-template-columns:1fr}}

    /* Cards */
    .card{background:#fff;border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;transition:all .2s;cursor:pointer}
    .card:hover{box-shadow:0 4px 16px rgba(0,0,0,.1);transform:translateY(-2px)}
    .card-thumb{height:130px;overflow:hidden;background:#f9f9f9;display:grid;place-items:center}
    .card-thumb img{width:100%;height:100%;object-fit:cover;transition:transform .3s}
    .card:hover .card-thumb img{transform:scale(1.03)}
    .card-body{padding:10px}
    .card-body h3{font-weight:700;font-size:13px;color:var(--green);margin:0 0 3px;line-height:1.3}
    .card-price{color:var(--green);font-weight:800;font-size:14px}
    .card-meta{font-size:10px;color:var(--text3);display:flex;align-items:center;gap:4px;margin-top:3px}
    .card-meta .cod{color:var(--success);font-weight:600}

    /* Buttons */
    .btn{padding:8px 16px;border-radius:var(--radius);border:none;font-weight:700;cursor:pointer;font-size:12px;font-family:inherit;transition:all .15s;display:inline-flex;align-items:center;gap:6px}
    .btn-primary{background:var(--green);color:#fff}
    .btn-primary:hover{background:var(--green-dark)}

    /* Category Pills */
    .cat-pills{display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:8px;padding:4px 0;margin:16px 0 8px;max-height:88px;overflow:hidden}
    .cat-pill{padding:8px 12px;border-radius:999px;background:#fff;border:1px solid var(--border);text-decoration:none;font-weight:600;font-size:12px;white-space:nowrap;transition:all .15s;color:var(--text2);text-align:center;overflow:hidden;text-overflow:ellipsis}
    .cat-pill:hover{border-color:var(--green);color:var(--green)}
    .cat-pill.active{background:var(--green);color:#fff;border-color:var(--green)}

    /* Footer */
    .footer{padding:16px;background:#fff;border-top:1px solid var(--border);margin-top:24px;text-align:center;color:var(--text3);font-size:11px}

    /* Profile Drop */
    .profile-btn{display:flex;align-items:center;gap:8px;background:transparent;border:1px solid var(--border);border-radius:999px;padding:4px 10px 4px 4px;cursor:pointer;transition:all .15s;color:var(--text)}
    .profile-btn:hover{border-color:var(--green);background:var(--bg)}
    .profile-avatar{width:28px;height:28px;border-radius:50%;border:2px solid var(--border)}
    #profileDrop{display:none;position:absolute;right:20px;top:52px;width:260px;background:#fff;border:1px solid var(--border);border-radius:var(--radius);box-shadow:0 8px 24px rgba(0,0,0,.12);padding:14px;z-index:50}
    #profileDrop.open{display:block;animation:dropIn .2s ease}
    @keyframes dropIn{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}

    /* Responsive */
    @media(max-width:768px){
      .search{display:none}
      .sidebar{position:fixed;left:0;top:56px;z-index:40;box-shadow:4px 0 20px rgba(0,0,0,.1)}
      .sidebar.collapsed{transform:translateX(-100%)}
      .hero{flex-direction:column}
      .hero-right{width:100%;margin:0;border-radius:0 0 var(--radius) var(--radius)}
    }
    @media(max-width:480px){
      .topbar{height:52px;padding:0 12px}
      .brand-text{font-size:13px}
    }

    ::-webkit-scrollbar{width:6px;height:6px}
    ::-webkit-scrollbar-track{background:transparent}
    ::-webkit-scrollbar-thumb{background:var(--border);border-radius:3px}
  </style>
</head>
<body>
  <header class="topbar">
    <button class="hamburger" onclick="document.getElementById('sidebar').classList.toggle('collapsed')" title="Toggle menu">&#9776;</button>
    <a href="{{ url('/') }}" class="brand">
      <div class="brand-icon"><img src="{{ asset('storage/logos/logo.png') }}" alt="Invoiz"></div>
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
      if ($buyer) {
          $cartCount = \App\Models\Cart::where('buyer_id', $buyer['id'])->sum('quantity');
      }
      $cartBadge = $cartCount > 99 ? '99+' : (string)$cartCount;
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
      <script>document.addEventListener('click',function(e){var d=document.getElementById('profileDrop');if(!e.target.closest('.profile-btn')&&d)d.classList.remove('open')})</script>
    @else
      <a href="{{ url('/login') }}" class="topbar-link btn-login">Login</a>
      <a href="{{ url('/register') }}" class="topbar-link btn-register">Register</a>
    @endif
  </header>
  <div class="shell">
    <aside id="sidebar" class="sidebar">
      <div class="sidebar-label" style="margin-top:0">Menu</div>
      <a class="side-link {{ request()->is('/') ? 'active' : '' }}" href="{{ url('/') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
        Home
      </a>
      <a class="side-link {{ request()->is('products') ? 'active' : '' }}" href="{{ url('/products') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
        Shop
      </a>
      <a class="side-link {{ request()->is('cart') ? 'active' : '' }}" href="{{ url('/cart') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
        Cart
        @if($cartCount > 0)<span style="margin-left:auto;background:var(--danger);color:#fff;font-size:9px;font-weight:800;padding:2px 6px;border-radius:999px">{{ $cartBadge }}</span>@endif
      </a>
      @if($buyer)
        <div class="sidebar-label">Account</div>
        <a class="side-link {{ request()->is('orders') ? 'active' : '' }}" href="{{ url('/orders') }}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
          My Orders
        </a>
        <a class="side-link {{ request()->is('profile') ? 'active' : '' }}" href="{{ url('/profile') }}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          My Profile
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
      <a class="side-link {{ request()->is('messages') ? 'active' : '' }}" href="{{ url('/messages') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        Messages
      </a>
      <a class="side-link {{ request()->is('notifications') ? 'active' : '' }}" href="{{ url('/notifications') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        Notifications
      </a>
      <div class="sidebar-footer">
        <div style="font-weight:600;color:var(--text2)">Invoiz v1</div>
        <div style="margin-top:2px">Desktop</div>
      </div>
    </aside>
    <div class="main">
      <main class="container" style="padding-top:0;flex:1">
        @yield('content')
      </main>
      <footer class="footer">
        <div>&copy; 2026 Invoiz Online Shopping. All rights reserved.</div>
      </footer>
    </div>
  </div>
</body>
</html>
