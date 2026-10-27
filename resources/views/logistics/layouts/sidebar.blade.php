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
    $pickupPendingCount = 0;
    try {
        $pickupPendingCount = (int) \App\Models\PickupRequest::query()->pendingReview($user)->count();
    } catch (\Throwable $e) {}

    $activeDashboard = request()->routeIs('dashboard');
    $activeDeliveries = request()->routeIs('deliveries.*');
    $activeScan      = request()->routeIs('deliveries.scan-page');
    $activeDeliveriesMain = $activeDeliveries && ! $activeScan;
    $activeSorting = request()->routeIs('sorting-center.*');
    $activeRiders    = request()->routeIs('riders.*');
    $activePickups   = request()->routeIs('pickup-requests.*');
    $activeStaff     = request()->routeIs('staff.*');
    $activeRiderApps = request()->routeIs('rider-applications.*');
    $activeCenterApps = request()->routeIs('center-applications.*');
    $activeAppsGroup = $activeRiderApps || $activeCenterApps;
    $activeCenters   = request()->routeIs('centers.*');
    $activeAreas     = request()->routeIs('service-areas.*');
    $activeTransactions = request()->routeIs('transactions.*');
    $activeRemittances = request()->routeIs('remittances.*');
    $activeReports   = request()->routeIs('reports.*');
    $activeArchived  = request()->routeIs('deliveries.archived');
    $activeMessages  = request()->routeIs('messages.*');
    $msgUnreadCount = 0;
    try { $msgUnreadCount = \App\Models\Logistics\Conversation::unreadForLogisticsUser((int) auth()->id()); } catch (\Throwable $e) {}
    $activeSettings  = request()->routeIs('settings.*');
@endphp

<style>
    /* Use the shared Buyer/Seller sidebar, with a fixed header and footer. */
    .sb2--log.sidebar-pane { width:72px; height:100vh; height:100dvh; min-height:0; overflow:hidden; transition:width .2s ease; }
    .sb2--log.sidebar-pane.is-expanded { width:256px; }
    .app-content { padding-left:72px; transition:padding-left .2s ease; }
    .sidebar-pane.is-expanded ~ .app-content { padding-left:256px; }
    .sb2--log .sb-brand-header { flex-shrink:0; padding:16px 12px; }
    .sb2--log .sidebar-nav { gap:4px; overscroll-behavior:contain; scrollbar-gutter:stable; mask-image:none; -webkit-mask-image:none; }
    .sb2--log .sidebar-nav > * { flex-shrink:0; }
    .sb2--log .sidebar-nav .sb-parent { height:auto; min-height:40px; padding:0; }
    .sb2--log .sidebar-nav .sb-parent > a { min-width:0; }
    .sb2--log .sb-foot { flex-shrink:0; }
    .app-content .ld-mobile-menu { display:none; }
    .sb2--log .sb-role { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .sb2--log.sidebar-collapsed .sb-logo-row { justify-content:center; }
    .sb2--log.sidebar-collapsed .sidebar-nav { display:flex; align-items:center; gap:8px; }
    .sb2--log.sidebar-collapsed .sidebar-nav > a,
    .sb2--log.sidebar-collapsed .sidebar-nav > .sb-parent { width:44px; height:44px; min-height:44px; padding:0; justify-content:center; }
    .sb2--log.sidebar-collapsed .sb-parent > a { width:44px; height:44px; min-height:44px; padding:0; flex:none; justify-content:center; }
    .sb2--log.sidebar-collapsed .sb-foot { display:flex; flex-direction:column; align-items:center; padding:12px 8px; }
    .sb2--log.sidebar-collapsed .sb-pill { width:56px; height:56px; padding:0; justify-content:center; }
    .sb2--log.sidebar-collapsed .sb-who { display:none; }
    @media (max-width:1023px) {
        .sb2--log.sidebar-pane { transform:translateX(-100%); visibility:hidden; transition:transform .2s ease, visibility .2s ease; }
        .sb2--log.sidebar-pane.is-expanded { transform:none; visibility:visible; width:min(280px,88vw); }
        .sidebar-pane ~ .app-content, .sidebar-pane.is-expanded ~ .app-content { padding-left:0; }
        .app-content .ld-mobile-menu { display:flex; align-items:center; justify-content:center; width:44px; height:44px; color:#0E4A57; }
        .sb2--log .sidebar-nav > a,
        .sb2--log .sidebar-nav .sb-parent { min-height:44px; }
    }
    @media (prefers-reduced-motion:reduce) {
        #app-sidebar.sidebar-pane, .app-content { transition:none !important; }
    }
</style>

<aside id="app-sidebar" class="sb2 sb2--log sidebar-root sidebar-pane fixed inset-y-0 left-0 z-40"
       :class="{ 'is-expanded': sidebarHover, 'sidebar-collapsed': !sidebarHover }" aria-label="Logistics sidebar" :aria-expanded="sidebarHover ? 'true' : 'false'">

    <div class="sb-brand-header">
        <div class="sb-logo-row flex items-center justify-between gap-2">
            <a href="{{ route('dashboard') }}" aria-label="INVOIZ logistics home" data-tip="INVOIZ logistics home"
               x-show="sidebarHover" x-cloak class="flex items-center gap-2.5 min-w-0">
                <img src="{{ asset('images/logo.png') }}" alt="" class="h-9 w-9 shrink-0 rounded-xl object-cover">
                <span class="min-w-0">
                    <span class="block text-xl font-extrabold tracking-tight text-[#0E4A57]">INVOIZ</span>
                    <span class="sb-eyebrow block mt-0.5">Logistics</span>
                </span>
            </a>
            <button type="button" @click="toggleSidebar()" class="sb-hamb"
                    :aria-expanded="sidebarHover ? 'true' : 'false'" aria-controls="app-sidebar"
                    aria-label="Toggle navigation menu" data-tip="Toggle navigation">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>
    </div>

    <nav class="sidebar-nav flex flex-col" aria-label="Logistics navigation"
         x-init="$nextTick(() => { try { $el.scrollTop = Number(sessionStorage.getItem(sidebarKey + ':scroll')) || 0; } catch (e) {} })"
         @scroll.passive="(() => { try { sessionStorage.setItem(sidebarKey + ':scroll', $el.scrollTop); } catch (e) {} })()">

        {{-- Dashboard --}}
        <a href="{{ route('dashboard') }}"
           aria-label="Dashboard" data-tip="Dashboard"
           @if($activeDashboard) aria-current="page" @endif
           class="{{ $activeDashboard ? 'active' : '' }} relative group flex items-center h-10 w-full rounded-xl transition-colors gap-3"
           :class="sidebarHover ? 'px-3 justify-start' : 'justify-center'">
            <svg class="h-5 w-5 flex-shrink-0 {{ $activeDashboard ? 'text-teal-dark' : 'text-teal' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block {{ $activeDashboard ? 'text-teal-dark' : 'text-[#1F2933]' }}">Dashboard</span>
        </a>

        @if(!$isRider)

        {{-- Deliveries (dropdown parent: the link navigates, the arrow toggles the child) --}}
        <div class="sb-parent relative group flex items-center h-10 w-full rounded-xl transition-colors {{ $activeDeliveriesMain ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light hover:text-teal-dark' }}"
             :class="sidebarHover ? 'ps-3 pe-1 justify-start gap-1' : 'justify-center'">
            <a href="{{ route('deliveries.index') }}"
               aria-label="Deliveries" data-tip="Deliveries"
               @if($activeDeliveriesMain) aria-current="page" @endif
               class="{{ $activeDeliveriesMain ? 'active' : '' }} flex items-center gap-3 flex-1 min-w-0 h-full"
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
                    :aria-label="deliveriesOpen ? 'Collapse Deliveries' : 'Expand Deliveries'" data-tip="deliveriesOpen ? 'Collapse Deliveries' : 'Expand Deliveries'"
                    class="flex items-center justify-center h-10 w-10 rounded-xl text-teal-dark hover:bg-teal/20 transition-colors flex-shrink-0">
                <svg class="h-4 w-4 transition-transform motion-safe:transition-transform" :class="deliveriesOpen ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </button>
        </div>

        {{-- Scan Parcel (child of Deliveries) --}}
        <div class="sb-sub" id="subnav-deliveries" x-show="sidebarHover && deliveriesOpen" x-cloak x-collapse>
            <a href="{{ route('deliveries.scan-page') }}"
               aria-label="Scan Parcel" data-tip="Scan Parcel"
               @if($activeScan) aria-current="page" @endif
               class="{{ $activeScan ? 'active' : '' }} relative group flex items-center h-10 w-full rounded-xl transition-colors pl-14 pr-3 {{ $activeScan ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light/60' }}">
                <span class="text-[13px] font-medium whitespace-nowrap block {{ $activeScan ? 'text-teal-dark' : 'text-gray-600' }}">Scan Parcel</span>
            </a>
        </div>

        {{-- Sorting Center (operational hub; detail workflow untouched) --}}
        <a href="{{ route('sorting-center.index') }}"
           aria-label="Sorting Center" data-tip="Sorting Center"
           @if($activeSorting) aria-current="page" @endif
           class="{{ $activeSorting ? 'active' : '' }} relative group flex items-center h-10 w-full rounded-xl transition-colors gap-3 {{ $activeSorting ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light hover:text-teal-dark' }}"
           :class="sidebarHover ? 'px-3 justify-start' : 'justify-center'">
            <svg class="h-5 w-5 flex-shrink-0 {{ $activeSorting ? 'text-teal-dark' : 'text-teal' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block {{ $activeSorting ? 'text-teal-dark' : 'text-[#1F2933]' }}">Sorting Center</span>
        </a>

        {{-- Riders --}}
        <a href="{{ route('riders.index') }}"
           aria-label="Riders" data-tip="Riders"
           @if($activeRiders) aria-current="page" @endif
           class="{{ $activeRiders ? 'active' : '' }} relative group flex items-center h-10 w-full rounded-xl transition-colors gap-3 {{ $activeRiders ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light hover:text-teal-dark' }}"
           :class="sidebarHover ? 'px-3 justify-start' : 'justify-center'">
            <svg class="h-5 w-5 flex-shrink-0 {{ $activeRiders ? 'text-teal-dark' : 'text-teal' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block {{ $activeRiders ? 'text-teal-dark' : 'text-[#1F2933]' }}">Riders</span>
        </a>

        {{-- Pickup Requests --}}
        <a href="{{ route('pickup-requests.index') }}"
           aria-label="Pickup Requests{{ $pickupPendingCount > 0 ? ' ('.$pickupPendingCount.' pending)' : '' }}" data-tip="Pickup Requests{{ $pickupPendingCount > 0 ? ' ('.$pickupPendingCount.' pending)' : '' }}"
           data-pickup-count="{{ $pickupPendingCount }}"
           @if($activePickups) aria-current="page" @endif
           class="{{ $activePickups ? 'active' : '' }} relative group flex items-center h-10 w-full rounded-xl transition-colors gap-3 {{ $activePickups ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light hover:text-teal-dark' }}"
           :class="sidebarHover ? 'px-3 justify-start' : 'justify-center'">
            <svg class="h-5 w-5 flex-shrink-0 {{ $activePickups ? 'text-teal-dark' : 'text-teal' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block {{ $activePickups ? 'text-teal-dark' : 'text-[#1F2933]' }}">Pickup Requests</span>
            @if($pickupPendingCount > 0)
                <span class="flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold"
                      :class="sidebarHover ? 'static ml-auto' : 'absolute -top-0.5 -right-0.5'">{{ $pickupPendingCount }}</span>
            @endif
        </a>

        @if($isAdmin)

        <div x-show="sidebarHover" x-cloak class="border-t border-teal/10 my-0.5 w-full"></div>

        {{-- Management section --}}
        <p x-show="sidebarHover" x-cloak class="nav-section">Management</p>

        {{-- Staff --}}
        <a href="{{ route('staff.index') }}"
           aria-label="Staff" data-tip="Staff"
           @if($activeStaff) aria-current="page" @endif
           class="{{ $activeStaff ? 'active' : '' }} relative group flex items-center h-10 w-full rounded-xl transition-colors gap-3 {{ $activeStaff ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light hover:text-teal-dark' }}"
           :class="sidebarHover ? 'px-3 justify-start' : 'justify-center'">
            <svg class="h-5 w-5 flex-shrink-0 {{ $activeStaff ? 'text-teal-dark' : 'text-teal' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block {{ $activeStaff ? 'text-teal-dark' : 'text-[#1F2933]' }}">Staff</span>
        </a>

        {{-- Applications (dropdown parent: the link navigates, the arrow toggles the child) --}}
        <div class="sb-parent relative group flex items-center h-10 w-full rounded-xl transition-colors {{ $activeRiderApps ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light hover:text-teal-dark' }}"
             :class="sidebarHover ? 'ps-3 pe-1 justify-start gap-1' : 'justify-center'">
            <a href="{{ route('rider-applications.index') }}"
               aria-label="Applications" data-tip="Applications"
               @if($activeRiderApps) aria-current="page" @endif
               class="{{ $activeRiderApps ? 'active' : '' }} flex items-center gap-3 flex-1 min-w-0 h-full"
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
                    :aria-label="applicationsOpen ? 'Collapse Applications' : 'Expand Applications'" data-tip="applicationsOpen ? 'Collapse Applications' : 'Expand Applications'"
                    class="flex items-center justify-center h-10 w-10 rounded-xl text-teal-dark hover:bg-teal/20 transition-colors flex-shrink-0">
                <svg class="h-4 w-4 transition-transform motion-safe:transition-transform" :class="applicationsOpen ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </button>
        </div>

        {{-- Center Applications (child of Applications) --}}
        <div class="sb-sub" id="subnav-applications" x-show="sidebarHover && applicationsOpen" x-cloak x-collapse>
            <a href="{{ route('center-applications.index') }}"
               aria-label="Center Applications" data-tip="Center Applications"
               @if($activeCenterApps) aria-current="page" @endif
               class="{{ $activeCenterApps ? 'active' : '' }} relative group flex items-center h-10 w-full rounded-xl transition-colors pl-14 pr-3 {{ $activeCenterApps ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light/60' }}">
                <span class="text-[13px] font-medium whitespace-nowrap block {{ $activeCenterApps ? 'text-teal-dark' : 'text-gray-600' }}">Center Applications</span>
            </a>
        </div>

        {{-- Centers --}}
        <a href="{{ route('centers.index') }}"
           aria-label="Centers" data-tip="Centers"
           @if($activeCenters) aria-current="page" @endif
           class="{{ $activeCenters ? 'active' : '' }} relative group flex items-center h-10 w-full rounded-xl transition-colors gap-3 {{ $activeCenters ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light hover:text-teal-dark' }}"
           :class="sidebarHover ? 'px-3 justify-start' : 'justify-center'">
            <svg class="h-5 w-5 flex-shrink-0 {{ $activeCenters ? 'text-teal-dark' : 'text-teal' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block {{ $activeCenters ? 'text-teal-dark' : 'text-[#1F2933]' }}">Centers</span>
        </a>

        {{-- Service Areas --}}
        <a href="{{ route('service-areas.index') }}"
           aria-label="Service Areas" data-tip="Service Areas"
           @if($activeAreas) aria-current="page" @endif
           class="{{ $activeAreas ? 'active' : '' }} relative group flex items-center h-10 w-full rounded-xl transition-colors gap-3 {{ $activeAreas ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light hover:text-teal-dark' }}"
           :class="sidebarHover ? 'px-3 justify-start' : 'justify-center'">
            <svg class="h-5 w-5 flex-shrink-0 {{ $activeAreas ? 'text-teal-dark' : 'text-teal' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block {{ $activeAreas ? 'text-teal-dark' : 'text-[#1F2933]' }}">Service Areas</span>
        </a>

        <div x-show="sidebarHover" x-cloak class="border-t border-teal/10 my-0.5 w-full"></div>

        {{-- Finance section --}}
        <p x-show="sidebarHover" x-cloak class="nav-section">Finance</p>

        {{-- Transactions --}}
        <a href="{{ route('transactions.index') }}"
           aria-label="Transactions" data-tip="Transactions"
           @if($activeTransactions) aria-current="page" @endif
           class="{{ $activeTransactions ? 'active' : '' }} relative group flex items-center h-10 w-full rounded-xl transition-colors gap-3 {{ $activeTransactions ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light hover:text-teal-dark' }}"
           :class="sidebarHover ? 'px-3 justify-start' : 'justify-center'">
            <svg class="h-5 w-5 flex-shrink-0 {{ $activeTransactions ? 'text-teal-dark' : 'text-teal' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block {{ $activeTransactions ? 'text-teal-dark' : 'text-[#1F2933]' }}">Transactions</span>
        </a>

        {{-- Remittances (read-only COD handoffs) --}}
        <a href="{{ route('remittances.index') }}"
           aria-label="Remittances" data-tip="Remittances"
           @if($activeRemittances) aria-current="page" @endif
           class="{{ $activeRemittances ? 'active' : '' }} relative group flex items-center h-10 w-full rounded-xl transition-colors gap-3 {{ $activeRemittances ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light hover:text-teal-dark' }}"
           :class="sidebarHover ? 'px-3 justify-start' : 'justify-center'">
            <svg class="h-5 w-5 flex-shrink-0 {{ $activeRemittances ? 'text-teal-dark' : 'text-teal' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block {{ $activeRemittances ? 'text-teal-dark' : 'text-[#1F2933]' }}">Remittances</span>
        </a>

        {{-- Reports --}}
        <a href="{{ route('reports.index') }}"
           aria-label="Reports" data-tip="Reports"
           @if($activeReports) aria-current="page" @endif
           class="{{ $activeReports ? 'active' : '' }} relative group flex items-center h-10 w-full rounded-xl transition-colors gap-3 {{ $activeReports ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light hover:text-teal-dark' }}"
           :class="sidebarHover ? 'px-3 justify-start' : 'justify-center'">
            <svg class="h-5 w-5 flex-shrink-0 {{ $activeReports ? 'text-teal-dark' : 'text-teal' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block {{ $activeReports ? 'text-teal-dark' : 'text-[#1F2933]' }}">Reports</span>
        </a>

        {{-- Archived --}}
        {{-- Messages (logistics inbox; unread badge from logistics_conversations) --}}
        <a href="{{ route('messages.index') }}"
           aria-label="Messages" data-tip="Messages"
           @if($activeMessages) aria-current="page" @endif
           class="{{ $activeMessages ? 'active' : '' }} relative group flex items-center h-10 w-full rounded-xl transition-colors gap-3 {{ $activeMessages ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light hover:text-teal-dark' }}"
           :class="sidebarHover ? 'px-3 justify-start' : 'justify-center'">
            <svg class="h-5 w-5 flex-shrink-0 {{ $activeMessages ? 'text-teal-dark' : 'text-teal' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block {{ $activeMessages ? 'text-teal-dark' : 'text-[#1F2933]' }}">Messages</span>
            @if($msgUnreadCount > 0)
                <span class="flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-teal-dark text-white text-[10px] font-bold"
                      :class="sidebarHover ? 'static ml-auto' : 'absolute -top-0.5 -right-0.5'">{{ $msgUnreadCount > 99 ? '99+' : $msgUnreadCount }}<span class="sr-only"> unread messages</span></span>
            @endif
        </a>
        <a href="{{ route('deliveries.archived') }}"
           aria-label="Archived" data-tip="Archived"
           @if($activeArchived) aria-current="page" @endif
           class="{{ $activeArchived ? 'active' : '' }} relative group flex items-center h-10 w-full rounded-xl transition-colors gap-3 {{ $activeArchived ? 'bg-teal-light text-teal-dark' : 'hover:bg-teal-light hover:text-teal-dark' }}"
           :class="sidebarHover ? 'px-3 justify-start' : 'justify-center'">
            <svg class="h-5 w-5 flex-shrink-0 {{ $activeArchived ? 'text-teal-dark' : 'text-teal' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="text-sm font-medium whitespace-nowrap block {{ $activeArchived ? 'text-teal-dark' : 'text-[#1F2933]' }}">Archived</span>
        </a>
        @endif
        @endif
    </nav>
    <div class="sb-foot">
        <div class="sb-pillwrap">
            <div class="sb-pill">
                <a href="{{ route('profile.show') }}" class="sb-who" data-tip="Profile" aria-label="Open profile">
                    @if($profilePhotoUrl)
                        <img src="{{ $profilePhotoUrl }}" alt="" class="sb-avatar">
                    @else
                        <span class="sb-avatar" aria-hidden="true">{{ strtoupper($userInitial) }}</span>
                    @endif
                    <span class="sb-fade">
                        <span class="sb-name">{{ $accountTitle }}</span>
                        <span class="sb-role">{{ $user->roleLabel() }}</span>
                    </span>
                </a>
                <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                    @csrf
                    <button type="submit" class="sb-logout" data-tip="Log out" aria-label="Log out">
                        <span class="sb-logout-circle">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H5a2 2 0 00-2 2v10a2 2 0 002 2h4m7-14l5 5-5 5m5-5H9" />
                            </svg>
                        </span>
                    </button>
                </form>
            </div>
        </div>
        <div x-show="sidebarHover" x-cloak class="sb-version">INVOIZ Logistics</div>
    </div>
</aside>
