<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-4 py-2 bg-teal border border-transparent rounded-xl font-semibold text-sm text-white hover:bg-teal-dark focus:bg-teal-dark active:bg-teal-dark focus:outline-none focus:ring-2 focus:ring-teal focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>