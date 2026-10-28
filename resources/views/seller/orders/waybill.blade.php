<!DOCTYPE html>
@php($shippingAddress = (object) ($order->shipping_address ?: ($order->address?->toShippingSnapshot() ?? [])))
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Waybill · Order #{{ $order->id }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('assets/shared/css/app.css') }}">
    <script src="{{ asset('vendor/qrcodejs/qrcode.min.js') }}"></script>
    <script src="{{ asset('vendor/jsbarcode/JsBarcode.all.min.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('assets/seller/css/orders/waybill.css') }}">
</head>
<body class="p-6">
    <div class="mx-auto max-w-2xl">
        <div class="mb-4 flex items-center justify-between no-print">
            <h1 class="text-lg font-extrabold">Shipping Waybill</h1>
            <button onclick="window.print()" class="btn btn-primary"><x-icon name="printer" class="h-4 w-4" /> Print / Save PDF</button>
        </div>

        <div id="print-area" class="waybill bg-white p-8">
            <div class="flex items-start justify-between border-b-2 border-dashed border-borderline pb-5">
                <div>
                    <div class="flex items-center gap-2">
                        <img src="{{ asset('assets/shared/images/logo.png') }}" alt="Invoiz" class="h-9 w-auto object-contain">
                        <div class="text-[10px] font-bold uppercase tracking-widest text-ink-light">Shipping Waybill</div>
                    </div>
                    <div class="mt-3">
                        <div class="text-xs font-bold uppercase tracking-wider text-ink-light">Shipped by</div>
                        <div class="text-sm font-extrabold">{{ auth()->user()->seller->business_name }}</div>
                        <div class="text-xs text-ink-light">{{ auth()->user()->full_name }} · {{ auth()->user()->phone }}</div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-ink-light">Order</div>
                    <div class="text-2xl font-extrabold">#{{ $order->id }}</div>
                    <div class="mt-1 text-xs text-ink-light">{{ $order->created_at->format('F j, Y') }}</div>
                    <span class="badge badge-{{ $order->status }} mt-1">{{ order_status_label($order->status) }}</span>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-6 border-b-2 border-dashed border-borderline py-5">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-wider text-ink-light">Deliver to</div>
                    <div class="mt-1 text-sm font-extrabold">{{ $shippingAddress->recipient_name ?? '' }}</div>
                    <div class="text-xs leading-relaxed text-ink-light">
                        {{ $shippingAddress->address_line ?? '' }},<br>
                        {{ $shippingAddress->barangay ?? '' }}, {{ $shippingAddress->city ?? '' }},<br>
                        {{ $shippingAddress->province ?? '' }} {{ $shippingAddress->region ?? '' }} {{ $shippingAddress->postal_code ?? '' }}
                    </div>
                    <div class="mt-1 text-xs font-semibold">{{ $shippingAddress->phone ?? '' }}</div>
                </div>
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-wider text-ink-light">Delivery</div>
                    <div class="mt-1 space-y-1 text-xs">
                        <div><span class="text-ink-light">Courier:</span> <span class="font-bold">{{ $order->courierPickup?->courier ?? '—' }}</span></div>
                        <div><span class="text-ink-light">Pickup:</span> {{ $order->courierPickup?->pickup_at?->format('M j, g:i A') ?? '—' }}</div>
                        <div><span class="text-ink-light">Tracking:</span> <span class="font-bold">{{ $order->delivery?->tracking_number ?? $order->courierPickup?->tracking_number ?? '—' }}</span></div>
                        <div><span class="text-ink-light">Payment:</span> Cash on Delivery</div>
                    </div>
                </div>
            </div>

            <table class="w-full border-b-2 border-dashed border-borderline py-3 text-sm">
                <thead>
                    <tr class="text-left text-[10px] font-bold uppercase tracking-wider text-ink-light">
                        <th class="py-2">Item</th>
                        <th class="py-2 text-center">Qty</th>
                        <th class="py-2 text-right">Price</th>
                        <th class="py-2 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->seller_items as $item)
                        <tr>
                            <td class="py-2">
                                <div class="font-semibold">{{ $item->product_name }}</div>
                                @if($item->variant_label)<div class="text-[11px] text-ink-light">{{ $item->variant_label }}</div>@endif
                            </td>
                            <td class="py-2 text-center font-semibold">{{ $item->quantity }}</td>
                            <td class="py-2 text-right">{{ peso($item->price) }}</td>
                            <td class="py-2 text-right font-semibold">{{ peso($item->subtotal) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="flex items-end justify-between gap-6 pt-4">
                <div>
                    @if($order->delivery?->tracking_number)
                        <svg id="seller-barcode"></svg>
                        <div class="mt-1 text-center font-mono text-sm font-bold tracking-widest">{{ $order->delivery->tracking_number }}</div>
                        <div class="mt-2 flex justify-center"><div id="seller-qr"></div></div>
                    @else
                        <div class="text-xs text-ink-light">Tracking pending — created when logistics books the parcel.</div>
                    @endif
                    <div class="mt-2 font-mono text-[11px] text-ink-light">{{ 'INV-'.$order->id }}</div>
                </div>
                <div class="text-right">
                    <div class="text-xs text-ink-light">Order total (COD)</div>
                    <div class="text-2xl font-extrabold">{{ peso($order->total_amount) }}</div>
                    <div class="text-[10px] uppercase tracking-wider text-ink-light">Thank you for shopping with Invoiz</div>
                </div>
            </div>
        </div>
    </div>
    @if($order->delivery?->tracking_number)
    <script src="{{ asset('assets/seller/js/orders/waybill.js') }}" data-config="{{ json_encode([($order->delivery->tracking_number)], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) }}"></script>
    @endif
</body>
</html>
