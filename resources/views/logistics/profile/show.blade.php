<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2 text-sm text-gray-400 mb-1">
            <span>Home</span>
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-gray-900 font-medium">Profile</span>
        </div>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 leading-tight">Logistics Profile</h1>
                <p class="mt-1 text-sm text-gray-500">Manage your personal credentials, contact info, and view facility assignments</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-teal-light text-teal-dark text-xs font-semibold">
                    <span class="w-1.5 h-1.5 rounded-full bg-teal"></span>
                    {{ $user->roleLabel() }}
                </span>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-semibold">
                    Active Account
                </span>
            </div>
        </div>
    </x-slot>

    @php
        $photoUrl = $user->profilePhotoUrl();
        $company = $user->logisticsCompany ?? $user->logisticsCenter?->company;
        $center = $user->logisticsCenter;
        $serviceArea = $user->serviceArea;
        $initials = strtoupper(substr($user->name, 0, 1));
        $isCompanyAdmin = $user->isAdmin() || $user->isCompanyManager() || $user->isLogisticsAdmin();
    @endphp

    <div class="max-w-4xl mx-auto space-y-6">

        @if(session('success'))
            <div class="bg-teal-light text-teal-dark border border-teal px-4 py-3 rounded-xl text-sm font-medium flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>{{ session('success') }}</span>
                </div>
            </div>
        @endif

        @if($errors->any())
            <div class="bg-red-50 text-red-800 border border-red-200 px-4 py-3 rounded-xl text-sm font-medium">
                <p class="font-semibold mb-1">Please correct the following errors:</p>
                <ul class="list-disc list-inside text-xs space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('logistics.profile.update') }}" enctype="multipart/form-data" class="space-y-6" x-data="profilePhotoUploader()">
            @csrf
            @method('PATCH')

            {{-- ═══════════════════════════════════════════════════════
                 SECTION 1: PROFILE PHOTO / COMPANY LOGO
            ════════════════════════════════════════════════════════ --}}
            <section class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-gray-900">
                            {{ $isCompanyAdmin ? 'Profile Photo / Company Logo' : 'Profile Photo' }}
                        </h3>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ $isCompanyAdmin ? 'Personal avatar or logistics company branding image' : 'Your personal staff identification photo' }}
                        </p>
                    </div>
                </div>

                <div class="p-6">
                    <div class="flex flex-col sm:flex-row items-center gap-6">
                        {{-- Photo Preview --}}
                        <div class="relative flex-shrink-0">
                            <template x-if="previewUrl">
                                <img :src="previewUrl" alt="Selected photo preview"
                                     class="h-24 w-24 rounded-2xl object-cover ring-4 ring-teal-light shadow-sm">
                            </template>
                            <template x-if="!previewUrl">
                                <div>
                                    @if($photoUrl)
                                        <img src="{{ $photoUrl }}" alt="Profile photo"
                                             class="h-24 w-24 rounded-2xl object-cover ring-4 ring-teal-light shadow-sm">
                                    @else
                                        <div class="h-24 w-24 rounded-2xl bg-teal flex items-center justify-center text-white text-3xl font-bold ring-4 ring-teal-light shadow-sm">
                                            {{ $initials }}
                                        </div>
                                    @endif
                                </div>
                            </template>
                            <span class="absolute -bottom-1.5 -right-1.5 h-6 w-6 rounded-full bg-teal text-white flex items-center justify-center shadow">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            </span>
                        </div>

                        {{-- Upload Control --}}
                        <div class="flex-1 text-center sm:text-left space-y-2">
                            <div>
                                <label for="photo" class="cursor-pointer inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-xl transition-colors shadow-sm">
                                    <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    <span>Upload New Photo</span>
                                    <input id="photo" name="photo" type="file" accept=".jpg,.jpeg,.png,.webp" class="sr-only" @change="onFileSelected($event)">
                                </label>
                                <button type="button" x-show="previewUrl" @click="clearFile()"
                                        class="ml-2 inline-flex items-center px-3 py-2 text-xs font-semibold text-gray-500 hover:text-red-600 transition-colors">
                                    Cancel Selection
                                </button>
                            </div>
                            <p class="text-xs text-gray-400">Accepted formats: JPG, PNG, or WebP (max. 2MB). Photos appear in communications, dispatch logs, and internal staff directories.</p>
                            @error('photo')
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </section>

            {{-- ═══════════════════════════════════════════════════════
                 SECTION 2: PERSONAL INFORMATION (EDITABLE)
            ════════════════════════════════════════════════════════ --}}
            <section class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-gray-900">Personal Information</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Your personal credentials and dispatch contact details</p>
                    </div>
                    <span class="text-xs text-teal font-semibold">Editable Fields</span>
                </div>

                <div class="p-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                        <div class="sm:col-span-2">
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                                Full Name <span class="text-red-500">*</span>
                            </label>
                            <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}"
                                   class="block w-full border-gray-300 focus:border-teal focus:ring-teal rounded-xl shadow-sm text-sm @error('name') border-red-300 @enderror"
                                   required />
                            @error('name')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                                Email Address <span class="text-red-500">*</span>
                            </label>
                            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}"
                                   class="block w-full border-gray-300 focus:border-teal focus:ring-teal rounded-xl shadow-sm text-sm @error('email') border-red-300 @enderror"
                                   required />
                            @error('email')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">
                                Contact Number
                            </label>
                            <input id="phone" name="phone" type="tel" value="{{ old('phone', $user->phone) }}" placeholder="09171234567"
                                   class="block w-full border-gray-300 focus:border-teal focus:ring-teal rounded-xl shadow-sm text-sm @error('phone') border-red-300 @enderror" />
                            <p class="mt-1 text-xs text-gray-400">Used for dispatch communications and urgent hub inquiries</p>
                            @error('phone')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-6 pt-5 border-t border-gray-100 flex items-center justify-between">
                        <span class="text-xs text-gray-400">Changes to your name or email will be reflected across your authenticated sessions.</span>
                        <button type="submit"
                                class="inline-flex items-center px-6 py-2.5 bg-teal hover:bg-teal-dark text-white text-sm font-semibold rounded-xl transition-colors duration-200 shadow-sm">
                            Save Profile Information
                        </button>
                    </div>
                </div>
            </section>

            {{-- ═══════════════════════════════════════════════════════
                 SECTION 3: ORGANIZATION & ASSIGNMENTS (READ-ONLY)
            ════════════════════════════════════════════════════════ --}}
            <section class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="h-6 w-6 rounded-md bg-amber-50 text-amber-600 flex items-center justify-center">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-gray-900">Organization &amp; Assignment</h3>
                            <p class="text-xs text-gray-500 mt-0.5">Role credentials, facility allocation, and logistics company assignment</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-gray-100 text-gray-600 text-xs font-semibold">
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        Admin-Controlled
                    </span>
                </div>

                <div class="p-6 space-y-6">
                    {{-- Notice banner --}}
                    <div class="p-4 rounded-xl bg-surface-soft border border-gray-200/60 flex items-start gap-3">
                        <svg class="h-5 w-5 text-teal flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div class="text-xs text-gray-600 leading-relaxed">
                            <p class="font-semibold text-gray-800">Protected Assignment Fields</p>
                            <p class="mt-0.5">Logistics personnel cannot alter their company assignment, role permissions, or assigned sorting facility directly. Transfers, role promotions, and territory adjustments must be processed by INVOIZ Logistics Administration.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        {{-- Account Role --}}
                        <div class="p-4 rounded-xl bg-gray-50/70 border border-gray-200/60">
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Position / Role</p>
                            <div class="mt-1.5 flex items-center justify-between">
                                <span class="text-sm font-bold text-gray-900">{{ $user->roleLabel() }}</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-teal-light text-teal-dark">
                                    {{ $user->role ?? 'staff' }}
                                </span>
                            </div>
                            <p class="mt-2 text-xs text-gray-400">Controls operational capabilities and dashboard dispatch privileges</p>
                        </div>

                        {{-- Logistics Company --}}
                        <div class="p-4 rounded-xl bg-gray-50/70 border border-gray-200/60">
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Logistics Company</p>
                            <div class="mt-1.5 flex items-center justify-between">
                                <span class="text-sm font-bold text-gray-900">{{ $company?->name ?? 'Global Logistics Platform' }}</span>
                                @if($company)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-700">
                                        {{ ucfirst($company->status ?? 'active') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-gray-100 text-gray-500">
                                        System-wide
                                    </span>
                                @endif
                            </div>
                            <p class="mt-2 text-xs text-gray-400">
                                @if($company?->registration_number)
                                    Reg. No: {{ $company->registration_number }}
                                @else
                                    Assigned logistics operator organization
                                @endif
                            </p>
                        </div>

                        {{-- Assigned Center / Hub --}}
                        <div class="p-4 rounded-xl bg-gray-50/70 border border-gray-200/60">
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned Logistics Hub</p>
                            <div class="mt-1.5 flex items-center justify-between">
                                <span class="text-sm font-bold text-gray-900">{{ $center?->name ?? 'All Centers (Global Dispatch)' }}</span>
                                @if($center)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold {{ $center->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                                        {{ $center->is_active ? 'Operating' : 'Inactive' }}
                                    </span>
                                @endif
                            </div>
                            <p class="mt-2 text-xs text-gray-400">
                                @if($center)
                                    {{ $center->city }}, {{ $center->province }} &bull; {{ $center->address }}
                                @else
                                    Unrestricted logistics management access
                                @endif
                            </p>
                        </div>

                        {{-- Assigned Service Area --}}
                        <div class="p-4 rounded-xl bg-gray-50/70 border border-gray-200/60">
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned Service Area</p>
                            <div class="mt-1.5 flex items-center justify-between">
                                <span class="text-sm font-bold text-gray-900">{{ $serviceArea?->name ?? ($center ? 'Full Center Coverage' : 'All Territories') }}</span>
                                @if($serviceArea)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-teal-light text-teal-dark">
                                        Assigned
                                    </span>
                                @endif
                            </div>
                            <p class="mt-2 text-xs text-gray-400">
                                {{ $serviceArea?->description ?? 'Territory scope governed by the assigned logistics hub' }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

        </form>
    </div>

@push('scripts')
<script>
function profilePhotoUploader() {
    return {
        previewUrl: null,
        onFileSelected(event) {
            const file = event.target.files[0];
            if (!file) {
                this.previewUrl = null;
                return;
            }
            if (file.size > 2 * 1024 * 1024) {
                alert('The selected image is larger than 2MB. Please choose a smaller file.');
                event.target.value = '';
                this.previewUrl = null;
                return;
            }
            const reader = new FileReader();
            reader.onload = (e) => {
                this.previewUrl = e.target.result;
            };
            reader.readAsDataURL(file);
        },
        clearFile() {
            this.previewUrl = null;
            const input = document.getElementById('photo');
            if (input) input.value = '';
        }
    };
}
</script>
@endpush

</x-app-layout>
