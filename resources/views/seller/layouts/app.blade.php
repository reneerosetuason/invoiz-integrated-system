<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Dashboard' }} · Invoiz Seller</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="{{ asset('assets/seller/js/layouts/app.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('assets/shared/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/shared/css/app-sidebar.css') }}?v={{ filemtime(public_path('assets/shared/css/app-sidebar.css')) }}">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    @stack('styles')
</head>
<body class="bg-basebg text-ink min-h-screen">
<div x-data="{ sidebarOpen: window.innerWidth >= 1024, sidebarCollapsed: localStorage.getItem('sellerSidebarCollapsed') === '1', notifyOpen: false, userOpen: false }"
     @keydown.escape.window="sidebarOpen = window.innerWidth >= 1024; notifyOpen = false; userOpen = false"
     class="flex min-h-screen">

    {{-- ===================== SIDEBAR ===================== --}}
    <div x-show="sidebarOpen" x-cloak x-transition.opacity
         @click="sidebarOpen = false"
         class="fixed inset-0 z-40 bg-[#0E4A57]/30 backdrop-blur-sm lg:hidden no-print"></div>

    @include('shared.navigation.sidebar', ['navigationRole' => 'seller', 'navigationVariant' => 'center'])

    {{-- ===================== MAIN ===================== --}}
    <div class="flex min-w-0 flex-1 flex-col">
        {{-- Topbar --}}
        @include('shared.navigation.navbar', ['navigationRole' => 'seller', 'navigationVariant' => 'center'])

        {{-- Flash messages --}}
        @if(session('success') || session('error') || $errors->any())
            <div class="px-3 pt-3 lg:px-6 no-print">
                @if(session('success'))
                    <div class="flex items-center gap-2 rounded-xl border border-successc/30 bg-green-50 px-3.5 py-2.5 text-xs font-semibold text-successc">
                        <x-icon name="check" class="h-4 w-4" /> {{ session('success') }}
                    </div>
                @endif
                @if(session('error'))
                    <div class="flex items-center gap-2 rounded-xl border border-warnc/30 bg-red-50 px-3.5 py-2.5 text-xs font-semibold text-warnc">
                        <x-icon name="alert-triangle" class="h-4 w-4" /> {{ session('error') }}
                    </div>
                @endif
                @if($errors->any())
                    @foreach($errors->all() as $error)
                        <div class="mt-2 flex items-center gap-2 rounded-xl border border-warnc/30 bg-red-50 px-3.5 py-2.5 text-xs font-semibold text-warnc">
                            <x-icon name="alert-triangle" class="h-4 w-4" /> {{ $error }}
                        </div>
                    @endforeach
                @endif
            </div>
        @endif

        <main class="flex-1 px-3 py-4 lg:px-6 lg:py-5">
            @yield('content')
        </main>
    </div>
</div>

<link rel="stylesheet" href="{{ asset('assets/seller/css/layouts/app.css') }}">
<script src="{{ asset('assets/shared/js/app-sidebar.js') }}?v={{ filemtime(public_path('assets/shared/js/app-sidebar.js')) }}"></script>
@stack('scripts')
@include('seller.partials.photo-editor')
</body>
</html>
