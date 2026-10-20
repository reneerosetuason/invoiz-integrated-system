@extends('layouts.seller')

@section('title', 'Returns & Refunds')

@section('content')
<div>
    <h2 class="text-xl font-extrabold tracking-tight">Returns &amp; Refunds</h2>
    <p class="mt-0.5 text-sm text-ink-light">Buyers may request a return within 7 days of delivery. Approve it, then confirm when the item arrives back — the buyer is refunded and stock is restored automatically.</p>
</div>

@if(session('success'))
    <div class="card mt-4 border-successc bg-[#E8F5EE] px-4 py-3 text-sm font-semibold text-successc">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="card mt-4 border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{{ session('error') }}</div>
@endif

<div class="grid gap-4 sm:grid-cols-2 mt-4">
    <div class="card px-4 py-3">
        <div class="text-xs font-bold uppercase tracking-wide text-ink-light">Awaiting decision</div>
        <div class="mt-1 text-2xl font-extrabold text-amber-700">{{ $counts['requested'] }}</div>
    </div>
    <div class="card px-4 py-3">
        <div class="text-xs font-bold uppercase tracking-wide text-ink-light">Refunded</div>
        <div class="mt-1 text-2xl font-extrabold text-successc">{{ $counts['refunded'] }}</div>
    </div>
</div>

<div class="card mt-4 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Product</th>
                    <th>Reason</th>
                    <th class="text-right">Refund</th>
                    <th>Status</th>
                    <th class="text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($returns as $r)
                    <tr>
                        <td class="font-bold">#{{ $r->order_id }}</td>
                        <td>{{ $r->product->name ?? '—' }} <span class="text-ink-light">×{{ $r->quantity }}</span></td>
                        <td>
                            <div class="font-semibold">{{ ucwords(str_replace('_', ' ', $r->reason)) }}</div>
                            @if($r->reason_details)<div class="text-xs text-ink-light">{{ $r->reason_details }}</div>@endif
                        </td>
                        <td class="text-right font-bold">{{ peso($r->refund_amount) }}</td>
                        <td>
                            <span class="badge {{ $r->status === 'refunded' ? 'bg-[#E8F5EE] !text-successc' : ($r->status === 'rejected' ? 'bg-red-50 !text-red-700' : 'badge-pending') }}">
                                {{ ucwords(str_replace('_', ' ', $r->status)) }}
                            </span>
                        </td>
                        <td class="text-right">
                            @if($r->status === 'requested')
                                <form method="POST" action="{{ route('seller.returns.update', $r->id) }}" class="flex justify-end gap-1.5">
                                    @csrf
                                    <input type="text" name="note" maxlength="500" placeholder="Note (optional)" class="input !w-36 !py-1.5 !text-xs">
                                    <button name="action" value="approve" class="btn btn-primary btn-xs">Approve</button>
                                    <button name="action" value="reject" class="btn btn-ghost btn-xs">Reject</button>
                                </form>
                            @elseif($r->status === 'approved')
                                <form method="POST" action="{{ route('seller.returns.update', $r->id) }}">
                                    @csrf
                                    <button name="action" value="receive" class="btn btn-primary btn-xs">Mark received &amp; refund</button>
                                </form>
                            @else
                                <span class="text-xs text-ink-light">{{ $r->resolved_at?->format('M j, Y') ?? '—' }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-10 text-center text-ink-light">No return requests yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        @include('admin.partials.pager', ['paginator' => $returns])
    </div>
</div>
@endsection
