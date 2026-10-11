<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="INVOIZ Rider entry — open the Rider App or continue on the web.">

    <title>Rider Entry — INVOIZ</title>

    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        teal: { DEFAULT: '#16697A', dark: '#0E4A57', light: '#EAF4F3' },
                        secondary: '#F0A202',
                    },
                    fontFamily: { sans: ['Segoe UI', 'system-ui', '-apple-system', 'sans-serif'] },
                },
            },
        };
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak]{display:none!important}
        :focus-visible{outline:2px solid #F0A202;outline-offset:2px;border-radius:8px}
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
        }
    </style>
</head>
<body class="font-sans antialiased bg-gradient-to-br from-[#16697A] via-[#0E4A57] to-[#0B3B46] min-h-screen flex items-center justify-center p-4 sm:p-8">
    <main class="w-full max-w-md bg-white rounded-3xl shadow-xl p-6 sm:p-8" x-data="{ tried:false, fallback:false }">
        <div class="flex items-center gap-3">
            <img src="{{ asset('images/logo.png') }}" alt="INVOIZ logo" class="h-11 w-11 rounded-xl object-cover ring-1 ring-gray-200">
            <div>
                <h1 class="text-xl font-extrabold tracking-tight text-gray-900">Rider Entry</h1>
                <p class="text-xs text-gray-500">Signed in as {{ auth()->user()->name }}</p>
            </div>
        </div>

        <p class="mt-4 text-sm text-gray-600 leading-relaxed">
            Your rider workspace lives in the INVOIZ Rider App. If it is installed on this device, open it below — otherwise continue on the web.
        </p>

        <div class="mt-5 space-y-2.5">
            <p class="text-[11px] font-mono bg-gray-50 px-2.5 py-1.5 rounded-lg border border-gray-100 text-gray-600 break-all" aria-label="Rider app deep link">{{ $deeplink }}</p>
            <button type="button"
                    @click="tried=true; fallback=false; window.location.href='{{ $deeplink }}'; setTimeout(()=>{ if(!document.hidden) fallback=true; }, 1200)"
                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-sm font-semibold bg-teal text-white hover:bg-teal-dark shadow-sm min-h-[44px]">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                Open Rider App
            </button>
            <p x-show="fallback" x-cloak role="status" class="text-[11px] text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">App did not open — please ensure the Rider App is installed on this device, or continue on the web below.</p>
            <p x-show="tried && !fallback" class="text-[11px] text-gray-500 text-center">Attempting to open app…</p>
            <a href="{{ route('rider.messages') }}"
               class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-sm font-semibold bg-white border border-gray-200 text-gray-800 hover:bg-gray-50 min-h-[44px]">
                Continue on Web
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>

        <form method="POST" action="{{ route('logout') }}" class="mt-5 text-center">
            @csrf
            <button type="submit" class="text-xs font-semibold text-gray-400 hover:text-gray-600 underline underline-offset-2 min-h-[44px] px-4">Sign out</button>
        </form>
    </main>
</body>
</html>
