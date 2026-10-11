<x-app-layout>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('service-areas.index') }}"
               class="p-2 bg-white border border-gray-200 rounded-xl text-gray-500 hover:text-teal-dark hover:border-teal transition shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Service Area Details</h1>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            @if($area->is_active)
                <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200">Active</span>
            @else
                <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600 ring-1 ring-inset ring-gray-200">Inactive</span>
            @endif
            <a href="{{ route('service-areas.edit', $area) }}" class="inline-flex items-center gap-2 bg-white border border-gray-200 text-gray-700 hover:text-teal hover:border-teal px-4 py-2 rounded-xl text-sm font-semibold transition">Edit</a>
            <form action="{{ route('service-areas.toggle', $area) }}" method="POST">
                @csrf
                <button type="submit" class="bg-white border border-gray-200 text-gray-700 hover:border-teal hover:text-teal px-4 py-2 rounded-xl text-sm font-semibold transition">
                    {{ $area->is_active ? 'Deactivate' : 'Activate' }}
                </button>
            </form>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-900">{{ $area->name }}</h2>
        @if($area->description)<p class="mt-1 text-sm text-gray-500">{{ $area->description }}</p>@endif
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Riders</p>
            <p class="text-3xl font-bold text-gray-900">{{ $area->riders_count }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Deliveries</p>
            <p class="text-3xl font-bold text-gray-900">{{ $area->deliveries_count }}</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-base font-bold text-gray-900 mb-5">Area Information</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div><p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Name</p><p class="text-sm font-medium text-gray-900">{{ $area->name }}</p></div>
            <div><p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Logistics Center</p><p class="text-sm font-medium text-gray-900">{{ $area->logisticsCenter?->name ?? '—' }}</p></div>
            <div><p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Description</p><p class="text-sm font-medium text-gray-900">{{ $area->description ?? '—' }}</p></div>
        </div>
    </div>
</x-app-layout>
