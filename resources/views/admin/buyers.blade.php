@extends('admin.layout')

@section('content')
<style>
  .buyer-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:18px; }
  .b-stat { background:#fff; border:1px solid var(--border); border-radius:14px; padding:14px 16px; text-align:center; transition:transform .15s, box-shadow .15s; text-decoration:none; color:inherit; display:block; }
  .b-stat:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(16,24,40,.08); }
  .b-stat.active { background:#16697A; color:#fff; border-color:#16697A; }
  .b-stat.active span, .b-stat.active strong, .b-stat.active small { color:#fff; }
  .b-stat span { display:block; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:var(--text-secondary); }
  .b-stat strong { display:block; font-size:22px; font-weight:800; margin-top:4px; }
  .b-stat small { font-size:11px; color:var(--text-secondary); }
  .filter-bar { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; }
  .filter-pill { padding:7px 14px; border-radius:999px; font-size:12.5px; font-weight:700; border:1px solid var(--border); background:#fff; color:#374151; text-decoration:none; transition:all .15s; }
  .filter-pill:hover { border-color:#16697A; color:#16697A; }
  .filter-pill.active { background:#16697A; color:#fff; border-color:#16697A; }

  /* Single-line clean table */
  .clean-table { width:100%; border-collapse:collapse; }
  .clean-table th { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:#6B7280; padding:10px 12px; border-bottom:2px solid #E8E6E0; background:#FAFAF8; text-align:left; white-space:nowrap; }
  .clean-table td { padding:10px 12px; border-bottom:1px solid #F1F0EC; font-size:12.5px; color:#1B1B1E; white-space:nowrap; vertical-align:middle; }
  .clean-table tr:hover td { background:#FAF9F6; }
  .buyer-avatar { width:26px; height:26px; border-radius:50%; background:linear-gradient(135deg,#16697A 0%,#1A9CB0 100%); color:#fff; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:11px; flex-shrink:0; vertical-align:middle; margin-right:8px; }
  .buyer-avatar.suspended { background:#9CA3AF; }

  @media (max-width: 900px) { .buyer-stats { grid-template-columns:repeat(2,1fr); } }
</style>

<div class="card">
  <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:6px;">
    <div>
      <h1 class="page-title" style="margin-bottom:4px;">Manage Buyers</h1>
      <p style="color:var(--text-secondary); font-size:13.5px; margin:0;">View registered buyer accounts, monitor order history, and manage buyer access.</p>
    </div>
  </div>

  @if(session('status'))
  <div style="padding:12px 16px;background:#E8F5EE;color:#166534;border-radius:12px;margin:16px 0 0;border:1px solid #BFE3D0;">{{ session('status') }}</div>
  @endif

  <div class="buyer-stats" style="margin-top:18px;">
    <a href="{{ url('/admin/buyers') }}" class="b-stat {{ !request('status') ? 'active' : '' }}">
      <span>All Buyers</span><strong>{{ $stats['all'] }}</strong><small>total registered</small>
    </a>
    <a href="{{ url('/admin/buyers?status=active') }}" class="b-stat {{ request('status')==='active' ? 'active' : '' }}" style="border-left:3px solid #2E8B57;">
      <span>Active</span><strong style="color:#166534;">{{ $stats['active'] }}</strong><small>good standing</small>
    </a>
    <a href="{{ url('/admin/buyers?status=suspended') }}" class="b-stat {{ request('status')==='suspended' ? 'active' : '' }}" style="border-left:3px solid #DC2626;">
      <span>Suspended</span><strong style="color:#991B1B;">{{ $stats['suspended'] ?? 0 }}</strong><small>access revoked</small>
    </a>
    <div class="b-stat" style="border-left:3px solid #16697A;">
      <span>Verified</span><strong style="color:#0E4A57;">{{ $stats['active'] ?? 0 }}</strong><small>active accounts</small>
    </div>
  </div>

  <form method="GET" style="display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap;">
    @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    <div style="position:relative; flex:1; min-width:220px;">
      <svg style="position:absolute; left:12px; top:50%; transform:translateY(-50%); width:16px; height:16px; stroke:#9CA3AF; fill:none; stroke-width:1.8;" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
      <input type="search" name="search" value="{{ request('search') }}" placeholder="Search by name or email..." style="padding:9px 14px 9px 36px;border:1px solid #d1d5db;border-radius:10px;width:100%;background:#fff;" />
    </div>
    <button type="submit" style="padding:9px 18px;background:#16697A;color:white;border:none;border-radius:10px;cursor:pointer;font-weight:700;">Search</button>
    @if(request('search') || request('status'))<a href="{{ url('/admin/buyers') }}" style="align-self:center;color:#6E6E73;font-size:13px;font-weight:600;">Clear filters</a>@endif
  </form>

  <div class="filter-bar">
    <a href="{{ url('/admin/buyers'.(request('search')?'?search='.request('search'):'')) }}" class="filter-pill {{ !request('status') ? 'active' : '' }}">All</a>
    <a href="{{ url('/admin/buyers?status=active'.(request('search')?'&search='.request('search'):'')) }}" class="filter-pill {{ request('status')==='active' ? 'active' : '' }}">Active</a>
    <a href="{{ url('/admin/buyers?status=suspended'.(request('search')?'&search='.request('search'):'')) }}" class="filter-pill {{ request('status')==='suspended' ? 'active' : '' }}">Suspended</a>
  </div>

  <div style="background:#fff; border:1px solid #E8E6E0; border-radius:14px; overflow-x:auto;">
    <table class="clean-table">
      <thead>
        <tr>
          <th>Buyer</th>
          <th>Email</th>
          <th>Orders & Spend</th>
          <th>Status</th>
          <th>Joined</th>
          <th style="text-align:right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($buyers as $buyer)
          @php
            $isActive = ($buyer->account_status ?? 'active') === 'active';
            $orderCount = $buyer->orders_count ?? $buyer->orders->count();
            $totalSpent = $buyer->total_spent ?? $buyer->orders->sum('total');
          @endphp
        <tr>
          <td>
            <div style="display:inline-flex; align-items:center;">
              <span class="buyer-avatar {{ $isActive ? '' : 'suspended' }}">{{ strtoupper(mb_substr($buyer->name,0,1)) }}</span>
              <strong style="color:#111827;" title="{{ $buyer->name }}">{{ Str::limit($buyer->name, 18) }}</strong>
            </div>
          </td>
          <td style="color:#6E6E73;" title="{{ $buyer->email }}">{{ Str::limit($buyer->email, 22) }}</td>
          <td>
            @if($orderCount > 0)
              <span style="font-weight:700; color:#111827;">{{ $orderCount }} order{{ $orderCount > 1 ? 's' : '' }}</span>
              <span style="color:#6E6E73; margin-left:4px;">· ₱{{ number_format($totalSpent, 0) }}</span>
            @else
              <span style="color:#9CA3AF; font-size:12px;">No purchases yet</span>
            @endif
          </td>
          <td>
            @if($buyer->approval_status === 'pending')
              <span style="display:inline-flex; align-items:center; gap:5px; padding:3px 9px; border-radius:999px; font-size:11.5px; font-weight:700; background:#FFFBEB; color:#92400e;">
                <span style="width:6px; height:6px; border-radius:50%; background:#F59E0B;"></span> Pending
              </span>
            @elseif($buyer->approval_status === 'rejected')
              <span style="display:inline-flex; align-items:center; gap:5px; padding:3px 9px; border-radius:999px; font-size:11.5px; font-weight:700; background:#FEF2F2; color:#991B1B;">
                <span style="width:6px; height:6px; border-radius:50%; background:#DC2626;"></span> Rejected
              </span>
            @elseif($isActive)
              <span style="display:inline-flex; align-items:center; gap:5px; padding:3px 9px; border-radius:999px; font-size:11.5px; font-weight:700; background:#ECFDF5; color:#166534;">
                <span style="width:6px; height:6px; border-radius:50%; background:#10B981;"></span> Active
              </span>
            @else
              <span style="display:inline-flex; align-items:center; gap:5px; padding:3px 9px; border-radius:999px; font-size:11.5px; font-weight:700; background:#FEF2F2; color:#991B1B;">
                <span style="width:6px; height:6px; border-radius:50%; background:#DC2626;"></span> Suspended
              </span>
            @endif
          </td>
          <td style="color:#6E6E73; font-size:12px;">
            {{ $buyer->created_at->format('M d, Y') }}
          </td>
          <td style="text-align:right;">
            <div style="display:inline-flex; gap:6px; align-items:center; justify-content:flex-end;">
              @if($buyer->approval_status === 'pending')
                <form method="POST" action="{{ route('admin.buyers.review', $buyer) }}" style="display:inline;">
                  @csrf
                  <input type="hidden" name="action" value="approve" />
                  <button type="submit" style="padding:4px 10px; background:#2E8B57; color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:11.5px; font-weight:700;" @disabled(!$buyer->email_verified_at) title="{{ $buyer->email_verified_at ? 'Approve buyer registration' : 'Buyer must verify email first' }}">Approve</button>
                </form>
                <form method="POST" action="{{ route('admin.buyers.review', $buyer) }}" style="display:inline;">
                  @csrf
                  <input type="hidden" name="action" value="reject" />
                  <button type="submit" style="padding:4px 10px; background:#E5E7EB; color:#374151; border:none; border-radius:6px; cursor:pointer; font-size:11.5px; font-weight:700;">Reject</button>
                </form>
              @elseif($isActive)
                <form method="POST" action="{{ url('/admin/manage-accounts/'.$buyer->id.'/action') }}" style="display:inline;" data-confirm="This buyer will be suspended and will not be able to log in or place orders. Continue?" data-confirm-title="Suspend Buyer?" data-confirm-ok="Suspend" data-confirm-class="btn-danger" data-confirm-icon="suspend">
                  @csrf
                  <input type="hidden" name="action" value="suspend" />
                  <button type="submit" style="padding:4px 10px; background:#F0A202; color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:11.5px; font-weight:700;">Suspend</button>
                </form>
              @else
                <form method="POST" action="{{ url('/admin/manage-accounts/'.$buyer->id.'/action') }}" style="display:inline;">
                  @csrf
                  <input type="hidden" name="action" value="activate" />
                  <button type="submit" style="padding:4px 10px; background:#2E8B57; color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:11.5px; font-weight:700;">Activate</button>
                </form>
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
            No buyers found.
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
    @include('admin.partials.pager', ['paginator' => $buyers])
  </div>
</div>
@endsection
