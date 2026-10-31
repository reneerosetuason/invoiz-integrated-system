<x-app-layout>
    <div class="space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-[#1B1B1E]">Logistics Hubs</h1>
                <p class="text-xs text-[#6E6E73] mt-0.5">Review each hub, its coverage, and its assigned riders and deliveries.</p>
            </div>
            <a href="{{ route('centers.create') }}" class="btn btn-primary">
                <x-icon name="plus" class="w-4 h-4" />
                Add Hub
            </a>
        </div>

        <div class="card p-4">
            <form method="GET" action="{{ route('centers.index') }}" class="flex flex-col sm:flex-row sm:items-end gap-3">
                <div class="flex-1">
                    <label class="field-label">Search Hubs</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name, city, province, or address..." class="input">
                </div>
                <div class="sm:w-48">
                    <label class="field-label">Status</label>
                    <select name="status" title="Filter by status" class="select">
                        <option value="">All statuses</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    @if(request('search') || request('status'))
                        <a href="{{ route('centers.index') }}" class="btn btn-ghost">Clear</a>
                    @endif
                </div>
            </form>
        </div>

        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Hub</th>
                            <th>City</th>
                            <th>Province</th>
                            <th>Phone</th>
                            <th>Areas</th>
                            <th>Riders</th>
                            <th>Deliveries</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($centers as $center)
                            <tr>
                                <td>
                                    <a href="{{ route('centers.show', $center) }}" class="font-bold text-[#16697A] hover:underline">{{ $center->name }}</a>
                                    <p class="text-[11px] text-[#6E6E73] mt-0.5">{{ $center->address }}</p>
                                </td>
                                <td>{{ $center->city }}</td>
                                <td>{{ $center->province ?? '—' }}</td>
                                <td>{{ $center->phone ?? '—' }}</td>
                                <td>{{ $center->service_areas_count }}</td>
                                <td>{{ $center->riders_count }}</td>
                                <td>{{ $center->deliveries_count }}</td>
                                <td>
                                    <x-status-badge :status="$center->is_active ? 'active' : 'inactive'" />
                                </td>
                                <td class="text-right">
                                    <a href="{{ route('centers.show', $center) }}" class="btn btn-soft btn-xs">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="py-12 text-center">
                                    <p class="text-sm font-bold text-[#1B1B1E]">No Logistics Hubs found</p>
                                    <p class="mt-1 text-xs text-[#6E6E73]">No logistics hubs match the current filters.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($centers->total() > 0)
                <div class="p-4 border-t border-[#E8E6E0] flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-[#6E6E73]">
                    <p>Showing <strong class="text-[#1B1B1E]">{{ $centers->firstItem() }}–{{ $centers->lastItem() }}</strong> of <strong class="text-[#1B1B1E]">{{ $centers->total() }}</strong> hubs</p>
                    <p>Page {{ $centers->currentPage() }} of {{ $centers->lastPage() }}</p>
                </div>
            @endif
        </div>

        <div>{{ $centers->links() }}</div>
    </div>
</x-app-layout>
