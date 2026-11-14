@extends('admin.layout')

@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h1 class="page-title" style="margin:0;">Complaint #{{ $complaint->id }}</h1>
        <a href="{{ url('/admin/complaints') }}" style="color:#16697A;text-decoration:none;font-weight:700;font-size:13px;">&larr; Back to Complaints</a>
    </div>
    
    @if(session('status'))
    <div style="padding:12px 16px;background:#E8F5EE;color:#166534;border-radius:8px;margin-bottom:16px;">{{ session('status') }}</div>
    @endif

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
        <div>
            <h2 style="font-size:18px;margin-bottom:12px;">Details</h2>
            <div style="background:#F0EEE9;padding:16px;border-radius:12px;">
                <p><strong>Subject:</strong> <span style="font-weight:700;color:#111827;">{{ $complaint->subject }}</span></p>
                <p><strong>Type:</strong> {{ ucfirst($complaint->type) }}</p>
                <p><strong>Status:</strong> 
                    <span style="padding:3px 10px;border-radius:12px;font-size:12px;background:{{ $complaint->status === 'open' ? '#FCE9E4' : ($complaint->status === 'resolved' ? '#E8F5EE' : '#FDF3E3') }};color:{{ $complaint->status === 'open' ? '#991b1b' : ($complaint->status === 'resolved' ? '#166534' : '#92400e') }};">{{ ucfirst(str_replace('_', ' ', $complaint->status)) }}</span>
                </p>
                <p><strong>Buyer:</strong> {{ $complaint->buyer->name ?? 'N/A' }} ({{ $complaint->buyer->email ?? '' }})</p>

                <!-- Complaint Store / Seller Block -->
                <div style="margin:14px 0;padding:14px 16px;background:#EAF4F3;border:1px solid #BFE3E0;border-radius:10px;">
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#0E4A57;">Store Ordered From</div>
                    <div style="font-size:17px;font-weight:800;color:#0E4A57;margin-top:3px;display:flex;align-items:center;gap:6px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                        {{ $complaint->store_name }}
                    </div>
                    @if($complaint->seller)
                        <div style="font-size:12px;color:#4B5563;margin-top:4px;">
                            Store Owner: <strong>{{ $complaint->seller->user?->name ?? $complaint->seller->store_name }}</strong> &bull; {{ $complaint->seller->user?->email ?? '' }}
                        </div>
                    @endif
                </div>

                @if($complaint->reported_product_name)
                <div style="margin:12px 0;padding:12px 14px;background:#FEF3C7;border:1px solid #FCD34D;border-radius:10px;">
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#92400E;">Specific Item in Complaint</div>
                    <div style="font-size:15px;font-weight:800;color:#92400E;margin-top:2px;">{{ $complaint->reported_product_name }}</div>
                    <div style="font-size:12px;color:#B45309;margin-top:2px;">
                        Sold by Store: <strong>{{ $complaint->store_name }}</strong>
                    </div>
                </div>
                @endif

                @if($complaint->order)
                <div style="margin:12px 0;padding:12px 14px;background:#fff;border:1px solid #E5E7EB;border-radius:10px;">
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#6B7280;">Reported Order Information</div>
                    <div style="margin-top:4px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                        <span style="font-size:15px;font-weight:800;color:#111827;">Order #{{ $complaint->order->order_number ?? $complaint->order->id }}</span>
                        <a href="{{ url('/admin/orders') }}?search={{ $complaint->order->order_number ?? $complaint->order->id }}" style="display:inline-block;padding:2px 8px;background:#16697A;color:#fff;border-radius:6px;font-size:11px;font-weight:700;text-decoration:none;">View in Orders &rarr;</a>
                    </div>
                    <div style="margin-top:4px;font-size:12.5px;color:#374151;">
                        Ordered from: <strong style="color:#0E4A57;">{{ $complaint->store_name }}</strong>
                    </div>
                    <div style="margin-top:2px;font-size:12px;color:#6E6E73;">
                        Order Status: <strong>{{ ucfirst($complaint->order->status) }}</strong> &bull; Placed: {{ $complaint->order->created_at->format('M d, Y g:i A') }}
                    </div>
                </div>
                @endif
                <p><strong>Date:</strong> {{ $complaint->created_at->format('M d, Y g:i A') }}</p>
            </div>

            <h2 style="font-size:18px;margin:20px 0 12px;">Description</h2>
            <div style="background:#F0EEE9;padding:16px;border-radius:12px;">
                <p style="white-space:pre-wrap;margin:0;">{{ $complaint->description }}</p>
            </div>

            @if($complaint->resolution)
            <h2 style="font-size:18px;margin:20px 0 12px;">Resolution</h2>
            <div style="background:#E8F5EE;padding:16px;border-radius:12px;">
                <p style="white-space:pre-wrap;margin:0;">{{ $complaint->resolution }}</p>
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

        @if($complaint->order && $complaint->order->items->isNotEmpty())
        <div style="grid-column:1 / -1;margin-top:10px;">
            <div style="background:#fff;border:1px solid #E8E6E0;border-radius:12px;padding:20px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                    <h2 style="font-size:17px;font-weight:800;margin:0;color:#111827;">
                        Products in Order {{ $complaint->order->order_number ?? ('#'.$complaint->order->id) }}
                    </h2>
                    <span style="font-size:12.5px;color:#6E6E73;font-weight:600;">{{ $complaint->order->items->count() }} {{ $complaint->order->items->count() === 1 ? 'item' : 'items' }}</span>
                </div>
                <p style="font-size:12.5px;color:#6B7280;margin:0 0 14px;">
                    Each item displays the specific seller/store that sold it. This ensures you can identify the exact store even when multiple sellers offer identical products.
                </p>
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;">
                        <thead>
                            <tr style="background:#FAFAF8;border-bottom:2px solid #E8E6E0;">
                                <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;color:#6B7280;">Product</th>
                                <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;color:#6B7280;">Selling Store</th>
                                <th style="padding:10px 12px;text-align:center;font-size:11px;font-weight:700;text-transform:uppercase;color:#6B7280;">Qty</th>
                                <th style="padding:10px 12px;text-align:right;font-size:11px;font-weight:700;text-transform:uppercase;color:#6B7280;">Unit Price</th>
                                <th style="padding:10px 12px;text-align:right;font-size:11px;font-weight:700;text-transform:uppercase;color:#6B7280;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($complaint->order->items as $item)
                                @php
                                    $isReported = ($complaint->order_item_id && (int)$complaint->order_item_id === (int)$item->id)
                                        || ($complaint->product_id && (int)$complaint->product_id === (int)$item->product_id);
                                    $itemStoreName = $item->store_name;
                                @endphp
                                <tr style="border-bottom:1px solid #F1F0EC;background:{{ $isReported ? '#FEF9EE' : '#fff' }};">
                                    <td style="padding:12px;vertical-align:middle;">
                                        <div style="font-weight:700;color:#1B1B1E;font-size:13.5px;">
                                            {{ $item->product_name }}
                                            @if($isReported)
                                                <span style="display:inline-block;padding:2px 8px;background:#F97316;color:#fff;border-radius:999px;font-size:10px;font-weight:800;text-transform:uppercase;margin-left:6px;">Reported Item</span>
                                            @endif
                                        </div>
                                        @if($item->variant_label)
                                            <div style="font-size:11.5px;color:#6B7280;margin-top:2px;">{{ $item->variant_label }}</div>
                                        @endif
                                    </td>
                                    <td style="padding:12px;vertical-align:middle;">
                                        <div style="display:inline-flex;align-items:center;gap:6px;padding:4px 10px;background:#EAF4F3;color:#0E4A57;border-radius:8px;font-weight:700;font-size:12.5px;">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                                            {{ $itemStoreName }}
                                        </div>
                                    </td>
                                    <td style="padding:12px;text-align:center;font-weight:600;font-size:13px;vertical-align:middle;">
                                        {{ $item->quantity }}
                                    </td>
                                    <td style="padding:12px;text-align:right;font-size:13px;color:#374151;vertical-align:middle;">
                                        ₱{{ number_format((float) ($item->unit_price ?? $item->price ?? 0), 2) }}
                                    </td>
                                    <td style="padding:12px;text-align:right;font-weight:700;font-size:13.5px;color:#111827;vertical-align:middle;">
                                        ₱{{ number_format((float) $item->line_total, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
