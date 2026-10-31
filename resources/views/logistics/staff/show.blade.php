<x-app-layout>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('staff.index') }}"
               class="lw-back" aria-label="Back to list">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Staff Details</h1>
                <p class="text-xs text-[#6E6E73] mt-0.5">Assigned hub, operational responsibilities, and account profile.</p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            @if($staff->status === 'active')
                <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200">Active</span>
            @else
                <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600 ring-1 ring-inset ring-gray-200">Inactive</span>
            @endif

            @if(Auth::user()?->canManageRiders() || ! $staff->rider()->exists())
                <a href="{{ route('staff.edit', $staff) }}" class="inline-flex items-center gap-2 bg-white border border-gray-200 text-gray-700 hover:text-teal hover:border-teal px-4 py-2 rounded-xl text-sm font-semibold transition">Edit</a>
                @if($staff->status === 'active')
                    <form action="{{ route('staff.destroy', $staff) }}" method="POST" x-data data-confirm-message="Deactivate {{ $staff->name }}?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-xl text-sm font-semibold transition">Deactivate</button>
                    </form>
                @else
                    <form action="{{ route('staff.activate', $staff) }}" method="POST">
                        @csrf
                        <button type="submit" class="bg-teal hover:bg-teal-dark text-white px-4 py-2 rounded-xl text-sm font-semibold transition">Activate</button>
                    </form>
                @endif
            @else
                <span class="text-sm text-gray-500">Managed by logistics</span>
            @endif
        </div>
    </div>

    {{-- Staff Profile Card --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-5">
            <div class="flex items-center gap-5">
                <div class="h-16 w-16 rounded-full bg-[#16697A] flex items-center justify-center text-2xl font-bold text-white shrink-0">
                    {{ strtoupper(substr($staff->name, 0, 1)) }}
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-900">{{ $staff->name }}</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ $staff->email }} &middot; {{ $staff->phone }}</p>
                    <div class="mt-2.5 flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-lg bg-teal-light text-teal-dark">
                            {{ $staff->roleLabel() }}
                        </span>
                        @if($staff->logisticsCenter)
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-lg bg-gray-100 text-gray-700">
                                <svg class="w-3.5 h-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                                Assigned Hub: {{ $staff->logisticsCenter->name }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>
            @if($staff->logisticsCenter)
                <a href="{{ route('centers.show', $staff->logisticsCenter) }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#16697A] hover:underline bg-[#E6F4F1] px-3 py-2 rounded-xl">
                    <span>View Hub Dashboard</span>
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            @endif
        </div>
    </div>

    {{-- Hub Assignment & Operational Responsibilities --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-base font-bold text-gray-900">Hub Responsibilities & Operational Scope</h3>
                <p class="text-xs text-gray-500 mt-0.5">Authorizations and duties handled by this staff member at their assigned hub.</p>
            </div>
            @if($staff->logisticsCenter)
                <span class="text-xs font-medium text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full border border-emerald-200">Hub Scoped</span>
            @else
                <span class="text-xs font-medium text-amber-700 bg-amber-50 px-2.5 py-1 rounded-full border border-amber-200">Pending Assignment</span>
            @endif
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {{-- 1. Parcel Intake & Receiving --}}
            <div class="p-4 rounded-xl border border-gray-100 bg-[#FAFAFA]">
                <div class="flex items-center gap-2.5 mb-2">
                    <div class="w-7 h-7 rounded-lg bg-[#E6F4F1] flex items-center justify-center text-[#16697A]">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                        </svg>
                    </div>
                    <h4 class="text-sm font-semibold text-gray-900">1. Parcel Intake & Receiving</h4>
                </div>
                <p class="text-xs text-gray-600 leading-relaxed">
                    Receives incoming parcels arriving at {{ $staff->logisticsCenter?->name ?? 'the hub' }} from sellers, transfer trucks, or couriers via the Incoming Parcels queue.
                </p>
            </div>

            {{-- 2. Scanning & Verification --}}
            <div class="p-4 rounded-xl border border-gray-100 bg-[#FAFAFA]">
                <div class="flex items-center gap-2.5 mb-2">
                    <div class="w-7 h-7 rounded-lg bg-[#E6F4F1] flex items-center justify-center text-[#16697A]">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                        </svg>
                    </div>
                    <h4 class="text-sm font-semibold text-gray-900">2. Scanning & Verification</h4>
                </div>
                <p class="text-xs text-gray-600 leading-relaxed">
                    Scans QR codes and barcodes for tracking verification, waybill inspection, and logging physical package custody timestamps.
                </p>
            </div>

            {{-- 3. Destination Sorting --}}
            <div class="p-4 rounded-xl border border-gray-100 bg-[#FAFAFA]">
                <div class="flex items-center gap-2.5 mb-2">
                    <div class="w-7 h-7 rounded-lg bg-[#E6F4F1] flex items-center justify-center text-[#16697A]">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                    </div>
                    <h4 class="text-sm font-semibold text-gray-900">3. Destination Sorting</h4>
                </div>
                <p class="text-xs text-gray-600 leading-relaxed">
                    Sorts parcels according to destination hub, service area, or delivery route to prepare them for correct next-hop movement.
                </p>
            </div>

            {{-- 4. Parcel Dispatching --}}
            <div class="p-4 rounded-xl border border-gray-100 bg-[#FAFAFA]">
                <div class="flex items-center gap-2.5 mb-2">
                    <div class="w-7 h-7 rounded-lg bg-[#E6F4F1] flex items-center justify-center text-[#16697A]">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                    </div>
                    <h4 class="text-sm font-semibold text-gray-900">4. Parcel Dispatching</h4>
                </div>
                <p class="text-xs text-gray-600 leading-relaxed">
                    Dispatches linehaul parcels via Fleet Hub Transfers (Truck Drivers) or releases parcels for last-mile delivery to local Delivery Riders.
                </p>
            </div>

            {{-- 5. Operational Monitoring --}}
            <div class="p-4 rounded-xl border border-gray-100 bg-[#FAFAFA]">
                <div class="flex items-center gap-2.5 mb-2">
                    <div class="w-7 h-7 rounded-lg bg-[#E6F4F1] flex items-center justify-center text-[#16697A]">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <h4 class="text-sm font-semibold text-gray-900">5. Operational Monitoring</h4>
                </div>
                <p class="text-xs text-gray-600 leading-relaxed">
                    Monitors local hub parcel counts, incoming linehaul schedules, damaged parcel reports, and operational sorting backlogs.
                </p>
            </div>

            {{-- 6. Boundary Protection --}}
            <div class="p-4 rounded-xl border border-gray-100 bg-[#FAFAFA]">
                <div class="flex items-center gap-2.5 mb-2">
                    <div class="w-7 h-7 rounded-lg bg-[#E6F4F1] flex items-center justify-center text-[#16697A]">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <h4 class="text-sm font-semibold text-gray-900">6. Hub Boundary Security</h4>
                </div>
                <p class="text-xs text-gray-600 leading-relaxed">
                    Access is securely scoped to {{ $staff->logisticsCenter?->name ?? 'the assigned hub' }}. System enforces hub boundaries preventing cross-hub data leakage.
                </p>
            </div>
        </div>
    </div>

    {{-- Account Details Card --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-base font-bold text-gray-900 mb-5">Account Profile Information</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Full Name</p>
                <p class="text-sm font-medium text-gray-900">{{ $staff->name }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Sex</p>
                <p class="text-sm font-medium text-gray-900">{{ ucfirst($staff->sex ?? '—') }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Birthday</p>
                <p class="text-sm font-medium text-gray-900">{{ $staff->birthday?->format('M d, Y') ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Age</p>
                <p class="text-sm font-medium text-gray-900">{{ $staff->age ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Logistics Hub</p>
                <p class="text-sm font-medium text-gray-900">{{ $staff->logisticsCenter?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Role</p>
                <p class="text-sm font-medium text-gray-900">{{ $staff->roleLabel() }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Status</p>
                <p class="text-sm font-medium text-gray-900 capitalize">{{ $staff->status }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Date Joined</p>
                <p class="text-sm font-medium text-gray-900">{{ $staff->created_at?->format('M d, Y') ?? '—' }}</p>
            </div>
        </div>
    </div>
</x-app-layout>
