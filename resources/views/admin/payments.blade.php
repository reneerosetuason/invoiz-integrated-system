@extends('admin.layout')

@section('content')
@include('admin.partials.list-ui-styles')
@include('admin.partials.order-ui-styles')

<style>
  .admin-payments{width:100%;max-width:1280px;min-width:0;margin:0 auto;color:var(--text-primary,#1B1B1E);font-family:'Inter',system-ui,-apple-system,'Segoe UI',sans-serif}
  .admin-payments *,.admin-payments *::before,.admin-payments *::after{box-sizing:border-box}
  .admin-payments .payments-head{margin:0 0 20px}
  .admin-payments .payments-eyebrow{display:block;margin:0 0 5px;color:#56696B;font-size:13px;font-weight:600;line-height:1.3}
  .admin-payments .payments-head h1{margin:0;color:#0E4A57;font-family:'Fraunces',Georgia,serif;font-size:28px;font-weight:560;line-height:1.15;letter-spacing:-.02em}
  .admin-payments .payments-flash{display:flex;align-items:flex-start;gap:9px;margin:0 0 16px;padding:11px 14px;border-radius:12px;background:#EAF3F2;color:#0E4A57;font-size:13px;font-weight:600;line-height:1.5}
  .admin-payments .payments-flash svg{width:17px;height:17px;flex:0 0 17px;margin-top:1px;fill:none;stroke:currentColor;stroke-width:1.8}
  .admin-payments .payments-status-strip{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px;margin:0 0 16px;border:0;border-radius:0;background:transparent;overflow:visible}
  .admin-payments .payments-status-strip .admin-list-stat{min-width:0;min-height:98px;padding:13px 14px;border:1px solid #D8E3E2;border-top:3px solid #0E4A57;border-radius:14px;background:#fff;box-shadow:0 2px 8px rgba(15,76,92,.045)}
  .admin-payments .payments-status-label,.admin-payments .payments-earning-label{color:#43575A;font-size:13px;font-weight:600;line-height:1.3}
  .admin-payments .payments-status-strip .admin-list-stat strong{margin-top:4px;color:#0E4A57;font-family:'Fraunces',Georgia,serif;font-size:28px;font-weight:560;line-height:1.1;font-variant-numeric:tabular-nums}
  .admin-payments .payments-status-strip .admin-list-stat strong.is-zero{color:#56696B}
  .admin-payments .payments-status-strip .admin-list-stat small{margin-top:4px;color:#56696B;font-size:12.5px;line-height:1.35;white-space:nowrap}
  .admin-payments .payments-earnings-strip{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:0 0 17px;border:0;border-radius:0;background:transparent;overflow:visible}
  .admin-payments .payments-earnings-strip .admin-list-stat{min-width:0;min-height:100px;padding:13px 16px;border:1px solid #D8E3E2;border-top:3px solid #0E4A57;border-radius:14px;background:#fff;box-shadow:0 2px 8px rgba(15,76,92,.045)}
  .admin-payments .payments-earnings-strip .payments-earning-value{display:block;margin-top:4px;color:#0E4A57;font-family:'Fraunces',Georgia,serif;font-size:28px;font-weight:560;line-height:1.1;font-variant-numeric:tabular-nums;white-space:nowrap}
  .admin-payments .payments-earnings-strip .payments-earning-value.is-zero{color:#56696B}
  .admin-payments .payments-earnings-strip small{margin-top:4px;color:#56696B;font-size:12.5px;line-height:1.35}
  .admin-payments .money-flow{display:flex;align-items:flex-start;gap:10px;margin:0 0 23px;padding:7px 0 7px 12px;border-left:2px solid #0E4A57;color:#56696B;font-size:14px;line-height:1.55}
  .admin-payments .money-flow svg{width:17px;height:17px;flex:0 0 17px;margin-top:1px;fill:none;stroke:#0E4A57;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}
  .admin-payments .money-flow p{margin:0;color:#43575A}
  .admin-payments .money-flow strong{color:#0E4A57;font-weight:650}
  .admin-payments .transactions-header{display:flex;align-items:center;justify-content:space-between;gap:16px;margin:0 0 11px}
  .admin-payments .transactions-header h2{margin:0;color:#0E4A57;font-family:'Fraunces',Georgia,serif;font-size:20px;font-weight:560;line-height:1.25;letter-spacing:-.015em}
  .admin-payments .payments-filters{display:flex;min-width:0;align-items:center;gap:8px;margin:0 0 14px}
  .admin-payments .payments-filters .admin-list-filter{min-height:38px;padding:7px 13px;font-size:12.5px}
  .admin-payments .payments-filters .admin-list-filter.is-active{border-color:#0E4A57;background:#0E4A57;color:#fff}
  .admin-payments .payments-panel{min-width:0;border:1px solid rgba(15,76,92,.12);border-radius:16px;background:#fff;overflow:hidden}
  .admin-payments .payments-table-wrap{width:100%;min-width:0;overflow:visible}
  .admin-payments .payments-table{width:100%;min-width:0;border-collapse:collapse;table-layout:fixed}
  .admin-payments .payments-table th{padding:12px 7px;border-bottom:1px solid rgba(15,76,92,.12);color:#56696B;font-size:12px;font-weight:600;letter-spacing:0;text-align:left;text-transform:none;vertical-align:bottom}
  .admin-payments .payments-table th:nth-child(n+3):nth-child(-n+7),.admin-payments .payments-table td:nth-child(n+3):nth-child(-n+7){text-align:right}
  .admin-payments .payments-table th:first-child,.admin-payments .payments-table td:first-child{padding-left:14px}
  .admin-payments .payments-table th:last-child,.admin-payments .payments-table td:last-child{padding-right:12px}
  .admin-payments .payments-table td{min-width:0;padding:15px 7px;border-bottom:1px solid rgba(15,76,92,.12);font-size:12.5px;line-height:1.4;vertical-align:middle}
  .admin-payments .payments-table tbody tr{transition:background-color .16s ease}
  .admin-payments .payments-table tbody tr:hover td{background:#F7FAF9}
  .admin-payments .payments-table tbody tr:last-child td{border-bottom:0}
  .admin-payments .payments-table .tx-empty{padding:26px 14px!important;color:#56696B;text-align:center}
  .admin-payments .tx-ref-order{display:grid;gap:4px;min-width:0}
  .admin-payments .tx-reference{display:block;max-width:100%;overflow:hidden;color:#56696B;font-size:12.5px;font-variant-numeric:tabular-nums;text-overflow:ellipsis;white-space:nowrap}
  .admin-payments .tx-order-link{display:inline-block;width:fit-content;max-width:100%;overflow:hidden;color:#0E4A57;font-size:13px;font-weight:650;text-decoration:none;text-overflow:ellipsis;white-space:nowrap}
  .admin-payments .tx-order-link::after{display:block;width:100%;height:1px;background:currentColor;content:"";transform:scaleX(0);transform-origin:left;transition:transform .18s ease}
  .admin-payments .tx-order-link:hover::after,.admin-payments .tx-order-link:focus-visible::after{transform:scaleX(1)}
  .admin-payments .tx-store-gateway{display:grid;gap:3px;min-width:0}
  .admin-payments .tx-store-name{display:block;max-width:100%;overflow:hidden;color:#1B1B1E;font-size:12.5px;font-weight:600;text-overflow:ellipsis;white-space:nowrap}
  .admin-payments .tx-store-name.is-missing{color:#56696B;font-weight:400}
  .admin-payments .tx-gateway{display:block;max-width:100%;overflow:hidden;color:#56696B;font-size:12px;text-overflow:ellipsis;white-space:nowrap}
  .admin-payments .tx-money{display:block;color:#0E4A57;font-size:12px;font-variant-numeric:tabular-nums;white-space:nowrap}
  .admin-payments .tx-money.is-zero,.admin-payments .tx-money.is-empty{color:#56696B;font-weight:400}
  .admin-payments .tx-amount{font-size:12.5px;font-weight:700}
  .admin-payments .tx-status-cell{text-align:left}
  .admin-payments .payment-status{min-height:27px;max-width:100%;gap:5px;padding:4px 7px;border-radius:999px;font-size:11.5px;font-weight:650;line-height:1.2;white-space:normal}
  .admin-payments .payment-status--neutral{border-color:rgba(15,76,92,.16);background:#F3F0E9;color:#43575A}
  .admin-payments .payment-status--teal{border-color:transparent;background:#EAF3F2;color:#0E4A57}
  .admin-payments .payment-status--brick{border-color:transparent;background:#F3F0E9;color:#8F4038}
  .admin-payments .payment-status svg{width:13px;height:13px;flex:0 0 13px;fill:none;stroke:currentColor;stroke-width:1.55;stroke-linecap:round;stroke-linejoin:round}
  .admin-payments a:focus-visible{outline:2px solid #0E4A57;outline-offset:2px}
  .admin-payments .admin-list-pagination{margin-top:12px}

  @media(max-width:1199.98px){
    .admin-payments .payments-status-strip{grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
    .admin-payments .payments-earnings-strip{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
    .admin-payments .payments-table,.admin-payments .payments-table tbody{display:block;width:100%}
    .admin-payments .payments-table thead{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);clip-path:inset(50%);white-space:nowrap}
    .admin-payments .payments-table tbody{display:grid;gap:0;padding:8px}
    .admin-payments .payments-table tr.tx-record{display:grid;grid-template-columns:minmax(0,1fr) auto;grid-template-areas:"reference status" "order gateway" "store store" "amount amount" "seller commission" "rider refunded";align-items:center;column-gap:12px;row-gap:0;margin:0;padding:11px 12px;border:0;border-bottom:1px solid rgba(15,76,92,.12);border-radius:0;background:#fff}
    .admin-payments .payments-table tr.tx-record:last-of-type{border-bottom:0}
    .admin-payments .payments-table tr.tx-record:hover{background:#F7FAF9}
    .admin-payments .payments-table td{display:contents;padding:0;border:0;font-size:13px;vertical-align:top}
    .admin-payments .payments-table td:first-child,.admin-payments .payments-table td:last-child{padding:0}
    .admin-payments .tx-ref-order,.admin-payments .tx-store-gateway{display:contents}
    .admin-payments .tx-reference{grid-area:reference;align-self:center;max-width:100%;font-size:13px}
    .admin-payments .tx-order-link{grid-area:order;align-self:center;min-height:30px;padding-top:5px;font-size:13.5px}
    .admin-payments .tx-gateway{grid-area:gateway;justify-self:end;max-width:100%;padding-top:5px;text-align:right}
    .admin-payments .tx-store-name{grid-area:store;max-width:100%;padding-top:9px;overflow-wrap:anywhere;white-space:normal}
    .admin-payments .tx-amount{grid-area:amount;padding:11px 0 3px;font-size:18px;font-weight:700}
    .admin-payments .tx-status-cell{grid-area:status;justify-self:end}
    .admin-payments .tx-status-cell>.payment-status{grid-area:status;justify-self:end}
    .admin-payments .payment-status{min-height:28px;font-size:12px}
    .admin-payments .tx-value--seller{grid-area:seller}
    .admin-payments .tx-value--commission{grid-area:commission}
    .admin-payments .tx-value--rider{grid-area:rider}
    .admin-payments .tx-value--refunded{grid-area:refunded}
    .admin-payments .tx-money:not(.tx-amount){display:flex;min-width:0;flex-direction:column;gap:4px;padding:10px 4px 2px;white-space:normal}
    .admin-payments .tx-money:not(.tx-amount)::before{color:#56696B;font-size:11.5px;font-weight:600;line-height:1.25;content:attr(data-label)}
    .admin-payments .tx-money:not(.tx-amount){font-size:12.5px;font-variant-numeric:tabular-nums}
    .admin-payments .payments-table tr.empty-order-row{display:block;margin:0;padding:0;border:0}
    .admin-payments .payments-table tr.empty-order-row td.tx-empty{display:block;padding:22px 12px!important;border:0}
    .admin-payments .payments-table .tx-empty::before{display:none}
  }
  @media(max-width:767.98px){
    .admin-payments{width:calc(100% + 32px);margin-right:-16px;margin-left:-16px;padding:0}
    .admin-payments .payments-head{margin-bottom:17px}
    .admin-payments .payments-head h1{font-size:24px}
    .admin-payments .payments-status-strip{grid-template-columns:repeat(2,minmax(0,1fr));gap:9px;margin-bottom:12px}
    .admin-payments .payments-status-strip .admin-list-stat{min-height:92px;padding:11px}
    .admin-payments .payments-status-strip .admin-list-stat strong{font-size:26px}
    .admin-payments .payments-status-strip .admin-list-stat small{font-size:clamp(11px,3.1vw,12.5px);white-space:normal;overflow-wrap:anywhere}
    .admin-payments .payments-earnings-strip{grid-template-columns:repeat(2,minmax(0,1fr));gap:9px;margin-bottom:14px}
    .admin-payments .payments-earnings-strip .admin-list-stat{min-height:96px;padding:11px}
    .admin-payments .payments-earnings-strip .payments-earning-value{font-size:24px;white-space:normal;overflow-wrap:anywhere}
    .admin-payments .money-flow{margin-bottom:19px;font-size:13.5px}
    .admin-payments .transactions-header{margin-bottom:9px}
    .admin-payments .transactions-header h2{font-size:19px}
    .admin-payments .payments-filters{flex-wrap:nowrap;overflow-x:auto;padding:2px 2px 7px;scroll-snap-type:x mandatory;scrollbar-width:none;-webkit-overflow-scrolling:touch}
    .admin-payments .payments-filters::-webkit-scrollbar{display:none}
    .admin-payments .payments-filters .admin-list-filter{min-height:44px;padding-inline:13px;scroll-snap-align:start}
    .admin-payments .payments-panel{border-radius:14px}
    .admin-payments .payments-table tbody{gap:0;padding:6px}
    .admin-payments .payments-table tr.tx-record{grid-template-areas:"reference status" "order gateway" "store store" "amount amount" "seller commission" "rider refunded";column-gap:8px;padding:10px;border-radius:0}
    .admin-payments .payments-table tr.tx-record:first-child{border-top:0}
    .admin-payments .tx-order-link{min-height:44px;padding-top:5px}
    .admin-payments .tx-gateway{font-size:11.5px}
    .admin-payments .tx-store-name{font-size:13px}
    .admin-payments .tx-money:not(.tx-amount){padding-right:2px;padding-left:2px;font-size:12px}
    .admin-payments .tx-money:not(.tx-amount)::before{font-size:11px}
    .admin-payments .admin-list-pagination>div{justify-content:center!important}
    .admin-payments .admin-list-pagination>div>a{position:relative}
    .admin-payments .admin-list-pagination>div>a::before{position:absolute;inset:-4px;content:""}
  }
  @media(max-width:359.98px){
    .admin-payments .payments-status-strip .admin-list-stat{padding:10px}
    .admin-payments .payments-status-strip .admin-list-stat strong{font-size:24px}
    .admin-payments .payments-earnings-strip .payments-earning-value{font-size:21px}
    .admin-payments .payments-table tr.tx-record{column-gap:6px;padding:9px}
    .admin-payments .payment-status{padding:4px 6px;font-size:11.5px}
  }
</style>

<div class="admin-list admin-payments">
    <header class="admin-list-head payments-head">
        <span class="payments-eyebrow">Finance</span>
        <h1>Payments</h1>
    </header>

    @if(session('status'))
    <div class="admin-list-flash payments-flash" role="status">
        <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 2.5a7.5 7.5 0 1 0 0 15 7.5 7.5 0 0 0 0-15Z"/><path d="M10 8.8v4.5M10 6.2v.1"/></svg>
        <span>{{ session('status') }}</span>
    </div>
    @endif

    <section class="admin-list-stats payments-status-strip" aria-label="Transaction totals">
        <div class="admin-list-stat">
            <span class="payments-status-label">All Transactions</span>
            <strong class="{{ $stats['all'] == 0 ? 'is-zero' : '' }}">{{ $stats['all'] }}</strong>
            <small>₱{{ number_format($stats['all_amount'], 2) }} volume</small>
        </div>
        <div class="admin-list-stat">
            <span class="payments-status-label">Successful</span>
            <strong class="{{ $stats['successful'] == 0 ? 'is-zero' : '' }}">{{ $stats['successful'] }}</strong>
            <small>₱{{ number_format($stats['successful_amount'], 2) }}</small>
        </div>
        <div class="admin-list-stat">
            <span class="payments-status-label">Pending</span>
            <strong class="{{ $stats['pending'] == 0 ? 'is-zero' : '' }}">{{ $stats['pending'] }}</strong>
            <small>₱{{ number_format($stats['pending_amount'], 2) }}</small>
        </div>
        <div class="admin-list-stat">
            <span class="payments-status-label">Failed</span>
            <strong class="{{ $stats['failed'] == 0 ? 'is-zero' : '' }}">{{ $stats['failed'] }}</strong>
            <small>₱{{ number_format($stats['failed_amount'], 2) }}</small>
        </div>
        <div class="admin-list-stat">
            <span class="payments-status-label">Refunded</span>
            <strong class="{{ $stats['refunded'] == 0 ? 'is-zero' : '' }}">{{ $stats['refunded'] }}</strong>
            <small>₱{{ number_format($stats['refunded_amount'], 2) }} returned</small>
        </div>
        <div class="admin-list-stat">
            <span class="payments-status-label">Partially Refunded</span>
            <strong class="{{ $stats['partially_refunded'] == 0 ? 'is-zero' : '' }}">{{ $stats['partially_refunded'] }}</strong>
        </div>
    </section>

    <section class="admin-list-stats admin-list-stats--4 payments-earnings-strip" aria-label="Earnings breakdown">
        <div class="admin-list-stat">
            <span class="payments-earning-label">Admin Earnings</span>
            <strong class="payments-earning-value">₱{{ number_format($stats['admin_earnings'], 2) }}</strong>
            <small>Commission + fees</small>
        </div>
        <div class="admin-list-stat">
            <span class="payments-earning-label">Platform Commission</span>
            <strong class="payments-earning-value">₱{{ number_format($stats['commission'], 2) }}</strong>
        </div>
        <div class="admin-list-stat">
            <span class="payments-earning-label">Seller Earnings</span>
            <strong class="payments-earning-value">₱{{ number_format($stats['seller_earnings'], 2) }}</strong>
        </div>
        <div class="admin-list-stat">
            <span class="payments-earning-label">Rider Fees</span>
            <strong class="payments-earning-value">₱{{ number_format($stats['rider_fees'], 2) }}</strong>
        </div>
    </section>

    <aside class="money-flow" aria-label="Money flow note">
        <svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="7.2"/><path d="M10 9v4M10 6.5v.1"/></svg>
        <p><strong>Money flow:</strong> Order → Payment → Seller Earnings → Platform Commission → Rider Fee → Refund. Every transaction below shows the complete breakdown.</p>
    </aside>

    <div class="transactions-header">
        <h2 id="transactions">All Transactions</h2>
    </div>
    <nav class="admin-list-filters payments-filters" aria-label="Filter transactions by status">
        @foreach (['' => 'All', 'successful' => 'Successful', 'pending' => 'Pending', 'failed' => 'Failed', 'refunded' => 'Refunded', 'partially_refunded' => 'Partial'] as $key => $label)
        @php $isActiveFilter = ($status ?? '') === $key; @endphp
        <a href="{{ $key ? url('/admin/payments?status='.$key) : url('/admin/payments') }}" data-filter="{{ $key }}" class="admin-list-filter tx-filter {{ $isActiveFilter ? 'is-active' : '' }}" aria-current="{{ $isActiveFilter ? 'page' : 'false' }}">{{ $label }}</a>
        @endforeach
    </nav>

    <div class="admin-list-panel payments-panel">
      <div class="admin-list-table-wrap payments-table-wrap">
        <table class="admin-list-table payments-table" role="table" aria-label="Payment transactions">
            <colgroup>
                <col style="width:15%"><col style="width:16%"><col style="width:12.5%"><col style="width:12.5%">
                <col style="width:12.5%"><col style="width:10%"><col style="width:10%"><col style="width:11.5%">
            </colgroup>
            <thead role="rowgroup">
                <tr role="row">
                    <th scope="col" role="columnheader">Ref / Order</th>
                    <th scope="col" role="columnheader">Store / Gateway</th>
                    <th scope="col" role="columnheader">Amount</th>
                    <th scope="col" role="columnheader">Seller earning</th>
                    <th scope="col" role="columnheader">Commission</th>
                    <th scope="col" role="columnheader">Rider fee</th>
                    <th scope="col" role="columnheader">Refunded</th>
                    <th scope="col" role="columnheader">Status</th>
                </tr>
            </thead>
            <tbody id="txBody" role="rowgroup">
                @forelse($transactions as $tx)
                @php
                    $orderedStores = ($tx->order?->items ?? collect())->map(function ($item) {
                        $store = $item->seller?->seller ?? $item->product?->seller;
                        $name = $store?->store_name ?: $store?->business_name;
                        if (!filled($name)) return null;
                        return ['id' => $store->getKey(), 'name' => trim($name)];
                    })->filter()->unique('id')->values();
                    if ($orderedStores->isEmpty() && $tx->order?->seller) {
                        $legacyStore = $tx->order->seller;
                        $legacyStoreName = $legacyStore->store_name ?: $legacyStore->business_name;
                        if (filled($legacyStoreName)) {
                            $orderedStores = collect([['id' => $legacyStore->getKey(), 'name' => trim($legacyStoreName)]]);
                        }
                    }
                    $storeNames = $orderedStores->pluck('name')->implode(', ');
                    $gatewayOriginal = trim((string) $tx->gateway);
                    $gatewayKey = strtolower(str_replace(' ', '_', $gatewayOriginal));
                    $gatewayLabel = match ($gatewayKey) {
                        'cash_on_delivery' => 'Cash on delivery',
                        'gcash' => 'GCash',
                        'cod' => 'COD',
                        default => ucfirst(strtolower(str_replace('_', ' ', $gatewayOriginal))),
                    };
                    $normalizedTxStatus = strtolower(str_replace(' ', '_', (string) $tx->status));
                    $txStatusLabel = ucfirst(str_replace('_', ' ', (string) $tx->status));
                    [$paymentTone, $paymentIcon] = match ($normalizedTxStatus) {
                        'successful' => ['teal', 'check'],
                        'pending' => ['neutral', 'clock'],
                        'failed' => ['brick', 'x'],
                        'refunded' => ['neutral', 'refund'],
                        'partially_refunded' => ['neutral', 'partial'],
                        default => ['neutral', 'dot'],
                    };
                @endphp
                <tr class="list-record tx-record" data-status="{{ $tx->status }}" role="row">
                    <td class="tx-ref-order-cell" data-label="Ref / Order" role="cell">
                        <span class="tx-ref-order">
                            <span class="tx-reference" title="{{ $tx->transaction_ref }}">{{ $tx->transaction_ref }}</span>
                            <a href="{{ url('/admin/orders/'.$tx->order_id) }}" class="tx-order-link" aria-label="Open order {{ $tx->order->order_number ?? '-' }} for transaction {{ $tx->transaction_ref }}" title="{{ $tx->order->order_number ?? '-' }}">{{ $tx->order->order_number ?? '-' }}</a>
                        </span>
                    </td>
                    <td class="tx-store-gateway-cell" data-label="Store / Gateway" role="cell">
                        <span class="tx-store-gateway">
                            <span class="tx-store-name {{ $storeNames ? '' : 'is-missing' }}" title="{{ $storeNames ?: 'N/A' }}">{{ $storeNames ?: 'N/A' }}</span>
                            <span class="tx-gateway" title="{{ $gatewayLabel }}">{{ $gatewayLabel }}</span>
                        </span>
                    </td>
                    <td data-label="Amount" role="cell"><span class="tx-money tx-amount {{ (float) $tx->amount == 0.0 ? 'is-zero' : '' }}" data-label="Amount">₱{{ number_format($tx->amount, 2) }}</span></td>
                    <td data-label="Seller earning" role="cell"><span class="tx-money tx-value--seller {{ (float) $tx->seller_earning == 0.0 ? 'is-zero' : '' }}" data-label="Seller earning">₱{{ number_format($tx->seller_earning, 2) }}</span></td>
                    <td data-label="Commission" role="cell"><span class="tx-money tx-value--commission {{ (float) $tx->platform_commission == 0.0 ? 'is-zero' : '' }}" data-label="Commission">₱{{ number_format($tx->platform_commission, 2) }}</span></td>
                    <td data-label="Rider fee" role="cell"><span class="tx-money tx-value--rider {{ (float) $tx->rider_fee == 0.0 ? 'is-zero' : '' }}" data-label="Rider fee">₱{{ number_format($tx->rider_fee, 2) }}</span></td>
                    <td data-label="Refunded" role="cell"><span class="tx-money tx-value--refunded {{ (float) $tx->refunded_amount > 0 ? '' : 'is-empty' }}" data-label="Refunded">{{ $tx->refunded_amount > 0 ? '₱'.number_format($tx->refunded_amount, 2) : '—' }}</span></td>
                    <td class="tx-status-cell" data-label="Status" role="cell">
                        <span class="order-status-chip order-status-chip--payment order-status-chip--{{ $paymentTone }} payment-status payment-status--{{ $paymentTone }}" data-status="{{ $normalizedTxStatus }}">
                            @if($paymentIcon === 'dot')
                                <span class="order-status-dot" aria-hidden="true"></span>
                            @else
                                <svg viewBox="0 0 20 20" aria-hidden="true">
                                    @if($paymentIcon === 'check')
                                        <path d="m4 10 4 4 8-8"/>
                                    @elseif($paymentIcon === 'clock')
                                        <circle cx="10" cy="10" r="7"/><path d="M10 6v4l2.5 1.5"/>
                                    @elseif($paymentIcon === 'refund')
                                        <path d="M5 6H2.5V3.5M3 6a7 7 0 1 1-.5 7"/>
                                    @elseif($paymentIcon === 'partial')
                                        <path d="M10 3a7 7 0 1 0 0 14V3Z"/><path d="M10 3a7 7 0 0 1 0 14"/>
                                    @else
                                        <path d="m5 5 10 10M15 5 5 15"/>
                                    @endif
                                </svg>
                            @endif
                            <span>{{ $txStatusLabel }}</span>
                        </span>
                    </td>
                </tr>
                @empty
                <tr class="empty-order-row" role="row"><td colspan="8" class="tx-empty" role="cell">No transactions found.</td></tr>
                @endforelse
                <tr id="txEmpty" class="empty-order-row" style="display:none;" role="row"><td colspan="8" class="tx-empty" role="cell">No transactions for this status.</td></tr>
            </tbody>
        </table>
      </div>
    </div>
    <div class="admin-list-pagination payments-pagination">
        @include('admin.partials.pager', ['paginator' => $transactions])
    </div>
</div>
<script>
(function(){
  const pills = document.querySelectorAll('.tx-filter');
  const rows = document.querySelectorAll('#txBody tr[data-status]');
  const emptyRow = document.getElementById('txEmpty');
  const title = document.getElementById('transactions');
  const urlParams = new URLSearchParams(window.location.search);
  let current = urlParams.get('status') || '';
  function applyFilter(status, push){
    pills.forEach(p=>{
      const f = p.getAttribute('data-filter');
      const active = f === status;
      p.classList.toggle('is-active', active);
      p.setAttribute('aria-current', active ? 'page' : 'false');
    });
    if(title){
      const labels = {'':'All Transactions','successful':'Successful Transactions','pending':'Pending Transactions','failed':'Failed Transactions','refunded':'Refunded Transactions','partially_refunded':'Partial Transactions'};
      title.textContent = labels[status] || 'All Transactions';
    }
    let visible = 0;
    rows.forEach(r=>{
      const show = !status || r.getAttribute('data-status') === status;
      r.style.display = show ? '' : 'none';
      if(show) visible++;
    });
    if(emptyRow) emptyRow.style.display = visible===0 && rows.length>0 ? '' : 'none';
    const url = status ? '/admin/payments?status='+status : '/admin/payments';
    if(push) history.replaceState({}, '', url);
  }
  if(current) applyFilter(current, false);
  pills.forEach(p=>{
    p.addEventListener('click', function(e){
      e.preventDefault();
      const status = p.getAttribute('data-filter');
      const y = window.scrollY;
      applyFilter(status, true);
      window.scrollTo(0, y);
    });
  });
})();
</script>
@endsection
