@extends('buyer.layouts.website')
@section('content')
<link rel="stylesheet" href="{{ asset('assets/buyer/css/checkout.css') }}">
@php
  $buyer = \App\Support\BuyerSession::current();
  $single = $single ?? null;
  $cartItems = $cartItems ?? collect();
  $checkoutMode = $checkoutMode ?? 'single';
  $sellerGroups = $sellerGroups ?? [];
  $grandTotal = $grandTotal ?? 0;
  $sellerQtyMap = $sellerQtyMap ?? [];
  $claimedVouchers = $claimedVouchers ?? collect();
@endphp
@if(session('error'))<div style="background:#fef2f2;color:#991b1b;padding:10px;border-radius:8px;border:1px solid #fecaca;margin-bottom:10px;font-size:13px;font-weight:600">{{ session('error') }}</div>@endif
<h2 style="font-weight:800">Checkout</h2>
<div class="checkout-grid">
  <div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:16px">
    <h3 style="margin:0 0 10px;font-size:14px">Payment method</h3>
    <form method="POST" action="/checkout">
      @csrf
      @if($single)
        <input type="hidden" name="checkout_mode" value="single">
        <input type="hidden" name="single" value="{{ $single }}">
        <input type="hidden" name="qty" value="{{ $singleQty ?? 1 }}">
        <input type="hidden" name="variant_id" value="{{ $singleVariantId ?? '' }}">
      @elseif($checkoutMode === 'seller')
        <input type="hidden" name="checkout_mode" value="seller">
        <input type="hidden" name="seller_id" value="{{ $sellerId ?? '' }}">
      @elseif($checkoutMode === 'selected')
        <input type="hidden" name="checkout_mode" value="selected">
        @foreach($sellerGroups as $group)
          @foreach($group['lines'] as $line)
            <input type="hidden" name="selected_item_ids[]" value="{{ $line['item']->id }}">
          @endforeach
        @endforeach
      @else
        <input type="hidden" name="checkout_mode" value="all">
      @endif
        <div style="margin:4px 0 16px;padding:14px;border:1px solid var(--border);border-radius:12px">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px">
            <h3 style="margin:0;font-size:14px">Delivery address</h3>
            <a href="{{ url('/profile#saved-addresses') }}" style="font-size:11px;color:var(--green);font-weight:700;text-decoration:none">Manage addresses</a>
          </div>
          <select name="address_id" required style="width:100%;padding:11px 12px;border:1px solid var(--border);border-radius:10px;font-size:13px;background:#fff">
            <option value="">Select a delivery address</option>
            @forelse($addresses as $address)
              @php
                $addressParts = array_filter([$address->address_line, $address->barangay, $address->city, $address->province, $address->region, $address->postal_code], fn($part) => filled($part));
              @endphp
              <option value="{{ $address->id }}" @selected((string)old('address_id', $selectedAddressId ? $selectedAddressId : null) === (string)$address->id) @if($address->is_default) data-default="1" @endif>
                {{ $address->recipient_name }} — {{ implode(', ', $addressParts) }} @if($address->is_default) <span style="color:#0F766E;font-weight:700">(Default)</span>@endif {{ $address->phone }}
              </option>
            @empty
              <option disabled>No saved addresses. Add one in your profile first.</option>
            @endforelse
          </select>
          @error('address_id')<div style="margin-top:8px;color:#B91C1C;font-size:11px;font-weight:600">{{ $message }}</div>@enderror
        </div>
      <label style="display:flex;align-items:center;gap:10px;padding:12px;border:1px solid var(--green);border-radius:10px;margin-bottom:10px;cursor:pointer;background:#f0fdf4">
        <input type="radio" name="payment_method" value="cod" checked style="accent-color:var(--green)">
        <img src="https://cdn-icons-png.flaticon.com/512/2168/2168741.png" alt="COD" style="width:28px;height:28px;object-fit:contain;background:#fff;border-radius:6px;padding:3px;border:1px solid var(--border)">
        <span style="font-weight:700">Cash on Delivery (COD)</span>
        <span style="margin-left:auto;color:var(--green);font-size:11px;font-weight:700"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-3px"><polyline points="20 6 9 17 4 12"/></svg> Selected</span>
      </label>
      <div style="margin-top:12px;padding:10px;background:#f0fdf4;border:1px solid var(--green);border-radius:8px;font-size:11px;color:var(--green-dark)">COD only: Pay when your order is delivered.</div>
      <div style="margin-top:10px">
        @php
          $shipVouchers = collect();
          $merchVouchers = collect();
          foreach($claimedVouchers as $claim){
            $v = $claim->voucher;
            if(!$v) continue;
            if((($v->discount_type ?? $v->type) === 'free_shipping')){ $shipVouchers->push($claim); } else { $merchVouchers->push($claim); }
          }
          $oldVoucherIds = array_map('intval', (array) old('voucher_claim_ids', []));
        @endphp
        <label style="font-size:12px;font-weight:700">Vouchers <span style="font-weight:400;color:var(--text2)">(optional — up to one store, one platform and one shipping)</span></label>
        @foreach([['Shipping voucher', $shipVouchers], ['Merchandise vouchers', $merchVouchers]] as [$groupLabel, $groupClaims])
          @if($groupClaims->isNotEmpty())
            <div style="font-size:11px;font-weight:700;color:var(--text2);margin:8px 0 2px">{{ $groupLabel }}</div>
            @foreach($groupClaims as $claim)
              @php
                $voucher = $claim->voucher;
                $voucherType = $voucher->discount_type ?? $voucher->type;
                $voucherValue = $voucher->discount_value ?? $voucher->value;
                $voucherText = $voucherType === 'free_shipping' ? 'Free shipping' : ($voucherType === 'percent' ? rtrim(rtrim(number_format((float)$voucherValue,2),'0'),'.').'% off' : '₱'.number_format((float)$voucherValue,2).' off');
                $voucherSeller = $voucher->seller_id ? 'Store' : 'Platform';
                if($voucher->seller_id){ try { $voucherSeller = \App\Models\Seller::nameFor(\App\Support\Vouchers::sellerUserId($voucher)); } catch (\Throwable $e) {} }
              @endphp
              <label style="display:flex;gap:8px;align-items:flex-start;font-size:13px;padding:6px 0;cursor:pointer">
                <input type="checkbox" name="voucher_claim_ids[]" value="{{ $claim->id }}" @checked(in_array((int)$claim->id, $oldVoucherIds))>
                <span>{{ $voucherText }} · {{ $voucherSeller }}@if($voucher->min_spend > 0) · Min ₱{{ number_format($voucher->min_spend,2) }}@endif</span>
              </label>
            @endforeach
          @endif
        @endforeach
        @if($claimedVouchers->isEmpty())
          <div style="font-size:12px;color:var(--text2);margin-top:8px">No claimed vouchers apply to the selected items.</div>
        @endif
        <div style="font-size:11px;color:var(--text2);margin-top:5px">Claim offers from <a href="{{ url('/vouchers') }}" style="color:var(--green);font-weight:700">the vouchers page</a>; no voucher code is needed.</div>
        <div style="font-size:11px;color:var(--text2);margin-top:4px">Discount vouchers cut the item total · free-shipping vouchers waive the ₱50/shop delivery fee. Applied when you place the order.</div>
      </div>
      <button type="submit" @disabled($addresses->isEmpty()) style="width:100%;margin-top:14px;padding:12px;background:{{ $addresses->isEmpty() ? '#9ca3af' : 'var(--green)' }};color:#fff;border:none;border-radius:var(--radius);font-weight:800;cursor:{{ $addresses->isEmpty() ? 'not-allowed' : 'pointer' }};font-size:13px">
        @if(in_array($checkoutMode, ['all','selected']))
          Place {{ count($sellerGroups) }} Order(s) — ₱{{ number_format($grandTotal + count($sellerGroups) * \App\Models\Order::SHIPPING_FEE_PER_SELLER,2) }}
        @else
          Place Order
        @endif
      </button>
    </form>
  </div>
  <div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:16px">
    <h3 style="margin:0 0 10px;font-size:14px">Order summary</h3>
    @if(in_array($checkoutMode, ['all', 'selected']))
      @php $orderNum = 1; @endphp
      @foreach($sellerGroups as $sid => $group)
        <div style="margin-bottom:12px;{{ !$loop->last ? 'padding-bottom:12px;border-bottom:1px solid var(--border-light)' : '' }}">
          @php $coStore = ''; try { $coStore = \App\Models\Seller::nameFor($sid); } catch (\Throwable $e) {} @endphp
          <div style="font-weight:700;font-size:12px;color:var(--green);margin-bottom:6px">Order {{ $orderNum }}@if($coStore && $coStore !== 'Store') — {{ $coStore }}@endif</div>
          @php $orderNum++; @endphp
          @foreach($group['lines'] as $gl)
            @php $p = $gl['product']; $qty = $gl['qty']; @endphp
            <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:12px">
              <span>{{ $p->name }}@if($gl['label']) <span style="color:var(--text3)">({{ $gl['label'] }})</span>@endif × {{ $qty }}</span>
              <b style="color:var(--green)">₱{{ number_format($gl['line'],2) }}</b>
            </div>
          @endforeach
          <div style="display:flex;justify-content:space-between;margin-top:4px;font-weight:700;font-size:12px;border-top:1px dashed var(--border);padding-top:4px">
            <span>Subtotal</span>
            <span style="color:var(--green)">₱{{ number_format($group['total'],2) }}</span>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text2)">
            <span>Shipping (1 shop)</span>
            <span>₱{{ number_format(\App\Models\Order::SHIPPING_FEE_PER_SELLER,2) }}</span>
          </div>
        </div>
      @endforeach
      <div style="display:flex;justify-content:space-between;margin-top:8px;font-weight:800;font-size:14px;border-top:2px solid var(--green);padding-top:8px">
        <span>Grand Total ({{ count($sellerGroups) }} orders, incl. shipping)</span>
        <span style="color:var(--green)">₱{{ number_format($grandTotal + count($sellerGroups) * \App\Models\Order::SHIPPING_FEE_PER_SELLER,2) }}</span>
      </div>
    @else
      @php
        $total = 0;
        if($single){
          $total = ($singleUnit ?? $products->first()->price) * ($singleQty ?? 1);
        } elseif($checkoutMode === 'seller'){
          $total = isset($sellerLines) ? $sellerLines->sum('line') : 0;
        } else {
          $total = isset($sellerGroups) ? collect($sellerGroups)->sum('total') : 0;
        }
      @endphp
      @if($single)
        @foreach($products as $p)
          <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border-light);font-size:12px">
            <span>{{ $p->name }}@if(!empty($singleLabel)) <span style="color:var(--text3)">({{ $singleLabel }})</span>@endif × {{ $singleQty ?? 1 }}</span>
            <b style="color:var(--green)">₱{{ number_format($total,2) }}</b>
          </div>
        @endforeach
      @elseif($checkoutMode === 'seller' && isset($sellerLines))
        @foreach($sellerLines as $sl)
          <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border-light);font-size:12px">
            <span>{{ $sl['product']->name }}@if($sl['label']) <span style="color:var(--text3)">({{ $sl['label'] }})</span>@endif × {{ $sl['qty'] }}</span>
            <b style="color:var(--green)">₱{{ number_format($sl['line'],2) }}</b>
          </div>
        @endforeach
      @else
      @foreach($products as $p)
        @php $qty = $sellerQtyMap[$p->id] ?? $cartItems->where('product_id',$p->id)->first()->quantity ?? 1; @endphp
        <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border-light);font-size:12px">
          <span>{{ $p->name }} × {{ $qty }}</span>
          <b style="color:var(--green)">₱{{ number_format($p->price * $qty,2) }}</b>
        </div>
      @endforeach
      @endif
      <div style="display:flex;justify-content:space-between;margin-top:10px;font-size:12px;color:var(--text2)"><span>Shipping (1 shop)</span><span>₱{{ number_format(\App\Models\Order::SHIPPING_FEE_PER_SELLER,2) }}</span></div>
      <div style="display:flex;justify-content:space-between;margin-top:6px;font-weight:800;font-size:14px">Total <span style="color:var(--green)">₱{{ number_format($total + \App\Models\Order::SHIPPING_FEE_PER_SELLER,2) }}</span></div>
    @endif
  </div>
</div>
@endsection
