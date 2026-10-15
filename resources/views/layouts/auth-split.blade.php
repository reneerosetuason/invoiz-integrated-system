<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'INVOIZ')</title>
<link rel="icon" href="{{ asset('images/logo.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,560;9..144,700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script>document.documentElement.classList.add('js');</script>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config={theme:{extend:{colors:{teal:{DEFAULT:'#16697A',dark:'#0E4A57',pale:'#EAF4F3'},ivory:'#FBF9F6',ink:'#1B1B1E',muted:'#6E6E73',line:'rgba(15,76,92,.12)',err:'#B42318'},fontFamily:{sans:['Inter','system-ui','-apple-system','Segoe UI','sans-serif'],display:['Fraunces','Georgia','serif']}}}};
</script>
<style>
[x-cloak]{display:none!important}
.font-display{font-family:'Fraunces',Georgia,'Times New Roman',serif}
.tnum{font-variant-numeric:tabular-nums}
.eyebrow{display:flex;align-items:center;gap:.5rem;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.18em;color:#0E4A57}
.eyebrow::before{content:"";width:.45rem;height:.45rem;background:#0E4A57;flex-shrink:0}
a:focus-visible,button:focus-visible,input:focus-visible,select:focus-visible{outline:2px solid #0E4A57;outline-offset:2px}
.lnk{background-image:linear-gradient(currentColor,currentColor);background-size:0% 1px;background-repeat:no-repeat;background-position:left 100%;transition:background-size .25s cubic-bezier(.22,1,.36,1)}
.lnk:hover{background-size:100% 1px}
.press{transition:transform .2s cubic-bezier(.22,1,.36,1)}
.press:active{transform:scale(.98)}
.ufield{position:relative}
.ufield input,.ufield select{background:transparent;border:0;border-bottom:1px solid rgba(15,76,92,.25);height:44px;width:100%;font-size:15px;color:#1B1B1E;outline:none;transition:border-color .2s cubic-bezier(.22,1,.36,1)}
.ufield input{padding-left:2rem;padding-right:.25rem}
.ufield select{appearance:none;-webkit-appearance:none;padding-left:2rem;padding-right:2rem;cursor:pointer}
.ufield input::placeholder{color:#6E6E73}
.ufield .bar{position:absolute;bottom:0;left:0;height:2px;width:100%;background:#0E4A57;transform:scaleX(0);transform-origin:left;transition:transform .25s cubic-bezier(.22,1,.36,1)}
.ufield input:focus ~ .bar,.ufield select:focus ~ .bar{transform:scaleX(1)}
.ufield:focus-within label{color:#0E4A57}
.ufield input.field-error,.ufield select.field-error{border-bottom-color:#B42318}
.js .rv{opacity:0;transform:translateY(24px);transition:opacity .7s cubic-bezier(.22,1,.36,1),transform .7s cubic-bezier(.22,1,.36,1)}
.js .rv-r{opacity:0;transform:translate(12px,12px);transition:opacity .7s cubic-bezier(.22,1,.36,1),transform .7s cubic-bezier(.22,1,.36,1)}
.js .rv.in,.js .rv-r.in{opacity:1;transform:none}
.mask-line{display:block;overflow:hidden;padding-bottom:.08em;margin-bottom:-.08em}
.mask-line>span{display:block;transform:translateY(112%);transition:transform .8s cubic-bezier(.22,1,.36,1)}
.in .mask-line>span{transform:none}
@keyframes shakeOnce{0%,100%{transform:translateX(0)}25%{transform:translateX(-7px)}55%{transform:translateX(5px)}80%{transform:translateX(-3px)}}
.shake-once{animation:shakeOnce .45s cubic-bezier(.22,1,.36,1) 1}
@keyframes spin360{to{transform:rotate(360deg)}}
.spinner{animation:spin360 .8s linear infinite}
.err-msg{animation:errIn .3s cubic-bezier(.22,1,.36,1) both}
@keyframes errIn{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:none}}
@keyframes driftbg{0%,100%{transform:translate3d(0,0,0)}50%{transform:translate3d(0,-22px,0)}}
.bg-drift{animation:driftbg 38s ease-in-out infinite}
body{background:linear-gradient(115deg,#FBF9F6 0%,#F4F5EF 48%,#EFF4F1 100%)}
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after { animation: none !important; transition: none !important; }
  .js .rv,.js .rv-r{opacity:1;transform:none}
  .js .mask-line>span{transform:none}
  .bg-drift{animation:none}
}
</style>
</head>
<body class="font-sans text-ink antialiased text-[15px] md:text-base leading-relaxed">
<div class="bg-drift pointer-events-none fixed inset-0" style="background:linear-gradient(115deg,#FBF9F6 0%,#F4F5EF 48%,#EFF4F1 100%)" aria-hidden="true"></div>
<div class="pointer-events-none fixed inset-0" style="opacity:.03;background-image:url(&quot;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='140' height='140'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2'/%3E%3C/filter%3E%3Crect width='140' height='140' filter='url(%23n)' opacity='1'/%3E%3C/svg%3E&quot;)" aria-hidden="true"></div>
@section('back_link')
<a href="{{ url('/') }}" class="absolute right-4 top-5 z-10 inline-flex items-center gap-1.5 text-sm font-medium text-muted hover:text-ink sm:right-8 sm:top-7"><span aria-hidden="true">←</span> Back to marketplace</a>
@show
<main class="relative grid px-8 lg:px-12 min-[900px]:grid-cols-[55fr_45fr]" style="min-height:100dvh;overflow-y:clip">
<div class="mx-auto flex w-full flex-col py-8 lg:py-10 min-[900px]:sticky min-[900px]:top-0 min-[900px]:h-[100dvh]" style="max-width:560px">
<div class="rv hidden items-center gap-2.5 min-[900px]:flex">
<img src="{{ asset('images/logo.png') }}" onerror="this.style.display='none'" alt="INVOIZ" class="h-9 w-9 rounded-xl object-cover">
<span><span class="block text-xl font-extrabold tracking-tight text-teal-dark leading-none">INVOIZ</span>@yield('wordmark_extra')</span>
</div>
<div class="flex items-center gap-2.5 min-[900px]:hidden" aria-hidden="false">
<img src="{{ asset('images/logo.png') }}" onerror="this.style.display='none'" alt="INVOIZ" class="h-9 w-9 rounded-xl object-cover">
<span class="text-lg font-extrabold tracking-tight text-teal-dark">INVOIZ</span>
</div>
<p class="mt-3 font-display text-teal-dark min-[900px]:hidden" style="font-weight:560;font-size:1.65rem;line-height:1.1;letter-spacing:-0.02em">@yield('brand_plain')</p>
<div class="my-auto hidden py-10 min-[900px]:block">
@hasSection('brand_eyebrow')
<p class="eyebrow rv">@yield('brand_eyebrow')</p>
@endif
<p class="rv font-display mt-4 tracking-[-0.02em] text-teal-dark" style="font-weight:560;font-size:clamp(2.25rem,4vw,3.5rem);line-height:1.05">@yield('brand_lines')</p>
<p class="rv mt-5 max-w-md text-[15px] leading-relaxed text-muted" style="transition-delay:300ms">@yield('brand_copy')</p>
</div>
@section('left_bottom')
<ul class="rv hidden flex-wrap items-center gap-x-7 gap-y-2 border-t pt-5 min-[900px]:flex" style="border-color:rgba(15,76,92,.12);transition-delay:380ms" aria-label="Why shop with Invoiz">
<li class="flex items-center gap-2 whitespace-nowrap text-[13px] font-semibold text-teal-dark"><svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2 9a2 2 0 012-2h16a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16 13h.01M6 9V7a2 2 0 012-2h8a2 2 0 012 2v2"/></svg>Cash on delivery</li>
<li class="flex items-center gap-2 whitespace-nowrap text-[13px] font-semibold text-teal-dark"><svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9l9-6 9 6v11a1 1 0 01-1 1H4a1 1 0 01-1-1V9z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 21v-7h6v7"/></svg>Local sellers</li>
<li class="flex items-center gap-2 whitespace-nowrap text-[13px] font-semibold text-teal-dark"><svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h11v9H3zM14 10h4l4 4v2h-8z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17" cy="18" r="1.6"/></svg>Rider-tracked delivery</li>
</ul>
@show
</div>
<div class="flex @yield('right-scroll')">
<div class="m-auto w-full py-5 min-[900px]:py-8" style="max-width:400px">
@hasSection('form_eyebrow')
<p class="eyebrow rv-r mt-5 min-[900px]:mt-0">@yield('form_eyebrow')</p>
@endif
@yield('form_head')
@yield('form')
@yield('after_form')
<p class="rv-r mt-8 text-xs text-muted" style="transition-delay:400ms">© 2026 INVOIZ</p>
</div>
</div>
</main>
<script>
(function(){
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var els = document.querySelectorAll('.rv,.rv-r');
  if ('IntersectionObserver' in window && !reduce) {
    var io = new IntersectionObserver(function(es){ es.forEach(function(e){ if(e.isIntersecting){ e.target.classList.add('in'); io.unobserve(e.target); } }); }, {threshold:.15});
    els.forEach(function(s){ io.observe(s); });
  } else { els.forEach(function(s){ s.classList.add('in'); }); }
  document.querySelectorAll('[data-pw-toggle]').forEach(function(btn){
    btn.addEventListener('click', function(){
      var input = document.getElementById(btn.getAttribute('data-pw-toggle'));
      if (!input) return;
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      btn.setAttribute('aria-pressed', show ? 'true' : 'false');
      var eye = btn.querySelector('[data-eye]');
      var eyeOff = btn.querySelector('[data-eye-off]');
      if (eye) eye.classList.toggle('hidden', show);
      if (eyeOff) eyeOff.classList.toggle('hidden', !show);
      input.focus({preventScroll:true});
    });
  });
  document.querySelectorAll('form[data-guard]').forEach(function(form){
    form.addEventListener('submit', function(){
      var btn = form.querySelector('[type="submit"]');
      if (!btn || btn.disabled) return;
      btn.disabled = true;
      var arrow = btn.querySelector('[data-arrow]');
      var spin = btn.querySelector('[data-spin]');
      if (arrow) arrow.style.display = 'none';
      if (spin) spin.classList.remove('hidden');
    });
  });
})();
</script>
</body>
</html>
