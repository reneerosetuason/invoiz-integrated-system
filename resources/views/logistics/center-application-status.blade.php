<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Check a Logistics Center Application Status</title>

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
        @media (prefers-reduced-motion: reduce) {
            .animate-spin { animation: none; }
        }
    </style>
</head>
<body class="font-sans antialiased bg-[#F7F6F2] text-gray-900 overflow-x-hidden">
<div class="min-h-screen flex flex-col-reverse lg:flex-row">

    {{-- ================= LEFT PANEL — LOGISTICS BRANDING ================= --}}
    <div class="lg:w-1/2 bg-gradient-to-br from-[#16697A] via-[#0E4A57] to-[#0B3B46] text-white flex flex-col p-8 sm:p-12 xl:p-16">
        <div class="flex-1 flex flex-col justify-center max-w-xl mx-auto lg:mx-0 w-full">
            {{-- Brand --}}
            <a href="/logistics/login" class="inline-flex items-center gap-4 rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60">
                <img src="{{ asset('images/logo.png') }}" alt="Logo" class="h-14 w-14 sm:h-16 sm:w-16 rounded-2xl object-cover ring-1 ring-white/25 shadow-lg">
                <div>
                    <p class="text-2xl sm:text-3xl font-extrabold tracking-tight">Logistics</p>
                    <p class="text-[11px] sm:text-xs font-semibold tracking-[0.3em] uppercase text-white/70 mt-0.5">Logistics Center</p>
                </div>
            </a>

            {{-- Headline --}}
            <h1 class="mt-12 sm:mt-16 text-3xl sm:text-4xl xl:text-5xl font-extrabold leading-tight tracking-tight">
                Track your<br>application.
            </h1>

            <p class="mt-5 text-sm sm:text-base text-white/75 leading-relaxed max-w-md">
                Enter the email address you used when applying to see the current status of your Logistics Center application.
            </p>
        </div>

        {{-- Footer --}}
        <div class="pt-10 flex flex-wrap items-center justify-between gap-3 max-w-xl mx-auto lg:mx-0 w-full">
            <p class="text-xs sm:text-sm text-white/50">Logistics &middot; v1.0 &middot; Logistics</p>
            <a href="/logistics/login" class="inline-flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-white/80 hover:text-white underline underline-offset-2 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back to Login
            </a>
        </div>
    </div>

    {{-- ================= RIGHT PANEL — STATUS LOOKUP ================= --}}
    <div class="lg:w-1/2 flex items-start justify-center p-6 sm:p-10 xl:p-16">
        <div class="w-full max-w-xl">
            <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-gray-900">Check a Logistics Center Application Status</h2>
            <p class="mt-2 text-sm sm:text-base text-gray-500">Enter the email address you used when you applied to see where your application stands.</p>

            {{-- Error summary --}}
            @if ($errors->any())
                <div role="alert" aria-live="assertive"
                     class="mt-6 flex items-start gap-3 bg-red-50 border border-red-200 text-red-700 px-4 py-4 rounded-xl text-sm">
                    <svg class="h-5 w-5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div class="min-w-0">
                        <p class="font-semibold">We couldn&rsquo;t check your status:</p>
                        <ul class="mt-1.5 list-disc list-inside space-y-1">
                            @foreach ($errors->all() as $error)
                                <li class="break-words">{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('center-application.status.check') }}"
                  class="mt-6 bg-white rounded-2xl border border-gray-100 shadow-sm p-6 sm:p-8"
                  x-data="{ submitting: false }"
                  x-on:submit="if (submitting) { $event.preventDefault(); return; } submitting = true"
                  novalidate>
                @csrf

                <div class="min-w-0">
                    <label for="email" class="block text-xs font-semibold tracking-wider uppercase text-gray-500">Email Address <span class="text-red-500" aria-hidden="true">*</span></label>
                    <input id="email" type="email" name="email" value="{{ old('email', $email ?? '') }}"
                           class="block mt-2 w-full @error('email') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
                           required maxlength="255" autocomplete="email" placeholder="you@example.com"
                           aria-describedby="status_email_hint @error('email') status_email_error @enderror"
                           aria-invalid="@error('email') true @enderror" />
                    <p id="status_email_hint" class="mt-1.5 text-xs text-gray-400">The email address you used when you applied.</p>
                    @error('email')<p id="status_email_error" class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <button type="submit" :disabled="submitting"
                        :class="submitting ? 'opacity-70 cursor-not-allowed' : 'hover:bg-teal-dark'"
                        class="mt-6 w-full min-h-[48px] bg-teal text-white rounded-xl py-3.5 font-semibold transition-colors duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-2 disabled:opacity-70">
                    <span class="inline-flex items-center justify-center gap-2">
                        <svg :class="submitting ? 'animate-spin' : ''" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>
                        </svg>
                        <span x-text="submitting ? 'Checking...' : 'Check Status'">Check Status</span>
                    </span>
                </button>
            </form>

            {{-- Result: not found --}}
            @if (($searched ?? false) && $application === null)
                <div role="status" aria-live="polite"
                     class="mt-6 bg-white rounded-2xl border border-amber-200 border-s-4 border-s-amber-400 shadow-sm p-6 sm:p-8">
                    <div class="flex items-start gap-3">
                        <div class="h-10 w-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center flex-shrink-0" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-base font-bold text-gray-900">No application found</h3>
                            <p class="mt-1 text-sm text-gray-500 break-words">
                                We could not find a Logistics Center application for
                                <span class="font-semibold text-gray-700 break-all">{{ $email }}</span>.
                                Double-check the email address you registered with.
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Result: found --}}
            @if (($searched ?? false) && $application !== null)
                <div role="status" aria-live="polite" aria-label="Application status result"
                     class="mt-6 bg-white rounded-2xl border border-gray-100 shadow-sm p-6 sm:p-8">
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Application Status</p>
                            <h3 class="mt-1 text-lg font-bold text-gray-900 break-words">{{ $application->business_name }}</h3>
                            <p class="mt-1 text-sm text-gray-500 break-words">{{ $application->owner_name }}</p>
                        </div>
                        <x-status-badge :status="$application->status" />
                    </div>

                    @if ($application->status === 'pending')
                        <div class="mt-6 flex items-start gap-3 border-s-4 border-amber-400 bg-amber-50 text-amber-800 px-4 py-4 rounded-xl text-sm">
                            <svg class="h-5 w-5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p>Your application is currently being reviewed. We&rsquo;ll update this page once a decision has been made.</p>
                        </div>
                    @elseif ($application->status === 'approved')
                        <div class="mt-6 flex items-start gap-3 border-s-4 border-emerald-400 bg-emerald-50 text-emerald-800 px-4 py-4 rounded-xl text-sm">
                            <svg class="h-5 w-5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p>Your Logistics Center has been approved. Login credentials were sent to your registered email address.</p>
                        </div>
                    @elseif ($application->status === 'rejected')
                        <div class="mt-6 flex items-start gap-3 border-s-4 border-red-400 bg-red-50 text-red-700 px-4 py-4 rounded-xl text-sm">
                            <svg class="h-5 w-5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            <p>Your application was not approved at this time. Please contact us for assistance.</p>
                        </div>
                    @endif

                    <dl class="mt-6 grid grid-cols-1 sm:grid-cols-3 gap-5 border-t border-gray-100 pt-6">
                        <div class="min-w-0">
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Submitted</dt>
                            <dd class="mt-1 text-sm font-medium text-gray-900 break-words">{{ $application->created_at?->format('M d, Y h:i A') ?? '—' }}</dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Reviewed</dt>
                            <dd class="mt-1 text-sm font-medium text-gray-900 break-words">{{ $application->reviewed_at?->format('M d, Y h:i A') ?? 'Not yet' }}</dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Provisioned</dt>
                            <dd class="mt-1 text-sm font-medium text-gray-900 break-words">{{ $application->provisioned_at?->format('M d, Y h:i A') ?? 'Not yet' }}</dd>
                        </div>
                    </dl>
                </div>
            @endif

            <p class="mt-6 text-center text-sm text-gray-500">
                Haven&rsquo;t applied yet?
                <a href="{{ route('center-application.apply') }}" class="font-semibold text-teal hover:text-teal-dark underline underline-offset-2 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-1">
                    Open a Logistics Center
                </a>
            </p>
        </div>
    </div>
</div>
@include('logistics.layouts.partials.form-controls')
</body>
</html>
