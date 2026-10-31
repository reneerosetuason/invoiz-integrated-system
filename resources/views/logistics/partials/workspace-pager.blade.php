@php
    $paginator = $paginator->appends(request()->except($paginator->getPageName()));
    $last = $paginator->lastPage();
    $cur = $paginator->currentPage();
    $pages = [];
    for ($p = 1; $p <= $last; $p++) {
        if ($p === 1 || $p === $last || abs($p - $cur) <= 2) $pages[] = $p;
    }
@endphp
<nav class="lw-pagination" aria-label="Conversation pages">
    <div>
        @if($paginator->onFirstPage())
            <span aria-disabled="true">Previous</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
        @endif
        @php $prev = 0; @endphp
        @foreach($pages as $p)
            @if($p - $prev > 1)<span class="lw-pagination-gap" aria-hidden="true">&hellip;</span>@endif
            @if($p === $cur)
                <span aria-current="page" aria-label="Page {{ $p }}">{{ $p }}</span>
            @else
                <a href="{{ $paginator->url($p) }}" aria-label="Page {{ $p }}">{{ $p }}</a>
            @endif
            @php $prev = $p; @endphp
        @endforeach
        @if($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
        @else
            <span aria-disabled="true">Next</span>
        @endif
    </div>
    <p>Page {{ $cur }} of {{ $last }}</p>
</nav>
