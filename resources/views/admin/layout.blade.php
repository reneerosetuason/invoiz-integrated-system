<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Invoiz Admin</title>
  <link rel="stylesheet" href="{{ asset('css/app-sidebar.css') }}?v={{ filemtime(public_path('css/app-sidebar.css')) }}">
  <script src="{{ asset('js/app-sidebar.js') }}?v={{ filemtime(public_path('js/app-sidebar.js')) }}" defer></script>
  <script>
    try {
      if (sessionStorage.getItem('invAdminSidebarIntroSeen') === '1') {
        document.documentElement.classList.add('admin-sidebar-seen');
      } else {
        sessionStorage.setItem('invAdminSidebarIntroSeen', '1');
      }
    } catch (e) {
      document.documentElement.classList.add('admin-sidebar-seen');
    }
  </script>
  <style>
    :root {
      --primary: #16697A;
      --primary-dark: #0E4A57;
      --secondary: #F0A202;
      --accent: #EAF4F3;
      --background: #F7F6F2;
      --card: #FFFFFF;
      --text-primary: #1B1B1E;
      --text-secondary: #6E6E73;
      --success: #2E8B57;
      --warning: #E05A33;
      --gold: #F5A623;
      --border: #E8E6E0;
      --surface-soft: #F0EEE9;
    }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: 'Segoe UI Variable Text', 'Segoe UI', system-ui, -apple-system, 'Helvetica Neue', Arial, sans-serif; background: linear-gradient(180deg, #F8F7F4 0%, #F3F1EB 100%) fixed; color: var(--text-primary); font-size: 14px; line-height: 1.55; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; text-rendering: optimizeLegibility; }
    ::placeholder { color: #9CA3AF; opacity: 1; }
    ::selection { background: rgba(22,105,122,.15); }
    ::-webkit-scrollbar { width: 9px; height: 9px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #DDD9CF; border-radius: 8px; border: 2px solid transparent; background-clip: content-box; }
    ::-webkit-scrollbar-thumb:hover { background: #CBC7BB; border: 2px solid transparent; background-clip: content-box; }
    h1, h2, h3 { margin: 0; line-height: 1.3; }
    p { line-height: 1.65; }
    .shell { display: flex; min-height: 100vh; }
    .sb2--admin.sidebar { width: 256px; min-width: 256px; flex: 0 0 256px; background: #FBF9F6; border-right: 0; padding: 0; position: sticky; top: 0; height: 100vh; height: 100dvh; min-height: 0; overflow: hidden; transition: none; }
    .sb2--admin.sidebar.collapsed { width: 72px; min-width: 72px; flex-basis: 72px; }
    .main { flex: 1; display: flex; flex-direction: column; min-width: 0; }
    .topbar { height: 64px; background: var(--card); border-bottom: 1px solid var(--border); box-shadow: 0 1px 2px rgba(16,24,40,.03); display: flex; align-items: center; gap: 14px; padding: 0 20px; position: sticky; top: 0; z-index: 10; }
    .hamburger { width: 40px; height: 40px; border: 1px solid var(--border); background: #fff; border-radius: 10px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: transform .15s, background .15s; flex-shrink: 0; }
    .hamburger:hover { background: var(--surface-soft); transform: scale(1.05); }
    .hamburger svg { width: 20px; height: 20px; stroke: var(--primary); fill: none; stroke-width: 2; stroke-linecap: round; }
    .hamburger:focus-visible { outline: 2px solid #0E4A57; outline-offset: 2px; }
    @media (max-width: 1023.98px) { .hamburger { width: 44px; height: 44px; } }
    .topbar-title { font-size: 15px; font-weight: 700; color: var(--text-primary); letter-spacing: -.3px; line-height: 1.2; }
    .topbar-sub { font-size: 12px; color: var(--text-secondary); }
    .content { flex: 1; padding: 28px 32px; max-width: 1280px; }
    .card { background: var(--card); border: 1px solid var(--border); border-radius: 18px; padding: 24px; box-shadow: 0 1px 2px rgba(16,24,40,.04), 0 8px 24px -14px rgba(16,24,40,.10); }
    .page-title { margin-top: 0; margin-bottom: 16px; font-size: 22px; font-weight: 700; letter-spacing: -0.4px; line-height: 1.25; color: var(--text-primary); }
    .grid { display: grid; gap: 20px; }
    .grid-3 { grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
    .section { margin-top: 24px; }
    .section h2 { font-size: 17px; font-weight: 700; letter-spacing: -0.2px; margin-bottom: 12px; }
    .section p { margin: 0; color: var(--text-secondary); line-height: 1.6; font-size: 14px; }
    .stats { display: grid; gap: 14px; grid-template-columns: repeat(4, minmax(0, 1fr)); margin-top: 20px; }
    .stat-card { padding: 20px; border-radius: 16px; background: var(--card); border: 1px solid var(--border); box-shadow: 0 1px 2px rgba(16,24,40,.04); transition: transform .18s ease, box-shadow .18s ease; }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 12px 28px -14px rgba(16,24,40,.16); }
    .stat-card span { color: var(--text-secondary); font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; }
    .stat-card strong { display: block; margin-top: 8px; font-size: 21px; font-weight: 800; letter-spacing: -0.3px; line-height: 1.2; }
    .btn { display: inline-block; padding: 10px 20px; border-radius: 12px; font-weight: 600; font-size: 13.5px; letter-spacing: 0.01em; cursor: pointer; border: none; text-decoration: none; line-height: 1.2; min-height: 40px; transition: transform .15s ease, box-shadow .15s ease, background .15s ease; box-shadow: 0 1px 2px rgba(16,24,40,.06); }
    .btn:hover { transform: translateY(-1px); box-shadow: 0 6px 16px -8px rgba(16,24,40,.28); }
    .btn:active { transform: translateY(0); box-shadow: 0 1px 2px rgba(16,24,40,.06); }
    .btn-primary { background: var(--primary); color: #fff; }
    .btn-primary:hover { background: var(--primary-dark); }
    .btn-secondary { background: transparent; color: var(--primary); border: 1px solid var(--primary); }
    .btn-secondary:hover { background: var(--accent); }
    .btn-success { background: var(--success); color: #fff; }
    .btn-danger { background: var(--warning); color: #fff; }
    .btn-warn { background: var(--secondary); color: #fff; }
    .btn-neutral { background: #fff; color: var(--text-primary); border: 1px solid var(--border); }
    .chip { display: inline-block; padding: 3px 12px; border-radius: 999px; font-size: 11.5px; font-weight: 600; letter-spacing: 0.02em; border: 1px solid var(--border); background: var(--card); }
    .chip-accent { background: var(--accent); color: var(--primary-dark); border-color: var(--accent); }
    input[type="text"], input[type="email"], input[type="password"], input[type="date"], input[type="search"], select, textarea { background: var(--surface-soft); border: none; border-radius: 14px; padding: 12px 16px; font-family: inherit; font-size: 14px; color: var(--text-primary); width: 100%; outline: none; }
    input:focus, select:focus, textarea:focus { outline: none; box-shadow: 0 0 0 3px rgba(22,105,122,.12); background: #fff; }
    label { font-weight: 600; font-size: 13px; display: block; margin-bottom: 6px; color: var(--text-primary); }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; padding: 11px 8px; border-bottom: 1px solid var(--border); font-size: 11.5px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.06em; }
    td { padding: 12px 8px; border-bottom: 1px solid var(--border); font-size: 13.5px; line-height: 1.5; vertical-align: middle; }
    .alert { padding: 12px 16px; border-radius: 12px; margin-bottom: 16px; font-size: 13.5px; line-height: 1.5; }
    .alert-success { background: #E8F5EE; color: #1D6B43; border: 1px solid #BFE3D0; }
    .alert-warn { background: #FDF3E3; color: #8A5A00; border: 1px solid #F7DFB0; }
    .pagination { margin-top: 16px; }
    .pagination .page-link, .pagination a { color: var(--primary); text-decoration: none; margin-right: 6px; font-weight: 600; }
    .pagination .disabled, .pagination .disabled span { color: var(--text-secondary); }
    .confirm-overlay { position: fixed; inset: 0; background: rgba(16,24,40,.45); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); display: none; align-items: center; justify-content: center; z-index: 9999; padding: 20px; }
    .confirm-overlay.open { display: flex; }
    .confirm-card { background: var(--card); border: 1px solid var(--border); border-radius: 20px; padding: 28px 24px; max-width: 420px; width: 100%; text-align: center; box-shadow: 0 20px 60px rgba(16,24,40,.18), 0 1px 2px rgba(16,24,40,.06); animation: confirmPop .2s ease; }
    @keyframes confirmPop { from { transform: scale(.96); opacity: 0; } to { transform: scale(1); opacity: 1; } }
    .confirm-icon { width: 56px; height: 56px; border-radius: 50%; background: #FDF3E3; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; }
    .confirm-icon.suspend { background: #FCE9E4; }
    .confirm-icon.suspend svg { fill: #991b1b; }
    .confirm-icon svg { width: 26px; height: 26px; fill: #92400e; }
    .confirm-card h3 { font-size: 18px; font-weight: 800; letter-spacing: -.3px; margin-bottom: 8px; color: var(--text-primary); }
    .confirm-card p { font-size: 13.5px; color: var(--text-secondary); line-height: 1.6; margin: 0 0 24px; }
    .confirm-actions { display: flex; gap: 10px; justify-content: center; }
    .confirm-actions .btn { min-width: 110px; border-radius: 12px; padding: 10px 20px; font-weight: 700; }

    /* Admin adopts the shared Seller/Buyer/Logistics sidebar primitives. */
    .sb2--admin { background: #FBF9F6; border-right: 0; }
    .sb2--admin .sb-brand-header { height: 72px; flex: 0 0 72px; display: flex; align-items: center; padding: 0 16px; }
    .sb2--admin .sb-logo-row { display: flex; align-items: center; gap: 10px; min-width: 0; }
    .sb2--admin .sb-logo { width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
    .sb2--admin .sb-logo img { width: 36px; height: 36px; border-radius: 10px; object-fit: cover; }
    .sb2--admin .sb-brand-copy { display: block; min-width: 0; }
    .sb2--admin .sb-brand-name { display: block; color: #0E4A57; font-size: 20px; font-weight: 800; letter-spacing: -.025em; line-height: 1.15; }
    .sb2--admin .sb-brand-sub { display: block; margin-top: 3px; color: #46676B; font-size: 12px; font-weight: 500; line-height: 1.2; }
    .sb2--admin .sidebar-nav { padding: 8px 10px 32px; scrollbar-gutter: stable; scrollbar-width: thin; scrollbar-color: transparent transparent; overscroll-behavior: contain; }
    .sb2--admin .sidebar-nav::-webkit-scrollbar { width: 6px; }
    .sb2--admin .sidebar-nav::-webkit-scrollbar-thumb { background: transparent; border-radius: 999px; }
    .sb2--admin .sidebar-nav:hover, .sb2--admin .sidebar-nav:focus-within { scrollbar-color: rgba(14,74,87,.35) transparent; }
    .sb2--admin .sidebar-nav:hover::-webkit-scrollbar-thumb, .sb2--admin .sidebar-nav:focus-within::-webkit-scrollbar-thumb { background: rgba(14,74,87,.28); }
    .sb2--admin .admin-nav-group:first-of-type .nav-section { margin-top: 16px; }
    .sb2--admin .nav-section { margin: 24px 0 8px; padding: 0 12px; color: #46676B; font-size: 12px; font-weight: 600; letter-spacing: 0; text-transform: none; }
    .sb2--admin .sidebar-nav a { transition: transform .2s ease, opacity .2s ease; }
    .sb2--admin .sidebar-nav a svg.nav-icon { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 1.5; transition: transform .2s ease, opacity .2s ease; }
    .sb2--admin .sidebar-nav a.active { box-shadow: none; }
    .sb2--admin .sidebar-foot { flex: 0 0 auto; margin-top: auto; padding: 12px 12px 12px; }
    .sb2--admin .sb-pill { box-shadow: none; transition: transform .2s ease, opacity .2s ease; }
    .sb2--admin .sb-avatar { background: #EAF4F3; color: #0E4A57; box-shadow: none; outline: 2px solid rgba(255,255,255,.45); outline-offset: 0; }
    .sb2--admin .sb-avatar-fallback { background: #EAF4F3; color: #0E4A57; }
    .sb2--admin .sb-role { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .sb2--admin .sb-version { margin-top: 8px; color: #56696B; }
    .sb2--admin .sb-logout svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 1.5; }
    .sb2--admin.collapsed { width: 72px; min-width: 72px; flex-basis: 72px; }
    .sb2--admin.collapsed .sb-hide-collapsed { display: none !important; }
    .sb2--admin.collapsed .admin-nav-group { display: contents; }
    .sb2--admin.collapsed .sidebar-nav { display: grid; justify-items: center; align-content: start; gap: 8px; }
    .sb2--admin.collapsed .sidebar-nav a { width: 44px; height: 44px; min-height: 44px; padding: 0; justify-content: center; gap: 0; }
    .sb2--admin.collapsed .sidebar-nav a.active::before { left: -14px; top: 10px; bottom: 10px; }
    .sb2--admin.collapsed .sb-brand-header { justify-content: center; padding: 0; }
    .sb2--admin.collapsed .sb-logo-row { justify-content: center; }
    .sb2--admin.collapsed .sb-foot { display: flex; flex-direction: column; align-items: center; padding: 12px 8px; }
    .sb2--admin.collapsed .sb-pill { width: 56px; height: auto; min-height: 56px; border-radius: 20px; padding: 8px 0; margin: 0 auto; flex-direction: column; gap: 4px; justify-content: center; }
    .sb2--admin.collapsed .sb-who { display: flex; width: 44px; height: 44px; min-height: 44px; justify-content: center; flex: 0 0 44px; }
    .sb2--admin.collapsed .sb-fade { display: none; }
    .sb2--admin.collapsed .sb-avatar { width: 32px; height: 32px; }
    .sb2--admin.collapsed .sb-version { display: none; }
    .sb2--admin .sb-who, .sb2--admin .sb-logout { transition: transform .2s ease, opacity .2s ease; }
    .sb2--admin .sb-who:focus-visible, .sb2--admin .sb-logout:focus-visible { outline: 2px solid #fff; outline-offset: 2px; }
    .sb2--admin .sb-logout:hover .sb-logout-circle { background: rgba(255,255,255,.12); }
    .sb2--admin .sb-logout .sb-logout-circle { transition: transform .2s ease, opacity .2s ease; }
    .sb2--admin .sb-logout:hover svg { transform: translateX(2px); }
    .sb2--admin .sb-logout:focus-visible { outline: 2px solid #fff; outline-offset: 2px; }

    @media (prefers-reduced-motion: no-preference) {
      html.admin-sidebar-seen .sb2--admin.sb-enter .sidebar-nav a { opacity: 1; animation: none; }
      html.admin-sidebar-seen .sb2--admin.sb-enter .sb-pill { opacity: 1; animation: none; }
      html:not(.admin-sidebar-seen) .sb2--admin.sb-enter .admin-nav-group:nth-of-type(1) .nav-link:nth-of-type(1) { animation-delay: 0s; }
      html:not(.admin-sidebar-seen) .sb2--admin.sb-enter .admin-nav-group:nth-of-type(2) .nav-link:nth-of-type(1) { animation-delay: .03s; }
      html:not(.admin-sidebar-seen) .sb2--admin.sb-enter .admin-nav-group:nth-of-type(2) .nav-link:nth-of-type(2) { animation-delay: .06s; }
      html:not(.admin-sidebar-seen) .sb2--admin.sb-enter .admin-nav-group:nth-of-type(2) .nav-link:nth-of-type(3) { animation-delay: .09s; }
      html:not(.admin-sidebar-seen) .sb2--admin.sb-enter .admin-nav-group:nth-of-type(2) .nav-link:nth-of-type(4) { animation-delay: .12s; }
      html:not(.admin-sidebar-seen) .sb2--admin.sb-enter .admin-nav-group:nth-of-type(2) .nav-link:nth-of-type(5) { animation-delay: .15s; }
      html:not(.admin-sidebar-seen) .sb2--admin.sb-enter .admin-nav-group:nth-of-type(2) .nav-link:nth-of-type(6) { animation-delay: .18s; }
      html:not(.admin-sidebar-seen) .sb2--admin.sb-enter .admin-nav-group:nth-of-type(3) .nav-link:nth-of-type(1) { animation-delay: .21s; }
      html:not(.admin-sidebar-seen) .sb2--admin.sb-enter .admin-nav-group:nth-of-type(3) .nav-link:nth-of-type(2) { animation-delay: .24s; }
      html:not(.admin-sidebar-seen) .sb2--admin.sb-enter .admin-nav-group:nth-of-type(3) .nav-link:nth-of-type(3) { animation-delay: .27s; }
      html:not(.admin-sidebar-seen) .sb2--admin.sb-enter .admin-nav-group:nth-of-type(4) .nav-link:nth-of-type(1) { animation-delay: .30s; }
      html:not(.admin-sidebar-seen) .sb2--admin.sb-enter .admin-nav-group:nth-of-type(4) .nav-link:nth-of-type(2) { animation-delay: .33s; }
      html:not(.admin-sidebar-seen) .sb2--admin.sb-enter .admin-nav-group:nth-of-type(5) .nav-link:nth-of-type(1) { animation-delay: .36s; }
    }
  </style>
</head>
<body>
  <div class="shell">
    <aside class="sidebar sb2 sb2--admin" id="sidebar">
      @php
        $acctLogo = public_path('storage/logos/invoiz-admin.png');
        $hasAcctLogo = file_exists($acctLogo);
        $adminName = auth()->user()->name ?? 'Administrator';
        $adminEmail = auth()->user()->email ?? '';
      @endphp

      <div class="sb-brand-header">
        <div class="sb-logo-row">
          <span class="sb-logo" data-tip="INVOIZ"><img src="{{ asset('images/logo.png') }}" alt="INVOIZ"></span>
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
          <a class="nav-link" href="/admin/riders" title="Manage Riders">
            <x-icon name="truck" class="nav-icon" aria-hidden="true" />
            <span class="nav-text sb-label sb-hide-collapsed">Manage Riders</span>
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
    <div class="main">
      <header class="topbar">
        <button class="hamburger" type="button" aria-label="Toggle sidebar">
          <svg viewBox="0 0 24 24"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
        </button>
        <div>
          <div class="topbar-title">Invoiz Admin</div>
          <div class="topbar-sub">Control Panel</div>
        </div>
      </header>
      <main class="content">
        @yield('content')
      </main>
    </div>
  </div>
  <script>
    (function () {
      var sb = document.getElementById('sidebar');
      var hb = document.querySelector('.hamburger');
      if (localStorage.getItem('invSidebarCollapsed') === '1') sb.classList.add('collapsed', 'sidebar-collapsed');
      hb.addEventListener('click', function () {
        var collapsed = sb.classList.toggle('collapsed');
        sb.classList.toggle('sidebar-collapsed', collapsed);
        localStorage.setItem('invSidebarCollapsed', collapsed ? '1' : '0');
      });
    })();

    (function () {
      var path = location.pathname.replace(/\/+$/, '') || '/';
      var best = null;
      document.querySelectorAll('.nav-link[href]').forEach(function (a) {
        a.classList.remove('active');
        a.removeAttribute('aria-current');
        var href = a.getAttribute('href').replace(/\/+$/, '');
        if (!href || href === '#') return;
        if ((path === href || path.indexOf(href + '/') === 0) &&
            (!best || href.length > best.getAttribute('href').replace(/\/+$/, '').length)) {
          best = a;
        }
      });
      if (best) {
        best.classList.add('active');
        best.setAttribute('aria-current', 'page');
      }
    })();
  </script>
  <div id="confirmModal" class="confirm-overlay" role="dialog" aria-modal="true">
    <div class="confirm-card">
      <div id="confirmIcon" class="confirm-icon">
        <svg viewBox="0 0 24 24"><path d="M1 21h22L12 2 1 21Zm12-3h-2v-2h2v2Zm0-4h-2v-4h2v4Z"/></svg>
      </div>
      <h3 id="confirmTitle">Confirm action</h3>
      <p id="confirmMsg">Are you sure you want to proceed?</p>
      <div class="confirm-actions">
        <button type="button" id="confirmCancel" class="btn btn-neutral">Cancel</button>
        <button type="button" id="confirmOk" class="btn btn-danger">OK</button>
      </div>
    </div>
  </div>
  <script>
    (function () {
      var overlay = document.getElementById('confirmModal');
      var titleEl = document.getElementById('confirmTitle');
      var msgEl = document.getElementById('confirmMsg');
      var okBtn = document.getElementById('confirmOk');
      var cancelBtn = document.getElementById('confirmCancel');
      var iconEl = document.getElementById('confirmIcon');
      var pendingForm = null;
      window.openConfirm = function (opts) {
        titleEl.textContent = opts.title || 'Confirm action';
        msgEl.textContent = opts.message || 'Are you sure?';
        okBtn.textContent = opts.okText || 'OK';
        okBtn.className = 'btn ' + (opts.okClass || 'btn-danger');
        okBtn.style.minWidth = '110px';
        iconEl.className = 'confirm-icon' + (opts.iconClass ? ' ' + opts.iconClass : '');
        pendingForm = opts.form || null;
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
        // trap focus
        cancelBtn.focus();
        if (opts.onConfirm) {
          pendingForm = null;
          okBtn._cb = opts.onConfirm;
        } else {
          okBtn._cb = null;
        }
      };
      function closeConfirm() {
        overlay.classList.remove('open');
        document.body.style.overflow = '';
        pendingForm = null;
        okBtn._cb = null;
      }
      cancelBtn.addEventListener('click', closeConfirm);
      overlay.addEventListener('click', function (e) { if (e.target === overlay) closeConfirm(); });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && overlay.classList.contains('open')) closeConfirm(); });
      okBtn.addEventListener('click', function () {
        var cb = okBtn._cb;
        var form = pendingForm;
        closeConfirm();
        if (cb) cb();
        else if (form) form.submit();
      });
      // Intercept forms with data-confirm
      document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.hasAttribute('data-confirm')) return;
        e.preventDefault();
        var msg = form.getAttribute('data-confirm');
        var title = form.getAttribute('data-confirm-title') || 'Confirm action';
        var okText = form.getAttribute('data-confirm-ok') || 'OK';
        var okClass = form.getAttribute('data-confirm-class') || 'btn-danger';
        var iconClass = form.getAttribute('data-confirm-icon') || '';
        openConfirm({ title: title, message: msg, okText: okText, okClass: okClass, iconClass: iconClass, form: form });
      });
    })();
  </script>
  <script src="{{ asset('js/table-enhancer.js') }}"></script>
</body>
</html>
