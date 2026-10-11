<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-bold text-gray-900 leading-tight">Tracking Result</h2>
        <p class="mt-1 text-sm text-gray-500">Live status of your delivery</p>
    </x-slot>

    @if ($delivery === null)
        <div class="flex items-center justify-center py-12">
            <div class="w-full max-w-lg bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center">
                <div class="mx-auto h-16 w-16 rounded-full bg-gray-100 flex items-center justify-center mb-5">
                    <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">No delivery found</h3>
                <p class="text-sm text-gray-500 mb-8">No delivery found with this tracking number. Please check the number and try again.</p>
                <a href="{{ route('tracking.index') }}" class="inline-flex items-center px-6 py-3 bg-teal text-white rounded-xl font-semibold hover:bg-teal-dark transition-colors duration-200">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Search
                </a>
            </div>
        </div>
    @else
        @php
            $steps = [
                'waiting_for_rider' => 'Waiting for Rider',
                'assigned'          => 'Assigned',
                'picked_up'         => 'Picked Up',
                'out_for_delivery'  => 'Out for Delivery',
                'delivered'         => 'Delivered',
            ];
            $order = array_keys($steps);
            $currentIndex = array_search($delivery->status, $order);
            if ($currentIndex === false) {
                $currentIndex = -1;
            }
            $lastIndex = count($order) - 1;
        @endphp

        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <h3 class="text-xl font-bold text-gray-900">Tracking Result</h3>
                <a href="{{ route('tracking.index') }}" class="inline-flex items-center text-sm font-medium text-gray-500 hover:text-teal-dark transition-colors">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Search
                </a>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sm:p-8">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Tracking Number</p>
                        <h4 class="font-mono text-3xl font-bold text-gray-900">{{ $delivery->tracking_number }}</h4>
                        <p class="mt-2 text-xs text-gray-400">Order #{{ $delivery->order_id ?? $delivery->id }} · Last updated {{ $delivery->updated_at->format('M d, Y \a\t g:i A') }}</p>
                    </div>
                    <x-status-badge :status="$delivery->status" class="px-4 py-2 text-sm" />
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sm:p-8">
                <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-8">Delivery Progress</h4>

                @if ($currentIndex === -1)
                    <div class="flex flex-col items-center text-center py-2">
                        <span class="inline-flex items-center gap-2 px-5 py-3 rounded-xl text-sm font-bold {{ $delivery->status === 'delivery_failed' ? 'bg-red-100 text-red-700 ring-1 ring-red-200' : 'bg-gray-100 text-gray-600 ring-1 ring-gray-200' }}">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $delivery->status === 'delivery_failed' ? 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z' : 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636'}}"/></svg>
                            Final Status: {{ ucfirst(str_replace('_', ' ', $delivery->status)) }} — this delivery was not completed
                        </span>
                        @if($delivery->status === 'delivery_failed' && $delivery->failure_reason)
                            <p class="text-sm text-red-600 mt-3"><strong>Failure reason:</strong> {{ $delivery->failure_reason }}</p>
                        @endif
                        @if($delivery->status === 'cancelled' && $delivery->cancellation_reason)
                            <p class="text-sm text-gray-600 mt-3"><strong>Cancellation reason:</strong> {{ $delivery->cancellation_reason }}</p>
                        @endif
                    </div>
                @else
                    <div class="relative">
                        <div class="absolute top-5 left-0 right-0 h-1 bg-gray-200 rounded-full"></div>
                        <div class="absolute top-5 left-0 h-1 bg-green-500 rounded-full transition-all duration-700" style="width: {{ ($currentIndex / $lastIndex) * 100 }}%"></div>

                        <div class="relative flex justify-between">
                            @foreach ($order as $index => $statusKey)
                                @php
                                    $isCompleted = $index < $currentIndex;
                                    $isCurrent = $index === $currentIndex;
                                    $isFuture = $index > $currentIndex;
                                @endphp
                                <div class="flex flex-col items-center flex-1 text-center">
                                    <div class="relative flex items-center justify-center">
                                        @if ($isCurrent)
                                            <span class="absolute h-3 w-3 rounded-full bg-teal animate-ping"></span>
                                        @endif
                                        <div class="relative h-10 w-10 rounded-full flex items-center justify-center {{ $isCompleted ? 'bg-green-500 text-white' : ($isCurrent ? 'bg-teal text-white shadow-lg shadow-teal/30' : 'bg-gray-200 text-gray-400') }}">
                                            @if ($isCompleted)
                                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                                </svg>
                                            @elseif ($isCurrent)
                                                <span class="h-3 w-3 rounded-full bg-white"></span>
                                            @else
                                                <span class="h-2.5 w-2.5 rounded-full bg-gray-400"></span>
                                            @endif
                                        </div>
                                    </div>
                                    <p class="mt-3 text-xs font-medium {{ $isCurrent ? 'text-teal-dark' : ($isCompleted ? 'text-green-600' : 'text-gray-400') }}">{{ $steps[$statusKey] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4 flex items-center">
                        <svg class="h-4 w-4 text-teal mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        Sender Information
                    </h4>
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-xs text-gray-400">Name</dt>
                            <dd class="text-sm font-medium text-gray-800">{{ $delivery->sender_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-400">Phone</dt>
                            <dd class="text-sm font-medium text-gray-800">{{ $delivery->sender_phone }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-400">Address</dt>
                            <dd class="text-sm font-medium text-gray-800">{{ $delivery->sender_address }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4 flex items-center">
                        <svg class="h-4 w-4 text-teal mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Recipient Information
                    </h4>
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-xs text-gray-400">Name</dt>
                            <dd class="text-sm font-medium text-gray-800">{{ $delivery->recipient_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-400">Phone</dt>
                            <dd class="text-sm font-medium text-gray-800">{{ $delivery->recipient_phone }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-400">Address</dt>
                            <dd class="text-sm font-medium text-gray-800">{{ $delivery->recipient_address }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4 flex items-center">
                        <svg class="h-4 w-4 text-teal mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        Package Information
                    </h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-gray-50 rounded-xl p-4">
                            <p class="text-xs text-gray-400">Weight</p>
                            <p class="text-gray-800 font-semibold mt-1">{{ $delivery->weight ? $delivery->weight . ' kg' : 'N/A' }}</p>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-4">
                            <p class="text-xs text-gray-400">Notes</p>
                            <p class="text-gray-800 font-semibold mt-1">{{ $delivery->notes ?? 'No notes' }}</p>
                        </div>
                    </div>
                </div>

                @if ($delivery->rider)
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4 flex items-center">
                            <svg class="h-4 w-4 text-teal mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            Assigned Rider
                        </h4>
                        <div class="flex items-center">
                            <div class="h-12 w-12 rounded-full bg-teal-light flex items-center justify-center">
                                <svg class="h-6 w-6 text-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                            <div class="ml-4 flex-1">
                                <p class="text-gray-800 font-semibold">{{ $delivery->rider->name }}</p>
                                <p class="text-sm text-gray-500">{{ $delivery->rider->phone }}</p>
                                @if ($delivery->rider->vehicle_type)
                                    <p class="text-sm text-gray-500">{{ ucfirst($delivery->rider->vehicle_type) }} · {{ $delivery->rider->license_plate }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @else
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4 flex items-center">
                            <svg class="h-4 w-4 text-teal mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            Assigned Rider
                        </h4>
                        <span class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-full bg-gray-100 text-gray-500 ring-1 ring-inset ring-gray-200">Unassigned</span>
                        <p class="text-xs text-gray-400 mt-2">A rider has not been assigned to this delivery yet.</p>
                    </div>
                @endif
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-6">Delivery Dates</h4>
                <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-5">
                    <div>
                        <dt class="text-xs text-gray-400">Created At</dt>
                        <dd class="text-sm font-medium text-gray-800 mt-0.5">{{ $delivery->created_at->format('M d, Y h:i A') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-400">Estimated Delivery</dt>
                        <dd class="text-sm font-medium text-gray-800 mt-0.5">{{ $delivery->estimated_delivery_at ? $delivery->estimated_delivery_at->format('M d, Y h:i A') : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-400">Actual Pickup</dt>
                        <dd class="text-sm font-medium text-gray-800 mt-0.5">{{ $delivery->picked_up_at ? $delivery->picked_up_at->format('M d, Y h:i A') : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-400">Actual Delivery</dt>
                        <dd class="text-sm font-medium text-gray-800 mt-0.5">{{ $delivery->delivered_at ? $delivery->delivered_at->format('M d, Y h:i A') : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-400">Created By</dt>
                        <dd class="text-sm font-medium text-gray-800 mt-0.5">{{ $delivery->creator?->name ?? 'System' }}</dd>
                    </div>
                </dl>
            </div>

            @if ($delivery->proofs && $delivery->proofs->count())
                <div class="bg-white rounded-2xl shadow-sm border border-emerald-100 p-6">
                    <h4 class="text-sm font-semibold text-emerald-600 uppercase tracking-wider mb-5">Proof of Delivery</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach ($delivery->proofs as $proof)
                            <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                                <p class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-1">{{ strtoupper($proof->type) }} confirmation</p>
                                @if($proof->type === 'signature' && $proof->signature_name)
                                    <p class="text-sm font-semibold text-gray-900">Signed by: {{ $proof->signature_name }}</p>
                                @elseif($proof->file_path && file_exists(public_path('storage/' . $proof->file_path)))
                                    <img src="{{ asset('storage/' . $proof->file_path) }}" alt="Proof" class="mt-1 rounded-lg max-h-40 object-cover w-full">
                                @endif
                                <p class="text-xs text-gray-400 mt-2">{{ $proof->created_at->format('M d, Y h:i A') }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($delivery->statusLogs && $delivery->statusLogs->count())
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sm:p-8">
                    <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-6">Delivery Timeline</h4>
                    <div class="relative">
                        <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-gray-200"></div>
                        <div class="space-y-6">
                            @php
                                // Past parcel-pipeline / center / record events get a
                                // kind tag so history never reads as current status.
                                $parcelEventStatuses = ['pending_arrival', 'received', 'scanned', 'sorted', 'dispatched'];
                                $centerEventStatuses = ['sorting_center_handoff', 'sorting_center_pickup'];
                                $recordEventStatuses = ['archived', 'restored'];
                            @endphp
                            @foreach ($delivery->statusLogs->sortByDesc('created_at') as $log)
                                @php
                                    $dotColors = [
                                        'delivered'         => 'bg-emerald-500',
                                        'out_for_delivery'  => 'bg-purple-500',
                                        'picked_up'         => 'bg-indigo-500',
                                        'assigned'          => 'bg-blue-500',
                                        'waiting_for_rider' => 'bg-amber-500',
                                    ];
                                    $dotColor = $dotColors[$log->status] ?? 'bg-gray-400';
                                @endphp
                                <div class="relative pl-10">
                                    <div class="absolute left-2.5 top-1.5 w-3 h-3 rounded-full {{ $dotColor }}"></div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-status-badge :status="$log->status" />
                                    @if(in_array($log->status, $parcelEventStatuses, true))<span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide rounded bg-teal-light text-teal-dark">Parcel</span>@endif
                                    @if(in_array($log->status, $centerEventStatuses, true))<span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide rounded bg-amber-100 text-amber-700">Sorting Center</span>@endif
                                    @if(in_array($log->status, $recordEventStatuses, true))<span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide rounded bg-gray-200 text-gray-500">Record</span>@endif
                                    <span class="text-xs text-gray-400">{{ $log->created_at->format('M d, Y \a\t g:i A') }}</span>
                                </div>
                                @if ($log->notes)
                                    <p class="text-sm text-gray-600 mt-2">{{ $log->notes }}</p>
                                @endif
                                @if($log->changer)
                                    <p class="text-xs text-gray-400 mt-1">Changed by: {{ $log->changer->name }}</p>
                                @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif
</x-app-layout>
