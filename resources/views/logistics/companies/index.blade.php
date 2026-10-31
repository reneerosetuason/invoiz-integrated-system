<x-app-layout>
    <div class="space-y-4">
        <div>
            <p class="text-xs font-bold uppercase tracking-wider text-[#16697A]">Logistics Network</p>
            <h1 class="text-2xl font-bold text-[#1B1B1E] mt-0.5">Logistics Companies</h1>
            <p class="text-xs text-[#6E6E73] mt-0.5">The global Logistics Admin oversees all companies. Company staff are assigned separately.</p>
        </div>

        @if(session('success'))
            <div class="card p-4 bg-green-50 border-green-200 text-green-800 text-xs">
                {{ session('success') }}
            </div>
        @endif

        {{-- Register Company --}}
        <div class="card p-5">
            <h2 class="text-sm font-bold text-[#1B1B1E] uppercase tracking-wide mb-3">Register Verified Logistics Company</h2>
            <form method="POST" action="{{ route('companies.store') }}" class="grid gap-3 sm:grid-cols-3 items-end">
                @csrf
                <div>
                    <label class="field-label">Legal Entity Name <span class="text-red-500">*</span></label>
                    <input name="name" required placeholder="Registered legal name" class="input">
                </div>
                <div>
                    <label class="field-label">Registration Number <span class="text-red-500">*</span></label>
                    <input name="registration_number" required placeholder="SEC / DTI registration" class="input">
                </div>
                <div>
                    <button type="submit" class="btn btn-primary w-full sm:w-auto">Create Company</button>
                </div>
            </form>
        </div>

        {{-- Registered Companies Table --}}
        <div class="card overflow-hidden">
            <div class="p-4 border-b border-[#E8E6E0]">
                <h2 class="text-sm font-bold text-[#1B1B1E] uppercase tracking-wide">Registered Companies</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Company</th>
                            <th>Registration</th>
                            <th>Staff</th>
                            <th>Hubs</th>
                            <th>Fleet Linehaul</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($companies as $company)
                            <tr>
                                <td class="font-bold text-[#1B1B1E]">{{ $company->name }}</td>
                                <td class="font-mono text-xs text-[#6E6E73]">{{ $company->registration_number }}</td>
                                <td>{{ $company->staff_count }} staff</td>
                                <td>{{ $company->centers->count() }} hubs</td>
                                <td class="text-xs text-[#6E6E73]">{{ $company->trucks_count }} trucks &middot; {{ $company->transport_drivers_count }} transport drivers</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-6 text-xs text-[#6E6E73]">No companies registered yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Logistics Hub Ownership Verification --}}
        <div class="card overflow-hidden">
            <div class="p-4 border-b border-[#E8E6E0]">
                <h2 class="text-sm font-bold text-[#1B1B1E] uppercase tracking-wide">Logistics Hub Ownership</h2>
                <p class="text-xs text-[#6E6E73] mt-0.5">Confirm ownership using supporting evidence and record the business reason.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Hub</th>
                            <th>Current Company</th>
                            <th>Verified At</th>
                            <th class="text-right">Assign / Correct</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($centers as $center)
                            <tr>
                                <td>
                                    <div class="font-bold text-[#1B1B1E]">#{{ $center->id }} {{ $center->name }}</div>
                                    <div class="text-[11px] text-[#6E6E73]">{{ $center->city }}</div>
                                </td>
                                <td>
                                    <span class="badge {{ $center->company ? 'badge-delivered' : 'badge-pending' }}">
                                        {{ $center->company?->name ?? 'UNASSIGNED' }}
                                    </span>
                                </td>
                                <td class="text-xs text-[#6E6E73]">
                                    {{ $center->ownership_verified_at?->format('M d, Y') ?? 'Review required' }}
                                </td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('companies.centers.assign', $center) }}" class="inline-flex items-center justify-end gap-1.5 flex-wrap">
                                        @csrf
                                        @method('PUT')
                                        <select name="logistics_company_id" required class="select !py-1 !px-2 !text-xs !w-36">
                                            @foreach($companies as $company)
                                                <option value="{{ $company->id }}" @selected($company->id === $center->logistics_company_id)>{{ $company->name }}</option>
                                            @endforeach
                                        </select>
                                        <input name="evidence" required placeholder="Evidence..." class="input !py-1 !px-2 !text-xs !w-28">
                                        <input name="reason" required placeholder="Reason..." class="input !py-1 !px-2 !text-xs !w-24">
                                        <button type="submit" class="btn btn-primary btn-xs">Confirm</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-6 text-xs text-[#6E6E73]">No logistics hubs available.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
