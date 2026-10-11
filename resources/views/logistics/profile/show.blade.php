<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2 text-sm text-gray-400 mb-1">
            <span>Home</span>
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-gray-900 font-medium">Profile</span>
        </div>
        <h2 class="text-2xl font-bold text-gray-900 leading-tight">Profile</h2>
        <p class="mt-1 text-sm text-gray-500">Your personal account information and profile photo</p>
    </x-slot>

    <div class="max-w-4xl mx-auto space-y-6">

        @if(session('success'))
            <div class="bg-teal-light text-teal-dark border border-teal px-4 py-3 rounded-xl text-sm font-medium">
                {{ session('success') }}
            </div>
        @endif

        {{-- ===================== Profile ===================== --}}
        <section class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Profile Information</h3>
                    <p class="text-sm text-gray-500 mt-0.5">Your personal account details and profile photo</p>
                </div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-teal-light text-teal-dark text-xs font-semibold">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ $user->roleLabel() }}
                </span>
            </div>

            <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="p-6 space-y-6">
                @csrf
                @method('PATCH')

                <div class="flex items-center gap-5 pb-6 border-b border-gray-100">
                    @if($settings->photo_path && file_exists(public_path($settings->photo_path)))
                        <img src="{{ asset($settings->photo_path) }}" alt="Profile photo" class="h-20 w-20 rounded-full object-cover ring-4 ring-teal-light flex-shrink-0">
                    @else
                        <div class="h-20 w-20 rounded-full bg-teal flex items-center justify-center text-white text-2xl font-bold ring-4 ring-teal-light flex-shrink-0">
                            {{ strtoupper(substr($user->name, 0, 1)) }}
                        </div>
                    @endif
                    <div>
                        <label class="cursor-pointer inline-flex items-center px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-xl transition-colors">
                            Change Photo
                            <input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp" class="sr-only">
                        </label>
                        <p class="text-xs text-gray-400 mt-1.5">JPG, PNG or WebP, max 2MB</p>
                        @error('photo')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                    <div class="sm:col-span-2">
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                        <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}"
                               class="block w-full border-gray-300 focus:border-teal focus:ring-teal rounded-xl shadow-sm"
                               required />
                        @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                        <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}"
                               class="block w-full border-gray-300 focus:border-teal focus:ring-teal rounded-xl shadow-sm"
                               required />
                        @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Contact Number</label>
                        <input id="phone" name="phone" type="tel" value="{{ old('phone', $user->phone) }}" placeholder="09XXXXXXXXX"
                               class="block w-full border-gray-300 focus:border-teal focus:ring-teal rounded-xl shadow-sm" />
                        @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                        <div class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-500">
                            {{ $user->roleLabel() }}
                        </div>
                    </div>
                </div>

                <div class="pt-6 border-t border-gray-100 flex flex-wrap items-center gap-3">
                    <button type="submit"
                            class="inline-flex items-center px-5 py-2.5 bg-teal hover:bg-teal-dark text-white text-sm font-semibold rounded-xl transition-colors duration-200">
                        Save Changes
                    </button>
                    <span class="text-sm text-gray-500">
                        Manage your password, notifications, and delivery preferences on the
                        <a href="{{ route('settings.index') }}" class="font-semibold text-teal-dark hover:text-teal">Settings</a> page.
                    </span>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
