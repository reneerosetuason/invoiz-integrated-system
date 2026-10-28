<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Logistics') }}</title>

        <script src="https://cdn.tailwindcss.com"></script>
        <script src="{{ asset('assets/logistics/js/layouts/guest.js') }}"></script>
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
        <link rel="stylesheet" href="{{ asset('assets/logistics/css/layouts/guest.css') }}">
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gradient-to-br from-[#16697A] via-[#0E4A57] to-[#0E4A57] flex flex-col sm:justify-center items-center p-6">
            <div class="mb-6 flex items-center gap-3">
                <div class="h-11 w-11 rounded-2xl bg-white/20 backdrop-blur flex items-center justify-center">
                <img src="{{ asset('assets/shared/images/logo.png') }}" alt="Logo" class="h-6 w-6 rounded-lg object-cover">
                </div>
                <span class="text-2xl font-extrabold tracking-tight text-white">LOGISTICS</span>
            </div>

            <div class="w-full sm:max-w-md bg-white rounded-2xl shadow-2xl p-8">
                {{ $slot }}
            </div>

            <p class="mt-6 text-sm text-white/80">&copy; {{ date('Y') }} Logistics. All rights reserved.</p>
        </div>
        @include('logistics.layouts.partials.form-controls')
    </body>
</html>