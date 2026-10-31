<x-app-layout>
    <div class="space-y-4">
        <div>
            <p class="text-xs font-bold uppercase tracking-wider text-[#16697A]">Fleet &amp; Transport Linehaul</p>
            <h1 class="text-2xl font-bold text-[#1B1B1E] mt-0.5">Fleet Management</h1>
            <p class="text-xs text-[#6E6E73] mt-0.5">Four-wheel trucks and Hub Transport Drivers operate inter-hub logistics transfers across all INVOIZ facilities.</p>
        </div>

        @if(session('success'))
            <div class="card p-4 bg-green-50 border-green-200 text-green-800 text-xs">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="card p-4 bg-red-50 border-red-200 text-red-800 text-xs">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-5 lg:grid-cols-2">
            {{-- Four-Wheel Trucks Section --}}
            <section class="card p-5 space-y-4">
                <div>
                    <h2 class="text-sm font-bold text-[#1B1B1E] uppercase tracking-wide">Four-Wheel Trucks</h2>
                    <p class="text-xs text-[#6E6E73] mt-0.5">Inter-hub fleet vehicles managed by INVOIZ Logistics.</p>
                </div>

                <form method="POST" action="{{ route('fleet.trucks.store') }}" class="card p-4 bg-[#FAF9F6] border-[#E8E6E0] space-y-3">
                    @csrf
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="field-label">Plate Number <span class="text-red-500">*</span></label>
                            <input name="plate_number" value="{{ old('plate_number') }}" required placeholder="e.g. ABC 1234" class="input">
                        </div>
                        <div>
                            <label class="field-label">Vehicle Name</label>
                            <input name="name" value="{{ old('name') }}" placeholder="e.g. Linehaul Van 01" class="input">
                        </div>
                        <div>
                            <label class="field-label">Vehicle Type <span class="text-red-500">*</span></label>
                            <input name="vehicle_type" required value="{{ old('vehicle_type', 'truck') }}" placeholder="truck / closed_van / forwarder" class="input">
                        </div>
                        <div>
                            <label class="field-label">Brand / Model</label>
                            <input name="model" value="{{ old('model') }}" placeholder="e.g. Isuzu Elf / Hino 300" class="input">
                        </div>
                        <div>
                            <label class="field-label">Parcel Capacity <span class="text-red-500">*</span></label>
                            <input name="parcel_capacity" type="number" min="1" value="{{ old('parcel_capacity') }}" required placeholder="e.g. 150" class="input">
                        </div>
                        <div>
                            <label class="field-label">Weight Capacity (kg) <span class="text-red-500">*</span></label>
                            <input name="capacity_kg" type="number" step="0.01" min="0.01" value="{{ old('capacity_kg') }}" required placeholder="e.g. 2000" class="input">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="field-label">Remarks</label>
                            <input name="remarks" value="{{ old('remarks') }}" placeholder="Optional notes (maintenance, garage, etc.)..." class="input">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-full">Register Truck</button>
                </form>

                <div class="card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Vehicle / Plate</th>
                                    <th>Capacity</th>
                                    <th>Status</th>
                                    <th class="text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($trucks as $truck)
                                    <tr>
                                        <td>
                                            <div class="font-bold text-[#1B1B1E]">{{ $truck->plate_number }}</div>
                                            <div class="text-[11px] text-[#6E6E73]">{{ $truck->name ?: $truck->model }} &middot; {{ ucfirst($truck->vehicle_type) }}</div>
                                            @if($truck->driver)
                                                <div class="text-[10px] text-[#16697A] font-semibold mt-0.5">Driver: {{ $truck->driver->name }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="text-xs font-medium text-[#1B1B1E]">{{ $truck->parcel_capacity }} pcs</div>
                                            <div class="text-[10px] text-[#6E6E73]">{{ number_format($truck->capacity_kg, 1) }} kg</div>
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
                                        <td colspan="4" class="text-center py-6 text-xs text-[#6E6E73]">No trucks registered yet.</td>
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
                    <p class="text-xs text-[#6E6E73] mt-0.5">Linehaul transport credentials are separate from last-mile Delivery Rider accounts.</p>
                </div>

                <form method="POST" action="{{ route('fleet.drivers.store') }}" class="card p-4 bg-[#FAF9F6] border-[#E8E6E0] space-y-3">
                    @csrf
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="field-label">Full Name <span class="text-red-500">*</span></label>
                            <input name="name" value="{{ old('name') }}" required placeholder="Driver full name" class="input">
                        </div>
                        <div>
                            <label class="field-label">Contact Phone</label>
                            <input name="phone" value="{{ old('phone') }}" placeholder="e.g. 09171234567" class="input">
                        </div>
                        <div>
                            <label class="field-label">Courier Email (Login) <span class="text-red-500">*</span></label>
                            <input name="email" type="email" value="{{ old('email') }}" required placeholder="email@example.com" class="input">
                        </div>
                        <div>
                            <label class="field-label">License Number</label>
                            <input name="license_number" value="{{ old('license_number') }}" placeholder="Driver's license #" class="input">
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
                                <option value="">Select available truck</option>
                                @foreach($allTrucks as $truck)
                                    <option value="{{ $truck->id }}" @selected(old('logistics_truck_id') == $truck->id)>
                                        {{ $truck->plate_number }} — {{ $truck->name ?: $truck->model }} ({{ ucfirst($truck->vehicle_type) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="field-label">Remarks</label>
                            <input name="remarks" value="{{ old('remarks') }}" placeholder="Optional driver notes..." class="input">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-full">Register Driver Account</button>
                </form>

                <div class="card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Driver</th>
                                    <th>Contact</th>
                                    <th>Assigned Truck</th>
                                    <th>Status</th>
                                    <th class="text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($drivers as $driver)
                                    <tr>
                                        <td>
                                            <div class="font-bold text-[#1B1B1E]">{{ $driver->name }}</div>
                                            <div class="text-[11px] text-[#6E6E73]">{{ $driver->email }}</div>
                                        </td>
                                        <td class="text-xs text-[#6E6E73]">
                                            <div>{{ $driver->phone ?: '—' }}</div>
                                            @if($driver->license_number)
                                                <div class="text-[10px] text-[#6E6E73]">Lic: {{ $driver->license_number }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            @if($driver->truck)
                                                <span class="text-xs font-semibold text-[#16697A]">{{ $driver->truck->plate_number }}</span>
                                                <div class="text-[10px] text-[#6E6E73]">{{ $driver->truck->name ?: $driver->truck->model }}</div>
                                            @else
                                                <span class="text-xs text-[#6E6E73] italic">Unassigned</span>
                                            @endif
                                        </td>
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
                                        <td colspan="5" class="text-center py-6 text-xs text-[#6E6E73]">No transport drivers registered yet.</td>
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
