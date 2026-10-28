<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Choose account — INVOIZ</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,560;9..144,700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('assets/shared/css/shop.css') }}?v={{ filemtime(public_path('assets/shared/css/shop.css')) }}">
  @php
    $storeParts = preg_split('/\s+/', trim((string) $storeName), -1, PREG_SPLIT_NO_EMPTY);
    $storeInitials = strtoupper(collect($storeParts)->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('')) ?: 'S';
  @endphp
</head>
<body class="choosex">
  <main class="choose-shell">
    <div class="choose-brand" aria-label="INVOIZ">
      <img src="{{ asset('assets/shared/images/logo.png') }}" onerror="this.style.display='none'" alt="" class="choose-logo">
      <span>INVOIZ</span>
    </div>

    <section class="choose-content" aria-labelledby="chooseTitle">
      <h1 id="chooseTitle">Welcome, {{ $user->displayName() }}</h1>
      <p class="choose-intro">Your login works as <b>both buyer and seller</b>. Choose how to continue:</p>

      <div class="choose-options">
        <form method="POST" action="{{ route('role.switch') }}" class="choose-form">
          @csrf
          <input type="hidden" name="role" value="buyer">
          <button type="submit" class="choose-option">
            <span class="choose-option-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            </span>
            <span class="choose-option-copy">
              <span class="choose-option-title">Continue as Buyer</span>
              <span class="choose-option-caption">Shop, cart, orders</span>
            </span>
            <svg class="choose-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
          </button>
        </form>

        <form method="POST" action="{{ route('role.switch') }}" class="choose-form">
          @csrf
          <input type="hidden" name="role" value="seller">
          <button type="submit" class="choose-option choose-option-seller">
            <span class="choose-option-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9z"/><path d="M9 22V12h6v10"/></svg>
            </span>
            <span class="choose-option-copy">
              <span class="choose-option-title">Continue as Seller</span>
              <span class="choose-option-caption choose-store-caption">
                <span class="choose-store-mark" aria-hidden="true">{{ $storeInitials }}</span>
                <span class="choose-store-name" title="{{ $storeName }}">{{ $storeName }}</span>
              </span>
            </span>
            <svg class="choose-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
          </button>
        </form>
      </div>

      <form method="POST" action="{{ url('/logout') }}" class="choose-logout-form">
        @csrf
        <button type="submit" class="choose-logout">Not you? Log out</button>
      </form>
    </section>

    <footer class="choose-footer">© 2026 INVOIZ</footer>
  </main>
</body>
</html>
