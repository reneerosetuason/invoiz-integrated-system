@extends('layouts.website')
@section('content')
@php
  $displayName = $user->name ?? trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
  $nameParts = preg_split('/\s+/', trim($displayName), 2);
  $firstName = $nameParts[0] ?? '';
  $lastName = $nameParts[1] ?? '';
@endphp
<div style="max-width:720px;margin:20px auto;background:#fff;border:1px solid var(--border);border-radius:16px;overflow:hidden">
  <div style="height:120px;background:linear-gradient(135deg,#0F766E,#0D9488);position:relative">
    <div style="position:absolute;left:50%;bottom:-32px;transform:translateX(-50%);width:72px;height:72px;border-radius:50%;border:4px solid #fff;overflow:hidden;background:#fff;box-shadow:0 4px 12px rgba(0,0,0,.12)">
      <img src="https://ui-avatars.com/api/?name={{ urlencode($displayName) }}&background=16697A&color=fff&size=72" style="width:100%;height:100%;object-fit:cover">
    </div>
  </div>
  <div style="padding:44px 24px 24px;text-align:center">
    <h2 style="margin:0;font-size:18px">{{ $displayName }}</h2>
    <div style="color:var(--text-secondary);font-size:12px">{{ $user->email }} • {{ $user->role }} • {{ $user->approval_status ?? $user->account_status ?? 'active' }}</div>
  </div>
  @if(session('success'))<div style="margin:0 24px;background:#E8F5EE;color:#1D6B43;padding:10px;border-radius:8px;border:1px solid #BFE3D0;font-size:13px">{{ session('success') }}</div>@endif
  @if($errors->any())<div style="margin:0 24px;background:#FDECEC;color:#B91C1C;padding:10px;border-radius:8px;border:1px solid #FECACA;font-size:13px">{{ $errors->first() }}</div>@endif
  <form method="POST" action="{{ url('/profile') }}" style="padding:0 24px 24px">
    @csrf
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
      <div><label style="font-size:11px;font-weight:700">First name</label><input type="text" name="first_name" value="{{ old('first_name', $firstName) }}" required style="width:100%;padding:9px 10px;border:1px solid #d1d5db;border-radius:10px;margin-top:4px"></div>
      <div><label style="font-size:11px;font-weight:700">Last name</label><input type="text" name="last_name" value="{{ old('last_name', $lastName) }}" required style="width:100%;padding:9px 10px;border:1px solid #d1d5db;border-radius:10px;margin-top:4px"></div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px">
      <div><label style="font-size:11px;font-weight:700">Email</label><input type="email" name="email" value="{{ old('email', $user->email) }}" required style="width:100%;padding:9px 10px;border:1px solid #d1d5db;border-radius:10px;margin-top:4px"></div>
      <div><label style="font-size:11px;font-weight:700">Phone</label><input type="text" name="phone" value="{{ old('phone', $user->phone ?? '') }}" style="width:100%;padding:9px 10px;border:1px solid #d1d5db;border-radius:10px;margin-top:4px"></div>
    </div>
    <div style="display:flex;gap:10px;margin-top:16px">
      <button type="submit" style="flex:1;padding:11px;background:var(--primary);color:#fff;border:none;border-radius:10px;font-weight:700;cursor:pointer">Save changes</button>
      <a href="{{ url('/') }}" style="flex:1;text-align:center;padding:11px;background:#fff;border:1px solid var(--border);border-radius:10px;text-decoration:none;color:var(--text-primary);font-weight:700">Cancel</a>
    </div>
  </form>
</div>
@endsection
