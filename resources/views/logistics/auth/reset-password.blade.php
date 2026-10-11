<x-guest-layout>
    <div x-data="{ submitting: false, showPassword: false, showConfirm: false }">
        <div class="text-center mb-6">
            <h2 class="text-xl font-bold text-gray-900">Reset Password</h2>
            <p class="mt-1 text-sm text-gray-500">Enter your new password below.</p>
        </div>

        <form method="POST" action="{{ route('password.store') }}"
              x-on:submit="submitting = true"
              novalidate>
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div>
                <label for="email" class="block font-medium text-sm text-gray-700">Email</label>
                <input id="email"
                       type="email"
                       name="email"
                       class="block mt-1 w-full border-gray-300 focus:border-teal focus:ring-teal rounded-xl shadow-sm @error('email') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
                       value="{{ old('email', $request->email) }}"
                       required
                       autofocus
                       autocomplete="username"
                       placeholder="you@company.com"
                       aria-describedby="@error('email') email-error @enderror"
                       aria-invalid="@error('email') true @enderror" />
                @error('email')
                    <p id="email-error" role="alert" class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mt-4">
                <label for="password" class="block font-medium text-sm text-gray-700">New Password</label>
                <div class="relative mt-1">
                    <input id="password"
                           x-bind:type="showPassword ? 'text' : 'password'"
                           name="password"
                           class="block w-full pr-12 border-gray-300 focus:border-teal focus:ring-teal rounded-xl shadow-sm @error('password') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
                           required
                           autocomplete="new-password"
                           placeholder="••••••••"
                           aria-describedby="@error('password') password-error @enderror"
                           aria-invalid="@error('password') true @enderror" />
                    <button type="button"
                            x-on:click="showPassword = !showPassword"
                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-teal rounded-lg"
                            :aria-label="showPassword ? 'Hide password' : 'Show password'"
                            :aria-pressed="showPassword"
                            tabindex="0">
                        <svg x-show="!showPassword" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M3 3l18 18" />
                        </svg>
                        <svg x-show="showPassword" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
                @error('password')
                    <p id="password-error" role="alert" class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mt-4">
                <label for="password_confirmation" class="block font-medium text-sm text-gray-700">Confirm Password</label>
                <div class="relative mt-1">
                    <input id="password_confirmation"
                           x-bind:type="showConfirm ? 'text' : 'password'"
                           name="password_confirmation"
                           class="block w-full pr-12 border-gray-300 focus:border-teal focus:ring-teal rounded-xl shadow-sm"
                           required
                           autocomplete="new-password"
                           placeholder="••••••••"
                           aria-label="Confirm password" />
                    <button type="button"
                            x-on:click="showConfirm = !showConfirm"
                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-teal rounded-lg"
                            :aria-label="showConfirm ? 'Hide password confirmation' : 'Show password confirmation'"
                            :aria-pressed="showConfirm"
                            tabindex="0">
                        <svg x-show="!showConfirm" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M3 3l18 18" />
                        </svg>
                        <svg x-show="showConfirm" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
                @error('password_confirmation')
                    <p role="alert" class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mt-6">
                <button type="submit"
                        :disabled="submitting"
                        :class="submitting ? 'opacity-70 cursor-not-allowed' : 'hover:bg-teal-dark'"
                        class="relative w-full bg-teal text-white rounded-xl py-3 font-semibold transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-teal focus:ring-offset-2 disabled:opacity-70">
                    <span class="inline-flex items-center justify-center gap-2">
                        <svg x-show="submitting" x-cloak class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                        <span x-text="submitting ? 'Resetting...' : 'Reset Password'">Reset Password</span>
                    </span>
                </button>
            </div>
        </form>

        <p class="mt-6 text-center text-sm text-gray-500">
            <a href="{{ route('login') }}"
               class="font-semibold text-teal-dark hover:text-teal underline underline-offset-2 rounded focus:outline-none focus:ring-2 focus:ring-teal focus:ring-offset-1">
                Back to Sign In
            </a>
        </p>
    </div>
</x-guest-layout>
