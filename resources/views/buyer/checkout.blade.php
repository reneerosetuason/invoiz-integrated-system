@extends('buyer.layouts.website')
@section('content')
<link rel="stylesheet" href="{{ asset('assets/buyer/css/checkout.css') }}?v={{ filemtime(public_path('assets/buyer/css/checkout.css')) }}">
@php
    $single = $single ?? null;
    $checkoutMode = $checkoutMode ?? 'single';
    $sellerGroups = $sellerGroups ?? [];
    $claimedVouchers = $claimedVouchers ?? collect();
    $oldVoucherIds = array_map('intval', (array) old('voucher_claim_ids', []));
    if ($single) {
        $checkoutProduct = $products->first();
        $checkoutGroups = [(int) $checkoutProduct->seller_id => ['lines' => collect([[
            'product' => $checkoutProduct, 'qty' => $singleQty ?? 1, 'label' => $singleLabel ?? '',
            'line' => ($singleUnit ?? $checkoutProduct->price) * ($singleQty ?? 1),
        ]]), 'total' => ($singleUnit ?? $checkoutProduct->price) * ($singleQty ?? 1)]];
    } elseif ($checkoutMode === 'seller') {
        $checkoutGroups = [(int) $sellerId => ['lines' => $sellerLines, 'total' => $sellerLines->sum('line')]];
    } else {
        $checkoutGroups = $sellerGroups;
    }
    $subtotals = collect($checkoutGroups)->map(fn ($group) => (float) $group['total'])->all();
    try {
        $initialQuote = \App\Services\GcashEscrowService::quoteForStores($subtotals, $claimedVouchers->whereIn('id', $oldVoucherIds)->pluck('voucher'));
    } catch (\Illuminate\Validation\ValidationException $exception) {
        $initialQuote = \App\Services\GcashEscrowService::quoteForStores($subtotals, collect());
    }
    $accounts = collect($initialQuote['accounts'])->keyBy('seller_id');
    $itemCount = collect($checkoutGroups)->sum(fn ($group) => $group['lines']->sum('qty'));
@endphp
<div class="checkout-page">
    <header class="checkout-heading">
        <a class="checkout-back" href="{{ url('/cart') }}">← Back to cart</a>
        <h1>Checkout</h1>
        <p>Review your order and payment details.</p>
    </header>
    @if(session('error'))<p class="checkout-alert" role="alert">{{ session('error') }}</p>@endif
    @if($errors->any())
        <div class="checkout-alert" role="alert"><strong>Let’s check those details.</strong><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
    @endif
    <form method="POST" action="{{ url('/checkout') }}" enctype="multipart/form-data" id="checkout-form" class="checkout-grid" data-quote-url="{{ url('/checkout/gcash-quote') }}">
        @csrf
        <input type="hidden" name="checkout_mode" value="{{ $single ? 'single' : $checkoutMode }}">
        @if($single)
            <input type="hidden" name="single" value="{{ $single }}">
            <input type="hidden" name="qty" value="{{ $singleQty ?? 1 }}">
            <input type="hidden" name="variant_id" value="{{ $singleVariantId ?? '' }}">
        @elseif($checkoutMode === 'seller')
            <input type="hidden" name="seller_id" value="{{ $sellerId }}">
        @elseif($checkoutMode === 'selected')
            @foreach($sellerGroups as $group)
                @foreach($group['lines'] as $line)<input type="hidden" name="selected_item_ids[]" value="{{ $line['item']->id }}">@endforeach
            @endforeach
        @endif
        <div class="checkout-details">
            <section class="checkout-section checkout-address">
                <div class="checkout-section-heading"><h2>Delivery address</h2><a href="{{ url('/profile#saved-addresses') }}">Manage addresses</a></div>
                <label class="checkout-field-label" for="checkout-address">Delivery address</label>
                <select id="checkout-address" name="address_id" required>
                    <option value="">Choose a saved address</option>
                    @foreach($addresses as $address)
                        @php $addressText = implode(', ', array_filter([$address->address_line, $address->barangay, $address->city, $address->province, $address->region, $address->postal_code])); @endphp
                        <option value="{{ $address->id }}" data-recipient="{{ $address->recipient_name }}" data-address="{{ $addressText }}" data-phone="{{ $address->phone }}" @selected((string) old('address_id', $selectedAddressId ?? '') === (string) $address->id)>
                            {{ $address->recipient_name }} · {{ $address->city }}{{ $address->is_default ? ' · Default' : '' }}
                        </option>
                    @endforeach
                </select>
                <div class="checkout-address-preview" id="checkout-address-preview" hidden><strong data-recipient></strong><p data-address></p><span data-phone></span></div>
                @if($addresses->isEmpty())<p class="checkout-note">Add a delivery address in your profile to place this order.</p>@endif
            </section>
            <section class="checkout-section checkout-payment">
                <div class="checkout-section-heading"><h2>Payment method</h2></div>
                <div class="checkout-methods" data-ui role="radiogroup" aria-label="Payment method">
                    <label class="checkout-payment-choice">
                        <input type="radio" name="payment_method" value="cod" @checked(old('payment_method', 'cod') === 'cod')>
                        <span><strong>Cash on delivery</strong><small>Pay when your parcel arrives.</small></span>
                        <span class="checkout-method-mark" aria-hidden="true">₱</span>
                    </label>
                    <label class="checkout-payment-choice">
                        <input type="radio" name="payment_method" value="gcash" aria-controls="gcash-proof-fields" disabled @checked(old('payment_method') === 'gcash')>
                        <span><strong>GCash</strong><small>Scan the store’s QR. Upload your receipt.</small></span>
                        <span class="checkout-method-mark" aria-hidden="true">G</span>
                    </label>
                </div>
                <p class="checkout-cod-note" id="checkout-cod-note">Keep the exact amount ready for your rider.</p>
                <p id="gcash-quote-message" class="checkout-note" role="status" aria-live="polite"></p>
                <button type="button" id="gcash-quote-retry" class="checkout-text-button" hidden>Reload payment details</button>
                <div id="gcash-proof-fields" class="checkout-gcash-proof" @if(old('payment_method') !== 'gcash') hidden @endif>
                    <p class="checkout-payment-intro">Use your own GCash app to scan or import the receiving QR. Pay each store separately, then attach its receipt.</p>
                    <div id="gcash-store-payments">
                        @foreach($initialQuote['accounts'] as $account)@include('buyer.partials.checkout-gcash-account')@endforeach
                    </div>
                </div>
                <noscript><p class="checkout-alert">Enable JavaScript to load current GCash payment details and receipt uploads. You can still choose cash on delivery.</p></noscript>
            </section>
            <section class="checkout-section checkout-vouchers">
                <details @if($oldVoucherIds) open @endif>
                    <summary><span>Apply a voucher</span><span class="checkout-voucher-count">{{ $claimedVouchers->count() }} {{ $claimedVouchers->count() === 1 ? 'voucher' : 'vouchers' }}</span></summary>
                    <div class="checkout-voucher-body">
                        <p class="checkout-note">Choose up to one store, one platform and one shipping voucher.</p>
                        @forelse($claimedVouchers as $claim)
                            @php
                                $voucher = $claim->voucher;
                                $type = $voucher->discount_type ?? $voucher->type;
                                $value = $voucher->discount_value ?? $voucher->value;
                                $offer = $type === 'free_shipping' ? 'Free shipping' : ($type === 'percent' ? rtrim(rtrim(number_format((float) $value, 2), '0'), '.').'% off' : '₱'.number_format((float) $value, 2).' off');
                                $issuer = $voucher->seller_id ? \App\Models\Seller::nameFor(\App\Support\Vouchers::sellerUserId($voucher)) : 'Invoiz';
                            @endphp
                            <label class="checkout-voucher-choice"><input type="checkbox" name="voucher_claim_ids[]" value="{{ $claim->id }}" @checked(in_array((int) $claim->id, $oldVoucherIds))><span><strong>{{ $offer }}</strong><small>{{ $issuer }}@if($voucher->min_spend > 0) · Min. ₱{{ number_format($voucher->min_spend, 2) }}@endif</small></span></label>
                        @empty<p>No claimed vouchers apply to these items.</p>@endforelse
                        <a class="checkout-inline-link" href="{{ url('/vouchers') }}">Find more vouchers →</a>
                    </div>
                </details>
            </section>
        </div>
        <aside class="checkout-receipt" aria-label="Order summary">
            <div class="checkout-receipt-heading"><h2>Your order</h2><span>{{ $itemCount }} {{ $itemCount === 1 ? 'item' : 'items' }} · {{ count($checkoutGroups) }} {{ count($checkoutGroups) === 1 ? 'store' : 'stores' }}</span></div>
            <div class="checkout-order-items">
                @foreach($checkoutGroups as $sid => $group)
                    <div class="checkout-store-order"><h3>{{ $accounts->get((int) $sid)['store_name'] ?? \App\Models\Seller::nameFor($sid) }}</h3>
                        @foreach($group['lines'] as $line)
                            @php $product = $line['product']; $photo = $product->primary_image_url; @endphp
                            <div class="checkout-product">
                                @if($photo)<img src="{{ $photo }}" alt="{{ $product->name }}" width="64" height="72" loading="lazy">@else<span class="checkout-product-initial" aria-hidden="true">{{ mb_substr($product->name, 0, 1) }}</span>@endif
                                <div><p>{{ $product->name }}</p>@if($line['label'])<span>{{ $line['label'] }}</span>@endif<span>Qty {{ $line['qty'] }}</span></div>
                                <strong>₱{{ number_format($line['line'], 2) }}</strong>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
            <dl class="checkout-totals">
                <div><dt>Items subtotal</dt><dd data-checkout-subtotal>₱{{ number_format($initialQuote['subtotal'], 2) }}</dd></div>
                <div><dt>Delivery · {{ count($checkoutGroups) }} {{ count($checkoutGroups) === 1 ? 'store' : 'stores' }}</dt><dd data-checkout-shipping>₱{{ number_format($initialQuote['shipping_fee'], 2) }}</dd></div>
                <div data-checkout-discount-row @if($initialQuote['discount'] <= 0) hidden @endif><dt>Voucher savings</dt><dd data-checkout-discount>−₱{{ number_format($initialQuote['discount'], 2) }}</dd></div>
            </dl>
            <div class="checkout-grand-total" data-ui><span>Total to pay</span><strong data-checkout-total>₱{{ number_format($initialQuote['total'], 2) }}</strong></div>
            <p class="checkout-receipt-note" id="checkout-payment-note">Pay on delivery. Your rider collects this amount.</p>
            <button type="submit" class="checkout-place-order" @disabled($addresses->isEmpty())>Place {{ count($checkoutGroups) > 1 ? count($checkoutGroups).' orders' : 'order' }} <span aria-hidden="true">→</span></button>
            <p class="checkout-submit-note">Check your address and payment details before placing your order.</p>
        </aside>
    </form>
    <script id="checkout-initial-quote" type="application/json">@json($initialQuote)</script>
    <template id="gcash-account-template">@include('buyer.partials.checkout-gcash-account', ['account' => ['seller_id' => 0, 'store_name' => '', 'account_name' => '', 'amount' => 0, 'qr_url' => null, 'available' => false, 'unavailable_reason' => 'seller_qr_missing']])</template>
</div>
<script src="{{ asset('assets/buyer/js/checkout-payment.js') }}?v={{ filemtime(public_path('assets/buyer/js/checkout-payment.js')) }}" defer></script>
@endsection
