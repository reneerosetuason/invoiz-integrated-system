@extends('admin.layout')

@section('content')
@include('admin.partials.list-ui-styles')
<style>
  .admin-commission{--commission-ink:#163B43;--commission-muted:#5C7073;--commission-rule:rgba(15,76,92,.12);max-width:1280px;margin:0 auto}
  .admin-commission *{box-sizing:border-box}
  .admin-commission .admin-list-head{margin-bottom:22px}
  .admin-commission .commission-stat-value{margin-top:5px;color:var(--list-deep);font-family:'Fraunces',Georgia,serif;font-size:25px;font-weight:560;line-height:1.15;font-variant-numeric:tabular-nums;overflow-wrap:anywhere}
  .commission-section{margin-top:28px}
  .commission-section-head{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;margin-bottom:11px}
  .commission-section h2{margin:0;color:var(--commission-ink);font-family:'Fraunces',Georgia,serif;font-size:21px;font-weight:560;line-height:1.2;letter-spacing:-.02em}
  .commission-section p{margin:5px 0 0;color:var(--commission-muted);font-size:13.5px;line-height:1.5}
  .commission-table-wrap{overflow-x:auto}
  .commission-table{width:100%;border-collapse:collapse;table-layout:fixed}
  .commission-table th{padding:12px 14px;border-bottom:1px solid var(--commission-rule);color:var(--commission-muted);font-size:12px;font-weight:600;letter-spacing:0;text-align:left;text-transform:none;vertical-align:bottom}
  .commission-table td{padding:14px;border-bottom:1px solid var(--commission-rule);color:var(--commission-ink);font-size:13.5px;line-height:1.4;vertical-align:middle;overflow-wrap:anywhere;font-variant-numeric:tabular-nums}
  .commission-table tbody tr:last-child td{border-bottom:0}
  .commission-table .commission-number{text-align:right;white-space:nowrap}
  .commission-table tfoot td{border-top:1px solid var(--commission-rule);border-bottom:0;background:#F6F9F8;font-weight:700}
  .commission-table .list-empty{text-align:center;padding:28px 14px;color:var(--commission-muted)}
  .commission-order-link{color:var(--list-deep);font-weight:700;text-decoration:none}
  .commission-order-link:hover{text-decoration:underline;text-underline-offset:3px}
  .commission-rate-form{display:flex;align-items:center;gap:8px}
  .commission-rate-form input{width:92px;min-height:38px;padding:7px 10px;border:1px solid var(--commission-rule);border-radius:9px;background:#fff;color:var(--commission-ink);font:inherit;text-align:right;font-variant-numeric:tabular-nums}
  .commission-rate-form span{color:var(--commission-muted)}
  .commission-rate-form button{min-height:38px;padding:7px 14px;border:0;border-radius:9px;background:var(--list-deep);color:#fff;font-family:inherit;font-size:12.5px;font-weight:600;cursor:pointer;transition:background-color .15s ease,transform .15s ease}
  .commission-rate-form button:hover{background:var(--list-teal)}
  .commission-rate-form button:active{transform:scale(.98)}
  .commission-pager{padding:0 14px 12px}
  .admin-commission .admin-list-stats{gap:10px;border:0;background:transparent;overflow:visible}
  .admin-commission .admin-list-stat{min-height:112px;border:0;border-radius:14px;background:#fff;padding:15px 17px}
  .admin-commission .admin-list-stat:hover{background:#F6F9F8}
  .admin-commission .admin-list-stat-label{font-size:13px}
  .admin-commission .admin-list-stat small{font-size:12px}
  @media(max-width:900px){.admin-commission .admin-list-stat:nth-child(n){border-right:0!important;border-bottom:0!important}}
  @media(max-width:1099.98px){
    .commission-table{display:block;table-layout:auto}
    .commission-table thead{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);clip-path:inset(50%);white-space:nowrap}
    .commission-table tbody{display:grid;gap:10px;padding:10px}
    .commission-table tr.list-empty-row{display:block;padding:0;border:0;background:transparent}
    .commission-table tr.commission-record{display:grid;align-items:start;gap:0 14px;margin:0;padding:10px 12px;border:1px solid var(--commission-rule);border-radius:12px;background:#fff}
    .commission-table td{display:block;min-width:0;padding:8px 4px;border:0;text-align:left;white-space:normal}
    .commission-table td::before{display:block;margin-bottom:4px;color:var(--commission-muted);content:attr(data-label);font-size:12px;font-weight:600;line-height:1.3}
    .commission-table td.commission-no-label::before{display:none}
    .commission-table tfoot,.commission-table tfoot tr{display:block}
    .commission-table tfoot tr{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:4px 14px;padding:10px 12px}
    .commission-table tfoot td{display:block;padding:7px 4px}
    .commission-table tfoot td::before{display:block;margin-bottom:4px;color:var(--commission-muted);content:attr(data-label);font-size:12px;font-weight:600;line-height:1.3}
    .commission-table tfoot td.commission-no-label{display:none}
    .commission-table.rates-table tr.commission-record{grid-template-columns:minmax(0,1fr) minmax(130px,auto);grid-template-areas:'category category' 'rate action'}
    .rates-table td.rate-category{grid-area:category}
    .rates-table td.rate-value{grid-area:rate}
    .rates-table td.rate-action{grid-area:action;justify-self:end}
    .commission-table.category-table tr.commission-record{grid-template-columns:repeat(2,minmax(0,1fr));grid-template-areas:'category rate' 'orders sales' 'commission commission'}
    .category-table td.category-cell{grid-area:category}
    .category-table td.rate-cell{grid-area:rate}
    .category-table td.orders-cell{grid-area:orders}
    .category-table td.sales-cell{grid-area:sales}
    .category-table td.commission-cell{grid-area:commission;justify-self:end}
    .commission-table.seller-table tr.commission-record{grid-template-columns:repeat(2,minmax(0,1fr));grid-template-areas:'store commission' 'sales absorbed' 'pending paid'}
    .seller-table td.store-cell{grid-area:store}
    .seller-table td.sales-cell{grid-area:sales}
    .seller-table td.commission-cell{grid-area:commission}
    .seller-table td.absorbed-cell{grid-area:absorbed}
    .seller-table td.pending-cell{grid-area:pending}
    .seller-table td.paid-cell{grid-area:paid}
    .commission-table.order-table tr.commission-record{grid-template-columns:repeat(2,minmax(0,1fr));grid-template-areas:'order store' 'buyer date' 'total commission'}
    .order-table td.order-cell{grid-area:order}
    .order-table td.store-cell{grid-area:store}
    .order-table td.buyer-cell{grid-area:buyer}
    .order-table td.date-cell{grid-area:date}
    .order-table td.total-cell{grid-area:total}
    .order-table td.commission-cell{grid-area:commission}
    .commission-table .commission-number{text-align:left;white-space:normal}
  }
  @media(max-width:767.98px){
    .commission-section{margin-top:24px}
    .commission-section h2{font-size:19px}
    .commission-section-head{align-items:flex-start}
    .commission-pager{padding-inline:8px}
    .commission-rate-form{flex-wrap:wrap}
  }
  @media(prefers-reduced-motion:reduce){.admin-commission *{scroll-behavior:auto!important;transition:none!important}}
</style>

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
