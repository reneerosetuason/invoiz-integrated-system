<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('riders.index') }}" class="btn btn-outline btn-sm" aria-label="Back to list">
                    &larr; Back
                </a>
                <div>
                    <h1 class="text-2xl font-bold text-[#1B1B1E]">Rider Details</h1>
                    <p class="mt-0.5 text-xs text-[#6E6E73]">Rider management &amp; operational profile</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-status-badge :status="$rider->status" />
                @if(Auth::user()->canManageRiders() && (Auth::user()->isLogisticsAdmin() || (Auth::user()->center_id && (int) Auth::user()->center_id === (int) $rider->center_id)))
                    <form action="{{ route('riders.reset-credentials', $rider) }}" method="POST" x-data data-confirm-message="Generate a NEW login credential for {{ $rider->name }}? The previous password will stop working immediately.">
                        @csrf
                        <button type="submit" class="btn btn-primary">
                            Generate New Credential
                        </button>
                    </form>
                    @if($rider->status === 'inactive')
                        <form action="{{ route('riders.activate', $rider) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-outline">
                                Activate Rider
                            </button>
                        </form>
                    @else
                        <form action="{{ route('riders.deactivate', $rider) }}" method="POST" x-data data-confirm-message="Suspend this rider? They will lose access to rider tasks." data-confirm-reason="Reason for suspension" data-confirm-min-length="10">
                            @csrf
                            <input type="hidden" name="reason" value="{{ old('reason') }}">
                            <button type="submit" class="btn btn-danger">
                                Suspend Rider
                            </button>
                        </form>
                        @error('reason')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    @endif
                @endif
            </div>
        </div>
    </x-slot>

    {{-- Profile Header Card --}}
    <div class="card p-6 mb-5">
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-5">
            <div class="h-14 w-14 rounded-full bg-[#16697A] flex items-center justify-center text-xl font-bold text-white shrink-0">
                {{ strtoupper(substr($rider->name, 0, 1)) }}
            </div>
            <div>
                <h2 class="text-lg font-bold text-[#1B1B1E]">{{ $rider->name }}</h2>
                <p class="mt-0.5 text-xs text-[#6E6E73]">{{ $rider->email }} &middot; {{ $rider->phone }}</p>
                <span class="mt-2 inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-lg bg-[#EAF4F3] text-[#0E4A57]">
                    {{ ucfirst($rider->vehicle_type) }} &middot; {{ $rider->license_plate }}
                </span>
            </div>
        </div>
    </div>

    {{-- Rider Info Grid --}}
    <div class="card p-6 mb-5">
        <h3 class="text-sm font-bold text-[#1B1B1E] mb-4">Operational Information</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
            <div>
                <p class="field-label">Name</p>
                <p class="text-sm font-semibold text-[#1B1B1E]">{{ $rider->name }}</p>
            </div>
            <div>
                <p class="field-label">Phone</p>
                <p class="text-sm font-semibold text-[#1B1B1E]">{{ $rider->phone }}</p>
            </div>
            <div>
                <p class="field-label">Email</p>
                <p class="text-sm font-semibold text-[#1B1B1E]">{{ $rider->email }}</p>
            </div>
            <div>
                <p class="field-label">Logistics Center</p>
                <p class="text-sm font-semibold text-[#1B1B1E]">{{ $rider->logisticsCenter?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="field-label">Service Area</p>
                <p class="text-sm font-semibold text-[#1B1B1E]">{{ $rider->serviceArea?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="field-label">Vehicle Type</p>
                <p class="text-sm font-semibold text-[#1B1B1E]">{{ ucfirst($rider->vehicle_type) }}</p>
            </div>
            <div>
                <p class="field-label">License Plate</p>
                <p class="text-sm font-semibold text-[#1B1B1E] font-mono">{{ $rider->license_plate }}</p>
            </div>
            <div>
                <p class="field-label">Vehicle Capacity</p>
                <p class="text-sm font-semibold text-[#1B1B1E]">
                    {{ $rider->vehicle_capacity_kg ? $rider->vehicle_capacity_kg . ' kg' : (\App\Models\LogisticsSetting::vehicleCapacities()[$rider->vehicle_type] ?? '—') . ' kg (default)' }}
                </p>
            </div>
            <div>
                <p class="field-label">Vehicle Verification</p>
                @if($rider->vehicle_verification === 'verified')
                    <span class="badge badge-completed">Verified</span>
                @elseif($rider->vehicle_verification === 'rejected')
                    <span class="badge badge-cancelled">Rejected</span>
                @else
                    <span class="badge badge-pending">Pending</span>
                @endif
            </div>
            <div>
                <p class="field-label">License Number</p>
                <p class="text-sm font-semibold text-[#1B1B1E] font-mono">{{ $rider->license_number ?: '—' }}</p>
            </div>
            <div>
                <p class="field-label">License Expiry</p>
                @if(!$rider->license_expiry)
                    <p class="text-sm text-[#6E6E73]">Not on file</p>
                @elseif($rider->licenseExpired())
                    <span class="badge badge-cancelled">Expired {{ $rider->license_expiry->format('M d, Y') }}</span>
                @elseif($rider->licenseExpiringSoon())
                    <span class="badge badge-pending">Expires {{ $rider->license_expiry->format('M d, Y') }}</span>
                @else
                    <p class="text-sm font-semibold text-[#1B1B1E]">{{ $rider->license_expiry->format('M d, Y') }}</p>
                @endif
            </div>
            <div>
                <p class="field-label">Availability</p>
                @if($rider->is_online)
                    <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-600">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span> Online
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 text-sm font-medium text-[#6E6E73]">
                        <span class="h-2 w-2 rounded-full bg-gray-300"></span> Offline
                    </span>
                @endif
            </div>
            <div class="lg:col-span-2">
                <p class="field-label">Current Delivery</p>
                @if($currentDelivery)
                    <a href="{{ route('deliveries.show', $currentDelivery) }}" class="inline-flex items-center gap-2 text-sm font-bold text-[#16697A] hover:underline">
                        {{ $currentDelivery->tracking_number }}
                        <x-status-badge :status="$currentDelivery->status" />
                    </a>
                @else
                    <p class="text-sm text-[#6E6E73]">No active delivery right now</p>
                @endif
            </div>
        </div>
    </div>

    @php($canManageRider = Auth::user()->canManageRiders() && (Auth::user()->isLogisticsAdmin() || (Auth::user()->center_id && (int) Auth::user()->center_id === (int) $rider->center_id)))
    @if($canManageRider)
        <div class="card p-6 mb-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
                <div>
                    <h3 class="text-sm font-bold text-[#1B1B1E]">Update / Re-verify License</h3>
                    <p class="mt-0.5 text-xs text-[#6E6E73]">Renew the license details and clear any expiry block.</p>
                </div>
                @if($rider->licenseExpired())
                    <span class="badge badge-cancelled">Action required</span>
                @endif
            </div>
            <form action="{{ route('riders.reverify', $rider) }}" method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                @csrf
                <div>
                    <label class="field-label">License Number</label>
                    <input type="text" name="license_number" value="{{ old('license_number', $rider->license_number) }}" required class="input">
                </div>
                <div>
                    <label class="field-label">License Expiry</label>
                    <input type="date" name="license_expiry" value="{{ old('license_expiry', optional($rider->license_expiry)->format('Y-m-d')) }}" required class="input">
                </div>
                <div>
                    <label class="field-label">Vehicle Registration</label>
                    <input type="text" name="vehicle_registration" value="{{ old('vehicle_registration', $rider->vehicle_registration) }}" class="input">
                </div>
                <div>
                    <button type="submit" class="btn btn-primary w-full">Save &amp; Verify</button>
                </div>
            </form>
            @error('license_expiry')
                <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
    @endif

    {{-- Rider Remittance Payouts & Earnings --}}
    <div class="card overflow-hidden mb-5">
        <div class="px-6 py-4 border-b border-[#E8E6E0] flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <h3 class="text-sm font-bold text-[#1B1B1E]">Rider Earnings &amp; Remittance Payouts</h3>
                <p class="mt-0.5 text-xs text-[#6E6E73]">Last-mile rider deducts ₱50 automatically from COD cash. Pickup rider earnings (₱30/item) are remitted by Admin and accepted here.</p>
            </div>
            @php
                $riderEarnedTotal = (float) $payouts->sum('amount');
                $riderPendingAccept = (float) $payouts->where('status', 'remitted')->sum('amount');
            @endphp
            <div class="flex items-center gap-3">
                <div class="text-right">
                    <span class="text-[11px] text-[#6E6E73]">Total Payouts:</span>
                    <strong class="text-sm text-[#0E4A57] block">₱{{ number_format($riderEarnedTotal, 2) }}</strong>
                </div>
            </div>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>Role &amp; Task</th>
                    <th>Order / Tracking #</th>
                    <th>Amount</th>
                    <th>Remittance Status</th>
                    <th class="text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payouts as $payout)
                    <tr>
                        <td>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold {{ $payout->recipient_role === 'last_mile_rider' ? 'bg-purple-100 text-purple-800' : 'bg-emerald-100 text-emerald-800' }}">
                                {{ $payout->roleLabel() }}
                            </span>
                            <div class="text-[11px] text-[#6E6E73] mt-0.5">{{ $payout->rule_applied }}</div>
                        </td>
                        <td>
                            @if($payout->delivery)
                                <a href="{{ route('deliveries.show', $payout->delivery_id) }}" class="font-bold text-[#16697A] hover:underline">{{ $payout->delivery->tracking_number }}</a>
                            @else
                                <span class="font-bold text-[#1B1B1E]">Order #{{ $payout->order_id }}</span>
                            @endif
                        </td>
                        <td class="font-extrabold text-[#1B1B1E]">
                            ₱{{ number_format((float) $payout->amount, 2) }}
                        </td>
                        <td>
                            @if($payout->status === 'accepted')
                                <span class="badge badge-completed inline-flex items-center gap-1">
                                    <svg class="w-3 h-3 text-emerald-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                    Accepted by Rider
                                </span>
                            @elseif($payout->status === 'remitted')
                                <span class="badge badge-confirmed inline-flex items-center gap-1">
                                    <svg class="w-3 h-3 text-blue-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                    Sent by Admin (Ready to Accept)
                                </span>
                            @elseif($payout->status === 'pending_remittance')
                                <span class="badge badge-pending inline-flex items-center gap-1">
                                    <svg class="w-3 h-3 text-amber-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    Awaiting Admin Approval
                                </span>
                            @else
                                <span class="badge badge-cancelled">Held in Escrow</span>
                            @endif
                        </td>
                        <td class="text-right">
                            @if($payout->status === 'remitted')
                                <form method="POST" action="{{ route('riders.payouts.accept', $payout->id) }}" class="inline" onsubmit="return confirm('Confirm receipt of ₱{{ number_format($payout->amount, 2) }} remittance?');">
                                    @csrf
                                    <button type="submit" class="btn btn-xs bg-emerald-600 hover:bg-emerald-700 text-white font-bold shadow-sm">
                                        Accept Remittance
                                    </button>
                                </form>
                            @elseif($payout->status === 'accepted')
                                <span class="text-xs text-emerald-700 font-bold inline-flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                    Confirmed
                                </span>
                            @elseif($payout->recipient_role === 'last_mile_rider')
                                <span class="text-xs text-gray-500">Deducted from COD</span>
                            @else
                                <span class="text-xs text-amber-700">Awaiting Admin</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-6 text-center text-xs text-[#6E6E73]">No remittance records for this rider yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Recent Deliveries --}}
    <div class="card overflow-hidden">
        <div class="px-6 py-4 border-b border-[#E8E6E0]">
            <h3 class="text-sm font-bold text-[#1B1B1E]">Recent Deliveries</h3>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>Tracking #</th>
                    <th>Recipient</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th class="text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rider->deliveries->take(10) as $delivery)
                    <tr>
                        <td>
                            <a href="{{ route('deliveries.show', $delivery) }}" class="font-bold text-[#16697A] hover:underline">
                                {{ $delivery->tracking_number }}
                            </a>
                        </td>
                        <td class="text-[#1B1B1E]">{{ $delivery->recipient_name }}</td>
                        <td>
                            <x-status-badge :status="$delivery->status" />
                        </td>
                        <td class="text-xs text-[#6E6E73]">{{ $delivery->created_at?->format('M d, Y') ?? '—' }}</td>
                        <td class="text-right">
                            <a href="{{ route('deliveries.show', $delivery) }}" class="btn btn-soft btn-xs">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-12 text-center">
                            <p class="text-sm font-bold text-[#1B1B1E]">No deliveries found</p>
                            <p class="mt-1 text-xs text-[#6E6E73]">This rider hasn't handled any deliveries yet.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
