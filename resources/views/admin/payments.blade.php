@extends('admin.layout')

@section('content')
@include('admin.partials.list-ui-styles')
@include('admin.partials.order-ui-styles')

<link rel="stylesheet" href="{{ asset('assets/admin/css/payments.css') }}">

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
        <p><strong>Escrow &amp; Stakeholder Remittances:</strong> COD Cash Collected → Last-Mile Rider Fee (₱50 retained) → Escrow Settlement → Origin Hub (₱30) + Pickup Rider (₱30/item) + Inter-Hub Driver (₱20) + Destination Hub (₱30) + Platform Commission (10%) + Seller Net Remittance.</p>
    </aside>

    @if(isset($escrowRemittances) && $escrowRemittances)
    {{-- Escrow Bank Remittances Table --}}
    <div class="transactions-header" style="margin-top: 2rem;">
        <div class="flex flex-wrap items-center justify-between gap-3 w-full" style="display:flex; justify-content:space-between; align-items:center; width:100%;">
            <div>
                <h2 id="escrow-remittances" style="margin:0;">Stakeholder Bank Remittances (Platform Escrow)</h2>
                <p style="font-size:0.8rem; color:#6B7280; margin:0.25rem 0 0;">Auditable remittances released directly to each worker's, hub's, and seller's bank account.</p>
            </div>
            @if(($escrowTotals['pending'] ?? 0) > 0)
            <form method="POST" action="{{ route('admin.escrow.remit-all') }}" onsubmit="return confirm('Release all pending escrow bank remittances?');">
                @csrf
                <button type="submit" class="btn btn-primary" style="background:#16697A; color:#fff; padding:0.4rem 0.85rem; border-radius:0.5rem; font-weight:700; font-size:0.8rem; border:none; cursor:pointer;">
                    Release All Pending (₱{{ number_format($escrowTotals['pending'], 2) }})
                </button>
            </form>
            @endif
        </div>
    </div>

    <div class="admin-list-panel payments-panel" style="margin-top: 0.75rem; margin-bottom: 2rem;">
      <div class="admin-list-table-wrap payments-table-wrap">
        <table class="admin-list-table payments-table" role="table" aria-label="Escrow remittances">
            <thead role="rowgroup">
                <tr role="row">
                    <th scope="col">Recipient / Role</th>
                    <th scope="col">Order / Tracking</th>
                    <th scope="col">Rule Applied</th>
                    <th scope="col" style="text-align:right;">Amount</th>
                    <th scope="col">Settlement Mode</th>
                    <th scope="col">Status</th>
                    <th scope="col" style="text-align:right;">Action</th>
                </tr>
            </thead>
            <tbody role="rowgroup">
                @forelse($escrowRemittances as $er)
                <tr class="list-record" role="row">
                    <td data-label="Recipient">
                        <strong>{{ $er->recipient_name }}</strong>
                        <div style="font-size:0.75rem; color:#6B7280;">{{ $er->roleLabel() }}</div>
                    </td>
                    <td data-label="Order / Tracking">
                        <a href="{{ url('/admin/orders/'.$er->order_id) }}" style="font-weight:700; color:#16697A;">#{{ $er->order_id }}</a>
                        @if($er->delivery?->tracking_number)
                            <div style="font-size:0.75rem; color:#6B7280; font-family:monospace;">{{ $er->delivery->tracking_number }}</div>
                        @endif
                    </td>
                    <td data-label="Rule Applied" style="font-size:0.8rem; color:#4B5563;">
                        {{ $er->rule_applied }}
                    </td>
                    <td data-label="Amount" style="text-align:right; font-weight:700;">
                        ₱{{ number_format($er->amount, 2) }}
                    </td>
                    <td data-label="Settlement Mode" style="font-size:0.75rem;">
                        <span style="display:inline-block; padding:0.2rem 0.5rem; border-radius:0.375rem; background:#F3F4F6; color:#374151; font-weight:600;">
                            {{ $er->settlementModeLabel() }}
                        </span>
                        @if($er->bank_reference)
                            <div style="font-size:0.7rem; color:#9CA3AF; margin-top:2px;">Ref: {{ $er->bank_reference }}</div>
                        @endif
                    </td>
                    <td data-label="Status">
                        @if($er->status === 'remitted')
                            <span style="display:inline-block; padding:0.2rem 0.5rem; border-radius:0.375rem; background:#DEF7EC; color:#03543F; font-size:0.75rem; font-weight:700;">Remitted</span>
                        @elseif($er->status === 'pending_remittance')
                            <span style="display:inline-block; padding:0.2rem 0.5rem; border-radius:0.375rem; background:#FEF08A; color:#854D0E; font-size:0.75rem; font-weight:700;">Ready to Remit</span>
                        @elseif($er->status === 'voided')
                            <span style="display:inline-block; padding:0.2rem 0.5rem; border-radius:0.375rem; background:#FEE2E2; color:#991B1B; font-size:0.75rem; font-weight:700;">Voided</span>
                        @else
                            <span style="display:inline-block; padding:0.2rem 0.5rem; border-radius:0.375rem; background:#F3F4F6; color:#4B5563; font-size:0.75rem; font-weight:600;">Held in Escrow</span>
                        @endif
                    </td>
                    <td data-label="Action" style="text-align:right;">
                        @if($er->status === 'pending_remittance')
                            <form method="POST" action="{{ route('admin.escrow.remit', $er->id) }}" style="display:inline-block;" onsubmit="return confirm('Remit ₱{{ number_format($er->amount, 2) }} to {{ $er->recipient_name }}?');">
                                @csrf
                                <button type="submit" class="btn" style="background:#059669; color:#fff; font-size:0.75rem; font-weight:700; padding:0.25rem 0.6rem; border-radius:0.375rem; border:none; cursor:pointer;">
                                    Remit Bank
                                </button>
                            </form>
                        @elseif($er->status === 'remitted')
                            <span style="font-size:0.75rem; color:#059669; font-weight:600;">Settled</span>
                        @else
                            <span style="font-size:0.75rem; color:#9CA3AF;">In Transit</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" style="text-align:center; padding:2rem; color:#6B7280;">No escrow remittance records found.</td></tr>
                @endforelse
            </tbody>
        </table>
      </div>
      @if($escrowRemittances->hasPages())
        <div style="padding:1rem;">@include('admin.partials.pager', ['paginator' => $escrowRemittances])</div>
      @endif
    </div>
    @endif

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
<script src="{{ asset('assets/admin/js/payments.js') }}"></script>
@endsection
