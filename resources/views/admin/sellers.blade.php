@extends('admin.layout')

@section('content')
<style>
  .seller-stats { display:grid; grid-template-columns:repeat(5,1fr); gap:12px; margin-bottom:18px; }
  .stat-card { background:#fff; border:1px solid var(--border); border-radius:14px; padding:14px 16px; text-align:center; transition:transform .15s, box-shadow .15s; cursor:pointer; text-decoration:none; color:inherit; display:block; }
  .stat-card:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(16,24,40,.08); }
  .stat-card.active { background:#16697A; color:#fff; border-color:#16697A; }
  .stat-card.active span, .stat-card.active strong { color:#fff; }
  .stat-card span { display:block; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:var(--text-secondary); }
  .stat-card strong { display:block; font-size:22px; font-weight:800; margin-top:4px; }
  .stat-card small { font-size:11px; color:var(--text-secondary); }
  .stat-card.active small { color:rgba(255,255,255,.7); }
  .filter-bar { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; }
  .filter-pill { padding:7px 14px; border-radius:999px; font-size:12.5px; font-weight:700; border:1px solid var(--border); background:#fff; color:#374151; text-decoration:none; transition:all .15s; }
  .filter-pill:hover { border-color:#16697A; color:#16697A; }
  .filter-pill.active { background:#16697A; color:#fff; border-color:#16697A; }
  .store-cell { display:flex; align-items:center; gap:10px; }
  .store-avatar { width:38px; height:38px; border-radius:10px; background:linear-gradient(135deg,#16697A 0%,#1A9CB0 100%); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:14px; flex-shrink:0; overflow:hidden; border:1px solid var(--border); }
  .store-avatar img { width:100%; height:100%; object-fit:cover; }
  .store-name { font-weight:700; font-size:13.5px; line-height:1.2; }
  .store-owner { font-size:11.5px; color:var(--text-secondary); }
  .prod-count { display:inline-flex; align-items:center; gap:4px; background:#F0FAFA; border:1px solid #E6F1F2; color:#16697A; padding:3px 8px; border-radius:999px; font-size:11.5px; font-weight:700; }
  @media (max-width: 900px) { .seller-stats { grid-template-columns:repeat(2,1fr); } }
</style>

<div class="card">
  <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:6px;">
    <div>
      <h1 class="page-title" style="margin-bottom:4px;">Manage Sellers</h1>
      <p style="color:var(--text-secondary); font-size:13.5px; margin:0;">Review seller applications, monitor compliance, and manage store accounts. Suspend sellers that violate category or platform policies.</p>
    </div>
  </div>

  @if(session('status'))
  <div style="padding:12px 16px;background:#E8F5EE;color:#166534;border-radius:12px;margin:16px 0 0;border:1px solid #BFE3D0;">{{ session('status') }}</div>
  @endif

  <div class="seller-stats" style="margin-top:18px;">
    <a href="{{ url('/admin/sellers') }}" class="stat-card {{ !request('status') ? 'active' : '' }}">
      <span>All Sellers</span><strong>{{ $stats['all'] }}</strong><small>total</small>
    </a>
    <a href="{{ url('/admin/sellers?status=pending') }}" class="stat-card {{ request('status')==='pending' ? 'active' : '' }}" style="border-left:3px solid #F0A202;">
      <span>Pending</span><strong style="color:#92400e;">{{ $stats['pending'] }}</strong><small>awaiting review</small>
    </a>
    <a href="{{ url('/admin/sellers?status=approved') }}" class="stat-card {{ request('status')==='approved' ? 'active' : '' }}" style="border-left:3px solid #2E8B57;">
      <span>Approved</span><strong style="color:#166534;">{{ $stats['approved'] }}</strong><small>active stores</small>
    </a>
    <a href="{{ url('/admin/sellers?status=suspended') }}" class="stat-card {{ request('status')==='suspended' ? 'active' : '' }}" style="border-left:3px solid #E05A33;">
      <span>Suspended</span><strong style="color:#991b1b;">{{ $stats['suspended'] }}</strong><small>compliance</small>
    </a>
    <a href="{{ url('/admin/sellers?status=rejected') }}" class="stat-card {{ request('status')==='rejected' ? 'active' : '' }}" style="border-left:3px solid #6E6E73;">
      <span>Rejected</span><strong style="color:#6E6E73;">{{ $stats['rejected'] }}</strong><small>declined</small>
    </a>
  </div>

  <form method="GET" style="display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap;">
    @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    <input type="search" name="search" value="{{ request('search') }}" placeholder="Search by store, owner or email..." style="padding:9px 14px;border:1px solid #d1d5db;border-radius:10px;flex:1;min-width:220px;background:#fff;" />
    <button type="submit" style="padding:9px 18px;background:#16697A;color:white;border:none;border-radius:10px;cursor:pointer;font-weight:700;">Search</button>
    @if(request('search') || request('status'))<a href="{{ url('/admin/sellers') }}" style="align-self:center;color:#6E6E73;font-size:13px;font-weight:600;">Clear filters</a>@endif
  </form>

  <div class="filter-bar">
    <a href="{{ url('/admin/sellers'.(request('search')?'?search='.request('search'):'')) }}" class="filter-pill {{ !request('status') ? 'active' : '' }}">All</a>
    <a href="{{ url('/admin/sellers?status=pending'.(request('search')?'&search='.request('search'):'')) }}" class="filter-pill {{ request('status')==='pending' ? 'active' : '' }}">Pending</a>
    <a href="{{ url('/admin/sellers?status=approved'.(request('search')?'&search='.request('search'):'')) }}" class="filter-pill {{ request('status')==='approved' ? 'active' : '' }}">Approved</a>
    <a href="{{ url('/admin/sellers?status=suspended'.(request('search')?'&search='.request('search'):'')) }}" class="filter-pill {{ request('status')==='suspended' ? 'active' : '' }}">Suspended</a>
    <a href="{{ url('/admin/sellers?status=rejected'.(request('search')?'&search='.request('search'):'')) }}" class="filter-pill {{ request('status')==='rejected' ? 'active' : '' }}">Rejected</a>
  </div>

  <div style="overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;min-width:760px;">
    <thead>
      <tr style="border-bottom:2px solid #E8E6E0;text-align:left;">
        <th style="padding:12px 10px;">Store</th>
        <th style="padding:12px 10px;">Email</th>
        <th style="padding:12px 10px;">Products</th>
        <th style="padding:12px 10px;">Status</th>
        <th style="padding:12px 10px;">Registered</th>
        <th style="padding:12px 10px;">Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse($sellers as $seller)
      <tr style="border-bottom:1px solid #F7F6F2;">
        <td style="padding:12px 10px;">
          <div class="store-cell">
            <div class="store-avatar">
              @if($seller->logo)
                <img src="{{ url('storage/'.ltrim($seller->logo,'/')) }}" alt="logo">
              @else
                {{ strtoupper(mb_substr($seller->store_name,0,1)) }}
              @endif
            </div>
            <div>
              <div class="store-name">{{ $seller->store_name }}</div>
              <div class="store-owner">{{ $seller->user->name ?? '—' }}</div>
            </div>
          </div>
        </td>
        <td style="padding:12px 10px;color:#6E6E73;font-size:13px;">{{ $seller->user->email ?? '—' }}</td>
        <td style="padding:12px 10px;">
          <span class="prod-count">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#16697A" stroke-width="2"><path d="M4 4h16v4H4V4Zm0 6h16v10H4V10Z"/></svg>
            {{ $seller->products_count }}
          </span>
        </td>
        <td style="padding:12px 10px;">
          @php
            $bg = $seller->status === 'approved' ? '#E8F5EE' : ($seller->status === 'rejected' ? '#FCE9E4' : ($seller->status === 'suspended' ? '#FEE2E2' : '#FDF3E3'));
            $col = $seller->status === 'approved' ? '#166534' : ($seller->status === 'rejected' ? '#991b1b' : ($seller->status === 'suspended' ? '#991b1b' : '#92400e'));
          @endphp
          <span style="padding:4px 10px;border-radius:999px;font-size:11.5px;font-weight:700;background:{{ $bg }};color:{{ $col }};border:1px solid {{ $seller->status === 'approved' ? '#BFE3D0' : ($seller->status === 'suspended' ? '#FECACA' : '#E8E6E0') }};">{{ ucfirst($seller->status) }}</span>
        </td>
        <td style="padding:12px 10px;color:#6E6E73;font-size:13px;">{{ $seller->created_at->format('M d, Y') }}</td>
        <td style="padding:12px 10px;">
          <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
            @if($seller->status === 'pending')
              <form method="POST" action="{{ url('/admin/sellers/'.$seller->id.'/approve') }}" style="display:inline;">
                @csrf
                <button type="submit" style="padding:6px 12px;background:#2E8B57;color:white;border:none;border-radius:8px;cursor:pointer;font-size:12px;font-weight:700;">Approve</button>
              </form>
              <form method="POST" action="{{ url('/admin/sellers/'.$seller->id.'/reject') }}" style="display:inline;" data-confirm="Reject this seller application? The applicant will be notified." data-confirm-title="Reject Seller?" data-confirm-ok="Reject" data-confirm-class="btn-danger">
                @csrf
                <input type="hidden" name="reason" value="Does not meet requirements" />
                <button type="submit" style="padding:6px 12px;background:#E05A33;color:white;border:none;border-radius:8px;cursor:pointer;font-size:12px;font-weight:700;">Reject</button>
              </form>
            @elseif($seller->status === 'approved')
              <form method="POST" action="{{ url('/admin/sellers/'.$seller->id.'/suspend') }}" style="display:inline;" data-confirm="This seller will be suspended for compliance violation. Their store and products will be hidden. Continue?" data-confirm-title="Suspend Seller?" data-confirm-ok="Suspend" data-confirm-class="btn-danger" data-confirm-icon="suspend">
                @csrf
                <button type="submit" style="padding:6px 12px;background:#E05A33;color:white;border:none;border-radius:8px;cursor:pointer;font-size:12px;font-weight:700;">Suspend</button>
              </form>
            @elseif($seller->status === 'suspended')
              <form method="POST" action="{{ url('/admin/sellers/'.$seller->id.'/reinstate') }}" style="display:inline;" data-confirm="This seller will be reinstated and their store will be visible again. Continue?" data-confirm-title="Reinstate Seller?" data-confirm-ok="Reinstate" data-confirm-class="btn-primary">
                @csrf
                <button type="submit" style="padding:6px 12px;background:#2E8B57;color:white;border:none;border-radius:8px;cursor:pointer;font-size:12px;font-weight:700;">Reinstate</button>
              </form>
            @elseif($seller->status === 'rejected')
              <form method="POST" action="{{ url('/admin/sellers/'.$seller->id.'/approve') }}" style="display:inline;" data-confirm="Approve this seller? Their account will be reactivated." data-confirm-title="Approve Seller?" data-confirm-ok="Approve" data-confirm-class="btn-primary">
                @csrf
                <button type="submit" style="padding:6px 12px;background:#2E8B57;color:white;border:none;border-radius:8px;cursor:pointer;font-size:12px;font-weight:700;">Approve</button>
              </form>
            @endif
            <a href="{{ url('/admin/sellers/'.$seller->id) }}" style="padding:6px 12px;background:#fff;color:#374151;border:1px solid #E8E6E0;border-radius:8px;text-decoration:none;font-size:12px;font-weight:700;">View</a>
          </div>
        </td>
      </tr>
      @empty
      <tr><td colspan="6" style="padding:32px;text-align:center;color:#6E6E73;">
        <div style="width:48px;height:48px;border-radius:50%;background:#F3F4F6;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.6"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-4.418 0-8 1.79-8 4v2h16v-2c0-2.21-3.582-4-8-4Z"/></svg>
        </div>
        No sellers found.
      </td></tr>
      @endforelse
    </tbody>
  </table>
  </div>
</div>
@endsection
