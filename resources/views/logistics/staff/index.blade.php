<x-app-layout>
    <div class="space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-[#1B1B1E]">Staff</h1>
                <p class="text-xs text-[#6E6E73] mt-0.5">Add staff, assign their center, and manage account access.</p>
            </div>
            <a href="{{ route('staff.create') }}" class="btn btn-primary">
                <x-icon name="plus" class="w-4 h-4" />
                Add Staff
            </a>
        </div>

        {{-- Search + Filter Toolbar --}}
        <div class="card p-4">
            <form method="GET" action="{{ route('staff.index') }}" class="flex flex-col sm:flex-row sm:items-end gap-3">
                <div class="flex-1">
                    <label class="field-label">Search Staff</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name, email, or phone..." class="input">
                </div>
                <div class="sm:w-56">
                    <label class="field-label">Center</label>
                    <select name="center_id" class="select">
                        <option value="">All Centers</option>
                        @foreach($centers as $center)
                            <option value="{{ $center->id }}" @selected((int) request('center_id') === $center->id)>{{ $center->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    @if(request('search') || request('center_id'))
                        <a href="{{ route('staff.index') }}" class="btn btn-ghost">Clear</a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Staff Table (No Horizontal Scroll) --}}
        <div class="card overflow-hidden">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Center</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($staff as $member)
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="h-8 w-8 rounded-full bg-[#16697A] flex items-center justify-center text-white text-xs font-bold shrink-0">
                                        {{ strtoupper(substr($member->name, 0, 1)) }}
                                    </div>
                                    <a href="{{ route('staff.show', $member) }}" class="font-semibold text-[#1B1B1E] hover:text-[#16697A]">{{ $member->name }}</a>
                                </div>
                            </td>
                            <td class="text-[#6E6E73]">{{ $member->logisticsCenter?->name ?? '—' }}</td>
                            <td>
                                <x-status-badge :status="$member->status" />
                            </td>
                            <td class="text-right">
                                <a href="{{ route('staff.show', $member) }}" class="btn btn-soft btn-xs">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-12 text-center">
                                <p class="text-sm font-bold text-[#1B1B1E]">No staff found</p>
                                <p class="mt-1 text-xs text-[#6E6E73]">No staff accounts match the current filters.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if($staff->total() > 0)
                <div class="p-4 border-t border-[#E8E6E0] flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-[#6E6E73]">
                    <p>Showing <strong class="text-[#1B1B1E]">{{ $staff->firstItem() }}–{{ $staff->lastItem() }}</strong> of <strong class="text-[#1B1B1E]">{{ $staff->total() }}</strong> staff</p>
                    <p>Page {{ $staff->currentPage() }} of {{ $staff->lastPage() }}</p>
                </div>
            @endif
        </div>

        <div>{{ $staff->links() }}</div>
    </div>
</x-app-layout>
