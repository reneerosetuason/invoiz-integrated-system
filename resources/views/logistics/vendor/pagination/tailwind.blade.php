@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" class="flex items-center justify-center gap-2 flex-wrap">

        {{-- Previous Page Link --}}
        @if ($paginator->onFirstPage())
            <span class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-400 bg-white border border-gray-200 rounded-xl cursor-not-allowed select-none">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Previous
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
               class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-xl hover:border-teal hover:text-teal-dark transition">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Previous
            </a>
        @endif

        {{-- Page Numbers --}}
        <div class="flex items-center gap-1.5">
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="inline-flex items-center justify-center w-10 h-10 text-sm font-medium text-gray-400 bg-white border border-gray-200 rounded-xl cursor-default select-none">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page"
                                  class="inline-flex items-center justify-center min-w-[40px] h-10 px-3 text-sm font-semibold text-white bg-teal border border-teal rounded-xl shadow-sm cursor-default select-none">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" aria-label="Go to page {{ $page }}"
                               class="inline-flex items-center justify-center min-w-[40px] h-10 px-3 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-xl hover:border-teal hover:text-teal-dark transition">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
        </div>

        {{-- Next Page Link --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next"
               class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-xl hover:border-teal hover:text-teal-dark transition">
                Next
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        @else
            <span class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-400 bg-white border border-gray-200 rounded-xl cursor-not-allowed select-none">
                Next
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </span>
        @endif
    </nav>
@endif
