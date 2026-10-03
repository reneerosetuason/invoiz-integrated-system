@extends('layouts.auth')
@section('content')
<div style="width:100%;max-width:420px;background:#fff;border:1px solid var(--border);border-radius:16px;padding:24px;box-shadow:0 8px 24px rgba(0,0,0,.06);text-align:center">
  <div style="width:56px;height:56px;background:#E8F5EE;border-radius:50%;display:grid;place-items:center;margin:0 auto 12px;font-size:20px">🔐</div>
  <h1 style="margin:0;font-size:18px">Authentication code</h1>
  <p style="color:var(--text-secondary);font-size:13px;margin:8px 0 16px">We sent a 6-digit code to <b>{{ $email ?? 'your email' }}</b></p>
  @if($errors->any())<div style="background:#FDECEC;color:#B91C1C;padding:10px;border-radius:8px;font-size:13px;margin-bottom:12px">{{ $errors->first() }}</div>@endif
  @if(session('error'))<div style="background:#FDECEC;color:#B91C1C;padding:10px;border-radius:8px;font-size:13px;margin-bottom:12px">{{ session('error') }}</div>@endif
  <form method="POST" action="{{ url('/verify') }}">
    @csrf
    <input type="hidden" name="email" value="{{ $email }}">
    <input type="password" name="code" placeholder="••••••" maxlength="6" pattern="\d{6}" inputmode="numeric" autocomplete="one-time-code" required style="width:100%;padding:12px;border:1px solid var(--border);border-radius:10px;text-align:center;font-size:20px;letter-spacing:8px;font-weight:800">
    <button type="submit" style="width:100%;margin-top:12px;padding:11px;background:var(--primary);color:#fff;border:none;border-radius:10px;font-weight:700;cursor:pointer">Verify</button>
  </form>
  @if(session('success') && str_contains(session('success'),'sent'))<div style="background:#E8F5EE;color:#1D6B43;padding:8px;border-radius:8px;font-size:12px;margin-bottom:10px">{{ session('success') }}</div>@endif
  @if(session('resent'))<div style="background:#E8F5EE;color:#1D6B43;padding:8px;border-radius:8px;font-size:12px;margin-bottom:10px">✓ Code resent to {{ $email }} — check your Gmail inbox</div>@endif
  <div style="margin-top:10px;font-size:11px;color:var(--text-secondary)">Didn't receive?
    <form method="POST" action="{{ url('/verify/resend') }}" style="display:inline">
      @csrf
      <input type="hidden" name="email" value="{{ $email }}">
      <button type="submit" style="background:none;border:none;color:var(--primary);cursor:pointer;font-weight:700;font-size:11px;padding:0">Resend code</button>
    </form> • <a href="{{ url('/login') }}" style="color:var(--primary)">Back to login</a>
  </div>
  <div style="margin-top:8px;font-size:10px;color:var(--text-secondary);text-align:center">Code also appears in Gmail inbox (via Invoiz) and in-app notification 🔔</div>
</div>
@endsection
