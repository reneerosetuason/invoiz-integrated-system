<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Sign in to Logistics Center</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        teal: {
                            DEFAULT: '#16697A',
                            dark: '#0E4A57',
                            light: '#EAF4F3',
                        },
                        secondary: '#F0A202',
                    },
                    fontFamily: {
                        sans: ['Segoe UI', 'system-ui', '-apple-system', 'sans-serif'],
                    },
                },
            },
        };
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear { display: none; }
    </style>
</head>
<body class="font-sans antialiased bg-[#F8FAF9] text-gray-900">
<div class="min-h-screen flex flex-col-reverse lg:flex-row">

    {{-- ================= LEFT PANEL — LOGISTICS BRANDING ================= --}}
    <div class="lg:w-1/2 bg-gradient-to-br from-[#16697A] via-[#0E4A57] to-[#0B3B46] text-white flex flex-col p-8 sm:p-12 xl:p-16">
        <div class="flex-1 flex flex-col justify-center max-w-xl mx-auto lg:mx-0 w-full">
            {{-- Brand --}}
            <div class="flex items-center gap-4">
                <img src="{{ asset('images/logo.png') }}" alt="Logo" class="h-14 w-14 sm:h-16 sm:w-16 rounded-2xl object-cover ring-1 ring-white/25 shadow-lg">
                <div>
                    <p class="text-2xl sm:text-3xl font-extrabold tracking-tight">Logistics</p>
                    <p class="text-[11px] sm:text-xs font-semibold tracking-[0.3em] uppercase text-white/70 mt-0.5">Logistics Center</p>
                </div>
            </div>

            {{-- Headline --}}
            <h1 class="mt-12 sm:mt-16 text-3xl sm:text-4xl xl:text-5xl font-extrabold leading-tight tracking-tight">
                Keep every delivery<br class="hidden sm:block"> moving.
            </h1>

            {{-- Supporting text --}}
            <p class="mt-5 text-sm sm:text-base text-white/75 leading-relaxed max-w-md">
                Manage rider applications, assign deliveries, track packages, and monitor your delivery operations — all from one connected Logistics Center.
            </p>

            {{-- Statistics --}}
            <div class="mt-10 grid grid-cols-3 gap-3 sm:gap-4 max-w-lg">
                <div class="bg-white/10 backdrop-blur rounded-2xl px-4 py-5 sm:px-5 ring-1 ring-white/10">
                    <span class="block w-7 h-[3px] rounded-full bg-secondary"></span>
                    <p class="mt-3 text-xl sm:text-2xl font-bold">{{ $stats['activeRiders'] }}</p>
                    <p class="mt-1 text-xs sm:text-sm text-white/70">Active Riders</p>
                </div>
                <div class="bg-white/10 backdrop-blur rounded-2xl px-4 py-5 sm:px-5 ring-1 ring-white/10">
                    <span class="block w-7 h-[3px] rounded-full bg-secondary"></span>
                    <p class="mt-3 text-xl sm:text-2xl font-bold">{{ $stats['deliveries'] }}</p>
                    <p class="mt-1 text-xs sm:text-sm text-white/70">Deliveries</p>
                </div>
                <div class="bg-white/10 backdrop-blur rounded-2xl px-4 py-5 sm:px-5 ring-1 ring-white/10">
                    <span class="block w-7 h-[3px] rounded-full bg-secondary"></span>
                    <p class="mt-3 text-xl sm:text-2xl font-bold">{{ $stats['completed'] }}</p>
                    <p class="mt-1 text-xs sm:text-sm text-white/70">Completed</p>
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <p class="pt-10 text-xs sm:text-sm text-white/50 max-w-xl mx-auto lg:mx-0 w-full">INVOIZ Logistics &middot; v1.0</p>
    </div>

    {{-- ================= RIGHT PANEL — LOGIN ================= --}}
    <div class="lg:w-1/2 flex items-center justify-center bg-[#F8FAF9] p-6 sm:p-8 xl:p-10">
        <div class="w-full max-w-md">
            <div x-data="{
                submitting: false,
                showPassword: false,
                email: '{{ old('email') }}',
                password: '',
                emailTouched: false,
                passwordTouched: false,
                get emailError() {
                    if (!this.emailTouched) return '';
                    if (!this.email) return 'Please enter your email address.';
                    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.email)) return 'Please enter a valid email address.';
                    return '';
                },
                get passwordError() {
                    if (!this.passwordTouched) return '';
                    if (!this.password) return 'Please enter your password.';
                    return '';
                }
            }">
                <form method="POST" action="/logistics/login"
                      class="bg-white rounded-2xl border border-gray-100 shadow-xl shadow-[#0E4A57]/10 p-5 sm:p-6"
                      x-on:submit="if (!email || !password || emailError || passwordError) { $event.preventDefault(); emailTouched = true; passwordTouched = true; } else { submitting = true }"
                      novalidate>
                    @csrf

                    <div class="flex items-center gap-3">
                        <img src="{{ asset('images/logo.png') }}" alt="INVOIZ" class="h-12 w-12 rounded-xl object-cover ring-1 ring-gray-200">
                        <div>
                            <p class="text-xl font-extrabold tracking-tight text-[#16697A]">INVOIZ</p>
                            <p class="text-xs font-semibold text-gray-500">Logistics Center</p>
                        </div>
                    </div>

                    <h2 class="mt-5 text-2xl sm:text-3xl font-extrabold tracking-tight text-[#1F2933]">Sign in to Logistics Center</h2>

                    {{-- Server-side auth errors --}}
                    @if ($errors->has('email'))
                        <div role="alert" aria-live="assertive" class="mt-4 flex items-start gap-2.5 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">
                            <svg class="h-4 w-4 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>{{ $errors->first('email') }}</span>
                        </div>
                    @endif

                    {{-- Email Address --}}
                    <div class="mt-5">
                        <label for="email" class="block text-sm font-semibold text-[#1F2933]">Email address</label>
                        <input id="email"
                               type="email"
                               name="email"
                               x-model="email"
                               x-on:blur="emailTouched = true"
                               class="block mt-2 w-full border-gray-300 focus:border-teal focus:ring-teal rounded-xl shadow-sm py-3 px-4 text-[#1F2933] @error('email') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
                               value="{{ old('email') }}"
                               required
                               autofocus
                               autocomplete="username"
                               placeholder="Email address"
                               aria-invalid="@error('email') true @enderror" />
                        <p x-show="emailError" x-cloak x-text="emailError" class="mt-1.5 text-sm text-red-600"></p>
                    </div>

                    {{-- Password --}}
                    <div class="mt-4">
                        <label for="password" class="block text-sm font-semibold text-[#1F2933]">Password</label>
                        <div class="relative mt-2">
                            <input id="password"
                                   x-bind:type="showPassword ? 'text' : 'password'"
                                   name="password"
                                   x-model="password"
                                   x-on:blur="passwordTouched = true"
                                   class="block w-full border-gray-300 focus:border-teal focus:ring-teal rounded-xl shadow-sm py-3 pl-4 pr-12 text-[#1F2933] @error('password') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
                                   required
                                   autocomplete="current-password"
                                   placeholder="Password"
                                   aria-invalid="@error('password') true @enderror" />
                            <button type="button"
                                    x-on:click="showPassword = !showPassword"
                                    class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-teal rounded-lg"
                                    :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                    :aria-pressed="showPassword"
                                    tabindex="0">
                                {{-- Slashed eye = password hidden (default). Click to reveal. --}}
                                <svg x-show="!showPassword" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M3 3l18 18" />
                                </svg>
                                {{-- Open eye = password visible. Click to hide. --}}
                                <svg x-show="showPassword" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>
                        </div>
                        <p x-show="passwordError" x-cloak x-text="passwordError" class="mt-1.5 text-sm text-red-600"></p>
                    </div>

                    {{-- Forgot password (Remember Me intentionally not offered: Logistics sessions are browser-session only) --}}
                    <div class="flex items-center justify-end mt-4">
                        <a href="{{ url('/logistics/forgot-password') }}"
                           class="text-sm font-medium text-teal hover:text-teal-dark underline underline-offset-2 rounded focus:outline-none focus:ring-2 focus:ring-teal focus:ring-offset-1">
                            Forgot password?
                        </a>
                    </div>

                    {{-- Sign in button --}}
                    <button type="submit"
                            :disabled="submitting"
                            :class="submitting ? 'opacity-70 cursor-not-allowed' : 'hover:bg-teal-dark'"
                            class="relative mt-5 w-full min-h-[48px] bg-teal text-white rounded-xl py-3.5 font-semibold transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-teal focus:ring-offset-2 disabled:opacity-70">
                        <span class="inline-flex items-center justify-center gap-2">
                            <svg x-show="submitting" x-cloak class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            <span x-text="submitting ? 'Logging in...' : 'Log in'">Log in</span>
                        </span>
                    </button>

                    <div class="mt-5 border-t border-gray-100 pt-4 text-center">
                        <a href="{{ route('center-application.apply') }}"
                           class="group flex min-h-[44px] w-full items-center justify-center gap-2 rounded-xl border-2 border-teal/20 bg-teal-light py-3 font-semibold text-teal-dark transition-colors duration-200 hover:border-teal/40 hover:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-2">
                            Open a Logistics Center
                        </a>
                    </div>
                </form>
            </div>

            {{-- Public action: check a Logistics Center application status (outside the card) --}}
            <div class="mt-4">
                <nav class="flex justify-center" aria-label="Public center actions">
                    <a href="{{ route('center-application.status') }}"
                       class="group inline-flex min-h-[48px] w-full min-w-0 max-w-sm items-center justify-center gap-2 rounded-xl border border-teal/15 bg-white px-5 py-3 text-center text-sm font-semibold text-teal shadow-sm shadow-[#0E4A57]/5 transition-colors hover:border-teal/30 hover:text-teal-dark focus:outline-none focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-2">
                        <svg class="h-4 w-4 flex-none text-teal/70" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"></path>
                        </svg>
                        <span class="break-words min-w-0">Check a Logistics Center Application Status</span>
                    </a>
                </nav>
            </div>
        </div>
    </div>
</div>
@include('logistics.layouts.partials.form-controls')
</body>
</html>
