@props([
    'id' => null,
    'name' => null,
    'value' => '',
    'required' => false,
    'autocomplete' => null,
    'placeholder' => null,
    'minlength' => null,
    'error' => false,
    'disabled' => false,
])

<div class="relative" x-data="{ show: false }">
    <input
        @if($id) id="{{ $id }}" @endif
        @if($name) name="{{ $name }}" @endif
        x-bind:type="show ? 'text' : 'password'"
        value="{{ $value }}"
        @required($required)
        @disabled($disabled)
        @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if($minlength) minlength="{{ $minlength }}" @endif
        @if($placeholder) placeholder="{{ $placeholder }}" @endif
        aria-label="{{ $attributes->get('aria-label', 'Password') }}"
        :aria-invalid="{{ $error ? 'true' : 'false' }}"
        {{ $attributes->merge(['class' => 'block w-full pr-12 border-gray-300 focus:border-teal focus:ring-teal rounded-xl shadow-sm transition-colors duration-150' . ($error ? ' border-red-400 focus:border-red-500 focus:ring-red-500' : '')]) }}
    />
    <button
        type="button"
        x-on:click="show = !show"
        class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-0"
        :aria-label="show ? 'Hide password' : 'Show password'"
        :aria-pressed="show"
        :title="show ? 'Hide password' : 'Show password'"
        tabindex="-1"
    >
        {{-- Closed-eye icon (shown while password is hidden) --}}
        <svg x-show="!show" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
        </svg>
        {{-- Open-eye icon (shown while password is visible) --}}
        <svg x-show="show" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
        </svg>
    </button>
</div>
