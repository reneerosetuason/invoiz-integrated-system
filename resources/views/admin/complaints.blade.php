@extends('admin.layout')

@section('content')
@include('admin.partials.list-ui-styles')
<link rel="stylesheet" href="{{ asset('assets/admin/css/complaints.css') }}">

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
