<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Login — Invoiz Admin Center</title>
  <link rel="stylesheet" href="{{ asset('assets/admin/css/login.css') }}">
</head>
<body>
  @php
    $pCount = \App\Models\Product::count();
    $oCount = \App\Models\Order::count();
    $sCount = \App\Models\Seller::approved()->count();
    $lgPath = public_path('assets/shared/images/logo.png');
    $hasLogo = file_exists($lgPath);
  @endphp
  <div class="split">
    <div class="left">
      <div class="brand">
        <div class="brand-logo">
          @if ($hasLogo)
            <img src="{{ asset('assets/shared/images/logo.png') }}" alt="Invoiz logo">
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
            <p style="text-align:right;margin:10px 0 0;font-size:12.5px"><a href="{{ url('/forgot-password') }}" style="color:#0E4A57;font-weight:700;text-decoration:none" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">Forgot password?</a></p>
            <button type="submit" class="btn">Sign in to Admin Center</button>
          </form>
      </div>
    </div>
  </div>
</body>
</html>