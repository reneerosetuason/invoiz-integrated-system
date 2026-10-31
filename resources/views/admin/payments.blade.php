@extends('admin.layout')

@section('content')
<style>
  .pay-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: auto;
  }
  .pay-table th {
    font-size: 10.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: #6E6E73;
    padding: 10px 12px;
    border-bottom: 1px solid #E8E6E0;
    background: #FAFAF8;
    text-align: left;
    white-space: nowrap;
  }
  .pay-table td {
    padding: 11px 12px;
    border-bottom: 1px solid #F1F0EC;
    font-size: 13px;
    color: #1B1B1E;
    white-space: nowrap;
    vertical-align: middle;
  }
  .pay-table tr:hover td { background: #FAF9F6; }
  .pay-table tr:last-child td { border-bottom: none; }
</style>

<div class="card" style="padding: 20px;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:14px; gap:16px; flex-wrap:wrap;">
        <div>
            <h1 class="page-title" style="margin-bottom:4px;">Payments &amp; Escrow Remittances</h1>
            <p style="color:var(--text-secondary); font-size:13px; margin:0;">Track customer order transactions, reconcile platform commission, and release stakeholder remittances.</p>
        </div>
    </div>

    @if(session('status'))
    <div class="alert alert-success" style="font-weight:600; font-size:13px; margin-bottom:14px;">{{ session('status') }}</div>
    @endif
    @if(session('error'))
    <div class="alert alert-danger" style="font-weight:600; font-size:13px; margin-bottom:14px;">{{ session('error') }}</div>
    @endif

    {{-- Top Stats --}}
    <div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));margin-bottom:14px;">
        <div class="stat-card" style="border-left:3px solid #16697A; padding:12px 14px;">
            <span style="font-size:10.5px; font-weight:700; color:#6E6E73; text-transform:uppercase;">All Transactions</span>
            <strong style="font-size:20px; font-weight:800; margin-top:2px; display:block;">{{ $stats['all'] }}</strong>
            <div style="font-size:11px;color:#6E6E73;margin-top:2px;">₱{{ number_format($stats['all_amount'], 2) }} volume</div>
        </div>
        <div class="stat-card" style="border-left:3px solid #10B981; padding:12px 14px;">
            <span style="font-size:10.5px; font-weight:700; color:#6E6E73; text-transform:uppercase;">Successful</span>
            <strong style="font-size:20px; font-weight:800; color:#059669; margin-top:2px; display:block;">{{ $stats['successful'] }}</strong>
            <div style="font-size:11px;color:#6E6E73;margin-top:2px;">₱{{ number_format($stats['successful_amount'], 2) }}</div>
        </div>
        <div class="stat-card" style="border-left:3px solid #F59E0B; padding:12px 14px;">
            <span style="font-size:10.5px; font-weight:700; color:#6E6E73; text-transform:uppercase;">Pending</span>
            <strong style="font-size:20px; font-weight:800; color:#D97706; margin-top:2px; display:block;">{{ $stats['pending'] }}</strong>
            <div style="font-size:11px;color:#6E6E73;margin-top:2px;">₱{{ number_format($stats['pending_amount'], 2) }}</div>
        </div>
        <div class="stat-card" style="border-left:3px solid #EF4444; padding:12px 14px;">
            <span style="font-size:10.5px; font-weight:700; color:#6E6E73; text-transform:uppercase;">Failed</span>
            <strong style="font-size:20px; font-weight:800; color:#DC2626; margin-top:2px; display:block;">{{ $stats['failed'] }}</strong>
            <div style="font-size:11px;color:#6E6E73;margin-top:2px;">₱{{ number_format($stats['failed_amount'], 2) }}</div>
        </div>
        <div class="stat-card" style="border-left:3px solid #8B5CF6; padding:12px 14px;">
            <span style="font-size:10.5px; font-weight:700; color:#6E6E73; text-transform:uppercase;">Refunded</span>
            <strong style="font-size:20px; font-weight:800; color:#7C3AED; margin-top:2px; display:block;">{{ $stats['refunded'] }}</strong>
            <div style="font-size:11px;color:#6E6E73;margin-top:2px;">₱{{ number_format($stats['refunded_amount'], 2) }} returned</div>
        </div>
        <div class="stat-card" style="border-left:3px solid #0E4A57; padding:12px 14px;">
            <span style="font-size:10.5px; font-weight:700; color:#6E6E73; text-transform:uppercase;">Admin Commission</span>
            <strong style="font-size:20px; font-weight:800; color:#0E4A57; margin-top:2px; display:block;">₱{{ number_format($stats['admin_earnings'], 2) }}</strong>
            <div style="font-size:11px;color:#6E6E73;margin-top:2px;">10% platform share</div>
        </div>
    </div>

    {{-- Earnings Distribution Strip --}}
    <div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-bottom:20px;">
        <div style="background:#F0EEE9;border-radius:10px;padding:12px 16px;">
            <div style="font-size:11px;font-weight:700;color:#6E6E73;text-transform:uppercase;letter-spacing:.05em;">Platform Commission (10%)</div>
            <div style="font-size:19px;font-weight:800;color:#16697A;margin-top:2px;">₱{{ number_format($stats['commission'], 2) }}</div>
        </div>
        <div style="background:#F0EEE9;border-radius:10px;padding:12px 16px;">
            <div style="font-size:11px;font-weight:700;color:#6E6E73;text-transform:uppercase;letter-spacing:.05em;">Seller Net Earnings</div>
            <div style="font-size:19px;font-weight:800;color:#10B981;margin-top:2px;">₱{{ number_format($stats['seller_earnings'], 2) }}</div>
        </div>
        <div style="background:#F0EEE9;border-radius:10px;padding:12px 16px;">
            <div style="font-size:11px;font-weight:700;color:#6E6E73;text-transform:uppercase;letter-spacing:.05em;">Logistics &amp; Rider Fees</div>
            <div style="font-size:19px;font-weight:800;color:#0E4A57;margin-top:2px;">₱{{ number_format(($stats['rider_fees'] ?? 0) + ($stats['origin_hub_fees'] ?? 0) + ($stats['destination_hub_fees'] ?? 0), 2) }}</div>
        </div>
    </div>

    {{-- ============================ ESCROW REMITTANCES ============================ --}}
    <div style="background:#F7FAF9; border:1px solid #D2E7E4; border-radius:14px; padding:18px; margin-bottom:24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; gap:12px; flex-wrap:wrap;">
            <div>
                <h2 style="font-size:16px; font-weight:800; color:#0E4A57; margin:0;">Escrow Remittances &amp; Stakeholder Payouts</h2>
                <p style="color:#56696B; font-size:12px; margin:3px 0 0;">Approve and release payouts to Pickup Riders, Drivers, and Hubs. Admin 10% commission is booked automatically upon payout release.</p>
            </div>
            @if(isset($pendingEscrowRemittances) && $pendingEscrowRemittances->count() > 0)
                <form method="POST" action="{{ route('admin.escrow.remit-all') }}" onsubmit="return confirm('Release all {{ $pendingEscrowRemittances->count() }} pending payouts totaling ₱{{ number_format($pendingEscrowTotal ?? 0, 2) }}?');">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm" style="font-weight:700; display:inline-flex; align-items:center; gap:5px;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>Release All Pending (₱{{ number_format($pendingEscrowTotal ?? 0, 2) }})</span>
                    </button>
                </form>
            @endif
        </div>

        {{-- Single-line Clean Table (Fits 100%, No Horizontal Scroll) --}}
        <div style="background:#fff; border:1px solid #E8E6E0; border-radius:10px; overflow:hidden;">
            <table class="pay-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Recipient</th>
                        <th>Role</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Mode</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($escrowRemittancesList ?? [] as $remit)
                        <tr>
                            <td>
                                <a href="{{ url('/admin/orders/'.($remit->order_id ?? '')) }}" style="font-weight:700; color:#16697A; text-decoration:none; font-family:monospace;">
                                    #{{ $remit->order?->order_number ?? $remit->order_id }}
                                </a>
                            </td>
                            <td>
                                <span style="font-weight:600; color:#1B1B1E;" title="{{ $remit->rule_applied }}">
                                    {{ Str::limit($remit->recipient_name, 22) }}
                                </span>
                            </td>
                            <td>
                                <span style="font-size:11px; font-weight:700; padding:2px 7px; border-radius:999px; background:#F0EEE9; color:#374151;">
                                    {{ $remit->roleLabel() }}
                                </span>
                            </td>
                            <td style="font-weight:800; font-size:13.5px; color:#0E4A57;">
                                ₱{{ number_format((float) $remit->amount, 2) }}
                            </td>
                            <td>
                                @if($remit->status === 'accepted')
                                    <span style="display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:700; color:#166534; background:#E8F5EE; border:1px solid #BFE3D0; padding:2px 7px; border-radius:999px;">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#166534" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                        Accepted
                                    </span>
                                @elseif($remit->status === 'remitted')
                                    <span style="font-size:11px; font-weight:700; color:#1E40AF; background:#DBEAFE; border:1px solid #BFDBFE; padding:2px 7px; border-radius:999px;">
                                        Remitted
                                    </span>
                                @elseif($remit->status === 'pending_remittance')
                                    <span style="display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:700; color:#92400E; background:#FEF3C7; border:1px solid #FDE68A; padding:2px 7px; border-radius:999px;">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#92400E" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                        Ready to Remit
                                    </span>
                                @else
                                    <span style="font-size:11px; font-weight:600; color:#6B7280; background:#F3F4F6; padding:2px 7px; border-radius:999px;">
                                        Held in Escrow
                                    </span>
                                @endif
                            </td>
                            <td style="font-size:12px; color:#6E6E73;">
                                {{ $remit->settlementModeLabel() }}
                            </td>
                            <td style="text-align: right;">
                                @if($remit->status === 'pending_remittance')
                                    <form method="POST" action="{{ route('admin.escrow.remit', $remit->id) }}" style="display:inline;" onsubmit="return confirm('Release payout of ₱{{ number_format((float) $remit->amount, 2) }} to {{ $remit->recipient_name }}?');">
                                        @csrf
                                        <button type="submit" class="btn btn-primary btn-xs" style="font-weight:700;">
                                            Release Payout
                                        </button>
                                    </form>
                                @elseif($remit->status === 'remitted')
                                    <span style="font-size:11.5px; color:#1E40AF; font-weight:600;">Sent</span>
                                @elseif($remit->status === 'accepted')
                                    <span style="display:inline-flex; align-items:center; gap:4px; font-size:11.5px; color:#166534; font-weight:700;">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#166534" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                        Confirmed
                                    </span>
                                @else
                                    <span style="font-size:11px; color:#9CA3AF;">Awaiting COD</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="padding:24px; text-align:center; color:#6E6E73; font-size:12.5px;">
                                No escrow remittances available yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            @if(isset($escrowRemittancesList) && method_exists($escrowRemittancesList, 'hasPages') && $escrowRemittancesList->hasPages())
                @include('admin.partials.pager', ['paginator' => $escrowRemittancesList])
            @endif
        </div>
    </div>

    {{-- ============================ ALL TRANSACTIONS ============================ --}}
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; gap:12px; flex-wrap:wrap;">
        <h2 id="transactions" style="font-size:16px; font-weight:800; margin:0;">All Payment Transactions</h2>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
            @foreach (['' => 'All', 'successful' => 'Successful', 'pending' => 'Pending', 'failed' => 'Failed', 'refunded' => 'Refunded'] as $key => $label)
            <a href="{{ $key ? url('/admin/payments?status='.$key) : url('/admin/payments') }}" style="padding:4px 12px;border-radius:999px;font-size:12px;font-weight:600;text-decoration:none;{{ ($status ?? '') === $key ? 'background:#16697A;color:#fff;' : 'background:#F0EEE9;color:#374151;' }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>

    {{-- Single-line Transactions Table (Fits 100%, No Horizontal Scroll) --}}
    <div style="background:#fff; border:1px solid #E8E6E0; border-radius:10px; overflow-x:auto;">
        <table class="pay-table">
            <thead>
                <tr>
                    <th>Ref #</th>
                    <th>Order #</th>
                    <th>Store</th>
                    <th>Method</th>
                    <th>Total Amount</th>
                    <th>Platform Cut (10%)</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $tx)
                @php
                    $txCls = match($tx->status) {
                        'successful' => 'background:#ECFDF5;color:#065F46;border:1px solid #A7F3D0;',
                        'failed' => 'background:#FEF2F2;color:#991B1B;border:1px solid #FECACA;',
                        default => 'background:#FFFBEB;color:#92400E;border:1px solid #FDE68A;',
                    };
                @endphp
                <tr>
                    <td style="font-family:monospace;font-size:12px;color:#1B1B1E;">{{ Str::limit($tx->transaction_ref, 14) }}</td>
                    <td>
                        <a href="{{ url('/admin/orders/'.$tx->order_id) }}" style="color:#16697A;text-decoration:none;font-weight:700;font-family:monospace;" title="{{ $tx->order->order_number ?? $tx->order_id }}">
                            #{{ Str::limit($tx->order->order_number ?? (string)$tx->order_id, 14) }}
                        </a>
                    </td>
                    <td>
                        <span style="color:#475569;" title="{{ $tx->order->seller->store_name ?? 'Invoiz Store' }}">{{ Str::limit($tx->order->seller->store_name ?? 'Invoiz Store', 14) }}</span>
                    </td>
                    <td style="color:#6E6E73;font-size:12px;">{{ strtoupper($tx->gateway ?? 'COD') }}</td>
                    <td style="font-weight:700;color:#0E4A57;">₱{{ number_format($tx->amount, 2) }}</td>
                    <td style="font-weight:600;color:#16697A;">₱{{ number_format($tx->platform_commission, 2) }}</td>
                    <td>
                        <span style="font-size:11px;font-weight:700;padding:2px 7px;border-radius:999px;{{ $txCls }}">
                            {{ ucfirst($tx->status) }}
                        </span>
                    </td>
                    <td style="font-size:12px;color:#6E6E73;">{{ $tx->created_at?->format('M d, Y') ?? '—' }}</td>
                    <td style="text-align: right;">
                        <a href="{{ url('/admin/orders/'.$tx->order_id) }}" class="btn btn-soft btn-xs" style="padding:4px 10px;font-weight:700;font-size:11.5px;border-radius:6px;text-decoration:none;">
                            View
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" style="padding:24px;text-align:center;color:#6E6E73;font-size:12.5px;">No transactions found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        @include('admin.partials.pager', ['paginator' => $transactions])
    </div>
</div>
@endsection
