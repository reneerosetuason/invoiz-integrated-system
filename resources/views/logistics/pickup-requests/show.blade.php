<x-app-layout>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('pickup-requests.index') }}"
               class="p-2 bg-white border border-gray-200 rounded-xl text-gray-500 hover:text-teal-dark hover:border-teal transition shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Pickup Request Review</h1>
                <p class="text-sm text-gray-500 mt-0.5">{{ $pickupRequest->delivery->tracking_number }}</p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            @if($pickupRequest->status === 'pending')
                <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200">Pending</span>
            @elseif($pickupRequest->status === 'approved')
                <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200">Approved</span>
            @elseif($pickupRequest->status === 'rejected')
                <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-red-50 text-red-700 ring-1 ring-inset ring-red-200">Rejected</span>
            @else
                <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-teal-light text-teal-dark ring-1 ring-inset ring-teal-200">Completed</span>
            @endif
            @if($pickupRequest->center)
                <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-700 ring-1 ring-inset ring-gray-200">{{ $pickupRequest->center->name }}</span>
            @else
                <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-gray-50 text-gray-400 ring-1 ring-inset ring-gray-200">Not yet received at a center</span>
            @endif
        </div>
    </div>

    {{-- Pickup request details --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
        <h3 class="text-base font-bold text-gray-900 mb-5">Request Details</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Requested</p>
                <p class="text-sm font-medium text-gray-900">{{ $pickupRequest->requested_at?->format('M d, Y h:i A') ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Reviewed By</p>
                <p class="text-sm font-medium text-gray-900">{{ $pickupRequest->reviewer?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Reviewed At</p>
                <p class="text-sm font-medium text-gray-900">{{ $pickupRequest->reviewed_at?->format('M d, Y h:i A') ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Delivery Status</p>
                <p class="text-sm font-medium text-gray-900">{{ ucfirst(str_replace('_', ' ', $pickupRequest->delivery->status)) }}</p>
            </div>
        </div>

        @if($pickupRequest->status === 'rejected' && $pickupRequest->rejection_reason)
            <div class="mt-6 bg-red-50 rounded-xl border border-red-100 p-4">
                <p class="text-xs font-medium text-red-600 uppercase tracking-wider mb-1">Rejection Reason</p>
                <p class="text-sm font-medium text-gray-900">{{ $pickupRequest->rejection_reason }}</p>
            </div>
        @endif
    </div>

    {{-- Delivery summary --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-base font-bold text-gray-900">Delivery</h3>
            <a href="{{ route('deliveries.show', $pickupRequest->delivery) }}" class="text-sm font-semibold text-teal hover:text-teal-dark">Open Delivery</a>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Tracking Number</p>
                <p class="text-sm font-medium text-gray-900">{{ $pickupRequest->delivery->tracking_number }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Destination Center</p>
                <p class="text-sm font-medium text-gray-900">{{ $pickupRequest->delivery->destinationCenter?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Service Area</p>
                <p class="text-sm font-medium text-gray-900">{{ $pickupRequest->delivery->serviceArea?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Parcel Status</p>
                <p class="text-sm font-medium text-gray-900">{{ ucfirst(str_replace('_', ' ', $pickupRequest->delivery->parcel_status ?? '—')) }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Sender</p>
                <p class="text-sm font-medium text-gray-900">{{ $pickupRequest->delivery->sender_name }}</p>
                <p class="text-xs text-gray-500 mt-0.5">{{ $pickupRequest->delivery->sender_phone }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Recipient</p>
                <p class="text-sm font-medium text-gray-900">{{ $pickupRequest->delivery->recipient_name }}</p>
                <p class="text-xs text-gray-500 mt-0.5">{{ $pickupRequest->delivery->recipient_phone }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Package</p>
                <p class="text-sm font-medium text-gray-900">{{ $pickupRequest->delivery->package_type ?? '—' }}</p>
                <p class="text-xs text-gray-500 mt-0.5">{{ $pickupRequest->delivery->weight ? $pickupRequest->delivery->weight.' kg' : '' }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Payment</p>
                <p class="text-sm font-medium text-gray-900">{{ ucfirst(str_replace('_', ' ', $pickupRequest->delivery->payment_method ?? '—')) }}</p>
                <p class="text-xs text-gray-500 mt-0.5">₱{{ number_format($pickupRequest->delivery->amount_to_collect ?? 0, 2) }} to collect</p>
            </div>
        </div>
        @if($pickupRequest->delivery->notes)
            <div class="mt-6 bg-gray-50 rounded-xl border border-gray-100 p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Delivery Notes</p>
                <p class="text-sm font-medium text-gray-900">{{ $pickupRequest->delivery->notes }}</p>
            </div>
        @endif
    </div>

    {{-- Review actions (pending + authorized only) --}}
    @if($pickupRequest->status === 'pending' && $canReview)
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            {{-- Approve --}}
            <div class="bg-white rounded-2xl shadow-sm border border-emerald-100 p-6">
                <h3 class="text-base font-bold text-gray-900 mb-1">Approve Pickup</h3>
                <p class="text-sm text-gray-500 mb-5">Approves the pickup request. Rider assignment is a separate step on the delivery page and is not performed here.</p>
                <form action="{{ route('pickup-requests.approve', $pickupRequest) }}" method="POST"
                      x-data x-on:submit.prevent="if (confirm('Approve this pickup request?')) $el.submit()">
                    @csrf
                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center gap-2 bg-teal hover:bg-teal-dark text-white font-semibold px-5 py-2.5 rounded-xl transition shadow-sm">
                            Approve Pickup Request
                        </button>
                    </div>
                </form>
            </div>

            {{-- Reject --}}
            <div class="bg-white rounded-2xl shadow-sm border border-red-100 p-6 self-start">
                <h3 class="text-base font-bold text-gray-900 mb-1">Reject Pickup</h3>
                <p class="text-sm text-gray-500 mb-5">The delivery itself is not modified when a pickup request is rejected.</p>
                <form action="{{ route('pickup-requests.reject', $pickupRequest) }}" method="POST"
                      x-data x-on:submit.prevent="if (confirm('Reject this pickup request?')) $el.submit()">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label for="rejection_reason" class="block text-sm font-medium text-gray-700 mb-1.5">Reason *</label>
                            <textarea name="rejection_reason" id="rejection_reason" rows="3" required
                                      class="w-full rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm @error('rejection_reason') border-red-500 @enderror"></textarea>
                            @error('rejection_reason')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="inline-flex items-center gap-2 bg-red-500 hover:bg-red-600 text-white font-semibold px-5 py-2.5 rounded-xl transition shadow-sm">
                                Reject Pickup Request
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
</x-app-layout>