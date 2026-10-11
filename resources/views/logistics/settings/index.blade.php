<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2 text-sm text-gray-400 mb-1">
            <span>Home</span>
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-gray-900 font-medium">Settings</span>
        </div>
        <h2 class="text-2xl font-bold text-gray-900 leading-tight">Settings</h2>
        <p class="mt-1 text-sm text-gray-500">Manage your account preferences, security, and notifications</p>
    </x-slot>

    @php
        $notif    = fn (string $key) => $settings->notificationEnabled($key) ? 'checked' : '';
        $delivery = $settings->delivery ?? [];
        $theme    = $settings->theme();
        $tz       = $settings->timezone() ?? config('app.timezone');
        $remSide  = $settings->rememberSidebar();
    @endphp

    {{-- ── Search + layout wrapper ─────────────────────────────── --}}
    <div class="max-w-4xl mx-auto" x-data="settingsSearch()">

        {{-- Search settings --}}
        <div class="mb-6 relative">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
                </svg>
            </div>
            <input type="search" x-model="query" @input="filter()"
                   placeholder="Search settings… (e.g. Password, Notifications, Appearance, Delivery, Privacy, Login)"
                   class="w-full pl-10 pr-4 py-3 border border-gray-200 rounded-xl text-sm bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-teal focus:border-transparent"
                   aria-label="Search settings">
            <p x-show="query && noResults()" class="mt-2 text-sm text-gray-500 pl-1">No settings match "<span x-text="query"></span>".</p>
        </div>

        <div class="space-y-8">

        {{-- ═══════════════════════════════════════════════════════
             GROUP: ACCOUNT
        ════════════════════════════════════════════════════════ --}}
        <div data-group="account profile security password" x-show="groupVisible('account profile security password')">
            <h3 class="text-xs font-bold uppercase tracking-widest text-gray-400 mb-3 px-1">Account</h3>
            <div class="space-y-3">

                {{-- Profile card --}}
                <div data-section="profile account" x-show="sectionVisible('profile account')"
                     class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-5 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-4">
                            <div class="h-10 w-10 rounded-xl bg-teal-light flex items-center justify-center flex-shrink-0">
                                <svg class="h-5 w-5 text-teal-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-900">Profile</p>
                                <p class="text-xs text-gray-500 mt-0.5">Your personal information and profile photo live on the separate Profile page</p>
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
                <div data-section="security password change" x-show="sectionVisible('security password change')"
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
                                <p class="text-sm font-semibold text-gray-900">Security &amp; Password</p>
                                <p class="text-xs text-gray-500 mt-0.5">Keep your account secure with a strong password</p>
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
                                <h4 class="font-bold text-gray-900">Change Password</h4>
                                <button @click="pwOpen = false" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100">
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
             GROUP: PREFERENCES
        ════════════════════════════════════════════════════════ --}}
        <div data-group="preferences notifications delivery appearance navigation language region timezone" x-show="groupVisible('preferences notifications delivery appearance navigation language region timezone')">
            <h3 class="text-xs font-bold uppercase tracking-widest text-gray-400 mb-3 px-1">Preferences</h3>
            <div class="space-y-3">

                {{-- Notifications --}}
                <div data-section="notifications alerts messages rider delivery" x-show="sectionVisible('notifications alerts messages rider delivery')"
                     class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-5 border-b border-gray-100">
                        <h4 class="text-base font-bold text-gray-900">Notifications</h4>
                        <p class="text-sm text-gray-500 mt-0.5">Choose which in-system notifications you receive</p>
                    </div>
                    <form method="POST" action="{{ route('settings.update-notifications') }}">
                        @csrf
                        @method('PUT')
                        <ul class="divide-y divide-gray-100">
                            @foreach([
                                'rider_applications'  => ['New Rider Applications',  'Notified when a rider submits an application'],
                                'application_updates' => ['Application Updates',     'Notified about Logistics Center application submissions and decisions'],
                                'delivery_requests'   => ['New Delivery Requests',   'Notified when a new delivery is created'],
                                'failed_deliveries'   => ['Failed Deliveries',       'Notified when a delivery attempt fails'],
                                'failed_pickups'      => ['Failed Pickups',          'Notified when a rider cannot pick up a package'],
                                'rider_status_updates'=> ['Rider Status Updates',    'Notified when riders go online, offline, or deliver'],
                                'delivery_completed'  => ['Delivery Completed',      'Notified when a delivery is successfully completed'],
                                'new_messages'        => ['New Messages',            'Notified about new messages from riders and sellers'],
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
                            <li class="px-6 py-4 flex items-center justify-between gap-4 bg-surface-soft/50">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">Email Notifications</p>
                                    <p class="text-xs text-gray-500 mt-0.5">Saved preference — email delivery is not currently configured</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer flex-shrink-0" aria-label="Toggle Email Notifications">
                                    <input type="hidden" name="email_notifications" value="0">
                                    <input type="checkbox" name="email_notifications" value="1" {{ $settings->email_notifications ? 'checked' : '' }} class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-teal after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:shadow after:transition-all peer-checked:after:translate-x-5"></div>
                                </label>
                            </li>
                        </ul>
                        <div class="px-6 py-4 border-t border-gray-100 flex justify-end">
                            <button type="submit"
                                    class="inline-flex items-center px-6 py-2.5 bg-teal hover:bg-teal-dark text-white text-sm font-semibold rounded-xl transition-colors duration-200">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                {{-- Delivery Preferences --}}
                <div data-section="delivery preferences proof attempts reassignment" x-show="sectionVisible('delivery preferences proof attempts reassignment')"
                     class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-5 border-b border-gray-100">
                        <h4 class="text-base font-bold text-gray-900">Delivery Preferences</h4>
                        <p class="text-sm text-gray-500 mt-0.5">Operational rules applied to delivery workflows</p>
                    </div>
                    <form method="POST" action="{{ route('settings.update-delivery') }}">
                        @csrf
                        @method('PUT')
                        <ul class="divide-y divide-gray-100">
                            <li class="px-6 py-4 flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">Require Proof of Delivery</p>
                                    <p class="text-xs text-gray-500 mt-0.5">Riders must upload photo or signature proof before completing a delivery</p>
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
                                    <p class="text-xs text-gray-500 mt-0.5">Number of attempts before a delivery is marked as failed</p>
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
                                    <p class="text-xs text-gray-500 mt-0.5">Permit reassigning an unaccepted delivery to another available rider</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer flex-shrink-0" aria-label="Toggle Allow Rider Reassignment">
                                    <input type="hidden" name="allow_reassignment" value="0">
                                    <input type="checkbox" name="allow_reassignment" value="1" {{ ($delivery['allow_reassignment'] ?? true) ? 'checked' : '' }} class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-teal after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:shadow after:transition-all peer-checked:after:translate-x-5"></div>
                                </label>
                            </li>
                        </ul>
                        <div class="px-6 py-4 border-t border-gray-100 flex justify-end">
                            <button type="submit"
                                    class="inline-flex items-center px-6 py-2.5 bg-teal hover:bg-teal-dark text-white text-sm font-semibold rounded-xl transition-colors duration-200">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                {{-- Appearance --}}
                <div data-section="appearance theme light dark system" x-show="sectionVisible('appearance theme light dark system')"
                     class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-5 border-b border-gray-100">
                        <h4 class="text-base font-bold text-gray-900">Appearance</h4>
                        <p class="text-sm text-gray-500 mt-0.5">Choose how the interface looks. Persists across sessions.</p>
                    </div>
                    <form method="POST" action="{{ route('settings.update-appearance') }}">
                        @csrf
                        @method('PUT')
                        <div class="px-6 py-5">
                            <div class="grid grid-cols-3 gap-3">
                                @foreach(['light' => ['Light', 'M7 21h10M12 3v18M5 8l7-5 7 5'], 'dark' => ['Dark', 'M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z'], 'system' => ['System', 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2']] as $val => [$label, $icon])
                                <label class="cursor-pointer">
                                    <input type="radio" name="theme" value="{{ $val }}" class="peer sr-only" {{ $theme === $val ? 'checked' : '' }}>
                                    <div class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 transition-all peer-checked:border-teal peer-checked:bg-teal-light {{ $theme === $val ? 'border-teal bg-teal-light' : 'border-gray-200 hover:border-gray-300' }}">
                                        <svg class="h-6 w-6 {{ $theme === $val ? 'text-teal-dark' : 'text-gray-500' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"/>
                                        </svg>
                                        <span class="text-sm font-semibold {{ $theme === $val ? 'text-teal-dark' : 'text-gray-700' }}">{{ $label }}</span>
                                    </div>
                                </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="px-6 py-4 border-t border-gray-100 flex justify-end">
                            <button type="submit"
                                    class="inline-flex items-center px-6 py-2.5 bg-teal hover:bg-teal-dark text-white text-sm font-semibold rounded-xl transition-colors duration-200">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                {{-- Navigation Preferences --}}
                <div data-section="navigation sidebar collapsed expanded remember" x-show="sectionVisible('navigation sidebar collapsed expanded remember')"
                     class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-5 border-b border-gray-100">
                        <h4 class="text-base font-bold text-gray-900">Navigation Preferences</h4>
                        <p class="text-sm text-gray-500 mt-0.5">Control how the sidebar behaves across sessions</p>
                    </div>
                    <form method="POST" action="{{ route('settings.update-navigation') }}">
                        @csrf
                        @method('PATCH')
                        <ul class="divide-y divide-gray-100">
                            <li class="px-6 py-4 flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">Remember Sidebar State</p>
                                    <p class="text-xs text-gray-500 mt-0.5">When enabled, the sidebar remembers whether it was expanded or collapsed</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer flex-shrink-0" aria-label="Toggle Remember Sidebar State">
                                    <input type="hidden" name="remember_sidebar" value="0">
                                    <input type="checkbox" name="remember_sidebar" value="1" {{ $remSide ? 'checked' : '' }} class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-teal after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:shadow after:transition-all peer-checked:after:translate-x-5"></div>
                                </label>
                            </li>
                        </ul>
                        <div class="px-6 py-4 border-t border-gray-100 flex justify-end">
                            <button type="submit"
                                    class="inline-flex items-center px-6 py-2.5 bg-teal hover:bg-teal-dark text-white text-sm font-semibold rounded-xl transition-colors duration-200">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                {{-- Language & Region --}}
                <div data-section="language region timezone" x-show="sectionVisible('language region timezone')"
                     class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-5 border-b border-gray-100">
                        <h4 class="text-base font-bold text-gray-900">Language &amp; Region</h4>
                        <p class="text-sm text-gray-500 mt-0.5">Set your timezone for accurate date and time display</p>
                    </div>
                    <form method="POST" action="{{ route('settings.update-region') }}">
                        @csrf
                        @method('PUT')
                        <div class="px-6 py-5">
                            <label for="timezone" class="block text-sm font-medium text-gray-700 mb-1">Timezone</label>
                            <select id="timezone" name="timezone"
                                    class="block w-full border-gray-300 focus:border-teal focus:ring-teal rounded-xl shadow-sm text-sm">
                                @foreach(\App\Models\LogisticsSetting::TIMEZONES as $tzOption)
                                <option value="{{ $tzOption }}" {{ $tz === $tzOption ? 'selected' : '' }}>{{ $tzOption }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1.5 text-xs text-gray-400">Affects how dates and times are displayed throughout the application</p>
                        </div>
                        <div class="px-6 py-4 border-t border-gray-100 flex justify-end">
                            <button type="submit"
                                    class="inline-flex items-center px-6 py-2.5 bg-teal hover:bg-teal-dark text-white text-sm font-semibold rounded-xl transition-colors duration-200">
                                Save Changes
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

                {{-- Login / Activity History --}}
                <div data-section="login activity history sessions ip" x-show="sectionVisible('login activity history sessions ip')"
                     class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <h4 class="text-base font-bold text-gray-900">Login History</h4>
                            <p class="text-sm text-gray-500 mt-0.5">Your 10 most recent sign-ins to this account</p>
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
                        <p class="text-xs text-gray-400 mt-1">History is recorded automatically on each sign-in.</p>
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
                                <p class="text-sm font-semibold text-gray-900">Help &amp; Support</p>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    @if($supportAdmin)
                                        Contact your system administrator via the Messages centre
                                    @else
                                        Use the Messages centre to contact your team
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
<script>
function settingsSearch() {
    return {
        query: '',
        filter() {
            // handled reactively via x-show bindings
        },
        _matches(keywords) {
            if (!this.query.trim()) return true;
            const q = this.query.toLowerCase();
            return keywords.toLowerCase().split(' ').some(k => k.includes(q)) || q.split(' ').some(w => keywords.toLowerCase().includes(w));
        },
        sectionVisible(keywords) {
            return this._matches(keywords);
        },
        groupVisible(keywords) {
            return this._matches(keywords);
        },
        noResults() {
            const sections = document.querySelectorAll('[data-section]');
            return Array.from(sections).every(el => {
                const kw = el.getAttribute('data-section') || '';
                return !this._matches(kw);
            });
        }
    };
}
</script>
@endpush

</x-app-layout>
