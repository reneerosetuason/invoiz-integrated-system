<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Invoiz — Auth</title>
  <style>
    :root{--primary:#16697A;--primary-dark:#0E4A57;--background:#F7F6F2;--card:#FFFFFF;--border:#E8E6E0;--text-primary:#1B1B1E;--text-secondary:#6E6E73}
    *{box-sizing:border-box} body{margin:0;font-family:'Segoe UI',system-ui,sans-serif;background:linear-gradient(180deg,#F8F7F4 0%,#F3F1EB 100%) fixed;color:var(--text-primary);min-height:100vh;display:flex;flex-direction:column}
    .topbar{height:54px;background:#fff;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;padding:0 20px}
    .brand{display:flex;align-items:center;gap:8px;font-weight:800;font-size:16px;color:var(--primary-dark);text-decoration:none}
    .brand-badge{width:32px;height:32px;border-radius:10px;background:linear-gradient(180deg,var(--primary),var(--primary-dark));color:#fff;display:grid;place-items:center;font-weight:800}
    .wrap{flex:1;display:grid;place-items:center;padding:20px}
    a{color:var(--primary)}
  </style>
</head>
<body>
  <header class="topbar">
    <a href="{{ url('/') }}" class="brand"><div class="brand-badge">I</div> Invoiz</a>
    <span style="color:var(--text-secondary);font-size:12px;margin-left:4px">— Secure auth • invoizdb</span>
    <span style="margin-left:auto"><a href="{{ url('/') }}" style="text-decoration:none;color:var(--text-secondary);font-weight:600;font-size:13px">← Back to store</a></span>
  </header>
  <div class="wrap">
    @yield('content')
  </div>
</body>
</html>
