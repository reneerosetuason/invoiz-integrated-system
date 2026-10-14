@extends('layouts.seller')

@section('title', 'Cash Remittances')

@section('content')
<div>
    <h2 class="text-xl font-extrabold tracking-tight">Cash Remittances</h2>
    <p class="mt-0.5 text-sm text-ink-light">Riders collect COD cash and transfer your share bank-to-bank. Confirm receipt below — the sale only settles (paid + booked) after you verify.</p>
</div>

@if(session('success'))
    <div class="card mt-4 border-successc bg-[#E8F5EE] px-4 py-3 text-sm font-semibold text-successc">{{ session('success') }}</div>
@endif

<div class="card mt-4 overflow-hidden">
    <div class="flex items-center justify-between px-4 pt-4 pb-2.5">
        <h3 class="text-sm font-bold">Awaiting your confirmation @if($pending->isNotEmpty())<span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-800">{{ $pending->total() }}</span>@endif</h3>
        <span class="text-sm font-extrabold text-primary">{{ peso($pendingTotal) }}</span>
    </div>
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Rider</th>
                    <th class="text-right">Amount</th>
                    <th>Status</th>
                    <th>Reference</th>
                    <th class="text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pending as $r)
                    <tr>
                        <td><a href="{{ route('seller.orders.show', $r->order_id) }}" class="font-bold text-primary hover:underline">#{{ $r->order_id }}</a></td>
                        <td>{{ $r->rider->name ?? '—' }}</td>
                        <td class="text-right font-bold">{{ peso($r->amount) }}</td>
                        <td>
                            @if($r->status === 'sent')
                                <span class="badge bg-soft !text-ink-light">Sent by rider — verify</span>
                            @else
                                <span class="badge badge-pending">Awaiting transfer</span>
                            @endif
                        </td>
                        <td class="text-ink-light">{{ $r->reference ?? '—' }}</td>
                        <td class="text-right">
                            <form method="POST" action="{{ route('seller.remittances.confirm', $r->id) }}" class="flex justify-end gap-1.5" onsubmit="return confirm('Confirm you received ₱{{ number_format($r->amount, 2) }} for order #{{ $r->order_id }}?');">
                                @csrf
                                <input type="text" name="reference" maxlength="150" placeholder="Bank ref (optional)" value="{{ $r->reference }}" class="input !w-36 !py-1.5 !text-xs">
                                <button type="submit" class="btn btn-primary btn-xs">Confirm receipt</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-10 text-center text-ink-light">Nothing awaiting confirmation. New handoffs appear here after deliveries.</td></tr>
                @endforelse
            </tbody>
        </table>
        @include('admin.partials.pager', ['paginator' => $pending])
    </div>
</div>

<div class="card mt-4 overflow-hidden">
    <div class="px-4 pt-4 pb-2.5">
        <h3 class="text-sm font-bold">Confirmed history</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Rider</th>
                    <th class="text-right">Amount</th>
                    <th>Confirmed</th>
                </tr>
            </thead>
            <tbody>
                @forelse($history as $r)
                    <tr>
                        <td class="font-bold">#{{ $r->order_id }}</td>
                        <td>{{ $r->rider->name ?? '—' }}</td>
                        <td class="text-right font-semibold">{{ peso($r->amount) }}</td>
                        <td class="text-ink-light">{{ $r->confirmed_at?->format('M j, Y g:i A') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-8 text-center text-ink-light">No confirmed handoffs yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        @include('admin.partials.pager', ['paginator' => $history])
    </div>
</div>
@endsection
