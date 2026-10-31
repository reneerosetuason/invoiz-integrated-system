<x-app-layout>
    <div class="space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('centers.index') }}" class="btn btn-outline btn-sm" aria-label="Back to hubs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <span>Back</span>
                </a>
                <div>
                    <h1 class="text-2xl font-bold text-[#1B1B1E]">Logistics Hub Details</h1>
                    <p class="text-xs text-[#6E6E73] mt-0.5">{{ $center->name }}</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-status-badge :status="$center->is_active ? 'active' : 'inactive'" />
                <a href="{{ route('centers.edit', $center) }}" class="btn btn-outline">Edit</a>
                <form action="{{ route('centers.toggle', $center) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="btn {{ $center->is_active ? 'btn-danger' : 'btn-primary' }}">
                        {{ $center->is_active ? 'Deactivate' : 'Activate' }}
                    </button>
                </form>
            </div>
        </div>

        <div class="card p-6">
            <h2 class="text-xl font-bold text-[#1B1B1E]">{{ $center->name }}</h2>
            <p class="mt-1 text-xs text-[#6E6E73]">{{ $center->address }}, {{ $center->city }}{{ $center->province ? ', ' . $center->province : '' }}</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="card p-5">
                <p class="text-[11px] font-bold text-[#6E6E73] uppercase tracking-wider mb-1">Service Areas</p>
                <p class="text-2xl font-bold text-[#1B1B1E]">{{ $center->service_areas_count }}</p>
            </div>
            <div class="card p-5">
                <p class="text-[11px] font-bold text-[#6E6E73] uppercase tracking-wider mb-1">Riders</p>
                <p class="text-2xl font-bold text-[#1B1B1E]">{{ $center->riders_count }}</p>
            </div>
            <div class="card p-5">
                <p class="text-[11px] font-bold text-[#6E6E73] uppercase tracking-wider mb-1">Deliveries</p>
                <p class="text-2xl font-bold text-[#1B1B1E]">{{ $center->deliveries_count }}</p>
            </div>
        </div>

        <div class="card p-6">
            <h3 class="text-sm font-bold text-[#1B1B1E] uppercase tracking-wide mb-4">Logistics Hub Information</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                <div>
                    <p class="text-[11px] font-bold text-[#6E6E73] uppercase tracking-wider mb-1">Name</p>
                    <p class="text-sm font-medium text-[#1B1B1E]">{{ $center->name }}</p>
                </div>
                <div>
                    <p class="text-[11px] font-bold text-[#6E6E73] uppercase tracking-wider mb-1">City</p>
                    <p class="text-sm font-medium text-[#1B1B1E]">{{ $center->city }}</p>
                </div>
                <div>
                    <p class="text-[11px] font-bold text-[#6E6E73] uppercase tracking-wider mb-1">Province</p>
                    <p class="text-sm font-medium text-[#1B1B1E]">{{ $center->province ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-[11px] font-bold text-[#6E6E73] uppercase tracking-wider mb-1">Phone</p>
                    <p class="text-sm font-medium text-[#1B1B1E]">{{ $center->phone ?? '—' }}</p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
