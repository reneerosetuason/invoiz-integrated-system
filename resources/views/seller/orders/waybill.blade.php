<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Waybill · Order #{{ $order->id }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('assets/shared/css/app.css') }}">
    {{-- Identical stylesheet to the logistics parcel label. --}}
    <link rel="stylesheet" href="{{ asset('assets/logistics/css/deliveries/show.css') }}">
    <style>
        body { background: #F4F2EE; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body>
    <div class="mx-auto max-w-xl px-4 py-6">
        <div class="mb-4 flex items-center justify-between gap-3 no-print">
            <div>
                <h1 class="text-sm font-extrabold text-gray-900">Shipping Waybill</h1>
                <p class="text-xs text-gray-500">Same label logistics prints. Affix it to the parcel before the rider arrives.</p>
            </div>
            @if($delivery)
                <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 rounded-xl bg-[#16697A] px-4 py-2 text-xs font-semibold text-white hover:bg-[#0E4A57]">
                    Print / Save PDF
                </button>
            @endif
        </div>

        @if($delivery)
            @include('shared.waybill.label')
        @else
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                No shipment has been booked for this order yet. Schedule a pickup first — the waybill and tracking number are generated with the shipment.
            </div>
        @endif
    </div>

    @if($delivery)
        <script src="{{ asset('vendor/qrcodejs/qrcode.min.js') }}"></script>
        <script src="{{ asset('vendor/jsbarcode/JsBarcode.all.min.js') }}"></script>
        <script src="{{ asset('assets/logistics/js/deliveries/show.js') }}" data-config="{{ json_encode([$delivery->tracking_number], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) }}"></script>
    @endif
</body>
</html>
