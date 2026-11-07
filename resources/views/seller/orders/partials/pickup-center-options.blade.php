<option value="">Select logistics center to pick up items...</option>
@foreach($logisticsCenters ?? [] as $center)
    @php($suggestion = $pickupRecommendations[$center->id] ?? [])
    <option value="{{ $center->id }}" @selected((int) $selectedCenterId === (int) $center->id)>
        {{ ($suggestion['recommended'] ?? false) ? 'Recommended — ' : '' }}{{ $center->name }} ({{ $center->code }}{{ $center->city ? ' · ' . $center->city : '' }})
        @if(isset($suggestion['distance_km']))
            — {{ ($suggestion['approximate'] ?? true) ? 'approx. ' : '' }}{{ number_format($suggestion['distance_km'], 1) }} km away
        @endif
    </option>
@endforeach
