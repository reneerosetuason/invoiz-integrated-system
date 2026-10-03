@extends('layouts.seller')

@section('title', 'Order #'.$order->id)

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4">
    <div>
        <a href="{{ route('seller.orders.index') }}" class="mb-2 inline-flex items-center gap-1 text-sm font-semibold text-ink-light hover:text-primary">
            <x-icon name="arrow-left" class="h-4 w-4" /> Back to orders
        </a>
        <div class="flex items-center gap-3">
            <h2 class="text-xl font-extrabold tracking-tight">Order #{{ $order->id }}</h2>
            <span class="badge badge-{{ $order->status }}"><span class="badge-dot"></span>{{ order_status_label($order->status) }}</span>
        </div>
        <p class="mt-0.5 text-sm text-ink-light">Placed {{ $order->created_at->format('F j, Y · g:i A') }}</p>
    </div>
    <div class="flex flex-wrap gap-2 no-print">
        @if($order->status === 'pending')
            <form method="POST" action="{{ route('seller.orders.status', $order->id) }}">
                @csrf
                <input type="hidden" name="action" value="cancel">
                <button type="submit" class="btn btn-danger">Cancel Order</button>
            </form>
            <form method="POST" action="{{ route('seller.orders.status', $order->id) }}">
                @csrf
                <input type="hidden" name="action" value="accept">
                <button type="submit" class="btn btn-primary">Accept Order</button>
            </form>
        @endif
        @if($order->status === 'confirmed')
            <form method="POST" action="{{ route('seller.orders.status', $order->id) }}">
                @csrf
                <input type="hidden" name="action" value="cancel">
                <button type="submit" class="btn btn-danger">Cancel Order</button>
            </form>
            <form method="POST" action="{{ route('seller.orders.status', $order->id) }}">
                @csrf
                <input type="hidden" name="action" value="process">
                <button type="submit" class="btn btn-primary">Start Preparing</button>
            </form>
        @endif
        @if($order->status === 'processing')
            <a href="{{ route('seller.orders.waybill', $order->id) }}" target="_blank" class="btn btn-outline">
                <x-icon name="printer" class="h-4 w-4" /> Print Waybill
            </a>
            <button type="button" @click="$refs.pickupModal.showModal()" class="btn btn-outline">
                <x-icon name="truck" class="h-4 w-4" /> Schedule Pickup
            </button>
            <form method="POST" action="{{ route('seller.orders.status', $order->id) }}">
                @csrf
                <input type="hidden" name="action" value="pack">
                <button type="submit" class="btn btn-primary">Mark as Packed</button>
            </form>
        @endif
        @if($order->status === 'ready_for_delivery')
            <a href="{{ route('seller.orders.waybill', $order->id) }}" target="_blank" class="btn btn-outline">
                <x-icon name="printer" class="h-4 w-4" /> Print Waybill
            </a>
            <button type="button" @click="$refs.pickupModal.showModal()" class="btn btn-outline">
                <x-icon name="truck" class="h-4 w-4" /> Schedule Pickup
            </button>
            <form method="POST" action="{{ route('seller.orders.status', $order->id) }}">
                @csrf
                <input type="hidden" name="action" value="handover">
                <button type="submit" class="btn btn-primary">Hand Over to Courier</button>
            </form>
        @endif
        @if($order->status === 'out_for_delivery')
            <a href="{{ route('seller.orders.waybill', $order->id) }}" target="_blank" class="btn btn-outline">
                <x-icon name="printer" class="h-4 w-4" /> Print Waybill
            </a>
            <form method="POST" action="{{ route('seller.orders.status', $order->id) }}">
                @csrf
                <input type="hidden" name="action" value="deliver">
                <button type="submit" class="btn btn-primary">
                    <x-icon name="check" class="h-4 w-4" /> Confirm Delivery
                </button>
            </form>
        @endif
        @if($order->status === 'delivered')
            <a href="{{ route('seller.orders.waybill', $order->id) }}" target="_blank" class="btn btn-outline">
                <x-icon name="printer" class="h-4 w-4" /> Print Waybill
            </a>
            <span class="badge badge-delivered self-center">Completed</span>
        @endif
    </div>
</div>

<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
    {{-- Left column: items + totals --}}
    <div class="space-y-6 xl:col-span-2">
        <div class="card overflow-hidden">
            <div class="border-b border-borderline px-6 py-4">
                <h3 class="text-base font-bold">Items to Fulfill</h3>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Variant</th>
                        <th class="text-center">Qty</th>
                        <th class="text-right">Price</th>
                        <th class="text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->seller_items as $item)
                        <tr>
                            <td>
                                <div class="font-semibold">{{ $item->product_name }}</div>
                                <div class="text-[11px] text-ink-light">Product #{{ $item->product_id }}</div>
                            </td>
                            <td>{{ $item->variant_label ?? '—' }}</td>
                            <td class="text-center font-semibold">{{ $item->quantity }}</td>
                            <td class="text-right">{{ peso($item->price) }}</td>
                            <td class="text-right font-semibold">{{ peso($item->subtotal) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="space-y-1.5 border-t border-borderline px-6 py-4 text-sm">
                <div class="flex justify-between"><span class="text-ink-light">Subtotal (your items)</span><span class="font-semibold">{{ peso($order->seller_subtotal) }}</span></div>
                @foreach($order->orderVouchers as $ov)
                    <div class="flex justify-between">
                        <span class="text-ink-light">Voucher {{ $ov->voucher->code }}</span>
                        <span class="font-semibold text-successc">− {{ peso($ov->discount_amount) }}</span>
                    </div>
                @endforeach
                <div class="flex justify-between border-t border-borderline pt-2 text-base">
                    <span class="font-bold">Order Total</span>
                    <span class="font-extrabold">{{ peso($order->total_amount) }}</span>
                </div>
            </div>
        </div>

        {{-- Status timeline --}}
        <div class="card p-6">
            <h3 class="mb-4 text-base font-bold">Status Timeline</h3>
            <ol class="relative space-y-5 border-l-2 border-borderline pl-6">
                @forelse($order->statusHistories->sortBy('created_at') as $history)
                    <li class="relative">
                        <span class="absolute -left-[31px] flex h-5 w-5 items-center justify-center rounded-full border-2 border-[#16697A] bg-white">
                            <span class="h-2 w-2 rounded-full bg-[#16697A]"></span>
                        </span>
                        <div class="text-sm font-bold">{{ order_status_label($history->to_status) }}</div>
                        <div class="text-xs text-ink-light">{{ $history->note }}</div>
                        <div class="text-[11px] text-ink-light">{{ $history->created_at->format('M j, Y · g:i A') }}</div>
                    </li>
                @empty
                    <li class="text-sm text-ink-light">No status history yet.</li>
                @endforelse
            </ol>
        </div>
    </div>

    {{-- Right column --}}
    <div class="space-y-6">
        {{-- Buyer & shipping --}}
        <div class="card p-6">
            <h3 class="mb-4 text-base font-bold">Buyer &amp; Shipping</h3>
            <div class="flex items-center gap-3">
                <div class="avatar h-10 w-10">{{ strtoupper(substr($order->buyer->first_name,0,1).substr($order->buyer->last_name,0,1)) }}</div>
                <div>
                    <div class="font-bold">{{ $order->buyer->full_name }}</div>
                    <div class="text-xs text-ink-light">{{ $order->buyer->email }}</div>
                    <div class="text-xs text-ink-light">{{ $order->buyer->phone }}</div>
                </div>
            </div>
            <div class="mt-4 rounded-2xl bg-basebg p-4">
                <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-ink-light">
                    <x-icon name="map-pin" class="h-4 w-4" /> Shipping Address
                </div>
                <p class="mt-2 text-sm leading-relaxed">
                    {{ $order->address?->recipient_name }}<br>
                    {{ $order->address?->address_line }}<br>
                    {{ $order->address?->barangay }}, {{ $order->address?->city }}<br>
                    {{ $order->address?->province }} {{ $order->address?->postal_code }}<br>
                    <span class="text-ink-light">{{ $order->address?->phone }}</span>
                </p>
            </div>
            @if($order->notes)
                <div class="mt-4 rounded-2xl bg-[#FFF8E7] p-4 text-sm">
                    <span class="font-bold">Buyer note: </span>{{ $order->notes }}
                </div>
            @endif
        </div>

        {{-- Payment & delivery --}}
        <div class="card p-6">
            <h3 class="mb-4 text-base font-bold">Payment &amp; Delivery</h3>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between"><span class="text-ink-light">Payment</span><span class="font-semibold">{{ $order->payment?->method === 'cash_on_delivery' ? 'Cash on Delivery' : ucwords(str_replace('_',' ',$order->payment?->method ?? '—')) }}</span></div>
                <div class="flex justify-between"><span class="text-ink-light">Payment status</span><span class="badge {{ $order->payment?->status === 'paid' ? 'badge-delivered' : 'badge-pending' }}">{{ ucfirst($order->payment?->status ?? '—') }}</span></div>
                <div class="flex justify-between"><span class="text-ink-light">Delivery</span><span class="font-semibold">{{ $order->delivery ? delivery_status_label($order->delivery->status) : 'Waiting for rider' }}</span></div>
                @if($order->courierPickup)
                    <div class="mt-2 rounded-2xl bg-basebg p-4">
                        <div class="text-xs font-bold uppercase tracking-wider text-ink-light">Pickup Schedule</div>
                        <div class="mt-1 font-semibold">{{ $order->courierPickup->courier }}</div>
                        <div class="text-xs text-ink-light">{{ $order->courierPickup->pickup_at?->format('M j, Y · g:i A') }}</div>
                        @if($order->courierPickup->tracking_number)
                            <div class="mt-1 text-xs">Tracking: <span class="font-bold">{{ $order->courierPickup->tracking_number }}</span></div>
                        @endif
                        <span class="badge badge-{{ $order->courierPickup->status }} mt-2">{{ ucfirst($order->courierPickup->status) }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Schedule pickup modal --}}
<dialog x-ref="pickupModal" class="m-auto w-full max-w-md rounded-2xl border border-borderline bg-white p-0 shadow-2xl backdrop:bg-black/40">
    <form method="POST" action="{{ route('seller.orders.pickup', $order->id) }}" class="p-6">
        @csrf
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-lg font-extrabold">Schedule Courier Pickup</h3>
            <button type="button" @click="$refs.pickupModal.close()" class="rounded-xl p-1.5 text-ink-light hover:bg-soft">
                <x-icon name="x" class="h-5 w-5" />
            </button>
        </div>
        <div class="space-y-4">
            <div>
                <label class="field-label">Courier</label>
                <select name="courier" class="select" required>
                    <option value="">Select courier...</option>
                    <option value="Lalamove">Lalamove</option>
                    <option value="GrabExpress">GrabExpress</option>
                    <option value="J&T Express">J&amp;T Express</option>
                    <option value="Shopee Xpress">Shopee Xpress</option>
                    <option value="Flash Express">Flash Express</option>
                    <option value="In-house Rider">In-house Rider</option>
                </select>
            </div>
            <div>
                <label class="field-label">Pickup Date &amp; Time</label>
                <input type="datetime-local" name="pickup_at" class="input" required>
            </div>
            <div>
                <label class="field-label">Tracking Number (optional)</label>
                <input type="text" name="tracking_number" class="input" placeholder="e.g. JNT20260815XXXX">
            </div>
            <div>
                <label class="field-label">Notes (optional)</label>
                <textarea name="notes" rows="3" class="textarea" placeholder="Packing instructions for the courier..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary w-full">Schedule Pickup</button>
        </div>
    </form>
</dialog>
@endsection