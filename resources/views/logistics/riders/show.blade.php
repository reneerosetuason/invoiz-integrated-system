<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <a href="{{ route('riders.index') }}" class="p-2 rounded-xl bg-white border border-gray-100 text-gray-500 hover:text-teal hover:border-teal transition">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </a>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Rider Details</h1>
                    <p class="mt-0.5 text-sm text-gray-500">Rider management</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <x-status-badge :status="$rider->status" />
                @if(Auth::user()->isAdmin() || Auth::user()->center_id == $rider->center_id)
                    <form action="{{ route('riders.reset-credentials', $rider) }}" method="POST" x-data x-on:submit.prevent="if (confirm('Generate a NEW login credential for {{ $rider->name }}? The previous password will stop working immediately.')) $el.submit()">
                        @csrf
                        <button type="submit" class="bg-teal hover:bg-teal-dark text-white px-4 py-2 rounded-xl text-sm font-semibold transition shadow-sm whitespace-nowrap">
                            Generate New Credential
                        </button>
                    </form>
                    @if($rider->status === 'inactive')
                        <form action="{{ route('riders.activate', $rider) }}" method="POST">
                            @csrf
                            <button type="submit" class="bg-emerald-500 hover:bg-emerald-600 text-white px-4 py-2 rounded-xl text-sm font-semibold transition shadow-sm whitespace-nowrap">
                                Activate Rider
                            </button>
                        </form>
                    @else
                        <form action="{{ route('riders.deactivate', $rider) }}" method="POST" x-data x-on:submit.prevent="if (confirm('Deactivate {{ $rider->name }}? Existing deliveries will be preserved.')) $el.submit()">
                            @csrf
                            <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-xl text-sm font-semibold transition shadow-sm whitespace-nowrap">
                                Deactivate
                            </button>
                        </form>
                    @endif
                @endif
            </div>
        </div>
    </x-slot>

    {{-- Profile Header Card --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-5">
            <div class="h-16 w-16 rounded-full bg-teal flex items-center justify-center text-2xl font-bold text-white flex-shrink-0">
                {{ strtoupper(substr($rider->name, 0, 1)) }}
            </div>
            <div>
                <h2 class="text-xl font-bold text-gray-900">{{ $rider->name }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $rider->email }} &middot; {{ $rider->phone }}</p>
                <span class="mt-3 inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-lg bg-teal-light text-teal-dark">
                    {{ ucfirst($rider->vehicle_type) }} &middot; {{ $rider->license_plate }}
                </span>
            </div>
        </div>
    </div>

    {{-- Rider Info Grid --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
        <h3 class="text-base font-bold text-gray-900 mb-5">Operational Information</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Name</p>
                <p class="text-sm font-medium text-gray-900">{{ $rider->name }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Phone</p>
                <p class="text-sm font-medium text-gray-900">{{ $rider->phone }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Email</p>
                <p class="text-sm font-medium text-gray-900">{{ $rider->email }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Logistics Center</p>
                @if($rider->logisticsCenter)
                    <p class="text-sm font-medium text-gray-900">{{ $rider->logisticsCenter->name }}</p>
                @else
                    <p class="text-sm text-gray-400">—</p>
                @endif
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Service Area</p>
                @if($rider->serviceArea)
                    <p class="text-sm font-medium text-gray-900">{{ $rider->serviceArea->name }}</p>
                @else
                    <p class="text-sm text-gray-400">—</p>
                @endif
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Vehicle Type</p>
                <p class="text-sm font-medium text-gray-900">{{ ucfirst($rider->vehicle_type) }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Vehicle Details / Plate</p>
                <p class="text-sm font-medium text-gray-900 font-mono">{{ $rider->license_plate }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Vehicle Capacity</p>
                <p class="text-sm font-medium text-gray-900">
                    {{ $rider->vehicle_capacity_kg ? $rider->vehicle_capacity_kg . ' kg' : (\App\Models\LogisticsSetting::vehicleCapacities()[$rider->vehicle_type] ?? '—') . ' kg (default)' }}
                </p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Vehicle Verification</p>
                @if($rider->vehicle_verification === 'verified')
                    <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200">Verified</span>
                @elseif($rider->vehicle_verification === 'rejected')
                    <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-red-50 text-red-700 ring-1 ring-inset ring-red-200">Rejected</span>
                @else
                    <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200">Pending</span>
                @endif
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Availability</p>
                @if($rider->is_online)
                    <span class="inline-flex items-center gap-1.5 text-sm font-medium text-green-600">
                        <span class="h-2 w-2 rounded-full bg-green-500"></span> Online — Available for deliveries
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-400" title="Offline — not available for new deliveries">
                        <span class="h-2 w-2 rounded-full bg-gray-300"></span> Offline — Not available for new deliveries
                    </span>
                @endif
            </div>
            <div class="lg:col-span-2">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Current Delivery</p>
                @if($currentDelivery)
                    <a href="{{ route('deliveries.show', $currentDelivery) }}" class="inline-flex items-center gap-2 text-sm font-mono font-semibold text-teal hover:text-teal-dark">
                        {{ $currentDelivery->tracking_number }}
                        <x-status-badge :status="$currentDelivery->status" />
                    </a>
                @else
                    <p class="text-sm text-gray-400">No active delivery right now</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Recent Deliveries --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100">
            <h3 class="text-base font-bold text-gray-900">Recent Deliveries</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100">
                <thead class="bg-gray-50/50">
                    <tr>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Tracking #</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Recipient</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($rider->deliveries->take(10) as $delivery)
                        <tr class="hover:bg-teal-light/40 transition">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <a href="{{ route('deliveries.show', $delivery) }}" class="text-sm font-semibold text-teal hover:text-teal-dark">
                                    {{ $delivery->tracking_number }}
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $delivery->recipient_name }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <x-status-badge :status="$delivery->status" />
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $delivery->created_at->format('M d, Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center">
                                    <div class="h-12 w-12 rounded-full bg-teal-light flex items-center justify-center mb-3">
                                        <svg class="h-6 w-6 text-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                                        </svg>
                                    </div>
                                    <p class="text-sm font-semibold text-gray-900">No deliveries found</p>
                                    <p class="mt-1 text-sm text-gray-500">This rider hasn't handled any deliveries yet.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
