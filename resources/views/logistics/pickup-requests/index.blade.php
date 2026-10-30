<x-app-layout>
    <div class="space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-[#1B1B1E]">
                    {{ $activeStatus === 'routing' ? 'Pickup Requests Needing Routing' : 'Pickup Requests' }}
                </h1>
                <p class="text-xs text-[#6E6E73] mt-0.5">
                    {{ $activeStatus === 'routing'
                        ? 'Route unassigned pickups to a logistics center before review'
                        : 'Review seller pickup requests and assign a rider to collect items from the seller' }}
                </p>
            </div>
            @if($isAdmin)
                <a href="{{ route('pickup-requests.index', ['status' => 'routing']) }}"
                   class="btn {{ $activeStatus === 'routing' ? 'btn-primary' : 'btn-outline' }}">
                    Needs Center Assignment
                    @if($needsRoutingCount > 0)
                        <span class="badge badge-pending">{{ $needsRoutingCount }}</span>
                    @endif
                </a>
            @endif
        </div>

        {{-- Filter Toolbar --}}
        <div class="card p-4">
            <form method="GET" action="{{ route('pickup-requests.index') }}" class="flex flex-col sm:flex-row sm:items-end gap-3">
                <div class="flex-1">
                    <label class="field-label">Search Pickup Requests</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by tracking number, sender, or recipient..." class="input">
                </div>
                <div class="sm:w-52">
                    <label class="field-label">Status</label>
                    <select name="status" class="select">
                        <option value="">All Statuses</option>
                        @if($isAdmin)
                            <option value="routing" @selected(request('status') === 'routing')>Needs center assignment</option>
                        @endif
                        @foreach(['pending', 'approved', 'rejected', 'completed'] as $s)
                            <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    @if(request('search') || request('status'))
                        <a href="{{ route('pickup-requests.index') }}" class="btn btn-ghost">Clear</a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Pickup Requests Table (No Horizontal Scroll) --}}
        <div class="card overflow-hidden">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tracking #</th>
                        <th>Seller</th>
                        <th>Logistics Hub &amp; Rider</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pickupRequests as $pickupRequest)
                        <tr>
                            <td>
                                <a href="{{ route('pickup-requests.show', $pickupRequest) }}" class="font-bold text-[#16697A] hover:underline">
                                    {{ $pickupRequest->delivery?->tracking_number ?? ('#' . $pickupRequest->id) }}
                                </a>
                            </td>
                            <td class="font-medium text-[#1B1B1E]">{{ $pickupRequest->delivery?->sender_name ?? '—' }}</td>
                            <td class="text-[#6E6E73]">
                                @if($pickupRequest->center)
                                    <div class="font-semibold text-[#1B1B1E]">{{ $pickupRequest->center->name }}</div>
                                @else
                                    <span class="badge badge-pending">Needs routing</span>
                                @endif
                                @if($pickupRequest->delivery?->rider)
                                    <div class="text-xs font-semibold text-[#16697A]">Rider: {{ $pickupRequest->delivery->rider->name }}</div>
                                @elseif($pickupRequest->status === 'approved')
                                    <div class="text-xs text-[#6E6E73]">Awaiting rider assignment</div>
                                @endif
                            </td>
                            <td>
                                <x-status-badge :status="$pickupRequest->status" />
                            </td>
                            <td class="text-right">
                                <a href="{{ route('pickup-requests.show', $pickupRequest) }}" class="btn btn-soft btn-xs">
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center">
                                <p class="text-sm font-bold text-[#1B1B1E]">No pickup requests found</p>
                                <p class="mt-1 text-xs text-[#6E6E73]">No pickup requests match the current filters.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if($pickupRequests->total() > 0)
                <div class="p-4 border-t border-[#E8E6E0] flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-[#6E6E73]">
                    <p>Showing <strong class="text-[#1B1B1E]">{{ $pickupRequests->firstItem() }}–{{ $pickupRequests->lastItem() }}</strong> of <strong class="text-[#1B1B1E]">{{ $pickupRequests->total() }}</strong> requests</p>
                    <p>Page {{ $pickupRequests->currentPage() }} of {{ $pickupRequests->lastPage() }}</p>
                </div>
            @endif
        </div>

        @include('admin.partials.pager', ['paginator' => $pickupRequests])
    </div>
</x-app-layout>
