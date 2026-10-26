@extends('admin.layout')

@section('content')
@include('admin.partials.list-ui-styles')
@include('admin.partials.order-ui-styles')

<div class="admin-list admin-orders" data-ui="admin-orders">
  <header class="admin-list-head">
    <h1>Manage Orders</h1>
    <p>Track every order from placement to delivery — filter by status, verify payments, and manage fulfillment.</p>
  </header>

  @if(session('status'))
    <div class="admin-list-flash" role="status" aria-live="polite">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>
      <span>{{ session('status') }}</span>
    </div>
  @endif

  <nav class="admin-list-stats order-stats" aria-label="Order totals">
    <a href="{{ url('/admin/orders') }}" class="admin-list-stat {{ !request('status') ? 'is-active' : '' }}" {{ !request('status') ? 'aria-current=page' : '' }}>
      <span class="admin-list-stat-label">All Orders</span><strong class="{{ $stats['all'] ? '' : 'is-zero' }}">{{ $stats['all'] }}</strong><small>total</small>
    </a>
    <a href="{{ url('/admin/orders?status=new') }}" class="admin-list-stat {{ request('status')==='new' ? 'is-active' : '' }}" {{ request('status')==='new' ? 'aria-current=page' : '' }}>
      <span class="admin-list-stat-label">New</span><strong class="{{ $stats['new'] ? '' : 'is-zero' }}">{{ $stats['new'] }}</strong><small>pending</small>
    </a>
    <a href="{{ url('/admin/orders?status=processing') }}" class="admin-list-stat {{ request('status')==='processing' ? 'is-active' : '' }}" {{ request('status')==='processing' ? 'aria-current=page' : '' }}>
      <span class="admin-list-stat-label">Processing</span><strong class="{{ $stats['processing'] ? '' : 'is-zero' }}">{{ $stats['processing'] }}</strong><small>preparing</small>
    </a>
    <a href="{{ url('/admin/orders?status=shipped') }}" class="admin-list-stat {{ request('status')==='shipped' ? 'is-active' : '' }}" {{ request('status')==='shipped' ? 'aria-current=page' : '' }}>
      <span class="admin-list-stat-label">Shipped</span><strong class="{{ $stats['shipped'] ? '' : 'is-zero' }}">{{ $stats['shipped'] }}</strong><small>in transit</small>
    </a>
    <a href="{{ url('/admin/orders?status=delivered') }}" class="admin-list-stat {{ request('status')==='delivered' ? 'is-active' : '' }}" {{ request('status')==='delivered' ? 'aria-current=page' : '' }}>
      <span class="admin-list-stat-label">Delivered</span><strong class="{{ $stats['delivered'] ? '' : 'is-zero' }}">{{ $stats['delivered'] }}</strong><small class="order-revenue">₱{{ number_format($stats['revenue'],0) }}</small>
    </a>
    <a href="{{ url('/admin/orders?status=cancelled') }}" class="admin-list-stat {{ request('status')==='cancelled' ? 'is-active' : '' }}" {{ request('status')==='cancelled' ? 'aria-current=page' : '' }}>
      <span class="admin-list-stat-label">Cancelled</span><strong class="{{ $stats['cancelled'] ? '' : 'is-zero' }}">{{ $stats['cancelled'] }}</strong><small>void</small>
    </a>
  </nav>

  <section class="order-toolbar" aria-label="Find orders">
    <nav class="admin-list-filters" aria-label="Filter orders by status">
      <a href="{{ url('/admin/orders'.(request('search')?'?search='.request('search'):'')) }}" class="admin-list-filter {{ !request('status') ? 'is-active' : '' }}" {{ !request('status') ? 'aria-current=page' : '' }}>All</a>
      <a href="{{ url('/admin/orders?status=new'.(request('search')?'&search='.request('search'):'')) }}" class="admin-list-filter {{ request('status')==='new' ? 'is-active' : '' }}" {{ request('status')==='new' ? 'aria-current=page' : '' }}>New</a>
      <a href="{{ url('/admin/orders?status=processing'.(request('search')?'&search='.request('search'):'')) }}" class="admin-list-filter {{ request('status')==='processing' ? 'is-active' : '' }}" {{ request('status')==='processing' ? 'aria-current=page' : '' }}>Processing</a>
      <a href="{{ url('/admin/orders?status=shipped'.(request('search')?'&search='.request('search'):'')) }}" class="admin-list-filter {{ request('status')==='shipped' ? 'is-active' : '' }}" {{ request('status')==='shipped' ? 'aria-current=page' : '' }}>Shipped</a>
      <a href="{{ url('/admin/orders?status=delivered'.(request('search')?'&search='.request('search'):'')) }}" class="admin-list-filter {{ request('status')==='delivered' ? 'is-active' : '' }}" {{ request('status')==='delivered' ? 'aria-current=page' : '' }}>Delivered</a>
      <a href="{{ url('/admin/orders?status=cancelled'.(request('search')?'&search='.request('search'):'')) }}" class="admin-list-filter {{ request('status')==='cancelled' ? 'is-active' : '' }}" {{ request('status')==='cancelled' ? 'aria-current=page' : '' }}>Cancelled</a>
    </nav>

    <form class="admin-list-search" method="GET" role="search">
      @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
      <div class="admin-list-search-field">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.6"/><path d="m16 16 4.2 4.2"/></svg>
        <input type="search" name="search" value="{{ request('search') }}" placeholder="Search by order #, buyer or store..." aria-label="Search by order number, buyer or store">
        <button type="submit">Search</button>
      </div>
      @if(request('search') || request('status'))<a class="admin-list-clear" href="{{ url('/admin/orders') }}">Clear filters</a>@endif
    </form>
  </section>

  <section class="admin-list-panel" aria-label="Order records">
    <div class="admin-list-table-wrap">
      <table class="admin-list-table order-table" role="table" aria-label="Manage orders">
        <colgroup><col class="order-col"><col class="customer-col"><col class="store-col"><col class="total-col"><col class="payment-col"><col class="status-col"><col class="date-col"><col class="actions-col"></colgroup>
        <thead role="rowgroup">
          <tr role="row">
            <th scope="col" role="columnheader">Order</th>
            <th scope="col" role="columnheader">Customer</th>
            <th scope="col" role="columnheader">Store</th>
            <th scope="col" role="columnheader">Total</th>
            <th scope="col" role="columnheader">Payment</th>
            <th scope="col" role="columnheader">Status</th>
            <th scope="col" role="columnheader">Date</th>
            <th scope="col" role="columnheader">Actions</th>
          </tr>
        </thead>
        <tbody role="rowgroup">
          @forelse($orders as $order)
            <tr class="list-record order-record" role="row">
              <td class="order-column" data-label="Order" role="cell">
                <div class="order-number" title="{{ $order->order_number }}">#{{ $order->order_number }}</div>
                <span class="order-items-badge">
                  <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M3 3h14v3H3zM3.5 8h13v9h-13z"/><path d="M6 3v3m8-3v3M7 11h6"/></svg>
                  {{ $order->items_count }} item{{ $order->items_count>1?'s':'' }}
                </span>
              </td>
              <td class="customer-column" data-label="Customer" role="cell">
                <div class="order-party">
                  <span class="party-avatar" aria-hidden="true">{{ strtoupper(mb_substr($order->buyer->name ?? 'B',0,1)) }}</span>
                  <span class="party-copy">
                    <span class="party-name">{{ $order->buyer->name ?? 'N/A' }}</span>
                    <span class="party-email" title="{{ $order->buyer->email ?? '' }}">{{ $order->buyer->email ?? '' }}</span>
                  </span>
                </div>
              </td>
              <td class="store-column" data-label="Store" role="cell">
                @php
                  $orderedStores = $order->items->map(function ($item) {
                    $store = $item->seller?->seller ?? $item->product?->seller;
                    $name = $store?->store_name ?: $store?->business_name;
                    if (!filled($name)) return null;
                    return [
                      'id' => $store->getKey(),
                      'name' => trim($name),
                      'email' => $item->seller?->email ?: ($store->user?->email ?? ''),
                    ];
                  })->filter()->unique('id')->values();
                  if ($orderedStores->isEmpty() && $order->seller) {
                    $legacyStore = $order->seller;
                    $legacyStoreName = $legacyStore->store_name ?: $legacyStore->business_name;
                    if (filled($legacyStoreName)) {
                      $orderedStores = collect([[
                        'id' => $legacyStore->getKey(),
                        'name' => trim($legacyStoreName),
                        'email' => $legacyStore->user?->email ?? '',
                      ]]);
                    }
                  }
                  $storeNames = $orderedStores->pluck('name')->implode(', ');
                  $storeEmails = $orderedStores->pluck('email')->filter()->unique()->implode(', ');
                @endphp
                <div class="order-party">
                  @if($storeNames)
                    <span class="party-avatar party-avatar--store" aria-hidden="true">{{ strtoupper(mb_substr($orderedStores->first()['name'],0,1)) }}</span>
                  @endif
                  <span class="party-copy">
                    @if($storeNames)
                      <span class="party-name" title="{{ $storeNames }}">{{ $storeNames }}</span>
                      @if($storeEmails)<span class="party-email" title="{{ $storeEmails }}">{{ $storeEmails }}</span>@endif
                    @else
                      <span class="party-name party-na">N/A</span>
                    @endif
                  </span>
                </div>
              </td>
              <td class="total-column" data-label="Total" role="cell">
                <div class="order-total {{ strlen(number_format($order->total, 2)) > 10 ? 'money-long' : '' }}" title="₱{{ number_format($order->total, 2) }}">₱{{ number_format($order->total, 2) }}</div>
                <div class="order-total-caption">{{ $order->items_count }} × items</div>
              </td>
              <td class="payment-column" data-label="Payment" role="cell">
                @include('admin.partials.order-status-chip', ['status' => $order->payment_status, 'kind' => 'payment'])
              </td>
              <td class="status-column" data-label="Status" role="cell">
                @include('admin.partials.order-status-chip', ['status' => $order->status, 'kind' => 'fulfillment'])
              </td>
              <td class="date-column" data-label="Date" role="cell">
                <div class="order-date">{{ $order->created_at->format('M d, Y') }}</div>
                <div class="order-time">{{ $order->created_at->format('h:i A') }}</div>
              </td>
              <td class="actions-column list-no-label" data-label="Actions" role="cell">
                <a class="admin-list-action view-order" href="{{ url('/admin/orders/'.$order->id) }}" aria-label="View order {{ $order->order_number }}">
                  <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M1.5 10s3-6.5 8.5-6.5 8.5 6.5 8.5 6.5-3 6.5-8.5 6.5S1.5 10 1.5 10Z"/><circle cx="10" cy="10" r="2.6"/></svg>
                  View
                </a>
              </td>
            </tr>
          @empty
            <tr class="empty-order-row" role="row"><td class="order-empty" colspan="8" role="cell"><strong>No orders found</strong><span>Try adjusting your search or status filter.</span></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="admin-list-pagination">
      @include('admin.partials.pager', ['paginator' => $orders])
    </div>
  </section>
</div>
@endsection
