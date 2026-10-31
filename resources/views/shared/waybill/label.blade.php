{{-- Shared INVOIZ shipping waybill. Used by the logistics parcel page and the
     seller "Print Waybill" page so both always print the identical label.
     Expects: $delivery and the App\Support\Waybill::context() variables. --}}
@php
    $labelCod = (float) ($delivery->amount_to_collect ?? 0);
    $labelShipDate = $delivery->created_at?->format('M d, Y') ?? '—';

    // Unified waybill context (read-only presentation of real data; "—" when unavailable).
    $wbOrder = $waybillOrder ?? $delivery->order;
    $wbAddress = $waybillAddress ?? null;
    $wbPayment = $waybillPayment ?? null;
    $wbDiscount = (float) ($waybillDiscount ?? 0);
    $wbStores = $waybillStores ?? collect();

    // Sellers behind the order (distinct), primary seller first.
    $wbSellers = ($wbOrder ? $wbOrder->items->pluck('seller')->filter()->unique('id')->values() : collect());
    $wbSeller = $wbSellers->first();
    $wbStore = ($wbSeller ? $wbStores->get($wbSeller->id) : null);

    // SHIPPED BY: seller store/user first, delivery sender fields as fallback.
    $wbShipName = ($wbStore->business_name ?? null) ?: ($wbSeller->name ?? null) ?: ($delivery->sender_name ?? null) ?: '—';
    $wbShipPhone = ($wbSeller->phone ?? null) ?: ($delivery->sender_phone ?? null) ?: '—';
    $wbShipAddrParts = array_values(array_filter([
        $wbSeller->address_line ?? null,
        $wbSeller->barangay ?? null,
        $wbSeller->municipality ?? null,
        $wbSeller->province ?? null,
    ]));
    $wbShipAddress = $wbShipAddrParts !== [] ? implode(', ', $wbShipAddrParts) : (($delivery->sender_address ?? null) ?: '—');

    // DELIVER TO: authoritative order address first, delivery recipient fields as fallback.
    $wbBuyName = ($wbAddress->recipient_name ?? null) ?: ($delivery->recipient_name ?? null) ?: '—';
    $wbBuyPhone = ($wbAddress->phone ?? null) ?: ($delivery->recipient_phone ?? null) ?: '—';
    $wbBuyAddrParts = $wbAddress ? array_values(array_filter([
        $wbAddress->address_line ?? null,
        $wbAddress->barangay ?? null,
        $wbAddress->city ?? null,
        $wbAddress->province ?? null,
        $wbAddress->region ?? null,
        isset($wbAddress->postal_code) && $wbAddress->postal_code ? $wbAddress->postal_code : null,
    ])) : [];
    $wbBuyAddress = $wbBuyAddrParts !== []
        ? implode(', ', $wbBuyAddrParts)
        : (($delivery->recipient_address ?? null) ?: '—');

    // ORDER ITEMS: authoritative order items first, delivery items as fallback.
    $wbRows = collect();
    if ($wbOrder && $wbOrder->items->isNotEmpty()) {
        foreach ($wbOrder->items as $wbOrderItem) {
            $wbRows->push([
                'name' => ($wbOrderItem->product_name ?? null) ?: '—',
                'variant' => $wbOrderItem->variant_label,
                'qty' => (int) $wbOrderItem->quantity,
                'price' => (float) $wbOrderItem->price,
                'subtotal' => $wbOrderItem->subtotal !== null
                    ? (float) $wbOrderItem->subtotal
                    : ((float) $wbOrderItem->price * (int) $wbOrderItem->quantity),
            ]);
        }
    } else {
        foreach ($delivery->items as $wbDeliveryItem) {
            $wbRows->push([
                'name' => ($wbDeliveryItem->name ?? null) ?: '—',
                'variant' => $wbDeliveryItem->variant_label,
                'qty' => (int) $wbDeliveryItem->quantity,
                'price' => (float) ($wbDeliveryItem->price ?? 0),
                'subtotal' => (float) $wbDeliveryItem->subtotal,
            ]);
        }
    }
    $wbItemsSubtotal = (float) $wbRows->sum('subtotal');
    $wbFee = $delivery->delivery_fee !== null ? (float) $delivery->delivery_fee : null;
    $wbTotal = $wbOrder
        ? (float) $wbOrder->total_amount
        : (($wbRows->isNotEmpty() || $wbFee !== null) ? $wbItemsSubtotal + ($wbFee ?? 0) : null);

    // PAYMENT: authoritative payment record first, delivery fields as fallback.
    $wbMethodRaw = ($wbPayment->method ?? null) ?: ($delivery->payment_method ?? null);
    $wbMethodKey = is_string($wbMethodRaw) ? strtolower($wbMethodRaw) : null;
    $wbIsCod = $labelCod > 0 || in_array($wbMethodKey, ['cash_on_delivery', 'cod'], true);
    $wbMethodLabels = [
        'cash_on_delivery' => 'CASH ON DELIVERY',
        'cod' => 'CASH ON DELIVERY',
        'gcash' => 'GCASH',
        'bank_transfer' => 'BANK TRANSFER',
    ];
    $wbMethodLabel = ($wbMethodKey && isset($wbMethodLabels[$wbMethodKey]))
        ? $wbMethodLabels[$wbMethodKey]
        : ($wbMethodRaw ? strtoupper($wbMethodRaw) : ($wbIsCod ? 'CASH ON DELIVERY' : null));

    // DELIVERY / LOGISTICS facts.
    $wbPickup = $delivery->picked_up_at
        ? 'Completed · ' . $delivery->picked_up_at->format('M d, Y h:i A')
        : '—';
    $wbParcelRows = array_values(array_filter([
        $delivery->package_type ? ['Type', $delivery->package_type] : null,
        $delivery->package_description ? ['Description', $delivery->package_description] : null,
        $delivery->weight ? ['Weight', rtrim(rtrim((string) $delivery->weight, '0'), '.') . ' kg'] : null,
        $delivery->priority ? ['Priority', ucfirst($delivery->priority)] : null,
        ($delivery->delivery_notes ?? null) ? ['Instructions', $delivery->delivery_notes] : null,
        (($delivery->notes ?? null) && ($delivery->notes !== $delivery->delivery_notes)) ? ['Notes', $delivery->notes] : null,
        ($wbOrder && ($wbOrder->notes ?? null)) ? ['Order Notes', $wbOrder->notes] : null,
    ]));
@endphp
{{-- Unified INVOIZ shipping waybill: seller/order/customer data merged with
    logistics operations. Tracking number stays the single identifier
    for the waybill, barcode and QR. Uses only real data ("—" fallback). --}}
<div class="delivery-waybill-preview mb-6 flex justify-center">
    <div id="shipping-label" role="img" aria-label="Shipping label for parcel {{ $delivery->tracking_number }}"
         class="w-full bg-[#ffffff] text-[#111111]" style="max-width: 105mm;">
        <div class="waybill-card">
            {{-- Header --}}
            <div class="waybill-header px-3 pt-2.5 pb-2 flex items-center gap-2.5">
                <img src="{{ asset('assets/shared/images/logo.png') }}" alt="INVOIZ logo" class="h-12 w-auto flex-shrink-0">
                <p class="waybill-title text-lg font-extrabold tracking-tight leading-none whitespace-nowrap text-[#0E4A57]">SHIPPING WAYBILL</p>
            </div>

            {{-- Waybill / tracking number --}}
            <div class="waybill-tracking px-3 py-2 border-b-2 border-[#111111] text-center">
                <p class="waybill-tracking-label text-[10px] font-bold tracking-widest">WAYBILL / TRACKING</p>
                <p class="waybill-tracking-number font-mono font-extrabold text-xl leading-tight break-all">{{ $delivery->tracking_number }}</p>
                @if($wbOrder)
                    <p class="text-[10px] font-bold tracking-wide mt-1">ORDER #: {{ $wbOrder->id }} · {{ $wbOrder->created_at?->format('F d, Y') ?? '—' }}</p>
                @endif
            </div>

            {{-- Barcode (CODE128 of the tracking number, rendered by script below) --}}
            <div class="waybill-barcode px-3 py-2 border-b-2 border-[#111111] text-center bg-white">
                <svg id="parcel-barcode" class="w-full" role="img" aria-label="Barcode for {{ $delivery->tracking_number }}"></svg>
                <p class="font-mono text-[11px] font-bold tracking-widest mt-0.5">{{ $delivery->tracking_number }}</p>
            </div>

            {{-- Shipped by / Deliver to --}}
            <div class="waybill-parties grid grid-cols-2 border-b-2 border-[#111111]">
                <div class="waybill-party px-3 py-2">
                    <p class="waybill-section-title text-[10px] font-extrabold tracking-widest text-[#16697A]">SHIPPED BY / SELLER</p>
                    <p class="waybill-party-value text-xs font-bold mt-1 break-words">{{ $wbShipName }}</p>
                    <p class="waybill-party-value text-[11px] mt-0.5 break-words">{{ $wbShipPhone }}</p>
                    <p class="waybill-party-value text-[11px] mt-0.5 break-words">{{ $wbShipAddress }}</p>
                    @if($wbSellers->count() > 1)
                        <p class="waybill-party-value text-[10px] mt-0.5 break-words">+ {{ $wbSellers->count() - 1 }} more: {{ $wbSellers->skip(1)->pluck('name')->filter()->implode(', ') }}</p>
                    @endif
                </div>
                <div class="waybill-party px-3 py-2 bg-[#F8FAF9]">
                    <p class="waybill-section-title text-[10px] font-extrabold tracking-widest text-[#16697A]">DELIVER TO / BUYER</p>
                    <p class="waybill-party-value text-sm font-extrabold mt-1 break-words">{{ $wbBuyName }}</p>
                    <p class="waybill-party-value text-xs font-bold mt-0.5 break-words">{{ $wbBuyAddress }}</p>
                    <p class="waybill-party-value text-xs font-bold mt-0.5 break-words">{{ $wbBuyPhone }}</p>
                </div>
            </div>

            {{-- Delivery / logistics --}}
            <div class="waybill-logistics px-3 py-2 border-b-2 border-[#111111]">
                <p class="waybill-section-title text-[10px] font-extrabold tracking-widest text-[#16697A]">DELIVERY / LOGISTICS</p>
                <dl class="mt-1 space-y-0.5 text-[11px]">
                    <div class="waybill-detail-row"><dt class="font-bold shrink-0">Courier:</dt><dd class="break-words">{{ $delivery->rider ? $delivery->rider->name . ($delivery->rider->phone ? ' · ' . $delivery->rider->phone : '') : '—' }}</dd></div>
                    <div class="waybill-detail-row"><dt class="font-bold shrink-0">Pickup:</dt><dd class="break-words">{{ $wbPickup }}</dd></div>
                    <div class="waybill-detail-row"><dt class="font-bold shrink-0">Tracking:</dt><dd class="font-mono break-all">{{ $delivery->tracking_number }}</dd></div>
                    <div class="waybill-detail-row"><dt class="font-bold shrink-0">Origin:</dt><dd class="break-words">{{ ($delivery->originCenter ?? $delivery->logisticsCenter)?->name ?? '—' }}</dd></div>
                    <div class="waybill-detail-row"><dt class="font-bold shrink-0">Destination:</dt><dd class="break-words">{{ $delivery->destinationCenter->name ?? '—' }}</dd></div>
                    <div class="waybill-detail-row"><dt class="font-bold shrink-0">Service Area:</dt><dd class="break-words">{{ $delivery->serviceArea->name ?? '—' }}</dd></div>
                </dl>
            </div>

            {{-- Payment + parcel (left) beside QR verification (right) --}}
            <div class="waybill-payment-parcel grid grid-cols-2 border-b-2 border-[#111111]">
                <div class="waybill-payment-details border-r-2 border-[#111111]">
                    <div class="waybill-payment px-3 py-2 border-b-2 border-[#111111]">
                        <p class="waybill-section-title text-[10px] font-extrabold tracking-widest text-[#16697A]">PAYMENT</p>
                        @if($labelCod > 0)
                            <p class="waybill-payment-amount text-xl font-extrabold mt-1 text-[#0E4A57]">{{ '₱' . number_format($labelCod, 2) }}</p>
                            <p class="text-[10px] font-bold tracking-widest mt-0.5">{{ $wbMethodLabel ?? 'CASH ON DELIVERY' }} — COLLECT ON DELIVERY</p>
                        @elseif($wbMethodLabel)
                            <p class="text-sm font-extrabold mt-1 break-words">{{ $wbMethodLabel }}</p>
                            <p class="text-[10px] font-bold tracking-widest mt-0.5">NO AMOUNT TO COLLECT</p>
                        @else
                            <p class="text-sm font-extrabold mt-1">—</p>
                            <p class="text-[10px] font-bold tracking-widest mt-0.5">NO AMOUNT TO COLLECT</p>
                        @endif
                        @if($wbPayment && ($wbPayment->reference_number ?? null))
                            <p class="text-[10px] mt-0.5 break-words">REF: {{ $wbPayment->reference_number }}</p>
                        @endif
                    </div>
                    <div class="waybill-parcel-details px-3 py-2">
                        <p class="waybill-section-title text-[10px] font-extrabold tracking-widest text-[#16697A]">PARCEL INFORMATION</p>
                        @if($wbParcelRows !== [])
                            <dl class="mt-1 space-y-0.5 text-[11px]">
                                @foreach($wbParcelRows as $wbParcelRow)
                                    <div class="waybill-detail-row"><dt class="font-bold shrink-0">{{ $wbParcelRow[0] }}:</dt><dd class="break-words">{{ $wbParcelRow[1] }}</dd></div>
                                @endforeach
                            </dl>
                        @else
                            <p class="mt-1 text-[11px]"><span class="font-bold">Details:</span> —</p>
                        @endif
                    </div>
                </div>
                <div class="waybill-qr-panel px-3 py-2 flex flex-col items-center justify-center text-center">
                    <p class="waybill-section-title text-[10px] font-extrabold tracking-widest text-[#16697A]">QR VERIFICATION</p>
                    <div id="parcel-qr" class="bg-white p-1.5 mt-1"
                         role="img" aria-label="QR code for parcel {{ $delivery->tracking_number }}"></div>
                    <p class="text-[10px] font-extrabold tracking-widest mt-1">SCAN TO VERIFY PARCEL</p>
                </div>
            </div>

            {{-- Order items + summary --}}
            @if($wbRows->isNotEmpty())
                <div class="waybill-items px-3 py-2 border-b-2 border-[#111111]">
                    <p class="waybill-section-title text-[10px] font-extrabold tracking-widest text-[#16697A]">ORDER ITEMS</p>
                    <table class="w-full mt-1 text-[11px]">
                        <thead>
                            <tr class="text-left">
                                <th class="font-extrabold tracking-wide py-0.5 pr-1">ITEM</th>
                                <th class="font-extrabold tracking-wide py-0.5 px-1 text-center">QTY</th>
                                <th class="font-extrabold tracking-wide py-0.5 px-1 text-right">PRICE</th>
                                <th class="font-extrabold tracking-wide py-0.5 pl-1 text-right">SUBTOTAL</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($wbRows as $wbRow)
                                <tr class="border-t border-[#111111]/20">
                                    <td class="py-0.5 pr-1 font-bold break-words">{{ $wbRow['name'] }}@if($wbRow['variant'])<span class="block font-normal">{{ $wbRow['variant'] }}</span>@endif</td>
                                    <td class="py-0.5 px-1 text-center">{{ $wbRow['qty'] }}</td>
                                    <td class="py-0.5 px-1 text-right whitespace-nowrap">₱{{ number_format($wbRow['price'], 2) }}</td>
                                    <td class="py-0.5 pl-1 text-right font-bold whitespace-nowrap">₱{{ number_format($wbRow['subtotal'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <dl class="waybill-totals mt-1.5 space-y-0.5 text-[11px]">
                        <div class="flex justify-between gap-2"><dt>Subtotal:</dt><dd class="font-bold whitespace-nowrap">₱{{ number_format($wbItemsSubtotal, 2) }}</dd></div>
                        @if($wbFee !== null)
                            <div class="flex justify-between gap-2"><dt>Delivery Fee:</dt><dd class="font-bold whitespace-nowrap">₱{{ number_format($wbFee, 2) }}</dd></div>
                        @endif
                        @if($wbDiscount > 0)
                            <div class="flex justify-between gap-2"><dt>Discount:</dt><dd class="font-bold whitespace-nowrap">−₱{{ number_format($wbDiscount, 2) }}</dd></div>
                        @endif
                        @if($wbTotal !== null)
                            <div class="waybill-total-row flex justify-between gap-2 text-[13px]"><dt class="font-extrabold">TOTAL:</dt><dd class="font-extrabold whitespace-nowrap">₱{{ number_format($wbTotal, 2) }}</dd></div>
                        @endif
                    </dl>
                </div>
            @endif

            {{-- Footer --}}
            <div class="waybill-footer px-3 py-2">
                @if($delivery->destinationCenter)
                    <p class="text-[11px] font-extrabold tracking-wide break-words">DESTINATION: {{ strtoupper($delivery->destinationCenter->name) }}</p>
                @endif
                @if($delivery->originCenter ?? $delivery->logisticsCenter)
                    <p class="text-[10px] font-bold mt-0.5 break-words">ORIGIN: {{ strtoupper(($delivery->originCenter ?? $delivery->logisticsCenter)->name) }}</p>
                @endif
                <p class="text-[10px] mt-0.5">SHIP DATE: {{ $labelShipDate }} · INVOIZ LOGISTICS</p>
                <p class="text-[10px] mt-0.5 italic">Thank you for shopping with INVOIZ.</p>
            </div>
        </div>
    </div>
</div>
