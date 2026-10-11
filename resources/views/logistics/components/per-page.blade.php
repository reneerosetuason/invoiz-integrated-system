@props(['route', 'align' => 'right'])

@php
    $perPage = (int) request('per_page');
    $current = in_array($perPage, [10, 25, 50]) ? $perPage : 10;

    $hidden = collect(request()->query())
        ->except(['page', 'per_page'])
        ->filter(fn ($v) => $v !== null && $v !== '' && !is_array($v))
        ->all();
@endphp

<form method="GET" action="{{ route($route) }}" {{ $attributes->merge(['class' => "flex items-center gap-2 {$align}"]) }}>
    @foreach($hidden as $key => $value)
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
    @endforeach
    <label for="per-page-select" class="text-xs text-gray-500 whitespace-nowrap">Rows per page</label>
    <select id="per-page-select" name="per_page" onchange="this.form.submit()"
            class="text-sm bg-white border border-gray-200 rounded-xl px-3 py-1.5 text-gray-700 focus:border-teal focus:ring-teal shadow-sm cursor-pointer">
        @foreach([10, 25, 50] as $option)
            <option value="{{ $option }}" {{ $current === $option ? 'selected' : '' }}>{{ $option }}</option>
        @endforeach
    </select>
    <noscript><button type="submit" class="text-xs font-semibold text-teal">Go</button></noscript>
</form>
