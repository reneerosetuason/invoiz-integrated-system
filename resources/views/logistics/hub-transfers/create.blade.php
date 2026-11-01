<x-app-layout>
    <div class="space-y-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('hub-transfers.index') }}" class="btn btn-outline btn-sm" aria-label="Back to hub transfers">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span>Back</span>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-[#1B1B1E]">Create Hub Transfer</h1>
                <p class="text-xs text-[#6E6E73] mt-0.5">Choose the responsible company, route, truck, driver, and parcels grouped by destination.</p>
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
            <form id="transfer-form" method="POST" action="{{ route('hub-transfers.store') }}" class="space-y-4">
                @csrf
                @if($global)
                    <input type="hidden" name="logistics_company_id" value="{{ $company->id }}">
                @endif

                <div class="card p-6">
                    <h2 class="text-sm font-bold text-[#1B1B1E] uppercase tracking-wide mb-4">Route &amp; Fleet Assignment</h2>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="field-label">Origin Logistics Hub <span class="text-red-500">*</span></label>
                            <select name="origin_center_id" id="origin-center-select" required class="select">
                                @foreach($centers as $center)
                                    @if(!$originCenterId || $originCenterId == $center->id)
                                        <option value="{{ $center->id }}" @selected(old('origin_center_id', $originCenterId ?? auth()->user()->center_id) == $center->id)>{{ $center->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="field-label">Destination Logistics Hub <span class="text-red-500">*</span></label>
                            <select name="destination_center_id" id="destination-center-select" required class="select">
                                <option value="">Select destination</option>
                                @foreach($centers as $center)
                                    @if(!$originCenterId || $originCenterId != $center->id)
                                        <option value="{{ $center->id }}" @selected(old('destination_center_id') == $center->id)>{{ $center->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                            <p class="text-[11px] text-[#6E6E73] mt-1">Parcels selected below must all share this destination hub.</p>
                        </div>
                        <div>
                            <label class="field-label">Available Truck (4-Wheel) <span class="text-red-500">*</span></label>
                            <select name="truck_id" id="truck-select" required class="select">
                                <option value="">Select truck</option>
                                @foreach($trucks as $truck)
                                    <option value="{{ $truck->id }}" data-capacity="{{ $truck->parcel_capacity }}" data-kg="{{ $truck->capacity_kg }}" @selected(old('truck_id') == $truck->id)>
                                        {{ $truck->plate_number }} — {{ $truck->model ?: $truck->name ?: $truck->vehicle_type }} (Max: {{ $truck->parcel_capacity }} pcs / {{ number_format($truck->capacity_kg, 1) }} kg)
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="field-label">Hub Transport Driver <span class="text-red-500">*</span></label>
                            <select name="driver_id" id="driver-select" required class="select">
                                <option value="">Select driver</option>
                                @foreach($drivers as $driver)
                                    <option value="{{ $driver->id }}" @selected(old('driver_id') == $driver->id)>{{ $driver->name }} ({{ $driver->phone ?: $driver->email }})</option>
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

                {{-- Live Selection & Confirmation Summary Banner --}}
                <div id="selection-summary-card" class="card p-4 bg-[#E6F4F1] border-2 border-[#16697A]/30 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sticky top-4 z-20 shadow-md">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-[#16697A] text-white flex items-center justify-center font-bold text-sm shrink-0">
                            <span id="selected-count-badge">0</span>
                        </div>
                        <div>
                            <div class="text-xs font-bold text-[#0E4A57] uppercase tracking-wide">Selected Transfer Manifest</div>
                            <div class="text-sm font-extrabold text-[#1B1B1E]">
                                <span id="selected-count-text">0 parcels selected</span>
                                <span class="text-[#6E6E73] font-normal">for</span>
                                <span id="selected-dest-text" class="text-[#16697A] font-bold">No destination selected</span>
                            </div>
                            <div class="text-[11px] text-[#6E6E73] mt-0.5">
                                Total Manifest Weight: <span id="selected-weight-text" class="font-bold text-[#1B1B1E]">0.00 kg</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" id="clear-selection-btn" class="btn btn-ghost btn-xs text-[#6E6E73] hover:text-[#1B1B1E]">Clear Selection</button>
                        <button type="button" id="open-review-modal-btn" class="btn btn-primary btn-sm" disabled>Review &amp; Create Transfer</button>
                    </div>
                </div>

                {{-- Eligible Parcels Grouped by Destination --}}
                <div class="card p-6 space-y-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-bold text-[#1B1B1E] uppercase tracking-wide">Eligible Parcels Grouped by Destination Hub</h2>
                            <p class="text-xs text-[#6E6E73] mt-0.5">Parcels physically received at the origin hub, filtered for transfer and separated by destination.</p>
                        </div>
                        <div class="sm:w-64">
                            <input type="search" id="parcel-search" placeholder="Filter by tracking #..." class="input text-xs">
                        </div>
                    </div>

                    @if($parcelsByDestination->isEmpty())
                        <div class="py-12 text-center text-xs text-[#6E6E73]">
                            <svg class="w-10 h-10 mx-auto text-gray-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                            </svg>
                            No eligible received parcels at the selected origin hub ready for inter-hub transfer.
                        </div>
                    @else
                        @foreach($parcelsByDestination as $destCenterId => $groupParcels)
                            @php
                                $destHub = $destinationHubs->get($destCenterId);
                                $destName = $destHub?->name ?? ('Hub #' . $destCenterId);
                            @endphp
                            <div class="destination-group card border border-[#E8E6E0] overflow-hidden" data-dest-id="{{ $destCenterId }}" data-dest-name="{{ $destName }}">
                                {{-- Group Header --}}
                                <div class="bg-[#F4F3EF] px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-[#E8E6E0]">
                                    <div class="flex items-center gap-3">
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="checkbox" class="group-select-all rounded border-[#E8E6E0] text-[#16697A] focus:ring-[#16697A] w-4 h-4" data-dest-id="{{ $destCenterId }}">
                                            <span class="text-xs font-bold uppercase tracking-wider text-[#16697A]">Select All ({{ $groupParcels->count() }})</span>
                                        </label>
                                        <span class="text-sm font-bold text-[#1B1B1E]">To: {{ $destName }}</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-[#16697A]/10 text-[#16697A]">
                                            {{ $groupParcels->count() }} Eligible {{ str('Parcel')->plural($groupParcels->count()) }}
                                        </span>
                                        <span class="text-[11px] text-[#6E6E73]">
                                            Total: {{ number_format($groupParcels->sum('weight'), 2) }} kg
                                        </span>
                                    </div>
                                </div>

                                {{-- Group Table --}}
                                <div class="max-h-72 overflow-y-auto">
                                    <table class="table">
                                        <thead class="sticky top-0 bg-[#F0EEE9] z-10 text-[11px]">
                                            <tr>
                                                <th class="w-12 text-center">Select</th>
                                                <th>Tracking #</th>
                                                <th>Status</th>
                                                <th>Weight</th>
                                                <th>Current Custody Hub</th>
                                                <th>Recipient / Destination</th>
                                            </tr>
                                        </thead>
                                        <tbody class="parcel-group-rows">
                                            @foreach($groupParcels as $parcel)
                                                <tr data-tracking="{{ strtolower($parcel->tracking_number) }}" class="parcel-row">
                                                    <td class="text-center">
                                                        <input type="checkbox" name="parcels[]" value="{{ $parcel->id }}"
                                                            class="parcel-checkbox rounded border-[#E8E6E0] text-[#16697A] focus:ring-[#16697A]"
                                                            data-dest-id="{{ $destCenterId }}"
                                                            data-dest-name="{{ $destName }}"
                                                            data-weight="{{ $parcel->weight ?? 0 }}"
                                                            data-tracking="{{ $parcel->tracking_number }}"
                                                            @checked(in_array($parcel->id, old('parcels', [])))>
                                                    </td>
                                                    <td class="font-mono font-bold text-[#16697A] text-xs">
                                                        {{ $parcel->tracking_number }}
                                                    </td>
                                                    <td>
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-gray-100 text-gray-800">
                                                            {{ str($parcel->parcel_status)->replace('_',' ')->title() }}
                                                        </span>
                                                    </td>
                                                    <td class="text-xs font-semibold text-[#1B1B1E]">
                                                        {{ $parcel->weight !== null ? number_format($parcel->weight, 2) . ' kg' : 'Unweighed' }}
                                                    </td>
                                                    <td class="text-xs text-[#1B1B1E]">
                                                        {{ $parcel->logisticsCenter?->name ?? '—' }}
                                                    </td>
                                                    <td class="text-xs text-[#6E6E73]">
                                                        <div class="font-medium text-[#1B1B1E]">{{ $destName }}</div>
                                                        <div class="text-[10px]">{{ $parcel->recipient_city ?: $parcel->recipient_address ?: '—' }}</div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>

                {{-- Manifest Review & Confirmation Modal --}}
                <dialog id="manifest-review-modal" class="modal p-6 rounded-xl shadow-2xl max-w-2xl w-full bg-white text-left">
                    <div class="flex items-center justify-between pb-3 border-b border-[#E8E6E0]">
                        <h3 class="text-base font-bold text-[#1B1B1E]">Review &amp; Confirm Hub Transfer Manifest</h3>
                        <button type="button" onclick="document.getElementById('manifest-review-modal').close()" class="btn btn-ghost btn-xs">✕</button>
                    </div>

                    <div class="mt-4 space-y-4">
                        <div class="grid grid-cols-2 gap-3 text-xs bg-[#F4F3EF] p-3 rounded-lg">
                            <div>
                                <span class="text-[#6E6E73] block">Origin Hub:</span>
                                <span id="modal-origin-hub" class="font-bold text-[#1B1B1E]">—</span>
                            </div>
                            <div>
                                <span class="text-[#6E6E73] block">Destination Hub:</span>
                                <span id="modal-dest-hub" class="font-bold text-[#16697A]">—</span>
                            </div>
                            <div>
                                <span class="text-[#6E6E73] block">Assigned Truck:</span>
                                <span id="modal-truck" class="font-semibold text-[#1B1B1E]">—</span>
                            </div>
                            <div>
                                <span class="text-[#6E6E73] block">Assigned Driver:</span>
                                <span id="modal-driver" class="font-semibold text-[#1B1B1E]">—</span>
                            </div>
                            <div>
                                <span class="text-[#6E6E73] block">Parcels in Manifest:</span>
                                <span id="modal-parcel-count" class="font-extrabold text-[#16697A]">0 pcs</span>
                            </div>
                            <div>
                                <span class="text-[#6E6E73] block">Total Weight:</span>
                                <span id="modal-total-weight" class="font-extrabold text-[#1B1B1E]">0.00 kg</span>
                            </div>
                        </div>

                        <div>
                            <div class="text-xs font-bold text-[#1B1B1E] uppercase tracking-wide mb-1">Manifest Parcels</div>
                            <div class="max-h-48 overflow-y-auto border border-[#E8E6E0] rounded-lg">
                                <ul id="modal-parcel-list" class="divide-y divide-[#E8E6E0] text-xs font-mono p-2">
                                </ul>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-3 border-t border-[#E8E6E0]">
                            <button type="button" onclick="document.getElementById('manifest-review-modal').close()" class="btn btn-outline btn-sm">Edit Selection</button>
                            <button type="submit" class="btn btn-primary btn-sm">Confirm &amp; Create Transfer</button>
                        </div>
                    </div>
                </dialog>
            </form>
        @endif
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const destSelect = document.getElementById('destination-center-select');
            const originSelect = document.getElementById('origin-center-select');
            const truckSelect = document.getElementById('truck-select');
            const driverSelect = document.getElementById('driver-select');
            const groupSelectAlls = document.querySelectorAll('.group-select-all');
            const parcelCheckboxes = document.querySelectorAll('.parcel-checkbox');
            const summaryBadge = document.getElementById('selected-count-badge');
            const summaryText = document.getElementById('selected-count-text');
            const summaryDest = document.getElementById('selected-dest-text');
            const summaryWeight = document.getElementById('selected-weight-text');
            const openReviewBtn = document.getElementById('open-review-modal-btn');
            const clearSelectionBtn = document.getElementById('clear-selection-btn');
            const reviewModal = document.getElementById('manifest-review-modal');

            let currentActiveDestId = null;

            function updateSummary() {
                const checkedBoxes = Array.from(parcelCheckboxes).filter(cb => cb.checked);
                const count = checkedBoxes.length;
                summaryBadge.textContent = count;
                summaryText.textContent = `${count} parcel${count === 1 ? '' : 's'} selected`;

                let totalWeight = 0;
                let activeDestName = 'No destination selected';
                let activeDestId = null;

                if (count > 0) {
                    activeDestId = checkedBoxes[0].dataset.destId;
                    activeDestName = checkedBoxes[0].dataset.destName;
                    checkedBoxes.forEach(cb => {
                        totalWeight += parseFloat(cb.dataset.weight || 0);
                    });
                    summaryDest.textContent = activeDestName;
                    currentActiveDestId = activeDestId;
                    if (destSelect) {
                        destSelect.value = activeDestId;
                    }
                } else {
                    summaryDest.textContent = 'No destination selected';
                    currentActiveDestId = null;
                }

                summaryWeight.textContent = `${totalWeight.toFixed(2)} kg`;
                openReviewBtn.disabled = count === 0;

                // Sync group select all checkboxes
                groupSelectAlls.forEach(gsa => {
                    const gDestId = gsa.dataset.destId;
                    const groupBoxes = Array.from(document.querySelectorAll(`.parcel-checkbox[data-dest-id="${gDestId}"]`));
                    if (groupBoxes.length > 0) {
                        const allChecked = groupBoxes.every(cb => cb.checked);
                        gsa.checked = allChecked;
                    }
                });
            }

            // Group Select All behavior
            groupSelectAlls.forEach(gsa => {
                gsa.addEventListener('change', e => {
                    const destId = gsa.dataset.destId;
                    const isChecked = gsa.checked;

                    if (isChecked) {
                        // Uncheck parcels from any other destination
                        parcelCheckboxes.forEach(cb => {
                            if (cb.dataset.destId !== destId) {
                                cb.checked = false;
                            }
                        });
                        groupSelectAlls.forEach(otherGsa => {
                            if (otherGsa.dataset.destId !== destId) {
                                otherGsa.checked = false;
                            }
                        });

                        // Check all parcels in this group
                        document.querySelectorAll(`.parcel-checkbox[data-dest-id="${destId}"]`).forEach(cb => {
                            cb.checked = true;
                        });
                    } else {
                        // Uncheck all in this group
                        document.querySelectorAll(`.parcel-checkbox[data-dest-id="${destId}"]`).forEach(cb => {
                            cb.checked = false;
                        });
                    }

                    updateSummary();
                });
            });

            // Individual checkbox behavior
            parcelCheckboxes.forEach(cb => {
                cb.addEventListener('change', () => {
                    if (cb.checked) {
                        const destId = cb.dataset.destId;
                        // If selecting a parcel from a different destination than currently active, switch group
                        if (currentActiveDestId && currentActiveDestId !== destId) {
                            parcelCheckboxes.forEach(otherCb => {
                                if (otherCb.dataset.destId !== destId) {
                                    otherCb.checked = false;
                                }
                            });
                            groupSelectAlls.forEach(gsa => {
                                if (gsa.dataset.destId !== destId) {
                                    gsa.checked = false;
                                }
                            });
                        }
                    }
                    updateSummary();
                });
            });

            // Clear Selection button
            clearSelectionBtn?.addEventListener('click', () => {
                parcelCheckboxes.forEach(cb => cb.checked = false);
                groupSelectAlls.forEach(gsa => gsa.checked = false);
                if (destSelect) destSelect.value = '';
                updateSummary();
            });

            // Review Modal populate
            openReviewBtn?.addEventListener('click', () => {
                const checkedBoxes = Array.from(parcelCheckboxes).filter(cb => cb.checked);
                if (checkedBoxes.length === 0) return;

                const originText = originSelect?.options[originSelect.selectedIndex]?.text ?? '—';
                const destText = destSelect?.options[destSelect.selectedIndex]?.text ?? checkedBoxes[0].dataset.destName ?? '—';
                const truckText = truckSelect?.options[truckSelect.selectedIndex]?.text ?? 'Not selected';
                const driverText = driverSelect?.options[driverSelect.selectedIndex]?.text ?? 'Not selected';

                let totalWeight = 0;
                const parcelList = document.getElementById('modal-parcel-list');
                if (parcelList) {
                    parcelList.innerHTML = '';
                    checkedBoxes.forEach(cb => {
                        const wt = parseFloat(cb.dataset.weight || 0);
                        totalWeight += wt;
                        const li = document.createElement('li');
                        li.className = 'py-1 flex justify-between';
                        li.innerHTML = `<span>${cb.dataset.tracking}</span><span class="text-[#6E6E73]">${wt.toFixed(2)} kg</span>`;
                        parcelList.appendChild(li);
                    });
                }

                document.getElementById('modal-origin-hub').textContent = originText;
                document.getElementById('modal-dest-hub').textContent = destText;
                document.getElementById('modal-truck').textContent = truckText;
                document.getElementById('modal-driver').textContent = driverText;
                document.getElementById('modal-parcel-count').textContent = `${checkedBoxes.length} pcs`;
                document.getElementById('modal-total-weight').textContent = `${totalWeight.toFixed(2)} kg`;

                reviewModal.showModal();
            });

            // Tracking search filter
            document.getElementById('parcel-search')?.addEventListener('input', e => {
                const q = e.target.value.toLowerCase();
                document.querySelectorAll('.parcel-row').forEach(row => {
                    const tracking = row.dataset.tracking || '';
                    row.hidden = !tracking.includes(q);
                });
            });

            // Initialize on page load (e.g. if old inputs exist)
            updateSummary();
        });
    </script>
</x-app-layout>
