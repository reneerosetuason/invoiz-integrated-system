<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\UnifiedAuthController;
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
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\AuthController as BuyerWebAuth;
use App\Models\Product;
use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| INVOIZ Unified — single backend for buyer + seller + admin
| DB: invoizdb (shared). Register -> buyer. Login -> role redirect.
|--------------------------------------------------------------------------
*/

// ---- Landing (live marketplace data) ----
Route::get('/', function () {
    $deals = [];
    $categories = collect();
    $stores = collect();
    $stats = ['products' => 0, 'sellers' => 0, 'orders' => 0];
    try {
        $dealRows = Product::with('category')->where('status', 'active')
            ->whereNotNull('compare_at_price')
            ->whereColumn('compare_at_price', '>', 'price')
            ->orderByDesc('created_at')->take(6)->get();
        if ($dealRows->isEmpty()) {
            $dealRows = Product::with('category')->where('status', 'active')->orderByDesc('created_at')->take(6)->get();
        }
        $deals = $dealRows->map(function ($p) {
            $pct = ($p->compare_at_price > $p->price && $p->compare_at_price > 0)
                ? (int) round((($p->compare_at_price - $p->price) / $p->compare_at_price) * 100) : 0;
            try { $store = \App\Models\Seller::nameFor($p->seller_id); } catch (\Throwable $e) { $store = ''; }
            return [
                'id' => $p->id, 'name' => $p->name,
                'price' => (float) ($p->price ?? 0),
                'compare_at' => (float) ($p->compare_at_price ?? 0),
                'pct' => $pct, 'rating' => $p->rating ? round((float) $p->rating, 1) : null,
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
    return view('landing', compact('deals', 'categories', 'stores', 'stats'));
})->name('landing');

// ---- Sell on INVOIZ (buyer applies, admin approves in /admin/sellers) ----
Route::get('/sell', function () {
    $uid = session('buyer.id') ?? (Auth::check() ? Auth::id() : null);
    if (! $uid) {
        return redirect('/register')->with('success', 'Create your buyer account first — then apply as a seller.');
    }
    $existing = \App\Models\Seller::where('user_id', $uid)->first();
    return view('sell', ['existing' => $existing]);
})->name('sell');

Route::post('/sell', function (Request $request) {
    $uid = session('buyer.id') ?? (Auth::check() ? Auth::id() : null);
    if (! $uid) {
        return redirect('/login')->with('error', 'Please log in first.');
    }
    if (\App\Models\Seller::where('user_id', $uid)->exists()) {
        return back()->with('error', 'You already have a seller application.');
    }
    $data = $request->validate([
        'business_name' => 'required|string|max:150',
        'line_of_business' => 'required|string|max:100',
        'id_image' => 'nullable|image|max:5120',
        'business_permit' => 'nullable|image|max:5120',
        'logo' => 'nullable|image|max:5120',
    ]);
    $idPath = $request->hasFile('id_image') ? $request->file('id_image')->store('seller-ids', 'public') : null;
    $permitPath = $request->hasFile('business_permit') ? $request->file('business_permit')->store('seller-permits', 'public') : null;
    $logoPath = $request->hasFile('logo') ? $request->file('logo')->store('logos', 'public') : null;
    \App\Models\Seller::create([
        'user_id' => $uid, 'business_name' => $data['business_name'],
        'line_of_business' => $data['line_of_business'],
        'id_image' => $idPath, 'business_permit' => $permitPath, 'logo' => $logoPath,
        'approval_status' => 'pending', 'status' => 'active',
    ]);
    return back()->with('success', 'Application submitted! An admin will review it in Seller Management.');
})->name('sell.post');

// ---- Unified auth (single login/register for everyone) ----
Route::get('/login', [UnifiedAuthController::class, 'showLogin'])->name('login');
Route::post('/login', [UnifiedAuthController::class, 'login'])->name('login.post');
Route::get('/register', [UnifiedAuthController::class, 'showRegister'])->name('register');
Route::post('/register', [UnifiedAuthController::class, 'register'])->name('register.post');
Route::post('/logout', [UnifiedAuthController::class, 'logout'])->name('logout');
Route::get('/logout', [UnifiedAuthController::class, 'logout']);
Route::get('/home', fn () => redirect('/shop'));

// Email code verification (Gmail OTP)
Route::get('/verify', [UnifiedAuthController::class, 'showVerify'])->name('verify');
Route::post('/verify', [UnifiedAuthController::class, 'verifyCode'])->name('verify.post');
Route::post('/verify/resend', [UnifiedAuthController::class, 'resendCode'])->name('verify.resend');

// Continue with Gmail (Google OAuth)
Route::get('/auth/google', [UnifiedAuthController::class, 'googleRedirect'])->name('google.redirect');
Route::get('/auth/google/callback', [UnifiedAuthController::class, 'googleCallback'])->name('google.callback');

// Dual buyer+seller logins pick an account here before entering a dashboard.
Route::middleware('auth')->group(function () {
    Route::get('/choose', [UnifiedAuthController::class, 'showChoose'])->name('choose');
    Route::get('/choose/buyer', [UnifiedAuthController::class, 'chooseBuyer'])->name('choose.buyer');
    Route::get('/choose/seller', [UnifiedAuthController::class, 'chooseSeller'])->name('choose.seller');
});

// (Unified /verify + /auth/google routes above cover all accounts.)

// ---- Buyer shop (common e-commerce flow: Shopee/Lazada style) ----
Route::get('/shop', [HomeController::class, 'index'])->name('shop');
Route::get('/products', [HomeController::class, 'index']);
Route::get('/sale', [HomeController::class, 'sale']);
Route::get('/product/{id}', [HomeController::class, 'show']);

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
  if($request->filled('single') || session()->has('checkout_single')){
    $raw = $request->filled('single') ? ['id'=>$request->integer('single'),'qty'=>$request->integer('qty',1),'variant_id'=>$request->input('variant_id')] : session('checkout_single');
    if(!is_array($raw)) $raw = ['id'=>(int)$raw,'qty'=>1,'variant_id'=>null];
    $p = Product::find($raw['id'] ?? 0);
    if(!$p) return redirect('/cart')->with('error','Product not found');
    $variant = Cart::resolveVariant($p, $raw['variant_id'] ?? null);
    $qty = max(1, min(200, (int)($raw['qty'] ?? 1)));
    $unit = Cart::unitFor($p, $variant);
    return view('checkout',['products'=>collect([$p]),'single'=>$p->id,'singleQty'=>$qty,'singleUnit'=>$unit,'singleVariantId'=>$variant ? $variant->id : null,'singleLabel'=>Cart::labelFor($variant),'cartItems'=>collect(),'checkoutMode'=>'single']);
  }
  if($request->filled('seller') || session()->has('checkout_seller_id')){
    $sellerId = $request->input('seller') ?? session('checkout_seller_id');
    $lines = Cart::linesFor($buyer['id'])->filter(fn($l) => (int)$l['product']->seller_id === (int)$sellerId)->values();
    if($lines->isEmpty()) return redirect('/cart')->with('error','No items from this seller');
    $cartItems = Cart::itemsFor($buyer['id'])->get();
    $pIds = $lines->pluck('product.id')->all();
    $products = Product::whereIn('id',$pIds)->get();
    return view('checkout',['products'=>$products,'cartItems'=>$cartItems,'single'=>null,'checkoutMode'=>'seller','sellerId'=>$sellerId,'sellerLines'=>$lines]);
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
    return view('checkout',['products'=>$products,'cartItems'=>$cartItems,'single'=>null,'checkoutMode'=>'all','sellerGroups'=>$sellerGroups,'grandTotal'=>$grandTotal]);
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
    return view('checkout',['products'=>$products,'cartItems'=>$cartItems,'single'=>null,'checkoutMode'=>'selected','sellerGroups'=>$sellerGroups,'grandTotal'=>$grandTotal]);
  }
  return redirect('/cart');
});

Route::post('/checkout', function(Request $request){
  $request->validate(['payment_method'=>'required|in:cod']);
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id(),'first_name'=>Auth::user()->first_name ?? '','last_name'=>Auth::user()->last_name ?? ''] : null);
  if(!$buyer) return redirect('/login')->with('error','Please login');
  $addr = \App\Models\Address::where('buyer_id',$buyer['id'])->first();
  if(!$addr){
    $addr = \App\Models\Address::create(['buyer_id'=>$buyer['id'],'recipient_name'=>trim(($buyer['first_name'] ?? 'Buyer').' '.($buyer['last_name'] ?? '')),'phone'=>'09170000000','address_line'=>'123 Street','barangay'=>'Test','city'=>'Test City','province'=>'Test Province','postal_code'=>'1000','is_default'=>1]);
  }
  $checkoutMode = $request->input('checkout_mode', session('checkout_single') ? 'single' : (session('checkout_all') ? 'all' : (session('checkout_selected') ? 'selected' : 'seller')));
  $ordersCreated = 0;
  try {
    \Illuminate\Support\Facades\DB::transaction(function () use ($buyer, $addr, $request, $checkoutMode, &$ordersCreated) {
    if($checkoutMode === 'single'){
      $raw = session('checkout_single');
      if(!is_array($raw)) $raw = ['id'=>$request->input('single'),'qty'=>$request->input('qty',1),'variant_id'=>$request->input('variant_id')];
      $product = Product::find((int)($raw['id'] ?? 0));
      if(!$product) throw new \Exception('Product not found');
      $variant = Cart::resolveVariant($product, $raw['variant_id'] ?? null);
      $qty = max(1, min(200, (int)($raw['qty'] ?? 1)));
      $unit = Cart::unitFor($product, $variant);
      $total = $unit * $qty;
      if(! \App\Support\Stock::deduct($product->id, $variant?->id, $qty)) throw new \Exception('Not enough stock for "'.$product->name.'".');
      $order = \App\Models\Order::create(['buyer_id'=>$buyer['id'],'address_id'=>$addr->id,'total_amount'=>$total,'total'=>$total,'status'=>'pending']);
      \App\Models\OrderStatusHistory::create(['order_id'=>$order->id,'from_status'=>null,'to_status'=>'pending','note'=>'Order placed.']);
      if (class_exists(\App\Models\Delivery::class)) \App\Models\Delivery::create(['order_id'=>$order->id,'status'=>'waiting_for_rider']);
      \App\Models\OrderItem::create(['order_id'=>$order->id,'product_id'=>$product->id,'product_variant_id'=>$variant?->id,'seller_id'=>$product->seller_id,'product_name'=>$product->name,'variant_label'=>Cart::labelFor($variant),'quantity'=>$qty,'price'=>$unit,'unit_price'=>$unit,'total_price'=>$total]);
      if (class_exists(\App\Models\Payment::class)) \App\Models\Payment::create(['order_id'=>$order->id,'method'=>'cash_on_delivery','status'=>'pending','amount'=>$total]);
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
      $bySeller = in_array($checkoutMode, ['all','selected']) ? $lines->groupBy(fn($l) => $l['product']->seller_id) : [0 => $lines];
      foreach($bySeller as $sLines){
        foreach($sLines as $l){
          if(! \App\Support\Stock::deduct($l['product']->id, $l['variant']?->id, $l['qty'])) throw new \Exception('Not enough stock for "'.$l['product']->name.'".');
        }
        $total = $sLines->sum('line');
        $order = \App\Models\Order::create(['buyer_id'=>$buyer['id'],'address_id'=>$addr->id,'total_amount'=>$total,'total'=>$total,'status'=>'pending']);
        \App\Models\OrderStatusHistory::create(['order_id'=>$order->id,'from_status'=>null,'to_status'=>'pending','note'=>'Order placed.']);
        if (class_exists(\App\Models\Delivery::class)) \App\Models\Delivery::create(['order_id'=>$order->id,'status'=>'waiting_for_rider']);
        foreach($sLines as $l){
          \App\Models\OrderItem::create(['order_id'=>$order->id,'product_id'=>$l['product']->id,'product_variant_id'=>$l['variant']?->id,'seller_id'=>$l['product']->seller_id,'product_name'=>$l['product']->name,'variant_label'=>$l['label'],'quantity'=>$l['qty'],'price'=>$l['unit'],'unit_price'=>$l['unit'],'total_price'=>$l['line']]);
          $l['item']->delete();
        }
        if (class_exists(\App\Models\Payment::class)) \App\Models\Payment::create(['order_id'=>$order->id,'method'=>'cash_on_delivery','status'=>'pending','amount'=>$total]);
        $ordersCreated++;
      }
    }
    });
  } catch(\Throwable $e){ \Log::error('Checkout failed: '.$e->getMessage()); return back()->with('error','Checkout failed: '.$e->getMessage()); }
  session()->forget(['checkout_single','checkout_seller_id','checkout_all','checkout_selected']);
  return redirect('/orders')->with('success', $ordersCreated > 1 ? "Placed {$ordersCreated} orders — Cash on Delivery — thank you!" : 'Order placed — Cash on Delivery — thank you!');
});

Route::get('/store/{id}', function($id, Request $request){
  $sellerId = (int)$id;
  $sellerUser = \App\Models\User::find($sellerId);
  // Accept sellers.id too (admin/seller links) — resolve to the same store.
  if(! $sellerUser) {
    $s = \App\Models\Seller::find($sellerId);
    if($s) { $sellerId = (int) $s->user_id; $sellerUser = \App\Models\User::find($sellerId); }
  }
  if(!$sellerUser) return redirect('/shop')->with('error','Store not found');
  $name = \App\Models\Seller::nameFor($sellerId);
  $pq = Product::with('category')->whereIn('seller_id', [$sellerId])->where('status','active');
  // Sellers rows may use sellers.id convention — include those products too.
  try {
    $alt = \App\Models\Seller::where('user_id',$sellerId)->first();
    if($alt) $pq = Product::with('category')->whereIn('seller_id', [$sellerId, $alt->id])->where('status','active');
  } catch (\Throwable $e) {}
  if($request->filled('search')) $pq->where('name','like','%'.$request->input('search').'%');
  $sort = $request->input('sort','newest');
  if($sort==='price_asc') $pq->orderBy('price','asc');
  elseif($sort==='price_desc') $pq->orderBy('price','desc');
  elseif($sort==='rating') $pq->orderByDesc('rating');
  else $pq->orderByDesc('created_at');
  $products = $pq->get();
  $productIds = Product::whereIn('seller_id', [$sellerId])->pluck('id');
  $reviews = collect();
  $rating = null;
  $followers = 0;
  $following = false;
  try {
    if (class_exists(\App\Models\Review::class)) {
      $reviews = \App\Models\Review::with('buyer')->whereIn('product_id',$productIds)->where('status','visible')->orderByDesc('created_at')->limit(20)->get();
      $rating = $reviews->avg('rating');
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
  return view('store', compact('sellerId','sellerUser','name','products','reviews','rating','followers','following','tab','buyer','storeLogo') + ['storeSearch'=>$request->input('search',''),'storeSort'=>$sort]);
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

Route::get('/profile', function(Request $request){
  $b = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$b && !Auth::check()) return redirect('/login')->with('error','Please login');
  $u = \App\Models\User::find($b['id'] ?? Auth::id());
  return view('profile',['user'=>$u]);
});

Route::post('/profile', function(Request $request){
  $b = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$b && !Auth::check()) return redirect('/login');
  $u = \App\Models\User::find($b['id'] ?? Auth::id());
  if(! $u) return redirect('/login');
  $request->validate(['first_name'=>'required|string|max:100','last_name'=>'required|string|max:100','email'=>'required|email|max:150|unique:users,email,'.$u->id,'phone'=>'nullable|string|max:30']);
  $u->update(['first_name'=>$request->first_name,'last_name'=>$request->last_name,'email'=>$request->email,'phone'=>$request->phone ?? $u->phone]);
  $request->session()->put('buyer',['id'=>$u->id,'first_name'=>$u->first_name,'last_name'=>$u->last_name,'email'=>$u->email]);
  return redirect('/profile')->with('success','Profile updated — same name now shows in shop, orders and chat.');
});

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
  $orders = $q->latest()->get();
  return view('orders',['orders'=>$orders,'activeTab'=>$tab,'q'=>$request->input('q','')]);
})->name('orders');

Route::get('/orders/{id}', function($id, Request $request){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return redirect('/login')->with('error','Please login');
  $order = \App\Models\Order::with(['items.product','payment','delivery','statusHistories'])->where('buyer_id',$buyer['id'])->findOrFail($id);
  return view('order', ['order'=>$order]);
})->whereNumber('id');

Route::post('/orders/{id}/cancel', function($id, Request $request){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return redirect('/login')->with('error','Please login');
  $order = \App\Models\Order::with('items')->where('buyer_id',$buyer['id'])->findOrFail($id);
  if(! $order->isCancellableByBuyer()) return back()->with('error','This order can no longer be cancelled — it is already being prepared or on its way to you.');
  $from = $order->status;
  $order->update(['status'=>'cancelled']);
  \App\Models\OrderStatusHistory::create(['order_id'=>$order->id,'from_status'=>$from,'to_status'=>'cancelled','note'=>'Order cancelled by buyer.']);
  // Never shipped (pending/confirmed only reach here) → give the stock back.
  \App\Support\Stock::restoreOrder($order->fresh('items'));
  return redirect('/orders/'.$order->id)->with('success','Order cancelled — stock restored and seller/admin see the same status.');
})->whereNumber('id');

// ---- Buyer ↔ seller chat (same threads the seller and admin see) ----
Route::get('/messages', function(Request $request){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return redirect('/login')->with('error','Please login');
  $userId = $buyer['id'];
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
  $conv = \App\Models\Conversation::firstOrCreate(['buyer_id'=>$buyer['id'],'seller_id'=>$sellerId], ['subject'=>null]);
  \App\Models\Message::where('conversation_id',$conv->id)->where('sender_id','!=',$buyer['id'])->where('is_read',0)->update(['is_read'=>1]);
  $messages = \App\Models\Message::with('conversation')->where('conversation_id',$conv->id)->orderBy('created_at')->get();
  return view('chat',['sellerId'=>(int)$sellerId,'messages'=>$messages]);
});

Route::post('/chat/send/{sellerId}', function(Request $request, $sellerId){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return response()->json(['error'=>'Login required'],401);
  $request->validate(['body'=>'required|string|max:2000']);
  $conv = \App\Models\Conversation::firstOrCreate(['buyer_id'=>$buyer['id'],'seller_id'=>$sellerId], ['subject'=>null]);
  \App\Models\Message::create(['conversation_id'=>$conv->id,'sender_id'=>$buyer['id'],'receiver_id'=>(int)$sellerId,'body'=>$request->input('body'),'is_read'=>0]);
  $conv->touch();
  return response()->json(['ok'=>true]);
});

Route::get('/chat/fetch/{sellerId}', function(Request $request, $sellerId){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return response()->json(['error'=>'Login required'],401);
  $conv = \App\Models\Conversation::where('buyer_id',$buyer['id'])->where('seller_id',$sellerId)->first();
  if(!$conv) return response()->json([]);
  $q = \App\Models\Message::where('conversation_id',$conv->id);
  if($request->filled('after')) $q->where('created_at','>',$request->input('after'));
  return response()->json($q->with('conversation')->orderBy('created_at')->get()->map(function($m) use($buyer){
    try { $role = $m->senderRole(); [$label] = \App\Models\Message::roleBadge($role); }
    catch (\Throwable $e) { $role = 'seller'; $label = 'Seller'; }
    return ['id'=>$m->id,'body'=>$m->body,'is_me'=>$m->sender_id==$buyer['id'],'sender_role'=>$role,'sender_label'=>$label,'created_at'=>$m->created_at->toDateTimeString()];
  }));
});

Route::get('/notifications', function(Request $request){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return redirect('/login')->with('error','Please login');
  if (! class_exists(\App\Support\BuyerNotifications::class)) return view('notifications',['items'=>[]]);
  $items = \App\Support\BuyerNotifications::for($buyer['id'], 30);
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

    // Reports
    Route::get('/reports', SellerAppReports::class)->name('seller.reports');

    // Chat
    Route::get('/chat', [SellerAppChat::class, 'index'])->name('seller.chat.index');
    Route::get('/chat/{conversation}', [SellerAppChat::class, 'show'])->name('seller.chat.show');
    Route::post('/chat/{conversation}/reply', [SellerAppChat::class, 'reply'])->name('seller.chat.reply');

    // Feedback
    Route::get('/feedback', [SellerAppReviews::class, 'index'])->name('seller.feedback.index');
    Route::post('/feedback/{review}/toggle', [SellerAppReviews::class, 'toggle'])->name('seller.feedback.toggle');

    // Notifications
    Route::get('/notifications', [SellerAppNotifications::class, 'index'])->name('seller.notifications.index');
    Route::match(['GET', 'POST'], '/notifications/{notification}/read', [SellerAppNotifications::class, 'markRead'])->name('seller.notifications.read');
    Route::post('/notifications/read-all', [SellerAppNotifications::class, 'markAllRead'])->name('seller.notifications.read-all');

    // Account
    Route::get('/account', [SellerAppProfile::class, 'index'])->name('seller.account');
    Route::post('/account/profile', [SellerAppProfile::class, 'updateProfile'])->name('seller.account.profile');
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

Route::prefix('admin')->middleware('auth')->group(function () {
    Route::get('dashboard', function () {
        $orders = \App\Models\Order::query();
        $stats = [
            'revenue' => (clone $orders)->where('status', 'delivered')->sum('total'),
            'today_revenue' => (clone $orders)->where('status', 'delivered')->whereDate('created_at', today())->sum('total'),
            'orders' => (clone $orders)->count(),
            'delivered' => (clone $orders)->where('status', 'delivered')->count(),
            'to_action' => (clone $orders)->whereIn('status', ['new', 'pending'])->count(),
            'to_ship' => (clone $orders)->where('status', 'processing')->count(),
            'products' => \App\Models\Product::count(),
            'low_stock' => class_exists(\App\Models\Inventory::class) ? \App\Models\Inventory::where('quantity', '<=', 5)->count() : 0,
            'rating' => (float) (\App\Models\ProductReview::avg('rating') ?? 0),
            'sellers' => \App\Models\Seller::approved()->count(),
            'pending_sellers' => \App\Models\Seller::where('approval_status', 'pending')->count(),
            'buyers' => \App\Models\User::where('role', 'buyer')->count(),
            'complaints' => class_exists(\App\Models\Complaint::class) ? \App\Models\Complaint::where('status', 'open')->count() : 0,
        ];
        $chart = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $chart[] = ['label' => $day->format('M d'), 'value' => round((clone $orders)->where('status', 'delivered')->whereDate('created_at', $day->toDateString())->sum('total'), 2)];
        }
        $recentOrders = \App\Models\Order::with(['buyer', 'seller'])->latest()->take(5)->get();
        $topProducts = \App\Models\OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')->join('products', 'products.id', '=', 'order_items.product_id')->selectRaw('products.name, SUM(order_items.quantity) as units, SUM(order_items.total_price) as sales')->groupBy('products.name')->orderByDesc('sales')->take(5)->get();
        $statusStats = [];
        return view('admin.dashboard', compact('stats', 'chart', 'statusStats', 'recentOrders', 'topProducts'));
    })->name('admin.dashboard');

    Route::get('sellers', [SellerController::class, 'index'])->name('admin.sellers');
    Route::get('sellers/{seller}', [SellerController::class, 'show'])->name('admin.seller.show');
    Route::post('sellers/{seller}/approve', [SellerController::class, 'approve'])->name('admin.seller.approve');
    Route::post('sellers/{seller}/reject', [SellerController::class, 'reject'])->name('admin.seller.reject');
    Route::post('sellers/{seller}/suspend', [SellerController::class, 'suspend'])->name('admin.seller.suspend');
    Route::post('sellers/{seller}/reinstate', [SellerController::class, 'reinstate'])->name('admin.seller.reinstate');
    Route::get('buyers', function (\Illuminate\Http\Request $request) {
        $query = \App\Models\User::where('role', 'buyer')->withCount('orders');
        if ($request->filled('search')) { $s = $request->search; $query->where(fn($qq) => $qq->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")); }
        $buyers = $query->orderBy('created_at', 'desc')->get();
        $stats = ['all' => \App\Models\User::where('role','buyer')->count(), 'active' => \App\Models\User::where('role','buyer')->where('account_status','active')->count(), 'suspended' => 0];
        return view('admin.buyers', compact('buyers','stats'));
    })->name('admin.buyers');
    Route::get('riders', fn (\Illuminate\Http\Request $request) => view('admin.riders', ['riders' => \App\Models\User::where('role','rider')->orderBy('created_at','desc')->get()]))->name('admin.riders');
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
    Route::post('payments/payouts/{payout}/mark-paid', [PaymentController::class, 'markPayoutPaid'])->name('admin.payments.payout.paid');
    Route::post('payments/withdrawals/{withdrawal}/approve', [PaymentController::class, 'approveWithdrawal'])->name('admin.payments.withdrawal.approve');
    Route::post('payments/withdrawals/{withdrawal}/reject', [PaymentController::class, 'rejectWithdrawal'])->name('admin.payments.withdrawal.reject');
    Route::post('payments/disputes/{dispute}/resolve', [PaymentController::class, 'resolveDispute'])->name('admin.payments.dispute.resolve');
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
});
