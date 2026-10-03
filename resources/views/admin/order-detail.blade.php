@extends('admin.layout')

@section('content')
<style>
  .order-hero { background:linear-gradient(135deg,#16697A 0%,#0E4A57 100%); border-radius:18px; padding:22px 24px; color:#fff; position:relative; overflow:hidden; }
  .order-hero::after{ content:''; position:absolute; right:-50px; top:-50px; width:180px; height:180px; background:rgba(255,255,255,.07); border-radius:50%; }
  .hero-top { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
  .hero-id { font-family:monospace; font-size:13px; background:rgba(255,255,255,.14); border:1px solid rgba(255,255,255,.2); padding:5px 10px; border-radius:999px; font-weight:700; letter-spacing:.5px; }
  .hero-badge { display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border-radius:999px; font-size:12px; font-weight:700; border:1px solid rgba(255,255,255,.3); backdrop-filter:blur(6px); }
  .hero-title { font-size:22px; font-weight:800; letter-spacing:-.5px; margin-top:12px; line-height:1.2; }
  .hero-meta { font-size:13px; color:rgba(255,255,255,.8); margin-top:6px; display:flex; gap:14px; flex-wrap:wrap; }
  .stat-row { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin:18px 0; }
  .stat-mini { background:#fff; border:1px solid var(--border); border-radius:14px; padding:14px; text-align:center; }
  .stat-mini strong{ font-size:20px; font-weight:800; display:block; }
  .stat-mini span{ font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:var(--text-secondary); }
  .info-card { background:#fff; border:1px solid var(--border); border-radius:16px; padding:20px; }
  .info-card h2{ font-size:15px; font-weight:800; letter-spacing:-.3px; margin:0 0 16px; display:flex; align-items:center; gap:8px; }
  .info-card h2 svg{ width:18px; height:18px; stroke:#16697A; fill:none; stroke-width:1.8; }
  .info-row{ display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #F1F2F4; align-items:flex-start; }
  .info-row:last-child{ border-bottom:none; }
  .info-icon{ width:36px; height:36px; border-radius:10px; background:#F0FAFA; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
  .info-icon svg{ width:16px; height:16px; stroke:#16697A; fill:none; stroke-width:1.7; }
  .info-label{ font-size:11px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:.7px; }
  .info-value{ font-size:13.5px; font-weight:600; color:#1B1B1E; margin-top:2px; word-break:break-word; }
  .info-sub{ font-size:12px; color:#6B7280; margin-top:1px; }
  .timeline{ display:flex; align-items:center; gap:0; margin-top:14px; }
  .t-step{ flex:1; text-align:center; position:relative; }
  .t-dot{ width:28px; height:28px; border-radius:50%; background:#F3F4F6; border:2px solid #E5E7EB; display:flex; align-items:center; justify-content:center; margin:0 auto 6px; font-size:11px; font-weight:800; color:#9CA3AF; }
  .t-step.active .t-dot{ background:#16697A; border-color:#16697A; color:#fff; box-shadow:0 4px 12px rgba(22,105,122,.2); }
  .t-step.done .t-dot{ background:#E8F5EE; border-color:#BFE3D0; color:#166534; }
  .t-label{ font-size:11px; font-weight:700; color:#9CA3AF; }
  .t-step.active .t-label, .t-step.done .t-label{ color:#16697A; }
  .t-line{ flex:1; height:2px; background:#E5E7EB; margin:0 -4px; position:relative; top:-14px; }
  .t-line.done{ background:#2E8B57; }
  .fin-row{ display:flex; justify-content:space-between; padding:8px 0; font-size:13.5px; border-bottom:1px dashed #E5E7EB; }
  .fin-row.total{ border-bottom:none; border-top:2px solid #E8E6E0; margin-top:8px; padding-top:12px; font-weight:800; font-size:15px; color:#16697A; }
  .item-grid{ display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:12px; }
  .item-card{ background:#fff; border:1px solid #E8E6E0; border-radius:14px; overflow:hidden; display:flex; gap:12px; padding:12px; transition:transform .15s, box-shadow .15s; }
  .item-card:hover{ transform:translateY(-1px); box-shadow:0 8px 20px rgba(16,24,40,.06); }
  .item-thumb{ width:68px; height:68px; border-radius:10px; background:#fff; border:1px solid #E8E6E0; display:flex; align-items:center; justify-content:center; overflow:hidden; flex-shrink:0; }
  .item-thumb img{ width:100%; height:100%; object-fit:cover; }
  .grid-2{ display:grid; grid-template-columns:1.15fr .85fr; gap:16px; }
  @media(max-width:900px){ .grid-2{ grid-template-columns:1fr; } .stat-row{ grid-template-columns:1fr; } .item-grid{ grid-template-columns:1fr; } }
</style>

<div class="order-hero">
  <div class="hero-top">
    <span class="hero-id">#{{ $order->order_number }}</span>
    @php
      $statusColors = ['new'=>'#E0F2FE','pending'=>'#E0F2FE','processing'=>'#EDE9FE','shipped'=>'#E6F1F2','delivered'=>'#E8F5EE','cancelled'=>'#FEE2E2'];
      $statusText = ['new'=>'#0C4A6E','pending'=>'#0C4A6E','processing'=>'#5B21B6','shipped'=>'#0E4A57','delivered'=>'#166534','cancelled'=>'#991B1B'];
      $payColors = ['paid'=>'#E8F5EE','pending'=>'#FDF3E3','failed'=>'#FEE2E2','refunded'=>'#F3E8FF'];
      $payText = ['paid'=>'#166534','pending'=>'#92400e','failed'=>'#991B1B','refunded'=>'#6b21a8'];
    @endphp
    <span class="hero-badge" style="background:{{ $statusColors[$order->status] ?? '#fff' }}; color:{{ $statusText[$order->status] ?? '#374151' }};">{{ ucfirst($order->status) }}</span>
    <span class="hero-badge" style="background:{{ $payColors[$order->payment_status] ?? '#fff' }}; color:{{ $payText[$order->payment_status] ?? '#374151' }};">{{ ucfirst($order->payment_status) }}</span>
    <span style="margin-left:auto; font-size:12px; background:rgba(255,255,255,.14); border:1px solid rgba(255,255,255,.2); padding:6px 10px; border-radius:999px;">{{ $order->created_at->format('M d, Y · h:i A') }}</span>
  </div>
  <div class="hero-title">Order {{ $order->order_number }}</div>
  <div class="hero-meta">
    <span style="display:flex; align-items:center; gap:6px;">
      <span style="width:28px; height:28px; border-radius:50%; background:#fff; color:#16697A; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:11px;">{{ strtoupper(mb_substr($order->buyer->name ?? 'B',0,1)) }}</span>
      Buyer: {{ $order->buyer->name ?? 'N/A' }} · {{ $order->buyer->email ?? '' }}
    </span>
    <span>·</span>
    <span style="display:flex; align-items:center; gap:6px;">
      <span style="width:28px; height:28px; border-radius:8px; background:#fff; color:#16697A; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:11px;">{{ strtoupper(mb_substr($order->seller->store_name ?? 'S',0,1)) }}</span>
      Store: {{ $order->seller->store_name ?? 'N/A' }}
    </span>
  </div>
</div>

@if(session('status'))
<div style="padding:12px 16px;background:#E8F5EE;color:#166534;border-radius:12px;margin:16px 0;border:1px solid #BFE3D0;">{{ session('status') }}</div>
@endif

<div class="stat-row">
  <div class="stat-mini"><span>Order Total</span><strong style="color:#16697A;">₱{{ number_format($order->total,2) }}</strong><small>{{ $order->items->count() }} item{{ $order->items->count()>1?'s':'' }}</small></div>
  <div class="stat-mini"><span>Payment</span><strong style="color:{{ $payText[$order->payment_status] ?? '#374151' }};">{{ ucfirst($order->payment_status) }}</strong><small>{{ $order->payment_status === 'paid' ? 'verified' : 'needs action' }}</small></div>
  <div class="stat-mini"><span>Fulfillment</span><strong style="color:{{ $statusText[$order->status] ?? '#374151' }};">{{ ucfirst($order->status) }}</strong><small>{{ ucfirst(str_replace('_',' ',$order->delivery_status ?? 'pending')) }}</small></div>
</div>

<!-- Status Timeline -->
<div class="info-card" style="margin-bottom:16px;">
  <h2>
    <svg viewBox="0 0 24 24"><path d="M12 8v4l3 3M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
    Order Progress
  </h2>
  @php
    $steps = ['new'=>'New','processing'=>'Processing','shipped'=>'Shipped','delivered'=>'Delivered'];
    $orderStatus = $order->status === 'pending' ? 'new' : $order->status;
    $keys = array_keys($steps);
    $currentIdx = array_search($orderStatus, $keys);
    if($order->status === 'cancelled') $currentIdx = -1;
  @endphp
  @if($order->status === 'cancelled')
    <div style="text-align:center; padding:12px; background:#FEE2E2; border:1px solid #FECACA; border-radius:10px; color:#991B1B; font-weight:700;">This order was cancelled</div>
  @else
    <div class="timeline">
      @foreach($steps as $key => $label)
        @php
          $idx = array_search($key, $keys);
          $isDone = $currentIdx !== false && $idx < $currentIdx;
          $isActive = $idx === $currentIdx;
        @endphp
        <div class="t-step {{ $isActive ? 'active' : ($isDone ? 'done' : '') }}">
          <div class="t-dot">@if($isDone)<x-ui-icon name="check" :size="13" />@else {{ $idx+1 }} @endif</div>
          <div class="t-label">{{ $label }}</div>
        </div>
        @if(!$loop->last)
          <div class="t-line {{ $isDone || $isActive ? 'done' : '' }}"></div>
        @endif
      @endforeach
    </div>
  @endif
</div>

<div class="grid-2">
  <!-- Left: Order Information -->
  <div class="info-card">
    <h2>
      <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg>
      Order Information
    </h2>

    <div class="info-row">
      <div class="info-icon"><svg viewBox="0 0 24 24"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-4.418 0-8 1.79-8 4v2h16v-2c0-2.21-3.582-4-8-4Z"/></svg></div>
      <div>
        <div class="info-label">Buyer</div>
        <div class="info-value">{{ $order->buyer->name ?? 'N/A' }}</div>
        <div class="info-sub">{{ $order->buyer->email ?? '' }} @if($order->buyer->phone) · {{ $order->buyer->phone }} @endif</div>
      </div>
    </div>
    <div class="info-row">
      <div class="info-icon"><svg viewBox="0 0 24 24"><path d="M3 9h18v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9Z"/><path d="M3 9V7a2 2 0 0 1 2-2h2"/><path d="M16 5h2a2 2 0 0 1 2 2v2"/></svg></div>
      <div>
        <div class="info-label">Seller / Store</div>
        <div class="info-value">{{ $order->seller->store_name ?? 'N/A' }}</div>
        <div class="info-sub">{{ $order->seller->user->email ?? '' }}</div>
      </div>
    </div>
    <div class="info-row">
      <div class="info-icon"><svg viewBox="0 0 24 24"><path d="M21 10c0 6-9 12-9 12S3 16 3 10a9 9 0 0 1 18 0Z"/><circle cx="12" cy="10" r="3"/></svg></div>
      <div>
        <div class="info-label">Shipping Address</div>
        <div class="info-value" style="font-weight:500; line-height:1.5;">
          @if(is_array($order->shipping_address))
            {{ implode(', ', array_filter($order->shipping_address)) ?: '—' }}
          @else
            {{ $order->shipping_address ?: '—' }}
          @endif
        </div>
      </div>
    </div>
    <div class="info-row">
      <div class="info-icon"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/></svg></div>
      <div>
        <div class="info-label">Delivery Status</div>
        <div class="info-value">{{ ucfirst(str_replace('_',' ', $order->delivery_status ?? 'pending')) }}</div>
      </div>
    </div>
    @if($order->notes)
    <div class="info-row">
      <div class="info-icon"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/></svg></div>
      <div>
        <div class="info-label">Notes</div>
        <div class="info-value" style="font-weight:500;">{{ $order->notes }}</div>
      </div>
    </div>
    @endif

    <h2 style="margin-top:20px;">
      <svg viewBox="0 0 24 24"><path d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4Z"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
      Financial Breakdown
    </h2>
    <div style="background:#F8F9FA; border:1px solid #F1F2F4; border-radius:12px; padding:14px 16px;">
      <div class="fin-row"><span style="color:#6B7280;">Subtotal</span><span>₱{{ number_format($order->sub_total ?? $order->total, 2) }}</span></div>
      @if(($order->discount_total ?? 0) > 0)
        <div class="fin-row"><span style="color:#6B7280;">Discount</span><span style="color:#2E8B57;">-₱{{ number_format($order->discount_total, 2) }}</span></div>
      @endif
      <div class="fin-row"><span style="color:#6B7280;">Shipping Fee</span><span>₱{{ number_format($order->shipping_fee ?? 0, 2) }}</span></div>
      <div class="fin-row"><span style="color:#6B7280;">Tax</span><span>₱{{ number_format($order->tax_total ?? 0, 2) }}</span></div>
      @if(($order->commission_amount ?? 0) > 0)
        <div class="fin-row"><span style="color:#6B7280;">Platform Commission</span><span style="color:#92400e;">₱{{ number_format($order->commission_amount, 2) }}</span></div>
      @endif
      <div class="fin-row total"><span>Total</span><span>₱{{ number_format($order->total, 2) }}</span></div>
    </div>
  </div>

  <!-- Right: Update + Actions -->
  <div>
    <div class="info-card">
      <h2>
        <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-3"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5Z"/></svg>
        Update Order
      </h2>
      <form method="POST" action="{{ url('/admin/orders/'.$order->id.'/update') }}">
        @csrf
        <div style="margin-bottom:14px;">
          <label style="display:block; margin-bottom:6px; font-size:11px; font-weight:700; letter-spacing:.6px; text-transform:uppercase; color:#374151;">Order Status</label>
          <select name="status" style="width:100%; padding:11px 14px; border:1px solid #D1D5DB; border-radius:10px; background:#fff; font-size:13.5px;">
            <option value="new" {{ $order->status === 'new' ? 'selected' : '' }}>New</option>
            <option value="processing" {{ $order->status === 'processing' ? 'selected' : '' }}>Processing</option>
            <option value="shipped" {{ $order->status === 'shipped' ? 'selected' : '' }}>Shipped</option>
            <option value="delivered" {{ $order->status === 'delivered' ? 'selected' : '' }}>Delivered</option>
            <option value="cancelled" {{ $order->status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
          </select>
        </div>
        <div style="margin-bottom:16px;">
          <label style="display:block; margin-bottom:6px; font-size:11px; font-weight:700; letter-spacing:.6px; text-transform:uppercase; color:#374151;">Delivery Status</label>
          <select name="delivery_status" style="width:100%; padding:11px 14px; border:1px solid #D1D5DB; border-radius:10px; background:#fff; font-size:13.5px;">
            <option value="pending" {{ ($order->delivery_status ?? 'pending') === 'pending' ? 'selected' : '' }}>Pending</option>
            <option value="assigned" {{ ($order->delivery_status ?? '') === 'assigned' ? 'selected' : '' }}>Assigned</option>
            <option value="in_transit" {{ ($order->delivery_status ?? '') === 'in_transit' ? 'selected' : '' }}>In Transit</option>
            <option value="delivered" {{ ($order->delivery_status ?? '') === 'delivered' ? 'selected' : '' }}>Delivered</option>
          </select>
        </div>
        <button type="submit" style="width:100%; padding:11px; background:#16697A; color:#fff; border:none; border-radius:10px; cursor:pointer; font-weight:700; font-size:14px; box-shadow:0 2px 8px rgba(22,105,122,.15);">Update Order</button>
      </form>
    </div>

    <div class="info-card" style="margin-top:16px; background:#F0FAFA; border-color:#E6F1F2;">
      <h2 style="color:#0E4A57;">
        <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Need Help?
      </h2>
      <p style="font-size:13px; color:#374151; line-height:1.6; margin:0;">Update the status as the order moves through fulfillment. The buyer and seller will see the new status in their dashboards.</p>
    </div>
  </div>
</div>

<div class="info-card" style="margin-top:16px;">
  <h2>
    <svg viewBox="0 0 24 24"><path d="M6 2h12l1 6H5l1-6Z"/><path d="M5 8h14l-1 12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 8Z"/></svg>
    Ordered Items ({{ $order->items->count() }})
  </h2>
  <div class="item-grid">
    @forelse($order->items as $item)
      @php
        $product = $item->product;
        $img = $product ? ($product->images->firstWhere('is_main') ?? $product->images->first()) : null;
        $imgPath = $img ? public_path('storage/'.ltrim($img->path, '/')) : null;
      @endphp
      <div class="item-card">
        <div class="item-thumb">
          @if($img && file_exists($imgPath))
            <img src="{{ url('storage/'.ltrim($img->path, '/')) }}" alt="{{ $product->name ?? 'Item' }}" />
          @else
            <div style="width:36px; height:36px; border-radius:8px; background:linear-gradient(135deg,#E6F1F2 0%,#F0FAFA 100%); display:flex; align-items:center; justify-content:center;">
              <svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:none;stroke:#9CA3AF;stroke-width:1.6;"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
            </div>
          @endif
        </div>
        <div style="flex:1; min-width:0;">
          <div style="font-weight:700; font-size:13px; line-height:1.3; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="{{ $product->name ?? 'Product #'.$item->product_id }}">{{ $product->name ?? 'Product #'.$item->product_id }}</div>
          <div style="font-size:11.5px; color:#6B7280; margin-top:2px;">SKU: {{ $product->sku ?? '—' }}</div>
          <div style="font-size:11.5px; color:#6B7280;">Qty: <b style="color:#1B1B1E;">{{ $item->quantity }}</b> × ₱{{ number_format($item->unit_price, 2) }}</div>
        </div>
        <div style="text-align:right; flex-shrink:0;">
          <div style="font-weight:800; font-size:14px; color:#16697A;">₱{{ number_format($item->total_price, 2) }}</div>
          <div style="font-size:11px; color:#9CA3AF; margin-top:2px;">Total</div>
        </div>
      </div>
    @empty
      <div style="grid-column:1/-1; text-align:center; padding:32px; color:#9CA3AF;">
        <div style="width:48px; height:48px; border-radius:50%; background:#F3F4F6; display:flex; align-items:center; justify-content:center; margin:0 auto 10px;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.6"><path d="M6 2h12l1 6H5l1-6Z"/></svg>
        </div>
        No items on this order.
      </div>
    @endforelse
  </div>
</div>

<div style="margin-top:18px;">
  <a href="{{ url('/admin/orders') }}" style="display:inline-flex; align-items:center; gap:6px; color:#16697A; text-decoration:none; font-weight:600; font-size:13px;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16697A" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    Back to Orders
  </a>
</div>
@endsection
