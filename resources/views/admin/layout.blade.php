<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Invoiz Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('assets/shared/css/app.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/shared/css/app-sidebar.css') }}?v={{ filemtime(public_path('assets/shared/css/app-sidebar.css')) }}">
  <script src="{{ asset('assets/shared/js/app-sidebar.js') }}?v={{ filemtime(public_path('assets/shared/js/app-sidebar.js')) }}" defer></script>
  <script src="{{ asset('assets/admin/js/layout.js') }}"></script>
  <link rel="stylesheet" href="{{ asset('assets/admin/css/layout.css') }}">
  @stack('styles')
</head>
<body>
  <div class="shell">
    @include('shared.navigation.sidebar', ['navigationRole' => 'admin', 'navigationVariant' => 'dashboard'])
    <div class="main">
      @include('shared.navigation.navbar', ['navigationRole' => 'admin', 'navigationVariant' => 'dashboard'])
      <main class="content">
        @yield('content')
      </main>
    </div>
  </div>
  <script src="{{ asset('assets/admin/js/layout-2.js') }}"></script>
  <div id="confirmModal" class="confirm-overlay" role="dialog" aria-modal="true">
    <div class="confirm-card">
      <div id="confirmIcon" class="confirm-icon">
        <svg viewBox="0 0 24 24"><path d="M1 21h22L12 2 1 21Zm12-3h-2v-2h2v2Zm0-4h-2v-4h2v4Z"/></svg>
      </div>
      <h3 id="confirmTitle">Confirm action</h3>
      <p id="confirmMsg">Are you sure you want to proceed?</p>
      <div class="confirm-actions">
        <button type="button" id="confirmCancel" class="btn btn-neutral">Cancel</button>
        <button type="button" id="confirmOk" class="btn btn-danger">OK</button>
      </div>
    </div>
  </div>
  <script src="{{ asset('assets/admin/js/layout-3.js') }}"></script>
  <script src="{{ asset('assets/shared/js/table-enhancer.js') }}"></script>
  @stack('scripts')
</body>
</html>
