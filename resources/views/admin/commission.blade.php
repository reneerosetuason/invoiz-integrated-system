@extends('admin.layout')

@section('content')
@include('admin.partials.list-ui-styles')
<link rel="stylesheet" href="{{ asset('assets/admin/css/commission.css') }}">

<main class="admin-list admin-commission">
  <header class="admin-list-head">
    <h1>Commission</h1>
    <p>Platform earnings and seller payouts — calculated to the centavo.</p>
  </header>

  @if(session('status'))
  <div class="admin-list-flash" role="status">{{ session('status') }}</div>
  @endif

  <section class="admin-list-stats admin-list-stats--4" aria-label="Commission overview">
    <div class="admin-list-stat">
      <span class="admin-list-stat-label">Total Commission</span>
      <strong class="commission-stat-value">₱{{ number_format($totalCommission, 2) }}</strong>
      <small>from ₱{{ number_format($totalSales, 2) }} sales · exact</small>
    </div>
    <div class="admin-list-stat">
      <span class="admin-list-stat-label">Pending Payouts</span>
      <strong class="commission-stat-value">₱{{ number_format($payoutStats['pending'], 2) }}</strong>
      <small>awaiting release</small>
    </div>
    <div class="admin-list-stat">
      <span class="admin-list-stat-label">Paid Payouts</span>
      <strong class="commission-stat-value">₱{{ number_format($payoutStats['paid'], 2) }}</strong>
      <small>released</small>
    </div>
    <div class="admin-list-stat">
      <span class="admin-list-stat-label">Default Rate</span>
      <strong class="commission-stat-value">{{ number_format($defaultRate, 2) }}%</strong>
      <small>fallback</small>
    </div>
  </section>

  <section class="commission-section">
    <div class="commission-section-head">
      <div>
        <h2>Commission Rates per Category</h2>
        <p>Set a percentage per category. Categories without a custom rate use the default.</p>
      </div>
    </div>
    <div class="admin-list-panel commission-table-wrap">
      <table class="commission-table rates-table">
        <thead><tr><th>Category</th><th>Rate</th><th>Actions</th></tr></thead>
        <tbody>
          @foreach($rates as $r)
          <tr class="commission-record">
            <td class="rate-category" data-label="Category">{{ $r->category->name ?? 'Default (all other categories)' }}</td>
            <td class="rate-value" data-label="Rate">
              <form method="POST" action="{{ url('/admin/commission/rates') }}" class="commission-rate-form">
                @csrf
                <input type="hidden" name="rate_id" value="{{ $r->id }}" />
                <input type="number" name="rate" value="{{ number_format($r->rate,2,'.','') }}" min="0" max="100" step="0.01" required />
                <span>%</span>
                <button type="submit">Save</button>
              </form>
            </td>
            <td class="rate-action" data-label="Actions">{{ $r->category ? 'Custom' : 'Fallback' }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>

  <section class="commission-section">
    <div class="commission-section-head"><h2>Commission per Category</h2></div>
    <div class="admin-list-panel commission-table-wrap">
      <table class="commission-table category-table">
        <thead><tr><th>Category</th><th>Rate</th><th>Orders</th><th>Sales</th><th class="commission-number">Commission</th></tr></thead>
        <tbody>
          @forelse($categoryCommissions as $c)
          <tr class="commission-record">
            <td class="category-cell" data-label="Category">{{ $c->name }}</td>
            <td class="rate-cell" data-label="Rate">{{ number_format($c->rate, 2) }}%</td>
            <td class="orders-cell" data-label="Orders">{{ $c->orders_count }}</td>
            <td class="sales-cell" data-label="Sales">₱{{ number_format($c->category_sales, 2) }}</td>
            <td class="commission-cell commission-number" data-label="Commission">₱{{ number_format($c->category_commission, 2) }}</td>
          </tr>
          @empty
          <tr class="list-empty-row"><td colspan="5" class="list-empty">No data.</td></tr>
          @endforelse
        </tbody>
        @if($categoryCommissions->count())
        <tfoot><tr>
          <td data-label="Category">Total</td><td class="commission-no-label"></td>
          <td data-label="Orders">{{ $categoryCommissions->sum('orders_count') }}</td>
          <td data-label="Sales">₱{{ number_format($categoryCommissions->sum('category_sales'), 2) }}</td>
          <td class="commission-number" data-label="Commission">₱{{ number_format($categoryCommissions->sum('category_commission'), 2) }}</td>
        </tr></tfoot>
        @endif
      </table>
    </div>
  </section>

  <section class="commission-section">
    <div class="commission-section-head"><h2>Commission per Seller</h2></div>
    <div class="admin-list-panel commission-table-wrap">
      <table class="commission-table seller-table">
        <thead><tr><th>Store</th><th>Sales</th><th class="commission-number">Commission</th><th class="commission-number">Absorbed</th><th>Pending</th><th>Paid</th></tr></thead>
        <tbody>
          @forelse($sellerCommissions as $sc)
          <tr class="commission-record">
            <td class="store-cell" data-label="Store">{{ $sc->store_name }}</td>
            <td class="sales-cell" data-label="Sales">₱{{ number_format($sc->total_sales, 2) }}</td>
            <td class="commission-cell commission-number" data-label="Commission">₱{{ number_format($sc->total_commission, 2) }}</td>
            <td class="absorbed-cell commission-number" data-label="Absorbed" title="Voucher discounts + waived shipping absorbed by this seller">₱{{ number_format($sc->absorbed ?? 0, 2) }}</td>
            <td class="pending-cell" data-label="Pending">₱{{ number_format($sc->payouts_pending, 2) }}</td>
            <td class="paid-cell" data-label="Paid">₱{{ number_format($sc->payouts_paid, 2) }}</td>
          </tr>
          @empty
          <tr class="list-empty-row"><td colspan="6" class="list-empty">No data.</td></tr>
          @endforelse
        </tbody>
      </table>
      <div class="commission-pager">@include('admin.partials.pager', ['paginator' => $sellerCommissions])</div>
    </div>
  </section>

  <section class="commission-section">
    <div class="commission-section-head"><h2>Commission per Order</h2></div>
    <div class="admin-list-panel commission-table-wrap">
      <table class="commission-table order-table">
        <thead><tr><th>Order #</th><th>Store</th><th>Buyer</th><th class="commission-number">Total</th><th class="commission-number">Commission</th><th>Date</th></tr></thead>
        <tbody>
          @forelse($orderCommissions as $o)
          <tr class="commission-record">
            <td class="order-cell" data-label="Order #"><a class="commission-order-link" href="{{ url('/admin/orders/'.$o->id) }}">{{ $o->order_number }}</a></td>
            <td class="store-cell" data-label="Store">{{ $o->seller->store_name ?? 'N/A' }}</td>
            <td class="buyer-cell" data-label="Buyer">{{ $o->buyer->name ?? 'N/A' }}</td>
            <td class="total-cell commission-number" data-label="Total">₱{{ number_format($o->total, 2) }}</td>
            <td class="commission-cell commission-number" data-label="Commission">₱{{ number_format($o->commission_amount, 2) }}</td>
            <td class="date-cell" data-label="Date">{{ $o->created_at->format('M d, Y') }}</td>
          </tr>
          @empty
          <tr class="list-empty-row"><td colspan="6" class="list-empty">No paid orders.</td></tr>
          @endforelse
        </tbody>
      </table>
      <div class="commission-pager">@include('admin.partials.pager', ['paginator' => $orderCommissions])</div>
    </div>
  </section>
</main>
@endsection
