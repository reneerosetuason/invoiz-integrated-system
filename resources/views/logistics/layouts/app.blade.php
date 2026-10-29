<!DOCTYPE html>
@php
    // Per-user shell preferences (read-only here: never creates a settings
    // row as a side effect of rendering the layout).
    $layoutSettings = auth()->check()
        ? \App\Models\LogisticsSetting::where('user_id', auth()->id())->first()
        : null;
    $userTheme = $layoutSettings?->theme() ?? 'light';
    $sidebarRemember = (bool) ($layoutSettings?->rememberSidebar());
    $sidebarStartExpanded = $sidebarRemember && (bool) ($layoutSettings?->sidebarExpanded());
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Logistics') }}</title>

        <script src="{{ asset('assets/logistics/js/layouts/app.js') }}" data-config="{{ json_encode([($userTheme)], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) }}"></script>
        <script src="https://cdn.tailwindcss.com"></script>
        <script src="{{ asset('assets/logistics/js/layouts/app-2.js') }}"></script>
        <link rel="stylesheet" href="{{ asset('assets/logistics/css/layouts/app.css') }}">
        <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
        <link rel="stylesheet" href="{{ asset('assets/shared/css/app-sidebar.css') }}?v={{ filemtime(public_path('assets/shared/css/app-sidebar.css')) }}">
        <link rel="stylesheet" href="{{ asset('assets/logistics/css/logistics-shell.css') }}?v={{ filemtime(public_path('assets/logistics/css/logistics-shell.css')) }}">
        @if(!request()->routeIs('dashboard', 'deliveries.*'))
            @include('logistics.partials.workspace-theme')
        @endif
        <script src="{{ asset('assets/shared/js/app-sidebar.js') }}?v={{ filemtime(public_path('assets/shared/js/app-sidebar.js')) }}" defer></script>
        @stack('styles')
    </head>
    <body class="logistics-shell font-sans bg-[#F7F6F2] text-[#1B1B1E] antialiased">
        <div class="min-h-screen" x-data="{
            sidebarHover: @js($sidebarStartExpanded),
            sidebarKey: @js('invoiz:logistics:sidebar:' . auth()->id()),
            deliveriesOpen: @js(request()->routeIs('deliveries.scan-page')),
            applicationsOpen: @js(request()->routeIs('rider-applications.*', 'center-applications.*')),
            navPersistTimeout: null,
            notifOpen: false,
            accountOpen: false,
            notifications: [],
            notificationsError: false,
            unreadCount: 0,
            get sidebarVisible() { return this.sidebarHover; },
            toggleSidebar() {
                this.sidebarHover = !this.sidebarHover;
            }
        }" x-init="
            (() => { try {
                const saved = sessionStorage.getItem(sidebarKey);
                if (saved !== null) sidebarHover = saved === 'expanded';
            } catch (e) {} })();
            $watch('sidebarHover', (expanded) => {
                try { sessionStorage.setItem(sidebarKey, expanded ? 'expanded' : 'collapsed'); } catch (e) {}
            });
            fetch('{{ route('notifications.index', [], false) }}', {
                    headers: { 'Accept': 'application/json' },
                })
                .then(r => { if (!r.ok) throw new Error('Notifications unavailable'); return r.json(); })
                .then(d => { notifications = Array.isArray(d.notifications) ? d.notifications : []; unreadCount = Number(d.unread_count) || 0; })
                .catch(() => { notificationsError = true; notifications = []; unreadCount = 0; });
            @if($sidebarRemember)
            $watch('sidebarHover', (expanded) => {
                clearTimeout(this.navPersistTimeout);
                this.navPersistTimeout = setTimeout(() => {
                    fetch('{{ route('settings.update-navigation', [], false) }}', {
                        method: 'PATCH',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ sidebar_expanded: !!expanded })
                    });
                }, 800);
            });
            @endif
        ">
            @include('shared.navigation.sidebar', ['navigationRole' => 'logistics', 'navigationVariant' => 'workspace'])

            <!-- Overlay (mobile drawer: shown behind expanded sidebar on small screens) -->
            <div x-show="sidebarVisible" @click="sidebarHover = false"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="fixed inset-0 z-30 bg-black/40 lg:hidden"></div>

            <div class="app-content flex flex-col min-h-screen">
                <!-- Topbar -->
                @include('shared.navigation.navbar', ['navigationRole' => 'logistics', 'navigationVariant' => 'workspace'])

                <!-- Page Content -->
                <main class="flex-1 p-4 sm:p-6 lg:p-6">
                    @if(session('success'))
                        <div role="status" class="mb-5 flex items-start gap-3 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl text-sm" x-data="{ show: true }" x-show="show">
                            <svg class="h-5 w-5 flex-shrink-0 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="flex-1">{{ session('success') }}</span>
                            <button @click="show = false" class="text-green-500 hover:text-green-700">&times;</button>
                        </div>
                    @endif
                    @if(session('error'))
                        <div role="alert" class="mb-5 flex items-start gap-3 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm" x-data="{ show: true }" x-show="show">
                            <svg class="h-5 w-5 flex-shrink-0 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="flex-1">{{ session('error') }}</span>
                            <button @click="show = false" class="text-red-500 hover:text-red-700">&times;</button>
                        </div>
                    @endif
                    @if(session('warning'))
                        <div role="alert" class="mb-5 border border-amber-200 bg-amber-50 text-amber-900 px-4 py-3 rounded-xl text-sm">
                            {{ session('warning') }}
                        </div>
                    @endif

                    <div class="max-w-7xl mx-auto {{ !request()->routeIs('dashboard', 'deliveries.*') ? 'logistics-workspace' : '' }}">
                        @isset($header)
                            <div class="logistics-page-header mb-5">{{ $header }}</div>
                        @endisset

                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>
        <link rel="stylesheet" href="{{ asset('assets/logistics/css/layouts/app-2.css') }}">
        @include('logistics.layouts.partials.form-controls')
        <link rel="stylesheet" href="{{ asset('assets/logistics/css/logistics-density.css') }}?v={{ filemtime(public_path('assets/logistics/css/logistics-density.css')) }}">
        <script src="{{ asset('assets/logistics/js/logistics-density.js') }}?v={{ filemtime(public_path('assets/logistics/js/logistics-density.js')) }}" defer></script>
        @stack('scripts')
    </body>
</html>
