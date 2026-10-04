@extends('layouts.seller')

@section('title', 'Order Management')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4">
    <div>
        <h2 class="text-xl font-extrabold tracking-tight">Orders</h2>
        <p class="mt-0.5 text-sm text-ink-light">Review, prepare, and ship your customers' orders.</p>
    </div>
    <span class="badge badge-pending"><span class="badge-dot"></span>{{ $counts['pending'] }} new order(s) awaiting review</span>
</div>

{{-- Status tabs --}}
<div class="mt-6 flex flex-wrap gap-2">
    @php
        $tabs = ['all', 'pending', 'confirmed', 'processing', 'ready_for_delivery', 'out_for_delivery', 'delivered', 'cancelled'];
        $labels = ['all' => 'All', 'pending' => 'Pending', 'confirmed' => 'Confirmed', 'processing' => 'Processing', 'ready_for_delivery' => 'Ready', 'out_for_delivery' => 'In Transit', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];
    @endphp
    @foreach($tabs as $tab)
        <a href="{{ route('seller.orders.index', ['status' => $tab]) }}"
           class="badge {{ $status === $tab ? '!bg-[#16697A] !text-white' : 'bg-white text-ink-light border border-borderline hover:bg-soft' }}">
            {{ $labels[$tab] }}
            @if($counts[$tab] > 0)<span class="ml-1">{{ $counts[$tab] }}</span>@endif
        </a>
    @endforeach
</div>

{{-- Orders table --}}
<div class="card mt-4 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Buyer</th>
                    <th>Items</th>
                    <th>Your Total</th>
                    <th>Status</th>
                    <th>Placed</th>
                    <th class="text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                    <tr>
                        <td>
                            <a href="{{ route('seller.orders.show', $order->id) }}" class="font-bold text-primary hover:underline">#{{ $order->id }}</a>
                            <div class="text-[11px] text-ink-light">{{ $order->payment?->method === 'cash_on_delivery' ? 'COD' : '—' }}</div>
                        </td>
                        <td>
                            <div class="font-semibold">{{ $order->buyer->full_name }}</div>
                            <div class="text-[11px] text-ink-light">{{ $order->buyer->email }}</div>
                        </td>
                        <td>
                            <div class="font-semibold">{{ $order->seller_items->sum('quantity') }}</div>
                            <div class="text-[11px] text-ink-light">{{ $order->seller_items->count() }} line(s)</div>
                        </td>
                        <td class="font-bold">{{ peso($order->seller_subtotal) }}</td>
                        <td><span class="badge badge-{{ $order->status }}">{{ order_status_label($order->status) }}</span></td>
                        <td class="text-ink-light">{{ $order->created_at->format('M j, g:i A') }}</td>
                        <td class="text-right">
                            <a href="{{ route('seller.orders.show', $order->id) }}" class="btn btn-outline btn-sm">
                                <x-icon name="eye" class="h-4 w-4" /> Review
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-16 text-center">
                            <x-icon name="shopping-bag" class="mx-auto h-10 w-10 text-ink-light" />
                            <p class="mt-3 font-semibold">No orders here yet</p>
                            <p class="text-sm text-ink-light">Orders placed by buyers will show up here.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection