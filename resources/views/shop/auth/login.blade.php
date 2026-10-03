@extends('layouts.auth')
@section('content')
<div style="width:100%;max-width:420px;background:#fff;border:1px solid var(--border);border-radius:16px;padding:24px;box-shadow:0 8px 24px rgba(0,0,0,.06)">
  <div style="width:48px;height:48px;border-radius:50%;background:linear-gradient(180deg,var(--primary),var(--primary-dark));color:#fff;display:grid;place-items:center;font-weight:800;margin:0 auto 10px;overflow:hidden;border:2px solid var(--border)"><img src="{{ asset('images/logo.png') }}" onerror="this.style.display='none'" style="width:100%;height:100%;object-fit:cover"><span style="position:absolute">I</span></div>
  <h1 style="text-align:center;margin:0;font-size:18px">Welcome back</h1>
  <p style="text-align:center;color:var(--text-secondary);font-size:12px;margin:4px 0 16px">Log in as buyer — connected to invoizdb</p>
  @if($errors->any())<div style="background:#FDECEC;color:#B91C1C;padding:10px;border-radius:8px;font-size:13px;margin-bottom:12px">{{ $errors->first() }}</div>@endif
  @if(session('error'))<div style="background:#FDECEC;color:#B91C1C;padding:10px;border-radius:8px;font-size:13px;margin-bottom:12px">{{ session('error') }}</div>@endif
  @if(session('success'))<div style="background:#E8F5EE;color:#1D6B43;padding:10px;border-radius:8px;font-size:13px;margin-bottom:12px">{{ session('success') }}</div>@endif
  <form method="POST" action="{{ url('/login') }}">
    @csrf
    <label style="font-size:12px;font-weight:600;color:var(--text-secondary)">Email</label>
    <input type="email" name="email" value="{{ old('email') }}" required style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;margin:6px 0 12px">
    <label style="font-size:12px;font-weight:600;color:var(--text-secondary)">Password</label>
    <input type="password" name="password" required style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;margin:6px 0 12px">
    <button type="submit" style="width:100%;padding:11px;background:var(--primary);color:#fff;border:none;border-radius:10px;font-weight:700;cursor:pointer">Log In</button>
  </form>
  <div style="display:flex;align-items:center;gap:10px;margin:14px 0"><div style="flex:1;height:1px;background:var(--border)"></div><span style="font-size:11px;color:var(--text-secondary)">or</span><div style="flex:1;height:1px;background:var(--border)"></div></div>
  <a href="{{ url('/auth/google') }}" style="display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:10px;background:#fff;border:1px solid var(--border);border-radius:10px;text-decoration:none;color:var(--text-primary);font-weight:700;font-size:13px">
    <img src="https://www.svgrepo.com/show/475656/google-color.svg" alt="G" style="width:18px;height:18px"> Continue with Google
  </a>
  <div style="text-align:center;margin-top:12px;font-size:12px">No account? <a href="{{ url('/register') }}" style="color:var(--primary);font-weight:700">Register as buyer</a></div>
  <div style="margin-top:10px;font-size:11px;color:var(--text-secondary);text-align:center">Demo: juan@test.com / password123 • seller@invoiz.test / password</div>
</div>
@endsection
