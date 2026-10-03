<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Dashboard' }} · Invoiz Seller</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { DEFAULT: '#16697A', dark: '#0E4A57', soft: '#EAF4F3' },
                        secondary: '#F0A202',
                        basebg: '#F7F6F2',
                        borderline: '#E8E6E0',
                        soft: '#F0EEE9',
                        ink: { DEFAULT: '#1B1B1E', light: '#6E6E73' },
                        successc: '#2E8B57',
                        warnc: '#E05A33',
                    },
                    borderRadius: { xl2: '18px' },
                    fontFamily: {
                        sans: ['Segoe UI', 'system-ui', '-apple-system', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
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
         class="fixed inset-0 z-40 bg-black/40 lg:hidden no-print"></div>

    <aside x-show="sidebarOpen" x-transition
           :class="sidebarCollapsed ? 'sidebar-collapsed lg:w-[60px]' : ''"
           class="fixed inset-y-0 left-0 z-50 flex w-[230px] flex-col bg-white border-r border-borderline
                  lg:sticky lg:top-0 lg:h-screen lg:shrink-0 lg:translate-x-0 lg:z-auto print:hidden no-print transition-[width] duration-200"
           x-cloak>
        {{-- Brand header --}}
        <div class="sb-brand-header relative px-4 pb-4 pt-5"
             style="background: linear-gradient(135deg, #16697A, #0E4A57);">
            <div class="flex items-center gap-2.5">
                <div class="sb-logo-wrap flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-white">
                    <img src="{{ asset('images/invoiz-mark.png') }}" alt="Invoiz" class="h-7 w-7 object-contain">
                </div>
                <div class="sb-hide-collapsed">
                    <div class="text-base font-extrabold tracking-wide text-white">Invoiz</div>
                    <div class="text-[9.5px] font-semibold uppercase tracking-widest text-white/70">Seller Center</div>
                </div>
            </div>

            <div class="sb-hide-collapsed mt-3.5 flex items-center gap-2.5 rounded-xl bg-white/10 px-3 py-2.5">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-white/90 text-xs font-bold text-[#0E4A57]">
                    {{ strtoupper(substr(auth()->user()->seller->business_name ?? 'S', 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <div class="truncate text-xs font-bold text-white">{{ auth()->user()->seller->business_name }}</div>
                    <div class="truncate text-[11px] text-white/70">{{ auth()->user()->seller->line_of_business }}</div>
                </div>
            </div>
        </div>

        {{-- Nav --}}
        <nav class="sidebar-nav min-h-0 flex-1 px-2.5 py-3">
            <div class="nav-section sb-hide-collapsed">Main</div>
            <a href="{{ route('seller.dashboard') }}" title="Dashboard" class="{{ request()->routeIs('seller.dashboard') ? 'active' : '' }}">
                <x-icon name="home" class="h-4 w-4 shrink-0" /><span class="sb-label">Dashboard</span>
            </a>
            <a href="{{ route('seller.orders.index') }}" title="Orders" class="{{ request()->routeIs('seller.orders.*') ? 'active' : '' }}">
                <x-icon name="shopping-bag" class="h-4 w-4 shrink-0" /><span class="sb-label">Orders</span>
            </a>

            <div class="nav-section sb-hide-collapsed">Store</div>
            <a href="{{ route('seller.products.index') }}" title="Inventory" class="{{ request()->routeIs('seller.products.*') ? 'active' : '' }}">
                <x-icon name="box" class="h-4 w-4 shrink-0" /><span class="sb-label">Inventory</span>
            </a>
            <a href="{{ route('seller.vouchers.index') }}" title="Vouchers" class="{{ request()->routeIs('seller.vouchers.*') ? 'active' : '' }}">
                <x-icon name="ticket" class="h-4 w-4 shrink-0" /><span class="sb-label">Vouchers</span>
            </a>
            <a href="{{ route('seller.feedback.index') }}" title="Customer Feedback" class="{{ request()->routeIs('seller.feedback.*') ? 'active' : '' }}">
                <x-icon name="star" class="h-4 w-4 shrink-0" /><span class="sb-label">Customer Feedback</span>
            </a>
            <a href="{{ route('seller.reports') }}" title="Reports" class="{{ request()->routeIs('seller.reports') ? 'active' : '' }}">
                <x-icon name="chart" class="h-4 w-4 shrink-0" /><span class="sb-label">Reports</span>
            </a>

            <div class="nav-section sb-hide-collapsed">Support</div>
            <a href="{{ route('seller.chat.index') }}" title="Chat / Messaging" class="{{ request()->routeIs('seller.chat.*') ? 'active' : '' }}">
                <x-icon name="chat" class="h-4 w-4 shrink-0" /><span class="sb-label">Chat / Messaging</span>
            </a>

            <div class="nav-section sb-hide-collapsed">Account</div>
            <a href="{{ route('seller.notifications.index') }}" title="Notifications" class="{{ request()->routeIs('seller.notifications.*') ? 'active' : '' }}">
                <x-icon name="bell" class="h-4 w-4 shrink-0" /><span class="sb-label">Notifications</span>
            </a>
            <a href="{{ route('seller.account') }}" title="Account Management" class="{{ request()->routeIs('seller.account') ? 'active' : '' }}">
                <x-icon name="user" class="h-4 w-4 shrink-0" /><span class="sb-label">Account Management</span>
            </a>

            <div class="mt-4">
                <form method="POST" action="{{ route('seller.logout') }}">
                    @csrf
                    <button type="submit" class="sb-logout flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-xs font-medium text-warnc transition hover:bg-red-50">
                        <x-icon name="logout" class="h-4 w-4 shrink-0" /><span class="sb-label">Logout</span>
                    </button>
                </form>
            </div>
        </nav>

        <div class="sb-footer px-3.5 py-2.5 text-[10px] text-ink-light border-t border-borderline no-print">
            Invoiz Seller · v1.0
        </div>
    </aside>

    {{-- ===================== MAIN ===================== --}}
    <div class="flex min-w-0 flex-1 flex-col">
        {{-- Topbar --}}
        <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-borderline bg-white px-4 lg:px-6 no-print">
            <button class="rounded-lg p-2 text-ink-light hover:bg-soft"
                    title="Toggle sidebar"
                    @click="window.innerWidth < 1024 ? (sidebarOpen = !sidebarOpen) : (sidebarCollapsed = !sidebarCollapsed); localStorage.setItem('sellerSidebarCollapsed', sidebarCollapsed ? '1' : '0')">
                <x-icon name="menu" class="h-5 w-5" />
            </button>

            <h1 class="truncate text-base font-bold tracking-tight">{{ $title ?? 'Dashboard' }}</h1>

            <div class="ml-auto flex items-center gap-2">
                {{-- Notifications --}}
                <div class="relative">
                    <button @click="notifyOpen = !notifyOpen; userOpen = false"
                            class="relative rounded-lg p-2 text-ink-light hover:bg-soft">
                        <x-icon name="bell" class="h-5 w-5" />
                        @if($layoutUnreadCount > 0)
                            <span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-warnc px-1 text-[9px] font-bold text-white">
                                {{ $layoutUnreadCount > 9 ? '9+' : $layoutUnreadCount }}
                            </span>
                        @endif
                    </button>

                    <div x-show="notifyOpen" @click.outside="notifyOpen = false" x-cloak x-transition
                         class="absolute right-0 mt-2 w-80 overflow-hidden rounded-2xl border border-borderline bg-white shadow-xl">
                        <div class="flex items-center justify-between border-b border-borderline px-4 py-3">
                            <span class="text-sm font-bold">Notifications</span>
                            @if($layoutUnreadCount > 0)
                                <form method="POST" action="{{ route('seller.notifications.read-all') }}">
                                    @csrf
                                    <button class="text-xs font-semibold text-primary hover:underline">Mark all read</button>
                                </form>
                            @endif
                        </div>
                        <div class="max-h-96 overflow-y-auto">
                            @forelse($layoutNotifications as $notification)
                                <a href="{{ route('seller.notifications.read', $notification->id) }}"
                                   class="flex gap-3 border-b border-borderline px-4 py-3 hover:bg-basebg">
                                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $notification->is_read ? 'bg-borderline' : 'bg-secondary' }}"></span>
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-semibold">{{ $notification->title }}</span>
                                        <span class="block truncate text-xs text-ink-light">{{ $notification->body }}</span>
                                        <span class="mt-0.5 block text-[11px] text-ink-light">{{ $notification->created_at->diffForHumans() }}</span>
                                    </span>
                                </a>
                            @empty
                                <div class="px-4 py-10 text-center text-sm text-ink-light">You're all caught up.</div>
                            @endforelse
                        </div>
                        <a href="{{ route('seller.notifications.index') }}"
                           class="block bg-basebg px-4 py-2.5 text-center text-xs font-bold text-primary hover:underline">
                            View all notifications
                        </a>
                    </div>
                </div>

                {{-- User --}}
                <div class="relative">
                    <button @click="userOpen = !userOpen; notifyOpen = false"
                            class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-soft">
                        <span class="avatar flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold text-white">
                            {{ strtoupper(substr(auth()->user()->first_name, 0, 1) . substr(auth()->user()->last_name, 0, 1)) }}
                        </span>
                        <span class="hidden text-left md:block">
                            <span class="block text-xs font-bold leading-tight">{{ auth()->user()->first_name }}</span>
                            <span class="block text-[10px] leading-tight text-ink-light">Seller</span>
                        </span>
                        <x-icon name="chevron-down" class="hidden h-3.5 w-3.5 text-ink-light md:block" />
                    </button>

                    <div x-show="userOpen" @click.outside="userOpen = false" x-cloak x-transition
                         class="absolute right-0 mt-2 w-56 overflow-hidden rounded-2xl border border-borderline bg-white py-1.5 shadow-xl">
                        <div class="border-b border-borderline px-4 py-3">
                            <div class="text-sm font-bold">{{ auth()->user()->full_name }}</div>
                            <div class="truncate text-xs text-ink-light">{{ auth()->user()->email }}</div>
                        </div>
                        <a href="{{ route('seller.account') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm hover:bg-basebg">
                            <x-icon name="user" class="h-4 w-4 text-ink-light" /> My Account
                        </a>
                        <a href="{{ route('seller.notifications.index') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm hover:bg-basebg">
                            <x-icon name="bell" class="h-4 w-4 text-ink-light" /> Notifications
                        </a>
                        <form method="POST" action="{{ route('seller.logout') }}" class="border-t border-borderline pt-1">
                            @csrf
                            <button class="flex w-full items-center gap-2.5 px-4 py-2.5 text-sm text-warnc hover:bg-red-50">
                                <x-icon name="logout" class="h-4 w-4" /> Logout
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

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

<style>
    [x-cloak] { display: none !important; }
</style>
@stack('scripts')
</body>
</html>