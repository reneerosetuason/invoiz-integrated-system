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
      <h1 class="page-title" style="margin-bottom:4px;">Rider Directory</h1>
      <p style="color:var(--text-secondary); font-size:13.5px; margin:0;">Directory of riders registered across logistics hubs. Hub supervisors manage assignments and status directly.</p>
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
    @if(request('search'))<a href="{{ url('/admin/riders') }}" style="align-self:center;color:#6E6E73;font-size:13px;font-weight:600;">Clear</a>@endif
  </form>

  <div style="background:#fff; border:1px solid #E8E6E0; border-radius:14px; overflow-x:auto;">
    <table class="clean-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Status</th>
          <th style="text-align:right;">Registered</th>
        </tr>
      </thead>
      <tbody>
        @forelse($riders as $rider)
        <tr>
          <td><strong style="color:#111827;">{{ $rider->name }}</strong></td>
          <td style="color:#6E6E73;">{{ $rider->email }}</td>
          <td>
            <span style="display:inline-flex; align-items:center; gap:5px; padding:3px 9px; border-radius:999px; font-size:11.5px; font-weight:700; background:{{ $rider->account_status === 'active' ? '#ECFDF5' : '#FCE9E4' }}; color:{{ $rider->account_status === 'active' ? '#166534' : '#991b1b' }};">
              <span style="width:6px; height:6px; border-radius:50%; background:{{ $rider->account_status === 'active' ? '#10B981' : '#DC2626' }};"></span>
              {{ ucfirst($rider->account_status ?? 'active') }}
            </span>
          </td>
          <td style="text-align:right; color:#6E6E73; font-size:12px;">{{ $rider->created_at->format('M d, Y') }}</td>
        </tr>
        @empty
        <tr>
          <td colspan="4" style="padding:32px;text-align:center;color:#6E6E73;">
            <div style="width:44px;height:44px;border-radius:50%;background:#F3F4F6;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.6"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
            </div>
            No riders found.
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
    @include('admin.partials.pager', ['paginator' => $riders])
  </div>
</div>
@endsection
