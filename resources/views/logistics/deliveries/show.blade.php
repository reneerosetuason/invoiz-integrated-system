<x-app-layout>
    @include('logistics.partials.delivery-theme')
    <div class="logistics-deliveries delivery-page-show">
    @php
        $dotColors = [
            'waiting_for_rider'      => 'bg-amber-500',
            'assigned'               => 'bg-blue-500',
            'picked_up'              => 'bg-indigo-500',
            'out_for_delivery'       => 'bg-purple-500',
            'delivered'              => 'bg-emerald-500',
            'delivery_failed'        => 'bg-red-500',
            'lost'                   => 'bg-red-700',
            'damaged'                => 'bg-orange-500',
            'returned_to_seller'     => 'bg-slate-500',
            'return_dispatched_to_seller' => 'bg-blue-400',
            'return_received_by_seller'   => 'bg-emerald-400',
            'cancelled'              => 'bg-gray-400',
            'dispatched'             => 'bg-sky-500',
            'sorting_center_handoff' => 'bg-orange-500',
            'sorting_center_pickup'  => 'bg-cyan-500',
            'archived'               => 'bg-gray-300',
            'restored'               => 'bg-teal-500',
        ];
        $stepOrder = ['assigned', 'picked_up', 'out_for_delivery', 'delivered'];
        $stepLabels = ['assigned' => 'Assigned', 'picked_up' => 'Picked Up', 'out_for_delivery' => 'Out for Delivery', 'delivered' => 'Delivered'];
        $progressStatus = match ($delivery->status) {
            'accepted', 'going_to_pickup', 'arrived_at_shop' => 'assigned',
            'arrived_at_customer' => 'out_for_delivery',
            default => $delivery->status,
        };
        $currentIndex = array_search($progressStatus, $stepOrder);
        $isTerminalBad = in_array($delivery->status, ['delivery_failed', 'lost', 'damaged', 'returned_to_seller', 'cancelled']);
        $weight = (float) ($delivery->weight ?? 0);
        $capacities = \App\Models\LogisticsSetting::vehicleCapacities();
        $attemptPrefs = \App\Models\LogisticsSetting::forUser(auth()->id())->delivery ?? [];
        $maxAttempts = (int) ($attemptPrefs['max_attempts'] ?? 2);
        $failedAttempts = $delivery->statusLogs->where('status', 'delivery_failed')->count();
        $isCodDelivery = \App\Support\Cod::isCod($delivery);
        $canOverride = auth()->user()->canManageRiders() && auth()->user()->isLogisticsAdmin();
        $codExpected = $isCodDelivery ? \App\Support\Cod::expected($delivery) : null;
    @endphp

    <div class="delivery-head">
        <a href="{{ route('deliveries.index') }}"
           class="delivery-back" aria-label="Back to deliveries">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
        </a>
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-bold text-gray-900">Delivery Details</h1>
            <x-status-badge :status="$delivery->status" />
            @if($failedAttempts > 0)
                <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full {{ $failedAttempts >= $maxAttempts ? 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-200' : 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200' }}" title="Failed delivery attempts used out of the Maximum Delivery Attempts preference in Settings">
                    Attempts {{ $failedAttempts }} of {{ $maxAttempts }}
                </span>
            @endif
            @if($delivery->archived_at)
                <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-gray-800 text-white">Archived</span>
            @endif
        </div>
    </div>

    {{-- Failure / Cancellation banner --}}
    @if($isTerminalBad && ($delivery->failure_reason || $delivery->cancellation_reason))
        <div class="mb-6 flex items-start gap-3 rounded-2xl border p-5 {{ in_array($delivery->status, ['delivery_failed', 'lost', 'damaged'], true) ? 'bg-red-50 border-red-200' : 'bg-gray-50 border-gray-200' }}">
            <svg class="h-5 w-5 flex-shrink-0 {{ in_array($delivery->status, ['delivery_failed', 'lost', 'damaged'], true) ? 'text-red-600' : 'text-gray-500' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div>
                <p class="text-sm font-bold {{ in_array($delivery->status, ['delivery_failed', 'lost', 'damaged'], true) ? 'text-red-700' : 'text-gray-700' }}">
                    {{ $delivery->status === 'cancelled' ? 'Delivery Cancelled' : delivery_status_label($delivery->status) }}
                    @if($delivery->status === 'delivery_failed' && $delivery->failed_at)<span class="font-normal">· {{ $delivery->failed_at->format('M d, Y h:i A') }}</span>@endif
                    @if($delivery->status === 'cancelled' && $delivery->cancelled_at)<span class="font-normal">· {{ $delivery->cancelled_at->format('M d, Y h:i A') }}</span>@endif
                </p>
                <p class="text-sm {{ $delivery->status === 'delivery_failed' ? 'text-red-600' : 'text-gray-600' }} mt-0.5">
                    <span class="font-semibold">Reason:</span> {{ $delivery->failure_reason ?? $delivery->cancellation_reason }}
                </p>
            </div>
        </div>
    @endif

    <div class="delivery-panel delivery-detail-summary">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
            <div>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Tracking Number</p>
                <p class="delivery-tracking">{{ $delivery->tracking_number }}</p>
                <p class="text-sm text-gray-500 mt-2">
                    Created {{ $delivery->created_at->format('M d, Y h:i A') }}
                    @if($delivery->creator) · by {{ $delivery->creator->name }}@endif
                </p>
            </div>
        </div>

        {{-- Delivery Progress (delivery status: rider-to-customer lifecycle) --}}
        <div class="delivery-progress mt-8 pt-6 border-t border-gray-100">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Delivery Progress</p>
            <p class="mt-1 mb-5 text-xs text-gray-400">Follow the rider’s progress from assignment to delivery. Hub processing is shown below.</p>
            <div class="relative">
                @if(!$isTerminalBad)
                    <div class="absolute top-5 left-0 right-0 h-1 bg-gray-200 rounded-full"></div>
                    <div class="absolute top-5 left-0 h-1 bg-emerald-500 rounded-full transition-all duration-700" style="width: {{ ($currentIndex !== false ? $currentIndex : 0) / (count($stepOrder) - 1) * 100 }}%"></div>
                @endif
                <div class="delivery-progress-steps relative flex justify-between">
                    <div class="flex flex-col items-center flex-1 min-w-0 text-center">
                        <div class="h-10 w-10 rounded-full flex items-center justify-center bg-green-500 text-white">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                        </div>
                        <p class="mt-3 text-xs font-medium text-green-600">Created</p>
                    </div>
                    @foreach($stepOrder as $i => $stepKey)
                        @php
                            $done = $currentIndex !== false && $i < $currentIndex;
                            $current = $currentIndex === $i;
                            $future = !$done && !$current;
                        @endphp
<div class="flex flex-col items-center flex-1 min-w-0 text-center">
                            <div class="relative flex items-center justify-center">
                                @if($current && !$isTerminalBad)
                                    <span class="absolute h-3 w-3 rounded-full bg-teal animate-ping"></span>
                                @endif
                                <div class="relative h-10 w-10 rounded-full flex items-center justify-center {{ $done ? 'bg-green-500 text-white' : ($current ? 'bg-teal text-white shadow-lg shadow-teal/30' : 'bg-gray-200 text-gray-400') }}">
                                    @if($done)
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    @elseif($current)
                                        <span class="h-3 w-3 rounded-full bg-white"></span>
                                    @else
                                        <span class="h-2.5 w-2.5 rounded-full bg-gray-400"></span>
                                    @endif
                                </div>
                            </div>
                            <p class="mt-3 text-xs font-medium {{ $current ? 'text-teal-dark' : ($done ? 'text-green-600' : 'text-gray-400') }}">{{ $stepLabels[$stepKey] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            @if($isTerminalBad)
                <div class="mt-4 flex justify-center">
                    <span class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold {{ in_array($delivery->status, ['delivery_failed', 'lost', 'damaged'], true) ? 'bg-red-100 text-red-700 ring-1 ring-red-200' : 'bg-gray-100 text-gray-600 ring-1 ring-gray-200' }}">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $delivery->status === 'delivery_failed' ? 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z' : 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636'}}"/></svg>
                        Final Status: {{ ucfirst(str_replace('_', ' ', $delivery->status)) }} — not completed
                    </span>
                </div>
            @endif
        </div>
    </div>

    {{-- Info grid --}}
    <div class="delivery-info-grid grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div class="bg-gray-50 rounded-xl p-5">
            <div class="flex items-center gap-2 mb-4">
                <div class="h-2 w-1 rounded-full bg-teal"></div>
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Sender Information</h3>
            </div>
            <dl class="space-y-3">
                <div><dt class="text-xs text-gray-500">Name</dt><dd class="text-sm font-medium text-gray-900">{{ $delivery->sender_name ?: 'Not recorded' }}</dd></div>
                <div><dt class="text-xs text-gray-500">Phone</dt><dd class="text-sm font-medium text-gray-900">{{ $delivery->sender_phone ?: 'Not recorded' }}</dd></div>
                <div><dt class="text-xs text-gray-500">Pickup Address</dt><dd class="text-sm font-medium text-gray-900">{{ $delivery->sender_address ?: 'Not recorded' }}</dd></div>
            </dl>
        </div>

        <div class="bg-gray-50 rounded-xl p-5">
            <div class="flex items-center gap-2 mb-4">
                <div class="h-2 w-1 rounded-full bg-teal"></div>
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Recipient Information</h3>
            </div>
            <dl class="space-y-3">
                <div><dt class="text-xs text-gray-500">Name</dt><dd class="text-sm font-medium text-gray-900">{{ $delivery->recipient_name ?: 'Not recorded' }}</dd></div>
                <div><dt class="text-xs text-gray-500">Phone</dt><dd class="text-sm font-medium text-gray-900">{{ $delivery->recipient_phone ?: 'Not recorded' }}</dd></div>
                <div><dt class="text-xs text-gray-500">Delivery Address</dt><dd class="text-sm font-medium text-gray-900">{{ $delivery->recipient_address ?: 'Not recorded' }}</dd></div>
            </dl>
        </div>

        <div class="bg-gray-50 rounded-xl p-5">
            <div class="flex items-center gap-2 mb-4">
                <div class="h-2 w-1 rounded-full bg-teal"></div>
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Package Details</h3>
            </div>
            <dl class="space-y-3">
                <div><dt class="text-xs text-gray-500">Package Type</dt><dd class="text-sm font-medium text-gray-900">{{ $delivery->package_type ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">Description</dt><dd class="text-sm font-medium text-gray-900">{{ $delivery->package_description ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">Weight</dt><dd class="text-sm font-medium text-gray-900">{{ $delivery->weight ? $delivery->weight . ' kg' : '—' }}</dd></div>
                <div>
                    <dt class="text-xs text-gray-500">Priority</dt>
                    <dd class="mt-0.5">
                        @php $prio = $delivery->priority ?? 'normal'; @endphp
                        <span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-full {{ match($prio) { 'urgent' => 'bg-red-100 text-red-700', 'high' => 'bg-amber-100 text-amber-700', default => 'bg-gray-100 text-gray-600' } }}">{{ ucfirst($prio) }}</span>
                    </dd>
                </div>
                <div><dt class="text-xs text-gray-500">Notes</dt><dd class="text-sm font-medium text-gray-900">{{ $delivery->notes ?? '—' }}</dd></div>
            </dl>
        </div>

        <div class="bg-gray-50 rounded-xl p-5">
            <div class="flex items-center gap-2 mb-4">
                <div class="h-2 w-1 rounded-full bg-teal"></div>
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Rider &amp; Contact</h3>
            </div>
            @if($delivery->rider)
                <dl class="space-y-3">
                    <div><dt class="text-xs text-gray-500">Name</dt><dd class="text-sm font-medium text-gray-900">{{ $delivery->rider->name }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Contact Number</dt><dd class="text-sm font-medium text-gray-900">{{ $delivery->rider->phone }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Vehicle</dt><dd class="text-sm font-medium text-gray-900">{{ ucfirst($delivery->rider->vehicle_type) }} · {{ $delivery->rider->license_plate }}</dd></div>
                </dl>
            @else
                <p class="text-sm text-gray-500">No rider assigned yet.</p>
            @endif
        </div>

        <div class="bg-gray-50 rounded-xl p-5">
            <div class="flex items-center gap-2 mb-4">
                <div class="h-2 w-1 rounded-full bg-teal"></div>
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Pickup Request</h3>
            </div>
            @if($delivery->pickupRequest)
                <dl class="space-y-3">
                    <div><dt class="text-xs text-gray-500">Status</dt><dd class="text-sm font-medium text-gray-900">{{ ucwords(str_replace('_', ' ', $delivery->pickupRequest->status)) }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Requested At</dt><dd class="text-sm font-medium text-gray-900">{{ $delivery->pickupRequest->requested_at?->format('M d, Y h:i A') ?? '—' }}</dd></div>
                    @if($delivery->pickupRequest->reviewed_at)
                        <div><dt class="text-xs text-gray-500">Reviewed At</dt><dd class="text-sm font-medium text-gray-900">{{ $delivery->pickupRequest->reviewed_at->format('M d, Y h:i A') }}{{ $delivery->pickupRequest->reviewer ? ' by ' . $delivery->pickupRequest->reviewer->name : '' }}</dd></div>
                    @endif
                    @if($delivery->pickupRequest->status === 'rejected' && $delivery->pickupRequest->rejection_reason)
                        <div><dt class="text-xs text-gray-500">Rejection Reason</dt><dd class="text-sm font-medium text-gray-900">{{ $delivery->pickupRequest->rejection_reason }}</dd></div>
                    @endif
                </dl>
            @else
                <p class="text-sm text-gray-500">No pickup request for this delivery.</p>
            @endif
        </div>

        <div class="bg-gray-50 rounded-xl p-5">
            <div class="flex items-center gap-2 mb-4">
                <div class="h-2 w-1 rounded-full bg-teal"></div>
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Payment &amp; Transaction</h3>
            </div>
            <dl class="space-y-3">
                <div><dt class="text-xs text-gray-500">Payment Method</dt><dd class="text-sm font-medium text-gray-900">{{ $delivery->payment_method ? strtoupper($delivery->payment_method) : '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">Cash on Delivery</dt><dd class="text-sm font-medium text-gray-900">{{ $delivery->amount_to_collect !== null && (float) $delivery->amount_to_collect > 0 ? '₱' . number_format((float) $delivery->amount_to_collect, 2) : '—' }}</dd></div>
                @if($delivery->cod_amount_received !== null)
                    <div><dt class="text-xs text-gray-500">Cash Received</dt><dd class="text-sm font-medium text-gray-900">₱{{ number_format((float) $delivery->cod_amount_received, 2) }}</dd></div>
                @endif
                @if($delivery->transaction)
                    <div><dt class="text-xs text-gray-500">Transaction Amount</dt><dd class="text-sm font-medium text-gray-900">₱{{ number_format((float) $delivery->transaction->amount, 2) }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Rider Fee</dt><dd class="text-sm font-medium text-gray-900">₱{{ number_format((float) $delivery->transaction->rider_fee, 2) }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Admin Commission</dt><dd class="text-sm font-medium text-gray-900">₱{{ number_format((float) $delivery->transaction->admin_commission, 2) }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Transaction Status</dt><dd class="text-sm font-medium text-gray-900">{{ ucfirst($delivery->transaction->status) }}</dd></div>
                @else
                    <div><dt class="text-xs text-gray-500">Transaction</dt><dd class="text-sm text-gray-500">No transaction recorded yet.</dd></div>
                @endif
            </dl>
        </div>

        <div class="bg-gray-50 rounded-xl p-5 md:col-span-2">
            <div class="flex items-center gap-2 mb-4">
                <div class="h-2 w-1 rounded-full bg-teal"></div>
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Timestamps</h3>
            </div>
            <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <div><dt class="text-xs text-gray-500">Created At</dt><dd class="text-sm font-medium text-gray-900">{{ $delivery->created_at->format('M d, Y h:i A') }}</dd></div>
                <div><dt class="text-xs text-gray-500">Estimated Delivery</dt><dd class="text-sm font-medium text-gray-900">{{ $delivery->estimated_delivery_at ? $delivery->estimated_delivery_at->format('M d, Y h:i A') : '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">Actual Pickup</dt><dd class="text-sm font-medium text-gray-900">{{ $delivery->picked_up_at ? $delivery->picked_up_at->format('M d, Y h:i A') : '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">Actual Delivery</dt><dd class="text-sm font-medium text-gray-900">{{ $delivery->delivered_at ? $delivery->delivered_at->format('M d, Y h:i A') : '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">Created By</dt><dd class="text-sm font-medium text-gray-900">{{ $delivery->creator?->name ?? '—' }}</dd></div>
            </dl>
        </div>
    </div>

    {{-- Parcel Processing (parcel status: sorting-center pipeline, independent of delivery status) --}}
    <div class="delivery-panel">
        <div class="delivery-processing-head flex items-center gap-2">
            <div class="h-2 w-1 rounded-full bg-teal"></div>
            <h2 class="text-lg font-semibold text-gray-900">Parcel Processing</h2>
            <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold rounded-full bg-teal-light text-teal-dark">
                {{ ucwords(str_replace('_', ' ', $delivery->parcel_status ?? 'pending_arrival')) }}
            </span>
        </div>
        <p class="mt-1 mb-5 text-xs text-gray-400">Follow the parcel through your Logistics Hub: receive, scan, sort, then dispatch. Rider assignment is managed separately.</p>

        @php
            $parcelSteps = [
                'pending_arrival' => ['label' => 'Pending Arrival', 'index' => 0],
                'received'        => ['label' => 'Received', 'index' => 1],
                'scanned'         => ['label' => 'Scanned', 'index' => 2],
                'sorted'          => ['label' => 'Sorted', 'index' => 3],
                'dispatched'      => ['label' => 'Dispatched', 'index' => 4],
            ];
            $currentParcelIndex = $parcelSteps[$delivery->parcel_status ?? 'pending_arrival']['index'];
        @endphp

        <div class="delivery-parcel-progress relative mb-6">
            <div class="absolute top-5 left-8 right-8 h-1 bg-gray-200 rounded-full"></div>
            <div class="absolute top-5 left-8 h-1 bg-teal rounded-full transition-all duration-700" style="width: {{ ($currentParcelIndex / (count($parcelSteps) - 1)) * 100 }}%"></div>
            <div class="delivery-progress-steps relative flex justify-between">
                @foreach($parcelSteps as $key => $step)
                    @php
                        $done = $currentParcelIndex > $step['index'];
                        $current = $currentParcelIndex === $step['index'];
                    @endphp
                    <div class="flex flex-col items-center flex-1 min-w-0 text-center">
                        <div class="relative flex items-center justify-center">
                            <div class="h-10 w-10 rounded-full flex items-center justify-center {{ $done ? 'bg-teal text-white' : ($current ? 'bg-teal text-white shadow-lg shadow-teal/30' : 'bg-gray-200 text-gray-400') }}">
                                @if($done)
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                @else
                                    <span class="h-3 w-3 rounded-full {{ $current ? 'bg-white' : 'bg-gray-400' }}"></span>
                                @endif
                            </div>
                        </div>
                        <p class="mt-3 text-xs font-medium {{ $current ? 'text-teal-dark' : ($done ? 'text-teal' : 'text-gray-400') }}">{{ $step['label'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
        @if(!$delivery->archived_at)
            <div class="mb-6 no-print">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Parcel Label</p>
                        <p class="text-xs text-gray-500 mt-1">Preview of the physical shipping label. Print it and affix it to the parcel so it can be scanned at the Logistics Hub.</p>
                    </div>
                    <button type="button" onclick="window.print()"
                            class="inline-flex items-center gap-2 self-start bg-white border border-gray-300 hover:bg-gray-100 text-gray-700 font-semibold px-4 py-2 rounded-xl transition text-sm">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        Print Shipping Label
                    </button>
                </div>
            </div>
            @include('shared.waybill.label')
        @endif

        @if(!$delivery->archived_at)
            @if($delivery->order_id && ! in_array($delivery->status, ['delivered', 'cancelled'], true))
                <p class="delivery-actions-note">Seller preparation &rarr; approved pickup &rarr; rider collection &rarr; center processing &rarr; delivery rider &rarr; buyer OTP and COD. Rider milestones are confirmed in the rider app.</p>
            @else
                <p class="delivery-actions-note">Available actions follow this delivery’s current status. Review the details before updating a record.</p>
            @endif

            @if($hasActiveHubTransfer)
                <p class="mb-3 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-800">This parcel remains in fleet-transfer custody. Destination-hub processing is available after the transfer is finalized.</p>
            @endif

            @if($delivery->order_id && ($delivery->isInterHubRoute() || $delivery->originCenter))
                @php
                    $originHub = $delivery->originCenter ?? $delivery->logisticsCenter;
                    $destHub = $delivery->destinationCenter;
                    $isInterHub = $delivery->isInterHubRoute();
                    $currentCenter = $delivery->logisticsCenter;
                    $driver = $delivery->interHubRider;

                    // Stage calculations
                    $stage1Done = $delivery->scanned_at || in_array($delivery->parcel_status, ['sorted', 'dispatched']) || ($delivery->center_id && $delivery->destination_center_id && (int)$delivery->center_id === (int)$delivery->destination_center_id);
                    $stage1Active = !$stage1Done;

                    $stage2Done = ($delivery->center_id && $delivery->destination_center_id && (int)$delivery->center_id === (int)$delivery->destination_center_id && $delivery->parcel_status !== 'dispatched');
                    $stage2Active = $stage1Done && !$stage2Done && ($delivery->inter_hub_dispatched_at || ($delivery->parcel_status === 'dispatched' && (int)$delivery->center_id !== (int)$delivery->destination_center_id));

                    $stage3Done = in_array($delivery->status, ['out_for_delivery', 'delivered']);
                    $stage3Active = $stage2Done && !$stage3Done;

                    $stage4Done = $delivery->status === 'delivered';
                    $stage4Active = $delivery->status === 'out_for_delivery';
                @endphp

                <div class="w-full rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-50/70 via-white to-sky-50/70 p-5 shadow-sm space-y-4 mb-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-indigo-100 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-1 rounded-lg text-xs font-bold uppercase tracking-wider {{ $isInterHub ? 'bg-indigo-600 text-white' : 'bg-emerald-600 text-white' }}">
                                {{ $isInterHub ? 'Inter-Hub Transit Route' : 'Local Single-Hub Route' }}
                            </span>
                            <span class="text-sm font-semibold text-gray-900">
                                {{ $originHub?->name ?? 'Origin Hub' }} &rarr; {{ $destHub?->name ?? 'Destination Hub' }}
                            </span>
                        </div>
                        <div class="text-xs text-gray-500">
                            Currently at: <strong class="text-indigo-900">{{ $currentCenter?->name ?? 'In Transit' }}</strong>
                        </div>
                    </div>

                    {{-- 4-Stage Stepper --}}
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 pt-1">
                        {{-- Step 1 --}}
                        <div class="relative rounded-xl p-3 border {{ $stage1Done ? 'bg-emerald-50/80 border-emerald-200' : ($stage1Active ? 'bg-amber-50 border-amber-300 ring-2 ring-amber-400/40' : 'bg-white border-gray-200') }}">
                            <div class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold {{ $stage1Done ? 'bg-emerald-600 text-white' : ($stage1Active ? 'bg-amber-500 text-white' : 'bg-gray-200 text-gray-500') }}">
                                    {!! $stage1Done ? '✓' : '1' !!}
                                </span>
                                <span class="text-xs font-bold text-gray-900">1. Origin Hub</span>
                            </div>
                            <p class="text-[11px] text-gray-600 mt-1 font-medium">{{ $originHub?->name ?? 'Origin' }}</p>
                            <p class="text-[10px] text-gray-500 mt-0.5">
                                {{ $stage1Done ? 'Intake & sorted' : 'Intake & scanning' }}
                            </p>
                        </div>

                        {{-- Step 2 --}}
                        <div class="relative rounded-xl p-3 border {{ $stage2Done ? 'bg-emerald-50/80 border-emerald-200' : ($stage2Active ? 'bg-indigo-50 border-indigo-300 ring-2 ring-indigo-400/40' : 'bg-white border-gray-200') }}">
                            <div class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold {{ $stage2Done ? 'bg-emerald-600 text-white' : ($stage2Active ? 'bg-indigo-600 text-white animate-pulse' : 'bg-gray-200 text-gray-500') }}">
                                    {!! $stage2Done ? '✓' : '2' !!}
                                </span>
                                <span class="text-xs font-bold text-gray-900">2. Hub Transfer</span>
                            </div>
                            <p class="text-[11px] text-gray-600 mt-1 font-medium">
                                Driver: {{ $driver?->name ?? 'Shared Hub Van' }}
                            </p>
                            <p class="text-[10px] text-gray-500 mt-0.5">
                                {{ $stage2Done ? 'Linehaul complete' : ($stage2Active ? 'In transit to Dest Hub' : 'Awaiting dispatch') }}
                            </p>
                        </div>

                        {{-- Step 3 --}}
                        <div class="relative rounded-xl p-3 border {{ $stage3Done ? 'bg-emerald-50/80 border-emerald-200' : ($stage3Active ? 'bg-sky-50 border-sky-300 ring-2 ring-sky-400/40' : 'bg-white border-gray-200') }}">
                            <div class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold {{ $stage3Done ? 'bg-emerald-600 text-white' : ($stage3Active ? 'bg-sky-600 text-white' : 'bg-gray-200 text-gray-500') }}">
                                    {!! $stage3Done ? '✓' : '3' !!}
                                </span>
                                <span class="text-xs font-bold text-gray-900">3. Destination Hub</span>
                            </div>
                            <p class="text-[11px] text-gray-600 mt-1 font-medium">{{ $destHub?->name ?? 'Destination' }}</p>
                            <p class="text-[10px] text-gray-500 mt-0.5">
                                {{ $stage3Done ? 'Received & dispatched' : 'Awaiting transfer arrival' }}
                            </p>
                        </div>

                        {{-- Step 4 --}}
                        <div class="relative rounded-xl p-3 border {{ $stage4Done ? 'bg-emerald-50/80 border-emerald-200' : ($stage4Active ? 'bg-teal-50 border-teal-300 ring-2 ring-teal-400/40' : 'bg-white border-gray-200') }}">
                            <div class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold {{ $stage4Done ? 'bg-emerald-600 text-white' : ($stage4Active ? 'bg-teal-600 text-white animate-pulse' : 'bg-gray-200 text-gray-500') }}">
                                    {!! $stage4Done ? '✓' : '4' !!}
                                </span>
                                <span class="text-xs font-bold text-gray-900">4. Out for Delivery</span>
                            </div>
                            <p class="text-[11px] text-gray-600 mt-1 font-medium">
                                Rider: {{ $delivery->rider?->name ?? 'Assigned Rider' }}
                            </p>
                            <p class="text-[10px] text-gray-500 mt-0.5">
                                {{ $stage4Done ? 'Delivered successfully' : ($stage4Active ? 'With delivery rider' : 'Pending final leg') }}
                            </p>
                        </div>
                    </div>

                    {{-- Why inter-hub note --}}
                    @if($isInterHub)
                        <div class="text-xs text-indigo-700 bg-white/80 rounded-xl p-3 border border-indigo-100 flex items-start gap-2">
                            <svg class="w-4 h-4 text-indigo-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                            <div>
                                <strong>Why is this an Inter-Hub Route?</strong> The seller is located in <strong>{{ $originHub?->city ?? 'Origin area' }}</strong> (handled by {{ $originHub?->name }}), while the buyer's shipping address is in <strong>{{ $destHub?->city ?? 'Destination area' }}</strong> (handled by {{ $destHub?->name }}). Parcels transfer between these two logistics centers before being dispatched to the last-mile rider.
                            </div>
                        </div>
                    @endif
                </div>
            @endif
            {{-- Parcel Hub Processing Status & Workflow Handoff --}}
            <div class="card p-4 bg-[#FAF9F6] border-[#E8E6E0] space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-[#6E6E73]">Hub Processing Status</span>
                            <span class="badge {{ $delivery->parcel_status === 'dispatched' ? 'badge-delivered' : 'badge-inactive' }}">
                                {{ ucwords(str_replace('_', ' ', $delivery->parcel_status ?? 'pending_arrival')) }}
                            </span>
                        </div>
                        <p class="text-xs text-[#6E6E73] mt-1">
                            @if(in_array($delivery->parcel_status, ['pending_arrival', null]))
                                Awaiting physical arrival and receipt verification at the logistics hub.
                            @elseif($delivery->parcel_status === 'received')
                                Parcel received at hub. Awaiting physical QR/barcode verification in Sorting Operations.
                            @elseif($delivery->parcel_status === 'scanned')
                                Parcel is verified. Ready for destination hub and service area sorting in Sorting Operations.
                            @elseif($delivery->parcel_status === 'sorted')
                                @if($delivery->destination_center_id && (int) $delivery->destination_center_id !== (int) $delivery->center_id)
                                    Parcel sorted for inter-hub transfer to {{ $delivery->destinationCenter?->name ?? 'destination hub' }}. Proceed through fleet Hub Transfers.
                                @else
                                    Parcel sorted at destination hub. Ready for last-mile delivery assignment below.
                                @endif
                            @elseif($delivery->parcel_status === 'dispatched')
                                Parcel dispatched from hub sorting. Last-mile Delivery Rider assignment and delivery progress are managed below.
                            @endif
                        </p>
                    </div>

                    {{-- Contextual Action to Sorting Center / Hub Transfers --}}
                    <div class="flex items-center gap-2 shrink-0">
                        @if(in_array($delivery->parcel_status, ['pending_arrival', null]))
                            <a href="{{ route('sorting-center.index', ['tab' => 'incoming', 'search' => $delivery->tracking_number]) }}"
                               class="btn btn-outline btn-sm">
                                Process in Sorting Center &rarr;
                            </a>
                        @elseif($delivery->parcel_status === 'received')
                            <a href="{{ route('sorting-center.index', ['tab' => 'received', 'search' => $delivery->tracking_number]) }}"
                               class="btn btn-outline btn-sm">
                                Verify in Sorting Center &rarr;
                            </a>
                        @elseif($delivery->parcel_status === 'scanned')
                            <a href="{{ route('sorting-center.index', ['tab' => 'scanned', 'search' => $delivery->tracking_number]) }}"
                               class="btn btn-outline btn-sm">
                                Sort in Sorting Center &rarr;
                            </a>
                        @elseif($delivery->parcel_status === 'sorted' && $delivery->destination_center_id && (int) $delivery->destination_center_id !== (int) $delivery->center_id)
                            <a href="{{ route('hub-transfers.index', ['search' => $delivery->tracking_number]) }}"
                               class="btn btn-outline btn-sm">
                                View in Hub Transfers &rarr;
                            </a>
                        @endif
                    </div>
                </div>

                @if($delivery->sorting_center_handoff_at || $delivery->sorting_center_pickup_at || (in_array($delivery->parcel_status, ['received', 'scanned', 'sorted', 'dispatched']) && $delivery->logisticsCenter))
                    <div class="pt-3 border-t border-[#E8E6E0] flex flex-wrap items-center gap-x-5 gap-y-1 text-xs text-[#6E6E73]">
                        @if($delivery->logisticsCenter)<span><strong class="text-[#1B1B1E]">Handling Hub:</strong> {{ $delivery->logisticsCenter->name }}</span>@endif
                        @if($delivery->sorting_center_handoff_at)<span><strong class="text-[#1B1B1E]">Handed to Hub:</strong> {{ $delivery->sorting_center_handoff_at->format('M d, Y h:i A') }}@if($delivery->sortingCenterHandoffRider) by {{ $delivery->sortingCenterHandoffRider->name }}@endif</span>@endif
                        @if($delivery->received_at)<span><strong class="text-[#1B1B1E]">Received:</strong> {{ $delivery->received_at->format('M d, Y h:i A') }}</span>@endif
                        @if($delivery->scanned_at)<span><strong class="text-[#1B1B1E]">Scanned:</strong> {{ $delivery->scanned_at->format('M d, Y h:i A') }}</span>@endif
                        @if($delivery->destinationCenter)<span><strong class="text-[#1B1B1E]">Destination:</strong> {{ $delivery->destinationCenter->name }}</span>@endif
                        @if($delivery->serviceArea)<span><strong class="text-[#1B1B1E]">Service Area:</strong> {{ $delivery->serviceArea->name }}</span>@endif
                        @if($delivery->sorted_at)<span><strong class="text-[#1B1B1E]">Sorted:</strong> {{ $delivery->sorted_at->format('M d, Y h:i A') }}</span>@endif
                        @if($delivery->sorting_center_pickup_at)<span><strong class="text-[#1B1B1E]">Picked from Hub:</strong> {{ $delivery->sorting_center_pickup_at->format('M d, Y h:i A') }}@if($delivery->sortingCenterPickupRider) by {{ $delivery->sortingCenterPickupRider->name }}@endif</span>@endif
                        @if($delivery->dispatched_at)<span><strong class="text-[#1B1B1E]">Dispatched:</strong> {{ $delivery->dispatched_at->format('M d, Y h:i A') }}</span>@endif
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- Proof of Delivery --}}
    @if($delivery->proofs->count())
        <div class="bg-white rounded-2xl shadow-sm border border-emerald-100 p-6 mb-6">
            <div class="flex items-center gap-2 mb-5">
                <div class="h-2 w-1 rounded-full bg-emerald-500"></div>
                <h2 class="text-lg font-semibold text-gray-900">Proof of Delivery</h2>
                <span class="inline-flex items-center px-2 py-0.5 text-[11px] font-bold rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200">Confirmed</span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($delivery->proofs as $proof)
                    <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                        <p class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-1">{{ strtoupper($proof->type) }} confirmation</p>
                        @if($proof->type === 'signature' && $proof->signature_name)
                            <p class="text-sm font-semibold text-gray-900">Signed by: {{ $proof->signature_name }}</p>
                        @elseif($proof->type === 'otp' && $proof->otp)
                            <p class="text-sm font-mono font-semibold text-gray-900">OTP verified</p>
                        @elseif($proof->file_path && file_exists(public_path('storage/' . $proof->file_path)))
                            <img src="{{ asset('storage/' . $proof->file_path) }}" alt="Proof" class="mt-1 rounded-lg max-h-40 object-cover w-full">
                        @elseif($proof->file_path)
                            <p class="text-sm text-gray-700">{{ $proof->file_path }}</p>
                        @endif
                        @if($proof->rider)
                            <p class="text-xs text-gray-500 mt-2">Uploaded by {{ $proof->rider->name }}</p>
                        @endif
                        <p class="text-xs text-gray-400 mt-1">{{ $proof->created_at->format('M d, Y h:i A') }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @elseif($delivery->status === 'delivered')
        <div class="mb-6 flex items-start gap-3 bg-amber-50 border border-amber-200 rounded-2xl p-5">
            <svg class="h-5 w-5 flex-shrink-0 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div>
                <p class="text-sm font-bold text-amber-700">No proof of delivery on record</p>
                <p class="text-sm text-amber-600 mt-0.5">This delivery was marked delivered without a recorded confirmation.</p>
            </div>
        </div>
    @endif

    {{-- Timeline --}}
    @if($delivery->statusLogs->count())
        <div class="delivery-panel">
            <div class="flex items-center gap-2 mb-6">
                <div class="h-2 w-1 rounded-full bg-teal"></div>
                <h2 class="text-lg font-semibold text-gray-900">Status Timeline</h2>
            </div>
            <div class="relative">
                <div class="absolute left-2 top-1 bottom-1 w-0.5 bg-gray-200"></div>
                <div class="space-y-4">
                    @php
                        // History event kinds: parcel-pipeline and record events are
                        // past facts, not the current delivery status. They get a
                        // small kind tag so the timeline never reads as a second
                        // status indicator. Delivery-lifecycle entries get no tag.
                        $parcelEventStatuses = ['pending_arrival', 'received', 'scanned', 'sorted', 'dispatched'];
                        $centerEventStatuses = ['sorting_center_handoff', 'sorting_center_pickup'];
                        $recordEventStatuses = ['archived', 'restored'];
                    @endphp
                    @foreach($delivery->statusLogs->sortBy('created_at') as $log)
                        <div class="relative pl-10">
                            <div class="absolute left-1.5 top-1.5 h-3 w-3 rounded-full border-2 border-white {{ $dotColors[$log->status] ?? 'bg-gray-400' }} shadow"></div>
                            <div class="bg-gray-50 rounded-xl p-4">
                                <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
                                    <span class="text-sm font-semibold text-gray-900">
                                        {{ \Illuminate\Support\Str::title(str_replace('_', ' ', $log->status)) }}
                                        @if(in_array($log->status, ['delivery_failed', 'lost', 'returned_to_seller', 'cancelled']))<span class="ml-1 text-xs font-normal text-red-500">(final)</span>@endif
                                        @if(in_array($log->status, $parcelEventStatuses, true))<span class="ml-1.5 inline-flex items-center px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide rounded bg-teal-light text-teal-dark">Parcel</span>@endif
                                        @if(in_array($log->status, $centerEventStatuses, true))<span class="ml-1.5 inline-flex items-center px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide rounded bg-amber-100 text-amber-700">Hub Sorting</span>@endif
                                        @if(in_array($log->status, $recordEventStatuses, true))<span class="ml-1.5 inline-flex items-center px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide rounded bg-gray-200 text-gray-500">Record</span>@endif
                                    </span>
                                    <span class="text-xs text-gray-500">{{ $log->created_at->format('M d, Y · h:i A') }}</span>
                                </div>
                                @if($log->notes)
                                    <p class="text-sm text-gray-600">{{ $log->notes }}</p>
                                @endif
                                @if($log->changer)
                                    <p class="text-xs text-gray-400 mt-1">Changed by: {{ $log->changer->name }}</p>
                                @endif
                                @if($log->location)
                                    <p class="text-xs text-gray-500 mt-1">📍 {{ $log->location }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- Actions --}}
    <div class="delivery-panel delivery-actions"
         x-data="{
             failOpen: false,
             cancelOpen: false,
             archiveOpen: false,
             overrideOpen: false,
             failReason: '',
             cancelReason: '',
             otherFail: '',
             otherCancel: ''
         }">

        <div class="flex items-center gap-2 mb-5">
            <div class="h-2 w-1 rounded-full bg-teal"></div>
            <h2 class="text-lg font-semibold text-gray-900">Delivery actions</h2>
        </div>

        @if(!$delivery->archived_at)
            <div class="delivery-processing-actions flex flex-wrap gap-3">
                @if(! $hasActiveHubTransfer && Auth::user()->canManageRiders() && $delivery->status === 'waiting_for_rider')
                    <form action="{{ route('deliveries.assign-rider', $delivery) }}" method="POST" class="w-full max-w-2xl">
                        @csrf
                        <div class="flex flex-col sm:flex-row sm:items-end gap-2">
                            <div class="flex-1 min-w-0">
                                <label for="rider_id" class="block text-sm font-medium text-gray-700 mb-1">Assign Rider</label>
                                <select name="rider_id" id="rider_id" required
                                        class="w-full rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">
                                    <option value="">— Select a rider —</option>
                                    @if($riderEligibility->where('eligible', true)->isNotEmpty())
                                        <optgroup label="✓ Available — Online">
                                            @foreach($riderEligibility->where('eligible', true) as $item)
                                                <option value="{{ $item['rider']->id }}">
                                                    @if($item['recommended']) Recommended — @endif✓ Online — {{ $item['rider']->name }} — {{ ucfirst($item['rider']->vehicle_type) }} — up to {{ $item['capacity'] }} kg ({{ $item['active_deliveries'] }} active)@if($item['distance_km'] !== null) - {{ number_format($item['distance_km'], 2) }} km {{ $item['distance_basis'] === 'pickup' ? 'to pickup' : 'to destination' }}@else - distance unavailable @endif
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                    @if($riderEligibility->where('eligible', false)->isNotEmpty())
                                        <optgroup label="✕ Unavailable">
                                            @foreach($riderEligibility->where('eligible', false) as $item)
                                                <option value="" disabled>
                                                    @if(!$item['is_online'])
                                                        ○ Offline — {{ $item['rider']->name }} — {{ ucfirst($item['rider']->vehicle_type) }} — {{ $item['reason'] }}
                                                    @else
                                                        ✕ {{ $item['rider']->name }} — {{ ucfirst($item['rider']->vehicle_type) }} — {{ $item['reason'] }}
                                                    @endif
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                </select>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button type="submit" class="inline-flex items-center justify-center bg-teal hover:bg-teal-dark text-white font-semibold py-2.5 px-5 rounded-xl transition shadow-sm text-sm whitespace-nowrap">
                                    Assign Rider
                                </button>
                                <button type="button" @click="cancelOpen = true"
                                        class="inline-flex items-center gap-2 bg-white border border-gray-200 hover:border-red-300 hover:text-red-600 text-gray-600 font-semibold px-4 py-2.5 rounded-xl transition text-sm whitespace-nowrap">
                                    Cancel Delivery
                                </button>
                            </div>
                        </div>
                        @if($riderEligibility->where('eligible', true)->isEmpty())
                            <p class="mt-2 text-xs text-amber-600">No eligible riders right now. Riders must be approved, active, online, have a verified vehicle, no active delivery, sufficient capacity, and belong to the delivery's destination Logistics Hub and service area.</p>
                        @endif
                        @if($errors->has('rider_id'))
                            <p class="text-red-500 text-xs mt-1">{{ $errors->first('rider_id') }}</p>
                        @endif
                    </form>

                    @if($riderEligibility->where('eligible', true)->isNotEmpty())
                        <form action="{{ route('deliveries.auto-assign', $delivery) }}" method="POST" class="mt-2 flex flex-wrap items-center gap-2">
                            @csrf
                            <button type="submit" class="inline-flex items-center justify-center bg-white border border-teal/30 hover:border-teal hover:text-teal-dark text-teal font-semibold py-2 px-4 rounded-xl transition text-sm">
                                Auto-assign recommended rider
                            </button>
                            <span class="text-xs text-gray-500">Nearest eligible rider with a fresh GPS ping; workload is the fallback. Eligibility is rechecked before assignment.</span>
                        </form>
                    @endif
                @endif

                @if(! $hasActiveHubTransfer && Auth::user()->canManageRiders() && in_array($delivery->status, ['assigned', 'delivery_failed'], true))
                    @php $reassignLabel = $delivery->status === 'delivery_failed' ? 'Retry — Assign Rider' : 'Reassign Rider'; @endphp
                    <form action="{{ route('deliveries.assign-rider', $delivery) }}" method="POST" class="w-full max-w-xl">
                        @csrf
                        <label for="reassign_rider_id" class="block text-sm font-medium text-gray-700 mb-1">{{ $reassignLabel }}</label>
                        @if($delivery->rider)
                            <p class="text-xs text-gray-500 mb-1">Currently assigned: <span class="font-semibold text-gray-700">{{ $delivery->rider->name }}</span>. Moving it appends a new assignment entry — history is preserved.</p>
                        @endif
                        <div class="flex flex-col sm:flex-row gap-2">
                            <select name="rider_id" id="reassign_rider_id" required
                                    class="flex-1 min-w-0 rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">
                                <option value="">— Select a rider —</option>
                                @if($riderEligibility->where('eligible', true)->isNotEmpty())
                                    <optgroup label="✓ Available — Online">
                                        @foreach($riderEligibility->where('eligible', true) as $item)
                                            <option value="{{ $item['rider']->id }}">
                                                @if($item['recommended']) Recommended — @endif✓ Online — {{ $item['rider']->name }} — {{ ucfirst($item['rider']->vehicle_type) }} — up to {{ $item['capacity'] }} kg ({{ $item['active_deliveries'] }} active)@if($item['distance_km'] !== null) - {{ number_format($item['distance_km'], 2) }} km {{ $item['distance_basis'] === 'pickup' ? 'to pickup' : 'to destination' }}@else - distance unavailable @endif
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endif
                                @if($riderEligibility->where('eligible', false)->isNotEmpty())
                                    <optgroup label="✕ Unavailable">
                                        @foreach($riderEligibility->where('eligible', false) as $item)
                                            <option value="" disabled>
                                                @if(!$item['is_online'])
                                                    ○ Offline — {{ $item['rider']->name }} — {{ ucfirst($item['rider']->vehicle_type) }} — {{ $item['reason'] }}
                                                @else
                                                    ✕ {{ $item['rider']->name }} — {{ ucfirst($item['rider']->vehicle_type) }} — {{ $item['reason'] }}
                                                @endif
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            </select>
                            <button type="submit" class="bg-teal hover:bg-teal-dark text-white font-semibold py-2 px-5 rounded-xl transition shadow-sm text-sm whitespace-nowrap">
                                {{ $delivery->status === 'delivery_failed' ? 'Assign & Retry' : 'Reassign' }}
                            </button>
                        </div>
                        @if($riderEligibility->where('eligible', true)->isEmpty())
                            <p class="mt-2 text-xs text-amber-600">No eligible riders right now. Riders must be approved, active, online, have a verified vehicle, no active delivery, sufficient capacity, and belong to the delivery's destination Logistics Hub and service area.</p>
                        @endif
                        @if($errors->has('rider_id'))
                            <p class="text-red-500 text-xs mt-1">{{ $errors->first('rider_id') }}</p>
                        @endif
                    </form>
                @endif

                @if($delivery->status === 'assigned')
                    <form action="{{ route('deliveries.update-status', $delivery) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="picked_up">
                        <button type="submit" class="bg-indigo-500 hover:bg-indigo-600 text-white font-semibold px-5 py-2.5 rounded-xl transition shadow-sm">
                            Confirm Pickup
                        </button>
                    </form>
                    <button type="button" @click="cancelOpen = true"
                            class="inline-flex items-center gap-2 bg-white border border-gray-200 hover:border-red-300 hover:text-red-600 text-gray-600 font-semibold px-4 py-2.5 rounded-xl transition text-sm">
                        Cancel Delivery
                    </button>
                @endif

                @if($delivery->status === 'picked_up')
                    <form action="{{ route('deliveries.update-status', $delivery) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="out_for_delivery">
                        <button type="submit" class="bg-purple-500 hover:bg-purple-600 text-white font-semibold px-5 py-2.5 rounded-xl transition shadow-sm">
                            Mark as Out for Delivery
                        </button>
                    </form>
                    <button type="button" @click="failOpen = true"
                            class="inline-flex items-center gap-2 bg-white border border-red-200 hover:border-red-300 hover:bg-red-50 text-red-600 font-semibold px-4 py-2.5 rounded-xl transition text-sm">
                        Mark as Failed
                    </button>
                @endif

                @if($delivery->status === 'out_for_delivery')
                    <form action="{{ route('deliveries.update-status', $delivery) }}" method="POST" class="flex flex-wrap items-end gap-2">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="delivered">
                        @if($isCodDelivery)
                            <div>
                                <label for="amount_received" class="block text-xs font-medium text-gray-600 mb-1">Cash Received (COD)</label>
                                <input type="number" name="amount_received" id="amount_received"
                                       value="{{ old('amount_received') }}" min="0" step="0.01" inputmode="decimal" required
                                       class="w-44 rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">
                                <p class="mt-1 text-[11px] text-gray-500">
                                    @if($codExpected !== null)
                                        Amount to collect: &#8369;{{ number_format($codExpected, 2) }}. Differences are flagged for reconciliation.
                                    @else
                                        Enter the actual cash received; the amount to collect is not configured.
                                    @endif
                                </p>
                                @error('amount_received')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif
                        <button type="submit" class="bg-emerald-500 hover:bg-emerald-600 text-white font-semibold px-5 py-2.5 rounded-xl transition shadow-sm">
                            Mark as Delivered
                        </button>
                    </form>
                    <button type="button" @click="failOpen = true"
                            class="inline-flex items-center gap-2 bg-white border border-red-200 hover:border-red-300 hover:bg-red-50 text-red-600 font-semibold px-4 py-2.5 rounded-xl transition text-sm">
                        Mark as Failed
                    </button>
                @endif

                @if($canOverride)
                    <button type="button" @click="overrideOpen = true"
                            class="inline-flex items-center gap-2 bg-white border border-amber-300 hover:bg-amber-50 text-amber-700 font-semibold px-4 py-2.5 rounded-xl transition text-sm">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        Override Status
                    </button>
                @endif

                <button type="button" @click="archiveOpen = true"
                        class="inline-flex items-center gap-2 sm:ml-auto bg-white border border-gray-200 hover:border-gray-300 text-gray-600 font-semibold px-4 py-2.5 rounded-xl transition text-sm">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                    Archive
                </button>
            </div>
        @else
            <div class="flex flex-wrap items-center gap-4">
                <p class="text-sm text-gray-500">
                    Archived {{ $delivery->archived_at?->format('M d, Y h:i A') }}
                    @if($delivery->archiver) by {{ $delivery->archiver->name }}@endif
                    @if($delivery->archive_note) · “{{ $delivery->archive_note }}”@endif
                </p>
                <form action="{{ route('deliveries.restore', $delivery) }}" method="POST" x-data
                      x-on:submit.prevent="$el.submit()">
                    @csrf
                    <button type="submit" class="bg-teal hover:bg-teal-dark text-white font-semibold px-4 py-2 rounded-xl transition text-sm">
                        Restore Delivery
                    </button>
                </form>
            </div>
        @endif

        @if($errors->any())
            <div class="mt-4 bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- Fail Modal --}}
        <div x-show="failOpen" x-cloak class="delivery-modal fixed inset-0 z-50 flex items-center justify-center p-4" x-transition.opacity>
            <div class="absolute inset-0 bg-black/40" @click="failOpen = false"></div>
            <form action="{{ route('deliveries.update-status', $delivery) }}" method="POST" class="relative bg-white rounded-2xl shadow-xl w-full max-w-md" x-transition>
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="delivery_failed">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h4 class="font-bold text-gray-900">Mark Delivery as Failed?</h4>
                    <p class="text-sm text-gray-500 mt-0.5">{{ $delivery->tracking_number }} will be marked failed and removed from active routes.</p>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <div>
                        <label for="delivery-action-field-1" class="block text-sm font-medium text-gray-700 mb-1">Failure Reason <span class="text-red-500">*</span></label>
                        <select id="delivery-action-field-1" name="reason" x-model="failReason" required class="block w-full rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">
                            <option value="">— Select a reason —</option>
                            @foreach(config('logistics.failure_reasons') as $reason)
                                <option value="{{ $reason }}">{{ $reason }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div x-show="failReason === 'Other'" x-cloak>
                        <label for="delivery-action-field-2" class="block text-sm font-medium text-gray-700 mb-1">Please explain <span class="text-red-500">*</span></label>
                        <textarea id="delivery-action-field-2" name="other_reason" :required="failReason === 'Other'" rows="3" class="block w-full rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm"></textarea>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 rounded-b-2xl flex justify-end gap-3">
                    <button type="button" @click="failOpen = false" class="px-5 py-2.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-xl transition">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 bg-red-500 hover:bg-red-600 text-white text-sm font-bold rounded-xl transition">Confirm Failure</button>
                </div>
            </form>
        </div>

        {{-- Cancel Modal --}}
        <div x-show="cancelOpen" x-cloak class="delivery-modal fixed inset-0 z-50 flex items-center justify-center p-4" x-transition.opacity>
            <div class="absolute inset-0 bg-black/40" @click="cancelOpen = false"></div>
            <form action="{{ route('deliveries.update-status', $delivery) }}" method="POST" class="relative bg-white rounded-2xl shadow-xl w-full max-w-md" x-transition>
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="cancelled">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h4 class="font-bold text-gray-900">Cancel Delivery?</h4>
                    <p class="text-sm text-gray-500 mt-0.5">This delivery will be cancelled and kept for records only.</p>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <div>
                        <label for="delivery-action-field-3" class="block text-sm font-medium text-gray-700 mb-1">Cancellation Reason <span class="text-red-500">*</span></label>
                        <select id="delivery-action-field-3" name="reason" x-model="cancelReason" required class="block w-full rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">
                            <option value="">— Select a reason —</option>
                            @foreach(config('logistics.cancellation_reasons') as $reason)
                                <option value="{{ $reason }}">{{ $reason }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div x-show="cancelReason === 'Other'" x-cloak>
                        <label for="delivery-action-field-4" class="block text-sm font-medium text-gray-700 mb-1">Please explain <span class="text-red-500">*</span></label>
                        <textarea id="delivery-action-field-4" name="other_reason" :required="cancelReason === 'Other'" rows="3" class="block w-full rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm"></textarea>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 rounded-b-2xl flex justify-end gap-3">
                    <button type="button" @click="cancelOpen = false" class="px-5 py-2.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-xl transition">Keep Delivery</button>
                    <button type="submit" class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-bold rounded-xl transition">Cancel Delivery</button>
                </div>
            </form>
        </div>

        {{-- Override Modal --}}
        <div x-show="overrideOpen" x-cloak class="delivery-modal fixed inset-0 z-50 flex items-center justify-center p-4" x-transition.opacity>
            <div class="absolute inset-0 bg-black/40" @click="overrideOpen = false"></div>
            <form action="{{ route('deliveries.update-status', $delivery) }}" method="POST" class="relative bg-white rounded-2xl shadow-xl w-full max-w-md" x-transition>
                @csrf
                @method('PATCH')
                <input type="hidden" name="override" value="1">
                @if($isCodDelivery)
                    <div class="px-6 pt-4">
                        <label for="override_amount_received" class="block text-sm font-medium text-gray-700">Cash Received (COD)</label>
                        <input type="number" name="amount_received" id="override_amount_received" min="0" step="0.01" inputmode="decimal"
                               class="mt-1 w-full rounded-xl border-gray-300 text-sm" value="{{ old('amount_received') }}">
                        <p class="mt-1 text-xs text-gray-500">Required when completing a COD delivery. Uploaded proof and a delivery rider are also required.</p>
                    </div>
                @endif
                <div class="px-6 py-4 border-b border-gray-100">
                    <h4 class="font-bold text-gray-900">Override Delivery Status?</h4>
                    <p class="text-sm text-gray-500 mt-0.5">
                        This is a Logistics Manager override. The change from
                        <strong>{{ str_replace('_', ' ', $delivery->status) }}</strong> will be permanently recorded in the audit timeline together with your reason.
                    </p>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <div>
                        <label for="delivery-action-field-5" class="block text-sm font-medium text-gray-700 mb-1">New Status <span class="text-red-500">*</span></label>
                        <select id="delivery-action-field-5" name="status" required class="block w-full rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">
                            <option value="">— Select target status —</option>
                            @foreach(($delivery->order_id ? ['picked_up', 'out_for_delivery', 'delivered'] : ['waiting_for_rider', 'assigned', 'picked_up', 'out_for_delivery', 'delivered']) as $target)
                                @if($target !== $delivery->status)
                                    <option value="{{ $target }}">{{ ucfirst(str_replace('_', ' ', $target)) }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="delivery-action-field-6" class="block text-sm font-medium text-gray-700 mb-1">Override Reason <span class="text-red-500">*</span></label>
                        <textarea id="delivery-action-field-6" name="override_reason" required minlength="10" maxlength="300" rows="3" placeholder="Explain why this override is necessary..."
                                  class="block w-full rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm"></textarea>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 rounded-b-2xl flex justify-end gap-3">
                    <button type="button" @click="overrideOpen = false" class="px-5 py-2.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-xl transition">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 bg-amber-500 hover:bg-amber-600 text-white text-sm font-bold rounded-xl transition">Apply Override</button>
                </div>
            </form>
        </div>

        {{-- Archive Modal --}}
        <div x-show="archiveOpen" x-cloak class="delivery-modal fixed inset-0 z-50 flex items-center justify-center p-4" x-transition.opacity>
            <div class="absolute inset-0 bg-black/40" @click="archiveOpen = false"></div>
            <form action="{{ route('deliveries.archive', $delivery) }}" method="POST" class="relative bg-white rounded-2xl shadow-xl w-full max-w-md" x-transition>
                @csrf
                <div class="px-6 py-4 border-b border-gray-100">
                    <h4 class="font-bold text-gray-900">Archive Delivery?</h4>
                    <p class="text-sm text-gray-500 mt-0.5">This delivery will be removed from active deliveries but preserved in the archive for records and auditing.</p>
                </div>
                <div class="px-6 py-5">
                    <label for="delivery-action-field-7" class="block text-sm font-medium text-gray-700 mb-1">Archive note (optional)</label>
                    <input id="delivery-action-field-7" type="text" name="archive_note" maxlength="255" placeholder="e.g. Duplicate of Order #1042"
                           class="block w-full rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">
                </div>
                <div class="px-6 py-4 bg-gray-50 rounded-b-2xl flex justify-end gap-3">
                    <button type="button" @click="archiveOpen = false" class="px-5 py-2.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-xl transition">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 bg-gray-800 hover:bg-black text-white text-sm font-bold rounded-xl transition">Archive Delivery</button>
                </div>
            </form>
        </div>
    </div>

    @php
        $damageProof = $delivery->proofs->firstWhere('type', 'damage_evidence')
            ?? $delivery->proofs->firstWhere('type', 'parcel_evidence');
        $canReportException = $delivery->rider_id
            && ! in_array($delivery->status, ['delivered', 'cancelled', 'lost', 'damaged', 'returned_to_seller'], true);
    @endphp

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center gap-2 mb-5">
            <div class="h-2 w-1 rounded-full bg-amber-500"></div>
            <h2 class="text-lg font-semibold text-gray-900">Shipment Exceptions &amp; Return to Seller</h2>
        </div>

        @if($damageProof)
            <div class="mb-5 rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
                Exception evidence:
                <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($damageProof->file_path) }}"
                   target="_blank" class="font-semibold underline">view uploaded photo</a>
            </div>
        @endif

        <div class="grid gap-5 lg:grid-cols-2">
            <div class="rounded-xl border border-gray-200 p-4">
                <h3 class="font-semibold text-gray-900 mb-1">Report Lost or Damaged</h3>
                <p class="text-xs text-gray-500 mb-3">Reporting does not refund the buyer or move stock. A damage photo is required.</p>

                @if($canReportException)
                    <form action="{{ route('deliveries.exception', $delivery) }}" method="POST" enctype="multipart/form-data"
                          class="space-y-3" x-data="{ exceptionType: 'damaged' }">
                        @csrf
                        <div>
                            <label for="delivery-action-field-8" class="block text-sm font-medium text-gray-700 mb-1">Exception</label>
                            <select id="delivery-action-field-8" name="type" x-model="exceptionType" @change="$el.form.elements.reason.value = ''" required class="w-full rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">
                                <option value="damaged">Damaged</option>
                                <option value="lost">Lost</option>
                            </select>
                        </div>
                        <div>
                            <label for="delivery-action-field-9" class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                            <select id="delivery-action-field-9" name="reason" required class="w-full rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">
                                <option value="">Select a reason</option>
                                @foreach(($exceptionReasons ?? config('logistics.exception_reasons')) as $type => $reasons)
                                    <optgroup label="{{ ucfirst($type) }}" :hidden="exceptionType !== @js($type)" :disabled="exceptionType !== @js($type)">
                                        @foreach($reasons as $reason)
                                            <option value="{{ $reason }}">{{ $reason }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="delivery-action-field-10" class="block text-sm font-medium text-gray-700 mb-1">Photo <span x-show="exceptionType === 'damaged'" class="text-red-500">*</span></label>
                            <input id="delivery-action-field-10" type="file" name="photo" accept="image/jpeg,image/png,image/webp"
                                   :required="exceptionType === 'damaged'"
                                   class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-teal file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white">
                            <p class="mt-1 text-xs text-gray-500">JPEG, PNG, or WebP up to 8 MB.</p>
                        </div>
                        <div>
                            <label for="delivery-action-field-11" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                            <textarea id="delivery-action-field-11" name="notes" rows="2" maxlength="2000" class="w-full rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm"></textarea>
                        </div>
                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-semibold px-4 py-2 rounded-xl text-sm">
                            Record Exception
                        </button>
                    </form>
                @else
                    <p class="text-sm text-gray-500">
                        @if(! $delivery->rider_id)
                            Assign a rider before recording a lost or damaged report.
                        @else
                            This parcel is already {{ str_replace('_', ' ', $delivery->status) }}.
                        @endif
                    </p>
                @endif
            </div>

            <div class="rounded-xl border border-gray-200 p-4">
                <h3 class="font-semibold text-gray-900 mb-1">Return to Seller</h3>
                @if($returnState)
                    @if($returnState['state'] === 'received')
                        <p class="text-sm font-semibold text-emerald-700">Seller confirmed receipt on {{ $returnState['received_at']?->format('M d, Y h:i A') }}.</p>
                        <p class="text-xs text-gray-500 mt-1">Stock was restored once. No automatic refund was issued.</p>
                    @elseif($returnState['state'] === 'dispatched')
                        <p class="text-sm font-semibold text-blue-700">Return sent to seller on {{ $returnState['dispatched_at']?->format('M d, Y h:i A') }}.</p>
                        <p class="text-xs text-gray-500 mt-1">Waiting for the seller to confirm physical receipt. Stock and refunds are still pending.</p>
                    @elseif($returnState['can_dispatch'])
                        <p class="text-sm text-gray-600 mb-3">The parcel is back at the Logistics Hub. Record the handoff/courier reference before it leaves to the seller.</p>
                        <form action="{{ route('deliveries.return-to-seller', $delivery) }}" method="POST" class="flex flex-col sm:flex-row gap-2">
                            @csrf
                            <input type="text" name="reference" maxlength="150" placeholder="Courier / handoff reference"
                                   class="flex-1 rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded-xl text-sm whitespace-nowrap">
                                Send to Seller
                            </button>
                        </form>
                    @else
                        <p class="text-sm text-gray-500">A failed or damaged parcel must be returned to the Logistics Hub before it can be sent back to the seller.</p>
                    @endif
                @else
                    <p class="text-sm text-gray-500">Return state is unavailable.</p>
                @endif
            </div>
        </div>
    </div>

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/logistics/css/deliveries/show.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('vendor/qrcodejs/qrcode.min.js') }}"></script>
    <script src="{{ asset('vendor/jsbarcode/JsBarcode.all.min.js') }}"></script>
    <script src="{{ asset('assets/logistics/js/deliveries/show.js') }}" data-config="{{ json_encode([($delivery->tracking_number)], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) }}"></script>
@endpush

    </div>
</x-app-layout>
