@extends('admin.layout')

@section('content')
<div class="card">
    <h1 class="page-title">Complaint #{{ $complaint->id }}</h1>
    
    @if(session('status'))
    <div style="padding:12px 16px;background:#E8F5EE;color:#166534;border-radius:8px;margin-bottom:16px;">{{ session('status') }}</div>
    @endif

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
        <div>
            <h2 style="font-size:18px;margin-bottom:12px;">Details</h2>
            <div style="background:#F0EEE9;padding:16px;border-radius:12px;">
                <p><strong>Subject:</strong> {{ $complaint->subject }}</p>
                <p><strong>Type:</strong> {{ ucfirst($complaint->type) }}</p>
                <p><strong>Status:</strong> 
                    <span style="padding:3px 10px;border-radius:12px;font-size:12px;background:{{ $complaint->status === 'open' ? '#FCE9E4' : ($complaint->status === 'resolved' ? '#E8F5EE' : '#FDF3E3') }};color:{{ $complaint->status === 'open' ? '#991b1b' : ($complaint->status === 'resolved' ? '#166534' : '#92400e') }};">{{ ucfirst(str_replace('_', ' ', $complaint->status)) }}</span>
                </p>
                <p><strong>Buyer:</strong> {{ $complaint->buyer->name ?? 'N/A' }} ({{ $complaint->buyer->email ?? '' }})</p>
                <p><strong>Seller:</strong> {{ $complaint->seller->store_name ?? 'N/A' }}</p>
                @if($complaint->order)
                <p><strong>Order:</strong> {{ $complaint->order->order_number }}</p>
                @endif
                <p><strong>Date:</strong> {{ $complaint->created_at->format('M d, Y g:i A') }}</p>
            </div>

            <h2 style="font-size:18px;margin:20px 0 12px;">Description</h2>
            <div style="background:#F0EEE9;padding:16px;border-radius:12px;">
                <p>{{ $complaint->description }}</p>
            </div>

            @if($complaint->resolution)
            <h2 style="font-size:18px;margin:20px 0 12px;">Resolution</h2>
            <div style="background:#E8F5EE;padding:16px;border-radius:12px;">
                <p>{{ $complaint->resolution }}</p>
                <p style="font-size:13px;color:#6E6E73;margin-top:8px;">Resolved at: {{ $complaint->resolved_at->format('M d, Y g:i A') }}</p>
            </div>
            @endif
        </div>

        <div>
            <h2 style="font-size:18px;margin-bottom:12px;">Coordinate & Resolve</h2>
            <div style="background:#F0EEE9;padding:20px;border-radius:12px;">
                <p style="color:#6E6E73;margin-bottom:12px;">Coordinate with buyer, seller, and/or courier. Update the resolution below.</p>
                <form method="POST" action="{{ url('/admin/complaints/'.$complaint->id.'/resolve') }}">
                    @csrf
                    <div style="margin-bottom:12px;">
                        <label style="display:block;margin-bottom:4px;font-weight:600;">Resolution</label>
                        <textarea name="resolution" rows="5" required style="width:100%;padding:8px 14px;border:1px solid #d1d5db;border-radius:8px;box-sizing:border-box;" placeholder="Describe the resolution...">{{ $complaint->resolution }}</textarea>
                    </div>
                    <div style="margin-bottom:12px;">
                        <label style="display:block;margin-bottom:4px;font-weight:600;">Mark As</label>
                        <select name="status" style="width:100%;padding:8px 14px;border:1px solid #d1d5db;border-radius:8px;box-sizing:border-box;">
                            <option value="resolved" {{ $complaint->status === 'resolved' ? 'selected' : '' }}>Resolved</option>
                            <option value="closed" {{ $complaint->status === 'closed' ? 'selected' : '' }}>Closed</option>
                            <option value="in_review" {{ $complaint->status === 'in_review' ? 'selected' : '' }}>In Review</option>
                        </select>
                    </div>
                    <button type="submit" style="padding:10px 24px;background:#2E8B57;color:white;border:none;border-radius:8px;cursor:pointer;">Update Resolution</button>
                </form>
            </div>
        </div>
    </div>

    <div style="margin-top:20px;">
        <a href="{{ url('/admin/complaints') }}" style="color:#16697A;text-decoration:none;">Back to Complaints</a>
    </div>
</div>
@endsection
