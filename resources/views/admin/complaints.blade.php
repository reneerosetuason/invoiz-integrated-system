@extends('admin.layout')

@section('content')
@include('admin.partials.list-ui-styles')
<style>
  .admin-complaints{--complaint-ink:#163B43;--complaint-muted:#5C7073;--complaint-rule:rgba(15,76,92,.12);max-width:1280px;margin:0 auto}
  .admin-complaints *{box-sizing:border-box}
  .admin-complaints .admin-list-head{margin-bottom:18px}
  .admin-complaints .admin-list-stats{gap:10px;border:0;background:transparent;overflow:visible}
  .admin-complaints .admin-list-stat{min-height:102px;border:0;border-radius:14px;background:#fff;padding:14px 16px}
  .admin-complaints .admin-list-stat:hover{background:#F6F9F8}
  .admin-complaints .admin-list-stat.is-active{background:#EAF3F2;box-shadow:inset 0 -2px 0 var(--list-deep)}
  .admin-complaints .admin-list-stat strong{font-size:27px}
  .complaint-toolbar{display:grid;grid-template-columns:minmax(0,1fr) minmax(280px,400px);align-items:center;gap:16px;margin-bottom:14px}
  .complaint-filters{display:flex;align-items:center;gap:8px;min-width:0}
  .complaint-table-wrap{overflow:visible}
  .complaint-table{width:100%;border-collapse:collapse;table-layout:fixed}
  .complaint-table th{padding:12px 10px;border-bottom:1px solid var(--complaint-rule);color:var(--complaint-muted);font-size:12px;font-weight:600;letter-spacing:0;text-align:left;text-transform:none;vertical-align:bottom}
  .complaint-table td{min-width:0;padding:14px 10px;border-bottom:1px solid var(--complaint-rule);color:var(--complaint-ink);font-size:13.5px;line-height:1.4;vertical-align:middle;overflow-wrap:anywhere}
  .complaint-table th:first-child,.complaint-table td:first-child{padding-left:16px}
  .complaint-table th:last-child,.complaint-table td:last-child{padding-right:16px}
  .complaint-table tbody tr{transition:background-color .15s ease}
  .complaint-table tbody tr:hover{background:#F7FAF9}
  .complaint-table tbody tr:last-child td{border-bottom:0}
  .complaint-table td.list-empty{padding:32px 16px;color:var(--complaint-muted);text-align:center}
  .complaint-empty-title{color:var(--complaint-ink);font-weight:650}
  .complaint-empty-copy{margin-top:4px;color:var(--complaint-muted);font-size:13px}
  .complaint-cell{display:flex;align-items:center;gap:10px;min-width:0}
  .complaint-cell-copy{min-width:0}
  .complaint-title{font-weight:650;line-height:1.3}
  .complaint-meta{margin-top:3px;color:var(--complaint-muted);font-size:12px;line-height:1.4}
  .complaint-icon{width:34px;height:34px;display:flex;align-items:center;justify-content:center;flex:0 0 34px;border-radius:10px;background:#EAF3F2;color:#0E4A57}
  .complaint-icon.open{background:#F5E9E6;color:#8F4038}
  .complaint-icon.in_review{background:#F4EEDC;color:#795A18}
  .complaint-icon.resolved{background:#EAF3F2;color:#0E4A57}
  .complaint-icon svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:1.7}
  .complaint-type{display:inline-flex;align-items:center;min-height:26px;padding:4px 9px;border-radius:999px;background:#EAF3F2;color:#0E4A57;font-size:12px;font-weight:600;line-height:1.2}
  .complaint-status{display:inline-flex;align-items:center;gap:7px;min-height:28px;padding:5px 10px;border-radius:999px;font-size:12px;font-weight:650;line-height:1.25;white-space:nowrap}
  .complaint-status .dot{width:7px;height:7px;flex:0 0 7px;border-radius:50%;background:currentColor}
  .complaint-status.open{background:#F5E9E6;color:#8F4038}
  .complaint-status.in_review{background:#F4EEDC;color:#795A18}
  .complaint-status.resolved{background:#EAF3F2;color:#0E4A57}
  .complaint-buyer{display:flex;align-items:center;gap:9px;min-width:0}
  .complaint-buyer-copy{min-width:0}
  .complaint-buyer-name{font-weight:600}
  .complaint-buyer-email{margin-top:2px;color:var(--complaint-muted);font-size:12px;overflow-wrap:anywhere}
  .buyer-avatar{width:32px;height:32px;display:flex;flex:0 0 32px;align-items:center;justify-content:center;border-radius:50%;background:#EAF3F2;color:#0E4A57;font-size:12px;font-weight:700}
  .complaint-date{font-weight:600;font-variant-numeric:tabular-nums}
  .complaint-time{margin-top:2px;color:var(--complaint-muted);font-size:12px;font-variant-numeric:tabular-nums}
  .admin-complaints .admin-list-flash{margin-bottom:16px}
  .admin-complaints .admin-list-panel{border-radius:14px}
  @media(max-width:900px){.admin-complaints .admin-list-stat:nth-child(n){border-right:0!important;border-bottom:0!important}}
  @media(max-width:1199.98px){.complaint-toolbar{grid-template-columns:minmax(0,1fr)}}
  @media(max-width:1099.98px){
    .complaint-table{display:block;table-layout:auto}
    .complaint-table thead{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);clip-path:inset(50%);white-space:nowrap}
    .complaint-table tbody{display:grid;gap:10px;padding:10px}
    .complaint-table tr.complaint-record{display:grid;grid-template-columns:minmax(0,1fr) auto;grid-template-areas:'subject status' 'type date' 'buyer buyer' 'actions actions';align-items:start;gap:0 12px;margin:0;padding:10px 12px;border:1px solid var(--complaint-rule);border-radius:12px;background:#fff}
    .complaint-table td{display:block;min-width:0;padding:8px 4px;border:0;font-size:13px}
    .complaint-table td::before{display:block;margin-bottom:4px;color:var(--complaint-muted);content:attr(data-label);font-size:12px;font-weight:600;line-height:1.3}
    .complaint-table td.subject-cell{grid-area:subject}
    .complaint-table td.type-cell{grid-area:type}
    .complaint-table td.buyer-cell{grid-area:buyer}
    .complaint-table td.status-cell{grid-area:status;justify-self:end}
    .complaint-table td.date-cell{grid-area:date;justify-self:end;text-align:right}
    .complaint-table td.actions-cell{grid-area:actions;margin-top:4px;padding:10px 4px 0;border-top:1px solid var(--complaint-rule)}
    .complaint-table td.status-cell::before{display:none}
    .complaint-table td.list-empty{grid-area:auto;padding:25px 12px!important;text-align:center}
    .complaint-table td.list-empty::before{display:none}
  }
  @media(max-width:767.98px){
    .complaint-toolbar{gap:10px}
    .complaint-filters{overflow-x:auto;padding:2px 2px 7px;scroll-snap-type:x mandatory;scrollbar-width:none;-webkit-overflow-scrolling:touch}
    .complaint-filters::-webkit-scrollbar{display:none}
    .complaint-filters .admin-list-filter{flex:0 0 auto;scroll-snap-align:start}
    .admin-complaints .admin-list-stat{min-height:94px;padding:11px}
    .admin-complaints .admin-list-stat strong{font-size:24px}
  }
  @media(prefers-reduced-motion:reduce){.admin-complaints *{scroll-behavior:auto!important;transition:none!important}}
</style>

<main class="admin-list admin-complaints">
  <header class="admin-list-head">
    <h1>Complaints &amp; Disputes</h1>
    <p>Review buyer issues, track resolution progress, and maintain trust in the marketplace.</p>
  </header>

  @if(session('status'))
  <div class="admin-list-flash" role="status">{{ session('status') }}</div>
  @endif

  <section class="admin-list-stats admin-list-stats--4" aria-label="Complaint status summary">
    <a href="{{ url('/admin/complaints') }}" class="admin-list-stat {{ !request('status') ? 'is-active' : '' }}">
      <span class="admin-list-stat-label">All Complaints</span><strong>{{ $stats['all'] }}</strong><small>total</small>
    </a>
    <a href="{{ url('/admin/complaints?status=open') }}" class="admin-list-stat {{ request('status')==='open' ? 'is-active' : '' }}">
      <span class="admin-list-stat-label">Open</span><strong>{{ $stats['open'] }}</strong><small>needs attention</small>
    </a>
    <a href="{{ url('/admin/complaints?status=in_review') }}" class="admin-list-stat {{ request('status')==='in_review' ? 'is-active' : '' }}">
      <span class="admin-list-stat-label">In Review</span><strong>{{ $stats['in_review'] }}</strong><small>under investigation</small>
    </a>
    <a href="{{ url('/admin/complaints?status=resolved') }}" class="admin-list-stat {{ request('status')==='resolved' ? 'is-active' : '' }}">
      <span class="admin-list-stat-label">Resolved</span><strong>{{ $stats['resolved'] }}</strong><small>closed cases</small>
    </a>
  </section>

  <div class="complaint-toolbar">
    <nav class="complaint-filters" aria-label="Filter complaints by status">
      <a href="{{ url('/admin/complaints'.(request('search')?'?search='.request('search'):'')) }}" class="admin-list-filter {{ !request('status') ? 'is-active' : '' }}">All</a>
      <a href="{{ url('/admin/complaints?status=open'.(request('search')?'&search='.request('search'):'')) }}" class="admin-list-filter {{ request('status')==='open' ? 'is-active' : '' }}">Open</a>
      <a href="{{ url('/admin/complaints?status=in_review'.(request('search')?'&search='.request('search'):'')) }}" class="admin-list-filter {{ request('status')==='in_review' ? 'is-active' : '' }}">In Review</a>
      <a href="{{ url('/admin/complaints?status=resolved'.(request('search')?'&search='.request('search'):'')) }}" class="admin-list-filter {{ request('status')==='resolved' ? 'is-active' : '' }}">Resolved</a>
    </nav>

    <form method="GET" class="admin-list-search">
      @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
      <div class="admin-list-search-field">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
        <input type="search" name="search" value="{{ request('search') }}" placeholder="Search by subject, type or buyer..." aria-label="Search complaints" />
        <button type="submit">Search</button>
      </div>
      @if(request('search') || request('status'))<a href="{{ url('/admin/complaints') }}" class="admin-list-clear">Clear filters</a>@endif
    </form>
  </div>

  <div class="admin-list-panel complaint-table-wrap">
    <table class="complaint-table">
      <thead><tr><th>Subject</th><th>Type</th><th>Buyer</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody>
        @forelse($complaints as $complaint)
          @php $st = $complaint->status; @endphp
        <tr class="complaint-record">
          <td class="subject-cell" data-label="Subject">
            <div class="complaint-cell">
              <div class="complaint-icon {{ $st }}">
                @if($st==='open')<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 9v4"/><path d="M12 17h.01"/><circle cx="12" cy="12" r="9"/></svg>
                @elseif($st==='in_review')<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
                @else<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="9"/></svg>
                @endif
              </div>
              <div class="complaint-cell-copy">
                <div class="complaint-title">{{ $complaint->subject }}</div>
                <div class="complaint-meta">#{{ $complaint->id }} · {{ Str::limit($complaint->description ?? '', 40) }}</div>
              </div>
            </div>
          </td>
          <td class="type-cell" data-label="Type"><span class="complaint-type">{{ ucfirst($complaint->type) }}</span></td>
          <td class="buyer-cell" data-label="Buyer">
            <div class="complaint-buyer">
              <div class="buyer-avatar">{{ strtoupper(mb_substr($complaint->buyer->name ?? 'B',0,1)) }}</div>
              <div class="complaint-buyer-copy">
                <div class="complaint-buyer-name">{{ $complaint->buyer->name ?? 'N/A' }}</div>
                <div class="complaint-buyer-email">{{ $complaint->buyer->email ?? '' }}</div>
              </div>
            </div>
          </td>
          <td class="status-cell" data-label="Status"><span class="complaint-status {{ $st }}"><span class="dot"></span>{{ ucfirst(str_replace('_',' ', $st)) }}</span></td>
          <td class="date-cell" data-label="Date">
            <div class="complaint-date">{{ $complaint->created_at->format('M d, Y') }}</div>
            <div class="complaint-time">{{ $complaint->created_at->format('h:i A') }}</div>
          </td>
          <td class="actions-cell" data-label="Actions">
            <a href="{{ url('/admin/complaints/'.$complaint->id) }}" class="admin-list-action">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>
              View
            </a>
          </td>
        </tr>
        @empty
        <tr><td colspan="6" class="list-empty">
          <div class="complaint-empty-title">No complaints found</div>
          <div class="complaint-empty-copy">Try adjusting your search or status filter.</div>
        </td></tr>
        @endforelse
      </tbody>
    </table>
    @include('admin.partials.pager', ['paginator' => $complaints])
  </div>
</main>
@endsection
