@extends('admin.layout')

@section('content')
<style>
  .order-stats-strip {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 10px;
    margin-bottom: 16px;
  }
  .o-stat-card {
    background: #fff;
    border: 1px solid #E8E6E0;
    border-radius: 12px;
    padding: 12px 14px;
    text-decoration: none;
    color: inherit;
    display: flex;
    flex-direction: column;
    justify-content: center;
    transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
    box-shadow: 0 1px 2px rgba(0,0,0,0.03);
  }
  .o-stat-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 14px rgba(0,0,0,0.06);
    border-color: #16697A;
  }
  .o-stat-card.active {
    background: #16697A;
    color: #fff;
    border-color: #16697A;
  }
  .o-stat-card.active span, .o-stat-card.active strong, .o-stat-card.active small { color: #fff; }
  .o-stat-label {
    font-size: 10.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #6E6E73;
  }
  .o-stat-num {
    font-size: 19px;
    font-weight: 800;
    margin-top: 3px;
    line-height: 1.1;
  }
  .o-stat-sub { font-size: 11px; color: #8E8E93; margin-top: 2px; }

  /* Filters */
  .orders-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 14px;
  }
  .filter-pills {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
  }
  .filter-pill {
    padding: 6px 13px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    border: 1px solid #E8E6E0;
    background: #fff;
    color: #374151;
    text-decoration: none;
    transition: all .15s ease;
  }
  .filter-pill:hover { border-color: #16697A; color: #16697A; }
  .filter-pill.active { background: #16697A; color: #fff; border-color: #16697A; }

  /* Single-line table */
  .orders-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: auto;
  }
  .orders-table th {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: #6E6E73;
    padding: 10px 12px;
    border-bottom: 1px solid #E8E6E0;
    background: #FAFAF8;
    text-align: left;
    white-space: nowrap;
  }
  .orders-table td {
    padding: 11px 12px;
    border-bottom: 1px solid #F1F0EC;
    font-size: 13px;
    color: #1B1B1E;
    white-space: nowrap;
    vertical-align: middle;
  }
  .orders-table tr:hover td { background: #FAF9F6; }
  .orders-table tr:last-child td { border-bottom: none; }

  /* Status chips */
  .status-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 999px;
    white-space: nowrap;
  }
  .sc-delivered { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
  .sc-shipped { background: #EAF4F3; color: #0E4A57; border: 1px solid #CBE3E1; }
  .sc-processing { background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE; }
  .sc-pending { background: #FFFBEB; color: #92400E; border: 1px solid #FDE68A; }
  .sc-cancelled { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }

  .pay-chip {
    display: inline-flex;
    align-items: center;
    font-size: 11px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 6px;
    background: #F7F6F2;
    color: #374151;
  }
  .pay-paid { background: #ECFDF5; color: #065F46; }
  .pay-pending { background: #FFFBEB; color: #92400E; }
  .pay-failed { background: #FEF2F2; color: #991B1B; }
</style>

<div class="card" style="padding: 20px;">
  <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 14px;">
    <div>
      <h1 class="page-title" style="margin-bottom: 4px;">Orders Directory</h1>
      <p style="color: var(--text-secondary); font-size: 13px; margin: 0;">Comprehensive order fulfillment oversight. Filter by milestone or customer.</p>
    </div>
  </div>

  @if(session('status'))
    <div style="padding: 10px 14px; background: #E8F5EE; color: #166534; border-radius: 10px; margin-bottom: 14px; font-weight: 600; font-size: 13px; border: 1px solid #BFE3D0;">
      {{ session('status') }}
    </div>
  @endif

  {{-- Status Stats Strip --}}
  <div class="order-stats-strip">
    <a href="{{ url('/admin/orders') }}" class="o-stat-card {{ !request('status') ? 'active' : '' }}">
      <span class="o-stat-label">All Orders</span>
      <strong class="o-stat-num">{{ $stats['all'] }}</strong>
      <small class="o-stat-sub">total orders</small>
    </a>
    <a href="{{ url('/admin/orders?status=new') }}" class="o-stat-card {{ request('status') === 'new' ? 'active' : '' }}" style="border-left: 3px solid #F59E0B;">
      <span class="o-stat-label">New</span>
      <strong class="o-stat-num" style="color: #D97706;">{{ $stats['new'] }}</strong>
      <small class="o-stat-sub">needs action</small>
    </a>
    <a href="{{ url('/admin/orders?status=processing') }}" class="o-stat-card {{ request('status') === 'processing' ? 'active' : '' }}" style="border-left: 3px solid #3B82F6;">
      <span class="o-stat-label">Processing</span>
      <strong class="o-stat-num" style="color: #2563EB;">{{ $stats['processing'] }}</strong>
      <small class="o-stat-sub">preparing</small>
    </a>
    <a href="{{ url('/admin/orders?status=shipped') }}" class="o-stat-card {{ request('status') === 'shipped' ? 'active' : '' }}" style="border-left: 3px solid #16697A;">
      <span class="o-stat-label">Shipped</span>
      <strong class="o-stat-num" style="color: #0E4A57;">{{ $stats['shipped'] }}</strong>
      <small class="o-stat-sub">in transit</small>
    </a>
    <a href="{{ url('/admin/orders?status=delivered') }}" class="o-stat-card {{ request('status') === 'delivered' ? 'active' : '' }}" style="border-left: 3px solid #10B981;">
      <span class="o-stat-label">Delivered</span>
      <strong class="o-stat-num" style="color: #059669;">{{ $stats['delivered'] }}</strong>
      <small class="o-stat-sub">₱{{ number_format($stats['revenue'], 0) }}</small>
    </a>
    <a href="{{ url('/admin/orders?status=cancelled') }}" class="o-stat-card {{ request('status') === 'cancelled' ? 'active' : '' }}" style="border-left: 3px solid #EF4444;">
      <span class="o-stat-label">Cancelled</span>
      <strong class="o-stat-num" style="color: #DC2626;">{{ $stats['cancelled'] }}</strong>
      <small class="o-stat-sub">voided</small>
    </a>
  </div>

  {{-- Search & Filters --}}
  <div class="orders-toolbar">
    <div class="filter-pills">
      <a href="{{ url('/admin/orders'.(request('search')?'?search='.request('search'):'')) }}" class="filter-pill {{ !request('status') ? 'active' : '' }}">All</a>
      <a href="{{ url('/admin/orders?status=new'.(request('search')?'&search='.request('search'):'')) }}" class="filter-pill {{ request('status')==='new' ? 'active' : '' }}">New</a>
      <a href="{{ url('/admin/orders?status=processing'.(request('search')?'&search='.request('search'):'')) }}" class="filter-pill {{ request('status')==='processing' ? 'active' : '' }}">Processing</a>
      <a href="{{ url('/admin/orders?status=shipped'.(request('search')?'&search='.request('search'):'')) }}" class="filter-pill {{ request('status')==='shipped' ? 'active' : '' }}">Shipped</a>
      <a href="{{ url('/admin/orders?status=delivered'.(request('search')?'&search='.request('search'):'')) }}" class="filter-pill {{ request('status')==='delivered' ? 'active' : '' }}">Delivered</a>
      <a href="{{ url('/admin/orders?status=cancelled'.(request('search')?'&search='.request('search'):'')) }}" class="filter-pill {{ request('status')==='cancelled' ? 'active' : '' }}">Cancelled</a>
    </div>

    <form method="GET" style="display: flex; gap: 8px; align-items: center;">
      @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
      <div style="position: relative;">
        <input type="search" name="search" value="{{ request('search') }}" placeholder="Search order #, customer, store..." style="padding: 7px 12px 7px 30px; border: 1px solid #D1D5DB; border-radius: 8px; font-size: 12.5px; width: 240px; background: #fff;" />
        <svg style="position: absolute; left: 9px; top: 50%; transform: translateY(-50%); width: 14px; height: 14px; stroke: #9CA3AF; fill: none; stroke-width: 2;" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
      </div>
      <button type="submit" class="btn btn-primary btn-sm" style="font-weight: 700;">Search</button>
      @if(request('search') || request('status'))
        <a href="{{ url('/admin/orders') }}" style="color: #6E6E73; font-size: 12px; font-weight: 600; text-decoration: none;">Clear</a>
      @endif
    </form>
  </div>

  {{-- Clean 1-Line Table (100% width, No Horizontal Scroll) --}}
  <div style="background: #fff; border: 1px solid #E8E6E0; border-radius: 12px; overflow-x: auto;">
    <table class="orders-table">
      <thead>
        <tr>
          <th>Order #</th>
          <th>Customer</th>
          <th>Store</th>
          <th>Items</th>
          <th>Total</th>
          <th>Payment</th>
          <th>Status</th>
          <th>Date</th>
          <th style="text-align: right;">Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse($orders as $order)
          @php
            $statusCls = match($order->status) {
              'delivered' => 'sc-delivered',
              'shipped', 'out_for_delivery' => 'sc-shipped',
              'processing' => 'sc-processing',
              'cancelled' => 'sc-cancelled',
              default => 'sc-pending',
            };
            $payCls = match($order->payment_status) {
              'paid' => 'pay-paid',
              'failed' => 'pay-failed',
              default => 'pay-pending',
            };
            $methodLabel = strtoupper($order->payment_method ?? 'COD');
            $payLabel = ucfirst($order->payment_status ?? 'pending');
          @endphp
          <tr>
            <td>
              <a href="{{ url('/admin/orders/'.$order->id) }}" style="font-weight: 700; color: #16697A; text-decoration: none; font-family: monospace;" title="{{ $order->order_number }}">
                #{{ Str::limit($order->order_number, 14) }}
              </a>
            </td>
            <td>
              <span style="font-weight: 600;" title="{{ $order->buyer->name ?? 'Guest' }} ({{ $order->buyer->email ?? '' }})">
                {{ Str::limit($order->buyer->name ?? 'Guest User', 14) }}
              </span>
            </td>
            <td>
              <span style="color: #475569;" title="{{ $order->seller->store_name ?? 'Invoiz Store' }}">
                {{ Str::limit($order->seller->store_name ?? 'Invoiz Store', 14) }}
              </span>
            </td>
            <td>
              <span style="font-size: 12px; color: #6E6E73;">
                {{ $order->items_count }} item{{ $order->items_count > 1 ? 's' : '' }}
              </span>
            </td>
            <td>
              <strong style="color: #0E4A57; font-size: 13px;">
                ₱{{ number_format((float) ($order->total_amount ?? $order->total), 2) }}
              </strong>
            </td>
            <td>
              <span class="pay-chip {{ $payCls }}">
                {{ $methodLabel }} · {{ $payLabel }}
              </span>
            </td>
            <td>
              <span class="status-chip {{ $statusCls }}">
                {{ ucfirst(str_replace('_', ' ', $order->status)) }}
              </span>
            </td>
            <td style="color: #6E6E73; font-size: 12px;">
              {{ $order->created_at->format('M d, Y') }}
            </td>
            <td style="text-align: right;">
              <a href="{{ url('/admin/orders/'.$order->id) }}" class="btn btn-soft btn-xs" style="padding: 4px 10px; font-weight: 700; font-size: 11.5px; border-radius: 6px; text-decoration: none;">
                View
              </a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="9" style="text-align: center; color: #6E6E73; padding: 36px 16px;">
              <p style="margin: 0; font-weight: 700; color: #1B1B1E; font-size: 14px;">No orders found</p>
              <p style="margin: 4px 0 0; font-size: 12.5px;">No orders match the selected status or search query.</p>
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
    @include('admin.partials.pager', ['paginator' => $orders])
  </div>
</div>
@endsection
