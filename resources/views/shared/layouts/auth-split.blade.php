<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'INVOIZ')</title>
<link rel="icon" href="{{ asset('assets/shared/images/logo.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,560;9..144,700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="{{ asset('assets/shared/js/enable-js.js') }}"></script>
<script src="https://cdn.tailwindcss.com"></script>
<script src="{{ asset('assets/shared/js/layouts/auth-split-2.js') }}"></script>
<link rel="stylesheet" href="{{ asset('assets/shared/css/layouts/auth-split.css') }}">
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
<img src="{{ asset('assets/shared/images/logo.png') }}" onerror="this.style.display='none'" alt="INVOIZ" class="h-9 w-9 rounded-xl object-cover">
<span><span class="block text-xl font-extrabold tracking-tight text-teal-dark leading-none">INVOIZ</span>@yield('wordmark_extra')</span>
</div>
<div class="flex items-center gap-2.5 min-[900px]:hidden" aria-hidden="false">
<img src="{{ asset('assets/shared/images/logo.png') }}" onerror="this.style.display='none'" alt="INVOIZ" class="h-9 w-9 rounded-xl object-cover">
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
<script src="{{ asset('assets/shared/js/layouts/auth-split-3.js') }}"></script>
</body>
</html>
