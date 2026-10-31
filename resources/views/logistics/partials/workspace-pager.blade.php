@php
    $paginator = $paginator->appends(request()->except($paginator->getPageName()));
    $last = $paginator->lastPage();
    $cur = $paginator->currentPage();
    $pages = [];
    for ($p = 1; $p <= $last; $p++) {
        if ($p === 1 || $p === $last || abs($p - $cur) <= 2) $pages[] = $p;
    }
@endphp
@if($last > 1)
<nav class="lw-pagination" aria-label="Conversation pages" style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding:8px 12px;border-top:1px solid #E5E7EB;background:#FAFAFA;font-size:12px;color:#4B5563;border-radius:0 0 12px 12px;">
    <div style="display:flex;align-items:center;gap:4px;">
        @if($paginator->onFirstPage())
            <span style="display:inline-flex;align-items:center;padding:3px 8px;border-radius:6px;background:#F3F4F6;color:#9CA3AF;border:1px solid #E5E7EB;font-size:11px;font-weight:600;" aria-disabled="true">‹ Prev</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" style="display:inline-flex;align-items:center;padding:3px 8px;border-radius:6px;background:#fff;color:#374151;border:1px solid #D1D5DB;font-size:11px;font-weight:600;text-decoration:none;">‹ Prev</a>
        @endif
        @php $prev = 0; @endphp
        @foreach($pages as $p)
            @if($p - $prev > 1)<span class="lw-pagination-gap" aria-hidden="true" style="color:#9CA3AF;padding:0 2px;">&hellip;</span>@endif
            @if($p === $cur)
                <span aria-current="page" style="display:inline-flex;align-items:center;justify-content:center;min-width:24px;height:24px;padding:0 5px;border-radius:6px;background:#16697A;color:#fff;font-size:11px;font-weight:700;">{{ $p }}</span>
            @else
                <a href="{{ $paginator->url($p) }}" style="display:inline-flex;align-items:center;justify-content:center;min-width:24px;height:24px;padding:0 5px;border-radius:6px;background:#fff;color:#374151;border:1px solid #D1D5DB;font-size:11px;font-weight:600;text-decoration:none;">{{ $p }}</a>
            @endif
            @php $prev = $p; @endphp
        @endforeach
        @if($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" style="display:inline-flex;align-items:center;padding:3px 8px;border-radius:6px;background:#fff;color:#374151;border:1px solid #D1D5DB;font-size:11px;font-weight:600;text-decoration:none;">Next ›</a>
        @else
            <span style="display:inline-flex;align-items:center;padding:3px 8px;border-radius:6px;background:#F3F4F6;color:#9CA3AF;border:1px solid #E5E7EB;font-size:11px;font-weight:600;" aria-disabled="true">Next ›</span>
        @endif
    </div>
    <span style="font-size:11px;color:#6B7280;">Page {{ $cur }} of {{ $last }}</span>
</nav>
@endif
