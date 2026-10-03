<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Invoiz Admin</title>
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
    .sidebar { width: 270px; background: var(--card); border-right: 1px solid var(--border); padding: 20px 14px; position: sticky; top: 0; height: 100vh; overflow-y: auto; overflow-x: hidden; display: flex; flex-direction: column; transition: width .2s ease; }
    .sidebar.collapsed { width: 72px; }
    .sidebar.collapsed .brand, .sidebar.collapsed .acct { justify-content: center; align-items: center; gap: 0; padding-left: 0; padding-right: 0; }
    .sidebar.collapsed .brand { padding-top: 8px; padding-bottom: 14px; }
    .sidebar.collapsed .acct { padding-top: 12px; padding-bottom: 12px; }
    .sidebar.collapsed .brand-text, .sidebar.collapsed .acct-text { display: none; }
    .sidebar.collapsed .brand-name, .sidebar.collapsed .brand-sub, .sidebar.collapsed .acct-name, .sidebar.collapsed .acct-role,
    .sidebar.collapsed .nav-head, .sidebar.collapsed .nav-text { font-size: 0; }
    .sidebar.collapsed .nav-link { justify-content: center; padding: 9px 0; gap: 0; font-size: 0; }
    .sidebar.collapsed .nav-head { margin: 0; padding: 0; }
    .sidebar.collapsed .sidebar-footer { font-size: 0; }
    .sidebar.collapsed .logout-btn { justify-content: center; padding: 10px 0; font-size: 0; }
    .main { flex: 1; display: flex; flex-direction: column; min-width: 0; }
    .topbar { height: 64px; background: var(--card); border-bottom: 1px solid var(--border); box-shadow: 0 1px 2px rgba(16,24,40,.03); display: flex; align-items: center; gap: 14px; padding: 0 20px; position: sticky; top: 0; z-index: 10; }
    .hamburger { width: 40px; height: 40px; border: 1px solid var(--border); background: #fff; border-radius: 10px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: transform .15s, background .15s; flex-shrink: 0; }
    .hamburger:hover { background: var(--surface-soft); transform: scale(1.05); }
    .hamburger svg { width: 20px; height: 20px; stroke: var(--primary); fill: none; stroke-width: 2; stroke-linecap: round; }
    .topbar-title { font-size: 15px; font-weight: 700; color: var(--text-primary); letter-spacing: -.3px; line-height: 1.2; }
    .topbar-sub { font-size: 12px; color: var(--text-secondary); }
    .acct { display: flex; align-items: center; gap: 12px; padding: 4px 8px 16px; border-bottom: 1px solid var(--surface-soft); margin-bottom: 14px; }
    .acct-ava { width: 42px; height: 42px; border-radius: 50%; background: linear-gradient(180deg, var(--primary) 0%, var(--primary-dark) 100%); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 18px; flex-shrink: 0; overflow: hidden; }
    .acct-ava img { width: 100%; height: 100%; object-fit: cover; }
    .acct-name { font-size: 14px; font-weight: 800; color: var(--text-primary); line-height: 1.2; }
    .acct-role { font-size: 11px; color: var(--text-secondary); margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .brand { display: flex; align-items: center; gap: 12px; padding: 4px 8px 18px; border-bottom: 1px solid var(--surface-soft); margin-bottom: 16px; }
    .brand-badge { display: inline-flex; align-items: center; justify-content: center; width: 42px; height: 42px; border-radius: 12px; background: linear-gradient(180deg, var(--primary) 0%, var(--primary-dark) 100%); color: #fff; font-weight: 800; font-size: 20px; flex-shrink: 0; }
    .brand-name { font-size: 17px; font-weight: 800; color: var(--primary-dark); letter-spacing: -0.4px; line-height: 1.2; }
    .brand-sub { font-size: 10px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1.6px; font-weight: 700; margin-top: 2px; }
    .brand-logo-img { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; background: #fff; border: 2px solid var(--border); box-shadow: 0 2px 10px rgba(0,0,0,.08); display: block; flex-shrink: 0; }
    .sidebar.collapsed .brand-logo-img { align-self: center; }
    .nav-section { margin-bottom: 18px; }
    .nav-head { font-size: 10px; font-weight: 700; letter-spacing: 1.8px; text-transform: uppercase; color: #9CA3AF; padding: 0 10px; margin-bottom: 6px; }
    .nav-link { display: flex; align-items: center; gap: 12px; color: #4B5563; text-decoration: none; padding: 9px 12px; border-radius: 10px; transition: background .15s, color .15s, transform .15s; font-size: 13.5px; font-weight: 600; letter-spacing: 0.01em; line-height: 1.4; margin-bottom: 2px; }
    .nav-link:hover { background: var(--surface-soft); color: var(--primary-dark); transform: translateX(2px); }
    .nav-link.active { background: var(--accent); color: var(--primary-dark); box-shadow: inset 3px 0 0 var(--primary); }
    .nav-icon { width: 17px; height: 17px; fill: #9CA3AF; flex-shrink: 0; }
    .nav-link:hover .nav-icon { fill: var(--primary); }
    .nav-link.active .nav-icon { fill: var(--primary); }
    .sidebar-foot { margin-top: auto; padding-top: 16px; border-top: 1px solid var(--surface-soft); }
    .logout-btn { display: flex; align-items: center; gap: 12px; width: 100%; border: none; background: #FDECEC; color: #B3261E; font-weight: 600; font-size: 13.5px; padding: 10px 12px; border-radius: 10px; cursor: pointer; font-family: inherit; transition: background .15s; }
    .logout-btn:hover { background: #FBDDDD; }
    .logout-btn svg { width: 17px; height: 17px; fill: #B3261E; flex-shrink: 0; }
    .sidebar-footer { margin-top: 12px; font-size: 11px; color: #B0B3B8; text-align: center; }
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
  </style>
</head>
<body>
  <div class="shell">
    <aside class="sidebar" id="sidebar">
      @php $acctLogo = public_path('storage/logos/invoiz-admin.png'); $hasAcctLogo = file_exists($acctLogo); @endphp
      <div class="acct">
        <div class="acct-ava">
          @if ($hasAcctLogo)
            <img src="{{ asset('storage/logos/invoiz-admin.png') }}" alt="logo">
          @else
            {{ mb_strtoupper(mb_substr(auth()->user()->name ?? 'A', 0, 1)) }}
          @endif
        </div>
        <div class="acct-text">
          <div class="acct-name">{{ auth()->user()->name ?? 'Administrator' }}</div>
          <div class="acct-role">{{ auth()->user()->email ?? '' }}</div>
        </div>
      </div>

      <div class="brand">
        <img src="{{ asset('images/logo.png') }}" alt="Invoiz logo" class="brand-logo-img" />
        <div class="brand-text">
          <div class="brand-name">Invoiz Admin</div>
          <div class="brand-sub">Control Panel</div>
        </div>
      </div>

      <div class="nav-section">
        <div class="nav-head">Overview</div>
        <a class="nav-link" href="/admin/dashboard">
          <svg class="nav-icon" viewBox="0 0 24 24"><path d="M3 13h8V3H3v10Zm10 8h8V11h-8v10Zm0-18v6h8V3h-8ZM3 21h8v-6H3v6Z"/></svg>
          Dashboard
        </a>
      </div>

      <div class="nav-section">
        <div class="nav-head">Management</div>
        <a class="nav-link" href="/admin/sellers">
          <svg class="nav-icon" viewBox="0 0 24 24"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-4.418 0-8 1.79-8 4v2h16v-2c0-2.21-3.582-4-8-4Z"/></svg>
          Manage Sellers
        </a>
        <a class="nav-link" href="/admin/buyers">
          <svg class="nav-icon" viewBox="0 0 24 24"><path d="M16 14c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4Zm-8 0c2.21 0 4-1.79 4-4S10.21 6 8 6 4 7.79 4 10s1.79 4 4 4Zm0 2c-2.67 0-8 1.34-8 4v2h8v-2c0-1.1.9-2 2-2h4c1.1 0 2 .9 2 2v2h8v-2c0-2.66-5.33-4-8-4H8Z"/></svg>
          Manage Buyers
        </a>
        <a class="nav-link" href="/admin/riders">
          <svg class="nav-icon" viewBox="0 0 24 24"><path d="M4 16V8H2V6h3V4h2v2h10V4h2v2h3v2h-2v8h-5v-2H9v2H4Zm2-8v8h2V8H6Zm10 8h2V8h-2v8Zm-5 0h2V8h-2v8Z"/></svg>
          Manage Riders
        </a>
        <a class="nav-link" href="/admin/products">
          <svg class="nav-icon" viewBox="0 0 24 24"><path d="M4 4h16v4H4V4Zm0 6h16v10H4V10Zm2 2v6h12v-6H6Z"/></svg>
          Manage Products
        </a>
        <a class="nav-link" href="/admin/categories">
          <svg class="nav-icon" viewBox="0 0 24 24"><path d="M4 4h7v7H4V4Zm9 0h7v7h-7V4ZM4 13h7v7H4v-7Zm9 0h7v7h-7v-7Z"/></svg>
          Manage Categories
        </a>
        <a class="nav-link" href="/admin/orders">
          <svg class="nav-icon" viewBox="0 0 24 24"><path d="M3 6h18v2H3V6Zm0 4h18v2H3v-2Zm0 4h12v2H3v-2Z"/></svg>
          Manage Orders
        </a>
      </div>

      <div class="nav-section">
        <div class="nav-head">Finance</div>
        <a class="nav-link" href="/admin/payments">
          <svg class="nav-icon" viewBox="0 0 24 24"><path d="M4 7h16v10H4V7Zm2 2v6h12V9H6Zm2 2h8v2H8v-2Z"/></svg>
          Manage Payments
        </a>
        <a class="nav-link" href="/admin/reports">
          <svg class="nav-icon" viewBox="0 0 24 24"><path d="M5 20h14V4H5v16Zm2-2v-6h3v6H7Zm5 0v-10h3v10h-3Zm5 0v-4h3v4h-3Z"/></svg>
          Reports
        </a>
        <a class="nav-link" href="/admin/commission">
          <svg class="nav-icon" viewBox="0 0 24 24"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm1.41 9.41L8 17 7 16l5-5V6h2v5.41Z"/></svg>
          Commission
        </a>
      </div>

      <div class="nav-section">
        <div class="nav-head">Support</div>
        <a class="nav-link" href="/admin/complaints">
          <svg class="nav-icon" viewBox="0 0 24 24"><path d="M1 21h22L12 2 1 21Zm12-3h-2v-2h2v2Zm0-4h-2v-4h2v4Z"/></svg>
          Complaints
        </a>
        <a class="nav-link" href="/admin/chat">
          <svg class="nav-icon" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2Zm0 14H5.17L4 17.17V4h16v12Z"/></svg>
          Chat / Messaging
        </a>
      </div>

      <div class="nav-section">
        <div class="nav-head">System</div>
        <a class="nav-link" href="/admin/settings">
          <svg class="nav-icon" viewBox="0 0 24 24"><path d="M12 8a4 4 0 1 0 4 4 4 4 0 0 0-4-4Zm8.94 4.5-1.07-.2a6.78 6.78 0 0 0-.6-1.47l.7-.81-1.5-1.5-.81.7A6.78 6.78 0 0 0 13.7 6.13l-.2-1.08H10.5l-.2 1.08a6.78 6.78 0 0 0-1.47.6l-.81-.7-1.5 1.5.7.81a6.78 6.78 0 0 0-.6 1.47l-1.07.2V12l1.07.2a6.78 6.78 0 0 0 .6 1.47l-.7.81 1.5 1.5.81-.7a6.78 6.78 0 0 0 1.47.6l.2 1.08h2.3l.2-1.08a6.78 6.78 0 0 0 1.47-.6l.81.7 1.5-1.5-.7-.81a6.78 6.78 0 0 0 .6-1.47l1.07-.2V12Z"/></svg>
          System Settings
        </a>
      </div>

      <div class="sidebar-foot">
        <form method="POST" action="{{ url('/admin/logout') }}">
          @csrf
          <button type="submit" class="logout-btn">
            <svg viewBox="0 0 24 24"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
            Logout
          </button>
        </form>
        <div class="sidebar-footer">Invoiz e-commerce admin system.</div>
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
      if (localStorage.getItem('invSidebarCollapsed') === '1') sb.classList.add('collapsed');
      hb.addEventListener('click', function () {
        sb.classList.toggle('collapsed');
        localStorage.setItem('invSidebarCollapsed', sb.classList.contains('collapsed') ? '1' : '0');
      });
    })();

    (function () {
      var path = location.pathname.replace(/\/+$/, '') || '/';
      var best = null;
      document.querySelectorAll('.nav-link[href]').forEach(function (a) {
        a.classList.remove('active');
        var href = a.getAttribute('href').replace(/\/+$/, '');
        if (!href || href === '#') return;
        if ((path === href || path.indexOf(href + '/') === 0) &&
            (!best || href.length > best.getAttribute('href').replace(/\/+$/, '').length)) {
          best = a;
        }
      });
      if (best) best.classList.add('active');
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