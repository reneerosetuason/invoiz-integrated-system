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
      <div class="text-2xl">🛒</div>
      <div class="mt-2">Continue as Buyer</div>
      <div class="mt-1 text-xs font-semibold opacity-70">Shop, cart, orders</div>
    </a>
    <a href="{{ url('/choose/seller') }}" class="rounded-2xl bg-[#16697A] p-5 text-center font-extrabold text-sm text-white hover:bg-[#0E4A57]">
      <div class="text-2xl">🏪</div>
      <div class="mt-2">Continue as Seller</div>
      <div class="mt-1 text-xs font-semibold opacity-70">{{ $storeName }}</div>
    </a>
  </div>
  <form method="POST" action="{{ url('/logout') }}" class="mt-4 text-center">@csrf<button class="text-xs text-gray-400 hover:text-gray-600">Not you? Log out</button></form>
</div>
</body>
</html>
