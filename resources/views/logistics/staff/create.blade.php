<x-app-layout>
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('staff.index') }}"
           class="p-2 bg-white border border-gray-200 rounded-xl text-gray-500 hover:text-teal-dark hover:border-teal transition shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Add Staff Account</h1>
            <p class="text-sm text-gray-500 mt-1">Create a logistics center staff account.</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sm:p-8">
        <form action="{{ route('staff.store') }}" method="POST">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
                    <input type="text" name="first_name" id="first_name" value="{{ old('first_name') }}" required class="w-full rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">
                    @error('first_name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name <span class="text-red-500">*</span></label>
                    <input type="text" name="last_name" id="last_name" value="{{ old('last_name') }}" required class="w-full rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">
                    @error('last_name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="middle_initial" class="block text-sm font-medium text-gray-700 mb-1">Middle Initial</label>
                    <input type="text" name="middle_initial" id="middle_initial" value="{{ old('middle_initial') }}" maxlength="10" class="w-full rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">
                </div>
                <div>
                    <label for="sex" class="block text-sm font-medium text-gray-700 mb-1">Sex <span class="text-red-500">*</span></label>
                    <select name="sex" id="sex" required class="w-full rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">
                        <option value="">— Select —</option>
                        @foreach(['male', 'female', 'other'] as $sex)
                            <option value="{{ $sex }}" @selected(old('sex') === $sex)>{{ ucfirst($sex) }}</option>
                        @endforeach
                    </select>
                    @error('sex')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" required class="w-full rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">
                    @error('email')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone <span class="text-red-500">*</span></label>
                    <input type="text" name="phone" id="phone" value="{{ old('phone') }}" placeholder="09XXXXXXXXX" required class="w-full rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">
                    @error('phone')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="birthday" class="block text-sm font-medium text-gray-700 mb-1">Birthday <span class="text-red-500">*</span></label>
                    <input type="date" name="birthday" id="birthday" value="{{ old('birthday') }}" required class="w-full rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">
                    @error('birthday')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="center_id" class="block text-sm font-medium text-gray-700 mb-1">Logistics Center <span class="text-red-500">*</span></label>
                    <select name="center_id" id="center_id" required class="w-full rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm">
                        <option value="">— Select center —</option>
                        @foreach($centers as $center)
                            <option value="{{ $center->id }}" @selected((int) old('center_id') === $center->id)>{{ $center->name }}</option>
                        @endforeach
                    </select>
                    @error('center_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password <span class="text-red-500">*</span></label>
                    <x-password-input name="password" id="password" class="w-full" required :error="$errors->has('password')" />
                    @error('password')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password <span class="text-red-500">*</span></label>
                    <x-password-input name="password_confirmation" id="password_confirmation" class="w-full" required />
                </div>
            </div>

            <div class="mt-8 flex items-center justify-end gap-3">
                <a href="{{ route('staff.index') }}" class="inline-flex items-center justify-center bg-white border border-gray-200 hover:border-gray-300 text-gray-700 font-semibold px-6 py-2.5 rounded-xl transition">Cancel</a>
                <button type="submit" class="bg-teal hover:bg-teal-dark text-white font-semibold px-6 py-2.5 rounded-xl transition shadow-sm">Create Staff Account</button>
            </div>
        </form>
    </div>
</x-app-layout>
