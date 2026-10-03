@extends('layouts.auth')
@section('content')
<div style="width:100%;max-width:520px;background:#fff;border:1px solid var(--border);border-radius:16px;padding:20px;box-shadow:0 8px 24px rgba(0,0,0,.06)">
  <h1 style="text-align:center;margin:0;font-size:18px">Create buyer account</h1>
  <p style="text-align:center;color:var(--text-secondary);font-size:11px;margin:4px 0 14px">Required: first/last name, birthdate, phone, gender, address, email, password</p>
  @if($errors->any())<div style="background:#FDECEC;color:#B91C1C;padding:10px;border-radius:8px;font-size:13px;margin-bottom:12px">{{ $errors->first() }}</div>@endif
  @if(session('success'))<div style="background:#E8F5EE;color:#1D6B43;padding:10px;border-radius:8px;font-size:13px;margin-bottom:12px">{{ session('success') }}</div>@endif
  <form method="POST" action="{{ url('/register') }}">
    @csrf
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <div><label style="font-size:11px;font-weight:700">First name *</label><input type="text" name="first_name" value="{{ old('first_name') }}" required style="width:100%;padding:9px 10px;border:1px solid #d1d5db;border-radius:10px;margin-top:5px;font-size:13px"></div>
      <div><label style="font-size:11px;font-weight:700">Last name *</label><input type="text" name="last_name" value="{{ old('last_name') }}" required style="width:100%;padding:9px 10px;border:1px solid #d1d5db;border-radius:10px;margin-top:5px;font-size:13px"></div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px">
      <div><label style="font-size:11px;font-weight:700">Birthdate *</label><input type="date" name="birthday" value="{{ old('birthday') }}" required style="width:100%;padding:9px 10px;border:1px solid #d1d5db;border-radius:10px;margin-top:5px;font-size:13px"></div>
      <div><label style="font-size:11px;font-weight:700">Gender *</label><select name="sex" required style="width:100%;padding:9px 10px;border:1px solid #d1d5db;border-radius:10px;margin-top:5px;font-size:13px"><option value="">Select</option><option value="male" {{ old('sex')=='male'?'selected':'' }}>Male</option><option value="female" {{ old('sex')=='female'?'selected':'' }}>Female</option><option value="other" {{ old('sex')=='other'?'selected':'' }}>Other</option></select></div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px">
      <div><label style="font-size:11px;font-weight:700">Phone number *</label><input type="text" name="phone" value="{{ old('phone') }}" required placeholder="0917..." style="width:100%;padding:9px 10px;border:1px solid #d1d5db;border-radius:10px;margin-top:5px;font-size:13px"></div>
      <div><label style="font-size:11px;font-weight:700">Email *</label><input type="email" name="email" value="{{ old('email') }}" required style="width:100%;padding:9px 10px;border:1px solid #d1d5db;border-radius:10px;margin-top:5px;font-size:13px"></div>
    </div>
    <label style="font-size:11px;font-weight:700;margin-top:10px;display:block">Address *</label><input type="text" name="address_line" value="{{ old('address_line') }}" required placeholder="House, street, barangay, municipality, province" style="width:100%;padding:9px 10px;border:1px solid #d1d5db;border-radius:10px;margin-top:5px;font-size:13px">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px">
      <div><label style="font-size:11px;font-weight:700">Password *</label><input type="password" name="password" required style="width:100%;padding:9px 10px;border:1px solid #d1d5db;border-radius:10px;margin-top:5px;font-size:13px"></div>
      <div><label style="font-size:11px;font-weight:700">Confirm Password *</label><input type="password" name="password_confirmation" required style="width:100%;padding:9px 10px;border:1px solid #d1d5db;border-radius:10px;margin-top:5px;font-size:13px"></div>
    </div>
    <button type="submit" style="width:100%;margin-top:14px;padding:11px;background:var(--primary);color:#fff;border:none;border-radius:10px;font-weight:700;cursor:pointer">Register</button>
  </form>
  <div style="display:flex;align-items:center;gap:10px;margin:14px 0"><div style="flex:1;height:1px;background:var(--border)"></div><span style="font-size:11px;color:var(--text-secondary)">or</span><div style="flex:1;height:1px;background:var(--border)"></div></div>
  <a href="{{ url('/auth/google') }}" style="display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:10px;background:#fff;border:1px solid var(--border);border-radius:10px;text-decoration:none;color:var(--text-primary);font-weight:700;font-size:13px">
    <img src="https://www.svgrepo.com/show/475656/google-color.svg" alt="G" style="width:18px;height:18px"> Continue with Google
  </a>
  <div style="text-align:center;margin-top:10px;font-size:12px">Already have account? <a href="{{ url('/login') }}" style="color:var(--primary);font-weight:700">Login</a></div>
</div>
@endsection
