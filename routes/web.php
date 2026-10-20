<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\UnifiedAuthController;
use App\Http\Controllers\SellerApplicationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\SellerAuthController;
use App\Http\Controllers\SellerCenterController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\SellerController;
use App\Http\Controllers\Admin\ComplaintController;
use App\Http\Controllers\Admin\CommissionController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\ChatController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Seller\AuthController as SellerAppAuth;
use App\Http\Controllers\Seller\ChatController as SellerAppChat;
use App\Http\Controllers\Seller\DashboardController as SellerAppDashboard;
use App\Http\Controllers\Seller\NotificationController as SellerAppNotifications;
use App\Http\Controllers\Seller\OrderController as SellerAppOrders;
use App\Http\Controllers\Seller\ProductController as SellerAppProducts;
use App\Http\Controllers\Seller\ProfileController as SellerAppProfile;
use App\Http\Controllers\Seller\ReportController as SellerAppReports;
use App\Http\Controllers\Seller\ReviewController as SellerAppReviews;
use App\Http\Controllers\Seller\VoucherController as SellerAppVouchers;
use App\Http\Controllers\BuyerChatController;
use App\Http\Controllers\BuyerFeaturesController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\AuthController as BuyerWebAuth;
use App\Models\Product;
use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| INVOIZ Unified â€” single backend for buyer + seller + admin
| DB: invoizdb (shared). Register -> buyer. Login -> role redirect.
|--------------------------------------------------------------------------
*/

// ---- Landing (live marketplace data) ----
Route::get('/', function (Request $request) {
    $deals = [];
    $categories = collect();
    $stores = collect();
    $sort = $request->input('sort', 'recommended');
    $allowedSorts = ['recommended', 'newest', 'price_asc', 'price_desc', 'rating', 'most_reviewed'];
    if (! in_array($sort, $allowedSorts, true)) $sort = 'recommended';
    $buyerId = session('buyer.id') ?? (Auth::check() ? Auth::id() : null);
    $favoriteIds = $buyerId ? \App\Models\Favorite::where('buyer_id', $buyerId)->pluck('product_id')->map(fn ($id) => (int) $id)->all() : [];
    $stats = ['products' => 0, 'sellers' => 0, 'orders' => 0];
    try {
        $dealQuery = Product::with('category')->where('status', 'active')
            ->select('products.*')
            ->selectSub(\App\Models\Review::query()->selectRaw('AVG(rating)')->whereColumn('reviews.product_id', 'products.id')->where('status', 'visible'), 'visible_rating')
            ->selectSub(\App\Models\Review::query()->selectRaw('COUNT(*)')->whereColumn('reviews.product_id', 'products.id')->where('status', 'visible'), 'visible_reviews_count');
        $dealFilter = (clone $dealQuery)
            ->whereNotNull('compare_at_price')
            ->whereColumn('compare_at_price', '>', 'price');
        $hasDeals = (clone $dealFilter)->exists();
        $dealRows = $dealFilter;
        if (! $hasDeals) {
            $dealRows = clone $dealQuery;
        }
        switch ($sort) {
            case 'price_asc': $dealRows->orderBy('price')->orderByDesc('created_at'); break;
            case 'price_desc': $dealRows->orderByDesc('price')->orderByDesc('created_at'); break;
            case 'rating': $dealRows->orderByRaw('COALESCE(visible_rating, rating, 0) DESC')->orderByDesc('visible_reviews_count')->orderByDesc('created_at'); break;
            case 'most_reviewed': $dealRows->orderByDesc('visible_reviews_count')->orderByRaw('COALESCE(visible_rating, rating, 0) DESC')->orderByDesc('created_at'); break;
            default: $dealRows->orderByDesc('created_at');
        }
        $dealRows = $dealRows->take(6)->get();
        $deals = $dealRows->map(function ($p) {
            $pct = ($p->compare_at_price > $p->price && $p->compare_at_price > 0)
                ? (int) round((($p->compare_at_price - $p->price) / $p->compare_at_price) * 100) : 0;
            try { $store = \App\Models\Seller::nameFor(\App\Models\Seller::userIdFor((int) $p->seller_id)); } catch (\Throwable $e) { $store = ''; }
            return [
                'id' => $p->id, 'name' => $p->name,
                'price' => (float) ($p->price ?? 0),
                'compare_at' => (float) ($p->compare_at_price ?? 0),
                'pct' => $pct, 'rating' => ($p->visible_rating ?? $p->rating) ? round((float) ($p->visible_rating ?? $p->rating), 1) : null,
                'store' => $store, 'image' => $p->primary_image_url,
            ];
        })->toArray();
        $categories = \App\Models\Category::active()->withCount(['products' => fn ($q) => $q->where('status', 'active')])->orderByDesc('products_count')->take(10)->get()->map(function ($c) {
            $imgs = Product::where('category_id', $c->id)->where('status', 'active')->orderByDesc('created_at')->take(8)->get()
                ->map(fn ($p) => $p->primary_image_url)->filter()->unique()->take(4)->values()->all();
            $c->cover_images = $imgs;
            return $c;
        });
        $sellers = \App\Models\Seller::approved()->with('user')->get();
        $stores = $sellers->map(function ($s) {
            $uid = $s->user_id;
            $count = Product::where(function ($q) use ($s, $uid) { $q->where('seller_id', $uid)->orWhere('seller_id', $s->id); })->where('status', 'active')->count();
            $logo = null;
            try {
                if ($s->logo && file_exists(storage_path('app/public/' . ltrim($s->logo, '/')))) {
                    $logo = asset('storage/' . ltrim($s->logo, '/'));
                }
            } catch (\Throwable $e) {}
            return ['user_id' => $uid, 'name' => $s->display_name, 'line' => $s->line_of_business ?: 'Local seller', 'products' => $count, 'logo' => $logo];
        })->sortByDesc('products')->take(3)->values();
        $stats = [
            'products' => Product::where('status', 'active')->count(),
            'sellers' => $sellers->count(),
            'orders' => \App\Models\Order::whereNotIn('status', ['cancelled'])->count(),
        ];
    } catch (\Throwable $e) { $deals = []; }
    return view('landing', compact('deals', 'categories', 'stores', 'stats', 'favoriteIds', 'sort'));
})->name('landing');

// ---- Sell on INVOIZ (buyer or new seller applies, admin approves in /admin/sellers) ----
Route::get('/sell', [SellerApplicationController::class, 'create'])->name('sell');

Route::get('/seller/register', fn () => redirect()->route('sell'))->name('seller.register');
Route::get('/seller/apply', fn () => redirect()->route('sell'))->name('seller.apply');

Route::post('/sell', [SellerApplicationController::class, 'store'])->name('sell.post');

// ---- Unified auth (single login/register for everyone) ----
Route::get('/login', [UnifiedAuthController::class, 'showLogin'])->name('login');
Route::post('/login', [UnifiedAuthController::class, 'login'])->name('login.post');
Route::get('/register', [UnifiedAuthController::class, 'showRegister'])->name('register');
Route::post('/register', [UnifiedAuthController::class, 'register'])->name('register.post');
Route::post('/logout', [UnifiedAuthController::class, 'logout'])->name('logout');
Route::get('/logout', [UnifiedAuthController::class, 'logout']);
Route::get('/home', function () {
    if (! Auth::check()) {
        return redirect('/shop');
    }

    $user = Auth::user();
    if ($user->isAdmin()) {
        return redirect('/admin/dashboard');
    }

    if ($user->canSell()) {
        return match (session('active_role')) {
            'seller' => redirect('/seller/dashboard'),
            'buyer' => redirect('/shop'),
            default => redirect('/choose'),
        };
    }

    return redirect('/shop');
});

// Email code verification (Gmail OTP)
Route::get('/verify', [UnifiedAuthController::class, 'showVerify'])->name('verify');
Route::post('/verify', [UnifiedAuthController::class, 'verifyCode'])->name('verify.post');
Route::post('/verify/resend', [UnifiedAuthController::class, 'resendCode'])->name('verify.resend');

// Password reset (one shared flow for buyer, seller, admin and logistics logins)
Route::get('/forgot-password', [\App\Http\Controllers\Auth\PasswordResetController::class, 'showLinkForm'])->name('auth.password.request');
Route::post('/forgot-password', [\App\Http\Controllers\Auth\PasswordResetController::class, 'sendLink'])->name('auth.password.email');
Route::get('/reset-password/{token}', [\App\Http\Controllers\Auth\PasswordResetController::class, 'showResetForm'])->name('auth.password.reset');
Route::post('/reset-password', [\App\Http\Controllers\Auth\PasswordResetController::class, 'reset'])->name('auth.password.update');

// Continue with Gmail (Google OAuth)
Route::get('/auth/google', [UnifiedAuthController::class, 'googleRedirect'])->name('google.redirect');
Route::get('/auth/google/callback', [UnifiedAuthController::class, 'googleCallback'])->name('google.callback');

// Dual buyer+seller logins pick an account here before entering a dashboard.
Route::middleware('auth')->group(function () {
    Route::get('/choose', [UnifiedAuthController::class, 'showChoose'])->name('choose');
    Route::get('/choose/buyer', fn () => redirect()->route('choose'))->name('choose.buyer');
    Route::get('/choose/seller', fn () => redirect()->route('choose'))->name('choose.seller');
    Route::post('/switch-role', [UnifiedAuthController::class, 'switchRole'])->name('role.switch');
});

// (Unified /verify + /auth/google routes above cover all accounts.)

// ---- Buyer shop (common e-commerce flow: Shopee/Lazada style) ----
Route::get('/shop', [HomeController::class, 'index'])->name('shop');
Route::get('/products', [HomeController::class, 'index']);
Route::get('/sale', [HomeController::class, 'sale']);
Route::get('/product/{id}', [HomeController::class, 'show']);
Route::get('/wishlist', [BuyerFeaturesController::class, 'wishlist'])->name('buyer.wishlist');
Route::post('/wishlist/{product}/toggle', [BuyerFeaturesController::class, 'toggleFavorite'])->whereNumber('product')->name('buyer.wishlist.toggle');
Route::get('/vouchers', [BuyerFeaturesController::class, 'vouchers'])->name('buyer.vouchers');
Route::post('/vouchers/{voucher}/claim', [BuyerFeaturesController::class, 'claimVoucher'])->whereNumber('voucher')->name('buyer.vouchers.claim');
Route::post('/orders/{order}/rate/{product}', [BuyerFeaturesController::class, 'rate'])->whereNumber('order')->whereNumber('product')->name('buyer.orders.rate');

Route::get('/cart/add/{id}', function($id, Request $request){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id(),'first_name'=>Auth::user()->first_name ?? '','last_name'=>Auth::user()->last_name ?? ''] : null);
  if(!$buyer) return redirect('/login')->with('error','Please login to add items to cart');
  $product = Product::find($id);
  if(!$product) return redirect('/cart')->with('error','Product not found');
  if($product->stock < 1) return redirect('/cart')->with('error','Product is out of stock');
  $qty = max(1, min(200, $request->integer('qty', 1)));
  $qty = min($qty, (int)$product->stock);
  $variant = Cart::resolveVariant($product, $request->input('variant_id'));
  if($variant && $variant->stock < 1) return redirect('/cart')->with('error','Selected variation is out of stock');
  if($variant) $qty = min($qty, (int)$variant->stock);
  $cart = Cart::headerFor($buyer['id']);
  $currentQty = CartItem::where('cart_id',$cart->id)->sum('quantity');
  if($currentQty + $qty > 200) return redirect('/cart')->with('error','Cart limit is 200 items');
  $line = CartItem::where('cart_id',$cart->id)->where('product_id',$id)->where('variant_id', $variant ? $variant->id : null)->first();
  // Never exceed available stock on lines already in the cart.
  $available = $variant ? (int) $variant->stock : (int) $product->stock;
  if($line){
    if($line->quantity >= $available) return redirect('/cart')->with('error','Only '.$available.' available for this item.');
    $line->update(['quantity' => min($line->quantity + $qty, $available)]);
  }
  else { CartItem::create(['cart_id'=>$cart->id,'product_id'=>$id,'variant_id'=>$variant ? $variant->id : null,'quantity'=>$qty]); }
  $product->trackCartAddition();
  return redirect('/cart')->with('success','Added to cart');
});

Route::get('/buy/{id}', function($id, Request $request){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return redirect('/login')->with('error','Please login to buy');
  $product = Product::find($id);
  if(!$product) return redirect('/shop')->with('error','Product not found');
  if($product->stock < 1) return back()->with('error','Product is out of stock');
  $qty = max(1, min(200, $request->integer('qty', 1)));
  $qty = min($qty, (int)$product->stock);
  $variant = Cart::resolveVariant($product, $request->input('variant_id'));
  if($variant && (int)$variant->stock > 0) $qty = min($qty, (int)$variant->stock);
  session(['checkout_single'=>['id'=>(int)$id,'qty'=>$qty,'variant_id'=>$variant ? $variant->id : null]]);
  session()->forget(['checkout_seller_id','checkout_all','checkout_selected']);
  return redirect('/checkout?single='.$id);
})->whereNumber('id');

Route::get('/cart/inc/{item}', function($item){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return redirect('/login');
  $line = Cart::itemsFor($buyer['id'])->where('id',$item)->first();
  if(!$line) return redirect('/cart')->with('error','Item not found in cart.');
  $line->loadMissing(['product','variant']);
  $product = $line->product;
  if(!$product) { $line->delete(); return redirect('/cart'); }
  $variant = $line->variant;
  if($variant && (int)$variant->stock < 1) return redirect('/cart')->with('error','This variation is out of stock.');
  if(!$variant && (int)$product->stock < 1) return redirect('/cart')->with('error','This product is out of stock.');
  $available = $variant ? (int) $variant->stock : (int) $product->stock;
  if($line->quantity >= $available) return redirect('/cart')->with('error','Only '.$available.' available for this item.');
  $line->increment('quantity');
  return redirect('/cart')->with('success','Added to cart');
});

Route::get('/cart/dec/{item}', function($item){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return redirect('/login');
  // Accept cart-item id (normal) OR product id (cart view buttons).
  $line = Cart::itemsFor($buyer['id'])->where('id',$item)->first()
    ?? Cart::itemsFor($buyer['id'])->where('product_id',$item)->first();
  if($line){ if($line->quantity <= 1) $line->delete(); else $line->decrement('quantity'); }
  return redirect('/cart');
});

Route::get('/cart/remove/{item}', function($item){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return redirect('/login');
  $q = Cart::itemsFor($buyer['id']);
  $deleted = (clone $q)->where('id',$item)->delete();
  if(! $deleted) $q->where('product_id',$item)->delete();
  return redirect('/cart');
});

// Buy-now helpers used by the cart view (were 404 before).
Route::get('/buy/seller/{sellerId}', function($sellerId){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return redirect('/login')->with('error','Please login to buy');
  $lines = Cart::linesFor($buyer['id'])->filter(fn($l) => (int)$l['product']->seller_id === (int)$sellerId);
  if($lines->isEmpty()) return redirect('/cart')->with('error','No items from this seller in cart');
  session(['checkout_seller_id' => (int)$sellerId]);
  session()->forget(['checkout_single','checkout_all','checkout_selected']);
  return redirect('/checkout?seller='.(int)$sellerId);
});

Route::get('/buy/all', function(){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return redirect('/login')->with('error','Please login');
  if(Cart::itemsFor($buyer['id'])->count() === 0) return redirect('/cart')->with('error','Cart is empty');
  session(['checkout_all'=>true]);
  session()->forget(['checkout_single','checkout_seller_id','checkout_selected']);
  return redirect('/checkout?all=1');
});

Route::get('/buy/selected', function(Request $request){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return redirect('/login')->with('error','Please login');
  $ids = $request->input('ids');
  if(!$ids) return redirect('/cart')->with('error','No items selected');
  $idArr = array_map('intval', explode(',', $ids));
  // Cart view sends product ids; also accept cart-item ids.
  $lines = Cart::linesFor($buyer['id'])->filter(fn($l) => in_array($l['item']->id, $idArr) || in_array($l['product']->id, $idArr));
  if($lines->isEmpty()) return redirect('/cart')->with('error','Selected items not found in cart');
  session(['checkout_selected' => $idArr]);
  session()->forget(['checkout_single','checkout_seller_id','checkout_all']);
  return redirect('/checkout?selected=1');
});

Route::get('/cart', function(){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return redirect('/login')->with('error','Please login to view cart');
  $cartItems = Cart::itemsFor($buyer['id'])->get();
  $productIds = $cartItems->pluck('product_id')->toArray();
  $products = Product::whereIn('id', $productIds)->get();
  return view('cart', ['products'=>$products, 'cartItems'=>$cartItems]);
})->name('cart');

Route::get('/checkout', function(Request $request){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return redirect('/login')->with('error','Please login to checkout');
  $claimedVouchers = \App\Models\VoucherClaim::with('voucher')
    ->where('buyer_id', $buyer['id'])->whereNull('used_at')
    ->whereHas('voucher', fn ($q) => $q->active()->where(fn ($x) => $x->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit')))
    ->latest('claimed_at')->get();
  \App\Models\Address::ensureForBuyer((int) $buyer['id']);
  $addresses = \App\Models\Address::where('buyer_id', $buyer['id'])->orderByDesc('is_default')->orderByDesc('updated_at')->get();
  $selectedAddressId = old('address_id', $request->input('address_id', $addresses->firstWhere('is_default', true)?->id));
  if($request->filled('single') || session()->has('checkout_single')){
    $raw = $request->filled('single') ? ['id'=>$request->integer('single'),'qty'=>$request->integer('qty',1),'variant_id'=>$request->input('variant_id')] : session('checkout_single');
    if(!is_array($raw)) $raw = ['id'=>(int)$raw,'qty'=>1,'variant_id'=>null];
    $p = Product::find($raw['id'] ?? 0);
    if(!$p) return redirect('/cart')->with('error','Product not found');
    $variant = Cart::resolveVariant($p, $raw['variant_id'] ?? null);
    $qty = max(1, min(200, (int)($raw['qty'] ?? 1)));
    $unit = Cart::unitFor($p, $variant);
    return view('checkout',['products'=>collect([$p]),'single'=>$p->id,'singleQty'=>$qty,'singleUnit'=>$unit,'singleVariantId'=>$variant ? $variant->id : null,'singleLabel'=>Cart::labelFor($variant),'cartItems'=>collect(),'checkoutMode'=>'single','addresses'=>$addresses,'selectedAddressId'=>$selectedAddressId,'claimedVouchers'=>$claimedVouchers]);
  }
  if($request->filled('seller') || session()->has('checkout_seller_id')){
    $sellerId = $request->input('seller') ?? session('checkout_seller_id');
    $lines = Cart::linesFor($buyer['id'])->filter(fn($l) => (int)$l['product']->seller_id === (int)$sellerId)->values();
    if($lines->isEmpty()) return redirect('/cart')->with('error','No items from this seller');
    $cartItems = Cart::itemsFor($buyer['id'])->get();
    $pIds = $lines->pluck('product.id')->all();
    $products = Product::whereIn('id',$pIds)->get();
    return view('checkout',['products'=>$products,'cartItems'=>$cartItems,'single'=>null,'checkoutMode'=>'seller','sellerId'=>$sellerId,'sellerLines'=>$lines,'addresses'=>$addresses,'selectedAddressId'=>$selectedAddressId,'claimedVouchers'=>$claimedVouchers]);
  }
  if($request->filled('all') || session()->has('checkout_all')){
    $lines = Cart::linesFor($buyer['id']);
    if($lines->isEmpty()) return redirect('/cart')->with('error','Cart is empty');
    $bySeller = $lines->groupBy(fn($l) => $l['product']->seller_id);
    $sellerGroups = [];
    foreach($bySeller as $sid => $sLines){ $sellerGroups[$sid] = ['lines'=>$sLines->values(),'total'=>$sLines->sum('line')]; }
    $grandTotal = collect($sellerGroups)->sum('total');
    $products = $lines->pluck('product');
    $cartItems = Cart::itemsFor($buyer['id'])->get();
    return view('checkout',['products'=>$products,'cartItems'=>$cartItems,'single'=>null,'checkoutMode'=>'all','sellerGroups'=>$sellerGroups,'grandTotal'=>$grandTotal,'addresses'=>$addresses,'selectedAddressId'=>$selectedAddressId,'claimedVouchers'=>$claimedVouchers]);
  }
  if($request->filled('selected') || session()->has('checkout_selected')){
    $idArr = session('checkout_selected', array_map('intval', explode(',', $request->input('ids',''))));
    $lines = Cart::linesFor($buyer['id'])->filter(fn($l) => in_array($l['item']->id, $idArr) || in_array($l['product']->id, $idArr));
    if($lines->isEmpty()) return redirect('/cart')->with('error','Selected items not found in cart');
    $bySeller = $lines->groupBy(fn($l) => $l['product']->seller_id);
    $sellerGroups = [];
    foreach($bySeller as $sid => $sLines){ $sellerGroups[$sid] = ['lines'=>$sLines->values(),'total'=>$sLines->sum('line')]; }
    $grandTotal = collect($sellerGroups)->sum('total');
    $products = $lines->pluck('product');
    $cartItems = Cart::itemsFor($buyer['id'])->get();
    return view('checkout',['products'=>$products,'cartItems'=>$cartItems,'single'=>null,'checkoutMode'=>'selected','sellerGroups'=>$sellerGroups,'grandTotal'=>$grandTotal,'addresses'=>$addresses,'selectedAddressId'=>$selectedAddressId,'claimedVouchers'=>$claimedVouchers]);
  }
  return redirect('/cart');
});

Route::post('/checkout', function(Request $request){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id(),'first_name'=>Auth::user()->first_name ?? '','last_name'=>Auth::user()->last_name ?? ''] : null);
  if(!$buyer) return redirect('/login')->with('error','Please login');
  $request->validate([
    'address_id' => ['required', 'integer'],
    'payment_method' => ['required', 'in:cod'],
    'voucher_claim_id' => ['nullable', 'integer', 'exists:voucher_claims,id'],
    'voucher_claim_ids' => ['nullable', 'array', 'max:3'],
    'voucher_claim_ids.*' => ['integer', 'exists:voucher_claims,id'],
  ]);
  $addr = \App\Models\Address::where('buyer_id', $buyer['id'])->find($request->integer('address_id'));
  if (! $addr) {
    return back()->withErrors(['address_id' => 'Select one of your saved delivery addresses before placing the order.'])->withInput();
  }
  $checkoutMode = $request->input('checkout_mode', session('checkout_single') ? 'single' : (session('checkout_all') ? 'all' : (session('checkout_selected') ? 'selected' : 'seller')));
  $ordersCreated = 0;
  try {
    \Illuminate\Support\Facades\DB::transaction(function () use ($buyer, $addr, $request, $checkoutMode, &$ordersCreated) {
    $claimIds = array_values(array_unique(array_filter(array_merge(
      array_map('intval', (array) $request->input('voucher_claim_ids', [])),
      $request->filled('voucher_claim_id') ? [(int) $request->integer('voucher_claim_id')] : []
    ))));
    $claims = collect();
    $claimsByVoucher = collect();
    $allVouchers = collect();
    if ($claimIds) {
      $claims = \App\Models\VoucherClaim::whereIn('id', $claimIds)->where('buyer_id', $buyer['id'])
        ->whereNull('used_at')->lockForUpdate()->get()->keyBy('id');
      if ($claims->count() !== count($claimIds)) {
        throw new \Exception('One of the claimed vouchers is unavailable. Choose another voucher.');
      }
      $allVouchers = $claims->map(fn($c) => \App\Models\Voucher::whereKey($c->voucher_id)->lockForUpdate()->first());
      foreach ($allVouchers as $v) {
        if (! $v || ! $v->is_active || ($v->usage_limit !== null && $v->used_count >= $v->usage_limit)) {
          throw new \Exception('One of the claimed vouchers is unavailable. Choose another voucher.');
        }
      }
      $claimsByVoucher = $claims->keyBy('voucher_id');
    }
    if($checkoutMode === 'single'){
      $raw = session('checkout_single');
      if(!is_array($raw)) $raw = ['id'=>$request->input('single'),'qty'=>$request->input('qty',1),'variant_id'=>$request->input('variant_id')];
      $product = Product::find((int)($raw['id'] ?? 0));
      if(!$product) throw new \Exception('Product not found');
      $variant = Cart::resolveVariant($product, $raw['variant_id'] ?? null);
      $qty = max(1, min(200, (int)($raw['qty'] ?? 1)));
      if (\App\Models\Seller::where(function($q) use($product){ $q->where('id',$product->seller_id)->orWhere('user_id',$product->seller_id); })->where('vacation_mode',true)->exists()) {
        throw new \Exception('This store is on vacation and is not accepting orders right now.');
      }
      $unit = Cart::unitFor($product, $variant);
      $subtotal = $unit * $qty;
      // Shipping: flat fee once per shop. Voucher (merchandise or free
      // shipping) validated here; the seller absorbs it, never the rider.
      $merch = 0.0; $waived = 0.0; $stack = ['perVoucher' => []];
      if ($allVouchers->isNotEmpty()) {
        $stack = \App\Support\Vouchers::resolveStack($allVouchers, $subtotal, [(int) $product->seller_id]);
        if ($stack['error']) throw new \Exception($stack['error']);
        $merch = $stack['merch'];
        $waived = round(array_sum($stack['waived']), 2);
      }
      $shipping = \App\Models\Order::SHIPPING_FEE_PER_SELLER;
      $total = round($subtotal - $merch + $shipping - $waived, 2);
      if(! \App\Support\Stock::deduct($product->id, $variant?->id, $qty)) throw new \Exception('Not enough stock for "'.$product->name.'".');
      $order = \App\Models\Order::create(['buyer_id'=>$buyer['id'],'address_id'=>$addr->id,'shipping_address'=>$addr->toShippingSnapshot(),'total_amount'=>$total,'total'=>$total,'sub_total'=>$subtotal,'discount_total'=>round($merch + $waived, 2),'shipping_fee'=>round($shipping - $waived, 2),'status'=>'pending']);
      \App\Models\OrderStatusHistory::create(['order_id'=>$order->id,'from_status'=>null,'to_status'=>'pending','note'=>'Order placed.']);
      if (class_exists(\App\Models\Delivery::class)) \App\Models\Delivery::create(['order_id'=>$order->id,'seller_id'=>(int) $product->seller_id,'status'=>'waiting_for_rider','delivery_fee'=>\App\Models\Order::SHIPPING_FEE_PER_SELLER]);
      \App\Models\OrderItem::create(['order_id'=>$order->id,'product_id'=>$product->id,'product_variant_id'=>$variant?->id,'seller_id'=>$product->seller_id,'product_name'=>$product->name,'variant_label'=>Cart::labelFor($variant),'quantity'=>$qty,'price'=>$unit,'unit_price'=>$unit,'total_price'=>$subtotal]);
      foreach ($stack['perVoucher'] ?? [] as $vid => $amt) {
        \App\Models\OrderVoucher::create(['order_id'=>$order->id,'voucher_id'=>$vid,'discount_amount'=>round(($amt['merch'] ?? 0) + ($amt['waived'] ?? 0), 2)]);
      }
      foreach ($claims as $c) {
        \App\Models\Voucher::whereKey($c->voucher_id)->increment('used_count');
        $c->update(['order_id' => $order->id, 'used_at' => now()]);
      }
      if (class_exists(\App\Models\Payment::class)) \App\Models\Payment::create(['order_id'=>$order->id,'method'=>'cash_on_delivery','status'=>'pending','amount'=>$total]);
      \App\Support\Payments::recordCheckout($order);
      Cart::itemsFor($buyer['id'])->where('product_id',$product->id)->where('variant_id', $variant ? $variant->id : null)->delete();
      $ordersCreated = 1;
    } else {
      $lines = Cart::linesFor($buyer['id']);
      if($checkoutMode === 'seller') {
        $lines = $lines->filter(fn($l) => (int)$l['product']->seller_id === (int)(session('checkout_seller_id') ?? $request->input('seller_id')))->values();
      } elseif($checkoutMode === 'selected') {
        $idArr = session('checkout_selected', []);
        $lines = $lines->filter(fn($l) => in_array($l['item']->id, $idArr) || in_array($l['product']->id, $idArr))->values();
      }
      if($lines->isEmpty()) throw new \Exception('Cart is empty');
      $vacationIds = $lines->map(fn($l) => (int) $l['product']->seller_id)->unique()->values()->all();
      $vacationSeller = \App\Models\Seller::where(function($q) use($vacationIds){ $q->whereIn('id',$vacationIds)->orWhereIn('user_id',$vacationIds); })->where('vacation_mode',true)->first();
      if ($vacationSeller) throw new \Exception(($vacationSeller->store_name ?: 'A store').' is on vacation and is not accepting orders right now.');
      // Vouchers stack per checkout: one store + one platform + one shipping.
      $consumedClaimIds = [];
      $groupIndex = 0;
      $bySeller = in_array($checkoutMode, ['all','selected']) ? $lines->groupBy(fn($l) => $l['product']->seller_id) : [(int) $lines->first()['product']->seller_id => $lines];
      foreach($bySeller as $groupSid => $sLines){
        foreach($sLines as $l){
          if(! \App\Support\Stock::deduct($l['product']->id, $l['variant']?->id, $l['qty'])) throw new \Exception('Not enough stock for "'.$l['product']->name.'".');
        }
        $subtotal = $sLines->sum('line');
        $sellerIds = $sLines->map(fn($l) => (int) $l['product']->seller_id)->unique()->values()->all();
        $isFirstGroup = ($groupIndex === 0);
        $groupVouchers = $allVouchers->filter(function($v) use ($groupSid, $isFirstGroup, $consumedClaimIds){
          if (isset($consumedClaimIds[$v->id])) return false;
          $type = $v->discount_type ?? $v->type;
          if ($type === 'free_shipping') return $isFirstGroup;
          if ($v->seller_id) return \App\Models\Seller::userIdFor((int)$v->seller_id) === \App\Models\Seller::userIdFor((int)$groupSid);
          return $isFirstGroup;
        })->values();
        $merch = 0.0; $waived = 0.0; $stack = ['perVoucher' => []];
        if ($groupVouchers->isNotEmpty()) {
          $stack = \App\Support\Vouchers::resolveStack($groupVouchers, $subtotal, $sellerIds);
          if ($stack['error']) throw new \Exception($stack['error']);
          $merch = $stack['merch'];
          $waived = round(array_sum($stack['waived']), 2);
        }
        $shipping = \App\Models\Order::SHIPPING_FEE_PER_SELLER;
        $total = round($subtotal - $merch + $shipping - $waived, 2);
        $order = \App\Models\Order::create(['buyer_id'=>$buyer['id'],'address_id'=>$addr->id,'shipping_address'=>$addr->toShippingSnapshot(),'total_amount'=>$total,'total'=>$total,'sub_total'=>$subtotal,'discount_total'=>round($merch + $waived, 2),'shipping_fee'=>round($shipping - $waived, 2),'status'=>'pending']);
        \App\Models\OrderStatusHistory::create(['order_id'=>$order->id,'from_status'=>null,'to_status'=>'pending','note'=>'Order placed.']);
        if (class_exists(\App\Models\Delivery::class)) \App\Models\Delivery::create(['order_id'=>$order->id,'seller_id'=>(int) $groupSid,'status'=>'waiting_for_rider','delivery_fee'=>\App\Models\Order::SHIPPING_FEE_PER_SELLER]);
        foreach($sLines as $l){
          \App\Models\OrderItem::create(['order_id'=>$order->id,'product_id'=>$l['product']->id,'product_variant_id'=>$l['variant']?->id,'seller_id'=>$l['product']->seller_id,'product_name'=>$l['product']->name,'variant_label'=>$l['label'],'quantity'=>$l['qty'],'price'=>$l['unit'],'unit_price'=>$l['unit'],'total_price'=>$l['line']]);
          $l['item']->delete();
        }
        foreach ($stack['perVoucher'] ?? [] as $vid => $amt) {
          \App\Models\OrderVoucher::create(['order_id'=>$order->id,'voucher_id'=>$vid,'discount_amount'=>round(($amt['merch'] ?? 0) + ($amt['waived'] ?? 0), 2)]);
          \App\Models\Voucher::whereKey($vid)->increment('used_count');
          $consumedClaimIds[$vid] = true;
          if (isset($claimsByVoucher[$vid])) $claimsByVoucher[$vid]->update(['order_id' => $order->id, 'used_at' => now()]);
        }
        $groupIndex++;
        if (class_exists(\App\Models\Payment::class)) \App\Models\Payment::create(['order_id'=>$order->id,'method'=>'cash_on_delivery','status'=>'pending','amount'=>$total]);
        \App\Support\Payments::recordCheckout($order);
        $ordersCreated++;
      }
      if ($allVouchers->isNotEmpty()) {
        foreach ($allVouchers as $v) {
          if (! isset($consumedClaimIds[$v->id])) {
            throw new \Exception('A selected voucher does not apply to the items in your order.');
          }
        }
      }
    }
    });
  } catch(\Throwable $e){ \Log::error('Checkout failed: '.$e->getMessage()); return back()->with('error','Checkout failed: '.$e->getMessage()); }
  session()->forget(['checkout_single','checkout_seller_id','checkout_all','checkout_selected']);
  return redirect('/orders')->with('success', $ordersCreated > 1 ? "Placed {$ordersCreated} orders â€” Cash on Delivery â€” thank you!" : 'Order placed â€” Cash on Delivery â€” thank you!');
});

Route::get('/store/{id}', function($id, Request $request){
  $sellerId = \App\Models\Seller::userIdFor((int) $id);
  $sellerUser = \App\Models\User::find($sellerId);
  // Accept sellers.id too (admin/seller links) â€” resolve to the same store.
  if(! $sellerUser) {
    $s = \App\Models\Seller::find($sellerId);
    if($s) { $sellerId = (int) $s->user_id; $sellerUser = \App\Models\User::find($sellerId); }
  }
  if(!$sellerUser || ! \App\Models\Seller::where('user_id', $sellerId)->exists()) return redirect('/shop')->with('error','Store not found');
  $name = \App\Models\Seller::nameFor($sellerId);
  $catalogIds = [$sellerId];
  $pq = Product::with('category')->whereIn('seller_id', $catalogIds)->where('status','active');
  // Sellers rows may use sellers.id convention â€” include those products too.
  try {
    $alt = \App\Models\Seller::where('user_id',$sellerId)->first();
    if($alt) { $catalogIds[] = (int) $alt->id; $pq = Product::with('category')->whereIn('seller_id', $catalogIds)->where('status','active'); }
  } catch (\Throwable $e) {}
  $pq->select('products.*')
    ->selectSub(\App\Models\Review::query()->selectRaw('AVG(rating)')->whereColumn('reviews.product_id','products.id')->where('status','visible'),'visible_rating')
    ->selectSub(\App\Models\Review::query()->selectRaw('COUNT(*)')->whereColumn('reviews.product_id','products.id')->where('status','visible'),'visible_reviews_count');
  if($request->filled('search')) $pq->where('name','like','%'.$request->input('search').'%');
  $sort = $request->input('sort','newest');
  if(!in_array($sort,['newest','price_asc','price_desc','rating','most_reviewed'],true)) $sort='newest';
  if($sort==='price_asc') $pq->orderBy('price','asc');
  elseif($sort==='price_desc') $pq->orderBy('price','desc');
  elseif($sort==='rating') $pq->orderByRaw('COALESCE(visible_rating, rating, 0) DESC')->orderByDesc('visible_reviews_count');
  elseif($sort==='most_reviewed') $pq->orderByDesc('visible_reviews_count')->orderByRaw('COALESCE(visible_rating, rating, 0) DESC');
  else $pq->orderByDesc('created_at');
  $products = $pq->get();
  $productIds = Product::whereIn('seller_id', $catalogIds)->pluck('id');
  $reviews = collect();
  $rating = null;
  $followers = 0;
  $following = false;
  try {
    if (class_exists(\App\Models\Review::class)) {
      $reviews = \App\Models\Review::with('buyer')->whereIn('product_id',$productIds)->where('status','visible')->orderByDesc('created_at')->limit(20)->get();
      $rating = \App\Models\Review::whereIn('product_id',$productIds)->where('status','visible')->avg('rating');
      if ($rating === null) $rating = Product::whereIn('id',$productIds)->avg('rating');
    }
    if (class_exists(\App\Models\StoreFollow::class)) {
      $followers = \App\Models\StoreFollow::where('seller_id',$sellerId)->count();
      $b = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
      if($b) $following = \App\Models\StoreFollow::where('buyer_id',$b['id'])->where('seller_id',$sellerId)->exists();
      $buyer = $b;
    } else { $buyer = session('buyer'); }
  } catch (\Throwable $e) { $buyer = session('buyer'); }
  $tab = $request->input('tab')==='reviews' ? 'reviews' : 'products';
  $storeLogo = null;
  try {
    $srow = \App\Models\Seller::where('user_id', $sellerId)->first();
    if ($srow && $srow->logo) {
      $storeLogo = asset('storage/' . ltrim($srow->logo, '/'));
    }
  } catch (\Throwable $e) {}
  $favoriteIds = !empty($buyer['id']) ? \App\Models\Favorite::where('buyer_id',$buyer['id'])->pluck('product_id')->map(fn($id)=>(int)$id)->all() : [];
  $vacation = null;
  try {
    $vs = \App\Models\Seller::where('user_id',$sellerId)->first();
    if ($vs && $vs->vacation_mode) $vacation = $vs->vacation_message ?: 'This store is currently on vacation — orders are paused.';
  } catch (\Throwable $e) {}
  return view('store', compact('sellerId','sellerUser','name','products','reviews','rating','followers','following','tab','buyer','storeLogo','favoriteIds','vacation') + ['storeSearch'=>$request->input('search',''),'storeSort'=>$sort]);
});

Route::post('/store/{id}/follow', function($id, Request $request){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return redirect('/login')->with('error','Please login to follow stores');
  if (! class_exists(\App\Models\StoreFollow::class)) return back()->with('error','Follows are unavailable.');
  $sellerId = (int)$id;
  $ex = \App\Models\StoreFollow::where('buyer_id',$buyer['id'])->where('seller_id',$sellerId)->first();
  if($ex){ $ex->delete(); $msg='Store unfollowed'; } else { \App\Models\StoreFollow::create(['buyer_id'=>$buyer['id'],'seller_id'=>$sellerId]); $msg='Store followed'; }
  return back()->with('success',$msg);
});

Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
Route::post('/profile', [ProfileController::class, 'update'])->name('buyer.profile.update');
Route::post('/profile/addresses', [ProfileController::class, 'storeAddress'])->name('buyer.addresses.store');
Route::put('/profile/addresses/{addressId}', [ProfileController::class, 'updateAddress'])->whereNumber('addressId')->name('buyer.addresses.update');
Route::post('/profile/addresses/{addressId}/default', [ProfileController::class, 'setDefaultAddress'])->whereNumber('addressId')->name('buyer.addresses.default');
Route::delete('/profile/addresses/{addressId}', [ProfileController::class, 'deleteAddress'])->whereNumber('addressId')->name('buyer.addresses.delete');
Route::post('/profile/email/request-code', [ProfileController::class, 'requestEmailCode'])
  ->middleware('throttle:5,1')->name('profile.email.request-code');
Route::post('/profile/email/confirm', [ProfileController::class, 'confirmEmailChange'])
  ->middleware('throttle:10,1')->name('profile.email.confirm');
Route::get('/orders', function(Request $request){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return redirect('/login')->with('error','Please login to view orders');
  $tab = $request->input('tab','all');
  $groups = ['pending'=>['pending','confirmed','processing','ready_for_delivery'],'out_for_delivery'=>['out_for_delivery','shipped'],'delivered'=>['delivered'],'cancelled'=>['cancelled']];
  $q = \App\Models\Order::with('items.product')->where('buyer_id',$buyer['id']);
  if($tab!=='all' && isset($groups[$tab])) $q->whereIn('status',$groups[$tab]);
  if($request->filled('q')){
    $needle = '%'.$request->input('q').'%';
    $q->whereHas('items', fn($iq) => $iq->where('product_name','like',$needle));
  }
  $orders = $q->latest()->paginate(10);
  return view('orders',['orders'=>$orders,'activeTab'=>$tab,'q'=>$request->input('q','')]);
})->name('orders');

Route::get('/orders/{id}', function($id, Request $request){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return redirect('/login')->with('error','Please login');
  $order = \App\Models\Order::with(['items.product','payment','delivery','statusHistories'])->where('buyer_id',$buyer['id'])->findOrFail($id);
  $reviews = \App\Models\Review::where('buyer_id',$buyer['id'])->where('order_id',$order->id)->get()->keyBy('product_id');
  return view('order', ['order'=>$order, 'reviews'=>$reviews]);
})->whereNumber('id');

Route::post('/orders/{id}/cancel', function($id, Request $request){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return redirect('/login')->with('error','Please login');
  $data = $request->validate([
    'reason' => ['required', 'in:changed_mind,ordered_by_mistake,found_better_price,delivery_too_long,wrong_item,other'],
    'reason_details' => ['nullable', 'required_if:reason,other', 'string', 'max:400'],
  ]);
  $order = \App\Models\Order::with('items')->where('buyer_id',$buyer['id'])->findOrFail($id);
  if(! $order->isCancellableByBuyer()) return back()->with('error','This order can no longer be cancelled â€” it is already being prepared or on its way to you.');
  $from = $order->status;
  $labels = ['changed_mind'=>'Changed my mind','ordered_by_mistake'=>'Ordered by mistake','found_better_price'=>'Found a better price','delivery_too_long'=>'Delivery is taking too long','wrong_item'=>'I ordered the wrong item','other'=>'Other'];
  $reason = $labels[$data['reason']] . ($data['reason'] === 'other' ? ': ' . trim($data['reason_details']) : '');
  $order->update(['status'=>'cancelled', 'cancellation_reason' => $reason]);
  \App\Models\OrderStatusHistory::create(['order_id'=>$order->id,'from_status'=>$from,'to_status'=>'cancelled','note'=>'Cancelled by buyer: '.$reason]);
  // Never shipped (pending/confirmed only reach here) â†’ give the stock back.
  \App\Support\Stock::restoreOrder($order->fresh('items'));
  // COD money never moved: void the pending ledger row.
  \App\Support\Payments::voidForCancel($order);
  return redirect('/orders/'.$order->id)->with('success','Order cancelled â€” stock restored and seller/admin see the same status.');
})->whereNumber('id');

// ---- Buyer â†” seller chat (same threads the seller and admin see) ----
Route::get('/messages', function(Request $request){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return redirect('/login')->with('error','Please login');
  $userId = $buyer['id'];
  \App\Support\ChatBridge::linkExistingForBuyer((int) $userId);
  $conversations = \App\Models\Conversation::with('lastMessage.conversation')
    ->where('buyer_id',$userId)->latest('updated_at')->get()
    ->filter(fn($c) => $c->lastMessage)
    ->map(function($c) use ($userId){
      $unread = \App\Models\Message::where('conversation_id',$c->id)->where('sender_id','!=',$userId)->where('is_read',0)->count();
      $last = $c->lastMessage;
      try { [$lastLabel] = \App\Models\Message::roleBadge($last->senderRole()); }
      catch (\Throwable $e) { $lastLabel = 'Seller'; }
      try { $contactRole = \App\Models\User::chatRoleFor(\App\Models\User::find($c->seller_id)); }
      catch (\Throwable $e) { $contactRole = 'seller'; }
      return ['other_id'=>$c->seller_id,'last'=>$last,'unread'=>$unread,'last_mine'=>(int)$last->sender_id===(int)$userId,'last_label'=>$lastLabel,'contact_role'=>$contactRole];
    })->values();
  $selId = (int)$request->input('seller', 0);
  $selConv = null; $selMessages = collect(); $selName = '';
  if($selId){
    $selConv = \App\Models\Conversation::firstOrCreate(['buyer_id'=>$userId,'seller_id'=>$selId], ['subject'=>null]);
    \App\Models\Message::where('conversation_id',$selConv->id)->where('sender_id','!=',$userId)->where('is_read',0)->update(['is_read'=>1]);
    $selMessages = \App\Models\Message::with('conversation')->where('conversation_id',$selConv->id)->orderBy('created_at')->get();
    $selName = \App\Models\Seller::nameFor($selId);
  }
  return view('messages',['conversations'=>$conversations,'selId'=>$selId,'selMessages'=>$selMessages,'selName'=>$selName]);
});

Route::get('/chat/seller/{sellerId}', function(Request $request, $sellerId){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return redirect('/login')->with('error','Please login');
  $sellerId = \App\Models\Seller::userIdFor((int) $sellerId);
  $conv = \App\Models\Conversation::firstOrCreate(['buyer_id'=>$buyer['id'],'seller_id'=>$sellerId], ['subject'=>null]);
  \App\Models\Message::where('conversation_id',$conv->id)->where('sender_id','!=',$buyer['id'])->where('is_read',0)->update(['is_read'=>1]);
  $messages = \App\Models\Message::with('conversation')->where('conversation_id',$conv->id)->orderBy('created_at')->get();
  return view('chat',['sellerId'=>(int)$sellerId,'messages'=>$messages]);
});

Route::post('/chat/send/{sellerId}', function(Request $request, $sellerId){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return response()->json(['error'=>'Login required'],401);
  $sellerId = \App\Models\Seller::userIdFor((int) $sellerId);
  $request->validate(['body'=>'required|string|max:2000']);
  $conv = \App\Models\Conversation::firstOrCreate(['buyer_id'=>$buyer['id'],'seller_id'=>$sellerId], ['subject'=>null]);
  $message = \App\Models\Message::create(['conversation_id'=>$conv->id,'sender_id'=>$buyer['id'],'receiver_id'=>(int)$sellerId,'body'=>$request->input('body'),'is_read'=>0]);
  $conv->touch();
  return response()->json(['ok'=>true,'id'=>$message->id,'created_at'=>$message->created_at?->toDateTimeString()]);
});

Route::get('/chat/fetch/{sellerId}', function(Request $request, $sellerId){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return response()->json(['error'=>'Login required'],401);
  $conv = \App\Models\Conversation::where('buyer_id',$buyer['id'])->where('seller_id',$sellerId)->first();
  if(!$conv) return response()->json([]);
  $q = \App\Models\Message::where('conversation_id',$conv->id);
  if($request->filled('after_id')) $q->where('id','>',$request->integer('after_id'));
  elseif($request->filled('after')) $q->where('created_at','>',$request->input('after'));
  return response()->json($q->with('conversation')->orderBy('created_at')->get()->map(function($m) use($buyer){
    try { $role = $m->senderRole(); [$label] = \App\Models\Message::roleBadge($role); }
    catch (\Throwable $e) { $role = 'seller'; $label = 'Seller'; }
    return ['id'=>$m->id,'body'=>$m->body,'is_me'=>$m->sender_id==$buyer['id'],'sender_role'=>$role,'sender_label'=>$label,'created_at'=>$m->created_at->toDateTimeString()];
  }));
});

Route::get('/notifications', function(Request $request){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return redirect('/login')->with('error','Please login');
  \App\Support\ChatBridge::linkExistingForBuyer((int) $buyer['id']);
  if (! class_exists(\App\Support\BuyerNotifications::class)) return view('notifications',['items'=>[]]);
  // Merged feed (orders + messages + resolutions): slice the current page
  // manually so the full feed stays pageable.
  $feed = \App\Support\BuyerNotifications::for($buyer['id'], 200);
  $page = max(1, (int) $request->input('page', 1));
  $per = 10;
  $items = new \Illuminate\Pagination\LengthAwarePaginator(
    $feed->forPage($page, $per)->values(), $feed->count(), $per, $page,
    ['path' => $request->url(), 'query' => $request->query()]
  );
  return view('notifications',['items'=>$items]);
});

Route::post('/notifications/read', function(Request $request){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return redirect('/login');
  $request->validate(['key'=>'required|string|max:150']);
  \App\Support\BuyerNotifications::dismiss($buyer['id'], $request->input('key'));
  return back();
});

Route::post('/notifications/read-all', function(Request $request){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return redirect('/login');
  \App\Support\BuyerNotifications::dismissAll($buyer['id']);
  return back()->with('success','All notifications marked as read');
});

// ---- Help center: FAQ + chatbot data (public) + customer-service contact ----
Route::get('/help', function(Request $request){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  $orders = collect();
  $complaints = collect();
  if ($buyer) {
    $orders = \App\Models\Order::where('buyer_id', $buyer['id'])->latest()->take(20)->get(['id', 'status', 'created_at']);
    $complaints = \App\Models\Complaint::where('buyer_id', $buyer['id'])->latest()->take(10)->get();
  }
  return view('help', [
    'support' => config('support'),
    'buyer' => $buyer,
    'orders' => $orders,
    'complaints' => $complaints,
  ]);
})->name('help');

Route::post('/help/complaint', function(Request $request){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return redirect('/login')->with('error','Please login to file a complaint');
  $data = $request->validate([
    'subject'     => 'required|string|max:150',
    'description' => 'required|string|max:2000',
    'order_id'    => 'nullable|integer|exists:orders,id',
  ]);
  $orderId = $data['order_id'] ?? null;
  if ($orderId && ! \App\Models\Order::where('id', $orderId)->where('buyer_id', $buyer['id'])->exists()) {
    return back()->with('error','That order does not belong to your account.');
  }
  \App\Models\Complaint::create([
    'buyer_id'    => $buyer['id'],
    'order_id'    => $orderId,
    'subject'     => $data['subject'],
    'description' => $data['description'],
    'type'        => $orderId ? 'order' : 'other',
    'status'      => 'open',
  ]);
  return back()->with('success','Complaint received. Our team will review it and reply by email within 24 hours on business days.');
})->name('help.complaint');

// ---- Seller Center: exact original seller app (same pages + functions) ----
Route::get('/seller/login', [SellerAppAuth::class, 'showLogin'])->name('seller.login');
Route::post('/seller/login', [SellerAppAuth::class, 'login'])->name('seller.login.post');
Route::post('/seller/logout', [SellerAppAuth::class, 'logout'])->name('seller.logout');

Route::prefix('seller')->middleware('seller')->group(function () {
    Route::get('/dashboard', SellerAppDashboard::class)->name('seller.dashboard');

    // Orders
    Route::get('/orders', [SellerAppOrders::class, 'index'])->name('seller.orders.index');
    Route::get('/orders/{order}', [SellerAppOrders::class, 'show'])->name('seller.orders.show');
    Route::post('/orders/{order}/status', [SellerAppOrders::class, 'updateStatus'])->name('seller.orders.status');
    Route::post('/orders/{order}/pickup', [SellerAppOrders::class, 'schedulePickup'])->name('seller.orders.pickup');
    Route::get('/orders/{order}/waybill', [SellerAppOrders::class, 'waybill'])->name('seller.orders.waybill');

    // Cash handoffs: rider -> seller bank transfers the seller must verify
    // before each sale settles.
    Route::get('/remittances', [\App\Http\Controllers\Seller\RemittanceController::class, 'index'])->name('seller.remittances.index');
    Route::post('/remittances/{remittance}/confirm', [\App\Http\Controllers\Seller\RemittanceController::class, 'confirm'])->name('seller.remittances.confirm');

    // Payouts: withdraw settled, available earnings.
    Route::get('/payouts', [\App\Http\Controllers\Seller\PayoutController::class, 'index'])->name('seller.payouts.index');
    Route::post('/payouts/request', [\App\Http\Controllers\Seller\PayoutController::class, 'request'])->name('seller.payouts.request');

    // Returns / refunds
    Route::get('/returns', [\App\Http\Controllers\Seller\ReturnController::class, 'index'])->name('seller.returns.index');
    Route::post('/returns/{returnRequest}', [\App\Http\Controllers\Seller\ReturnController::class, 'update'])->name('seller.returns.update');

    // Products
    Route::get('/products', [SellerAppProducts::class, 'index'])->name('seller.products.index');
    Route::get('/products/create', [SellerAppProducts::class, 'create'])->name('seller.products.create');
    Route::post('/products', [SellerAppProducts::class, 'store'])->name('seller.products.store');
    Route::get('/products/{product}/edit', [SellerAppProducts::class, 'edit'])->name('seller.products.edit');
    Route::post('/products/{product}', [SellerAppProducts::class, 'update'])->name('seller.products.update');
    Route::post('/products/{product}/archive', [SellerAppProducts::class, 'archive'])->name('seller.products.archive');
    Route::post('/products/{product}/restore', [SellerAppProducts::class, 'restore'])->name('seller.products.restore');

    // Vouchers
    Route::get('/vouchers', [SellerAppVouchers::class, 'index'])->name('seller.vouchers.index');
    Route::post('/vouchers', [SellerAppVouchers::class, 'store'])->name('seller.vouchers.store');
    Route::post('/vouchers/{voucher}', [SellerAppVouchers::class, 'update'])->name('seller.vouchers.update');
    Route::delete('/vouchers/{voucher}', [SellerAppVouchers::class, 'destroy'])->name('seller.vouchers.destroy');

// Store sales (one running sale per store).
Route::get('/sales', [\App\Http\Controllers\Seller\SaleController::class, 'index'])->name('seller.sales.index');
Route::post('/sales', [\App\Http\Controllers\Seller\SaleController::class, 'store'])->name('seller.sales.store');
Route::post('/sales/{sale}', [\App\Http\Controllers\Seller\SaleController::class, 'update'])->name('seller.sales.update');
Route::delete('/sales/{sale}', [\App\Http\Controllers\Seller\SaleController::class, 'destroy'])->name('seller.sales.destroy');

    // Reports
    Route::get('/reports', SellerAppReports::class)->name('seller.reports');

    // Chat
    Route::get('/chat', [SellerAppChat::class, 'index'])->name('seller.chat.index');
    Route::get('/chat/{conversation}', [SellerAppChat::class, 'show'])->name('seller.chat.show');
    Route::post('/chat/{conversation}/reply', [SellerAppChat::class, 'reply'])->name('seller.chat.reply');

    // Feedback
    Route::get('/feedback', [SellerAppReviews::class, 'index'])->name('seller.feedback.index');
    Route::post('/feedback/{review}/reply', [SellerAppReviews::class, 'reply'])->name('seller.feedback.reply');

    // Notifications
    Route::get('/notifications', [SellerAppNotifications::class, 'index'])->name('seller.notifications.index');
    Route::match(['GET', 'POST'], '/notifications/{notification}/read', [SellerAppNotifications::class, 'markRead'])->name('seller.notifications.read');
    Route::post('/notifications/read-all', [SellerAppNotifications::class, 'markAllRead'])->name('seller.notifications.read-all');

    // Account
    Route::get('/account', [SellerAppProfile::class, 'index'])->name('seller.account');
    Route::post('/account/profile', [SellerAppProfile::class, 'updateProfile'])->name('seller.account.profile');
    Route::post('/account/vacation', [SellerAppProfile::class, 'updateVacation'])->name('seller.account.vacation');
    Route::post('/account/password', [SellerAppProfile::class, 'updatePassword'])->name('seller.account.password');
});

Route::get('/admin/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'login'])->name('admin.login.post');
Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

Route::get('/buyer/login', [BuyerChatController::class, 'showLogin'])->name('buyer.login');
Route::post('/buyer/login', [BuyerChatController::class, 'login'])->name('buyer.login.post');
Route::post('/buyer/logout', [BuyerChatController::class, 'logout'])->name('buyer.logout');

Route::prefix('buyer')->middleware('buyer')->group(function () {
    Route::get('/chat', [BuyerChatController::class, 'chat'])->name('buyer.chat');
    Route::post('/chat/send', [BuyerChatController::class, 'send'])->name('buyer.chat.send');
    Route::get('/chat/{user}/messages', [BuyerChatController::class, 'messages'])->name('buyer.chat.messages');
});


// Logistics routes are included via require __DIR__.'/logistics/web.php';
// The /logistics route is defined in logistics/web.php and goes to CentralEntryController

Route::prefix('admin')->middleware(['auth', 'admin'])->group(function () {
    Route::get('dashboard', function () {
        $orders = \App\Models\Order::query();
        // Revenue counts SETTLED sales only: delivered AND cash handoff
        // confirmed by the seller (payment_status paid). Anything less
        // is still the rider's cash, not platform money.
        $settledBase = \App\Models\Order::where('status', 'delivered')->where('payment_status', 'paid');
        $paidTotal = fn () => \Illuminate\Support\Facades\DB::raw('COALESCE(total_amount, total, 0)');
        $stats = [
            'revenue' => (clone $settledBase)->sum($paidTotal()),
            'today_revenue' => (clone $settledBase)->whereDate('created_at', today())->sum($paidTotal()),
            'commission_earned' => (float) \App\Models\PaymentTransaction::where('status', 'successful')->sum('platform_commission'),
            'logistics_earned' => \Illuminate\Support\Facades\Schema::hasColumn('payment_transactions', 'logistics_fee')
                ? (float) \App\Models\PaymentTransaction::where('status', 'successful')->sum('logistics_fee')
                : 0.0,
            'orders' => (clone $orders)->count(),
            'delivered' => (clone $orders)->where('status', 'delivered')->count(),
            'to_action' => (clone $orders)->whereIn('status', ['new', 'pending'])->count(),
            'to_ship' => (clone $orders)->where('status', 'processing')->count(),
            'products' => \App\Models\Product::count(),
            'low_stock' => class_exists(\App\Models\Inventory::class) ? \App\Models\Inventory::where('quantity', '<=', 5)->count() : 0,
            'rating' => (float) (\App\Models\Review::where('status', 'visible')->avg('rating') ?? 0),
            'sellers' => \App\Models\Seller::approved()->count(),
            'pending_sellers' => \App\Models\Seller::where('approval_status', 'pending')->count(),
            'buyers' => \App\Models\User::where('role', 'buyer')->count(),
            'complaints' => class_exists(\App\Models\Complaint::class) ? \App\Models\Complaint::where('status', 'open')->count() : 0,
        ];
        $chart = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $chart[] = ['label' => $day->format('M d'), 'value' => round((clone $settledBase)->whereDate('created_at', $day->toDateString())->sum($paidTotal()), 2)];
        }
        $recentOrders = \App\Models\Order::with(['buyer', 'seller'])->latest()->take(5)->get();
        $topProducts = \App\Models\OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')->join('products', 'products.id', '=', 'order_items.product_id')->where('orders.status', 'delivered')->where('orders.payment_status', 'paid')->selectRaw('products.name, SUM(order_items.quantity) as units, SUM(order_items.total_price) as sales')->groupBy('products.name')->orderByDesc('sales')->take(5)->get();
        $statusCounts = (clone $orders)->select('status', \Illuminate\Support\Facades\DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status')->map(fn ($v) => (int) $v)->toArray();
        $statusMeta = [
            'pending' => ['Pending', '#F59E0B'], 'confirmed' => ['Confirmed', '#0EA5E9'],
            'processing' => ['Processing', '#3B82F6'], 'ready_for_delivery' => ['Ready', '#8B5CF6'],
            'out_for_delivery' => ['In Transit', '#F97316'], 'shipped' => ['Shipped', '#06B6D4'],
            'delivered' => ['Delivered', '#10B981'], 'cancelled' => ['Cancelled', '#EF4444'],
            'new' => ['New', '#F59E0B'],
        ];
        $statusTotal = array_sum($statusCounts) ?: 1;
        $statusStats = [];
        foreach ($statusCounts as $status => $count) {
            [$label, $color] = $statusMeta[$status] ?? [ucfirst(str_replace('_', ' ', $status)), '#94A3B8'];
            $statusStats[] = [
                'status' => $status, 'label' => $label, 'count' => $count,
                'color' => $color, 'percent' => round($count / $statusTotal * 100),
            ];
        }
        return view('admin.dashboard', compact('stats', 'chart', 'statusStats', 'statusCounts', 'recentOrders', 'topProducts'));
    })->name('admin.dashboard');

    Route::get('sellers', [SellerController::class, 'index'])->name('admin.sellers');
    Route::get('sellers/{seller}', [SellerController::class, 'show'])->name('admin.seller.show');
    Route::get('sellers/{seller}/document/{type}', [SellerController::class, 'viewDocument'])->name('admin.seller.document');
    Route::post('sellers/{seller}/approve', [SellerController::class, 'approve'])->name('admin.seller.approve');
    Route::post('sellers/{seller}/reject', [SellerController::class, 'reject'])->name('admin.seller.reject');
    Route::post('sellers/{seller}/suspend', [SellerController::class, 'suspend'])->name('admin.seller.suspend');
    Route::post('sellers/{seller}/reinstate', [SellerController::class, 'reinstate'])->name('admin.seller.reinstate');
    Route::get('buyers', function (\Illuminate\Http\Request $request) {
        $query = \App\Models\User::where('role', 'buyer')->withCount('orders');
        if ($request->filled('search')) { $s = $request->search; $query->where(fn($qq) => $qq->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")); }
        $buyers = $query->orderBy('created_at', 'desc')->paginate(10);
        $stats = ['all' => \App\Models\User::where('role','buyer')->count(), 'active' => \App\Models\User::where('role','buyer')->where('account_status','active')->count(), 'suspended' => 0];
        return view('admin.buyers', compact('buyers','stats'));
    })->name('admin.buyers');
    Route::get('riders', function (\Illuminate\Http\Request $request) { $r = \App\Models\User::where('role','rider')->orderBy('created_at','desc')->paginate(10); return view('admin.riders', ['riders' => $r]); })->name('admin.riders');
    Route::get('products', [ProductController::class, 'index'])->name('admin.products');
    Route::post('products/{product}/status', [ProductController::class, 'updateStatus'])->name('admin.product.status');
    Route::get('categories', [CategoryController::class, 'index'])->name('admin.categories');
    Route::post('categories', [CategoryController::class, 'store'])->name('admin.categories.store');
    Route::put('categories/{category}', [CategoryController::class, 'update'])->name('admin.categories.update');
    Route::post('categories/{category}/archive', [CategoryController::class, 'archive'])->name('admin.categories.archive');
    Route::post('categories/{category}/restore', [CategoryController::class, 'restore'])->name('admin.categories.restore');
    Route::get('orders', [OrderController::class, 'index'])->name('admin.orders');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('admin.order.show');
    Route::post('orders/{order}/update', [OrderController::class, 'update'])->name('admin.order.update');
        Route::get('payments', [PaymentController::class, 'index'])->name('admin.payments');
        Route::post('payments/payouts/{id}/mark-paid', [\App\Http\Controllers\Admin\PaymentActionController::class, 'markPayoutPaid'])->name('admin.payouts.mark-paid');
        Route::post('payments/withdrawals/{id}/approve', [\App\Http\Controllers\Admin\PaymentActionController::class, 'approveWithdrawal'])->name('admin.withdrawals.approve');
        Route::post('payments/withdrawals/{id}/reject', [\App\Http\Controllers\Admin\PaymentActionController::class, 'rejectWithdrawal'])->name('admin.withdrawals.reject');
        Route::post('payments/disputes/{id}/resolve', [\App\Http\Controllers\Admin\PaymentActionController::class, 'resolveDispute'])->name('admin.disputes.resolve');
    Route::get('manage-accounts', [UserManagementController::class, 'index'])->name('admin.manage-accounts');
    Route::post('manage-accounts/{user}/action', [UserManagementController::class, 'toggleStatus'])->name('admin.manage-accounts.action');
    Route::get('complaints', [ComplaintController::class, 'index'])->name('admin.complaints');
    Route::get('complaints/{complaint}', [ComplaintController::class, 'show'])->name('admin.complaint.show');
    Route::post('complaints/{complaint}/resolve', [ComplaintController::class, 'resolve'])->name('admin.complaint.resolve');
    Route::get('commission', [CommissionController::class, 'index'])->name('admin.commission');
    Route::post('commission/rates', [CommissionController::class, 'updateRate'])->name('admin.commission.rates.update');
    Route::post('commission/rates/add', [CommissionController::class, 'storeRate'])->name('admin.commission.rates.store');
    Route::get('reports', [ReportController::class, 'index'])->name('admin.reports');
    Route::get('reports/export', [ReportController::class, 'export'])->name('admin.reports.export');
    Route::get('reports/print', [ReportController::class, 'printView'])->name('admin.reports.print');
    Route::get('reports/commission', [ReportController::class, 'commission'])->name('admin.reports.commission');
    Route::get('settings', [SettingsController::class, 'index'])->name('admin.settings');
    Route::post('settings/update', [SettingsController::class, 'update'])->name('admin.settings.update');
    Route::post('settings/announcement', [SettingsController::class, 'save'])->name('admin.settings.announcement');
    Route::post('settings/policy', [SettingsController::class, 'createPolicy'])->name('admin.settings.policy.create');
    Route::put('settings/policy/{policy}', [SettingsController::class, 'updatePolicy'])->name('admin.settings.policy.update');
    Route::get('chat', [ChatController::class, 'index'])->name('admin.chat');
    Route::post('chat/send', [ChatController::class, 'send'])->name('admin.chat.send');
    Route::get('chat/{user}/messages', [ChatController::class, 'messages'])->name('admin.chat.messages');
    // Route::get('/logistics', function () {
    // return redirect('http://127.0.0.1:8000');
// })->name('logistics');

});




// Public fallback route for storage assets (safely serves files if web server symlink fails)
Route::get('/storage/{path}', function ($path) {
    $clean = ltrim(str_replace(['\\', '/storage/'], ['/', ''], (string) $path), '/');
    $candidates = [
        public_path('storage/' . $clean),
        storage_path('app/public/' . $clean),
        public_path($clean),
        storage_path('app/' . $clean),
    ];
    foreach ($candidates as $cand) {
        if (file_exists($cand) && ! is_dir($cand)) {
            $mime = mime_content_type($cand) ?: 'application/octet-stream';
            return response()->file($cand, [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="' . basename($cand) . '"',
            ]);
        }
    }
    abort(404, 'File not found');
})->where('path', '.*');

require __DIR__.'/logistics/web.php';
