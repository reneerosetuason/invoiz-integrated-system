<x-app-layout>
    <div class="space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-[#1B1B1E]">Rider Applications</h1>
                <p class="text-xs text-[#6E6E73] mt-0.5">Check applicant details and documents, then approve or reject the application.</p>
            </div>
        </div>

        {{-- Filter Toolbar --}}
        <div class="card p-4">
            <form method="GET" action="{{ route('rider-applications.index') }}" class="flex flex-col sm:flex-row sm:items-end gap-3">
                <div class="flex-1">
                    <label class="field-label">Search Applications</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name, email, or phone..." class="input">
                </div>
                <div class="sm:w-48">
                    <label class="field-label">Status</label>
                    <select name="status" class="select">
                        <option value="">All Statuses</option>
                        @foreach(['pending', 'approved', 'rejected'] as $s)
                            <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    @if(request('search') || request('status'))
                        <a href="{{ route('rider-applications.index') }}" class="btn btn-ghost">Clear</a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Applications Table (No Horizontal Scroll) --}}
        <div class="card overflow-hidden">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Vehicle</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($applications as $application)
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="h-8 w-8 rounded-full bg-[#16697A] flex items-center justify-center text-white text-xs font-bold shrink-0">
                                        {{ strtoupper(substr($application->name, 0, 1)) }}
                                    </div>
                                    <a href="{{ route('rider-applications.show', $application) }}" class="font-semibold text-[#1B1B1E] hover:text-[#16697A]">{{ $application->name }}</a>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-confirmed">{{ ucfirst($application->vehicle_type) }}</span>
                            </td>
                            <td>
                                <x-status-badge :status="$application->status" />
                            </td>
                            <td class="text-xs text-[#6E6E73]">{{ $application->created_at?->format('M d, Y') ?? '—' }}</td>
                            <td class="text-right">
                                <a href="{{ route('rider-applications.show', $application) }}" class="btn btn-soft btn-xs">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center">
                                <p class="text-sm font-bold text-[#1B1B1E]">No applications found</p>
                                <p class="mt-1 text-xs text-[#6E6E73]">No rider applications match the current filters.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if($applications->total() > 0)
                <div class="p-4 border-t border-[#E8E6E0] flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-[#6E6E73]">
                    <p>Showing <strong class="text-[#1B1B1E]">{{ $applications->firstItem() }}–{{ $applications->lastItem() }}</strong> of <strong class="text-[#1B1B1E]">{{ $applications->total() }}</strong> applications</p>
                    <p>Page {{ $applications->currentPage() }} of {{ $applications->lastPage() }}</p>
                </div>
            @endif
        </div>

        <div>{{ $applications->links() }}</div>
    </div>
</x-app-layout>
