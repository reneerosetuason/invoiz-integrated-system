{{-- Buyer pager (buyer theme has no Tailwind): @include('partials.buyer-pager', ['paginator' => $orders]) --}}
@php
  $paginator = $paginator->appends(request()->except($paginator->getPageName()));
  $win = 2;
  $last = $paginator->lastPage();
  $cur = $paginator->currentPage();
  $pages = [];
  for ($p = 1; $p <= $last; $p++) {
      if ($p === 1 || $p === $last || abs($p - $cur) <= $win) $pages[] = $p;
  }
  $btn = 'display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 12px;border-radius:999px;font-size:12.5px;font-weight:700;text-decoration:none;font-family:inherit;';
  $idle = 'background:#fff;border:1px solid var(--border);color:var(--text2);';
  $on = 'background:var(--green);border:1px solid var(--green);color:#fff;';
  $off = 'background:var(--bg);border:1px solid var(--border-light);color:var(--text3);cursor:default;';
@endphp
<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;padding:14px 2px 4px;">
  @if($paginator->onFirstPage())
    <span style="{{ $btn.$off }}">‹ Prev</span>
  @else
    <a href="{{ $paginator->previousPageUrl() }}" style="{{ $btn.$idle }}">‹ Prev</a>
  @endif
  @php $prev = 0; @endphp
  @foreach($pages as $p)
    @if($p - $prev > 1)
      <span style="color:var(--text3);">…</span>
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
  <span style="font-size:11.5px;color:var(--text3);margin-left:4px;">Page {{ $cur }} of {{ $last }} · {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }}</span>
</div>
