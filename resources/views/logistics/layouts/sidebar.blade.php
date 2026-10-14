@php
    $user = Auth::user();
    $isAdmin = in_array($user->role, ['admin', 'logistics_admin'], true);
    $isStaff = in_array($user->role, ['staff', 'logistics_staff'], true);
    $isRider = $user->role === 'rider';
    $userName = $user->name ?? 'User';
    $userEmail = $user->email ?? '';
    $userInitial = substr($userName, 0, 1);
    $accountTitle = $user->role === 'admin' ? $user->roleLabel() : $userName;
    $profilePhotoUrl = $user->profilePhotoUrl();
    $pickupPendingCount = ($isAdmin || $isStaff)
        ? \App\Models\PickupRequest::query()
            ->where('status', 'pending')
            ->when($isStaff, fn ($q) => $q->whereNotNull('center_id')->where('center_id', $user->center_id))
            ->count()
        : 0;

    $activeDashboard = request()->routeIs('dashboard');
    $activeDeliveries = request()->routeIs('deliveries.*');
    $activeScan      = request()->routeIs('deliveries.scan-page');
    $activeDeliveriesMain = $activeDeliveries && ! $activeScan;
    $activeRiders    = request()->routeIs('riders.*');
    $activePickups   = request()->routeIs('pickup-requests.*');
    $activeStaff     = request()->routeIs('staff.*');
    $activeRiderApps = request()->routeIs('rider-applications.*');
    $activeCenterApps = request()->routeIs('center-applications.*');
    $activeAppsGroup = $activeRiderApps || $activeCenterApps;
    $activeCenters   = request()->routeIs('centers.*');
    $activeAreas     = request()->routeIs('service-areas.*');
    $activeTransactions = request()->routeIs('transactions.*');
    $activeReports   = request()->routeIs('reports.*');
    $activeArchived  = request()->routeIs('deliveries.archived');
    $activeSettings  = request()->routeIs('settings.*');
@endphp

<style>
    /* ── Sidebar UX helpers ─────────────────────────────────────── */

    /* Active indicator: a dark-teal bar at the left edge of the
       current-page link (non-color indicator for accessibility). */
    .sidebar-root [aria-current="page"] {
        position: relative;
    }
    .sidebar-root [aria-current="page"]::before {
        content: "";
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 3px;
        height: 40%;
        border-radius: 0 999px 999px 0;
        background: #0E4A57;
    }

    /* Keyboard focus: consistent amber ring across all sidebar controls. */
    .sidebar-root a:focus-visible,
    .sidebar-root button:focus-visible {
        outline: 2px solid #F0A202;
        outline-offset: 2px;
        border-radius: 0.75rem;
    }

    /* Native width/padding transitions for the collapsible layout. */
    .sidebar-pane {
        width: 70px;
        transition: width 0.3s ease-in-out;
    }
    .sidebar-pane.is-expanded {
        width: 248px;
    }
    .app-content {
        padding-left: 70px;
        transition: padding-left 0.3s ease-in-out;
    }
    .sidebar-pane.is-expanded ~ .app-content {
        padding-left: 248px;
    }

    /* On small screens the expanded sidebar works as a drawer that
       overlays the content (backdrop provided by the layout). No
       overflow is applied to the sidebar at any size: the pane never
       scrolls and never shows a scrollbar — spacing/sizing contains
       the content. */
    @media (max-width: 1023px) {
        .sidebar-pane.is-expanded ~ .app-content {
            padding-left: 70px;
        }
    }

    /* Expanded-only density for medium/short viewports: the expanded pane
       carries section labels, dividers, and wider rows, so it needs more
       room than the collapsed rail. Trims expanded spacing/targets only —
       the collapsed rail is untouched. */
    @media (max-height: 820px) {
        /* The expanded header keeps h-16 so its divider stays aligned
           with the topbar divider; space is reclaimed below instead. */
        .sidebar-pane.is-expanded nav {
            row-gap: 2px;
            padding-top: 4px;
            padding-bottom: 4px;
        }
        .sidebar-pane.is-expanded nav a,
        .sidebar-pane.is-expanded nav button {
            height: 36px;
        }
        .sidebar-pane.is-expanded nav p {
            padding-top: 0;
            padding-bottom: 0;
        }
        .sidebar-pane.is-expanded nav > div {
            margin-top: 0;
            margin-bottom: 0;
        }
        .sidebar-pane.is-expanded > div:last-child {
            padding-top: 8px;
            padding-bottom: 8px;
            row-gap: 4px;
        }
        .sidebar-pane.is-expanded > div:last-child button {
            height: 36px;
        }
    }

    /* Compact density for short viewports: trims spacing (never targets,
       icons, or items) so the full rail still fits without scrolling.
       Applies whether the sidebar is collapsed or expanded. */
    @media (max-height: 720px) {
        .sidebar-pane nav {
            row-gap: 2px;
            padding-top: 4px;
            padding-bottom: 4px;
        }
        .sidebar-pane nav a,
        .sidebar-pane nav button {
            height: 36px;
        }
        .sidebar-pane > div:first-child {
            padding-top: 2px;
            padding-bottom: 2px;
        }
        .sidebar-pane > div:first-child button {
            height: 36px;
            width: 36px;
        }
        .sidebar-pane > div:last-child {
            padding-top: 8px;
            padding-bottom: 0;
            row-gap: 4px;
        }
        .sidebar-pane > div:last-child button {
            height: 36px;
        }
        .sidebar-pane > div:last-child .h-8 {
            height: 28px;
            width: 28px;
        }
    }

    /* Reduced-motion: respect user preference. */
    @media (prefers-reduced-motion: reduce) {
        .sidebar-root *,
        .sidebar-root *::before,
        .sidebar-root *::after,
        .sidebar-pane,
        .app-content {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
            transition-delay: 0ms !important;
        }
    }
</style>

<aside id="app-sidebar" class="sidebar-root sidebar-pane fixed inset-y-0 left-0 z-40 flex flex-col bg-[#F8FAF9] border-r border-teal/15"
       :class="{ 'is-expanded': sidebarHover }" :aria-expanded="sidebarHover ? 'true' : 'false'">

    {{-- Header. Expanded: larger INVOIZ logo + MENU text + toggle in a
         single row, sized h-16 so its divider aligns with the topbar.
         Collapsed: clean icon rail with ONLY the toggle — the logo (and
         its branding) never appears when collapsed. --}}
    <div class="flex-shrink-0 border-b border-teal/10 px-3 transition-all duration-300 flex"
         :class="sidebarHover ? 'flex-row items-center justify-between h-16 py-0 gap-2' : 'flex-col items-center justify-center h-16 py-0'">
        <a href="{{ route('dashboard') }}" aria-label="Home"
           x-show="sidebarHover" x-cloak
           class="flex items-center gap-2 min-w-0 whitespace-nowrap">
            <img src="{{ asset('images/logo.png') }}" alt="INVOIZ logo"
                 class="h-14 w-14 rounded-lg object-cover flex-shrink-0 transition-all duration-300">
            <span x-show="sidebarHover" x-cloak class="text-[10px] font-bold tracking-[0.25em] uppercase text-teal-dark flex-shrink-0">MENU</span>
        </a>
        <button type="button" @click="toggleSidebar()"
                :aria-expanded="sidebarHover ? 'true' : 'false'"
                aria-controls="app-sidebar"
                aria-label="Toggle navigation menu"
                class="group relative flex items-center justify-center h-10 w-10 rounded-xl text-teal-dark hover:bg-teal-light transition-colors flex-shrink-0">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
            <span class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 px-2.5 py-1 bg-white text-[#1F2933] text-xs font-medium rounded-lg border border-teal/20 shadow-lg whitespace-nowrap z-50 opacity-0 group-hover:opacity-100 transition-opacity"
                  x-show="!sidebarHover" role="tooltip" aria-hidden="true">Menu</span>
        </button>
    </div>

    <nav class="flex flex-col gap-1 px-2 py-2" role="navigation" aria-label="Primary">

        {{-- Dashboard --}}
        <a href="{{ route('dashboard') }}"
           aria-label="Dashboard"
           @if($activeDashboard) aria-current="page" @endif
           class="relative group flex items-center h-10 w-full rounded-xl transition-colors gap-3"
           :class="sidebarHover ? 'px-3 justify-start' : 'justify-center'">
            <svg class="h-5 w-5 flex-shrink-0 {{ $activeDashboard ? 'text-teal-dark' : 'text-teal' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block {{ $activeDashboard ? 'text-teal-dark' : 'text-[#1F2933]' }}">Dashboard</span>
            <span class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 px-2.5 py-1 bg-white text-[#1F2933] text-xs font-medium rounded-lg border border-teal/20 shadow-lg whitespace-nowrap z-50 opacity-0 group-hover:opacity-100 transition-opacity"
                  x-show="!sidebarHover" role="tooltip" aria-hidden="true">Dashboard</span>
        </a>

        @if(!$isRider)

        {{-- Deliveries (dropdown parent: the link navigates, the arrow toggles the child) --}}
        <div class="relative group flex items-center h-10 w-full rounded-xl transition-colors {{ $activeDeliveriesMain ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light hover:text-teal-dark' }}"
             :class="sidebarHover ? 'ps-3 pe-1 justify-start gap-1' : 'justify-center'">
            <a href="{{ route('deliveries.index') }}"
               aria-label="Deliveries"
               @if($activeDeliveriesMain) aria-current="page" @endif
               class="flex items-center gap-3 flex-1 min-w-0 h-full"
               :class="sidebarHover ? '' : 'justify-center'">
                <svg class="h-5 w-5 flex-shrink-0 {{ $activeDeliveriesMain ? 'text-teal-dark' : 'text-teal' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                </svg>
                <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block {{ $activeDeliveriesMain ? 'text-teal-dark' : 'text-[#1F2933]' }}">Deliveries</span>
            </a>
            <button type="button" x-show="sidebarHover" x-cloak
                    @click="deliveriesOpen = !deliveriesOpen"
                    :aria-expanded="deliveriesOpen ? 'true' : 'false'"
                    aria-controls="subnav-deliveries"
                    :aria-label="deliveriesOpen ? 'Collapse Deliveries' : 'Expand Deliveries'"
                    class="flex items-center justify-center h-10 w-10 rounded-xl text-teal-dark hover:bg-teal/20 transition-colors flex-shrink-0">
                <svg class="h-4 w-4 transition-transform motion-safe:transition-transform" :class="deliveriesOpen ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </button>
            <span class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 px-2.5 py-1 bg-white text-[#1F2933] text-xs font-medium rounded-lg border border-teal/20 shadow-lg whitespace-nowrap z-50 opacity-0 group-hover:opacity-100 transition-opacity"
                  x-show="!sidebarHover" role="tooltip" aria-hidden="true">Deliveries</span>
        </div>

        {{-- Scan Parcel (child of Deliveries) --}}
        <div id="subnav-deliveries" x-show="sidebarHover && deliveriesOpen" x-cloak x-collapse>
            <a href="{{ route('deliveries.scan-page') }}"
               aria-label="Scan Parcel"
               @if($activeScan) aria-current="page" @endif
               class="relative group flex items-center h-10 w-full rounded-xl transition-colors pl-14 pr-3 {{ $activeScan ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light/60' }}">
                <span class="text-[13px] font-medium whitespace-nowrap block {{ $activeScan ? 'text-teal-dark' : 'text-gray-600' }}">Scan Parcel</span>
            </a>
        </div>

        {{-- Riders --}}
        <a href="{{ route('riders.index') }}"
           aria-label="Riders"
           @if($activeRiders) aria-current="page" @endif
           class="relative group flex items-center h-10 w-full rounded-xl transition-colors gap-3 {{ $activeRiders ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light hover:text-teal-dark' }}"
           :class="sidebarHover ? 'px-3 justify-start' : 'justify-center'">
            <svg class="h-5 w-5 flex-shrink-0 {{ $activeRiders ? 'text-teal-dark' : 'text-teal' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block {{ $activeRiders ? 'text-teal-dark' : 'text-[#1F2933]' }}">Riders</span>
            <span class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 px-2.5 py-1 bg-white text-[#1F2933] text-xs font-medium rounded-lg border border-teal/20 shadow-lg whitespace-nowrap z-50 opacity-0 group-hover:opacity-100 transition-opacity"
                  x-show="!sidebarHover" role="tooltip" aria-hidden="true">Riders</span>
        </a>

        {{-- Pickup Requests --}}
        <a href="{{ route('pickup-requests.index') }}"
           aria-label="Pickup Requests"
           @if($activePickups) aria-current="page" @endif
           class="relative group flex items-center h-10 w-full rounded-xl transition-colors gap-3 {{ $activePickups ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light hover:text-teal-dark' }}"
           :class="sidebarHover ? 'px-3 justify-start' : 'justify-center'">
            <svg class="h-5 w-5 flex-shrink-0 {{ $activePickups ? 'text-teal-dark' : 'text-teal' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block {{ $activePickups ? 'text-teal-dark' : 'text-[#1F2933]' }}">Pickup Requests</span>
            @if($pickupPendingCount > 0)
                <span class="flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold"
                      :class="sidebarHover ? 'static ml-auto' : 'absolute -top-0.5 -right-0.5'">{{ $pickupPendingCount }}</span>
            @endif
            <span class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 px-2.5 py-1 bg-white text-[#1F2933] text-xs font-medium rounded-lg border border-teal/20 shadow-lg whitespace-nowrap z-50 opacity-0 group-hover:opacity-100 transition-opacity"
                  x-show="!sidebarHover" role="tooltip" aria-hidden="true">Pickups</span>
        </a>

        @if($isAdmin)

        <div x-show="sidebarHover" x-cloak class="border-t border-teal/10 my-0.5 w-full"></div>

        {{-- Management section --}}
        <p x-show="sidebarHover" x-cloak class="text-[11px] font-semibold uppercase tracking-wider text-teal/60 px-3 py-0.5">Management</p>

        {{-- Staff --}}
        <a href="{{ route('staff.index') }}"
           aria-label="Staff"
           @if($activeStaff) aria-current="page" @endif
           class="relative group flex items-center h-10 w-full rounded-xl transition-colors gap-3 {{ $activeStaff ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light hover:text-teal-dark' }}"
           :class="sidebarHover ? 'px-3 justify-start' : 'justify-center'">
            <svg class="h-5 w-5 flex-shrink-0 {{ $activeStaff ? 'text-teal-dark' : 'text-teal' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block {{ $activeStaff ? 'text-teal-dark' : 'text-[#1F2933]' }}">Staff</span>
            <span class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 px-2.5 py-1 bg-white text-[#1F2933] text-xs font-medium rounded-lg border border-teal/20 shadow-lg whitespace-nowrap z-50 opacity-0 group-hover:opacity-100 transition-opacity"
                  x-show="!sidebarHover" role="tooltip" aria-hidden="true">Staff</span>
        </a>

        {{-- Applications (dropdown parent: the link navigates, the arrow toggles the child) --}}
        <div class="relative group flex items-center h-10 w-full rounded-xl transition-colors {{ $activeRiderApps ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light hover:text-teal-dark' }}"
             :class="sidebarHover ? 'ps-3 pe-1 justify-start gap-1' : 'justify-center'">
            <a href="{{ route('rider-applications.index') }}"
               aria-label="Applications"
               @if($activeRiderApps) aria-current="page" @endif
               class="flex items-center gap-3 flex-1 min-w-0 h-full"
               :class="sidebarHover ? '' : 'justify-center'">
                <svg class="h-5 w-5 flex-shrink-0 {{ $activeAppsGroup ? 'text-teal-dark' : 'text-teal' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block {{ $activeAppsGroup ? 'text-teal-dark' : 'text-[#1F2933]' }}">Applications</span>
            </a>
            <button type="button" x-show="sidebarHover" x-cloak
                    @click="applicationsOpen = !applicationsOpen"
                    :aria-expanded="applicationsOpen ? 'true' : 'false'"
                    aria-controls="subnav-applications"
                    :aria-label="applicationsOpen ? 'Collapse Applications' : 'Expand Applications'"
                    class="flex items-center justify-center h-10 w-10 rounded-xl text-teal-dark hover:bg-teal/20 transition-colors flex-shrink-0">
                <svg class="h-4 w-4 transition-transform motion-safe:transition-transform" :class="applicationsOpen ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </button>
            <span class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 px-2.5 py-1 bg-white text-[#1F2933] text-xs font-medium rounded-lg border border-teal/20 shadow-lg whitespace-nowrap z-50 opacity-0 group-hover:opacity-100 transition-opacity"
                  x-show="!sidebarHover" role="tooltip" aria-hidden="true">Applications</span>
        </div>

        {{-- Center Applications (child of Applications) --}}
        <div id="subnav-applications" x-show="sidebarHover && applicationsOpen" x-cloak x-collapse>
            <a href="{{ route('center-applications.index') }}"
               aria-label="Center Applications"
               @if($activeCenterApps) aria-current="page" @endif
               class="relative group flex items-center h-10 w-full rounded-xl transition-colors pl-14 pr-3 {{ $activeCenterApps ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light/60' }}">
                <span class="text-[13px] font-medium whitespace-nowrap block {{ $activeCenterApps ? 'text-teal-dark' : 'text-gray-600' }}">Center Applications</span>
            </a>
        </div>

        {{-- Centers --}}
        <a href="{{ route('centers.index') }}"
           aria-label="Centers"
           @if($activeCenters) aria-current="page" @endif
           class="relative group flex items-center h-10 w-full rounded-xl transition-colors gap-3 {{ $activeCenters ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light hover:text-teal-dark' }}"
           :class="sidebarHover ? 'px-3 justify-start' : 'justify-center'">
            <svg class="h-5 w-5 flex-shrink-0 {{ $activeCenters ? 'text-teal-dark' : 'text-teal' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block {{ $activeCenters ? 'text-teal-dark' : 'text-[#1F2933]' }}">Centers</span>
            <span class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 px-2.5 py-1 bg-white text-[#1F2933] text-xs font-medium rounded-lg border border-teal/20 shadow-lg whitespace-nowrap z-50 opacity-0 group-hover:opacity-100 transition-opacity"
                  x-show="!sidebarHover" role="tooltip" aria-hidden="true">Centers</span>
        </a>

        {{-- Service Areas --}}
        <a href="{{ route('service-areas.index') }}"
           aria-label="Service Areas"
           @if($activeAreas) aria-current="page" @endif
           class="relative group flex items-center h-10 w-full rounded-xl transition-colors gap-3 {{ $activeAreas ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light hover:text-teal-dark' }}"
           :class="sidebarHover ? 'px-3 justify-start' : 'justify-center'">
            <svg class="h-5 w-5 flex-shrink-0 {{ $activeAreas ? 'text-teal-dark' : 'text-teal' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block {{ $activeAreas ? 'text-teal-dark' : 'text-[#1F2933]' }}">Service Areas</span>
            <span class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 px-2.5 py-1 bg-white text-[#1F2933] text-xs font-medium rounded-lg border border-teal/20 shadow-lg whitespace-nowrap z-50 opacity-0 group-hover:opacity-100 transition-opacity"
                  x-show="!sidebarHover" role="tooltip" aria-hidden="true">Areas</span>
        </a>

        <div x-show="sidebarHover" x-cloak class="border-t border-teal/10 my-0.5 w-full"></div>

        {{-- Finance section --}}
        <p x-show="sidebarHover" x-cloak class="text-[11px] font-semibold uppercase tracking-wider text-teal/60 px-3 py-0.5">Finance</p>

        {{-- Transactions --}}
        <a href="{{ route('transactions.index') }}"
           aria-label="Transactions"
           @if($activeTransactions) aria-current="page" @endif
           class="relative group flex items-center h-10 w-full rounded-xl transition-colors gap-3 {{ $activeTransactions ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light hover:text-teal-dark' }}"
           :class="sidebarHover ? 'px-3 justify-start' : 'justify-center'">
            <svg class="h-5 w-5 flex-shrink-0 {{ $activeTransactions ? 'text-teal-dark' : 'text-teal' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block {{ $activeTransactions ? 'text-teal-dark' : 'text-[#1F2933]' }}">Transactions</span>
            <span class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 px-2.5 py-1 bg-white text-[#1F2933] text-xs font-medium rounded-lg border border-teal/20 shadow-lg whitespace-nowrap z-50 opacity-0 group-hover:opacity-100 transition-opacity"
                  x-show="!sidebarHover" role="tooltip" aria-hidden="true">Transactions</span>
        </a>

        {{-- Reports --}}
        <a href="{{ route('reports.index') }}"
           aria-label="Reports"
           @if($activeReports) aria-current="page" @endif
           class="relative group flex items-center h-10 w-full rounded-xl transition-colors gap-3 {{ $activeReports ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light hover:text-teal-dark' }}"
           :class="sidebarHover ? 'px-3 justify-start' : 'justify-center'">
            <svg class="h-5 w-5 flex-shrink-0 {{ $activeReports ? 'text-teal-dark' : 'text-teal' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block {{ $activeReports ? 'text-teal-dark' : 'text-[#1F2933]' }}">Reports</span>
            <span class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 px-2.5 py-1 bg-white text-[#1F2933] text-xs font-medium rounded-lg border border-teal/20 shadow-lg whitespace-nowrap z-50 opacity-0 group-hover:opacity-100 transition-opacity"
                  x-show="!sidebarHover" role="tooltip" aria-hidden="true">Reports</span>
        </a>

        {{-- Archived --}}
        <a href="{{ route('deliveries.archived') }}"
           aria-label="Archived"
           @if($activeArchived) aria-current="page" @endif
           class="relative group flex items-center h-10 w-full rounded-xl transition-colors gap-3 {{ $activeArchived ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light hover:text-teal-dark' }}"
           :class="sidebarHover ? 'px-3 justify-start' : 'justify-center'">
            <svg class="h-5 w-5 flex-shrink-0 {{ $activeArchived ? 'text-teal-dark' : 'text-teal' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block {{ $activeArchived ? 'text-teal-dark' : 'text-[#1F2933]' }}">Archived</span>
            <span class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 px-2.5 py-1 bg-white text-[#1F2933] text-xs font-medium rounded-lg border border-teal/20 shadow-lg whitespace-nowrap z-50 opacity-0 group-hover:opacity-100 transition-opacity"
                  x-show="!sidebarHover" role="tooltip" aria-hidden="true">Archived</span>
        </a>
        @endif

        @if(!$isRider)
        <div x-show="sidebarHover" x-cloak class="border-t border-teal/10 my-0.5 w-full"></div>
        @endif

        @if(!$isRider)
        {{-- Settings --}}
        <a href="{{ route('settings.index') }}"
           aria-label="Settings"
           @if($activeSettings) aria-current="page" @endif
           class="relative group flex items-center h-10 w-full rounded-xl transition-colors gap-3 {{ $activeSettings ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light hover:text-teal-dark' }}"
           :class="sidebarHover ? 'px-3 justify-start' : 'justify-center'">
            <svg class="h-5 w-5 flex-shrink-0 {{ $activeSettings ? 'text-teal-dark' : 'text-teal' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block {{ $activeSettings ? 'text-teal-dark' : 'text-[#1F2933]' }}">Settings</span>
            <span class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 px-2.5 py-1 bg-white text-[#1F2933] text-xs font-medium rounded-lg border border-teal/20 shadow-lg whitespace-nowrap z-50 opacity-0 group-hover:opacity-100 transition-opacity"
                  x-show="!sidebarHover" role="tooltip" aria-hidden="true">Settings</span>
        </a>
        @endif
        @endif
    </nav>

    {{-- Profile block + logout --}}
    <div class="px-3 py-3 border-t border-teal/10 flex flex-col gap-1.5 mt-auto">
        <a href="{{ route('profile.show') }}" title="Open profile"
           class="group relative flex items-center rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-secondary"
           :class="sidebarHover ? 'gap-3 px-3 py-2 justify-start bg-white/60 hover:bg-teal-light' : 'justify-center py-1'">
            @if($profilePhotoUrl)
                <img src="{{ $profilePhotoUrl }}" alt="Profile photo" class="h-8 w-8 rounded-full object-cover ring-2 ring-teal/20 group-hover:ring-secondary transition flex-shrink-0">
            @else
                <div class="h-8 w-8 rounded-full bg-teal-light ring-2 ring-teal/20 group-hover:ring-secondary flex items-center justify-center text-teal-dark text-sm font-bold transition flex-shrink-0">
                    {{ $userInitial }}
                </div>
            @endif
            <div x-show="sidebarHover" x-cloak class="min-w-0 flex-1 block">
                <p class="text-sm font-semibold text-[#1F2933] truncate">{{ $accountTitle }}</p>
                @if($accountTitle !== $user->roleLabel())
                    <p class="text-xs font-medium text-teal-dark truncate">{{ $user->roleLabel() }}</p>
                @endif
                <p class="text-xs text-gray-500 truncate">{{ $userEmail }}</p>
            </div>
            <span class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 px-2.5 py-1 bg-white text-[#1F2933] text-xs font-medium rounded-lg border border-teal/20 shadow-lg whitespace-nowrap z-50 opacity-0 group-hover:opacity-100 transition-opacity"
                  x-show="!sidebarHover" role="tooltip" aria-hidden="true">{{ $accountTitle }} · Profile</span>
        </a>
        <form method="POST" action="/logistics/logout" onsubmit="var b=this.querySelector('button[type=submit]');if(b.disabled)return false;b.disabled=true;">
            @csrf
            <button type="submit" aria-label="Log out"
                    class="relative group flex items-center h-10 w-full rounded-xl transition-colors gap-3 text-teal-dark hover:bg-teal-light hover:text-teal-dark"
                    :class="sidebarHover ? 'px-3 justify-start text-red-600 hover:bg-red-50' : 'justify-center'">
                <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block">Log Out</span>
                <span class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 px-2.5 py-1 bg-white text-[#1F2933] text-xs font-medium rounded-lg border border-teal/20 shadow-lg whitespace-nowrap z-50 opacity-0 group-hover:opacity-100 transition-opacity"
                      x-show="!sidebarHover" role="tooltip" aria-hidden="true">Log Out</span>
            </button>
        </form>
    </div>
</aside>