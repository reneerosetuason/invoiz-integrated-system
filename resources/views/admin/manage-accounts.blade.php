@extends('admin.layout')

@section('content')
<style>
  .clean-table { width:100%; border-collapse:collapse; }
  .clean-table th { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:#6B7280; padding:10px 12px; border-bottom:2px solid #E8E6E0; background:#FAFAF8; text-align:left; white-space:nowrap; }
  .clean-table td { padding:10px 12px; border-bottom:1px solid #F1F0EC; font-size:12.5px; color:#1B1B1E; white-space:nowrap; vertical-align:middle; }
  .clean-table tr:hover td { background:#FAF9F6; }
</style>

<div class="card">
  <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:6px;">
    <div>
      <h1 class="page-title" style="margin-bottom:4px;">Manage Accounts</h1>
      <p style="color:var(--text-secondary); font-size:13.5px; margin:0;">Platform user accounts, role management, and access controls.</p>
    </div>
  </div>
  
  @if(session('status'))
  <div style="padding:12px 16px;background:#E8F5EE;color:#166534;border-radius:12px;margin:16px 0;border:1px solid #BFE3D0;">{{ session('status') }}</div>
  @endif

  <form method="GET" style="display:flex;gap:10px;margin:18px 0 16px;flex-wrap:wrap;">
    <div style="position:relative; flex:1; min-width:220px;">
      <svg style="position:absolute; left:12px; top:50%; transform:translateY(-50%); width:16px; height:16px; stroke:#9CA3AF; fill:none; stroke-width:1.8;" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
      <input type="search" name="search" value="{{ request('search') }}" placeholder="Search by name or email..." style="padding:9px 14px 9px 36px;border:1px solid #d1d5db;border-radius:10px;width:100%;background:#fff;" />
    </div>
    <button type="submit" style="padding:9px 18px;background:#16697A;color:white;border:none;border-radius:10px;cursor:pointer;font-weight:700;">Search</button>
    @if(request('search'))<a href="{{ url('/admin/manage-accounts') }}" style="align-self:center;color:#6E6E73;font-size:13px;font-weight:600;">Clear</a>@endif
  </form>

  <div style="background:#fff; border:1px solid #E8E6E0; border-radius:14px; overflow-x:auto;">
    <table class="clean-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Role</th>
          <th>Status</th>
          <th>Created</th>
          <th style="text-align:right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($users as $u)
        <tr>
          <td><strong style="color:#111827;">{{ $u->name }}</strong></td>
          <td style="color:#6E6E73;">{{ $u->email }}</td>
          <td>
            <span style="padding:3px 9px;border-radius:999px;font-size:11.5px;font-weight:700;background:{{ $u->role === 'admin' ? '#EDE9FE' : ($u->role === 'seller' ? '#EAF4F3' : ($u->role === 'rider' ? '#FFEDD5' : '#F3F4F6')) }};color:{{ $u->role === 'admin' ? '#7C3AED' : ($u->role === 'seller' ? '#16697A' : ($u->role === 'rider' ? '#C2410C' : '#374151')) }};">
              {{ ucfirst($u->role ?? 'buyer') }}
            </span>
          </td>
          <td>
            <span style="display:inline-flex; align-items:center; gap:5px; padding:3px 9px; border-radius:999px; font-size:11.5px; font-weight:700; background:{{ ($u->account_status ?? 'active') === 'active' ? '#ECFDF5' : '#FEF2F2' }}; color:{{ ($u->account_status ?? 'active') === 'active' ? '#166534' : '#991B1B' }};">
              <span style="width:6px; height:6px; border-radius:50%; background:{{ ($u->account_status ?? 'active') === 'active' ? '#10B981' : '#DC2626' }};"></span>
              {{ ucfirst($u->account_status ?? 'active') }}
            </span>
          </td>
          <td style="color:#6E6E73; font-size:12px;">{{ $u->created_at->format('M d, Y') }}</td>
          <td style="text-align:right;">
            <div style="display:inline-flex; gap:6px; align-items:center; justify-content:flex-end;">
              @if($u->role === 'rider' || $u->rider_exists)
                <span style="font-size:11.5px; color:#9CA3AF;">Logistics managed</span>
              @else
                @if(($u->account_status ?? 'active') === 'active')
                  <form method="POST" action="{{ url('/admin/manage-accounts/'.$u->id.'/action') }}" style="display:inline;" data-confirm="This account will be suspended and the user will be unable to log in. Continue?" data-confirm-title="Suspend Account?" data-confirm-ok="Suspend" data-confirm-class="btn-danger" data-confirm-icon="suspend">
                    @csrf
                    <input type="hidden" name="action" value="suspend" />
                    <button type="submit" style="padding:4px 10px; background:#F0A202; color:white; border:none; border-radius:6px; cursor:pointer; font-size:11.5px; font-weight:700;">Suspend</button>
                  </form>
                @else
                  <form method="POST" action="{{ url('/admin/manage-accounts/'.$u->id.'/action') }}" style="display:inline;">
                    @csrf
                    <input type="hidden" name="action" value="activate" />
                    <button type="submit" style="padding:4px 10px; background:#2E8B57; color:white; border:none; border-radius:6px; cursor:pointer; font-size:11.5px; font-weight:700;">Activate</button>
                  </form>
                @endif
                @if(($u->account_status ?? 'active') !== 'deactivated')
                  <form method="POST" action="{{ url('/admin/manage-accounts/'.$u->id.'/action') }}" style="display:inline;" data-confirm="This account will be permanently deactivated. Continue?" data-confirm-title="Deactivate Account?" data-confirm-ok="Deactivate" data-confirm-class="btn-danger">
                    @csrf
                    <input type="hidden" name="action" value="deactivate" />
                    <button type="submit" style="padding:4px 10px; background:#E05A33; color:white; border:none; border-radius:6px; cursor:pointer; font-size:11.5px; font-weight:700;">Deactivate</button>
                  </form>
                @endif
              @endif
            </div>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="6" style="padding:32px;text-align:center;color:#6E6E73;">
            <div style="width:44px;height:44px;border-radius:50%;background:#F3F4F6;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.6"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
            </div>
            No accounts found.
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
    @include('admin.partials.pager', ['paginator' => $users])
  </div>
</div>
@endsection
