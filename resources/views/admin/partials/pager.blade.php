{{-- Compact pager for admin tables (the default Tailwind pager needs
      Tailwind CSS, which the admin layout does not load). Usage:
      @include('admin.partials.pager', ['paginator' => $best]) --}}
@php
  // Keep active filters/search + sibling pagers when flipping pages.
  $paginator = $paginator->appends(request()->except($paginator->getPageName()));
  $win = 2;
  $last = $paginator->lastPage();
  $cur = $paginator->currentPage();
  $pages = [];
  for ($p = 1; $p <= $last; $p++) {
      if ($p === 1 || $p === $last || abs($p - $cur) <= $win) $pages[] = $p;
  }
  $btn = 'display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 10px;border-radius:8px;font-size:12.5px;font-weight:700;text-decoration:none;';
  $idle = 'background:#fff;border:1px solid #E5E7EB;color:#374151;';
  $on = 'background:#16697A;border:1px solid #16697A;color:#fff;';
  $off = 'background:#F9FAFB;border:1px solid #EFEFEF;color:#B6BCC4;cursor:default;';
@endphp
<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;padding:12px 8px 4px;">
  @if($paginator->onFirstPage())
    <span style="{{ $btn.$off }}">‹ Prev</span>
  @else
    <a href="{{ $paginator->previousPageUrl() }}" style="{{ $btn.$idle }}">‹ Prev</a>
  @endif
  @php $prev = 0; @endphp
  @foreach($pages as $p)
    @if($p - $prev > 1)
      <span style="color:#9CA3AF;">…</span>
    @endif
    @if($p === $cur)
      <span style="{{ $btn.$on }}">{{ $p }}</span>
    @else
      <a href="{{ $paginator->url($p) }}" style="{{ $btn.$idle }}">{{ $p }}</a>
    @endif
    @php $prev = $p; @endphp
  @endforeach
  @if($paginator->hasMorePages())
    <a href="{{ $paginator->nextPageUrl() }}" style="{{ $btn.$idle }}">Next ›</a>
  @else
    <span style="{{ $btn.$off }}">Next ›</span>
  @endif
  <span style="font-size:11.5px;color:#6E6E73;margin-left:4px;">Page {{ $cur }} of {{ $last }} · Showing {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }}</span>
</div>
