<x-app-layout>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('rider-applications.index') }}"
               class="lw-back" aria-label="Back to list">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Application Review</h1>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            @if($application->status === 'pending')
                <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200">Pending</span>
            @elseif($application->status === 'approved')
                <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200">Approved</span>
            @else
                <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-red-50 text-red-700 ring-1 ring-inset ring-red-200">Rejected</span>
            @endif
            @if($application->submitted_via === 'mobile')
                <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-200">Via Mobile</span>
            @endif
            @if($application->provisioned_at)
                <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-teal-light text-teal-dark">Account Provisioned</span>
            @endif
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-5">
            <div class="h-16 w-16 rounded-full bg-teal flex items-center justify-center text-2xl font-bold text-white flex-shrink-0">
                {{ strtoupper(substr($application->name, 0, 1)) }}
            </div>
            <div>
                <h2 class="text-xl font-bold text-gray-900">{{ $application->name }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $application->email }} &middot; {{ $application->phone }}</p>
                @if($application->isHubTransportDriver())
                    <span class="mt-3 inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-lg bg-sky-50 text-sky-800 ring-1 ring-inset ring-sky-200">
                        <svg class="w-4 h-4 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h11v10H3zM14 10h4l3 3v4h-7zM7 20a2 2 0 100-4 2 2 0 000 4zm11 0a2 2 0 100-4 2 2 0 000 4z"/></svg>
                        Hub Transport Driver Applicant
                    </span>
                @else
                    <span class="mt-3 inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-lg bg-teal-light text-teal-dark">Delivery Rider Applicant</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Application details --}}
    @if($application->isHubTransportDriver())
        {{-- Hub Transport Driver Information --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
            <h3 class="text-base font-bold text-gray-900 mb-5">Driver Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Full Name</p>
                    <p class="text-sm font-medium text-gray-900">{{ $application->name }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Email</p>
                    <p class="text-sm font-medium text-gray-900">{{ $application->email }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Phone</p>
                    <p class="text-sm font-medium text-gray-900">{{ $application->phone }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Driver License Number</p>
                    <p class="text-sm font-medium text-gray-900">{{ $application->license_number ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">License Expiry</p>
                    <p class="text-sm font-medium text-gray-900">{{ $application->license_expiry?->format('M d, Y') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Sex &middot; Age</p>
                    <p class="text-sm font-medium text-gray-900">{{ ucfirst($application->sex ?? '—') }} &middot; {{ $application->age ?? '—' }} yrs old</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Birthday</p>
                    <p class="text-sm font-medium text-gray-900">{{ $application->birthday?->format('M d, Y') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Address</p>
                    <p class="text-sm font-medium text-gray-900">
                        {{ trim(implode(', ', array_filter([$application->house_number,
                                                            $application->street,
                                                            $application->barangay,
                                                            $application->municipality,
                                                            $application->province,
                                                            $application->region]))) ?: ($application->address ?? '—') }}
                    </p>
                    @if ($application->region_code || $application->province_code || $application->municipality_code || $application->barangay_code)
                        <span class="block text-xs text-gray-400 mt-0.5">PSGC: {{ implode(' / ', array_filter([$application->region_code, $application->province_code, $application->municipality_code, $application->barangay_code])) }}</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Hub Transport Vehicle Information / Fleet Note --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-base font-bold text-gray-900">Vehicle Arrangement & Specification</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Vehicle arrangement selected by the applicant during submission.</p>
                </div>
                @if($application->vehicle_ownership === 'driver_owned' || $application->vehicle_ownership === 'own')
                    <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200">Driver-Owned Truck</span>
                @else
                    <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-200">Company-Provided Truck</span>
                @endif
            </div>

            @if($application->vehicle_ownership === 'driver_owned' || $application->vehicle_ownership === 'own')
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 pt-2 mb-4">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Arrangement</p>
                        <p class="text-sm font-semibold text-gray-900">Driver-Owned Truck</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Truck / Vehicle Type</p>
                        <p class="text-sm font-medium text-gray-900">{{ ucfirst($application->vehicle_type ?? 'Truck') }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">License Plate</p>
                        <p class="text-sm font-medium text-gray-900">{{ $application->license_plate ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Vehicle Registration (OR/CR)</p>
                        <p class="text-sm font-medium text-gray-900">{{ $application->vehicle_registration ?? '—' }}</p>
                    </div>
                    @if($application->vehicle_model)
                        <div>
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Brand / Model</p>
                            <p class="text-sm font-medium text-gray-900">{{ $application->vehicle_model }}</p>
                        </div>
                    @endif
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-4 text-xs text-emerald-900">
                    <p class="font-semibold text-emerald-950">Driver-Owned Truck Verification</p>
                    <p class="mt-0.5 text-emerald-800 leading-relaxed">
                        Applicant registered their personal four-wheel truck. Review the uploaded OR/CR registration document below to verify plate <strong>{{ $application->license_plate }}</strong> before approval. Upon approval, this driver qualifies for standard trip compensation plus vehicle-use allowance.
                    </p>
                </div>
            @else
                <div class="rounded-xl border border-sky-200/70 bg-sky-50/50 p-4 text-xs text-sky-900 space-y-2">
                    <div class="flex items-start gap-2.5">
                        <svg class="w-5 h-5 text-sky-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <p class="font-semibold text-sky-950">Company Fleet Assignment Policy</p>
                            <p class="mt-0.5 text-sky-800 leading-relaxed">
                                Applicant requested a company-provided vehicle. No personal truck OR/CR documents are required.
                                When this application is approved, a Hub Transport Driver account is created with <strong>Available</strong> status. A four-wheel fleet truck may then be assigned from
                                <a href="{{ route('fleet.index') }}" class="font-bold underline hover:text-sky-950">Fleet Management</a>.
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            @if($application->hubTransportDriver)
                <div class="mt-5 pt-4 border-t border-gray-100 flex flex-wrap items-center gap-4">
                    <span class="text-xs font-bold uppercase tracking-wider text-emerald-700 flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Hub Transport Driver Provisioned:
                    </span>
                    <a href="{{ route('fleet.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-lg bg-emerald-50 text-emerald-800 ring-1 ring-inset ring-emerald-200 hover:bg-emerald-100 transition">
                        Driver: {{ $application->hubTransportDriver->name }} ({{ $application->hubTransportDriver->status }})
                    </a>
                    @if($application->hubTransportDriver->truck)
                        <a href="{{ route('fleet.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-lg bg-sky-50 text-sky-800 ring-1 ring-inset ring-sky-200 hover:bg-sky-100 transition">
                            Assigned Truck: {{ $application->hubTransportDriver->truck->plate_number }} ({{ ucfirst(str_replace('_', ' ', $application->hubTransportDriver->truck->vehicle_type)) }})
                        </a>
                    @else
                        <span class="inline-flex items-center px-2.5 py-1 text-xs font-medium rounded-lg bg-amber-50 text-amber-800 border border-amber-200">
                            Awaiting Four-Wheel Truck Assignment in Fleet Management
                        </span>
                    @endif
                </div>
            @endif
        </div>
    @else
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
            <h3 class="text-base font-bold text-gray-900 mb-5">Application Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Full Name</p>
                    <p class="text-sm font-medium text-gray-900">{{ $application->name }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Middle Initial</p>
                    <p class="text-sm font-medium text-gray-900">{{ $application->middle_initial ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Sex</p>
                    <p class="text-sm font-medium text-gray-900">{{ ucfirst($application->sex ?? '—') }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Birthday</p>
                    <p class="text-sm font-medium text-gray-900">{{ $application->birthday?->format('M d, Y') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Age</p>
                    <p class="text-sm font-medium text-gray-900">{{ $application->age ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Address</p>
                    <p class="text-sm font-medium text-gray-900">
                        {{ trim(implode(', ', array_filter([$application->house_number,
                                                            $application->street,
                                                            $application->barangay,
                                                            $application->municipality,
                                                            $application->province,
                                                            $application->region]))) ?: ($application->address ?? '—') }}
                    </p>
                    @if ($application->region_code || $application->province_code || $application->municipality_code || $application->barangay_code)
                        <span class="block text-xs text-gray-400 mt-0.5">PSGC: {{ implode(' / ', array_filter([$application->region_code, $application->province_code, $application->municipality_code, $application->barangay_code])) }}</span>
                    @endif
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Vehicle Type</p>
                    <p class="text-sm font-medium text-gray-900">{{ ucfirst($application->vehicle_type) }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">License Plate</p>
                    <p class="text-sm font-medium text-gray-900">{{ $application->license_plate ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">License Number</p>
                    <p class="text-sm font-medium text-gray-900">{{ $application->license_number ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Vehicle Registration</p>
                    <p class="text-sm font-medium text-gray-900">{{ $application->vehicle_registration ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Assigned Logistics Hub</p>
                    <p class="text-sm font-medium text-gray-900">{{ $application->logisticsCenter?->name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Service Area</p>
                    <p class="text-sm font-medium text-gray-900">{{ $application->serviceArea?->name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Submitted</p>
                    <p class="text-sm font-medium text-gray-900">{{ $application->created_at?->format('M d, Y h:i A') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Reviewed By</p>
                    <p class="text-sm font-medium text-gray-900">{{ $application->approver?->name ?? '—' }}</p>
                </div>
            </div>

            @if($application->notes && $application->status !== 'pending')
                <div class="mt-6 bg-gray-50 rounded-xl border border-gray-100 p-4">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Review Note</p>
                    <p class="text-sm font-medium text-gray-900">{{ $application->notes }}</p>
                </div>
            @endif
        </div>
    @endif

    {{-- Documents --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
        <h3 class="text-base font-bold text-gray-900 mb-5">Supporting Documents</h3>
        @if($application->supportingDocuments->isEmpty())
            <p class="text-sm text-gray-500">No documents uploaded.</p>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($application->supportingDocuments as $document)
                    <div class="border border-gray-100 rounded-xl p-4 flex items-start gap-3 bg-gray-50/50">
                        <div class="h-10 w-10 rounded-lg bg-teal-light flex items-center justify-center flex-shrink-0">
                            @if($document->isImage())
                                <svg class="h-5 w-5 text-teal-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            @else
                                <svg class="h-5 w-5 text-teal-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900">{{ $document->typeLabel() }}</p>
                            <p class="text-xs text-gray-500 truncate mt-0.5">{{ $document->original_filename }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $document->humanSize() }}</p>
                            @if($document->fileExists())
                                <div class="mt-2 flex items-center gap-3">
                                    <a data-image-viewer="document" href="{{ route('rider-applications.documents.view', $document) }}"  class="inline-flex items-center gap-1 text-xs font-semibold text-teal-dark hover:text-teal">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        View
                                    </a>
                                    <a href="{{ route('rider-applications.documents.download', $document) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-gray-600 hover:text-gray-900">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                        Download
                                    </a>
                                </div>
                            @else
                                <p class="text-xs text-red-500 mt-1">File missing on disk</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Review actions (pending only) --}}
    @if($application->status === 'pending')
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            {{-- Approve & provision --}}
            <div class="bg-white rounded-2xl shadow-sm border border-emerald-100 p-6">
                @if($application->isHubTransportDriver())
                    <h3 class="text-base font-bold text-gray-900 mb-1">Approve Hub Transport Driver</h3>
                    <p class="text-sm text-gray-500 mb-5">Approves the Hub Transport Driver account and provisions their driver record linked to this logistics hub's company. Truck assignment is handled in Fleet Management after approval.</p>
                @else
                    <h3 class="text-base font-bold text-gray-900 mb-1">Approve &amp; Provision Account</h3>
                    <p class="text-sm text-gray-500 mb-5">Sets the rider's login password and creates their account. Leave the password blank to automatically generate a secure temporary password. After approval, the login credentials will be sent to the rider's registered email address.</p>
                @endif
                <form action="{{ route('rider-applications.approve', $application) }}" method="POST" x-data="{}" data-rider-approval>
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label for="center_id" class="block text-sm font-medium text-gray-700 mb-1.5">Logistics Hub</label>
                            <select name="center_id" id="center_id" required
                                    class="w-full rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm @error('center_id') border-red-500 @enderror">
                                <option value="">Select Logistics Hub</option>
                                @foreach($centers as $center)
                                    <option value="{{ $center->id }}" @selected((int) old('center_id', $application->center_id) === $center->id)>{{ $center->name }}</option>
                                @endforeach
                            </select>
                            @error('center_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>

                        @if($application->isHubTransportDriver())
                            <div class="rounded-xl bg-sky-50/70 border border-sky-200/60 p-3.5 text-xs text-sky-800">
                                <p class="font-semibold flex items-center gap-1.5 mb-1">
                                    <svg class="w-4 h-4 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Automatic Driver Provisioning (Fleet Assignment Pending)
                                </p>
                                <p>Upon approval, an active <strong>Hub Transport Driver</strong> record will be created and linked to the hub's logistics company with status <em>Available</em> (no truck automatically assigned). You can assign an available four-wheel truck in <strong>Fleet Management</strong> at any time.</p>
                            </div>
                        @else
                            <div>
                                <label for="service_area_id" class="block text-sm font-medium text-gray-700 mb-1.5">Service Area</label>
                                <select name="service_area_id" id="service_area_id" required aria-describedby="service-area-hint"
                                        class="w-full rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm @error('service_area_id') border-red-500 @enderror">
                                    <option value="">Select service area</option>
                                    @foreach($areas as $area)
                                        <option value="{{ $area->id }}" data-center-id="{{ $area->logistics_center_id }}" @selected((int) old('service_area_id', $application->service_area_id) === $area->id)>{{ $area->name }} ({{ $area->logisticsCenter?->name ?? 'Center' }})</option>
                                    @endforeach
                                </select>
                                @error('service_area_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                <p id="service-area-hint" class="mt-2 text-xs text-gray-500" role="status" aria-live="polite">Select a Logistics Hub to see its active service areas.</p>
                                <a href="{{ route('service-areas.create') }}" data-create-service-area target="_blank" rel="noopener"
                                   class="mt-2 inline-flex text-xs font-semibold text-teal hover:text-teal-dark">Add a service area (opens a new tab)</a>
                            </div>
                        @endif

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">Initial Password (optional — auto-generated if blank)</label>
                                <x-password-input name="password" id="password" class="w-full" autocomplete="new-password" :error="$errors->has('password')" />
                                @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1.5">Confirm Password</label>
                                <x-password-input name="password_confirmation" id="password_confirmation" class="w-full" autocomplete="new-password" />
                            </div>
                        </div>
                        <div>
                            <label for="notes" class="block text-sm font-medium text-gray-700 mb-1.5">Approval Note (optional)</label>
                            <textarea name="notes" id="notes" rows="2"
                                      class="w-full rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">{{ old('notes') }}</textarea>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" data-approve-rider class="inline-flex items-center gap-2 bg-teal hover:bg-teal-dark text-white font-semibold px-5 py-2.5 rounded-xl transition shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                                {{ $application->isHubTransportDriver() ? 'Approve & Register Driver' : 'Approve & Create Account' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            {{-- Reject --}}
            <div class="bg-white rounded-2xl shadow-sm border border-red-100 p-6 self-start">
                <h3 class="text-base font-bold text-gray-900 mb-1">Reject Application</h3>
                <p class="text-sm text-gray-500 mb-5">No account is created; the rider is notified they were rejected.</p>
                <form action="{{ route('rider-applications.reject', $application) }}" method="POST" x-data data-confirm-message="Reject this application?">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label for="reason" class="block text-sm font-medium text-gray-700 mb-1.5">Reason *</label>
                            <textarea name="reason" id="reason" rows="3" required
                                      class="w-full rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm @error('reason') border-red-500 @enderror"></textarea>
                            @error('reason')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="inline-flex items-center gap-2 bg-red-500 hover:bg-red-600 text-white font-semibold px-5 py-2.5 rounded-xl transition shadow-sm">
                                Reject Application
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Resend login credentials (approved & provisioned riders only) --}}
    @if($application->status === 'approved' && $application->provisioned_at)
        <div class="bg-white rounded-2xl shadow-sm border border-teal-100 p-6 mb-6">
            <h3 class="text-base font-bold text-gray-900 mb-1">{{ $keepsExistingLogin ? 'Resend Approval Email' : 'Resend Login Credentials' }}</h3>
            <p class="text-sm text-gray-500 mb-5">
                @if($keepsExistingLogin)
                    Send the approval notice to {{ $application->email }} again. The {{ $application->isHubTransportDriver() ? 'driver' : 'rider' }} uses their existing password; it will stay unchanged.
                @else
                    Send login credentials to {{ $application->email }}.
                @endif
            </p>
            <form action="{{ route('rider-applications.resend-credentials', $application) }}" method="POST"
                  x-data data-confirm-message="{{ $keepsExistingLogin ? 'Send approval notice again? The existing password will stay unchanged.' : 'Send login credentials again?' }}">
                @csrf
                <div class="flex justify-end">
                    <button type="submit" class="inline-flex items-center gap-2 bg-teal hover:bg-teal-dark text-white font-semibold px-5 py-2.5 rounded-xl transition shadow-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        {{ $keepsExistingLogin ? 'Resend Approval Email' : 'Resend Login Credentials' }}
                    </button>
                </div>
            </form>
        </div>
    @endif

    @if($application->status === 'rejected')
        <div class="bg-white rounded-2xl border border-gray-100 p-6 mb-6">
            <h3 class="text-base font-bold text-gray-900 mb-1">Rejection Email</h3>
            <p class="text-sm text-gray-500 mb-3">Send the recorded rejection reason to {{ $application->email }} again.</p>
            <form action="{{ route('rider-applications.resend-rejection', $application) }}" method="POST"
                  data-confirm-message="Send the rejection email again with the recorded reason?">
                @csrf
                <button type="submit" class="inline-flex items-center bg-teal text-white font-semibold px-4 py-2 rounded-lg">Resend Rejection Email</button>
            </form>
        </div>
    @endif

    {{-- Review history --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-base font-bold text-gray-900 mb-5">Review History</h3>
        @if($application->logs->isEmpty())
            <p class="text-sm text-gray-500">No review activity yet.</p>
        @else
            <div class="space-y-4">
                @foreach ($application->logs as $log)
                    <div class="flex items-start gap-3">
                        <div class="h-8 w-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 flex-shrink-0">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-900">
                                Changed from <span class="text-gray-400">{{ $log->previous_status ?? '—' }}</span> to
                                <span class="text-teal-dark">{{ $log->new_status }}</span>
                            </p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                {{ $log->changer?->name ?? 'System' }} &middot; {{ $log->created_at?->format('M d, Y h:i A') ?? '—' }}
                            </p>
                            @if($log->reason)
                                <p class="text-sm text-gray-600 mt-1">{{ $log->reason }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    @push('scripts')
        <script src="{{ asset('assets/logistics/js/rider-applications/review.js') }}?v={{ filemtime(public_path('assets/logistics/js/rider-applications/review.js')) }}" defer></script>
    @endpush
</x-app-layout>
