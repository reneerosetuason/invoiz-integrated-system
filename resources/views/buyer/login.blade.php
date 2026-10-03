<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Buyer Login - Invoiz</title>
<style>
  body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; background:#F7F6F2; font-family:'Segoe UI',system-ui,sans-serif; color:#1B1B1E; }
  .card { background:white; border:1px solid #E8E6E0; border-radius:16px; padding:36px; width:100%; max-width:380px; box-shadow:0 8px 24px rgba(0,0,0,.06); }
  .logo { width:52px; height:52px; border-radius:14px; background:#16697A; color:white; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:20px; margin:0 auto 14px; }
  h1 { font-size:20px; text-align:center; margin:0 0 4px; }
  .sub { text-align:center; color:#6E6E73; font-size:13px; margin:0 0 22px; }
  label { font-size:12px; font-weight:600; color:#6E6E73; display:block; margin-bottom:6px; }
  input { width:100%; box-sizing:border-box; padding:11px 14px; border:1px solid #d1d5db; border-radius:10px; font-size:14px; margin-bottom:14px; outline:none; }
  input:focus { border-color:#16697A; }
  button { width:100%; padding:12px; background:#16697A; color:white; border:none; border-radius:10px; font-weight:700; font-size:14px; cursor:pointer; }
  button:hover { background:#12525F; }
  .error { background:#FDECEC; color:#B91C1C; font-size:13px; padding:10px 12px; border-radius:8px; margin-bottom:14px; }
</style>
</head>
<body>
  <form method="POST" action="{{ url('/buyer/login') }}" class="card">
    @csrf
    <div class="logo" style="border-radius:50%;overflow:hidden;border:2px solid #E8E6E0;box-shadow:0 4px 14px rgba(0,0,0,.08);"><img src="{{ asset('images/logo.png') }}" alt="Invoiz" style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;" /></div>
    <h1>Welcome back</h1>
    <p class="sub">Log in to chat with sellers on Invoiz</p>
    @if ($errors->any())
      <div class="error">{{ $errors->first() }}</div>
    @endif
    <label for="email">Email</label>
    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
    <label for="password">Password</label>
    <input id="password" type="password" name="password" required>
    <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:#6E6E73;margin:-6px 0 14px;cursor:pointer;font-weight:400">
      <input type="checkbox" style="width:auto;margin:0" onclick="var i=document.getElementById('password');i.type=this.checked?'text':'password';" /> Show password
    </label>
    <button type="submit">Log In</button>
  </form>
</body>
</html>
