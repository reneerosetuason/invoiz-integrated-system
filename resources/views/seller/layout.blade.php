<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $title ?? 'Seller Center' }} — Invoiz Seller Center</title>
  <style>
    :root {
      --teal: #116B78;
      --teal-dark: #0C555F;
      --teal-light: #E6F1F2;
      --bg: #F5F6F7;
      --card: #FFFFFF;
      --border: #E5E5E5;
      --text: #1A202C;
      --muted: #6B7280;
      --green: #2E8B57;
      --orange: #E07B00;
      --red: #D64545;
      --blue: #3B82F6;
      --purple: #8B5CF6;
    }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: 'Inter', 'Segoe UI Variable Text', 'Segoe UI', system-ui, -apple-system, sans-serif; background: var(--bg); color: var(--text); font-size: 14px; line-height: 1.55; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; text-rendering: optimizeLegibility; }
    ::placeholder { color: #9CA3AF; opacity: 1; }
    h1, h2, h3 { margin: 0; line-height: 1.3; }
    p { line-height: 1.65; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; padding: 11px 10px; border-bottom: 1px solid var(--border); font-size: 11.5px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.06em; }
    td { padding: 12px 10px; border-bottom: 1px solid #F1F2F4; font-size: 13.5px; line-height: 1.5; vertical-align: middle; }
    a { text-decoration: none; color: inherit; }
    .app { display: flex; min-height: 100vh; }

    /* Sidebar */
    .sidebar { width: 300px; min-width: 300px; background: #fff; border-right: 1px solid var(--border); display: flex; flex-direction: column; position: fixed; top: 0; left: 0; bottom: 0; z-index: 40; transition: transform .25s ease; }
    .side-head { background: linear-gradient(150deg, #13808F 0%, #0C555F 100%); padding: 24px 22px 18px; }
    .brand-row { display: flex; align-items: center; gap: 12px; }
    .brand-logo { width: 42px; height: 42px; border-radius: 50%; background: #0A4650; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 19px; border: 2px solid rgba(255,255,255,.25); }
    .brand-name { color: #fff; font-weight: 800; font-size: 22px; letter-spacing: -.5px; line-height: 1; }
    .brand-label { color: rgba(255,255,255,.7); font-size: 10px; letter-spacing: 3px; text-transform: uppercase; font-weight: 600; margin-top: 3px; }
    .store-card { margin-top: 18px; background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.18); border-radius: 14px; padding: 12px; display: flex; align-items: center; gap: 12px; }
    .store-avatar { width: 40px; height: 40px; border-radius: 50%; background: #fff; color: var(--teal); font-weight: 800; font-size: 17px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .store-name { color: #fff; font-weight: 700; font-size: 14px; }
    .store-sub { color: rgba(255,255,255,.65); font-size: 12px; margin-top: 2px; }

    .side-nav { flex: 1; overflow-y: auto; padding: 16px 14px 10px; }
    .nav-group { margin-bottom: 16px; }
    .nav-group-label { font-size: 10px; letter-spacing: 2px; color: var(--muted); font-weight: 700; padding: 0 12px; margin-bottom: 6px; text-transform: uppercase; }
    .nav-link { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border-radius: 10px; font-size: 14px; font-weight: 500; letter-spacing: 0.01em; line-height: 1.4; color: var(--text); margin-bottom: 2px; transition: background .15s ease, color .15s ease; }
    .nav-link svg { width: 20px; height: 20px; stroke: currentColor; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; flex-shrink: 0; }
    .nav-link:hover { background: #F3F4F6; }
    .nav-link.active { background: var(--teal-light); color: var(--teal); font-weight: 700; }
    .nav-link.logout { color: var(--red); }
    .nav-link.logout:hover { background: #FDECEC; }
    .side-foot { padding: 14px 22px; border-top: 1px solid var(--border); color: var(--muted); font-size: 12px; }

    /* Main */
    .main { flex: 1; margin-left: 300px; min-width: 0; display: flex; flex-direction: column; }
    .topbar { background: #fff; border-bottom: 1px solid var(--border); box-shadow: 0 1px 2px rgba(16,24,40,.03); padding: 0 26px; height: 68px; display: flex; align-items: center; justify-content: space-between; }
    .topbar-left { display: flex; align-items: center; gap: 16px; }
    .hamburger { display: none; background: none; border: none; cursor: pointer; padding: 6px; }
    .hamburger svg { width: 24px; height: 24px; stroke: var(--text); fill: none; stroke-width: 2; stroke-linecap: round; }
    .page-title { font-size: 20px; font-weight: 700; letter-spacing: -.3px; line-height: 1.25; }
    .topbar-right { display: flex; align-items: center; gap: 18px; }
    .bell-wrap { position: relative; display: flex; align-items: center; }
    .bell { background: none; border: none; cursor: pointer; padding: 6px; }
    .bell svg { width: 22px; height: 22px; stroke: var(--muted); fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
    .badge { position: absolute; top: 0; right: 0; background: var(--red); color: #fff; font-size: 10px; font-weight: 700; min-width: 16px; height: 16px; border-radius: 999px; display: flex; align-items: center; justify-content: center; padding: 0 4px; }
    .user-chip { display: flex; align-items: center; gap: 10px; }
    .user-avatar { width: 38px; height: 38px; border-radius: 50%; background: var(--teal); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; }
    .user-meta { line-height: 1.2; }
    .user-name { font-size: 14px; font-weight: 700; }
    .user-role { font-size: 12px; color: var(--muted); }
    .drop-arrow { width: 14px; height: 14px; stroke: var(--muted); fill: none; stroke-width: 2; }

    ::-webkit-scrollbar { width: 9px; height: 9px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #DDE0E3; border-radius: 8px; border: 2px solid transparent; background-clip: content-box; }

    .content { flex: 1; padding: 26px; }

    .confirm-overlay { position: fixed; inset: 0; background: rgba(16,24,40,.45); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); display: none; align-items: center; justify-content: center; z-index: 9999; padding: 20px; }
    .confirm-overlay.open { display: flex; }
    .confirm-card { background: #fff; border: 1px solid #E5E5E5; border-radius: 20px; padding: 28px 24px; max-width: 420px; width: 100%; text-align: center; box-shadow: 0 20px 60px rgba(16,24,40,.18); animation: confirmPop .2s ease; }
    @keyframes confirmPop { from { transform: scale(.96); opacity: 0; } to { transform: scale(1); opacity: 1; } }
    .confirm-icon { width: 56px; height: 56px; border-radius: 50%; background: #FDF3E3; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; }
    .confirm-icon.suspend { background: #FCE9E4; }
    .confirm-card h3 { font-size: 18px; font-weight: 800; letter-spacing: -.3px; margin-bottom: 8px; color: var(--text); }
    .confirm-card p { font-size: 13.5px; color: var(--muted); line-height: 1.6; margin: 0 0 24px; }
    .confirm-actions { display: flex; gap: 10px; justify-content: center; }
    .confirm-actions button { min-width: 110px; padding: 10px 20px; border-radius: 12px; font-weight: 700; font-size: 13.5px; cursor: pointer; border: none; }
    .confirm-actions .btn-cancel { background: #fff; color: var(--text); border: 1px solid var(--border); }
    .confirm-actions .btn-ok { background: #D64545; color: #fff; }
    .confirm-actions .btn-ok.primary { background: #116B78; }
    .overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.35); z-index: 30; }

    @media (max-width: 1024px) {
      .sidebar { transform: translateX(-100%); }
      .sidebar.open { transform: translateX(0); }
      .hamburger { display: block; }
      .overlay.show { display: block; }
      .main { margin-left: 0; }
    }
    @media (max-width: 640px) {
      .content { padding: 16px; }
      .topbar { padding: 0 16px; }
      .user-meta { display: none; }
    }
  </style>
</head>
<body>
  <div class="app">
    <aside class="sidebar" id="sidebar">
      <div class="side-head">
        <div class="brand-row">
          <div class="brand-logo"><img src="{{ asset('images/logo.png') }}" alt="Invoiz logo" style="width:100%;height:100%;object-fit:cover;border-radius:50%;background:#fff;display:block;" /></div>
          <div>
            <div class="brand-name">Invoiz</div>
            <div class="brand-label">Seller Center</div>
          </div>
        </div>
        <div class="store-card">
          <div class="store-avatar">{{ strtoupper(mb_substr($seller->store_name, 0, 1)) }}</div>
          <div>
            <div class="store-name">{{ $seller->store_name }}</div>
            <div class="store-sub">{{ $storeSubtitle ?? 'General Merchandise' }}</div>
          </div>
        </div>
      </div>

      <nav class="side-nav">
        <div class="nav-group">
          <div class="nav-group-label">Main</div>
          <a class="nav-link {{ $active === 'dashboard' ? 'active' : '' }}" href="/seller/dashboard">
            <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
            Dashboard
          </a>
          <a class="nav-link {{ $active === 'orders' ? 'active' : '' }}" href="/seller/orders">
            <svg viewBox="0 0 24 24"><path d="M6 2h12l1 6H5l1-6Z"/><path d="M5 8h14l-1 12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 8Z"/><path d="M9 11v6M15 11v6"/></svg>
            Orders
          </a>
        </div>

        <div class="nav-group">
          <div class="nav-group-label">Store</div>
          <a class="nav-link {{ $active === 'inventory' ? 'active' : '' }}" href="/seller/inventory">
            <svg viewBox="0 0 24 24"><path d="M21 8l-9-5-9 5v8l9 5 9-5V8Z"/><path d="M3 8l9 5 9-5M12 13v8"/></svg>
            Inventory
          </a>
          <a class="nav-link {{ $active === 'products' ? 'active' : '' }}" href="/seller/products">
            <svg viewBox="0 0 24 24"><path d="M20.59 13.41 11 3H4v7l9.59 9.59a2 2 0 0 0 2.82 0l4.18-4.18a2 2 0 0 0 0-2.82z"/><circle cx="7.5" cy="7.5" r="1.5"/></svg>
            Products
          </a>
          <a class="nav-link {{ $active === 'vouchers' ? 'active' : '' }}" href="/seller/vouchers">
            <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M15 5v14M3 12h4a2 2 0 0 0 4 0 2 2 0 0 0 4 0h4"/></svg>
            Vouchers
          </a>
          <a class="nav-link {{ $active === 'feedback' ? 'active' : '' }}" href="/seller/feedback">
            <svg viewBox="0 0 24 24"><path d="M12 3l2.4 4.9 5.4.8-3.9 3.8.9 5.4-4.8-2.5-4.8 2.5.9-5.4L4.2 8.7l5.4-.8L12 3Z"/></svg>
            Customer Feedback
          </a>
          <a class="nav-link {{ $active === 'reports' ? 'active' : '' }}" href="/seller/reports">
            <svg viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>
            Reports
          </a>
        </div>

        <div class="nav-group">
          <div class="nav-group-label">Support</div>
          <a class="nav-link {{ $active === 'chat' ? 'active' : '' }}" href="/seller/chat">
            <svg viewBox="0 0 24 24"><path d="M21 11.5a8.5 8.5 0 0 1-12.4 7.5L3 21l2-5.6A8.5 8.5 0 1 1 21 11.5Z"/></svg>
            Chat / Messaging
          </a>
        </div>

        <div class="nav-group">
          <div class="nav-group-label">Account</div>
          <a class="nav-link {{ $active === 'notifications' ? 'active' : '' }}" href="/seller/notifications">
            <svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
            Notifications
          </a>
          <a class="nav-link {{ $active === 'account' ? 'active' : '' }}" href="/seller/account">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3h.1a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5h.1a1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9v.1a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/></svg>
            Account Management
          </a>
        </div>

        <form method="POST" action="/seller/logout">
          @csrf
          <button type="submit" class="nav-link logout" style="width:100%;border:none;background:none;cursor:pointer;text-align:left;font-family:inherit;font-size:15px;">
            <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
            Logout
          </button>
        </form>
      </nav>

      <div class="side-foot">Invoiz Seller · v1.0</div>
    </aside>

    <div class="overlay" id="overlay" onclick="toggleSidebar(false)"></div>

    <div class="main">
      <header class="topbar">
        <div class="topbar-left">
          <button class="hamburger" onclick="toggleSidebar(true)">
            <svg viewBox="0 0 24 24"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
          </button>
          <h1 class="page-title">{{ $title ?? 'Seller Center' }}</h1>
        </div>
        <div class="topbar-right">
          <div class="bell-wrap">
            <button class="bell" title="Notifications">
              <svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
            </button>
            <span class="badge">3</span>
          </div>
          <div class="user-chip">
            <div class="user-avatar">{{ strtoupper(mb_substr($userName, 0, 1)) }}</div>
            <div class="user-meta">
              <div class="user-name">{{ $userName }}</div>
              <div class="user-role">Seller</div>
            </div>
            <svg class="drop-arrow" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
          </div>
        </div>
      </header>

      <main class="content">
        @yield('content')
      </main>
    </div>
  </div>

  <script>
    function toggleSidebar(open) {
      document.getElementById('sidebar').classList.toggle('open', open);
      document.getElementById('overlay').classList.toggle('show', open);
    }

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
        <svg viewBox="0 0 24 24" width="26" height="26" fill="#92400e"><path d="M1 21h22L12 2 1 21Zm12-3h-2v-2h2v2Zm0-4h-2v-4h2v4Z"/></svg>
      </div>
      <h3 id="confirmTitle">Confirm action</h3>
      <p id="confirmMsg">Are you sure?</p>
      <div class="confirm-actions">
        <button type="button" id="confirmCancel" class="btn-cancel">Cancel</button>
        <button type="button" id="confirmOk" class="btn-ok">OK</button>
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
        okBtn.className = opts.okClass === 'primary' ? 'btn-ok primary' : 'btn-ok';
        iconEl.className = 'confirm-icon' + (opts.iconClass ? ' ' + opts.iconClass : '');
        pendingForm = opts.form || null;
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
        cancelBtn.focus();
        okBtn._cb = opts.onConfirm || null;
        if (!opts.onConfirm) pendingForm = opts.form;
      };
      function closeConfirm() { overlay.classList.remove('open'); document.body.style.overflow = ''; pendingForm = null; okBtn._cb = null; }
      cancelBtn.addEventListener('click', closeConfirm);
      overlay.addEventListener('click', function (e) { if (e.target === overlay) closeConfirm(); });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && overlay.classList.contains('open')) closeConfirm(); });
      okBtn.addEventListener('click', function () { var cb = okBtn._cb; var form = pendingForm; closeConfirm(); if (cb) cb(); else if (form) form.submit(); });
      document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.hasAttribute('data-confirm')) return;
        e.preventDefault();
        openConfirm({ title: form.getAttribute('data-confirm-title') || 'Confirm action', message: form.getAttribute('data-confirm'), okText: form.getAttribute('data-confirm-ok') || 'OK', okClass: form.getAttribute('data-confirm-class') === 'btn-primary' ? 'primary' : 'danger', iconClass: form.getAttribute('data-confirm-icon') || '', form: form });
      });
    })();
  </script>
  <script src="{{ asset('js/table-enhancer.js') }}"></script>
</body>
</html>