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

        <script>
            // Apply the saved appearance before first paint (Settings →
            // Appearance). Runs inline so there is no light-mode flash.
            window.__invoizTheme = @js($userTheme);
            (function () {
                var t = window.__invoizTheme;
                if (t === 'dark' || (t === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dark');
                }
                if (t === 'system' && window.matchMedia) {
                    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (e) {
                        document.documentElement.classList.toggle('dark', e.matches);
                    });
                }
            })();
        </script>
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                darkMode: 'class',
                theme: {
                    extend: {
                        colors: {
                            teal: {
                                DEFAULT: '#16697A',
                                dark: '#0E4A57',
                                light: '#EAF4F3',
                            },
                            secondary: '#F0A202',
                            'surface-soft': '#F0EEE9',
                        },
                        fontFamily: {
                            sans: ['Segoe UI', 'system-ui', '-apple-system', 'sans-serif'],
                        },
                    },
                },
            };
        </script>
        <style>
            /* Class-based dark theme (Settings → Appearance). Only the neutral
               surfaces/text flip; teal/amber/functional colors are untouched. */
            .dark { color-scheme: dark; }
            .dark body { background-color: #111827; color: #F3F4F6; }
            .dark .bg-white, .dark .bg-white\/90, .dark .bg-white\/95 { background-color: #1F2937; }
            .dark .bg-gray-50, .dark .bg-gray-50\/50 { background-color: #374151; }
            .dark .bg-gray-100, .dark .bg-surface-soft, .dark .bg-surface-soft\/50 { background-color: #374151; }
            .dark .text-gray-900 { color: #F9FAFB; }
            .dark .text-gray-800 { color: #F3F4F6; }
            .dark .text-gray-700 { color: #E5E7EB; }
            .dark .text-gray-600 { color: #D1D5DB; }
            .dark .text-gray-500 { color: #9CA3AF; }
            .dark .text-gray-400 { color: #6B7280; }
            .dark .text-teal, .dark .text-teal-dark { color: #5EEAD4; }
            .dark .hover\:text-teal:hover, .dark .hover\:text-teal-dark:hover { color: #5EEAD4; }
            .dark .border-gray-100, .dark .border-gray-200, .dark .divide-gray-100, .dark .divide-gray-50,
            .dark .ring-gray-200 { border-color: #374151; }
            .dark .border-gray-300 { border-color: #4B5563; }
            .dark .hover\:bg-gray-50:hover, .dark .hover\:bg-gray-100:hover, .dark .hover\:bg-gray-200:hover { background-color: #374151; }
            .dark input, .dark select, .dark textarea { background-color: #111827; color: #F3F4F6; border-color: #4B5563; }
            .dark input::placeholder, .dark textarea::placeholder { color: #6B7280; }
        </style>
        <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
        <link rel="stylesheet" href="{{ asset('css/app-sidebar.css') }}?v={{ filemtime(public_path('css/app-sidebar.css')) }}">
        <script src="{{ asset('js/app-sidebar.js') }}?v={{ filemtime(public_path('js/app-sidebar.js')) }}" defer></script>
    </head>
    <body class="font-sans bg-[#F7F6F2] text-[#1B1B1E] antialiased">
        <div class="min-h-screen" x-data="{
            sidebarHover: @js($sidebarStartExpanded),
            deliveriesOpen: @js(request()->routeIs('deliveries.scan-page')),
            applicationsOpen: @js(request()->routeIs('center-applications.*')),
            navPersistTimeout: null,
            notifOpen: false,
            notifications: [],
            unreadCount: 0,
            get sidebarVisible() { return this.sidebarHover; },
            toggleSidebar() {
                this.sidebarHover = !this.sidebarHover;
            }
        }" x-init="
            fetch('{{ route('notifications.index', [], false) }}')
                .then(r => r.json())
                .then(d => { notifications = d.notifications; unreadCount = d.unread_count; });
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
            @include('logistics.layouts.sidebar')

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
                <header class="sticky top-0 z-20 bg-white/90 backdrop-blur border-b border-gray-200">
                    <div class="flex items-center justify-between px-4 sm:px-6 h-16">
                        <div class="flex items-center gap-3">
                            <span class="text-lg font-bold text-gray-900">Logistics</span>
                        </div>

                        <div class="flex items-center gap-2">
                            <!-- Notification Bell -->
                            <div class="relative">
                                <button @click="notifOpen = !notifOpen" @click.outside="notifOpen = false" class="relative text-gray-500 hover:text-gray-700 p-2 rounded-lg hover:bg-gray-100">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                    </svg>
                                    <span x-show="unreadCount > 0" x-text="unreadCount" class="absolute -top-0.5 -right-0.5 h-4.5 w-4.5 bg-secondary text-white text-[10px] font-bold rounded-full flex items-center justify-center min-w-[18px] h-[18px]"></span>
                                </button>

                                <!-- Notification Dropdown -->
                                <div x-show="notifOpen" x-transition x-cloak
                                     class="absolute right-0 mt-2 w-80 sm:w-96 bg-white rounded-xl shadow-lg ring-1 ring-gray-200 z-50 max-h-[70vh] flex flex-col">
                                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                                        <h3 class="text-sm font-semibold text-gray-900">Notifications</h3>
                                        <button @click="
                                            fetch('{{ route('notifications.mark-all-read', [], false) }}', { method: 'PATCH', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
                                                .then(r => r.json())
                                                .then(d => { unreadCount = d.unread_count; notifications.forEach(n => n.is_read = true); })
                                        " x-show="unreadCount > 0" class="text-xs font-medium text-teal hover:text-teal-dark">Mark all read</button>
                                    </div>

                                    <div class="overflow-y-auto flex-1 divide-y divide-gray-50">
                                        <template x-if="notifications.length === 0">
                                            <div class="px-4 py-8 text-center">
                                                <p class="text-sm text-gray-400">No notifications yet.</p>
                                            </div>
                                        </template>
                                        <template x-for="n in notifications" :key="n.id">
                                            <div @click="
                                                if (!n.is_read) {
                                                    fetch('/logistics/notifications/' + n.id + '/read', { method: 'PATCH', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
                                                        .then(r => r.json())
                                                        .then(d => { unreadCount = d.unread_count; n.is_read = true; });
                                                }
                                                if (n.link) window.location.href = n.link;
                                            " class="flex items-start gap-3 px-4 py-3 cursor-pointer transition-colors"
                                               :class="n.is_read ? 'bg-white hover:bg-gray-50' : 'bg-teal-light/30 hover:bg-teal-light/50'">
                                                <span class="text-lg flex-shrink-0 mt-0.5" x-text="n.icon"></span>
                                                <div class="min-w-0 flex-1">
                                                    <p class="text-sm font-medium text-gray-900" x-text="n.title"></p>
                                                    <p class="text-xs text-gray-500 mt-0.5 line-clamp-2" x-text="n.message"></p>
                                                    <p class="text-[11px] text-gray-400 mt-1" x-text="new Date(n.created_at).toLocaleString()"></p>
                                                </div>
                                                <div x-show="!n.is_read" class="h-2 w-2 rounded-full bg-teal flex-shrink-0 mt-2"></div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <!-- Messages Icon -->
                            <a href="{{ route('messages.index') }}" class="relative text-gray-500 hover:text-gray-700 p-2 rounded-lg hover:bg-gray-100" title="Messages">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                </svg>
                                @php try { $topbarUnreadMsgs = \App\Models\Logistics\Conversation::sum('unread_count'); } catch (\Throwable $e) { $topbarUnreadMsgs = 0; } @endphp
                                @if($topbarUnreadMsgs)
                                    <span class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] bg-secondary text-white text-[10px] font-bold rounded-full flex items-center justify-center px-1">{{ $topbarUnreadMsgs }}</span>
                                @endif
                            </a>
                        </div>
                    </div>
                </header>

                <!-- Page Content -->
                <main class="flex-1 p-4 sm:p-6 lg:p-8">
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

                    <div class="max-w-7xl mx-auto">
                        @isset($header)
                            <div class="mb-6">{{ $header }}</div>
                        @endisset

                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>
        <style>[x-cloak] { display: none !important; }</style>
        @include('logistics.layouts.partials.form-controls')
        @stack('scripts')
    </body>
</html>