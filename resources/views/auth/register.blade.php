<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign Up — INVOIZ</title>
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
  .field>svg{position:absolute;left:1rem;top:50%;transform:translateY(-50%);width:1.05rem;height:1.05rem;color:#9CA3AF;pointer-events:none}
  .field input,.field select{width:100%;border-radius:1rem;border:1.5px solid #E5E7EB;background:#fff;padding:.85rem 1rem .85rem 2.75rem;font-size:.9rem;outline:none;transition:border-color .2s, box-shadow .2s;color:#1F2933}
  .field input:focus,.field select:focus{border-color:#16697A;box-shadow:0 0 0 4px rgba(22,105,122,.12)}
  .field input::placeholder{color:#9CA3AF}
  .field .pad{padding-left:1rem;padding-right:3.5rem}
  .eye{position:absolute;right:.6rem;top:50%;transform:translateY(-50%);border-radius:.6rem;padding:.35rem .6rem;font-size:.72rem;font-weight:800;color:#16697A}
  .eye:hover{background:#EAF4F3}
  .btn-primary{background:linear-gradient(135deg,#16697A,#0E4A57);transition:transform .2s, box-shadow .2s, filter .2s}
  .btn-primary:hover{transform:translateY(-1px);filter:brightness(1.06);box-shadow:0 14px 30px rgba(14,74,87,.28)}
  .step{display:flex;align-items:center;gap:.6rem;font-size:.78rem;font-weight:700;color:#6B7280}
  .step .n{flex-shrink:0;display:flex;height:1.5rem;width:1.5rem;align-items:center;justify-content:center;border-radius:9999px;background:#EAF4F3;color:#0E4A57;font-size:.72rem;font-weight:800}
  .float-slow{animation:floaty 7s ease-in-out infinite}
  @keyframes floaty{0%,100%{transform:translateY(0)}50%{transform:translateY(-12px)}}
  @media (prefers-reduced-motion: reduce){.float-slow{animation:none}}
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
      <p class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-[11px] font-extrabold uppercase tracking-widest text-amber">Join free today</p>
      <h2 class="mt-4 text-3xl font-extrabold leading-tight">Your marketplace<br>journey starts here.</h2>
      <p class="mt-3 max-w-xs text-sm leading-relaxed text-white/75">Create a buyer account in seconds. Shop local picks, track orders live, and apply to sell whenever you're ready.</p>
      <div class="mt-6 space-y-3">
        <div class="step !text-white/85"><span class="n !bg-white/15 !text-white">1</span> Create your buyer account</div>
        <div class="step !text-white/85"><span class="n !bg-white/15 !text-white">2</span> Shop &amp; track your orders</div>
        <div class="step !text-white/85"><span class="n !bg-white/15 !text-white">3</span> Apply to open your store</div>
      </div>
    </div>
    <div class="relative float-slow grid grid-cols-3 gap-3">
      <div class="rounded-2xl bg-white/10 p-3 text-center backdrop-blur"><p class="text-lg font-extrabold">500+</p><p class="text-[11px] text-white/65">Local picks</p></div>
      <div class="rounded-2xl bg-white/10 p-3 text-center backdrop-blur"><p class="text-lg font-extrabold">Fast</p><p class="text-[11px] text-white/65">Delivery</p></div>
      <div class="rounded-2xl bg-white/10 p-3 text-center backdrop-blur"><p class="text-lg font-extrabold">COD</p><p class="text-[11px] text-white/65">Pay on arrival</p></div>
    </div>
  </div>

  <div class="max-h-[92vh] overflow-y-auto p-6 sm:p-10">
    <div class="flex items-center gap-2 md:hidden">
      <img src="{{ asset('images/logo.png') }}" onerror="this.style.display='none'" alt="INVOIZ" class="h-9 w-9 rounded-xl object-cover ring-1 ring-gray-200">
      <span class="text-lg font-extrabold text-teal-dark">INVOIZ</span>
    </div>
    <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-charcoal md:mt-0">Create your account</h1>
    <p class="mt-1 text-sm text-gray-500">Always a <b>buyer</b> account — sell later from <a href="{{ url('/sell') }}" class="font-bold text-teal">/sell</a>.</p>
    @if(!empty($google['email']))
      <div class="mt-4 flex items-center gap-2.5 rounded-2xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700 ring-1 ring-emerald-100">
        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.27-4.74 3.27-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.1a7.16 7.16 0 010-4.2V7.06H2.18a11 11 0 000 9.88l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>
        <span>Google-verified email — no code needed, just complete your profile.</span>
      </div>
    @endif

    @if($errors->any())<div class="mt-4 rounded-2xl bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 ring-1 ring-red-100">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ url('/register') }}" class="mt-6 space-y-4">
      @csrf
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="mb-1.5 block text-xs font-extrabold uppercase tracking-wider text-gray-500" for="first_name">First name</label>
          <div class="field"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
          <input id="first_name" name="first_name" value="{{ old('first_name', $google['first_name'] ?? '') }}" required placeholder="Juan"></div>
        </div>
        <div>
          <label class="mb-1.5 block text-xs font-extrabold uppercase tracking-wider text-gray-500" for="last_name">Last name</label>
          <div class="field"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
          <input id="last_name" name="last_name" value="{{ old('last_name', $google['last_name'] ?? '') }}" required placeholder="Dela Cruz"></div>
        </div>
      </div>
      <div>
        <label class="mb-1.5 block text-xs font-extrabold uppercase tracking-wider text-gray-500" for="email">Email</label>
        <div class="field"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.9 5.3a2 2 0 002.2 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        <input id="email" type="email" name="email" value="{{ old('email', $google['email'] ?? '') }}" required placeholder="you@example.com" @if(!empty($google['email'])) readonly class="bg-gray-50" @endif></div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="mb-1.5 block text-xs font-extrabold uppercase tracking-wider text-gray-500" for="sex">Sex</label>
          <div class="field"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5c-4.5 0-7.5 3-7.5 7s3 7 7.5 7 7.5-3 7.5-7-3-7-7.5-7zm0 0v13"/></svg>
          <select id="sex" name="sex" required><option value="">Select...</option><option value="male" @selected(old('sex')==='male')>Male</option><option value="female" @selected(old('sex')==='female')>Female</option><option value="other" @selected(old('sex')==='other')>Other</option></select></div>
        </div>
        <div>
          <label class="mb-1.5 block text-xs font-extrabold uppercase tracking-wider text-gray-500" for="birthday">Birthday</label>
          <div class="field"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          <input id="birthday" type="date" name="birthday" value="{{ old('birthday') }}" required max="{{ date('Y-m-d', strtotime('-1 day')) }}"></div>
        </div>
      </div>
      <div>
        <label class="mb-1.5 block text-xs font-extrabold uppercase tracking-wider text-gray-500" for="phone">Phone <span class="font-semibold normal-case text-gray-400">(optional)</span></label>
        <div class="field"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.3a1 1 0 01.9.7l1.5 4a1 1 0 01-.3 1.1L8.1 10a16 16 0 006 6l1.2-2.3a1 1 0 011.1-.3l4 1.5a1 1 0 01.7.9V19a2 2 0 01-2 2h-1C9.7 21 3 14.3 3 6V5z"/></svg>
        <input id="phone" name="phone" value="{{ old('phone') }}" placeholder="09xx xxx xxxx"></div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="mb-1.5 block text-xs font-extrabold uppercase tracking-wider text-gray-500" for="password">Password</label>
          <div class="field">
          <input id="password" type="password" name="password" required placeholder="Min. 8 characters" class="pad" style="padding-left:1rem">
          <button type="button" class="eye" onclick="var i=document.getElementById('password');var s=i.type==='password';i.type=s?'text':'password';this.textContent=s?'Hide':'Show';">Show</button></div>
        </div>
        <div>
          <label class="mb-1.5 block text-xs font-extrabold uppercase tracking-wider text-gray-500" for="password_confirmation">Confirm</label>
          <div class="field">
          <input id="password_confirmation" type="password" name="password_confirmation" required placeholder="Repeat it" class="pad" style="padding-left:1rem">
          <button type="button" class="eye" onclick="var i=document.getElementById('password_confirmation');var s=i.type==='password';i.type=s?'text':'password';this.textContent=s?'Hide':'Show';">Show</button></div>
        </div>
      </div>
      <button class="btn-primary w-full rounded-2xl py-3.5 text-sm font-extrabold text-white">Create My Buyer Account →</button>
    </form>

    <div class="my-5 flex items-center gap-3 text-xs font-bold text-gray-400"><span class="h-px flex-1 bg-gray-200"></span>OR<span class="h-px flex-1 bg-gray-200"></span></div>
    <a href="{{ url('/auth/google') }}" class="flex w-full items-center justify-center gap-2.5 rounded-2xl border-2 border-gray-200 bg-white py-3 text-sm font-extrabold text-charcoal transition hover:border-gray-300 hover:bg-gray-50">
      <svg class="h-5 w-5" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.27-4.74 3.27-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.1a7.16 7.16 0 010-4.2V7.06H2.18a11 11 0 000 9.88l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>
      Continue with Gmail
    </a>

    <p class="mt-6 text-center text-sm text-gray-500">Already have an account? <a href="{{ url('/login') }}" class="font-extrabold text-teal hover:text-teal-dark">Log in</a></p>
    <p class="mt-3 text-center text-xs text-gray-400"><a href="{{ url('/') }}" class="hover:text-teal-dark">← Back to marketplace</a></p>
  </div>
</div>
</body>
</html>
