<x-app-layout>
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('service-areas.index') }}"
           class="p-2 bg-white border border-gray-200 rounded-xl text-gray-500 hover:text-teal-dark hover:border-teal transition shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Add Service Area</h1>
            <p class="text-sm text-gray-500 mt-1">Create a new service area under a logistics center.</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sm:p-8">
        <form action="{{ route('service-areas.store') }}" method="POST">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="logistics_center_id" class="block text-sm font-medium text-gray-700 mb-1">Logistics Center <span class="text-red-500">*</span></label>
                    <select name="logistics_center_id" id="logistics_center_id" required class="w-full rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">
                        <option value="">— Select center —</option>
                        @foreach($centers as $center)
                            <option value="{{ $center->id }}" @selected((int) old('logistics_center_id') === $center->id)>{{ $center->name }}</option>
                        @endforeach
                    </select>
                    @error('logistics_center_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Area Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required class="w-full rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">
                    @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="md:col-span-2">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" id="description" rows="3" class="w-full rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">{{ old('description') }}</textarea>
                    @error('description')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-8 flex items-center justify-end gap-3">
                <a href="{{ route('service-areas.index') }}" class="inline-flex items-center justify-center bg-white border border-gray-200 hover:border-gray-300 text-gray-700 font-semibold px-6 py-2.5 rounded-xl transition">Cancel</a>
                <button type="submit" class="bg-teal hover:bg-teal-dark text-white font-semibold px-6 py-2.5 rounded-xl transition shadow-sm">Create Area</button>
            </div>
        </form>
    </div>
</x-app-layout>
