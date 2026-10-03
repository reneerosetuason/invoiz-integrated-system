@extends('admin.layout')

@section('content')
<div class="card">
    <h1 class="page-title">Manage Accounts</h1>
    
    @if(session('status'))
    <div style="padding:12px 16px;background:#E8F5EE;color:#166534;border-radius:8px;margin-bottom:16px;">{{ session('status') }}</div>
    @endif

    <form method="GET" style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
        <input type="search" name="search" value="{{ request('search') }}" placeholder="Search by name or email..." style="padding:8px 14px;border:1px solid #d1d5db;border-radius:8px;flex:1;min-width:200px;" />
        <button type="submit" style="padding:8px 20px;background:#16697A;color:white;border:none;border-radius:8px;cursor:pointer;">Search</button>
        @if(request('search'))<a href="{{ url('/admin/manage-accounts') }}" style="align-self:center;color:#6E6E73;font-size:13px;">Clear</a>@endif
    </form>

    <table style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="border-bottom:2px solid #E8E6E0;text-align:left;">
                <th style="padding:12px 8px;">Name</th>
                <th style="padding:12px 8px;">Email</th>
                <th style="padding:12px 8px;">Role</th>
                <th style="padding:12px 8px;">Status</th>
                <th style="padding:12px 8px;">Created</th>
                <th style="padding:12px 8px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($users as $u)
            <tr style="border-bottom:1px solid #F7F6F2;">
                <td style="padding:12px 8px;font-weight:600;">{{ $u->name }}</td>
                <td style="padding:12px 8px;color:#6E6E73;">{{ $u->email }}</td>
                <td style="padding:12px 8px;">
                    <span style="padding:3px 10px;border-radius:12px;font-size:12px;background:{{ $u->role === 'admin' ? '#ede9fe' : ($u->role === 'seller' ? '#EAF4F3' : ($u->role === 'rider' ? '#ffedd5' : '#F7F6F2')) }};color:{{ $u->role === 'admin' ? '#7c3aed' : ($u->role === 'seller' ? '#16697A' : ($u->role === 'rider' ? '#c2410c' : '#1B1B1E')) }};">{{ ucfirst($u->role ?? 'buyer') }}</span>
                </td>
                <td style="padding:12px 8px;">
                    <span style="padding:3px 10px;border-radius:12px;font-size:12px;background:{{ ($u->account_status ?? 'active') === 'active' ? '#E8F5EE' : '#FCE9E4' }};color:{{ ($u->account_status ?? 'active') === 'active' ? '#166534' : '#991b1b' }};">{{ ucfirst($u->account_status ?? 'active') }}</span>
                </td>
                <td style="padding:12px 8px;color:#6E6E73;font-size:13px;">{{ $u->created_at->format('M d, Y') }}</td>
                <td style="padding:12px 8px;">
                    @if(($u->account_status ?? 'active') === 'active')
                    <form method="POST" action="{{ url('/admin/manage-accounts/'.$u->id.'/action') }}" style="display:inline;" data-confirm="This account will be suspended and the user will be unable to log in. Continue?" data-confirm-title="Suspend Account?" data-confirm-ok="Suspend" data-confirm-class="btn-danger" data-confirm-icon="suspend">
                        @csrf
                        <input type="hidden" name="action" value="suspend" />
                        <button type="submit" style="padding:4px 12px;background:#F0A202;color:white;border:none;border-radius:6px;cursor:pointer;font-size:12px;">Suspend</button>
                    </form>
                    @else
                    <form method="POST" action="{{ url('/admin/manage-accounts/'.$u->id.'/action') }}" style="display:inline;">
                        @csrf
                        <input type="hidden" name="action" value="activate" />
                        <button type="submit" style="padding:4px 12px;background:#2E8B57;color:white;border:none;border-radius:6px;cursor:pointer;font-size:12px;">Activate</button>
                    </form>
                    @endif
                    @if(($u->account_status ?? 'active') !== 'deactivated')
                    <form method="POST" action="{{ url('/admin/manage-accounts/'.$u->id.'/action') }}" style="display:inline;margin-left:4px;" data-confirm="This account will be permanently deactivated. Continue?" data-confirm-title="Deactivate Account?" data-confirm-ok="Deactivate" data-confirm-class="btn-danger">
                        @csrf
                        <input type="hidden" name="action" value="deactivate" />
                        <button type="submit" style="padding:4px 12px;background:#E05A33;color:white;border:none;border-radius:6px;cursor:pointer;font-size:12px;">Deactivate</button>
                    </form>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="6" style="padding:20px;text-align:center;color:#6E6E73;">No users found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
