<x-app-layout>
    <div class="space-y-4">
        <div>
            <p class="text-xs font-bold uppercase tracking-wider text-[#16697A]">Fleet &amp; Transport Linehaul</p>
            <h1 class="text-2xl font-bold text-[#1B1B1E] mt-0.5">Fleet Management</h1>
            <p class="text-xs text-[#6E6E73] mt-0.5">Trucks and Hub Transport Drivers are company-owned and separate from last-mile Delivery Riders.</p>
        </div>

        @if(session('success'))
            <div class="card p-4 bg-green-50 border-green-200 text-green-800 text-xs">
                {{ session('success') }}
            </div>
        @endif

        <div class="card p-4">
            <form method="GET" class="flex flex-col sm:flex-row sm:items-end gap-3">
                <div class="flex-1">
                    <label class="field-label">Filter by Company</label>
                    <select name="logistics_company_id" class="select">
                        <option value="">All companies</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}" @selected($companyId === $company->id)>{{ $company->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Filter</button>
            </form>
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
            {{-- Trucks Section --}}
            <section class="card p-5 space-y-4">
                <div>
                    <h2 class="text-sm font-bold text-[#1B1B1E] uppercase tracking-wide">Four-Wheel Trucks</h2>
                    <p class="text-xs text-[#6E6E73] mt-0.5">Inter-hub fleet vehicles managed by your logistics company.</p>
                </div>

                <form method="POST" action="{{ route('fleet.trucks.store') }}" class="card p-4 bg-[#FAF9F6] border-[#E8E6E0] space-y-3">
                    @csrf
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="field-label">Company <span class="text-red-500">*</span></label>
                            <select name="logistics_company_id" required class="select">
                                <option value="">Select company</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}" @selected($companyId === $company->id)>{{ $company->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="field-label">Plate Number <span class="text-red-500">*</span></label>
                            <input name="plate_number" required placeholder="e.g. ABC 1234" class="input">
                        </div>
                        <div>
                            <label class="field-label">Vehicle Name</label>
                            <input name="name" placeholder="e.g. Van-01" class="input">
                        </div>
                        <div>
                            <label class="field-label">Vehicle Type <span class="text-red-500">*</span></label>
                            <input name="vehicle_type" required value="truck" placeholder="Type" class="input">
                        </div>
                        <div>
                            <label class="field-label">Brand / Model</label>
                            <input name="model" placeholder="e.g. Isuzu Elf" class="input">
                        </div>
                        <div>
                            <label class="field-label">Parcel Capacity <span class="text-red-500">*</span></label>
                            <input name="parcel_capacity" type="number" min="1" required placeholder="e.g. 150" class="input">
                        </div>
                        <div>
                            <label class="field-label">Weight Capacity (kg) <span class="text-red-500">*</span></label>
                            <input name="capacity_kg" type="number" step="0.01" min="0.01" required placeholder="e.g. 2000" class="input">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="field-label">Remarks</label>
                            <input name="remarks" placeholder="Optional notes..." class="input">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-full">Register Truck</button>
                </form>

                <div class="card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Company / Truck</th>
                                    <th>Status</th>
                                    <th class="text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($trucks as $truck)
                                    <tr>
                                        <td>
                                            <div class="font-bold text-[#1B1B1E]">{{ $truck->plate_number }}</div>
                                            <div class="text-[11px] text-[#6E6E73]">{{ $truck->company?->name }} · {{ $truck->name ?: $truck->model }}</div>
                                        </td>
                                        <td>
                                            <span class="badge {{ $truck->status === 'available' ? 'badge-delivered' : 'badge-inactive' }}">{{ str($truck->status)->title() }}</span>
                                        </td>
                                        <td class="text-right">
                                            <form method="POST" action="{{ route('fleet.trucks.update', $truck) }}" class="inline-flex items-center justify-end gap-1">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="plate_number" value="{{ $truck->plate_number }}">
                                                <input type="hidden" name="name" value="{{ $truck->name }}">
                                                <input type="hidden" name="vehicle_type" value="{{ $truck->vehicle_type }}">
                                                <input type="hidden" name="model" value="{{ $truck->model }}">
                                                <input type="hidden" name="parcel_capacity" value="{{ $truck->parcel_capacity }}">
                                                <input type="hidden" name="capacity_kg" value="{{ $truck->capacity_kg }}">
                                                <input type="hidden" name="remarks" value="{{ $truck->remarks }}">
                                                <select name="status" class="select !py-1 !px-2 !text-xs !w-28">
                                                    <option value="available" @selected($truck->status === 'available')>Available</option>
                                                    <option value="maintenance" @selected($truck->status === 'maintenance')>Maintenance</option>
                                                    <option value="inactive" @selected($truck->status === 'inactive')>Inactive</option>
                                                </select>
                                                <button type="submit" class="btn btn-soft btn-xs">Save</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-6 text-xs text-[#6E6E73]">No trucks registered yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div>{{ $trucks->links() }}</div>
            </section>

            {{-- Hub Transport Drivers Section --}}
            <section class="card p-5 space-y-4">
                <div>
                    <h2 class="text-sm font-bold text-[#1B1B1E] uppercase tracking-wide">Hub Transport Drivers</h2>
                    <p class="text-xs text-[#6E6E73] mt-0.5">Courier credentials are separate from Delivery Rider accounts.</p>
                </div>

                <form method="POST" action="{{ route('fleet.drivers.store') }}" class="card p-4 bg-[#FAF9F6] border-[#E8E6E0] space-y-3">
                    @csrf
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="field-label">Company <span class="text-red-500">*</span></label>
                            <select name="logistics_company_id" required class="select">
                                <option value="">Select company</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}" @selected($companyId === $company->id)>{{ $company->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="field-label">Full Name <span class="text-red-500">*</span></label>
                            <input name="name" required placeholder="Driver full name" class="input">
                        </div>
                        <div>
                            <label class="field-label">Contact Phone</label>
                            <input name="phone" placeholder="e.g. 09171234567" class="input">
                        </div>
                        <div>
                            <label class="field-label">Courier Email (Login) <span class="text-red-500">*</span></label>
                            <input name="email" type="email" required placeholder="email@example.com" class="input">
                        </div>
                        <div>
                            <label class="field-label">License Number</label>
                            <input name="license_number" placeholder="Driver's license #" class="input">
                        </div>
                        <div>
                            <label class="field-label">Temporary Password <span class="text-red-500">*</span></label>
                            <input name="password" type="password" required minlength="8" placeholder="Min 8 characters" class="input">
                        </div>
                        <div>
                            <label class="field-label">Confirm Password <span class="text-red-500">*</span></label>
                            <input name="password_confirmation" type="password" required minlength="8" placeholder="Repeat password" class="input">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="field-label">Assigned Four-Wheel Truck <span class="text-red-500">*</span></label>
                            <select name="logistics_truck_id" required class="select">
                                <option value="">Select truck</option>
                                @foreach($allTrucks as $truck)
                                    <option value="{{ $truck->id }}">{{ $truck->plate_number }} — {{ $truck->company?->name }} ({{ $truck->vehicle_type }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-full">Register Driver Account</button>
                </form>

                <div class="card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Driver / Company</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th class="text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($drivers as $driver)
                                    <tr>
                                        <td>
                                            <div class="font-bold text-[#1B1B1E]">{{ $driver->name }}</div>
                                            <div class="text-[11px] text-[#6E6E73]">{{ $driver->company?->name }}</div>
                                        </td>
                                        <td class="text-xs text-[#6E6E73]">{{ $driver->phone ?: '—' }}</td>
                                        <td>
                                            <span class="badge {{ $driver->status === 'available' ? 'badge-delivered' : 'badge-inactive' }}">{{ str($driver->status)->title() }}</span>
                                        </td>
                                        <td class="text-right">
                                            <form method="POST" action="{{ route('fleet.drivers.update', $driver) }}" class="inline-flex items-center justify-end gap-1">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="name" value="{{ $driver->name }}">
                                                <input type="hidden" name="phone" value="{{ $driver->phone }}">
                                                <input type="hidden" name="license_number" value="{{ $driver->license_number }}">
                                                <input type="hidden" name="remarks" value="{{ $driver->remarks }}">
                                                <select name="status" class="select !py-1 !px-2 !text-xs !w-28">
                                                    <option value="available" @selected($driver->status === 'available')>Available</option>
                                                    <option value="inactive" @selected($driver->status === 'inactive')>Inactive</option>
                                                </select>
                                                <button type="submit" class="btn btn-soft btn-xs">Save</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-6 text-xs text-[#6E6E73]">No transport drivers registered yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div>{{ $drivers->links() }}</div>
            </section>
        </div>
    </div>
</x-app-layout>
