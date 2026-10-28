<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Buyer\ProfileController;
use App\Http\Controllers\Buyer\BuyerFeaturesController;
use App\Http\Controllers\Buyer\Web\HomeController;
use App\Models\Product;
use App\Models\Cart;
use Illuminate\Http\Request;

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

Route::post('/cart/add/{id}', function($id, Request $request){
  $buyer = \App\Support\BuyerSession::current();
  if(!$buyer) return redirect('/login')->with('error','Please login to add items to cart');
  $request->validate(['qty' => ['nullable', 'integer', 'min:1', 'max:200'], 'variant_id' => ['nullable', 'integer', 'min:1']]);
  $product = Product::where('status', 'active')->find($id);
  if(!$product) return redirect('/cart')->with('error','Product not found');
  $variant = Cart::purchaseVariant($product, $request->input('variant_id'));
  if($error = Cart::addFor($buyer['id'], $product, $variant, (int)($request->input('qty') ?? 1))) return redirect('/cart')->with('error', $error);
  $product->trackCartAddition();
  return redirect('/cart')->with('success','Added to cart');
});

Route::get('/buy/{id}', function($id, Request $request){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return redirect('/login')->with('error','Please login to buy');
  $request->validate(['qty' => ['nullable', 'integer', 'min:1', 'max:200'], 'variant_id' => ['nullable', 'integer', 'min:1']]);
  $product = Product::where('status', 'active')->find($id);
  if(!$product) return redirect('/shop')->with('error','Product not found');
  $qty = max(1, min(200, $request->integer('qty', 1)));
  $variant = Cart::purchaseVariant($product, $request->input('variant_id'));
  $available = Cart::availableFor($product, $variant);
  if($available < 1) return back()->with('error', 'This item is out of stock.');
  $qty = min($qty, $available);
  session(['checkout_single'=>['id'=>(int)$id,'qty'=>$qty,'variant_id'=>$variant ? $variant->id : null]]);
  session()->forget(['checkout_seller_id','checkout_all','checkout_selected']);
  return redirect('/checkout?single='.$id);
})->whereNumber('id');

Route::post('/cart/inc/{item}', function($item){
  $buyer = \App\Support\BuyerSession::current();
  if(!$buyer) return redirect('/login');
  if($error = Cart::increaseFor($buyer['id'], (int)$item)) return redirect('/cart')->with('error', $error);
  return redirect('/cart')->with('success','Added to cart');
});

Route::post('/cart/dec/{item}', function($item){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return redirect('/login');
  $line = Cart::itemsFor($buyer['id'])->where('id',$item)->first();
  if($line){ if($line->quantity <= 1) $line->delete(); else $line->decrement('quantity'); }
  return redirect('/cart');
});

Route::post('/cart/remove/{item}', function($item){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return redirect('/login');
  Cart::itemsFor($buyer['id'])->where('id',$item)->delete();
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
  $request->validate(['ids' => ['required', 'string', 'max:2000', 'regex:/^\d+(,\d+)*$/']]);
  $idArr = array_map('intval', explode(',', $ids));
  $lines = Cart::selectedLinesFor($buyer['id'], $idArr);
  if($lines->isEmpty()) return redirect('/cart')->with('error','Selected items not found in cart');
  session(['checkout_selected' => $lines->pluck('item.id')->map(fn ($id) => (int) $id)->all()]);
  session()->forget(['checkout_single','checkout_seller_id','checkout_all']);
  return redirect('/checkout?selected=1');
});

Route::get('/cart', function(){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return redirect('/login')->with('error','Please login to view cart');
  $cartItems = Cart::itemsFor($buyer['id'])->get();
  $productIds = $cartItems->pluck('product_id')->toArray();
  $products = Product::whereIn('id', $productIds)->get();
  return view('buyer.cart', ['products'=>$products, 'cartItems'=>$cartItems]);
})->name('cart');

Route::get('/checkout', function(Request $request){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return redirect('/login')->with('error','Please login to checkout');
  $request->validate(['single' => ['nullable', 'integer', 'min:1'], 'qty' => ['nullable', 'integer', 'min:1', 'max:200'], 'variant_id' => ['nullable', 'integer', 'min:1'], 'ids' => ['nullable', 'string', 'max:2000', 'regex:/^\d+(,\d+)*$/']]);
  $claimedVouchers = \App\Models\VoucherClaim::with('voucher')
    ->where('buyer_id', $buyer['id'])->whereNull('used_at')
    ->whereHas('voucher', fn ($q) => $q->active()->where(fn ($x) => $x->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit')))
    ->latest('claimed_at')->get();
  \App\Models\Address::ensureForBuyer((int) $buyer['id']);
  $addresses = \App\Models\Address::where('buyer_id', $buyer['id'])->orderByDesc('is_default')->orderByDesc('updated_at')->get();
  $selectedAddressId = old('address_id', $request->input('address_id', $addresses->firstWhere('is_default', true)?->id));
  if($request->filled('single') || session()->has('checkout_single')){
    $saved = session('checkout_single');
    if(!is_array($saved)) $saved = ['id'=>(int)$saved,'qty'=>1,'variant_id'=>null];
    $sameProduct = $request->integer('single') === (int)($saved['id'] ?? 0);
    $raw = $request->filled('single')
      ? ['id'=>$request->integer('single'),'qty'=>$request->integer('qty', $sameProduct ? (int)($saved['qty'] ?? 1) : 1),'variant_id'=>$request->input('variant_id', $sameProduct ? ($saved['variant_id'] ?? null) : null)]
      : $saved;
    if(!is_array($raw)) $raw = ['id'=>(int)$raw,'qty'=>1,'variant_id'=>null];
    $p = Product::where('status', 'active')->find($raw['id'] ?? 0);
    if(!$p) return redirect('/cart')->with('error','Product not found');
    $variant = Cart::purchaseVariant($p, $raw['variant_id'] ?? null);
    $qty = max(1, min(200, (int)($raw['qty'] ?? 1)));
    $available = Cart::availableFor($p, $variant);
    if($available < 1) return redirect('/cart')->with('error', 'This item is out of stock.');
    $qty = min($qty, $available);
    session(['checkout_single'=>['id'=>$p->id,'qty'=>$qty,'variant_id'=>$variant?->id]]);
    $unit = Cart::unitFor($p, $variant);
    return view('buyer.checkout',['products'=>collect([$p]),'single'=>$p->id,'singleQty'=>$qty,'singleUnit'=>$unit,'singleVariantId'=>$variant ? $variant->id : null,'singleLabel'=>Cart::labelFor($variant),'cartItems'=>collect(),'checkoutMode'=>'single','addresses'=>$addresses,'selectedAddressId'=>$selectedAddressId,'claimedVouchers'=>$claimedVouchers]);
  }
  if($request->filled('seller') || session()->has('checkout_seller_id')){
    $sellerId = $request->input('seller') ?? session('checkout_seller_id');
    $lines = Cart::linesFor($buyer['id'])->filter(fn($l) => (int)$l['product']->seller_id === (int)$sellerId)->values();
    if($lines->isEmpty()) return redirect('/cart')->with('error','No items from this seller');
    $cartItems = Cart::itemsFor($buyer['id'])->get();
    $pIds = $lines->pluck('product.id')->all();
    $products = Product::whereIn('id',$pIds)->get();
    return view('buyer.checkout',['products'=>$products,'cartItems'=>$cartItems,'single'=>null,'checkoutMode'=>'seller','sellerId'=>$sellerId,'sellerLines'=>$lines,'addresses'=>$addresses,'selectedAddressId'=>$selectedAddressId,'claimedVouchers'=>$claimedVouchers]);
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
    return view('buyer.checkout',['products'=>$products,'cartItems'=>$cartItems,'single'=>null,'checkoutMode'=>'all','sellerGroups'=>$sellerGroups,'grandTotal'=>$grandTotal,'addresses'=>$addresses,'selectedAddressId'=>$selectedAddressId,'claimedVouchers'=>$claimedVouchers]);
  }
  if($request->filled('selected') || session()->has('checkout_selected')){
    $idArr = session()->has('checkout_selected') ? (array) session('checkout_selected') : array_map('intval', explode(',', (string)$request->input('ids','')));
    $lines = Cart::selectedLinesFor($buyer['id'], $idArr);
    if($lines->isEmpty()) return redirect('/cart')->with('error','Selected items not found in cart');
    $bySeller = $lines->groupBy(fn($l) => $l['product']->seller_id);
    $sellerGroups = [];
    foreach($bySeller as $sid => $sLines){ $sellerGroups[$sid] = ['lines'=>$sLines->values(),'total'=>$sLines->sum('line')]; }
    $grandTotal = collect($sellerGroups)->sum('total');
    $products = $lines->pluck('product');
    $cartItems = Cart::itemsFor($buyer['id'])->get();
    return view('buyer.checkout',['products'=>$products,'cartItems'=>$cartItems,'single'=>null,'checkoutMode'=>'selected','sellerGroups'=>$sellerGroups,'grandTotal'=>$grandTotal,'addresses'=>$addresses,'selectedAddressId'=>$selectedAddressId,'claimedVouchers'=>$claimedVouchers]);
  }
  return redirect('/cart');
});

Route::post('/checkout', function(Request $request){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id(),'first_name'=>Auth::user()->first_name ?? '','last_name'=>Auth::user()->last_name ?? ''] : null);
  if(!$buyer) return redirect('/login')->with('error','Please login');
  $request->validate([
    'address_id' => ['required', 'integer'],
    'payment_method' => ['required', 'in:cod'],
    'checkout_mode' => ['nullable', 'in:single,seller,all,selected'],
    'single' => ['nullable', 'integer', 'min:1'],
    'qty' => ['nullable', 'integer', 'min:1', 'max:200'],
    'variant_id' => ['nullable', 'integer', 'min:1'],
    'seller_id' => ['nullable', 'integer', 'min:1'],
    'selected_item_ids' => ['sometimes', 'array', 'min:1', 'max:200'],
    'selected_item_ids.*' => ['integer', 'min:1', 'distinct'],
    'voucher_claim_id' => ['nullable', 'integer', 'exists:voucher_claims,id'],
    'voucher_claim_ids' => ['nullable', 'array', 'max:3'],
    'voucher_claim_ids.*' => ['integer', 'exists:voucher_claims,id'],
  ]);
  $addr = \App\Models\Address::where('buyer_id', $buyer['id'])->find($request->integer('address_id'));
  if (! $addr) {
    return back()->withErrors(['address_id' => 'Select one of your saved delivery addresses before placing the order.'])->withInput();
  }
  if ($dropoffProblem = \App\Support\DeliveryServiceability::dropoffAddressProblem($addr)) {
    return back()->with('error', $dropoffProblem)->withInput();
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
      $raw = $request->filled('single')
        ? ['id'=>$request->integer('single'),'qty'=>$request->integer('qty',1),'variant_id'=>$request->input('variant_id')]
        : session('checkout_single');
      if(!is_array($raw)) throw new \Exception('Choose an item before checking out.');
      $product = Product::where('status', 'active')->find((int)($raw['id'] ?? 0));
      if(!$product) throw new \Exception('Product not found');
      if ($pickupProblem = \App\Support\DeliveryServiceability::pickupAddressProblem((int) $product->seller_id)) {
        throw new \Exception($pickupProblem);
      }
      $variant = Cart::purchaseVariant($product, $raw['variant_id'] ?? null);
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
      $order = \App\Models\Order::create(['seller_id'=>(int) $product->seller_id,'buyer_id'=>$buyer['id'],'address_id'=>$addr->id,'shipping_address'=>$addr->toShippingSnapshot(),'total_amount'=>$total,'total'=>$total,'sub_total'=>$subtotal,'discount_total'=>round($merch + $waived, 2),'shipping_fee'=>round($shipping - $waived, 2),'status'=>'pending']);
      \App\Models\OrderStatusHistory::create(['order_id'=>$order->id,'from_status'=>null,'to_status'=>'pending','note'=>'Order placed.']);
      if (class_exists(\App\Models\Delivery::class)) \App\Models\Delivery::create(['order_id'=>$order->id,'seller_id'=>(int) $product->seller_id,'status'=>'waiting_for_rider','payment_method'=>'cash_on_delivery','amount_to_collect'=>$total,'delivery_fee'=>\App\Models\Order::SHIPPING_FEE_PER_SELLER]);
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
        $lines = $lines->filter(fn($l) => (int)$l['product']->seller_id === (int)$request->input('seller_id', session('checkout_seller_id')))->values();
      } elseif($checkoutMode === 'selected') {
        $idArr = $request->input('selected_item_ids', session('checkout_selected', []));
        $lines = Cart::selectedLinesFor($buyer['id'], $idArr);
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
        if ($pickupProblem = \App\Support\DeliveryServiceability::pickupAddressProblem((int) $groupSid)) {
          throw new \Exception($pickupProblem);
        }
        foreach($sLines as $l){
          if(Cart::availableFor($l['product'], $l['variant'], $l['item']->variant_id) < $l['qty']) throw new \Exception('This item is unavailable or has insufficient stock. Please review your cart.');
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
        $order = \App\Models\Order::create(['seller_id'=>(int) $groupSid,'buyer_id'=>$buyer['id'],'address_id'=>$addr->id,'shipping_address'=>$addr->toShippingSnapshot(),'total_amount'=>$total,'total'=>$total,'sub_total'=>$subtotal,'discount_total'=>round($merch + $waived, 2),'shipping_fee'=>round($shipping - $waived, 2),'status'=>'pending']);
        \App\Models\OrderStatusHistory::create(['order_id'=>$order->id,'from_status'=>null,'to_status'=>'pending','note'=>'Order placed.']);
        if (class_exists(\App\Models\Delivery::class)) \App\Models\Delivery::create(['order_id'=>$order->id,'seller_id'=>(int) $groupSid,'status'=>'waiting_for_rider','payment_method'=>'cash_on_delivery','amount_to_collect'=>$total,'delivery_fee'=>\App\Models\Order::SHIPPING_FEE_PER_SELLER]);
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
  return view('buyer.store', compact('sellerId','sellerUser','name','products','reviews','rating','followers','following','tab','buyer','storeLogo','favoriteIds','vacation') + ['storeSearch'=>$request->input('search',''),'storeSort'=>$sort]);
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
  return view('buyer.orders',['orders'=>$orders,'activeTab'=>$tab,'q'=>$request->input('q','')]);
})->name('orders');

Route::get('/orders/{id}', function($id, Request $request){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return redirect('/login')->with('error','Please login');
  $order = \App\Models\Order::with(['items.product','payment','deliveries','statusHistories'])->where('buyer_id',$buyer['id'])->findOrFail($id);
  $reviews = \App\Models\Review::where('buyer_id',$buyer['id'])->where('order_id',$order->id)->get()->keyBy('product_id');
  return view('buyer.order', ['order'=>$order, 'reviews'=>$reviews]);
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
  $labels = ['changed_mind'=>'Changed my mind','ordered_by_mistake'=>'Ordered by mistake','found_better_price'=>'Found a better price','delivery_too_long'=>'Delivery is taking too long','wrong_item'=>'I ordered the wrong item','other'=>'Other'];
  $reason = $labels[$data['reason']] . ($data['reason'] === 'other' ? ': ' . trim($data['reason_details']) : '');
  \App\Support\OrderCancellation::byBuyer($order, $reason, (int) $buyer['id']);
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
  return view('buyer.messages',['conversations'=>$conversations,'selId'=>$selId,'selMessages'=>$selMessages,'selName'=>$selName]);
});

Route::get('/chat/seller/{sellerId}', function(Request $request, $sellerId){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return redirect('/login')->with('error','Please login');
  $sellerId = \App\Models\Seller::userIdFor((int) $sellerId);
  $conv = \App\Models\Conversation::firstOrCreate(['buyer_id'=>$buyer['id'],'seller_id'=>$sellerId], ['subject'=>null]);
  \App\Models\Message::where('conversation_id',$conv->id)->where('sender_id','!=',$buyer['id'])->where('is_read',0)->update(['is_read'=>1]);
  $messages = \App\Models\Message::with('conversation')->where('conversation_id',$conv->id)->orderBy('created_at')->get();
  return view('buyer.chat-thread',['sellerId'=>(int)$sellerId,'messages'=>$messages]);
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
  if (! class_exists(\App\Support\BuyerNotifications::class)) return view('buyer.notifications',['items'=>[]]);
  // Merged feed (orders + messages + resolutions): slice the current page
  // manually so the full feed stays pageable.
  $feed = \App\Support\BuyerNotifications::for($buyer['id'], 200);
  $page = max(1, (int) $request->input('page', 1));
  $per = 10;
  $items = new \Illuminate\Pagination\LengthAwarePaginator(
    $feed->forPage($page, $per)->values(), $feed->count(), $per, $page,
    ['path' => $request->url(), 'query' => $request->query()]
  );
  return view('buyer.notifications',['items'=>$items]);
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
  return view('buyer.help', [
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
