<x-app-layout>
    <div class="space-y-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('hub-transfers.index') }}" class="btn btn-outline btn-sm" aria-label="Back to hub transfers">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span>Back</span>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-[#1B1B1E]">Create Hub Transfer</h1>
                <p class="text-xs text-[#6E6E73] mt-0.5">Choose the responsible company, route, truck, driver, and parcels.</p>
            </div>
        </div>

        @if($errors->any())
            <div class="card p-4 bg-red-50 border-red-200 text-red-800 text-xs">
                {{ $errors->first() }}
            </div>
        @endif

        @if($global && !$company)
            <div class="card p-6">
                <form method="GET" class="flex flex-col sm:flex-row sm:items-end gap-3">
                    <div class="flex-1">
                        <label class="field-label">Company</label>
                        <select name="logistics_company_id" required class="select">
                            <option value="">Select a company</option>
                            @foreach($companies as $item)
                                <option value="{{ $item->id }}">{{ $item->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Load Company Hubs</button>
                </form>
            </div>
        @else
            <form method="POST" action="{{ route('hub-transfers.store') }}" class="space-y-4">
                @csrf
                @if($global)
                    <input type="hidden" name="logistics_company_id" value="{{ $company->id }}">
                @endif

                <div class="card p-6">
                    <h2 class="text-sm font-bold text-[#1B1B1E] uppercase tracking-wide mb-4">Route &amp; Fleet Assignment</h2>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="field-label">Origin Logistics Hub <span class="text-red-500">*</span></label>
                            <select name="origin_center_id" required class="select">
                                @foreach($centers as $center)
                                    @if(!$originCenterId || $originCenterId == $center->id)
                                        <option value="{{ $center->id }}" @selected(old('origin_center_id', auth()->user()->center_id) == $center->id)>{{ $center->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="field-label">Destination Logistics Hub <span class="text-red-500">*</span></label>
                            <select name="destination_center_id" required class="select">
                                <option value="">Select destination</option>
                                @foreach($centers as $center)
                                    @if(!$originCenterId || $originCenterId != $center->id)
                                        <option value="{{ $center->id }}" @selected(old('destination_center_id') == $center->id)>{{ $center->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="field-label">Available Truck <span class="text-red-500">*</span></label>
                            <select name="truck_id" required class="select">
                                <option value="">Select truck</option>
                                @foreach($trucks as $truck)
                                    <option value="{{ $truck->id }}">{{ $truck->plate_number }} — {{ $truck->name ?: $truck->vehicle_type }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="field-label">Hub Transport Driver <span class="text-red-500">*</span></label>
                            <select name="driver_id" required class="select">
                                <option value="">Select driver</option>
                                @foreach($drivers as $driver)
                                    <option value="{{ $driver->id }}">{{ $driver->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="field-label">Planned Dispatch</label>
                            <input type="datetime-local" name="planned_dispatch_at" value="{{ old('planned_dispatch_at') }}" class="input">
                        </div>
                        <div>
                            <label class="field-label">Remarks</label>
                            <input type="text" name="notes" value="{{ old('notes') }}" placeholder="Optional transfer notes..." class="input">
                        </div>
                    </div>
                </div>

                <div class="card p-6">
                    <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-bold text-[#1B1B1E] uppercase tracking-wide">Parcel manifest</h2>
                            <p class="text-xs text-[#6E6E73] mt-0.5">Eligible parcels currently received, scanned, or sorted at the origin hub.</p>
                        </div>
                        <div class="sm:w-64">
                            <input type="search" id="parcel-search" placeholder="Filter by tracking #..." class="input">
                        </div>
                    </div>

                    <div class="card overflow-hidden">
                        <div class="max-h-80 overflow-y-auto">
                            <table class="table">
                                <thead class="sticky top-0 bg-[#F0EEE9] z-10">
                                    <tr>
                                        <th class="w-12 text-center">Select</th>
                                        <th>Tracking #</th>
                                        <th>Status</th>
                                        <th>Current Hub</th>
                                        <th>Destination</th>
                                    </tr>
                                </thead>
                                <tbody id="parcel-rows">
                                    @forelse($parcels as $parcel)
                                        <tr data-tracking="{{ strtolower($parcel->tracking_number) }}">
                                            <td class="text-center">
                                                <input type="checkbox" name="parcels[]" value="{{ $parcel->id }}" @checked(in_array($parcel->id, old('parcels', []))) class="rounded border-[#E8E6E0] text-[#16697A] focus:ring-[#16697A]">
                                            </td>
                                            <td class="font-mono font-bold text-[#16697A]">{{ $parcel->tracking_number }}</td>
                                            <td>
                                                <span class="badge badge-inactive">{{ str($parcel->parcel_status)->replace('_',' ')->title() }}</span>
                                            </td>
                                            <td class="text-xs text-[#1B1B1E]">{{ $parcel->logisticsCenter?->name ?? '—' }}</td>
                                            <td class="text-xs text-[#6E6E73]">{{ $parcel->destinationCenter?->name ?? 'Not routed' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="py-8 text-center text-xs text-[#6E6E73]">
                                                No eligible parcels at the selected company hub.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-3 pt-4 border-t border-[#E8E6E0]">
                        <a href="{{ route('hub-transfers.index') }}" class="btn btn-outline">Cancel</a>
                        <button type="submit" class="btn btn-primary">Create Transfer</button>
                    </div>
                </div>
            </form>
        @endif
    </div>

    <script>
        document.getElementById('parcel-search')?.addEventListener('input', e => {
            const q = e.target.value.toLowerCase();
            document.querySelectorAll('#parcel-rows tr[data-tracking]').forEach(row => {
                row.hidden = !row.dataset.tracking.includes(q);
            });
        });
    </script>
</x-app-layout>
