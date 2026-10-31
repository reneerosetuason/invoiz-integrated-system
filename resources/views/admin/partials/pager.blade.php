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
  $btn = 'display:inline-flex;align-items:center;justify-content:center;min-width:30px;height:30px;padding:0 9px;border-radius:7px;font-size:12px;font-weight:700;text-decoration:none;transition:all .15s;';
  $idle = 'background:#fff;border:1px solid #D1D5DB;color:#374151;';
  $on = 'background:#16697A;border:1px solid #16697A;color:#fff;';
  $off = 'background:#F9FAFB;border:1px solid #E5E7EB;color:#9CA3AF;cursor:default;';
@endphp
@if($last > 1)
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;padding:12px 16px;border-top:1px solid #E8E6E0;background:#fff;border-radius:0 0 14px 14px;">
  <div style="display:flex;align-items:center;gap:5px;flex-wrap:wrap;">
    @if($paginator->onFirstPage())
      <span style="{{ $btn.$off }}">‹ Prev</span>
    @else
      <a href="{{ $paginator->previousPageUrl() }}" style="{{ $btn.$idle }}">‹ Prev</a>
    @endif
    @php $prev = 0; @endphp
    @foreach($pages as $p)
      @if($p - $prev > 1)
        <span style="color:#9CA3AF;padding:0 3px;">…</span>
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
  </div>
  <div style="font-size:12px;color:#6E6E73;font-weight:500;">
    Page {{ $cur }} of {{ $last }} · Showing {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }} results
  </div>
</div>
@endif
