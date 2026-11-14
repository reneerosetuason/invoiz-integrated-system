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
Route::middleware(\App\Http\Middleware\EnsureBuyerWebContext::class)->group(function () {
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
  if(!$buyer) {
    if ($request->expectsJson() || $request->ajax()) {
      return response()->json(['message' => 'Please login to add items to cart', 'redirect' => '/login'], 401);
    }
    return redirect('/login')->with('error','Please login to add items to cart');
  }
  $request->validate(['qty' => ['nullable', 'integer', 'min:1', 'max:200'], 'variant_id' => ['nullable', 'integer', 'min:1']]);
  $product = Product::where('status', 'active')->find($id);
  if(!$product) {
    if ($request->expectsJson() || $request->ajax()) {
      return response()->json(['message' => 'Product not found'], 404);
    }
    return redirect('/cart')->with('error','Product not found');
  }
  $variant = Cart::purchaseVariant($product, $request->input('variant_id'));
  if ($product->variants()->where('status', 'active')->exists() && ! $variant) {
    $variant = $product->variants()->where('status', 'active')->where('stock', '>', 0)->first() ?? $product->variants()->where('status', 'active')->first();
  }
  if($error = Cart::addFor($buyer['id'], $product, $variant, (int)($request->input('qty') ?? 1))) {
    if ($request->expectsJson() || $request->ajax()) {
      return response()->json(['message' => $error], 422);
    }
    return redirect('/cart')->with('error', $error);
  }
  $product->trackCartAddition();
  $newCount = \App\Models\Cart::itemsFor($buyer['id'])->sum('quantity');
  if ($request->expectsJson() || $request->ajax()) {
    return response()->json(['success' => true, 'message' => 'Added to cart', 'cart_count' => $newCount]);
  }
  return redirect('/cart')->with('success','Added to cart');
});

Route::match(['get', 'post'], '/buy/{id}', function($id, Request $request){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return redirect('/login')->with('error','Please login to buy');
  $request->validate(['qty' => ['nullable', 'integer', 'min:1', 'max:200'], 'variant_id' => ['nullable', 'integer', 'min:1']]);
  $product = Product::where('status', 'active')->find($id);
  if(!$product) return redirect('/shop')->with('error','Product not found');
  $qty = max(1, min(200, $request->integer('qty', 1)));
  $variant = Cart::purchaseVariant($product, $request->input('variant_id'));
  if ($product->variants()->where('status', 'active')->exists() && ! $variant) {
    $variant = $product->variants()->where('status', 'active')->where('stock', '>', 0)->first() ?? $product->variants()->where('status', 'active')->first();
  }
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

Route::post('/checkout/gcash-quote', [\App\Http\Controllers\GcashEscrowController::class, 'quote']);
Route::get('/gcash/receiving-qr/{seller}', [\App\Http\Controllers\GcashEscrowController::class, 'qr'])->whereNumber('seller')->name('buyer.gcash.qr');
Route::get('/checkout', function(Request $request){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return redirect('/login')->with('error','Please login to checkout');
  $request->validate(['single' => ['nullable', 'integer', 'min:1'], 'qty' => ['nullable', 'integer', 'min:1', 'max:200'], 'variant_id' => ['nullable', 'integer', 'min:1'], 'ids' => ['nullable', 'string', 'max:2000', 'regex:/^\d+(,\d+)*$/']]);
  $claimedVouchers = \App\Models\VoucherClaim::with('voucher')
    ->where('buyer_id', $buyer['id'])->whereNull('used_at')
    ->whereHas('voucher', fn ($q) => $q->active()->where(fn ($x) => $x->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit')))
    ->latest('claimed_at')->get();
  $eligibleClaimsFor = function(array $sellerSubtotals) use ($claimedVouchers) {
    return $claimedVouchers->filter(function($claim) use ($sellerSubtotals) {
      // Web checkout creates one order per store. Platform offers retain
      // their existing first-order scope; store offers use their own store.
      $subtotals = $claim->voucher->seller_id ? $sellerSubtotals : array_slice($sellerSubtotals, 0, 1, true);
      return ! \App\Support\Shipping::resolveVoucher($claim->voucher, array_sum($subtotals), array_keys($subtotals), $subtotals)['error'];
    })->values();
  };
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
    $claimedVouchers = $eligibleClaimsFor([(int) $p->seller_id => $unit * $qty]);
    return view('buyer.checkout',['products'=>collect([$p]),'single'=>$p->id,'singleQty'=>$qty,'singleUnit'=>$unit,'singleVariantId'=>$variant ? $variant->id : null,'singleLabel'=>Cart::labelFor($variant),'cartItems'=>collect(),'checkoutMode'=>'single','addresses'=>$addresses,'selectedAddressId'=>$selectedAddressId,'claimedVouchers'=>$claimedVouchers]);
  }
  if($request->filled('seller') || session()->has('checkout_seller_id')){
    $sellerId = $request->input('seller') ?? session('checkout_seller_id');
    $lines = Cart::linesFor($buyer['id'])->filter(fn($l) => (int)$l['product']->seller_id === (int)$sellerId)->values();
    if($lines->isEmpty()) return redirect('/cart')->with('error','No items from this seller');
    $cartItems = Cart::itemsFor($buyer['id'])->get();
    $pIds = $lines->pluck('product.id')->all();
    $products = Product::whereIn('id',$pIds)->get();
    $claimedVouchers = $eligibleClaimsFor([(int) $sellerId => (float) $lines->sum('line')]);
    return view('buyer.checkout',['products'=>$products,'cartItems'=>$cartItems,'single'=>null,'checkoutMode'=>'seller','sellerId'=>$sellerId,'sellerLines'=>$lines,'addresses'=>$addresses,'selectedAddressId'=>$selectedAddressId,'claimedVouchers'=>$claimedVouchers]);
  }
  if($request->filled('all') || session()->has('checkout_all')){
    $lines = Cart::linesFor($buyer['id']);
    if($lines->isEmpty()) return redirect('/cart')->with('error','Cart is empty');
    $bySeller = $lines->groupBy(fn($l) => $l['product']->seller_id);
    $sellerGroups = [];
    foreach($bySeller as $sid => $sLines){ $sellerGroups[$sid] = ['lines'=>$sLines->values(),'total'=>$sLines->sum('line')]; }
    $grandTotal = collect($sellerGroups)->sum('total');
    $claimedVouchers = $eligibleClaimsFor(collect($sellerGroups)->map(fn($group) => (float) $group['total'])->all());
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
    $claimedVouchers = $eligibleClaimsFor(collect($sellerGroups)->map(fn($group) => (float) $group['total'])->all());
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
    'payment_method' => ['required', 'in:cod,gcash'],
    'gcash_proof' => ['prohibited_unless:payment_method,gcash', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'gcash_proofs' => ['prohibited_unless:payment_method,gcash', 'nullable', 'array', 'max:20'],
            'gcash_proofs.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
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
  $proofPath = [];
  try {
    \Illuminate\Support\Facades\DB::transaction(function () use ($buyer, $addr, $request, $checkoutMode, &$ordersCreated, &$proofPath) {

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
      \App\Services\GcashEscrowService::storeCheckoutProofs($request, [(int) $product->seller_id], $proofPath);
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

      \App\Models\OrderItem::create(['order_id'=>$order->id,'product_id'=>$product->id,'product_variant_id'=>$variant?->id,'seller_id'=>$product->seller_id,'product_name'=>$product->name,'variant_label'=>Cart::labelFor($variant),'quantity'=>$qty,'price'=>$unit,'unit_price'=>$unit,'total_price'=>$subtotal]);
      foreach ($stack['perVoucher'] ?? [] as $vid => $amt) {
        \App\Models\OrderVoucher::create(['order_id'=>$order->id,'voucher_id'=>$vid,'discount_amount'=>round(($amt['merch'] ?? 0) + ($amt['waived'] ?? 0), 2)]);
      }
      foreach ($claims as $c) {
        \App\Models\Voucher::whereKey($c->voucher_id)->increment('used_count');
        $c->update(['order_id' => $order->id, 'used_at' => now()]);
      }
      \App\Models\Payment::create(['order_id'=>$order->id,'method'=>$request->input('payment_method') === 'gcash' ? 'gcash' : 'cash_on_delivery','status'=>'pending','amount'=>$total] + (isset($proofPath[$product->seller_id]) ? ['proof_path' => $proofPath[$product->seller_id]] : []));
      \App\Support\Payments::recordCheckout($order);
      app(\App\Services\OrderShipmentService::class)->ensure($order);
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
      \App\Services\GcashEscrowService::storeCheckoutProofs($request, array_keys(collect($bySeller)->all()), $proofPath);
      $sellerSubtotals = collect($bySeller)->map(fn($sLines) => (float) $sLines->sum('line'))->all();
      $checkoutStack = \App\Support\Vouchers::resolveStack($allVouchers, array_sum($sellerSubtotals), array_keys($sellerSubtotals), $sellerSubtotals);
      if ($checkoutStack['error']) throw new \Exception($checkoutStack['error']);
      $allocations = \App\Support\Vouchers::allocateForOrders($allVouchers, $checkoutStack, $sellerSubtotals);
      $redemptions = [];
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
        $waived = (float) ($checkoutStack['waived'][$groupSid] ?? 0);
        $merch = round(array_sum($allocations[$groupSid] ?? []) - $waived, 2);
        $shipping = \App\Models\Order::SHIPPING_FEE_PER_SELLER;
        $total = round($subtotal - $merch + $shipping - $waived, 2);
        $order = \App\Models\Order::create(['seller_id'=>(int) $groupSid,'buyer_id'=>$buyer['id'],'address_id'=>$addr->id,'shipping_address'=>$addr->toShippingSnapshot(),'total_amount'=>$total,'total'=>$total,'sub_total'=>$subtotal,'discount_total'=>round($merch + $waived, 2),'shipping_fee'=>round($shipping - $waived, 2),'status'=>'pending']);
        \App\Models\OrderStatusHistory::create(['order_id'=>$order->id,'from_status'=>null,'to_status'=>'pending','note'=>'Order placed.']);

        foreach($sLines as $l){
          \App\Models\OrderItem::create(['order_id'=>$order->id,'product_id'=>$l['product']->id,'product_variant_id'=>$l['variant']?->id,'seller_id'=>$l['product']->seller_id,'product_name'=>$l['product']->name,'variant_label'=>$l['label'],'quantity'=>$l['qty'],'price'=>$l['unit'],'unit_price'=>$l['unit'],'total_price'=>$l['line']]);
          $l['item']->delete();
        }
        foreach ($allocations[$groupSid] ?? [] as $vid => $amount) {
          if ($amount <= 0) continue;
          $redemptions[$vid] ??= $order->id;
          \App\Models\OrderVoucher::create(['order_id'=>$order->id,'voucher_id'=>$vid,'redemption_order_id'=>$redemptions[$vid],'discount_amount'=>$amount]);
          if (! isset($consumedClaimIds[$vid])) {
            \App\Models\Voucher::whereKey($vid)->increment('used_count');
            $consumedClaimIds[$vid] = true;
            if (isset($claimsByVoucher[$vid])) $claimsByVoucher[$vid]->update(['order_id' => $order->id, 'used_at' => now()]);
          }
        }
        $groupIndex++;
        \App\Models\Payment::create(['order_id'=>$order->id,'method'=>$request->input('payment_method') === 'gcash' ? 'gcash' : 'cash_on_delivery','status'=>'pending','amount'=>$total] + (isset($proofPath[$groupSid]) ? ['proof_path' => $proofPath[$groupSid]] : []));
        \App\Support\Payments::recordCheckout($order);
      app(\App\Services\OrderShipmentService::class)->ensure($order);
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
  } catch(\Throwable $e){
    if ($proofPath) \Illuminate\Support\Facades\Storage::disk('private')->delete(array_values($proofPath));
    if ($e instanceof \Illuminate\Validation\ValidationException) throw $e;
    \Log::error('Checkout failed: '.$e->getMessage());
    $checkoutError = get_class($e) === \Exception::class ? $e->getMessage() : 'Please review your order and try again.';
    $receiptHint = $request->input('payment_method') === 'gcash' ? ' Select your GCash receipt again before retrying.' : '';
    return back()->withInput()->with('error', 'Checkout failed: '.$checkoutError.$receiptHint);
  }
  session()->forget(['checkout_single','checkout_seller_id','checkout_all','checkout_selected']);
  $paymentMessage = $request->input('payment_method') === 'gcash'
    ? 'Your GCash receipt has been submitted for seller review.' : 'Cash on Delivery - thank you!';
  return redirect('/orders')->with('success', ($ordersCreated > 1 ? "Placed {$ordersCreated} orders. " : 'Order placed. ').$paymentMessage);
});

Route::get('/orders/{order}/payment-proof', [\App\Http\Controllers\PaymentProofController::class, 'buyer'])->whereNumber('order')->name('buyer.orders.payment-proof');

Route::get('/store/{id}', function($id, Request $request){
  $sellerId = \App\Models\Seller::userIdFor((int) $id);
  $sellerUser = \App\Models\User::find($sellerId);
  // Accept sellers.id too (admin/seller links) â€” resolve to the same store.
  if(! $sellerUser) {
    $s = \App\Models\Seller::find($sellerId);
    if($s) { $sellerId = (int) $s->user_id; $sellerUser = \App\Models\User::find($sellerId); }
  }
  $store = \App\Models\Seller::where('user_id', $sellerId)->first();
  if(!$sellerUser || !$store) return redirect('/shop')->with('error','Store not found');
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
  $tab = in_array($request->input('tab'), ['reviews','vouchers'], true) ? $request->input('tab') : 'products';
  $storeVouchers = \App\Models\Voucher::active()->forStore($store)
    ->when(!$store->isApproved() || !$sellerUser->isActive(), fn ($query) => $query->whereRaw('1 = 0'))
    ->latest()->paginate(12, ['*'], 'voucher_page')->withQueryString();
  $voucherClaims = !empty($buyer['id']) ? \App\Models\VoucherClaim::where('buyer_id', $buyer['id'])
    ->whereIn('voucher_id', $storeVouchers->pluck('id'))->get()->keyBy('voucher_id') : collect();
  $claimedIds = $voucherClaims->keys()->map(fn ($id) => (int) $id)->all();
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
  return view('buyer.store', compact('sellerId','sellerUser','name','products','reviews','rating','followers','following','tab','buyer','storeLogo','favoriteIds','vacation','storeVouchers','voucherClaims','claimedIds') + ['storeSearch'=>$request->input('search',''),'storeSort'=>$sort]);
});

Route::post('/store/{id}/follow', function($id, Request $request){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return redirect('/login')->with('error','Please login to follow stores');
  if (! class_exists(\App\Models\StoreFollow::class)) return back()->with('error','Follows are unavailable.');
  $sellerId = \App\Models\Seller::userIdFor((int)$id);
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
Route::get('/profile/account-deletion', [\App\Http\Controllers\Buyer\AccountDeletionController::class, 'show'])->middleware(['auth', 'account.active'])->name('buyer.account-deletion');
Route::post('/profile/account-deletion/verify', [\App\Http\Controllers\Buyer\AccountDeletionController::class, 'verify'])->middleware(['auth', 'account.active', 'throttle:5,1'])->name('buyer.account-deletion.verify');
Route::post('/profile/delete-account', [\App\Http\Controllers\Buyer\AccountDeletionController::class, 'store'])->middleware(['auth', 'account.active', 'throttle:5,1'])->name('buyer.profile.delete-request');
Route::post('/profile/cancel-deletion', [\App\Http\Controllers\Buyer\AccountDeletionController::class, 'cancel'])->middleware(['auth', 'account.active', 'throttle:5,1'])->name('buyer.profile.cancel-deletion');

Route::get('/orders', function(Request $request){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return redirect('/login')->with('error','Please login to view orders');
  $tab = $request->input('tab','all');
  $groups = ['pending'=>['pending','confirmed','processing','ready_for_delivery'],'out_for_delivery'=>['out_for_delivery','shipped'],'delivered'=>['delivered'],'cancelled'=>['cancelled']];
  $q = \App\Models\Order::with(['items.product', 'returnRequests', 'deliveries'])->where('buyer_id',$buyer['id']);
  if($tab === 'return'){
    $q->where(function($sq){
      $sq->whereIn('status', ['return_requested', 'returned', 'refunded'])
         ->orWhereHas('returnRequests');
    });
  } elseif($tab === 'delivered') {
    $q->where(function($sq){
      $sq->where('status', 'delivered')
         ->orWhereHas('deliveries', fn($dq) => $dq->where('status', 'delivered'));
    });
  } elseif($tab!=='all' && isset($groups[$tab])) $q->whereIn('status',$groups[$tab]);
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
  $order = \App\Models\Order::with([
    'items.product',
    'payment',
    'deliveries.statusLogs',
    'deliveries.logisticsCenter',
    'deliveries.destinationCenter',
    'deliveries.pickupRequest',
    'statusHistories',
  ])->where('buyer_id',$buyer['id'])->findOrFail($id);
  $reviews = \App\Models\Review::where('buyer_id',$buyer['id'])->where('order_id',$order->id)->get()->keyBy('product_id');
  $returns = \App\Models\ReturnRequest::where('order_id', $order->id)->where('buyer_id', $buyer['id'])->get()->groupBy('order_item_id');
  $timeline = \App\Support\CustomerTracking::timeline($order, $order->deliveries->first(), \App\Support\CustomerTracking::AUDIENCE_BUYER);
  return view('buyer.order', ['order'=>$order, 'reviews'=>$reviews, 'returns'=>$returns, 'timeline'=>$timeline]);
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
Route::post('/orders/{id}/returns', function($id, Request $request){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return redirect('/login')->with('error','Please login');

  $validated = $request->validate([
    'order_item_id' => ['required', 'integer', 'min:1'],
    'reason' => ['required', 'in:damaged,wrong_item,not_as_described,changed_mind,ordered_wrong_size,ordered_wrong_color,received_wrong_size,received_wrong_color,other'],
    'reason_details' => ['nullable', 'required_if:reason,other', 'string', 'max:300'],
    'quantity' => ['required', 'integer', 'min:1', 'max:9999'],
    'evidence' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,mp4,mov,avi,webm,quicktime', 'max:51200'],
  ], [
    'evidence.required' => 'Please provide photo or video evidence for your return request.',
    'evidence.mimes' => 'Evidence must be a photo (JPG, PNG, WEBP) or video (MP4, MOV, WEBM).',
    'evidence.max' => 'Evidence file must not exceed 50MB.',
  ]);

  if ($validated['reason'] === 'other' && strlen(trim($validated['reason_details'] ?? '')) < 10) {
    return back()->with('error', 'Please provide at least 10 characters explaining the other reason.');
  }

  try {
    \Illuminate\Support\Facades\DB::transaction(function() use ($buyer, $validated, $id, $request) {
      $order = \App\Models\Order::with('items')->where('buyer_id', $buyer['id'])->lockForUpdate()->findOrFail($id);
      if ($order->status !== 'delivered') {
        throw new \Exception('You can only request a return after the order is delivered.');
      }
      $item = $order->items->where('id', (int) $validated['order_item_id'])->first();
      if (! $item) {
        throw new \Exception('The selected order item was not found.');
      }
      $windowDays = max(1, (int) \App\Models\Setting::get('return_window_days', 7));
      $deliveredAt = $order->statusHistories()->where('to_status', 'delivered')->oldest('created_at')->value('created_at');
      if (! $deliveredAt) {
        $deliveredAt = $order->deliveries()->where('seller_id', $item->seller_id)->where('status', 'delivered')->max('delivered_at');
      }
      $deliveredAt = \Illuminate\Support\Carbon::parse($deliveredAt ?: $order->created_at);
      if ($deliveredAt->lt(now()->subDays($windowDays))) {
        throw new \Exception('The return window (' . $windowDays . ' days after delivery) has closed.');
      }

      $open = \App\Models\ReturnRequest::where('order_id', $order->id)
        ->where('order_item_id', $item->id)
        ->whereIn('status', ['requested', 'approved', 'item_received'])
        ->first();
      if ($open) {
        throw new \Exception('You already have an open return request for this item.');
      }

      $completed = \App\Models\ReturnRequest::where('order_item_id', $item->id)->where('status', 'refunded');
      $remaining = max(0, (int) $item->quantity - (int) (clone $completed)->sum('quantity'));
      $qty = (int) ($validated['quantity'] ?? $remaining);
      if ($qty < 1 || $qty > $remaining) {
        throw new \Exception('The quantity exceeds the remaining returnable items.');
      }

      $sellerSubtotal = $order->sellerSubtotal((int) $item->seller_id);
      $discount = $sellerSubtotal > 0
        ? $order->voucherDiscountForSeller((int) $item->seller_id) * $item->lineTotal / $sellerSubtotal
        : 0;
      $lineNet = round(max(0, $item->lineTotal - $discount), 2);
      $remainingRefund = round(max(0, $lineNet - (float) (clone $completed)->get()->sum(fn ($r) => $r->merchandiseRefundAmount())), 2);
      $refund = $qty === $remaining
        ? $remainingRefund
        : min(round($lineNet * $qty / $item->quantity, 2), $remainingRefund);

      $evidenceFile = $request->file('evidence');
      $evidencePath = null;
      $evidenceType = null;
      if ($evidenceFile) {
        $mime = $evidenceFile->getMimeType() ?: '';
        $evidenceType = str_starts_with($mime, 'video/') ? 'video' : 'image';
        $evidencePath = $evidenceFile->store('returns', 'public');
      }

      $ret = \App\Models\ReturnRequest::create([
        'order_id' => $order->id,
        'order_item_id' => $item->id,
        'buyer_id' => $buyer['id'],
        'seller_id' => $item->seller_id,
        'product_id' => $item->product_id,
        'quantity' => $qty,
        'reason' => $validated['reason'],
        'reason_details' => $validated['reason_details'] ?? null,
        'evidence_path' => $evidencePath,
        'evidence_type' => $evidenceType,
        'refund_amount' => $refund,
        'status' => 'requested',
      ]);

      \App\Services\ReturnSettlementService::ensure($ret);

      \App\Models\SellerNotification::create([
        'seller_id' => $item->seller_id,
        'order_id' => $order->id,
        'product_id' => $item->product_id,
        'type' => 'return_requested',
        'title' => 'New return request',
        'body' => 'Return #' . $ret->id . ' for ' . $qty . ' × ' . ($item->product_name ?: 'purchased item') . '. Review it in Returns & Refunds.',
        'is_read' => false,
      ]);
    });
    return redirect('/orders/' . $id)->with('success', 'Return request submitted successfully. The seller will review it shortly.');
  } catch (\Throwable $e) {
    return redirect('/orders/' . $id)->with('error', $e->getMessage());
  }
})->whereNumber('id');

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
    $contact = \App\Models\User::find($selId);
    abort_unless($contact && \App\Support\MessagingAccess::canContact(\App\Models\User::findOrFail($userId), $contact), 403);
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
  abort_unless(\App\Support\MessagingAccess::canContact(\App\Models\User::findOrFail($buyer['id']), \App\Models\User::findOrFail($sellerId)), 403);
  $blocked = \App\Models\ChatBlock::blocksBetween(\App\Models\User::findOrFail($buyer['id']), \App\Models\User::findOrFail($sellerId));
  $conv = \App\Models\Conversation::firstOrCreate(['buyer_id'=>$buyer['id'],'seller_id'=>$sellerId], ['subject'=>null]);
  \App\Models\Message::where('conversation_id',$conv->id)->where('sender_id','!=',$buyer['id'])->where('is_read',0)->update(['is_read'=>1]);
  $messages = \App\Models\Message::with('conversation')->where('conversation_id',$conv->id)->orderBy('created_at')->get();
  return view('buyer.chat-thread',['sellerId'=>(int)$sellerId,'messages'=>$messages,'blocked'=>$blocked]);
});

// Buyer blocks/unblocks a seller in the marketplace chat (admins exempt).
Route::post('/chat/block/{sellerId}', function(Request $request, $sellerId){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return response()->json(['error'=>'Login required'],401);
  $sellerId = \App\Models\Seller::userIdFor((int) $sellerId);
  $buyerUser = \App\Models\User::findOrFail($buyer['id']);
  $sellerUser = \App\Models\User::findOrFail($sellerId);
  if ($buyerUser->is_admin || $sellerUser->is_admin) {
    return response()->json(['error'=>'Platform support cannot be blocked.'], 422);
  }
  $blocked = $request->boolean('blocked');
  if ($blocked) {
    \App\Models\ChatBlock::firstOrCreate(['blocker_id'=>$buyerUser->id,'blocked_id'=>$sellerUser->id]);
  } else {
    \App\Models\ChatBlock::where('blocker_id',$buyerUser->id)->where('blocked_id',$sellerUser->id)->delete();
  }
  return response()->json(['ok'=>true,'blocked'=>$blocked]);
});

Route::post('/chat/send/{sellerId}', function(Request $request, $sellerId){
  $buyer = session('buyer') ?? (Auth::check() ? ['id'=>Auth::id()] : null);
  if(!$buyer) return response()->json(['error'=>'Login required'],401);
  $sellerId = \App\Models\Seller::userIdFor((int) $sellerId);
  abort_unless(\App\Support\MessagingAccess::canContact(\App\Models\User::findOrFail($buyer['id']), \App\Models\User::findOrFail($sellerId)), 403);
  abort_if(\App\Models\ChatBlock::blocksBetween(\App\Models\User::findOrFail($buyer['id']), \App\Models\User::findOrFail($sellerId)), 403, 'You can no longer exchange messages with this user.');
  $request->validate(['body'=>['required','string','max:2000', new \App\Rules\CleanMessage]]);
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
    $orders = \App\Models\Order::with(['items.product', 'seller'])
      ->where('buyer_id', $buyer['id'])
      ->latest()
      ->take(30)
      ->get();
    $complaints = \App\Models\Complaint::with(['order.items', 'seller', 'orderItem', 'product'])
      ->where('buyer_id', $buyer['id'])
      ->latest()
      ->take(15)
      ->get();
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
    'subject'       => 'nullable|string|max:150',
    'description'   => 'required|string|max:2000',
    'order_id'      => 'required|integer|exists:orders,id',
    'order_item_id' => 'nullable|integer|exists:order_items,id',
    'seller_id'     => 'nullable|integer',
  ]);
  $orderId = (int) $data['order_id'];
  $order = \App\Models\Order::with(['items.product', 'seller'])->where('id', $orderId)->where('buyer_id', $buyer['id'])->first();
  if (! $order) {
    return back()->with('error','That order does not belong to your account.');
  }

  $orderItemId = $data['order_item_id'] ?? null;
  $orderItem = null;
  if ($orderItemId) {
    $orderItem = $order->items->firstWhere('id', (int) $orderItemId);
    if (! $orderItem) {
      $orderItemId = null;
    }
  }

  $productId = $orderItem?->product_id;

  // Resolve canonical Seller ID
  $sellerId = null;
  if (! empty($data['seller_id'])) {
    $sCandidate = \App\Models\Seller::where('id', (int) $data['seller_id'])->orWhere('user_id', (int) $data['seller_id'])->first();
    if ($sCandidate) $sellerId = $sCandidate->id;
  }
  if (! $sellerId && $orderItem && $orderItem->seller_id) {
    $sCandidate = \App\Models\Seller::where('user_id', (int) $orderItem->seller_id)->orWhere('id', (int) $orderItem->seller_id)->first();
    if ($sCandidate) $sellerId = $sCandidate->id;
  }
  if (! $sellerId) {
    if ($order->seller_id) {
      $sCandidate = \App\Models\Seller::where('id', (int) $order->seller_id)->orWhere('user_id', (int) $order->seller_id)->first();
      if ($sCandidate) $sellerId = $sCandidate->id;
    } elseif ($order->items->isNotEmpty()) {
      $firstSellerId = (int) $order->items->first()->seller_id;
      $sCandidate = \App\Models\Seller::where('user_id', $firstSellerId)->orWhere('id', $firstSellerId)->first();
      if ($sCandidate) $sellerId = $sCandidate->id;
    }
  }

  $seller = $sellerId ? \App\Models\Seller::find($sellerId) : null;
  $storeName = $seller?->store_name ?? $order->store_name_display;
  $orderNum = $order->order_number ?? $order->id;

  if ($orderItem) {
    $subject = "Order #{$orderNum} ({$orderItem->product_name}) — Store: {$storeName}";
  } else {
    $subject = "Order #{$orderNum} — Store: {$storeName}";
  }
  $subject = \Illuminate\Support\Str::limit($subject, 150, '...');

  \App\Models\Complaint::create([
    'buyer_id'      => $buyer['id'],
    'order_id'      => $orderId,
    'order_item_id' => $orderItemId,
    'product_id'    => $productId,
    'seller_id'     => $sellerId,
    'subject'       => $subject,
    'description'   => $data['description'],
    'type'          => 'order',
    'status'        => 'open',
  ]);
  return redirect('/help#reports')->with('success','Your report for Order #'.$orderNum.' (Store: '.$storeName.') has been sent to our support queue.');
})->name('help.complaint');

});
