<x-app-layout>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('pickup-requests.index') }}"
                   class="btn btn-outline btn-sm" aria-label="Back to list">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back
                </a>
                <div>
                    <h1 class="text-2xl font-bold text-[#1B1B1E]">Pickup Request Review</h1>
                    <p class="text-xs text-[#6E6E73] mt-0.5">Tracking {{ $pickupRequest->delivery->tracking_number }} · Seller: {{ $pickupRequest->delivery->sender_name }}</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-status-badge :status="$pickupRequest->status" />
                @if($pickupRequest->center)
                    <span class="badge badge-confirmed">{{ $pickupRequest->center->name }}</span>
                @elseif($pickupRequest->status === 'pending')
                    <span class="badge badge-pending">Needs routing</span>
                @else
                    <span class="badge bg-[#F4F3EF] text-[#6E6E73]">Not yet received at a center</span>
                @endif
            </div>
        </div>

        @if($pickupRequest->delivery->order_id && in_array($pickupRequest->delivery->order?->status, ['pending', 'confirmed', 'processing'], true))
            <p class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                Waiting for the seller to mark this order as packed or schedule pickup. Routing, approval, and rider assignment become available after preparation.
            </p>
        @endif

        {{-- Pickup request & seller schedule details --}}
        <div class="card p-6">
            <h3 class="text-base font-bold text-[#1B1B1E] mb-4">Request &amp; Seller Pickup Details</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
                <div>
                    <p class="text-xs font-semibold text-[#6E6E73] uppercase tracking-wider mb-1">Requested At</p>
                    <p class="text-sm font-semibold text-[#1B1B1E]">{{ $pickupRequest->requested_at?->format('M d, Y h:i A') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-[#6E6E73] uppercase tracking-wider mb-1">Seller Preferred Pickup</p>
                    <p class="text-sm font-semibold text-[#16697A]">
                        {{ isset($courierPickup) && $courierPickup?->pickup_at ? $courierPickup->pickup_at->format('M d, Y h:i A') : 'As soon as possible' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-[#6E6E73] uppercase tracking-wider mb-1">Assigned Pickup Rider</p>
                    @if($pickupRequest->delivery->rider)
                        <p class="text-sm font-bold text-[#16697A]">{{ $pickupRequest->delivery->rider->name }}</p>
                        <p class="text-xs text-[#6E6E73]">{{ $pickupRequest->delivery->rider->phone ?? '' }} · {{ ucfirst($pickupRequest->delivery->rider->vehicle_type ?? 'Motorcycle') }}</p>
                    @else
                        <p class="text-sm font-medium text-[#6E6E73]">Not assigned yet</p>
                    @endif
                </div>
                <div>
                    <p class="text-xs font-semibold text-[#6E6E73] uppercase tracking-wider mb-1">Delivery Status</p>
                    <p class="text-sm font-semibold text-[#1B1B1E]">{{ ucfirst(str_replace('_', ' ', $pickupRequest->delivery->status)) }}</p>
                    @if($pickupRequest->reviewer)
                        <p class="text-xs text-[#6E6E73]">Reviewed by {{ $pickupRequest->reviewer->name }}</p>
                    @endif
                </div>
            </div>

            @if(isset($courierPickup) && $courierPickup?->notes)
                <div class="mt-4 rounded-xl bg-[#FFF8E7] border border-amber-200 p-4">
                    <p class="text-xs font-bold text-amber-800 uppercase tracking-wider mb-1">Seller Pickup Instructions</p>
                    <p class="text-sm font-medium text-[#1B1B1E]">{{ $courierPickup->notes }}</p>
                </div>
            @endif

            @if($pickupRequest->status === 'rejected' && $pickupRequest->rejection_reason)
                <div class="mt-4 bg-red-50 rounded-xl border border-red-200 p-4">
                    <p class="text-xs font-bold text-red-700 uppercase tracking-wider mb-1">Rejection Reason</p>
                    <p class="text-sm font-medium text-[#1B1B1E]">{{ $pickupRequest->rejection_reason }}</p>
                </div>
            @endif
        </div>

        {{-- Delivery summary --}}
        <div class="card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-bold text-[#1B1B1E]">Parcel &amp; Seller Pickup Location</h3>
                <a href="{{ route('deliveries.show', $pickupRequest->delivery) }}" class="btn btn-soft btn-sm">Open Full Delivery</a>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
                <div>
                    <p class="text-xs font-semibold text-[#6E6E73] uppercase tracking-wider mb-1">Tracking Number</p>
                    <p class="text-sm font-mono font-bold text-[#1B1B1E]">{{ $pickupRequest->delivery->tracking_number }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-[#6E6E73] uppercase tracking-wider mb-1">Handling Center</p>
                    <p class="text-sm font-semibold text-[#1B1B1E]">{{ $pickupRequest->center?->name ?? $pickupRequest->delivery->logisticsCenter?->name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-[#6E6E73] uppercase tracking-wider mb-1">Destination Center</p>
                    <p class="text-sm font-semibold text-[#1B1B1E]">{{ $pickupRequest->delivery->destinationCenter?->name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-[#6E6E73] uppercase tracking-wider mb-1">Parcel Status</p>
                    <p class="text-sm font-semibold text-[#1B1B1E]">{{ ucfirst(str_replace('_', ' ', $pickupRequest->delivery->parcel_status ?? '—')) }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-[#6E6E73] uppercase tracking-wider mb-1">Seller (Pickup From)</p>
                    <p class="text-sm font-bold text-[#1B1B1E]">{{ $pickupRequest->delivery->sender_name }}</p>
                    <p class="text-xs text-[#6E6E73] mt-0.5">{{ $pickupRequest->delivery->sender_phone }}</p>
                    <p class="text-xs text-[#6E6E73] mt-0.5">{{ $pickupRequest->delivery->sender_address }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-[#6E6E73] uppercase tracking-wider mb-1">Buyer (Deliver To)</p>
                    <p class="text-sm font-bold text-[#1B1B1E]">{{ $pickupRequest->delivery->recipient_name }}</p>
                    <p class="text-xs text-[#6E6E73] mt-0.5">{{ $pickupRequest->delivery->recipient_phone }}</p>
                    <p class="text-xs text-[#6E6E73] mt-0.5">{{ $pickupRequest->delivery->recipient_address }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-[#6E6E73] uppercase tracking-wider mb-1">Package</p>
                    <p class="text-sm font-semibold text-[#1B1B1E]">{{ $pickupRequest->delivery->package_type ?? 'Parcel' }}</p>
                    <p class="text-xs text-[#6E6E73] mt-0.5">{{ $pickupRequest->delivery->weight ? $pickupRequest->delivery->weight.' kg' : '' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-[#6E6E73] uppercase tracking-wider mb-1">Payment</p>
                    <p class="text-sm font-semibold text-[#1B1B1E]">{{ ucfirst(str_replace('_', ' ', $pickupRequest->delivery->payment_method ?? '—')) }}</p>
                    <p class="text-xs text-[#6E6E73] mt-0.5">₱{{ number_format($pickupRequest->delivery->amount_to_collect ?? 0, 2) }} to collect</p>
                </div>
            </div>
            @if($pickupRequest->delivery->notes)
                <div class="mt-4 bg-[#F4F3EF] rounded-xl p-4">
                    <p class="text-xs font-semibold text-[#6E6E73] uppercase tracking-wider mb-1">Order / Delivery Notes</p>
                    <p class="text-sm font-medium text-[#1B1B1E]">{{ $pickupRequest->delivery->notes }}</p>
                </div>
            @endif
        </div>

        {{-- Routing (admin only, before review) --}}
        @if($canRoute)
            <div class="card p-6 border-amber-300">
                <h3 class="text-base font-bold text-[#1B1B1E] mb-1">Route to a Logistics Center</h3>
                <p class="text-xs text-[#6E6E73] mb-4">
                    This pickup has no handling center yet. Assign one before the pickup can be reviewed and a rider assigned.
                </p>
                <form action="{{ route('pickup-requests.route', $pickupRequest) }}" method="POST" class="flex flex-col sm:flex-row sm:items-end gap-3">
                    @csrf
                    <div class="flex-1">
                        <label for="center_id" class="field-label">Logistics Center *</label>
                        <select name="center_id" id="center_id" required class="select">
                            <option value="">Select a center</option>
                            @foreach($centers as $center)
                                <option value="{{ $center->id }}" @selected(old('center_id') == $center->id)>{{ $center->name }}</option>
                            @endforeach
                        </select>
                        @error('center_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary">Assign Center</button>
                </form>
            </div>
        @endif

        {{-- Review actions (pending + authorized only) --}}
        @if($pickupRequest->status === 'pending' && $canReview)
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Approve & optionally assign rider --}}
                <div class="card p-6">
                    <h3 class="text-base font-bold text-[#1B1B1E] mb-1">Approve Pickup &amp; Assign Rider</h3>
                    <p class="text-xs text-[#6E6E73] mb-4">
                        Approve the seller's pickup request for <strong>{{ $pickupRequest->center?->name ?? 'your logistics center' }}</strong>. You can also select an available rider below to immediately assign them to pick up the items from the seller.
                    </p>
                    <form action="{{ route('pickup-requests.approve', $pickupRequest) }}" method="POST" class="space-y-4">
                        @csrf
                        @if(auth()->user()?->canManageRiders() && isset($riderEligibility) && $riderEligibility->isNotEmpty())
                            <div>
                                <label for="approve_rider_id" class="field-label">Assign Rider to Pick Up from Seller (optional)</label>
                                <select name="rider_id" id="approve_rider_id" class="select">
                                    <option value="">Approve first (assign rider separately)</option>
                                    @foreach($riderEligibility as $entry)
                                        @php($r = $entry['rider'])
                                        <option value="{{ $r->id }}" @disabled(! $entry['eligible']) @selected($entry['recommended'])>
                                            {{ $r->name }} ({{ ucfirst($r->vehicle_type ?? 'Motorcycle') }} · {{ $entry['active_deliveries'] }}/{{ \App\Models\Rider::MAX_ACTIVE_DELIVERIES }} active)
                                            @if($entry['recommended']) — Recommended @endif
                                            @if(! $entry['eligible']) — {{ $entry['reason'] }} @endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('rider_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                        @endif
                        <div class="flex justify-end">
                            <button type="submit" class="btn btn-primary">
                                Approve Pickup Request
                            </button>
                        </div>
                    </form>
                </div>

                {{-- Reject --}}
                <div class="card p-6 self-start">
                    <h3 class="text-base font-bold text-[#1B1B1E] mb-1">Reject Pickup</h3>
                    <p class="text-xs text-[#6E6E73] mb-4">The seller will see your rejection reason and can reschedule the pickup request.</p>
                    <form action="{{ route('pickup-requests.reject', $pickupRequest) }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label for="rejection_reason" class="field-label">Reason *</label>
                            <textarea name="rejection_reason" id="rejection_reason" rows="3" required class="textarea"></textarea>
                            @error('rejection_reason')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="btn btn-danger">
                                Reject Pickup Request
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- Assign / Reassign Pickup Rider once Pickup Request is Approved --}}
        @if($pickupRequest->status === 'approved' && ! $pickupRequest->delivery->picked_up_at && auth()->user()?->canManageRiders())
            <div class="card p-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
                    <div>
                        <h3 class="text-base font-bold text-[#1B1B1E]">Assign Rider to Pick Up from Seller</h3>
                        <p class="text-xs text-[#6E6E73]">
                            Select an eligible rider from <strong>{{ $pickupRequest->center?->name ?? 'this center' }}</strong> to collect the items from <strong>{{ $pickupRequest->delivery->sender_name }}</strong>.
                        </p>
                    </div>
                    @if($pickupRequest->delivery->rider)
                        <span class="badge badge-confirmed">Currently Assigned: {{ $pickupRequest->delivery->rider->name }}</span>
                    @else
                        <span class="badge badge-pending">Waiting for Rider Assignment</span>
                    @endif
                </div>

                <form action="{{ route('deliveries.assign-rider', $pickupRequest->delivery) }}" method="POST" class="flex flex-col sm:flex-row sm:items-end gap-3">
                    @csrf
                    <div class="flex-1">
                        <label for="rider_id" class="field-label">Select Center Rider *</label>
                        <select name="rider_id" id="rider_id" required class="select">
                            <option value="">Choose an eligible rider...</option>
                            @foreach($riderEligibility ?? [] as $entry)
                                @php($r = $entry['rider'])
                                <option value="{{ $r->id }}"
                                        @disabled(! $entry['eligible'])
                                        @selected((int) ($pickupRequest->delivery->rider_id ?: ($entry['recommended'] ? $r->id : 0)) === (int) $r->id)>
                                    {{ $r->name }} ({{ ucfirst($r->vehicle_type ?? 'Motorcycle') }} · Plate {{ $r->license_plate ?? '—' }} · {{ $entry['active_deliveries'] }}/{{ \App\Models\Rider::MAX_ACTIVE_DELIVERIES }} active)
                                    @if($entry['recommended']) — Recommended @endif
                                    @if(! $entry['eligible']) — {{ $entry['reason'] }} @endif
                                </option>
                            @endforeach
                        </select>
                        @error('rider_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary">
                        {{ $pickupRequest->delivery->rider ? 'Update Assigned Rider' : 'Assign Pickup Rider' }}
                    </button>
                </form>
            </div>
        @endif
    </div>
</x-app-layout>
