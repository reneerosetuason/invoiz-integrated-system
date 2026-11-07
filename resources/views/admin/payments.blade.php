@extends('admin.layout')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/admin/css/payment-overview.css') }}?v={{ filemtime(public_path('assets/admin/css/payment-overview.css')) }}">
@endpush
@push('scripts')
<script src="{{ asset('assets/admin/js/payment-overview.js') }}?v={{ filemtime(public_path('assets/admin/js/payment-overview.js')) }}" defer></script>
@endpush

@section('content')
    @if(\Illuminate\Support\Facades\Schema::hasTable('return_settlements'))<p><a class="btn btn-outline" href="{{ route('returns.remittances') }}">Return payments &amp; remittances</a> <a class="btn btn-outline" href="{{ route('returns.center') }}">Return funding &amp; refunds</a></p>@endif
@php
    $escrowRemittancesList = $escrowRemittancesList ?? $escrowRemittances ?? null;
    $pendingEscrowRemittances = $pendingEscrowRemittances ?? (isset($escrowRemittancesList) ? collect($escrowRemittancesList->items())->where('status', 'pending_remittance')->filter(fn ($row) => $row->order?->payment?->method !== 'gcash') : collect());
    $pendingEscrowTotal = $pendingEscrowRemittances->sum('amount');
    $summaryCards = [
        ['All transactions', $stats['all'], $stats['all_amount'], 'all'],
        ['Successful', $stats['successful'], $stats['successful_amount'], 'successful'],
        ['Pending', $stats['pending'], $stats['pending_amount'], 'pending'],
        ['Failed', $stats['failed'], $stats['failed_amount'], 'failed'],
        ['Refunded', $stats['refunded'], $stats['refunded_amount'], 'refunded'],
    ];
@endphp
<div class="payment-overview">
    <header class="po-heading">
        <div><p class="po-eyebrow">Payment management</p><h1>Payments &amp; escrow</h1><p>Review transactions and release stakeholder payouts.</p></div>
        <a class="po-button" href="{{ route('admin.gcash.index') }}">GCash deposits &amp; receiving account <span aria-hidden="true">&rarr;</span></a>
    </header>
    @if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger" role="alert">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert"><strong>Review your payout details.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="po-stats">
        @foreach($summaryCards as [$label, $count, $amount, $tone])
            <div class="po-stat"><span>{{ $label }}</span><strong class="po-tone-{{ $tone }}">{{ $count }}</strong><small>&#8369;{{ number_format($amount, 2) }}{{ $tone === 'refunded' ? ' returned' : '' }}</small></div>
        @endforeach
    </div>
    <div class="po-earnings">
        <div><span>Confirmed platform commission</span><strong>&#8369;{{ number_format($stats['commission'], 2) }}</strong></div>
        <div><span>Seller net earnings</span><strong>&#8369;{{ number_format($stats['seller_earnings'], 2) }}</strong></div>
        <div><span>Logistics &amp; rider fees</span><strong>&#8369;{{ number_format(($stats['rider_fees'] ?? 0) + ($stats['origin_hub_fees'] ?? 0) + ($stats['destination_hub_fees'] ?? 0), 2) }}</strong></div>
    </div>

    <section class="po-panel" id="admin-remittances" aria-labelledby="admin-remittances-title">
        <div class="po-section-heading"><div><h2 id="admin-remittances-title">Admin remittances</h2><p>Confirm receipt of the platform cut after release or retention. Only accepted remittances count as confirmed commission.</p></div></div>
        <div class="po-earnings">
            <div><span>Confirmed earnings</span><strong>&#8369;{{ number_format($adminRemittanceTotals['accepted'], 2) }}</strong></div>
            <div><span>Ready to confirm</span><strong>&#8369;{{ number_format($adminRemittanceTotals['remitted'], 2) }}</strong></div>
            <div><span>Awaiting release</span><strong>&#8369;{{ number_format($adminRemittanceTotals['awaiting_release'], 2) }}</strong></div>
        </div>
        <table class="po-table po-admin-remittances">
            <caption class="po-sr-only">Platform commission remittances. View details to release or accept a remittance.</caption>
            <thead><tr><th scope="col">Order</th><th scope="col" class="po-money">Amount</th><th scope="col">Status</th><th scope="col" class="po-action">Action</th></tr></thead>
            <tbody>
                @forelse($adminRemittances ?? [] as $remit)
                    <tr>
                        <td><a class="po-truncate po-order" href="{{ url('/admin/orders/'.$remit->order_id) }}" title="{{ $remit->order?->order_number ?? $remit->order_id }}">#{{ $remit->order?->order_number ?? $remit->order_id }}</a></td>
                        <td class="po-money">&#8369;{{ number_format((float) $remit->amount, 2) }}</td>
                        <td>@include('admin.partials.payment-status', ['paymentStatus' => $remit->status])</td>
                        <td class="po-action"><button class="po-view" type="button" data-payment-dialog="payout-detail-{{ $remit->id }}" aria-haspopup="dialog" aria-label="View admin remittance {{ $remit->id }}">View</button></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="po-empty">No admin remittances available yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if(isset($adminRemittances) && $adminRemittances->hasPages())
            @include('admin.partials.pager', ['paginator' => $adminRemittances])
        @endif
    </section>

    <section class="po-panel" id="escrow-remittances" aria-labelledby="escrow-title">
        <div class="po-section-heading">
            <div><h2 id="escrow-title">Escrow remittances</h2><p>View a payout for recipient details and release controls. GCash requires an individual transfer reference.</p></div>
            @if($pendingEscrowRemittances->count() > 0)
                <form method="POST" action="{{ route('admin.escrow.remit-all') }}" data-payout-confirm="Release all {{ $pendingEscrowRemittances->count() }} pending COD payouts totaling &#8369;{{ number_format($pendingEscrowTotal, 2) }}?">
                    @csrf
                    <button type="submit" class="po-button">Release COD pending (&#8369;{{ number_format($pendingEscrowTotal, 2) }})</button>
                </form>
            @endif
        </div>
        <table class="po-table po-remittances">
            <caption class="po-sr-only">Escrow payout summary. Use View to see full details and release a payout.</caption>
            <colgroup><col class="po-col-order"><col class="po-col-recipient"><col class="po-col-amount"><col class="po-col-status"><col class="po-col-action"></colgroup>
            <thead><tr><th scope="col">Order</th><th scope="col" class="po-recipient">Recipient</th><th scope="col" class="po-money">Amount</th><th scope="col">Status</th><th scope="col" class="po-action">Action</th></tr></thead>
            <tbody>
                @forelse($escrowRemittancesList ?? [] as $remit)
                    <tr>
                        <td><a class="po-truncate po-order" href="{{ url('/admin/orders/'.$remit->order_id) }}" title="{{ $remit->order?->order_number ?? $remit->order_id }}">#{{ $remit->order?->order_number ?? $remit->order_id }}</a></td>
                        <td class="po-recipient"><span class="po-truncate po-recipient-name">{{ $remit->recipient_name }}</span><small class="po-truncate">{{ $remit->roleLabel() }}</small></td>
                        <td class="po-money">&#8369;{{ number_format((float) $remit->amount, 2) }}</td>
                        <td>@include('admin.partials.payment-status', ['paymentStatus' => $remit->status])</td>
                        <td class="po-action"><button class="po-view" type="button" data-payment-dialog="payout-detail-{{ $remit->id }}" aria-haspopup="dialog" aria-label="View payout {{ $remit->id }}">View</button></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="po-empty">No escrow remittances available yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if(isset($escrowRemittancesList) && method_exists($escrowRemittancesList, 'hasPages') && $escrowRemittancesList->hasPages())
            @include('admin.partials.pager', ['paginator' => $escrowRemittancesList])
        @endif
    </section>

    <section class="po-panel" aria-labelledby="transactions">
        <div class="po-section-heading">
            <div><h2 id="transactions">Payment transactions</h2><p>View a transaction for its full reference, store, fees and date.</p></div>
            <nav class="po-filters" aria-label="Transaction status">
                @foreach(['' => 'All', 'successful' => 'Successful', 'pending' => 'Pending', 'failed' => 'Failed', 'refunded' => 'Refunded'] as $key => $label)
                    <a href="{{ $key ? url('/admin/payments?status='.$key) : url('/admin/payments') }}" @if(($status ?? '') === $key) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>
        </div>
        <table class="po-table po-transactions">
            <caption class="po-sr-only">Payment transaction summary. Use View for complete transaction information.</caption>
            <colgroup><col class="po-col-order"><col class="po-col-method"><col class="po-col-amount"><col class="po-col-status"><col class="po-col-action"></colgroup>
            <thead><tr><th scope="col">Order</th><th scope="col" class="po-method">Method</th><th scope="col" class="po-money">Amount</th><th scope="col">Status</th><th scope="col" class="po-action">Action</th></tr></thead>
            <tbody>
                @forelse($transactions as $tx)
                    <tr>
                        <td><a class="po-truncate po-order" href="{{ url('/admin/orders/'.$tx->order_id) }}" title="{{ $tx->order?->order_number ?? $tx->order_id }}">#{{ $tx->order?->order_number ?? $tx->order_id }}</a></td>
                        <td class="po-method">{{ in_array(strtolower($tx->gateway ?? 'cod'), ['cod', 'cash_on_delivery']) ? 'COD' : strtoupper(str_replace('_', ' ', $tx->gateway)) }}</td>
                        <td class="po-money">&#8369;{{ number_format($tx->amount, 2) }}</td>
                        <td>@include('admin.partials.payment-status', ['paymentStatus' => $tx->status])</td>
                        <td class="po-action"><button class="po-view" type="button" data-payment-dialog="transaction-detail-{{ $tx->id }}" aria-haspopup="dialog" aria-label="View transaction {{ $tx->transaction_ref }}">View</button></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="po-empty">No transactions found.</td></tr>
                @endforelse
            </tbody>
        </table>
        @include('admin.partials.pager', ['paginator' => $transactions])
    </section>
    <noscript><p class="alert alert-warn">Enable JavaScript to open payment details. Order links and status filters remain available.</p></noscript>
</div>

@foreach(collect($escrowRemittancesList?->items() ?? [])->merge($adminRemittances?->items() ?? []) as $remit)
<dialog class="po-dialog" id="payout-detail-{{ $remit->id }}" aria-labelledby="payout-title-{{ $remit->id }}">
    <header><div><p class="po-eyebrow">Allocation #{{ $remit->id }}</p><h2 id="payout-title-{{ $remit->id }}">Payout details</h2></div><button type="button" class="po-close" data-payment-close aria-label="Close payout details">&times;</button></header>
    <dl class="po-detail-list">
        <div><dt>Order</dt><dd><a href="{{ url('/admin/orders/'.$remit->order_id) }}">#{{ $remit->order?->order_number ?? $remit->order_id }}</a></dd></div>
        <div><dt>Recipient</dt><dd>{{ $remit->recipient_name }}</dd></div>
        <div><dt>Role</dt><dd>{{ $remit->roleLabel() }}</dd></div>
        <div><dt>Amount</dt><dd class="po-detail-amount">&#8369;{{ number_format((float) $remit->amount, 2) }}</dd></div>
        <div><dt>Status</dt><dd>@include('admin.partials.payment-status', ['paymentStatus' => $remit->status])</dd></div>
        <div><dt>Payment method</dt><dd>{{ strtoupper(str_replace('_', ' ', $remit->order?->payment?->method ?? 'Unavailable')) }}</dd></div>
        <div><dt>Settlement mode</dt><dd>{{ $remit->settlementModeLabel() }}</dd></div>
        <div><dt>Allocation rule</dt><dd>{{ $remit->rule_applied ?: 'Not recorded' }}</dd></div>
        <div><dt>Transfer reference</dt><dd>{{ $remit->bank_reference ?: 'Not recorded' }}</dd></div>
        <div><dt>Released</dt><dd>{{ $remit->remitted_at?->format('M d, Y g:i A') ?? 'Not released' }}</dd></div>
        @if($remit->notes)<div><dt>Notes</dt><dd>{{ $remit->notes }}</dd></div>@endif
    </dl>
    @if($remit->status === 'pending_remittance')
        <form class="po-release-form" method="POST" action="{{ route('admin.escrow.remit', $remit->id) }}" data-payout-confirm="Release payout of &#8369;{{ number_format((float) $remit->amount, 2) }} to {{ $remit->recipient_name }}?">
            @csrf
            @if($remit->order?->payment?->method === 'gcash')
                <label for="payout-reference-{{ $remit->id }}">Transfer / retention reference</label>
                <input id="payout-reference-{{ $remit->id }}" name="bank_reference" maxlength="150" required autocomplete="off" aria-describedby="payout-help-{{ $remit->id }}">
                <p id="payout-help-{{ $remit->id }}">Record the actual transfer or retention reference for this GCash payout.</p>
            @endif
            <button class="po-button po-button-primary" type="submit">Release payout</button>
        </form>
    @elseif($remit->recipient_role === 'admin' && $remit->status === 'remitted')
        <form class="po-release-form" method="POST" action="{{ route('admin.remittances.accept', $remit->id) }}">
            @csrf
            <p>Confirm the platform has received or retained this commission before recording it as earnings.</p>
            <label><input type="checkbox" name="confirm_received" value="1" required> I confirm the platform received or retained this amount.</label>
            <button class="po-button po-button-primary" type="submit">Accept admin remittance</button>
        </form>
    @else
        <p class="po-dialog-note">{{ match($remit->status) { 'remitted' => 'Payout sent. Awaiting recipient confirmation.', 'accepted' => 'The recipient has confirmed this payout.', 'held_in_escrow' => 'Held in escrow. Release becomes available when settlement requirements are met.', default => 'No release action is available for this status.' } }}</p>
    @endif
    <footer><button type="button" class="po-button" data-payment-close>Close</button><a href="{{ url('/admin/orders/'.$remit->order_id) }}" class="po-button">Open order &rarr;</a></footer>
</dialog>
@endforeach

@foreach($transactions as $tx)
<dialog class="po-dialog" id="transaction-detail-{{ $tx->id }}" aria-labelledby="transaction-title-{{ $tx->id }}">
    <header><div><p class="po-eyebrow">Payment record</p><h2 id="transaction-title-{{ $tx->id }}">Transaction details</h2></div><button type="button" class="po-close" data-payment-close aria-label="Close transaction details">&times;</button></header>
    <dl class="po-detail-list">
        <div><dt>Reference</dt><dd>{{ $tx->transaction_ref }}</dd></div>
        <div><dt>Order</dt><dd><a href="{{ url('/admin/orders/'.$tx->order_id) }}">#{{ $tx->order?->order_number ?? $tx->order_id }}</a></dd></div>
        <div><dt>Store</dt><dd>{{ $tx->order?->seller?->store_name ?? 'Invoiz Store' }}</dd></div>
        <div><dt>Method</dt><dd>{{ strtoupper(str_replace('_', ' ', $tx->gateway ?? 'COD')) }}</dd></div>
        <div><dt>Total amount</dt><dd class="po-detail-amount">&#8369;{{ number_format($tx->amount, 2) }}</dd></div>
        <div><dt>Platform cut (10%)</dt><dd>&#8369;{{ number_format($tx->platform_commission, 2) }}</dd></div>
        <div><dt>Status</dt><dd>@include('admin.partials.payment-status', ['paymentStatus' => $tx->status])</dd></div>
        <div><dt>Date</dt><dd>{{ $tx->created_at?->format('M d, Y g:i A') ?? 'Not recorded' }}</dd></div>
    </dl>
    <footer><button type="button" class="po-button" data-payment-close>Close</button><a class="po-button" href="{{ url('/admin/orders/'.$tx->order_id) }}">Open order &rarr;</a></footer>
</dialog>
@endforeach
@endsection
