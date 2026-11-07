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
          @include('buyer.partials.voucher-card', ['canClaim' => true])
        @endforeach
      </div>
      <div class="bfp-pager">{{ $vouchers->links() }}</div>
    @endif
  </div>
</div>
@endsection
