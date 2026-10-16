@extends('layouts.website')
@section('content')
<div style="max-width:1000px;margin:0 auto;padding:8px 0 30px">
  <div style="font-size:11px;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.12em">Offers for you</div>
  <h1 style="margin:5px 0 0;font-size:26px;font-weight:800;color:var(--green-dark)">Vouchers</h1>
  <p style="margin:6px 0 0;color:var(--text2);font-size:13px">Claim an offer here, then select it when you place an order.</p>
  @if(session('success'))<div role="status" style="margin-top:14px;padding:10px 12px;border-radius:9px;background:#ecfdf5;color:#065f46;font-size:13px;font-weight:600">{{ session('success') }}</div>@endif
  @if(session('error'))<div role="alert" style="margin-top:14px;padding:10px 12px;border-radius:9px;background:#fef2f2;color:#991b1b;font-size:13px;font-weight:600">{{ session('error') }}</div>@endif
  @if($vouchers->isEmpty())
    <div style="margin-top:18px;padding:42px 18px;text-align:center;background:#fff;border:1px solid var(--border);border-radius:14px;color:var(--text2)">There are no available vouchers right now.</div>
  @else
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:13px;margin-top:18px">
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
        <article style="display:flex;flex-direction:column;min-height:174px;padding:16px;background:#fff;border:1px solid var(--border);border-radius:13px">
          <div style="display:flex;justify-content:space-between;gap:10px;align-items:start">
            <div>
              <div style="font-size:18px;font-weight:800;color:var(--green-dark)">
                @if($type === 'free_shipping')Free shipping@elseif($type === 'percent'){{ rtrim(rtrim(number_format((float)$value, 2), '0'), '.') }}% off@else₱{{ number_format((float)$value, 2) }} off@endif
              </div>
              <div style="margin-top:3px;font-size:12px;color:var(--text2)">{{ $voucher->name && $voucher->name !== $voucher->code ? $voucher->name : $sellerName }}</div>
            </div>
            <span style="padding:4px 8px;border-radius:999px;background:#f0fdf4;color:#166534;font-size:10px;font-weight:800">{{ $sellerName }}</span>
          </div>
          @if($voucher->min_spend > 0)<div style="margin-top:12px;font-size:11px;color:var(--text2)">Minimum spend: ₱{{ number_format($voucher->min_spend, 2) }}</div>@endif
          @if($voucherExpiryLabel)<div style="margin-top:4px;font-size:11px;color:var(--text3)">Expires {{ $voucherExpiryLabel }}</div>@endif
          <div style="margin-top:auto;padding-top:14px">
            @if($claimed)
              <div style="padding:9px 12px;text-align:center;border-radius:8px;background:#f1f5f9;color:#475569;font-size:12px;font-weight:700">{{ $used ? 'Already used' : 'Claimed · ready at checkout' }}</div>
            @else
              <form method="POST" action="{{ url('/vouchers/'.$voucher->id.'/claim') }}">@csrf<button type="submit" style="width:100%;padding:9px 12px;border:0;border-radius:8px;background:var(--green);color:#fff;font-size:12px;font-weight:800;cursor:pointer">Claim voucher</button></form>
            @endif
          </div>
        </article>
      @endforeach
    </div>
    <div style="margin-top:18px">{{ $vouchers->links() }}</div>
  @endif
</div>
@endsection
