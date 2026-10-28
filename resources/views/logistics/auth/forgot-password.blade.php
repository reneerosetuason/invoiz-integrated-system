<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Password Recovery — Logistics</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="{{ asset('assets/logistics/js/auth/forgot-password.js') }}"></script>
</head>
<body class="font-sans antialiased bg-[#F7F6F2] text-gray-900">
<div class="min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8 sm:p-10 text-center">
            {{-- Brand --}}
            <div class="flex flex-col items-center">
                <img src="{{ asset('assets/shared/images/logo.png') }}" alt="Logo" class="h-14 w-14 rounded-2xl object-cover ring-1 ring-gray-100">
                <p class="mt-3 text-lg font-extrabold tracking-tight text-gray-900">Logistics</p>
                <p class="text-[11px] font-semibold tracking-[0.3em] uppercase text-gray-400 mt-0.5">Logistics Center</p>
            </div>

            {{-- Icon --}}
            <div class="mt-8 mx-auto w-14 h-14 rounded-2xl bg-teal-light text-teal-dark flex items-center justify-center">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
            </div>

            {{-- Heading --}}
            <h1 class="mt-5 text-2xl sm:text-3xl font-extrabold tracking-tight text-gray-900">Password Recovery</h1>

            {{-- Message --}}
            <p class="mt-3 text-sm sm:text-base text-gray-500 leading-relaxed">
                For security reasons, Logistics passwords cannot be reset through this page.
            </p>

            <div class="mt-5 bg-teal-light/60 border border-teal/20 rounded-xl px-5 py-4 text-left">
                <p class="text-sm text-gray-700 leading-relaxed">
                    <span class="font-semibold text-teal-dark">Please contact the Logistics Manager</span>
                    to have your password reset. Once the Logistics Manager has reset it, sign in with the new password.
                </p>
            </div>

            {{-- Back to Login --}}
            <a href="/logistics/login"
               class="mt-7 inline-flex items-center justify-center gap-2 w-full bg-teal text-white rounded-xl py-3.5 font-semibold hover:bg-teal-dark transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-teal focus:ring-offset-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Back to Login
            </a>
        </div>

        <p class="mt-6 text-center text-xs sm:text-sm text-gray-400">Logistics &middot; v1.0 &middot; Logistics</p>
    </div>
</div>
@include('logistics.layouts.partials.form-controls')
</body>
</html>
