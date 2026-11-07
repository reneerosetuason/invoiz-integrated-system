@php
  $type = $voucher->discount_type ?? $voucher->type;
  $value = $voucher->discount_value ?? $voucher->value;
  $claimed = in_array((int)$voucher->id, array_map('intval', $claimedIds), true);
  $used = filled($voucherClaims->get($voucher->id)?->used_at);
  $sellerName = $voucher->seller_id ? \App\Models\Seller::nameFor(\App\Support\Vouchers::sellerUserId($voucher)) : 'Platform offer';
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
    @elseif(!($canClaim ?? true))
      <a href="{{ route('login') }}" class="bfp-action voucher-claim">Log in to claim</a>
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
