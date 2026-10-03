@extends('admin.layout')

@section('content')
<style>
  .order-stats { display:grid; grid-template-columns:repeat(6,1fr); gap:10px; margin-bottom:18px; }
  .o-stat { background:#fff; border:1px solid var(--border); border-radius:14px; padding:12px 14px; text-align:center; text-decoration:none; color:inherit; display:block; transition:transform .15s, box-shadow .15s; }
  .o-stat:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(16,24,40,.08); }
  .o-stat.active { background:#16697A; color:#fff; border-color:#16697A; }
  .o-stat.active span, .o-stat.active strong, .o-stat.active small { color:#fff; }
  .o-stat span { display:block; font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:var(--text-secondary); }
  .o-stat strong { display:block; font-size:20px; font-weight:800; margin-top:4px; }
  .o-stat small { font-size:11px; color:var(--text-secondary); }
  .o-stat.active small { color:rgba(255,255,255,.7); }
  .filter-bar { display:flex; gap:7px; flex-wrap:wrap; margin-bottom:14px; }
  .filter-pill { padding:6px 12px; border-radius:999px; font-size:12px; font-weight:700; border:1px solid var(--border); background:#fff; color:#374151; text-decoration:none; transition:all .15s; }
  .filter-pill:hover { border-color:#16697A; color:#16697A; }
  .filter-pill.active { background:#16697A; color:#fff; border-color:#16697A; }
  .order-id { font-family:monospace; font-weight:700; font-size:12.5px; color:#1B1B1E; }
  .order-items-badge { display:inline-flex; align-items:center; gap:4px; background:#F0FAFA; border:1px solid #E6F1F2; color:#16697A; padding:2px 7px; border-radius:999px; font-size:11px; font-weight:700; margin-top:2px; }
  .party-cell { display:flex; align-items:center; gap:8px; }
  .party-avatar { width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:11px; flex-shrink:0; border:2px solid #fff; box-shadow:0 1px 4px rgba(0,0,0,.08); }
  .party-avatar.buyer { background:linear-gradient(135deg,#16697A 0%,#1A9CB0 100%); color:#fff; }
  .party-avatar.seller { background:linear-gradient(135deg,#F0A202 0%,#F59E0B 100%); color:#fff; border-radius:8px; }
  .party-name { font-weight:700; font-size:12.5px; line-height:1.2; }
  .party-sub { font-size:11px; color:var(--text-secondary); }
  .badge { display:inline-flex; align-items:center; gap:5px; padding:4px 9px; border-radius:999px; font-size:11px; font-weight:700; border:1px solid; white-space:nowrap; }
  .badge-dot { width:6px; height:6px; border-radius:50%; display:inline-block; }
  .badge-paid { background:#E8F5EE; color:#166534; border-color:#BFE3D0; } .badge-paid .badge-dot{ background:#2E8B57; }
  .badge-pending { background:#FDF3E3; color:#92400e; border-color:#FDE68A; } .badge-pending .badge-dot{ background:#F59E0B; }
  .badge-failed { background:#FEE2E2; color:#991B1B; border-color:#FECACA; } .badge-failed .badge-dot{ background:#DC2626; }
  .badge-new { background:#E0F2FE; color:#0C4A6E; border-color:#BAE6FD; } .badge-new .badge-dot{ background:#0284C7; }
  .badge-processing { background:#EDE9FE; color:#5B21B6; border-color:#DDD6FE; } .badge-processing .badge-dot{ background:#7C3AED; }
  .badge-shipped { background:#E6F1F2; color:#0E4A57; border-color:#CBE3E1; } .badge-shipped .badge-dot{ background:#16697A; }
  .badge-delivered { background:#E8F5EE; color:#166534; border-color:#BFE3D0; } .badge-delivered .badge-dot{ background:#2E8B57; }
  .badge-cancelled { background:#FEE2E2; color:#991B1B; border-color:#FECACA; } .badge-cancelled .badge-dot{ background:#DC2626; }
  @media (max-width: 900px) { .order-stats { grid-template-columns:repeat(3,1fr); } }
  @media (max-width: 600px) { .order-stats { grid-template-columns:repeat(2,1fr); } }
</style>

<div class="card">
  <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:4px;">
    <div>
      <h1 class="page-title" style="margin-bottom:4px;">Manage Orders</h1>
      <p style="color:var(--text-secondary); font-size:13.5px; margin:0;">Track every order from placement to delivery — filter by status, verify payments, and manage fulfillment.</p>
    </div>
  </div>

  @if(session('status'))
  <div style="padding:12px 16px;background:#E8F5EE;color:#166534;border-radius:12px;margin:16px 0 0;border:1px solid #BFE3D0;">{{ session('status') }}</div>
  @endif

  <div class="order-stats" style="margin-top:18px;">
    <a href="{{ url('/admin/orders') }}" class="o-stat {{ !request('status') ? 'active' : '' }}">
      <span>All Orders</span><strong>{{ $stats['all'] }}</strong><small>total</small>
    </a>
    <a href="{{ url('/admin/orders?status=new') }}" class="o-stat {{ request('status')==='new' ? 'active' : '' }}" style="border-left:3px solid #0284C7;">
      <span>New</span><strong style="color:#0C4A6E;">{{ $stats['new'] }}</strong><small>pending</small>
    </a>
    <a href="{{ url('/admin/orders?status=processing') }}" class="o-stat {{ request('status')==='processing' ? 'active' : '' }}" style="border-left:3px solid #7C3AED;">
      <span>Processing</span><strong style="color:#5B21B6;">{{ $stats['processing'] }}</strong><small>preparing</small>
    </a>
    <a href="{{ url('/admin/orders?status=shipped') }}" class="o-stat {{ request('status')==='shipped' ? 'active' : '' }}" style="border-left:3px solid #16697A;">
      <span>Shipped</span><strong style="color:#0E4A57;">{{ $stats['shipped'] }}</strong><small>in transit</small>
    </a>
    <a href="{{ url('/admin/orders?status=delivered') }}" class="o-stat {{ request('status')==='delivered' ? 'active' : '' }}" style="border-left:3px solid #2E8B57;">
      <span>Delivered</span><strong style="color:#166534;">{{ $stats['delivered'] }}</strong><small>₱{{ number_format($stats['revenue'],0) }}</small>
    </a>
    <a href="{{ url('/admin/orders?status=cancelled') }}" class="o-stat {{ request('status')==='cancelled' ? 'active' : '' }}" style="border-left:3px solid #DC2626;">
      <span>Cancelled</span><strong style="color:#991B1B;">{{ $stats['cancelled'] }}</strong><small>void</small>
    </a>
  </div>

  <form method="GET" style="display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap;">
    @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    <div style="position:relative; flex:1; min-width:220px;">
      <svg style="position:absolute; left:12px; top:50%; transform:translateY(-50%); width:16px; height:16px; stroke:#9CA3AF; fill:none; stroke-width:1.8;" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
      <input type="search" name="search" value="{{ request('search') }}" placeholder="Search by order #, buyer or store..." style="padding:9px 14px 9px 36px;border:1px solid #d1d5db;border-radius:10px;width:100%;background:#fff;" />
    </div>
    <button type="submit" style="padding:9px 18px;background:#16697A;color:white;border:none;border-radius:10px;cursor:pointer;font-weight:700;">Search</button>
    @if(request('search') || request('status'))<a href="{{ url('/admin/orders') }}" style="align-self:center;color:#6E6E73;font-size:13px;font-weight:600;">Clear filters</a>@endif
  </form>

  <div class="filter-bar">
    <a href="{{ url('/admin/orders'.(request('search')?'?search='.request('search'):'')) }}" class="filter-pill {{ !request('status') ? 'active' : '' }}">All</a>
    <a href="{{ url('/admin/orders?status=new'.(request('search')?'&search='.request('search'):'')) }}" class="filter-pill {{ request('status')==='new' ? 'active' : '' }}">New</a>
    <a href="{{ url('/admin/orders?status=processing'.(request('search')?'&search='.request('search'):'')) }}" class="filter-pill {{ request('status')==='processing' ? 'active' : '' }}">Processing</a>
    <a href="{{ url('/admin/orders?status=shipped'.(request('search')?'&search='.request('search'):'')) }}" class="filter-pill {{ request('status')==='shipped' ? 'active' : '' }}">Shipped</a>
    <a href="{{ url('/admin/orders?status=delivered'.(request('search')?'&search='.request('search'):'')) }}" class="filter-pill {{ request('status')==='delivered' ? 'active' : '' }}">Delivered</a>
    <a href="{{ url('/admin/orders?status=cancelled'.(request('search')?'&search='.request('search'):'')) }}" class="filter-pill {{ request('status')==='cancelled' ? 'active' : '' }}">Cancelled</a>
  </div>

  <div style="overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;min-width:900px;">
    <thead>
      <tr style="border-bottom:2px solid #E8E6E0;text-align:left; background:#FAFAF8;">
        <th style="padding:11px 10px; font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase; letter-spacing:.6px;">Order</th>
        <th style="padding:11px 10px; font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase; letter-spacing:.6px;">Customer</th>
        <th style="padding:11px 10px; font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase; letter-spacing:.6px;">Store</th>
        <th style="padding:11px 10px; font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase; letter-spacing:.6px;">Total</th>
        <th style="padding:11px 10px; font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase; letter-spacing:.6px;">Payment</th>
        <th style="padding:11px 10px; font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase; letter-spacing:.6px;">Status</th>
        <th style="padding:11px 10px; font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase; letter-spacing:.6px;">Date</th>
        <th style="padding:11px 10px; font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase; letter-spacing:.6px;">Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse($orders as $order)
        @php
          $payClass = $order->payment_status === 'paid' ? 'badge-paid' : ($order->payment_status === 'failed' ? 'badge-failed' : 'badge-pending');
          $statusClass = match($order->status) {
            'new','pending' => 'badge-new',
            'processing' => 'badge-processing',
            'shipped' => 'badge-shipped',
            'delivered' => 'badge-delivered',
            'cancelled' => 'badge-cancelled',
            default => 'badge-pending',
          };
        @endphp
      <tr style="border-bottom:1px solid #F1F2F4; transition:background .12s;" onmouseover="this.style.background='#FAFAF8'" onmouseout="this.style.background=''">
        <td style="padding:12px 10px;">
          <div class="order-id">#{{ $order->order_number }}</div>
          <span class="order-items-badge">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#16697A" stroke-width="2"><path d="M4 4h16v4H4V4Zm0 6h16v10H4V10Z"/></svg>
            {{ $order->items_count }} item{{ $order->items_count>1?'s':'' }}
          </span>
        </td>
        <td style="padding:12px 10px;">
          <div class="party-cell">
            <div class="party-avatar buyer">{{ strtoupper(mb_substr($order->buyer->name ?? 'B',0,1)) }}</div>
            <div>
              <div class="party-name">{{ $order->buyer->name ?? 'N/A' }}</div>
              <div class="party-sub">{{ $order->buyer->email ?? '' }}</div>
            </div>
          </div>
        </td>
        <td style="padding:12px 10px;">
          <div class="party-cell">
            <div class="party-avatar seller">{{ strtoupper(mb_substr($order->seller->store_name ?? 'S',0,1)) }}</div>
            <div>
              <div class="party-name">{{ $order->seller->store_name ?? 'N/A' }}</div>
              <div class="party-sub">{{ $order->seller->user->email ?? '' }}</div>
            </div>
          </div>
        </td>
        <td style="padding:12px 10px;">
          <div style="font-weight:800; font-size:13.5px; color:#16697A;">₱{{ number_format($order->total, 2) }}</div>
          <div style="font-size:11px; color:#9CA3AF;">{{ $order->items_count }} × items</div>
        </td>
        <td style="padding:12px 10px;">
          <span class="badge {{ $payClass }}"><span class="badge-dot"></span> {{ ucfirst($order->payment_status) }}</span>
        </td>
        <td style="padding:12px 10px;">
          <span class="badge {{ $statusClass }}"><span class="badge-dot"></span> {{ ucfirst($order->status) }}</span>
        </td>
        <td style="padding:12px 10px;">
          <div style="font-size:12.5px; font-weight:600; color:#374151;">{{ $order->created_at->format('M d, Y') }}</div>
          <div style="font-size:11px; color:#9CA3AF;">{{ $order->created_at->format('h:i A') }}</div>
        </td>
        <td style="padding:12px 10px;">
          <a href="{{ url('/admin/orders/'.$order->id) }}" style="display:inline-flex; align-items:center; gap:5px; padding:6px 12px; background:#fff; color:#374151; border:1px solid #E5E7EB; border-radius:8px; text-decoration:none; font-size:12px; font-weight:700;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#6B7280" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>
            View
          </a>
        </td>
      </tr>
      @empty
      <tr><td colspan="8" style="padding:36px;text-align:center;">
        <div style="width:48px;height:48px;border-radius:50%;background:#F3F4F6;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.6"><path d="M3 6h18v2H3V6Zm0 4h18v2H3v-2Zm0 4h12v2H3v-2Z"/></svg>
        </div>
        <div style="font-weight:600; color:#6B7280;">No orders found</div>
        <div style="font-size:13px; color:#9CA3AF; margin-top:4px;">Try adjusting your search or status filter.</div>
      </td></tr>
      @endforelse
    </tbody>
  </table>
  </div>
</div>
@endsection
