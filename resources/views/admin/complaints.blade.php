@extends('admin.layout')

@section('content')
<style>
  .complaint-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:18px; }
  .c-stat { background:#fff; border:1px solid var(--border); border-radius:14px; padding:14px 16px; text-align:center; text-decoration:none; color:inherit; display:block; transition:transform .15s, box-shadow .15s; }
  .c-stat:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(16,24,40,.08); }
  .c-stat.active { background:#16697A; color:#fff; border-color:#16697A; }
  .c-stat.active span, .c-stat.active strong, .c-stat.active small { color:#fff; }
  .c-stat span { display:block; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:var(--text-secondary); }
  .c-stat strong { display:block; font-size:22px; font-weight:800; margin-top:4px; }
  .c-stat small { font-size:11px; color:var(--text-secondary); }
  .c-stat.active small { color:rgba(255,255,255,.7); }
  .filter-bar { display:flex; gap:7px; flex-wrap:wrap; margin-bottom:14px; }
  .filter-pill { padding:6px 12px; border-radius:999px; font-size:12.5px; font-weight:700; border:1px solid var(--border); background:#fff; color:#374151; text-decoration:none; transition:all .15s; }
  .filter-pill:hover { border-color:#16697A; color:#16697A; }
  .filter-pill.active { background:#16697A; color:#fff; border-color:#16697A; }
  .complaint-cell { display:flex; align-items:center; gap:10px; }
  .complaint-icon { width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
  .complaint-icon.open { background:#FEE2E2; color:#991B1B; }
  .complaint-icon.in_review { background:#FEF3C7; color:#92400e; }
  .complaint-icon.resolved { background:#E8F5EE; color:#166534; }
  .complaint-icon svg { width:18px; height:18px; stroke:currentColor; fill:none; stroke-width:1.8; }
  .type-badge { display:inline-flex; align-items:center; gap:4px; background:#EAF4F3; color:#0E4A57; padding:3px 8px; border-radius:999px; font-size:11px; font-weight:700; border:1px solid #CBE3E1; }
  .status-badge { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; font-size:11.5px; font-weight:700; border:1px solid; }
  .status-open { background:#FEE2E2; color:#991B1B; border-color:#FECACA; }
  .status-open .dot{ background:#DC2626; } .status-in_review{ background:#FEF3C7; color:#92400e; border-color:#FDE68A; } .status-in_review .dot{ background:#F59E0B; }
  .status-resolved{ background:#E8F5EE; color:#166534; border-color:#BFE3D0; } .status-resolved .dot{ background:#2E8B57; }
  .dot{ width:7px; height:7px; border-radius:50%; display:inline-block; }
  .buyer-avatar { width:32px; height:32px; border-radius:50%; background:linear-gradient(135deg,#16697A 0%,#1A9CB0 100%); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:11px; flex-shrink:0; }
  @media(max-width:900px){ .complaint-stats{ grid-template-columns:repeat(2,1fr); } }
</style>

<div class="card">
  <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:4px;">
    <div>
      <h1 class="page-title" style="margin-bottom:4px;">Complaints & Disputes</h1>
      <p style="color:var(--text-secondary); font-size:13.5px; margin:0;">Review buyer issues, track resolution progress, and maintain trust in the marketplace.</p>
    </div>
  </div>

  @if(session('status'))
  <div style="padding:12px 16px;background:#E8F5EE;color:#166534;border-radius:12px;margin:16px 0 0;border:1px solid #BFE3D0;">{{ session('status') }}</div>
  @endif

  <div class="complaint-stats" style="margin-top:18px;">
    <a href="{{ url('/admin/complaints') }}" class="c-stat {{ !request('status') ? 'active' : '' }}">
      <span>All Complaints</span><strong>{{ $stats['all'] }}</strong><small>total</small>
    </a>
    <a href="{{ url('/admin/complaints?status=open') }}" class="c-stat {{ request('status')==='open' ? 'active' : '' }}" style="border-left:3px solid #DC2626;">
      <span>Open</span><strong style="color:#991B1B;">{{ $stats['open'] }}</strong><small>needs attention</small>
    </a>
    <a href="{{ url('/admin/complaints?status=in_review') }}" class="c-stat {{ request('status')==='in_review' ? 'active' : '' }}" style="border-left:3px solid #F59E0B;">
      <span>In Review</span><strong style="color:#92400e;">{{ $stats['in_review'] }}</strong><small>under investigation</small>
    </a>
    <a href="{{ url('/admin/complaints?status=resolved') }}" class="c-stat {{ request('status')==='resolved' ? 'active' : '' }}" style="border-left:3px solid #2E8B57;">
      <span>Resolved</span><strong style="color:#166534;">{{ $stats['resolved'] }}</strong><small>closed cases</small>
    </a>
  </div>

  <form method="GET" style="display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap;">
    @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    <div style="position:relative; flex:1; min-width:220px;">
      <svg style="position:absolute; left:12px; top:50%; transform:translateY(-50%); width:16px; height:16px; stroke:#9CA3AF; fill:none; stroke-width:1.8;" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
      <input type="search" name="search" value="{{ request('search') }}" placeholder="Search by subject, type or buyer..." style="padding:9px 14px 9px 36px;border:1px solid #d1d5db;border-radius:10px;width:100%;background:#fff;" />
    </div>
    <button type="submit" style="padding:9px 18px;background:#16697A;color:white;border:none;border-radius:10px;cursor:pointer;font-weight:700;">Search</button>
    @if(request('search') || request('status'))<a href="{{ url('/admin/complaints') }}" style="align-self:center;color:#6E6E73;font-size:13px;font-weight:600;">Clear filters</a>@endif
  </form>

  <div class="filter-bar">
    <a href="{{ url('/admin/complaints'.(request('search')?'?search='.request('search'):'')) }}" class="filter-pill {{ !request('status') ? 'active' : '' }}">All</a>
    <a href="{{ url('/admin/complaints?status=open'.(request('search')?'&search='.request('search'):'')) }}" class="filter-pill {{ request('status')==='open' ? 'active' : '' }}">Open</a>
    <a href="{{ url('/admin/complaints?status=in_review'.(request('search')?'&search='.request('search'):'')) }}" class="filter-pill {{ request('status')==='in_review' ? 'active' : '' }}">In Review</a>
    <a href="{{ url('/admin/complaints?status=resolved'.(request('search')?'&search='.request('search'):'')) }}" class="filter-pill {{ request('status')==='resolved' ? 'active' : '' }}">Resolved</a>
  </div>

  <div style="overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;min-width:760px;">
    <thead>
      <tr style="border-bottom:2px solid #E8E6E0;text-align:left; background:#FAFAF8;">
        <th style="padding:11px 12px; font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase; letter-spacing:.6px;">Subject</th>
        <th style="padding:11px 12px; font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase; letter-spacing:.6px;">Type</th>
        <th style="padding:11px 12px; font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase; letter-spacing:.6px;">Buyer</th>
        <th style="padding:11px 12px; font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase; letter-spacing:.6px;">Status</th>
        <th style="padding:11px 12px; font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase; letter-spacing:.6px;">Date</th>
        <th style="padding:11px 12px; font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase; letter-spacing:.6px;">Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse($complaints as $complaint)
        @php $st = $complaint->status; @endphp
      <tr style="border-bottom:1px solid #F1F2F4; transition:background .12s;" onmouseover="this.style.background='#FAFAF8'" onmouseout="this.style.background=''">
        <td style="padding:12px;">
          <div class="complaint-cell">
            <div class="complaint-icon {{ $st }}">
              @if($st==='open')<svg viewBox="0 0 24 24"><path d="M12 9v4"/><path d="M12 17h.01"/><circle cx="12" cy="12" r="9"/></svg>
              @elseif($st==='in_review')<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
              @else<svg viewBox="0 0 24 24"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="9"/></svg>
              @endif
            </div>
            <div>
              <div style="font-weight:700; font-size:13.5px; line-height:1.2;">{{ $complaint->subject }}</div>
              <div style="font-size:11px; color:#9CA3AF; margin-top:1px;">#{{ $complaint->id }} · {{ Str::limit($complaint->description ?? '', 40) }}</div>
            </div>
          </div>
        </td>
        <td style="padding:12px 8px;">
          <span class="type-badge">{{ ucfirst($complaint->type) }}</span>
        </td>
        <td style="padding:12px 8px;">
          <div style="display:flex; align-items:center; gap:8px;">
            <div class="buyer-avatar">{{ strtoupper(mb_substr($complaint->buyer->name ?? 'B',0,1)) }}</div>
            <div>
              <div style="font-weight:600; font-size:13px;">{{ $complaint->buyer->name ?? 'N/A' }}</div>
              <div style="font-size:11px; color:#9CA3AF;">{{ $complaint->buyer->email ?? '' }}</div>
            </div>
          </div>
        </td>
        <td style="padding:12px 8px;">
          <span class="status-badge status-{{ $st }}"><span class="dot"></span> {{ ucfirst(str_replace('_',' ', $st)) }}</span>
        </td>
        <td style="padding:12px 8px;">
          <div style="font-size:12.5px; font-weight:600; color:#374151;">{{ $complaint->created_at->format('M d, Y') }}</div>
          <div style="font-size:11px; color:#9CA3AF;">{{ $complaint->created_at->format('h:i A') }}</div>
        </td>
        <td style="padding:12px 8px;">
          <a href="{{ url('/admin/complaints/'.$complaint->id) }}" style="display:inline-flex; align-items:center; gap:5px; padding:6px 12px; background:#fff; color:#374151; border:1px solid #E5E7EB; border-radius:8px; text-decoration:none; font-size:12px; font-weight:700;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#6B7280" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>
            View
          </a>
        </td>
      </tr>
      @empty
      <tr><td colspan="6" style="padding:36px;text-align:center;">
        <div style="width:48px;height:48px;border-radius:50%;background:#F3F4F6;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.6"><path d="M12 9v4"/><circle cx="12" cy="12" r="9"/></svg>
        </div>
        <div style="font-weight:600; color:#6B7280;">No complaints found</div>
        <div style="font-size:13px; color:#9CA3AF; margin-top:4px;">Try adjusting your search or status filter.</div>
      </td></tr>
      @endforelse
    </tbody>
  </table>
  </div>
</div>
@endsection
