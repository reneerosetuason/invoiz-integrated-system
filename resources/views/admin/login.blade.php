<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Login — Invoiz Admin Center</title>
  <style>
    :root { --teal: #16697A; --teal-dark: #0E4A57; --teal-soft: rgba(255,255,255,.12); }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif; }
    .split { display: flex; min-height: 100vh; }

    .left { width: 50%; background: linear-gradient(160deg, #13586A 0%, #0C4350 100%); color: #fff; padding: 48px 52px; display: flex; flex-direction: column; justify-content: space-between; }
    .brand { display: flex; align-items: center; gap: 14px; }
    .brand-logo { width: 60px; height: 60px; border-radius: 50%; background: #083D46; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 20px; border: 2px solid rgba(255,255,255,.22); overflow: hidden; }
    .brand-logo img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
    .brand-name { font-size: 26px; font-weight: 800; letter-spacing: -.6px; line-height: 1; }
    .brand-label { font-size: 10px; letter-spacing: 4px; text-transform: uppercase; color: rgba(255,255,255,.65); font-weight: 600; margin-top: 4px; }

    .hero { max-width: 480px; }
    .hero h1 { font-size: 42px; line-height: 1.15; font-weight: 800; letter-spacing: -.8px; margin: 0 0 18px; }
    .hero p { font-size: 16px; line-height: 1.6; color: rgba(255,255,255,.78); margin: 0 0 28px; }
    .stats { display: flex; gap: 14px; }
    .stat { background: var(--teal-soft); border: 1px solid rgba(255,255,255,.14); border-radius: 16px; padding: 16px 18px; min-width: 120px; }
    .stat b { display: block; font-size: 22px; font-weight: 800; }
    .stat span { font-size: 12px; color: rgba(255,255,255,.7); text-transform: uppercase; letter-spacing: 1px; margin-top: 4px; display: block; }

    .foot { font-size: 12px; color: rgba(255,255,255,.5); letter-spacing: 1px; }

    .right { width: 50%; background: #F4F5F7; display: flex; align-items: center; justify-content: center; padding: 40px; }
    .right-inner { width: 100%; max-width: 440px; }
    .right h2 { font-size: 30px; font-weight: 800; letter-spacing: -.6px; margin: 0 0 6px; color: #1A202C; }
    .right .sub { color: #6B7280; font-size: 15px; margin: 0 0 26px; }
    .card { background: #fff; border: 1px solid #E5E5E5; border-radius: 18px; padding: 32px; box-shadow: 0 4px 18px rgba(0,0,0,.05); }
    .field { margin-bottom: 18px; }
    label { display: block; font-size: 12px; font-weight: 700; letter-spacing: 1px; color: #374151; margin-bottom: 8px; text-transform: uppercase; }
    input[type="email"] { width: 100%; background: #fff; border: 1px solid #D8DBDF; border-radius: 12px; padding: 13px 16px; font-size: 15px; color: #1A202C; outline: none; transition: border .15s; font-family: inherit; }
    input[type="email"]:focus { border-color: var(--teal); box-shadow: 0 0 0 3px rgba(22,105,122,.12); }
    input[type="password"] { width: 100%; background: #F4F5F7; border: 1px solid #F4F5F7; border-radius: 12px; padding: 13px 16px; font-size: 15px; color: #1A202C; outline: none; transition: border .15s; font-family: inherit; }
    input[type="password"]:focus { border-color: var(--teal); background: #fff; box-shadow: 0 0 0 3px rgba(22,105,122,.12); }
    .remember { display: flex; align-items: center; gap: 8px; font-size: 14px; color: #374151; margin: 2px 0 20px; cursor: pointer; }
    .remember input { width: 16px; height: 16px; accent-color: var(--teal); }
    .btn { width: 100%; background: var(--teal); color: #fff; font-weight: 700; font-size: 15px; padding: 15px; border: none; border-radius: 12px; cursor: pointer; transition: background .15s, transform .18s ease; font-family: inherit; }
    .btn:hover { background: var(--teal-dark); transform: scaleX(1.06); }
    .error { background: #FDECEC; color: #B3261E; border: 1px solid #F5C6C6; border-radius: 10px; padding: 12px 14px; font-size: 14px; margin-bottom: 16px; }
    .test-note { text-align: center; font-size: 13px; color: #6B7280; margin-top: 20px; }
    .test-note a { color: var(--teal); font-weight: 600; }
    .test-note .buyer { margin-top: 6px; }

    @media (max-width: 900px) {
      .left { display: none; }
      .right { width: 100%; }
    }
  </style>
</head>
<body>
  @php
    $pCount = \App\Models\Product::count();
    $oCount = \App\Models\Order::count();
    $sCount = \App\Models\Seller::approved()->count();
    $lgPath = public_path('images/logo.png');
    $hasLogo = file_exists($lgPath);
  @endphp
  <div class="split">
    <div class="left">
      <div class="brand">
        <div class="brand-logo">
          @if ($hasLogo)
            <img src="{{ asset('images/logo.png') }}" alt="Invoiz logo">
          @else
            I
          @endif
        </div>
        <div>
          <div class="brand-name">Invoiz</div>
          <div class="brand-label">Admin Center</div>
        </div>
      </div>

      <div class="hero">
        <h1>Run your marketplace.<br>Grow every store.</h1>
        <p>Approve sellers, monitor orders, manage products, and keep the whole platform running smoothly — all from one Invoiz Admin Center.</p>
        <div class="stats">
          <div class="stat"><b>{{ $pCount }}</b><span>Products</span></div>
          <div class="stat"><b>{{ $oCount }}</b><span>Orders</span></div>
          <div class="stat"><b>{{ $sCount }}</b><span>Sellers</span></div>
        </div>
      </div>

      <div class="foot">Invoiz · v1.0 · Admin</div>
    </div>

    <div class="right">
      <div class="right-inner">
        <h2>Admin Login</h2>
        <p class="sub">Sign in with your administrator account.</p>

        <div class="card">
          @if($errors->any())
            <div class="error">{{ $errors->first() }}</div>
          @endif
          <form method="POST" action="{{ url('/admin/login') }}">
            @csrf
            <div class="field">
              <label>Email Address</label>
              <input name="email" type="email" placeholder="cmiavenus@gmail.com" value="{{ old('email') }}" required />
            </div>
            <div class="field">
              <label>Password</label>
              <input id="admin-password" name="password" type="password" placeholder="••••••••" required />
              <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:#6B7280;margin-top:6px;cursor:pointer">
                <input type="checkbox" onclick="var i=document.getElementById('admin-password');i.type=this.checked?'text':'password';" /> Show password
              </label>
            </div>
            <label class="remember">
              <input type="checkbox" name="remember" /> Remember me
            </label>
            <button type="submit" class="btn">Sign in to Admin Center</button>
          </form>
      </div>
    </div>
  </div>
</body>
</html>