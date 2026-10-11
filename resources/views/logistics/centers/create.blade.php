<x-app-layout>
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('centers.index') }}"
           class="p-2 bg-white border border-gray-200 rounded-xl text-gray-500 hover:text-teal-dark hover:border-teal transition shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Add Logistics Center</h1>
            <p class="text-sm text-gray-500 mt-1">Register a new logistics center.</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sm:p-8">
        <form action="{{ route('centers.store') }}" method="POST">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Center Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required class="w-full rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">
                    @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="md:col-span-2">
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address <span class="text-red-500">*</span></label>
                    <textarea name="address" id="address" rows="2" required class="w-full rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">{{ old('address') }}</textarea>
                    @error('address')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="city" class="block text-sm font-medium text-gray-700 mb-1">City <span class="text-red-500">*</span></label>
                    <input type="text" name="city" id="city" value="{{ old('city') }}" required class="w-full rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">
                    @error('city')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="province" class="block text-sm font-medium text-gray-700 mb-1">Province</label>
                    <input type="text" name="province" id="province" value="{{ old('province') }}" class="w-full rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">
                </div>
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="text" name="phone" id="phone" value="{{ old('phone') }}" class="w-full rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">
                    @error('phone')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-8 flex items-center justify-end gap-3">
                <a href="{{ route('centers.index') }}" class="inline-flex items-center justify-center bg-white border border-gray-200 hover:border-gray-300 text-gray-700 font-semibold px-6 py-2.5 rounded-xl transition">Cancel</a>
                <button type="submit" class="bg-teal hover:bg-teal-dark text-white font-semibold px-6 py-2.5 rounded-xl transition shadow-sm">Create Center</button>
            </div>
        </form>
    </div>
</x-app-layout>
