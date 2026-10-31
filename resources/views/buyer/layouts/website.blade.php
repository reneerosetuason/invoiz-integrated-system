<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Invoiz — Online Shopping</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('assets/shared/css/frontend-app.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/shared/css/theme.css') }}">
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,560;9..144,700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('assets/shared/css/app-sidebar.css') }}?v={{ filemtime(public_path('assets/shared/css/app-sidebar.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/shared/css/shop.css') }}?v={{ filemtime(public_path('assets/shared/css/shop.css')) }}">
  <script src="{{ asset('assets/buyer/js/layouts/website.js') }}"></script>
  <script src="{{ asset('assets/shared/js/app-sidebar.js') }}?v={{ filemtime(public_path('assets/shared/js/app-sidebar.js')) }}" defer></script>
  <script src="{{ asset('assets/buyer/js/shop.js') }}?v={{ filemtime(public_path('assets/buyer/js/shop.js')) }}" defer></script>
  <link rel="stylesheet" href="{{ asset('assets/buyer/css/layouts/website.css') }}">
</head>
<body>
  @php
    $hideSidebar = (request()->is('/') || request()->is('products')) && !\App\Support\BuyerSession::current();
  @endphp
  @php
      $buyer = \App\Support\BuyerSession::current();
      $buyerPicturePath = $buyer ? \App\Models\User::whereKey($buyer['id'])->value('profile_picture') : null;
      $buyerAvatar = $buyerPicturePath ? asset('storage/' . ltrim($buyerPicturePath, '/')) : null;
      $cartCount = 0;
      $wishlistCount = 0;
      $unreadMsgs = 0;
      $pendingOrders = 0;
      if ($buyer) {
          $cartCount = \App\Models\Cart::itemsFor($buyer['id'])->sum('quantity');
          $wishlistCount = \App\Models\Favorite::where('buyer_id', $buyer['id'])->count();
          $convIds = \App\Models\Conversation::where('buyer_id', $buyer['id'])->pluck('id');
          $unreadMsgs = \App\Models\Message::whereIn('conversation_id', $convIds)
              ->where('sender_id', '!=', $buyer['id'])->where('is_read', 0)->count();
          $pendingOrders = \App\Models\Order::where('buyer_id', $buyer['id'])
              ->where('status', 'pending')->count();
      }
      $cartBadge = $cartCount > 99 ? '99+' : (string)$cartCount;
      $notifCount = $buyer ? \App\Support\BuyerNotifications::count($buyer['id']) : 0;
    @endphp
@include('shared.navigation.navbar', ['navigationRole' => 'buyer', 'navigationVariant' => 'website'])
  <div class="shell">
    @if(!$hideSidebar)
    <div class="sb-scrim" id="buyerScrim" hidden></div>
    @include('shared.navigation.sidebar', ['navigationRole' => 'buyer', 'navigationVariant' => 'website'])
    @endif
    <div class="main">
      <main class="container" style="padding-top:0;flex:1">
        @yield('content')
      </main>
      <footer class="footer buyerfoot">
        <div class="buyerfoot-mark">INVOIZ</div>
        <div>&copy; 2026 Invoiz Online Shopping. All rights reserved.</div>
      </footer>
    </div>
  </div>
  @stack('scripts')
  <script src="{{ asset('assets/buyer/js/app.js') }}?v={{ filemtime(public_path('assets/buyer/js/app.js')) }}"></script>
  @include('buyer.partials.support-chat')
</body>
</html>
