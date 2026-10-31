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

  @php
      $escrowRates = \App\Support\EscrowEarnings::rates();
  @endphp

  {{-- Configurable Escrow & Earnings Distribution --}}
  <section class="commission-section">
    <div class="commission-section-head">
      <div>
        <h2>Configurable Escrow &amp; Stakeholder Earnings Distribution</h2>
        <p>Fixed rate schedule applied to every seller shipment. Free-shipping and merchandise vouchers never reduce logistics or rider earnings — the last-mile rider deducts pay from COD cash, and the seller absorbs all deductions.</p>
      </div>
    </div>
    <div class="escrow-rates-panel">
      <form method="POST" action="{{ route('admin.commission.rates.earnings') }}">
        @csrf
        <div class="escrow-rates-grid">
          <div class="escrow-rate-card">
            <div>
              <div class="escrow-rate-head">
                <label class="escrow-rate-label">Origin Logistics Hub</label>
                <span class="escrow-rate-badge escrow-badge--shipment">Per shipment</span>
              </div>
              <div class="escrow-rate-input-wrap">
                <span class="escrow-rate-prefix">₱</span>
                <input type="number" step="0.01" min="0" name="origin_hub_fee" value="{{ number_format($escrowRates['origin_hub_fee'], 2, '.', '') }}" class="escrow-rate-field" required>
              </div>
            </div>
            <p class="escrow-rate-hint">Paid for parcel intake, QR scan, handling and dispatching at origin hub.</p>
          </div>

          <div class="escrow-rate-card">
            <div>
              <div class="escrow-rate-head">
                <label class="escrow-rate-label">Pickup Rider</label>
                <span class="escrow-rate-badge escrow-badge--item">Per item</span>
              </div>
              <div class="escrow-rate-input-wrap">
                <span class="escrow-rate-prefix">₱</span>
                <input type="number" step="0.01" min="0" name="pickup_rider_per_item" value="{{ number_format($escrowRates['pickup_rider_per_item'], 2, '.', '') }}" class="escrow-rate-field" required>
              </div>
            </div>
            <p class="escrow-rate-hint">Earned per item picked up from seller (e.g. 2 items = 2 × ₱{{ number_format($escrowRates['pickup_rider_per_item'], 2) }}).</p>
          </div>

          <div class="escrow-rate-card">
            <div>
              <div class="escrow-rate-head">
                <label class="escrow-rate-label">Hub Transport Driver</label>
                <span class="escrow-rate-badge escrow-badge--interhub">Inter-hub only</span>
              </div>
              <div class="escrow-rate-input-wrap">
                <span class="escrow-rate-prefix">₱</span>
                <input type="number" step="0.01" min="0" name="inter_hub_driver_fee" value="{{ number_format($escrowRates['inter_hub_driver_fee'], 2, '.', '') }}" class="escrow-rate-field" required>
              </div>
            </div>
            <p class="escrow-rate-hint">Paid once per seller shipment for the full route, no matter how many transfer legs it takes.</p>
          </div>

          <div class="escrow-rate-card">
            <div>
              <div class="escrow-rate-head">
                <label class="escrow-rate-label">Destination Logistics Hub</label>
                <span class="escrow-rate-badge escrow-badge--interhub">Inter-hub only</span>
              </div>
              <div class="escrow-rate-input-wrap">
                <span class="escrow-rate-prefix">₱</span>
                <input type="number" step="0.01" min="0" name="destination_hub_fee" value="{{ number_format($escrowRates['destination_hub_fee'], 2, '.', '') }}" class="escrow-rate-field" required>
              </div>
            </div>
            <p class="escrow-rate-hint">Intake &amp; sorting at destination hub (₱0 when origin = destination).</p>
          </div>

          <div class="escrow-rate-card">
            <div>
              <div class="escrow-rate-head">
                <label class="escrow-rate-label">Last-Mile Delivery Rider</label>
                <span class="escrow-rate-badge escrow-badge--lastmile">Per shipment</span>
              </div>
              <div class="escrow-rate-input-wrap">
                <span class="escrow-rate-prefix">₱</span>
                <input type="number" step="0.01" min="0" name="last_mile_rider_fee" value="{{ number_format($escrowRates['last_mile_rider_fee'], 2, '.', '') }}" class="escrow-rate-field" required>
              </div>
            </div>
            <p class="escrow-rate-hint">Directly deducted by rider from COD cash; seller absorbs any free shipping.</p>
          </div>

          <div class="escrow-rate-card">
            <div>
              <div class="escrow-rate-head">
                <label class="escrow-rate-label">Seller &amp; Admin</label>
                <span class="escrow-rate-badge escrow-badge--automated">Automated</span>
              </div>
              <div class="escrow-summary-text">
                <strong>Admin:</strong> {{ number_format($defaultRate, 2) }}% commission on net merchandise.<br>
                <strong>Seller:</strong> Net product earnings after all deductions.
              </div>
            </div>
            <div>
              <button type="submit" class="escrow-save-btn">Save Earnings Schedule</button>
            </div>
          </div>
        </div>
      </form>
    </div>
  </section>

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
          @forelse($rates as $r)
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
          @empty
          <tr class="list-empty-row"><td colspan="3" class="list-empty">No category rates configured. Default fallback applies to all products.</td></tr>
          @endforelse
        </tbody>
      </table>
      @if(isset($availableCategories) && $availableCategories->isNotEmpty())
      <div class="category-rate-add-bar">
        <div>
          <strong style="font-size:13px; color:var(--commission-ink);">Add Category Custom Rate</strong>
          <p style="margin:2px 0 0; font-size:12px; color:var(--commission-muted);">Assign an override commission rate for specific product categories.</p>
        </div>
        <form method="POST" action="{{ route('admin.commission.rates.store') }}" class="category-rate-add-form">
          @csrf
          <select name="category_id" required>
            <option value="" disabled selected>Select category...</option>
            @foreach($availableCategories as $cat)
              <option value="{{ $cat->id }}">{{ $cat->name }}</option>
            @endforeach
          </select>
          <div style="display:inline-flex; align-items:center; gap:4px;">
            <input type="number" name="rate" min="0" max="100" step="0.01" placeholder="Rate %" style="width:90px;" required>
            <span style="font-size:13px; font-weight:600; color:var(--commission-muted);">%</span>
          </div>
          <button type="submit">+ Add Rate</button>
        </form>
      </div>
      @endif
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
