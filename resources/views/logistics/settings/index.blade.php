<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2 text-sm text-gray-400 mb-1">
            <span>Home</span>
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-gray-900 font-medium">Settings</span>
        </div>
        <h1 class="text-2xl font-bold text-gray-900 leading-tight">Settings</h1>
        <p class="mt-1 text-sm text-gray-500">Manage your operational preferences, account security, and notifications</p>
    </x-slot>

    @php
        $notif    = fn (string $key) => $settings->notificationEnabled($key) ? 'checked' : '';
        $delivery = $settings->delivery ?? [];
        $remSide  = $settings->rememberSidebar();
    @endphp

    <div class="lw-settings max-w-4xl mx-auto" x-data="settingsSearch()">

        @if(session('success'))
            <div class="mb-6 bg-teal-light text-teal-dark border border-teal px-4 py-3 rounded-xl text-sm font-medium flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>{{ session('success') }}</span>
                </div>
            </div>
        @endif

        {{-- Search settings --}}
        <div class="lw-settings-search mb-6 relative">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
                </svg>
            </div>
            <input type="search" x-model="query" @input="filter()"
                   placeholder="Search settings… (e.g. Password, Delivery, Notifications, Workspace, Login)"
                   class="w-full pl-10 pr-4 py-3 border border-gray-200 rounded-xl text-sm bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-teal focus:border-transparent"
                   aria-label="Search settings">
            <p x-show="query && noResults()" class="mt-2 text-sm text-gray-500 pl-1">No settings match "<span x-text="query"></span>".</p>
        </div>

        <div class="lw-settings-sections space-y-8">

            {{-- ═══════════════════════════════════════════════════════
                 GROUP: ACCOUNT & SECURITY
            ════════════════════════════════════════════════════════ --}}
            <div data-group="account profile security password" x-show="groupVisible('account profile security password')">
                <h3 class="text-xs font-bold uppercase tracking-widest text-gray-400 mb-3 px-1">Account &amp; Security</h3>
                <div class="space-y-3">

                    {{-- Profile card --}}
                    <div data-section="profile account personal details" x-show="sectionVisible('profile account personal details')"
                         class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-5 flex items-center justify-between gap-4">
                            <div class="flex items-center gap-4">
                                <div class="h-10 w-10 rounded-xl bg-teal-light flex items-center justify-center flex-shrink-0">
                                    <svg class="h-5 w-5 text-teal-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">Profile Information</p>
                                    <p class="text-xs text-gray-500 mt-0.5">Manage your personal details, contact number, role, and company assignment</p>
                                </div>
                            </div>
                            <a href="{{ route('profile.show') }}"
                               class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-xl transition-colors flex-shrink-0">
                                Open Profile
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </div>
                    </div>

                    {{-- Security & Password --}}
                    <div data-section="security password change credentials" x-show="sectionVisible('security password change credentials')"
                         x-data="{ pwOpen: {{ $errors->has('current_password') || $errors->has('password') ? 'true' : 'false' }} }"
                         class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-5 flex items-center justify-between gap-4">
                            <div class="flex items-center gap-4">
                                <div class="h-10 w-10 rounded-xl bg-teal-light flex items-center justify-center flex-shrink-0">
                                    <svg class="h-5 w-5 text-teal-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">Change Password</p>
                                    <p class="text-xs text-gray-500 mt-0.5">Keep your account secure by using a strong, unique password</p>
                                </div>
                            </div>
                            <button type="button" @click="pwOpen = true"
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-xl transition-colors flex-shrink-0">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                </svg>
                                Change Password
                            </button>
                        </div>

                        {{-- Change Password Modal --}}
                        <div x-show="pwOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" x-transition.opacity>
                            <div class="absolute inset-0 bg-black/40" @click="pwOpen = false"></div>
                            <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md" x-transition>
                                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                                    <h4 class="font-bold text-gray-900">Change Account Password</h4>
                                    <button type="button" aria-label="Close password dialog" @click="pwOpen = false" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                                <form method="POST" action="{{ route('settings.update-password') }}">
                                    @csrf
                                    @method('PUT')
                                    <div class="px-6 py-5 space-y-4">
                                        <div>
                                            <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                                            <x-password-input id="current_password" name="current_password" class="w-full" required :error="$errors->has('current_password')" />
                                            @error('current_password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                        </div>
                                        <div>
                                            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">New Password <span class="text-gray-400 font-normal">(min. 8 characters)</span></label>
                                            <x-password-input id="password" name="password" class="w-full" required minlength="8" :error="$errors->has('password')" />
                                            @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                        </div>
                                        <div>
                                            <label for="password-confirm" class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                                            <x-password-input id="password-confirm" name="password_confirmation" class="w-full" required minlength="8" />
                                        </div>
                                    </div>
                                    <div class="px-6 py-4 bg-gray-50 rounded-b-2xl flex justify-end gap-3">
                                        <button type="button" @click="pwOpen = false"
                                                class="px-5 py-2.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-xl transition-colors">
                                            Cancel
                                        </button>
                                        <button type="submit"
                                                class="px-5 py-2.5 bg-teal hover:bg-teal-dark text-white text-sm font-semibold rounded-xl transition-colors duration-200">
                                            Save Password
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════════
                 GROUP: LOGISTICS OPERATIONS & DELIVERY PREFERENCES
            ════════════════════════════════════════════════════════ --}}
            <div data-group="operations delivery proof attempts reassignment" x-show="groupVisible('operations delivery proof attempts reassignment')">
                <h3 class="text-xs font-bold uppercase tracking-widest text-gray-400 mb-3 px-1">Logistics Operations</h3>
                <div class="space-y-3">

                    <div data-section="delivery preferences operations proof attempts reassignment" x-show="sectionVisible('delivery preferences operations proof attempts reassignment')"
                         class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-5 border-b border-gray-100">
                            <h4 class="text-base font-bold text-gray-900">Delivery Rules &amp; Operations</h4>
                            <p class="text-sm text-gray-500 mt-0.5">Operational guidelines applied to rider deliveries and hub handoffs</p>
                        </div>
                        <form method="POST" action="{{ route('settings.update-delivery') }}">
                            @csrf
                            @method('PUT')
                            <ul class="divide-y divide-gray-100">
                                <li class="px-6 py-4 flex items-center justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">Require Proof of Delivery</p>
                                        <p class="text-xs text-gray-500 mt-0.5">Riders must submit photographic proof or customer signature before completing a delivery</p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0" aria-label="Toggle Require Proof of Delivery">
                                        <input type="hidden" name="require_proof" value="0">
                                        <input type="checkbox" name="require_proof" value="1" {{ ($delivery['require_proof'] ?? true) ? 'checked' : '' }} class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-teal after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:shadow after:transition-all peer-checked:after:translate-x-5"></div>
                                    </label>
                                </li>
                                <li class="px-6 py-4 flex items-center justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">Maximum Delivery Attempts</p>
                                        <p class="text-xs text-gray-500 mt-0.5">Maximum number of delivery re-attempts allowed before a parcel is returned to hub</p>
                                    </div>
                                    <div class="flex gap-2 flex-shrink-0">
                                        @foreach([1, 2, 3] as $attempt)
                                        <label class="cursor-pointer">
                                            <input type="radio" name="max_attempts" value="{{ $attempt }}"
                                                   class="peer sr-only" {{ (int)($delivery['max_attempts'] ?? 2) === $attempt ? 'checked' : '' }}>
                                            <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl text-sm font-bold bg-gray-100 text-gray-600 transition-colors peer-checked:bg-teal peer-checked:text-white hover:bg-gray-200 peer-checked:hover:bg-teal-dark">
                                                {{ $attempt }}
                                            </span>
                                        </label>
                                        @endforeach
                                    </div>
                                </li>
                                <li class="px-6 py-4 flex items-center justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">Allow Rider Reassignment</p>
                                        <p class="text-xs text-gray-500 mt-0.5">Permit dispatchers to reassign pending or unaccepted deliveries to another available rider</p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0" aria-label="Toggle Allow Rider Reassignment">
                                        <input type="hidden" name="allow_reassignment" value="0">
                                        <input type="checkbox" name="allow_reassignment" value="1" {{ ($delivery['allow_reassignment'] ?? true) ? 'checked' : '' }} class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-teal after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:shadow after:transition-all peer-checked:after:translate-x-5"></div>
                                    </label>
                                </li>
                            </ul>
                            <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between bg-gray-50/50">
                                <span class="text-xs text-gray-400">Operational settings take effect immediately across all dispatch queues.</span>
                                <button type="submit"
                                        class="inline-flex items-center px-6 py-2.5 bg-teal hover:bg-teal-dark text-white text-sm font-semibold rounded-xl transition-colors duration-200 shadow-sm">
                                    Save Operations Settings
                                </button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════════
                 GROUP: NOTIFICATIONS
            ════════════════════════════════════════════════════════ --}}
            <div data-group="notifications alerts rider delivery" x-show="groupVisible('notifications alerts rider delivery')">
                <h3 class="text-xs font-bold uppercase tracking-widest text-gray-400 mb-3 px-1">Notifications</h3>
                <div class="space-y-3">

                    <div data-section="notifications alerts messages rider delivery" x-show="sectionVisible('notifications alerts messages rider delivery')"
                         class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-5 border-b border-gray-100">
                            <h4 class="text-base font-bold text-gray-900">In-System &amp; Email Notifications</h4>
                            <p class="text-sm text-gray-500 mt-0.5">Control which operational notifications you receive in the logistics dashboard</p>
                        </div>
                        <form method="POST" action="{{ route('settings.update-notifications') }}">
                            @csrf
                            @method('PUT')
                            <ul class="divide-y divide-gray-100">
                                @foreach([
                                    'rider_applications'   => ['New Rider Applications',   'Alert when a new rider or driver submits an application'],
                                    'application_updates'  => ['Application Decisions',    'Alert when logistics hub applications are submitted or decided'],
                                    'delivery_requests'    => ['New Delivery Requests',    'Alert when a new delivery order is dispatched to this center'],
                                    'failed_deliveries'    => ['Failed Delivery Attempts', 'Alert when a delivery cannot be completed by a rider'],
                                    'failed_pickups'       => ['Failed Pickups',           'Alert when a rider reports a merchant pickup problem'],
                                    'rider_status_updates' => ['Rider Availability',      'Alert when assigned riders go online, offline, or start transit'],
                                    'delivery_completed'   => ['Delivery Completed',       'Alert when a parcel is successfully marked as delivered'],
                                    'new_messages'         => ['New Support Messages',     'Alert about incoming messages from riders, sellers, or admin'],
                                ] as $key => [$title, $desc])
                                <li class="px-6 py-4 flex items-center justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">{{ $title }}</p>
                                        <p class="text-xs text-gray-500 mt-0.5">{{ $desc }}</p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0" aria-label="Toggle {{ $title }}">
                                        <input type="hidden" name="{{ $key }}" value="0">
                                        <input type="checkbox" name="{{ $key }}" value="1" {{ $notif($key) }} class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-teal after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:shadow after:transition-all peer-checked:after:translate-x-5"></div>
                                    </label>
                                </li>
                                @endforeach
                                <li class="px-6 py-4 flex items-center justify-between gap-4 bg-gray-50/50">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">Email Notifications</p>
                                        <p class="text-xs text-gray-500 mt-0.5">Send a copy of critical alerts to your account email address</p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0" aria-label="Toggle Email Notifications">
                                        <input type="hidden" name="email_notifications" value="0">
                                        <input type="checkbox" name="email_notifications" value="1" {{ $settings->email_notifications ? 'checked' : '' }} class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-teal after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:shadow after:transition-all peer-checked:after:translate-x-5"></div>
                                    </label>
                                </li>
                            </ul>
                            <div class="px-6 py-4 border-t border-gray-100 flex justify-end bg-gray-50/50">
                                <button type="submit"
                                        class="inline-flex items-center px-6 py-2.5 bg-teal hover:bg-teal-dark text-white text-sm font-semibold rounded-xl transition-colors duration-200 shadow-sm">
                                    Save Notification Preferences
                                </button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════════
                 GROUP: WORKSPACE PREFERENCES
            ════════════════════════════════════════════════════════ --}}
            <div data-group="workspace navigation sidebar" x-show="groupVisible('workspace navigation sidebar')">
                <h3 class="text-xs font-bold uppercase tracking-widest text-gray-400 mb-3 px-1">Workspace Preferences</h3>
                <div class="space-y-3">

                    <div data-section="navigation sidebar collapsed expanded remember" x-show="sectionVisible('navigation sidebar collapsed expanded remember')"
                         class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-5 border-b border-gray-100">
                            <h4 class="text-base font-bold text-gray-900">Navigation &amp; Layout</h4>
                            <p class="text-sm text-gray-500 mt-0.5">Configure your workspace navigation behavior across browser sessions</p>
                        </div>
                        <form method="POST" action="{{ route('settings.update-navigation') }}">
                            @csrf
                            @method('PATCH')
                            <ul class="divide-y divide-gray-100">
                                <li class="px-6 py-4 flex items-center justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">Remember Sidebar State</p>
                                        <p class="text-xs text-gray-500 mt-0.5">When enabled, the sidebar remembers whether it was expanded or collapsed during your previous session</p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0" aria-label="Toggle Remember Sidebar State">
                                        <input type="hidden" name="remember_sidebar" value="0">
                                        <input type="checkbox" name="remember_sidebar" value="1" {{ $remSide ? 'checked' : '' }} class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-teal after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:shadow after:transition-all peer-checked:after:translate-x-5"></div>
                                    </label>
                                </li>
                            </ul>
                            <div class="px-6 py-4 border-t border-gray-100 flex justify-end bg-gray-50/50">
                                <button type="submit"
                                        class="inline-flex items-center px-6 py-2.5 bg-teal hover:bg-teal-dark text-white text-sm font-semibold rounded-xl transition-colors duration-200 shadow-sm">
                                    Save Workspace Settings
                                </button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════════
                 GROUP: PRIVACY & SECURITY
            ════════════════════════════════════════════════════════ --}}
            <div data-group="privacy security login activity history" x-show="groupVisible('privacy security login activity history')">
                <h3 class="text-xs font-bold uppercase tracking-widest text-gray-400 mb-3 px-1">Privacy &amp; Security</h3>
                <div class="space-y-3">

                    <div data-section="login activity history sessions ip" x-show="sectionVisible('login activity history sessions ip')"
                         class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
                            <div>
                                <h4 class="text-base font-bold text-gray-900">Recent Login Activity</h4>
                                <p class="text-sm text-gray-500 mt-0.5">Your 10 most recent sign-ins to this logistics account</p>
                            </div>
                            @if($loginHistory->isNotEmpty())
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-teal-light text-teal-dark text-xs font-semibold">
                                {{ $loginHistory->count() }} record{{ $loginHistory->count() !== 1 ? 's' : '' }}
                            </span>
                            @endif
                        </div>

                        @if($loginHistory->isEmpty())
                        <div class="px-6 py-8 text-center">
                            <svg class="h-10 w-10 text-gray-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            <p class="text-sm text-gray-500">No login history recorded yet.</p>
                            <p class="text-xs text-gray-400 mt-1">Activity is recorded automatically on each authenticated session.</p>
                        </div>
                        @else
                        <ul class="divide-y divide-gray-100">
                            @foreach($loginHistory as $entry)
                            <li class="px-6 py-3.5 flex items-center justify-between gap-4">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="h-8 w-8 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0">
                                        <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate">{{ $entry->deviceLabel() }}</p>
                                        <p class="text-xs text-gray-500 truncate">{{ $entry->ip_address ?? 'Unknown IP' }}</p>
                                    </div>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <p class="text-xs font-medium text-gray-700">{{ $entry->logged_in_at->format('M j, Y') }}</p>
                                    <p class="text-xs text-gray-400">{{ $entry->logged_in_at->format('g:i A') }}</p>
                                </div>
                            </li>
                            @endforeach
                        </ul>
                        @endif
                    </div>

                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════════
                 GROUP: SUPPORT
            ════════════════════════════════════════════════════════ --}}
            <div data-group="support help messages contact" x-show="groupVisible('support help messages contact')">
                <h3 class="text-xs font-bold uppercase tracking-widest text-gray-400 mb-3 px-1">Support</h3>
                <div class="space-y-3">

                    <div data-section="help support messages contact" x-show="sectionVisible('help support messages contact')"
                         class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-5 flex items-center justify-between gap-4">
                            <div class="flex items-center gap-4">
                                <div class="h-10 w-10 rounded-xl bg-amber-50 flex items-center justify-center flex-shrink-0">
                                    <svg class="h-5 w-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">Help &amp; Operational Support</p>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        @if($supportAdmin)
                                            Contact your INVOIZ system administrator ({{ $supportAdmin->name }}) via the Messages center
                                        @else
                                            Use the Messages center to reach out to INVOIZ system administration
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <a href="{{ route('messages.index') }}"
                               class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-xl transition-colors flex-shrink-0">
                                Open Messages
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </div>
                    </div>

                </div>
            </div>

        </div>{{-- end space-y-8 --}}
    </div>{{-- end max-w-4xl / x-data --}}

@push('scripts')
<script src="{{ asset('assets/logistics/js/settings/index.js') }}"></script>
@endpush

</x-app-layout>
