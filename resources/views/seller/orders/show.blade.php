@extends('seller.layouts.app')

@section('title', 'Order #'.$order->id)

@section('content')
@php
    $shippingAddress = (object) ($order->shipping_address ?: ($order->address?->toShippingSnapshot() ?? []));
    $pickupReview = $order->delivery?->pickupRequest;
    $selectedCenter = $order->delivery?->logisticsCenter ?? $pickupReview?->center;
    $assignedRider = $order->delivery?->rider;
@endphp
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
            <button type="button" @click="$refs.pickupModal.showModal()" class="btn btn-primary">
                <x-icon name="truck" class="h-4 w-4" /> {{ $selectedCenter ? 'Update Pickup Hub' : 'Schedule Pickup' }}
            </button>
            <form method="POST" action="{{ route('seller.orders.status', $order->id) }}">
                @csrf
                <input type="hidden" name="action" value="pack">
                <button type="submit" class="btn btn-outline">Mark as Packed</button>
            </form>
        @endif
        @if($order->status === 'ready_for_delivery')
            <a href="{{ route('seller.orders.waybill', $order->id) }}" target="_blank" class="btn btn-outline">
                <x-icon name="printer" class="h-4 w-4" /> Print Waybill
            </a>
            @if(! $order->delivery?->picked_up_at)
                <button type="button" @click="$refs.pickupModal.showModal()" class="btn {{ $selectedCenter ? 'btn-outline' : 'btn-primary' }}">
                    <x-icon name="truck" class="h-4 w-4" /> {{ $selectedCenter ? 'Change Pickup Schedule' : 'Schedule Pickup' }}
                </button>
            @endif
            <form method="POST" action="{{ route('seller.orders.status', $order->id) }}">
                @csrf
                <input type="hidden" name="action" value="cancel">
                <button type="submit" class="btn btn-danger">Cancel Order</button>
            </form>
            <p class="w-full text-sm text-ink-light">
                @if($pickupReview?->status === 'rejected')
                    Pickup rejected by {{ $selectedCenter?->name ?? 'Logistics' }}: {{ $pickupReview->rejection_reason }}. Use Schedule Pickup to submit a revised request.
                @elseif($assignedRider)
                    Assigned pickup rider from <strong>{{ $selectedCenter?->name ?? 'Logistics' }}</strong>: <strong>{{ $assignedRider->name }}</strong> ({{ $assignedRider->phone ?? 'No phone' }}). The rider will collect the items from you.
                @elseif($pickupReview?->status === 'approved')
                    Pickup approved by <strong>{{ $selectedCenter?->name ?? 'Logistics' }}</strong>. Their team is assigning a rider to pick up the items from you.
                @elseif($selectedCenter)
                    Routed to <strong>{{ $selectedCenter->name }}</strong>. Waiting for logistics approval and rider assignment.
                @else
                    Click <strong>Schedule Pickup</strong> and select a Logistics Center so they can assign a rider to pick up the items from you.
                @endif
            </p>
        @endif
        @if($order->status === 'out_for_delivery')
            <a href="{{ route('seller.orders.waybill', $order->id) }}" target="_blank" class="btn btn-outline">
                <x-icon name="printer" class="h-4 w-4" /> Print Waybill
            </a>
            <span class="self-center text-sm text-ink-light">Delivery is tracked by {{ $selectedCenter?->name ?? 'logistics' }}. The assigned delivery rider confirms arrival with the buyer's OTP.</span>
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
            @php
                $calc = \App\Support\EscrowEarnings::calculateForSeller($order, auth()->id(), $order->delivery);
            @endphp
            <div class="space-y-1.5 border-t border-borderline px-6 py-4 text-sm">
                <div class="flex justify-between"><span class="text-ink-light">Subtotal (your items)</span><span class="font-semibold">{{ peso($calc['gross_merch']) }}</span></div>
                <div class="flex justify-between">
                    <span class="text-ink-light">Buyer Shipping Fee</span>
                    <span class="font-semibold">{{ peso($calc['buyer_shipping_base']) }}</span>
                </div>
                @if($calc['merch_discount'] > 0)
                    <div class="flex justify-between">
                        <span class="text-ink-light">Voucher discount absorbed by you</span>
                        <span class="font-semibold text-rose-600">− {{ peso($calc['merch_discount']) }}</span>
                    </div>
                @endif
                @if($calc['shipping_waived'] > 0)
                    <div class="flex justify-between">
                        <span class="text-ink-light">Free-shipping voucher absorbed by you</span>
                        <span class="font-semibold text-rose-600">− {{ peso($calc['shipping_waived']) }}</span>
                    </div>
                @endif
                <div class="flex justify-between">
                    <span class="text-ink-light">Last-Mile Delivery Rider</span>
                    <span class="font-semibold text-rose-600">− {{ peso($calc['last_mile_rider_fee']) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-ink-light">Origin Logistics Hub Fee</span>
                    <span class="font-semibold text-rose-600">− {{ peso($calc['origin_hub_fee']) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-ink-light">Pickup Rider Fee ({{ $calc['item_quantity'] }} item{{ $calc['item_quantity'] === 1 ? '' : 's' }} × ₱{{ number_format($calc['pickup_rider_rate'], 2) }})</span>
                    <span class="font-semibold text-rose-600">− {{ peso($calc['pickup_rider_fee']) }}</span>
                </div>
                @if($calc['is_inter_hub'])
                    <div class="flex justify-between">
                        <span class="text-ink-light">Inter-Hub Transport Driver (Shared)</span>
                        <span class="font-semibold text-rose-600">− {{ peso($calc['inter_hub_driver_fee']) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-ink-light">Destination Logistics Hub</span>
                        <span class="font-semibold text-rose-600">− {{ peso($calc['destination_hub_fee']) }}</span>
                    </div>
                @endif
                <div class="flex justify-between">
                    <span class="text-ink-light">Admin Platform Commission ({{ number_format($calc['commission_rate'], 2) }}%)</span>
                    <span class="font-semibold text-rose-600">− {{ peso($calc['admin_commission']) }}</span>
                </div>
                <div class="flex justify-between border-t border-borderline pt-2 text-base">
                    <span class="font-bold">Your Net Withdrawable Earnings</span>
                    <span class="font-extrabold text-emerald-700">{{ peso($calc['seller_earning']) }}</span>
                </div>
                <p class="mt-1 text-right text-[11px] text-ink-light">
                    Buyer paid {{ peso($calc['buyer_total_payable']) }} in total. Last-mile rider always deducts {{ peso($calc['last_mile_rider_fee']) }} from COD; all deductions are settled cleanly through Escrow.
                </p>
                @if($order->status === 'delivered' && $order->payment_status !== 'paid')
                    <p class="mt-1 rounded-lg bg-amber-50 px-2 py-1 text-right text-[11px] font-semibold text-amber-800">Awaiting your cash confirmation — not counted in earnings yet. Confirm it under Remittances.</p>
                @endif
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
                    {{ $shippingAddress->recipient_name ?? '' }}<br>
                    {{ $shippingAddress->address_line ?? '' }}<br>
                    {{ $shippingAddress->barangay ?? '' }}, {{ $shippingAddress->city ?? '' }}<br>
                    {{ $shippingAddress->province ?? '' }} {{ $shippingAddress->region ?? '' }} {{ $shippingAddress->postal_code ?? '' }}<br>
                    <span class="text-ink-light">{{ $shippingAddress->phone ?? '' }}</span>
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
                <div class="flex justify-between gap-3">
                    <span class="text-ink-light">Logistics Hub</span>
                    <span class="text-right font-semibold">{{ $selectedCenter?->name ?? $order->courierPickup?->courier ?? 'Not selected yet' }}</span>
                </div>
                <div class="flex justify-between"><span class="text-ink-light">Delivery</span><span class="font-semibold">{{ $order->delivery ? delivery_status_label($order->delivery->status) : 'Waiting for rider' }}</span></div>
                <div class="flex justify-between gap-3">
                    <span class="text-ink-light">Tracking number</span>
                    @if($order->delivery?->tracking_number)
                        <span class="break-all text-right font-mono font-bold">{{ $order->delivery->tracking_number }}</span>
                    @else
                        <span class="text-right text-xs font-semibold text-ink-light">Available once the parcel is booked</span>
                    @endif
                </div>

                @if($assignedRider)
                    <div class="mt-2 rounded-2xl border border-[#16697A]/20 bg-[#16697A]/5 p-4">
                        <div class="text-xs font-bold uppercase tracking-wider text-[#16697A]">Assigned Rider ({{ $selectedCenter?->name ?? 'Logistics' }})</div>
                        <div class="mt-1 font-bold text-ink">{{ $assignedRider->name }}</div>
                        @if($assignedRider->phone)
                            <div class="text-xs text-ink-light">Phone: {{ $assignedRider->phone }}</div>
                        @endif
                        <div class="mt-0.5 text-xs text-ink-light">
                            Vehicle: {{ ucfirst($assignedRider->vehicle_type ?? 'Motorcycle') }}{{ $assignedRider->license_plate ? ' · Plate ' . $assignedRider->license_plate : '' }}
                        </div>
                    </div>
                @elseif($selectedCenter)
                    <div class="mt-2 rounded-2xl bg-basebg p-3.5 text-xs text-ink-light">
                        <span class="font-bold text-ink">{{ $selectedCenter->name }}</span> has your pickup request and will assign a rider to pick up the items from you.
                    </div>
                @endif

                @if($order->courierPickup)
                    <div class="mt-2 rounded-2xl bg-basebg p-4">
                        <div class="text-xs font-bold uppercase tracking-wider text-ink-light">Pickup Schedule</div>
                        <div class="mt-1 font-semibold">{{ $selectedCenter?->name ?? $order->courierPickup->courier }}</div>
                        <div class="text-xs text-ink-light">{{ $order->courierPickup->pickup_at?->format('M j, Y · g:i A') }}</div>
                        @if($order->courierPickup->tracking_number)
                            <div class="mt-1 text-xs">Tracking: <span class="font-bold">{{ $order->courierPickup->tracking_number }}</span></div>
                        @endif
                        @if($order->courierPickup->notes)
                            <div class="mt-1 text-xs text-ink-light">Note: {{ $order->courierPickup->notes }}</div>
                        @endif
                        <span class="badge badge-{{ $order->courierPickup->status }} mt-2">{{ ucfirst($order->courierPickup->status) }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@if($order->delivery && $returnState && $returnState['state'] !== 'not_queued')
    <div class="card mt-6 p-6">
        <h3 class="mb-2 text-base font-bold">Returned Parcel</h3>
        <p class="text-sm text-ink-light">Tracking {{ $order->delivery->tracking_number }}</p>

        @if($returnState['state'] === 'received')
            <p class="mt-3 text-sm font-semibold text-green-700">You confirmed receipt on {{ $returnState['received_at']?->format('M j, Y g:i A') }}.</p>
        @elseif($returnState['state'] === 'dispatched')
            <div class="mt-3 rounded-2xl bg-[#FFF8E7] p-4 text-sm">
                This parcel was sent back to you on {{ $returnState['dispatched_at']?->format('M j, Y g:i A') }}. Confirm only after you physically receive it.
            </div>
            @if($returnState['can_confirm'])
                <form method="POST" action="{{ route('seller.returns.confirm', $order->delivery) }}" class="mt-4 space-y-3">
                    @csrf
                    <div>
                        <label class="field-label">Receipt note (optional)</label>
                        <textarea name="note" rows="2" maxlength="1000" class="textarea" placeholder="Condition, missing items, or handoff details"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Confirm Return Received</button>
                </form>
            @endif
        @else
            <p class="mt-3 text-sm text-ink-light">The parcel is back at the sorting center and is waiting to be sent to you.</p>
        @endif
    </div>
@endif

{{-- Schedule pickup modal --}}
<dialog x-ref="pickupModal" class="m-auto w-full max-w-md rounded-2xl border border-borderline bg-white p-0 shadow-2xl backdrop:bg-black/40">
    <form method="POST" action="{{ route('seller.orders.pickup', $order->id) }}" class="p-6">
        @csrf
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h3 class="text-lg font-extrabold">Schedule Logistics Pickup</h3>
                <p class="text-xs text-ink-light">Select the logistics hub that will assign a rider to pick up your items.</p>
            </div>
            <button type="button" @click="$refs.pickupModal.close()" class="rounded-xl p-1.5 text-ink-light hover:bg-soft">
                <x-icon name="x" class="h-5 w-5" />
            </button>
        </div>
        <div class="space-y-4">
            <div>
                <label class="field-label">Logistics Center (Courier Hub)</label>
                <select name="center_id" class="select" required>
                    <option value="">Select logistics center to pick up items...</option>
                    @foreach($logisticsCenters ?? [] as $center)
                        <option value="{{ $center->id }}"
                                @selected((int) old('center_id', $order->delivery?->center_id ?? $order->delivery?->pickupRequest?->center_id) === (int) $center->id)>
                            {{ $center->name }} ({{ $center->code }}{{ $center->city ? ' · ' . $center->city : '' }})
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-[11px] text-ink-light">Your pickup request will be routed directly to this logistics center so their staff can assign a rider.</p>
            </div>
            <div>
                <label class="field-label">Pickup Date &amp; Time</label>
                <input type="datetime-local"
                       name="pickup_at"
                       value="{{ old('pickup_at', $order->courierPickup?->pickup_at?->format('Y-m-d\TH:i') ?? now()->addHour()->format('Y-m-d\TH:i')) }}"
                       class="input"
                       required>
            </div>
            <div>
                <label class="field-label">Tracking Number (auto-generated if blank)</label>
                <input type="text"
                       name="tracking_number"
                       value="{{ old('tracking_number', $order->delivery?->tracking_number ?? $order->courierPickup?->tracking_number) }}"
                       @if($order->delivery?->tracking_number) readonly @endif
                       class="input {{ $order->delivery?->tracking_number ? 'bg-basebg font-mono' : '' }}"
                       placeholder="Auto-generated TRK number">
            </div>
            <div>
                <label class="field-label">Pickup Instructions / Notes (optional)</label>
                <textarea name="notes" rows="3" class="textarea" placeholder="Landmark, contact person, or packing notes for the pickup rider...">{{ old('notes', $order->courierPickup?->notes) }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary w-full">Schedule Logistics Pickup</button>
        </div>
    </form>
</dialog>
@endsection
