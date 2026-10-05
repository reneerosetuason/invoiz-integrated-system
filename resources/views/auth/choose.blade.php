<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Choose account — INVOIZ</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#F8FAF9] min-h-screen flex items-center justify-center p-4">
<div class="w-full max-w-lg bg-white rounded-3xl shadow-lg p-8">
  <div class="flex items-center gap-2 justify-center">
    <img src="{{ asset('images/logo.png') }}" onerror="this.style.display='none'" alt="INVOIZ" class="h-10 w-10 rounded-xl object-cover">
    <span class="text-xl font-extrabold text-[#0E4A57]">INVOIZ</span>
  </div>
  <h1 class="text-center text-xl font-extrabold mt-4">Welcome, {{ $user->displayName() }}</h1>
  <p class="text-center text-sm text-gray-500 mt-1">Your login works as <b>both buyer and seller</b>. Choose how to continue:</p>
  <div class="mt-6 grid gap-3 sm:grid-cols-2">
    <a href="{{ url('/choose/buyer') }}" class="rounded-2xl bg-[#F0A202] p-5 text-center font-extrabold text-sm hover:brightness-95">
      <div class="mx-auto flex h-9 w-9 items-center justify-center text-charcoal"><svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg></div>
      <div class="mt-2">Continue as Buyer</div>
      <div class="mt-1 text-xs font-semibold opacity-70">Shop, cart, orders</div>
    </a>
    <a href="{{ url('/choose/seller') }}" class="rounded-2xl bg-[#16697A] p-5 text-center font-extrabold text-sm text-white hover:bg-[#0E4A57]">
      <div class="mx-auto flex h-9 w-9 items-center justify-center text-white"><svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path d="M9 22V12h6v10"/></svg></div>
      <div class="mt-2">Continue as Seller</div>
      <div class="mt-1 text-xs font-semibold opacity-70">{{ $storeName }}</div>
    </a>
  </div>
  <form method="POST" action="{{ url('/logout') }}" class="mt-4 text-center">@csrf<button class="text-xs text-gray-400 hover:text-gray-600">Not you? Log out</button></form>
</div>
</body>
</html>
