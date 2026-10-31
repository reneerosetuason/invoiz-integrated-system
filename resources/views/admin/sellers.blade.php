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
  
  /* Single line clean table */
  .clean-table { width:100%; border-collapse:collapse; }
  .clean-table th { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:#6B7280; padding:10px 12px; border-bottom:2px solid #E8E6E0; background:#FAFAF8; text-align:left; white-space:nowrap; }
  .clean-table td { padding:10px 12px; border-bottom:1px solid #F1F0EC; font-size:12.5px; color:#1B1B1E; white-space:nowrap; vertical-align:middle; }
  .clean-table tr:hover td { background:#FAF9F6; }
  .store-avatar { width:28px; height:28px; border-radius:8px; background:linear-gradient(135deg,#16697A 0%,#1A9CB0 100%); color:#fff; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:12px; flex-shrink:0; overflow:hidden; vertical-align:middle; margin-right:8px; }
  .store-avatar img { width:100%; height:100%; object-fit:cover; }
  
  @media (max-width: 900px) { .seller-stats { grid-template-columns:repeat(2,1fr); } }
</style>

<div class="card">
  <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:6px;">
    <div>
      <h1 class="page-title" style="margin-bottom:4px;">Manage Sellers</h1>
      <p style="color:var(--text-secondary); font-size:13.5px; margin:0;">Review seller applications, monitor compliance, and manage store accounts. Suspend sellers that violate platform policies.</p>
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

  <div style="background:#fff; border:1px solid #E8E6E0; border-radius:14px; overflow-x:auto;">
    <table class="clean-table">
      <thead>
        <tr>
          <th>Store</th>
          <th>Owner</th>
          <th>Business Type</th>
          <th>Status</th>
          <th>Joined</th>
          <th style="text-align:right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($sellers as $seller)
        @php
          $currentStatus = $seller->approval_status ?? $seller->status;
          $bg = $currentStatus === 'approved' ? '#E8F5EE' : ($currentStatus === 'rejected' ? '#FCE9E4' : ($currentStatus === 'suspended' ? '#FEE2E2' : '#FDF3E3'));
          $col = $currentStatus === 'approved' ? '#166534' : ($currentStatus === 'rejected' ? '#991b1b' : ($currentStatus === 'suspended' ? '#991b1b' : '#92400e'));
        @endphp
        <tr>
          <td>
            <div style="display:inline-flex; align-items:center;">
              <span class="store-avatar">
                @if($seller->logo)
                  <img src="{{ url('storage/'.ltrim($seller->logo,'/')) }}" alt="logo">
                @else
                  {{ strtoupper(mb_substr($seller->display_name,0,1)) }}
                @endif
              </span>
              <strong style="color:#111827;" title="{{ $seller->display_name }}">{{ Str::limit($seller->display_name, 18) }}</strong>
              <span style="margin-left:5px; font-size:11px; color:#9CA3AF;">#{{ $seller->id }}</span>
            </div>
          </td>
          <td>
            <span style="color:#374151; font-weight:600;" title="{{ $seller->user ? $seller->user->displayName() : '—' }}">
              {{ Str::limit($seller->user ? $seller->user->displayName() : '—', 16) }}
            </span>
          </td>
          <td>
            <span style="color:#4B5563; font-size:12.5px;" title="{{ $seller->line_of_business ?: 'General' }} ({{ $seller->seller_type ?: 'Sole Prop' }})">
              {{ Str::limit($seller->line_of_business ?: 'General', 18) }}
            </span>
          </td>
          <td>
            <span style="display:inline-flex; align-items:center; gap:5px; padding:3px 9px; border-radius:999px; font-size:11.5px; font-weight:700; background:{{ $bg }}; color:{{ $col }};">
              <span style="width:6px; height:6px; border-radius:50%; background:{{ $col }};"></span>
              {{ ucfirst($currentStatus) }}
            </span>
          </td>
          <td style="color:#6E6E73; font-size:12px;">
            {{ $seller->created_at->format('M d, Y') }}
          </td>
          <td style="text-align:right;">
            <div style="display:inline-flex; gap:6px; align-items:center; justify-content:flex-end;">
              @if($currentStatus === 'pending')
                <form method="POST" action="{{ url('/admin/sellers/'.$seller->id.'/approve') }}" style="display:inline;">
                  @csrf
                  <button type="submit" style="padding:4px 10px; background:#2E8B57; color:white; border:none; border-radius:6px; cursor:pointer; font-size:11.5px; font-weight:700;">Approve</button>
                </form>
                <a href="{{ url('/admin/sellers/'.$seller->id) }}" style="padding:4px 10px; background:#FFF7ED; color:#9A3412; border:1px solid #FED7AA; border-radius:6px; text-decoration:none; font-size:11.5px; font-weight:700;">Review</a>
              @elseif($currentStatus === 'approved')
                <form method="POST" action="{{ url('/admin/sellers/'.$seller->id.'/suspend') }}" style="display:inline;" data-confirm="This seller will be suspended for compliance violation. Their store and products will be hidden. Continue?" data-confirm-title="Suspend Seller?" data-confirm-ok="Suspend" data-confirm-class="btn-danger" data-confirm-icon="suspend">
                  @csrf
                  <button type="submit" style="padding:4px 10px; background:#E05A33; color:white; border:none; border-radius:6px; cursor:pointer; font-size:11.5px; font-weight:700;">Suspend</button>
                </form>
                <a href="{{ url('/admin/sellers/'.$seller->id) }}" style="padding:4px 10px; background:#fff; color:#374151; border:1px solid #E8E6E0; border-radius:6px; text-decoration:none; font-size:11.5px; font-weight:700;">View</a>
              @elseif($currentStatus === 'suspended')
                <form method="POST" action="{{ url('/admin/sellers/'.$seller->id.'/reinstate') }}" style="display:inline;" data-confirm="This seller will be reinstated and their store will be visible again. Continue?" data-confirm-title="Reinstate Seller?" data-confirm-ok="Reinstate" data-confirm-class="btn-primary">
                  @csrf
                  <button type="submit" style="padding:4px 10px; background:#2E8B57; color:white; border:none; border-radius:6px; cursor:pointer; font-size:11.5px; font-weight:700;">Reinstate</button>
                </form>
                <a href="{{ url('/admin/sellers/'.$seller->id) }}" style="padding:4px 10px; background:#fff; color:#374151; border:1px solid #E8E6E0; border-radius:6px; text-decoration:none; font-size:11.5px; font-weight:700;">View</a>
              @elseif($currentStatus === 'rejected')
                <form method="POST" action="{{ url('/admin/sellers/'.$seller->id.'/approve') }}" style="display:inline;" data-confirm="Approve this seller? Their account will be reactivated." data-confirm-title="Approve Seller?" data-confirm-ok="Approve" data-confirm-class="btn-primary">
                  @csrf
                  <button type="submit" style="padding:4px 10px; background:#2E8B57; color:white; border:none; border-radius:6px; cursor:pointer; font-size:11.5px; font-weight:700;">Approve</button>
                </form>
                <a href="{{ url('/admin/sellers/'.$seller->id) }}" style="padding:4px 10px; background:#fff; color:#374151; border:1px solid #E8E6E0; border-radius:6px; text-decoration:none; font-size:11.5px; font-weight:700;">View</a>
              @endif
            </div>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="6" style="padding:32px;text-align:center;color:#6E6E73;">
            <div style="width:44px;height:44px;border-radius:50%;background:#F3F4F6;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.6"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-4.418 0-8 1.79-8 4v2h16v-2c0-2.21-3.582-4-8-4Z"/></svg>
            </div>
            No sellers found.
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
    @include('admin.partials.pager', ['paginator' => $sellers])
  </div>
</div>
@endsection
