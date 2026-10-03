@extends('admin.layout')

@section('content')
<div class="card">
  <h1 class="page-title">Commission</h1>
  <p style="color:var(--text-secondary); font-size:13.5px; margin:4px 0 0;">Platform earnings and seller payouts — calculated to the centavo.</p>

  @if(session('status'))
  <div style="padding:12px 16px;background:#E8F5EE;color:#166534;border-radius:8px;margin:16px 0;border:1px solid #BFE3D0;">{{ session('status') }}</div>
  @endif

  <div style="display:grid;gap:12px;grid-template-columns:repeat(4,1fr);margin:18px 0 24px;">
    <div style="background:#fff; border:1px solid var(--border); border-left:3px solid #16697A; border-radius:12px; padding:16px; transition:transform .15s, box-shadow .15s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 20px rgba(16,24,40,.08)'" onmouseout="this.style.transform=''; this.style.boxShadow=''">
      <div style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; letter-spacing:.5px;">Total Commission</div>
      <div style="font-size:20px; font-weight:800; color:#16697A; margin-top:6px;">₱{{ number_format($totalCommission, 2) }}</div>
      <div style="font-size:12px; color:#6E6E73; margin-top:4px;">from ₱{{ number_format($totalSales, 2) }} sales · exact</div>
    </div>
    <div style="background:#fff; border:1px solid var(--border); border-left:3px solid #F0A202; border-radius:12px; padding:16px; transition:transform .15s, box-shadow .15s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 20px rgba(16,24,40,.08)'" onmouseout="this.style.transform=''; this.style.boxShadow=''">
      <div style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; letter-spacing:.5px;">Pending Payouts</div>
      <div style="font-size:20px; font-weight:800; color:#92400e; margin-top:6px;">₱{{ number_format($payoutStats['pending'], 2) }}</div>
      <div style="font-size:12px; color:#6E6E73; margin-top:4px;">awaiting release</div>
    </div>
    <div style="background:#fff; border:1px solid var(--border); border-left:3px solid #2E8B57; border-radius:12px; padding:16px; transition:transform .15s, box-shadow .15s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 20px rgba(16,24,40,.08)'" onmouseout="this.style.transform=''; this.style.boxShadow=''">
      <div style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; letter-spacing:.5px;">Paid Payouts</div>
      <div style="font-size:20px; font-weight:800; color:#166534; margin-top:6px;">₱{{ number_format($payoutStats['paid'], 2) }}</div>
      <div style="font-size:12px; color:#6E6E73; margin-top:4px;">released</div>
    </div>
    <div style="background:#fff; border:1px solid var(--border); border-left:3px solid #8B5CF6; border-radius:12px; padding:16px; transition:transform .15s, box-shadow .15s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 20px rgba(16,24,40,.08)'" onmouseout="this.style.transform=''; this.style.boxShadow=''">
      <div style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; letter-spacing:.5px;">Default Rate</div>
      <div style="font-size:20px; font-weight:800; color:#1B1B1E; margin-top:6px;">{{ number_format($defaultRate, 2) }}%</div>
      <div style="font-size:12px; color:#6E6E73; margin-top:4px;">fallback</div>
    </div>
  </div>

  <h2 style="font-size:15px; font-weight:700; margin:0 0 8px;">Commission Rates per Category</h2>
  <p style="color:#6E6E73; font-size:13px; margin:0 0 12px;">Set a percentage per category. Categories without a custom rate use the default.</p>
  <div style="background:#fff; border:1px solid var(--border); border-radius:12px; overflow:hidden; margin-bottom:24px;">
    <table style="width:100%;border-collapse:collapse;">
      <thead>
        <tr style="background:#F8F9FA; border-bottom:1px solid var(--border); text-align:left;">
          <th style="padding:10px 14px; font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase;">Category</th>
          <th style="padding:10px 14px; font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase;">Rate</th>
          <th style="padding:10px 14px; font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase;">Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach($rates as $r)
        <tr style="border-bottom:1px solid #F1F2F4;">
          <td style="padding:12px 14px; font-weight:600; font-size:13.5px;">{{ $r->category->name ?? 'Default (all other categories)' }}</td>
          <td style="padding:12px 14px;">
            <form method="POST" action="{{ url('/admin/commission/rates') }}" style="display:flex;gap:8px;align-items:center;">
              @csrf
              <input type="hidden" name="rate_id" value="{{ $r->id }}" />
              <input type="number" name="rate" value="{{ number_format($r->rate,2,'.','') }}" min="0" max="100" step="0.01" style="width:80px; padding:7px 10px; border:1px solid #D1D5DB; border-radius:8px; font-size:13px; text-align:center;" required />
              <span style="color:#6E6E73; font-size:13px;">%</span>
              <button type="submit" style="padding:7px 14px;background:#16697A;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:12.5px;font-weight:600;">Save</button>
            </form>
          </td>
          <td style="padding:12px 14px; color:#9CA3AF; font-size:12px;">{{ $r->category ? 'Custom' : 'Fallback' }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <h2 style="font-size:15px; font-weight:700; margin:0 0 8px;">Commission per Category</h2>
  <div style="background:#fff; border:1px solid var(--border); border-radius:12px; overflow:hidden; margin-bottom:24px;">
    <table style="width:100%;border-collapse:collapse;">
      <thead>
        <tr style="background:#F8F9FA; border-bottom:1px solid var(--border); text-align:left;">
          <th style="padding:10px 14px; font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase;">Category</th>
          <th style="padding:10px 14px; font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase;">Rate</th>
          <th style="padding:10px 14px; font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase;">Orders</th>
          <th style="padding:10px 14px; font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase;">Sales</th>
          <th style="padding:10px 14px; font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase; text-align:right;">Commission</th>
        </tr>
      </thead>
      <tbody>
        @forelse($categoryCommissions as $c)
        <tr style="border-bottom:1px solid #F1F2F4;">
          <td style="padding:11px 14px; font-weight:600; font-size:13px;">{{ $c->name }}</td>
          <td style="padding:11px 14px; font-size:13px;">{{ number_format($c->rate, 2) }}%</td>
          <td style="padding:11px 14px; font-size:13px;">{{ $c->orders_count }}</td>
          <td style="padding:11px 14px; font-size:13px;">₱{{ number_format($c->category_sales, 2) }}</td>
          <td style="padding:11px 14px; font-size:13px; font-weight:700; text-align:right;">₱{{ number_format($c->category_commission, 2) }}</td>
        </tr>
        @empty
        <tr><td colspan="5" style="padding:20px;text-align:center;color:#6E6E73; font-size:13px;">No data.</td></tr>
        @endforelse
      </tbody>
      @if($categoryCommissions->count())
      <tfoot>
        <tr style="background:#F8F9FA; border-top:1px solid var(--border); font-weight:700;">
          <td style="padding:11px 14px;">Total</td>
          <td></td>
          <td style="padding:11px 14px;">{{ $categoryCommissions->sum('orders_count') }}</td>
          <td style="padding:11px 14px;">₱{{ number_format($categoryCommissions->sum('category_sales'), 2) }}</td>
          <td style="padding:11px 14px; text-align:right;">₱{{ number_format($categoryCommissions->sum('category_commission'), 2) }}</td>
        </tr>
      </tfoot>
      @endif
    </table>
  </div>

  <h2 style="font-size:15px; font-weight:700; margin:0 0 8px;">Commission per Seller</h2>
  <div style="background:#fff; border:1px solid var(--border); border-radius:12px; overflow:hidden; margin-bottom:24px;">
    <table style="width:100%;border-collapse:collapse;">
      <thead>
        <tr style="background:#F8F9FA; border-bottom:1px solid var(--border); text-align:left;">
          <th style="padding:10px 14px; font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase;">Store</th>
          <th style="padding:10px 14px; font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase;">Sales</th>
          <th style="padding:10px 14px; font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase; text-align:right;">Commission</th>
          <th style="padding:10px 14px; font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase;">Pending</th>
          <th style="padding:10px 14px; font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase;">Paid</th>
        </tr>
      </thead>
      <tbody>
        @forelse($sellerCommissions as $sc)
        <tr style="border-bottom:1px solid #F1F2F4;">
          <td style="padding:11px 14px; font-weight:600; font-size:13px;">{{ $sc->store_name }}</td>
          <td style="padding:11px 14px; font-size:13px;">₱{{ number_format($sc->total_sales, 2) }}</td>
          <td style="padding:11px 14px; font-size:13px; font-weight:700; text-align:right;">₱{{ number_format($sc->total_commission, 2) }}</td>
          <td style="padding:11px 14px; font-size:13px; color:#6E6E73;">₱{{ number_format($sc->payouts_pending, 2) }}</td>
          <td style="padding:11px 14px; font-size:13px; color:#6E6E73;">₱{{ number_format($sc->payouts_paid, 2) }}</td>
        </tr>
        @empty
        <tr><td colspan="5" style="padding:20px;text-align:center;color:#6E6E73; font-size:13px;">No data.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <h2 style="font-size:15px; font-weight:700; margin:0 0 8px;">Commission per Order</h2>
  <div style="background:#fff; border:1px solid var(--border); border-radius:12px; overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;">
      <thead>
        <tr style="background:#F8F9FA; border-bottom:1px solid var(--border); text-align:left;">
          <th style="padding:10px 14px; font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase;">Order #</th>
          <th style="padding:10px 14px; font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase;">Store</th>
          <th style="padding:10px 14px; font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase;">Buyer</th>
          <th style="padding:10px 14px; font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase; text-align:right;">Total</th>
          <th style="padding:10px 14px; font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase; text-align:right;">Commission</th>
          <th style="padding:10px 14px; font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase;">Date</th>
        </tr>
      </thead>
      <tbody>
        @forelse($orderCommissions as $o)
        <tr style="border-bottom:1px solid #F1F2F4;">
          <td style="padding:11px 14px;"><a href="{{ url('/admin/orders/'.$o->id) }}" style="color:#16697A; text-decoration:none; font-weight:600; font-family:monospace; font-size:12.5px;">{{ $o->order_number }}</a></td>
          <td style="padding:11px 14px; font-size:13px;">{{ $o->seller->store_name ?? 'N/A' }}</td>
          <td style="padding:11px 14px; font-size:13px; color:#6E6E73;">{{ $o->buyer->name ?? 'N/A' }}</td>
          <td style="padding:11px 14px; font-size:13px; text-align:right;">₱{{ number_format($o->total, 2) }}</td>
          <td style="padding:11px 14px; font-size:13px; font-weight:700; text-align:right;">₱{{ number_format($o->commission_amount, 2) }}</td>
          <td style="padding:11px 14px; font-size:12.5px; color:#6E6E73;">{{ $o->created_at->format('M d, Y') }}</td>
        </tr>
        @empty
        <tr><td colspan="6" style="padding:20px;text-align:center;color:#6E6E73; font-size:13px;">No paid orders.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
