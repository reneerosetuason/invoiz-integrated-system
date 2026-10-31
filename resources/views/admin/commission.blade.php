@extends('admin.layout')

@section('content')
<div class="card">
  <h1 class="page-title">Commission & Stakeholder Rates</h1>
  <p style="color:var(--text-secondary); font-size:13.5px; margin:4px 0 0;">Platform earnings, stakeholder distribution rates, and seller payouts — calculated to the centavo.</p>

  @if(session('status'))
  <div style="padding:12px 16px;background:#E8F5EE;color:#166534;border-radius:10px;margin:16px 0;border:1px solid #BFE3D0;font-weight:600;">
    {{ session('status') }}
  </div>
  @endif

  @php
      $escrowRates = \App\Support\EscrowEarnings::rates();
  @endphp

  {{-- Top Stat Cards --}}
  <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin:18px 0 24px;">
    <div style="background:#fff; border:1px solid var(--border); border-left:4px solid #16697A; border-radius:12px; padding:16px; box-shadow:0 1px 3px rgba(0,0,0,.04);">
      <div style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; letter-spacing:.5px;">Total Commission</div>
      <div style="font-size:22px; font-weight:800; color:#16697A; margin-top:6px;">₱{{ number_format($totalCommission, 2) }}</div>
      <div style="font-size:12px; color:#6E6E73; margin-top:4px;">from ₱{{ number_format($totalSales, 2) }} sales · exact</div>
    </div>
    <div style="background:#fff; border:1px solid var(--border); border-left:4px solid #F0A202; border-radius:12px; padding:16px; box-shadow:0 1px 3px rgba(0,0,0,.04);">
      <div style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; letter-spacing:.5px;">Pending Payouts</div>
      <div style="font-size:22px; font-weight:800; color:#92400e; margin-top:6px;">₱{{ number_format($payoutStats['pending'], 2) }}</div>
      <div style="font-size:12px; color:#6E6E73; margin-top:4px;">awaiting release</div>
    </div>
    <div style="background:#fff; border:1px solid var(--border); border-left:4px solid #2E8B57; border-radius:12px; padding:16px; box-shadow:0 1px 3px rgba(0,0,0,.04);">
      <div style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; letter-spacing:.5px;">Paid Payouts</div>
      <div style="font-size:22px; font-weight:800; color:#166534; margin-top:6px;">₱{{ number_format($payoutStats['paid'], 2) }}</div>
      <div style="font-size:12px; color:#6E6E73; margin-top:4px;">released</div>
    </div>
    <div style="background:#fff; border:1px solid var(--border); border-left:4px solid #8B5CF6; border-radius:12px; padding:16px; box-shadow:0 1px 3px rgba(0,0,0,.04);">
      <div style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; letter-spacing:.5px;">Default Platform Rate</div>
      <div style="font-size:22px; font-weight:800; color:#1B1B1E; margin-top:6px;">{{ number_format($defaultRate, 2) }}%</div>
      <div style="font-size:12px; color:#6E6E73; margin-top:4px;">platform commission</div>
    </div>
  </div>

  {{-- Configurable Escrow & Stakeholder Rates --}}
  <div style="background:#F7FAF9; border:1px solid #D2E7E4; border-radius:14px; padding:20px; margin-bottom:28px;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:14px; gap:12px; flex-wrap:wrap;">
      <div>
        <h2 style="font-size:16px; font-weight:800; color:#0E4A57; margin:0;">Configurable Escrow & Stakeholder Earnings Distribution</h2>
        <p style="color:#56696B; font-size:12.5px; margin:4px 0 0;">Fixed rate schedule applied to every shipment. Free-shipping and vouchers never reduce logistics or rider earnings.</p>
      </div>
    </div>

    <form method="POST" action="{{ route('admin.commission.rates.earnings') }}">
      @csrf
      <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:12px; margin-bottom:16px;">
        <div style="background:#fff; border:1px solid #E8E6E0; border-radius:10px; padding:12px;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
            <label style="font-size:12px; font-weight:700; color:#1B1B1E; margin:0;">Origin Logistics Hub</label>
            <span style="font-size:10px; font-weight:700; background:#EAF4F3; color:#0E4A57; padding:2px 6px; border-radius:4px;">Per shipment</span>
          </div>
          <div style="display:flex; align-items:center; gap:4px;">
            <span style="font-weight:700; color:#6E6E73;">₱</span>
            <input type="number" step="0.01" min="0" name="origin_hub_fee" value="{{ number_format($escrowRates['origin_hub_fee'], 2, '.', '') }}" style="padding:6px 10px; border:1px solid #D1D5DB; border-radius:6px; font-size:14px; font-weight:700; width:100%;" required>
          </div>
          <div style="font-size:11px; color:#6E6E73; margin-top:4px;">Intake, QR scan, handling at origin.</div>
        </div>

        <div style="background:#fff; border:1px solid #E8E6E0; border-radius:10px; padding:12px;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
            <label style="font-size:12px; font-weight:700; color:#1B1B1E; margin:0;">Pickup Rider</label>
            <span style="font-size:10px; font-weight:700; background:#FEF3C7; color:#92400E; padding:2px 6px; border-radius:4px;">Per item</span>
          </div>
          <div style="display:flex; align-items:center; gap:4px;">
            <span style="font-weight:700; color:#6E6E73;">₱</span>
            <input type="number" step="0.01" min="0" name="pickup_rider_per_item" value="{{ number_format($escrowRates['pickup_rider_per_item'], 2, '.', '') }}" style="padding:6px 10px; border:1px solid #D1D5DB; border-radius:6px; font-size:14px; font-weight:700; width:100%;" required>
          </div>
          <div style="font-size:11px; color:#6E6E73; margin-top:4px;">Earned per item picked up from seller.</div>
        </div>

        <div style="background:#fff; border:1px solid #E8E6E0; border-radius:10px; padding:12px;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
            <label style="font-size:12px; font-weight:700; color:#1B1B1E; margin:0;">Inter-Hub Driver</label>
            <span style="font-size:10px; font-weight:700; background:#E0F2FE; color:#0369A1; padding:2px 6px; border-radius:4px;">Per transfer</span>
          </div>
          <div style="display:flex; align-items:center; gap:4px;">
            <span style="font-weight:700; color:#6E6E73;">₱</span>
            <input type="number" step="0.01" min="0" name="inter_hub_driver_fee" value="{{ number_format($escrowRates['inter_hub_driver_fee'], 2, '.', '') }}" style="padding:6px 10px; border:1px solid #D1D5DB; border-radius:6px; font-size:14px; font-weight:700; width:100%;" required>
          </div>
          <div style="font-size:11px; color:#6E6E73; margin-top:4px;">Linehaul driver transport fee.</div>
        </div>

        <div style="background:#fff; border:1px solid #E8E6E0; border-radius:10px; padding:12px;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
            <label style="font-size:12px; font-weight:700; color:#1B1B1E; margin:0;">Destination Hub</label>
            <span style="font-size:10px; font-weight:700; background:#EAF4F3; color:#0E4A57; padding:2px 6px; border-radius:4px;">Per transfer</span>
          </div>
          <div style="display:flex; align-items:center; gap:4px;">
            <span style="font-weight:700; color:#6E6E73;">₱</span>
            <input type="number" step="0.01" min="0" name="destination_hub_fee" value="{{ number_format($escrowRates['destination_hub_fee'], 2, '.', '') }}" style="padding:6px 10px; border:1px solid #D1D5DB; border-radius:6px; font-size:14px; font-weight:700; width:100%;" required>
          </div>
          <div style="font-size:11px; color:#6E6E73; margin-top:4px;">Receiving hub sorting and dispatch.</div>
        </div>

        <div style="background:#fff; border:1px solid #E8E6E0; border-radius:10px; padding:12px;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
            <label style="font-size:12px; font-weight:700; color:#1B1B1E; margin:0;">Last-Mile Rider</label>
            <span style="font-size:10px; font-weight:700; background:#EDE9FE; color:#5B21B6; padding:2px 6px; border-radius:4px;">Per delivery</span>
          </div>
          <div style="display:flex; align-items:center; gap:4px;">
            <span style="font-weight:700; color:#6E6E73;">₱</span>
            <input type="number" step="0.01" min="0" name="last_mile_rider_fee" value="{{ number_format($escrowRates['last_mile_rider_fee'], 2, '.', '') }}" style="padding:6px 10px; border:1px solid #D1D5DB; border-radius:6px; font-size:14px; font-weight:700; width:100%;" required>
          </div>
          <div style="font-size:11px; color:#6E6E73; margin-top:4px;">Deducted directly from COD collection.</div>
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="font-weight:700;">Save Escrow Distribution Rates</button>
    </form>
  </div>

  {{-- Commission Rates per Category --}}
  <h2 style="font-size:15px; font-weight:700; margin:0 0 8px;">Platform Commission Rates per Category</h2>
  <p style="color:#6E6E73; font-size:13px; margin:0 0 12px;">Set a percentage per category. Categories without a custom rate use the default.</p>
  <div style="background:#fff; border:1px solid var(--border); border-radius:12px; overflow:hidden; margin-bottom:28px;">
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
              <button type="submit" class="btn btn-primary btn-sm">Save</button>
            </form>
          </td>
          <td style="padding:12px 14px; color:#9CA3AF; font-size:12px;">{{ $r->category ? 'Custom' : 'Fallback' }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  {{-- Commission per Category --}}
  <h2 style="font-size:15px; font-weight:700; margin:0 0 8px;">Commission Summary per Category</h2>
  <div style="background:#fff; border:1px solid var(--border); border-radius:12px; overflow:hidden; margin-bottom:28px;">
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
          <td style="padding:11px 14px; font-size:13px; font-weight:700; text-align:right; color:#16697A;">₱{{ number_format($c->category_commission, 2) }}</td>
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
          <td style="padding:11px 14px; text-align:right; color:#16697A;">₱{{ number_format($categoryCommissions->sum('category_commission'), 2) }}</td>
        </tr>
      </tfoot>
      @endif
    </table>
  </div>
</div>
@endsection
