<x-app-layout>
    <div class="space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-[#1B1B1E]">Service Areas</h1>
                <p class="text-xs text-[#6E6E73] mt-0.5">Define where each logistics hub can collect and deliver parcels.</p>
            </div>
            <a href="{{ route('service-areas.create') }}" class="btn btn-primary">
                <x-icon name="plus" class="w-4 h-4" />
                Add Area
            </a>
        </div>

        <div class="card p-4">
            <form method="GET" action="{{ route('service-areas.index') }}" class="flex flex-col sm:flex-row sm:items-end gap-3">
                <div class="flex-1">
                    <label class="field-label">Search Service Areas</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by area name or description..." class="input">
                </div>
                <div class="sm:w-52">
                    <label class="field-label">Logistics Hub</label>
                    <select name="center_id" class="select">
                        <option value="">All Logistics Hubs</option>
                        @foreach($centers as $center)
                            <option value="{{ $center->id }}" @selected((int) request('center_id') === $center->id)>{{ $center->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:w-44">
                    <label class="field-label">Status</label>
                    <select name="status" title="Filter by status" class="select">
                        <option value="">All statuses</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    @if(request('search') || request('center_id') || request('status'))
                        <a href="{{ route('service-areas.index') }}" class="btn btn-ghost">Clear</a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Service Areas Table (No Horizontal Scroll) --}}
        <div class="card overflow-hidden">
            <table class="table">
                <thead>
                    <tr>
                        <th>Area</th>
                        <th>Logistics Hub</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($areas as $area)
                        <tr>
                            <td>
                                <a href="{{ route('service-areas.show', $area) }}" class="font-bold text-[#16697A] hover:underline">{{ $area->name }}</a>
                                @if($area->description)<p class="text-[11px] text-[#6E6E73] mt-0.5 max-w-xs truncate">{{ $area->description }}</p>@endif
                            </td>
                            <td class="text-[#6E6E73]">{{ $area->logisticsCenter?->name ?? '—' }}</td>
                            <td>
                                <x-status-badge :status="$area->is_active ? 'active' : 'inactive'" />
                            </td>
                            <td class="text-right">
                                <a href="{{ route('service-areas.show', $area) }}" class="btn btn-soft btn-xs">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-12 text-center">
                                <p class="text-sm font-bold text-[#1B1B1E]">No service areas found</p>
                                <p class="mt-1 text-xs text-[#6E6E73]">No service areas match the current filters.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if($areas->total() > 0)
                <div class="p-4 border-t border-[#E8E6E0] flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-[#6E6E73]">
                    <p>Showing <strong class="text-[#1B1B1E]">{{ $areas->firstItem() }}–{{ $areas->lastItem() }}</strong> of <strong class="text-[#1B1B1E]">{{ $areas->total() }}</strong> areas</p>
                    <p>Page {{ $areas->currentPage() }} of {{ $areas->lastPage() }}</p>
                </div>
            @endif
        </div>

        <div>{{ $areas->links() }}</div>
    </div>
</x-app-layout>
