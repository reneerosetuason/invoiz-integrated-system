@extends('seller.layout')

@section('content')
<style>
  .fb-header { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:18px; }
  .fb-title { font-size:20px; font-weight:800; letter-spacing:-.4px; margin:0; }
  .fb-sub { font-size:13px; color:#6B7280; margin-top:4px; }
  .fb-badge { background:#116B78; color:#fff; font-size:12px; font-weight:700; padding:8px 14px; border-radius:999px; white-space:nowrap; }
  .grid-2 { display:grid; grid-template-columns: 360px 1fr; gap:16px; align-items:start; }
  .panel { background:#fff; border:1px solid #E5E7EB; border-radius:18px; padding:22px; box-shadow:0 1px 4px rgba(0,0,0,.04); }
  .panel-title { font-size:15px; font-weight:800; letter-spacing:-.3px; margin:0 0 18px; display:flex; align-items:center; gap:8px; }
  .panel-title svg { width:18px; height:18px; stroke:#116B78; fill:none; stroke-width:1.8; }
  /* Rating hero */
  .rating-hero { text-align:center; padding:18px 12px 20px; background:linear-gradient(180deg,#F0FAFA 0%,#FFF 100%); border:1px solid #E6F1F2; border-radius:14px; margin-bottom:18px; }
  .rating-circle { width:84px; height:84px; border-radius:50%; background:linear-gradient(135deg,#116B78 0%,#0C555F 100%); color:#fff; display:flex; align-items:center; justify-content:center; font-size:30px; font-weight:800; margin:0 auto 10px; box-shadow:0 8px 20px rgba(17,107,120,.22); }
  .rating-label { font-size:13px; font-weight:700; color:#116B78; text-transform:uppercase; letter-spacing:.8px; margin-bottom:4px; }
  .stars { display:flex; align-items:center; justify-content:center; gap:3px; }
  .stars svg { width:20px; height:20px; }
  .stars svg.filled { fill:#F5A623; stroke:#F5A623; }
  .stars svg.empty { fill:#E5E7EB; stroke:#E5E7EB; }
  .sentiment { display:inline-flex; align-items:center; gap:6px; margin-top:10px; font-size:12px; font-weight:700; padding:6px 12px; border-radius:999px; }
  .sentiment.excellent { background:#E6F5EE; color:#166534; border:1px solid #BFE3D0; }
  .sentiment.great { background:#E0F2FE; color:#0C4A6E; border:1px solid #BAE6FD; }
  .sentiment.good { background:#FEF3C7; color:#92400E; border:1px solid #FDE68A; }
  .sentiment.poor { background:#FEE2E2; color:#991B1B; border:1px solid #FECACA; }
  .dist-row { display:flex; align-items:center; gap:10px; margin:9px 0; }
  .dist-label { font-size:12px; font-weight:700; color:#374151; min-width:32px; display:flex; align-items:center; gap:3px; }
  .dist-bar { flex:1; height:8px; background:#F1F2F4; border-radius:999px; overflow:hidden; }
  .dist-fill { height:100%; background:linear-gradient(90deg,#F5A623 0%,#F59E0B 100%); border-radius:999px; transition:width .6s ease; }
  .dist-count { font-size:12px; color:#6B7280; min-width:36px; text-align:right; }
  .dist-pct { font-size:11px; color:#9CA3AF; min-width:32px; text-align:right; }
  /* Filter bar */
  .filter-bar { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:16px; flex-wrap:wrap; }
  .filter-pills { display:flex; gap:6px; flex-wrap:wrap; }
  .pill { padding:6px 12px; border-radius:999px; font-size:12px; font-weight:700; cursor:pointer; border:1px solid #E5E7EB; background:#fff; color:#374151; transition:all .15s; }
  .pill.active { background:#116B78; color:#fff; border-color:#116B78; }
  .pill:hover { border-color:#116B78; }
  .sort-select { padding:7px 12px; border:1px solid #D8DBDF; border-radius:10px; font-size:12px; font-weight:600; color:#374151; background:#fff; }
  /* Review card */
  .rev-card { background:#fff; border:1px solid #E5E7EB; border-radius:16px; padding:16px; margin-bottom:12px; transition:transform .15s, box-shadow .15s, border-color .15s; }
  .rev-card:hover { transform:translateY(-1px); box-shadow:0 8px 24px rgba(0,0,0,.06); border-color:#D1D5DB; }
  .rev-card.hidden { display:none; }
  .rev-head { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:10px; }
  .rev-user { display:flex; align-items:center; gap:10px; min-width:0; }
  .avatar { width:40px; height:40px; border-radius:50%; background:linear-gradient(135deg,#E6F1F2 0%,#D1E9EB 100%); color:#116B78; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:14px; border:2px solid #fff; box-shadow:0 2px 8px rgba(17,107,120,.12); flex-shrink:0; }
  .rev-name { font-weight:700; font-size:14px; line-height:1.2; }
  .rev-meta { font-size:12px; color:#6B7280; display:flex; align-items:center; gap:6px; flex-wrap:wrap; margin-top:2px; }
  .verified { display:inline-flex; align-items:center; gap:4px; background:#E6F5EE; color:#166534; font-size:11px; font-weight:700; padding:2px 7px; border-radius:999px; border:1px solid #BFE3D0; }
  .verified svg { width:12px; height:12px; fill:#166534; }
  .stars-sm { display:flex; gap:2px; flex-shrink:0; }
  .stars-sm svg { width:14px; height:14px; }
  .stars-sm svg.filled { fill:#F5A623; stroke:#F5A623; }
  .stars-sm svg.empty { fill:#E5E7EB; stroke:#E5E7EB; }
  .rev-product { display:flex; align-items:center; gap:10px; background:#F8F9FA; border:1px solid #F1F2F4; border-radius:10px; padding:8px 10px; margin-bottom:10px; }
  .prod-thumb { width:36px; height:36px; border-radius:8px; background:linear-gradient(135deg,#116B78 0%,#1A9CB0 100%); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:13px; flex-shrink:0; }
  .prod-info { min-width:0; }
  .prod-name { font-size:13px; font-weight:700; color:#1A202C; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .prod-cat { font-size:11px; color:#9CA3AF; }
  .rev-bubble { background:#F9FAFB; border:1px solid #F1F2F4; border-radius:12px; padding:12px 14px; position:relative; }
  .rev-bubble::before { content:'“'; position:absolute; top:6px; left:10px; font-size:20px; color:#D1D5DB; font-family:Georgia, serif; line-height:1; }
  .rev-text { color:#1F2937; font-size:13.5px; line-height:1.6; padding-left:14px; }
  .rev-text.empty { color:#9CA3AF; font-style:italic; }
  .rev-footer { display:flex; align-items:center; gap:8px; margin-top:10px; }
  .tag { font-size:11px; font-weight:700; padding:4px 8px; border-radius:999px; }
  .tag.positive { background:#E6F5EE; color:#166534; }
  .tag.neutral { background:#FEF3C7; color:#92400E; }
  .tag.critical { background:#FEE2E2; color:#991B1B; }
  .tag.order { background:#F3F4F6; color:#6B7280; font-weight:600; }
  .empty-state { text-align:center; padding:48px 20px; }
  .empty-icon { width:64px; height:64px; border-radius:50%; background:#F3F4F6; display:flex; align-items:center; justify-content:center; margin:0 auto 14px; }
  .empty-icon svg { width:28px; height:28px; stroke:#9CA3AF; fill:none; stroke-width:1.6; }
  .pagination { margin-top:18px; display:flex; justify-content:center; }
  .pagination a, .pagination span { color:#116B78; margin:0 4px; font-weight:600; font-size:13px; text-decoration:none; padding:6px 10px; border-radius:8px; border:1px solid transparent; }
  .pagination .active span { background:#116B78; color:#fff; border-color:#116B78; }
  @media (max-width: 900px) { .grid-2 { grid-template-columns:1fr; } }
</style>

@php
  $avg = (float)($rating ?? 0);
  $sentimentClass = $avg >= 4.5 ? 'excellent' : ($avg >= 4.0 ? 'great' : ($avg >= 3.0 ? 'good' : 'poor'));
  $sentimentLabel = $avg >= 4.5 ? 'Excellent' : ($avg >= 4.0 ? 'Great' : ($avg >= 3.0 ? 'Good' : ($avg > 0 ? 'Needs improvement' : 'No ratings yet')));
  $fivePct = $total > 0 ? round((($distribution[5] ?? 0) / $total) * 100) : 0;
@endphp

<div class="fb-header">
  <div>
    <h2 class="fb-title">Customer Feedback</h2>
    <div class="fb-sub">What buyers say about your store and products — build trust with future customers</div>
  </div>
  <span class="fb-badge">{{ $total }} {{ $total === 1 ? 'review' : 'reviews' }} · {{ number_format($avg, 1) }}/5</span>
</div>

<div class="grid-2">
  <!-- Rating Summary -->
  <div class="panel">
    <h3 class="panel-title">
      <svg viewBox="0 0 24 24"><path d="M12 3l2.4 4.9 5.4.8-3.9 3.8.9 5.4-4.8-2.5-4.8 2.5.9-5.4L4.2 8.7l5.4-.8L12 3Z"/></svg>
      Rating Summary
    </h3>

    <div class="rating-hero">
      <div class="rating-circle">{{ $total > 0 ? number_format($avg, 1) : '—' }}</div>
      <div class="stars">
        @for ($i = 1; $i <= 5; $i++)
          @if ($i <= round($avg))
            <svg class="filled" viewBox="0 0 24 24"><path d="M12 3l2.4 4.9 5.4.8-3.9 3.8.9 5.4-4.8-2.5-4.8 2.5.9-5.4L4.2 8.7l5.4-.8L12 3Z"/></svg>
          @else
            <svg class="empty" viewBox="0 0 24 24"><path d="M12 3l2.4 4.9 5.4.8-3.9 3.8.9 5.4-4.8-2.5-4.8 2.5.9-5.4L4.2 8.7l5.4-.8L12 3Z"/></svg>
          @endif
        @endfor
      </div>
      <div style="font-size:12px; color:#6B7280; margin-top:6px;">Based on {{ $total }} verified purchases</div>
      <span class="sentiment {{ $sentimentClass }}">{{ $sentimentLabel }}</span>
      @if ($total > 0)
        <div style="margin-top:12px; font-size:12px; color:#374151; background:#fff; border:1px solid #E5E7EB; border-radius:999px; display:inline-flex; gap:12px; padding:6px 12px;">
          <span><b>{{ $fivePct }}%</b> 5-star</span>
          <span style="color:#D1D5DB;">·</span>
          <span><b>{{ $total }}</b> total</span>
        </div>
      @endif
    </div>

    @foreach (array_reverse($distribution, true) as $star => $count)
      @php $pct = $total > 0 ? round(($count / $total) * 100) : 0; @endphp
      <div class="dist-row">
        <span class="dist-label">{{ $star }} <svg width="12" height="12" viewBox="0 0 24 24" style="fill:#F5A623; stroke:#F5A623;"><path d="M12 3l2.4 4.9 5.4.8-3.9 3.8.9 5.4-4.8-2.5-4.8 2.5.9-5.4L4.2 8.7l5.4-.8L12 3Z"/></svg></span>
        <div class="dist-bar"><div class="dist-fill" style="width:{{ $pct }}%"></div></div>
        <span class="dist-count">{{ $count }}</span>
        <span class="dist-pct">{{ $pct }}%</span>
      </div>
    @endforeach

    <div style="margin-top:16px; padding:12px; background:#F0FAFA; border:1px solid #E6F1F2; border-radius:10px; font-size:12px; color:#0C555F; line-height:1.5;">
      <b style="color:#116B78;">Tip:</b> Reply to reviews and maintain a 4-plus <x-ui-icon name="star" :size="12" /> rating to boost visibility in search and increase buyer trust.
    </div>
  </div>

  <!-- Reviews List -->
  <div class="panel">
    <h3 class="panel-title">
      <svg viewBox="0 0 24 24"><path d="M21 11.5a8.5 8.5 0 0 1-12.4 7.5L3 21l2-5.6A8.5 8.5 0 1 1 21 11.5Z"/><path d="M8 10h.01M12 10h.01M16 10h.01"/></svg>
      Reviews
      <span style="margin-left:auto; font-size:12px; font-weight:600; color:#6B7280; background:#F3F4F6; padding:4px 10px; border-radius:999px;">{{ $reviews->total() }} total</span>
    </h3>

    <div class="filter-bar">
      <div class="filter-pills" id="filterPills">
        <button class="pill active" data-filter="all">All</button>
        <button class="pill" data-filter="5">5 <x-ui-icon name="star" :size="11" /></button>
        <button class="pill" data-filter="4">4 <x-ui-icon name="star" :size="11" /></button>
        <button class="pill" data-filter="3">3 <x-ui-icon name="star" :size="11" /></button>
        <button class="pill" data-filter="2">2 <x-ui-icon name="star" :size="11" /></button>
        <button class="pill" data-filter="1">1 <x-ui-icon name="star" :size="11" /></button>
      </div>
      <select class="sort-select" id="sortSelect">
        <option value="latest">Latest first</option>
        <option value="highest">Highest rated</option>
        <option value="lowest">Lowest rated</option>
      </select>
    </div>

    <div id="revList">
      @forelse ($reviews as $r)
        @php
          $tagClass = $r->rating >= 4 ? 'positive' : ($r->rating == 3 ? 'neutral' : 'critical');
          $tagLabel = $r->rating >= 4 ? 'Positive experience' : ($r->rating == 3 ? 'Average' : 'Needs attention');
        @endphp
        <div class="rev-card" data-rating="{{ $r->rating }}" data-date="{{ $r->created_at->timestamp }}">
          <div class="rev-head">
            <div class="rev-user">
              <div class="avatar">{{ strtoupper(mb_substr($r->buyer->name ?? 'B', 0, 1)) }}</div>
              <div>
                <div class="rev-name">{{ $r->buyer->name ?? 'Buyer' }}</div>
                <div class="rev-meta">
                  {{ $r->created_at->format('M d, Y') }}
                  <span class="verified"><svg viewBox="0 0 24 24"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"/></svg> Verified purchase</span>
                </div>
              </div>
            </div>
            <div class="stars-sm" title="{{ $r->rating }}/5">
              @for ($i = 1; $i <= 5; $i++)
                @if ($i <= $r->rating)
                  <svg class="filled" viewBox="0 0 24 24"><path d="M12 3l2.4 4.9 5.4.8-3.9 3.8.9 5.4-4.8-2.5-4.8 2.5.9-5.4L4.2 8.7l5.4-.8L12 3Z"/></svg>
                @else
                  <svg class="empty" viewBox="0 0 24 24"><path d="M12 3l2.4 4.9 5.4.8-3.9 3.8.9 5.4-4.8-2.5-4.8 2.5.9-5.4L4.2 8.7l5.4-.8L12 3Z"/></svg>
                @endif
              @endfor
            </div>
          </div>

          <div class="rev-product">
            <div class="prod-thumb">{{ strtoupper(mb_substr($r->product->name ?? 'P', 0, 1)) }}</div>
            <div class="prod-info">
              <div class="prod-name">{{ $r->product->name ?? 'Product' }}</div>
              <div class="prod-cat">{{ $r->product->category->name ?? 'General' }} · ₱{{ number_format($r->product->price ?? 0, 2) }}</div>
            </div>
            <span style="margin-left:auto; font-size:11px; font-weight:700; color:#116B78; background:#E6F1F2; padding:4px 8px; border-radius:999px;">{{ $r->rating }}/5</span>
          </div>

          <div class="rev-bubble">
            <div class="rev-text {{ empty($r->review) ? 'empty' : '' }}">{{ $r->review ?: 'No written review — buyer left a star rating only.' }}</div>
          </div>

          <div class="rev-footer">
            <span class="tag {{ $tagClass }}">{{ $tagLabel }}</span>
            @if($r->order_id)
              <span class="tag order">Order #{{ $r->order_id }}</span>
            @endif
            <span style="margin-left:auto; font-size:11px; color:#9CA3AF;">{{ $r->created_at->diffForHumans() }}</span>
          </div>
        </div>
      @empty
        <div class="empty-state">
          <div class="empty-icon">
            <svg viewBox="0 0 24 24"><path d="M21 11.5a8.5 8.5 0 0 1-12.4 7.5L3 21l2-5.6A8.5 8.5 0 1 1 21 11.5Z"/><path d="M8 10h.01M12 10h.01M16 10h.01"/></svg>
          </div>
          <div style="font-weight:700; font-size:15px; color:#1A202C;">No customer feedback yet</div>
          <div style="font-size:13px; color:#6B7280; margin-top:6px; max-width:320px; margin-left:auto; margin-right:auto;">When buyers leave ratings and reviews for your products, they'll appear here. Great reviews build trust and boost sales.</div>
        </div>
      @endforelse
    </div>

    <div id="noFilterResult" style="display:none; text-align:center; padding:32px 0; color:#6B7280; font-size:13px;">No reviews match this filter.</div>

    <div class="pagination">{{ $reviews->links() }}</div>
  </div>
</div>

<script>
  (function () {
    var pills = document.querySelectorAll('#filterPills .pill');
    var cards = document.querySelectorAll('#revList .rev-card');
    var noResult = document.getElementById('noFilterResult');
    var sortSelect = document.getElementById('sortSelect');
    var list = document.getElementById('revList');

    pills.forEach(function (pill) {
      pill.addEventListener('click', function () {
        pills.forEach(function (p) { p.classList.remove('active'); });
        pill.classList.add('active');
        var f = pill.getAttribute('data-filter');
        var visible = 0;
        cards.forEach(function (c) {
          var show = f === 'all' || c.getAttribute('data-rating') === f;
          c.classList.toggle('hidden', !show);
          if (show) visible++;
        });
        noResult.style.display = visible === 0 && cards.length > 0 ? 'block' : 'none';
      });
    });

    sortSelect.addEventListener('change', function () {
      var mode = sortSelect.value;
      var arr = Array.from(cards);
      arr.sort(function (a, b) {
        if (mode === 'highest') return parseInt(b.getAttribute('data-rating')) - parseInt(a.getAttribute('data-rating'));
        if (mode === 'lowest') return parseInt(a.getAttribute('data-rating')) - parseInt(b.getAttribute('data-rating'));
        return parseInt(b.getAttribute('data-date')) - parseInt(a.getAttribute('data-date'));
      });
      arr.forEach(function (c) { list.appendChild(c); });
    });
  })();
</script>
@endsection
