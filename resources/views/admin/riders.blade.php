@extends('admin.layout')

@section('content')
<div class="card">
    <h1 class="page-title">Rider Directory</h1>
    <p style="color:#6E6E73;">Rider accounts are managed by logistics personnel.</p>

    @if(session('status'))
    <div style="padding:12px 16px;background:#E8F5EE;color:#166534;border-radius:8px;margin-bottom:16px;">{{ session('status') }}</div>
    @endif

    <form method="GET" style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
        <input type="search" name="search" value="{{ request('search') }}" placeholder="Search by name or email..." style="padding:8px 14px;border:1px solid #d1d5db;border-radius:8px;flex:1;min-width:200px;" />
        <button type="submit" style="padding:8px 20px;background:#16697A;color:white;border:none;border-radius:8px;cursor:pointer;">Search</button>
        @if(request('search'))<a href="{{ url('/admin/riders') }}" style="align-self:center;color:#6E6E73;font-size:13px;">Clear</a>@endif
    </form>

    <table style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="border-bottom:2px solid #E8E6E0;text-align:left;">
                <th style="padding:12px 8px;">Name</th>
                <th style="padding:12px 8px;">Email</th>
                <th style="padding:12px 8px;">Status</th>
                <th style="padding:12px 8px;">Registered</th>
            </tr>
        </thead>
        <tbody>
            @forelse($riders as $rider)
            <tr style="border-bottom:1px solid #F7F6F2;">
                <td style="padding:12px 8px;font-weight:600;">{{ $rider->name }}</td>
                <td style="padding:12px 8px;color:#6E6E73;">{{ $rider->email }}</td>
                <td style="padding:12px 8px;">
                    <span style="padding:3px 10px;border-radius:12px;font-size:12px;background:{{ $rider->account_status === 'active' ? '#E8F5EE' : '#FCE9E4' }};color:{{ $rider->account_status === 'active' ? '#166534' : '#991b1b' }};">{{ ucfirst($rider->account_status ?? 'active') }}</span>
                </td>
                <td style="padding:12px 8px;color:#6E6E73;font-size:13px;">{{ $rider->created_at->format('M d, Y') }}</td>

            </tr>
            @empty
            <tr><td colspan="4" style="padding:20px;text-align:center;color:#6E6E73;">No riders found.</td></tr>
            @endforelse
        </tbody>
    </table>
    @include('admin.partials.pager', ['paginator' => $riders])
</div>
@endsection
