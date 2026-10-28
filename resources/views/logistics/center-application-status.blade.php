<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Check a Logistics Center Application Status</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="{{ asset('assets/logistics/js/auth/forgot-password.js') }}"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="{{ asset('assets/logistics/css/center-application-status.css') }}">
</head>
<body class="font-sans antialiased bg-[#F8FAF9] text-gray-900 overflow-x-hidden">
<div class="min-h-screen flex flex-col-reverse lg:flex-row">

    {{-- ================= LEFT PANEL — LOGISTICS BRANDING ================= --}}
    {{-- Same compact 40/60 system as login + apply. This page had the worst
         ratio (one short paragraph in a 50% full-height column), so the
         narrower column + tighter rhythm fixes it without adding filler. --}}
    <div class="lg:w-[40%] xl:w-[38%] bg-gradient-to-br from-[#16697A] via-[#0E4A57] to-[#0B3B46] text-white flex flex-col p-6 sm:p-10 lg:p-10 xl:p-12">
        <div class="w-full max-w-md mx-auto lg:mx-0 my-auto py-8 lg:py-10 flex flex-col justify-center">
            {{-- Brand --}}
            <a href="{{ '/logistics/login' }}" class="inline-flex items-center gap-3 rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60">
                <img src="{{ asset('assets/shared/images/logo.png') }}" alt="Logo" class="h-12 w-12 rounded-xl object-cover ring-1 ring-white/25 shadow-lg">
                <div>
                    <p class="text-xl font-extrabold tracking-tight">Logistics</p>
                    <p class="text-[11px] font-semibold tracking-[0.25em] uppercase text-white/70 mt-0.5">Logistics Center</p>
                </div>
            </a>

            {{-- Headline --}}
            <h1 class="mt-8 text-2xl sm:text-3xl xl:text-4xl font-extrabold leading-tight tracking-tight">
                Track your application.
            </h1>

            <p class="mt-4 text-sm sm:text-[15px] text-white/75 leading-relaxed">
                Enter the email address you used when applying to see the current status of your Logistics Center application.
            </p>
        </div>

        {{-- Footer --}}
        <div class="mt-8 pt-6 border-t border-white/10 flex flex-wrap items-center justify-between gap-3 w-full max-w-md mx-auto lg:mx-0">
            <p class="text-xs text-white/50">Logistics &middot; v1.0 &middot; Logistics</p>
            <a href="{{ '/logistics/login' }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-white/80 hover:text-white underline underline-offset-2 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back to Login
            </a>
        </div>
    </div>

    {{-- ================= RIGHT PANEL — STATUS LOOKUP ================= --}}
    {{-- m-auto vertical rhythm (matches apply page): centers the short lookup
         form without forcing full-viewport centering on mobile, and lets long
         result states scroll naturally. --}}
    <div class="flex-1 lg:w-[60%] xl:w-[62%] flex justify-center bg-[#F8FAF9] px-6 py-10 sm:p-10">
        <div class="w-full max-w-lg m-auto">
            <h2 class="text-[22px] sm:text-[27px] leading-snug font-bold tracking-tight text-[#1F2933]">Check application status</h2>
            <p class="mt-3 text-[15px] leading-relaxed text-gray-500">Enter the email address you used when applying.</p>

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
                  class="mt-6 bg-white rounded-2xl border border-gray-200/80 shadow-[0_10px_36px_-16px_rgba(14,74,87,0.28)] p-6 sm:p-7"
                  data-status-verification
                  data-send-url="{{ route('center-application.verification.send') }}"
                  data-verify-url="{{ route('center-application.verification.verify') }}"
                  x-data="{ submitting: false }"
                  novalidate>
                @csrf

                <div class="min-w-0">
                    <label for="email" class="block text-xs font-bold tracking-wider uppercase text-gray-600">Email address <span class="text-red-500" aria-hidden="true">*</span></label>
                    <input id="email" type="email" name="email" value="{{ old('email', $email ?? '') }}"
                           class="block mt-2.5 w-full h-[52px] rounded-xl border-gray-300 bg-white px-4 text-[15px] text-gray-900 placeholder:text-gray-400 focus:border-teal focus:ring-4 focus:ring-teal/15 @error('email') border-red-400 focus:border-red-500 focus:ring-red-500/15 @enderror"
                           required maxlength="255" autocomplete="email" placeholder="you@example.com"
                           aria-describedby="status_email_hint @error('email') status_email_error @enderror"
                           aria-invalid="@error('email') true @enderror" />
                    <p id="status_email_hint" class="mt-2 text-[13px] leading-relaxed text-gray-500">Use the same email address from your application.</p>
                    @error('email')<p id="status_email_error" class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="mt-4">
                    <button type="button" data-send-code class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold">Send verification code</button>
                    <label for="status-code" class="mt-3 block text-sm font-semibold">Email verification code</label>
                    <input id="status-code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" class="mt-2 block w-full rounded-xl border border-gray-300 p-3" placeholder="6-digit code">
                    <p data-verification-feedback role="status" class="mt-2 text-sm text-gray-600">Verify your email to keep your application details private.</p>
                </div>
                <button type="submit" :disabled="submitting"
                        :class="submitting ? 'opacity-70 cursor-not-allowed' : 'hover:bg-teal-dark active:bg-teal-dark active:scale-[0.99]'"
                        class="mt-6 w-full min-h-[52px] bg-teal text-white rounded-xl px-6 py-3.5 text-[15px] font-bold tracking-wide shadow-[0_10px_25px_-10px_rgba(22,105,122,0.55)] transition duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-2 disabled:opacity-70">
                    <span class="inline-flex items-center justify-center gap-2.5">
                        <svg :class="submitting ? 'animate-spin' : ''" class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.25" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>
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
                            <p class="mt-1 text-sm text-gray-500 break-words">{{ $application->displayOwnerName() }}</p>
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

            <p class="mt-5 text-center text-sm text-gray-500">
                Have not applied yet?
                <a href="{{ route('center-application.apply') }}" class="font-semibold text-teal hover:text-teal-dark hover:underline hover:underline-offset-2 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-1">
                    Open a Logistics Center
                </a>
            </p>

            {{-- How it works --}}
            <hr class="my-8 border-t border-gray-200" aria-hidden="true">
            <section aria-labelledby="how-it-works-heading">
                <h3 id="how-it-works-heading" class="text-lg font-bold tracking-tight text-[#1F2933]">How it works</h3>
                <ol class="mt-5 space-y-5">
                    <li class="flex items-start gap-4">
                        <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-teal-light text-teal" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </span>
                        <span class="min-w-0 pt-0.5">
                            <span class="block text-[15px] font-semibold text-gray-900">Enter your email</span>
                            <span class="mt-1 block text-[13px] leading-relaxed text-gray-500">Use the email associated with your application.</span>
                        </span>
                    </li>
                    <li class="flex items-start gap-4">
                        <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                        </span>
                        <span class="min-w-0 pt-0.5">
                            <span class="block text-[15px] font-semibold text-gray-900">Check your application</span>
                            <span class="mt-1 block text-[13px] leading-relaxed text-gray-500">Submit the form to retrieve your current status.</span>
                        </span>
                    </li>
                    <li class="flex items-start gap-4">
                        <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-teal-light text-teal-dark" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                        </span>
                        <span class="min-w-0 pt-0.5">
                            <span class="block text-[15px] font-semibold text-gray-900">Review the result</span>
                            <span class="mt-1 block text-[13px] leading-relaxed text-gray-500">See the status and any next steps provided by the system.</span>
                        </span>
                    </li>
                </ol>
            </section>
        </div>
    </div>
</div>
@include('logistics.layouts.partials.form-controls')
<script src="{{ asset('assets/logistics/js/application-status.js') }}"></script>
</body>
</html>
