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
                        <td>
                            <a class="font-bold" href="{{ route('seller.orders.show', $r->order_id) }}">Order #{{ $r->order_id }}</a>
                            <div class="text-xs text-ink-light">Return #{{ $r->id }}</div>
                        </td>
                        <td>
                            {{ $r->item?->product_name ?: ($r->product->name ?? 'Unavailable item') }} <span class="text-ink-light">×{{ $r->quantity }}</span>
                            @if($r->item?->variant_label)<div class="text-xs text-ink-light">{{ $r->item->variant_label }}</div>@endif
                        </td>
                        <td>
                            <div class="font-semibold">{{ ucwords(str_replace('_', ' ', $r->reason)) }}</div>
                            @if($r->reason_details)<div class="text-xs text-ink-light">{{ $r->reason_details }}</div>@endif
                        </td>
                        <td class="text-right font-bold">{{ peso($r->refund_amount) }}</td>
                        <td>
                            <span class="badge {{ $r->status === 'refunded' ? 'bg-[#E8F5EE] !text-successc' : ($r->status === 'rejected' ? 'bg-red-50 !text-red-700' : 'badge-pending') }}">
                                {{ $r->status === 'refunded' ? 'Refund recorded' : ucwords(str_replace('_', ' ', $r->status)) }}
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
                                    <button name="action" value="receive" class="btn btn-primary btn-xs">Receive &amp; record refund</button>
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
