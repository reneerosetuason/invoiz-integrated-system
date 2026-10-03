<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login — INVOIZ</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#F8FAF9] min-h-screen flex items-center justify-center p-4">
<div class="w-full max-w-md bg-white rounded-3xl shadow-lg p-8">
  <div class="flex items-center gap-2 justify-center">
    <img src="{{ asset('images/logo.png') }}" onerror="this.style.display='none'" alt="INVOIZ" class="h-10 w-10 rounded-xl object-cover">
    <span class="text-xl font-extrabold text-[#0E4A57]">INVOIZ</span>
  </div>
  <h1 class="text-center text-xl font-extrabold mt-4">Welcome back</h1>
  <p class="text-center text-sm text-gray-500 mt-1">One login for buyers, sellers and admins — connected to <b>invoizdb</b>.</p>
  @if($errors->any())<div class="mt-4 bg-red-50 text-red-700 text-sm p-3 rounded-xl">{{ $errors->first() }}</div>@endif
  @if(session('success'))<div class="mt-4 bg-emerald-50 text-emerald-700 text-sm p-3 rounded-xl">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="mt-4 bg-red-50 text-red-700 text-sm p-3 rounded-xl">{{ session('error') }}</div>@endif
  <form method="POST" action="{{ url('/login') }}" class="mt-6 space-y-4">
    @csrf
    <div><label class="text-xs font-bold text-gray-500">Email</label>
    <input type="email" name="email" value="{{ old('email') }}" required class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3 text-sm outline-none focus:border-[#16697A]"></div>
    <div><label class="text-xs font-bold text-gray-500">Password</label>
    <div class="relative mt-1">
      <input id="password" type="password" name="password" required class="w-full rounded-xl border border-gray-200 px-4 py-3 pr-16 text-sm outline-none focus:border-[#16697A]">
      <button type="button" onclick="var i=document.getElementById('password');var s=i.type==='password';i.type=s?'text':'password';this.textContent=s?'Hide':'Show';" class="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg px-3 py-1 text-xs font-bold text-[#16697A] hover:bg-gray-100">Show</button>
    </div></div>
    <button class="w-full rounded-2xl bg-[#16697A] text-white font-extrabold py-3 text-sm hover:bg-[#0E4A57]">Log In</button>
  </form>
  <p class="text-center text-xs text-gray-500 mt-4">No account? <a href="{{ url('/register') }}" class="font-bold text-[#16697A]">Register as buyer</a></p>
  <p class="text-center text-xs text-gray-400 mt-2"><a href="{{ url('/') }}">← Back to marketplace</a> · Seller or admin? Just log in here — sellers/admins go to their dashboard, dual buyer+seller logins pick an account first.</p>
</div>
</body>
</html>
