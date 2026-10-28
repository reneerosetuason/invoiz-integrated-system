@extends('buyer.layouts.website')
@section('content')
<div class="voucherx buyerfeature-page">
  <div class="bfp-wrap">
    <header class="bfp-head">
      <h1>Vouchers</h1>
      <span class="bfp-meta">{{ $vouchers->total() }} {{ $vouchers->total() === 1 ? 'voucher' : 'vouchers' }}</span>
    </header>
    <p class="bfp-description">Claim an offer here, then select it when you place an order.</p>

    @if(session('success'))
      <div class="bfp-flash bfp-flash-success" role="status">{{ session('success') }}</div>
    @endif
    @if(session('error'))
      <div class="bfp-flash bfp-flash-error" role="alert">{{ session('error') }}</div>
    @endif

    @if($vouchers->isEmpty())
      <div class="bfp-empty voucher-empty">
        <p>There are no available vouchers right now.</p>
      </div>
    @else
      <div class="voucher-grid">
        @foreach($vouchers as $voucher)
          @php
            $type = $voucher->discount_type ?? $voucher->type;
            $value = $voucher->discount_value ?? $voucher->value;
            $claimed = in_array((int)$voucher->id, array_map('intval', $claimedIds), true);
            $used = filled($voucherClaims->get($voucher->id)?->used_at);
            $sellerName = 'Store offer';
            try { $sellerName = \App\Models\Seller::nameFor(\App\Models\Seller::userIdFor((int) $voucher->seller_id)); } catch (\Throwable $e) {}
            $voucherExpiry = $voucher->ends_at ?? $voucher->valid_until;
            $voucherExpiryLabel = $voucherExpiry ? \Illuminate\Support\Carbon::parse($voucherExpiry)->format('M j, Y') : null;
          @endphp
          <article class="voucher-card">
            <div class="voucher-scope">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 10V5a2 2 0 0 1 2-2h5l11 11-7 7L3 10Z"/><circle cx="7.5" cy="7.5" r="1"/></svg>
              <span>{{ $sellerName }}</span>
            </div>
            <div class="voucher-value">
              @if($type === 'free_shipping')
                Free shipping
              @elseif($type === 'percent')
                {{ rtrim(rtrim(number_format((float)$value, 2), '0'), '.') }}% off
              @else
                ₱{{ number_format((float)$value, 2) }} off
              @endif
            </div>
            <div class="voucher-name">{{ $voucher->name && $voucher->name !== $voucher->code ? $voucher->name : $sellerName }}</div>
            @if($voucher->min_spend > 0)
              <div class="voucher-detail">Minimum spend: <span>₱{{ number_format($voucher->min_spend, 2) }}</span></div>
            @endif
            @if($voucherExpiryLabel)
              <div class="voucher-validity">Expires {{ $voucherExpiryLabel }}</div>
            @endif
            <div class="voucher-action">
              @if($claimed)
                <div class="voucher-state">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>
                  {{ $used ? 'Already used' : 'Claimed · ready at checkout' }}
                </div>
              @else
                <form method="POST" action="{{ url('/vouchers/'.$voucher->id.'/claim') }}">
                  @csrf
                  <button type="submit" class="bfp-action voucher-claim">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 8a2 2 0 0 0 0 4 2 2 0 0 1 0 4v2h18v-2a2 2 0 0 1 0-4 2 2 0 0 0 0-4V6H3v2Z"/><path d="M13 6v2m0 2v2m0 2v2m0 2v2"/></svg>
                    Claim voucher
                  </button>
                </form>
              @endif
            </div>
          </article>
        @endforeach
      </div>
      <div class="bfp-pager">{{ $vouchers->links() }}</div>
    @endif
  </div>
</div>
@endsection
