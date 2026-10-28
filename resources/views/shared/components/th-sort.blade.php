@props(['field', 'label', 'align' => 'left'])

@php
    $currentSort = request('sort');
    $currentDir = strtolower(request('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
    $isActive = $currentSort === $field;
    $nextDir = $isActive && $currentDir === 'asc' ? 'desc' : 'asc';

    $params = collect(request()->query())
        ->except(['page', 'sort', 'dir'])
        ->merge(['sort' => $field, 'dir' => $nextDir])
        ->filter(fn ($v) => $v !== null && $v !== '')
        ->all();
    $url = request()->url() . (count($params) ? '?' . http_build_query($params) : '');

    $thClass = 'px-6 py-3.5 text-xs font-semibold uppercase tracking-wider whitespace-nowrap select-none '
        . ($isActive ? 'text-teal-dark' : 'text-gray-500');
@endphp

<th {{ $attributes->merge(['class' => $thClass]) }}>
    <a href="{{ $url }}" class="inline-flex items-center gap-1.5 transition-colors hover:text-teal-dark" @if($isActive) aria-sort="{{ $currentDir === 'asc' ? 'ascending' : 'descending' }}" @endif>
        {{ $label }}
        <span class="inline-flex flex-col items-center justify-center leading-[0]">
            <svg class="h-2 w-2 {{ $isActive && $currentDir === 'asc' ? 'text-teal' : 'text-gray-300' }}" viewBox="0 0 8 5" fill="currentColor"><path d="M4 0l4 5H0z"/></svg>
            <svg class="h-2 w-2 mt-0.5 {{ $isActive && $currentDir === 'desc' ? 'text-teal' : 'text-gray-300' }}" viewBox="0 0 8 5" fill="currentColor"><path d="M4 5L0 0h8z"/></svg>
        </span>
    </a>
</th>
