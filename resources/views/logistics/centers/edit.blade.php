<x-app-layout>
    <div class="space-y-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('centers.show', $center) }}" class="btn btn-outline btn-sm" aria-label="Back to hub">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span>Back</span>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-[#1B1B1E]">Edit Logistics Hub</h1>
                <p class="text-xs text-[#6E6E73] mt-0.5">Update hub details for {{ $center->name }}.</p>
            </div>
        </div>

        <div class="card p-6 sm:p-8">
            <form action="{{ route('centers.update', $center) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="md:col-span-2">
                        <label for="name" class="field-label">Logistics Hub Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" id="name" value="{{ old('name', $center->name) }}" required class="input">
                        @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-2">
                        <label for="address" class="field-label">Address <span class="text-red-500">*</span></label>
                        <textarea name="address" id="address" rows="2" required class="textarea">{{ old('address', $center->address) }}</textarea>
                        @error('address')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    @include('shared.partials.psgc-address-fields', [
                        'prefix' => '',
                        'uid' => 'center_address',
                        'values' => [
                            'region' => old('region', $center->region ?? ''),
                            'province' => old('province', $center->province),
                            'city' => old('city', $center->city),
                        ],
                        'controlClass' => 'select',
                        'labelClass' => 'field-label',
                        'fields' => ['region', 'province', 'city'],
                        'required' => ['region', 'province', 'city'],
                        'regionFromProvince' => true,
                    ])
                    <div class="md:col-span-2">
                        @error('city')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        @error('region')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="phone" class="field-label">Phone</label>
                        <input type="text" name="phone" id="phone" value="{{ old('phone', $center->phone) }}" class="input">
                        @error('phone')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="mt-8 flex items-center justify-end gap-3 pt-4 border-t border-[#E8E6E0]">
                    <a href="{{ route('centers.show', $center) }}" class="btn btn-outline">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
