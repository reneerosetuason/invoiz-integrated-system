<x-app-layout>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('staff.index') }}"
               class="p-2 bg-white border border-gray-200 rounded-xl text-gray-500 hover:text-teal-dark hover:border-teal transition shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Staff Details</h1>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            @if($staff->status === 'active')
                <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200">Active</span>
            @else
                <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600 ring-1 ring-inset ring-gray-200">Inactive</span>
            @endif
            <a href="{{ route('staff.edit', $staff) }}" class="inline-flex items-center gap-2 bg-white border border-gray-200 text-gray-700 hover:text-teal hover:border-teal px-4 py-2 rounded-xl text-sm font-semibold transition">Edit</a>
            @if($staff->status === 'active')
                <form action="{{ route('staff.destroy', $staff) }}" method="POST" x-data x-on:submit.prevent="if (confirm('Deactivate {{ $staff->name }}?')) $el.submit()">
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
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-5">
            <div class="h-16 w-16 rounded-full bg-teal flex items-center justify-center text-2xl font-bold text-white flex-shrink-0">
                {{ strtoupper(substr($staff->name, 0, 1)) }}
            </div>
            <div>
                <h2 class="text-xl font-bold text-gray-900">{{ $staff->name }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $staff->email }} &middot; {{ $staff->phone }}</p>
                <span class="mt-3 inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-lg bg-teal-light text-teal-dark">{{ $staff->roleLabel() }}</span>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-base font-bold text-gray-900 mb-5">Account Information</h3>
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
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Logistics Center</p>
                <p class="text-sm font-medium text-gray-900">{{ $staff->logisticsCenter?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Role</p>
                <p class="text-sm font-medium text-gray-900">{{ $staff->roleLabel() }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Joined</p>
                <p class="text-sm font-medium text-gray-900">{{ $staff->created_at?->format('M d, Y') ?? '—' }}</p>
            </div>
        </div>
    </div>
</x-app-layout>
