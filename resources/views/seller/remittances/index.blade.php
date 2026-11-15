@extends('seller.layouts.app')
@php
    $title = 'Remittances';
@endphp
@section('title', 'Remittances')
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/seller/css/remittances.css') }}?v={{ filemtime(public_path('assets/seller/css/remittances.css')) }}">
@endpush

@section('content')
<div class="seller-remittances">
    <header class="sr-heading">
        <div><p class="sr-eyebrow">Seller payments</p><h2>Remittances</h2><p>Track your payouts and confirm when the money reaches your account.</p></div>
        <a class="sr-secondary" href="#receiving-account" onclick="document.getElementById('receiving-account').open = true">Bank details <span aria-hidden="true">↗</span></a>
    </header>

    <section class="sr-panel" aria-labelledby="escrow-payouts-title">
        <div class="sr-panel-heading"><div><h3 id="escrow-payouts-title">Admin escrow payouts</h3><p>Your net earnings from COD and GCash orders.</p></div><span class="sr-count">{{ $escrowPayouts->total() }} {{ $escrowPayouts->total() === 1 ? 'payout' : 'payouts' }}</span></div>
        <div class="sr-columns" aria-hidden="true"><span>Order</span><span>Payment method</span><span>Transfer reference</span><span class="sr-right">Net payout</span><span>Status</span></div>
        @forelse($escrowPayouts as $payout)
            @php
                $statusLabel = match($payout->status) {
                    'held_in_escrow' => 'Held in escrow', 'pending_remittance' => 'Awaiting admin release',
                    'remitted' => 'Released - confirm receipt', 'accepted' => 'Receipt confirmed',
                    default => ucwords(str_replace('_', ' ', $payout->status)),
                };
                $statusTone = match($payout->status) {
                    'remitted' => 'released', 'accepted' => 'received', 'pending_remittance' => 'pending',
                    'voided' => 'voided', default => 'held',
                };
            @endphp
            <article class="sr-payout">
                <div class="sr-order"><a href="{{ route('seller.orders.show', $payout->order_id) }}">Order #{{ $payout->order_id }}</a><small>{{ $payout->remitted_at ? 'Released '.$payout->remitted_at->format('M j, Y') : 'Release pending' }}</small></div>
                <div class="sr-method"><span class="sr-mobile-label">Payment method</span>{{ $payout->order?->payment?->method === 'gcash' ? 'GCash' : 'Cash on delivery' }}</div>
                <div class="sr-reference"><span class="sr-mobile-label">Transfer reference</span>{{ $payout->bank_reference ?: 'Not yet recorded' }}</div>
                <div class="sr-amount"><span class="sr-mobile-label">Net payout</span>{{ peso($payout->amount) }}</div>
                <div class="sr-state"><span class="sr-status sr-status--{{ $statusTone }}"><i aria-hidden="true"></i>{{ $statusLabel }}</span></div>
                @if($payout->status === 'remitted')
                    <form method="POST" action="{{ route('seller.gcash.accept-payout', $payout) }}" class="sr-confirm">
                        @csrf
                        <label><input type="checkbox" name="confirm_received" value="1" required><span>I received this payout in my account.</span></label>
                        <button class="sr-primary" type="submit">Confirm payout received</button>
                    </form>
                @endif
            </article>
        @empty
            <div class="sr-empty"><strong>No admin escrow payouts yet.</strong><p>Your payouts will appear here once an order has an earnings allocation.</p></div>
        @endforelse
        @if($escrowPayouts->hasPages())<div class="sr-pagination">{{ $escrowPayouts->withQueryString()->links() }}</div>@endif
        <p class="sr-footnote">Confirm receipt only after the funds arrive in your bank or e-wallet.</p>
    </section>

    <details class="sr-bank" id="receiving-account" @if(old('target') || session('payout_account_saved')) open @endif>
        <summary><span><strong>Receiving account</strong><small>View or update your saved bank and e-wallet details.</small></span><span class="sr-bank-toggle" aria-hidden="true">+</span></summary>
        @include('shared.remittances.payout-account')
    </details>

    <section class="sr-panel" aria-labelledby="cash-handoffs-title">
        <div class="sr-panel-heading"><div><p class="sr-eyebrow">COD cash handoffs</p><h3 id="cash-handoffs-title">Awaiting your confirmation <span class="sr-count">{{ $pending->total() }}</span></h3><p>Cash transferred by a rider. Confirm receipt to settle the sale.</p></div><strong class="sr-total">{{ peso($pendingTotal) }}</strong></div>
        @forelse($pending as $r)
            <article class="sr-handoff">
                <div class="sr-order"><a href="{{ route('seller.orders.show', $r->order_id) }}">Order #{{ $r->order_id }}</a><small>{{ $r->rider->name ?? 'Rider not recorded' }}</small></div>
                <div class="sr-reference"><span class="sr-mobile-label">Reference</span>{{ $r->reference ?: 'Not yet recorded' }}</div>
                <strong class="sr-amount">{{ peso($r->amount) }}</strong>
                <span class="sr-status sr-status--pending"><i aria-hidden="true"></i>{{ $r->status === 'sent' ? 'Sent by rider — verify' : 'Awaiting transfer' }}</span>
                <form method="POST" action="{{ route('seller.remittances.confirm', $r->id) }}" class="sr-cash-confirm" onsubmit="return confirm('Confirm you received ₱{{ number_format($r->amount, 2) }} for order #{{ $r->order_id }}?');">
                    @csrf
                    <label for="cash-reference-{{ $r->id }}">Bank reference <small>(optional)</small></label>
                    <input id="cash-reference-{{ $r->id }}" type="text" name="reference" maxlength="150" placeholder="Enter transfer reference" value="{{ $r->reference }}">
                    <button type="submit" class="sr-primary">Confirm receipt</button>
                </form>
            </article>
        @empty
            <div class="sr-empty"><strong>No cash handoffs to confirm.</strong><p>New handoffs appear here after deliveries.</p></div>
        @endforelse
        @if($pending->hasPages())<div class="sr-pagination">@include('admin.partials.pager', ['paginator' => $pending])</div>@endif
    </section>

    <section class="sr-panel" aria-labelledby="cash-history-title">
        <div class="sr-panel-heading"><div><h3 id="cash-history-title">Confirmed cash handoffs</h3><p>Your history of confirmed rider transfers.</p></div><span class="sr-count">{{ $history->total() }} confirmed</span></div>
        @forelse($history as $r)
            <article class="sr-history">
                <div class="sr-order"><a href="{{ route('seller.orders.show', $r->order_id) }}">Order #{{ $r->order_id }}</a><small>{{ $r->rider->name ?? 'Rider not recorded' }}</small></div>
                <span class="sr-history-date">{{ $r->confirmed_at?->format('M j, Y · g:i A') ?? 'Date not recorded' }}</span>
                <strong class="sr-amount">{{ peso($r->amount) }}</strong>
                <span class="sr-status sr-status--received"><i aria-hidden="true"></i>Confirmed</span>
            </article>
        @empty
            <div class="sr-empty"><strong>No confirmed handoffs yet.</strong><p>Transfers you confirm will be saved here.</p></div>
        @endforelse
        @if($history->hasPages())<div class="sr-pagination">@include('admin.partials.pager', ['paginator' => $history])</div>@endif
    </section>
</div>
@endsection
