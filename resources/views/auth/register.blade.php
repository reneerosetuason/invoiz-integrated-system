<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign Up — INVOIZ</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#F8FAF9] min-h-screen flex items-center justify-center p-4">
<div class="w-full max-w-md bg-white rounded-3xl shadow-lg p-8">
  <div class="flex items-center gap-2 justify-center">
    <img src="{{ asset('images/logo.png') }}" onerror="this.style.display='none'" alt="INVOIZ" class="h-10 w-10 rounded-xl object-cover">
    <span class="text-xl font-extrabold text-[#0E4A57]">INVOIZ</span>
  </div>
  <h1 class="text-center text-xl font-extrabold mt-4">Create your buyer account</h1>
  <p class="text-center text-sm text-gray-500 mt-1">Registration is always a <b>buyer</b> account — shop right away, like Shopee / Lazada.</p>
  @if($errors->any())<div class="mt-4 bg-red-50 text-red-700 text-sm p-3 rounded-xl">{{ $errors->first() }}</div>@endif
  <form method="POST" action="{{ url('/register') }}" class="mt-6 space-y-4">
    @csrf
    <div class="grid grid-cols-2 gap-3">
      <div><label class="text-xs font-bold text-gray-500">First name</label>
      <input name="first_name" value="{{ old('first_name') }}" required class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3 text-sm outline-none focus:border-[#16697A]"></div>
      <div><label class="text-xs font-bold text-gray-500">Last name</label>
      <input name="last_name" value="{{ old('last_name') }}" required class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3 text-sm outline-none focus:border-[#16697A]"></div>
    </div>
    <div><label class="text-xs font-bold text-gray-500">Email</label>
    <input type="email" name="email" value="{{ old('email') }}" required class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3 text-sm outline-none focus:border-[#16697A]"></div>
    <div><label class="text-xs font-bold text-gray-500">Phone (optional)</label>
    <input name="phone" value="{{ old('phone') }}" class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3 text-sm outline-none focus:border-[#16697A]"></div>
    <div class="grid grid-cols-2 gap-3">
      <div><label class="text-xs font-bold text-gray-500">Password (min 8)</label>
      <div class="relative mt-1">
        <input id="password" type="password" name="password" required class="w-full rounded-xl border border-gray-200 px-4 py-3 pr-14 text-sm outline-none focus:border-[#16697A]">
        <button type="button" onclick="var i=document.getElementById('password');var s=i.type==='password';i.type=s?'text':'password';this.textContent=s?'Hide':'Show';" class="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg px-2 py-1 text-xs font-bold text-[#16697A] hover:bg-gray-100">Show</button>
      </div></div>
      <div><label class="text-xs font-bold text-gray-500">Confirm</label>
      <div class="relative mt-1">
        <input id="password_confirmation" type="password" name="password_confirmation" required class="w-full rounded-xl border border-gray-200 px-4 py-3 pr-14 text-sm outline-none focus:border-[#16697A]">
        <button type="button" onclick="var i=document.getElementById('password_confirmation');var s=i.type==='password';i.type=s?'text':'password';this.textContent=s?'Hide':'Show';" class="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg px-2 py-1 text-xs font-bold text-[#16697A] hover:bg-gray-100">Show</button>
      </div></div>
    </div>
    <button class="w-full rounded-2xl bg-[#F0A202] font-extrabold py-3 text-sm hover:brightness-95">Sign Up as Buyer</button>
  </form>
  <p class="text-center text-xs text-gray-500 mt-4">Already have an account? <a href="{{ url('/login') }}" class="font-bold text-[#16697A]">Log in</a></p>
  <p class="text-center text-xs text-gray-400 mt-2"><a href="{{ url('/') }}">← Back to marketplace</a></p>
</div>
</body>
</html>
