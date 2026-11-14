<details class="text-xs min-w-0">
    <summary class="cursor-pointer font-semibold text-[#16697A]">{{ $next['label'] }}</summary>
    <div class="mt-2 space-y-2 rounded-lg border border-[#E8E6E0] bg-[#FAF9F6] p-3">
        <p><strong>Pickup:</strong> {{ $delivery->sender_name ?: 'Seller' }}<br>{{ $delivery->sender_address ?: 'Pickup address not recorded' }}</p>
        @if($delivery->sender_phone)<p>{{ $delivery->sender_phone }}</p>@endif
        @if($delivery->package_description)<p>{{ $delivery->package_description }}</p>@endif
        @if($next['key'] === 'review_pickup')
            <form method="POST" action="{{ route('pickup-requests.approve', $pickup) }}">
                @csrf
                <button type="submit" class="btn btn-primary btn-xs">Approve pickup</button>
            </form>
            <details>
                <summary class="cursor-pointer text-[#6E6E73]">Reject with reason</summary>
                <form method="POST" action="{{ route('pickup-requests.reject', $pickup) }}" class="mt-2 space-y-2">
                    @csrf
                    <label class="block">Reason
                        <textarea name="rejection_reason" required maxlength="2000" class="input w-full" rows="2"></textarea>
                    </label>
                    <button type="submit" class="btn btn-outline btn-xs">Reject pickup</button>
                </form>
            </details>
        @else
            <form method="POST" action="{{ route('deliveries.auto-assign', $delivery) }}">
                @csrf
                <button type="submit" class="btn btn-primary btn-xs">Auto-assign pickup rider</button>
            </form>
            @php
                $pickupRiders = $eligibleRiders->isEmpty() ? collect() : $eligibleRiders->filter(function ($rider) use ($delivery) {
                    $scope = $delivery->riderAssignmentScope();
                    return (int) $rider->center_id === (int) $scope['center_id']
                        && ($scope['service_area_id'] === null || (int) $rider->service_area_id === (int) $scope['service_area_id'])
                        && $delivery->weight !== null && (float) $delivery->weight > 0
                        && (float) $delivery->weight <= $rider->capacityLimit();
                });
            @endphp
            @if($pickupRiders->isNotEmpty())
                <form method="POST" action="{{ route('deliveries.assign-rider', $delivery) }}" class="space-y-2">
                    @csrf
                    <label class="block">Choose pickup rider
                        <select name="rider_id" required class="input w-full">
                            <option value="">Select rider</option>
                            @foreach($pickupRiders as $pickupRider)
                                <option value="{{ $pickupRider->id }}">{{ $pickupRider->name }} · {{ $pickupRider->serviceArea?->name ?: 'Hub area' }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" class="btn btn-outline btn-xs">Assign selected rider</button>
                </form>
            @else
                <p class="text-[#6E6E73]">No online rider currently matches this pickup's hub, service area, and recorded parcel weight.</p>
            @endif
        @endif
    </div>
</details>
