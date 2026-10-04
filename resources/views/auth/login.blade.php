<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login — INVOIZ</title>
<link rel="icon" href="{{ asset('images/logo.png') }}">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = { theme: { extend: {
  colors: { teal: { DEFAULT:'#16697A', dark:'#0E4A57', light:'#EAF4F3' }, amber: { DEFAULT:'#F0A202', light:'#FFF8E1' }, offwhite:'#F8FAF9', charcoal:'#1F2933' },
  fontFamily: { sans:['Segoe UI','system-ui','-apple-system','sans-serif'] },
  boxShadow: { soft:'0 24px 60px rgba(14,74,87,.14)', card:'0 10px 25px rgba(31,41,51,.08)' },
}}};
</script>
<style>
  html,body{height:100%}
  body{background:#F8FAF9;font-family:'Segoe UI',system-ui,-apple-system,sans-serif}
  .mesh{background:
    radial-gradient(38rem 20rem at 8% -60px, rgba(240,162,2,.22), transparent 60%),
    radial-gradient(34rem 20rem at 105% 15%, rgba(22,105,122,.18), transparent 60%),
    linear-gradient(135deg,#F8FAF9 0%,#ffffff 55%,#EAF4F3 100%)}
  .field{position:relative}
  .field svg{position:absolute;left:1rem;top:50%;transform:translateY(-50%);width:1.05rem;height:1.05rem;color:#9CA3AF;pointer-events:none}
  .field input{width:100%;border-radius:1rem;border:1.5px solid #E5E7EB;background:#fff;padding:.85rem 3rem .85rem 2.75rem;font-size:.9rem;outline:none;transition:border-color .2s, box-shadow .2s}
  .field input:focus{border-color:#16697A;box-shadow:0 0 0 4px rgba(22,105,122,.12)}
  .field input::placeholder{color:#9CA3AF}
  .eye{position:absolute;right:.6rem;top:50%;transform:translateY(-50%);border-radius:.6rem;padding:.35rem .6rem;font-size:.72rem;font-weight:800;color:#16697A}
  .eye:hover{background:#EAF4F3}
  .btn-primary{background:linear-gradient(135deg,#16697A,#0E4A57);transition:transform .2s, box-shadow .2s, filter .2s}
  .btn-primary:hover{transform:translateY(-1px);filter:brightness(1.06);box-shadow:0 14px 30px rgba(14,74,87,.28)}
  .float-slow{animation:floaty 7s ease-in-out infinite}
  .float-slower{animation:floaty 9s ease-in-out infinite reverse}
  @keyframes floaty{0%,100%{transform:translateY(0)}50%{transform:translateY(-12px)}}
  @media (prefers-reduced-motion: reduce){.float-slow,.float-slower{animation:none}}
</style>
</head>
<body class="mesh min-h-screen flex items-center justify-center p-4 sm:p-8">
<div class="w-full max-w-4xl overflow-hidden rounded-[2rem] bg-white shadow-soft ring-1 ring-black/5 grid md:grid-cols-[1fr_1.05fr]">

  <div class="relative hidden flex-col justify-between overflow-hidden bg-gradient-to-br from-teal via-teal-dark to-[#0B3B46] p-8 text-white md:flex">
    <div class="pointer-events-none absolute -right-16 -top-16 h-64 w-64 rounded-full bg-white/10 blur-2xl"></div>
    <div class="pointer-events-none absolute -bottom-20 -left-10 h-64 w-64 rounded-full bg-amber/20 blur-2xl"></div>
    <div class="relative flex items-center gap-2.5">
      <img src="{{ asset('images/logo.png') }}" onerror="this.style.display='none'" alt="INVOIZ" class="h-10 w-10 rounded-xl bg-white object-cover p-1">
      <span class="text-xl font-extrabold tracking-tight">INVOIZ</span>
    </div>
    <div class="relative">
      <p class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-[11px] font-extrabold uppercase tracking-widest text-amber">Welcome back</p>
      <h2 class="mt-4 text-3xl font-extrabold leading-tight">Life's short.<br>Shop fast.</h2>
      <p class="mt-3 max-w-xs text-sm leading-relaxed text-white/75">One login for the whole marketplace — shop as a buyer, manage your store as a seller, or oversee everything as an admin.</p>
      <ul class="mt-6 space-y-2.5 text-sm font-semibold text-white/85">
        <li class="flex items-center gap-2.5"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-white/15 text-xs">✓</span> Buyers, sellers &amp; admins</li>
        <li class="flex items-center gap-2.5"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-white/15 text-xs">✓</span> Secure role-based access</li>
        <li class="flex items-center gap-2.5"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-white/15 text-xs">✓</span> Fast doorstep delivery</li>
      </ul>
    </div>
    <div class="relative float-slower rounded-2xl bg-white/10 p-4 backdrop-blur">
      <p class="text-sm font-bold">"Ordered in the morning, delivered the same day. INVOIZ is my daily habit now."</p>
      <p class="mt-2 text-xs font-semibold text-white/60">— Renee R., verified buyer</p>
    </div>
  </div>

  <div class="p-6 sm:p-10">
    <div class="flex items-center gap-2 md:hidden">
      <img src="{{ asset('images/logo.png') }}" onerror="this.style.display='none'" alt="INVOIZ" class="h-9 w-9 rounded-xl object-cover ring-1 ring-gray-200">
      <span class="text-lg font-extrabold text-teal-dark">INVOIZ</span>
    </div>
    <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-charcoal md:mt-0">Welcome back</h1>
    <p class="mt-1 text-sm text-gray-500">Log in to continue to your account.</p>

    @if($errors->any())<div class="mt-4 rounded-2xl bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 ring-1 ring-red-100">{{ $errors->first() }}</div>@endif
    @if(session('success'))<div class="mt-4 rounded-2xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700 ring-1 ring-emerald-100">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mt-4 rounded-2xl bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 ring-1 ring-red-100">{{ session('error') }}</div>@endif

    <form method="POST" action="{{ url('/login') }}" class="mt-6 space-y-4">
      @csrf
      <div>
        <label class="mb-1.5 block text-xs font-extrabold uppercase tracking-wider text-gray-500" for="email">Email</label>
        <div class="field">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.9 5.3a2 2 0 002.2 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
          <input id="email" type="email" name="email" value="{{ old('email') }}" required placeholder="you@example.com" autocomplete="email">
        </div>
      </div>
      <div>
        <label class="mb-1.5 block text-xs font-extrabold uppercase tracking-wider text-gray-500" for="password">Password</label>
        <div class="field">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
          <input id="password" type="password" name="password" required placeholder="••••••••" autocomplete="current-password">
          <button type="button" class="eye" onclick="var i=document.getElementById('password');var s=i.type==='password';i.type=s?'text':'password';this.textContent=s?'Hide':'Show';">Show</button>
        </div>
      </div>
      <label class="flex cursor-pointer items-center gap-2 text-sm font-semibold text-gray-500"><input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded accent-[#16697A]"> Remember me</label>
      <button class="btn-primary w-full rounded-2xl py-3.5 text-sm font-extrabold text-white">Log In →</button>
    </form>

    <div class="my-5 flex items-center gap-3 text-xs font-bold text-gray-400"><span class="h-px flex-1 bg-gray-200"></span>OR<span class="h-px flex-1 bg-gray-200"></span></div>
    <a href="{{ url('/auth/google') }}" class="flex w-full items-center justify-center gap-2.5 rounded-2xl border-2 border-gray-200 bg-white py-3 text-sm font-extrabold text-charcoal transition hover:border-gray-300 hover:bg-gray-50">
      <svg class="h-5 w-5" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.27-4.74 3.27-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.1a7.16 7.16 0 010-4.2V7.06H2.18a11 11 0 000 9.88l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>
      Continue with Gmail
    </a>

    <p class="mt-6 text-center text-sm text-gray-500">New to INVOIZ? <a href="{{ url('/register') }}" class="font-extrabold text-teal hover:text-teal-dark">Create an account</a></p>
    <p class="mt-3 text-center text-xs text-gray-400"><a href="{{ url('/') }}" class="hover:text-teal-dark">← Back to marketplace</a></p>
  </div>
</div>
</body>
</html>
