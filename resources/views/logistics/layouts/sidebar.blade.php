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
    $activeMessages  = request()->routeIs('messages.*');
    $msgUnreadCount = 0;
    try { $msgUnreadCount = \App\Models\Logistics\Conversation::unreadForLogisticsUser((int) auth()->id()); } catch (\Throwable $e) {}
@endphp

<style>
    /* Logistics pane widths (rail 72px / expanded 256px, matching seller/buyer). */
    .sidebar-pane {
        width: 72px;
        transition: width 0.25s cubic-bezier(.22,1,.36,1);
    }
    .sidebar-pane.is-expanded {
        width: 256px;
    }
    .app-content {
        padding-left: 72px;
        transition: padding-left 0.25s cubic-bezier(.22,1,.36,1);
    }
    .sidebar-pane.is-expanded ~ .app-content {
        padding-left: 256px;
    }

    /* On small screens the expanded sidebar works as a drawer that
       overlays the content (backdrop provided by the layout). */
    @media (max-width: 1023px) {
        .sidebar-pane.is-expanded ~ .app-content {
            padding-left: 72px;
        }
    }

    /* Reduced-motion: respect user preference. */
    @media (prefers-reduced-motion: reduce) {
        .sidebar-pane,
        .app-content {
            transition-duration: 0.01ms !important;
            transition-delay: 0ms !important;
        }
    }
</style>

<aside id="app-sidebar" class="sb2 sb2--log sidebar-pane fixed inset-y-0 left-0 z-40 flex flex-col"
       :class="{ 'is-expanded': sidebarHover, 'sidebar-collapsed': !sidebarHover }" :aria-expanded="sidebarHover ? 'true' : 'false'">

    {{-- Header: brand lockup + hamburger. Pinned (flex none). --}}
    <div class="sb-brand-header flex-none border-b px-4 transition-all duration-300 flex" style="border-color:rgba(15,76,92,.12)"
         :class="sidebarHover ? 'flex-row items-center justify-between h-16 py-0 gap-2' : 'flex-col items-center justify-center gap-2 py-3'">
        <div class="sb-logo-row flex items-center gap-2.5" :class="sidebarHover ? '' : 'justify-center'">
            <a href="{{ route('dashboard') }}" aria-label="Home" class="sb-logo">
                <img src="{{ asset('images/logo.png') }}" alt="INVOIZ" class="h-9 w-9 rounded-xl object-cover flex-shrink-0">
            </a>
            <span x-show="sidebarHover" x-cloak class="min-w-0">
                <span class="block text-xl font-extrabold tracking-tight" style="color:#0E4A57">INVOIZ</span>
                <span class="sb-eyebrow mt-0.5 block">Logistics Center</span>
            </span>
        </div>
        <button type="button" @click="toggleSidebar()"
                :aria-expanded="sidebarHover ? 'true' : 'false'"
                aria-controls="app-sidebar"
                aria-label="Toggle navigation menu"
                class="sb-hamb">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
    </div>

    <nav class="sidebar-nav" aria-label="Logistics navigation">

        {{-- Dashboard --}}
        <a href="{{ route('dashboard') }}"
           title="Dashboard"
           @if($activeDashboard) aria-current="page" @endif
           class="{{ $activeDashboard ? 'active' : '' }}">
            <svg class="flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="sb-label">Dashboard</span>
        </a>

        @if(!$isRider)

        {{-- Deliveries (parent: the link navigates, the arrow toggles the child) --}}
        <div class="sb-parent relative flex w-full items-center rounded-[10px] transition-colors {{ $activeDeliveriesMain ? 'is-active' : '' }}">
            <a href="{{ route('deliveries.index') }}"
               title="Deliveries"
               @if($activeDeliveriesMain) aria-current="page" @endif
               class="flex min-w-0 flex-1 items-center gap-3 px-3">
                <svg class="flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                </svg>
                <span x-show="sidebarHover" x-cloak class="sb-label">Deliveries</span>
            </a>
            <button type="button" x-show="sidebarHover" x-cloak
                    @click="deliveriesOpen = !deliveriesOpen"
                    :aria-expanded="deliveriesOpen ? 'true' : 'false'"
                    aria-controls="subnav-deliveries"
                    :aria-label="deliveriesOpen ? 'Collapse Deliveries' : 'Expand Deliveries'"
                    class="flex h-11 w-11 items-center justify-center rounded-[10px] transition-colors flex-shrink-0 lg:h-10 lg:w-10" style="color:#0E4A57">
                <svg class="h-4 w-4 transition-transform duration-200 motion-reduce:transition-none" :class="deliveriesOpen ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
            </button>
        </div>

        {{-- Scan Parcel (child of Deliveries) --}}
        <div id="subnav-deliveries" class="sb-sub" x-show="sidebarHover && deliveriesOpen" x-cloak x-collapse>
            <a href="{{ route('deliveries.scan-page') }}"
               title="Scan Parcel"
               @if($activeScan) aria-current="page" @endif
               class="{{ $activeScan ? 'active' : '' }}">
                <span>Scan Parcel</span>
            </a>
        </div>

        {{-- Riders --}}
        <a href="{{ route('riders.index') }}"
           title="Riders"
           @if($activeRiders) aria-current="page" @endif
           class="{{ $activeRiders ? 'active' : '' }}">
            <svg class="flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="sb-label">Riders</span>
        </a>

        {{-- Pickup Requests --}}
        <a href="{{ route('pickup-requests.index') }}"
           title="Pickup Requests"
           @if($activePickups) aria-current="page" @endif
           class="{{ $activePickups ? 'active' : '' }}">
            <svg class="flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="sb-label">Pickup Requests</span>
            @if($pickupPendingCount > 0)
                <span class="sb-badge" data-count="{{ $pickupPendingCount }}"><span class="sb-num">{{ $pickupPendingCount > 99 ? '99+' : $pickupPendingCount }}</span><span class="sr-only"> pending pickups</span></span>
            @endif
        </a>

        {{-- Messages --}}
        <a href="{{ route('messages.index') }}"
           title="Messages"
           @if($activeMessages) aria-current="page" @endif
           class="{{ $activeMessages ? 'active' : '' }}">
            <svg class="flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="sb-label">Messages</span>
            @if($msgUnreadCount > 0)
                <span class="sb-badge" data-count="{{ $msgUnreadCount }}"><span class="sb-num">{{ $msgUnreadCount > 99 ? '99+' : $msgUnreadCount }}</span><span class="sr-only"> unread messages</span></span>
            @endif
        </a>

        @if($isAdmin)

        <div x-show="sidebarHover" x-cloak class="my-0.5 w-full border-t" style="border-color:rgba(15,76,92,.12)"></div>

        {{-- Management section (first group above has no label, as before) --}}
        <p x-show="sidebarHover" x-cloak class="nav-section">Management</p>

        {{-- Staff --}}
        <a href="{{ route('staff.index') }}"
           title="Staff"
           @if($activeStaff) aria-current="page" @endif
           class="{{ $activeStaff ? 'active' : '' }}">
            <svg class="flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="sb-label">Staff</span>
        </a>

        {{-- Applications (parent: the link navigates, the arrow toggles the child) --}}
        <div class="sb-parent relative flex w-full items-center rounded-[10px] transition-colors {{ $activeRiderApps ? 'is-active' : '' }}">
            <a href="{{ route('rider-applications.index') }}"
               title="Applications"
               @if($activeRiderApps) aria-current="page" @endif
               class="flex min-w-0 flex-1 items-center gap-3 px-3">
                <svg class="flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span x-show="sidebarHover" x-cloak class="sb-label">Applications</span>
            </a>
            <button type="button" x-show="sidebarHover" x-cloak
                    @click="applicationsOpen = !applicationsOpen"
                    :aria-expanded="applicationsOpen ? 'true' : 'false'"
                    aria-controls="subnav-applications"
                    :aria-label="applicationsOpen ? 'Collapse Applications' : 'Expand Applications'"
                    class="flex h-11 w-11 items-center justify-center rounded-[10px] transition-colors flex-shrink-0 lg:h-10 lg:w-10" style="color:#0E4A57">
                <svg class="h-4 w-4 transition-transform duration-200 motion-reduce:transition-none" :class="applicationsOpen ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
            </button>
        </div>

        {{-- Center Applications (child of Applications) --}}
        <div id="subnav-applications" class="sb-sub" x-show="sidebarHover && applicationsOpen" x-cloak x-collapse>
            <a href="{{ route('center-applications.index') }}"
               title="Center Applications"
               @if($activeCenterApps) aria-current="page" @endif
               class="{{ $activeCenterApps ? 'active' : '' }}">
                <span>Center Applications</span>
            </a>
        </div>

        {{-- Centers --}}
        <a href="{{ route('centers.index') }}"
           title="Centers"
           @if($activeCenters) aria-current="page" @endif
           class="{{ $activeCenters ? 'active' : '' }}">
            <svg class="flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="sb-label">Centers</span>
        </a>

        {{-- Service Areas --}}
        <a href="{{ route('service-areas.index') }}"
           title="Service Areas"
           @if($activeAreas) aria-current="page" @endif
           class="{{ $activeAreas ? 'active' : '' }}">
            <svg class="flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="sb-label">Service Areas</span>
        </a>

        <div x-show="sidebarHover" x-cloak class="my-0.5 w-full border-t" style="border-color:rgba(15,76,92,.12)"></div>

        {{-- Finance section --}}
        <p x-show="sidebarHover" x-cloak class="nav-section">Finance</p>

        {{-- Transactions --}}
        <a href="{{ route('transactions.index') }}"
           title="Transactions"
           @if($activeTransactions) aria-current="page" @endif
           class="{{ $activeTransactions ? 'active' : '' }}">
            <svg class="flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="sb-label">Transactions</span>
        </a>

        {{-- Reports --}}
        <a href="{{ route('reports.index') }}"
           title="Reports"
           @if($activeReports) aria-current="page" @endif
           class="{{ $activeReports ? 'active' : '' }}">
            <svg class="flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="sb-label">Reports</span>
        </a>

        {{-- Archived --}}
        <a href="{{ route('deliveries.archived') }}"
           title="Archived"
           @if($activeArchived) aria-current="page" @endif
           class="{{ $activeArchived ? 'active' : '' }}">
            <svg class="flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="sb-label">Archived</span>
        </a>
        @endif

        @if(!$isRider)
        <div x-show="sidebarHover" x-cloak class="my-0.5 w-full border-t" style="border-color:rgba(15,76,92,.12)"></div>
        @endif

        @if(!$isRider)
        {{-- Settings (own group, as before) --}}
        <a href="{{ route('settings.index') }}"
           title="Settings"
           @if($activeSettings) aria-current="page" @endif
           class="{{ $activeSettings ? 'active' : '' }}">
            <svg class="flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span x-show="sidebarHover" x-cloak class="sb-label">Settings</span>
        </a>
        @endif
        @endif
    </nav>

    {{-- Pinned footer: floating pill (replaces user card + Log Out row). --}}
    <div class="sb-foot flex-none">
        <div class="sb-pillwrap">
            <div class="sb-pill">
                <a href="{{ route('profile.show') }}" class="sb-who" data-tip="{{ $userName }} · {{ $user->roleLabel() }}" title="Open profile">
                    @if($profilePhotoUrl)
                        <img src="{{ $profilePhotoUrl }}" alt="" class="sb-avatar">
                    @else
                        <span class="sb-avatar sb-avatar-fallback" aria-hidden="true">{{ $userInitial }}</span>
                    @endif
                    <span class="sb-fade">
                        <span class="sb-name" title="{{ $userName }}{{ $userEmail ? ' · '.$userEmail : '' }}">{{ $userName }}</span>
                        <span class="sb-role">{{ $user->roleLabel() }}</span>
                    </span>
                </a>
                <form method="POST" action="/logistics/logout" style="margin:0;flex-shrink:0" onsubmit="var b=this.querySelector('button[type=submit]');if(b.disabled)return false;b.disabled=true;">
                    @csrf
                    <button type="submit" class="sb-logout" data-tip="Log out" aria-label="Log out">
                        <span class="sb-logout-circle"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg></span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</aside>
