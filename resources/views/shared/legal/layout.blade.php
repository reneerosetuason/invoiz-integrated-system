<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') | INVOIZ</title>
    <meta name="description" content="@yield('description')">
    <link rel="icon" href="{{ asset('assets/shared/images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('assets/shared/css/legal.css') }}?v={{ filemtime(public_path('assets/shared/css/legal.css')) }}">
</head>
<body>
    <header class="legal-header">
        <a class="legal-brand" href="{{ url('/shop') }}" aria-label="INVOIZ marketplace"><img src="{{ asset('assets/shared/images/logo.png') }}" width="36" height="36" alt=""><strong>INVOIZ</strong></a>
        <nav aria-label="Site navigation"><a href="{{ url('/shop') }}">Marketplace</a><a href="{{ url('/help') }}">Help Center</a></nav>
    </header>
    <main class="legal-page">
        <div class="legal-heading"><p class="legal-eyebrow">INVOIZ policies</p><h1>@yield('title')</h1><p>@yield('description')</p><span>Last updated: November 5, 2026</span></div>
        <div class="legal-grid">
            <aside class="legal-index"><h2>On this page</h2><nav aria-label="Policy contents">@yield('contents')</nav><a class="legal-other" href="{{ request()->routeIs('legal.terms') ? route('legal.privacy') : route('legal.terms') }}">{{ request()->routeIs('legal.terms') ? 'Read the Privacy Policy' : 'Read the Terms of Service' }} &rarr;</a></aside>
            <article class="legal-document" aria-label="@yield('title')">@yield('document')</article>
        </div>
    </main>
    <footer class="legal-footer"><span>&copy; {{ now()->year }} INVOIZ</span><nav aria-label="Policy navigation"><a href="{{ route('legal.terms') }}" @if(request()->routeIs('legal.terms')) aria-current="page" @endif>Terms of Service</a><a href="{{ route('legal.privacy') }}" @if(request()->routeIs('legal.privacy')) aria-current="page" @endif>Privacy Policy</a><a href="{{ url('/help') }}">Help Center</a></nav></footer>
</body>
</html>
