@if($canViewOriginPrivate && (int) $transfer->payment_version === 1)
<section class="card p-4 space-y-4" aria-label="Trip payment">
    <div>
        <h2 class="font-bold text-sm">Driver trip payment</h2>
        <p class="text-xs text-[#6E6E73]">One payment for this truck trip. {{ $transfer->payment_confirmed_at ? 'Reserved using the confirmed rates.' : 'Record measurements and road distance, then review and confirm.' }}</p>
    </div>
    @if($tripPaymentWarning)
        <p role="alert" class="p-3 bg-amber-50 text-amber-900 text-sm">{{ $tripPaymentWarning }}</p>
    @endif
    @if($canManageOrigin && in_array($transfer->status, ['loading', 'ready_for_dispatch']) && !$transfer->payment_confirmed_at)
    <form method="POST" action="{{ route('hub-transfers.payment.prepare', $transfer) }}" class="space-y-3">
        @csrf
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="text-xs">Recorded road distance (km)
                <input name="route_distance_km" value="{{ old('route_distance_km', $transfer->route_distance_km) }}" type="number" step="0.001" min="0.001" max="10000" required class="input w-full">
            </label>
            <label class="text-xs">Road-distance source / route reference
                <input name="route_distance_source" value="{{ old('route_distance_source', $transfer->route_distance_source) }}" maxlength="255" required class="input w-full" placeholder="Approved route record or routing report">
            </label>
        </div>
        <p class="text-xs text-[#6E6E73]">Use actual road distance. Saving records your account as the source authorizer. Record usable cargo dimensions in Fleet Management. Measure the packed parcel; product dimensions alone may differ.</p>
        @foreach($manifestParcels as $entry)
        <fieldset class="border rounded-lg p-3">
            <legend class="text-xs font-semibold">{{ $entry->delivery?->tracking_number }}</legend>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                @foreach(['weight' => 'Actual weight (kg)', 'length_cm' => 'Length (cm)', 'width_cm' => 'Width (cm)', 'height_cm' => 'Height (cm)'] as $field => $label)
                <label class="text-xs">{{ $label }}
                    <input name="parcels[{{ $entry->delivery_id }}][{{ $field }}]" value="{{ old('parcels.'.$entry->delivery_id.'.'.$field, $entry->delivery?->$field) }}" type="number" step="0.01" min="0.01" max="{{ $field === 'weight' ? '999999.99' : '10000' }}" required class="input w-full">
                </label>
                @endforeach
            </div>
        </fieldset>
        @endforeach
        <button class="btn btn-outline btn-sm">Save measurements &amp; calculate</button>
    </form>
    @endif
    @if($tripPreview)
        <dl class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
            @foreach([
                'Road distance' => number_format($tripPreview['distance'], 3).' km',
                'Parcels' => $tripPreview['parcel_count'],
                'Actual cargo weight' => number_format($tripPreview['weight'], 2).' kg',
                'Cargo volume' => number_format($tripPreview['volume'], 4).' m³',
                'Truck payload' => number_format($tripPreview['payload'], 2).' kg',
                'Truck cargo volume' => number_format($tripPreview['cargoVolume'], 4).' m³',
                'Weight utilization' => number_format($tripPreview['weightUtilization'], 2).'%',
                'Volume utilization' => number_format($tripPreview['volumeUtilization'], 2).'%',
                'Final utilization' => number_format($tripPreview['utilization'], 2).'%',
                'Base payment' => 'PHP '.number_format($tripPreview['base'] / 100, 2),
                'Distance payment' => 'PHP '.number_format($tripPreview['distancePayment'] / 100, 2),
                'Load bonus' => 'PHP '.number_format($tripPreview['bonus'] / 100, 2),
                'Total driver earnings' => 'PHP '.number_format($tripPreview['total'] / 100, 2),
            ] as $label => $value)
            <div><dt class="text-[#6E6E73]">{{ $label }}</dt><dd class="mt-1 font-bold">{{ $value }}</dd></div>
            @endforeach
        </dl>
        <p class="text-xs text-[#6E6E73]">Source: {{ $tripPreview['route_distance_source'] }}. Bonus below 50%: PHP 0.00. Rate: PHP {{ number_format($tripPreview['rates']['trip_rate_per_km'], 2) }}/km.</p>
        <details class="text-xs">
            <summary class="cursor-pointer font-semibold">Shipment allocations</summary>
            <ul class="mt-2 space-y-1">
            @foreach($tripPreview['allocations'] as $deliveryId => $cents)
                <li>Parcel #{{ $deliveryId }}: PHP {{ number_format($cents / 100, 2) }}</li>
            @endforeach
            </ul>
        </details>
        <p class="text-xs text-[#6E6E73]">Allocation uses chargeable weight with divisor {{ $tripPreview['rates']['trip_volumetric_divisor'] }}. Seller deductions retain the existing shipment budget; the difference is recorded for platform reconciliation. Receipt alone does not release funds.</p>
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
