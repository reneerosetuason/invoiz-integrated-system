<!DOCTYPE html>
@php($shippingAddress = (object) ($order->shipping_address ?: ($order->address?->toShippingSnapshot() ?? [])))
@php($trackingCode = $order->delivery?->tracking_number ?? $order->courierPickup?->tracking_number)
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
<body>
    <div class="waybill-screen-container">
        <div class="mb-3 flex items-center justify-between no-print">
            <h1 class="text-sm font-extrabold text-gray-900">Shipping Waybill Preview</h1>
            <button onclick="window.print()" class="btn btn-primary inline-flex items-center gap-1.5 text-xs py-1.5 px-3">
                <x-icon name="printer" class="h-3.5 w-3.5" /> Print / Save PDF
            </button>
        </div>

        <div class="waybill-wrapper">
            <div id="print-area" class="waybill">
                {{-- 1. Header: Logo, Title, Sender, and Order Info --}}
                <div class="border-b border-gray-300 pb-1.5 flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-1.5">
                            <img src="{{ asset('assets/shared/images/logo.png') }}" alt="Invoiz" class="h-5 w-auto object-contain">
                            <span class="text-[9.5px] font-extrabold uppercase tracking-widest text-[#0E4A57]">SHIPPING WAYBILL</span>
                        </div>
                        <div class="mt-1">
                            <div class="text-[7px] font-bold uppercase tracking-wider text-gray-500">Shipped by</div>
                            <div class="text-[9.5px] font-extrabold text-gray-900 leading-tight">{{ auth()->user()->seller->business_name }}</div>
                            <div class="text-[7.5px] text-gray-600 leading-tight">{{ auth()->user()->full_name }} · {{ auth()->user()->phone }}</div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-[7px] font-bold uppercase tracking-wider text-gray-500">Order</div>
                        <div class="text-xs font-extrabold font-mono text-gray-900 leading-none">#{{ $order->id }}</div>
                        <div class="mt-0.5 text-[7.5px] text-gray-600">{{ $order->created_at->format('M j, Y') }}</div>
                        <span class="inline-block mt-0.5 px-1 py-0.2 text-[7px] font-bold uppercase rounded badge badge-{{ $order->status }}">{{ order_status_label($order->status) }}</span>
                    </div>
                </div>

                {{-- 2. Barcode Section --}}
                <div class="border-b border-gray-300 py-1 text-center bg-white">
                    @if($trackingCode)
                        <svg id="seller-barcode" data-tracking="{{ $trackingCode }}" class="w-full mx-auto" style="max-height: 28px;"></svg>
                        <div class="font-mono text-[9.5px] font-extrabold tracking-widest text-gray-900 mt-0.5">{{ $trackingCode }}</div>
                    @else
                        <div class="text-[8px] text-gray-500 py-1">Tracking pending — created when logistics books the parcel.</div>
                    @endif
                </div>

                {{-- 3. Deliver to & Delivery Details (2 columns) --}}
                <div class="grid grid-cols-2 gap-2 border-b border-gray-300 py-1.5 text-left">
                    <div class="pr-1 border-r border-gray-200">
                        <div class="text-[7px] font-extrabold uppercase tracking-wider text-[#16697A]">Deliver to</div>
                        <div class="mt-0.5 text-[9.5px] font-extrabold text-gray-900 leading-tight">{{ $shippingAddress->recipient_name ?? '—' }}</div>
                        <div class="text-[7.5px] leading-tight text-gray-700 mt-0.5">
                            {{ $shippingAddress->address_line ?? '' }}@if(!empty($shippingAddress->address_line)),<br>@endif
                            {{ $shippingAddress->barangay ?? '' }}@if(!empty($shippingAddress->barangay)), @endif{{ $shippingAddress->city ?? '' }}@if(!empty($shippingAddress->city)),<br>@endif
                            {{ $shippingAddress->province ?? '' }} {{ $shippingAddress->region ?? '' }} {{ $shippingAddress->postal_code ?? '' }}
                        </div>
                        <div class="mt-0.5 text-[8px] font-bold text-gray-900">{{ $shippingAddress->phone ?? '' }}</div>
                    </div>
                    <div class="pl-1">
                        <div class="text-[7px] font-extrabold uppercase tracking-wider text-[#16697A]">Delivery</div>
                        <div class="mt-0.5 space-y-0.5 text-[7.5px] leading-tight">
                            <div><span class="text-gray-500">Courier:</span> <span class="font-bold text-gray-900">{{ $order->courierPickup?->courier ?? 'INVOIZ Express' }}</span></div>
                            <div><span class="text-gray-500">Pickup:</span> {{ $order->courierPickup?->pickup_at?->format('M j, g:i A') ?? '—' }}</div>
                            <div><span class="text-gray-500">Tracking:</span> <span class="font-mono font-bold">{{ $trackingCode ?? '—' }}</span></div>
                            <div><span class="text-gray-500">Payment:</span> <span class="font-bold text-teal-800">Cash on Delivery</span></div>
                        </div>
                    </div>
                </div>

                {{-- 4. Compact Package Items Table --}}
                <div class="flex-1 min-h-0 border-b border-gray-300 py-1 overflow-hidden">
                    <div class="text-[7px] font-extrabold uppercase tracking-wider text-[#16697A] mb-0.5">Package Items</div>
                    <table class="w-full text-left" style="font-size: 7.5px; line-height: 1.2;">
                        <thead>
                            <tr class="text-[6.5px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200">
                                <th class="py-0.5 pr-1">Item</th>
                                <th class="py-0.5 text-center px-1">Qty</th>
                                <th class="py-0.5 text-right px-1">Price</th>
                                <th class="py-0.5 text-right pl-1">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @php($itemLimit = 3)
                            @foreach($order->seller_items->take($itemLimit) as $item)
                                <tr>
                                    <td class="py-0.5 pr-1 font-medium text-gray-800 truncate" style="max-width: 44mm;">
                                        <div>{{ $item->product_name }}</div>
                                        @if($item->variant_label)<div class="text-[6.5px] text-gray-500">{{ $item->variant_label }}</div>@endif
                                    </td>
                                    <td class="py-0.5 text-center px-1 font-semibold">{{ $item->quantity }}</td>
                                    <td class="py-0.5 text-right px-1 whitespace-nowrap">{{ peso($item->price) }}</td>
                                    <td class="py-0.5 text-right pl-1 font-semibold whitespace-nowrap">{{ peso($item->subtotal) }}</td>
                                </tr>
                            @endforeach
                            @if($order->seller_items->count() > $itemLimit)
                                <tr>
                                    <td colspan="4" class="py-0.5 text-[6.5px] text-gray-500 italic">
                                        + {{ $order->seller_items->count() - $itemLimit }} more item(s) on order invoice
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>

                {{-- 5. Bottom Section: QR Code + Order Total --}}
                <div class="flex items-center justify-between gap-2 pt-1.5">
                    <div class="flex items-center gap-1.5">
                        @if($trackingCode)
                            <div id="seller-qr" class="bg-white p-0.5 border border-gray-300 rounded" style="width: 44px; height: 44px;"></div>
                            <div>
                                <div class="text-[6.5px] font-extrabold uppercase tracking-widest text-[#16697A]">QR Verification</div>
                                <div class="text-[6px] text-gray-500">Scan at Logistics Hub</div>
                                <div class="font-mono text-[7px] font-semibold text-gray-600 mt-0.5">{{ 'INV-'.$order->id }}</div>
                            </div>
                        @else
                            <div class="font-mono text-[7.5px] font-semibold text-gray-600">{{ 'INV-'.$order->id }}</div>
                        @endif
                    </div>
                    <div class="text-right">
                        <div class="text-[7px] font-bold uppercase tracking-wider text-gray-500">Order total (COD)</div>
                        <div class="text-sm font-extrabold text-[#0E4A57] leading-none my-0.5">{{ peso($order->total_amount) }}</div>
                        <div class="text-[6.5px] uppercase tracking-wider text-gray-400">Thank you for shopping with Invoiz</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($trackingCode)
    <script src="{{ asset('assets/seller/js/orders/waybill.js') }}" data-config="{{ json_encode([($trackingCode)], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) }}"></script>
    @endif
</body>
</html>
