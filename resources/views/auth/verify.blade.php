<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Verify Email — INVOIZ</title>
<link rel="icon" href="{{ asset('images/logo.png') }}">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = { theme: { extend: {
  colors: { teal: { DEFAULT:'#16697A', dark:'#0E4A57', light:'#EAF4F3' }, amber: { DEFAULT:'#F0A202', light:'#FFF8E1' }, offwhite:'#F8FAF9', charcoal:'#1F2933' },
  fontFamily: { sans:['Segoe UI','system-ui','-apple-system','sans-serif'] },
  boxShadow: { soft:'0 24px 60px rgba(14,74,87,.14)' },
}}};
</script>
<style>
  body{background:#F8FAF9;font-family:'Segoe UI',system-ui,-apple-system,sans-serif}
  .mesh{background:
    radial-gradient(38rem 20rem at 8% -60px, rgba(240,162,2,.22), transparent 60%),
    radial-gradient(34rem 20rem at 105% 15%, rgba(22,105,122,.18), transparent 60%),
    linear-gradient(135deg,#F8FAF9 0%,#ffffff 55%,#EAF4F3 100%)}
  #otp{letter-spacing:.6rem;text-indent:.6rem}
  #otp:focus{border-color:#16697A;box-shadow:0 0 0 4px rgba(22,105,122,.12);outline:none}
  .btn-primary{background:linear-gradient(135deg,#16697A,#0E4A57);transition:transform .2s,filter .2s}
  .btn-primary:hover{transform:translateY(-1px);filter:brightness(1.06)}
</style>
</head>
<body class="mesh min-h-screen flex items-center justify-center p-4">
<div class="w-full max-w-md overflow-hidden rounded-[2rem] bg-white shadow-soft ring-1 ring-black/5">
  <div class="bg-gradient-to-br from-teal via-teal-dark to-[#0B3B46] p-8 text-center text-white">
    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 text-white backdrop-blur"><svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.9 5.3a2 2 0 002.2 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg></div>
    <h1 class="mt-4 text-xl font-extrabold">Check your Gmail</h1>
    <p class="mt-1 text-sm text-white/75">We sent a 6-digit code to<br><b class="text-white">{{ $email }}</b></p>
  </div>
  <div class="p-6 sm:p-8">
    @if($errors->any())<div class="mb-4 rounded-2xl bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 ring-1 ring-red-100">{{ $errors->first() }}</div>@endif
    @if(session('success'))<div class="mb-4 rounded-2xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700 ring-1 ring-emerald-100">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 rounded-2xl bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 ring-1 ring-red-100">{{ session('error') }}</div>@endif
    <form method="POST" action="{{ url('/verify') }}">
      @csrf
      <input type="hidden" name="email" value="{{ $email }}">
      <label class="mb-1.5 block text-center text-xs font-extrabold uppercase tracking-wider text-gray-500" for="otp">Enter code</label>
      <input id="otp" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required placeholder="••••••"
        class="w-full rounded-2xl border-2 border-gray-200 px-4 py-4 text-center text-2xl font-extrabold text-teal-dark">
      <button class="btn-primary mt-4 w-full rounded-2xl py-3.5 text-sm font-extrabold text-white">Verify &amp; Continue →</button>
    </form>
    <div class="mt-4 text-center text-sm text-gray-500">
      Didn't get it?
      <form method="POST" action="{{ url('/verify/resend') }}" class="inline">
        @csrf
        <input type="hidden" name="email" value="{{ $email }}">
        <button id="resendBtn" class="font-extrabold text-teal hover:text-teal-dark">Resend code</button>
      </form>
      <span id="resendTimer" class="text-gray-400"></span>
    </div>
    <p class="mt-4 text-center text-xs text-gray-400"><a href="{{ url('/login') }}" class="hover:text-teal-dark">← Back to login</a></p>
  </div>
</div>
<script>
(function(){
  var btn = document.getElementById('resendBtn'), tag = document.getElementById('resendTimer'), s = 60;
  btn.disabled = true; btn.classList.add('opacity-40');
  var t = setInterval(function(){
    s--;
    if(s <= 0){ clearInterval(t); btn.disabled = false; btn.classList.remove('opacity-40'); tag.textContent = ''; }
    else { tag.textContent = '(' + s + 's)'; }
  }, 1000);
  var otp = document.getElementById('otp');
  otp.addEventListener('input', function(){ otp.value = otp.value.replace(/\D/g,'').slice(0,6); });
})();
</script>
</body>
</html>
