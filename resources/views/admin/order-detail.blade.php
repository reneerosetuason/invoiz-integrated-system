@extends('admin.layout')

@section('content')
@include('admin.partials.list-ui-styles')
@include('admin.partials.order-ui-styles')

<div class="admin-list admin-order-detail" data-ui="admin-order-detail">
  <a class="order-back" href="{{ url('/admin/orders') }}">
    <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M16 10H4m0 0 5-5m-5 5 5 5"/></svg>
    Back to Orders
  </a>

  <header class="order-header">
    <div class="order-kicker">Order</div>
    <h1>Order {{ $order->order_number }}</h1>
    <div class="order-header-status">
      @include('admin.partials.order-status-chip', ['status' => $order->status, 'kind' => 'fulfillment'])
      @include('admin.partials.order-status-chip', ['status' => $order->payment_status, 'kind' => 'payment'])
      <span class="order-date-meta">{{ $order->created_at->format('M d, Y · h:i A') }}</span>
    </div>
    <div class="order-party-line">
      <span class="order-party">
        <span class="order-party-avatar" aria-hidden="true">{{ strtoupper(mb_substr($order->buyer->name ?? 'B',0,1)) }}</span>
        <span class="order-party-copy"><span class="order-party-label">Buyer:</span> {{ $order->buyer->name ?? 'N/A' }} · <span class="order-party-email">{{ $order->buyer->email ?? '' }}</span></span>
      </span>
      <span class="order-party">
        <span class="order-party-avatar order-party-avatar--store" aria-hidden="true">{{ strtoupper(mb_substr($order->seller->store_name ?? 'S',0,1)) }}</span>
        <span class="order-party-copy"><span class="order-party-label">Store:</span> {{ $order->seller->store_name ?? 'N/A' }}</span>
      </span>
    </div>
  </header>

  @if(session('status'))
    <div class="order-flash" role="status" aria-live="polite">
      <svg viewBox="0 0 20 20" aria-hidden="true"><path d="m3.5 10 4 4 9-9"/></svg>
      <span>{{ session('status') }}</span>
    </div>
  @endif
  @if($errors->any())
    <div class="order-flash order-flash--error" role="alert">
      <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 3 2.5 16h15L10 3Z"/><path d="M10 7v4m0 2.5v.1"/></svg>
      <div>@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
    </div>
  @endif

  <section class="summary-strip" aria-label="Order summary">
    <div class="summary-cell">
      <span class="summary-label">Order Total</span>
      <strong class="summary-total {{ strlen(number_format($order->total,2)) > 12 ? 'money-long' : '' }}">₱{{ number_format($order->total,2) }}</strong>
      <span class="summary-caption">{{ $order->items->count() }} item{{ $order->items->count()>1?'s':'' }}</span>
    </div>
    <div class="summary-cell">
      <span class="summary-label">Payment</span>
      @include('admin.partials.order-status-chip', ['status' => $order->payment_status, 'kind' => 'payment', 'large' => true])
      <span class="summary-caption">{{ $order->payment_status === 'paid' ? 'verified' : 'needs action' }}</span>
    </div>
    <div class="summary-cell">
      <span class="summary-label">Fulfillment</span>
      @include('admin.partials.order-status-chip', ['status' => $order->status, 'kind' => 'fulfillment', 'large' => true])
      <span class="summary-caption">{{ ucfirst(str_replace('_',' ',$order->delivery_status ?? 'pending')) }}</span>
    </div>
  </section>

  <div class="order-detail-layout">
    <aside class="order-update-column" aria-labelledby="update-order-title">
      <h2 id="update-order-title">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-3"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5Z"/></svg>
        Update Order
      </h2>
      <form method="POST" action="{{ url('/admin/orders/'.$order->id.'/update') }}">
        @csrf
        <div class="order-field">
          <label for="orderStatus">Order Status</label>
          <select id="orderStatus" name="status">
            <option value="new" {{ $order->status === 'new' ? 'selected' : '' }}>New</option>
            <option value="processing" {{ $order->status === 'processing' ? 'selected' : '' }}>Processing</option>
            <option value="shipped" {{ $order->status === 'shipped' ? 'selected' : '' }}>Shipped</option>
            <option value="delivered" {{ $order->status === 'delivered' ? 'selected' : '' }}>Delivered</option>
            <option value="cancelled" {{ $order->status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
          </select>
        </div>
        <div class="order-field">
          <label for="deliveryStatus">Delivery Status</label>
          <select id="deliveryStatus" name="delivery_status">
            <option value="pending" {{ ($order->delivery_status ?? 'pending') === 'pending' ? 'selected' : '' }}>Pending</option>
            <option value="assigned" {{ ($order->delivery_status ?? '') === 'assigned' ? 'selected' : '' }}>Assigned</option>
            <option value="in_transit" {{ ($order->delivery_status ?? '') === 'in_transit' ? 'selected' : '' }}>In Transit</option>
            <option value="delivered" {{ ($order->delivery_status ?? '') === 'delivered' ? 'selected' : '' }}>Delivered</option>
          </select>
        </div>
        <button class="update-order-button" type="submit">Update Order</button>
      </form>
      <div class="order-help">
        <div class="order-help-label">Need Help?</div>
        <p>Update the status as the order moves through fulfillment. The buyer and seller will see the new status in their dashboards.</p>
      </div>
    </aside>

    <div class="order-main-column">
      <section class="detail-section progress-section" aria-labelledby="order-progress-title">
        <h2 id="order-progress-title">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v4l3 3M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
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
          <div class="cancelled-progress">This order was cancelled</div>
        @else
          <div class="timeline" aria-label="Order progress">
            @foreach($steps as $key => $label)
              @php
                $idx = array_search($key, $keys);
                $isDone = $currentIdx !== false && $idx < $currentIdx;
                $isActive = $idx === $currentIdx;
              @endphp
              <div class="t-step {{ $isActive ? 'active' : ($isDone ? 'done' : '') }}" @if($isActive) aria-current="step" @endif>
                <div class="t-dot">@if($isDone)<x-ui-icon name="check" :size="13" />@else {{ $idx+1 }} @endif</div>
                <div class="t-label">{{ $label }}</div>
              </div>
              @if(!$loop->last)
                <div class="t-line {{ $isDone || $isActive ? 'done' : '' }}" aria-hidden="true"></div>
              @endif
            @endforeach
          </div>
        @endif
      </section>

      <section class="detail-section info-section" aria-labelledby="order-information-title">
        <h2 id="order-information-title">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M16 13H8M16 17H8M10 9H8"/></svg>
          Order Information
        </h2>

        <dl class="order-info-list">
          <div class="info-row">
            <dt class="info-label"><span class="info-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-4.418 0-8 1.79-8 4v2h16v-2c0-2.21-3.582-4-8-4Z"/></svg></span>Buyer</dt>
            <dd class="info-copy"><div class="info-value">{{ $order->buyer->name ?? 'N/A' }}</div><div class="info-sub">{{ $order->buyer->email ?? '' }} @if($order->buyer->phone) · {{ $order->buyer->phone }} @endif</div></dd>
          </div>
          <div class="info-row">
            <dt class="info-label"><span class="info-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 9h18v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9ZM3 9V7a2 2 0 0 1 2-2h2m9 0h2a2 2 0 0 1 2 2v2"/></svg></span>Seller / Store</dt>
            <dd class="info-copy"><div class="info-value">{{ $order->seller->store_name ?? 'N/A' }}</div><div class="info-sub">{{ $order->seller->user->email ?? '' }}</div></dd>
          </div>
          <div class="info-row">
            <dt class="info-label"><span class="info-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M21 10c0 6-9 12-9 12S3 16 3 10a9 9 0 0 1 18 0Z"/><circle cx="12" cy="10" r="3"/></svg></span>Shipping Address</dt>
            <dd class="info-copy"><div class="info-value">
              @if(is_array($order->shipping_address))
                {{ implode(', ', array_filter($order->shipping_address)) ?: '—' }}
              @else
                {{ $order->shipping_address ?: '—' }}
              @endif
            </div></dd>
          </div>
          <div class="info-row">
            <dt class="info-label"><span class="info-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/></svg></span>Delivery Status</dt>
            <dd class="info-copy"><div class="info-value">{{ ucfirst(str_replace('_',' ', $order->delivery_status ?? 'pending')) }}</div></dd>
          </div>
          @if($order->notes)
            <div class="info-row">
              <dt class="info-label"><span class="info-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/></svg></span>Notes</dt>
              <dd class="info-copy"><div class="info-value">{{ $order->notes }}</div></dd>
            </div>
          @endif
        </dl>
      </section>

      <section class="detail-section items-section" aria-labelledby="ordered-items-title">
        <h2 id="ordered-items-title">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 2h12l1 6H5l1-6ZM5 8h14l-1 12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 8Z"/></svg>
          Ordered Items ({{ $order->items->count() }})
        </h2>
        <div class="item-list">
          @forelse($order->items as $item)
            @php
              $product = $item->product;
              $img = $product ? ($product->images->firstWhere('is_main') ?? $product->images->first()) : null;
              $imgPath = $img ? public_path('storage/'.ltrim($img->path, '/')) : null;
            @endphp
            <article class="item-row">
              <div class="item-thumb">
                @if($img && file_exists($imgPath))
                  <img src="{{ url('storage/'.ltrim($img->path, '/')) }}" alt="{{ $product->name ?? 'Item' }}" />
                @else
                  <span class="item-placeholder" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg></span>
                @endif
              </div>
              <div class="item-copy">
                <div class="item-name" title="{{ $product->name ?? 'Product #'.$item->product_id }}">{{ $product->name ?? 'Product #'.$item->product_id }}</div>
                <div class="item-sku">SKU: {{ $product->sku ?? '—' }}</div>
                @if($item->quantity !== null || $item->unit_price !== null)
                  <div class="item-quantity">
                    @if($item->quantity !== null)Qty: <strong>{{ $item->quantity }}</strong>@endif
                    @if($item->quantity !== null && $item->unit_price !== null)<span class="item-separator" aria-hidden="true">·</span>@endif
                    @if($item->unit_price !== null)₱{{ number_format($item->unit_price, 2) }}@endif
                  </div>
                @endif
              </div>
              <div class="item-total">
              <div class="item-total-amount {{ strlen(number_format($item->total_price, 2)) > 12 ? 'money-long' : '' }}">₱{{ number_format($item->total_price, 2) }}</div>
                <div class="item-total-caption">Total</div>
              </div>
            </article>
          @empty
            <div class="items-empty">No items on this order.</div>
          @endforelse
        </div>
      </section>

      <section class="detail-section financial-section" aria-labelledby="financial-breakdown-title">
        <h2 id="financial-breakdown-title">
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41M4.93 19.07l1.41-1.41m11.32-11.32 1.41-1.41"/></svg>
          Financial Breakdown
        </h2>
        <dl class="financial-list">
          <div class="fin-row"><dt>Subtotal</dt><dd class="{{ strlen(number_format($order->sub_total ?? $order->total, 2)) > 12 ? 'money-long' : '' }}">₱{{ number_format($order->sub_total ?? $order->total, 2) }}</dd></div>
          @if(($order->discount_total ?? 0) > 0)
            <div class="fin-row discount"><dt>Discount</dt><dd class="{{ strlen(number_format($order->discount_total, 2)) > 12 ? 'money-long' : '' }}">-₱{{ number_format($order->discount_total, 2) }}</dd></div>
          @endif
          <div class="fin-row"><dt>Shipping Fee</dt><dd class="{{ strlen(number_format($order->shipping_fee ?? 0, 2)) > 12 ? 'money-long' : '' }}">₱{{ number_format($order->shipping_fee ?? 0, 2) }}</dd></div>
          <div class="fin-row"><dt>Tax</dt><dd class="{{ strlen(number_format($order->tax_total ?? 0, 2)) > 12 ? 'money-long' : '' }}">₱{{ number_format($order->tax_total ?? 0, 2) }}</dd></div>
          @if(($order->commission_amount ?? 0) > 0)
            <div class="fin-row commission"><dt>Platform Commission</dt><dd class="{{ strlen(number_format($order->commission_amount, 2)) > 12 ? 'money-long' : '' }}">₱{{ number_format($order->commission_amount, 2) }}</dd></div>
          @endif
          <div class="fin-row total"><dt>Total</dt><dd class="{{ strlen(number_format($order->total, 2)) > 12 ? 'money-long' : '' }}">₱{{ number_format($order->total, 2) }}</dd></div>
        </dl>
      </section>
    </div>
  </div>
</div>
@endsection
