@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-gray-300 focus:border-teal focus:ring-teal rounded-xl shadow-sm']) }}>