<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Buyer Login - Invoiz</title>
<link rel="stylesheet" href="{{ asset('assets/buyer/css/login.css') }}">
</head>
<body>
  <form method="POST" action="{{ url('/buyer/login') }}" class="card">
    @csrf
    <div class="logo" style="border-radius:50%;overflow:hidden;border:2px solid #E8E6E0;box-shadow:0 4px 14px rgba(0,0,0,.08);"><img src="{{ asset('assets/shared/images/logo.png') }}" alt="Invoiz" style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;" /></div>
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
