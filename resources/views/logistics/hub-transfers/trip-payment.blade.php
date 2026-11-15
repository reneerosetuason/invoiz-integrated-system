@if($canViewOriginPrivate && (int) $transfer->payment_version === 1)
<section class="card p-4 space-y-4" aria-label="Trip payment">
    <div>
        <h2 class="font-bold text-sm">Driver trip payment</h2>
        <p class="text-xs text-[#6E6E73]">One payment for this truck trip. {{ $transfer->payment_confirmed_at ? 'Reserved using the confirmed rates.' : 'Calculated automatically from hub addresses and seller product measurements. Review and confirm.' }}</p>
    </div>
    @if($tripPaymentWarning)
        <p role="alert" class="p-3 bg-amber-50 text-amber-900 text-sm">{{ $tripPaymentWarning }}</p>
    @endif
    @if($canManageOrigin && in_array($transfer->status, ['loading', 'ready_for_dispatch']) && !$transfer->payment_confirmed_at)
    <form method="POST" action="{{ route('hub-transfers.payment.prepare', $transfer) }}" class="space-y-3">
        @csrf
        @if($tripInputs ?? null)
        <div class="text-sm"><strong>Automatic road distance: {{ number_format($tripInputs['distance'], 3) }} km</strong>
            <p class="text-xs text-[#6E6E73]">{{ $tripInputs['source'] }}</p>
        </div>
        @endif
        <p class="text-xs text-[#6E6E73]">Road distance comes automatically from the hub addresses. Actual parcel weight comes from the recorded shipment or seller product quantities. Payment uses handling, road distance and actual weight.</p>
        <p class="text-xs text-[#6E6E73]">Map data: OpenStreetMap contributors. Address lookup: Photon. Road routing: OSRM.</p>
        @foreach($manifestParcels as $entry)
        <fieldset class="border rounded-lg p-3">
            <legend class="text-xs font-semibold">{{ $entry->delivery?->tracking_number }}</legend>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                @foreach(['weight' => 'Actual weight (kg)'] as $field => $label)
                <label class="text-xs">{{ $label }}
                    <input value="{{ $entry->delivery?->$field }}" placeholder="Missing seller data" readonly class="input w-full">
                </label>
                @endforeach
            </div>
        </fieldset>
        @endforeach
        <button class="btn btn-outline btn-sm">Recalculate automatically</button>
    </form>
    @endif
    @if($tripPreview)
        <dl class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
            @foreach([
                'Road distance' => number_format($tripPreview['distance'], 3).' km',
                'Parcels' => $tripPreview['parcel_count'],
                'Actual cargo weight' => number_format($tripPreview['weight'], 2).' kg',
                'Truck payload' => number_format($tripPreview['payload'], 2).' kg',
                'Weight utilization' => number_format($tripPreview['weightUtilization'], 2).'%',
                ...(($tripPreview['pricing_version'] ?? 1) >= 3 ? [
                    'Handling fees' => 'PHP '.number_format($tripPreview['base'] / 100, 2),
                    'Distance fees' => 'PHP '.number_format($tripPreview['distancePayment'] / 100, 2),
                    'Weight and distance fees' => 'PHP '.number_format($tripPreview['weightPayment'] / 100, 2),
                ] : [
                    'Base payment' => 'PHP '.number_format($tripPreview['base'] / 100, 2),
                    'Distance payment' => 'PHP '.number_format($tripPreview['distancePayment'] / 100, 2),
                    'Load bonus' => 'PHP '.number_format($tripPreview['bonus'] / 100, 2),
                    'Historical calculation before cap' => 'PHP '.number_format(($tripPreview['uncapped_total'] ?? $tripPreview['total']) / 100, 2),
                ]),
                'Total driver earnings' => 'PHP '.number_format($tripPreview['total'] / 100, 2),
            ] as $label => $value)
            <div><dt class="text-[#6E6E73]">{{ $label }}</dt><dd class="mt-1 font-bold">{{ $value }}</dd></div>
            @endforeach
        </dl>
        <p class="text-xs text-[#6E6E73]">Source: {{ $tripPreview['route_distance_source'] }}.
            @if(($tripPreview['pricing_version'] ?? 1) >= 3)
                Per parcel: PHP {{ number_format($tripPreview['rates']['inter_hub_handling_fee'], 2) }} handling + km &times; PHP {{ number_format($tripPreview['rates']['inter_hub_rate_per_km'], 2) }} + actual kg &times; km &times; PHP {{ number_format($tripPreview['rates']['inter_hub_rate_per_kg_km'], 2) }}.
            @else
                Confirmed historical rates: PHP {{ number_format($tripPreview['rates']['trip_rate_per_km'], 2) }}/km; load bonus {{ number_format($tripPreview['bonus'] / 100, 2) }}.
            @endif
        </p>
        <details class="text-xs">
            <summary class="cursor-pointer font-semibold">Shipment allocations</summary>
            <ul class="mt-2 space-y-1">
            @foreach($tripPreview['allocations'] as $deliveryId => $cents)
                <li>Parcel #{{ $deliveryId }}: transport allocation PHP {{ number_format($cents / 100, 2) }} (included once in the seller’s inter-hub deduction).</li>
            @endforeach
            </ul>
        </details>
        <p class="text-xs text-[#6E6E73]">
            @if(($tripPreview['pricing_version'] ?? 1) >= 3)
                Each parcel fee is rounded once to centavos. Driver earnings equal the sum of those fees, charged once to the corresponding shipments. Actual weight and road distance determine payment. Receipt alone does not release funds.
            @else
                This is a confirmed historical payment. Its recorded rates and allocations remain unchanged.
            @endif
        </p>
        @if($canManageOrigin && !$transfer->payment_confirmed_at && in_array($transfer->status, ['loading', 'ready_for_dispatch']))
        <form method="POST" action="{{ route('hub-transfers.payment.confirm', $transfer) }}">
            @csrf
            <input type="hidden" name="preview_hash" value="{{ hash('sha256', json_encode($tripPreview)) }}">
            <button class="btn btn-primary btn-sm">Confirm &amp; reserve PHP {{ number_format($tripPreview['total'] / 100, 2) }}</button>
        </form>
        @endif
    @endif
    @if($canManageOrigin && $transfer->payment_confirmed_at && in_array($transfer->status, ['loading', 'ready_for_dispatch']))
    <form method="POST" action="{{ route('hub-transfers.payment.reverse', $transfer) }}" class="flex flex-wrap gap-2">
        @csrf
        <input name="reason" required minlength="3" maxlength="1000" placeholder="Reason for payment reversal" aria-label="Reason for payment reversal" class="input flex-1">
        <button class="btn btn-outline btn-sm">Reverse reservation</button>
    </form>
    @endif
</section>
@endif
